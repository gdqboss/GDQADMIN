import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { requirePermission, requireRole, PERMISSIONS } from '../middleware/rbac.js'
import { translateFields } from '../services/translation.js'

const router = Router()

router.get('/', async (req, res, next) => {
  try {
    const { keyword, category, category_id, status } = req.query
    const { page, size } = parsePagination(req.query)
    let sql = 'SELECT * FROM products WHERE 1=1'
    let countSql = 'SELECT COUNT(*) as total FROM products WHERE 1=1'
    const params = []
    const countParams = []

    // Supplier data isolation: if user is linked to a supplier, only show their products
    if (req.user.supplier_id) {
      const [[sup]] = await pool.query('SELECT name FROM suppliers WHERE id = ?', [req.user.supplier_id])
      if (sup) {
        sql += ' AND supplier = ?'
        countSql += ' AND supplier = ?'
        params.push(sup.name)
        countParams.push(sup.name)
      }
    }

    if (keyword) {
      sql += ' AND (p.name LIKE ? OR p.sku LIKE ?)'
      countSql += ' AND (p.name LIKE ? OR p.sku LIKE ?)'
      params.push(`%${keyword}%`, `%${keyword}%`)
      countParams.push(`%${keyword}%`, `%${keyword}%`)
    }
    if (category_id) {
      // WITH RECURSIVE 包含所有子分类
      const [catRows] = await pool.query(`
        WITH RECURSIVE subtree AS (
          SELECT id FROM categories WHERE id = ?
          UNION ALL
          SELECT c.id FROM categories c JOIN subtree s ON c.parent_id = s.id
        )
        SELECT id FROM subtree
      `, [Number(category_id)])
      const catIds = catRows.map(r => r.id)
      if (catIds.length) {
        const ph = catIds.map(() => '?').join(',')
        sql += ` AND category_id IN (${ph})`
        countSql += ` AND category_id IN (${ph})`
        params.push(...catIds)
        countParams.push(...catIds)
      }
    } else if (category) {
      sql += ' AND category = ?'
      countSql += ' AND category = ?'
      params.push(category)
      countParams.push(category)
    }
    if (status) {
      sql += ' AND status = ?'
      countSql += ' AND status = ?'
      params.push(status)
      countParams.push(status)
    }

    const whereClause = sql.split('WHERE 1=1')[1] || ''
    const countParamsModified = countParams.slice(0, -2) // remove limit/offset for count subquery

    const [rows] = await pool.query(`
      SELECT p.*, COUNT(s.id) as sku_count,
        GROUP_CONCAT(CONCAT(s.sku, '|', COALESCE(s.sale_price, ''), '|', COALESCE(s.stock, 0)) SEPARATOR ';') as sku_summary
      FROM products p
      LEFT JOIN product_skus s ON p.id = s.product_id
      WHERE 1=1${whereClause}
      GROUP BY p.id
      ORDER BY p.created_at DESC
      LIMIT ${size} OFFSET ${(page - 1) * size}
    `, params)

    const [[{ total }]] = await pool.query(`SELECT COUNT(*) as total FROM products p WHERE 1=1${whereClause.split('ORDER BY')[0]}`, countParams)

    // Auto-translate to English if requested
    if (req.lang === 'en') {
      await translateFields(rows, ['name', 'category'])
    }

    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

router.get('/all', async (req, res, next) => {
  try {
    let sql = 'SELECT id, name, sku, image_main FROM products WHERE 1=1'
    const params = []

    if (req.user.supplier_id) {
      const [[sup]] = await pool.query('SELECT name FROM suppliers WHERE id = ?', [req.user.supplier_id])
      if (sup) {
        sql += ' AND supplier = ?'
        params.push(sup.name)
      }
    }

    sql += ' ORDER BY id DESC'
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

router.post('/', requirePermission(PERMISSIONS.PRODUCTS_WRITE), async (req, res, next) => {
  try {
    let { sku, name, category, category_id, spec, unit, supplier, purchase_price, sale_price, stock, safe_stock, image_main, images, external_links, require_qrcode, group_qr_url, group_qr_type } = req.body
    if (!sku) {
      const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '')
      // 查找当前日期最大的SKU序号
      const [[{ maxSku }]] = await pool.query(
        "SELECT MAX(CAST(SUBSTRING_INDEX(sku, '-', -1) AS UNSIGNED)) as maxSku FROM products WHERE sku LIKE ?",
        [`${dateStr}%`]
      )
      const nextNum = (maxSku || 0) + 1
      sku = `${dateStr}-${String(nextNum).padStart(4, '0')}`
    }

    // Convert empty strings to null for numeric fields
    const cleanPurchasePrice = purchase_price === '' || purchase_price === null ? null : purchase_price
    const cleanSalePrice = sale_price === '' || sale_price === null ? null : sale_price
    const cleanStock = stock === '' || stock === null ? 0 : stock
    const cleanSafeStock = safe_stock === '' || safe_stock === null ? 0 : safe_stock

    const [result] = await pool.query(
      'INSERT INTO products (sku, name, category, category_id, spec, unit, supplier, purchase_price, sale_price, stock, safe_stock, image_main, images, external_links, require_qrcode, group_qr_url, group_qr_type) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [sku, name || null, category || null, category_id || null, spec || null, unit || '个', supplier || null,
       cleanPurchasePrice, cleanSalePrice, cleanStock, cleanSafeStock,
       image_main || null,
       images ? JSON.stringify(images) : null,
       external_links ? JSON.stringify(external_links) : null,
       require_qrcode ? 1 : 0, group_qr_url || null, group_qr_type || null]
    )
    res.json({ code: 0, data: { id: result.insertId, sku }, message: 'ok' })
  } catch (err) {
    
    next(err)
  }
})

router.put('/:id', requirePermission(PERMISSIONS.PRODUCTS_WRITE), async (req, res, next) => {
  try {
    const allowedFields = ['name', 'category', 'category_id', 'spec', 'unit', 'supplier', 'purchase_price', 'sale_price', 'alert_stock', 'status', 'image_main', 'require_qrcode', 'group_qr_url', 'group_qr_type']
    const fields = []
    const params = []
    for (const field of allowedFields) {
      if (req.body[field] !== undefined) {
        fields.push(`${field} = ?`)
        // Convert empty strings to null for numeric fields
        if (['purchase_price', 'sale_price', 'alert_stock'].includes(field)) {
          params.push(req.body[field] === '' || req.body[field] === null ? null : req.body[field])
        } else {
          params.push(req.body[field])
        }
      }
    }
    // JSON fields
    if (req.body.images !== undefined) {
      fields.push('images = ?')
      params.push(req.body.images ? JSON.stringify(req.body.images) : null)
    }
    if (req.body.external_links !== undefined) {
      fields.push('external_links = ?')
      params.push(req.body.external_links ? JSON.stringify(req.body.external_links) : null)
    }
    if (!fields.length) return res.status(400).json({ code: 400, message: '没有需要更新的字段' })
    params.push(req.params.id)
    await pool.query(`UPDATE products SET ${fields.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

router.delete('/:id', requirePermission(PERMISSIONS.PRODUCTS_DELETE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const productId = req.params.id

    // Check if product has related records with detailed info
    const [stockRecords] = await conn.query(
      `SELECT ws.quantity, w.name as warehouse_name
       FROM warehouse_stock ws
       LEFT JOIN warehouses w ON ws.warehouse_id = w.id
       WHERE ws.product_id = ?`,
      [productId]
    )

    const [qrcodeRecords] = await conn.query(
      `SELECT code, status FROM qrcodes WHERE product_id = ? LIMIT 10`,
      [productId]
    )

    // Check inbound/outbound records
    const [[inboundCheck]] = await conn.query(
      'SELECT COUNT(*) as count FROM inbound_items WHERE product_id = ?',
      [productId]
    )
    const [[outboundCheck]] = await conn.query(
      'SELECT COUNT(*) as count FROM outbound_items WHERE product_id = ?',
      [productId]
    )
    const [[returnCheck]] = await conn.query(
      'SELECT COUNT(*) as count FROM return_items WHERE product_id = ?',
      [productId]
    )

    if (stockRecords.length > 0 || qrcodeRecords.length > 0 ||
        inboundCheck.count > 0 || outboundCheck.count > 0 || returnCheck.count > 0) {
      await conn.rollback()

      // Build detailed error message
      let message = '无法删除该商品，存在以下关联数据：\n\n'

      if (stockRecords.length > 0) {
        message += `📦 库存记录（${stockRecords.length}条）：\n`
        stockRecords.forEach(record => {
          message += `  • ${record.warehouse_name || '未知仓库'}：${record.quantity} 件\n`
        })
        message += '\n'
      }

      if (qrcodeRecords.length > 0) {
        const totalQrcodes = (await conn.query(
          'SELECT COUNT(*) as count FROM qrcodes WHERE product_id = ?',
          [productId]
        ))[0][0].count

        message += `🏷️ 二维码绑定（${totalQrcodes}个）：\n`
        qrcodeRecords.slice(0, 5).forEach(qr => {
          const statusText = qr.status === 'unused' ? '未使用' :
                           qr.status === 'bound' ? '已绑定' :
                           qr.status === 'shipped' ? '已发货' :
                           qr.status === 'sold' ? '已销售' : qr.status
          message += `  • ${qr.code} (${statusText})\n`
        })
        if (totalQrcodes > 5) {
          message += `  • ... 还有 ${totalQrcodes - 5} 个二维码\n`
        }
        message += '\n'
      }

      if (inboundCheck.count > 0 || outboundCheck.count > 0 || returnCheck.count > 0) {
        message += `📋 出入库记录：\n`
        if (inboundCheck.count > 0) message += `  • 入库记录：${inboundCheck.count} 条\n`
        if (outboundCheck.count > 0) message += `  • 出库记录：${outboundCheck.count} 条\n`
        if (returnCheck.count > 0) message += `  • 退货记录：${returnCheck.count} 条\n`
        message += '\n'
      }

      message += '💡 解决方案：\n'
      message += '  1. 前往"仓库管理"删除各仓库的库存记录\n'
      message += '  2. 前往"一物一码"解绑或删除二维码\n'
      if (inboundCheck.count > 0 || outboundCheck.count > 0 || returnCheck.count > 0) {
        message += '  3. 前往"出入库管理"删除相关出入库记录\n'
        message += '  4. 或使用"停产"功能保留数据但标记为已停产'
      } else {
        message += '  3. 或使用"停产"功能保留数据但标记为已停产'
      }

      return res.status(400).json({
        code: 400,
        message: message
      })
    }

    // Delete related specs and SKUs first
    await conn.query('DELETE FROM product_spec_values WHERE spec_id IN (SELECT id FROM product_specs WHERE product_id = ?)', [productId])
    await conn.query('DELETE FROM product_specs WHERE product_id = ?', [productId])
    await conn.query('DELETE FROM product_skus WHERE product_id = ?', [productId])
    
    // Delete all related records (外键约束)
    await conn.query('DELETE FROM inbound_items WHERE product_id = ?', [productId])
    await conn.query('DELETE FROM outbound_items WHERE product_id = ?', [productId])
    await conn.query('DELETE FROM purchase_costs WHERE product_id = ?', [productId])
    await conn.query('DELETE FROM return_items WHERE product_id = ?', [productId])
    await conn.query('DELETE FROM sales_revenues WHERE product_id = ?', [productId])
    await conn.query('DELETE FROM stock_alerts WHERE product_id = ?', [productId])
    await conn.query('DELETE FROM transfer_items WHERE product_id = ?', [productId])
    await conn.query('DELETE FROM warehouse_stock WHERE product_id = ?', [productId])
    await conn.query('DELETE FROM qrcodes WHERE product_id = ?', [productId])

    // Finally delete the product
    await conn.query('DELETE FROM products WHERE id = ?', [productId])

    await conn.commit()
    res.json({ code: 0, data: null, message: '删除成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ── 规格与SKU ──────────────────────────────────────────────────────────────────

// GET /api/products/:id/specs  — 返回规格+SKU
router.get('/:id/specs', async (req, res, next) => {
  try {
    const pid = req.params.id
    const [specs] = await pool.query('SELECT * FROM product_specs WHERE product_id = ? ORDER BY sort_order', [pid])
    if (specs.length) {
      const sids = specs.map(s => s.id)
      const [values] = await pool.query('SELECT * FROM product_spec_values WHERE spec_id IN (?) ORDER BY sort_order', [sids])
      for (const s of specs) s.values = values.filter(v => v.spec_id === s.id)
    }
    const [skus] = await pool.query('SELECT id, product_id, sku, sku_key AS `key`, specs, image, purchase_price, sale_price, stock, status FROM product_skus WHERE product_id = ? ORDER BY id', [pid])
    res.json({ code: 0, data: { specs, skus }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/products/:id/specs  — 事务保存规格+SKU（全量替换）
router.post('/:id/specs', requirePermission(PERMISSIONS.PRODUCTS_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const pid = req.params.id
    const { specs = [], skus = [] } = req.body

    // 清旧数据
    const [oldSpecs] = await conn.query('SELECT id FROM product_specs WHERE product_id = ?', [pid])
    if (oldSpecs.length) {
      const sids = oldSpecs.map(s => s.id)
      await conn.query('DELETE FROM product_spec_values WHERE spec_id IN (?)', [sids])
    }
    await conn.query('DELETE FROM product_specs WHERE product_id = ?', [pid])
    await conn.query('DELETE FROM product_skus WHERE product_id = ?', [pid])

    // 插入规格
    for (let i = 0; i < specs.length; i++) {
      const s = specs[i]
      const [r] = await conn.query('INSERT INTO product_specs (product_id, name, sort_order) VALUES (?,?,?)', [pid, s.name, i])
      for (let j = 0; j < (s.values || []).length; j++) {
        await conn.query('INSERT INTO product_spec_values (spec_id, value, sort_order) VALUES (?,?,?)', [r.insertId, s.values[j], j])
      }
    }

    // 插入SKU
    for (let idx = 0; idx < skus.length; idx++) {
      const sku = skus[idx]

      // 如果SKU为空，自动生成
      let skuCode = sku.sku && sku.sku.trim() !== '' ? sku.sku.trim() : null
      if (!skuCode) {
        const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '')
        const [[{ cnt }]] = await conn.query(
          "SELECT COUNT(*) as cnt FROM product_skus WHERE sku LIKE ?",
          [`SKU-${dateStr}%`]
        )
        skuCode = `SKU-${dateStr}-${String(cnt + idx + 1).padStart(4, '0')}`
      }


      // 处理价格字段（空字符串转null）
      const cleanPurchasePrice = sku.purchase_price === '' || sku.purchase_price === null ? null : sku.purchase_price
      const cleanSalePrice = sku.sale_price === '' || sku.sale_price === null ? null : sku.sale_price
      const cleanStock = sku.stock === '' || sku.stock === null ? 0 : sku.stock

      await conn.query(
        'INSERT INTO product_skus (product_id, sku, sku_key, specs, image, purchase_price, sale_price, stock, status) VALUES (?,?,?,?,?,?,?,?,?)',
        [pid, skuCode, sku.key || null, JSON.stringify(sku.specs || {}), sku.image || null, cleanPurchasePrice, cleanSalePrice, cleanStock, sku.status || 'active']
      )
    }

    await conn.commit()
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// POST /api/products/batch-delete - 批量删除商品
router.post('/batch-delete', requirePermission(PERMISSIONS.PRODUCTS_DELETE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { ids } = req.body
    if (!ids || !Array.isArray(ids) || ids.length === 0) {
      return res.status(400).json({ code: 400, message: '请提供要删除的商品ID列表' })
    }

    await conn.beginTransaction()

    let successCount = 0
    const errors = []

    for (const id of ids) {
      try {
        // 检查是否有库存
        const [[stockCheck]] = await conn.query(
          'SELECT SUM(quantity) as total FROM warehouse_stock WHERE product_id = ?',
          [id]
        )
        if (stockCheck.total > 0) {
          errors.push(`商品ID ${id} 有库存，无法删除`)
          continue
        }

        // 检查是否有二维码
        const [[qrCheck]] = await conn.query(
          'SELECT COUNT(*) as count FROM qrcodes WHERE product_id = ?',
          [id]
        )
        if (qrCheck.count > 0) {
          errors.push(`商品ID ${id} 有关联二维码，无法删除`)
          continue
        }

        // 删除商品
        await conn.query('DELETE FROM product_skus WHERE product_id = ?', [id])
        await conn.query('DELETE FROM product_specs WHERE product_id = ?', [id])
        await conn.query('DELETE FROM product_spec_values WHERE product_id = ?', [id])
        await conn.query('DELETE FROM products WHERE id = ?', [id])
        successCount++
      } catch (err) {
        errors.push(`商品ID ${id} 删除失败: ${err.message}`)
      }
    }

    await conn.commit()

    const message = errors.length > 0
      ? `成功删除 ${successCount} 个商品，${errors.length} 个失败：${errors.join('; ')}`
      : `成功删除 ${successCount} 个商品`

    res.json({ code: 0, data: { successCount, errors }, message })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

export default router
