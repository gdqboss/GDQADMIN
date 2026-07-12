import express from 'express'
import { pool } from '../db/connection.js'
import { uploadInvoice } from '../middleware/upload.js'

const router = express.Router()

// 获取发票列表
router.get('/', async (req, res) => {
  try {
    const {
      page = 1,
      limit = 50,
      direction,
      invoice_type,
      status,
      start_date,
      end_date,
      search
    } = req.query

    let sql = `
      SELECT i.*, u.name as creator_name
      FROM invoices i
      LEFT JOIN users u ON i.creator_id = u.id
      WHERE 1=1
    `
    const params = []

    if (direction) {
      sql += ' AND i.direction = ?'
      params.push(direction)
    }

    if (invoice_type) {
      sql += ' AND i.invoice_type = ?'
      params.push(invoice_type)
    }

    if (status) {
      sql += ' AND i.status = ?'
      params.push(status)
    }

    if (start_date) {
      sql += ' AND i.invoice_date >= ?'
      params.push(start_date)
    }

    if (end_date) {
      sql += ' AND i.invoice_date <= ?'
      params.push(end_date)
    }

    if (search) {
      sql += ' AND (i.invoice_no LIKE ? OR i.seller_name LIKE ? OR i.buyer_name LIKE ?)'
      const searchPattern = `%${search}%`
      params.push(searchPattern, searchPattern, searchPattern)
    }

    // 获取总数
    const countSql = sql.replace(/SELECT i\.\*, u\.username as creator_name/, 'SELECT COUNT(*) as total')
    const [countResult] = await pool.query(countSql, params)
    const total = countResult[0].total

    // 分页查询
    sql += ' ORDER BY i.invoice_date DESC, i.id DESC LIMIT ? OFFSET ?'
    params.push(parseInt(limit), (parseInt(page) - 1) * parseInt(limit))

    const [rows] = await pool.query(sql, params)

    res.json({
      success: true,
      data: rows,
      pagination: {
        page: parseInt(page),
        limit: parseInt(limit),
        total
      }
    })
  } catch (error) {
    console.error('获取发票列表失败:', error)
    res.status(500).json({ success: false, message: '获取发票列表失败' })
  }
})

// 创建发票
router.post('/', uploadInvoice.single('image'), async (req, res) => {
  try {
    const {
      invoice_no,
      invoice_code,
      invoice_type,
      direction,
      invoice_date,
      seller_name,
      seller_tax_no,
      buyer_name,
      buyer_tax_no,
      total_amount,
      tax_amount,
      amount_without_tax,
      tax_rate,
      related_type,
      related_id,
      note
    } = req.body

    const image_path = req.file ? `/uploads/invoices/${req.file.filename}` : null
    const creator_id = req.user.id

    const sql = `
      INSERT INTO invoices (
        invoice_no, invoice_code, invoice_type, direction, invoice_date,
        seller_name, seller_tax_no, buyer_name, buyer_tax_no,
        total_amount, tax_amount, amount_without_tax, tax_rate,
        image_path, related_type, related_id, note, creator_id
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `

    const [result] = await pool.query(sql, [
      invoice_no, invoice_code, invoice_type, direction, invoice_date,
      seller_name, seller_tax_no, buyer_name, buyer_tax_no,
      total_amount, tax_amount, amount_without_tax, tax_rate,
      image_path, related_type || 'none', related_id || null, note, creator_id
    ])

    res.json({ success: true, data: { id: result.insertId } })
  } catch (error) {
    console.error('创建发票失败:', error)
    res.status(500).json({ success: false, message: '创建发票失败' })
  }
})

// 获取发票详情
router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params

    const sql = `
      SELECT i.*, u.name as creator_name
      FROM invoices i
      LEFT JOIN users u ON i.creator_id = u.id
      WHERE i.id = ?
    `

    const [rows] = await pool.query(sql, [id])

    if (rows.length === 0) {
      return res.status(404).json({ success: false, message: '发票不存在' })
    }

    res.json({ success: true, data: rows[0] })
  } catch (error) {
    console.error('获取发票详情失败:', error)
    res.status(500).json({ success: false, message: '获取发票详情失败' })
  }
})

// 更新发票
router.put('/:id', uploadInvoice.single('image'), async (req, res) => {
  try {
    const { id } = req.params
    const {
      invoice_no,
      invoice_code,
      invoice_type,
      direction,
      invoice_date,
      seller_name,
      seller_tax_no,
      buyer_name,
      buyer_tax_no,
      total_amount,
      tax_amount,
      amount_without_tax,
      tax_rate,
      related_type,
      related_id,
      note
    } = req.body

    let sql = `
      UPDATE invoices SET
        invoice_no = ?, invoice_code = ?, invoice_type = ?, direction = ?,
        invoice_date = ?, seller_name = ?, seller_tax_no = ?, buyer_name = ?,
        buyer_tax_no = ?, total_amount = ?, tax_amount = ?, amount_without_tax = ?,
        tax_rate = ?, related_type = ?, related_id = ?, note = ?
    `

    const params = [
      invoice_no, invoice_code, invoice_type, direction, invoice_date,
      seller_name, seller_tax_no, buyer_name, buyer_tax_no,
      total_amount, tax_amount, amount_without_tax, tax_rate,
      related_type || 'none', related_id || null, note
    ]

    if (req.file) {
      sql += ', image_path = ?'
      params.push(`/uploads/invoices/${req.file.filename}`)
    }

    sql += ' WHERE id = ?'
    params.push(id)

    await pool.query(sql, params)

    res.json({ success: true })
  } catch (error) {
    console.error('更新发票失败:', error)
    res.status(500).json({ success: false, message: '更新发票失败' })
  }
})

// 删除发票
router.delete('/:id', async (req, res) => {
  try {
    const { id } = req.params

    await pool.query('DELETE FROM invoices WHERE id = ?', [id])

    res.json({ success: true })
  } catch (error) {
    console.error('删除发票失败:', error)
    res.status(500).json({ success: false, message: '删除发票失败' })
  }
})

// 核销发票
router.put('/:id/verify', async (req, res) => {
  try {
    const { id } = req.params

    await pool.query('UPDATE invoices SET status = ? WHERE id = ?', ['verified', id])

    res.json({ success: true })
  } catch (error) {
    console.error('核销发票失败:', error)
    res.status(500).json({ success: false, message: '核销发票失败' })
  }
})

// 作废发票
router.put('/:id/void', async (req, res) => {
  try {
    const { id } = req.params

    await pool.query('UPDATE invoices SET status = ? WHERE id = ?', ['void', id])

    res.json({ success: true })
  } catch (error) {
    console.error('作废发票失败:', error)
    res.status(500).json({ success: false, message: '作废发票失败' })
  }
})

// 发票统计
router.get('/statistics/summary', async (req, res) => {
  try {
    const { start_date, end_date } = req.query

    let sql = `
      SELECT
        direction,
        COUNT(*) as count,
        SUM(total_amount) as total_amount,
        SUM(tax_amount) as tax_amount,
        SUM(amount_without_tax) as amount_without_tax
      FROM invoices
      WHERE status != 'void'
    `
    const params = []

    if (start_date) {
      sql += ' AND invoice_date >= ?'
      params.push(start_date)
    }

    if (end_date) {
      sql += ' AND invoice_date <= ?'
      params.push(end_date)
    }

    sql += ' GROUP BY direction'

    const [rows] = await pool.query(sql, params)

    const statistics = {
      input: { count: 0, total_amount: 0, tax_amount: 0, amount_without_tax: 0 },
      output: { count: 0, total_amount: 0, tax_amount: 0, amount_without_tax: 0 }
    }

    rows.forEach(row => {
      statistics[row.direction] = {
        count: row.count,
        total_amount: parseFloat(row.total_amount || 0),
        tax_amount: parseFloat(row.tax_amount || 0),
        amount_without_tax: parseFloat(row.amount_without_tax || 0)
      }
    })

    res.json({ success: true, data: statistics })
  } catch (error) {
    console.error('获取发票统计失败:', error)
    res.status(500).json({ success: false, message: '获取发票统计失败' })
  }
})

// 按月统计
router.get('/statistics/monthly', async (req, res) => {
  try {
    const { start_date, end_date } = req.query

    let sql = `
      SELECT
        DATE_FORMAT(invoice_date, '%Y-%m') as month,
        direction,
        SUM(total_amount) as total_amount,
        SUM(tax_amount) as tax_amount
      FROM invoices
      WHERE status != 'void'
    `
    const params = []

    if (start_date) {
      sql += ' AND invoice_date >= ?'
      params.push(start_date)
    }

    if (end_date) {
      sql += ' AND invoice_date <= ?'
      params.push(end_date)
    }

    sql += ' GROUP BY month, direction ORDER BY month'

    const [rows] = await pool.query(sql, params)

    res.json({ success: true, data: rows })
  } catch (error) {
    console.error('获取月度统计失败:', error)
    res.status(500).json({ success: false, message: '获取月度统计失败' })
  }
})

// 按类型统计
router.get('/statistics/by-type', async (req, res) => {
  try {
    const { start_date, end_date, direction } = req.query

    let sql = `
      SELECT
        invoice_type,
        COUNT(*) as count,
        SUM(total_amount) as total_amount
      FROM invoices
      WHERE status != 'void'
    `
    const params = []

    if (direction) {
      sql += ' AND direction = ?'
      params.push(direction)
    }

    if (start_date) {
      sql += ' AND invoice_date >= ?'
      params.push(start_date)
    }

    if (end_date) {
      sql += ' AND invoice_date <= ?'
      params.push(end_date)
    }

    sql += ' GROUP BY invoice_type'

    const [rows] = await pool.query(sql, params)

    res.json({ success: true, data: rows })
  } catch (error) {
    console.error('获取类型统计失败:', error)
    res.status(500).json({ success: false, message: '获取类型统计失败' })
  }
})

export default router
