import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requireRole } from '../middleware/rbac.js'
import { translateFields } from '../services/translation.js'

const router = Router()

// GET / — 返回平铺数组，含层级字段
router.get('/', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT id, name, parent_id, level, sort_order FROM categories ORDER BY level, COALESCE(parent_id, 0), sort_order, id'
    )
    // Auto-translate to English if requested
    if (req.lang === 'en') {
      await translateFields(rows, ['name'])
    }
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST / — 新建分类（支持 parent_id）
router.post('/', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { name, parent_id } = req.body
    if (!name?.trim()) return res.status(400).json({ code: 400, message: '分类名称不能为空' })

    let level = 1
    const parentIdVal = parent_id ? Number(parent_id) : null

    if (parentIdVal) {
      const [[parent]] = await pool.query('SELECT id, level FROM categories WHERE id = ?', [parentIdVal])
      if (!parent) return res.status(400).json({ code: 400, message: '父分类不存在' })
      level = parent.level + 1
      if (level > 4) return res.status(400).json({ code: 400, message: '最多支持4级分类，无法继续添加子分类' })
    }

    // 同级名称唯一（null-safe 比较）
    const [[{ cnt }]] = await pool.query(
      'SELECT COUNT(*) as cnt FROM categories WHERE name = ? AND parent_id <=> ?',
      [name.trim(), parentIdVal]
    )
    if (cnt > 0) return res.status(400).json({ code: 400, message: '同级分类中该名称已存在' })

    const [result] = await pool.query(
      'INSERT INTO categories (name, parent_id, level, sort_order) VALUES (?, ?, ?, 0)',
      [name.trim(), parentIdVal, level]
    )
    res.json({
      code: 0,
      data: { id: result.insertId, name: name.trim(), parent_id: parentIdVal, level, sort_order: 0 },
      message: '分类创建成功'
    })
  } catch (err) { next(err) }
})

// PUT /:id — 重命名（同步 products.category 字符串）
router.put('/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { name } = req.body
    if (!name?.trim()) return res.status(400).json({ code: 400, message: '分类名称不能为空' })

    const [[cat]] = await pool.query('SELECT id, name FROM categories WHERE id = ?', [req.params.id])
    if (!cat) return res.status(404).json({ code: 404, message: '分类不存在' })

    await pool.query('UPDATE categories SET name = ? WHERE id = ?', [name.trim(), req.params.id])
    // 同步商品的 category 字符串字段
    await pool.query('UPDATE products SET category = ? WHERE category_id = ?', [name.trim(), req.params.id])

    res.json({ code: 0, data: null, message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /:id — 删除分类（WITH RECURSIVE 查子树，有商品则拒绝）
router.delete('/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const [[cat]] = await pool.query('SELECT id FROM categories WHERE id = ?', [req.params.id])
    if (!cat) return res.status(404).json({ code: 404, message: '分类不存在' })

    // 递归查子树
    const [subtreeRows] = await pool.query(`
      WITH RECURSIVE subtree AS (
        SELECT id FROM categories WHERE id = ?
        UNION ALL
        SELECT c.id FROM categories c JOIN subtree s ON c.parent_id = s.id
      )
      SELECT id FROM subtree
    `, [req.params.id])

    const subtreeIds = subtreeRows.map(r => r.id)

    // 检查子树内是否有商品
    const placeholders = subtreeIds.map(() => '?').join(',')
    const [[{ cnt }]] = await pool.query(
      `SELECT COUNT(*) as cnt FROM products WHERE category_id IN (${placeholders})`,
      subtreeIds
    )
    if (cnt > 0) {
      return res.status(400).json({ code: 400, message: `该分类及子分类下共有 ${cnt} 个商品，请先移除商品再删除` })
    }

    // 删除根节点（FK CASCADE 会级联删除子节点）
    await pool.query('DELETE FROM categories WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: '删除成功' })
  } catch (err) { next(err) }
})

export default router
