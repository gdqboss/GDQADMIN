import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'

// 从 rbac_role_permissions 表获取角色权限
async function getRbacPermissions(roleName) {
  if (!roleName) return []
  try {
    const [roleRows] = await pool.query('SELECT id FROM rbac_roles WHERE name = ?', [roleName])
    if (!roleRows.length) return []
    const [permRows] = await pool.query(
      `SELECT p.name FROM rbac_permissions p
       JOIN rbac_role_permissions rp ON p.id = rp.permission_id
       WHERE rp.role_id = ?`,
      [roleRows[0].id]
    )
    return permRows.map(r => r.name)
  } catch { return [] }
}

// 解析 permissions 字段
function parsePermissions(perms) {
  if (Array.isArray(perms)) return perms
  if (!perms) return null
  try { return JSON.parse(perms) } catch { return null }
}

// 统一权限解析
async function resolvePermissions(user) {
  // 规则：admin / superuser 角色永远拥有所有权限（不论 rbac_role_permissions 表里勾没勾）
  // superuser 语义 = 超级用户(全权限), 代码在 office.js 已与 admin 并列识别 (2026-08-29)
  if (user.role === 'admin' || user.role === 'superuser') {
    try {
      const [rows] = await pool.query('SELECT name FROM rbac_permissions')
      if (rows.length) return rows.map(r => r.name)
    } catch {}
  }
  let userPerms = parsePermissions(user.permissions)
  if (userPerms && userPerms.length > 0) return userPerms
  if (user.role) {
    userPerms = await getRbacPermissions(user.role)
    if (userPerms && userPerms.length > 0) return userPerms
  }
  return []
}

// 2026-08-12 多租户: 从 DB 加载用户最新 server_profile_id (防止 token 内 profile 过期)
// 2026-08-29 增强: 同时检查 users.status (active 才能放行) + 改密码后旧 token 失效
async function loadUserProfile(userId, tokenIat) {
  try {
    const [rows] = await pool.execute(
      `SELECT id, server_profile_id, role, name, status, password_changed_at FROM users WHERE id = ?`,
      [userId]
    )
    if (!rows.length) return { _userMissing: true }
    const u = rows[0]
    // 禁用用户 → token 失效
    if (u.status && u.status !== 'active') {
      return { _userDisabled: true, status: u.status }
    }
    // 用户改过密码 → 旧 token 失效 (token IAT < password_changed_at)
    if (u.password_changed_at && tokenIat) {
      const pwdChanged = Math.floor(new Date(u.password_changed_at).getTime() / 1000)
      if (tokenIat < pwdChanged) {
        return { _passwordChanged: true, password_changed_at: u.password_changed_at }
      }
    }
    return {
      server_profile_id: u.server_profile_id || 1,
      role: u.role,
      name: u.name,
      status: u.status,
    }
  } catch {
    return null
  }
}

// 同步校验: 用户是否存在 + 是否 active + 改密码后旧 token 失效
// 2026-08-29 改为同步, 不阻塞请求但立即返 403 (禁用用户不能再用 token)
async function syncValidateUser(decoded) {
  const u = await loadUserProfile(decoded.id, decoded.iat)
  if (!u) return { _error: 401, message: '用户不存在' }
  if (u._userMissing) return { _error: 401, message: '用户不存在' }
  if (u._userDisabled) return { _error: 403, message: `账号已被${u.status === 'disabled' ? '禁用' : '锁定'}, 请联系管理员` }
  if (u._passwordChanged) return { _error: 401, message: '密码已修改, 请重新登录', password_changed_at: u.password_changed_at }
  return { _user: u }
}

export async function auth(req, res, next) {
  const header = req.headers.authorization
  if (!header || !header.startsWith('Bearer ')) {
    return res.status(401).json({ code: 401, message: '未登录或 token 缺失' })
  }
  let decoded
  try {
    decoded = jwt.verify(header.split(' ')[1], process.env.JWT_SECRET)
  } catch {
    return res.status(401).json({ code: 401, message: 'token 无效或已过期' })
  }
  // 同步校验用户状态
  const v = await syncValidateUser(decoded)
  if (v._error) return res.status(v._error).json({ code: v._error, message: v.message })
  req.user = { ...decoded, server_profile_id: v._user.server_profile_id, status: v._user.status }
  // super_admin = role='admin'/'superuser' 且 profile_id=1 (主控) — 可跨客户访问
  req.user.is_super_admin = ((v._user.role === 'admin' || v._user.role === 'superuser') && (!v._user.server_profile_id || v._user.server_profile_id === 1))
  next()
}

// auth 中间件增强版：挂载用户权限列表到 req.user.permissions
export async function authWithPerms(req, res, next) {
  const header = req.headers.authorization
  if (!header || !header.startsWith('Bearer ')) {
    return res.status(401).json({ code: 401, message: '未登录或 token 缺失' })
  }
  let decoded
  try {
    decoded = jwt.verify(header.split(' ')[1], process.env.JWT_SECRET)
  } catch {
    return res.status(401).json({ code: 401, message: 'token 无效或已过期' })
  }
  const v = await syncValidateUser(decoded)
  if (v._error) return res.status(v._error).json({ code: v._error, message: v.message })
  req.user = { ...decoded, server_profile_id: v._user.server_profile_id, status: v._user.status }
  req.user.is_super_admin = ((v._user.role === 'admin' || v._user.role === 'superuser') && (!v._user.server_profile_id || v._user.server_profile_id === 1))
  next()
}

export { resolvePermissions }