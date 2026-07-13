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
  // 规则：admin 角色永远拥有所有权限（不论 rbac_role_permissions 表里勾没勾）
  if (user.role === 'admin') {
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
async function loadUserProfile(userId) {
  try {
    const [rows] = await pool.execute(
      `SELECT server_profile_id, role, username FROM users WHERE id = ?`,
      [userId]
    )
    return rows[0] || null
  } catch {
    return null
  }
}

export function auth(req, res, next) {
  const header = req.headers.authorization
  if (!header || !header.startsWith('Bearer ')) {
    return res.status(401).json({ code: 401, message: '未登录或 token 缺失' })
  }
  try {
    const decoded = jwt.verify(header.split(' ')[1], process.env.JWT_SECRET)
    req.user = decoded
    // 异步加载最新 server_profile_id (不阻塞主流程)
    loadUserProfile(decoded.id).then(u => {
      if (u) {
        req.user.server_profile_id = u.server_profile_id || 1
        // super_admin = role='admin' 且 profile_id=1 (主控) — 可跨客户访问
        req.user.is_super_admin = (u.role === 'admin' && (!u.server_profile_id || u.server_profile_id === 1))
      }
    })
    next()
  } catch {
    return res.status(401).json({ code: 401, message: 'token 无效或已过期' })
  }
}

// auth 中间件增强版：挂载用户权限列表到 req.user.permissions
export function authWithPerms(req, res, next) {
  const header = req.headers.authorization
  if (!header || !header.startsWith('Bearer ')) {
    return res.status(401).json({ code: 401, message: '未登录或 token 缺失' })
  }
  try {
    const decoded = jwt.verify(header.split(' ')[1], process.env.JWT_SECRET)
    req.user = decoded
    loadUserProfile(decoded.id).then(u => {
      if (u) {
        req.user.server_profile_id = u.server_profile_id || 1
        req.user.is_super_admin = (u.role === 'admin' && (!u.server_profile_id || u.server_profile_id === 1))
      }
    })
    next()
  } catch {
    return res.status(401).json({ code: 401, message: 'token 无效或已过期' })
  }
}

export { resolvePermissions }