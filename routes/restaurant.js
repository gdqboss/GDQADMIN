import { Router } from 'express'
import { pool } from '../db/connection.js'
import { ROLES } from '../middleware/rbac.js'

const router = Router()

function requireRole(req, res, next) {
  const allowed = [ROLES.ADMIN, ROLES.MANAGER, ROLES.SUPERADMIN, ROLES.OPERATOR]
  if (!req.user || !allowed.includes(req.user.role)) {
    return res.status(403).json({ code: 403, message: '无权限访问' })
  }
  next()
}

function generateNo(prefix) {
  const date = new Date()
  const ds = date.toISOString().slice(0, 10).replace(/-/g, '')
  const ts = String(date.getHours()).padStart(2, '0') + String(date.getMinutes()).padStart(2, '0') + String(date.getSeconds()).padStart(2, '0')
  const r = String(Math.floor(Math.random() * 10000)).padStart(4, '0')
  return `${prefix}${ds}${ts}${r}`
}

// ===================== 桌台管理 =====================
// GET /api/restaurant/tables
router.get('/tables', requireRole, async (req, res, next) => {
  try {
    const { area, status } = req.query
    let where = 'WHERE 1=1'
    const params = []
    if (area) { where += ' AND t.area = ?'; params.push(area) }
    if (status) { where += ' AND t.status = ?'; params.push(status) }
    const [rows] = await pool.query(
      `SELECT t.*, o.order_no as current_order_no
       FROM restaurant_tables t
       LEFT JOIN dine_orders o ON o.id = t.current_order_id AND o.status NOT IN ('completed','cancelled')
       ${where}
       ORDER BY t.area, t.table_no`, params
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/restaurant/tables
router.post('/tables', requireRole, async (req, res, next) => {
  try {
    const { table_no, table_name, capacity, area } = req.body
    if (!table_no || !table_name) return res.status(400).json({ code: 400, message: '桌台编号和名称必填' })
    const [result] = await pool.query(
      'INSERT INTO restaurant_tables (table_no, table_name, capacity, area) VALUES (?, ?, ?, ?)',
      [table_no, table_name, capacity || 4, area || 'main']
    )
    const [[row]] = await pool.query('SELECT * FROM restaurant_tables WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/restaurant/tables/:id
router.put('/tables/:id', requireRole, async (req, res, next) => {
  try {
    const { table_name, capacity, area, status } = req.body
    const fields = []
    const vals = []
    if (table_name) { fields.push('table_name = ?'); vals.push(table_name) }
    if (capacity) { fields.push('capacity = ?'); vals.push(capacity) }
    if (area) { fields.push('area = ?'); vals.push(area) }
    if (status) { fields.push('status = ?'); vals.push(status) }
    if (!fields.length) return res.status(400).json({ code: 400, message: '无更新字段' })
    vals.push(req.params.id)
    await pool.query(`UPDATE restaurant_tables SET ${fields.join(', ')} WHERE id = ?`, vals)
    const [[row]] = await pool.query('SELECT * FROM restaurant_tables WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE /api/restaurant/tables/:id
router.delete('/tables/:id', requireRole, async (req, res, next) => {
  try {
    const [[table]] = await pool.query('SELECT status FROM restaurant_tables WHERE id = ?', [req.params.id])
    if (!table) return res.status(404).json({ code: 404, message: '桌台不存在' })
    if (table.status !== 'idle') return res.status(400).json({ code: 400, message: '只能删除空闲状态的桌台' })
    await pool.query('DELETE FROM restaurant_tables WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// ===================== 菜品分类 =====================
// GET /api/restaurant/categories
router.get('/categories', requireRole, async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM dish_categories ORDER BY sort_order, id')
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

router.post('/categories', requireRole, async (req, res, next) => {
  try {
    const { name, sort_order } = req.body
    if (!name) return res.status(400).json({ code: 400, message: '分类名称必填' })
    const [result] = await pool.query('INSERT INTO dish_categories (name, sort_order) VALUES (?, ?)', [name, sort_order || 0])
    const [[row]] = await pool.query('SELECT * FROM dish_categories WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

router.put('/categories/:id', requireRole, async (req, res, next) => {
  try {
    const { name, sort_order, status } = req.body
    const fields = []
    const vals = []
    if (name) { fields.push('name = ?'); vals.push(name) }
    if (sort_order !== undefined) { fields.push('sort_order = ?'); vals.push(sort_order) }
    if (status) { fields.push('status = ?'); vals.push(status) }
    vals.push(req.params.id)
    await pool.query(`UPDATE dish_categories SET ${fields.join(', ')} WHERE id = ?`, vals)
    const [[row]] = await pool.query('SELECT * FROM dish_categories WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

router.delete('/categories/:id', requireRole, async (req, res, next) => {
  try {
    await pool.query('DELETE FROM dish_categories WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// ===================== 菜品管理 =====================
// GET /api/restaurant/dishes
router.get('/dishes', requireRole, async (req, res, next) => {
  try {
    const { category_id, is_available } = req.query
    let where = 'WHERE 1=1'
    const params = []
    if (category_id) { where += ' AND d.category_id = ?'; params.push(category_id) }
    if (is_available) { where += ' AND d.is_available = ?'; params.push(is_available) }
    const [rows] = await pool.query(
      `SELECT d.*, c.name as category_name
       FROM dishes d
       LEFT JOIN dish_categories c ON c.id = d.category_id
       ${where}
       ORDER BY c.sort_order, d.id`, params
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

router.post('/dishes', requireRole, async (req, res, next) => {
  try {
    const { name, category_id, unit, price, image, description, is_available } = req.body
    if (!name || !price) return res.status(400).json({ code: 400, message: '菜品名称和价格必填' })
    const [result] = await pool.query(
      'INSERT INTO dishes (name, category_id, unit, price, image, description, is_available) VALUES (?, ?, ?, ?, ?, ?, ?)',
      [name, category_id || null, unit || '份', price, image || null, description || null, is_available || 'yes']
    )
    const [[row]] = await pool.query('SELECT d.*, c.name as category_name FROM dishes d LEFT JOIN dish_categories c ON c.id = d.category_id WHERE d.id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

router.put('/dishes/:id', requireRole, async (req, res, next) => {
  try {
    const { name, category_id, unit, price, image, description, is_available } = req.body
    const fields = []
    const vals = []
    ;['name','category_id','unit','price','image','description','is_available'].forEach(f => {
      if (req.body[f] !== undefined) { fields.push(`${f} = ?`); vals.push(req.body[f]) }
    })
    if (!fields.length) return res.status(400).json({ code: 400, message: '无更新字段' })
    vals.push(req.params.id)
    await pool.query(`UPDATE dishes SET ${fields.join(', ')} WHERE id = ?`, vals)
    const [[row]] = await pool.query('SELECT d.*, c.name as category_name FROM dishes d LEFT JOIN dish_categories c ON c.id = d.category_id WHERE d.id = ?', [req.params.id])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

router.delete('/dishes/:id', requireRole, async (req, res, next) => {
  try {
    await pool.query('DELETE FROM dishes WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// ===================== 堂食点餐 =====================
// GET /api/restaurant/dine-orders
router.get('/dine-orders', requireRole, async (req, res, next) => {
  try {
    const { status, table_id, date_start, date_end } = req.query
    let where = 'WHERE 1=1'
    const params = []
    if (status) { where += ' AND o.status = ?'; params.push(status) }
    if (table_id) { where += ' AND o.table_id = ?'; params.push(table_id) }
    if (date_start) { where += ' AND DATE(o.created_at) >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND DATE(o.created_at) <= ?'; params.push(date_end) }
    const [rows] = await pool.query(
      `SELECT o.*, t.table_name, u.name as creator_name
       FROM dine_orders o
       LEFT JOIN restaurant_tables t ON t.id = o.table_id
       LEFT JOIN users u ON u.id = o.created_by
       ${where}
       ORDER BY o.created_at DESC`, params
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/restaurant/dine-orders - 开台/点餐
router.post('/dine-orders', requireRole, async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { table_id, customer_count, items = [], remark } = req.body
    if (!table_id) return res.status(400).json({ code: 400, message: '桌台ID必填' })
    if (!items || items.length === 0) return res.status(400).json({ code: 400, message: '菜品不能为空' })

    await conn.beginTransaction()

    // 占用桌台
    const [[table]] = await conn.query('SELECT * FROM restaurant_tables WHERE id = ?', [table_id])
    if (!table) { await conn.rollback(); return res.status(404).json({ code: 404, message: '桌台不存在' }) }
    if (table.status === 'occupied') { await conn.rollback(); return res.status(400).json({ code: 400, message: '桌台已被占用' }) }

    const order_no = generateNo('D')
    let total_amount = 0
    for (const item of items) {
      total_amount += parseFloat(item.price) * parseInt(item.number || 1)
    }

    const [result] = await conn.query(
      `INSERT INTO dine_orders (order_no, table_id, customer_count, total_amount, status, remark, created_by)
       VALUES (?, ?, ?, ?, 'ordering', ?, ?)`,
      [order_no, table_id, customer_count || 1, total_amount, remark || null, req.user.id]
    )
    const order_id = result.insertId

    for (const item of items) {
      const subtotal = parseFloat(item.price) * parseInt(item.number || 1)
      await conn.query(
        `INSERT INTO dine_order_items (order_id, dish_id, dish_name, unit, price, number, subtotal, remark)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [order_id, item.dish_id, item.dish_name, item.unit || '份', item.price, item.number || 1, subtotal, item.remark || null]
      )
    }

    await conn.query("UPDATE restaurant_tables SET status = 'occupied', current_order_id = ? WHERE id = ?", [order_id, table_id])

    await conn.commit()
    const [[order]] = await pool.query(
      `SELECT o.*, t.table_name FROM dine_orders o LEFT JOIN restaurant_tables t ON t.id = o.table_id WHERE o.id = ?`,
      [order_id]
    )
    const [orderItems] = await pool.query('SELECT * FROM dine_order_items WHERE order_id = ?', [order_id])
    res.json({ code: 0, data: { ...order, items: orderItems }, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// PUT /api/restaurant/dine-orders/:id - 状态流转
router.put('/dine-orders/:id/status', requireRole, async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { action } = req.body
    await conn.beginTransaction()
    const [[order]] = await conn.query('SELECT * FROM dine_orders WHERE id = ?', [req.params.id])
    if (!order) { await conn.rollback(); return res.status(404).json({ code: 404, message: '订单不存在' }) }

    let newStatus, setFields = [], allowedFrom = []

    if (action === 'confirm') {
      if (order.status !== 'ordering') { await conn.rollback(); return res.status(400).json({ code: 400, message: '只有点单中状态可以确认' }) }
      newStatus = 'confirmed'; setFields = ["status = 'confirmed'", 'confirmed_at = NOW()']
    } else if (action === 'prepare') {
      if (order.status !== 'confirmed') { await conn.rollback(); return res.status(400).json({ code: 400, message: '只有已确认状态可以开始制作' }) }
      newStatus = 'preparing'; setFields = ["status = 'preparing'"]
    } else if (action === 'serve') {
      if (order.status !== 'preparing') { await conn.rollback(); return res.status(400).json({ code: 400, message: '只有制作中状态可以上菜' }) }
      newStatus = 'served'; setFields = ["status = 'served'", 'served_at = NOW()']
    } else if (action === 'complete') {
      if (!['served', 'confirmed'].includes(order.status)) { await conn.rollback(); return res.status(400).json({ code: 400, message: 'only served or confirmed can complete' }) }
      newStatus = 'completed'; setFields = ["status = 'completed'", 'completed_at = NOW()']
      // 释放桌台
      await conn.query("UPDATE restaurant_tables SET status = 'idle', current_order_id = NULL WHERE id = ?", [order.table_id])
    } else if (action === 'cancel') {
      if (!['ordering', 'confirmed'].includes(order.status)) { await conn.rollback(); return res.status(400).json({ code: 400, message: 'cancel requires ordering or confirmed' }) }
      newStatus = 'cancelled'; setFields = ["status = 'cancelled'"]
      await conn.query("UPDATE restaurant_tables SET status = 'idle', current_order_id = NULL WHERE id = ?", [order.table_id])
    } else {
      await conn.rollback(); return res.status(400).json({ code: 400, message: '无效的 action' })
    }

    await conn.query(`UPDATE dine_orders SET ${setFields.join(', ')} WHERE id = ?`, [req.params.id])
    await conn.commit()
    const [[updated]] = await pool.query('SELECT * FROM dine_orders WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: updated, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ===================== 外卖订单 =====================
// GET /api/restaurant/takeout-orders
router.get('/takeout-orders', requireRole, async (req, res, next) => {
  try {
    const { status, date_start, date_end, keyword } = req.query
    let where = 'WHERE 1=1'
    const params = []
    if (status) { where += ' AND o.status = ?'; params.push(status) }
    if (date_start) { where += ' AND DATE(o.created_at) >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND DATE(o.created_at) <= ?'; params.push(date_end) }
    if (keyword) { where += ' AND (o.order_no LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)'; params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`) }
    const [rows] = await pool.query(
      `SELECT o.*, u.name as creator_name
       FROM takeout_orders o
       LEFT JOIN users u ON u.id = o.created_by
       ${where}
       ORDER BY o.created_at DESC`, params
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/restaurant/takeout-orders
router.post('/takeout-orders', requireRole, async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { customer_name, customer_phone, delivery_address, items = [], freight_amount = 0, discount_amount = 0, pay_type, remark } = req.body
    if (!customer_name || !customer_phone) return res.status(400).json({ code: 400, message: '客户姓名和电话必填' })
    if (!items || items.length === 0) return res.status(400).json({ code: 400, message: '菜品不能为空' })

    await conn.beginTransaction()
    const order_no = generateNo('T')
    let total_amount = 0
    for (const item of items) { total_amount += parseFloat(item.price) * parseInt(item.number || 1) }
    const pay_amount = total_amount + parseFloat(freight_amount) - parseFloat(discount_amount || 0)

    const [result] = await conn.query(
      `INSERT INTO takeout_orders (order_no, customer_name, customer_phone, delivery_address, total_amount, freight_amount, discount_amount, pay_amount, pay_type, status, remark, created_by)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)`,
      [order_no, customer_name, customer_phone, delivery_address || null, total_amount, freight_amount, discount_amount || 0, pay_amount, pay_type || null, remark || null, req.user.id]
    )
    const order_id = result.insertId

    for (const item of items) {
      const subtotal = parseFloat(item.price) * parseInt(item.number || 1)
      await conn.query(
        `INSERT INTO takeout_order_items (order_id, dish_id, dish_name, unit, price, number, subtotal)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [order_id, item.dish_id || null, item.dish_name, item.unit || '份', item.price, item.number || 1, subtotal]
      )
    }

    await conn.commit()
    const [[order]] = await pool.query('SELECT * FROM takeout_orders WHERE id = ?', [order_id])
    const [orderItems] = await pool.query('SELECT * FROM takeout_order_items WHERE order_id = ?', [order_id])
    res.json({ code: 0, data: { ...order, items: orderItems }, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// PUT /api/restaurant/takeout-orders/:id/status
router.put('/takeout-orders/:id/status', requireRole, async (req, res, next) => {
  try {
    const { action } = req.body
    const [[order]] = await pool.query('SELECT * FROM takeout_orders WHERE id = ?', [req.params.id])
    if (!order) return res.status(404).json({ code: 404, message: '订单不存在' })

    let newStatus, setFields = []
    if (action === 'confirm') {
      if (order.status !== 'pending') return res.status(400).json({ code: 400, message: '只有待确认可以确认接单' })
      newStatus = 'confirmed'; setFields = ["status = 'confirmed'", 'confirmed_at = NOW()']
    } else if (action === 'prepare') {
      if (order.status !== 'confirmed') return res.status(400).json({ code: 400, message: '只有已确认可以开始制作' })
      newStatus = 'preparing'; setFields = ["status = 'preparing'"]
    } else if (action === 'deliver') {
      if (order.status !== 'preparing') return res.status(400).json({ code: 400, message: '只有制作中可以开始配送' })
      newStatus = 'delivering'; setFields = ["status = 'delivering'"]
    } else if (action === 'complete') {
      if (order.status !== 'delivering') return res.status(400).json({ code: 400, message: '只有配送中可以完成' })
      newStatus = 'completed'; setFields = ["status = 'completed'", 'completed_at = NOW()']
    } else if (action === 'cancel') {
      if (!['pending','confirmed'].includes(order.status)) return res.status(400).json({ code: 400, message: '只能取消待确认或已确认订单' })
      newStatus = 'cancelled'; setFields = ["status = 'cancelled'"]
    } else {
      return res.status(400).json({ code: 400, message: '无效的 action' })
    }

    await pool.query(`UPDATE takeout_orders SET ${setFields.join(', ')} WHERE id = ?`, [req.params.id])
    const [[updated]] = await pool.query('SELECT * FROM takeout_orders WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: updated, message: 'ok' })
  } catch (err) { next(err) }
})

// ===================== 预订管理 =====================
// GET /api/restaurant/reservations
router.get('/reservations', requireRole, async (req, res, next) => {
  try {
    const { status, date } = req.query
    let where = 'WHERE 1=1'
    const params = []
    if (status) { where += ' AND r.status = ?'; params.push(status) }
    if (date) { where += ' AND r.reserve_date = ?'; params.push(date) }
    const [rows] = await pool.query(
      `SELECT r.*, t.table_name, u.name as creator_name
       FROM reservations r
       LEFT JOIN restaurant_tables t ON t.id = r.table_id
       LEFT JOIN users u ON u.id = r.created_by
       ${where}
       ORDER BY r.reserve_date, r.reserve_time`, params
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/restaurant/reservations
router.post('/reservations', requireRole, async (req, res, next) => {
  try {
    const { customer_name, customer_phone, table_id, people_count, reserve_date, reserve_time, remark } = req.body
    if (!customer_name || !customer_phone || !reserve_date || !reserve_time) {
      return res.status(400).json({ code: 400, message: '客户姓名、电话、预订日期和时间必填' })
    }
    const reserve_no = generateNo('R')
    const [result] = await pool.query(
      `INSERT INTO reservations (reserve_no, customer_name, customer_phone, table_id, people_count, reserve_date, reserve_time, remark, created_by)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [reserve_no, customer_name, customer_phone, table_id || null, people_count || 1, reserve_date, reserve_time, remark || null, req.user.id]
    )
    const [[row]] = await pool.query(
      `SELECT r.*, t.table_name FROM reservations r LEFT JOIN restaurant_tables t ON t.id = r.table_id WHERE r.id = ?`,
      [result.insertId]
    )
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/restaurant/reservations/:id/status
router.put('/reservations/:id/status', requireRole, async (req, res, next) => {
  try {
    const { action } = req.body
    const [[resv]] = await pool.query('SELECT * FROM reservations WHERE id = ?', [req.params.id])
    if (!resv) return res.status(404).json({ code: 404, message: '预订不存在' })

    let newStatus, setFields = []
    if (action === 'confirm') {
      if (resv.status !== 'pending') return res.status(400).json({ code: 400, message: '只有待确认可以确认' })
      newStatus = 'confirmed'; setFields = ["status = 'confirmed'", 'confirmed_at = NOW()']
      if (resv.table_id) await pool.query("UPDATE restaurant_tables SET status = 'reserved' WHERE id = ?", [resv.table_id])
    } else if (action === 'arrive') {
      if (resv.status !== 'confirmed') return res.status(400).json({ code: 400, message: '只有已确认可以报到' })
      newStatus = 'arrived'; setFields = ["status = 'arrived'"]
      if (resv.table_id) await pool.query("UPDATE restaurant_tables SET status = 'occupied' WHERE id = ?", [resv.table_id])
    } else if (action === 'cancel') {
      if (resv.status === 'arrived') return res.status(400).json({ code: 400, message: '已到店不能取消' })
      newStatus = 'cancelled'; setFields = ["status = 'cancelled'"]
      if (resv.table_id) await pool.query("UPDATE restaurant_tables SET status = 'idle' WHERE id = ? AND status = 'reserved'", [resv.table_id])
    } else if (action === 'no_show') {
      if (resv.status !== 'confirmed') return res.status(400).json({ code: 400, message: '只有已确认可以标记失约' })
      newStatus = 'no_show'; setFields = ["status = 'no_show'"]
      if (resv.table_id) await pool.query("UPDATE restaurant_tables SET status = 'idle' WHERE id = ? AND status = 'reserved'", [resv.table_id])
    } else {
      return res.status(400).json({ code: 400, message: '无效的 action' })
    }

    await pool.query(`UPDATE reservations SET ${setFields.join(', ')} WHERE id = ?`, [req.params.id])
    const [[updated]] = await pool.query('SELECT * FROM reservations WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: updated, message: 'ok' })
  } catch (err) { next(err) }
})

// ===================== 排队叫号 =====================
// GET /api/restaurant/queue
router.get('/queue', requireRole, async (req, res, next) => {
  try {
    const { status } = req.query
    let where = 'WHERE 1=1'
    const params = []
    if (status) { where += ' AND q.status = ?'; params.push(status) }
    const [rows] = await pool.query(
      `SELECT q.*, t.table_name
       FROM queue_tickets q
       LEFT JOIN restaurant_tables t ON t.id = q.table_id
       ${where}
       ORDER BY q.position`, params
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/restaurant/queue - 取号
router.post('/queue', requireRole, async (req, res, next) => {
  try {
    const { customer_name, customer_phone, people_count } = req.body
    const [[last]] = await pool.query('SELECT MAX(position) as maxPos FROM queue_tickets WHERE status = ?', ['waiting'])
    const position = (last.maxPos || 0) + 1
    const ticket_no = `Q${String(position).padStart(4, '0')}`
    const [result] = await pool.query(
      `INSERT INTO queue_tickets (ticket_no, customer_name, customer_phone, people_count, position, status)
       VALUES (?, ?, ?, ?, ?, 'waiting')`,
      [ticket_no, customer_name || null, customer_phone || null, people_count || 1, position]
    )
    const [[row]] = await pool.query('SELECT * FROM queue_tickets WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/restaurant/queue/:id -叫号/入座
router.put('/queue/:id', requireRole, async (req, res, next) => {
  try {
    const { action, table_id } = req.body
    const [[ticket]] = await pool.query('SELECT * FROM queue_tickets WHERE id = ?', [req.params.id])
    if (!ticket) return res.status(404).json({ code: 404, message: '排队号不存在' })

    if (action === 'call') {
      if (ticket.status !== 'waiting') return res.status(400).json({ code: 400, message: '只能叫号等待中的号码' })
      await pool.query("UPDATE queue_tickets SET status = 'called', called_at = NOW() WHERE id = ?", [req.params.id])
    } else if (action === 'serve') {
      if (ticket.status !== 'called') return res.status(400).json({ code: 400, message: '只能入座已叫号的号码' })
      if (!table_id) return res.status(400).json({ code: 400, message: '需要指定桌台' })
      const [[table]] = await pool.query('SELECT * FROM restaurant_tables WHERE id = ?', [table_id])
      if (!table) return res.status(404).json({ code: 404, message: '桌台不存在' })
      if (table.status === 'occupied') return res.status(400).json({ code: 400, message: '桌台已被占用' })
      await pool.query("UPDATE queue_tickets SET status = 'served', served_at = NOW(), table_id = ? WHERE id = ?", [table_id, req.params.id])
      await pool.query("UPDATE restaurant_tables SET status = 'occupied' WHERE id = ?", [table_id])
    } else if (action === 'cancel') {
      if (!['waiting','called'].includes(ticket.status)) return res.status(400).json({ code: 400, message: '无法取消' })
      await pool.query("UPDATE queue_tickets SET status = 'cancelled' WHERE id = ?", [req.params.id])
    } else {
      return res.status(400).json({ code: 400, message: '无效的 action' })
    }

    const [[updated]] = await pool.query('SELECT * FROM queue_tickets WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: updated, message: 'ok' })
  } catch (err) { next(err) }
})

// ===================== 收银 =====================
// GET /api/restaurant/cashier
router.get('/cashier', requireRole, async (req, res, next) => {
  try {
    const { date_start, date_end, pay_type } = req.query
    let where = 'WHERE 1=1'
    const params = []
    if (date_start) { where += ' AND DATE(c.created_at) >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND DATE(c.created_at) <= ?'; params.push(date_end) }
    if (pay_type) { where += ' AND c.pay_type = ?'; params.push(pay_type) }
    const [rows] = await pool.query(
      `SELECT c.*, u.name as operator_name
       FROM cashier_records c
       LEFT JOIN users u ON u.id = c.operator_id
       ${where}
       ORDER BY c.created_at DESC`, params
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/restaurant/cashier - 收银结账
router.post('/cashier', requireRole, async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { order_type, order_id, received_amount, pay_type, discount_amount = 0 } = req.body
    if (!order_type || !order_id || !received_amount || !pay_type) {
      return res.status(400).json({ code: 400, message: '缺少必填字段' })
    }

    await conn.beginTransaction()

    let total_amount = 0
    if (order_type === 'dine') {
      const [[order]] = await conn.query('SELECT * FROM dine_orders WHERE id = ?', [order_id])
      if (!order) { await conn.rollback(); return res.status(404).json({ code: 404, message: '堂食订单不存在' }) }
      if (order.status === 'completed') { await conn.rollback(); return res.status(400).json({ code: 400, message: '订单已结账' }) }
      total_amount = order.pay_amount || order.total_amount
      await conn.query("UPDATE dine_orders SET status = 'completed', completed_at = NOW(), pay_amount = ?, pay_type = ?, discount_amount = ? WHERE id = ?",
        [total_amount - discount_amount, pay_type, discount_amount, order_id])
      await conn.query("UPDATE restaurant_tables SET status = 'idle', current_order_id = NULL WHERE id = ?", [order.table_id])
    } else if (order_type === 'takeout') {
      const [[order]] = await conn.query('SELECT * FROM takeout_orders WHERE id = ?', [order_id])
      if (!order) { await conn.rollback(); return res.status(404).json({ code: 404, message: '外卖订单不存在' }) }
      if (order.status === 'completed') { await conn.rollback(); return res.status(400).json({ code: 400, message: '订单已结账' }) }
      total_amount = order.pay_amount
      await conn.query("UPDATE takeout_orders SET status = 'completed', completed_at = NOW() WHERE id = ?", [order_id])
    } else {
      await conn.rollback(); return res.status(400).json({ code: 400, message: '无效的订单类型' })
    }

    const receivable = total_amount - discount_amount
    const change = Math.max(0, parseFloat(received_amount) - receivable)
    const record_no = generateNo('P')

    const [result] = await conn.query(
      `INSERT INTO cashier_records (record_no, order_type, order_id, total_amount, discount_amount, receivable_amount, received_amount, change_amount, pay_type, operator_id)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [record_no, order_type, order_id, total_amount, discount_amount, receivable, received_amount, change, pay_type, req.user.id]
    )

    await conn.commit()
    const [[row]] = await pool.query('SELECT * FROM cashier_records WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

export default router