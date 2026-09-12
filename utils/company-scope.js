/**
 * 企业作用域工具（多企业隔离 · 2026-09-11，放权修订 2026-09-12）
 *
 * 语义（用户已确认）：
 *  - 无 company_id + role=admin/superuser = global：平台管理员跨企业全量可见
 *    （2026-09-12 放权：profile 不再限定 {NULL,1}，跨 profile 管理员/HK superuser 均为 global）
 *  - 无 company_id 的其他用户（孵化器内部）= incubator 作用域（读接口按 company_id IS NULL 过滤）
 *  - 有 company_id + 是公司管理员（company_admins 表，role=enterprise-admin）= company-manage：可见/管理本企业
 *  - 有 company_id + 普通员工 = company-self：仅本人相关（考勤/任务/日志只看自己的）
 *
 * 判定顺序：global（平台管理员）> 公司管理员 > 本人角色 > 兜底。
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
  let role = u.role ?? undefined

  // 兜底查库（一次）：company_id 或 role 未预载时（HK 异步 auth / JWT 缺字段）
  if ((u.company_id === undefined || role === undefined) && u.id) {
    try {
      const [[row]] = await pool.query('SELECT role, server_profile_id, company_id FROM users WHERE id = ?', [u.id])
      if (row) {
        if (u.company_id === undefined) companyId = row.company_id || null
        if (role === undefined) role = row.role
      }
    } catch { /* 兜底失败按无企业/无角色处理 */ }
  }

  // [company-iso] 2026-09-12 is_super_admin 竞态兜底（保留，供其他依赖 req.user.is_super_admin 的调用方）
  if (u.is_super_admin === undefined && !companyId && u.id) {
    try {
      const [[row]] = await pool.query('SELECT role, server_profile_id FROM users WHERE id = ?', [u.id])
      if (row) {
        u.is_super_admin = (row.role === 'admin' && (!row.server_profile_id || row.server_profile_id === 1))
      }
    } catch { /* 兜底失败维持 undefined → 走 incubator 兜底语义 */ }
  }

  // 孵化器超管可跨企业（admin/superuser 且无企业归属）
  // 2026-09-12 放权（用户拍板）：profile 不再限定 {NULL,1} —— 跨 profile 管理员
  // （江清波 HK profile=6、厉无害 HK=6、SGP profile=3 波哥团队账号）与 HK superuser
  // 全部获得 global；不再依赖 auth 预载的 is_super_admin（双端公式不一致 + HK 异步竞态），
  // 直接按查库/Token 的 role 判定。企业内账号（companyId 非空）不受影响。
  if ((role === 'admin' || role === 'superuser') && !companyId) {
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