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
  let userPerms = parsePermissions(user.permissions)
  if (userPerms && userPerms.length > 0) return userPerms
  if (user.role) {
    userPerms = await getRbacPermissions(user.role)
    if (userPerms && userPerms.length > 0) return userPerms
  }
  return []
}

export function auth(req, res, next) {
  const header = req.headers.authorization
  if (!header || !header.startsWith('Bearer ')) {
    return res.status(401).json({ code: 401, message: '未登录或 token 缺失' })
  }
  try {
    const decoded = jwt.verify(header.split(' ')[1], process.env.JWT_SECRET)
    req.user = decoded
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
    next()
  } catch {
    return res.status(401).json({ code: 401, message: 'token 无效或已过期' })
  }
}

export { resolvePermissions }
