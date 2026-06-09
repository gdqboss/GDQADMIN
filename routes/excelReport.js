// Excel分析报告管理API
import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

async function query(sql, params = []) {
  const [rows] = await pool.query(sql, params)
  return rows
}

// ==================== 1. 保存分析报告 ====================
router.post('/save', async (req, res) => {
  try {
    const { 
      name, file_name, supplier, brand, date_range,
      total_qty, total_records, unique_sku, unique_stores,
      top_stores, size_dist, color_dist, top_sku, store_skus, insights, raw_data, combo_analysis
    } = req.body
    
    const userId = req.user?.id || 1
    
    const sql = `
      INSERT INTO excel_reports 
      (name, file_name, supplier, brand, date_range, total_qty, total_records, 
       unique_sku, unique_stores, top_stores, size_dist, color_dist, top_sku, store_skus, insights, raw_data, combo_analysis, created_by)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `
    
    const result = await query(sql, [
      name, file_name, supplier, brand, date_range,
      total_qty, total_records, unique_sku, unique_stores,
      JSON.stringify(top_stores || []),
      JSON.stringify(size_dist || []),
      JSON.stringify(color_dist || []),
      JSON.stringify(top_sku || []),
      JSON.stringify(store_skus || {}),
      JSON.stringify(insights || []),
      JSON.stringify(raw_data || {}),
      JSON.stringify(combo_analysis || {}),
      userId
    ])
    
    res.json({ code: 0, data: { id: result.insertId }, message: '保存成功' })
  } catch (err) {
    console.error('Save report error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 2. 获取报告列表 ====================
router.get('/list', async (req, res) => {
  try {
    const { page = 1, pageSize = 20 } = req.query
    const offset = (page - 1) * pageSize
    
    const sql = `
      SELECT id, name, file_name, supplier, brand, date_range,
             total_qty, total_records, unique_sku, unique_stores,
             created_at, updated_at
      FROM excel_reports
      ORDER BY created_at DESC
      LIMIT ? OFFSET ?
    `
    
    const list = await query(sql, [parseInt(pageSize), parseInt(offset)])
    
    const [countResult] = await query('SELECT COUNT(*) as total FROM excel_reports')
    
    res.json({
      code: 0,
      data: list,
      total: countResult.total,
      page: parseInt(page),
      pageSize: parseInt(pageSize)
    })
  } catch (err) {
    console.error('List reports error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 3. 门店销售报表（跨所有导入）====================
// 注意：必须放在 /:id 前面，否则会被 /:id 拦截
router.get('/store-sales', async (req, res) => {
  try {
    const { page = 1, pageSize = 50, sort_by = 'total_qty', sort_order = 'DESC' } = req.query
    const offset = (parseInt(page) - 1) * parseInt(pageSize)

    let orderCol = 'total_qty'
    if (sort_by === 'amount') orderCol = 'total_amount'
    else if (sort_by === 'sku_count') orderCol = 'sku_count'
    else if (sort_by === 'model_count') orderCol = 'model_count'
    const orderDir = sort_order.toUpperCase() === 'ASC' ? 'ASC' : 'DESC'

    const [rows] = await pool.query(`
      SELECT 
        store_code,
        store_name,
        SUM(quantity) as total_qty,
        SUM(amount) as total_amount,
        COUNT(DISTINCT model) as model_count,
        COUNT(DISTINCT sku) as sku_count,
        COUNT(*) as record_count,
        MIN(record_id) as first_record_id
      FROM imported_excel_items
      WHERE store_code IS NOT NULL AND store_code != ''
      GROUP BY store_code, store_name
      ORDER BY ${orderCol} ${orderDir}
      LIMIT ? OFFSET ?
    `, [parseInt(pageSize), offset])

    const [[{ total }]] = await pool.query(`
      SELECT COUNT(DISTINCT store_code) as total 
      FROM imported_excel_items 
      WHERE store_code IS NOT NULL AND store_code != ''
    `)

    const [[overall]] = await pool.query(`
      SELECT 
        SUM(quantity) as grand_qty,
        SUM(amount) as grand_amount,
        COUNT(DISTINCT model) as grand_models,
        COUNT(DISTINCT sku) as grand_skus,
        COUNT(DISTINCT store_code) as store_count
      FROM imported_excel_items
      WHERE store_code IS NOT NULL AND store_code != ''
    `)

    res.json({
      code: 0,
      data: rows,
      total,
      page: parseInt(page),
      pageSize: parseInt(pageSize),
      overall
    })
  } catch (err) {
    console.error('Store sales error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 4. 门店销售明细 ====================
router.get('/store-sales/:storeCode/items', async (req, res) => {
  try {
    const { storeCode } = req.params
    const { page = 1, pageSize = 50, sort_by = 'quantity', sort_order = 'DESC' } = req.query
    const offset = (parseInt(page) - 1) * parseInt(pageSize)

    let orderCol = 'quantity'
    if (sort_by === 'amount') orderCol = 'amount'
    else if (sort_by === 'sku') orderCol = 'sku'
    else if (sort_by === 'model') orderCol = 'model'
    else if (sort_by === 'color') orderCol = 'color'
    else if (sort_by === 'size') orderCol = 'size'
    const orderDir = sort_order.toUpperCase() === 'ASC' ? 'ASC' : 'DESC'

    const [rows] = await pool.query(`
      SELECT * FROM imported_excel_items
      WHERE store_code = ?
      ORDER BY ${orderCol} ${orderDir}
      LIMIT ? OFFSET ?
    `, [storeCode, parseInt(pageSize), offset])

    const [[{ total }]] = await pool.query(
      'SELECT COUNT(*) as total FROM imported_excel_items WHERE store_code = ?', [storeCode]
    )

    const [byModel] = await pool.query(`
      SELECT model, 
             SUM(quantity) as total_qty, 
             SUM(amount) as total_amount,
             COUNT(DISTINCT sku) as sku_count
      FROM imported_excel_items
      WHERE store_code = ? AND model IS NOT NULL AND model != ''
      GROUP BY model
      ORDER BY total_qty DESC
      LIMIT 30
    `, [storeCode])

    const [bySku] = await pool.query(`
      SELECT sku, product_name, model, color, size,
             SUM(quantity) as total_qty, 
             SUM(amount) as total_amount
      FROM imported_excel_items
      WHERE store_code = ? AND sku IS NOT NULL AND sku != ''
      GROUP BY sku, product_name, model, color, size
      ORDER BY total_qty DESC
      LIMIT 100
    `, [storeCode])

    res.json({
      code: 0,
      data: rows,
      total,
      page: parseInt(page),
      pageSize: parseInt(pageSize),
      byModel,
      bySku
    })
  } catch (err) {
    console.error('Store items error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 5. 门店+型号+SKU 交叉分析 ====================
router.get('/store-sales/model-sku', async (req, res) => {
  try {
    const { store_code, model } = req.query

    let where = 'WHERE i.model IS NOT NULL AND i.model != ""'
    let params = []
    if (store_code) { where += ' AND i.store_code = ?'; params.push(store_code) }
    if (model) { where += ' AND i.model = ?'; params.push(model) }

    //1. 型号聚合
    const [storeModel] = await pool.query(`
      SELECT store_code, store_name, model,
             SUM(quantity) as total_qty,
             SUM(amount) as total_amount,
             COUNT(DISTINCT sku) as sku_count
      FROM imported_excel_items
      WHERE store_code = ? AND model IS NOT NULL AND model != ""
      GROUP BY store_code, store_name, model
      ORDER BY total_qty DESC
      LIMIT 500
    `, [store_code])

    // 2.查该门店所有有图片的型号→图片映射
    const [imgRows] = await pool.query(`
      SELECT model, image_url,
             ROW_NUMBER() OVER (PARTITION BY model ORDER BY quantity DESC) as rn
      FROM imported_excel_items
      WHERE store_code = ? AND image_url IS NOT NULL AND image_url != ''
    `, [store_code])
    const imgMap = {}
    for (const r of imgRows) {
      if (!imgMap[r.model]) imgMap[r.model] = r.image_url
    }
    // 注入 image_url
    for (const m of storeModel) {
      m.image_url = imgMap[m.model] || null
    }

    // 3. SKU聚合
    let skuWhere = 'WHERE i.model IS NOT NULL AND i.model != ""'
    let skuParams = [store_code]
    if (model) { skuWhere += ' AND i.model = ?'; skuParams.push(model) }

    const [modelSku] = await pool.query(`
      SELECT i.model, i.sku, i.product_name, i.color, i.size,
             SUM(i.quantity) as total_qty,
             SUM(i.amount) as total_amount,
             COUNT(DISTINCT i.store_code) as store_count
      FROM imported_excel_items i
      ${skuWhere}
      GROUP BY i.model, i.sku, i.product_name, i.color, i.size
      ORDER BY total_qty DESC
      LIMIT 500
    `, skuParams)

    // 4. 查所有有图片的SKU→图片映射
    const [skuImgRows] = await pool.query(`
      SELECT sku, image_url,
             ROW_NUMBER() OVER (PARTITION BY sku ORDER BY quantity DESC) as rn
      FROM imported_excel_items
      WHERE image_url IS NOT NULL AND image_url != ''
    `)
    const skuImgMap = {}
    for (const r of skuImgRows) {
      if (!skuImgMap[r.sku]) skuImgMap[r.sku] = r.image_url
    }
    for (const s of modelSku) {
      s.image_url = skuImgMap[s.sku] || null
    }

    res.json({ code: 0, data: { storeModel, modelSku } })
  } catch (err) {
    console.error('Model SKU error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 6. 获取报告详情 ====================
router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params
    
    const [report] = await query('SELECT * FROM excel_reports WHERE id = ?', [id])
    
    if (!report) {
      return res.json({ code: 404, message: '报告不存在' })
    }
    
    report.top_stores = JSON.parse(report.top_stores || '[]')
    report.size_dist = JSON.parse(report.size_dist || '[]')
    report.color_dist = JSON.parse(report.color_dist || '[]')
    report.top_sku = JSON.parse(report.top_sku || '[]')
    report.store_skus = JSON.parse(report.store_skus || '{}')
    report.insights = JSON.parse(report.insights || '[]')
    report.raw_data = JSON.parse(report.raw_data || '{}')
    report.combo_analysis = JSON.parse(report.combo_analysis || '{}')
    
    res.json({ code: 0, data: report })
  } catch (err) {
    console.error('Get report error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 7. 更新报告 ====================
router.put('/:id', async (req, res) => {
  try {
    const { id } = req.params
    const { name, supplier, brand, date_range } = req.body
    
    const sql = `
      UPDATE excel_reports 
      SET name = ?, supplier = ?, brand = ?, date_range = ?
      WHERE id = ?
    `
    
    await query(sql, [name, supplier, brand, date_range, id])
    
    res.json({ code: 0, message: '更新成功' })
  } catch (err) {
    console.error('Update report error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 8. 删除报告 ====================
router.delete('/:id', async (req, res) => {
  try {
    const { id } = req.params
    
    await query('DELETE FROM excel_reports WHERE id = ?', [id])
    
    res.json({ code: 0, message: '删除成功' })
  } catch (err) {
    console.error('Delete report error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 9. 批量删除 ====================
router.post('/batch-delete', async (req, res) => {
  try {
    const { ids } = req.body
    
    if (!ids || !Array.isArray(ids) || ids.length === 0) {
      return res.json({ code: 400, message: '请选择要删除的报告' })
    }
    
    const placeholders = ids.map(() => '?').join(',')
    await query(`DELETE FROM excel_reports WHERE id IN (${placeholders})`, ids)
    
    res.json({ code: 0, message: `已删除${ids.length}条记录` })
  } catch (err) {
    console.error('Batch delete error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 10. 保存合并报告 ====================
router.post('/merge-save', async (req, res) => {
  try {
    const { name, report_ids, merged_data } = req.body
    const userId = req.user?.id || 1
    
    const sql = `
      INSERT INTO excel_report_merges (name, report_ids, merged_data, created_by)
      VALUES (?, ?, ?, ?)
    `
    
    const result = await query(sql, [
      name,
      JSON.stringify(report_ids || []),
      JSON.stringify(merged_data || {}),
      userId
    ])
    
    res.json({ code: 0, data: { id: result.insertId }, message: '合并报告保存成功' })
  } catch (err) {
    console.error('Save merge error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 11. 获取合并列表 ====================
router.get('/merge/list', async (req, res) => {
  try {
    const sql = `
      SELECT id, name, report_ids, created_at
      FROM excel_report_merges
      ORDER BY created_at DESC
    `
    
    const list = await query(sql)
    
    list.forEach(item => {
      item.report_ids = JSON.parse(item.report_ids || '[]')
    })
    
    res.json({ code: 0, data: list })
  } catch (err) {
    console.error('List merges error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 12. 获取合并详情 ====================
router.get('/merge/:id', async (req, res) => {
  try {
    const { id } = req.params
    
    const [merge] = await query('SELECT * FROM excel_report_merges WHERE id = ?', [id])
    
    if (!merge) {
      return res.json({ code: 404, message: '合并报告不存在' })
    }
    
    merge.report_ids = JSON.parse(merge.report_ids || '[]')
    merge.merged_data = JSON.parse(merge.merged_data || '{}')
    
    if (merge.report_ids.length > 0) {
      const placeholders = merge.report_ids.map(() => '?').join(',')
      const reports = await query(
        `SELECT id, name, supplier, brand, total_qty, total_records, unique_sku, unique_stores, top_stores, size_dist, top_sku FROM excel_reports WHERE id IN (${placeholders})`,
        merge.report_ids
      )
      merge.reports = reports
    }
    
    res.json({ code: 0, data: merge })
  } catch (err) {
    console.error('Get merge error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 13. 删除合并记录 ====================
router.delete('/merge/:id', async (req, res) => {
  try {
    const { id } = req.params
    await query('DELETE FROM excel_report_merges WHERE id = ?', [id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) {
    console.error('Delete merge error:', err)
    res.json({ code: 500, message: err.message })
  }
})

export default router