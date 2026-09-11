/**
 * 企业作用域工具（多企业隔离 · 2026-09-11）
 *
 * 语义（用户已确认）：
 *  - 无 company_id 的用户（孵化器内部）= incubator 作用域，不注入任何过滤（零回归）
 *  - 有 company_id + 是公司管理员（company_admins 表）= company-manage：可见/管理本企业
 *  - 有 company_id + 普通员工 = company-self：仅本人相关（考勤/任务/日志只看自己的）
 *  - 有 company_id + role=admin/superuser 且 is_super_admin = global：跨企业可见（孵化器超管）
 *
 * 判定顺序：公司管理员 > 本人角色 > 兜底。
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

  // 无企业 → 孵化器内部，不隔离
  if (!companyId) {
    return { kind: 'incubator', companyId: null }
  }

  // 孵化器超管可跨企业（role admin/superuser 且 is_super_admin）
  // 2026-09-11 修正：is_super_admin 在 SGP 本机对一切 role=admin 为 true（profile 恒=1），
  // 企业内 admin 角色曾被误判 global。有企业归属者按定义不是孵化器超管。
  if (u.is_super_admin && !companyId) {
    return { kind: 'global', companyId }
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

export default { getCompanyScope, scopeUserIds, companyWhere }