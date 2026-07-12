import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

function generateOrderNo() {
  const d = new Date()
  const ds = d.toISOString().slice(0, 10).replace(/-/g, '')
  const ts = String(d.getHours()).padStart(2, '0') + String(d.getMinutes()).padStart(2, '0') + String(d.getSeconds()).padStart(2, '0')
  const r = String(Math.floor(Math.random() * 10000)).padStart(4, '0')
  return `PRE${ds}${ts}${r}`
}

// GET /api/preorder/stores - 列出当前用户可下单的门店（按 user_stores 关联过滤，admin 看全部）
router.get('/stores', requirePermission(PERMISSIONS.PREORDER_READ), async (req, res) => {
  try {
    const { region } = req.query
    const userId = req.user?.id
    const isAdmin = req.user?.role === 'admin'

    let rows
    if (isAdmin) {
      // admin 看全部门店
      const where = region ? 'WHERE city LIKE ?' : 'WHERE 1=1'
      const params = region ? [`%${region}%`] : []
      ;[rows] = await pool.query(
        `SELECT id, name, store_code, city FROM stores ${where} ORDER BY city, name`,
        params
      )
    } else {
      // 普通员工只看自己关联的门店
      const where = region ? 'AND s.city LIKE ?' : ''
      const params = region ? [userId, `%${region}%`] : [userId]
      ;[rows] = await pool.query(
        `SELECT s.id, s.name, s.store_code, s.city
         FROM stores s
         JOIN user_stores us ON us.store_id = s.id
         WHERE us.user_id = ? ${where}
         ORDER BY s.city, s.name`,
        params
      )
    }
    res.json({ code: 0, data: rows })
  } catch (e) { res.status(500).json({ code: 500, message: e.message }) }
})

// GET /api/preorder/products - 列出所有可订产品（status=active，按关键字过滤）
router.get('/products', requirePermission(PERMISSIONS.PREORDER_READ), async (req, res) => {
  try {
    const { keyword } = req.query
    let where = "WHERE status = 'active'"
    const params = []
    if (keyword) {
      where += ' AND (name LIKE ? OR sku LIKE ?)'
      params.push(`%${keyword}%`, `%${keyword}%`)
    }
    const [rows] = await pool.query(
      `SELECT id, sku, name, spec, sale_price, image_main FROM products ${where} ORDER BY name`,
      params
    )
    res.json({ code: 0, data: rows })
  } catch (e) { res.status(500).json({ code: 500, message: e.message }) }
})

// POST /api/preorder/create - 创建门店预订单
router.post('/create', requirePermission(PERMISSIONS.PREORDER_CREATE), async (req, res) => {
  const conn = await pool.getConnection()
  try {
    const { store_id, product_id, sku_id, quantity, box_qty, remark } = req.body
    if (!store_id || !product_id || quantity == null) {
      return res.status(400).json({ code: 400, message: 'store_id/product_id/quantity required' })
    }
    const order_no = generateOrderNo()
    const member_id = req.user?.id || 0
    const member_name = req.user?.name || 'store_user'
    await conn.beginTransaction()
    const [r1] = await conn.query(
      `INSERT INTO online_orders (order_no, user_id, user_name, store_id, store_name, type, total_qty, total_amount, status, remark)
       VALUES (?, ?, ?, ?, ?, 'store_preorder', ?, 0, 'draft', ?)`,
      [order_no, member_id, member_name, store_id, '', quantity, remark || '']
    )
    const orderId = r1.insertId
    await conn.query(
      `INSERT INTO store_order_items (order_id, store_id, product_id, sku_id, quantity, box_qty, remark)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [orderId, store_id, product_id, sku_id || null, quantity, box_qty || 0, remark || '']
    )
    await conn.commit()
    res.json({ code: 0, data: { order_no, id: orderId } })
  } catch (e) {
    await conn.rollback()
    res.status(500).json({ code: 500, message: e.message })
  } finally {
    conn.release()
  }
})

// GET /api/preorder/list - 预订单列表（按批次 group）
router.get('/list', requirePermission(PERMISSIONS.PREORDER_READ), async (req, res) => {
  try {
    const { status, date_start, date_end } = req.query
    const { page, size } = parsePagination(req.query)
    let where = "WHERE o.type = 'store_preorder'"
    const params = []
    if (status) { where += ' AND o.status = ?'; params.push(status) }
    if (date_start) { where += ' AND DATE(o.created_at) >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND DATE(o.created_at) <= ?'; params.push(date_end) }

    const [countRows] = await pool.query(`SELECT COUNT(*) AS total FROM online_orders o ${where}`, params)
    const [rows] = await pool.query(
      `SELECT o.id, o.order_no, o.status, o.created_at, o.remark,
              s.name AS store_name, s.store_code, s.city,
              soi.product_id, soi.quantity, soi.box_qty,
              p.name AS product_name, p.spec, p.sale_price
       FROM online_orders o
       JOIN store_order_items soi ON soi.order_id = o.id
       LEFT JOIN stores s ON s.id = o.store_id
       LEFT JOIN products p ON p.id = soi.product_id
       ${where}
       ORDER BY o.created_at DESC
       LIMIT ? OFFSET ?`,
      [...params, size, (page - 1) * size]
    )
    res.json({ code: 0, data: { list: rows, total: countRows[0].total } })
  } catch (e) { res.status(500).json({ code: 500, message: e.message }) }
})

// GET /api/preorder/aggregate?product_id=22 - 汇总表：按产品 group，门店 × SKU 矩阵
router.get('/aggregate', requirePermission(PERMISSIONS.PREORDER_AGGREGATE), async (req, res) => {
  try {
    const { product_id, date_start, date_end } = req.query
    if (!product_id) return res.status(400).json({ code: 400, message: 'product_id required' })

    let where = "WHERE o.type = 'store_preorder' AND soi.product_id = ?"
    const params = [product_id]
    if (date_start) { where += ' AND DATE(o.created_at) >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND DATE(o.created_at) <= ?'; params.push(date_end) }

    const [stores] = await pool.query(
      `SELECT s.id, s.name, s.store_code FROM stores s
       WHERE s.city LIKE '%菲律宾%'
       ORDER BY s.id`
    )
    const [items] = await pool.query(
      `SELECT soi.store_id, soi.quantity, soi.box_qty, o.order_no, o.created_at
       FROM store_order_items soi
       JOIN online_orders o ON o.id = soi.order_id
       ${where}
       ORDER BY soi.store_id, o.created_at`,
      params
    )

    // 构建矩阵：stores × items
    const matrix = stores.map(s => {
      const storeItems = items.filter(i => i.store_id === s.id)
      const qty = storeItems.reduce((sum, i) => sum + i.quantity, 0)
      const box = storeItems.reduce((sum, i) => sum + i.box_qty, 0)
      return {
        store_id: s.id, store_name: s.name, store_code: s.store_code,
        quantity: qty, box_qty: box,
        orders: storeItems.map(i => ({ order_no: i.order_no, qty: i.quantity, box: i.box_qty, at: i.created_at }))
      }
    })

    const totals = {
      total_qty: matrix.reduce((s, m) => s + m.quantity, 0),
      total_box: matrix.reduce((s, m) => s + m.box_qty, 0),
      store_count: matrix.filter(m => m.quantity > 0).length
    }

    res.json({ code: 0, data: { stores: matrix, totals } })
  } catch (e) { res.status(500).json({ code: 500, message: e.message }) }
})

export default router
