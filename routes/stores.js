import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// GET list (with dealer name joined)
router.get('/', async (req, res, next) => {
  try {
    const { keyword, status, dealer_id } = req.query
    let sql = `SELECT s.*, d.name as dealer_name FROM stores s LEFT JOIN dealers d ON s.dealer_id = d.id WHERE 1=1`
    const params = []
    if (keyword) { sql += ' AND (s.name LIKE ? OR s.contact LIKE ? OR s.city LIKE ?)'; params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`) }
    if (status) { sql += ' AND s.status = ?'; params.push(status) }
    if (dealer_id) { sql += ' AND s.dealer_id = ?'; params.push(dealer_id) }
    sql += ' ORDER BY s.created_at DESC'
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST create
router.post('/', async (req, res, next) => {
  try {
    const { name, store_code, dealer_id, contact, phone, address, city, remark } = req.body
    if (!name) return res.status(400).json({ code: 400, message: '门店名称必填' })
    const [result] = await pool.query(
      'INSERT INTO stores (name, store_code, dealer_id, contact, phone, address, city, remark) VALUES (?,?,?,?,?,?,?,?)',
      [name, store_code || null, dealer_id || null, contact || null, phone || null, address || null, city || null, remark || null]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT update
router.put('/:id', async (req, res, next) => {
  try {
    const allowed = ['name', 'store_code', 'dealer_id', 'contact', 'phone', 'address', 'city', 'status', 'remark', 'service_user_id']
    const fields = []; const params = []
    for (const f of allowed) {
      if (req.body[f] !== undefined) { fields.push(`${f} = ?`); params.push(req.body[f]) }
    }
    if (!fields.length) return res.status(400).json({ code: 400, message: '无更新内容' })
    params.push(req.params.id)
    await pool.query(`UPDATE stores SET ${fields.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE
router.delete('/:id', async (req, res, next) => {
  try {
    await pool.query('DELETE FROM stores WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})


// GET /api/stores/:id/preorder-products - 独立门店已选的可订商品（用 store.id 当 dealer_id）
router.get('/:id/preorder-products', async (req, res, next) => {
  try {
    const storeId = req.params.id
    const [rows] = await pool.query(
      `SELECT p.id, p.sku, p.name, p.spec, p.sale_price, p.image_main, p.is_preorderable
       FROM dealer_preorder_products dpp
       JOIN products p ON p.id = dpp.product_id
       WHERE dpp.dealer_id = ?
       ORDER BY p.name`,
      [storeId]
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/stores/:id/preorder-products - 独立门店覆盖式更新
router.put('/:id/preorder-products', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const storeId = req.params.id
    const { product_ids } = req.body
    if (!Array.isArray(product_ids)) {
      return res.status(400).json({ code: 400, message: 'product_ids 必须是数组' })
    }
    await conn.beginTransaction()
    await conn.query('DELETE FROM dealer_preorder_products WHERE dealer_id = ?', [storeId])
    const uniqueIds = [...new Set(product_ids.filter(id => Number.isInteger(Number(id))))]
    if (uniqueIds.length > 0) {
      const values = uniqueIds.map(pid => [storeId, pid])
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

export default router
