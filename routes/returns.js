import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { parsePagination } from '../utils/pagination.js'
import { checkPerm } from '../utils/permission.js'

const router = Router()

// GET /api/returns - 获取退货记录列表
router.get('/', auth, async (req, res, next) => {
  try {
    const { status, warehouse_id, date_from, date_to } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE 1=1'
    const params = []
    const countParams = []

    if (status) {
      where += ' AND rr.status = ?'
      params.push(status)
      countParams.push(status)
    }

    if (warehouse_id) {
      where += ' AND rr.warehouse_id = ?'
      params.push(warehouse_id)
      countParams.push(warehouse_id)
    }

    if (date_from) {
      where += ' AND DATE(rr.created_at) >= ?'
      params.push(date_from)
      countParams.push(date_from)
    }

    if (date_to) {
      where += ' AND DATE(rr.created_at) <= ?'
      params.push(date_to)
      countParams.push(date_to)
    }

    const countSql = `SELECT COUNT(*) as total FROM return_records rr ${where}`
    const [[{ total }]] = await pool.query(countSql, countParams)

    const sql = `
      SELECT rr.*, w.name as warehouse_name
      FROM return_records rr
      LEFT JOIN warehouses w ON rr.warehouse_id = w.id
      ${where}
      ORDER BY rr.created_at DESC
      LIMIT ? OFFSET ?
    `
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)

    // 加载退货明细
    if (rows.length) {
      const ids = rows.map(r => r.id)
      const [items] = await pool.query(
        `SELECT ri.*, p.name as product_name, p.sku FROM return_items ri
         LEFT JOIN products p ON ri.product_id = p.id WHERE ri.record_id IN (?)`, [ids]
      ).catch(() => [[]])
      const itemsByRecord = {}
      for (const item of items) {
        if (!itemsByRecord[item.record_id]) itemsByRecord[item.record_id] = []
        itemsByRecord[item.record_id].push(item)
      }
      for (const r of rows) r.items = itemsByRecord[r.id] || []
    }

    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// GET /api/returns/:id - 获取退货详情
router.get('/:id', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT rr.*,
        p.name as product_name, p.sku as product_sku, p.image_main as product_image,
        w.name as warehouse_name,
        q.code as qrcode,
        u.name as operator_name,
        retail.buyer_name as original_buyer, retail.sale_price as original_price
       FROM return_records rr
       LEFT JOIN products p ON rr.product_id = p.id
       LEFT JOIN warehouses w ON rr.warehouse_id = w.id
       LEFT JOIN qrcodes q ON rr.qrcode_id = q.id
       LEFT JOIN users u ON rr.operator_id = u.id
       LEFT JOIN retail_records retail ON rr.retail_record_id = retail.id
       WHERE rr.id = ?`,
      [req.params.id]
    )

    if (!rows.length) {
      return res.status(404).json({ code: 404, message: '退货记录不存在' })
    }

    res.json({ code: 0, data: rows[0], message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// POST /api/returns - 创建退货记录
router.post('/', auth, async (req, res, next) => {
  try {
    const { product_id, qrcode_id, warehouse_id, quantity, reason, return_type } = req.body

    if (!product_id || !warehouse_id || !quantity || !reason) {
      return res.status(400).json({ code: 400, message: '商品、仓库、数量和退货原因为必填项' })
    }

    // 生成退货单号
    const date = new Date().toISOString().slice(0, 10).replace(/-/g, '')
    const [[{ count }]] = await pool.query(
      'SELECT COUNT(*) as count FROM return_records WHERE DATE(created_at) = CURDATE()'
    )
    const record_no = `RT-${date}-${String(count + 1).padStart(4, '0')}`

    const conn = await pool.getConnection()
    try {
      await conn.beginTransaction()

      // 如果有二维码，获取关联的零售记录和客服信息
      let retail_record_id = null
      let buyer_name = null
      let buyer_phone = null
      let salesperson_name = null
      let service_user_id = null

      if (qrcode_id) {
        const [[qrcode]] = await conn.query(
          `SELECT q.*, retail.id as retail_id, retail.buyer_name, retail.buyer_phone, 
            retail.operator as salesperson, retail.service_user_id
           FROM qrcodes q
           LEFT JOIN retail_records retail ON retail.qrcode_id = q.id
           WHERE q.id = ?`,
          [qrcode_id]
        )

        if (qrcode) {
          retail_record_id = qrcode.retail_id
          buyer_name = qrcode.buyer_name
          buyer_phone = qrcode.buyer_phone
          salesperson_name = qrcode.salesperson
          service_user_id = qrcode.service_user_id
        }
      }

      // 插入退货记录
      const [result] = await conn.query(
        `INSERT INTO return_records 
         (record_no, product_id, qrcode_id, warehouse_id, quantity, reason, return_type, 
          operator_id, operator_name, retail_record_id, buyer_name, buyer_phone, 
          salesperson_name, service_user_id, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')`,
        [record_no, product_id, qrcode_id, warehouse_id, quantity, reason, return_type || 'other',
         req.user.id, req.user.name, retail_record_id, buyer_name, buyer_phone,
         salesperson_name, service_user_id]
      )

      // 如果有二维码，更新二维码状态
      if (qrcode_id) {
        await conn.query(
          'UPDATE qrcodes SET status = ?, warehouse = ? WHERE id = ?',
          ['bound', warehouse_id, qrcode_id]
        )
      }

      await conn.commit()

      res.json({ code: 0, data: { id: result.insertId, record_no }, message: '退货记录创建成功' })
    } catch (err) {
      await conn.rollback()
      throw err
    } finally {
      conn.release()
    }
  } catch (err) {
    next(err)
  }
})

// PUT /api/returns/:id/approve - 审批退货
router.put('/:id/approve', auth, async (req, res, next) => {
  try {
    if (!(await checkPerm(req, 'retail:write'))) {
      return res.status(403).json({ code: 403, message: '权限不足' })
    }

    const [[returnRecord]] = await pool.query(
      'SELECT * FROM return_records WHERE id = ?',
      [req.params.id]
    )

    if (!returnRecord) {
      return res.status(404).json({ code: 404, message: '退货记录不存在' })
    }

    if (returnRecord.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '只能审批待处理状态的退货' })
    }

    await pool.query(
      'UPDATE return_records SET status = ?, approved_at = NOW() WHERE id = ?',
      ['approved', req.params.id]
    )

    res.json({ code: 0, message: '审批通过' })
  } catch (err) {
    next(err)
  }
})

// PUT /api/returns/:id/reject - 拒绝退货
router.put('/:id/reject', auth, async (req, res, next) => {
  try {
    if (!(await checkPerm(req, 'retail:write'))) {
      return res.status(403).json({ code: 403, message: '权限不足' })
    }

    const [[returnRecord]] = await pool.query(
      'SELECT * FROM return_records WHERE id = ?',
      [req.params.id]
    )

    if (!returnRecord) {
      return res.status(404).json({ code: 404, message: '退货记录不存在' })
    }

    if (returnRecord.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '只能拒绝待处理状态的退货' })
    }

    await pool.query(
      'UPDATE return_records SET status = ? WHERE id = ?',
      ['rejected', req.params.id]
    )

    res.json({ code: 0, message: '已拒绝' })
  } catch (err) {
    next(err)
  }
})

// PUT /api/returns/:id/complete - 完成退货
router.put('/:id/complete', auth, async (req, res, next) => {
  try {
    const [[returnRecord]] = await pool.query(
      'SELECT * FROM return_records WHERE id = ?',
      [req.params.id]
    )

    if (!returnRecord) {
      return res.status(404).json({ code: 404, message: '退货记录不存在' })
    }

    if (returnRecord.status !== 'approved') {
      return res.status(400).json({ code: 400, message: '只能完成已审批的退货' })
    }

    await pool.query(
      'UPDATE return_records SET status = ?, completed_at = NOW() WHERE id = ?',
      ['completed', req.params.id]
    )

    res.json({ code: 0, message: '退货已完成' })
  } catch (err) {
    next(err)
  }
})

export default router
