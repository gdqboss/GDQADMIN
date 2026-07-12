import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requirePermission, requireRole, PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

// GET /warehouses - 仓库列表
router.get('/', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM warehouses ORDER BY created_at DESC')
    if (rows.length) {
      const ids = rows.map(w => w.id)
      const [stockSummary] = await pool.query(
        `SELECT warehouse_id, COALESCE(SUM(quantity),0) as total_qty, COUNT(*) as product_count
         FROM warehouse_stock WHERE warehouse_id IN (?) GROUP BY warehouse_id`, [ids]
      )
      const summaryMap = {}
      for (const s of stockSummary) summaryMap[s.warehouse_id] = s
      for (const wh of rows) {
        const s = summaryMap[wh.id]
        wh.totalQty = s ? s.total_qty : 0
        wh.productCount = s ? s.product_count : 0
      }
    }
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /warehouses/:id - 仓库详情
router.get('/:id', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM warehouses WHERE id = ?', [req.params.id])
    if (!rows.length) return res.status(404).json({ code: 404, message: '仓库不存在' })
    const warehouse = rows[0]
    const [stockRows] = await pool.query(
      `SELECT ws.*, p.name as product_name, p.sku, p.category, p.unit
       FROM warehouse_stock ws JOIN products p ON ws.product_id = p.id
       WHERE ws.warehouse_id = ?`,
      [req.params.id]
    )
    warehouse.stockList = stockRows
    res.json({ code: 0, data: warehouse, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /warehouses - 创建仓库 (需要 warehouses_write 权限)
router.post('/', requirePermission(PERMISSIONS.WAREHOUSES_WRITE), async (req, res, next) => {
  try {
    const { name, address, type, manager } = req.body
    if (!name) return res.status(400).json({ code: 400, message: '仓库名称必填' })
    const [result] = await pool.query(
      'INSERT INTO warehouses (name, address, type, manager) VALUES (?,?,?,?)',
      [name, address, type, manager]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /warehouses/:id - 更新仓库 (需要 warehouses_write 权限)
router.put('/:id', requirePermission(PERMISSIONS.WAREHOUSES_WRITE), async (req, res, next) => {
  try {
    const { name, address, type, manager, status } = req.body
    await pool.query(
      'UPDATE warehouses SET name=?, address=?, type=?, manager=?, status=? WHERE id=?',
      [name, address, type, manager, status, req.params.id]
    )
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE /warehouses/:id - 删除仓库 (需要 warehouses_delete 权限，仅admin)
router.delete('/:id', requireRole('admin'), async (req, res, next) => {
  try {
    const warehouseId = req.params.id

    const [[stockCheck]] = await pool.query(
      'SELECT COUNT(*) as count FROM warehouse_stock WHERE warehouse_id = ?',
      [warehouseId]
    )

    if (stockCheck.count > 0) {
      return res.status(400).json({
        code: 400,
        message: `无法删除：该仓库还有 ${stockCheck.count} 条库存记录。请先清空库存或转移到其他仓库。`
      })
    }

    await pool.query('DELETE FROM warehouses WHERE id = ?', [warehouseId])
    res.json({ code: 0, data: null, message: '删除成功' })
  } catch (err) {
    next(err)
  }
})

// ─── Stock Management ──────────────────────────────────────────────────────

// Adjust stock quantity (库存盘点调整 - 需要 inventory_write)
router.post('/:warehouseId/stock/:productId/adjust', requirePermission(PERMISSIONS.INVENTORY_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { quantity, type, reason, reference_no } = req.body
    const { warehouseId, productId } = req.params

    if (quantity === undefined || !type) {
      return res.status(400).json({ code: 400, message: 'quantity和type必填' })
    }

    const [[current]] = await conn.query(
      'SELECT quantity FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? FOR UPDATE',
      [warehouseId, productId]
    )

    let newQty
    if (type === 'set') {
      newQty = quantity
    } else if (type === 'add') {
      newQty = (current?.quantity || 0) + quantity
    } else if (type === 'subtract') {
      newQty = (current?.quantity || 0) - quantity
      if (newQty < 0) {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: '库存不足' })
      }
    } else {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '无效的调整类型' })
    }

    if (current) {
      await conn.query(
        'UPDATE warehouse_stock SET quantity = ? WHERE warehouse_id = ? AND product_id = ?',
        [newQty, warehouseId, productId]
      )
    } else {
      await conn.query(
        'INSERT INTO warehouse_stock (warehouse_id, product_id, quantity) VALUES (?, ?, ?)',
        [warehouseId, productId, newQty]
      )
    }

    await conn.query(
      `INSERT INTO warehouse_stock_history 
       (warehouse_id, product_id, type, quantity_change, quantity_after, reason, reference_no, operator_id)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [warehouseId, productId, type, quantity, newQty, reason, reference_no, req.user.id]
    )

    await conn.commit()
    res.json({ code: 0, data: { quantity: newQty }, message: '调整成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// DELETE /warehouses/:warehouseId/stock/:productId - 删除库存记录
router.delete('/:warehouseId/stock/:productId', requirePermission(PERMISSIONS.INVENTORY_WRITE), async (req, res, next) => {
  try {
    await pool.query(
      'DELETE FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ?',
      [req.params.warehouseId, req.params.productId]
    )
    res.json({ code: 0, data: null, message: '删除成功' })
  } catch (err) { next(err) }
})

// POST /warehouses/:warehouseId/stock/:productId/transfer - 调库
router.post('/:warehouseId/stock/:productId/transfer', requirePermission(PERMISSIONS.INVENTORY_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { to_warehouse_id, quantity } = req.body
    const { warehouseId, productId } = req.params

    if (!to_warehouse_id || !quantity) {
      return res.status(400).json({ code: 400, message: '目标仓库和数量必填' })
    }

    const [[fromStock]] = await conn.query(
      'SELECT quantity FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? FOR UPDATE',
      [warehouseId, productId]
    )

    if (!fromStock || fromStock.quantity < quantity) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '库存不足' })
    }

    // 出库
    await conn.query(
      'UPDATE warehouse_stock SET quantity = quantity - ? WHERE warehouse_id = ? AND product_id = ?',
      [quantity, warehouseId, productId]
    )

    // 入库
    const [[toStock]] = await conn.query(
      'SELECT id, quantity FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? FOR UPDATE',
      [to_warehouse_id, productId]
    )

    if (toStock) {
      await conn.query(
        'UPDATE warehouse_stock SET quantity = quantity + ? WHERE warehouse_id = ? AND product_id = ?',
        [quantity, to_warehouse_id, productId]
      )
    } else {
      await conn.query(
        'INSERT INTO warehouse_stock (warehouse_id, product_id, quantity) VALUES (?, ?, ?)',
        [to_warehouse_id, productId, quantity]
      )
    }

    // 记录调库历史
    await conn.query(
      `INSERT INTO warehouse_stock_history 
       (warehouse_id, product_id, type, quantity_change, quantity_after, reason, reference_no, operator_id)
       VALUES (?, ?, 'transfer_out', ?, ?, '调库', ?, ?)`,
      [warehouseId, productId, -quantity, fromStock.quantity - quantity, `TO:${to_warehouse_id}`, req.user.id]
    )

    await conn.commit()
    res.json({ code: 0, data: null, message: '调库成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// GET /warehouses/:warehouseId/stock/:productId/history - 库存历史
router.get('/:warehouseId/stock/:productId/history', requirePermission(PERMISSIONS.INVENTORY_READ), async (req, res, next) => {
  try {
    const { warehouseId, productId } = req.params
    const [rows] = await pool.query(
      `SELECT h.*, u.name as operator_name
       FROM warehouse_stock_history h
       LEFT JOIN users u ON h.operator_id = u.id
       WHERE h.warehouse_id = ? AND h.product_id = ?
       ORDER BY h.created_at DESC
       LIMIT 100`,
      [warehouseId, productId]
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /warehouses/batch-delete - 批量删除仓库 (仅admin)
router.post('/batch-delete', requireRole('admin'), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { ids } = req.body
    if (!ids || !Array.isArray(ids) || ids.length === 0) {
      return res.status(400).json({ code: 400, message: '请提供要删除的仓库ID列表' })
    }

    await conn.beginTransaction()

    // 检查每个仓库是否有库存
    const [stockChecks] = await conn.query(
      `SELECT warehouse_id, COUNT(*) as count FROM warehouse_stock 
       WHERE warehouse_id IN (?) GROUP BY warehouse_id`,
      [ids]
    )

    const warehousesWithStock = stockChecks
      .filter(s => s.count > 0)
      .map(s => s.warehouse_id)

    if (warehousesWithStock.length > 0) {
      await conn.rollback()
      return res.status(400).json({
        code: 400,
        message: `以下仓库还有库存记录，无法删除：${warehousesWithStock.join(', ')}`
      })
    }

    // 删除仓库
    await conn.query('DELETE FROM warehouses WHERE id IN (?)', [ids])

    await conn.commit()
    res.json({ code: 0, data: null, message: `成功删除 ${ids.length} 个仓库` })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

export default router
