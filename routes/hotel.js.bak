/**
 * 酒店管理后端路由 - hotel.js
 * 包含：房型/房价/订单/评论 API
 */
import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { ROLES } from '../middleware/rbac.js'

const router = Router()

// 角色检查中间件
function requireRole(req, res, next) {
  const allowed = [ROLES.ADMIN, ROLES.MANAGER, ROLES.SUPERADMIN, ROLES.OPERATOR]
  if (!req.user || !allowed.includes(req.user.role)) {
    return res.status(403).json({ code: 403, message: 'forbidden' })
  }
  next()
}

// =====================房型管理 =====================

// GET /api/hotel/room-types
router.get('/room-types', async (req, res, next) => {
  try {
    const { enabled } = req.query
    let sql = 'SELECT * FROM hotel_room_types WHERE 1=1'
    const params = []
    if (enabled !== undefined) {
      sql += ' AND enabled = ?'
      params.push(enabled)
    }
    sql += ' ORDER BY sort_order ASC, id ASC'
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/hotel/room-types/:id
router.get('/room-types/:id', async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT * FROM hotel_room_types WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: 'not found' })
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/hotel/room-types
router.post('/room-types', requireRole, async (req, res, next) => {
  try {
    const { name, description, max_guests, bed_type, room_area, floor_range, amenities, image_url, sort_order, enabled } = req.body
    if (!name) return res.status(400).json({ code: 400, message: 'name required' })
    const [result] = await pool.query(
      `INSERT INTO hotel_room_types (name, description, max_guests, bed_type, room_area, floor_range, amenities, image_url, sort_order, enabled)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [name, description || null, max_guests || 2, bed_type || null, room_area || null, floor_range || null, amenities || null, image_url || null, sort_order || 0, enabled !== undefined ? enabled : 1]
    )
    const [[row]] = await pool.query('SELECT * FROM hotel_room_types WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/hotel/room-types/:id
router.put('/room-types/:id', requireRole, async (req, res, next) => {
  try {
    const fields = [], vals = []
    const allowed = ['name', 'description', 'max_guests', 'bed_type', 'room_area', 'floor_range', 'amenities', 'image_url', 'sort_order', 'enabled']
    for (const f of allowed) {
      if (req.body[f] !== undefined) { fields.push(`${f}=?`); vals.push(req.body[f]) }
    }
    if (!fields.length) return res.status(400).json({ code: 400, message: 'no fields' })
    vals.push(req.params.id)
    await pool.query(`UPDATE hotel_room_types SET ${fields.join(',')} WHERE id = ?`, vals)
    const [[row]] = await pool.query('SELECT * FROM hotel_room_types WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: 'not found' })
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE /api/hotel/room-types/:id
router.delete('/room-types/:id', requireRole, async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT id FROM hotel_room_types WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: 'not found' })
    await pool.query('DELETE FROM hotel_room_types WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// ===================== 房价管理 =====================

// GET /api/hotel/prices
router.get('/prices', async (req, res, next) => {
  try {
    const { room_type_id, date_start, date_end } = req.query
    const { page, size } = parsePagination(req.query)
    let where = 'WHERE 1=1'
    const params = [], cp = []
    if (room_type_id) { where += ' AND p.room_type_id = ?'; params.push(room_type_id); cp.push(room_type_id) }
    if (date_start) { where += ' AND p.price_date >= ?'; params.push(date_start); cp.push(date_start) }
    if (date_end) { where += ' AND p.price_date <= ?'; params.push(date_end); cp.push(date_end) }
    const sql = `SELECT p.*, rt.name as room_type_name FROM hotel_room_prices p LEFT JOIN hotel_room_types rt ON rt.id = p.room_type_id ${where} ORDER BY p.price_date ASC LIMIT ? OFFSET ?`
    const csql = `SELECT COUNT(*) as total FROM hotel_room_prices p ${where}`
    const [[{ total }]] = await pool.query(csql, cp)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/hotel/prices/:id
router.get('/prices/:id', async (req, res, next) => {
  try {
    const [[row]] = await pool.query(`SELECT p.*, rt.name as room_type_name FROM hotel_room_prices p LEFT JOIN hotel_room_types rt ON rt.id = p.room_type_id WHERE p.id = ?`, [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: 'not found' })
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/hotel/prices
router.post('/prices', requireRole, async (req, res, next) => {
  try {
    const { room_type_id, price_date, price, stock, extra_bed_price } = req.body
    if (!room_type_id || !price_date || price == null) return res.status(400).json({ code: 400, message: 'required fields missing' })
    const [[rt]] = await pool.query('SELECT id FROM hotel_room_types WHERE id = ?', [room_type_id])
    if (!rt) return res.status(404).json({ code: 404, message: 'room type not found' })
    const [[ex]] = await pool.query('SELECT id FROM hotel_room_prices WHERE room_type_id = ? AND price_date = ?', [room_type_id, price_date])
    if (ex) {
      await pool.query('UPDATE hotel_room_prices SET price=?, stock=?, extra_bed_price=? WHERE id=?', [price, stock ?? null, extra_bed_price ?? null, ex.id])
      const [[row]] = await pool.query('SELECT * FROM hotel_room_prices WHERE id = ?', [ex.id])
      return res.json({ code: 0, data: row, message: 'price updated' })
    }
    const [result] = await pool.query(`INSERT INTO hotel_room_prices (room_type_id, price_date, price, stock, extra_bed_price) VALUES (?, ?, ?, ?, ?)`, [room_type_id, price_date, price, stock ?? null, extra_bed_price ?? null])
    const [[row]] = await pool.query('SELECT * FROM hotel_room_prices WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/hotel/prices/:id
router.put('/prices/:id', requireRole, async (req, res, next) => {
  try {
    const fields = [], vals = []
    if (req.body.price !== undefined) { fields.push('price=?'); vals.push(req.body.price) }
    if (req.body.stock !== undefined) { fields.push('stock=?'); vals.push(req.body.stock) }
    if (req.body.extra_bed_price !== undefined) { fields.push('extra_bed_price=?'); vals.push(req.body.extra_bed_price) }
    if (!fields.length) return res.status(400).json({ code: 400, message: 'no fields' })
    vals.push(req.params.id)
    await pool.query(`UPDATE hotel_room_prices SET ${fields.join(',')} WHERE id = ?`, vals)
    const [[row]] = await pool.query('SELECT * FROM hotel_room_prices WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: 'not found' })
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE /api/hotel/prices/:id
router.delete('/prices/:id', requireRole, async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT id FROM hotel_room_prices WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: 'not found' })
    await pool.query('DELETE FROM hotel_room_prices WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/hotel/prices/calendar
router.get('/prices/calendar', async (req, res, next) => {
  try {
    const { date_start, date_end } = req.query
    if (!date_start || !date_end) return res.status(400).json({ code: 400, message: 'date range required' })
    const [types] = await pool.query('SELECT * FROM hotel_room_types WHERE enabled=1 ORDER BY sort_order ASC, id ASC')
    const [prices] = await pool.query('SELECT * FROM hotel_room_prices WHERE price_date BETWEEN ? AND ? ORDER BY price_date ASC', [date_start, date_end])
    const priceMap = {}
    for (const p of prices) {
      if (!priceMap[p.room_type_id]) priceMap[p.room_type_id] = {}
      priceMap[p.room_type_id][p.price_date] = p
    }
    res.json({ code: 0, data: { room_types: types, price_map: priceMap }, message: 'ok' })
  } catch (err) { next(err) }
})

// ===================== 酒店订单 =====================

function generateHotelOrderNo() {
  const date = new Date()
  const dateStr = date.toISOString().slice(0, 10).replace(/-/g, '')
  const timeStr = String(date.getHours()).padStart(2, '0') + String(date.getMinutes()).padStart(2, '0') + String(date.getSeconds()).padStart(2, '0')
  const random = String(Math.floor(Math.random() * 10000)).padStart(4, '0')
  return `HOTEL${dateStr}${timeStr}${random}`
}

// GET /api/hotel/orders
router.get('/orders', requireRole, async (req, res, next) => {
  try {
    const { status, keyword, date_start, date_end } = req.query
    const { page, size } = parsePagination(req.query)
    let where = 'WHERE 1=1'
    const params = [], cp = []
    if (status) { where += ' AND o.status = ?'; params.push(status); cp.push(status) }
    if (date_start) { where += ' AND DATE(o.created_at) >= ?'; params.push(date_start); cp.push(date_start) }
    if (date_end) { where += ' AND DATE(o.created_at) <= ?'; params.push(date_end); cp.push(date_end) }
    if (keyword) {
      where += ' AND (o.order_no LIKE ? OR o.guest_name LIKE ? OR o.guest_phone LIKE ?)'
      const kw = `%${keyword}%`
      params.push(kw, kw, kw); cp.push(kw, kw, kw)
    }
    const sql = `SELECT o.*, rt.name as room_type_name FROM hotel_orders o LEFT JOIN hotel_room_types rt ON rt.id = o.room_type_id ${where} ORDER BY o.created_at DESC LIMIT ? OFFSET ?`
    const csql = `SELECT COUNT(*) as total FROM hotel_orders o ${where}`
    const [[{ total }]] = await pool.query(csql, cp)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/hotel/orders/:id
router.get('/orders/:id', requireRole, async (req, res, next) => {
  try {
    const [[order]] = await pool.query(`SELECT o.*, rt.name as room_type_name FROM hotel_orders o LEFT JOIN hotel_room_types rt ON rt.id = o.room_type_id WHERE o.id = ?`, [req.params.id])
    if (!order) return res.status(404).json({ code: 404, message: 'not found' })
    res.json({ code: 0, data: order, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/hotel/orders
router.post('/orders', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { room_type_id, guest_name, guest_phone, check_in_date, check_out_date, total_price, remark, adults, children } = req.body
    if (!room_type_id || !guest_name || !guest_phone || !check_in_date || !check_out_date) {
      return res.status(400).json({ code: 400, message: 'required fields missing' })
    }
    const [[rt]] = await conn.query('SELECT * FROM hotel_room_types WHERE id = ? AND enabled=1', [room_type_id])
    if (!rt) { await conn.rollback(); return res.status(404).json({ code: 404, message: 'room type unavailable' }) }
    const [priceRows] = await conn.query('SELECT price_date, stock FROM hotel_room_prices WHERE room_type_id = ? AND price_date BETWEEN ? AND ? ORDER BY price_date ASC', [room_type_id, check_in_date, check_out_date])
    if (!priceRows.length) { await conn.rollback(); return res.status(400).json({ code: 400, message: 'no price set for selected dates' }) }
    for (const row of priceRows) {
      if (row.stock != null && row.stock <= 0) { await conn.rollback(); return res.status(400).json({ code: 400, message: 'insufficient stock' }) }
    }
    const nights = Math.ceil((new Date(check_out_date) - new Date(check_in_date)) / 86400000)
    const order_no = generateHotelOrderNo()
    await conn.beginTransaction()
    const [result] = await conn.query(`INSERT INTO hotel_orders (order_no, room_type_id, guest_name, guest_phone, check_in_date, check_out_date, nights, total_price, adults, children, status, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_pay', ?)`, [order_no, room_type_id, guest_name, guest_phone, check_in_date, check_out_date, nights, total_price || 0, adults || 1, children || 0, remark || null])
    const order_id = result.insertId
    for (const row of priceRows) {
      if (row.stock != null) await conn.query('UPDATE hotel_room_prices SET stock = stock - 1 WHERE room_type_id = ? AND price_date = ?', [room_type_id, row.price_date])
    }
    await conn.commit()
    const [[order]] = await pool.query(`SELECT o.*, rt.name as room_type_name FROM hotel_orders o LEFT JOIN hotel_room_types rt ON rt.id = o.room_type_id WHERE o.id = ?`, [order_id])
    res.json({ code: 0, data: order, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// PUT /api/hotel/orders/:id/status
router.put('/orders/:id/status', requireRole, async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { action } = req.body
    if (!action) return res.status(400).json({ code: 400, message: 'action required' })
    await conn.beginTransaction()
    const [[order]] = await conn.query('SELECT * FROM hotel_orders WHERE id = ?', [req.params.id])
    if (!order) { await conn.rollback(); return res.status(404).json({ code: 404, message: 'not found' }) }
    let newStatus = null
    let setFields = []
    if (action === 'pay') {
      if (order.status !== 'pending_pay') { await conn.rollback(); return res.status(400).json({ code: 400, message: 'invalid status transition' }) }
      newStatus = 'paid'
      setFields = ["status = 'paid'", 'paid_at = NOW()']
    } else if (action === 'checkin') {
      if (order.status !== 'paid') { await conn.rollback(); return res.status(400).json({ code: 400, message: 'invalid status transition' }) }
      newStatus = 'checked_in'
      setFields = ["status = 'checked_in'", 'checked_in_at = NOW()']
    } else if (action === 'checkout') {
      if (order.status !== 'checked_in') { await conn.rollback(); return res.status(400).json({ code: 400, message: 'invalid status transition' }) }
      newStatus = 'checked_out'
      setFields = ["status = 'checked_out'", 'checked_out_at = NOW()']
    } else if (action === 'cancel') {
      if (order.status !== 'pending_pay' && order.status !== 'paid') { await conn.rollback(); return res.status(400).json({ code: 400, message: 'invalid status transition' }) }
      newStatus = 'cancelled'
      setFields = ["status = 'cancelled'"]
      const [pr] = await conn.query('SELECT price_date FROM hotel_room_prices WHERE room_type_id = ? AND price_date BETWEEN ? AND ?', [order.room_type_id, order.check_in_date, order.check_out_date])
      for (const r of pr) { if (r.stock != null) await conn.query('UPDATE hotel_room_prices SET stock = stock + 1 WHERE room_type_id = ? AND price_date = ?', [order.room_type_id, r.price_date]) }
    } else if (action === 'refund') {
      if (order.status !== 'paid' && order.status !== 'checked_in') { await conn.rollback(); return res.status(400).json({ code: 400, message: 'invalid status transition' }) }
      newStatus = 'refunded'
      setFields = ["status = 'refunded'"]
      const [pr] = await conn.query('SELECT price_date FROM hotel_room_prices WHERE room_type_id = ? AND price_date BETWEEN ? AND ?', [order.room_type_id, order.check_in_date, order.check_out_date])
      for (const r of pr) { if (r.stock != null) await conn.query('UPDATE hotel_room_prices SET stock = stock + 1 WHERE room_type_id = ? AND price_date = ?', [order.room_type_id, r.price_date]) }
    } else {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: 'invalid action' })
    }
    await conn.query(`UPDATE hotel_orders SET ${setFields.join(', ')} WHERE id = ?`, [req.params.id])
    await conn.commit()
    const [[updated]] = await pool.query('SELECT * FROM hotel_orders WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: updated, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// DELETE /api/hotel/orders/:id
router.delete('/orders/:id', requireRole, async (req, res, next) => {
  try {
    const [[order]] = await pool.query('SELECT * FROM hotel_orders WHERE id = ?', [req.params.id])
    if (!order) return res.status(404).json({ code: 404, message: 'not found' })
    if (order.status !== 'pending_pay') return res.status(400).json({ code: 400, message: 'only pending orders can be voided' })
    await pool.query("UPDATE hotel_orders SET status = 'cancelled' WHERE id = ?", [req.params.id])
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// ===================== 评论管理 =====================

// GET /api/hotel/reviews
router.get('/reviews', async (req, res, next) => {
  try {
    const { room_type_id, status } = req.query
    const { page, size } = parsePagination(req.query)
    let where = 'WHERE 1=1'
    const params = [], cp = []
    if (room_type_id) { where += ' AND r.room_type_id = ?'; params.push(room_type_id); cp.push(room_type_id) }
    if (status) { where += ' AND r.status = ?'; params.push(status); cp.push(status) }
    const sql = `SELECT r.*, rt.name as room_type_name, o.order_no, o.guest_name FROM hotel_reviews r LEFT JOIN hotel_room_types rt ON rt.id = r.room_type_id LEFT JOIN hotel_orders o ON o.id = r.order_id ${where} ORDER BY r.created_at DESC LIMIT ? OFFSET ?`
    const csql = `SELECT COUNT(*) as total FROM hotel_reviews r ${where}`
    const [[{ total }]] = await pool.query(csql, cp)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/hotel/reviews/:id
router.get('/reviews/:id', async (req, res, next) => {
  try {
    const [[row]] = await pool.query(`SELECT r.*, rt.name as room_type_name, o.order_no, o.guest_name FROM hotel_reviews r LEFT JOIN hotel_room_types rt ON rt.id = r.room_type_id LEFT JOIN hotel_orders o ON o.id = r.order_id WHERE r.id = ?`, [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: 'not found' })
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/hotel/reviews
router.post('/reviews', async (req, res, next) => {
  try {
    const { order_id, room_type_id, rating, content, images } = req.body
    if (!order_id || !room_type_id || !rating) return res.status(400).json({ code: 400, message: 'required fields missing' })
    if (rating < 1 || rating > 5) return res.status(400).json({ code: 400, message: 'rating must be 1-5' })
    const [[ord]] = await pool.query("SELECT * FROM hotel_orders WHERE id = ? AND status IN ('checked_out','completed')", [order_id])
    if (!ord) return res.status(400).json({ code: 400, message: 'order status not allowed for review' })
    const [[existing]] = await pool.query('SELECT id FROM hotel_reviews WHERE order_id = ?', [order_id])
    if (existing) return res.status(400).json({ code: 400, message: 'order already reviewed' })
    const [result] = await pool.query(`INSERT INTO hotel_reviews (order_id, room_type_id, rating, content, images, status) VALUES (?, ?, ?, ?, ?, 'pending')`, [order_id, room_type_id, rating, content || null, images || null])
    const [[row]] = await pool.query('SELECT * FROM hotel_reviews WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/hotel/reviews/:id
router.put('/reviews/:id', requireRole, async (req, res, next) => {
  try {
    const { action, reply_content } = req.body
    const [[rev]] = await pool.query('SELECT * FROM hotel_reviews WHERE id = ?', [req.params.id])
    if (!rev) return res.status(404).json({ code: 404, message: 'not found' })
    if (action === 'approve') {
      await pool.query('UPDATE hotel_reviews SET status=?, reply_content=? WHERE id=?', ['approved', reply_content || null, req.params.id])
    } else if (action === 'reject') {
      await pool.query('UPDATE hotel_reviews SET status=? WHERE id=?', ['rejected', req.params.id])
    } else {
      return res.status(400).json({ code: 400, message: 'invalid action' })
    }
    const [[row]] = await pool.query('SELECT * FROM hotel_reviews WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/hotel/stats
router.get('/stats', requireRole, async (req, res, next) => {
  try {
    const [totalOrders] = await pool.query('SELECT COUNT(*) as cnt FROM hotel_orders')
    const [totalRevenue] = await pool.query("SELECT COALESCE(SUM(total_price),0) as sum FROM hotel_orders WHERE status NOT IN ('cancelled','refunded')")
    const [avgRating] = await pool.query("SELECT COALESCE(AVG(rating),0) as avg FROM hotel_reviews WHERE status='approved'")
    const [totalReviews] = await pool.query("SELECT COUNT(*) as cnt FROM hotel_reviews WHERE status='approved'")
    res.json({ code: 0, data: { total_orders: totalOrders[0]?.cnt || 0, total_revenue: totalRevenue[0]?.sum || 0, avg_rating: parseFloat((avgRating[0]?.avg || 0).toFixed(2)), total_reviews: totalReviews[0]?.cnt || 0 }, message: 'ok' })
  } catch (err) { next(err) }
})

export default router