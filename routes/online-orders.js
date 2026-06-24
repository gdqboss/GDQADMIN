/**
 * 线上订单管理 API
 * 流程：员工创建订单 → reviewer 审核 → dispatcher 制单 → 出库
 *
 * 角色权限：
 *   - 任何有 order:create 的角色：创建草稿
 *   - reviewer (order:review)：pending → approved/rejected
 *   - dispatcher (order:dispatch)：approved → dispatched（生成 dispatch_no + 关联出库）
 *   - member (order:read_own)：看自己
 *   - admin/manager (order:read_all)：看全部
 */

import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { ROLES, PERMISSIONS, requirePermission, hasPermission } from '../middleware/rbac.js'

const router = Router()

// 通用角色检查
function requireAnyRole(req, res, next) {
  if (!req.user) {
    return res.status(401).json({ code: 401, message: '未登录' })
  }
  const allowed = [ROLES.ADMIN, ROLES.MANAGER, ROLES.SUPERADMIN, ROLES.OPERATOR, ROLES.REVIEWER, ROLES.DISPATCHER]
  if (!allowed.includes(req.user.role)) {
    return res.status(403).json({ code: 403, message: '无权限访问' })
  }
  next()
}

// 生成订单号 ORD + yyyymmdd + HHMMSS + 4位随机
function generateOrderNo() {
  const date = new Date()
  const dateStr = date.toISOString().slice(0, 10).replace(/-/g, '')
  const timeStr = String(date.getHours()).padStart(2, '0') +
                  String(date.getMinutes()).padStart(2, '0') +
                  String(date.getSeconds()).padStart(2, '0')
  const random = String(Math.floor(Math.random() * 10000)).padStart(4, '0')
  return `ORD${dateStr}${timeStr}${random}`
}

// 生成送货单号 DSP + yyyymmdd + HHMMSS + 4位随机
function generateDispatchNo() {
  const date = new Date()
  const dateStr = date.toISOString().slice(0, 10).replace(/-/g, '')
  const timeStr = String(date.getHours()).padStart(2, '0') +
                  String(date.getMinutes()).padStart(2, '0') +
                  String(date.getSeconds()).padStart(2, '0')
  const random = String(Math.floor(Math.random() * 10000)).padStart(4, '0')
  return `DSP${dateStr}${timeStr}${random}`
}

// 记录订单日志（异步，失败不阻断主流程）
async function logOrder(orderId, action, fromStatus, toStatus, operator, comment = null) {
  try {
    await pool.execute(
      `INSERT INTO online_order_logs (order_id, action, from_status, to_status, operator_id, operator_name, comment)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [orderId, action, fromStatus, toStatus, operator.id, operator.name, comment]
    )
  } catch (err) {
    console.error('[online-orders] log failed:', err.message)
  }
}

// ─── GET /api/online-orders 订单列表 ──────────────────────────────────────
router.get('/', requireAnyRole, async (req, res, next) => {
  try {
    const { status, keyword, date_start, date_end, supplier_id, dealer_id, store_id } = req.query
    const { page, size } = parsePagination(req.query)
    const offset = (page - 1) * size

    let where = 'WHERE 1=1'
    const params = []

    // 权限过滤：普通用户只能看自己
    const isAll = req.user.role === ROLES.ADMIN || req.user.role === ROLES.MANAGER || req.user.role === ROLES.SUPERADMIN
    if (!isAll && hasPermission(req.user, PERMISSIONS.ORDER_READ_ALL)) {
      // 有读全部权限（含 reviewer/dispatcher 看 pending/approved 单）
    } else if (!isAll) {
      where += ' AND o.user_id = ?'
      params.push(req.user.id)
    }

    if (status) {
      where += ' AND o.status = ?'
      params.push(status)
    }
    if (keyword) {
      where += ' AND (o.order_no LIKE ? OR o.user_name LIKE ?)'
      const kw = `%${keyword}%`
      params.push(kw, kw)
    }
    if (date_start) {
      where += ' AND o.created_at >= ?'
      params.push(date_start)
    }
    if (date_end) {
      where += ' AND o.created_at <= ?'
      params.push(date_end)
    }
    if (supplier_id) {
      where += ' AND o.supplier_id = ?'
      params.push(supplier_id)
    }
    if (dealer_id) {
      where += ' AND o.dealer_id = ?'
      params.push(dealer_id)
    }
    if (store_id) {
      where += ' AND o.store_id = ?'
      params.push(store_id)
    }

    const [rows] = await pool.query(
      `SELECT o.* FROM online_orders o ${where} ORDER BY o.id DESC LIMIT ? OFFSET ?`,
      [...params, size, offset]
    )
    const [cnt] = await pool.query(
      `SELECT COUNT(*) as total FROM online_orders o ${where}`,
      params
    )

    res.json({ code: 0, data: { list: rows, total: cnt[0].total, page, size } })
  } catch (err) {
    next(err)
  }
})

// ─── GET /api/online-orders/stats 订单统计（按状态分组） ──────────────────
router.get('/stats', requireAnyRole, async (req, res, next) => {
  try {
    let where = 'WHERE 1=1'
    const params = []
    const isAll = req.user.role === ROLES.ADMIN || req.user.role === ROLES.MANAGER || req.user.role === ROLES.SUPERADMIN
    if (!isAll && !hasPermission(req.user, PERMISSIONS.ORDER_READ_ALL)) {
      where += ' AND user_id = ?'
      params.push(req.user.id)
    }
    const [rows] = await pool.query(
      `SELECT status, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as amount FROM online_orders ${where} GROUP BY status`,
      params
    )
    const byStatus = {}
    let total = 0, totalAmount = 0
    rows.forEach(r => {
      byStatus[r.status] = { count: r.count, amount: parseFloat(r.amount) }
      total += r.count
      totalAmount += parseFloat(r.amount)
    })
    res.json({ code: 0, data: { by_status: byStatus, total, total_amount: totalAmount } })
  } catch (err) {
    next(err)
  }
})

// ─── GET /api/online-orders/:id 订单详情（含明细） ─────────────────────────
router.get('/:id', requireAnyRole, async (req, res, next) => {
  try {
    const { id } = req.params
    const [orders] = await pool.query('SELECT * FROM online_orders WHERE id = ?', [id])
    if (orders.length === 0) {
      return res.status(404).json({ code: 404, message: '订单不存在' })
    }
    const order = orders[0]

    // 权限检查
    const isAll = req.user.role === ROLES.ADMIN || req.user.role === ROLES.MANAGER || req.user.role === ROLES.SUPERADMIN
    if (!isAll && !hasPermission(req.user, PERMISSIONS.ORDER_READ_ALL) && order.user_id !== req.user.id) {
      return res.status(403).json({ code: 403, message: '无权查看该订单' })
    }

    const [items] = await pool.query('SELECT * FROM online_order_items WHERE order_id = ?', [id])
    const [logs] = await pool.query('SELECT * FROM online_order_logs WHERE order_id = ? ORDER BY created_at DESC', [id])

    res.json({ code: 0, data: { order, items, logs } })
  } catch (err) {
    next(err)
  }
})

// ─── POST /api/online-orders 创建订单 ─────────────────────────────────────
router.post('/', requireAnyRole, async (req, res, next) => {
  try {
    const { supplier_id, dealer_id, store_id, delivery_address, delivery_contact, delivery_phone, remark, items } = req.body

    if (!items || !Array.isArray(items) || items.length === 0) {
      return res.status(400).json({ code: 400, message: '订单至少需要一个商品' })
    }

    // 取供应商/经销商/门店名称（可空）
    let supplier_name = null, dealer_name = null, store_name = null
    if (supplier_id) {
      const [r] = await pool.query('SELECT name FROM suppliers WHERE id = ?', [supplier_id])
      supplier_name = r[0]?.name || null
    }
    if (dealer_id) {
      const [r] = await pool.query('SELECT name FROM dealers WHERE id = ?', [dealer_id])
      dealer_name = r[0]?.name || null
    }
    if (store_id) {
      const [r] = await pool.query('SELECT name FROM stores WHERE id = ?', [store_id])
      store_name = r[0]?.name || null
    }

    // 计算总额
    let total_qty = 0, total_amount = 0
    for (const it of items) {
      total_qty += parseInt(it.quantity || 0)
      total_amount += parseFloat(it.unit_price || 0) * parseInt(it.quantity || 0)
    }

    const order_no = generateOrderNo()
    const [result] = await pool.execute(
      `INSERT INTO online_orders (order_no, user_id, user_name, supplier_id, supplier_name, dealer_id, dealer_name, store_id, store_name, total_qty, total_amount, status, remark, delivery_address, delivery_contact, delivery_phone)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)`,
      [order_no, req.user.id, req.user.name, supplier_id || null, supplier_name, dealer_id || null, dealer_name, store_id || null, store_name, total_qty, total_amount.toFixed(2), remark || null, delivery_address || null, delivery_contact || null, delivery_phone || null]
    )
    const orderId = result.insertId

    // 插入明细
    for (const it of items) {
      const subtotal = parseFloat(it.unit_price || 0) * parseInt(it.quantity || 0)
      await pool.execute(
        `INSERT INTO online_order_items (order_id, product_id, product_sku_id, product_name, product_image, product_model, product_color, product_size, sku_code, unit_price, quantity, subtotal, remark)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [orderId, it.product_id, it.product_sku_id || null, it.product_name || '', it.product_image || null, it.product_model || null, it.product_color || null, it.product_size || null, it.sku_code || null, parseFloat(it.unit_price || 0), parseInt(it.quantity || 0), subtotal.toFixed(2), it.remark || null]
      )
    }

    // 写日志
    await logOrder(orderId, 'create', null, 'pending', { id: req.user.id, name: req.user.name }, '订单创建')

    res.json({ code: 0, data: { id: orderId, order_no } })
  } catch (err) {
    next(err)
  }
})

// ─── PUT /api/online-orders/:id/submit 草稿 → 提交审核 ────────────────────
router.put('/:id/submit', requireAnyRole, async (req, res, next) => {
  try {
    const { id } = req.params
    const [orders] = await pool.query('SELECT * FROM online_orders WHERE id = ?', [id])
    if (orders.length === 0) {
      return res.status(404).json({ code: 404, message: '订单不存在' })
    }
    const order = orders[0]
    if (order.user_id !== req.user.id && ![ROLES.ADMIN, ROLES.MANAGER].includes(req.user.role)) {
      return res.status(403).json({ code: 403, message: '只能提交自己的订单' })
    }
    if (order.status !== 'draft') {
      return res.status(400).json({ code: 400, message: `订单状态为 ${order.status}，无需提交` })
    }
    await pool.execute(`UPDATE online_orders SET status = 'pending', updated_at = NOW() WHERE id = ?`, [id])
    await logOrder(parseInt(id), 'submit', 'draft', 'pending', { id: req.user.id, name: req.user.name }, req.body?.comment || null)
    res.json({ code: 0, data: { id: parseInt(id), status: 'pending' } })
  } catch (err) {
    next(err)
  }
})

// ─── PUT /api/online-orders/:id/review 审核（reviewer） ────────────────────
router.put('/:id/review', requireAnyRole, async (req, res, next) => {
  try {
    const { id } = req.params
    const { action, remark } = req.body  // action: 'approve' | 'reject'
    if (!['approve', 'reject'].includes(action)) {
      return res.status(400).json({ code: 400, message: 'action 必须是 approve 或 reject' })
    }
    // 权限：必须有 order:review 权限
    if (![ROLES.ADMIN, ROLES.MANAGER, ROLES.REVIEWER].includes(req.user.role) && !hasPermission(req.user, PERMISSIONS.ORDER_REVIEW)) {
      return res.status(403).json({ code: 403, message: '无审核权限' })
    }
    const [orders] = await pool.query('SELECT * FROM online_orders WHERE id = ?', [id])
    if (orders.length === 0) {
      return res.status(404).json({ code: 404, message: '订单不存在' })
    }
    const order = orders[0]
    if (order.status !== 'pending') {
      return res.status(400).json({ code: 400, message: `订单状态为 ${order.status}，无法审核` })
    }
    const newStatus = action === 'approve' ? 'approved' : 'rejected'
    await pool.execute(
      `UPDATE online_orders SET status = ?, reviewer_id = ?, reviewer_name = ?, review_remark = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ?`,
      [newStatus, req.user.id, req.user.name, remark || null, id]
    )
    await logOrder(parseInt(id), `review_${action}`, 'pending', newStatus, { id: req.user.id, name: req.user.name }, remark || null)
    res.json({ code: 0, data: { id: parseInt(id), status: newStatus } })
  } catch (err) {
    next(err)
  }
})

// ─── PUT /api/online-orders/:id/dispatch 制单（dispatcher） ────────────────
router.put('/:id/dispatch', requireAnyRole, async (req, res, next) => {
  try {
    const { id } = req.params
    const { delivery_address, delivery_contact, delivery_phone, outbound_id, remark } = req.body
    if (![ROLES.ADMIN, ROLES.MANAGER, ROLES.DISPATCHER].includes(req.user.role) && !hasPermission(req.user, PERMISSIONS.ORDER_DISPATCH)) {
      return res.status(403).json({ code: 403, message: '无制单权限' })
    }
    const [orders] = await pool.query('SELECT * FROM online_orders WHERE id = ?', [id])
    if (orders.length === 0) {
      return res.status(404).json({ code: 404, message: '订单不存在' })
    }
    const order = orders[0]
    if (order.status !== 'approved') {
      return res.status(400).json({ code: 400, message: `订单状态为 ${order.status}，无法制单` })
    }
    const dispatch_no = generateDispatchNo()
    await pool.execute(
      `UPDATE online_orders SET status = 'dispatched', dispatch_no = ?, dispatcher_id = ?, dispatcher_name = ?, delivery_address = ?, delivery_contact = ?, delivery_phone = ?, outbound_id = ?, dispatched_at = NOW(), updated_at = NOW() WHERE id = ?`,
      [dispatch_no, req.user.id, req.user.name, delivery_address || order.delivery_address, delivery_contact || order.delivery_contact, delivery_phone || order.delivery_phone, outbound_id || null, id]
    )
    await logOrder(parseInt(id), 'dispatch', 'approved', 'dispatched', { id: req.user.id, name: req.user.name }, remark || null)
    res.json({ code: 0, data: { id: parseInt(id), status: 'dispatched', dispatch_no } })
  } catch (err) {
    next(err)
  }
})

// ─── PUT /api/online-orders/:id/cancel 取消订单 ────────────────────────────
router.put('/:id/cancel', requireAnyRole, async (req, res, next) => {
  try {
    const { id } = req.params
    const [orders] = await pool.query('SELECT * FROM online_orders WHERE id = ?', [id])
    if (orders.length === 0) {
      return res.status(404).json({ code: 404, message: '订单不存在' })
    }
    const order = orders[0]
    // 只能取消自己的或 admin/manager
    if (order.user_id !== req.user.id && ![ROLES.ADMIN, ROLES.MANAGER].includes(req.user.role)) {
      return res.status(403).json({ code: 403, message: '只能取消自己的订单' })
    }
    // 已制单/已完成的不能取消
    if (['dispatched', 'completed'].includes(order.status)) {
      return res.status(400).json({ code: 400, message: `订单状态为 ${order.status}，无法取消` })
    }
    await pool.execute(`UPDATE online_orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?`, [id])
    await logOrder(parseInt(id), 'cancel', order.status, 'cancelled', { id: req.user.id, name: req.user.name }, req.body?.remark || null)
    res.json({ code: 0, data: { id: parseInt(id), status: 'cancelled' } })
  } catch (err) {
    next(err)
  }
})

export default router