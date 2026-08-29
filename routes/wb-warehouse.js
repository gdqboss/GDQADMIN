import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// GET /api/workbuddy/warehouses - 仓库列表
router.get('/warehouses', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT w.id, w.name, w.address, w.type, w.manager, w.status,
             (SELECT COUNT(*) FROM warehouse_stock ws WHERE ws.warehouse_id = w.id) AS item_count,
             (SELECT COALESCE(SUM(quantity),0) FROM warehouse_stock ws WHERE ws.warehouse_id = w.id) AS total_qty
      FROM warehouses w
      ORDER BY w.id
    `)
    res.json({
      warehouses: rows.map(r => ({
        id: r.id, name: r.name, address: r.address, type: r.type,
        manager: r.manager, status: r.status,
        item_count: Number(r.item_count)||0, total_qty: Number(r.total_qty)||0,
      })),
      count: rows.length,
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/warehouses/:id - 仓库详情（含库存汇总）
router.get('/warehouses/:id', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const id = Number(req.params.id)
    if (!id) return res.status(400).json({ error: 'invalid id' })
    const [[w]] = await pool.query(`SELECT * FROM warehouses WHERE id=?`, [id])
    if (!w) return res.status(404).json({ error: 'warehouse not found' })
    const [items] = await pool.query(`
      SELECT ws.product_id, p.sku, p.name, p.category, p.spec, p.unit, ws.quantity, ws.location
      FROM warehouse_stock ws
      LEFT JOIN products p ON p.id = ws.product_id
      WHERE ws.warehouse_id = ?
      ORDER BY ws.quantity DESC
    `, [id])
    const [[agg]] = await pool.query(`
      SELECT COALESCE(SUM(quantity),0) AS total_qty, COUNT(*) AS item_count
      FROM warehouse_stock WHERE warehouse_id=?
    `, [id])
    res.json({
      warehouse: {
        id: w.id, name: w.name, address: w.address, type: w.type,
        manager: w.manager, status: w.status,
        total_qty: Number(agg.total_qty)||0, item_count: Number(agg.item_count)||0,
      },
      items: (items||[]).map(i => ({
        product_id: i.product_id, sku: i.sku, name: i.name, category: i.category,
        spec: i.spec, unit: i.unit, quantity: Number(i.quantity)||0, location: i.location,
      })),
    })
  } catch (err) { next(err) }
})

export default router