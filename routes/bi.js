// BI分析系统 - 新加坡本地数据版（含菲律宾销售数据）
import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

async function query(sql, params = []) {
  const [rows] = await pool.query(sql, params)
  return rows
}

// ==================== 1. 总览数据 ====================
router.get('/dashboard', async (req, res) => {
  try {
    // 销售统计
    const [salesStats] = await query(`
      SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(sale_price), 0) as total_amount,
        COUNT(DISTINCT buyer_phone) as total_customers,
        COUNT(DISTINCT product_id) as total_products
      FROM sales_orders WHERE status = 'completed'
    `)
    
    // 库存统计
    const [invStats] = await query(`
      SELECT 
        COUNT(*) as total_products,
        SUM(stock) as total_stock,
        SUM(stock * purchase_price) as total_stock_value,
        SUM(CASE WHEN stock <= 0 THEN 1 WHEN stock < safe_stock THEN 1 ELSE 0 END) as alert_count,
        SUM(CASE WHEN stock > safe_stock THEN 1 ELSE 0 END) as healthy_count
      FROM products WHERE status = 'active'
    `)
    
    // 今日销售
    const [todaySales] = await query(`
      SELECT COUNT(*) as orders, COALESCE(SUM(sale_price), 0) as amount
      FROM sales_orders WHERE status = 'completed' AND DATE(created_at) = CURDATE()
    `)
    
    // 本月销售
    const [monthSales] = await query(`
      SELECT COUNT(*) as orders, COALESCE(SUM(sale_price), 0) as amount
      FROM sales_orders WHERE status = 'completed' 
      AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())
    `)
    
    // 周环比
    const [lastWeek] = await query(`
      SELECT COALESCE(SUM(sale_price), 0) as amount
      FROM sales_orders WHERE status = 'completed'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
      AND created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    `)
    const [thisWeek] = await query(`
      SELECT COALESCE(SUM(sale_price), 0) as amount
      FROM sales_orders WHERE status = 'completed'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    `)
    
    const weekGrowth = lastWeek.amount > 0 
      ? ((thisWeek.amount - lastWeek.amount) / lastWeek.amount * 100).toFixed(1)
      : 0
    
    res.json({
      code: 0,
      data: {
        total_orders: salesStats.total_orders || 0,
        total_amount: parseFloat(salesStats.total_amount) || 0,
        total_customers: salesStats.total_customers || 0,
        total_products: salesStats.total_products || 0,
        inventory_products: invStats.total_products || 0,
        total_stock: invStats.total_stock || 0,
        inventory_alert: invStats.alert_count || 0,
        orders_today: todaySales.orders || 0,
        amount_today: parseFloat(todaySales.amount) || 0,
        orders_month: monthSales.orders || 0,
        amount_month: parseFloat(monthSales.amount) || 0,
        week_growth: parseFloat(weekGrowth)
      }
    })
  } catch (err) {
    console.error('Dashboard error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 2. SKU销量排行 ====================
router.get('/sales-by-sku', async (req, res) => {
  try {
    const { limit = 50, offset = 0 } = req.query
    
    const sql = `
      SELECT 
        p.id as product_id,
        p.name as product_name,
        p.sku as product_code,
        p.category,
        COUNT(o.id) as order_count,
        COALESCE(SUM(o.sale_price), 0) as total_amount,
        AVG(o.sale_price) as avg_price
      FROM sales_orders o
      LEFT JOIN products p ON o.product_id = p.id
      WHERE o.status = 'completed'
      GROUP BY p.id, p.name, p.sku, p.category
      ORDER BY total_amount DESC
      LIMIT ? OFFSET ?
    `
    
    const result = await query(sql, [parseInt(limit), parseInt(offset)])
    const [countResult] = await query('SELECT COUNT(DISTINCT product_id) as total FROM sales_orders WHERE status = \'completed\'')
    
    res.json({
      code: 0,
      data: result,
      total: countResult.total
    })
  } catch (err) {
    console.error('Sales by SKU error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 3. 客户销量排行 ====================
router.get('/sales-by-customer', async (req, res) => {
  try {
    const { limit = 50 } = req.query
    
    const sql = `
      SELECT 
        buyer_phone,
        COUNT(*) as order_count,
        COALESCE(SUM(sale_price), 0) as total_amount,
        AVG(sale_price) as avg_order,
        COUNT(DISTINCT product_id) as product_count,
        MIN(created_at) as first_order,
        MAX(created_at) as last_order
      FROM sales_orders 
      WHERE status = 'completed'
      GROUP BY buyer_phone
      ORDER BY total_amount DESC
      LIMIT ?
    `
    
    const result = await query(sql, [parseInt(limit)])
    
    result.forEach(c => {
      const days = c.last_order ? Math.floor((Date.now() - new Date(c.last_order)) / 86400000) : 999
      c.R = days <= 7 ? 5 : days <= 30 ? 4 : days <= 90 ? 3 : 2
      c.F = c.order_count >= 10 ? 5 : c.order_count >= 5 ? 4 : 3
      c.M = c.total_amount >= 10000 ? 5 : c.total_amount >= 5000 ? 4 : 3
      c.rfm_score = c.R + c.F + c.M
    })
    
    res.json({ code: 0, data: result })
  } catch (err) {
    console.error('Customer analysis error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 4. 时间趋势 ====================
router.get('/sales-by-time', async (req, res) => {
  try {
    const { groupBy = 'day' } = req.query
    
    let dateGroup
    switch(groupBy) {
      case 'month': dateGroup = "DATE_FORMAT(created_at, '%Y-%m')"; break
      case 'week': dateGroup = "YEARWEEK(created_at, 1)"; break
      default: dateGroup = 'DATE(created_at)'
    }
    
    const sql = `
      SELECT 
        ${dateGroup} as date_key,
        COUNT(*) as order_count,
        COALESCE(SUM(sale_price), 0) as total_amount,
        COUNT(DISTINCT buyer_phone) as customer_count
      FROM sales_orders 
      WHERE status = 'completed'
      GROUP BY ${dateGroup}
      ORDER BY date_key DESC
      LIMIT 30
    `
    
    const result = await query(sql)
    res.json({ code: 0, data: result.reverse() })
  } catch (err) {
    console.error('Time trend error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 5. 分类分析 ====================
router.get('/sales-by-category', async (req, res) => {
  try {
    const sql = `
      SELECT 
        COALESCE(p.category, '未分类') as category,
        COUNT(*) as order_count,
        COALESCE(SUM(o.sale_price), 0) as total_amount,
        COUNT(DISTINCT o.buyer_phone) as customer_count
      FROM sales_orders o
      LEFT JOIN products p ON o.product_id = p.id
      WHERE o.status = 'completed'
      GROUP BY p.category
      ORDER BY total_amount DESC
    `
    
    const result = await query(sql)
    const total = result.reduce((sum, item) => sum + parseFloat(item.total_amount || 0), 0)
    result.forEach(item => {
      item.percentage = total > 0 ? (parseFloat(item.total_amount || 0) / total * 100).toFixed(1) : 0
    })
    
    res.json({ code: 0, data: result, total_amount: total })
  } catch (err) {
    console.error('Category error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 6. 爆款分析 ====================
router.get('/hot-products', async (req, res) => {
  try {
    const { limit = 20 } = req.query
    
    const sql = `
      SELECT 
        p.id as product_id,
        p.name as product_name,
        p.sku as product_code,
        p.category,
        p.stock,
        COUNT(o.id) as order_count,
        COALESCE(SUM(o.sale_price), 0) as total_amount
      FROM sales_orders o
      LEFT JOIN products p ON o.product_id = p.id
      WHERE o.status = 'completed'
      GROUP BY p.id, p.name, p.sku, p.category, p.stock
      HAVING order_count >= 3
      ORDER BY order_count DESC
      LIMIT ?
    `
    
    const result = await query(sql, [parseInt(limit)])
    result.forEach(item => {
      item.hot_level = item.order_count >= 50 ? '🔥极爆' : item.order_count >= 20 ? '⭐热门' : '📈畅销'
      item.need_replenish = item.stock < item.order_count
    })
    
    res.json({ code: 0, data: result })
  } catch (err) {
    console.error('Hot products error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 7. 门店分布 ====================
router.get('/sales-by-branch', async (req, res) => {
  try {
    const sql = `
      SELECT 
        buyer_phone,
        COUNT(*) as order_count,
        COALESCE(SUM(sale_price), 0) as total_amount,
        COUNT(DISTINCT product_id) as sku_count
      FROM sales_orders 
      WHERE status = 'completed'
      GROUP BY buyer_phone
      ORDER BY total_amount DESC
      LIMIT 50
    `
    
    const result = await query(sql)
    res.json({ code: 0, data: result })
  } catch (err) {
    console.error('Branch analysis error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 8. 库存预警 ====================
router.get('/inventory-alert', async (req, res) => {
  try {
    const sql = `
      SELECT 
        id, name, sku, category,
        stock, safe_stock, purchase_price, sale_price,
        CASE 
          WHEN stock <= 0 THEN '🔴缺货'
          WHEN stock < safe_stock THEN '🟡低于安全库存'
          ELSE '🟢正常'
        END as alert_level
      FROM products 
      WHERE status = 'active'
        AND (stock <= safe_stock OR stock <= 5)
      ORDER BY stock ASC
    `
    
    const result = await query(sql)
    
    res.json({
      code: 0,
      data: result,
      summary: {
        total: result.length,
        out_of_stock: result.filter(r => r.stock <= 0).length,
        low_stock: result.filter(r => r.stock > 0).length
      }
    })
  } catch (err) {
    console.error('Inventory alert error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 9. 补货建议 ====================
router.get('/replenish-suggest', async (req, res) => {
  try {
    const sql = `
      SELECT 
        p.id, p.name, p.sku, p.category,
        p.stock, p.safe_stock, p.purchase_price, p.sale_price,
        COALESCE(s.order_count, 0) as order_count_30d,
        CASE 
          WHEN p.stock <= 0 THEN p.safe_stock * 3
          ELSE GREATEST(0, p.safe_stock * 2 - p.stock)
        END as suggest_quantity,
        CASE 
          WHEN p.stock <= 0 THEN p.safe_stock * 3 * COALESCE(p.purchase_price, 0)
          ELSE GREATEST(0, p.safe_stock * 2 - p.stock) * COALESCE(p.purchase_price, 0)
        END as suggest_amount
      FROM products p
      LEFT JOIN (
        SELECT product_id, COUNT(*) as order_count
        FROM sales_orders WHERE status = 'completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY product_id
      ) s ON p.id = s.product_id
      WHERE p.status = 'active' AND p.stock < p.safe_stock * 2
      ORDER BY suggest_quantity DESC
      LIMIT 50
    `
    
    const result = await query(sql)
    const total = result.reduce((sum, item) => sum + parseFloat(item.suggest_amount || 0), 0)
    
    res.json({
      code: 0,
      data: result,
      summary: { product_count: result.length, total_suggest_amount: total.toFixed(2) }
    })
  } catch (err) {
    console.error('Replenish error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 10. RFM分析 ====================
router.get('/rfm-analysis', async (req, res) => {
  try {
    const sql = `
      SELECT 
        buyer_phone,
        MAX(created_at) as last_order,
        COUNT(*) as order_count,
        COALESCE(SUM(sale_price), 0) as total_amount,
        DATEDIFF(CURDATE(), MAX(created_at)) as days_since_last
      FROM sales_orders WHERE status = 'completed'
      GROUP BY buyer_phone
    `
    
    const result = await query(sql)
    
    const rMax = Math.max(...result.map(r => r.days_since_last)) || 1
    const fMax = Math.max(...result.map(r => r.order_count)) || 1
    const mMax = Math.max(...result.map(r => r.total_amount)) || 1
    
    result.forEach(c => {
      c.R = Math.max(1, Math.min(5, Math.ceil((1 - c.days_since_last / rMax) * 5)))
      c.F = Math.max(1, Math.min(5, Math.ceil((c.order_count / fMax) * 5)))
      c.M = Math.max(1, Math.min(5, Math.ceil((c.total_amount / mMax) * 5)))
      c.RFM = c.R * 100 + c.F * 10 + c.M
      
      if (c.R >= 4 && c.F >= 4 && c.M >= 4) { c.level = '💎重要价值'; c.action = 'VIP服务' }
      else if (c.M >= 4) { c.level = '💰重要客户'; c.action = '提升频次' }
      else if (c.F >= 4) { c.level = '🔥活跃客户'; c.action = '提升客单价' }
      else if (c.R >= 4) { c.level = '🆕新客户'; c.action = '培养忠诚' }
      else if (c.days_since_last > 60) { c.level = '⚠️流失风险'; c.action = '唤醒营销' }
      else { c.level = '📉一般客户'; c.action = '持续维护' }
    })
    
    const stats = {}
    result.forEach(c => { stats[c.level] = (stats[c.level] || 0) + 1 })
    
    res.json({ code: 0, data: result, level_stats: stats })
  } catch (err) {
    console.error('RFM error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 11. 滞销预警 ====================
router.get('/slow-products', async (req, res) => {
  try {
    const sql = `
      SELECT 
        p.id, p.name, p.sku, p.category,
        p.stock, p.sale_price,
        DATEDIFF(CURDATE(), COALESCE(s.last_date, p.created_at)) as days_no_sale
      FROM products p
      LEFT JOIN (
        SELECT product_id, MAX(created_at) as last_date
        FROM sales_orders WHERE status = 'completed' GROUP BY product_id
      ) s ON p.id = s.product_id
      WHERE p.status = 'active' AND p.stock > 0
      ORDER BY days_no_sale DESC
      LIMIT 50
    `
    
    const result = await query(sql)
    result.forEach(item => {
      if (item.days_no_sale >= 90) { item.slow_level = '🔴严重'; item.action = '促销/下架' }
      else if (item.days_no_sale >= 60) { item.slow_level = '🟠滞销'; item.action = '加大推广' }
      else if (item.days_no_sale >= 30) { item.slow_level = '🟡需关注'; item.action = '观察中' }
      else { item.slow_level = '🟢正常'; item.action = '无需操作' }
    })
    
    res.json({ code: 0, data: result })
  } catch (err) {
    console.error('Slow products error:', err)
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 12. 商品列表 ====================
router.get('/products', async (req, res) => {
  try {
    const { limit = 100, offset = 0 } = req.query
    const sql = 'SELECT * FROM products WHERE status = \'active\' ORDER BY id DESC LIMIT ? OFFSET ?'
    const result = await query(sql, [parseInt(limit), parseInt(offset)])
    const [count] = await query('SELECT COUNT(*) as total FROM products WHERE status = \'active\'')
    res.json({ code: 0, data: result, total: count.total })
  } catch (err) {
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 13. 导出报表 ====================
router.get('/export-report', async (req, res) => {
  try {
    const { type = 'sales', format = 'json' } = req.query
    
    let data, filename
    if (type === 'sales') {
      data = await query(`
        SELECT o.order_no, p.name as product, o.sale_price, o.buyer_phone, o.created_at
        FROM sales_orders o LEFT JOIN products p ON o.product_id = p.id
        WHERE o.status = 'completed' ORDER BY o.created_at DESC LIMIT 1000
      `)
      filename = `sales_report_${Date.now()}.csv`
    } else {
      data = await query('SELECT * FROM products WHERE status = \'active\' ORDER BY stock ASC')
      filename = `inventory_${Date.now()}.csv`
    }
    
    if (format === 'csv') {
      if (!data.length) return res.send('No data')
      const headers = Object.keys(data[0])
      const csv = [headers.join(','), ...data.map(r => headers.map(h => JSON.stringify(r[h] || '')).join(','))].join('\n')
      res.setHeader('Content-Type', 'text/csv')
      res.setHeader('Content-Disposition', `attachment; filename=${filename}`)
      res.send(csv)
    } else {
      res.json({ code: 0, data })
    }
  } catch (err) {
    res.json({ code: 500, message: err.message })
  }
})

// ==================== 14. 库存健康度 ====================
router.get('/inventory-health', async (req, res) => {
  try {
    const [stats] = await query(`
      SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN stock > 0 AND stock < safe_stock THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN stock >= safe_stock THEN 1 ELSE 0 END) as healthy,
        SUM(stock * purchase_price) as total_value
      FROM products WHERE status = 'active'
    `)
    res.json({
      code: 0,
      data: {
        ...stats,
        health_rate: stats.total > 0 ? ((stats.healthy || 0) / stats.total * 100).toFixed(1) : 0
      }
    })
  } catch (err) {
    res.json({ code: 500, message: err.message })
  }
})

export default router
