import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// 获取出货单数据（从已出库的二维码聚合）
router.get('/outbound-records', async (req, res, next) => {
  try {
    const { date_start, date_end, dealer_id } = req.query
    
    // 查询已出库的二维码，按出库日期聚合
    let sql = `
      SELECT 
        DATE(o.created_at) as outbound_date,
        COUNT(*) as total_qty,
        GROUP_CONCAT(DISTINCT o.warehouse ORDER BY o.warehouse SEPARATOR ', ') as warehouses,
        o.dealer_id,
        d.name as dealer_name
      FROM qrcodes o
      LEFT JOIN dealers d ON o.dealer_id = d.id
      WHERE o.status = 'outStock'
    `
    const params = []
    
    if (date_start) {
      sql += ' AND DATE(o.created_at) >= ?'
      params.push(date_start)
    }
    if (date_end) {
      sql += ' AND DATE(o.created_at) <= ?'
      params.push(date_end)
    }
    if (dealer_id) {
      sql += ' AND o.dealer_id = ?'
      params.push(dealer_id)
    }
    
    sql += ' GROUP BY DATE(o.created_at), o.dealer_id ORDER BY outbound_date DESC'
    
    const [rows] = await pool.query(sql, params)
    
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// 获取出货单明细
router.get('/outbound-detail', async (req, res, next) => {
  try {
    const { date_start, date_end } = req.query
    
    const sql = `
      SELECT 
        q.code as qr_code,
        q.warehouse,
        q.created_at as outbound_at,
        p.name as product_name,
        p.sku,
        ps.sku as sku_code,
        ps.specs as sku_specs,
        p.sale_price,
        d.name as dealer_name
      FROM qrcodes q
      LEFT JOIN products p ON q.product_id = p.id
      LEFT JOIN product_skus ps ON q.sku_id = ps.id
      LEFT JOIN dealers d ON q.dealer_id = d.id
      WHERE q.status = 'outStock'
    `
    
    const [rows] = await pool.query(sql)
    
    // 按日期分组
    const grouped = {}
    rows.forEach(r => {
      const dateStr = r.outbound_at instanceof Date 
        ? r.outbound_at.toISOString().split('T')[0]
        : (r.outbound_at ? String(r.outbound_at).split('T')[0] : 'unknown')
      if (!grouped[dateStr]) {
        grouped[dateStr] = []
      }
      grouped[dateStr].push(r)
    })
    
    res.json({ code: 0, data: { records: rows, grouped }, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

export default router
