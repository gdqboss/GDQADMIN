import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// period helpers
function getDateGroupSQL(period, col) {
  switch (period) {
    case 'day':     return `DATE_FORMAT(${col}, '%Y-%m-%d')`
    case 'week':    return `DATE_FORMAT(${col}, '%Y-%u')`
    case 'quarter': return `CONCAT(YEAR(${col}), '-Q', QUARTER(${col}))`
    case 'year':    return `YEAR(${col})`
    case 'month':
    default:        return `DATE_FORMAT(${col}, '%Y-%m')`
  }
}

router.get('/monthly-inout', async (req, res, next) => {
  try {
    // Generate last 6 months using recursive CTE
    const [rows] = await pool.query(`
      WITH RECURSIVE months AS (
        SELECT DATE_SUB(CURDATE(), INTERVAL 5 MONTH) as month_date
        UNION ALL
        SELECT DATE_ADD(month_date, INTERVAL 1 MONTH)
        FROM months
        WHERE month_date < CURDATE()
      )
      SELECT
        MONTH(m.month_date) as month,
        COALESCE(SUM(ib.total_qty), 0) as inbound,
        COALESCE(SUM(ob.total_qty), 0) as outbound
      FROM months m
      LEFT JOIN inbound_records ib
        ON DATE_FORMAT(ib.created_at, '%Y-%m') = DATE_FORMAT(m.month_date, '%Y-%m')
        AND ib.status = 'completed'
      LEFT JOIN outbound_records ob
        ON DATE_FORMAT(ob.created_at, '%Y-%m') = DATE_FORMAT(m.month_date, '%Y-%m')
        AND ob.status = 'completed'
      GROUP BY m.month_date
      ORDER BY m.month_date
    `)

    const data = rows.map(r => ({
      month: String(r.month),
      inbound: Number(r.inbound),
      outbound: Number(r.outbound)
    }))

    res.json({ code: 0, data, message: 'ok' })
  } catch (err) { next(err) }
})

router.get('/category-stock', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT category, SUM(stock) as total_stock, COUNT(*) as product_count
       FROM products WHERE status = 'active' GROUP BY category ORDER BY total_stock DESC`
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

router.get('/price-analysis', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT category,
              ROUND(AVG(purchase_price), 2) as avg_purchase,
              ROUND(AVG(sale_price), 2) as avg_sale,
              ROUND(AVG(sale_price - purchase_price), 2) as avg_profit,
              ROUND(AVG((sale_price - purchase_price) / purchase_price * 100), 1) as avg_margin
       FROM products WHERE status = 'active' AND purchase_price > 0
       GROUP BY category`
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

router.get('/warehouse-summary', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT w.id, w.name, w.address, w.type, w.manager, w.status,
              COALESCE(SUM(ws.quantity), 0) as total_qty,
              COUNT(DISTINCT ws.product_id) as product_count
       FROM warehouses w LEFT JOIN warehouse_stock ws ON w.id = ws.warehouse_id
       GROUP BY w.id ORDER BY total_qty DESC`
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

export default router
