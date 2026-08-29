import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// GET /api/workbuddy/orders/summary - 订单统计（今日/本周/本月）
router.get('/orders/summary', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [[today]] = await pool.query(`
      SELECT COUNT(*) AS cnt, COALESCE(SUM(pay_amount),0) AS amt
      FROM orders WHERE DATE(created_at) = CURDATE()
    `).catch(() => [[{ cnt: 0, amt: 0 }]])
    const [[week]] = await pool.query(`
      SELECT COUNT(*) AS cnt, COALESCE(SUM(pay_amount),0) AS amt
      FROM orders WHERE YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1)
    `).catch(() => [[{ cnt: 0, amt: 0 }]])
    const [[month]] = await pool.query(`
      SELECT COUNT(*) AS cnt, COALESCE(SUM(pay_amount),0) AS amt
      FROM orders WHERE DATE_FORMAT(created_at,'%Y%m') = DATE_FORMAT(CURDATE(),'%Y%m')
    `).catch(() => [[{ cnt: 0, amt: 0 }]])
    res.json({
      today: { count: Number(today.cnt), amount: Number(today.amt) },
      week: { count: Number(week.cnt), amount: Number(week.amt) },
      month: { count: Number(month.cnt), amount: Number(month.amt) },
      currency: 'CNY',
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/orders/search?q=关键词 - 订单搜索（必须在 /orders/:id 之前定义，避免被 :id 捕获）
router.get('/orders/search', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const q = String(req.query.q || '').trim()
    if (!q) return res.status(400).json({ error: 'q required' })
    const like = `%${q}%`
    const [rows] = await pool.query(`
      SELECT id, order_no, member_name, member_phone, pay_amount, status, created_at
      FROM orders
      WHERE order_no LIKE ? OR member_name LIKE ? OR member_phone LIKE ?
      ORDER BY created_at DESC LIMIT 30
    `, [like, like, like])
    res.json({
      query: q,
      orders: rows.map(r => ({
        id: r.id, order_no: r.order_no, customer: r.member_name, phone: r.member_phone,
        paid: Number(r.pay_amount)||0, status: r.status, created_at: r.created_at,
      })),
      count: rows.length,
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/orders/recent?limit=10 - 最新订单列表
router.get('/orders/recent', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const limit = Math.min(Number(req.query.limit) || 10, 50)
    const [rows] = await pool.query(`
      SELECT id, order_no, member_name, member_phone, total_amount, pay_amount,
             pay_type, status, created_at
      FROM orders
      ORDER BY created_at DESC, id DESC LIMIT ?
    `, [limit])
    res.json({
      orders: rows.map(r => ({
        id: r.id, order_no: r.order_no, customer: r.member_name, phone: r.member_phone,
        total: Number(r.total_amount)||0, paid: Number(r.pay_amount)||0,
        pay_type: r.pay_type, status: r.status, created_at: r.created_at,
      })),
      count: rows.length,
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/orders/:id - 订单详情（含明细）— 定义在 /search /recent 之后
router.get('/orders/:id', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const id = Number(req.params.id)
    if (!id) return res.status(400).json({ error: 'invalid id' })
    const [[o]] = await pool.query(`SELECT * FROM orders WHERE id=?`, [id])
    if (!o) return res.status(404).json({ error: 'order not found' })
    const [items] = await pool.query(`
      SELECT product_name, product_spec, price, number, subtotal
      FROM order_items WHERE order_id=? ORDER BY id
    `, [id]).catch(() => [[]])
    res.json({
      order: {
        id: o.id, order_no: o.order_no, customer: o.member_name, phone: o.member_phone,
        total_amount: Number(o.total_amount)||0, freight: Number(o.freight_amount)||0,
        discount: Number(o.discount_amount)||0, pay_amount: Number(o.pay_amount)||0,
        pay_type: o.pay_type, status: o.status, remark: o.remark,
        paid_at: o.paid_at, shipped_at: o.shipped_at, created_at: o.created_at,
      },
      items: (items||[]).map(i => ({
        product_name: i.product_name, spec: i.product_spec,
        price: Number(i.price)||0, qty: Number(i.number)||0,
        subtotal: Number(i.subtotal)||0,
      })),
    })
  } catch (err) { next(err) }
})

export default router