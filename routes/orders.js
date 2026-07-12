import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { ROLES } from '../middleware/rbac.js'

const router = Router()

// 角色检查中间件
function requireRole(req, res, next) {
  const allowed = [ROLES.ADMIN, ROLES.MANAGER, ROLES.SUPERADMIN, ROLES.OPERATOR]
  if (!req.user || !allowed.includes(req.user.role)) {
    return res.status(403).json({ code: 403, message: '无权限访问' })
  }
  next()
}

// 生成订单号
function generateOrderNo() {
  const date = new Date()
  const dateStr = date.toISOString().slice(0, 10).replace(/-/g, '')
  const timeStr = String(date.getHours()).padStart(2, '0') + String(date.getMinutes()).padStart(2, '0') + String(date.getSeconds()).padStart(2, '0')
  const random = String(Math.floor(Math.random() * 10000)).padStart(4, '0')
  return `ORD${dateStr}${timeStr}${random}`
}

// GET /api/orders - 订单列表（分页+多条件筛选）
router.get('/', requireRole, async (req, res, next) => {
  try {
    const { status, keyword, date_start, date_end, member_id } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (status) {
      where += ' AND o.status = ?'
      params.push(status)
      countParams.push(status)
    }
    if (member_id) {
      where += ' AND o.member_id = ?'
      params.push(member_id)
      countParams.push(member_id)
    }
    if (date_start) {
      where += ' AND DATE(o.created_at) >= ?'
      params.push(date_start)
      countParams.push(date_start)
    }
    if (date_end) {
      where += ' AND DATE(o.created_at) <= ?'
      params.push(date_end)
      countParams.push(date_end)
    }
    if (keyword) {
      where += ' AND (o.order_no LIKE ? OR o.member_name LIKE ? OR o.member_phone LIKE ?)'
      const kw = `%${keyword}%`
      params.push(kw, kw, kw)
      countParams.push(kw, kw, kw)
    }

    const sql = `
      SELECT o.*
      FROM orders o
      ${where}
      ORDER BY o.created_at DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(*) as total FROM orders o ${where}`

    const [[{ total }]] = await pool.query(countSql, countParams)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/orders/:id - 订单详情（含商品明细）
router.get('/:id', requireRole, async (req, res, next) => {
  try {
    const [[order]] = await pool.query('SELECT * FROM orders WHERE id = ?', [req.params.id])
    if (!order) {
      return res.status(404).json({ code: 404, message: '订单不存在' })
    }
    const [items] = await pool.query(
      'SELECT * FROM order_items WHERE order_id = ?',
      [req.params.id]
    )
    res.json({ code: 0, data: { ...order, items }, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/orders/:id/status - 状态流转
router.put('/:id/status', requireRole, async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { action } = req.body
    if (!action) return res.status(400).json({ code: 400, message: 'action 必填' })

    const allowedRoles = [ROLES.ADMIN, ROLES.MANAGER, ROLES.SUPERADMIN, ROLES.OPERATOR]
    if (!allowedRoles.includes(req.user.role)) {
      return res.status(403).json({ code: 403, message: '无权限操作' })
    }

    await conn.beginTransaction()

    const [[order]] = await conn.query('SELECT * FROM orders WHERE id = ?', [req.params.id])
    if (!order) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '订单不存在' })
    }

    let newStatus = null
    let setFields = []

    if (action === 'pay') {
      // 待支付 -> 已支付
      if (order.status !== 'pending_pay') {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: '只有待支付状态可以确认支付' })
      }
      newStatus = 'paid'
      setFields.push("status = 'paid'", 'paid_at = NOW()')
    } else if (action === 'ship') {
      // 已支付 -> 已发货
      if (order.status !== 'paid') {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: '只有已支付状态可以发货' })
      }
      newStatus = 'shipped'
      setFields.push("status = 'shipped'", 'shipped_at = NOW()')
    } else if (action === 'complete') {
      // 已发货 -> 已完成
      if (order.status !== 'shipped') {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: '只有已发货状态可以确认收货' })
      }
      newStatus = 'completed'
      setFields.push("status = 'completed'", 'completed_at = NOW()')
    } else if (action === 'cancel') {
      // 待支付/已支付 -> 已取消
      if (!['pending_pay', 'paid'].includes(order.status)) {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: '只有待支付或已支付状态可以取消' })
      }
      newStatus = 'cancelled'
      setFields.push("status = 'cancelled'")
    } else if (action === 'refund') {
      // 已支付/已发货 -> 已退款（需要管理员确认）
      if (!['paid', 'shipped'].includes(order.status)) {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: '只有已支付或已发货状态可以退款' })
      }
      newStatus = 'refunded'
      setFields.push("status = 'refunded'")
    } else {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '无效的 action' })
    }

    await conn.query(`UPDATE orders SET ${setFields.join(', ')} WHERE id = ?`, [req.params.id])
    await conn.commit()

    const [[updated]] = await pool.query('SELECT * FROM orders WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: updated, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// POST /api/orders - 后台手动创建订单
router.post('/', requireRole, async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const allowedRoles = [ROLES.ADMIN, ROLES.MANAGER, ROLES.SUPERADMIN, ROLES.OPERATOR]
    if (!allowedRoles.includes(req.user.role)) {
      return res.status(403).json({ code: 403, message: '无权限创建订单' })
    }

    const { member_id, member_name, member_phone, items = [], freight_amount = 0, discount_amount = 0, remark, pay_type, wechat_trade_no, admin_remark } = req.body

    if (!items || !Array.isArray(items) || items.length === 0) {
      return res.status(400).json({ code: 400, message: '订单商品不能为空' })
    }

    await conn.beginTransaction()

    // 计算订单金额
    let total_amount = 0
    for (const item of items) {
      const { product_id, price, number = 1 } = item
      if (!product_id || !price) {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: '商品ID和价格必填' })
      }
      const subtotal = parseFloat(price) * parseInt(number)
      total_amount += subtotal
    }

    const pay_amount = parseFloat(total_amount) + parseFloat(freight_amount) - parseFloat(discount_amount || 0)
    const order_no = generateOrderNo()

    // 插入订单
    const [result] = await conn.query(
      `INSERT INTO orders (order_no, member_id, member_name, member_phone, total_amount, freight_amount, discount_amount, pay_amount, pay_type, wechat_trade_no, status, remark, admin_remark)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_pay', ?, ?)`,
      [order_no, member_id || null, member_name || null, member_phone || null, total_amount, freight_amount, discount_amount || 0, pay_amount, pay_type || null, wechat_trade_no || null, remark || null, admin_remark || null]
    )

    const order_id = result.insertId

    // 插入订单商品
    for (const item of items) {
      const { product_id, product_name, product_spec, product_image, price, number = 1 } = item
      const subtotal = parseFloat(price) * parseInt(number)

      // 获取商品信息（如果没提供名称）
      let name = product_name
      if (!name) {
        const [[prod]] = await conn.query('SELECT name FROM products WHERE id = ?', [product_id])
        name = prod ? prod.name : ''
      }

      await conn.query(
        `INSERT INTO order_items (order_id, product_id, product_name, product_spec, product_image, price, number, subtotal)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [order_id, product_id, name, product_spec || null, product_image || null, price, number, subtotal]
      )
    }

    await conn.commit()

    const [[order]] = await pool.query('SELECT * FROM orders WHERE id = ?', [order_id])
    const [orderItems] = await pool.query('SELECT * FROM order_items WHERE order_id = ?', [order_id])

    res.json({ code: 0, data: { ...order, items: orderItems }, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// DELETE /api/orders/:id - 作废订单（仅 pending_pay 可操作）
router.delete('/:id', requireRole, async (req, res, next) => {
  try {
    const [[order]] = await pool.query('SELECT * FROM orders WHERE id = ?', [req.params.id])
    if (!order) {
      return res.status(404).json({ code: 404, message: '订单不存在' })
    }
    if (order.status !== 'pending_pay') {
      return res.status(400).json({ code: 400, message: '只有待支付状态的订单可以作废' })
    }
    await pool.query("UPDATE orders SET status = 'cancelled' WHERE id = ?", [req.params.id])
    res.json({ code: 0, data: null, message: '订单已作废' })
  } catch (err) { next(err) }
})

export default router