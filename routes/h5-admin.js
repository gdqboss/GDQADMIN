import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { checkPerm } from '../utils/permission.js'
import { ROLES } from '../middleware/rbac.js'

const router = Router()

// All routes require admin/manager permission
router.use(auth)

// GET /api/h5-admin/users - List H5 users with pagination
router.get('/users', async (req, res, next) => {
  try {
    if (![ROLES.ADMIN, ROLES.MANAGER].includes(req.user.role)) {
      return res.status(403).json({ code: 403, message: '权限不足' })
    }

    const { page = 1, limit = 50, search = '', role = '', status = '' } = req.query
    const offset = (page - 1) * limit

    let where = []
    let params = []

    if (search) {
      where.push('(u.name LIKE ? OR u.phone LIKE ?)')
      params.push(`%${search}%`, `%${search}%`)
    }
    if (role) {
      where.push('u.role = ?')
      params.push(role)
    }
    if (status) {
      where.push('u.status = ?')
      params.push(status)
    }

    const whereClause = where.length ? 'WHERE ' + where.join(' AND ') : ''

    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM h5_users u ${whereClause}`,
      params
    )

    const [users] = await pool.query(`
      SELECT
        u.id, u.name, u.phone, u.role, u.store_id, u.level, u.status, u.created_at,
        r.label as role_label,
        s.name as store_name
      FROM h5_users u
      LEFT JOIN h5_roles r ON u.role COLLATE utf8mb4_unicode_ci = r.name COLLATE utf8mb4_unicode_ci
      LEFT JOIN stores s ON u.store_id = s.id
      ${whereClause}
      ORDER BY u.created_at DESC
      LIMIT ? OFFSET ?
    `, [...params, parseInt(limit), offset])

    res.json({ code: 0, data: { list: users, total }, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/h5-admin/users/:id - Update H5 user (role, store, level, status, is_internal)
router.put('/users/:id', async (req, res, next) => {
  try {
    if (![ROLES.ADMIN, ROLES.MANAGER].includes(req.user.role)) {
      return res.status(403).json({ code: 403, message: '权限不足' })
    }

    const { id } = req.params
    const { role, store_id, level, status, is_internal } = req.body

    const updates = []
    const params = []

    if (role !== undefined) {
      // Validate role exists
      const [[roleExists]] = await pool.query('SELECT id FROM h5_roles WHERE name = ?', [role])
      if (!roleExists) return res.status(400).json({ code: 400, message: '角色不存在' })
      updates.push('role = ?')
      params.push(role)
    }
    if (store_id !== undefined) {
      updates.push('store_id = ?')
      params.push(store_id || null)
    }
    if (level !== undefined) {
      updates.push('level = ?')
      params.push(parseInt(level))
    }
    if (status !== undefined) {
      updates.push('status = ?')
      params.push(status)
    }
    if (is_internal !== undefined) {
      updates.push('is_internal = ?')
      params.push(is_internal ? 1 : 0)
    }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有可更新的字段' })
    }

    params.push(id)
    await pool.query(
      `UPDATE h5_users SET ${updates.join(', ')} WHERE id = ?`,
      params
    )

    res.json({ code: 0, data: null, message: '更新成功' })
  } catch (err) { next(err) }
})

// PUT /api/h5-admin/users/:id/parent - Update referral relationship (superadmin only)
router.put('/users/:id/parent', async (req, res, next) => {
  try {
    if (!(await checkPerm(req, 'system:config'))) {
      return res.status(403).json({ code: 403, message: '仅超级管理员可修改推荐关系' })
    }

    const { id } = req.params
    const { parent_id, reason } = req.body

    // Get old parent_id
    const [[user]] = await pool.query('SELECT parent_id FROM h5_users WHERE id = ?', [id])
    if (!user) return res.status(404).json({ code: 404, message: '用户不存在' })

    // Validate parent exists
    if (parent_id) {
      const [[parent]] = await pool.query('SELECT id FROM h5_users WHERE id = ?', [parent_id])
      if (!parent) return res.status(400).json({ code: 400, message: '推荐人不存在' })

      // Prevent circular reference
      if (parseInt(parent_id) === parseInt(id)) {
        return res.status(400).json({ code: 400, message: '不能将自己设为推荐人' })
      }
    }

    // Update parent_id
    await pool.query('UPDATE h5_users SET parent_id = ? WHERE id = ?', [parent_id || null, id])

    // Log the change
    await pool.query(
      'INSERT INTO h5_user_parent_logs (h5_user_id, old_parent_id, new_parent_id, operator_id, reason) VALUES (?, ?, ?, ?, ?)',
      [id, user.parent_id, parent_id || null, req.user.id, reason || null]
    )

    res.json({ code: 0, data: null, message: '推荐关系更新成功' })
  } catch (err) { next(err) }
})

export default router
