import { Router } from 'express'
import { pool } from '../db/connection.js'
import cache from '../utils/cache.js'
import { requirePermission } from '../middleware/rbac.js'
import { PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

router.get('/stats', requirePermission(PERMISSIONS.DASHBOARD_STATS), async (req, res, next) => {
  // 尝试从缓存获取
  const cacheKey = 'dashboard:stats'
  const cached = await cache.get(cacheKey)
  if (cached) {
    return res.json({ ...cached, _cached: true })
  }

  try {
    // Execute all queries in parallel for better performance
    const [
      [[{ totalStock }]],
      [[{ todayInbound }]],
      [[{ todayOutbound }]],
      [[{ alertCount }]],
      [[{ pendingApprovals }]],
      [[{ todaySales }]],
      [[{ monthSales }]],
      [[{ totalProducts }]],
      [[{ totalQrcodes }]]
    ] = await Promise.all([
      // 1. Total stock value (warehouse_stock quantity * products.purchase_price)
      pool.query(`
        SELECT COALESCE(SUM(ws.quantity * p.purchase_price), 0) as totalStock
        FROM warehouse_stock ws
        LEFT JOIN products p ON ws.product_id = p.id
        WHERE p.purchase_price IS NOT NULL
      `),

      // 2. Today's inbound quantity (优化：使用范围查询，不用DATE函数)
      pool.query(`
        SELECT COALESCE(SUM(total_qty), 0) as todayInbound
        FROM inbound_records
        WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY AND status = 'completed'
      `),

      // 3. Today's outbound quantity (优化：使用范围查询，不用DATE函数)
      pool.query(`
        SELECT COALESCE(SUM(total_qty), 0) as todayOutbound
        FROM outbound_records
        WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY AND status = 'completed'
      `),

      // 4. Stock alert count (unhandled alerts)
      pool.query(`
        SELECT COUNT(*) as alertCount
        FROM stock_alerts
        WHERE handled = FALSE
      `),

      // 5. Pending approvals count
      pool.query(`
        SELECT COUNT(*) as pendingApprovals
        FROM approvals
        WHERE status = 'pending'
      `),

      // 6. Today's sales revenue (优化：使用范围查询)
      pool.query(`
        SELECT COALESCE(SUM(sale_price), 0) as todaySales
        FROM retail_records
        WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY
      `),

      // 7. This month's sales revenue (优化：使用范围查询)
      pool.query(`
        SELECT COALESCE(SUM(sale_price), 0) as monthSales
        FROM retail_records
        WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') 
          AND created_at < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')
      `),

      // 8. Total products count
      pool.query(`
        SELECT COUNT(*) as totalProducts
        FROM products
        WHERE status = 'active'
      `),

      // 9. Total qrcodes count
      pool.query(`
        SELECT COUNT(*) as totalQrcodes
        FROM qrcodes
      `)
    ])

    const result = {
      code: 0,
      data: {
        totalStock: parseFloat(totalStock) || 0,
        todayInbound: parseInt(todayInbound) || 0,
        todayOutbound: parseInt(todayOutbound) || 0,
        alertCount: parseInt(alertCount) || 0,
        pendingApprovals: parseInt(pendingApprovals) || 0,
        todaySales: parseFloat(todaySales) || 0,
        monthSales: parseFloat(monthSales) || 0,
        totalProducts: parseInt(totalProducts) || 0,
        totalQrcodes: parseInt(totalQrcodes) || 0
      },
      message: 'ok'
    }

    // 缓存5分钟
    await cache.set(cacheKey, result, 300)
    
    res.json(result)
  } catch (err) { next(err) }
})

export default router
