import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { requirePermission, requireRole, PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

// 一物一码生成 helper（复用 qrcode.js /batch 的编码规则）
async function generateQrcodes(conn, { count, product_id, sku_id, warehouse_id, operator, inbound_item_id }) {
  if (!count || count <= 0) return []
  const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '')
  const [[{ maxSeq }]] = await conn.query(
    "SELECT COUNT(*) as maxSeq FROM qrcodes WHERE code LIKE ?",
    [`GDQ-${dateStr}%`]
  )
  const codes = []
  for (let i = 0; i < count; i++) {
    const seq = String(maxSeq + i + 1).padStart(6, '0')
    const code = `GDQ-${dateStr}-${seq}`
    await conn.query(
      'INSERT INTO qrcodes (code, product_id, sku_id, warehouse_id, status, inbound_by, inbound_item_id) VALUES (?,?,?,?,?,?,?)',
      [code, product_id, sku_id || null, warehouse_id, 'inStock', operator, inbound_item_id || null]
    )
    codes.push(code)
  }
  return codes
}

/**
// 出入库后 recheck 库存预警
// 事务内调用（用同一个 conn）
// stock < alert_stock → 触发/保留预警；stock >= alert_stock → 自动 handled
*/
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

// ---- Inbound ----
router.get('/inbound', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    const offset = (page - 1) * size
    const [[{ total }]] = await pool.query('SELECT COUNT(*) as total FROM inbound_records')
    const [rows] = await pool.query(
      `SELECT r.*, w.name as warehouse_name FROM inbound_records r
       LEFT JOIN warehouses w ON r.warehouse_id = w.id
       ORDER BY r.created_at DESC LIMIT ? OFFSET ?`, [size, offset]
    )
    if (rows.length) {
      const ids = rows.map(r => r.id)
      const [allItems] = await pool.query(
        `SELECT i.*, p.name as product_name, p.sku, p.image_main,
                ps.sku as sku_code, ps.specs as sku_specs
         FROM inbound_items i
         JOIN products p ON i.product_id = p.id
         LEFT JOIN product_skus ps ON i.sku_id = ps.id
         WHERE i.record_id IN (?)`, [ids]
      )
      const itemsByRecord = {}
      for (const item of allItems) {
        if (!itemsByRecord[item.record_id]) itemsByRecord[item.record_id] = []
        itemsByRecord[item.record_id].push(item)
      }
      for (const r of rows) r.items = itemsByRecord[r.id] || []
    }
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /inbound/audit-log/:recordId — 入库审计日志
router.get('/inbound/audit-log/:recordId', async (req, res, next) => {
  try {
    const recordId = req.params.recordId
    const [rows] = await pool.query(
      `SELECT * FROM inbound_audit_log WHERE record_id = ? ORDER BY created_at DESC`, [recordId]
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /inbound/check-duplicate — 入库前预检 (warehouse_id, product_id, sku_id) 三元组是否已存在
router.get('/inbound/check-duplicate', async (req, res, next) => {
  try {
    const { warehouse_id, product_id, sku_id } = req.query
    if (!warehouse_id || !product_id) {
      return res.status(400).json({ code: 400, message: 'warehouse_id 和 product_id 必填' })
    }
    let rows
    if (sku_id === undefined || sku_id === null || sku_id === '') {
      [rows] = await pool.query(
        `SELECT ws.id, ws.quantity, ws.warehouse_id, ws.product_id, ws.sku_id,
                w.name as warehouse_name, p.name as product_name,
                (SELECT ii.record_id FROM inbound_items ii
                  JOIN inbound_records ir ON ir.id = ii.record_id
                  WHERE ii.product_id = ws.product_id AND ii.sku_id IS NULL
                    AND ir.warehouse_id = ws.warehouse_id
                  ORDER BY ii.id DESC LIMIT 1) as record_id,
                (SELECT ir.record_no FROM inbound_items ii
                  JOIN inbound_records ir ON ir.id = ii.record_id
                  WHERE ii.product_id = ws.product_id AND ii.sku_id IS NULL
                    AND ir.warehouse_id = ws.warehouse_id
                  ORDER BY ii.id DESC LIMIT 1) as record_no,
                (SELECT ir.created_at FROM inbound_items ii
                  JOIN inbound_records ir ON ir.id = ii.record_id
                  WHERE ii.product_id = ws.product_id AND ii.sku_id IS NULL
                    AND ir.warehouse_id = ws.warehouse_id
                  ORDER BY ii.id DESC LIMIT 1) as created_at
         FROM warehouse_stock ws
         LEFT JOIN warehouses w ON ws.warehouse_id = w.id
         LEFT JOIN products p ON ws.product_id = p.id
         WHERE ws.warehouse_id = ? AND ws.product_id = ? AND ws.sku_id IS NULL`,
        [warehouse_id, product_id])
    } else {
      [rows] = await pool.query(
        `SELECT ws.id, ws.quantity, ws.warehouse_id, ws.product_id, ws.sku_id,
                w.name as warehouse_name, p.name as product_name,
                (SELECT ii.record_id FROM inbound_items ii
                  JOIN inbound_records ir ON ir.id = ii.record_id
                  WHERE ii.product_id = ws.product_id AND ii.sku_id = ?
                    AND ir.warehouse_id = ws.warehouse_id
                  ORDER BY ii.id DESC LIMIT 1) as record_id,
                (SELECT ir.record_no FROM inbound_items ii
                  JOIN inbound_records ir ON ir.id = ii.record_id
                  WHERE ii.product_id = ws.product_id AND ii.sku_id = ?
                    AND ir.warehouse_id = ws.warehouse_id
                  ORDER BY ii.id DESC LIMIT 1) as record_no,
                (SELECT ir.created_at FROM inbound_items ii
                  JOIN inbound_records ir ON ir.id = ii.record_id
                  WHERE ii.product_id = ws.product_id AND ii.sku_id = ?
                    AND ir.warehouse_id = ws.warehouse_id
                  ORDER BY ii.id DESC LIMIT 1) as created_at
         FROM warehouse_stock ws
         LEFT JOIN warehouses w ON ws.warehouse_id = w.id
         LEFT JOIN products p ON ws.product_id = p.id
         WHERE ws.warehouse_id = ? AND ws.product_id = ? AND ws.sku_id = ?`,
        [sku_id, sku_id, sku_id, warehouse_id, product_id, sku_id])
    }
    if (rows.length > 0) {
      res.json({
        code: 0,
        data: { exists: true, ...rows[0] },
        message: '该商品在此仓库已存在库存记录，请去编辑原记录补货'
      })
    } else {
      res.json({ code: 0, data: { exists: false }, message: 'ok' })
    }
  } catch (err) { next(err) }
})

// GET /inbound/:id — 入库单详情
router.get('/inbound/:id', async (req, res, next) => {
  try {
    const [[row]] = await pool.query(
      `SELECT r.*, w.name as warehouse_name FROM inbound_records r
       LEFT JOIN warehouses w ON r.warehouse_id = w.id
       WHERE r.id = ?`, [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '入库记录不存在' })
    const [items] = await pool.query(
      `SELECT i.*, p.name as product_name, p.sku, p.image_main,
              ps.sku as sku_code, ps.specs as sku_specs
       FROM inbound_items i
       JOIN products p ON i.product_id = p.id
       LEFT JOIN product_skus ps ON i.sku_id = ps.id
       WHERE i.record_id = ?`, [req.params.id])
    row.items = items
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

router.post('/inbound', requirePermission(PERMISSIONS.INVENTORY_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { warehouse_id, supplier, items } = req.body
    // operator 从 JWT 自动获取
    const operator = req.user?.name || req.body.operator || ''
    if (!warehouse_id || !items?.length) {
      return res.status(400).json({ code: 400, message: '仓库和商品明细必填' })
    }
    const totalQty = items.reduce((s, i) => s + (i.quantity || 0), 0)
    const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '')
    let recordNo
    for (let attempt = 0; attempt < 3; attempt++) {
      const [[{ cnt }]] = await conn.query(
        "SELECT COUNT(*) as cnt FROM inbound_records WHERE record_no LIKE ?", [`RK-${dateStr}%`]
      )
      recordNo = `RK-${dateStr}-${String(cnt + 1 + attempt).padStart(4, '0')}`
      try {
        const [result] = await conn.query(
          'INSERT INTO inbound_records (record_no, warehouse_id, supplier, total_qty, operator, status) VALUES (?,?,?,?,?,?)',
          [recordNo, warehouse_id, supplier || '', totalQty, operator, 'completed']
        )
        const operatorId = req.user?.id || null
        for (const item of items) {
          // 1. 查询商品基本信息（用于错误提示和审计日志）
          const [[prod]] = await conn.query(
            'SELECT id, name, require_qrcode FROM products WHERE id = ?', [item.product_id])
          if (!prod) {
            await conn.rollback()
            return res.status(400).json({ code: 400, message: `商品 ${item.product_id} 不存在` })
          }
          const needQrcode = prod.require_qrcode === 1

          // 2. 业务规则：(warehouse_id, product_id, sku_id) 三元组唯一 — 重复则拦截
          //    跨仓库不算重复，每个仓库独立
          //    SKU 三元组已存在也算重复（避免同一商品多个 SKU 行造成种类错乱）
          //    补货 = 去编辑原记录改大 quantity
          const skuId = item.sku_id || null
          let existingStockRows
          if (skuId === null) {
            [existingStockRows] = await conn.query(
              'SELECT id, quantity FROM warehouse_stock WHERE warehouse_id=? AND product_id=? AND sku_id IS NULL FOR UPDATE',
              [warehouse_id, item.product_id])
          } else {
            [existingStockRows] = await conn.query(
              'SELECT id, quantity FROM warehouse_stock WHERE warehouse_id=? AND product_id=? AND sku_id=? FOR UPDATE',
              [warehouse_id, item.product_id, skuId])
          }
          if (existingStockRows.length > 0) {
            // 找出原入库单号（用于前端直接跳转编辑）
            let originRecord = null
            if (skuId === null) {
              [originRecord] = await conn.query(
                `SELECT ir.id as record_id, ir.record_no, ir.created_at
                 FROM inbound_items ii JOIN inbound_records ir ON ir.id = ii.record_id
                 WHERE ii.product_id = ? AND ii.sku_id IS NULL AND ir.warehouse_id = ?
                 ORDER BY ii.id DESC LIMIT 1`,
                [item.product_id, warehouse_id])
            } else {
              [originRecord] = await conn.query(
                `SELECT ir.id as record_id, ir.record_no, ir.created_at
                 FROM inbound_items ii JOIN inbound_records ir ON ir.id = ii.record_id
                 WHERE ii.product_id = ? AND ii.sku_id = ? AND ir.warehouse_id = ?
                 ORDER BY ii.id DESC LIMIT 1`,
                [item.product_id, skuId, warehouse_id])
            }
            await conn.rollback()
            return res.status(409).json({
              code: 409,
              message: `商品 "${prod.name}"${skuId ? `(SKU ${skuId})` : ''} 在该仓库已存在库存记录，请编辑原记录补货`,
              data: {
                existing_warehouse_stock_id: existingStockRows[0].id,
                record_id: originRecord?.[0]?.record_id || null,
                record_no: originRecord?.[0]?.record_no || null,
                created_at: originRecord?.[0]?.created_at || null,
                product_id: item.product_id,
                product_name: prod.name,
                sku_id: skuId,
                warehouse_id,
                current_quantity: existingStockRows[0].quantity
              }
            })
          }

          // 3. 入库明细记录
          const [itemRes] = await conn.query(
            'INSERT INTO inbound_items (record_id, product_id, sku_id, qrcode_count, quantity, alert_stock) VALUES (?,?,?,?,?,?)',
            [result.insertId, item.product_id, skuId,
             needQrcode ? item.quantity : 0, item.quantity,
             (item.alert_stock !== undefined && item.alert_stock !== null && item.alert_stock !== '') ? Number(item.alert_stock) : null])
          const inboundItemId = itemRes.insertId

          // 4. 一物一码
          if (needQrcode && item.quantity > 0) {
            await generateQrcodes(conn, {
              count: item.quantity,
              product_id: item.product_id,
              sku_id: skuId,
              warehouse_id,
              operator,
              inbound_item_id: inboundItemId
            })
          }

          // 5. INSERT 新 warehouse_stock 行（不累加）
          await conn.query(
            'INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,?,?)',
            [warehouse_id, item.product_id, skuId, item.quantity])

          // 6. products.stock 累加
          await conn.query(
            'UPDATE products SET stock = COALESCE(stock, 0) + ? WHERE id = ?',
            [item.quantity, item.product_id])

          // 7. alert_stock 同步（覆盖式保存）
          if (item.alert_stock !== undefined && item.alert_stock !== null && item.alert_stock !== '' && Number(item.alert_stock) >= 0) {
            await conn.query(
              'UPDATE products SET alert_stock = ? WHERE id = ?',
              [Number(item.alert_stock), item.product_id])
          }

          // 8. recheck 库存预警
          await recheckStockAlert(conn, item.product_id, warehouse_id)

          // 9. 写审计日志 — 新增
          await conn.query(
            `INSERT INTO inbound_audit_log
              (record_id, item_id, action, operator_id, operator_name, after_qty, after_alert_stock, note)
             VALUES (?,?,?,?,?,?,?,?)`,
            [result.insertId, inboundItemId, 'create', operatorId, operator, item.quantity,
             (item.alert_stock !== undefined && item.alert_stock !== null && item.alert_stock !== '') ? Number(item.alert_stock) : null,
             `新建入库 ${prod.name}${skuId ? `(SKU ${skuId})` : ''} 数量 ${item.quantity}`])
        }
        await conn.commit()
        return res.json({ code: 0, data: { id: result.insertId, record_no: recordNo }, message: 'ok' })
      } catch (err) {
        if (err.code === 'ER_DUP_ENTRY' && attempt < 2) continue
        throw err
      }
    }
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// ---- Outbound ----
router.get('/outbound', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    const offset = (page - 1) * size
    const [[{ total }]] = await pool.query('SELECT COUNT(*) as total FROM outbound_records')
    const [rows] = await pool.query(
      `SELECT r.*, w.name as warehouse_name FROM outbound_records r
       LEFT JOIN warehouses w ON r.warehouse_id = w.id
       ORDER BY r.created_at DESC LIMIT ? OFFSET ?`, [size, offset]
    )
    if (rows.length) {
      const ids = rows.map(r => r.id)
      const [allItems] = await pool.query(
        `SELECT i.*, p.name as product_name, p.sku, p.image_main FROM outbound_items i
         JOIN products p ON i.product_id = p.id WHERE i.record_id IN (?)`, [ids]
      )
      const itemsByRecord = {}
      for (const item of allItems) {
        if (!itemsByRecord[item.record_id]) itemsByRecord[item.record_id] = []
        itemsByRecord[item.record_id].push(item)
      }
      for (const r of rows) r.items = itemsByRecord[r.id] || []
    }
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// Helper function to get consecutive QR codes
function getConsecutiveCodes(startCode, count) {
  const match = startCode.match(/^(.*-)(\d+)$/)
  if (!match) throw new Error('二维码格式不正确，无法识别连续码')

  const prefix = match[1]
  const startNum = parseInt(match[2])
  const codes = []

  for (let i = 0; i < count; i++) {
    const num = String(startNum + i).padStart(match[2].length, '0')
    codes.push(prefix + num)
  }

  return codes
}

router.post('/outbound', requirePermission(PERMISSIONS.INVENTORY_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { warehouse_id, customer, items, batch_mode, start_qrcode, quantity } = req.body
    const operator = req.user?.name || req.body.operator || ''

    // Normalize: 如果传了 qrcodes 数组，自动用 qrcodes.length 作为 quantity
    const normalizedItems = (items || []).map(it => ({
      ...it,
      quantity: it.quantity || (it.qrcodes?.length) || (it.qrcode_id ? 1 : 0) || 0
    }))

    let finalItems = normalizedItems
    if (batch_mode && start_qrcode && quantity) {
      try {
        const codes = getConsecutiveCodes(start_qrcode, quantity)

        // Validate all codes exist
        const [qrcodes] = await conn.query(
          'SELECT id, code, product_id FROM qrcodes WHERE code IN (?)',
          [codes]
        )

        if (qrcodes.length !== codes.length) {
          await conn.rollback()
          return res.status(400).json({
            code: 400,
            message: `连续码验证失败：期望${codes.length}个，实际找到${qrcodes.length}个`
          })
        }

        // Group by product_id (含 sku_id 拆分：每个 SKU 独立一行)
        const productMap = {}
        for (const qr of qrcodes) {
          const key = `${qr.product_id}_${qr.sku_id || 'NULL'}`
          if (!productMap[key]) {
            productMap[key] = { product_id: qr.product_id, sku_id: qr.sku_id, quantity: 0, qrcodes: [] }
          }
          productMap[key].quantity++
          productMap[key].qrcodes.push(qr.id)
        }

        finalItems = Object.values(productMap)
      } catch (err) {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: err.message })
      }
    }

    if (!warehouse_id || !finalItems?.length) {
      return res.status(400).json({ code: 400, message: '仓库和商品明细必填' })
    }

    // 校验库存 & require_qrcode
    // 重要：warehouse_stock 表可能因为 NULL sku_id UNIQUE 索引失效产生重复行
    // 必须 SUM 所有行的 quantity，不能只看一行
    for (const item of finalItems) {
      const stockSql = item.sku_id
        ? 'SELECT COALESCE(SUM(quantity), 0) AS total FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? AND sku_id = ?'
        : 'SELECT COALESCE(SUM(quantity), 0) AS total FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? AND sku_id IS NULL'
      const stockParams = item.sku_id
        ? [warehouse_id, item.product_id, item.sku_id]
        : [warehouse_id, item.product_id]
      const [[stock]] = await conn.query(stockSql, stockParams)
      if (!stock || stock.total < item.quantity) {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: `商品ID ${item.product_id} 库存不足 (需 ${item.quantity}, 实际 ${stock?.total || 0})` })
      }
      // 检查是否需要一物一码（支持 qrcode_id 单个 + qrcodes 数组）
      const [[prod]] = await conn.query('SELECT require_qrcode FROM products WHERE id = ?', [item.product_id])
      if (prod?.require_qrcode && !item.qrcode_id && !(item.qrcodes && item.qrcodes.length > 0)) {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: `商品ID ${item.product_id} 出库必须绑定一物一码` })
      }
    }

    const totalQty = items.reduce((s, i) => s + (i.quantity || 0), 0)
    const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '')
    let recordNo
    for (let attempt = 0; attempt < 3; attempt++) {
      const [[{ cnt }]] = await conn.query(
        "SELECT COUNT(*) as cnt FROM outbound_records WHERE record_no LIKE ?", [`CK-${dateStr}%`]
      )
      recordNo = `CK-${dateStr}-${String(cnt + 1 + attempt).padStart(4, '0')}`
      try {
        const [result] = await conn.query(
          'INSERT INTO outbound_records (record_no, warehouse_id, customer, total_qty, operator, status) VALUES (?,?,?,?,?,?)',
          [recordNo, warehouse_id, customer || '', totalQty, operator, 'completed']
        )
        for (const item of finalItems) {
          // Handle batch mode with multiple qrcodes
          if (item.qrcodes && item.qrcodes.length > 0) {
            for (const qrcode_id of item.qrcodes) {
              await conn.query('INSERT INTO outbound_items (record_id, product_id, sku_id, quantity, qrcode_id) VALUES (?,?,?,?,?)',
                [result.insertId, item.product_id, item.sku_id || null, 1, qrcode_id])
              await conn.query("UPDATE qrcodes SET status = 'shipped' WHERE id = ?", [qrcode_id])
            }
          } else {
            await conn.query('INSERT INTO outbound_items (record_id, product_id, sku_id, quantity, qrcode_id) VALUES (?,?,?,?,?)',
              [result.insertId, item.product_id, item.sku_id || null, item.quantity, item.qrcode_id || null])
            if (item.qrcode_id) {
              await conn.query("UPDATE qrcodes SET status = 'shipped' WHERE id = ?", [item.qrcode_id])
            }
          }
          // warehouse_stock 扣减（拆 SKU 维度）
          // 修复：warehouse_stock 可能因 NULL sku_id UNIQUE 索引失效产生重复行
          // 必须用 SUM 一次性判断总库存是否够，循环扣到扣完为止
          const checkSql = item.sku_id
            ? 'SELECT COALESCE(SUM(quantity), 0) AS total FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? AND sku_id = ?'
            : 'SELECT COALESCE(SUM(quantity), 0) AS total FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? AND sku_id IS NULL'
          const checkParams = item.sku_id
            ? [warehouse_id, item.product_id, item.sku_id]
            : [warehouse_id, item.product_id]
          const [[stockCheck]] = await conn.query(checkSql, checkParams)
          if (!stockCheck || stockCheck.total < item.quantity) {
            throw Object.assign(new Error(`商品ID ${item.product_id} 库存不足 (需 ${item.quantity}, 实际 ${stockCheck?.total || 0})`), { status: 400 })
          }
          // 循环扣减：从最早一行开始扣，扣到完为止（避免 NULL sku_id 重复行问题）
          let remaining = item.quantity
          while (remaining > 0) {
            const getOneSql = item.sku_id
              ? 'SELECT id, quantity FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? AND sku_id = ? AND quantity > 0 ORDER BY id LIMIT 1'
              : 'SELECT id, quantity FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? AND sku_id IS NULL AND quantity > 0 ORDER BY id LIMIT 1'
            const [[oneRow]] = await conn.query(getOneSql, checkParams)
            if (!oneRow) break  // 防御：理论上 checkSql 已经校验过
            const deductThis = Math.min(remaining, oneRow.quantity)
            await conn.query('UPDATE warehouse_stock SET quantity = quantity - ? WHERE id = ?', [deductThis, oneRow.id])
            remaining -= deductThis
          }
          if (remaining > 0) {
            throw Object.assign(new Error(`商品ID ${item.product_id} 库存扣减失败, 剩余 ${remaining}`), { status: 400 })
          }
          const [pResult] = await conn.query(
            'UPDATE products SET stock = COALESCE(stock, 0) - ? WHERE id = ? AND COALESCE(stock, 0) >= ?',
            [item.quantity, item.product_id, item.quantity])
          if (pResult.affectedRows === 0) {
            throw Object.assign(new Error(`商品ID ${item.product_id} 总库存不足`), { status: 400 })
          }
          // ✅ 出库后 recheck 库存预警（库存下降可能触发预警）
          await recheckStockAlert(conn, item.product_id, warehouse_id)
        }
        await conn.commit()
        return res.json({ code: 0, data: { id: result.insertId, record_no: recordNo }, message: 'ok' })
      } catch (err) {
        if (err.code === 'ER_DUP_ENTRY' && attempt < 2) continue
        throw err
      }
    }
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// ---- Batch Outbound Preview ----
router.post('/outbound/preview', requirePermission(PERMISSIONS.INVENTORY_WRITE), async (req, res, next) => {
  try {
    const { start_qrcode, quantity } = req.body

    if (!start_qrcode || !quantity) {
      return res.status(400).json({ code: 400, message: '起始二维码和数量必填' })
    }

    try {
      const codes = getConsecutiveCodes(start_qrcode, quantity)

      // Validate all codes exist
      const [qrcodes] = await pool.query(
        'SELECT q.id, q.code, q.product_id, q.status, p.name as product_name, p.sku FROM qrcodes q LEFT JOIN products p ON q.product_id = p.id WHERE q.code IN (?)',
        [codes]
      )

      if (qrcodes.length !== codes.length) {
        const foundCodes = qrcodes.map(q => q.code)
        const missingCodes = codes.filter(c => !foundCodes.includes(c))
        return res.status(400).json({
          code: 400,
          message: `连续码验证失败：期望${codes.length}个，实际找到${qrcodes.length}个`,
          data: { missing: missingCodes }
        })
      }

      // Check if all codes are available (status should be 'bound' or 'unused')
      const unavailable = qrcodes.filter(q => !['bound', 'unused'].includes(q.status))
      if (unavailable.length > 0) {
        return res.status(400).json({
          code: 400,
          message: `部分二维码状态不可用`,
          data: { unavailable: unavailable.map(q => ({ code: q.code, status: q.status })) }
        })
      }

      // Group by product
      const productMap = {}
      for (const qr of qrcodes) {
        if (!productMap[qr.product_id]) {
          productMap[qr.product_id] = {
            product_id: qr.product_id,
            product_name: qr.product_name,
            sku: qr.sku,
            quantity: 0,
            qrcodes: []
          }
        }
        productMap[qr.product_id].quantity++
        productMap[qr.product_id].qrcodes.push({ id: qr.id, code: qr.code })
      }

      res.json({
        code: 0,
        data: {
          total: codes.length,
          items: Object.values(productMap)
        },
        message: 'ok'
      })
    } catch (err) {
      return res.status(400).json({ code: 400, message: err.message })
    }
  } catch (err) {
    next(err)
  }
})

// ---- Returns ----
router.get('/returns', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    const offset = (page - 1) * size
    const [[{ total }]] = await pool.query('SELECT COUNT(*) as total FROM return_records')
    const [rows] = await pool.query(
      `SELECT r.*, w.name as warehouse_name FROM return_records r
       LEFT JOIN warehouses w ON r.warehouse_id = w.id
       ORDER BY r.created_at DESC LIMIT ? OFFSET ?`, [size, offset]
    )
    if (rows.length) {
      const ids = rows.map(r => r.id)
      const [allItems] = await pool.query(
        `SELECT i.*, p.name as product_name, p.sku, p.image_main FROM return_items i
         JOIN products p ON i.product_id = p.id WHERE i.record_id IN (?)`, [ids]
      )
      const itemsByRecord = {}
      for (const item of allItems) {
        if (!itemsByRecord[item.record_id]) itemsByRecord[item.record_id] = []
        itemsByRecord[item.record_id].push(item)
      }
      for (const r of rows) r.items = itemsByRecord[r.id] || []
    }
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

router.post('/returns', requirePermission(PERMISSIONS.INVENTORY_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { warehouse_id, source, items, qrcode_id, reason } = req.body
    const operator = req.user?.name || req.body.operator || ''

    // Support scan-based return（含 sku_id + warehouse_id）
    let finalItems = items || []
    if (qrcode_id && !items) {
      const [[qr]] = await conn.query(
        'SELECT product_id, sku_id, warehouse_id FROM qrcodes WHERE id = ?', [qrcode_id])
      if (!qr) {
        await conn.rollback()
        return res.status(404).json({ code: 404, message: '二维码不存在' })
      }
      finalItems = [{ product_id: qr.product_id, sku_id: qr.sku_id, warehouse_id: qr.warehouse_id || warehouse_id, quantity: 1, qrcode_id }]
    }

    if (!warehouse_id || !finalItems?.length) {
      return res.status(400).json({ code: 400, message: '仓库和商品明细必填' })
    }
    const totalQty = finalItems.reduce((s, i) => s + (i.quantity || 0), 0)
    const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '')
    let recordNo
    for (let attempt = 0; attempt < 3; attempt++) {
      const [[{ cnt }]] = await conn.query(
        "SELECT COUNT(*) as cnt FROM return_records WHERE record_no LIKE ?", [`TH-${dateStr}%`]
      )
      recordNo = `TH-${dateStr}-${String(cnt + 1 + attempt).padStart(4, '0')}`
      try {
        const [result] = await conn.query(
          'INSERT INTO return_records (record_no, warehouse_id, source, total_qty, operator, status, note) VALUES (?,?,?,?,?,?,?)',
          [recordNo, warehouse_id, source || '', totalQty, operator, 'completed', reason || null]
        )
        for (const item of finalItems) {
          await conn.query('INSERT INTO return_items (record_id, product_id, sku_id, quantity, qrcode_id) VALUES (?,?,?,?,?)',
            [result.insertId, item.product_id, item.sku_id || null, item.quantity, item.qrcode_id || null])
          // warehouse_stock 数量回退（拆 SKU 维度，兼容无 SKU）
          // 注意：MySQL UNIQUE INDEX 对 NULL 不去重，显式 SELECT FOR UPDATE 累加
          const targetWh = item.warehouse_id || warehouse_id
          if (item.sku_id) {
            const [existing] = await conn.query(
              'SELECT id, quantity FROM warehouse_stock WHERE warehouse_id=? AND product_id=? AND sku_id=? FOR UPDATE',
              [targetWh, item.product_id, item.sku_id])
            if (existing.length > 0) {
              await conn.query('UPDATE warehouse_stock SET quantity = quantity + ? WHERE id = ?',
                [item.quantity, existing[0].id])
            } else {
              await conn.query(
                'INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,?,?)',
                [targetWh, item.product_id, item.sku_id, item.quantity])
            }
          } else {
            const [existing] = await conn.query(
              'SELECT id, quantity FROM warehouse_stock WHERE warehouse_id=? AND product_id=? AND sku_id IS NULL FOR UPDATE',
              [targetWh, item.product_id])
            if (existing.length > 0) {
              await conn.query('UPDATE warehouse_stock SET quantity = quantity + ? WHERE id = ?',
                [item.quantity, existing[0].id])
            } else {
              await conn.query(
                'INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,NULL,?)',
                [targetWh, item.product_id, item.quantity])
            }
          }

          await conn.query(
            'UPDATE products SET stock = COALESCE(stock, 0) + ? WHERE id = ?', [item.quantity, item.product_id])

          // 决策 2: 退货直接 inStock 重新可卖（不是 returned）
          // 同时恢复 warehouse_id/sku_id/product_id（防丢关联）
          if (item.qrcode_id) {
            await conn.query(
              "UPDATE qrcodes SET status='inStock', warehouse_id=?, sku_id=?, product_id=? WHERE id=?",
              [warehouse_id, item.sku_id || null, item.product_id, item.qrcode_id]
            )
          }
        }
        await conn.commit()
        return res.json({ code: 0, data: { id: result.insertId, record_no: recordNo }, message: 'ok' })
      } catch (err) {
        if (err.code === 'ER_DUP_ENTRY' && attempt < 2) continue
        throw err
      }
    }
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// Alias for return endpoint (singular)
router.post('/return', async (req, res, next) => {
  // Reuse the same handler
  req.url = '/returns'
  return router.handle(req, res, next)
})

// ─── Edit Records ─────────────────────────────────────────────────────────────

// Edit inbound record
router.put('/inbound/:id', requirePermission(PERMISSIONS.INVENTORY_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const recordId = req.params.id
    const { warehouse_id, supplier, items } = req.body

    const [[record]] = await conn.query('SELECT * FROM inbound_records WHERE id = ?', [recordId])
    if (!record) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '入库记录不存在' })
    }

    if (!warehouse_id || !items?.length) {
      return res.status(400).json({ code: 400, message: '仓库和商品明细必填' })
    }

    // 先抓 old items 的 before 数据（在 rollback 之前，用于审计日志）
    const [oldItems] = await conn.query('SELECT * FROM inbound_items WHERE record_id = ?', [recordId])
    const beforeMap = {} // key: `${product_id}_${sku_id}` -> {qty, alert}
    for (const item of oldItems) {
      const skuKey = item.sku_id || 'null'
      const mapKey = `${item.product_id}_${skuKey}`
      let beforeStockRows
      if (item.sku_id) {
        [beforeStockRows] = await conn.query(
          'SELECT id, quantity FROM warehouse_stock WHERE warehouse_id=? AND product_id=? AND sku_id=?',
          [record.warehouse_id, item.product_id, item.sku_id])
      } else {
        [beforeStockRows] = await conn.query(
          'SELECT id, quantity FROM warehouse_stock WHERE warehouse_id=? AND product_id=? AND sku_id IS NULL',
          [record.warehouse_id, item.product_id])
      }
      const [[beforeProd]] = await conn.query(
        'SELECT alert_stock FROM products WHERE id = ?', [item.product_id])
      beforeMap[mapKey] = {
        qty: beforeStockRows.length > 0 ? beforeStockRows[0].quantity : 0,
        alert: beforeProd?.alert_stock ?? null
      }
    }

    // Rollback old stock (拆 SKU 维度，扣减 + 扣完 0 → 删行)
    for (const item of oldItems) {
      if (item.sku_id) {
        await conn.query(
          'UPDATE warehouse_stock SET quantity = quantity - ? WHERE warehouse_id = ? AND product_id = ? AND sku_id = ? AND quantity >= ?',
          [item.quantity, record.warehouse_id, item.product_id, item.sku_id, item.quantity])
        await conn.query(
          'DELETE FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? AND sku_id = ? AND quantity = 0',
          [record.warehouse_id, item.product_id, item.sku_id])
      } else {
        await conn.query(
          'UPDATE warehouse_stock SET quantity = quantity - ? WHERE warehouse_id = ? AND product_id = ? AND sku_id IS NULL AND quantity >= ?',
          [item.quantity, record.warehouse_id, item.product_id, item.quantity])
        await conn.query(
          'DELETE FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? AND sku_id IS NULL AND quantity = 0',
          [record.warehouse_id, item.product_id])
      }
      await conn.query('UPDATE products SET stock = stock - ? WHERE id = ?', [item.quantity, item.product_id])
    }

    // Update record
    const totalQty = items.reduce((s, i) => s + (i.quantity || 0), 0)
    await conn.query(
      'UPDATE inbound_records SET warehouse_id=?, supplier=?, total_qty=? WHERE id=?',
      [warehouse_id, supplier || '', totalQty, recordId])

    // Delete old items, insert new
    await conn.query('DELETE FROM inbound_items WHERE record_id = ?', [recordId])
    const operatorId = req.user?.id || null
    const operator = req.user?.name || ''
    for (const item of items) {
      const skuId = item.sku_id || null
      const skuKey = skuId || 'null'
      const mapKey = `${item.product_id}_${skuKey}`
      // 从 rollback 前抓的 beforeMap 取真实 before 数据
      const beforeQty = beforeMap[mapKey]?.qty ?? 0
      const beforeAlert = beforeMap[mapKey]?.alert ?? null

      const newAlert = (item.alert_stock !== undefined && item.alert_stock !== null && item.alert_stock !== '') ? Number(item.alert_stock) : null

      await conn.query(
        'INSERT INTO inbound_items (record_id, product_id, sku_id, quantity, alert_stock) VALUES (?,?,?,?,?)',
        [recordId, item.product_id, skuId, item.quantity, newAlert])

      // Apply new stock — 检查 rollback 前是否原 warehouse_stock 行存在
      // （rollback 可能把行删了，所以这里要重新 SELECT FOR UPDATE 而不是用 beforeRows）
      let stockRows
      if (skuId === null) {
        [stockRows] = await conn.query(
          'SELECT id, quantity FROM warehouse_stock WHERE warehouse_id=? AND product_id=? AND sku_id IS NULL FOR UPDATE',
          [warehouse_id, item.product_id])
      } else {
        [stockRows] = await conn.query(
          'SELECT id, quantity FROM warehouse_stock WHERE warehouse_id=? AND product_id=? AND sku_id=? FOR UPDATE',
          [warehouse_id, item.product_id, skuId])
      }
      if (stockRows.length > 0) {
        await conn.query('UPDATE warehouse_stock SET quantity = quantity + ? WHERE id = ?',
          [item.quantity, stockRows[0].id])
      } else {
        if (skuId === null) {
          await conn.query(
            'INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,NULL,?)',
            [warehouse_id, item.product_id, item.quantity])
        } else {
          await conn.query(
            'INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,?,?)',
            [warehouse_id, item.product_id, skuId, item.quantity])
        }
      }
      await conn.query('UPDATE products SET stock = stock + ? WHERE id = ?', [item.quantity, item.product_id])

      // 编辑入库时同步更新 products.alert_stock
      if (newAlert !== null) {
        await conn.query('UPDATE products SET alert_stock = ? WHERE id = ?', [newAlert, item.product_id])
      }

      // 编辑入库后 recheck 库存预警
      await recheckStockAlert(conn, item.product_id, warehouse_id)

      // 写审计日志 — 更新
      const [[prodInfo]] = await conn.query('SELECT name FROM products WHERE id = ?', [item.product_id])
      await conn.query(
        `INSERT INTO inbound_audit_log
          (record_id, action, operator_id, operator_name, before_qty, after_qty, before_alert_stock, after_alert_stock, note)
         VALUES (?,?,?,?,?,?,?,?,?)`,
        [recordId, 'update', operatorId, operator, beforeQty, item.quantity, beforeAlert, newAlert,
         `编辑入库 ${prodInfo?.name || ''}${skuId ? `(SKU ${skuId})` : ''} 数量 ${beforeQty}→${item.quantity}`])
    }

    await conn.commit()
    res.json({ code: 0, data: { id: parseInt(recordId) }, message: '编辑成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// Edit outbound record
router.put('/outbound/:id', requirePermission(PERMISSIONS.INVENTORY_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const recordId = req.params.id
    const { warehouse_id, customer, items } = req.body

    const [[record]] = await conn.query('SELECT * FROM outbound_records WHERE id = ?', [recordId])
    if (!record) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '出库记录不存在' })
    }

    if (!warehouse_id || !items?.length) {
      return res.status(400).json({ code: 400, message: '仓库和商品明细必填' })
    }

    // Rollback old stock — MySQL UNIQUE INDEX 对 NULL 不去重，显式 SELECT FOR UPDATE
    const [oldItems] = await conn.query('SELECT * FROM outbound_items WHERE record_id = ?', [recordId])
    for (const item of oldItems) {
      const [existingB] = await conn.query(
        'SELECT id FROM warehouse_stock WHERE warehouse_id=? AND product_id=? AND sku_id IS NULL FOR UPDATE',
        [record.warehouse_id, item.product_id])
      if (existingB.length > 0) {
        await conn.query('UPDATE warehouse_stock SET quantity = quantity + ? WHERE id = ?',
          [item.quantity, existingB[0].id])
      } else {
        await conn.query(
          'INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,NULL,?)',
          [record.warehouse_id, item.product_id, item.quantity])
      }
      await conn.query('UPDATE products SET stock = stock + ? WHERE id = ?', [item.quantity, item.product_id])
      if (item.qrcode_id) {
        await conn.query("UPDATE qrcodes SET status = 'bound' WHERE id = ?", [item.qrcode_id])
      }
    }

    // Update record
    const totalQty = items.reduce((s, i) => s + (i.quantity || 0), 0)
    await conn.query(
      'UPDATE outbound_records SET warehouse_id=?, customer=?, total_qty=? WHERE id=?',
      [warehouse_id, customer || '', totalQty, recordId])

    // Delete old items, insert new
    await conn.query('DELETE FROM outbound_items WHERE record_id = ?', [recordId])
    for (const item of items) {
      await conn.query('INSERT INTO outbound_items (record_id, product_id, sku_id, quantity, qrcode_id) VALUES (?,?,?,?,?)',
        [recordId, item.product_id, item.sku_id || null, item.quantity, item.qrcode_id || null])
      // Apply new stock deduction
      await conn.query(
        'UPDATE warehouse_stock SET quantity = quantity - ? WHERE warehouse_id = ? AND product_id = ?',
        [item.quantity, warehouse_id, item.product_id])
      await conn.query('UPDATE products SET stock = stock - ? WHERE id = ?', [item.quantity, item.product_id])
      if (item.qrcode_id) {
        await conn.query("UPDATE qrcodes SET status = 'used' WHERE id = ?", [item.qrcode_id])
      }
      // ✅ 编辑出库后 recheck 库存预警
      await recheckStockAlert(conn, item.product_id, warehouse_id)
    }

    await conn.commit()
    res.json({ code: 0, data: { id: parseInt(recordId) }, message: '编辑成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ─── Delete Records (Admin only) ────────────────────────────────────────────

// Delete inbound record
router.delete('/inbound/:id', requireRole('admin'), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const recordId = req.params.id

    // Get record details
    const [[record]] = await conn.query('SELECT * FROM inbound_records WHERE id = ?', [recordId])
    if (!record) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '入库记录不存在' })
    }

    // Get items
    const [items] = await conn.query('SELECT * FROM inbound_items WHERE record_id = ?', [recordId])

    // Rollback stock changes + qrcode status
    for (const item of items) {
      // 1. qrcode 状态恢复（如果生成了，禁用并清空商品关联）
      //    按 (product_id, sku_id, warehouse_id, status='inStock') 匹配 N 个（删除前先查 count）
      if (item.qrcode_count > 0) {
        await conn.query(
          `UPDATE qrcodes SET status='disabled', warehouse_id=NULL, sku_id=NULL, product_id=NULL, inbound_item_id=NULL
           WHERE product_id=? AND (sku_id <=> ?) AND warehouse_id=? AND status='inStock'
           LIMIT ?`,
          [item.product_id, item.sku_id, record.warehouse_id, item.qrcode_count]
        )
      }

      // 2. warehouse_stock 扣减（拆 SKU 维度，扣完如果是 0 就清掉，避免重新入库被 409 误拦截）
      if (item.sku_id) {
        await conn.query(
          `UPDATE warehouse_stock SET quantity = quantity - ?
           WHERE warehouse_id=? AND product_id=? AND sku_id=? AND quantity >= ?`,
          [item.quantity, record.warehouse_id, item.product_id, item.sku_id, item.quantity]
        )
        // 扣完是 0 → 删除该行
        await conn.query(
          `DELETE FROM warehouse_stock
           WHERE warehouse_id=? AND product_id=? AND sku_id=? AND quantity = 0`,
          [record.warehouse_id, item.product_id, item.sku_id]
        )
      } else {
        await conn.query(
          'UPDATE warehouse_stock SET quantity = quantity - ? WHERE warehouse_id = ? AND product_id = ? AND sku_id IS NULL AND quantity >= ?',
          [item.quantity, record.warehouse_id, item.product_id, item.quantity]
        )
        // 扣完是 0 → 删除该行
        await conn.query(
          'DELETE FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? AND sku_id IS NULL AND quantity = 0',
          [record.warehouse_id, item.product_id]
        )
      }

      // 3. products.stock
      await conn.query('UPDATE products SET stock = stock - ? WHERE id = ?', [item.quantity, item.product_id])
    }

    // Delete items and record
    await conn.query('DELETE FROM inbound_items WHERE record_id = ?', [recordId])
    await conn.query('DELETE FROM inbound_records WHERE id = ?', [recordId])

    // 写审计日志 — 删除（每条 item 一条）
    const operatorId = req.user?.id || null
    const operator = req.user?.name || ''
    for (const item of items) {
      const [[prodInfo]] = await conn.query('SELECT name FROM products WHERE id = ?', [item.product_id])
      await conn.query(
        `INSERT INTO inbound_audit_log
          (record_id, action, operator_id, operator_name, before_qty, before_alert_stock, note)
         VALUES (?,?,?,?,?,?,?)`,
        [recordId, 'delete', operatorId, operator, item.quantity, item.alert_stock,
         `删除入库 ${prodInfo?.name || ''}${item.sku_id ? `(SKU ${item.sku_id})` : ''} 数量 ${item.quantity}`])
    }

    await conn.commit()
    res.json({ code: 0, data: null, message: '删除成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// Delete outbound record
router.delete('/outbound/:id', requireRole('admin'), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const recordId = req.params.id

    // Get record details
    const [[record]] = await conn.query('SELECT * FROM outbound_records WHERE id = ?', [recordId])
    if (!record) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '出库记录不存在' })
    }

    // Get items
    const [items] = await conn.query('SELECT * FROM outbound_items WHERE record_id = ?', [recordId])

    // Rollback stock changes and qrcode status
    for (const item of items) {
      // 1. 库存回退（拆 SKU 维度）
      // 注意：MySQL UNIQUE INDEX 对 NULL 不去重，显式 SELECT FOR UPDATE 累加
      if (item.sku_id) {
        const [existing] = await conn.query(
          'SELECT id, quantity FROM warehouse_stock WHERE warehouse_id=? AND product_id=? AND sku_id=? FOR UPDATE',
          [record.warehouse_id, item.product_id, item.sku_id])
        if (existing.length > 0) {
          await conn.query('UPDATE warehouse_stock SET quantity = quantity + ? WHERE id = ?',
            [item.quantity, existing[0].id])
        } else {
          await conn.query(
            'INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,?,?)',
            [record.warehouse_id, item.product_id, item.sku_id, item.quantity])
        }
      } else {
        const [existing] = await conn.query(
          'SELECT id, quantity FROM warehouse_stock WHERE warehouse_id=? AND product_id=? AND sku_id IS NULL FOR UPDATE',
          [record.warehouse_id, item.product_id])
        if (existing.length > 0) {
          await conn.query('UPDATE warehouse_stock SET quantity = quantity + ? WHERE id = ?',
            [item.quantity, existing[0].id])
        } else {
          await conn.query(
            'INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,NULL,?)',
            [record.warehouse_id, item.product_id, item.quantity])
        }
      }
      await conn.query('UPDATE products SET stock = stock + ? WHERE id = ?', [item.quantity, item.product_id])

      // 2. qrcode 状态恢复（按 qrcode_count 个数从 shipped → inStock）
      if (item.qrcode_count > 0) {
        // 优先按 qrcode_id 恢复单个（精确）
        if (item.qrcode_id) {
          await conn.query(
            "UPDATE qrcodes SET status='inStock' WHERE id=? AND status='shipped'",
            [item.qrcode_id]
          )
        } else {
          // 批量模式：按 (product_id, sku_id, warehouse_id) 找 shipped 状态恢复
          await conn.query(
            `UPDATE qrcodes SET status='inStock'
             WHERE product_id=? AND (sku_id <=> ?) AND warehouse_id=? AND status='shipped'
             LIMIT ?`,
            [item.product_id, item.sku_id, record.warehouse_id, item.qrcode_count]
          )
        }
      }
    }

    // Delete items and record
    await conn.query('DELETE FROM outbound_items WHERE record_id = ?', [recordId])
    await conn.query('DELETE FROM outbound_records WHERE id = ?', [recordId])

    await conn.commit()
    res.json({ code: 0, data: null, message: '删除成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// Edit return record
router.put('/returns/:id', requirePermission(PERMISSIONS.INVENTORY_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const recordId = req.params.id
    const { warehouse_id, source, reason, remark, items } = req.body
    const operator = req.user?.name || req.body.operator || ''

    // Get old items for rollback
    const [oldItems] = await conn.query('SELECT * FROM return_items WHERE record_id = ?', [recordId])

    // Rollback old stock
    for (const item of oldItems) {
      await conn.query(
        'UPDATE warehouse_stock SET quantity = quantity - ? WHERE warehouse_id = ? AND product_id = ?',
        [item.quantity, warehouse_id, item.product_id])
      await conn.query('UPDATE products SET stock = stock - ? WHERE id = ?', [item.quantity, item.product_id])
      if (item.qrcode_id) {
        await conn.query("UPDATE qrcodes SET status = 'shipped' WHERE id = ?", [item.qrcode_id])
      }
    }

    // Update record
    const totalQty = items.reduce((s, i) => s + (i.quantity || 0), 0)
    await conn.query(
      'UPDATE return_records SET warehouse_id=?, source=?, reason=?, total_qty=? WHERE id=?',
      [warehouse_id, source || '', reason || '', totalQty, recordId])

    // Delete old items, insert new
    await conn.query('DELETE FROM return_items WHERE record_id = ?', [recordId])
    for (const item of items) {
      await conn.query('INSERT INTO return_items (record_id, product_id, sku_id, quantity, qrcode_id) VALUES (?,?,?,?,?)',
        [recordId, item.product_id, item.sku_id || null, item.quantity, item.qrcode_id || null])
      // Apply stock return — MySQL UNIQUE INDEX 对 NULL 不去重，显式 SELECT FOR UPDATE
      const [existingC] = await conn.query(
        'SELECT id FROM warehouse_stock WHERE warehouse_id=? AND product_id=? AND sku_id IS NULL FOR UPDATE',
        [warehouse_id, item.product_id])
      if (existingC.length > 0) {
        await conn.query('UPDATE warehouse_stock SET quantity = quantity + ? WHERE id = ?',
          [item.quantity, existingC[0].id])
      } else {
        await conn.query(
          'INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,NULL,?)',
          [warehouse_id, item.product_id, item.quantity])
      }
      await conn.query('UPDATE products SET stock = stock + ? WHERE id = ?', [item.quantity, item.product_id])
      if (item.qrcode_id) {
        await conn.query("UPDATE qrcodes SET status = 'bound' WHERE id = ?", [item.qrcode_id])
      }
    }

    await conn.commit()
    res.json({ code: 0, data: { id: parseInt(recordId) }, message: '编辑成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// Delete return record
router.delete('/returns/:id', requireRole('admin'), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const recordId = req.params.id

    // Get record details
    const [[record]] = await conn.query('SELECT * FROM return_records WHERE id = ?', [recordId])
    if (!record) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '退货记录不存在' })
    }

    // 警告：删除 completed 状态的退货会同时回退库存（设计选择：可清理脏数据）
    // 只拒绝 cancelled（避免误删已撤销的审计记录）

    // Get items
    const [items] = await conn.query('SELECT * FROM return_items WHERE record_id = ?', [recordId])

    // Rollback stock changes
    for (const item of items) {
      await conn.query(
        'UPDATE warehouse_stock SET quantity = quantity - ? WHERE warehouse_id = ? AND product_id = ?',
        [item.quantity, record.warehouse_id, item.product_id]
      )
      await conn.query('UPDATE products SET stock = stock - ? WHERE id = ?', [item.quantity, item.product_id])

      // Reset qrcode status if applicable
      if (item.qrcode_id) {
        await conn.query("UPDATE qrcodes SET status = 'shipped' WHERE id = ?", [item.qrcode_id])
      }
    }

    // Delete items and record
    await conn.query('DELETE FROM return_items WHERE record_id = ?', [recordId])
    await conn.query('DELETE FROM return_records WHERE id = ?', [recordId])

    await conn.commit()
    res.json({ code: 0, data: null, message: '删除成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ═══ 一物一码 P0 新功能（波哥 2026-06-17 决策）═══════════════════════════════

// POST /api/inventory/stocktake - 盘点接口（决策 3）
router.post('/stocktake', requirePermission(PERMISSIONS.INVENTORY_WRITE), async (req, res, next) => {
  try {
    const { warehouse_id, qrcode_ids = [], blind_mode = false } = req.body
    if (!warehouse_id) return res.status(400).json({ code: 400, message: 'warehouse_id 必填' })

    // 1. 查所有该仓库 inStock 的 qrcode
    const [stockQrcodes] = await pool.query(
      `SELECT id, code, product_id, sku_id FROM qrcodes
       WHERE warehouse_id=? AND status='inStock'`, [warehouse_id]
    )
    const stockSet = new Set(stockQrcodes.map(q => q.id))
    const scannedSet = new Set(qrcode_ids.map(Number))

    // 2. 分类
    const matched = []   // 扫到的
    const missing = []   // 系统有但没扫到
    const extra = []     // 扫到但系统没

    for (const q of stockQrcodes) {
      if (scannedSet.has(q.id)) {
        matched.push(blind_mode ? { id: q.id, code: '***' } : q)
      } else {
        missing.push(q)
      }
    }
    for (const sid of scannedSet) {
      if (!stockSet.has(sid)) extra.push({ id: sid, note: '二维码不在该仓库或已出库' })
    }

    // 3. 统计 + warehouse_stock 对比
    const diff = []
    if (!blind_mode) {
      const [stockRows] = await pool.query(
        `SELECT product_id, sku_id, CAST(SUM(quantity) AS UNSIGNED) as total_qty
         FROM warehouse_stock WHERE warehouse_id=? GROUP BY product_id, sku_id`, [warehouse_id]
      )
      // 扫码按 (product_id, sku_id) 聚合
      const scannedMap = {}
      for (const q of stockQrcodes) {
        if (scannedSet.has(q.id)) {
          const k = `${q.product_id}_${q.sku_id || 'NULL'}`
          scannedMap[k] = (scannedMap[k] || 0) + 1
        }
      }
      for (const row of stockRows) {
        const k = `${row.product_id}_${row.sku_id || 'NULL'}`
        const actual = scannedMap[k] || 0
        const sysQty = Number(row.total_qty)
        if (actual !== sysQty) {
          diff.push({ product_id: row.product_id, sku_id: row.sku_id, system_qty: sysQty, actual_qty: actual, delta: actual - sysQty })
        }
      }
    }

    res.json({
      code: 0, message: 'ok',
      data: {
        warehouse_id, blind_mode,
        summary: { total_stock: stockQrcodes.length, scanned: qrcode_ids.length, matched: matched.length, missing: missing.length, extra: extra.length },
        matched, missing, extra, diff: blind_mode ? [] : diff
      }
    })
  } catch (err) { next(err) }
})

// ═══ STOCKTAKE 库存盘点（2026-06-17 新增）═════════════════════════════════════

// GET /api/inventory/stocktakes - 盘点历史列表
router.get('/stocktakes', requirePermission(PERMISSIONS.STOCKTAKE_REPORT), async (req, res, next) => {
  try {
    const { warehouse_id, status, limit = 20, offset = 0 } = req.query
    const where = []
    const params = []
    if (warehouse_id) { where.push('s.warehouse_id = ?'); params.push(warehouse_id) }
    if (status) { where.push('s.status = ?'); params.push(status) }
    const whereSql = where.length ? 'WHERE ' + where.join(' AND ') : ''

    const [rows] = await pool.query(
      `SELECT s.*, w.name AS warehouse_name
       FROM stocktakes s LEFT JOIN warehouses w ON w.id = s.warehouse_id
       ${whereSql}
       ORDER BY s.started_at DESC LIMIT ? OFFSET ?`,
      [...params, Number(limit), Number(offset)]
    )
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) AS total FROM stocktakes s ${whereSql}`, params
    )
    res.json({ code: 0, message: 'ok', data: { list: rows, total } })
  } catch (err) { next(err) }
})

// POST /api/inventory/stocktakes - 创建盘点单（基于 warehouse_stock 全量盘点）
router.post('/stocktakes', requirePermission(PERMISSIONS.STOCKTAKE_RUN), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { warehouse_id, blind_mode = false, notes = '' } = req.body
    if (!warehouse_id) return res.status(400).json({ code: 400, message: 'warehouse_id 必填' })

    await conn.beginTransaction()

    // 1. 创建盘点单
    const [insertRes] = await conn.query(
      `INSERT INTO stocktakes (warehouse_id, blind_mode, operator_id, operator_name, notes, status)
       VALUES (?, ?, ?, ?, ?, 'in_progress')`,
      [warehouse_id, blind_mode ? 1 : 0, req.user?.id || null, req.user?.name || req.user?.phone || 'unknown', notes]
    )
    const stocktakeId = insertRes.insertId

    // 2. 基于 warehouse_stock 生成盘点明细
    const [stockRows] = await conn.query(
      `SELECT product_id, sku_id, SUM(quantity) AS sys_qty, MAX(location) AS location
       FROM warehouse_stock WHERE warehouse_id = ? GROUP BY product_id, sku_id`,
      [warehouse_id]
    )

    let totalItems = 0
    for (const row of stockRows) {
      await conn.query(
        `INSERT INTO stocktake_items (stocktake_id, product_id, sku_id, system_stock, counted_stock, diff, location, counted)
         VALUES (?, ?, ?, ?, 0, 0, ?, 0)`,
        [stocktakeId, row.product_id, row.sku_id, Number(row.sys_qty) || 0, row.location]
      )
      totalItems++
    }

    // 3. 更新盘点单 total_items
    await conn.query('UPDATE stocktakes SET total_items = ? WHERE id = ?', [totalItems, stocktakeId])

    await conn.commit()

    res.json({
      code: 0, message: '盘点单创建成功',
      data: { stocktake_id: stocktakeId, warehouse_id, total_items: totalItems, blind_mode }
    })
  } catch (err) {
    await conn.rollback(); next(err)
  } finally { conn.release() }
})

// GET /api/inventory/stocktakes/:id - 盘点单详情 + 明细
router.get('/stocktakes/:id', requirePermission(PERMISSIONS.STOCKTAKE_REPORT), async (req, res, next) => {
  try {
    const id = Number(req.params.id)
    const [[stocktake]] = await pool.query(
      `SELECT s.*, w.name AS warehouse_name FROM stocktakes s
       LEFT JOIN warehouses w ON w.id = s.warehouse_id WHERE s.id = ?`, [id]
    )
    if (!stocktake) return res.status(404).json({ code: 404, message: '盘点单不存在' })

    const [items] = await pool.query(
      `SELECT i.*, p.name AS product_name, p.image_main, p.sku,
              sk.specs AS sku_specs
       FROM stocktake_items i
       LEFT JOIN products p ON p.id = i.product_id
       LEFT JOIN product_skus sk ON sk.id = i.sku_id
       WHERE i.stocktake_id = ? ORDER BY i.id`, [id]
    )

    res.json({ code: 0, message: 'ok', data: { ...stocktake, items } })
  } catch (err) { next(err) }
})

// POST /api/inventory/stocktakes/:id/count - 提交某个 item 的盘点结果
router.post('/stocktakes/:id/count', requirePermission(PERMISSIONS.STOCKTAKE_RUN), async (req, res, next) => {
  try {
    const id = Number(req.params.id)
    const { item_id, counted_stock, notes = '' } = req.body
    if (!item_id || counted_stock === undefined) {
      return res.status(400).json({ code: 400, message: 'item_id + counted_stock 必填' })
    }

    const [[item]] = await pool.query(
      'SELECT id, system_stock, stocktake_id FROM stocktake_items WHERE id = ? AND stocktake_id = ?',
      [item_id, id]
    )
    if (!item) return res.status(404).json({ code: 404, message: '盘点明细不存在' })

    const counted = Number(counted_stock)
    const diff = counted - Number(item.system_stock)

    await pool.query(
      `UPDATE stocktake_items SET counted_stock = ?, diff = ?, counted = 1, notes = ? WHERE id = ?`,
      [counted, diff, notes, item_id]
    )

    // 更新盘点单统计
    const [[stats]] = await pool.query(
      `SELECT COUNT(*) AS total,
              SUM(CASE WHEN counted = 1 THEN 1 ELSE 0 END) AS counted_items,
              SUM(CASE WHEN counted = 1 AND diff != 0 THEN 1 ELSE 0 END) AS diff_items
       FROM stocktake_items WHERE stocktake_id = ?`, [id]
    )
    await pool.query(
      `UPDATE stocktakes SET counted_items = ?, diff_items = ? WHERE id = ?`,
      [Number(stats.counted_items) || 0, Number(stats.diff_items) || 0, id]
    )

    res.json({ code: 0, message: '盘点结果已提交', data: { item_id, system_stock: item.system_stock, counted_stock: counted, diff } })
  } catch (err) { next(err) }
})

// POST /api/inventory/stocktakes/:id/complete - 完成盘点（写回 warehouse_stock）
router.post('/stocktakes/:id/complete', requirePermission(PERMISSIONS.STOCKTAKE_RUN), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const id = Number(req.params.id)
    await conn.beginTransaction()

    const [[st]] = await conn.query('SELECT * FROM stocktakes WHERE id = ? FOR UPDATE', [id])
    if (!st) { await conn.rollback(); return res.status(404).json({ code: 404, message: '盘点单不存在' }) }
    if (st.status !== 'in_progress') { await conn.rollback(); return res.status(400).json({ code: 400, message: '盘点单状态非 in_progress' }) }

    // 1. 写回 warehouse_stock (仅当 counted=1 且有差异)
    const [items] = await conn.query(
      'SELECT * FROM stocktake_items WHERE stocktake_id = ? AND counted = 1', [id]
    )
    let updated = 0
    for (const item of items) {
      const [[exist]] = await conn.query(
        'SELECT id FROM warehouse_stock WHERE warehouse_id=? AND product_id=? AND (sku_id <=> ?)',
        [st.warehouse_id, item.product_id, item.sku_id]
      )
      if (exist) {
        await conn.query('UPDATE warehouse_stock SET quantity = ? WHERE id = ?', [item.counted_stock, exist.id])
      } else {
        await conn.query(
          'INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity, location) VALUES (?,?,?,?,?)',
          [st.warehouse_id, item.product_id, item.sku_id, item.counted_stock, item.location]
        )
      }
      updated++
    }

    // 2. 更新盘点单状态
    await conn.query(
      `UPDATE stocktakes SET status='completed', finished_at=NOW() WHERE id = ?`, [id]
    )

    await conn.commit()

    res.json({ code: 0, message: '盘点完成，库存已更新', data: { stocktake_id: id, updated_items: updated } })
  } catch (err) {
    await conn.rollback(); next(err)
  } finally { conn.release() }
})

// POST /api/inventory/reconcile - 盘点对账（从 qrcodes 重算 warehouse_stock）
router.post('/reconcile', requireRole('admin'), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    // 1. 收集所有 inStock qrcode 按 (warehouse, product, sku) 计数
    const [rows] = await conn.query(
      `SELECT warehouse_id, product_id, sku_id, COUNT(*) as cnt
       FROM qrcodes WHERE status='inStock' AND warehouse_id IS NOT NULL AND product_id IS NOT NULL
       GROUP BY warehouse_id, product_id, sku_id`
    )
    let synced = 0, created = 0
    for (const r of rows) {
      // 检查 warehouse_stock 是否存在
      const [exist] = await conn.query(
        'SELECT id FROM warehouse_stock WHERE warehouse_id=? AND product_id=? AND (sku_id <=> ?)', [r.warehouse_id, r.product_id, r.sku_id]
      )
      if (exist.length) {
        await conn.query('UPDATE warehouse_stock SET quantity=? WHERE id=?', [r.cnt, exist[0].id])
        synced++
      } else {
        await conn.query(
          'INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,?,?)',
          [r.warehouse_id, r.product_id, r.sku_id, r.cnt]
        )
        created++
      }
    }
    await conn.commit()
    res.json({ code: 0, message: '对账完成', data: { synced, created, total_rows: rows.length } })
  } catch (err) {
    await conn.rollback(); next(err)
  } finally { conn.release() }
})

export default router
