import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requirePermission, requireRole, PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

// ===== 工具函数 =====

// 生成采购单号 CG-YYYYMMDD-NNNN
async function genOrderNo(conn) {
  const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '')
  for (let attempt = 0; attempt < 3; attempt++) {
    const [[{ cnt }]] = await conn.query(
      "SELECT COUNT(*) as cnt FROM material_purchase_orders WHERE order_no LIKE ?",
      [`CG-${dateStr}%`]
    )
    const orderNo = `CG-${dateStr}-${String(cnt + 1 + attempt).padStart(4, '0')}`
    const [[{ dupCnt }]] = await conn.query(
      "SELECT COUNT(*) as dupCnt FROM material_purchase_orders WHERE order_no = ?",
      [orderNo]
    )
    if (!dupCnt) return orderNo
  }
  throw new Error('Failed to generate unique order_no')
}

// 写操作日志
async function writeLog(conn, orderId, action, fromStatus, toStatus, operator, comment = '') {
  await conn.query(
    `INSERT INTO material_purchase_logs
       (order_id, action, from_status, to_status, operator_id, operator_name, comment)
     VALUES (?,?,?,?,?,?,?)`,
    [
      orderId, action, fromStatus, toStatus,
      operator?.id || null, operator?.name || '',
      comment || ''
    ]
  )
}

// 到货入库（精简版，不走一物一码，直接 +stock）
async function autoInbound(conn, order) {
  const items = await conn.query(
    'SELECT * FROM material_purchase_items WHERE order_id = ?',
    [order.id]
  )
  const itemRows = items[0]
  if (!itemRows.length) throw new Error('采购单无明细，无法入库')

  const operator = order.applicant_name || '系统'
  const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '')
  let recordNo
  for (let attempt = 0; attempt < 3; attempt++) {
    const [[{ cnt }]] = await conn.query(
      "SELECT COUNT(*) as cnt FROM inbound_records WHERE record_no LIKE ?",
      [`RK-${dateStr}%`]
    )
    recordNo = `RK-${dateStr}-${String(cnt + 1 + attempt).padStart(4, '0')}`
    try {
      const totalQty = itemRows.reduce((s, i) => s + i.quantity, 0)
      const [recordRes] = await conn.query(
        `INSERT INTO inbound_records (record_no, warehouse_id, supplier, total_qty, operator, status, remark)
         VALUES (?,?,?,?,?,?,?)`,
        [recordNo, order.warehouse_id, order.supplier || '', totalQty, operator, 'completed',
         `自动入库 — 采购单 ${order.order_no}`]
      )
      const inboundRecordId = recordRes.insertId

      for (const it of itemRows) {
        // INSERT inbound_item
        await conn.query(
          `INSERT INTO inbound_items (record_id, product_id, sku_id, qrcode_count, quantity)
           VALUES (?,?,?,?,?)`,
          [inboundRecordId, it.product_id, it.sku_id || null, 0, it.quantity]
        )
        // UPSERT warehouse_stock (累加，不创建新行)
        const [[existing]] = await conn.query(
          `SELECT id, quantity FROM warehouse_stock
           WHERE warehouse_id=? AND product_id=? AND ${it.sku_id ? 'sku_id=?' : 'sku_id IS NULL'}
           FOR UPDATE`,
          it.sku_id
            ? [order.warehouse_id, it.product_id, it.sku_id]
            : [order.warehouse_id, it.product_id]
        )
        if (existing) {
          await conn.query(
            `UPDATE warehouse_stock SET quantity = quantity + ? WHERE id = ?`,
            [it.quantity, existing.id]
          )
        } else {
          await conn.query(
            `INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,?,?)`,
            [order.warehouse_id, it.product_id, it.sku_id || null, it.quantity]
          )
        }
        // products.stock 累加
        await conn.query(
          'UPDATE products SET stock = COALESCE(stock, 0) + ? WHERE id = ?',
          [it.quantity, it.product_id]
        )
      }
      return inboundRecordId
    } catch (err) {
      if (err.code === 'ER_DUP_ENTRY' && attempt < 2) continue
      throw err
    }
  }
}

// ===== 列表 =====

router.get('/', requirePermission(PERMISSIONS.MATERIAL_PURCHASE_READ), async (req, res, next) => {
  try {
    const { status, page = 1, size = 20, applicant_id } = req.query
    const offset = (parseInt(page) - 1) * parseInt(size)
    const where = ['1=1']
    const params = []
    if (status) { where.push('o.status = ?'); params.push(status) }
    if (applicant_id) { where.push('o.applicant_id = ?'); params.push(applicant_id) }

    const whereSql = where.join(' AND ')
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM material_purchase_orders o WHERE ${whereSql}`,
      params
    )
    const [rows] = await pool.query(
      `SELECT o.*, w.name as warehouse_name
       FROM material_purchase_orders o
       LEFT JOIN warehouses w ON w.id = o.warehouse_id
       WHERE ${whereSql}
       ORDER BY o.created_at DESC LIMIT ? OFFSET ?`,
      [...params, parseInt(size), offset]
    )
    res.json({ code: 0, data: { rows, total, page: parseInt(page), size: parseInt(size) } })
  } catch (err) { next(err) }
})

// 我的申请
router.get('/my', requirePermission(PERMISSIONS.MATERIAL_PURCHASE_READ), async (req, res, next) => {
  try {
    const userId = req.user?.id
    if (!userId) return res.status(401).json({ code: 401, message: '未登录' })
    const [rows] = await pool.query(
      `SELECT o.*, w.name as warehouse_name
       FROM material_purchase_orders o
       LEFT JOIN warehouses w ON w.id = o.warehouse_id
       WHERE o.applicant_id = ?
       ORDER BY o.created_at DESC`,
      [userId]
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// 待审批列表
router.get('/pending-approval', requirePermission(PERMISSIONS.MATERIAL_PURCHASE_APPROVE), async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT o.*, w.name as warehouse_name, u.name as applicant_display_name
       FROM material_purchase_orders o
       LEFT JOIN warehouses w ON w.id = o.warehouse_id
       LEFT JOIN users u ON u.id = o.applicant_id
       WHERE o.status = 'pending_approval'
       ORDER BY o.urgency DESC, o.created_at ASC`
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// 详情
router.get('/:id', requirePermission(PERMISSIONS.MATERIAL_PURCHASE_READ), async (req, res, next) => {
  try {
    const { id } = req.params
    const [[order]] = await pool.query(
      `SELECT o.*, w.name as warehouse_name
       FROM material_purchase_orders o
       LEFT JOIN warehouses w ON w.id = o.warehouse_id
       WHERE o.id = ?`,
      [id]
    )
    if (!order) return res.status(404).json({ code: 404, message: '采购单不存在' })
    const [items] = await pool.query(
      'SELECT * FROM material_purchase_items WHERE order_id = ?',
      [id]
    )
    const [[{ inbound_record_no }]] = await pool.query(
      `SELECT record_no as inbound_record_no FROM inbound_records WHERE id = ?`,
      [order.inbound_record_id || 0]
    ).catch(() => [[{ inbound_record_no: null }]])
    const [logs] = await pool.query(
      'SELECT * FROM material_purchase_logs WHERE order_id = ? ORDER BY created_at ASC',
      [id]
    )
    res.json({ code: 0, data: { ...order, items, logs, inbound_record_no } })
  } catch (err) { next(err) }
})

// ===== 创建草稿 =====
router.post('/', requirePermission(PERMISSIONS.MATERIAL_PURCHASE_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { title, items, supplier, supplier_id, warehouse_id, urgency, expected_date, remark } = req.body
    const user = req.user || {}
    if (!title || !items?.length) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '主题和明细必填' })
    }
    const orderNo = await genOrderNo(conn)
    const totalAmount = items.reduce((s, i) => s + (i.quantity || 0) * (i.unit_price || 0), 0)

    const [orderRes] = await conn.query(
      `INSERT INTO material_purchase_orders
        (order_no, title, applicant_id, applicant_name, department, supplier, supplier_id, warehouse_id,
         total_amount, urgency, status, expected_date, remark)
       VALUES (?,?,?,?,?,?,?,?,?,?,'draft',?,?)`,
      [
        orderNo, title, user.id || null, user.name || '',
        user.department || '', supplier || '', supplier_id || null, warehouse_id || null,
        totalAmount, urgency || 'normal',
        expected_date || null, remark || ''
      ]
    )
    const orderId = orderRes.insertId

    for (const it of items) {
      const subtotal = (it.quantity || 0) * (it.unit_price || 0)
      await conn.query(
        `INSERT INTO material_purchase_items
          (order_id, product_id, product_name, sku_id, quantity, unit_price, subtotal, remark)
         VALUES (?,?,?,?,?,?,?,?)`,
        [orderId, it.product_id, it.product_name || '', it.sku_id || null,
         it.quantity || 1, it.unit_price || 0, subtotal, it.remark || '']
      )
    }
    await writeLog(conn, orderId, 'create', null, 'draft', user, '创建草稿')
    await conn.commit()
    res.json({ code: 0, data: { id: orderId, order_no: orderNo } })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// ===== 提交审批 =====
router.post('/:id/submit', requirePermission(PERMISSIONS.MATERIAL_PURCHASE_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { id } = req.params
    const user = req.user || {}
    const [[order]] = await conn.query('SELECT * FROM material_purchase_orders WHERE id = ? FOR UPDATE', [id])
    if (!order) { await conn.rollback(); return res.status(404).json({ code: 404, message: '采购单不存在' }) }
    if (!['draft', 'rejected'].includes(order.status)) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `当前状态 ${order.status} 不可提交` })
    }
    await conn.query(
      "UPDATE material_purchase_orders SET status = 'pending_approval' WHERE id = ?",
      [id]
    )
    await writeLog(conn, id, 'submit', order.status, 'pending_approval', user, req.body.comment || '')
    await conn.commit()
    res.json({ code: 0, message: '已提交审批' })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// ===== 审批通过 =====
router.post('/:id/approve', requirePermission(PERMISSIONS.MATERIAL_PURCHASE_APPROVE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { id } = req.params
    const user = req.user || {}
    const [[order]] = await conn.query('SELECT * FROM material_purchase_orders WHERE id = ? FOR UPDATE', [id])
    if (!order) { await conn.rollback(); return res.status(404).json({ code: 404, message: '采购单不存在' }) }
    if (order.status !== 'pending_approval') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `当前状态 ${order.status} 不可审批` })
    }
    await conn.query(
      "UPDATE material_purchase_orders SET status = 'approved' WHERE id = ?",
      [id]
    )
    await writeLog(conn, id, 'approve', order.status, 'approved', user, req.body.comment || '审批通过')
    await conn.commit()
    res.json({ code: 0, message: '审批通过' })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// ===== 审批驳回 =====
router.post('/:id/reject', requirePermission(PERMISSIONS.MATERIAL_PURCHASE_APPROVE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { id } = req.params
    const user = req.user || {}
    const [[order]] = await conn.query('SELECT * FROM material_purchase_orders WHERE id = ? FOR UPDATE', [id])
    if (!order) { await conn.rollback(); return res.status(404).json({ code: 404, message: '采购单不存在' }) }
    if (order.status !== 'pending_approval') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `当前状态 ${order.status} 不可驳回` })
    }
    await conn.query(
      "UPDATE material_purchase_orders SET status = 'rejected' WHERE id = ?",
      [id]
    )
    await writeLog(conn, id, 'reject', order.status, 'rejected', user, req.body.comment || '驳回')
    await conn.commit()
    res.json({ code: 0, message: '已驳回' })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// ===== 标记采购中 =====
router.post('/:id/start-purchase', requirePermission(PERMISSIONS.MATERIAL_PURCHASE_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { id } = req.params
    const user = req.user || {}
    const [[order]] = await conn.query('SELECT * FROM material_purchase_orders WHERE id = ? FOR UPDATE', [id])
    if (!order) { await conn.rollback(); return res.status(404).json({ code: 404, message: '采购单不存在' }) }
    if (!['approved', 'purchasing'].includes(order.status)) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `当前状态 ${order.status} 不可开始采购` })
    }
    await conn.query(
      "UPDATE material_purchase_orders SET status = 'purchasing' WHERE id = ?",
      [id]
    )
    await writeLog(conn, id, 'purchase', order.status, 'purchasing', user, req.body.comment || '开始采购')
    await conn.commit()
    res.json({ code: 0, message: '已开始采购' })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// ===== 到货入库 =====
router.post('/:id/arrive', requirePermission(PERMISSIONS.MATERIAL_PURCHASE_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { id } = req.params
    const user = req.user || {}
    const [[order]] = await conn.query('SELECT * FROM material_purchase_orders WHERE id = ? FOR UPDATE', [id])
    if (!order) { await conn.rollback(); return res.status(404).json({ code: 404, message: '采购单不存在' }) }
    if (!['purchasing', 'approved'].includes(order.status)) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `当前状态 ${order.status} 不可到货入库` })
    }
    if (!order.warehouse_id) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '采购单未指定目标仓库' })
    }
    const inboundRecordId = await autoInbound(conn, order)
    await conn.query(
      `UPDATE material_purchase_orders
       SET status = 'completed', arrived_at = NOW(), inbound_record_id = ?
       WHERE id = ?`,
      [inboundRecordId, id]
    )
    await writeLog(conn, id, 'arrive', order.status, 'completed', user,
      `到货入库, 入库单 ID=${inboundRecordId}`)
    await conn.commit()
    res.json({ code: 0, message: '到货入库完成', data: { inbound_record_id: inboundRecordId } })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// ===== 取消 =====
router.post('/:id/cancel', requirePermission(PERMISSIONS.MATERIAL_PURCHASE_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { id } = req.params
    const user = req.user || {}
    const [[order]] = await conn.query('SELECT * FROM material_purchase_orders WHERE id = ? FOR UPDATE', [id])
    if (!order) { await conn.rollback(); return res.status(404).json({ code: 404, message: '采购单不存在' }) }
    if (['completed', 'cancelled'].includes(order.status)) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `当前状态 ${order.status} 不可取消` })
    }
    await conn.query(
      "UPDATE material_purchase_orders SET status = 'cancelled' WHERE id = ?",
      [id]
    )
    await writeLog(conn, id, 'cancel', order.status, 'cancelled', user, req.body.comment || '取消')
    await conn.commit()
    res.json({ code: 0, message: '已取消' })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// ===== 删除草稿 =====
router.delete('/:id', requireRole('admin'), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { id } = req.params
    const [[order]] = await conn.query('SELECT * FROM material_purchase_orders WHERE id = ? FOR UPDATE', [id])
    if (!order) { await conn.rollback(); return res.status(404).json({ code: 404, message: '采购单不存在' }) }
    if (order.status !== 'draft') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '只有草稿可删' })
    }
    await conn.query('DELETE FROM material_purchase_items WHERE order_id = ?', [id])
    await conn.query('DELETE FROM material_purchase_logs WHERE order_id = ?', [id])
    await conn.query('DELETE FROM material_purchase_orders WHERE id = ?', [id])
    await conn.commit()
    res.json({ code: 0, message: '已删除' })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

export default router
