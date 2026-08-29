import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// GET /api/workbuddy/inventory/summary - 库存总览（各仓库库存条目数）
router.get('/inventory/summary', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [warehouses] = await pool.query(`SELECT id, name, address, type, manager, status FROM warehouses ORDER BY id`)
    const [stockAgg] = await pool.query(`
      SELECT warehouse_id, COUNT(*) AS item_count, COALESCE(SUM(quantity),0) AS total_qty
      FROM warehouse_stock GROUP BY warehouse_id
    `)
    const [,] = [warehouses, stockAgg]
    const stockMap = {}
    for (const s of stockAgg) stockMap[s.warehouse_id] = s

    const top = warehouses.map(w => ({
      id: w.id, name: w.name, type: w.type, manager: w.manager, status: w.status,
      item_count: stockMap[w.id] ? Number(stockMap[w.id].item_count) : 0,
      total_qty: stockMap[w.id] ? Number(stockMap[w.id].total_qty) : 0,
    }))

    res.json({
      warehouses: top,
      summary: {
        warehouse_count: top.length,
        total_items: top.reduce((a, b) => a + b.item_count, 0),
      },
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/inventory/alerts - 库存预警列表
router.get('/inventory/alerts', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT id, sku, name, stock, alert_stock, category, spec, unit
      FROM products
      WHERE status = 1 AND stock <= alert_stock
      ORDER BY (stock - alert_stock) ASC
    `)
    res.json({
      alerts: rows.map(r => ({
        id: r.id, sku: r.sku, name: r.name,
        stock: Number(r.stock) || 0, alert_stock: Number(r.alert_stock) || 0,
        category: r.category, spec: r.spec, unit: r.unit,
        shortfall: Math.max(0, (Number(r.alert_stock)||0) - (Number(r.stock)||0)),
      })),
      count: rows.length,
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/inventory/check?q=关键词 - 按SKU/品名搜索库存
router.get('/inventory/check', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const q = String(req.query.q || '').trim()
    if (!q) return res.status(400).json({ error: 'q required' })
    const like = `%${q}%`
    const [rows] = await pool.query(`
      SELECT id, sku, name, stock, alert_stock, category, spec, unit, status
      FROM products
      WHERE (sku LIKE ? OR name LIKE ? OR category LIKE ?)
      ORDER BY id DESC LIMIT 30
    `, [like, like, like])
    res.json({
      query: q,
      products: rows.map(r => ({
        id: r.id, sku: r.sku, name: r.name, category: r.category, spec: r.spec, unit: r.unit,
        stock: Number(r.stock)||0, alert_stock: Number(r.alert_stock)||0, status: r.status,
      })),
      count: rows.length,
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/inventory/warehouse/:id - 指定仓库库存明细
router.get('/inventory/warehouse/:id', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const id = Number(req.params.id)
    if (!id) return res.status(400).json({ error: 'invalid id' })
    const [[wh]] = await pool.query(`SELECT name FROM warehouses WHERE id=?`, [id])
    if (!wh) return res.status(404).json({ error: 'warehouse not found' })
    const [rows] = await pool.query(`
      SELECT ws.product_id, p.sku, p.name, p.spec, p.unit, ws.quantity, ws.location
      FROM warehouse_stock ws
      LEFT JOIN products p ON p.id = ws.product_id
      WHERE ws.warehouse_id = ? AND ws.quantity > 0
      ORDER BY ws.quantity DESC
    `, [id])
    res.json({
      warehouse_id: id, warehouse_name: wh.name,
      items: rows.map(r => ({
        product_id: r.product_id, sku: r.sku, name: r.name, spec: r.spec, unit: r.unit,
        quantity: Number(r.quantity)||0, location: r.location,
      })),
      count: rows.length,
    })
  } catch (err) { next(err) }
})

export default router