// qrcode-customer-admin.js — 一物一码顾客订单管理（后台）
// 2026-08-14 — admin/manager 角色权限
// 公开 C 端接口（扫码+下单）在 qrcode-customer.js 里

import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requireRole } from '../middleware/rbac.js'

const router = Router()

// GET /api/admin/qrcode-customer/orders — 看所有顾客订单（可筛选）
router.get('/orders', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { status, keyword, page = 1, size = 20 } = req.query
    const limitNum = Math.min(Math.max(1, Number(size) || 20), 200)
    const offsetNum = (Math.max(1, Number(page) || 1) - 1) * limitNum

    let sql = `SELECT co.*, p.name as product_name, p.image_main, p.sku as product_sku
               FROM qrcode_customer_orders co
               LEFT JOIN qrcodes q ON co.qrcode_id = q.id
               LEFT JOIN products p ON q.product_id = p.id
               WHERE 1=1`
    const params = []

    if (status) {
      sql += ' AND co.status = ?'
      params.push(status)
    }
    if (keyword) {
      sql += ' AND (co.order_no LIKE ? OR co.qrcode_code LIKE ? OR p.name LIKE ?)'
      params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`)
    }

    // 总数
    let countQ = `SELECT COUNT(*) as total FROM qrcode_customer_orders co
                  LEFT JOIN qrcodes q ON co.qrcode_id = q.id
                  LEFT JOIN products p ON q.product_id = p.id
                  WHERE 1=1`
    const countParams = []
    if (status) { countQ += ' AND co.status = ?'; countParams.push(status) }
    if (keyword) {
      countQ += ' AND (co.order_no LIKE ? OR co.qrcode_code LIKE ? OR p.name LIKE ?)'
      countParams.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`)
    }
    const [[{ total }]] = await pool.query(countQ, countParams)

    sql += ' ORDER BY co.created_at DESC LIMIT ? OFFSET ?'
    params.push(limitNum, offsetNum)

    const [rows] = await pool.query(sql, params)

    res.json({
      code: 0,
      data: { list: rows, total, page: Number(page), size: limitNum },
      message: 'ok',
    })
  } catch (err) { next(err) }
})

// GET /api/admin/qrcode-customer/orders/stats — 订单统计
router.get('/orders/stats', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT
        COUNT(*) as total_orders,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status='confirmed' THEN 1 ELSE 0 END) as confirmed_count,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled_count,
        COALESCE(SUM(CASE WHEN status='confirmed' THEN customer_amount ELSE 0 END), 0) as confirmed_amount,
        COALESCE(SUM(customer_amount), 0) as total_amount
      FROM qrcode_customer_orders
    `)
    res.json({ code: 0, data: rows[0], message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/admin/qrcode-customer/orders/:id/confirm — 确认收款（事务：扣库存 + 改 qrcode 状态）
router.post('/orders/:id/confirm', requireRole('admin', 'manager'), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const orderId = req.params.id

    await conn.beginTransaction()

    const [[order]] = await conn.query(
      'SELECT id, order_no, qrcode_id, quantity, status FROM qrcode_customer_orders WHERE id = ? FOR UPDATE',
      [orderId]
    )
    if (!order) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '订单不存在' })
    }
    if (order.status !== 'pending') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `订单当前状态为 ${order.status}，无法确认` })
    }

    const [[qr]] = await conn.query(
      'SELECT id, status, batch_mode, batch_quantity, remaining_qty, sku_id, product_id, warehouse_id FROM qrcodes WHERE id = ? FOR UPDATE',
      [order.qrcode_id]
    )
    if (!qr) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '关联二维码不存在' })
    }

    let newStatus = qr.status
    if (qr.status === 'bindProduct' || qr.status === 'inStock' || qr.status === 'shipped') {
      newStatus = 'sold'
    }
    let newRemaining = qr.batch_mode === 'batch' ? qr.remaining_qty - order.quantity : 0
    if (qr.batch_mode === 'batch' && newRemaining <= 0) {
      newStatus = 'sold_out'
    }

    await conn.query(
      'UPDATE qrcodes SET status = ?, remaining_qty = ? WHERE id = ?',
      [newStatus, newRemaining, qr.id]
    )

    if (qr.sku_id && qr.warehouse_id) {
      await conn.query(
        'UPDATE warehouse_stock SET quantity = GREATEST(0, quantity - ?) WHERE warehouse_id = ? AND product_id = ? AND sku_id = ?',
        [order.quantity, qr.warehouse_id, qr.product_id, qr.sku_id]
      )
    }

    await conn.query(
      `INSERT INTO qrcode_sales (qrcode_id, quantity, buyer, sales_person, status_after, created_at)
       VALUES (?, ?, ?, ?, ?, NOW())`,
      [qr.id, order.quantity, '扫码顾客', req.user?.name || req.user?.phone || '系统', newStatus]
    )

    await conn.query(
      `UPDATE qrcode_customer_orders
       SET status = 'confirmed', confirmed_at = NOW(), confirmed_by = ?
       WHERE id = ?`,
      [req.user?.name || req.user?.phone || 'admin', orderId]
    )

    await conn.commit()

    res.json({
      code: 0,
      data: {
        order_id: orderId,
        order_no: order.order_no,
        qrcode_status: newStatus,
        remaining_qty: newRemaining,
      },
      message: '已确认收款，库存已扣减',
    })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// POST /api/admin/qrcode-customer/orders/:id/cancel — 取消订单
router.post('/orders/:id/cancel', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { reason } = req.body
    const [[order]] = await pool.query('SELECT status FROM qrcode_customer_orders WHERE id = ?', [req.params.id])
    if (!order) return res.status(404).json({ code: 404, message: '订单不存在' })
    if (order.status !== 'pending') return res.status(400).json({ code: 400, message: `当前状态 ${order.status}，无法取消` })

    await pool.query(
      `UPDATE qrcode_customer_orders
       SET status = 'cancelled', cancel_reason = ?, confirmed_by = ?
       WHERE id = ?`,
      [reason || null, req.user?.name || 'admin', req.params.id]
    )
    res.json({ code: 0, message: '已取消' })
  } catch (err) { next(err) }
})

export default router
