import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// GET /api/workbuddy/products/summary - 商品总览（总数/上架/预警）
router.get('/products/summary', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [[total]] = await pool.query(`SELECT COUNT(*) AS c FROM products`)
    const [[active]] = await pool.query(`SELECT COUNT(*) AS c FROM products WHERE status='active'`)
    const [[alert]] = await pool.query(`SELECT COUNT(*) AS c FROM products WHERE status='active' AND stock <= alert_stock`)
    const [[stockSum]] = await pool.query(`SELECT COUNT(DISTINCT product_id) AS c FROM warehouse_stock`)
    res.json({
      total_products: Number(total.c),
      active_products: Number(active.c),
      low_stock_alerts: Number(alert.c),
      stocked_in_warehouses: Number(stockSum.c),
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/products/list?page=1&limit=20 - 商品列表（分页）
router.get('/products/list', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const page = Math.max(Number(req.query.page) || 1, 1)
    const limit = Math.min(Number(req.query.limit) || 20, 100)
    const offset = (page - 1) * limit
    const [[{ c }]] = await pool.query(`SELECT COUNT(*) AS c FROM products`)
    const [rows] = await pool.query(`
      SELECT id, sku, name, category, spec, unit, stock, alert_stock,
             sale_price, purchase_price, status, created_at
      FROM products
      ORDER BY id DESC LIMIT ? OFFSET ?
    `, [limit, offset])
    res.json({
      page, limit, total: Number(c), total_pages: Math.ceil(Number(c)/limit),
      products: rows.map(r => ({
        id: r.id, sku: r.sku, name: r.name, category: r.category, spec: r.spec, unit: r.unit,
        stock: Number(r.stock)||0, alert_stock: Number(r.alert_stock)||0,
        sale_price: Number(r.sale_price)||0, purchase_price: Number(r.purchase_price)||0,
        status: r.status, created_at: r.created_at,
      })),
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/products/search?q=关键词 - 商品搜索
router.get('/products/search', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const q = String(req.query.q || '').trim()
    if (!q) return res.status(400).json({ error: 'q required' })
    const like = `%${q}%`
    const [rows] = await pool.query(`
      SELECT id, sku, name, category, spec, unit, stock, alert_stock, sale_price, status
      FROM products
      WHERE sku LIKE ? OR name LIKE ? OR category LIKE ?
      ORDER BY id DESC LIMIT 30
    `, [like, like, like])
    res.json({
      query: q,
      products: rows.map(r => ({
        id: r.id, sku: r.sku, name: r.name, category: r.category, spec: r.spec, unit: r.unit,
        stock: Number(r.stock)||0, alert_stock: Number(r.alert_stock)||0,
        sale_price: Number(r.sale_price)||0, status: r.status,
      })),
      count: rows.length,
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/products/:id - 商品详情（含各仓库库存分布）
// ⚠️ 必须定义在 /summary /list /search 之后（express 匹配顺序）
router.get('/products/:id', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const id = Number(req.params.id)
    if (!id) return res.status(400).json({ error: 'invalid id' })
    const [[p]] = await pool.query(`
      SELECT id, sku, name, category, category_id, spec, unit, supplier,
             purchase_price, sale_price, stock, alert_stock, turnover_days,
             status, publish_status, require_qrcode, orderable, created_at,
             image_main
      FROM products WHERE id=?
    `, [id])
    if (!p) return res.status(404).json({ error: 'product not found' })

    // 各仓库库存分布
    const [whStock] = await pool.query(`
      SELECT ws.warehouse_id, w.name AS warehouse_name, ws.quantity, ws.updated_at
      FROM warehouse_stock ws LEFT JOIN warehouses w ON w.id = ws.warehouse_id
      WHERE ws.product_id=? ORDER BY ws.quantity DESC
    `, [id]).catch(() => [[]])

    res.json({
      product: {
        id: p.id, sku: p.sku, name: p.name, category: p.category, spec: p.spec, unit: p.unit,
        supplier: p.supplier,
        purchase_price: Number(p.purchase_price)||0, sale_price: Number(p.sale_price)||0,
        stock: Number(p.stock)||0, alert_stock: Number(p.alert_stock)||0,
        turnover_days: p.turnover_days, status: p.status, publish_status: p.publish_status,
        require_qrcode: p.require_qrcode, orderable: p.orderable,
        created_at: p.created_at, image_main: p.image_main,
        low_stock: Number(p.stock) <= Number(p.alert_stock),
      },
      warehouse_stock: (whStock||[]).map(w => ({
        warehouse_id: w.warehouse_id, warehouse: w.warehouse_name || `#${w.warehouse_id}`,
        quantity: Number(w.quantity)||0, updated_at: w.updated_at,
      })),
    })
  } catch (err) { next(err) }
})

export default router