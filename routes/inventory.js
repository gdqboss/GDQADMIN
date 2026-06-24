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

    // ✅ 累加模式：同一 SKU 在同一仓库入库会自动累加库存
    // 不再拒绝"SKU 已存在"的请求（波哥 2026-06-24 反馈：补货是正常业务）
    // - 同 SKU×仓库 已存在 → quantity = old + new（补货）
    // - 不存在 → 直接 INSERT（首次入库）
    // 仍记录冲突信息供前端提示（"此商品已存在，本次入库会累加到现有库存 X"）

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
        for (const item of items) {
          // 查询商品是否需要一物一码
          const [[prod]] = await conn.query(
            'SELECT require_qrcode FROM products WHERE id = ?', [item.product_id])
          const needQrcode = prod?.require_qrcode === 1

          // 入库明细记录（qrcode_count 标识生成了几个二维码）
          // alert_stock 记录这次入库设置的预警值（再编辑时能恢复，NULL=未设）
          const [itemRes] = await conn.query(
            'INSERT INTO inbound_items (record_id, product_id, sku_id, qrcode_count, quantity, alert_stock) VALUES (?,?,?,?,?,?)',
            [result.insertId, item.product_id, item.sku_id || null,
             needQrcode ? item.quantity : 0, item.quantity,
             (item.alert_stock !== undefined && item.alert_stock !== null && item.alert_stock !== '')
               ? Number(item.alert_stock) : null])
          const inboundItemId = itemRes.insertId

          // ✅ 入库时同步更新 products.alert_stock（前端可定档预警值）
          // 业务规则：直接覆盖预警值（波哥 2026-06-24 反馈原 GREATEST 不能降值）
          // - 传 > 0：覆盖为新值（可升可降）
          // - 传 0 或 null：不修改（保留旧值）
          if (item.alert_stock !== undefined && item.alert_stock !== null && Number(item.alert_stock) >= 0) {
            await conn.query(
              'UPDATE products SET alert_stock = ? WHERE id = ?',
              [Number(item.alert_stock), item.product_id])
          }

          // 一物一码：每个实物生成独立 qrcode
          if (needQrcode && item.quantity > 0) {
            await generateQrcodes(conn, {
              count: item.quantity,
              product_id: item.product_id,
              sku_id: item.sku_id,
              warehouse_id,
              operator,
              inbound_item_id: inboundItemId
            })
          }

          // ✅ 累加模式：warehouse_stock UPSERT
          // - 首次入库 → INSERT 新记录
          // - 补货/同 SKU 再次入库 → UPDATE quantity = old + new
          // （前端不再需要走"库存管理 → 调整"流程）
          let newStockId
          let beforeQty = 0
          if (item.sku_id) {
            // 先查现状
            const [[existing]] = await conn.query(
              `SELECT id, quantity FROM warehouse_stock
               WHERE warehouse_id=? AND product_id=? AND sku_id=?`,
              [warehouse_id, item.product_id, item.sku_id]
            )
            if (existing) {
              beforeQty = existing.quantity
              await conn.query(
                'UPDATE warehouse_stock SET quantity = quantity + ? WHERE id = ?',
                [item.quantity, existing.id]
              )
              newStockId = existing.id
            } else {
              const [insertRes] = await conn.query(
                `INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,?,?)`,
                [warehouse_id, item.product_id, item.sku_id, item.quantity])
              newStockId = insertRes.insertId
            }
          } else {
            // sku_id=NULL 的情况：先查
            const [[existing]] = await conn.query(
              `SELECT id, quantity FROM warehouse_stock
               WHERE warehouse_id=? AND product_id=? AND sku_id IS NULL`,
              [warehouse_id, item.product_id]
            )
            if (existing) {
              beforeQty = existing.quantity
              await conn.query(
                'UPDATE warehouse_stock SET quantity = quantity + ? WHERE id = ?',
                [item.quantity, existing.id]
              )
              newStockId = existing.id
            } else {
              const [insertRes] = await conn.query(
                `INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,NULL,?)`,
                [warehouse_id, item.product_id, item.quantity])
              newStockId = insertRes.insertId
            }
          }

          // 写流水：入库 +N (before=已有, after=已有+本次)
          // 先查 stock_movements 表是否存在（北京没这表）
          const [[smInfo]] = await conn.query(
            "SELECT COUNT(*) as has_sm FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'stock_movements'")
          if (smInfo.has_sm > 0) {
            await conn.query(
              `INSERT INTO stock_movements
               (warehouse_id, product_id, sku_id, change_type, delta, before_qty, after_qty, operator, ref_type, ref_id, remark)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)`,
              [warehouse_id, item.product_id, item.sku_id || null, 'inbound',
               item.quantity, beforeQty, beforeQty + item.quantity, operator,
               'inbound_records', result.insertId, supplier ? `供应商：${supplier}` : null])
          }

          await conn.query(
            'UPDATE products SET stock = COALESCE(stock, 0) + ? WHERE id = ?',
            [item.quantity, item.product_id])

          // ✅ 入库后 recheck 库存预警
          // 规则：stock < alert_stock → 触发预警；stock >= alert_stock → 自动 handled
          const [[alertProd]] = await conn.query(
            'SELECT id, stock, alert_stock FROM products WHERE id = ?', [item.product_id])
          if (alertProd && alertProd.alert_stock > 0) {
            if (alertProd.stock < alertProd.alert_stock) {
              // 创建或保留未 handled 的预警（去重）
              const [[existing]] = await conn.query(
                'SELECT id FROM stock_alerts WHERE product_id = ? AND warehouse_id = ? AND handled = 0 LIMIT 1',
                [item.product_id, warehouse_id])
              if (!existing) {
                const suggestQty = alertProd.alert_stock * 2 - alertProd.stock
                const level = alertProd.stock <= alertProd.alert_stock * 0.5 ? 'critical' : 'low'
                await conn.query(
                  `INSERT INTO stock_alerts (product_id, warehouse_id, current_stock, alert_stock, suggest_qty, level)
                   VALUES (?,?,?,?,?,?)`,
                  [item.product_id, warehouse_id, alertProd.stock, alertProd.alert_stock, suggestQty, level])
              }
            } else {
              // 库存够了 → 自动标 handled
              await conn.query(
                `UPDATE stock_alerts SET handled = 1, handled_at = NOW(),
                 handled_reason = 'auto: stock >= alert_stock after inbound'
                 WHERE product_id = ? AND warehouse_id = ? AND handled = 0`,
                [item.product_id, warehouse_id])
            }
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
    for (const item of finalItems) {
      // 库存按 SKU 维度校验（兼容无 SKU 商品）
      const stockSql = item.sku_id
        ? 'SELECT quantity FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? AND sku_id = ?'
        : 'SELECT quantity FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ? AND sku_id IS NULL'
      const stockParams = item.sku_id
        ? [warehouse_id, item.product_id, item.sku_id]
        : [warehouse_id, item.product_id]
      const [[stock]] = await conn.query(stockSql, stockParams)
      if (!stock || stock.quantity < item.quantity) {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: `商品ID ${item.product_id} 库存不足` })
      }
      // 不需要校验 require_qrcode（出库允许不绑定一物一码）
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
          const wsSql = item.sku_id
            ? 'UPDATE warehouse_stock SET quantity = quantity - ? WHERE warehouse_id = ? AND product_id = ? AND sku_id = ? AND quantity >= ?'
            : 'UPDATE warehouse_stock SET quantity = quantity - ? WHERE warehouse_id = ? AND product_id = ? AND sku_id IS NULL AND quantity >= ?'
          const wsParams = item.sku_id
            ? [item.quantity, warehouse_id, item.product_id, item.sku_id, item.quantity]
            : [item.quantity, warehouse_id, item.product_id, item.quantity]
          const [wsResult] = await conn.query(wsSql, wsParams)
          if (wsResult.affectedRows === 0) {
            throw Object.assign(new Error(`商品ID ${item.product_id} 库存不足`), { status: 400 })
          }
          const [pResult] = await conn.query(
            'UPDATE products SET stock = COALESCE(stock, 0) - ? WHERE id = ? AND COALESCE(stock, 0) >= ?',
            [item.quantity, item.product_id, item.quantity])
          if (pResult.affectedRows === 0) {
            throw Object.assign(new Error(`商品ID ${item.product_id} 总库存不足`), { status: 400 })
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
          if (item.sku_id) {
            await conn.query(
              `INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,?,?)
               ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)`,
              [item.warehouse_id || warehouse_id, item.product_id, item.sku_id, item.quantity]
            )
          } else {
            await conn.query(
              `INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,NULL,?)
               ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)`,
              [item.warehouse_id || warehouse_id, item.product_id, item.quantity]
            )
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

    // Rollback old stock
    const [oldItems] = await conn.query('SELECT * FROM inbound_items WHERE record_id = ?', [recordId])
    for (const item of oldItems) {
      await conn.query(
        'UPDATE warehouse_stock SET quantity = quantity - ? WHERE warehouse_id = ? AND product_id = ?',
        [item.quantity, record.warehouse_id, item.product_id])
      await conn.query('UPDATE products SET stock = stock - ? WHERE id = ?', [item.quantity, item.product_id])
    }

    // Update record
    const totalQty = items.reduce((s, i) => s + (i.quantity || 0), 0)
    await conn.query(
      'UPDATE inbound_records SET warehouse_id=?, supplier=?, total_qty=? WHERE id=?',
      [warehouse_id, supplier || '', totalQty, recordId])

    // Delete old items, insert new
    await conn.query('DELETE FROM inbound_items WHERE record_id = ?', [recordId])
    for (const item of items) {
      await conn.query(
        'INSERT INTO inbound_items (record_id, product_id, sku_id, quantity, alert_stock) VALUES (?,?,?,?,?)',
        [recordId, item.product_id, item.sku_id || null, item.quantity,
         (item.alert_stock !== undefined && item.alert_stock !== null && item.alert_stock !== '')
           ? Number(item.alert_stock) : null])
      // Apply new stock
      await conn.query(
        `INSERT INTO warehouse_stock (warehouse_id, product_id, quantity) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE quantity = quantity + ?`,
        [warehouse_id, item.product_id, item.quantity, item.quantity])
      await conn.query('UPDATE products SET stock = stock + ? WHERE id = ?', [item.quantity, item.product_id])
      // ✅ 编辑入库时也同步更新 alert_stock（按需覆盖）
      if (item.alert_stock !== undefined && item.alert_stock !== null && item.alert_stock !== '') {
        await conn.query('UPDATE products SET alert_stock = ? WHERE id = ?', [Number(item.alert_stock), item.product_id])
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

    // Rollback old stock
    const [oldItems] = await conn.query('SELECT * FROM outbound_items WHERE record_id = ?', [recordId])
    for (const item of oldItems) {
      await conn.query(
        `INSERT INTO warehouse_stock (warehouse_id, product_id, quantity) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE quantity = quantity + ?`,
        [record.warehouse_id, item.product_id, item.quantity, item.quantity])
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
      await conn.query(
        'INSERT INTO outbound_items (record_id, product_id, sku_id, quantity, qrcode_id) VALUES (?,?,?,?,?)',
        [recordId, item.product_id, item.sku_id || null, item.quantity, item.qrcode_id || null])
      // Apply new stock deduction
      await conn.query(
        'UPDATE warehouse_stock SET quantity = quantity - ? WHERE warehouse_id = ? AND product_id = ?',
        [item.quantity, warehouse_id, item.product_id])
      await conn.query('UPDATE products SET stock = stock - ? WHERE id = ?', [item.quantity, item.product_id])
      if (item.qrcode_id) {
        await conn.query("UPDATE qrcodes SET status = 'used' WHERE id = ?", item.qrcode_id)
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

      // 2. warehouse_stock 扣减（拆 SKU 维度）
      if (item.sku_id) {
        await conn.query(
          `UPDATE warehouse_stock SET quantity = quantity - ?
           WHERE warehouse_id=? AND product_id=? AND sku_id=? AND quantity >= ?`,
          [item.quantity, record.warehouse_id, item.product_id, item.sku_id, item.quantity]
        )
      } else {
        await conn.query(
          'UPDATE warehouse_stock SET quantity = quantity - ? WHERE warehouse_id = ? AND product_id = ? AND sku_id IS NULL AND quantity >= ?',
          [item.quantity, record.warehouse_id, item.product_id, item.quantity]
        )
      }

      // 3. products.stock
      await conn.query('UPDATE products SET stock = stock - ? WHERE id = ?', [item.quantity, item.product_id])
    }

    // Delete items and record
    await conn.query('DELETE FROM inbound_items WHERE record_id = ?', [recordId])
    await conn.query('DELETE FROM inbound_records WHERE id = ?', [recordId])

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
      if (item.sku_id) {
        await conn.query(
          `INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,?,?)
           ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)`,
          [record.warehouse_id, item.product_id, item.sku_id, item.quantity]
        )
      } else {
        await conn.query(
          `INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,NULL,?)
           ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)`,
          [record.warehouse_id, item.product_id, item.quantity]
        )
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
      // Apply stock return
      await conn.query(
        `INSERT INTO warehouse_stock (warehouse_id, product_id, quantity) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE quantity = quantity + ?`,
        [warehouse_id, item.product_id, item.quantity, item.quantity])
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

// ========== 库存管理（新模型：主表 + 调整） ==========

// GET /api/inventory/stock — 库存列表（带商品/SKU/仓库信息）
router.get('/stock', requirePermission(PERMISSIONS.INVENTORY_READ), async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    const offset = (page - 1) * size
    const { warehouse_id, product_id, keyword } = req.query
    const where = ['1=1']
    const params = []
    if (warehouse_id) { where.push('ws.warehouse_id = ?'); params.push(warehouse_id) }
    if (product_id)   { where.push('ws.product_id = ?');   params.push(product_id) }
    if (keyword) {
      where.push('(p.name LIKE ? OR ps.sku LIKE ? OR ps.sku_key LIKE ?)')
      const kw = `%${keyword}%`
      params.push(kw, kw, kw)
    }
    const whereSql = where.join(' AND ')
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM warehouse_stock ws
       JOIN products p ON ws.product_id = p.id
       LEFT JOIN product_skus ps ON ws.sku_id = ps.id
       WHERE ${whereSql}`, params)
    const [rows] = await pool.query(
      `SELECT ws.id, ws.warehouse_id, ws.product_id, ws.sku_id, ws.quantity, ws.location,
              p.name AS product_name, p.sku AS product_sku, p.image_main, p.alert_stock, p.stock AS product_total_stock,
              ps.sku AS sku_code, ps.sku_key, ps.specs,
              w.name AS warehouse_name,
              CASE
                WHEN p.alert_stock IS NULL OR p.alert_stock = 0 THEN 'none'
                WHEN ws.quantity < p.alert_stock * 0.5 THEN 'critical'
                WHEN ws.quantity < p.alert_stock THEN 'low'
                ELSE 'ok'
              END AS alert_status
       FROM warehouse_stock ws
       JOIN products p ON ws.product_id = p.id
       LEFT JOIN product_skus ps ON ws.sku_id = ps.id
       LEFT JOIN warehouses w ON ws.warehouse_id = w.id
       WHERE ${whereSql}
       ORDER BY ws.id DESC LIMIT ? OFFSET ?`,
      [...params, size, offset])
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/inventory/stock/:id/adjust — 调整数量（增量 +/-）
router.put('/stock/:id/adjust', requirePermission(PERMISSIONS.INVENTORY_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const stockId = Number(req.params.id)
    const { delta, remark } = req.body
    const operator = req.user?.name || req.body.operator || 'system'
    if (!Number.isFinite(delta) || delta === 0) {
      return res.status(400).json({ code: 400, message: 'delta 必须是非 0 整数' })
    }
    const [[stock]] = await conn.query(
      'SELECT * FROM warehouse_stock WHERE id = ? FOR UPDATE', [stockId])
    if (!stock) {
      return res.status(404).json({ code: 404, message: '库存记录不存在' })
    }
    const beforeQty = stock.quantity
    const afterQty = beforeQty + delta
    if (afterQty < 0) {
      return res.status(400).json({ code: 400, message: `调整后数量不能为负（当前 ${beforeQty}，delta ${delta}）` })
    }
    await conn.query('UPDATE warehouse_stock SET quantity = ? WHERE id = ?', [afterQty, stockId])
    await conn.query(
      `INSERT INTO stock_movements
       (warehouse_id, product_id, sku_id, change_type, delta, before_qty, after_qty, operator, ref_type, ref_id, remark)
       VALUES (?,?,?,?,?,?,?,?,?,?,?)`,
      [stock.warehouse_id, stock.product_id, stock.sku_id, 'adjust',
       delta, beforeQty, afterQty, operator,
       'manual_adjust', stockId, remark || null])
    await conn.commit()
    res.json({ code: 0, data: { id: stockId, before: beforeQty, after: afterQty, delta }, message: 'ok' })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// DELETE /api/inventory/stock/:id — 删除库存关系（必须 quantity=0）
router.delete('/stock/:id', requirePermission(PERMISSIONS.INVENTORY_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const stockId = Number(req.params.id)
    const operator = req.user?.name || req.body.operator || 'system'
    const [[stock]] = await conn.query(
      'SELECT * FROM warehouse_stock WHERE id = ? FOR UPDATE', [stockId])
    if (!stock) {
      return res.status(404).json({ code: 404, message: '库存记录不存在' })
    }
    if (stock.quantity !== 0) {
      return res.status(400).json({ code: 400, message: `当前数量为 ${stock.quantity}，必须先调整到 0 才能删除` })
    }
    // 写流水
    await conn.query(
      `INSERT INTO stock_movements
       (warehouse_id, product_id, sku_id, change_type, delta, before_qty, after_qty, operator, ref_type, ref_id, remark)
       VALUES (?,?,?,?,?,?,?,?,?,?,?)`,
      [stock.warehouse_id, stock.product_id, stock.sku_id, 'delete',
       0, 0, 0, operator, 'manual_delete', stockId, '彻底删除库存关系'])
    await conn.query('DELETE FROM warehouse_stock WHERE id = ?', [stockId])
    await conn.commit()
    res.json({ code: 0, message: '已删除' })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// GET /api/inventory/stock/:id/movements — 流水记录
router.get('/stock/:id/movements', requirePermission(PERMISSIONS.STOCK_MOVEMENTS_READ), async (req, res, next) => {
  try {
    const stockId = Number(req.params.id)
    const [[stock]] = await pool.query('SELECT * FROM warehouse_stock WHERE id = ?', [stockId])
    if (!stock) return res.status(404).json({ code: 404, message: '库存记录不存在' })
    const [movements] = await pool.query(
      `SELECT * FROM stock_movements
       WHERE warehouse_id=? AND product_id=? AND (sku_id <=> ?)
       ORDER BY created_at DESC LIMIT 100`,
      [stock.warehouse_id, stock.product_id, stock.sku_id])
    res.json({ code: 0, data: { stock, movements }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/inventory/stock-movements — 全流水查询（操作记录 Tab 用）
// 支持筛选: warehouse_id, change_type, operator, start_date, end_date, keyword(SKU/商品编号)
router.get('/stock-movements', requirePermission(PERMISSIONS.STOCK_MOVEMENTS_READ), async (req, res, next) => {
  try {
    const { warehouse_id, change_type, operator, start_date, end_date, keyword } = req.query
    const page = Math.max(1, Number(req.query.page) || 1)
    const limit = Math.min(200, Math.max(1, Number(req.query.limit) || 50))
    const offset = (page - 1) * limit

    const where = []
    const params = []
    if (warehouse_id) { where.push('m.warehouse_id = ?'); params.push(Number(warehouse_id)) }
    if (change_type)  { where.push('m.change_type = ?');  params.push(change_type) }
    if (operator)     { where.push('m.operator = ?');     params.push(operator) }
    if (start_date)   { where.push('m.created_at >= ?');  params.push(start_date + ' 00:00:00') }
    if (end_date)     { where.push('m.created_at <= ?');  params.push(end_date + ' 23:59:59') }
    if (keyword)      { where.push('(p.sku LIKE ? OR p.name LIKE ? OR ps.sku_key LIKE ?)')
                        params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`) }
    const whereSql = where.length ? 'WHERE ' + where.join(' AND ') : ''

    const [rows] = await pool.query(
      `SELECT m.*, p.sku AS product_code, p.name AS product_name, ps.sku_key, w.name AS warehouse_name
       FROM stock_movements m
       LEFT JOIN products p ON m.product_id = p.id
       LEFT JOIN product_skus ps ON m.sku_id = ps.id
       LEFT JOIN warehouses w ON m.warehouse_id = w.id
       ${whereSql}
       ORDER BY m.created_at DESC, m.id DESC
       LIMIT ? OFFSET ?`,
      [...params, limit, offset])
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) AS total FROM stock_movements m
       LEFT JOIN products p ON m.product_id = p.id
       LEFT JOIN product_skus ps ON m.sku_id = ps.id
       ${whereSql}`,
      params)
    res.json({ code: 0, data: { list: rows, total, page, limit }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/stock-movements/adjust — 手动调整库存（补单/冲正/盘点修正）
// body: { warehouse_id, product_id, sku_id?, new_qty, reason }
//   - new_qty 是调整后的**最终数量**（不是 delta）
//   - reason 必填（操作人写的调整原因，会写进 stock_movements.remark）
router.post('/stock-movements/adjust', requirePermission(PERMISSIONS.STOCK_MOVEMENTS_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { warehouse_id, product_id, sku_id, new_qty, reason } = req.body || {}
    const operator = req.user?.name || req.user?.phone || 'unknown'

    // 1. 参数校验
    if (!warehouse_id || !product_id || new_qty == null) {
      return res.status(400).json({ code: 400, message: '参数不完整：warehouse_id / product_id / new_qty 必填' })
    }
    const targetQty = Number(new_qty)
    if (!Number.isFinite(targetQty) || targetQty < 0) {
      return res.status(400).json({ code: 400, message: 'new_qty 必须是非负整数' })
    }
    if (!reason || !String(reason).trim()) {
      return res.status(400).json({ code: 400, message: '请填写调整原因（必填，可追溯）' })
    }

    await conn.beginTransaction()

    // 2. 查当前 stock（如果不存在则视为 0）
    const [stockRows] = await conn.query(
      `SELECT id, quantity FROM warehouse_stock
       WHERE warehouse_id = ? AND product_id = ? AND ${sku_id ? 'sku_id = ?' : 'sku_id IS NULL'}`,
      sku_id ? [warehouse_id, product_id, sku_id] : [warehouse_id, product_id])
    const beforeQty = stockRows[0]?.quantity || 0
    const delta = targetQty - beforeQty

    // 3. 如果数量没变，不写流水（无意义的操作）
    if (delta === 0) {
      await conn.rollback()
      return res.json({ code: 0, data: { adjusted: false, message: '新数量与当前一致，无需调整' }, message: 'ok' })
    }

    // 4. UPSERT warehouse_stock
    if (stockRows.length === 0) {
      await conn.query(
        `INSERT INTO warehouse_stock (warehouse_id, product_id, sku_id, quantity) VALUES (?,?,?,?)`,
        [warehouse_id, product_id, sku_id || null, targetQty])
    } else {
      await conn.query(
        `UPDATE warehouse_stock SET quantity = ? WHERE id = ?`,
        [targetQty, stockRows[0].id])
    }

    // 5. 写流水
    const [moveRes] = await conn.query(
      `INSERT INTO stock_movements
       (warehouse_id, product_id, sku_id, change_type, delta, before_qty, after_qty, operator, ref_type, ref_id, remark)
       VALUES (?,?,?,?,?,?,?,?,?,?,?)`,
      [warehouse_id, product_id, sku_id || null, 'adjust',
       delta, beforeQty, targetQty, operator,
       'manual_adjust', null, `[手动调整] ${reason.trim()}`])

    // 6. 同步 products.stock 总数
    if (delta !== 0) {
      // 统计所有仓库的总量
      const [[{ totalStock }]] = await conn.query(
        `SELECT COALESCE(SUM(quantity), 0) AS totalStock FROM warehouse_stock WHERE product_id = ?`,
        [product_id])
      await conn.query(
        `UPDATE products SET stock = ? WHERE id = ?`,
        [totalStock, product_id])
    }

    await conn.commit()
    res.json({
      code: 0,
      data: {
        adjusted: true,
        movement_id: moveRes.insertId,
        before_qty: beforeQty,
        after_qty: targetQty,
        delta,
      },
      message: 'ok',
    })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

export default router
