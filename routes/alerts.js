import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

router.get('/', async (req, res, next) => {
  try {
    const { handled } = req.query
    let sql = `SELECT a.*, p.name as product_name, p.sku, w.name as warehouse_name
               FROM stock_alerts a
               LEFT JOIN products p ON a.product_id = p.id
               LEFT JOIN warehouses w ON a.warehouse_id = w.id WHERE 1=1`
    const params = []
    if (handled !== undefined) {
      sql += ' AND a.handled = ?'
      params.push(handled === 'true' ? 1 : 0)
    }
    sql += ' ORDER BY a.created_at DESC'
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

router.put('/:id', async (req, res, next) => {
  try {
    await pool.query('UPDATE stock_alerts SET handled = TRUE WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// Check and generate alerts for all products
router.post('/check', async (req, res, next) => {
  try {
    const [products] = await pool.query('SELECT * FROM products WHERE stock <= safe_stock AND safe_stock > 0')
    let created = 0
    for (const p of products) {
      const [[existing]] = await pool.query(
        'SELECT id FROM stock_alerts WHERE product_id = ? AND handled = FALSE', [p.id]
      )
      if (!existing) {
        const level = p.stock <= p.safe_stock * 0.5 ? 'critical' : 'low'
        const suggestQty = p.safe_stock * 2 - p.stock
        await pool.query(
          'INSERT INTO stock_alerts (product_id, current_stock, safe_stock, suggest_qty, level) VALUES (?,?,?,?,?)',
          [p.id, p.stock, p.safe_stock, suggestQty, level]
        )
        created++
      }
    }
    res.json({ code: 0, data: { created }, message: 'ok' })
  } catch (err) { next(err) }
})

export default router
