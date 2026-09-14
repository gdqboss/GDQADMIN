/**
 * OA 图模型流程引擎 · 路由层 (routes/oa-flow.js)
 * 2026-09-08 · AutoClaw · 依据 OA引擎升级_交接_AutoClaw_引擎后端_20260908.md
 *
 * 挂载: index.js → app.use('/api/oa/flow', auth, apiLimiter, oaFlowRoutes)
 *
 * 引擎语义（移植自前端 oa-engine.js + 交接文档 §5）：
 *   token 从 start 出发逐节点走查：
 *     start      → 继续（记发起）
 *     approve    → 按角色解析任务（可多人并行），停下等待
 *     gate       → 按 gateType 即时求值分支
 *                  amount/duration/budget: cond 比较路由
 *                  qualification: field==expect 走 pass(第一个分支)
 *                  quota: form_data[checkField] > max 拦截(400 GATE_BLOCKED)
 *                  countersign: 并行 approve 任务, 全过汇聚/一驳整单拒
 *                  vote: 并行 vote 任务, 达阈值 pass 分支/未达 fail 分支(无 fail 整单拒)
 *                  timeout: 依据前序节点 elapsed 判分支(cron 兜底自动路由)
 *     cc         → 只写日志 cc_pass, 不建任务
 *     end        → completed, 执行 end.actions(仅记日志)
 *   老病修复: 任务只在 token 到达时激活; current_node 实时更新; 链长不限
 *
 * 打回/重提: return → status=returned + return_info + 取消其余 pending 任务
 *            resubmit → restart(重走首个审批节点, 网关重新求值) / resume(重激活打回节点)
 * 多租户: definitions/instances 按 tenant_id 隔离(默认 1); 用户-租户映射另立任务
 * 老模块冻结: routes/oa.js 的 approvals/approval_steps/leave/overtime/老 workflow 一律不动
 */

import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requireRole } from '../middleware/rbac.js'
import { getBalance, consumeLeave, refundLeave, addCompByMinutes, checkQuota } from './balance-service.js'
import { checkPerm } from '../utils/permission.js' // 2026-09-14 余额接口权限

const router = Router()

// ─────────────────────────────────────────────────────────────
// 工具
// ─────────────────────────────────────────────────────────────

function safeParse(str, def = null) {
  if (str === null || str === undefined) return def
  if (typeof str === 'object') return str
  try { return JSON.parse(str) } catch { return def }
}

function now() {
  // 'YYYY-MM-DD HH:MM:SS' 本地时间（与库内其他表 timestamp 习惯一致）
  return new Date().toLocaleString('sv-SE', { timeZone: 'Asia/Shanghai' }).replace('T', ' ')
}

/** 引擎错误：带 http 状态与业务码 */
class FlowError extends Error {
  constructor(status, code, message) {
    super(message)
    this.status = status
    this.code = code
  }
}

const JOB_TENANT = () => 1 // 用户-租户映射另立任务; 当前全部默认租户 1

// ─────────────────────────────────────────────────────────────
// 条件求值（交接文档 §5.2）
//   cond: '<=5000' '>3' '==x' '!=x' 'else' 'balance_insufficient'(由 quota 网关特判)
// ─────────────────────────────────────────────────────────────

function evalCond(cond, value) {
  if (cond === 'else') return true
  const m = String(cond).match(/^\s*(<=|>=|<|>|==|!=)\s*(.+?)\s*$/)
  if (!m) return false
  const [, op, rawRhs] = m
  // 数值优先比较
  const numL = parseFloat(value)
  const numR = parseFloat(rawRhs)
  if (!isNaN(numL) && !isNaN(numR) && String(value).trim() !== '') {
    switch (op) {
      case '<=': return numL <= numR
      case '>=': return numL >= numR
      case '<': return numL < numR
      case '>': return numL > numR
      case '==': return numL === numR
      case '!=': return numL !== numR
    }
  }
  // 字符串比较
  const sl = String(value ?? '')
  switch (op) {
    case '==': return sl === rawRhs
    case '!=': return sl !== rawRhs
    case '>': return sl > rawRhs
    case '<': return sl < rawRhs
    case '>=': return sl >= rawRhs
    case '<=': return sl <= rawRhs
  }
  return false
}

/** 解析投票阈值：'majority'(过半,缺省) | 'unanimous'(全票通过) | 'veto'(一票否决,通过线=全票) | '2/3'(分数) | 数字(绝对票数)
 *  [vote-veto] 2026-09-14 R4：原来只认 majority / 分数 / 数字，前端给的 unanimous、veto 会 NaN → 兜底成"多数通过"，
 *    即管理员选了"全票通过/一票否决"实际按"多数通过"跑（配了不生效）。 */
function parseThreshold(threshold, total) {
  const t = (threshold === undefined || threshold === null) ? 'majority' : String(threshold).trim().toLowerCase()
  if (t === 'majority') return Math.floor(total / 2) + 1
  if (t === 'unanimous' || t === 'veto') return total
  const frac = t.match(/^(\d+)\/(\d+)$/)
  if (frac) return Math.ceil((total * Number(frac[1])) / Number(frac[2]))
  const n = Number(t)
  return isNaN(n) ? Math.floor(total / 2) + 1 : n
}

/**
 * [form-validate] 2026-09-14 R4：按 definition 的 form_config 做服务端校验（发起 / 重提都走）。
 *   为什么需要：以前只靠前端拦，**绕过页面直接调接口**就能提交残缺申请，审批人收到空字段还要来回问。
 *   宽容原则：① 只校验定义里**声明过**的字段，**不拒绝额外字段**（流程会插入 refInstanceId 等系统字段）；
 *            ② 类型只做"明显错误"的判定，不做格式军规（避免把正常提交卡死）。
 * @returns {string|null} null=通过；否则返回给用户看的中文原因
 */
function validateFormData(formConfig, formData) {
  let fields = formConfig
  if (!fields) return null
  if (typeof fields === 'string') fields = safeParse(fields, null)
  if (fields && !Array.isArray(fields) && Array.isArray(fields.fields)) fields = fields.fields
  if (!Array.isArray(fields) || !fields.length) return null

  const fd = (formData && typeof formData === 'object' && !Array.isArray(formData)) ? formData : {}
  const isEmpty = (v) => v === undefined || v === null || v === ''
    || (Array.isArray(v) && v.length === 0)

  for (const f of fields) {
    if (!f || !f.key) continue
    const label = f.label || f.key
    const v = fd[f.key]
    if (f.required && isEmpty(v)) return `请填写「${label}」`
    if (isEmpty(v)) continue
    const t = String(f.type || '').toLowerCase()
    if (t === 'number' || t === 'money') {
      const num = (typeof v === 'number') ? v : Number(String(v).trim())
      if (!Number.isFinite(num)) return `「${label}」必须是数字`
      if (t === 'money' && num < 0) return `「${label}」不能为负数`
    } else if (t === 'select') {
      if (Array.isArray(f.options) && f.options.length && !f.options.includes(v)) {
        return `「${label}」的取值不在可选范围内`
      }
    } else if (t === 'text' || t === 'textarea' || t === 'string') {
      const cap = (t === 'textarea') ? 20000 : 1000
      if (String(v).length > cap) return `「${label}」内容过长（上限 ${cap} 字）`
    } else if (t === 'multi') {
      const arr = Array.isArray(v) ? v : String(v).split(/[,，]/).map(x => x.trim()).filter(Boolean)
      if (Array.isArray(f.options) && f.options.length) {
        const bad = arr.find(x => !f.options.includes(x))
        if (bad) return `「${label}」含不在可选范围内的值：${bad}`
      }
    }
  }
  return null
}

// ─────────────────────────────────────────────────────────────
// 角色解析（交接文档 §5.9）
//   applicant | direct_supervisor | user:123 | dept_head | 角色名
//   返回 user id 数组（去重、只含 active）
//   解析不到 → 空数组（调用方置空 assignee + warn 日志，不阻断）
// ─────────────────────────────────────────────────────────────

async function resolveRole(conn, role, initiatorId, { firstOnly = false } = {}) {
  const ids = []
  if (!role) return ids

  if (role === 'applicant') {
    ids.push(initiatorId)
  } else if (role === 'direct_supervisor') {
    const [[u]] = await conn.query('SELECT supervisor_id FROM users WHERE id = ?', [initiatorId])
    if (u && u.supervisor_id) {
      // FK 防护：supervisor_id 可能指向不存在/已删用户（跨库迁移数据常见）→ 校验存在且 active
      const [[sv]] = await conn.query("SELECT id FROM users WHERE id = ? AND status = 'active'", [u.supervisor_id])
      if (sv) ids.push(sv.id)
    }
  } else if (role === 'dept_head') {
    // 实测(2026-09-08): SGP departments.manager_id 大多为空(12/13) → 降级 direct_supervisor（交接文档 §5.9 预案）
    // 降级前先查发起人所在部门的 manager；若 manager 是发起人本人则视为“自己审批自己”，跳过并降级
    const [[u]] = await conn.query('SELECT department_id FROM users WHERE id = ?', [initiatorId])
    if (u && u.department_id) {
      const [[d]] = await conn.query('SELECT manager_id FROM departments WHERE id = ?', [u.department_id])
      if (d && d.manager_id && d.manager_id !== initiatorId) ids.push(d.manager_id)
    }
    if (!ids.length) return resolveRole(conn, 'direct_supervisor', initiatorId, { firstOnly })
  } else if (String(role).startsWith('user:')) {
    ids.push(Number(String(role).slice(5)))
  } else {
    // 角色名: ① users.role 精确匹配（英文角色）
    const lim = firstOnly ? 'LIMIT 1' : ''
    const [rows] = await conn.query(
      'SELECT id FROM users WHERE role = ? AND status = \'active\' ORDER BY id ' + lim, [role])
    rows.forEach(r => ids.push(r.id))
    // ② 中文部门名（如 运营部/财务部）→ users.department 或 departments.name 匹配其成员/负责人
    if (!ids.length) {
      const [deptUsers] = await conn.query(
        `SELECT u.id FROM users u LEFT JOIN departments d ON d.id = u.department_id
         WHERE (u.department = ? OR d.name = ?) AND u.status = 'active' ORDER BY u.id ` + lim, [role, role])
      deptUsers.forEach(r => ids.push(r.id))
    }
    // ③ 部门名别名（如「总经办」→「总经理办公室」）：常见别名映射 + 去除通用后缀后的包含匹配
    if (!ids.length) {
      const ROLE_ALIAS = { '总经办': '总经理办公室', '总经理': '总经理办公室', 'HR': '人事部', 'hr': '人事部', '财务': '财务部', '法务': '法务部', '运营': '运营部', 'IT': 'IT部', '招商': '招商部' }
      const aliased = ROLE_ALIAS[role]
      if (aliased) {
        const [aliasUsers] = await conn.query(
          `SELECT u.id FROM users u LEFT JOIN departments d ON d.id = u.department_id
           WHERE d.name = ? AND u.status = 'active' ORDER BY u.id ` + lim, [aliased])
        aliasUsers.forEach(r => ids.push(r.id))
      }
    }
    if (!ids.length) {
      // 兜底：字符串包含匹配（双向）
      const [fuzzyUsers] = await conn.query(
        `SELECT u.id FROM users u LEFT JOIN departments d ON d.id = u.department_id
         WHERE (d.name LIKE CONCAT('%', ?, '%') OR ? LIKE CONCAT('%', d.name, '%'))
           AND u.status = 'active' ORDER BY u.id ` + lim, [role, role])
      fuzzyUsers.forEach(r => ids.push(r.id))
    }
  }
  return [...new Set(ids.filter(x => Number.isFinite(x)))]
}

// ─────────────────────────────────────────────────────────────
// 日志
// ─────────────────────────────────────────────────────────────

async function log(conn, instanceId, { taskId = null, nodeId = null, action, operatorId = null, message = null, details = null }) {
  await conn.query(
    'INSERT INTO workflow_logs (instance_id, task_id, node_id, action, operator_id, message, details) VALUES (?,?,?,?,?,?,?)',
    [instanceId, taskId, nodeId, action, operatorId, message, details ? JSON.stringify(details) : null]
  )
}

// ─────────────────────────────────────────────────────────────
// 引擎核心：token 走查
//   在事务连接 conn 上执行。stopReason: 'wait' | 'completed' | null
//   上下文 ctx: { instanceId, formData, initiatorId, flow, tenantId, snapshot(冻结json) }
// ─────────────────────────────────────────────────────────────

function nodeById(flow, id) { return (flow.nodes || []).find(n => n.id === id) || null }
function edgesFrom(flow, id) { return (flow.edges || []).filter(e => e.from === id) }
function firstEdgeTo(flow, nodeId) { return (flow.edges || []).find(e => e.to === nodeId) || null }

async function runToken(conn, ctx, startNodeId) {
  const { flow, formData, initiatorId, instanceId } = ctx
  let nodeId = startNodeId
  let guard = 0
  const MAX_STEPS = 100 // 环保护

  while (nodeId && guard++ < MAX_STEPS) {
    const node = nodeById(flow, nodeId)
    if (!node) {
      // 悬空节点 → 视为终止（防御）
      await log(conn, instanceId, { nodeId, action: 'warn', message: `节点 ${nodeId} 未定义，流程强制归档` })
      await conn.query('UPDATE workflow_instances SET status=?, current_node=?, completed_at=? WHERE id=?',
        ['completed', nodeId, now(), instanceId])
      return { stop: 'completed' }
    }

    // ── start：直接走唯一出边 ──
    if (node.type === 'start') {
      const e = edgesFrom(flow, nodeId)[0]
      await log(conn, instanceId, { nodeId, action: 'start', operatorId: initiatorId, message: `${node.name || '发起'} 提交申请` })
      nodeId = e ? e.to : null
      continue
    }

    // ── cc：只记日志 ──
    if (node.type === 'cc') {
      await log(conn, instanceId, { nodeId, action: 'cc_pass', message: `抄送：${node.name || node.role || nodeId}` })
      const e = edgesFrom(flow, nodeId)[0]
      nodeId = e ? e.to : null
      continue
    }

    // ── end：完成 ──
    if (node.type === 'end') {
      const actions = node.actions || []
      if (actions.length) {
        // ── 2026-09-14 真实假期余额挂扣（前置在标记为「已归档但未扣」之前；补卡/剩余天数 clamp 见 balance-service）──
        try {
          const fd = ctx.formData || {}
          const gqGate = (flow.nodes || []).find(n => n.type === 'gate' && n.gateType === 'quota')
          const gq = (gqGate && gqGate.gateConfig) || {}
          const typeName = fd[gq.field]; // 假种中文（年假/调休/事假/补卡）
          const daysRaw = parseFloat(fd[gq.checkField ?? gq.field]);

          // deduct_quota：请假审批通过 → 扣对应假种
          if (actions.includes('deduct_quota')) {
            await consumeLeave(conn, { userId: ctx.initiatorId, typeName, days: daysRaw, companyId: ctx.companyId ?? null, refId: instanceId, refDesc: 'OA请假审批通过扣减' });
          }
          // refund_quota：销假/作废 → 退回（假种/退回天数由表单 leaveType/refundDays 提供）
          if (actions.includes('refund_quota')) {
            const rt = fd.leaveType;
            const rd = parseFloat(fd.refundDays);
            await refundLeave(conn, { userId: ctx.initiatorId, typeName: rt, days: rd, companyId: ctx.companyId ?? null, refId: instanceId, refDesc: 'OA销假退回' });
          }
          // add_comp：加班转调休（items[] 中 comp==='调休累计' 的 minutes 加总；兼容单条 comp/minutes）
          if (actions.includes('add_comp')) {
            let totalMin = 0;
            if (Array.isArray(fd.items)) {
              fd.items.forEach(it => { if (it.comp === '调休累计') { const m = parseFloat(it.minutes); if (!isNaN(m) && m > 0) totalMin += m; } });
            } else if (fd.comp === '调休累计') {
              const m = parseFloat(fd.minutes); if (!isNaN(m) && m > 0) totalMin = m;
            }
            if (totalMin > 0) await addCompByMinutes(conn, { userId: ctx.initiatorId, overtimeMinutes: totalMin, companyId: ctx.companyId ?? null, refId: instanceId, refDesc: 'OA加班转调休' });
          }
        } catch (e) {
          await log(conn, instanceId, { nodeId, action: 'warn', message: '余额挂扣失败: ' + e.message });
        }
        await log(conn, instanceId, { nodeId, action: 'end', message: `归档动作: ${actions.join(',')}` , details: { actions } })
      } else {
        await log(conn, instanceId, { nodeId, action: 'end', message: '归档' })
      }
      await conn.query('UPDATE workflow_instances SET status=?, current_node=?, completed_at=? WHERE id=?',
        ['completed', nodeId, now(), instanceId])
      return { stop: 'completed' }
    }

    // ── gate：网关求值 ──
    if (node.type === 'gate') {
      const r = await evalGate(conn, ctx, node)
      if (r.blocked) return { stop: 'blocked', message: r.message } // quota 拦截（由调用方回滚并返回 400）
      if (r.waitGate) {
        // countersign/vote：并行任务已建，挂起等审批
        await conn.query('UPDATE workflow_instances SET current_node=? WHERE id=?', [node.id, instanceId])
        return { stop: 'wait' }
      }
      nodeId = r.nextNodeId
      continue
    }

    // ── approve / vote：激活任务并等待 ──
    if (node.type === 'approve' || node.type === 'vote') {
      return await activateNode(conn, ctx, node)
    }

    // 未知类型 → 当作透传（防御，记 warn）
    await log(conn, instanceId, { nodeId, action: 'warn', message: `未知节点类型 ${node.type}，透传` })
    const e0 = edgesFrom(flow, nodeId)[0]
    nodeId = e0 ? e0.to : null
  }

  if (guard >= MAX_STEPS) {
    await log(conn, instanceId, { action: 'warn', message: '走查步数超限（疑似环），强制归档' })
    await conn.query('UPDATE workflow_instances SET status=?, completed_at=? WHERE id=?', ['completed', now(), instanceId])
    return { stop: 'completed' }
  }
  // 无出边自然结束
  await conn.query('UPDATE workflow_instances SET status=?, completed_at=? WHERE id=?', ['completed', now(), instanceId])
  return { stop: 'completed' }
}

// ─────────────────────────────────────────────────────────────
// 网关求值（§5.2/5.3/5.4/5.5/5.6）
// ─────────────────────────────────────────────────────────────

async function evalGate(conn, ctx, node) {
  const { flow, formData, initiatorId, instanceId } = ctx
  const gt = node.gateType
  const gc = node.gateConfig || {}
  const branches = gc.branches || []
  const branchIds = (b) => b && (b.nextNodeIds || (b.nextNodeId ? [b.nextNodeId] : null)) || null

  // ── quota 额度拦截（静态 max；真实余额服务另立任务）──
  if (gt === 'quota') {
    const checkVal = parseFloat(formData[gc.checkField ?? gc.field])
    const maxMap = gc.max // 支持 {事假:30,...} 按类型 或单值 max
    const typeVal = formData[gc.field]
    let max = null
    if (maxMap && typeof maxMap === 'object') max = maxMap[typeVal] ?? null
    else if (maxMap !== undefined) max = Number(maxMap)

    // 决策分支：
    //  A) branches 里有 balance_insufficient 分支（前端语义）：超限 → 若该分支 action=reject 且 message → 400 拦截
    //  B) 无 branches（纯 max 配置）：超限 → 400 GATE_BLOCKED(用 gc.message)
    //  C) 2026-09-14 真实余额优先：gc.realBalance 默认开，假种命中余额账户 → 以 checkQuota 为准（不再只信静态 max）
    const insufficientBranch = branches.find(b => b.cond === 'balance_insufficient')
    let overLimit = max !== null && !isNaN(checkVal) && checkVal > max
    let quotaMsg = gc.message || '超出额度上限，无法提交'
    if (gc.realBalance !== false) {
      const q = await checkQuota(conn, { userId: ctx.initiatorId, typeName: typeVal, days: checkVal })
      if (!q.ok && q.blocked) { overLimit = true; quotaMsg = q.message }
    }
    if (overLimit) {
      if (insufficientBranch) {
        if (insufficientBranch.action === 'reject') {
          await log(conn, instanceId, { nodeId: node.id, action: 'gate_block', message: insufficientBranch.message || quotaMsg, details: { field: typeVal, used: checkVal, quotaMsg } })
          return { blocked: true, message: insufficientBranch.message || quotaMsg }
        }
        // 未来可扩展 action:'route' → insufficientBranch.nextNodeId
      }
      await log(conn, instanceId, { nodeId: node.id, action: 'gate_block', message: quotaMsg, details: { field: typeVal, used: checkVal, quotaMsg } })
      return { blocked: true, message: quotaMsg }
    }
    const elseB = branches.find(b => b.cond === 'else')
    const ids = branchIds(elseB)
    const next = ids ? ids[0] : (edgesFrom(flow, node.id)[0]?.to ?? null)
    await log(conn, instanceId, { nodeId: node.id, action: 'gate_pass', message: '额度校验通过', details: { used: checkVal, max } })
    return { nextNodeId: next }
  }

  // ── amount / duration / budget 条件路由 ──
  if (gt === 'amount' || gt === 'duration' || gt === 'budget') {
    const val = formData[gc.field]
    for (const b of branches) {
      if (evalCond(b.cond, val)) {
        const ids = branchIds(b)
        const next = ids ? ids[0] : null // duration 网关多分支(nextNodeIds 多值)在 §5.6 前不支持并行多链——先取首个, 后续日志记录
        await log(conn, instanceId, {
          nodeId: node.id, action: 'gate_route',
          message: `${node.name}：${gc.field}=${val} 命中 [${b.cond}] → ${ids ? ids.join(',') : '无出边'}`,
          details: { field: gc.field, value: val, cond: b.cond, nextNodeIds: ids }
        })
        if (!next) throw new FlowError(500, 'FLOW_CONFIG', `网关 ${node.id} 分支 [${b.cond}] 无目标节点`)
        return { nextNodeId: next }
      }
    }
    // 无命中 → 整单 rejected（§5.2）
    await log(conn, instanceId, { nodeId: node.id, action: 'gate_reject', message: `${node.name}：无命中分支，整单拒绝`, details: { field: gc.field, value: val } })
    await conn.query('UPDATE workflow_instances SET status=?, current_node=? WHERE id=?', ['rejected', node.id, instanceId])
    throw new FlowError(400, 'GATE_NO_BRANCH', `条件未命中任何分支，流程被拒绝`)
  }

  // ── qualification 资格网关：field==expect → pass(第一分支)；否则 fail 分支或阻断 ──
  if (gt === 'qualification') {
    const val = formData[gc.field]
    const pass = String(val) === String(gc.expect)
    if (pass) {
      const b0 = branches[0]
      const ids = branchIds(b0)
      const next = ids ? ids[0] : (edgesFrom(flow, node.id)[0]?.to ?? null)
      await log(conn, instanceId, { nodeId: node.id, action: 'gate_pass', message: `资格通过（${gc.field}=${val}）` })
      return { nextNodeId: next }
    }
    const failB = branches.find(b => b.cond === 'fail' || b.cond === 'else')
    if (failB) {
      const ids = branchIds(failB)
      await log(conn, instanceId, { nodeId: node.id, action: 'gate_route', message: `资格不符走 fail 分支`, details: { field: gc.field, value: val, expect: gc.expect } })
      return { nextNodeId: ids ? ids[0] : null }
    }
    await log(conn, instanceId, { nodeId: node.id, action: 'gate_reject', message: `资格不符且无 fail 分支，整单拒绝`, details: { field: gc.field, value: val, expect: gc.expect } })
    await conn.query('UPDATE workflow_instances SET status=?, current_node=? WHERE id=?', ['rejected', node.id, instanceId])
    throw new FlowError(400, 'GATE_NO_BRANCH', '资格校验未通过')
  }

  // ── countersign 会签网关（到达网关前, 并行任务已在**前置审批节点**模型中处理的情况除外）
  // 交接文档 §5.4: participants → 各建并行 approve 任务。实现: 把会签任务挂在**网关自身**,
  // node_type='approve' 语义复用（node_id=网关id, node_name=participant name）, 全过 → 走唯一出边
  if (gt === 'countersign') {
    const participants = gc.participants || gc.parallelNodes || []
    if (!participants.length) throw new FlowError(500, 'FLOW_CONFIG', `会签网关 ${node.id} 未配置 participants`)
    // [countersign-joinType] 2026-09-14 R4 会签：joinType='all'（默认，全部同意才放行）
    //   / 'any'（任一同意即放行，其余待办作废）。缺省与非法值一律按 'all'，不改变既有流程行为。
    const joinType = String(gc.joinType || 'all').toLowerCase() === 'any' ? 'any' : 'all'
    let allDone = true, anyRejected = false, anyApproved = false
    const rows = await currentGateTasks(conn, instanceId, node.id)
    for (const p of participants) {
      const existing = rows.find(r => r.node_name === p.name)
      if (!existing) {
        // 首次进入：解析参与者并建并行任务
        const pids = await resolveRole(conn, p.role, initiatorId)
        if (!pids.length) {
          await log(conn, instanceId, { nodeId: node.id, action: 'warn', message: `会签参与者 ${p.name}(${p.role}) 解析不到人，任务置空待后台指派` })
        }
        for (const uid of (pids.length ? pids : [null])) {
          await conn.query(
            `INSERT INTO workflow_tasks (instance_id, node_id, node_name, node_type, assignee_id, assignee_type, status, enter_at)
             VALUES (?,?,?,?,?,?, 'pending', ?)`,
            [instanceId, node.id, p.name, 'approve', uid, p.role, now()])
        }
        allDone = false
      } else if (existing.status === 'pending') {
        allDone = false
      } else if (existing.status === 'completed') {
        if (existing.action === 'reject') anyRejected = true
        else anyApproved = true
      }
    }

    // ── 或签（joinType='any'）：任一同意即放行，其余待办作废；全部驳回才整单拒 ──
    if (joinType === 'any') {
      if (anyApproved) {
        await conn.query(
          `UPDATE workflow_tasks SET status='cancelled', completed_at=?
            WHERE instance_id=? AND node_id=? AND status='pending'`,
          [now(), instanceId, node.id])
        await log(conn, instanceId, { nodeId: node.id, action: 'gate_pass', message: '会签「任一同意」已满足，其余待办作废，汇聚继续' })
        return { nextNodeId: edgesFrom(flow, node.id)[0]?.to ?? null }
      }
      if (!allDone) return { waitGate: true }
      // [countersign-cancelrst] 2026-09-14 R4：结论已定（整单拒）→ 其余待办作废，
      //   避免其他人还在"待我审批"里看到一张已经作废的单
      await conn.query(
        `UPDATE workflow_tasks SET status='cancelled', completed_at=?
          WHERE instance_id=? AND node_id=? AND status='pending'`,
        [now(), instanceId, node.id])
      await log(conn, instanceId, { nodeId: node.id, action: 'gate_reject', message: '会签「任一同意」未获任何同意，整单拒绝' })
      await conn.query('UPDATE workflow_instances SET status=?, current_node=? WHERE id=?', ['rejected', node.id, instanceId])
      throw new FlowError(400, 'COUNTERSIGN_REJECTED', '会签未通过')
    }

    // ── 会签（joinType='all'，默认）：全部同意才放行；任一驳回整单拒 ──
    if (anyRejected) {
      // [countersign-cancelrst] 2026-09-14 R4：结论已定（整单拒）→ 其余待办作废，
      //   避免其他人还在"待我审批"里看到一张已经作废的单
      await conn.query(
        `UPDATE workflow_tasks SET status='cancelled', completed_at=?
          WHERE instance_id=? AND node_id=? AND status='pending'`,
        [now(), instanceId, node.id])
      await log(conn, instanceId, { nodeId: node.id, action: 'gate_reject', message: '会签出现驳回，整单拒绝' })
      await conn.query('UPDATE workflow_instances SET status=?, current_node=? WHERE id=?', ['rejected', node.id, instanceId])
      throw new FlowError(400, 'COUNTERSIGN_REJECTED', '会签未通过')
    }
    if (!allDone) return { waitGate: true } // 引擎挂起等任务
    await log(conn, instanceId, { nodeId: node.id, action: 'gate_pass', message: '会签全部通过，汇聚继续' })
    return { nextNodeId: edgesFrom(flow, node.id)[0]?.to ?? null }
  }

  // ── vote 投票网关（同会签挂网关自身, node_type='vote'）──
  if (gt === 'vote') {
    const participants = gc.participants || gc.committee || []
    if (!participants.length) throw new FlowError(500, 'FLOW_CONFIG', `投票网关 ${node.id} 未配置 participants`)
    // participants: [{name, role}] 或 ['名字']（前端 committee 为字符串数组）
    const plist = participants.map(p => typeof p === 'string' ? { name: p, role: p } : p)
    const rows = await currentGateTasks(conn, instanceId, node.id)
    if (rows.length < plist.length) {
      for (const p of plist) {
        if (rows.find(r => r.node_name === p.name)) continue
        const pids = await resolveRole(conn, p.role, initiatorId)
        if (!pids.length) {
          await log(conn, instanceId, { nodeId: node.id, action: 'warn', message: `投票人 ${p.name}(${p.role}) 解析不到人，任务置空待后台指派` })
        }
        for (const uid of (pids.length ? pids : [null])) {
          await conn.query(
            `INSERT INTO workflow_tasks (instance_id, node_id, node_name, node_type, assignee_id, assignee_type, status, enter_at)
             VALUES (?,?,?,?,?,?, 'pending', ?)`,
            [instanceId, node.id, p.name, 'vote', uid, p.role, now()])
        }
      }
      return { waitGate: true }
    }
    // [vote-veto] 2026-09-14 R4：一票否决——出现任何反对票即立即否决，不必等其他人投完，
    //   其余待办作废（与或签「任一同意即通过」对称；否则"一票否决"要等全员投完才生效，其他人白投）
    const _voteRule = String(gc.threshold ?? gc.passRule ?? 'majority').trim().toLowerCase()
    if (_voteRule === 'veto' && rows.some(r => r.status === 'completed' && r.action === 'reject')) {
      await conn.query(
        `UPDATE workflow_tasks SET status='cancelled', cancelled_at=?
          WHERE instance_id=? AND node_id=? AND status='pending'`,
        [now(), instanceId, node.id])
      await log(conn, instanceId, { nodeId: node.id, action: 'gate_route', message: '投票「一票否决」生效，其余待办作废' })
      const fbVeto = branches.find(b => b.cond === 'fail')
      if (fbVeto) return { nextNodeId: branchIds(fbVeto)?.[0] ?? null }
      await conn.query('UPDATE workflow_instances SET status=?, current_node=? WHERE id=?', ['rejected', node.id, instanceId])
      throw new FlowError(400, 'VOTE_REJECTED', '投票一票否决，流程被拒绝')
    }
    // 全员已投
    const pending = rows.filter(r => r.status === 'pending')
    if (pending.length) return { waitGate: true }
    const approveCnt = rows.filter(r => r.status === 'completed' && r.action === 'approve').length
    const rejectCnt = rows.filter(r => r.status === 'completed' && r.action === 'reject').length
    const need = parseThreshold(gc.threshold ?? gc.passRule, rows.length)
    const passBranch = branches.find(b => b.cond === 'pass' || b.cond === 'else')
    const failBranch = branches.find(b => b.cond === 'fail')
    await log(conn, instanceId, {
      nodeId: node.id, action: 'gate_route',
      message: `投票结果：赞成${approveCnt}/反对${rejectCnt}，阈值${need} → ${approveCnt >= need ? '通过' : '未通过'}`,
      details: { approve: approveCnt, reject: rejectCnt, threshold: need }
    })
    if (approveCnt >= need) {
      const passTarget = (passBranch && branchIds(passBranch) && branchIds(passBranch)[0]) || (edgesFrom(flow, node.id)[0] && edgesFrom(flow, node.id)[0].to) || null
      return { nextNodeId: passTarget }
    }
    if (failBranch) return { nextNodeId: branchIds(failBranch)?.[0] ?? null }
    await conn.query('UPDATE workflow_instances SET status=?, current_node=? WHERE id=?', ['rejected', node.id, instanceId])
    throw new FlowError(400, 'VOTE_REJECTED', '投票未达阈值，流程被拒绝')
  }

  // ── timeout 超时路由（§5.6）：form_data 无字段, 依前序节点 enter_at 判 elapsed ──
  if (gt === 'timeout') {
    const hours = Number(gc.hours)
    // 找上一审批节点的 enter_at（tasks 里 node_id=前驱、最早 enter_at）
    const prevEdges = (flow.edges || []).filter(e => e.to === node.id)
    let elapsedH = null
    for (const pe of prevEdges) {
      const [[t]] = await conn.query(
        `SELECT enter_at, completed_at FROM workflow_tasks
         WHERE instance_id=? AND node_id=? ORDER BY enter_at DESC LIMIT 1`, [instanceId, pe.from])
      if (t && t.enter_at) {
        const enter = new Date(t.enter_at)
        const done = t.completed_at ? new Date(t.completed_at) : new Date()
        elapsedH = Math.max(elapsedH ?? 0, (done - enter) / 3600000)
      }
    }
    const elapsedStr = elapsedH === null ? 'unknown' : elapsedH.toFixed(2) + 'h'
    for (const b of branches) {
      if (b.cond === 'else') continue
      // cond: '<=N' / '>N' 与 hours 比对；同时要求 form_data 不需要
      if (evalCond(b.cond, elapsedH === null ? NaN : elapsedH)) {
        await log(conn, instanceId, { nodeId: node.id, action: 'gate_route', message: `超时路由：elapsed=${elapsedStr} 命中 [${b.cond}]`, details: { elapsedHours: elapsedH } })
        const ids = branchIds(b)
        return { nextNodeId: ids ? ids[0] : null }
      }
    }
    const elseB = branches.find(b => b.cond === 'else')
    if (elseB) return { nextNodeId: branchIds(elseB)?.[0] ?? null }
    throw new FlowError(500, 'FLOW_CONFIG', `timeout 网关 ${node.id} 无命中分支(hours=${gc.hours}, elapsed=${elapsedStr})`)
  }

  throw new FlowError(500, 'FLOW_CONFIG', `未知网关类型 ${gt}（节点 ${node.id}）`)
}

/** 取网关自身的并行任务（countersign/vote 挂在网关节点上） */
async function currentGateTasks(conn, instanceId, gateNodeId) {
  const [rows] = await conn.query(
    'SELECT id, node_name, status, action FROM workflow_tasks WHERE instance_id=? AND node_id=?', [instanceId, gateNodeId])
  return rows
}

// ─────────────────────────────────────────────────────────────
// 激活审批节点（§5.1 关键修复：只在 token 到达时激活）
// ─────────────────────────────────────────────────────────────

async function activateNode(conn, ctx, node) {
  const { instanceId, initiatorId } = ctx
  const pids = await resolveRole(conn, node.role, initiatorId, { firstOnly: node.type === 'approve' })
  if (!pids.length) {
    await log(conn, instanceId, { nodeId: node.id, action: 'warn', message: `节点 ${node.name}(${node.role}) 解析不到处理人，任务置空待后台指派` })
  }
  const enterAt = now()
  for (const uid of (pids.length ? pids : [null])) {
    await conn.query(
      `INSERT INTO workflow_tasks (instance_id, node_id, node_name, node_type, assignee_id, assignee_type, status, enter_at)
       VALUES (?,?,?,?,?,?, 'pending', ?)`,
      [instanceId, node.id, node.name, node.type, uid, node.role || null, enterAt])
  }
  await conn.query('UPDATE workflow_instances SET current_node=? WHERE id=?', [node.id, instanceId])
  if (node.timeout !== undefined) {
    await log(conn, instanceId, { nodeId: node.id, action: 'gate_pass', message: `进入节点 ${node.name}（SLA ${node.timeout}h）`, details: { sla: node.timeout } })
  }
  return { stop: 'wait' }
}

// ─────────────────────────────────────────────────────────────
// 任务完成后的推进（approve/vote/会签汇聚的公共入口）
//   返回 { stop: 'wait'|'completed'|null, rejected?, blockedMsg? }
// ─────────────────────────────────────────────────────────────

async function advanceAfterTask(conn, ctx, task) {
  const { flow, instanceId } = ctx
  const node = nodeById(flow, task.node_id)
  if (!node) throw new FlowError(500, 'FLOW_CONFIG', `任务指向未定义节点 ${task.node_id}`)

  // 会签/投票任务挂在 gate 节点上 → 推进=重新求值网关
  if (node.type === 'gate' && (node.gateType === 'countersign' || node.gateType === 'vote')) {
    const r = await evalGate(conn, ctx, node)
    if (r.waitGate) {
      await conn.query('UPDATE workflow_instances SET current_node=? WHERE id=?', [node.id, instanceId])
      return { stop: 'wait' }
    }
    if (r.nextNodeId == null) {
      await conn.query('UPDATE workflow_instances SET current_node=? WHERE id=?', [node.id, instanceId])
      return { stop: 'wait' }
    }
    await conn.query('UPDATE workflow_instances SET current_node=? WHERE id=?', [node.id, instanceId])
    const r2 = await runToken(conn, ctx, r.nextNodeId)
    return r2
  }

  // 普通审批节点：任务完成后沿出边走查
  const e = edgesFrom(flow, node.id)[0]
  await conn.query('UPDATE workflow_instances SET current_node=? WHERE id=?', [node.id, instanceId])
  if (!e) {
    await conn.query('UPDATE workflow_instances SET status=?, completed_at=? WHERE id=?', ['completed', now(), instanceId])
    return { stop: 'completed' }
  }
  return await runToken(conn, ctx, e.to)
}

// ─────────────────────────────────────────────────────────────
// 载入实例上下文（快照优先）
// ─────────────────────────────────────────────────────────────

async function loadCtx(conn, instanceId) {
  const [[inst]] = await conn.query('SELECT * FROM workflow_instances WHERE id = ?', [instanceId])
  if (!inst) throw new FlowError(404, 'NOT_FOUND', '实例不存在')
  const flow = safeParse(inst.flow_snapshot) || safeParse(inst.flow_config_of_definition) || null
  return { inst, flow }
}

// ============================================================
// API
// ============================================================

// ── 4.1 定义 ───────────────────────────────────────────────

// GET /definitions?category=
router.get('/definitions', async (req, res, next) => {
  try {
    const tenantId = JOB_TENANT()
    const { category } = req.query
    let sql = `SELECT id, code, name, category, description, form_config, flow_config, version, is_active, updated_at
               FROM workflow_definitions WHERE tenant_id = ? AND is_active = 1`
    const params = [tenantId]
    if (category) { sql += ' AND category = ?'; params.push(category) }
    sql += ' ORDER BY updated_at DESC'
    const [rows] = await pool.query(sql, params)
    const data = rows.map(r => ({
      id: r.id, code: r.code, name: r.name, category: r.category, status: r.is_active ? 'active' : 'inactive',
      version: r.version, form_config: safeParse(r.form_config, []), flow_config: safeParse(r.flow_config, {}),
      updated_at: r.updated_at
    }))
    res.json({ code: 0, data })
  } catch (err) { next(err) }
})

// POST /definitions
router.post('/definitions', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { code, name, category, form_config, flow_config } = req.body
    if (!code || !name || !flow_config) {
      return res.status(400).json({ code: 400, message: 'code/name/flow_config 必填' })
    }
    const [[dup]] = await pool.query('SELECT id FROM workflow_definitions WHERE code = ?', [code])
    if (dup) return res.status(400).json({ code: 400, message: `code ${code} 已存在` })
    const [r] = await pool.query(
      `INSERT INTO workflow_definitions (tenant_id, name, code, category, form_config, flow_config, version, is_active, created_by)
       VALUES (?,?,?,?,?,?,1,1,?)`,
      [JOB_TENANT(), name, code, category || null,
       form_config ? JSON.stringify(form_config) : null,
       JSON.stringify(flow_config), req.user.id])
    res.json({ code: 0, data: { id: r.insertId }, message: '已创建' })
  } catch (err) { next(err) }
})

// PUT /definitions/:id — 改 flow_config 时 version+1
router.put('/definitions/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { id } = req.params
    const { name, category, form_config, flow_config, is_active } = req.body
    const [[def]] = await pool.query('SELECT * FROM workflow_definitions WHERE id = ?', [id])
    if (!def) return res.status(404).json({ code: 404, message: '定义不存在' })
    const sets = [], params = []
    if (name !== undefined) { sets.push('name = ?'); params.push(name) }
    if (category !== undefined) { sets.push('category = ?'); params.push(category) }
    if (form_config !== undefined) { sets.push('form_config = ?'); params.push(JSON.stringify(form_config)) }
    let versionChanged = false
    if (flow_config !== undefined) {
      sets.push('flow_config = ?'); params.push(JSON.stringify(flow_config))
      sets.push('version = version + 1'); versionChanged = true
    }
    if (is_active !== undefined) { sets.push('is_active = ?'); params.push(is_active ? 1 : 0) }
    if (!sets.length) return res.json({ code: 0, data: { version: def.version }, message: '无改动' })
    params.push(id)
    await pool.query(`UPDATE workflow_definitions SET ${sets.join(', ')} WHERE id = ?`, params)
    const [[after]] = await pool.query('SELECT version FROM workflow_definitions WHERE id = ?', [id])
    res.json({ code: 0, data: { id: Number(id), version: after.version, versionChanged }, message: '已更新' })
  } catch (err) { next(err) }
})

// ── 4.2 实例 ───────────────────────────────────────────────

// POST /instances — 同步引擎：快照 → 走查 → 激活首批任务
router.post('/instances', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { code, form_data, title } = req.body
    if (!code || !form_data) return res.status(400).json({ code: 400, message: 'code 与 form_data 必填' })
    const tenantId = JOB_TENANT()
    const [[def]] = await conn.query(
      'SELECT * FROM workflow_definitions WHERE code = ? AND tenant_id = ? AND is_active = 1', [code, tenantId])
    if (!def) return res.status(404).json({ code: 404, message: `流程 ${code} 不存在或未启用` })

    // [form-validate] R4：按定义声明的字段校验，绕过页面提交的残缺数据当场拦下
    const _fverr = validateFormData(safeParse(def.form_config, null), form_data)
    if (_fverr) return res.status(400).json({ code: 400, message: _fverr })

    const flow = safeParse(def.flow_config)
    if (!flow || !Array.isArray(flow.nodes)) return res.status(500).json({ code: 500, message: 'flow_config 无效' })
    const startNode = flow.nodes.find(n => n.type === 'start')
    if (!startNode) return res.status(500).json({ code: 500, message: 'flow_config 缺少 start 节点' })

    const resubmitPolicy = flow.resubmitPolicy || 'restart'
    const flowSnapshot = JSON.stringify({ ...flow, resubmitPolicy })
    const titleFinal = title || `${def.name} · ${req.user.name}`

    const [r] = await conn.query(
      `INSERT INTO workflow_instances
        (tenant_id, workflow_id, workflow_code, workflow_version, title, initiator_id, form_data, current_node, status, flow_snapshot)
       VALUES (?,?,?,?,?,?,?,?,'running',?)`,
      [tenantId, def.id, def.code, def.version, titleFinal, req.user.id,
       JSON.stringify(form_data), startNode.id, flowSnapshot])

    const ctx = {
      instanceId: r.insertId, flow, formData: form_data,
      initiatorId: req.user.id, tenantId
    }
    const result = await runToken(conn, ctx, startNode.id)
    if (result.stop === 'blocked') {
      await conn.rollback()
      return res.status(400).json({ code: 'GATE_BLOCKED', message: result.message })
    }
    await conn.commit()
    const [[inst]] = await pool.query('SELECT id, status, current_node FROM workflow_instances WHERE id = ?', [r.insertId])
    res.json({ code: 0, data: { id: inst.id, status: inst.status, current_node: inst.current_node } })
  } catch (err) {
    await conn.rollback().catch(() => {})
    if (err instanceof FlowError) return res.status(err.status).json({ code: err.code, message: err.message })
    next(err)
  } finally { conn.release() }
})

// GET /instances?scope=mine&status=&page=&size=
router.get('/instances', async (req, res, next) => {
  try {
    const { scope = 'mine', status } = req.query
    const { page = 1, size = 20 } = req.query
    const tenantId = JOB_TENANT()
    if (scope !== 'mine') return res.status(400).json({ code: 400, message: 'scope 仅支持 mine（待办请用 /tasks/my）' })
    let sql = `SELECT wi.id, wi.title, wi.status, wi.current_node, wi.started_at, wi.completed_at,
                      wi.workflow_code, wi.workflow_version, wi.form_data,
                      wd.name AS workflow_name, u.name AS initiator_name
               FROM workflow_instances wi
               LEFT JOIN workflow_definitions wd ON wd.id = wi.workflow_id
               LEFT JOIN users u ON u.id = wi.initiator_id
               WHERE wi.tenant_id = ? AND wi.initiator_id = ?`
    const params = [tenantId, req.user.id]
    if (status) { sql += ' AND wi.status IN (?)'; params.push(String(status).split(',')) }
    sql += ' ORDER BY wi.started_at DESC LIMIT ? OFFSET ?'
    params.push(Number(size), (Number(page) - 1) * Number(size))
    const [rows] = await pool.query(sql, params)
    const [[{ total }]] = await pool.query(
      'SELECT COUNT(*) total FROM workflow_instances WHERE tenant_id = ? AND initiator_id = ?' +
      (status ? ' AND status IN (?)' : ''),
      status ? [tenantId, req.user.id, String(status).split(',')] : [tenantId, req.user.id])
    res.json({ code: 0, data: { list: rows, total, page: Number(page), size: Number(size) } })
  } catch (err) { next(err) }
})

// GET /instances/:id — {instance, tasks, logs}
router.get('/instances/:id', async (req, res, next) => {
  try {
    const tenantId = JOB_TENANT()
    const [[inst]] = await pool.query(
      `SELECT wi.*, wd.name AS workflow_name, u.name AS initiator_name
       FROM workflow_instances wi
       LEFT JOIN workflow_definitions wd ON wd.id = wi.workflow_id
       LEFT JOIN users u ON u.id = wi.initiator_id
       WHERE wi.id = ? AND wi.tenant_id = ?`, [req.params.id, tenantId])
    if (!inst) return res.status(404).json({ code: 404, message: '实例不存在' })
    const [tasks] = await pool.query(
      `SELECT t.*, u.name AS assignee_name FROM workflow_tasks t
       LEFT JOIN users u ON u.id = t.assignee_id
       WHERE t.instance_id = ? ORDER BY t.id`, [req.params.id])
    const [logs] = await pool.query(
      `SELECT l.*, u.name AS operator_name FROM workflow_logs l
       LEFT JOIN users u ON u.id = l.operator_id
       WHERE l.instance_id = ? ORDER BY l.id`, [req.params.id])
    // 快照 flow 给前端画进度（含 nodes/edges）
    const snapshot = safeParse(inst.flow_snapshot) || {}
    delete inst.flow_snapshot
    res.json({
      code: 0,
      data: {
        instance: { ...inst, form_data: safeParse(inst.form_data, {}), flow: snapshot },
        tasks, logs
      }
    })
  } catch (err) { next(err) }
})

// POST /instances/:id/resubmit — 仅 returned
router.post('/instances/:id/resubmit', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const tenantId = JOB_TENANT()
    const { form_data } = req.body
    if (!form_data) return res.status(400).json({ code: 400, message: 'form_data 必填' })
    const [[inst]] = await conn.query(
      'SELECT * FROM workflow_instances WHERE id = ? AND tenant_id = ? FOR UPDATE', [req.params.id, tenantId])
    if (!inst) { await conn.rollback(); return res.status(404).json({ code: 404, message: '实例不存在' }) }
    if (inst.status !== 'returned') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `仅 status=returned 可重提（当前 ${inst.status}）` })
    }
    if (inst.initiator_id !== req.user.id) {
      await conn.rollback()
      return res.status(403).json({ code: 403, message: '仅发起人可重提' })
    }
    // [form-validate] R4：重提同样按定义校验（打回后重填的半成品也要挡住）
    const [[_defRow]] = await conn.query('SELECT form_config FROM workflow_definitions WHERE id = ?', [inst.workflow_id])
    const _fverr2 = validateFormData(safeParse(_defRow && _defRow.form_config, null), form_data)
    if (_fverr2) { await conn.rollback(); return res.status(400).json({ code: 400, message: _fverr2 }) }
    const flow = safeParse(inst.flow_snapshot)
    const returnInfo = safeParse(inst.return_info, {})
    const policy = (flow && flow.resubmitPolicy) || 'restart'
    const ctx = { instanceId: inst.id, flow, formData: form_data, initiatorId: inst.initiator_id, tenantId }

    // 覆盖表单 + 状态回 running + 清 return_info
    await conn.query(
      'UPDATE workflow_instances SET form_data=?, status=?, return_info=NULL WHERE id=?',
      [JSON.stringify(form_data), 'running', inst.id])
    await log(conn, inst.id, {
      action: 'resubmit', operatorId: req.user.id,
      message: `重新提交（${policy}）`, details: { policy, fromNodeId: returnInfo.fromNodeId ?? null }
    })

    let result
    if (policy === 'resume' && returnInfo.fromNodeId) {
      // 续走：重新激活打回发起节点（该节点是审批节点则重建任务；是网关则重求值）
      const node = nodeById(flow, returnInfo.fromNodeId)
      // 先清理该节点残留 pending 任务（打回时应已取消，防御再清一次）
      await conn.query(
        `UPDATE workflow_tasks SET status='cancelled', cancelled_at=? WHERE instance_id=? AND node_id=? AND status='pending'`,
        [now(), inst.id, returnInfo.fromNodeId])
      if (node && (node.type === 'approve' || node.type === 'vote')) {
        result = await activateNode(conn, ctx, node)
      } else if (node && node.type === 'gate') {
        const gr = await evalGate(conn, ctx, node)
        if (gr.blocked) { await conn.rollback(); return res.status(400).json({ code: 'GATE_BLOCKED', message: gr.message }) }
        result = gr.waitGate ? { stop: 'wait' } : await runToken(conn, ctx, gr.nextNodeId)
      } else {
        result = await runToken(conn, ctx, returnInfo.fromNodeId)
      }
    } else {
      // restart：从首个审批节点重走（网关重新求值）；无审批节点则从 start
      const firstApprove = (flow.nodes || []).find(n => n.type === 'approve' || n.type === 'vote')
      // restart 前把所有残留 pending 清掉（防御）
      await conn.query(`UPDATE workflow_tasks SET status='cancelled', cancelled_at=? WHERE instance_id=? AND status='pending'`, [now(), inst.id])
      result = await runToken(conn, ctx, firstApprove ? firstApprove.id : (flow.nodes.find(n => n.type === 'start')?.id))
    }
    if (result?.stop === 'blocked') {
      await conn.rollback()
      return res.status(400).json({ code: 'GATE_BLOCKED', message: result.message })
    }
    await conn.commit()
    const [[after]] = await pool.query('SELECT id, status, current_node FROM workflow_instances WHERE id = ?', [inst.id])
    res.json({ code: 0, data: after })
  } catch (err) {
    await conn.rollback().catch(() => {})
    if (err instanceof FlowError) return res.status(err.status).json({ code: err.code, message: err.message })
    next(err)
  } finally { conn.release() }
})

// ── 4.3 任务 ───────────────────────────────────────────────

// GET /tasks/my — 我的 pending 任务 + 实例摘要
router.get('/tasks/my', async (req, res, next) => {
  try {
    const tenantId = JOB_TENANT()
    const [rows] = await pool.query(
      `SELECT t.id, t.instance_id, t.node_id, t.node_name, t.node_type, t.enter_at,
              wi.title, wi.form_data, wi.status AS instance_status,
              wi.workflow_code, wd.name AS workflow_name, u.name AS initiator_name, wi.started_at
       FROM workflow_tasks t
       JOIN workflow_instances wi ON wi.id = t.instance_id AND wi.tenant_id = ?
       LEFT JOIN workflow_definitions wd ON wd.id = wi.workflow_id
       LEFT JOIN users u ON u.id = wi.initiator_id
       WHERE t.assignee_id = ? AND t.status = 'pending'
       ORDER BY t.enter_at DESC, t.id DESC`, [tenantId, req.user.id])
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /tasks/:id/act — approve / reject / return
router.post('/tasks/:id/act', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { action, comment } = req.body
    if (!['approve', 'reject', 'return'].includes(action)) {
      return res.status(400).json({ code: 400, message: 'action 仅支持 approve/reject/return' })
    }
    if (action !== 'approve' && !comment) {
      return res.status(400).json({ code: 400, message: `${action} 必须填写 comment` })
    }
    const tenantId = JOB_TENANT()
    const [[task]] = await conn.query(
      `SELECT t.*, wi.tenant_id, wi.status AS instance_status, wi.initiator_id
       FROM workflow_tasks t JOIN workflow_instances wi ON wi.id = t.instance_id
       WHERE t.id = ? FOR UPDATE`, [req.params.id])
    if (!task) { await conn.rollback(); return res.status(404).json({ code: 404, message: '任务不存在' }) }
    if (task.tenant_id !== tenantId) { await conn.rollback(); return res.status(404).json({ code: 404, message: '任务不存在' }) }
    if (task.instance_status !== 'running') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `实例状态 ${task.instance_status} 不可审批` })
    }
    if (task.status !== 'pending') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `任务已处理（${task.status}）` })
    }
    // 权限：处理人本人
    if (task.assignee_id !== req.user.id) {
      await conn.rollback()
      return res.status(403).json({ code: 403, message: '该任务不属于当前用户' })
    }

    const flow = safeParse((await loadCtxSnapshot(conn, task.instance_id)))
    const formDataRow = await conn.query('SELECT form_data FROM workflow_instances WHERE id=?', [task.instance_id])
    const formData = safeParse(formDataRow[0][0].form_data, {})
    const ctx = { instanceId: task.instance_id, flow, formData, initiatorId: task.initiator_id, tenantId }

    // 完成本任务
    await conn.query(
      'UPDATE workflow_tasks SET status=?, action=?, comment=?, completed_at=? WHERE id=?',
      ['completed', action, comment || null, now(), task.id])

    // reject 语义分三种：
    //   vote 任务（node_type='vote'）→ 只是投反对票，不杀整单，推进投票网关统计
    //   或签任务（会签网关 且 joinType='any'）→ 一票反对不杀整单，交网关统计（全部反对才拒），
    //     否则"任一同意就放行"名存实亡：一人反对就把整单杀了，其他人根本没机会同意
    //   普通审批任务 / 会签（joinType='all'）→ 整单 rejected（终态），取消其余 pending
    // [any-countersign-no-kill] 2026-09-14 R4
    const _gateNode = flow ? (flow.nodes || []).find(n => n.id === task.node_id) : null
    const _isAnyCountersign = !!(_gateNode && _gateNode.type === 'gate' && _gateNode.gateType === 'countersign'
      && String((_gateNode.gateConfig || {}).joinType || 'all').toLowerCase() === 'any')
    if (action === 'reject' && task.node_type !== 'vote' && !_isAnyCountersign) {
      await conn.query(`UPDATE workflow_tasks SET status='cancelled', cancelled_at=? WHERE instance_id=? AND status='pending'`, [now(), task.instance_id])
      await conn.query(`UPDATE workflow_instances SET status='rejected', current_node=? WHERE id=?`, [task.node_id, task.instance_id])
      await log(conn, task.instance_id, { taskId: task.id, nodeId: task.node_id, action: 'reject', operatorId: req.user.id, message: comment })
      await conn.commit()
      const [[inst]] = await pool.query('SELECT id, status, current_node FROM workflow_instances WHERE id=?', [task.instance_id])
      return res.json({ code: 0, data: { instance: inst } })
    }

    // return：打回（实例 returned + return_info + 取消同实例其余 pending）
    if (action === 'return') {
      await conn.query(`UPDATE workflow_tasks SET status='cancelled', cancelled_at=? WHERE instance_id=? AND status='pending'`, [now(), task.instance_id])
      const returnInfo = JSON.stringify({ fromNodeId: task.node_id, comment, returnedAt: now() })
      await conn.query(`UPDATE workflow_instances SET status='returned', current_node=?, return_info=? WHERE id=?`,
        [task.node_id, returnInfo, task.instance_id])
      await log(conn, task.instance_id, { taskId: task.id, nodeId: task.node_id, action: 'return', operatorId: req.user.id, message: comment })
      await conn.commit()
      const [[inst]] = await pool.query('SELECT id, status, current_node, return_info FROM workflow_instances WHERE id=?', [task.instance_id])
      return res.json({ code: 0, data: { instance: inst } })
    }

    // vote 的 reject / 普遍 approve：都走推进引擎（投票网关会重统计）
    const result = await advanceAfterTask(conn, ctx, task)
    const logAction = (task.node_type === 'vote' && action === 'reject') ? 'vote_against' : action
    await log(conn, task.instance_id, { taskId: task.id, nodeId: task.node_id, action: logAction, operatorId: req.user.id, message: comment || null })
    if (result?.stop === 'blocked') {
      await conn.rollback()
      return res.status(400).json({ code: 'GATE_BLOCKED', message: result.message })
    }
    await conn.commit()
    const [[inst]] = await pool.query('SELECT id, status, current_node FROM workflow_instances WHERE id=?', [task.instance_id])
    res.json({ code: 0, data: { instance: inst } })
  } catch (err) {
    await conn.rollback().catch(() => {})
    if (err instanceof FlowError) return res.status(err.status).json({ code: err.code, message: err.message })
    next(err)
  } finally { conn.release() }
})

/** 实例的 flow_snapshot 载入（act 用） */
async function loadCtxSnapshot(conn, instanceId) {
  const [[r]] = await conn.query('SELECT flow_snapshot FROM workflow_instances WHERE id = ?', [instanceId])
  return r ? r.flow_snapshot : null
}

// ============================================================
// cron 兜底：超时未完成任务自动路由（§5.6）
//   每 10 分钟扫描 pending 任务, 其后继是 timeout 网关且 elapsed > hours →
//   取消该任务、走 > 分支。由 index.js 的 startCronJobs 调用。
// ============================================================

export async function scanTimeoutRoutes() {
  const conn = await pool.getConnection()
  try {
    // 找所有 running 实例的 timeout 网关
    const [instances] = await conn.query(
      `SELECT wi.id, wi.flow_snapshot, wi.form_data, wi.initiator_id, wi.tenant_id
       FROM workflow_instances wi WHERE wi.status = 'running'`)
    let actions = 0
    for (const inst of instances) {
      const flow = safeParse(inst.flow_snapshot)
      if (!flow || !Array.isArray(flow.nodes)) continue
      const timeoutGates = flow.nodes.filter(n => n.type === 'gate' && n.gateType === 'timeout')
      if (!timeoutGates.length) continue
      const formData = safeParse(inst.form_data, {})
      const ctx = { instanceId: inst.id, flow, formData, initiatorId: inst.initiator_id, tenantId: inst.tenant_id }

      for (const gate of timeoutGates) {
        const hours = Number(gate.gateConfig?.hours)
        if (!Number.isFinite(hours) || hours < 0) continue // hours=0 为测试模式（enter 后立即视为超时）
        // 前驱任务
        const prevEdges = (flow.edges || []).filter(e => e.to === gate.id)
        for (const pe of prevEdges) {
          const [tasks] = await conn.query(
            `SELECT * FROM workflow_tasks WHERE instance_id=? AND node_id=? AND status='pending'`, [inst.id, pe.from])
          for (const t of tasks) {
            if (!t.enter_at) continue
            const elapsedH = (Date.now() - new Date(t.enter_at).getTime()) / 3600000
            if (elapsedH <= hours) continue
            // 超时：取消任务 → 走 > 分支
            await conn.query(`UPDATE workflow_tasks SET status='cancelled', cancelled_at=? WHERE id=?`, [now(), t.id])
            await log(conn, inst.id, { taskId: t.id, nodeId: pe.from, action: 'timeout_route', message: `任务超时(${elapsedH.toFixed(1)}h > ${hours}h)，自动路由` })
            const overBranch = (gate.gateConfig?.branches || []).find(b => String(b.cond).trim().startsWith('>'))
            if (overBranch) {
              const ids = overBranch.nextNodeIds || (overBranch.nextNodeId ? [overBranch.nextNodeId] : [])
              if (ids.length) {
                await conn.query('UPDATE workflow_instances SET current_node=? WHERE id=?', [gate.id, inst.id])
                const r = await runToken(conn, ctx, ids[0])
                actions += (r?.stop === 'completed' ? 1 : 0) + 1
              }
            }
          }
        }
      }
    }
    if (actions) console.log(`[oa-flow-cron] timeout routes executed: ${actions}`)
    return actions
  } catch (err) {
    console.error('[oa-flow-cron] scan failed:', err.message)
    return 0
  } finally { conn.release() }
}


// ─────────────────────────────────────────────────────────────
// 假期余额 · 真实账本接口（2026-09-14）
// ─────────────────────────────────────────────────────────────

// GET /api/oa/flow/balance — 我的假期余额
router.get('/balance', async (req, res, next) => {
  try {
    const b = await getBalance(pool, req.user.id)
    res.json({ code: 0, data: b })
  } catch (err) { next(err) }
})

// POST /api/oa/flow/balance/consume — 扣减假期余额（需 oa:write）
//   body: { type, days }   type=年假/调休/事假/补卡, days=天数(补卡=次)
router.post('/balance/consume', async (req, res, next) => {
  try {
    if (!(await checkPerm(req, 'oa:write'))) return res.status(403).json({ code: 403, message: '无权限扣减余额' })
    const { type, days } = req.body
    const r = await consumeLeave(pool, { userId: req.user.id, typeName: type, days: Number(days), companyId: req.user.company_id ?? null })
    if (!r.ok) return res.status(400).json({ code: 400, message: r.message })
    res.json({ code: 0, data: { applied: r.applied } })
  } catch (err) { next(err) }
})

// POST /api/oa/flow/balance/refund — 退回假期余额（需 oa:write）
//   body: { type, days }
router.post('/balance/refund', async (req, res, next) => {
  try {
    if (!(await checkPerm(req, 'oa:write'))) return res.status(403).json({ code: 403, message: '无权限退回余额' })
    const { type, days } = req.body
    const r = await refundLeave(pool, { userId: req.user.id, typeName: type, days: Number(days), companyId: req.user.company_id ?? null })
    if (!r.ok) return res.status(400).json({ code: 400, message: r.message })
    res.json({ code: 0, data: { added: r.added } })
  } catch (err) { next(err) }
})

export default router
