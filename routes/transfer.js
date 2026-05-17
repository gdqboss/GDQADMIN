import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { parsePagination } from '../utils/pagination.js'

const router = Router()

// GET /api/transfer - 获取调货单列表
router.get('/', auth, async (req, res, next) => {
  try {
    const { status, from_store_id, to_store_id, date_from, date_to } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE 1=1'
    const params = []
    const countParams = []

    if (status) {
      where += ' AND tr.status = ?'
      params.push(status)
      countParams.push(status)
    }

    if (from_store_id) {
      where += ' AND tr.from_store_id = ?'
      params.push(from_store_id)
      countParams.push(from_store_id)
    }

    if (to_store_id) {
      where += ' AND tr.to_store_id = ?'
      params.push(to_store_id)
      countParams.push(to_store_id)
    }

    if (date_from) {
      where += ' AND DATE(tr.created_at) >= ?'
      params.push(date_from)
      countParams.push(date_from)
    }

    if (date_to) {
      where += ' AND DATE(tr.created_at) <= ?'
      params.push(date_to)
      countParams.push(date_to)
    }

    const sql = `
      SELECT tr.*,
        fs.name as from_store_name, fs.city as from_store_city,
        ts.name as to_store_name, ts.city as to_store_city,
        u1.name as initiated_by_name,
        u2.name as received_by_name
      FROM transfer_records tr
      LEFT JOIN stores fs ON tr.from_store_id = fs.id
      LEFT JOIN stores ts ON tr.to_store_id = ts.id
      LEFT JOIN users u1 ON tr.initiated_by = u1.id
      LEFT JOIN users u2 ON tr.received_by = u2.id
      ${where}
      ORDER BY tr.created_at DESC
      LIMIT ? OFFSET ?
    `

    const countSql = `SELECT COUNT(*) as total FROM transfer_records tr ${where}`

    params.push(size, (page - 1) * size)

    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// GET /api/transfer/:id - 获取调货单详情
router.get('/:id', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT tr.*,
        fs.name as from_store_name, fs.city as from_store_city, fs.address as from_store_address,
        ts.name as to_store_name, ts.city as to_store_city, ts.address as to_store_address,
        u1.name as initiated_by_name, u1.email as initiated_by_email,
        u2.name as received_by_name, u2.email as received_by_email
       FROM transfer_records tr
       LEFT JOIN stores fs ON tr.from_store_id = fs.id
       LEFT JOIN stores ts ON tr.to_store_id = ts.id
       LEFT JOIN users u1 ON tr.initiated_by = u1.id
       LEFT JOIN users u2 ON tr.received_by = u2.id
       WHERE tr.id = ?`,
      [req.params.id]
    )

    if (!rows.length) {
      return res.status(404).json({ code: 404, message: '调货单不存在' })
    }

    const transfer = rows[0]

    // 获取调货明细
    const [items] = await pool.query(
      `SELECT ti.*,
        p.name as product_name, p.sku as product_sku, p.image_main as product_image,
        q.qr_code as qrcode
       FROM transfer_items ti
       LEFT JOIN products p ON ti.product_id = p.id
       LEFT JOIN qrcodes q ON ti.qrcode_id = q.id
       WHERE ti.record_id = ?`,
      [req.params.id]
    )

    transfer.items = items

    res.json({ code: 0, data: transfer, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// POST /api/transfer - 创建调货单
router.post('/', auth, async (req, res, next) => {
  try {
    const { from_store_id, to_store_id, items, note } = req.body

    if (!from_store_id || !to_store_id || !items || !items.length) {
      return res.status(400).json({ code: 400, message: '调出门店、调入门店和商品明细为必填项' })
    }

    if (from_store_id === to_store_id) {
      return res.status(400).json({ code: 400, message: '调出门店和调入门店不能相同' })
    }

    // 生成调货单号
    const date = new Date().toISOString().slice(0, 10).replace(/-/g, '')
    const [[{ count }]] = await pool.query(
      'SELECT COUNT(*) as count FROM transfer_records WHERE DATE(created_at) = CURDATE()'
    )
    const record_no = `TF-${date}-${String(count + 1).padStart(4, '0')}`

    // 计算总数量
    const total_qty = items.reduce((sum, item) => sum + (item.quantity || 1), 0)

    const conn = await pool.getConnection()
    try {
      await conn.beginTransaction()

      // 插入调货单
      const [result] = await conn.query(
        `INSERT INTO transfer_records (record_no, from_store_id, to_store_id, total_qty, initiated_by, note, status)
         VALUES (?, ?, ?, ?, ?, ?, 'pending')`,
        [record_no, from_store_id, to_store_id, total_qty, req.user.id, note]
      )

      const record_id = result.insertId

      // 插入调货明细
      for (const item of items) {
        await conn.query(
          'INSERT INTO transfer_items (record_id, product_id, qrcode_id, quantity) VALUES (?, ?, ?, ?)',
          [record_id, item.product_id, item.qrcode_id || null, item.quantity || 1]
        )
      }

      await conn.commit()

      res.json({ code: 0, data: { id: record_id, record_no }, message: '调货单创建成功' })
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

// PUT /api/transfer/:id/ship - 确认发货
router.put('/:id/ship', auth, async (req, res, next) => {
  try {
    const [[transfer]] = await pool.query(
      'SELECT * FROM transfer_records WHERE id = ?',
      [req.params.id]
    )

    if (!transfer) {
      return res.status(404).json({ code: 404, message: '调货单不存在' })
    }

    if (transfer.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '只能发货待发货状态的调货单' })
    }

    // 更新状态为已发货
    await pool.query(
      'UPDATE transfer_records SET status = ?, shipped_at = NOW() WHERE id = ?',
      ['shipped', req.params.id]
    )

    res.json({ code: 0, message: '发货成功' })
  } catch (err) {
    next(err)
  }
})

// PUT /api/transfer/:id/receive - 确认收货
router.put('/:id/receive', auth, async (req, res, next) => {
  try {
    const [[transfer]] = await pool.query(
      'SELECT * FROM transfer_records WHERE id = ?',
      [req.params.id]
    )

    if (!transfer) {
      return res.status(404).json({ code: 404, message: '调货单不存在' })
    }

    if (transfer.status !== 'shipped') {
      return res.status(400).json({ code: 400, message: '只能收货已发货状态的调货单' })
    }

    const conn = await pool.getConnection()
    try {
      await conn.beginTransaction()

      // 更新调货单状态
      await conn.query(
        'UPDATE transfer_records SET status = ?, received_by = ?, received_at = NOW() WHERE id = ?',
        ['received', req.user.id, req.params.id]
      )

      // 获取调货明细
      const [items] = await conn.query(
        'SELECT * FROM transfer_items WHERE record_id = ?',
        [req.params.id]
      )

      // 更新库存归属（如果有二维码）
      for (const item of items) {
        if (item.qrcode_id) {
          await conn.query(
            'UPDATE qrcodes SET store_id = ? WHERE id = ?',
            [transfer.to_store_id, item.qrcode_id]
          )
        }
      }

      await conn.commit()

      res.json({ code: 0, message: '收货成功' })
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

// PUT /api/transfer/:id/cancel - 取消调货
router.put('/:id/cancel', auth, async (req, res, next) => {
  try {
    const [[transfer]] = await pool.query(
      'SELECT * FROM transfer_records WHERE id = ?',
      [req.params.id]
    )

    if (!transfer) {
      return res.status(404).json({ code: 404, message: '调货单不存在' })
    }

    if (transfer.status === 'received') {
      return res.status(400).json({ code: 400, message: '已收货的调货单不能取消' })
    }

    if (transfer.initiated_by !== req.user.id && req.user.role !== 'admin') {
      return res.status(403).json({ code: 403, message: '只有发起人或管理员可以取消调货单' })
    }

    await pool.query(
      'UPDATE transfer_records SET status = ? WHERE id = ?',
      ['cancelled', req.params.id]
    )

    res.json({ code: 0, message: '调货单已取消' })
  } catch (err) {
    next(err)
  }
})

export default router
