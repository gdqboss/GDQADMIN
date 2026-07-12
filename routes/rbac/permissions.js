// 动态权限 CRUD API
import { Router } from 'express'
import { auth } from '../../middleware/auth.js'
import { pool } from '../../db/connection.js'

const router = Router()

// GET /api/rbac/permissions - 权限列表
router.get('/', auth, async (req, res, next) => {
  try {
    const { category, keyword, page = 1, pageSize = 100 } = req.query
    const offset = (page - 1) * pageSize

    let where = '1=1'
    const params = []
    if (category) { where += ' AND category = ?'; params.push(category) }
    if (keyword) { where += ' AND (name LIKE ? OR label LIKE ?)'; params.push(`%${keyword}%`, `%${keyword}%`) }

    const [rows] = await pool.query(
      `SELECT * FROM rbac_permissions WHERE ${where} ORDER BY category, id`,
      params
    )

    const total = rows.length
    const list = rows.slice(offset, offset + Number(pageSize))

    res.json({ code: 0, data: { list, total, page: Number(page), pageSize: Number(pageSize) } })
  } catch (err) { next(err) }
})

// GET /api/rbac/permissions/categories - 权限分类列表
router.get('/categories', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT category, COUNT(*) as count FROM rbac_permissions GROUP BY category ORDER BY category`
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/rbac/permissions - 新增权限
router.post('/', auth, async (req, res, next) => {
  try {
    const { name, label, category = 'other', description = '' } = req.body
    if (!name || !label) return res.status(400).json({ code: 400, message: 'name 和 label 必填' })
    if (!/^[a-z][a-z0-9:_/-]{2,99}$/.test(name)) return res.status(400).json({ code: 400, message: 'name 格式：英文小写，可包含 : / -' })

    const [existing] = await pool.query('SELECT id FROM rbac_permissions WHERE name = ?', [name])
    if (existing.length > 0) return res.status(400).json({ code: 400, message: '权限标识已存在' })

    const [result] = await pool.query(
      'INSERT INTO rbac_permissions (name, label, category, description) VALUES (?, ?, ?, ?)',
      [name, label, category, description]
    )
    const [newRows] = await pool.query('SELECT * FROM rbac_permissions WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: newRows[0], message: '创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/rbac/permissions/:id - 更新权限
router.put('/:id', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const { label, category, description } = req.body
    const [existing] = await pool.query('SELECT id FROM rbac_permissions WHERE id = ?', [id])
    if (existing.length === 0) return res.status(404).json({ code: 404, message: '权限不存在' })

    await pool.query(
      'UPDATE rbac_permissions SET label=?, category=?, description=? WHERE id=?',
      [label, category, description, id]
    )
    const [rows] = await pool.query('SELECT * FROM rbac_permissions WHERE id = ?', [id])
    res.json({ code: 0, data: rows[0], message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/rbac/permissions/:id - 删除权限
router.delete('/:id', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const [existing] = await pool.query('SELECT id, is_system FROM rbac_permissions WHERE id = ?', [id])
    if (existing.length === 0) return res.status(404).json({ code: 404, message: '权限不存在' })
    if (existing[0].is_system) return res.status(403).json({ code: 403, message: '系统权限不可删除' })

    await pool.query('DELETE FROM rbac_permissions WHERE id = ?', [id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

export default router
