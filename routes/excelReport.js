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

// ==================== 3. 获取报告详情 ====================
router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params
    
    const [report] = await query('SELECT * FROM excel_reports WHERE id = ?', [id])
    
    if (!report) {
      return res.json({ code: 404, message: '报告不存在' })
    }
    
    // 解析JSON字段
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

// ==================== 4. 更新报告 ====================
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

// ==================== 5. 删除报告 ====================
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

// ==================== 6. 批量删除 ====================
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

// ==================== 7. 保存合并报告 ====================
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

// ==================== 8. 获取合并列表 ====================
router.get('/merge/list', async (req, res) => {
  try {
    const sql = `
      SELECT id, name, report_ids, created_at
      FROM excel_report_merges
      ORDER BY created_at DESC
    `
    
    const list = await query(sql)
    
    // 解析report_ids
    list.forEach(item => {
      item.report_ids = JSON.parse(item.report_ids || '[]')
    })
    
    res.json({ code: 0, data: list })
  } catch (err) {
    console.error('List merges error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 9. 获取合并详情 ====================
router.get('/merge/:id', async (req, res) => {
  try {
    const { id } = req.params
    
    const [merge] = await query('SELECT * FROM excel_report_merges WHERE id = ?', [id])
    
    if (!merge) {
      return res.json({ code: 404, message: '合并报告不存在' })
    }
    
    merge.report_ids = JSON.parse(merge.report_ids || '[]')
    merge.merged_data = JSON.parse(merge.merged_data || '{}')
    
    // 获取关联的原始报告
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

// ==================== 10. 删除合并记录 ====================
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
