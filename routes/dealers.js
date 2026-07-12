import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// GET list
router.get('/', async (req, res, next) => {
  try {
    const { keyword, status } = req.query
    let sql = 'SELECT * FROM dealers WHERE 1=1'
    const params = []
    if (keyword) { sql += ' AND (name LIKE ? OR contact LIKE ? OR region LIKE ?)'; params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`) }
    if (status) { sql += ' AND status = ?'; params.push(status) }
    sql += ' ORDER BY created_at DESC'
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST create
router.post('/', async (req, res, next) => {
  try {
    const { name, contact, phone, email, address, region, remark } = req.body
    if (!name) return res.status(400).json({ code: 400, message: '经销商名称必填' })
    const [result] = await pool.query(
      'INSERT INTO dealers (name, contact, phone, email, address, region, remark) VALUES (?,?,?,?,?,?,?)',
      [name, contact || null, phone || null, email || null, address || null, region || null, remark || null]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT update
router.put('/:id', async (req, res, next) => {
  try {
    const allowed = ['name', 'contact', 'phone', 'email', 'address', 'region', 'status', 'remark']
    const fields = []; const params = []
    for (const f of allowed) {
      if (req.body[f] !== undefined) { fields.push(`${f} = ?`); params.push(req.body[f]) }
    }
    if (!fields.length) return res.status(400).json({ code: 400, message: '无更新内容' })
    params.push(req.params.id)
    await pool.query(`UPDATE dealers SET ${fields.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE
router.delete('/:id', async (req, res, next) => {
  try {
    await pool.query('DELETE FROM dealers WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})


// GET /api/dealers/:id/preorder-products - 经销商已选的可订商品
router.get('/:id/preorder-products', async (req, res, next) => {
  try {
    const dealerId = req.params.id
    const [rows] = await pool.query(
      `SELECT p.id, p.sku, p.name, p.spec, p.sale_price, p.image_main, p.is_preorderable
       FROM dealer_preorder_products dpp
       JOIN products p ON p.id = dpp.product_id
       WHERE dpp.dealer_id = ?
       ORDER BY p.name`,
      [dealerId]
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/dealers/:id/preorder-products - 覆盖式更新（传 product_ids[]）
router.put('/:id/preorder-products', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const dealerId = req.params.id
    const { product_ids } = req.body
    if (!Array.isArray(product_ids)) {
      return res.status(400).json({ code: 400, message: 'product_ids 必须是数组' })
    }
    await conn.beginTransaction()
    // 1. 清空原有关联
    await conn.query('DELETE FROM dealer_preorder_products WHERE dealer_id = ?', [dealerId])
    // 2. 插入新关联（去重）
    const uniqueIds = [...new Set(product_ids.filter(id => Number.isInteger(Number(id))))]
    if (uniqueIds.length > 0) {
      const values = uniqueIds.map(pid => [dealerId, pid])
      await conn.query(
        'INSERT INTO dealer_preorder_products (dealer_id, product_id) VALUES ? ON DUPLICATE KEY UPDATE dealer_id = dealer_id',
        [values]
      )
    }
    await conn.commit()
    res.json({ code: 0, data: { count: uniqueIds.length }, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// GET /api/dealers/:id/available-products - 经销商可选的商品池（status=active AND is_preorderable=1）
router.get('/:id/available-products', async (req, res, next) => {
  try {
    const { keyword } = req.query
    let where = "WHERE status = 'active' AND is_preorderable = 1"
    const params = []
    if (keyword) {
      where += ' AND (name LIKE ? OR sku LIKE ?)'
      params.push(`%${keyword}%`, `%${keyword}%`)
    }
    const [rows] = await pool.query(
      `SELECT id, sku, name, spec, sale_price, image_main, is_preorderable FROM products ${where} ORDER BY name`,
      params
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

export default router
