// 动态角色 CRUD + 角色-权限关联 API
import { Router } from 'express'
import { auth } from '../../middleware/auth.js'
import { pool } from '../../db/connection.js'

const router = Router()

// ========== 角色 CRUD ==========

// GET /api/rbac/roles - 角色列表
router.get('/', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT r.*, (SELECT COUNT(*) FROM rbac_user_roles WHERE role_id=r.id) as user_count
       FROM rbac_roles r ORDER BY r.sort_order, r.id`
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/rbac/roles - 新增角色
router.post('/', auth, async (req, res, next) => {
  try {
    const { name, label, description = '', sort_order = 0, status = 'enabled' } = req.body
    if (!name || !label) return res.status(400).json({ code: 400, message: 'name 和 label 必填' })
    if (!/^[a-z][a-z0-9_-]{1,49}$/.test(name)) return res.status(400).json({ code: 400, message: 'name 格式：英文小写字母开头' })

    const [existing] = await pool.query('SELECT id FROM rbac_roles WHERE name = ?', [name])
    if (existing.length > 0) return res.status(400).json({ code: 400, message: '角色标识已存在' })

    const [result] = await pool.query(
      'INSERT INTO rbac_roles (name, label, description, sort_order, status) VALUES (?, ?, ?, ?, ?)',
      [name, label, description, sort_order, status]
    )
    const [newRows] = await pool.query('SELECT * FROM rbac_roles WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: newRows[0], message: '创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/rbac/roles/:id - 更新角色
router.put('/:id', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const { label, description, sort_order, status } = req.body
    const [existing] = await pool.query('SELECT id, is_system FROM rbac_roles WHERE id = ?', [id])
    if (existing.length === 0) return res.status(404).json({ code: 404, message: '角色不存在' })

    const updates = []
    const values = []
    if (label !== undefined) { updates.push('label=?'); values.push(label) }
    if (description !== undefined) { updates.push('description=?'); values.push(description) }
    if (sort_order !== undefined) { updates.push('sort_order=?'); values.push(sort_order) }
    if (status !== undefined) { updates.push('status=?'); values.push(status) }
    if (updates.length === 0) return res.status(400).json({ code: 400, message: '没有有效字段' })
    values.push(id)
    await pool.query(`UPDATE rbac_roles SET ${updates.join(',')} WHERE id=?`, values)
    const [rows] = await pool.query('SELECT * FROM rbac_roles WHERE id = ?', [id])
    res.json({ code: 0, data: rows[0], message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/rbac/roles/:id - 删除角色
router.delete('/:id', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const [existing] = await pool.query('SELECT id, is_system FROM rbac_roles WHERE id = ?', [id])
    if (existing.length === 0) return res.status(404).json({ code: 404, message: '角色不存在' })
    if (existing[0].is_system) return res.status(403).json({ code: 403, message: '系统角色不可删除' })

    await pool.query('DELETE FROM rbac_roles WHERE id = ?', [id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

// ========== 角色-权限关联 ==========

// GET /api/rbac/roles/:id/permissions - 获取角色的权限列表
router.get('/:id/permissions', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const [rows] = await pool.query(
      `SELECT p.* FROM rbac_permissions p
       JOIN rbac_role_permissions rp ON p.id = rp.permission_id
       WHERE rp.role_id = ? ORDER BY p.category, p.id`,
      [id]
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// PUT /api/rbac/roles/:id/permissions - 更新角色的权限列表（整体替换）
router.put('/:id/permissions', auth, async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { id } = req.params
    const { permission_ids = [] } = req.body

    const [role] = await conn.query('SELECT id FROM rbac_roles WHERE id = ?', [id])
    if (role.length === 0) { await conn.rollback(); return res.status(404).json({ code: 404, message: '角色不存在' }) }

    // 删除旧的关联
    await conn.query('DELETE FROM rbac_role_permissions WHERE role_id = ?', [id])
    // 插入新的关联
    if (permission_ids.length > 0) {
      const values = permission_ids.map(pid => [id, pid])
      await conn.query('INSERT INTO rbac_role_permissions (role_id, permission_id) VALUES ?', [values])
    }

    await conn.commit()
    // 返回更新后的权限列表
    const [perms] = await pool.query(
      `SELECT p.* FROM rbac_permissions p
       JOIN rbac_role_permissions rp ON p.id = rp.permission_id
       WHERE rp.role_id = ? ORDER BY p.category, p.id`, [id]
    )
    res.json({ code: 0, data: perms, message: '权限更新成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

export default router
