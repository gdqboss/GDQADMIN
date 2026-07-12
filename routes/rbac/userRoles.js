// 用户-角色关联 API
import { Router } from 'express'
import { auth } from '../../middleware/auth.js'
import { pool } from '../../db/connection.js'

const router = Router()

// GET /api/rbac/users - 用户-角色关联列表(admin 后台)
// ⚠️ 必须放在 /:userId/* 之前,否则 Express 会把 'users' 当 userId 匹配
router.get('/', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT u.id, u.name, u.phone, u.email, u.role as legacy_role,
             u.status, u.created_at,
             GROUP_CONCAT(r.name ORDER BY r.sort_order SEPARATOR ',') as role_names,
             GROUP_CONCAT(r.id ORDER BY r.sort_order SEPARATOR ',') as role_ids,
             COUNT(ur.role_id) as role_count
        FROM users u
        LEFT JOIN rbac_user_roles ur ON ur.user_id = u.id
        LEFT JOIN rbac_roles r ON r.id = ur.role_id
       GROUP BY u.id
       ORDER BY u.id ASC
       LIMIT 500
    `)
    // 把 role_ids 字符串拆数组
    const data = rows.map(u => ({
      ...u,
      role_ids: u.role_ids ? u.role_ids.split(',').map(Number) : [],
      role_names: u.role_names ? u.role_names.split(',') : [],
    }))
    res.json({ code: 0, data })
  } catch (err) { next(err) }
})

// GET /api/rbac/users/:userId/roles - 获取用户的角色列表
router.get('/:userId/roles', auth, async (req, res, next) => {
  try {
    const { userId } = req.params
    const [rows] = await pool.query(
      `SELECT r.* FROM rbac_roles r
       JOIN rbac_user_roles ur ON r.id = ur.role_id
       WHERE ur.user_id = ? ORDER BY r.sort_order`,
      [userId]
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// PUT /api/rbac/users/:userId/roles - 批量更新用户的角色（整体替换）
router.put('/:userId/roles', auth, async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { userId } = req.params
    const { role_ids = [] } = req.body

    const [user] = await conn.query('SELECT id FROM users WHERE id = ?', [userId])
    if (user.length === 0) { await conn.rollback(); return res.status(404).json({ code: 404, message: '用户不存在' }) }

    await conn.query('DELETE FROM rbac_user_roles WHERE user_id = ?', [userId])
    if (role_ids.length > 0) {
      const values = role_ids.map(rid => [userId, rid])
      await conn.query('INSERT INTO rbac_user_roles (user_id, role_id) VALUES ?', [values])
    }

    await conn.commit()
    const [roles] = await pool.query(
      `SELECT r.* FROM rbac_roles r
       JOIN rbac_user_roles ur ON r.id = ur.role_id
       WHERE ur.user_id = ? ORDER BY r.sort_order`, [userId]
    )
    res.json({ code: 0, data: roles, message: '角色分配成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// GET /api/rbac/users/:userId/permissions - 获取用户的有效权限（合并所有角色的权限）
router.get('/:userId/permissions', auth, async (req, res, next) => {
  try {
    const { userId } = req.params
    const [rows] = await pool.query(
      `SELECT DISTINCT p.* FROM rbac_permissions p
       JOIN rbac_role_permissions rp ON p.id = rp.permission_id
       JOIN rbac_user_roles ur ON rp.role_id = ur.role_id
       WHERE ur.user_id = ? AND rp.role_id IN (
         SELECT role_id FROM rbac_user_roles WHERE user_id = ?
       ) AND (SELECT status FROM rbac_roles WHERE id = rp.role_id) = 'enabled'
       ORDER BY p.category, p.id`,
      [userId, userId]
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// GET /api/rbac/users/:userId/menus - 获取用户可见的菜单（基于角色）
router.get('/:userId/menus', auth, async (req, res, next) => {
  try {
    const { userId } = req.params
    const [rows] = await pool.query(
      `SELECT DISTINCT m.* FROM rbac_menus m
       WHERE m.status = 'enabled' AND m.visible = 'show'
       AND (
         -- 有权限的菜单（通过角色关联的权限匹配）
         m.name IN (
           SELECT DISTINCT REPLACE(p.name, ':read', '') FROM rbac_permissions p
           JOIN rbac_role_permissions rp ON p.id = rp.permission_id
           JOIN rbac_user_roles ur ON rp.role_id = ur.role_id
           WHERE ur.user_id = ? AND rp.role_id IN (
             SELECT role_id FROM rbac_user_roles WHERE user_id = ?
           )
           UNION ALL
           SELECT DISTINCT REPLACE(p.name, ':write', '') FROM rbac_permissions p
           JOIN rbac_role_permissions rp ON p.id = rp.permission_id
           JOIN rbac_user_roles ur ON rp.role_id = ur.role_id
           WHERE ur.user_id = ? AND rp.role_id IN (
             SELECT role_id FROM rbac_user_roles WHERE user_id = ?
           )
         )
         OR m.parent_id IS NULL  -- 顶级菜单（可折叠分组）
       )
       ORDER BY m.parent_id, m.sort_order, m.id`,
      [userId, userId, userId, userId]
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

export default router
