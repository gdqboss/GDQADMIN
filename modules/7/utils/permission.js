/**
 * 权限检查工具 - 给路由层使用
 * 用法: import { checkPerm } from '../utils/permission.js'
 *       if (!await checkPerm(req, 'product:write')) return res.status(403)
 */

import { pool } from '../db/connection.js'

const _cache = new Map() // roleName -> [permNames]

async function getRolePermissions(roleName) {
  if (!roleName) return []
  if (_cache.has(roleName)) return _cache.get(roleName)

  try {
    const [roleRows] = await pool.query('SELECT id FROM rbac_roles WHERE name = ?', [roleName])
    if (!roleRows.length) return []
    const [permRows] = await pool.query(
      `SELECT p.name FROM rbac_permissions p
       JOIN rbac_role_permissions rp ON p.id = rp.permission_id
       WHERE rp.role_id = ?`,
      [roleRows[0].id]
    )
    const perms = permRows.map(r => r.name)
    _cache.set(roleName, perms)
    return perms
  } catch { return [] }
}

function parsePerms(perms) {
  if (Array.isArray(perms)) return perms
  if (!perms) return null
  try { return JSON.parse(perms) } catch { return null }
}

/**
 * 检查用户是否有指定权限
 * admin 角色天然拥有所有权限
 */
export async function checkPerm(req, permName) {
  if (!req.user) return false
  // admin 天然全权限
  if (req.user.role === 'admin') return true
  // 先查用户个人权限
  if (req.user.permissions) {
    const up = parsePerms(req.user.permissions)
    if (up && up.includes(permName)) return true
  }
  // 再查角色权限
  const rolePerms = await getRolePermissions(req.user.role)
  return rolePerms.includes(permName)
}

/**
 * 检查用户是否有所有列出的权限
 */
export async function checkAllPerms(req, permNames) {
  for (const p of permNames) {
    if (!(await checkPerm(req, p))) return false
  }
  return true
}

/**
 * 检查用户是否有任意一个权限
 */
export async function checkAnyPerm(req, permNames) {
  for (const p of permNames) {
    if (await checkPerm(req, p)) return true
  }
  return false
}

/** 清除缓存（权限变更后调用） */
export function clearPermCache() {
  _cache.clear()
}