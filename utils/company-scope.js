/**
 * 企业作用域工具（多企业隔离 · 2026-09-11）
 *
 * 语义（用户已确认）：
 *  - 无 company_id + is_super_admin（role=admin/superuser）= global：孵化器超管，跨企业全量可见
 *  - 无 company_id 的其他用户（孵化器内部）= incubator 作用域（读接口按 company_id IS NULL 过滤）
 *  - 有 company_id + 是公司管理员（company_admins 表）= company-manage：可见/管理本企业
 *  - 有 company_id + 普通员工 = company-self：仅本人相关（考勤/任务/日志只看自己的）
 *
 * 判定顺序：global（超管）> 公司管理员 > 本人角色 > 兜底。
 */
import { pool } from '../db/connection.js'
import { ROLES } from '../middleware/rbac.js'

/**
 * 判定用户的企业作用域
 * @returns {{ kind: 'incubator'|'company-self'|'company-manage'|'global', companyId: number|null }}
 */
export async function getCompanyScope(req) {
  const u = req.user || {}
  let companyId = u.company_id ?? null

  // 兼容HK异步auth：company_id 未预载（undefined）时主动查库兜底（一次 SELECT）
  if (u.company_id === undefined && u.id) {
    try {
      const [[row]] = await pool.query('SELECT company_id FROM users WHERE id = ?', [u.id])
      if (row) companyId = row.company_id || null
    } catch { /* 兜底失败视为无企业 */ }
  }

  // [company-iso] 2026-09-11 HK异步auth竞态兜底：HK auth.js 为 fire-and-forget 异步挂载
  // profile（is_super_admin/company_id），业务 handler 可能先于挂载执行（HK 实测 C 段超管被误判
  // incubator 即此因；SGP auth.js 为同步挂载不受影响）。公式与 HK auth.js 完全一致：
  // role=admin 且 server_profile_id ∈ {NULL,1}。仅在 is_super_admin 未挂载时兜底，
  // SGP（同步挂载）永不触发；HK 无论竞态输赢结果一致。
  if (u.is_super_admin === undefined && !companyId && u.id) {
    try {
      const [[row]] = await pool.query('SELECT role, server_profile_id FROM users WHERE id = ?', [u.id])
      if (row) {
        u.is_super_admin = (row.role === 'admin' && (!row.server_profile_id || row.server_profile_id === 1))
      }
    } catch { /* 兜底失败维持 undefined → 走 incubator 兜底语义 */ }
  }

  // 孵化器超管可跨企业（role admin/superuser 且 is_super_admin 且无企业归属）
  // 2026-09-11 修正(读隔离时发现)：原顺序 global 判断位于 !companyId 早退之后，恒不可达（死代码）——
  // 孵化器超管一直被误判为 incubator。写隔离未暴露（global/incubator 的 companyId 同为 NULL，
  // 写入行为相同），但 tasks/work-logs/office 读隔离滤网无 checkPerm 兜底，超管将看不到企业数据。
  // 现将 global 判断提前：is_super_admin 且无企业归属 → global；企业内 admin 因 companyId≠NULL 被正确挡住。
  if (u.is_super_admin && !companyId) {
    return { kind: 'global', companyId: null }
  }

  // 无企业 → 孵化器内部（未授权孵化器人员：读接口按 company_id IS NULL 过滤）
  if (!companyId) {
    return { kind: 'incubator', companyId: null }
  }

  // 企业管理员（独立表 company_admins，孵化器指派）
  if (u._isCompanyAdmin || u.is_company_admin) {
    return { kind: 'company-manage', companyId }
  }
  // 兜底：显式查一次（auth 未预载时）
  try {
    const [[row]] = await pool.query('SELECT 1 FROM company_admins WHERE company_id = ? AND user_id = ?', [companyId, u.id])
    if (row) return { kind: 'company-manage', companyId }
  } catch {}

  return { kind: 'company-self', companyId }
}

/**
 * 计算当前用户可见的人员 user_id 集合（SQL IN 用）
 * @param {object} scope getCompanyScope 的结果
 * @param {number} currentUserId
 * @param {boolean} includeSelf 是否包含本人（默认 true）
 * @returns {Promise<number[]>}
 */
export async function scopeUserIds(scope, currentUserId, includeSelf = true) {
  const ids = includeSelf ? [currentUserId] : []
  if (!scope) return ids
  if (scope.kind === 'global' || scope.kind === 'incubator') return null // null = 不限制（走原有逻辑）
  if (scope.kind === 'company-manage') {
    // 本企业所有 active 用户
    const [rows] = await pool.query('SELECT id FROM users WHERE company_id = ? AND status = ?', [scope.companyId, 'active'])
    const all = rows.map(r => r.id)
    if (includeSelf) return all
    return all.filter(id => id !== currentUserId)
  }
  // company-self：仅本人
  return ids
}

/**
 * 给业务接口生成 where 追加片段（简化注入）
 * @param {object} scope
 * @param {number} currentUserId
 * @param {string} alias user 表别名（如 'u'）或 null（直接 users）
 */
export async function companyWhere(scope, currentUserId, alias = 'u') {
  if (!scope) return { sql: '', params: [] }
  if (scope.kind === 'global' || scope.kind === 'incubator') return { sql: '', params: [] }
  if (scope.kind === 'company-manage') {
    const pf = alias ? alias + '.company_id' : 'company_id'
    return { sql: ` AND ${pf} = ?`, params: [scope.companyId] }
  }
  // company-self：仅本人
  const pf = alias ? alias + '.id' : 'id'
  return { sql: ` AND ${pf} = ?`, params: [currentUserId] }
}

/**
 * [company-iso] 2026-09-12 按主键写接口（PUT/DELETE/PATCH）的跨企业归属守卫
 *
 * 只做"归属防线"：global 放行；incubator 仅 NULL 记录；企业用户仅本企业记录。
 * 接口原有的业务权限逻辑（本人/收件人/角色审批等）保持不变——守卫只多挡跨企业，不放松任何原规则。
 *
 * @param {Request} req
 * @param {string} table 表名（调用方传固定字面量，本函数不做白名单校验）
 * @param {number} rowId 记录主键
 * @returns {Promise<{ok: boolean, status?: number, message?: string, row?: object, scope: object}>}
 *   ok=true 放行；ok=false 时调用方 res.status(status).json({code, message})
 */
export async function assertRowCompany(req, table, rowId) {
  const scope = await getCompanyScope(req)
  if (scope.kind === 'global') return { ok: true, scope }
  const [[row]] = await pool.query(`SELECT company_id AS cid FROM ${table} WHERE id = ?`, [rowId])
  if (!row) return { ok: false, status: 404, message: '记录不存在', scope }
  if (scope.kind === 'incubator') {
    return row.cid == null
      ? { ok: true, row, scope }
      : { ok: false, status: 403, message: '无权操作该记录', scope }
  }
  // company-manage / company-self：仅本企业记录
  return row.cid === scope.companyId
    ? { ok: true, row, scope }
    : { ok: false, status: 403, message: '无权操作该记录', scope }
}

export default { getCompanyScope, scopeUserIds, companyWhere, assertRowCompany }