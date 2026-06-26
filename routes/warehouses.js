import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requirePermission, requireRole, PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

// GET /warehouses/available-products - 所有仓库有库存的商品 + SKU（用于订货选择表）
router.get('/available-products', requirePermission(PERMISSIONS.INVENTORY_READ), async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT
        p.id, p.sku, p.name, p.image_main, p.sale_price, p.unit, p.category,
        COALESCE(SUM(ws.quantity), 0) AS total_stock
      FROM products p
      INNER JOIN warehouse_stock ws ON ws.product_id = p.id AND ws.quantity > 0
      GROUP BY p.id
      HAVING total_stock > 0
      ORDER BY p.name ASC
    `)
    // 加载每个商品的 SKU
    for (const p of rows) {
      const [skus] = await pool.query(`
        SELECT id, sku, sku AS sku_code,
          COALESCE(specs, '{}') AS specs,
          COALESCE(purchase_price, 0) AS unit_price,
          image
        FROM product_skus
        WHERE product_id = ?
        ORDER BY id ASC
      `, [p.id])
      p.skus = skus
    }
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /warehouses - 仓库列表
router.get('/', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM warehouses ORDER BY created_at DESC')
    if (rows.length) {
      const ids = rows.map(w => w.id)
      const [stockSummary] = await pool.query(
        `SELECT warehouse_id, COALESCE(SUM(quantity),0) as total_qty,
                COUNT(DISTINCT CASE WHEN quantity > 0 THEN product_id END) as product_count
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
       (warehouse_id, product_id, type, quantity_change, quantity_after, reason, reference_no, operator_id, operator_name)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [warehouseId, productId, type, quantity, newQty, reason || null, reference_no || null, req.user?.id || null, req.user?.name || null]
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
    // 历史来源：warehouse_stock_history（adjust/transfer）+ inbound_records + outbound_records
    const [rows] = await pool.query(`
      SELECT
        h.id, h.warehouse_id, h.product_id, h.sku_id, h.type,
        h.quantity_change AS quantity, h.quantity_after, h.reference_no AS record_no,
        h.party, h.remark, h.reason, h.operator_id, h.operator_name, h.created_at
      FROM warehouse_stock_history h
      WHERE h.warehouse_id = ? AND h.product_id = ?
      ORDER BY h.created_at DESC
      LIMIT 200
    `, [warehouseId, productId])

    // 兼容字段：operator_name 优先，否则用 operator_id
    const result = rows.map(r => ({
      ...r,
      operator: r.operator_name || (r.operator_id ? `用户#${r.operator_id}` : '—')
    }))

    res.json({ code: 0, data: result, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /warehouses/:warehouseId/history - 整个仓库的所有操作日志（按仓库维度）
// 合并来源：warehouse_stock_history + stock_movements + inbound_audit_log
router.get('/:warehouseId/history', requirePermission(PERMISSIONS.INVENTORY_READ), async (req, res, next) => {
  try {
    const { warehouseId } = req.params
    const { limit = 200, type, operator, start_date, end_date } = req.query
    const whId = Number(warehouseId)

    const filterConditions = []
    const filterParams = []

    if (type) {
      filterConditions.push('change_type = ?')
      filterParams.push(type)
    }
    if (operator) {
      filterConditions.push('operator_name LIKE ?')
      filterParams.push(`%${operator}%`)
    }
    if (start_date) {
      filterConditions.push('created_at >= ?')
      filterParams.push(start_date)
    }
    if (end_date) {
      filterConditions.push('created_at <= ?')
      filterParams.push(end_date)
    }

    const where = filterConditions.length ? `WHERE ${filterConditions.join(' AND ')}` : ''

    const sql = `
      SELECT * FROM (
        SELECT h.id, h.warehouse_id, h.product_id,
          p.name COLLATE utf8mb4_unicode_ci AS product_name,
          p.sku COLLATE utf8mb4_unicode_ci AS product_sku,
          h.sku_id,
          CASE WHEN h.type = 'adjust' THEN 'adjust'
               WHEN h.type LIKE 'transfer_%' THEN 'transfer'
               ELSE h.type END COLLATE utf8mb4_unicode_ci AS change_type,
          h.quantity_change AS delta, h.quantity_after AS before_qty, h.quantity_after AS after_qty,
          h.reference_no COLLATE utf8mb4_unicode_ci AS record_no,
          h.party COLLATE utf8mb4_unicode_ci AS party,
          h.remark COLLATE utf8mb4_unicode_ci AS remark,
          h.reason COLLATE utf8mb4_unicode_ci AS reason,
          h.operator_id,
          h.operator_name COLLATE utf8mb4_unicode_ci AS operator_name,
          h.created_at,
          'history' AS source
        FROM warehouse_stock_history h
        LEFT JOIN products p ON p.id = h.product_id
        WHERE h.warehouse_id = ?
        UNION ALL
        SELECT sm.id, sm.warehouse_id, sm.product_id, p.name AS product_name, p.sku AS product_sku,
          sm.sku_id, sm.change_type, sm.delta, sm.before_qty, sm.after_qty,
          NULL AS record_no, NULL AS party, NULL AS remark, sm.remark AS reason,
          NULL AS operator_id, sm.operator AS operator_name, sm.created_at,
          'movement' AS source
        FROM stock_movements sm
        LEFT JOIN products p ON p.id = sm.product_id
        WHERE sm.warehouse_id = ?
        UNION ALL
        SELECT ial.id, ir.warehouse_id,
          COALESCE(
            (SELECT product_id FROM inbound_items WHERE id = ial.item_id),
            (SELECT product_id FROM inbound_items WHERE record_id = ial.record_id ORDER BY id LIMIT 1),
            ial.item_id
          ) AS product_id,
          (SELECT p.name FROM products p WHERE p.id = COALESCE(
            (SELECT product_id FROM inbound_items WHERE id = ial.item_id),
            (SELECT product_id FROM inbound_items WHERE record_id = ial.record_id ORDER BY id LIMIT 1),
            ial.item_id
          )) AS product_name,
          (SELECT p.sku FROM products p WHERE p.id = COALESCE(
            (SELECT product_id FROM inbound_items WHERE id = ial.item_id),
            (SELECT product_id FROM inbound_items WHERE record_id = ial.record_id ORDER BY id LIMIT 1),
            ial.item_id
          )) AS product_sku,
          NULL AS sku_id,
          CASE ial.action WHEN 'create' THEN 'inbound' WHEN 'update' THEN 'adjust' WHEN 'delete' THEN 'delete' END AS change_type,
          COALESCE(ial.after_qty,0) - COALESCE(ial.before_qty,0) AS delta,
          ial.before_qty, ial.after_qty,
          ir.record_no AS record_no, NULL AS party, ial.note AS remark, ial.note AS reason,
          ial.operator_id, ial.operator_name, ial.created_at,
          'audit' AS source
        FROM inbound_audit_log ial
        LEFT JOIN inbound_records ir ON ir.id = ial.record_id
        WHERE ir.warehouse_id = ?
      ) AS merged
      ${where}
      ORDER BY created_at DESC
      LIMIT ?
    `

    const finalParams = [whId, whId, whId, ...filterParams, Number(limit)]
    const [rows] = await pool.query(sql, finalParams)

    const result = rows.map(r => ({
      ...r,
      operator: r.operator_name || (r.operator_id ? `用户#${r.operator_id}` : '—')
    }))

    res.json({ code: 0, data: result, message: 'ok' })
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
