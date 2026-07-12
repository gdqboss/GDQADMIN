import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// GET /api/reports - 报告列表
router.get('/', async (req, res, next) => {
  try {
    res.json({
      code: 0,
      data: {
        reports: [
          { key: 'monthly-inout', name: '月度出入库统计', nameEn: 'Monthly In/Out Report' },
          { key: 'category-stock', name: '分类库存统计', nameEn: 'Category Stock Report' },
          { key: 'price-analysis', name: '价格分析', nameEn: 'Price Analysis Report' },
          { key: 'warehouse-summary', name: '仓库汇总', nameEn: 'Warehouse Summary' },
        ]
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

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
      `SELECT w.id, w.name AS warehouse, w.address, w.type, w.manager, w.status,
              COALESCE(SUM(ws.quantity), 0) AS total_qty,
              COUNT(DISTINCT ws.product_id) AS product_count
       FROM warehouses w LEFT JOIN warehouse_stock ws ON w.id = ws.warehouse_id
       GROUP BY w.id ORDER BY total_qty DESC`
    )
    // 给老 ReportCenter 字段补 categories（按产品 category 分组）
    const [catRows] = await pool.query(
      `SELECT ws.warehouse_id, p.category, COALESCE(SUM(ws.quantity), 0) AS qty
       FROM warehouse_stock ws
       LEFT JOIN products p ON ws.product_id = p.id
       WHERE p.category IS NOT NULL
       GROUP BY ws.warehouse_id, p.category`
    )
    const catMap = {}
    for (const r of catRows) {
      if (!catMap[r.warehouse_id]) catMap[r.warehouse_id] = {}
      catMap[r.warehouse_id][r.category] = Number(r.qty)
    }
    for (const row of rows) {
      row.categories = catMap[row.id] || {}
    }
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// Dashboard 用的精简版：Top N 仓库，5 个核心指标（库存数/价值/种类/30天出库/低库存数）
router.get('/dashboard-top-warehouses', async (req, res, next) => {
  try {
    const limit = Math.min(parseInt(req.query.limit) || 5, 20)
    const [rows] = await pool.query(
      `SELECT
         w.id, w.name, w.type, w.manager, w.status,
         COALESCE(SUM(ws.quantity), 0) AS total_qty,
         COUNT(DISTINCT ws.product_id) AS sku_count,
         COALESCE(ROUND(SUM(ws.quantity * COALESCE(p.purchase_price, 0)), 2), 0) AS total_value,
         COALESCE((
           SELECT SUM(oi.quantity)
           FROM outbound_items oi
           JOIN outbound_records r ON oi.record_id = r.id
           WHERE oi.product_id IN (
             SELECT DISTINCT product_id FROM warehouse_stock WHERE warehouse_id = w.id
           )
           AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         ), 0) AS outbound_30d,
         COALESCE((
           SELECT COUNT(*)
           FROM products p2
           WHERE p2.stock IS NOT NULL
           AND p2.safe_stock IS NOT NULL
           AND p2.stock <= p2.safe_stock
           AND p2.id IN (
             SELECT DISTINCT product_id FROM warehouse_stock WHERE warehouse_id = w.id
           )
         ), 0) AS low_stock_count
       FROM warehouses w
       LEFT JOIN warehouse_stock ws ON w.id = ws.warehouse_id
       LEFT JOIN products p ON ws.product_id = p.id
       GROUP BY w.id
       ORDER BY total_qty DESC
       LIMIT ?`,
      [limit]
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

export default router
