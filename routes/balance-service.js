/**
 * OA 假期余额服务 · 真实账本 (routes/balance-service.js)
 *
 * 2026-09-14 · 湾创 OA 双轨收口 · 由 oa-flow.js 调用
 *
 * 表：
 *   hq_leave_balance        每用户各假种当前余额（annual/comp/personal/patch）
 *   hq_leave_balance_logs   每笔加/扣流水（可追溯）
 *
 * 假种映射（对齐前端 data/oa-engine.js）：
 *   年假→annual / 调休→comp / 事假→personal / 补卡→patch
 *   - annual/comp/personal 按「天」存（8h=1d）
 *   - patch 按「次」存（int，不做天换算）
 *
 * 挂扣语义：
 *   - consumeLeave(type, days)     请假审批通过时扣减  → source='leave_approve'
 *   - refundLeave(type, days)      销假/作废退回        → source='leave_refund'
 *   - addComp(overtimeMinutes)     加班归档转调休        → source='overtime_comp'（8h=1d）
 *   - getBalance(userId)           当前余额（前端 GET /balance）
 *   - checkQuota(type, days)       配额校验（quota 网关用）→ {ok} 或 {blocked,msg}
 *
 * 注：扣/退/加 均为「允许超扣到负数则 clamp 到 0」保守策略；超额扣时按 0 记流水，避免负余额。
 * 事务由调用方（oa-flow.js）控制；本模块函数不带事务，靠传入 conn 保证原子。
 */

import { pool } from '../db/connection.js'

/** 假种中文 → 余额列（不认识的假种返回 null，表示无余额账户，只记流水不拦截） */
const TYPE_COL = {
  '年假': 'annual',
  '调休': 'comp',
  '事假': 'personal',
  '补卡': 'patch',
  'annual': 'annual',
  'comp': 'comp',
  'personal': 'personal',
  'patch': 'patch'
}

/** 小时→天（8h=1d） */
const HOURS_PER_DAY = 8

function isNum(v) { return typeof v === 'number' && !isNaN(v) }

/**
 * 读取用户余额。无记录时按默认配额初始化（对存量用户友好）。
 * @returns {Promise<{annual,comp,personal,patch}>}
 */
export async function getBalance(conn, userId) {
  const [rows] = await conn.query('SELECT * FROM hq_leave_balance WHERE user_id = ?', [userId])
  if (rows.length) {
    const r = rows[0]
    return { annual: Number(r.annual), comp: Number(r.comp), personal: Number(r.personal), patch: Number(r.patch) }
  }
  // 默认配额（MVP：年假 5 天，其余 0；后续由后台/初始化任务写入真实配额）
  return { annual: 5, comp: 0, personal: 0, patch: 0 }
}

/** 内部：确保余额行存在（按需初始化） */
async function ensureRow(conn, userId, companyId) {
  await conn.query(
    `INSERT IGNORE INTO hq_leave_balance (user_id, company_id, annual, comp, personal, patch)
     VALUES (?, ?, 5, 0, 0, 0)`,
    [userId, companyId || null]
  )
}

/** 内部：写流水 */
async function writeLog(conn, { userId, companyId, type, delta, source, refId = null, refDesc = null }) {
  await conn.query(
    `INSERT INTO hq_leave_balance_logs (user_id, company_id, type, delta, source, ref_id, ref_desc)
     VALUES (?, ?, ?, ?, ?, ?, ?)`,
    [userId, companyId || null, type, delta, source, refId, refDesc]
  )
}

/**
 * 扣减余额（请假审批通过）。可负则 clamp 到 0 并写流水。
 * 返回：{ key, applied }  applied=实际扣减数
 */
export async function consumeLeave(conn, { userId, typeName, days, companyId = null, refId = null, refDesc = null }) {
  const key = TYPE_COL[typeName]
  if (!key || !isNum(days) || days <= 0) {
    return { ok: false, key, applied: 0, message: '无效的假种或天数' }
  }
  await ensureRow(conn, userId, companyId)
  const cur = await getBalance(conn, userId)
  const curVal = key === 'patch' ? cur.patch : cur[key]
  const deduct = key === 'patch' ? Math.ceil(days) : days // 补卡按次取整
  const available = curVal
  // 超额则取可用值（不为负）；都按实际落库
  const applied = Math.min(deduct, Math.max(0, available))
  await conn.query(
    `UPDATE hq_leave_balance SET ${key} = ? WHERE user_id = ?`,
    [key === 'patch' ? cur.patch - applied : cur[key] - applied, userId]
  )
  await writeLog(conn, { userId, companyId, type: key, delta: -applied, source: 'leave_approve', refId, refDesc: refDesc || `请假(${typeName}) ${days}天` })
  return { ok: true, key, applied }
}

/**
 * 退回余额（销假/作废）。按实退天数加回。
 */
export async function refundLeave(conn, { userId, typeName, days, companyId = null, refId = null, refDesc = null }) {
  const key = TYPE_COL[typeName]
  if (!key || !isNum(days) || days <= 0) {
    return { ok: false, key, added: 0, message: '无效的假种或天数' }
  }
  await ensureRow(conn, userId, companyId)
  const cur = await getBalance(conn, userId)
  const add = key === 'patch' ? Math.ceil(days) : days
  await conn.query(
    `UPDATE hq_leave_balance SET ${key} = ? WHERE user_id = ?`,
    [key === 'patch' ? cur.patch + add : cur[key] + add, userId]
  )
  await writeLog(conn, { userId, companyId, type: key, delta: add, source: 'leave_refund', refId, refDesc: refDesc || `销假退回(${typeName}) ${days}天` })
  return { ok: true, key, added: add }
}

/**
 * 加班转调休：加班分钟 → 天（8h=1d）并入 comp。
 * @param {number} overtimeMinutes 参与调休累计的加班总分钟
 */
export async function addCompByMinutes(conn, { userId, overtimeMinutes, companyId = null, refId = null, refDesc = null }) {
  const mins = Number(overtimeMinutes)
  if (!isNum(mins) || mins <= 0) {
    return { ok: false, key: 'comp', added: 0, message: '无效的加班分钟' }
  }
  await ensureRow(conn, userId, companyId)
  const days = mins / 60 / HOURS_PER_DAY
  const cur = await getBalance(conn, userId)
  await conn.query('UPDATE hq_leave_balance SET comp = ? WHERE user_id = ?', [cur.comp + days, userId])
  await writeLog(conn, { userId, companyId, type: 'comp', delta: days, source: 'overtime_comp', refId, refDesc: refDesc || `加班转调休 ${mins}分钟(≈${days}天)` })
  return { ok: true, key: 'comp', added: days }
}

/**
 * quota 网关校验：余额是否足以请 typeName 的 days 天。
 * 非余额假种(null) → 放行（不拦截）。补卡按次。
 * @returns {{ok:true} | {ok:false, blocked:true, message, key, available}}
 */
export async function checkQuota(conn, { userId, typeName, days }) {
  const key = TYPE_COL[typeName]
  if (!key) return { ok: true } // 不认识假种：不拦截（可能纯 max 配置流）
  const cur = await getBalance(conn, userId)
  const available = key === 'patch' ? cur.patch : cur[key]
  const need = key === 'patch' ? Math.ceil(Number(days) || 0) : Number(days) || 0
  if (need > available) {
    return { ok: false, blocked: true, key, available, message: `您的${typeName}余额不足（剩余 ${available} ${key === 'patch' ? '次' : '天'}，本次需 ${need} ${key === 'patch' ? '次' : '天'}）` }
  }
  return { ok: true }
}