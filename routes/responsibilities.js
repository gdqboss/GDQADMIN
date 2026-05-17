import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// GET /api/responsibilities - 获取所有角色权责
router.get('/', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT * FROM role_responsibilities ORDER BY created_at DESC'
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/responsibilities/:role - 获取指定角色权责
router.get('/:role', async (req, res, next) => {
  try {
    const [[row]] = await pool.query(
      'SELECT * FROM role_responsibilities WHERE role = ?',
      [req.params.role]
    )

    if (!row) {
      return res.status(404).json({ code: 404, message: '该角色权责不存在' })
    }

    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/responsibilities - 创建/更新角色权责（仅管理员）
router.post('/', async (req, res, next) => {
  try {
    if (req.user.role !== 'admin') {
      return res.status(403).json({ code: 403, message: '仅管理员可创建角色权责' })
    }

    const { role, title, description, responsibilities } = req.body

    if (!role || !title) {
      return res.status(400).json({ code: 400, message: '角色和标题必填' })
    }

    // Check if role already exists
    const [[existing]] = await pool.query(
      'SELECT id FROM role_responsibilities WHERE role = ?',
      [role]
    )

    if (existing) {
      // Update existing
      await pool.query(
        `UPDATE role_responsibilities
         SET title = ?, description = ?, responsibilities = ?
         WHERE role = ?`,
        [title, description || null, responsibilities ? JSON.stringify(responsibilities) : null, role]
      )
      res.json({ code: 0, data: { id: existing.id }, message: '角色权责更新成功' })
    } else {
      // Create new
      const [result] = await pool.query(
        `INSERT INTO role_responsibilities (role, title, description, responsibilities)
         VALUES (?, ?, ?, ?)`,
        [role, title, description || null, responsibilities ? JSON.stringify(responsibilities) : null]
      )
      res.json({ code: 0, data: { id: result.insertId }, message: '角色权责创建成功' })
    }
  } catch (err) { next(err) }
})

// PUT /api/responsibilities/:role - 更新角色权责（仅管理员）
router.put('/:role', async (req, res, next) => {
  try {
    if (req.user.role !== 'admin') {
      return res.status(403).json({ code: 403, message: '仅管理员可更新角色权责' })
    }

    const { title, description, responsibilities } = req.body

    const [[existing]] = await pool.query(
      'SELECT id FROM role_responsibilities WHERE role = ?',
      [req.params.role]
    )

    if (!existing) {
      return res.status(404).json({ code: 404, message: '该角色权责不存在' })
    }

    const updates = []
    const values = []

    if (title !== undefined) { updates.push('title = ?'); values.push(title) }
    if (description !== undefined) { updates.push('description = ?'); values.push(description) }
    if (responsibilities !== undefined) {
      updates.push('responsibilities = ?')
      values.push(responsibilities ? JSON.stringify(responsibilities) : null)
    }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有更新内容' })
    }

    values.push(req.params.role)
    await pool.query(
      `UPDATE role_responsibilities SET ${updates.join(', ')} WHERE role = ?`,
      values
    )

    res.json({ code: 0, data: null, message: '角色权责更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/responsibilities/:role - 删除角色权责（仅管理员）
router.delete('/:role', async (req, res, next) => {
  try {
    if (req.user.role !== 'admin') {
      return res.status(403).json({ code: 403, message: '仅管理员可删除角色权责' })
    }

    const [[existing]] = await pool.query(
      'SELECT id FROM role_responsibilities WHERE role = ?',
      [req.params.role]
    )

    if (!existing) {
      return res.status(404).json({ code: 404, message: '该角色权责不存在' })
    }

    await pool.query('DELETE FROM role_responsibilities WHERE role = ?', [req.params.role])

    res.json({ code: 0, data: null, message: '角色权责删除成功' })
  } catch (err) { next(err) }
})

export default router
