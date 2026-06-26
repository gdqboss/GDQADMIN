import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

// 出入库后 recheck 库存预警（与 inventory.js 同款）
async function recheckStockAlert(conn, productId, warehouseId) {
  const [[alertProd]] = await conn.query(
    'SELECT id, stock, alert_stock FROM products WHERE id = ?', [productId])
  if (!alertProd) return

  // alert_stock = NULL/0 → 把现有未处理预警标 handled（避免僵尸预警）
  if (!alertProd.alert_stock || alertProd.alert_stock <= 0) {
    await conn.query(
      `UPDATE stock_alerts SET handled = 1, handled_at = NOW(),
       handled_reason = 'auto: alert_stock=0/NULL (warning disabled)'
       WHERE product_id = ? AND warehouse_id = ? AND handled = 0`,
      [productId, warehouseId])
    return
  }

  // 波哥 2026-07-26 规则：建议补货 = alert_stock - 实时库存（补到预警线即可）
  // 库存 ≥ 预警线 → 已有预警自动 handled（不需要保留，警告已经解除）
  if (alertProd.stock < alertProd.alert_stock) {
    const suggestQty = alertProd.alert_stock - alertProd.stock
    const [[existing]] = await conn.query(
      'SELECT id FROM stock_alerts WHERE product_id = ? AND warehouse_id = ? AND handled = 0 LIMIT 1',
      [productId, warehouseId])
    if (!existing) {
      const level = alertProd.stock <= alertProd.alert_stock * 0.5 ? 'critical' : 'low'
      await conn.query(
        `INSERT INTO stock_alerts (product_id, warehouse_id, current_stock, alert_stock, suggest_qty, level)
         VALUES (?,?,?,?,?,?)`,
        [productId, warehouseId, alertProd.stock, alertProd.alert_stock, suggestQty, level])
    }
  } else {
    // 库存 ≥ 预警线 → 自动 handled（警告已解除，业务允许库存高于预警值）
    await conn.query(
      `UPDATE stock_alerts SET handled = 1, handled_at = NOW(),
       handled_reason = 'auto: stock >= alert_stock after operation'
       WHERE product_id = ? AND warehouse_id = ? AND handled = 0`,
      [productId, warehouseId])
  }
}

router.get('/', async (req, res, next) => {
  try {
    const { handled } = req.query
    // 修复（波哥 2026-07-26 反馈）：stock_alerts.current_stock 是创建时的快照，
    // 库存变化后表格还在显示旧值（5/8/2），需要 JOIN warehouse_stock 实时算
    // suggest_qty 也按实时库存算：MAX(0, alert_stock - 实时库存)（补到预警线即可）
    let sql = `SELECT a.id, a.product_id, a.warehouse_id, a.alert_stock,
                      a.level, a.handled,
                      a.handled_at, a.handled_reason, a.created_at,
                      p.name as product_name, p.sku, w.name as warehouse_name,
                      COALESCE((
                        SELECT SUM(quantity) FROM warehouse_stock
                        WHERE product_id = a.product_id
                          AND warehouse_id = a.warehouse_id
                          AND quantity > 0
                      ), 0) AS current_stock,
                      GREATEST(0, a.alert_stock - COALESCE((
                        SELECT SUM(quantity) FROM warehouse_stock
                        WHERE product_id = a.product_id
                          AND warehouse_id = a.warehouse_id
                          AND quantity > 0
                      ), 0)) AS suggest_qty
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
    // 波哥 2026-07-26 规则：建议补 = MAX(0, alert_stock - 实时库存)
    const [products] = await pool.query('SELECT * FROM products WHERE stock <= alert_stock AND alert_stock > 0')
    let created = 0
    for (const p of products) {
      const [[existing]] = await pool.query(
        'SELECT id FROM stock_alerts WHERE product_id = ? AND handled = FALSE', [p.id]
      )
      if (!existing) {
        const level = p.stock <= p.alert_stock * 0.5 ? 'critical' : 'low'
        const suggestQty = p.alert_stock - p.stock  // 补到预警线即可
        await pool.query(
          'INSERT INTO stock_alerts (product_id, current_stock, alert_stock, suggest_qty, level) VALUES (?,?,?,?,?)',
          [p.id, p.stock, p.alert_stock, suggestQty, level]
        )
        created++
      }
    }
    res.json({ code: 0, data: { created }, message: 'ok' })
  } catch (err) { next(err) }
})

export default router

// 批量补货（库存预警页面"一键补货"按钮调用）
// 业务规则（波哥 2026-07-19 确认）：
// 1. 入参：{ alert_ids?: number[], override_qty?: number }
//    - alert_ids 不传 = 处理所有未处理预警
//    - alert_ids 传 = 只处理选中的
// 2. override_qty 传了 = 全部用这个数量；不传 = 每条用各自的 suggest_qty
// 3. 按 warehouse_id 分组，每个仓库生成 1 张入库单
// 4. 入库后自动 handled=1，标记处理时间和原因
router.post('/batch-replenish', requirePermission(PERMISSIONS.INVENTORY_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { alert_ids, override_qty, total_qty } = req.body
    // 兼容两种用法：
    //   override_qty = 每条预警用这个数（你之前说的"每条数量"）
    //   total_qty    = 总数，自动平均分给每条（"一次性补多少"）
    let override = null
    if (total_qty !== undefined && total_qty !== null && total_qty !== '') {
      override = { mode: 'total', value: Number(total_qty) }
    } else if (override_qty !== undefined && override_qty !== null && override_qty !== '') {
      override = { mode: 'each', value: Number(override_qty) }
    }
    if (override && (!Number.isFinite(override.value) || override.value < 0)) {
      return res.status(400).json({ code: 400, message: '数量必须是非负数' })
    }

    // 1. 查符合条件的预警（未处理、有 warehouse_id）
    let where = 'a.handled = 0 AND a.warehouse_id IS NOT NULL'
    const params = []
    if (Array.isArray(alert_ids) && alert_ids.length > 0) {
      where += ' AND a.id IN (?)'
      params.push(alert_ids)
    }
    const [alerts] = await conn.query(
      `SELECT a.id, a.product_id, a.warehouse_id, a.suggest_qty, p.alert_stock, p.stock
       FROM stock_alerts a JOIN products p ON a.product_id = p.id WHERE ${where}`, params)
    if (alerts.length === 0) {
      await conn.rollback()
      return res.json({ code: 0, data: { created: 0, records: [], message: '没有可补货的预警' }, message: 'ok' })
    }

    // 2. 按 warehouse_id 分组
    const byWh = {}
    for (const a of alerts) {
      if (!byWh[a.warehouse_id]) byWh[a.warehouse_id] = []
      byWh[a.warehouse_id].push(a)
    }

    const operator = req.user?.name || 'system'
    const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '')
    const tsSuffix = String(Date.now()).slice(-4) + Math.floor(Math.random() * 100).toString().padStart(2, '0')
    const results = []
    let seq = 0

    for (const [whId, items] of Object.entries(byWh)) {
      seq++
      const recordNo = `RK-${dateStr}-${tsSuffix}-BATCH${seq}`

      // total 模式先算每条均分值
      let perItem = 0
      if (override && override.mode === 'total') {
        perItem = Math.floor(override.value / items.length)
        // 余数加到第一条
      }

      const totalQty = items.reduce((s, i) => {
        const q = !override ? (i.suggest_qty || 0)
                : override.mode === 'each' ? override.value
                : perItem
        return s + q
      }, 0)

      const [recRes] = await conn.query(
        'INSERT INTO inbound_records (record_no, warehouse_id, supplier, total_qty, operator, status, remark) VALUES (?,?,?,?,?,?,?)',
        [recordNo, Number(whId), '批量补货-库存预警', totalQty, operator, 'completed',
         `一键补货 ${items.length} 个 SKU` + (override?.mode === 'total' ? `（总数 ${override.value} 均分）` : '')])
      const recordId = recRes.insertId

      for (let idx = 0; idx < items.length; idx++) {
        const a = items[idx]
        let qty = !override ? (a.suggest_qty || 0)
                : override.mode === 'each' ? override.value
                : perItem
        // total 模式：余数加到第一条
        if (override && override.mode === 'total' && idx === 0) {
          qty += override.value - perItem * items.length
        }
        if (qty <= 0) continue

        // 写明细（兼容 alert_stock 字段）
        await conn.query(
          'INSERT INTO inbound_items (record_id, product_id, sku_id, qrcode_count, quantity, alert_stock) VALUES (?,?,?,?,?,?)',
          [recordId, a.product_id, null, 0, qty, a.alert_stock || 0])

        // 累加 warehouse_stock
        const [[existing]] = await conn.query(
          `SELECT id, quantity FROM warehouse_stock
           WHERE warehouse_id=? AND product_id=? AND sku_id IS NULL FOR UPDATE`,
          [whId, a.product_id])
        if (existing) {
          await conn.query('UPDATE warehouse_stock SET quantity = quantity + ? WHERE id = ?', [qty, existing.id])
        } else {
          await conn.query(
            `INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,NULL,?)`,
            [whId, a.product_id, qty])
        }
        // 写流水
        await conn.query(
          `INSERT INTO stock_movements
           (warehouse_id, product_id, sku_id, change_type, delta, before_qty, after_qty, operator, ref_type, ref_id, remark)
           VALUES (?,?,?,?,?,?,?,?,?,?,?)`,
          [whId, a.product_id, null, 'inbound', qty,
           existing ? existing.quantity : 0, (existing ? existing.quantity : 0) + qty,
           operator, 'inbound_records', recordId, '批量补货-库存预警'])
        // 更新 products.stock
        await conn.query('UPDATE products SET stock = COALESCE(stock, 0) + ? WHERE id = ?', [qty, a.product_id])

        // ✅ 补货后 recheck 库存预警（统一由 recheckStockAlert 决定 handled=0/1）
        // 不能在这里直接 handled=1，要看补完后 stock 是否真的 >= alert_stock
        await recheckStockAlert(conn, a.product_id, Number(whId))
      }

      // 标记 alert 为 handled（仅对已经 handled 的；recheck 函数会处理）
      // recheckStockAlert 已经处理了 handled 状态，这里不需要重复

      results.push({ warehouse_id: Number(whId), record_no: recordNo, item_count: items.length, total_qty: totalQty })
    }

    await conn.commit()
    res.json({ code: 0, data: { created: results.length, records: results, alert_count: alerts.length }, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})
