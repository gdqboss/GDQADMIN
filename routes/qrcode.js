import { Router } from 'express'
import { pool } from '../db/connection.js'
import QRCode from 'qrcode'
import { mkdir } from 'fs/promises'
import path from 'path'
import { fileURLToPath } from 'url'
import { parsePagination } from '../utils/pagination.js'
import { requireRole, requirePermission, PERMISSIONS } from '../middleware/rbac.js'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const router = Router()

// 合法状态流转映射
// 批码（batch_mode='batch'）：sold 后 remaining_qty>0 保持 sold，扣到 0 自动转 sold_out
// 单码（batch_mode='single'）：sold 即终态（除走售后）
const VALID_TRANSITIONS = {
  unused:      ['bindProduct', 'disabled'],
  bindProduct: ['inStock', 'unused', 'disabled'],
  inStock:     ['outStock', 'bindProduct', 'disabled'],
  outStock:    ['shipped', 'inStock', 'disabled'],
  shipped:     ['sold', 'inStock', 'disabled'],
  sold:        ['activated', 'afterSale', 'sold_out', 'inStock', 'disabled'],
  sold_out:    ['returned', 'disabled'],
  activated:   ['afterSale', 'disabled'],
  afterSale:   ['sold', 'activated', 'returned', 'disabled'],
  returned:    ['inStock', 'disabled'],
  disabled:    [],
}

router.get('/', async (req, res, next) => {
  try {
    const { status, product_id, keyword, category, date_start, date_end } = req.query
    const { page, size } = parsePagination(req.query)
    let sql = `SELECT q.*,
      p.name as product_name, p.sku, p.category as product_category,
      ps.sku as sku_code, ps.specs as sku_specs
      FROM qrcodes q
      LEFT JOIN products p ON q.product_id = p.id
      LEFT JOIN product_skus ps ON q.sku_id = ps.id
      WHERE 1=1`
    let countSql = 'SELECT COUNT(*) as total FROM qrcodes q LEFT JOIN products p ON q.product_id = p.id LEFT JOIN product_skus ps ON q.sku_id = ps.id WHERE 1=1'
    const params = [], countParams = []

    // Supplier data isolation
    if (req.user.supplier_id) {
      const [[sup]] = await pool.query('SELECT name FROM suppliers WHERE id = ?', [req.user.supplier_id])
      if (sup) {
        sql += ' AND p.supplier = ?'
        countSql += ' AND p.supplier = ?'
        params.push(sup.name)
        countParams.push(sup.name)
      }
    }

    if (status) { sql += ' AND q.status = ?'; countSql += ' AND q.status = ?'; params.push(status); countParams.push(status) }
    if (product_id) { sql += ' AND q.product_id = ?'; countSql += ' AND q.product_id = ?'; params.push(product_id); countParams.push(product_id) }
    if (keyword) { sql += ' AND (q.code LIKE ? OR p.name LIKE ?)'; countSql += ' AND (q.code LIKE ? OR p.name LIKE ?)'; params.push(`%${keyword}%`, `%${keyword}%`); countParams.push(`%${keyword}%`, `%${keyword}%`) }
    if (category) { sql += ' AND p.category = ?'; countSql += ' AND p.category = ?'; params.push(category); countParams.push(category) }
    if (date_start) { sql += ' AND DATE(q.created_at) >= ?'; countSql += ' AND DATE(q.created_at) >= ?'; params.push(date_start); countParams.push(date_start) }
    if (date_end) { sql += ' AND DATE(q.created_at) <= ?'; countSql += ' AND DATE(q.created_at) <= ?'; params.push(date_end); countParams.push(date_end) }
    sql += ' ORDER BY q.created_at DESC LIMIT ? OFFSET ?'
    params.push(size, (page - 1) * size)
    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)
    const list = rows.map(r => ({ ...r, image_url: `/uploads/qrcodes/${r.code}.png` }))
    res.json({ code: 0, data: { list, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/qrcodes/by-code/:code — 通过二维码编号查询（扫码销售用）
router.get('/by-code/:code', async (req, res, next) => {
  try {
    const [[qr]] = await pool.query(
      `SELECT q.*, p.name, p.sku, p.spec, p.sale_price, p.purchase_price, p.image_main,
              p.id as product_id_ref
       FROM qrcodes q
       LEFT JOIN products p ON q.product_id = p.id
       WHERE q.code = ?`,
      [req.params.code]
    )
    if (!qr) return res.status(404).json({ code: 404, message: '二维码不存在' })
    // 组装 product 对象给前端
    const product = qr.product_id ? {
      id: qr.product_id,
      name: qr.name,
      sku: qr.sku,
      spec: qr.spec,
      sale_price: qr.sale_price,
      purchase_price: qr.purchase_price,
      image_main: qr.image_main
    } : null
    res.json({ code: 0, data: { id: qr.id, qr_code: qr.code, status: qr.status, product_id: qr.product_id, product }, message: 'ok' })
  } catch (err) { next(err) }
})

router.post('/batch', async (req, res, next) => {
  try {
    let { count = 10 } = req.body
    count = Math.min(Math.max(1, Number(count) || 10), 500)
    const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '')
    const [[{ maxSeq }]] = await pool.query(
      "SELECT COUNT(*) as maxSeq FROM qrcodes WHERE code LIKE ?", [`GDQ-${dateStr}%`]
    )
    const dir = path.join(__dirname, '..', 'uploads', 'qrcodes')
    await mkdir(dir, { recursive: true })
    const codes = []
    for (let i = 0; i < count; i++) {
      const seq = String(maxSeq + i + 1).padStart(6, '0')
      const code = `GDQ-${dateStr}-${seq}`
      try {
        await pool.query('INSERT INTO qrcodes (code) VALUES (?)', [code])
      } catch (err) {
        if (err.code === 'ER_DUP_ENTRY') continue
        throw err
      }
      const filePath = path.join(dir, `${code}.png`)
      const scanUrl = `${process.env.SCAN_BASE_URL || 'https://claw.gdqshop.cn'}/scan/${code}`
      await QRCode.toFile(filePath, scanUrl, { width: 300, margin: 2 })
      codes.push({ code, image_url: `/uploads/qrcodes/${code}.png` })
    }
    res.json({ code: 0, data: { list: codes, count: codes.length }, message: 'ok' })
  } catch (err) { next(err) }
})

router.put('/:id/bind', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { product_id, sku_id, mode = 'single', batch_quantity = 1, warehouse_id } = req.body
    if (!product_id) return res.status(400).json({ code: 400, message: '商品ID必填' })

    // mode 校验
    if (!['single', 'batch'].includes(mode)) {
      return res.status(400).json({ code: 400, message: 'mode 必须是 single 或 batch' })
    }

    // 单码模式：必须传 sku_id，且 SKU 属于该商品
    if (mode === 'single') {
      if (!sku_id) {
        return res.status(400).json({ code: 400, message: '单码模式必须传 sku_id' })
      }
      const [[sku]] = await pool.query(
        'SELECT id FROM product_skus WHERE id = ? AND product_id = ?',
        [sku_id, product_id]
      )
      if (!sku) {
        return res.status(400).json({ code: 400, message: 'SKU不属于该商品' })
      }
    }

    // 批码模式：必须有 SKU，batch_quantity > 0
    if (mode === 'batch') {
      if (!sku_id) {
        return res.status(400).json({ code: 400, message: '批码模式必须选 SKU' })
      }
      const [[sku]] = await pool.query(
        'SELECT id FROM product_skus WHERE id = ? AND product_id = ?',
        [sku_id, product_id]
      )
      if (!sku) {
        return res.status(400).json({ code: 400, message: 'SKU不属于该商品' })
      }
      const qty = parseInt(batch_quantity)
      if (!qty || qty < 1) {
        return res.status(400).json({ code: 400, message: 'batch_quantity 必须 >= 1' })
      }
    }

    // 写入
    if (mode === 'batch') {
      await pool.query(
        `UPDATE qrcodes 
         SET product_id = ?, sku_id = ?, 
             warehouse_id = ?,
             batch_mode = 'batch', batch_quantity = ?, remaining_qty = ?,
             status = 'bindProduct', bound_at = NOW() 
         WHERE id = ?`,
        [product_id, sku_id, warehouse_id || null, parseInt(batch_quantity), parseInt(batch_quantity), req.params.id]
      )
    } else {
      await pool.query(
        `UPDATE qrcodes 
         SET product_id = ?, sku_id = ?, 
             warehouse_id = ?,
             batch_mode = 'single', batch_quantity = 1, remaining_qty = 1,
             status = 'bindProduct', bound_at = NOW() 
         WHERE id = ?`,
        [product_id, sku_id, warehouse_id || null, req.params.id]
      )
    }
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

router.put('/:id/status', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { status, buyer, buy_date, warranty_period, warranty_unit, warranty_end, sales_person, warehouse } = req.body

    // 查询当前状态
    const [[qr]] = await pool.query('SELECT status FROM qrcodes WHERE id = ?', [req.params.id])
    if (!qr) return res.status(404).json({ code: 404, message: '二维码不存在' })

    // 校验状态流转合法性
    const allowed = VALID_TRANSITIONS[qr.status] || []
    if (!allowed.includes(status)) {
      return res.status(400).json({ code: 400, message: `不允许从 ${qr.status} 转为 ${status}` })
    }

    // 撤回绑定：清除商品关联
    if (status === 'unused') {
      await pool.query(
        "UPDATE qrcodes SET status = 'unused', product_id = NULL, sku_id = NULL, bound_at = NULL WHERE id = ?",
        [req.params.id]
      )
      return res.json({ code: 0, data: null, message: 'ok' })
    }
    const fields = ['status = ?']
    const params = [status]
    if (buyer) { fields.push('buyer = ?'); params.push(buyer) }
    if (buy_date) { fields.push('buy_date = ?'); params.push(buy_date) }

    // Handle warranty period calculation
    if (warranty_period && warranty_unit && buy_date) {
      const buyDateObj = new Date(buy_date)
      let warrantyEndDate = new Date(buyDateObj)

      if (warranty_unit === 'day') {
        warrantyEndDate.setDate(warrantyEndDate.getDate() + parseInt(warranty_period))
      } else if (warranty_unit === 'month') {
        warrantyEndDate.setMonth(warrantyEndDate.getMonth() + parseInt(warranty_period))
      } else if (warranty_unit === 'year') {
        warrantyEndDate.setFullYear(warrantyEndDate.getFullYear() + parseInt(warranty_period))
      }

      fields.push('warranty_period = ?', 'warranty_unit = ?', 'warranty_end = ?')
      params.push(warranty_period, warranty_unit, warrantyEndDate.toISOString().slice(0, 10))
    } else if (warranty_end) {
      // Fallback to direct warranty_end if provided
      fields.push('warranty_end = ?')
      params.push(warranty_end)
    }

    if (sales_person) { fields.push('sales_person = ?'); params.push(sales_person) }
    if (warehouse) { fields.push('warehouse = ?'); params.push(warehouse) }
    params.push(req.params.id)
    await pool.query(`UPDATE qrcodes SET ${fields.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// 批码扣减（扫码销售 1 件）
// 单码：传 quantity 默认 1，扣完状态变 sold
// 批码：传 quantity 1，扣完 remaining_qty==0 状态变 sold_out
router.post('/:id/sell', requireRole('admin', 'manager', 'salesperson', 'cashier'), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { quantity = 1, buyer, sales_person } = req.body
    const qty = parseInt(quantity) || 1
    if (qty < 1) return res.status(400).json({ code: 400, message: 'quantity 必须 >= 1' })

    await conn.beginTransaction()

    const [[qr]] = await conn.query(
      'SELECT id, status, batch_mode, batch_quantity, remaining_qty, sku_id, product_id, warehouse_id FROM qrcodes WHERE id = ? FOR UPDATE',
      [req.params.id]
    )
    if (!qr) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '二维码不存在' })
    }

    // 状态校验
    if (!['bindProduct', 'inStock', 'shipped', 'sold'].includes(qr.status)) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `当前状态 ${qr.status} 不允许销售` })
    }

    // 单码：quantity 必须 == 1
    if (qr.batch_mode === 'single' && qty !== 1) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '单码一次只能卖 1 件' })
    }

    // 批码：remaining_qty 校验
    if (qr.batch_mode === 'batch' && qr.remaining_qty < qty) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `批码剩余 ${qr.remaining_qty}，不够 ${qty}` })
    }

    // 状态机：如果是首次销售，转 shipped → sold
    let newStatus = qr.status
    if (qr.status === 'bindProduct' || qr.status === 'inStock' || qr.status === 'shipped') {
      newStatus = 'sold'
    }
    // 批码：remaining_qty 扣到 0 自动转 sold_out
    let newRemaining = qr.batch_mode === 'batch' ? qr.remaining_qty - qty : 0
    if (qr.batch_mode === 'batch' && newRemaining === 0) {
      newStatus = 'sold_out'
    }

    const fields = ['status = ?', 'remaining_qty = ?']
    const params = [newStatus, newRemaining]
    if (buyer) { fields.push('buyer = ?'); params.push(buyer) }
    if (sales_person) { fields.push('sales_person = ?'); params.push(sales_person) }
    params.push(req.params.id)
    await conn.query(`UPDATE qrcodes SET ${fields.join(', ')} WHERE id = ?`, params)

    // 同步扣减 warehouse_stock（批码走 SKU 维度的库存）
    if (qr.sku_id && qr.warehouse_id) {
      await conn.query(
        'UPDATE warehouse_stock SET quantity = GREATEST(0, quantity - ?) WHERE warehouse_id = ? AND product_id = ? AND sku_id = ?',
        [qty, qr.warehouse_id, qr.product_id, qr.sku_id]
      )
    }

    // 记录销售明细
    await conn.query(
      `INSERT INTO qrcode_sales (qrcode_id, quantity, buyer, sales_person, status_after, created_at)
       VALUES (?, ?, ?, ?, ?, NOW())`,
      [req.params.id, qty, buyer || null, sales_person || null, newStatus]
    )

    await conn.commit()
    res.json({ 
      code: 0, 
      data: { 
        id: req.params.id, 
        status: newStatus, 
        remaining_qty: newRemaining,
        sold_quantity: qty
      }, 
      message: 'ok' 
    })
  } catch (err) { 
    await conn.rollback()
    next(err) 
  }
  finally { conn.release() }
})

// 批码调整数量（库存管理员权限）
// 用途：实际数量与初始不一致时手动调整
// delta > 0 增加 remaining_qty，delta < 0 减少
router.put('/:id/adjust-batch', requireRole('admin', 'manager'), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { delta, reason } = req.body
    const d = parseInt(delta)
    if (!d || d === 0) return res.status(400).json({ code: 400, message: 'delta 必须非零' })

    await conn.beginTransaction()

    const [[qr]] = await conn.query(
      'SELECT id, status, batch_mode, batch_quantity, remaining_qty FROM qrcodes WHERE id = ? FOR UPDATE',
      [req.params.id]
    )
    if (!qr) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '二维码不存在' })
    }
    if (qr.batch_mode !== 'batch') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '只有批码可以调整数量' })
    }
    if (!['bindProduct', 'inStock', 'shipped', 'sold', 'sold_out'].includes(qr.status)) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `当前状态 ${qr.status} 不允许调整` })
    }

    const newRemaining = qr.remaining_qty + d
    if (newRemaining < 0) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `扣减后剩余 ${newRemaining}，不能为负` })
    }
    if (newRemaining > qr.batch_quantity) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `剩余 ${newRemaining} 不能超过初始 ${qr.batch_quantity}` })
    }

    let newStatus = qr.status
    // 售罄状态调整：sold → sold_out
    if (qr.status === 'sold' && newRemaining === 0) {
      newStatus = 'sold_out'
    }
    // 售罄后回退到 sold
    if (qr.status === 'sold_out' && newRemaining > 0) {
      newStatus = 'sold'
    }

    await conn.query(
      'UPDATE qrcodes SET remaining_qty = ?, status = ? WHERE id = ?',
      [newRemaining, newStatus, req.params.id]
    )

    // 记录调整日志
    await conn.query(
      `INSERT INTO qrcode_batch_adjustments (qrcode_id, before_qty, delta, after_qty, reason, operator, created_at)
       VALUES (?, ?, ?, ?, ?, ?, NOW())`,
      [req.params.id, qr.remaining_qty, d, newRemaining, reason || null, req.user?.name || null]
    )

    await conn.commit()
    res.json({ 
      code: 0, 
      data: { 
        id: req.params.id, 
        remaining_qty: newRemaining,
        batch_quantity: qr.batch_quantity,
        status: newStatus
      }, 
      message: 'ok' 
    })
  } catch (err) { 
    await conn.rollback()
    next(err) 
  }
  finally { conn.release() }
})

router.get('/:id/scan-logs', requirePermission('qrcode:scan'), async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM scan_logs WHERE qrcode_id = ? ORDER BY created_at DESC', [req.params.id])
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// 管理端扫码查询：根据 code 查 QR 信息（销售/仓库员扫码核验）
// 需要 QRCODE_SCAN 权限（区别于前端 H5 公开扫码）
router.get('/scan-lookup/:code', requirePermission('qrcode:scan'), async (req, res, next) => {
  try {
    const { code } = req.params
    const [rows] = await pool.query(
      `SELECT q.id, q.code, q.status, q.warehouse, q.bound_at, q.warranty_end,
              q.buyer, q.buy_date, q.sales_person, q.scan_count,
              p.id as product_id, p.name as product_name, p.image_main as product_image,
              s.id as sku_id, s.specs as sku_specs, s.sale_price as sku_price
       FROM qrcodes q
       LEFT JOIN products p ON q.product_id = p.id
       LEFT JOIN product_skus s ON q.sku_id = s.id
       WHERE q.code = ? LIMIT 1`,
      [code]
    )
    if (rows.length === 0) {
      return res.json({ code: 1, data: null, message: '二维码不存在' })
    }
    const qr = rows[0]
    // 记录扫码日志
    await pool.query(
      `INSERT INTO scan_logs (qrcode_id, scanner, role, action, location, created_at)
       VALUES (?, ?, ?, 'admin_lookup', ?, NOW())`,
      [qr.id, req.user?.id || null, req.user?.role || 'unknown', req.headers['user-agent'] || '']
    )
    // 更新扫码次数
    await pool.query('UPDATE qrcodes SET scan_count = scan_count + 1 WHERE id = ?', [qr.id])
    res.json({ code: 0, data: qr, message: 'ok' })
  } catch (err) { next(err) }
})

// After-sale records
router.get('/after-sale', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT a.*, q.code as qrcode FROM after_sale_records a
       LEFT JOIN qrcodes q ON a.qrcode_id = q.id ORDER BY a.created_at DESC`
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

router.post('/after-sale', async (req, res, next) => {
  try {
    const { qrcode_id, buyer, issue, type, priority, images, contact_phone, h5_user_id, product_id } = req.body
    if (!qrcode_id) return res.status(400).json({ code: 400, message: '二维码ID必填' })

    // 查询当前状态，校验流转合法性
    const [[qr]] = await pool.query('SELECT status FROM qrcodes WHERE id = ?', [qrcode_id])
    if (!qr) return res.status(404).json({ code: 404, message: '二维码不存在' })
    const allowed = VALID_TRANSITIONS[qr.status] || []
    if (!allowed.includes('afterSale')) {
      return res.status(400).json({ code: 400, message: `当前状态 ${qr.status} 不允许发起售后` })
    }

    // 生成工单编号 AS-YYYYMMDD-NNNNNN
    const today = new Date().toISOString().slice(0, 10).replace(/-/g, '')
    const [[{ cnt }]] = await pool.query(
      "SELECT COUNT(*) as cnt FROM after_sale_records WHERE ticket_no LIKE ?",
      [`AS-${today}-%`]
    )
    const ticket_no = `AS-${today}-${String(cnt + 1).padStart(6, '0')}`

    // 记录售后前状态用于回退
    await pool.query(
      "UPDATE qrcodes SET status = 'afterSale' WHERE id = ?",
      [qrcode_id]
    )

    const [result] = await pool.query(
      `INSERT INTO after_sale_records
        (qrcode_id, buyer, issue, ticket_no, type, priority, images, contact_phone, h5_user_id, product_id, previous_status)
       VALUES (?,?,?,?,?,?,?,?,?,?,?)`,
      [qrcode_id, buyer, issue, ticket_no,
       type || 'repair', priority || 'normal',
       images ? JSON.stringify(images) : null,
       contact_phone || null, h5_user_id || null, product_id || null,
       qr.status]
    )
    res.json({ code: 0, data: { id: result.insertId, ticket_no }, message: 'ok' })
  } catch (err) { next(err) }
})

router.put('/after-sale/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { status, handler, handler_note, assigned_to, priority, channel_qrcodes } = req.body
    const [[record]] = await pool.query('SELECT * FROM after_sale_records WHERE id = ?', [req.params.id])
    if (!record) return res.status(404).json({ code: 404, message: '售后记录不存在' })

    const updates = []
    const params = []

    if (status) { updates.push('status = ?'); params.push(status) }
    if (handler !== undefined) { updates.push('handler = ?'); params.push(handler) }
    if (handler_note !== undefined) { updates.push('handler_note = ?'); params.push(handler_note) }
    if (assigned_to !== undefined) { updates.push('assigned_to = ?'); params.push(assigned_to || null) }
    if (priority) { updates.push('priority = ?'); params.push(priority) }
    if (channel_qrcodes !== undefined) { updates.push('channel_qrcodes = ?'); params.push(JSON.stringify(channel_qrcodes)) }

    // 首次指派/处理时记录响应时间
    if ((handler || assigned_to) && !record.responded_at) {
      updates.push('responded_at = NOW()')
    }

    // 解决/拒绝时记录解决时间
    if ((status === 'resolved' || status === 'rejected') && !record.resolved_at) {
      updates.push('resolved_at = NOW()')
    }

    if (!updates.length) return res.status(400).json({ code: 400, message: '无更新内容' })
    params.push(req.params.id)
    await pool.query(`UPDATE after_sale_records SET ${updates.join(', ')} WHERE id = ?`, params)

    // 售后完成时，回退二维码状态
    if (status === 'resolved' || status === 'rejected') {
      const rollbackStatus = record.previous_status || 'sold'
      await pool.query('UPDATE qrcodes SET status = ? WHERE id = ?', [rollbackStatus, record.qrcode_id])
    }

    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// User hierarchy
router.get('/user-hierarchy', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM user_hierarchy ORDER BY level, id')
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// Commissions
router.get('/commissions', async (req, res, next) => {
  try {
    const { status } = req.query
    let sql = `SELECT c.*, q.code as qrcode FROM commission_records c
               LEFT JOIN qrcodes q ON c.qrcode_id = q.id WHERE 1=1`
    const params = []
    if (status) { sql += ' AND c.status = ?'; params.push(status) }
    sql += ' ORDER BY c.created_at DESC'
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

router.post('/commissions/settle', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const [pending] = await conn.query("SELECT * FROM commission_records WHERE status = 'pending'")
    for (const c of pending) {
      await conn.query("UPDATE commission_records SET status = 'settled' WHERE id = ?", [c.id])
      await conn.query(
        'UPDATE user_hierarchy SET total_commission = total_commission + ? WHERE id = ?',
        [c.amount, c.beneficiary_id]
      )
    }
    await conn.commit()
    res.json({ code: 0, data: { settled: pending.length }, message: 'ok' })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// ---- QR Code Detail ----
router.get('/:id/detail', async (req, res, next) => {
  try {
    const [[qr]] = await pool.query(
      `SELECT q.*,
        p.name as product_name, p.sku, p.spec, p.category,
        s.name as supplier_name, s.contact as supplier_contact, s.phone as supplier_phone,
        d.name as dealer_name, d.contact as dealer_contact, d.phone as dealer_phone, d.region as dealer_region,
        st.name as store_name, st.contact as store_contact, st.phone as store_phone, st.city as store_city
       FROM qrcodes q
       LEFT JOIN products p ON q.product_id = p.id
       LEFT JOIN suppliers s ON q.supplier_id = s.id
       LEFT JOIN dealers d ON q.dealer_id = d.id
       LEFT JOIN stores st ON q.store_id = st.id
       WHERE q.id = ?`,
      [req.params.id]
    )
    if (!qr) return res.status(404).json({ code: 404, message: '二维码不存在' })
    qr.image_url = `/uploads/qrcodes/${qr.code}.png`

    // scan logs
    const [scanLogs] = await pool.query(
      'SELECT * FROM scan_logs WHERE qrcode_id = ? ORDER BY created_at DESC LIMIT 20',
      [req.params.id]
    )
    // after sale records
    const [afterSaleRecords] = await pool.query(
      'SELECT * FROM after_sale_records WHERE qrcode_id = ? ORDER BY created_at DESC',
      [req.params.id]
    )
    // repair records
    const [repairRecords] = await pool.query(
      'SELECT * FROM repair_records WHERE qrcode_id = ? ORDER BY created_at DESC',
      [req.params.id]
    )

    res.json({ code: 0, data: { ...qr, scanLogs, afterSaleRecords, repairRecords }, message: 'ok' })
  } catch (err) { next(err) }
})

// Update qrcode detail fields
router.put('/:id/detail', async (req, res, next) => {
  try {
    const allowed = ['supplier_id', 'dealer_id', 'store_id', 'buyer', 'buy_date', 'warranty_end',
      'sales_person', 'warehouse', 'inbound_by', 'outbound_by', 'after_sale_contact']
    const fields = []; const params = []
    for (const f of allowed) {
      if (req.body[f] !== undefined) { fields.push(`${f} = ?`); params.push(req.body[f] || null) }
    }
    if (!fields.length) return res.status(400).json({ code: 400, message: '无更新内容' })
    params.push(req.params.id)
    await pool.query(`UPDATE qrcodes SET ${fields.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// Repair records
router.post('/:id/repair', async (req, res, next) => {
  try {
    const { repair_person, issue, solution, cost, status } = req.body
    const [result] = await pool.query(
      'INSERT INTO repair_records (qrcode_id, repair_person, issue, solution, cost, status) VALUES (?,?,?,?,?,?)',
      [req.params.id, repair_person || null, issue || null, solution || null, cost || 0, status || 'pending']
    )
    res.json({ code: 0, data: { id: result.insertId }, message: 'ok' })
  } catch (err) { next(err) }
})

router.put('/:qid/repair/:rid', async (req, res, next) => {
  try {
    const { repair_person, issue, solution, cost, status } = req.body
    const fields = []; const params = []
    if (repair_person !== undefined) { fields.push('repair_person = ?'); params.push(repair_person) }
    if (issue !== undefined) { fields.push('issue = ?'); params.push(issue) }
    if (solution !== undefined) { fields.push('solution = ?'); params.push(solution) }
    if (cost !== undefined) { fields.push('cost = ?'); params.push(cost) }
    if (status !== undefined) {
      fields.push('status = ?'); params.push(status)
      if (status === 'completed') { fields.push('repaired_at = NOW()') }
    }
    if (!fields.length) return res.status(400).json({ code: 400, message: '无更新内容' })
    params.push(req.params.rid)
    await pool.query(`UPDATE repair_records SET ${fields.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// Batch delete — must come before /:id to avoid routing conflict
router.delete('/batch', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { ids } = req.body
    if (!Array.isArray(ids) || !ids.length) return res.status(400).json({ code: 400, message: 'ids 必填' })
    await pool.query('DELETE FROM scan_logs WHERE qrcode_id IN (?)', [ids])
    await pool.query('DELETE FROM repair_records WHERE qrcode_id IN (?)', [ids])
    await pool.query('DELETE FROM after_sale_records WHERE qrcode_id IN (?)', [ids])
    const [result] = await pool.query('DELETE FROM qrcodes WHERE id IN (?)', [ids])
    res.json({ code: 0, data: { count: result.affectedRows }, message: '批量删除成功' })
  } catch (err) { next(err) }
})

router.delete('/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { id } = req.params
    await pool.query('DELETE FROM scan_logs WHERE qrcode_id = ?', [id])
    await pool.query('DELETE FROM repair_records WHERE qrcode_id = ?', [id])
    await pool.query('DELETE FROM after_sale_records WHERE qrcode_id = ?', [id])
    await pool.query('DELETE FROM qrcodes WHERE id = ?', [id])
    res.json({ code: 0, data: null, message: '删除成功' })
  } catch (err) { next(err) }
})

// Batch delete after-sale records (must come before /:id)
router.delete('/after-sale/batch', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { ids } = req.body
    if (!Array.isArray(ids) || !ids.length) {
      return res.status(400).json({ code: 400, message: 'ids 必填' })
    }
    // 先删除关联的消息
    await pool.query('DELETE FROM aftersale_messages WHERE aftersale_id IN (?)', [ids])
    const [result] = await pool.query('DELETE FROM after_sale_records WHERE id IN (?)', [ids])
    res.json({ code: 0, data: { count: result.affectedRows }, message: '批量删除成功' })
  } catch (err) { next(err) }
})

// Clean old after-sale records (must come before /:id)
router.delete('/after-sale/clean', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { days = 90, status } = req.body
    // 先找出要删除的记录
    let selectSql = 'SELECT id FROM after_sale_records WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
    const params = [days]
    
    if (status) {
      selectSql += ' AND status = ?'
      params.push(status)
    }
    
    const [records] = await pool.query(selectSql, params)
    if (records.length === 0) {
      return res.json({ code: 0, data: { count: 0 }, message: '没有需要清理的记录' })
    }
    
    const ids = records.map(r => r.id)
    // 先删除关联的消息
    await pool.query('DELETE FROM aftersale_messages WHERE aftersale_id IN (?)', [ids])
    // 再删除记录
    const [result] = await pool.query('DELETE FROM after_sale_records WHERE id IN (?)', [ids])
    res.json({ code: 0, data: { count: result.affectedRows }, message: `清理了 ${result.affectedRows} 条记录` })
  } catch (err) { next(err) }
})

// Delete after-sale record by id
router.delete('/after-sale/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { id } = req.params
    // 先删除关联的消息
    await pool.query('DELETE FROM aftersale_messages WHERE aftersale_id = ?', [id])
    const [result] = await pool.query('DELETE FROM after_sale_records WHERE id = ?', [id])
    if (result.affectedRows === 0) {
      return res.status(404).json({ code: 404, message: '售后记录不存在' })
    }
    res.json({ code: 0, message: '售后记录删除成功' })
  } catch (err) { next(err) }
})

// ═══ 一物一码 P0 新功能：重复打印 + 重新绑定（波哥 2026-06-17 决策 5）═══════════

// POST /api/qrcode/reprint/:id - 重复打印（不创建新码）
router.post('/reprint/:id', requirePermission(PERMISSIONS.QRCODE_WRITE), async (req, res, next) => {
  try {
    const id = req.params.id
    const operator = req.user?.name || req.user?.username || 'system'

    const [[qrcode]] = await pool.query(
      'SELECT id, code, product_id, sku_id, warehouse_id, status, print_count FROM qrcodes WHERE id = ?', [id]
    )
    if (!qrcode) return res.status(404).json({ code: 404, message: '二维码不存在' })
    if (!qrcode.code) return res.status(400).json({ code: 400, message: '二维码尚未生成 code' })

    // 重新生成 PNG 文件（如果需要）
    const fs = await import('fs/promises')
    const path = await import('path')
    const QRCode = (await import('qrcode')).default
    const qrDir = path.join(process.cwd(), 'qrcodes')
    await fs.mkdir(qrDir, { recursive: true })
    const filePath = path.join(qrDir, `${qrcode.code}.png`)
    try {
      await QRCode.toFile(filePath, qrcode.code, { width: 300, margin: 2 })
    } catch (e) {
      // 文件系统错误不阻断打印记录
    }

    // 更新打印计数
    await pool.query(
      'UPDATE qrcodes SET print_count = COALESCE(print_count, 0) + 1, last_printed_at = NOW(), last_printed_by = ? WHERE id = ?',
      [operator, id]
    )

    res.json({
      code: 0, message: '打印指令已生成',
      data: {
        qrcode_id: id, code: qrcode.code,
        product_id: qrcode.product_id, sku_id: qrcode.sku_id,
        status: qrcode.status,
        print_count: (qrcode.print_count || 0) + 1,
        file_path: filePath
      }
    })
  } catch (err) { next(err) }
})

// POST /api/qrcode/rebind/:id - 重新绑定（决策 5：需要新码则 dispose + create new）
router.post('/rebind/:id', requirePermission(PERMISSIONS.QRCODE_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const id = req.params.id
    const operator = req.user?.name || req.user?.username || 'system'

    const [[old]] = await conn.query(
      'SELECT id, code, product_id, sku_id, warehouse_id, status FROM qrcodes WHERE id = ?', [id]
    )
    if (!old) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '二维码不存在' })
    }
    if (!['inStock', 'shipped', 'boundProduct', 'unused'].includes(old.status)) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `当前状态 ${old.status} 不能重新绑定` })
    }

    // 1. 创建 rebind_history 表（如果不存在）
    await conn.query(`
      CREATE TABLE IF NOT EXISTS rebind_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        old_qrcode_id INT NOT NULL,
        old_code VARCHAR(50),
        new_code VARCHAR(50) NOT NULL,
        product_id INT,
        sku_id INT,
        warehouse_id INT,
        rebound_by VARCHAR(100),
        rebound_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_old (old_qrcode_id),
        INDEX idx_new (new_code)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    `)

    // 2. 生成新 code
    const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '')
    const [[{ cnt }]] = await conn.query(
      "SELECT COUNT(*) as cnt FROM qrcodes WHERE code LIKE ?",
      [`GDQ-${dateStr}%`]
    )
    const newCode = `GDQ-${dateStr}-${String(cnt + 1).padStart(6, '0')}`

    // 3. 旧码 status='disabled'，清空关联
    await conn.query(
      "UPDATE qrcodes SET status='disabled', product_id=NULL, sku_id=NULL, warehouse_id=NULL WHERE id=?",
      [id]
    )

    // 4. 新码继承原关联
    await conn.query(
      `INSERT INTO qrcodes (code, product_id, sku_id, warehouse_id, status, inbound_by, inbound_at)
       VALUES (?,?,?,?,?,?, NOW())`,
      [newCode, old.product_id, old.sku_id, old.warehouse_id, 'inStock', operator]
    )
    const newId = (await conn.query('SELECT LAST_INSERT_ID() as id'))[0][0].id

    // 5. 记录 rebind_history
    await conn.query(
      `INSERT INTO rebind_history (old_qrcode_id, old_code, new_code, product_id, sku_id, warehouse_id, rebound_by)
       VALUES (?,?,?,?,?,?,?)`,
      [id, old.code, newCode, old.product_id, old.sku_id, old.warehouse_id, operator]
    )

    await conn.commit()
    res.json({
      code: 0, message: '重新绑定成功',
      data: {
        old: { id, code: old.code, status: 'disabled' },
        new: { id: newId, code: newCode, status: 'inStock', product_id: old.product_id, sku_id: old.sku_id, warehouse_id: old.warehouse_id }
      }
    })
  } catch (err) {
    await conn.rollback(); next(err)
  } finally { conn.release() }
})

// GET /api/qrcodes/warehouse-products/:warehouseId — 按仓库取商品列表（用于绑定弹窗）
router.get('/warehouse-products/:warehouseId', async (req, res, next) => {
  try {
    const { warehouseId } = req.params
    const [rows] = await pool.query(
      `SELECT DISTINCT ws.product_id, ws.sku_id, ws.quantity,
        p.name, p.sku, p.unit, p.category, p.image_main,
        ps.specs,
        COALESCE(ps.sku, '') as sku_value
      FROM warehouse_stock ws
      JOIN products p ON ws.product_id = p.id
      LEFT JOIN product_skus ps ON ws.sku_id = ps.id
      WHERE ws.warehouse_id = ?
      ORDER BY p.name ASC`,
      [warehouseId]
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

export default router
