// 动态菜单 CRUD API
import { Router } from 'express'
import { auth } from '../../middleware/auth.js'
import { pool } from '../../db/connection.js'

const router = Router()

// GET /api/rbac/menus - 菜单树形列表
router.get('/', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT * FROM rbac_menus ORDER BY parent_id, sort_order, id`
    )
    // 组装成树形
    const tree = buildTree(rows)
    res.json({ code: 0, data: tree })
  } catch (err) { next(err) }
})

// GET /api/rbac/menus/flat - 扁平菜单列表（不含层级）
router.get('/flat', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT m.*, p.label as parent_label FROM rbac_menus m
       LEFT JOIN rbac_menus p ON m.parent_id = p.id
       ORDER BY m.parent_id, m.sort_order, m.id`
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/rbac/menus - 新增菜单
router.post('/', auth, async (req, res, next) => {
  try {
    const { name, label, path, icon = '', parent_id = null, sort_order = 0, component_path = null, type = 'menu', visible = 'show', status = 'enabled' } = req.body
    if (!name || !label || !path) return res.status(400).json({ code: 400, message: 'name, label, path 必填' })

    const [result] = await pool.query(
      `INSERT INTO rbac_menus (name, label, path, icon, parent_id, sort_order, component_path, type, visible, status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [name, label, path, icon, parent_id, sort_order, component_path, type, visible, status]
    )
    const [newRows] = await pool.query('SELECT * FROM rbac_menus WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: newRows[0], message: '创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/rbac/menus/:id - 更新菜单
router.put('/:id', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const fields = req.body
    const allowed = ['name', 'label', 'path', 'icon', 'parent_id', 'sort_order', 'component_path', 'type', 'visible', 'status']
    const updates = []
    const values = []
    for (const key of allowed) {
      if (fields[key] !== undefined) { updates.push(`${key}=?`); values.push(fields[key]) }
    }
    if (updates.length === 0) return res.status(400).json({ code: 400, message: '没有有效字段' })
    values.push(id)
    await pool.query(`UPDATE rbac_menus SET ${updates.join(',')} WHERE id=?`, values)
    const [rows] = await pool.query('SELECT * FROM rbac_menus WHERE id = ?', [id])
    res.json({ code: 0, data: rows[0], message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/rbac/menus/:id - 删除菜单（如果有子菜单先删除子菜单）
router.delete('/:id', auth, async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { id } = req.params
    // 先删除子菜单
    await conn.query('DELETE FROM rbac_menus WHERE parent_id = ?', [id])
    await conn.query('DELETE FROM rbac_menus WHERE id = ?', [id])
    await conn.commit()
    res.json({ code: 0, message: '删除成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// 辅助函数：将扁平数据构建为树形
function buildTree(rows, parentId = null) {
  return rows
    .filter(r => r.parent_id === parentId)
    .map(r => ({
      ...r,
      children: buildTree(rows, r.id)
    }))
}

export default router
