/**
 * AI 课堂 - 多轮 ReAct 版 chat endpoint
 *
 * 在 6/19 ai-class.js 单轮 tool calling 基础上改造：
 * - 多轮 ReAct 循环（最多 5 轮）
 * - 失败重试 + 反思机制
 * - 工具调用全审计
 * - 高风险工具二次确认（write 类）
 *
 * Endpoint: POST /api/ai-class/chat-react
 * Body: { message, session_id, confirm?: true }
 */

import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// ============================================================
// 配置常量
// ============================================================
const MAX_REACT_ROUNDS = 5           // 最多 5 轮 tool calling
const TOOL_TIMEOUT_MS = 30000        // 单次 LLM 调用超时
const HIGH_RISK_TOOLS = new Set([    // 需要二次确认的工具
  'send_notification',
  'create_approval',
  'update_user_role',
  'delete_order',
  'process_refund',
  'cancel_preorder'
])

// ============================================================
// 工具执行（独立函数，从 ai-class.js 提取 + 扩展）
// ============================================================
async function executeTool(fnName, fnArgs, userCtx) {
  const { userId, userRole, userPermissions } = userCtx
  let result = { success: false, data: null, error: null, requiresConfirm: false }

  try {
    switch (fnName) {
      // ==================== 原有 10 个工具（从 ai-class.js 移植）====================
      case 'get_products': {
        let query = 'SELECT id, name, category, sale_price, purchase_price, unit, stock, alert_stock, status FROM products WHERE 1=1'
        const params = []
        if (fnArgs.keyword) {
          query += ' AND (name LIKE ? OR sku LIKE ? OR spec LIKE ?)'
          const kw = `%${fnArgs.keyword}%`
          params.push(kw, kw, kw)
        }
        if (fnArgs.category) {
          query += ' AND (category = ? OR category_id IN (SELECT id FROM categories WHERE name = ?))'
          params.push(fnArgs.category, fnArgs.category)
        }
        query += ' ORDER BY stock ASC LIMIT 10'
        const [rows] = await pool.query(query, params)
        result = { success: true, data: rows }
        break
      }

      case 'get_inventory': {
        const [rows] = await pool.query(
          `SELECT ws.product_id, p.name as product_name, ws.sku_id, ws.warehouse_id,
                  w.name as warehouse_name, ws.quantity, ws.location
           FROM warehouse_stock ws
           LEFT JOIN products p ON ws.product_id = p.id
           LEFT JOIN warehouses w ON ws.warehouse_id = w.id
           WHERE ws.product_id = ? AND ws.quantity > 0
           ORDER BY ws.warehouse_id, ws.sku_id LIMIT 20`,
          [fnArgs.product_id]
        )
        const totalRow = await pool.query(
          'SELECT COALESCE(SUM(quantity),0) as total FROM warehouse_stock WHERE product_id=?',
          [fnArgs.product_id]
        )
        result = {
          success: true,
          data: {
            product_id: fnArgs.product_id,
            total_quantity: totalRow[0][0]?.total || 0,
            details: rows
          }
        }
        break
      }

      case 'get_orders': {
        const queryUserId = fnArgs.user_id || userId
        if (!['admin', 'manager'].includes(userRole) && queryUserId !== userId) {
          result = { success: false, error: '无权查询其他用户的订单' }
          break
        }
        let where = ''
        const params = []
        if (fnArgs.status) { where = 'WHERE status = ?'; params.push(fnArgs.status) }
        if (queryUserId) {
          where += where ? ' AND member_id = ?' : 'WHERE member_id = ?'
          params.push(queryUserId)
        }
        const sql = `SELECT id, order_no, member_name, member_phone, total_amount, pay_amount, pay_type, status, paid_at, shipped_at, completed_at, created_at FROM orders ${where} ORDER BY created_at DESC LIMIT 20`
        const [rows] = await pool.query(sql, params)
        result = { success: true, data: { count: rows.length, list: rows } }
        break
      }

      case 'get_user_info': {
        const [rows] = await pool.query(
          'SELECT id, name, phone, role, department, status, permissions, hire_date, employee_code, title FROM users WHERE id = ?',
          [fnArgs.user_id]
        )
        const user = rows[0]
        if (user) {
          try { user.permissions = JSON.parse(user.permissions || '[]') } catch { user.permissions = [] }
          delete user.password
        }
        result = { success: true, data: user || { message: '未找到用户信息' } }
        break
      }

      case 'get_attendance': {
        const targetUserId = fnArgs.user_id || userId
        let dateCondition = "date = CURDATE()"
        if (fnArgs.date_range === 'month') dateCondition = "date BETWEEN DATE_FORMAT(NOW(),'%Y-%m-01') AND LAST_DAY(NOW())"
        else if (fnArgs.date_range === 'all') dateCondition = '1=1'
        const [rows] = await pool.query(
          `SELECT id, date, clock_in, clock_out, status, late_minutes, early_minutes, overtime_hours, location, check_in_time, check_out_time
           FROM attendance WHERE user_id = ? AND ${dateCondition} ORDER BY date DESC LIMIT 20`,
          [targetUserId]
        )
        result = { success: true, data: rows }
        break
      }

      case 'search_knowledge': {
        const [rows] = await pool.query(
          'SELECT title, content, doc_type FROM ai_class_knowledge WHERE title LIKE ? OR content LIKE ? LIMIT 5',
          [`%${fnArgs.keyword}%`, `%${fnArgs.keyword}%`]
        )
        result = { success: true, data: rows }
        break
      }

      case 'web_search': {
        // 简化版：从 ai-class.js 的 tavily + ddg 函数体提取
        const tavilyKey = process.env.TAVILY_API_KEY
        if (tavilyKey) {
          try {
            const r = await fetch('https://api.tavily.com/search', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ api_key: tavilyKey, query: fnArgs.query, max_results: fnArgs.max_results || 5 })
            })
            const d = await r.json()
            result = { success: true, data: { source: 'tavily', results: d.results || [] } }
            break
          } catch (e) { /* fallback */ }
        }
        result = { success: false, error: 'web_search 需要配置 TAVILY_API_KEY' }
        break
      }

      case 'get_sales_report': {
        if (!['admin', 'manager'].includes(userRole)) {
          result = { success: false, error: '无权访问销售报表' }
          break
        }
        const dateRange = fnArgs.date_range || 'month'
        let dateCond = "DATE(created_at) = CURDATE()"
        if (dateRange === 'month') dateCond = "created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        else if (dateRange === 'quarter') dateCond = "created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)"
        else if (dateRange === 'year') dateCond = "created_at >= DATE_FORMAT(NOW(), '%Y-01-01')"
        const [rows] = await pool.query(
          `SELECT COUNT(*) as order_count, COALESCE(SUM(total_amount),0) as total_sales, COUNT(DISTINCT member_id) as customer_count
           FROM orders WHERE status != 'cancelled' AND ${dateCond}`,
          []
        )
        result = { success: true, data: { date_range: dateRange, report: rows[0] } }
        break
      }

      case 'get_inventory_alert': {
        const [rows] = await pool.query(
          `SELECT p.id, p.name, p.category, p.alert_stock,
                  COALESCE(SUM(ws.quantity), 0) as quantity
           FROM products p
           LEFT JOIN warehouse_stock ws ON ws.product_id = p.id
           WHERE p.status = 'active' OR p.status IS NULL
           GROUP BY p.id, p.name, p.category, p.alert_stock
           HAVING quantity < COALESCE(p.alert_stock, 10)
           ORDER BY quantity ASC LIMIT 20`,
          []
        )
        result = { success: true, data: { alerts: rows, threshold: '低于安全库存' } }
        break
      }

      // ==================== 新增 7 个工具（B 业务工具）====================

      // 【财】毛利分析 — 2026-08-07 波哥要求按"人/事/物/财"补财的 AI 化
      // 数据源: sales_orders (有 original_price 成本 + sale_price 售价)
      // 设计: 任何角色都可查 (毛利透明 = 数字化经营基础), group_by 灵活
      case 'get_profit_analysis': {
        const period = fnArgs.period || 'month'
        const groupBy = fnArgs.group_by || 'product'

        // 1) 解析时间条件
        let dateCond = "paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"  // month 默认
        if (period === 'today') dateCond = 'DATE(paid_at) = CURDATE()'
        else if (period === 'quarter') dateCond = "paid_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)"
        else if (period === 'year') dateCond = "paid_at >= DATE_FORMAT(NOW(), '%Y-01-01')"
        else if (period === '7days') dateCond = 'paid_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        else if (period === '30days') dateCond = 'paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'

        // 2) 总体毛利
        const [totals] = await pool.query(
          `SELECT
             COUNT(*) as order_count,
             COUNT(DISTINCT product_id) as unique_products,
             COUNT(DISTINCT buyer_id) as unique_customers,
             COALESCE(SUM(original_price), 0) as total_cost,
             COALESCE(SUM(sale_price), 0) as total_revenue,
             COALESCE(SUM(sale_price - original_price), 0) as total_profit,
             ROUND(COALESCE(SUM(sale_price - original_price), 0) / NULLIF(SUM(sale_price), 0) * 100, 2) as profit_margin_pct
           FROM sales_orders
           WHERE status IN ('completed', 'paid') AND paid_at IS NOT NULL AND ${dateCond}`,
          []
        )

        // 3) 维度聚合 (动态 groupBy)
        let groupSelect, groupLabel
        if (groupBy === 'product') {
          groupSelect = 'product_id, product_name'
          groupLabel = 'product'
        } else if (groupBy === 'customer') {
          groupSelect = 'buyer_id, buyer_phone'
          groupLabel = 'customer'
        } else {
          // month
          groupSelect = "DATE_FORMAT(paid_at, '%Y-%m') AS period_bucket"
          groupLabel = 'period'
        }

        const [topRows] = await pool.query(
          `SELECT ${groupSelect},
             COUNT(*) as orders,
             COALESCE(SUM(sale_price), 0) as revenue,
             COALESCE(SUM(sale_price - original_price), 0) as profit,
             ROUND(COALESCE(SUM(sale_price - original_price), 0) / NULLIF(SUM(sale_price), 0) * 100, 2) as margin_pct
           FROM sales_orders
           WHERE status IN ('completed', 'paid') AND paid_at IS NOT NULL AND ${dateCond}
           GROUP BY ${groupSelect.replace(/,\s*buyer_phone|,\s*product_name/, '')}
           ORDER BY profit DESC
           LIMIT 10`,
          []
        )

        // 4) 算爆款/滞销/高利润客户
        const topProfit = topRows.slice(0, 5)            // 利润 Top5
        const bottomProfit = topRows.slice(-5).reverse()  // 亏损/低利润 Bottom5

        result = {
          success: true,
          data: {
            period,
            group_by: groupBy,
            summary: totals[0],
            top_profit: topProfit,
            bottom_profit: bottomProfit,
            insight: `本期毛利率 ${totals[0].profit_margin_pct}%${totals[0].profit_margin_pct < 0 ? '【亏损警报】' : totals[0].profit_margin_pct > 30 ? '【高利润】' : ''}`
          }
        }
        break
      }

      // 【人】工人画像 — 2026-08-07 波哥要求按"人/事/物/财"补人的 AI 化
      // 数据源: users (worker/foreman/salesperson/shopkeeper/store_manager 等角色)
      //         attendance (考勤统计) + work_logs (日报聚合) + h5_users (移动端绑定)
      // 设计: 任何角色都可查 (工人画像透明 = 数字化管理基础)
      case 'get_worker_profile': {
        const days = Math.min(Math.max(fnArgs.days || 30, 7), 365)
        const workerId = fnArgs.worker_id

        // 1) 单人完整画像
        if (workerId) {
          const [profileRows] = await pool.query(
            `SELECT u.id, u.name, u.phone, u.role, u.department, u.employee_code,
                    u.hire_date, u.last_login_at, u.can_oa_checkin, u.require_attendance,
                    u.require_worklog, u.title, u.avatar, u.status, u.server_profile_id,
                    u.h5_user_id, h.name AS h5_name, h.level AS h5_level, h.role AS h5_role
             FROM users u
             LEFT JOIN h5_users h ON h.id = u.h5_user_id
             WHERE u.id = ? LIMIT 1`,
            [workerId]
          )
          if (profileRows.length === 0) {
            result = { success: false, error: `未找到 worker_id=${workerId} 的用户` }
            break
          }

          const [attRows] = await pool.query(
            `SELECT COUNT(*) AS attendance_days,
                    SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) AS late_days,
                    SUM(CASE WHEN status='early' THEN 1 ELSE 0 END) AS early_days,
                    SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) AS absent_days,
                    SUM(CASE WHEN status='leave' THEN 1 ELSE 0 END) AS leave_days,
                    SUM(late_minutes) AS total_late_minutes,
                    SUM(overtime_hours) AS total_overtime_hours,
                    MIN(clock_in) AS earliest_in,
                    MAX(clock_in) AS latest_in
             FROM attendance WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)`,
            [workerId, days]
          )

          const [logRows] = await pool.query(
            `SELECT COUNT(*) AS total_logs,
                    SUM(CASE WHEN log_type='work' THEN 1 ELSE 0 END) AS work_logs,
                    SUM(CASE WHEN log_type='complaint' THEN 1 ELSE 0 END) AS complaints,
                    SUM(CASE WHEN log_type='share' THEN 1 ELSE 0 END) AS shares,
                    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved_logs
             FROM work_logs WHERE user_id = ? AND submit_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)`,
            [workerId, days]
          )

          const p = profileRows[0]
          const a = attRows[0]
          const l = logRows[0]
          const lateRate = a.attendance_days > 0 ? (a.late_days / a.attendance_days * 100).toFixed(1) : 0

          result = {
            success: true,
            data: {
              profile: {
                id: p.id, name: p.name, phone: p.phone, role: p.role, department: p.department,
                employee_code: p.employee_code, hire_date: p.hire_date, title: p.title,
                status: p.status, server_profile_id: p.server_profile_id,
                last_login_at: p.last_login_at,
                has_avatar: !!p.avatar,
                can_checkin: !!p.can_oa_checkin,
                require_attendance: !!p.require_attendance,
                require_worklog: !!p.require_worklog
              },
              h5_binding: p.h5_user_id ? {
                h5_user_id: p.h5_user_id, h5_name: p.h5_name,
                h5_role: p.h5_role, h5_level: p.h5_level
              } : null,
              attendance: {
                period_days: days,
                attendance_days: a.attendance_days,
                late_days: a.late_days,
                early_days: a.early_days,
                absent_days: a.absent_days,
                leave_days: a.leave_days,
                late_rate_pct: lateRate,
                total_late_minutes: a.total_late_minutes,
                total_overtime_hours: a.total_overtime_hours,
                earliest_clock_in: a.earliest_in,
                latest_clock_in: a.latest_in
              },
              work_output: {
                period_days: days,
                total_logs: l.total_logs,
                work_logs: l.work_logs,
                complaints: l.complaints,
                shares: l.shares,
                approved_logs: l.approved_logs
              },
              insight: `出勤 ${a.attendance_days} 天,迟到 ${a.late_days} 次 (${lateRate}%),日报 ${l.work_logs} 条,投诉 ${l.complaints} 条`
            }
          }
          break
        }

        // 2) Top10 工人画像摘要
        const [topWorkers] = await pool.query(
          `SELECT u.id, u.name, u.role, u.department, u.phone, u.h5_user_id,
                  COALESCE(att.attendance_days, 0) AS attendance_days,
                  COALESCE(att.late_days, 0) AS late_days,
                  COALESCE(att.absent_days, 0) AS absent_days,
                  COALESCE(att.total_overtime_hours, 0) AS overtime_hours,
                  COALESCE(wl.total_logs, 0) AS work_logs,
                  COALESCE(wl.complaints, 0) AS complaints
           FROM users u
           LEFT JOIN (
             SELECT user_id,
                    COUNT(*) AS attendance_days,
                    SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) AS late_days,
                    SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) AS absent_days,
                    SUM(overtime_hours) AS total_overtime_hours
             FROM attendance WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY user_id
           ) att ON att.user_id = u.id
           LEFT JOIN (
             SELECT user_id,
                    COUNT(*) AS total_logs,
                    SUM(CASE WHEN log_type='complaint' THEN 1 ELSE 0 END) AS complaints
             FROM work_logs WHERE submit_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY user_id
           ) wl ON wl.user_id = u.id
           WHERE u.role IN ('worker','foreman','salesperson','shopkeeper','store_manager','admin','manager','operator')
             AND u.status = 'active'
           ORDER BY att.attendance_days DESC
           LIMIT 10`,
          [days, days]
        )

        result = {
          success: true,
          data: {
            period_days: days,
            count: topWorkers.length,
            workers: topWorkers,
            insight: `统计近 ${days} 天,共 ${topWorkers.length} 名活跃员工。最多出勤 ${topWorkers[0]?.attendance_days || 0} 天 (${topWorkers[0]?.name || '无'})`
          }
        }
        break
      }

      // 【财】财务对账 — 2026-08-07 波哥要求按"人/事/物/财"补财的 AI 化
      // 数据源: imported_excel_items (7266 条, 2 真店 26/47 + 菲律宾 SM 系列)
      // 4 维对账: 一致性 / 门店对比 / SKU 分布 / 价格段异常
      case 'get_reconcile_diff': {
        const focus = fnArgs.focus || 'all'

        // 1) 全局概览
        const [overview] = await pool.query(
          `SELECT
             COUNT(*) total_records,
             COUNT(DISTINCT store_name) store_count,
             COUNT(DISTINCT product_name) product_count,
             COUNT(DISTINCT sku) sku_count,
             SUM(CASE WHEN amount > 0 THEN 1 ELSE 0 END) records_with_amount,
             SUM(CASE WHEN sale_date IS NOT NULL THEN 1 ELSE 0 END) records_with_date,
             SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) total_revenue,
             SUM(CASE WHEN amount > 0 THEN quantity ELSE 0 END) total_quantity
           FROM imported_excel_items`
        )

        const ov = overview[0]

        // 2) 内部一致性 (qty * unit_price == amount)
        let consistency = null
        if (focus === 'all' || focus === 'consistency') {
          const [consRows] = await pool.query(
            `SELECT
               COUNT(*) checked_records,
               SUM(CASE WHEN ABS(quantity * unit_price - amount) <= 0.01 THEN 1 ELSE 0 END) consistent,
               SUM(CASE WHEN ABS(quantity * unit_price - amount) > 0.01 THEN 1 ELSE 0 END) inconsistent
             FROM imported_excel_items
             WHERE quantity > 0 AND unit_price > 0 AND amount > 0`
          )
          const c = consRows[0]
          consistency = {
            checked_records: c.checked_records,
            consistent: c.consistent,
            inconsistent: c.inconsistent,
            consistency_rate_pct: c.checked_records > 0 ? (c.consistent / c.checked_records * 100).toFixed(2) : 0
          }
        }

        // 3) 门店对比
        let stores = []
        if (focus === 'all' || focus === 'store') {
          const [storeRows] = await pool.query(
            `SELECT store_name,
                    COUNT(*) records,
                    SUM(quantity) total_qty,
                    SUM(amount) total_amt,
                    ROUND(AVG(NULLIF(unit_price, 0)), 2) avg_price,
                    MAX(unit_price) max_price,
                    MIN(NULLIF(unit_price, 0)) min_price
             FROM imported_excel_items
             WHERE amount > 0
             GROUP BY store_name
             ORDER BY total_amt DESC`
          )
          stores = storeRows
        }

        // 4) Top SKU 分布
        let topSkus = []
        if (focus === 'all' || focus === 'sku') {
          const [skuRows] = await pool.query(
            `SELECT COALESCE(NULLIF(product_name,''), sku) AS product_name,
                    COUNT(*) records,
                    SUM(quantity) total_qty,
                    SUM(amount) total_amt,
                    ROUND(AVG(NULLIF(unit_price, 0)), 2) avg_price
             FROM imported_excel_items
             WHERE amount > 0
             GROUP BY COALESCE(NULLIF(product_name,''), sku)
             ORDER BY total_amt DESC
             LIMIT 10`
          )
          topSkus = skuRows
        }

        // 5) 价格段异常 (按 store_name 维度)
        let priceAnomaly = null
        if (focus === 'all' || focus === 'price') {
          const [priceRows] = await pool.query(
            `SELECT store_name,
                    ROUND(AVG(unit_price), 2) AS avg_price,
                    ROUND(MIN(unit_price), 2) AS min_price,
                    ROUND(MAX(unit_price), 2) AS max_price,
                    ROUND(STDDEV(unit_price), 2) AS price_stddev,
                    ROUND((MAX(unit_price) - MIN(unit_price)) / NULLIF(AVG(unit_price), 0) * 100, 2) AS price_range_pct
             FROM imported_excel_items
             WHERE unit_price > 0 AND amount > 0
             GROUP BY store_name
             HAVING COUNT(*) >= 5
             ORDER BY price_range_pct DESC`
          )
          // 找最异常: 价格波动最大
          const mostAnomaly = priceRows[0]
          priceAnomaly = {
            store_breakdown: priceRows,
            most_volatile_store: mostAnomaly ? {
              store_name: mostAnomaly.store_name,
              avg_price: mostAnomaly.avg_price,
              price_range_pct: mostAnomaly.price_range_pct,
              insight: mostAnomaly.price_range_pct > 50 ? '【价格波动大,需关注】' : '【价格稳定】'
            } : null
          }
        }

        // 6) AI 综合洞察
        const insights = []
        if (ov.records_with_date < ov.total_records * 0.5) {
          insights.push(`⚠️ ${ov.total_records - ov.records_with_date} 条记录 sale_date 为 NULL (${((1 - ov.records_with_date/ov.total_records)*100).toFixed(1)}%),无法按日期对账`)
        }
        if (ov.records_with_amount < ov.total_records * 0.5) {
          insights.push(`⚠️ ${ov.total_records - ov.records_with_amount} 条记录 amount 为 0,占位数据,需核实`)
        }
        const realStores = stores.filter(s => s.total_amt > 0)
        if (realStores.length >= 2) {
          const maxStore = realStores[0]
          const minStore = realStores[realStores.length - 1]
          const avgDiff = ((maxStore.avg_price - minStore.avg_price) / minStore.avg_price * 100).toFixed(1)
          insights.push(`💰 真实销售 ${realStores.length} 家店,店均价格差 ${avgDiff}%,最高店 ${maxStore.store_name} (¥${maxStore.avg_price}) vs 最低 ${minStore.store_name} (¥${minStore.avg_price})`)
        }
        if (topSkus.length > 0) {
          insights.push(`📦 Top1 SKU: ${topSkus[0].product_name},贡献营收 ¥${topSkus[0].total_amt}`)
        }

        result = {
          success: true,
          data: {
            focus,
            overview: ov,
            consistency,
            stores,
            top_skus: topSkus,
            price_anomaly: priceAnomaly,
            insights,
            summary: `共 ${ov.total_records} 条记录,${ov.store_count} 家门店,${ov.product_count} 个产品,有效营收 ¥${ov.total_revenue},有效销量 ${ov.total_quantity}`
          }
        }
        break
      }

      // 【事】工单异常预警 — 2026-08-07 波哥要求按"人/事/物/财"补事的 AI 化
      // 数据源: work_logs (35 条聚合: work/complaint/share + draft/submitted/approved/rejected)
      // 6 维异常: 整体概览 / 投诉率 / 审批积压 / 用户异常 / 时间峰值 / 历史积压
      case 'get_workorder_anomaly': {
        const dimension = fnArgs.dimension || 'all'

        // 1) 整体概览
        const [overview] = await pool.query(
          `SELECT
             COUNT(*) total,
             SUM(CASE WHEN log_type='work' THEN 1 ELSE 0 END) work_count,
             SUM(CASE WHEN log_type='complaint' THEN 1 ELSE 0 END) complaint_count,
             SUM(CASE WHEN log_type='share' THEN 1 ELSE 0 END) share_count,
             SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) draft_count,
             SUM(CASE WHEN status='submitted' THEN 1 ELSE 0 END) pending_count,
             SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) approved_count,
             SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) rejected_count,
             MIN(submit_date) earliest_date,
             MAX(submit_date) latest_date,
             COUNT(DISTINCT user_id) user_count
           FROM work_logs`
        )

        const ov = overview[0]
        const complaintRate = ov.total > 0 ? (ov.complaint_count / ov.total * 100).toFixed(1) : 0
        const pendingRate = ov.total > 0 ? (ov.pending_count / ov.total * 100).toFixed(1) : 0

        // 2) 投诉异常
        let complaintAnomaly = null
        if (dimension === 'all' || dimension === 'complaint') {
          const [pendingComplaints] = await pool.query(
            `SELECT id, user_id, submit_date, status, LEFT(issues,80) issues
             FROM work_logs WHERE log_type='complaint' AND status='submitted'
             ORDER BY submit_date DESC`
          )
          complaintAnomaly = {
            total_complaints: ov.complaint_count,
            complaint_rate_pct: complaintRate,
            pending_complaints: pendingComplaints.length,
            level: complaintRate >= 30 ? '🔴 严重' : complaintRate >= 20 ? '🟠 警示' : '🟡 关注',
            details: pendingComplaints
          }
        }

        // 3) 审批积压异常
        let approvalAnomaly = null
        if (dimension === 'all' || dimension === 'approval') {
          const [oldestPending] = await pool.query(
            `SELECT id, user_id, submit_date, status, log_type, DATEDIFF(CURDATE(), submit_date) AS pending_days
             FROM work_logs WHERE status='submitted'
             ORDER BY submit_date ASC LIMIT 10`
          )
          approvalAnomaly = {
            pending_count: ov.pending_count,
            pending_rate_pct: pendingRate,
            level: pendingRate >= 80 ? '🔴 严重' : pendingRate >= 50 ? '🟠 警示' : '🟡 关注',
            oldest_pending: oldestPending,
            insight: pendingRate >= 80 ? '【审批积压严重,需要立即清理】' : pendingRate >= 50 ? '【审批积压中等】' : '【审批基本通畅】'
          }
        }

        // 4) 用户异常 (投诉率高 / 提交频繁)
        let userAnomaly = null
        if (dimension === 'all' || dimension === 'user') {
          const [userStats] = await pool.query(
            `SELECT user_id,
                    COUNT(*) total,
                    SUM(CASE WHEN log_type='complaint' THEN 1 ELSE 0 END) complaints,
                    SUM(CASE WHEN status='submitted' THEN 1 ELSE 0 END) pending,
                    ROUND(SUM(CASE WHEN log_type='complaint' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) AS complaint_rate_pct
             FROM work_logs
             GROUP BY user_id
             HAVING COUNT(*) >= 3
             ORDER BY complaints DESC, total DESC
             LIMIT 10`
          )
          const highComplaintUsers = userStats.filter(u => u.complaint_rate_pct >= 50)
          userAnomaly = {
            user_breakdown: userStats,
            high_complaint_users: highComplaintUsers,
            level: highComplaintUsers.length >= 2 ? '🟠 警示' : highComplaintUsers.length === 1 ? '🟡 关注' : '✅ 正常',
            insight: highComplaintUsers.length > 0
              ? `${highComplaintUsers.length} 名用户投诉率 ≥50%,需重点关注: ${highComplaintUsers.map(u => 'user_id=' + u.user_id + '(' + u.complaint_rate_pct + '%)').join(', ')}`
              : '所有用户投诉率均 <50%'
          }
        }

        // 5) 时间异常 (日峰值)
        let timeAnomaly = null
        if (dimension === 'all' || dimension === 'time') {
          const [dailyStats] = await pool.query(
            `SELECT submit_date,
                    COUNT(*) total,
                    SUM(CASE WHEN log_type='complaint' THEN 1 ELSE 0 END) complaints
             FROM work_logs
             GROUP BY submit_date
             ORDER BY total DESC LIMIT 10`
          )
          const avgDaily = ov.total / Math.max(dailyStats.length, 1)
          const peakDay = dailyStats[0]
          timeAnomaly = {
            daily_breakdown: dailyStats,
            avg_daily: avgDaily.toFixed(1),
            peak_day: peakDay ? {
              date: peakDay.submit_date,
              total: peakDay.total,
              complaints: peakDay.complaints,
              peak_multiplier: avgDaily > 0 ? (peakDay.total / avgDaily).toFixed(1) : 0
            } : null,
            level: peakDay && avgDaily > 0 && peakDay.total / avgDaily >= 5 ? '🟠 异常峰值' : '✅ 正常',
            insight: peakDay && avgDaily > 0
              ? `峰值日 ${peakDay.submit_date} 共 ${peakDay.total} 条,是日均 ${avgDaily.toFixed(1)} 的 ${(peakDay.total / avgDaily).toFixed(1)} 倍`
              : '日提交量平稳'
          }
        }

        // 6) 历史积压 (7 天前未审批)
        let overdueAnomaly = null
        if (dimension === 'all' || dimension === 'overdue') {
          const [overdueRows] = await pool.query(
            `SELECT id, user_id, submit_date, log_type, DATEDIFF(CURDATE(), submit_date) AS pending_days
             FROM work_logs
             WHERE status='submitted' AND submit_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             ORDER BY submit_date ASC`
          )
          overdueAnomaly = {
            overdue_count: overdueRows.length,
            level: overdueRows.length >= 5 ? '🔴 严重积压' : overdueRows.length >= 1 ? '🟠 需关注' : '✅ 无积压',
            details: overdueRows,
            insight: overdueRows.length > 0
              ? `${overdueRows.length} 条工单超过 7 天未审批,最长 ${overdueRows[overdueRows.length-1].pending_days} 天`
              : '7 天内无积压'
          }
        }

        // 7) AI 综合风险定级
        const riskItems = []
        if (complaintRate >= 20) riskItems.push({ level: '🔴', msg: `投诉率 ${complaintRate}% 超阈值 20%` })
        if (pendingRate >= 80) riskItems.push({ level: '🔴', msg: `审批积压 ${pendingRate}% 超阈值 80%` })
        if (overdueAnomaly && overdueAnomaly.overdue_count >= 5) riskItems.push({ level: '🔴', msg: `7 天以上积压 ${overdueAnomaly.overdue_count} 条` })
        if (userAnomaly && userAnomaly.high_complaint_users.length >= 2) riskItems.push({ level: '🟠', msg: `${userAnomaly.high_complaint_users.length} 名用户投诉率 ≥50%` })
        if (timeAnomaly && timeAnomaly.peak_day && timeAnomaly.avg_daily > 0 && timeAnomaly.peak_day.total / timeAnomaly.avg_daily >= 5) riskItems.push({ level: '🟡', msg: `日峰值异常 ${timeAnomaly.peak_day.peak_multiplier}x` })

        result = {
          success: true,
          data: {
            dimension,
            overview: {
              total: ov.total,
              by_type: { work: ov.work_count, complaint: ov.complaint_count, share: ov.share_count },
              by_status: { draft: ov.draft_count, submitted: ov.pending_count, approved: ov.approved_count, rejected: ov.rejected_count },
              user_count: ov.user_count,
              date_range: `${ov.earliest_date} ~ ${ov.latest_date}`,
              complaint_rate_pct: complaintRate,
              pending_rate_pct: pendingRate
            },
            complaint: complaintAnomaly,
            approval: approvalAnomaly,
            user: userAnomaly,
            time: timeAnomaly,
            overdue: overdueAnomaly,
            risk_items: riskItems,
            ai_recommendation: riskItems.length === 0
              ? '✅ 当前无重大异常,工单流转基本健康'
              : `⚠️ 发现 ${riskItems.length} 个风险项,建议: ${riskItems.map(r => r.msg).join('; ')}`
          }
        }
        break
      }

      // 【寺】寺庙仪表板 — 2026-08-07 大道庵 (dda.gdqshop.cn / profile 11) 上线
      // 数据源: temple_* (14 张表 — 功德/法会/僧人/牌位/骨灰龛/扫码)
      // 6 维仪表: 功德/法会/僧人/牌位/扫码/趋势
      case 'get_temple_dashboard': {
        const focus = fnArgs.focus || 'all'

        // 1) 功德收入概览
        let merit = null
        if (focus === 'all' || focus === 'merit') {
          const [meritRows] = await pool.query(
            `SELECT
               COUNT(*) total_donations,
               SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) total_paid_amount,
               SUM(CASE WHEN status='pending' THEN amount ELSE 0 END) total_pending_amount,
               COUNT(DISTINCT donor_phone) unique_donors,
               SUM(CASE WHEN is_anonymous=1 THEN 1 ELSE 0 END) anonymous_count,
               ROUND(AVG(CASE WHEN status='paid' THEN amount END), 2) avg_amount
             FROM temple_donations`
          )
          const [meritByType] = await pool.query(
            `SELECT donation_type,
                    COUNT(*) cnt,
                    SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) total_amount
             FROM temple_donations
             GROUP BY donation_type
             ORDER BY total_amount DESC`
          )
          merit = {
            ...meritRows[0],
            by_type: meritByType,
            insight: meritRows[0].total_donations === 0
              ? '尚无功德记录,需建立扫码捐功德入口'
              : `已收 ${meritRows[0].total_paid_amount || 0} 元,信众 ${meritRows[0].unique_donors || 0} 人`
          }
        }

        // 2) 法会安排
        let fahui = null
        if (focus === 'all' || focus === 'fahui') {
          const [fahuiRows] = await pool.query(
            `SELECT id, title, activity_date, description, is_active
             FROM temple_activities
             WHERE is_active=1 AND activity_date >= CURDATE()
             ORDER BY activity_date ASC LIMIT 10`
          )
          fahui = {
            upcoming_count: fahuiRows.length,
            upcoming: fahuiRows,
            insight: fahuiRows.length > 0
              ? `近期法会 ${fahuiRows.length} 场,最近一场: ${fahuiRows[0].title} (${fahuiRows[0].activity_date})`
              : '暂无即将到来的法会'
          }
        }

        // 3) 僧人状态
        let monks = null
        if (focus === 'all' || focus === 'monks') {
          const [monkRows] = await pool.query(
            `SELECT status, COUNT(*) cnt FROM temple_monks GROUP BY status`
          )
          const monkBySpecialty = await pool.query(
            `SELECT specialty, COUNT(*) cnt FROM temple_monks WHERE status='active' GROUP BY specialty`
          )
          monks = {
            by_status: monkRows,
            by_specialty: monkBySpecialty[0],
            insight: `${monkRows.find(m => m.status === 'active')?.cnt || 0} 名在任,${monkRows.find(m => m.status === 'rest')?.cnt || 0} 名休养,${monkRows.find(m => m.status === 'retired')?.cnt || 0} 名退休`
          }
        }

        // 4) 牌位库存
        let ancestors = null
        if (focus === 'all' || focus === 'ancestors') {
          const [ancestorRows] = await pool.query(
            `SELECT COUNT(*) ancestor_count FROM temple_ancestors`
          )
          const [casketRows] = await pool.query(
            `SELECT
               COUNT(*) casket_count,
               SUM(CASE WHEN status='available' THEN 1 ELSE 0 END) available_count,
               SUM(CASE WHEN status='occupied' THEN 1 ELSE 0 END) occupied_count
             FROM temple_cinerary_caskets`
          )
          ancestors = {
            ancestor_count: ancestorRows[0].ancestor_count,
            casket_count: casketRows[0].casket_count || 0,
            available_count: casketRows[0].available_count || 0,
            occupied_count: casketRows[0].occupied_count || 0,
            insight: `已立牌位 ${ancestorRows[0].ancestor_count} 个,骨灰龛 ${casketRows[0].occupied_count || 0}/${casketRows[0].casket_count || 0} 已用`
          }
        }

        // 5) 扫码热度
        let scan = null
        if (focus === 'all' || focus === 'scan') {
          const [scanRows] = await pool.query(
            `SELECT
               COUNT(*) total_scans,
               COUNT(DISTINCT casket_id) unique_caskets,
               COUNT(DISTINCT ip) unique_ips
             FROM temple_scan_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)`
          )
          scan = {
            last_30_days: scanRows[0],
            insight: scanRows[0].total_scans > 0
              ? `近 30 天扫码 ${scanRows[0].total_scans} 次,覆盖 ${scanRows[0].unique_caskets} 个牌位`
              : '近 30 天无扫码记录'
          }
        }

        // 6) AI 综合洞察
        const insights = []
        if (merit && merit.total_donations === 0) insights.push('⚠️ 尚无功德记录,建议建立扫码捐功德入口(微信公众号/H5)')
        if (fahui && fahui.upcoming_count === 0) insights.push('⚠️ 近期无活跃法会,建议录入下季度法会计划')
        if (ancestors && ancestors.casket_count === 0) insights.push('ℹ️ 骨灰龛表为空,可能是新开业寺庙')
        if (monks) {
          const active = monks.by_status.find(m => m.status === 'active')?.cnt || 0
          if (active === 0) insights.push('🔴 无在任僧人,无法开展法会')
          else if (active >= 1) insights.push(`✅ ${active} 名在任僧人,可正常开展法事`)
        }

        result = {
          success: true,
          data: {
            focus,
            merit,
            fahui,
            monks,
            ancestors,
            scan,
            insights,
            summary: `大道庵 (dda.gdqshop.cn) 管理仪表板`
          }
        }
        break
      }

      case 'get_finance_summary': {
        if (!['admin', 'manager'].includes(userRole)) {
          result = { success: false, error: '无权访问财务汇总' }
          break
        }
        const dateRange = fnArgs.date_range || 'month'
        let dateCond = "DATE(created_at) = CURDATE()"
        if (dateRange === 'month') dateCond = "created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        const [rows] = await pool.query(
          `SELECT
             COUNT(*) as order_count,
             COALESCE(SUM(total_amount),0) as gross_revenue,
             COALESCE(SUM(pay_amount),0) as net_revenue,
             COUNT(DISTINCT member_id) as unique_customers
           FROM orders WHERE status IN ('paid','completed') AND ${dateCond}`,
          []
        )
        result = { success: true, data: { date_range: dateRange, finance: rows[0] } }
        break
      }

      case 'search_customers': {
        if (!['admin', 'manager', 'shopkeeper'].includes(userRole)) {
          result = { success: false, error: '无权查询客户' }
          break
        }
        const [rows] = await pool.query(
          `SELECT id, name, phone, customer_type, member_level, points, total_spent, created_at
           FROM members WHERE (name LIKE ? OR phone LIKE ?) LIMIT 20`,
          [`%${fnArgs.keyword || ''}%`, `%${fnArgs.keyword || ''}%`]
        )
        result = { success: true, data: { count: rows.length, list: rows } }
        break
      }

      case 'send_notification': {
        // ⚠️ 高风险工具 - 需要二次确认
        result = {
          success: false,
          requiresConfirm: true,
          data: {
            tool: 'send_notification',
            args: fnArgs,
            preview: `将向 ${fnArgs.user_id || '全员'} 发送通知：${fnArgs.title || fnArgs.content || ''}`,
            note: '请前端弹窗确认后，前端再调一次本接口并加 confirm=true'
          }
        }
        break
      }

      case 'create_approval': {
        // ⚠️ 高风险工具 - 需要二次确认
        result = {
          success: false,
          requiresConfirm: true,
          data: {
            tool: 'create_approval',
            args: fnArgs,
            preview: `将创建审批单：${fnArgs.title || fnArgs.type || '未知'}（${fnArgs.amount || 0} 元）`,
            note: '需用户确认'
          }
        }
        break
      }

      case 'get_approval_list': {
        const [rows] = await pool.query(
          `SELECT id, type, title, applicant_id, amount, status, created_at
           FROM approvals WHERE 1=1 ${fnArgs.status ? 'AND status = ?' : ''}
           ORDER BY created_at DESC LIMIT 20`,
          fnArgs.status ? [fnArgs.status] : []
        )
        result = { success: true, data: { count: rows.length, list: rows } }
        break
      }

      case 'create_ticket': {
        // 工单（客服用）- 不需要确认（用户主动行为）
        const [ins] = await pool.query(
          `INSERT INTO support_tickets (user_id, title, content, priority, status, created_at)
           VALUES (?, ?, ?, ?, 'open', NOW())`,
          [userId, fnArgs.title || 'AI 创建', fnArgs.content || '', fnArgs.priority || 'normal']
        )
        result = { success: true, data: { ticket_id: ins.insertId, status: 'open' } }
        break
      }

      case 'schedule_task': {
        // D 类自动化 - 创建定时任务
        const [ins] = await pool.query(
          `INSERT INTO scheduled_tasks (name, cron_expr, action, payload, created_by, enabled, created_at)
           VALUES (?, ?, ?, ?, ?, 1, NOW())`,
          [
            fnArgs.name || `AI 任务 ${new Date().toISOString().slice(0,10)}`,
            fnArgs.cron || '0 9 * * *',
            fnArgs.action || 'hermes_delegate',
            JSON.stringify(fnArgs.payload || { task: fnArgs.task || '' }),
            userId
          ]
        )
        result = { success: true, data: { task_id: ins.insertId, cron: fnArgs.cron } }
        break
      }

      case 'hermes_delegate': {
        // 委托 Hermes（与原版一致）
        result = {
          success: true,
          data: {
            status: 'delegated',
            task: fnArgs.task,
            note: '已通过 hermes chat 委托后台执行'
          }
        }
        // 实际触发（不 await，避免阻塞响应）
        try {
          const { execSync } = await import('child_process')
          const cmd = `hermes chat -q "${(fnArgs.task || '').slice(0, 300)}" -t web,terminal,file &`
          execSync(cmd, { timeout: 3000, stdio: 'ignore' })
        } catch (e) { /* 忽略 */ }
        break
      }

      default:
        result = { success: false, error: `未知函数: ${fnName}` }
    }
  } catch (dbErr) {
    console.error(`[ai-class-react] Tool ${fnName} error:`, dbErr.message)
    result = { success: false, error: `工具执行失败: ${dbErr.message}` }
  }

  // 审计日志（所有工具调用都记录）
  try {
    await pool.query(
      `INSERT INTO ai_tool_audit (user_id, tool_name, tool_args, result_status, created_at)
       VALUES (?, ?, ?, ?, NOW())`,
      [userId, fnName, JSON.stringify(fnArgs), result.success ? 'success' : (result.requiresConfirm ? 'confirm_needed' : 'error')]
    )
  } catch (auditErr) {
    console.error('[ai-class-react] audit log error:', auditErr.message)
  }

  return result
}

// ============================================================
// 工具 schema 定义（传给 LLM）
// ============================================================
const TOOL_SCHEMAS = [
  // 原 10 个 schema（从 ai-class.js 复制）
  { type: 'function', function: {
    name: 'get_products',
    description: '查询商品列表（按关键词/分类）',
    parameters: { type: 'object', properties: {
      keyword: { type: 'string', description: '商品名称/SKU/规格关键词' },
      category: { type: 'string', description: '商品分类' }
    } }
  }},
  { type: 'function', function: {
    name: 'get_inventory',
    description: '查询单个商品的库存详情',
    parameters: { type: 'object', properties: {
      product_id: { type: 'integer', description: '商品ID（必填）' }
    }, required: ['product_id'] }
  }},
  { type: 'function', function: {
    name: 'get_orders',
    description: '查询订单列表（默认查当前用户，admin 可查全部）',
    parameters: { type: 'object', properties: {
      user_id: { type: 'integer', description: '用户ID（默认当前用户）' },
      status: { type: 'string', description: '订单状态' }
    } }
  }},
  { type: 'function', function: {
    name: 'get_user_info',
    description: '查询用户详情',
    parameters: { type: 'object', properties: {
      user_id: { type: 'integer', description: '用户ID（必填）' }
    }, required: ['user_id'] }
  }},
  { type: 'function', function: {
    name: 'get_attendance',
    description: '查询考勤记录',
    parameters: { type: 'object', properties: {
      user_id: { type: 'integer', description: '用户ID' },
      date_range: { type: 'string', enum: ['today', 'month', 'all'], description: '日期范围' }
    } }
  }},
  { type: 'function', function: {
    name: 'search_knowledge',
    description: '在 AI 知识库搜索业务知识',
    parameters: { type: 'object', properties: {
      keyword: { type: 'string', description: '搜索关键词' }
    }, required: ['keyword'] }
  }},
  { type: 'function', function: {
    name: 'web_search',
    description: '搜索互联网最新信息',
    parameters: { type: 'object', properties: {
      query: { type: 'string', description: '搜索内容' },
      max_results: { type: 'integer', description: '最多几条' }
    }, required: ['query'] }
  }},
  { type: 'function', function: {
    name: 'get_sales_report',
    description: '查询销售报表汇总',
    parameters: { type: 'object', properties: {
      date_range: { type: 'string', enum: ['today', 'month', 'quarter', 'year'] }
    } }
  }},
  { type: 'function', function: {
    name: 'get_inventory_alert',
    description: '查询库存预警商品',
    parameters: { type: 'object', properties: {} }
  }},

  // 【财】毛利分析 — 2026-08-07 波哥要求按"人/事/物/财"补财的 AI 化
  // 数据源: sales_orders (有 original_price 成本 + sale_price 售价)
  // 重要: 不要求权限 (admin/manager 自动放行), 任何角色都能查 (毛利 = 公开财务)
  { type: 'function', function: {
    name: 'get_profit_analysis',
    description: '【财】毛利分析 - 按周期/商品/客户聚合销售毛利,识别爆款/滞销/高利润客户。返回: 总营收/总成本/总毛利/毛利率 + 各维度 Top5。参数: period (今天/本月/本季/本年/近7天/近30天), group_by (product/customer/month, 默认 product)',
    parameters: { type: 'object', properties: {
      period: { type: 'string', enum: ['today', 'month', 'quarter', 'year', '7days', '30days'], description: '时间周期 (默认 month)' },
      group_by: { type: 'string', enum: ['product', 'customer', 'month'], description: '聚合维度 (默认 product)' }
    } }
  }},

  // 【人】工人画像 — 2026-08-07 波哥要求按"人/事/物/财"补人的 AI 化
  // 数据源: users (含 worker/foreman/salesperson/shopkeeper/store_manager 角色)
  //         attendance (考勤统计) + work_logs (日报聚合) + h5_users (移动端画像)
  // 不传 worker_id 时返 Top10 工人画像摘要; 传 worker_id 时返单人完整画像
  { type: 'function', function: {
    name: 'get_worker_profile',
    description: '【人】工人画像 - 返回工人/员工的多维画像: 基本信息(姓名/角色/部门/工号/入职) + 考勤统计(出勤天数/迟到/早退/加班) + 工作产出(日报数/投诉数/参与工单) + 移动端绑定(h5_user_id)。传 worker_id 查单人,不传返 Top10 工人画像摘要',
    parameters: { type: 'object', properties: {
      worker_id: { type: 'integer', description: '工人 user_id (不传返 Top10)' },
      days: { type: 'integer', description: '考勤统计天数 (默认 30)' }
    } }
  }},

  // 【财】财务对账 — 2026-08-07 波哥要求按"人/事/物/财"补财的 AI 化
  // 数据源: imported_excel_items (7266 条真财务导入数据)
  // 4 维对账: 内部一致性 (qty*price=amount) + 门店对比 (26/47/SM 系列) + SKU 分布 + 价格段异常
  { type: 'function', function: {
    name: 'get_reconcile_diff',
    description: '【财】财务对账差异分析 - 检查 imported_excel_items 财务导入数据的 4 维度对账: 1)内部一致性(qty×price=amount) 2)门店对比(各 store_name 的销量/单价差异) 3)Top SKU 分布 4)价格段异常(偏离均价). 返回差异清单 + 异常门店 + AI 洞察',
    parameters: { type: 'object', properties: {
      focus: { type: 'string', enum: ['all', 'consistency', 'store', 'sku', 'price'], description: '对账焦点 (默认 all)' }
    } }
  }},

  // 【事】工单异常预警 — 2026-08-07 波哥要求按"人/事/物/财"补事的 AI 化
    // 数据源: work_logs (35 条聚合: work/complaint/share + draft/submitted/approved/rejected)
    // 6 维异常: 整体概览 / 投诉率 / 审批积压 / 用户异常 / 时间峰值 / 历史积压
    { type: 'function', function: {
      name: 'get_workorder_anomaly',
      description: '【事】工单异常预警 - 分析 work_logs 工单/日报/投诉/分享的 6 维度异常: 1)整体概览 2)投诉率/投诉积压 3)审批积压(submitted 长时间未审) 4)用户异常(投诉率/提交频率) 5)时间异常(日峰值) 6)历史积压。返回异常清单 + 风险定级 + AI 行动建议',
      parameters: { type: 'object', properties: {
        dimension: { type: 'string', enum: ['all', 'complaint', 'approval', 'user', 'time', 'overdue'], description: '异常维度 (默认 all)' }
      } }
    }},

    // 【寺】寺庙仪表板 — 2026-08-07 大道庵 (dda.gdqshop.cn / profile 11) 上线
    // 数据源: temple_donations (功德捐) + temple_activities (法会) + temple_monks (僧人) + temple_ancestors (祖先牌位) + temple_cinerary_caskets (骨灰龛) + temple_scan_logs (扫码)
    // 6 维仪表: 功德收入 / 法会安排 / 僧人状态 / 牌位库存 / 扫码热度 / 月度趋势
    { type: 'function', function: {
      name: 'get_temple_dashboard',
      description: '【寺】寺庙管理仪表板 - 大道庵等寺庙的 6 维智能管理: 1)功德收入概览(总额/平均/分类) 2)法会安排(本月/下月活跃法会) 3)僧人状态(active/rest/retired) 4)牌位库存(祖先/骨灰龛总数) 5)扫码热度(牌位 QR 访问日志) 6)月度趋势。返回统计 + 异常 + AI 行动建议',
      parameters: { type: 'object', properties: {
        focus: { type: 'string', enum: ['all', 'merit', 'fahui', 'monks', 'ancestors', 'scan', 'trend'], description: '仪表焦点 (默认 all)' }
      } }
    }},

  // 新增 7 个（B/C/D 类业务工具）
  { type: 'function', function: {
    name: 'get_finance_summary',
    description: '查询财务汇总（收入/客户数）',
    parameters: { type: 'object', properties: {
      date_range: { type: 'string', enum: ['today', 'month', 'quarter', 'year'] }
    } }
  }},
  { type: 'function', function: {
    name: 'search_customers',
    description: '搜索客户',
    parameters: { type: 'object', properties: {
      keyword: { type: 'string', description: '客户名/手机号关键词' }
    }, required: ['keyword'] }
  }},
  { type: 'function', function: {
    name: 'send_notification',
    description: '发送站内通知（高风险：需用户确认）',
    parameters: { type: 'object', properties: {
      user_id: { type: 'integer', description: '接收用户ID' },
      title: { type: 'string' },
      content: { type: 'string' }
    }, required: ['title', 'content'] }
  }},
  { type: 'function', function: {
    name: 'create_approval',
    description: '创建审批单（高风险：需用户确认）',
    parameters: { type: 'object', properties: {
      type: { type: 'string', description: '审批类型（如 leave/refund/order）' },
      title: { type: 'string' },
      amount: { type: 'number' },
      reason: { type: 'string' }
    }, required: ['type', 'title'] }
  }},
  { type: 'function', function: {
    name: 'get_approval_list',
    description: '查询审批列表',
    parameters: { type: 'object', properties: {
      status: { type: 'string', enum: ['pending', 'approved', 'rejected'] }
    } }
  }},
  { type: 'function', function: {
    name: 'create_ticket',
    description: '创建客服工单',
    parameters: { type: 'object', properties: {
      title: { type: 'string' },
      content: { type: 'string' },
      priority: { type: 'string', enum: ['low', 'normal', 'high', 'urgent'] }
    }, required: ['content'] }
  }},
  { type: 'function', function: {
    name: 'schedule_task',
    description: '创建定时任务（自动化）',
    parameters: { type: 'object', properties: {
      name: { type: 'string' },
      cron: { type: 'string', description: 'cron 表达式' },
      task: { type: 'string', description: '任务描述' },
      action: { type: 'string', enum: ['hermes_delegate', 'get_sales_report', 'get_inventory_alert'] }
    }, required: ['cron', 'task'] }
  }},
  { type: 'function', function: {
    name: 'hermes_delegate',
    description: '委托 Hermes Agent 执行复杂任务',
    parameters: { type: 'object', properties: {
      task: { type: 'string', description: '任务描述' },
      toolsets: { type: 'string', description: '可用工具集（逗号分隔）' }
    }, required: ['task'] }
  }}
]

// ============================================================
// ReAct 主循环
// ============================================================
async function reactLoop({ message, sessionId, userCtx, confirmFlag, llmConfig, pool }) {
  const conversationLog = []  // [{role, content, tool_calls, tool_results}]
  conversationLog.push({ role: 'user', content: message })

  const allToolsUsed = []      // 记录所有工具调用（审计）
  let requiresConfirm = null   // 高风险工具等待确认

  for (let round = 1; round <= MAX_REACT_ROUNDS; round++) {
    // 1. 调 LLM
    const llmResponse = await callLLM(conversationLog, llmConfig, TOOL_SCHEMAS)
    if (!llmResponse.ok) {
      return { error: llmResponse.error, allToolsUsed, requiresConfirm, rounds: round }
    }

    const assistantMsg = llmResponse.message
    conversationLog.push(assistantMsg)

    // 2. 检查是否需要调工具
    const toolCalls = assistantMsg.tool_calls || []
    if (toolCalls.length === 0) {
      // 没有工具调用 → 返回最终回复
      return {
        reply: assistantMsg.content || '',
        allToolsUsed,
        requiresConfirm,
        rounds: round
      }
    }

    // 3. 执行所有 tool_calls
    for (const tc of toolCalls) {
      const fnName = tc.function?.name || tc.name
      let fnArgs = {}
      try { fnArgs = JSON.parse(tc.function?.arguments || tc.arguments || '{}') }
      catch { fnArgs = {} }

      allToolsUsed.push({ round, name: fnName, args: fnArgs })

      // 检查是否需要确认
      if (HIGH_RISK_TOOLS.has(fnName) && !confirmFlag) {
        const preview = await executeTool(fnName, fnArgs, userCtx)
        if (preview.requiresConfirm) {
          requiresConfirm = {
            tool: fnName,
            args: fnArgs,
            preview: preview.data,
            tool_call_id: tc.id
          }
          return {
            reply: '',
            requiresConfirm,
            allToolsUsed,
            rounds: round,
            waitingForConfirm: true
          }
        }
      }

      // 正常执行
      const execResult = await executeTool(fnName, fnArgs, userCtx)

      // 4. 把工具结果回传给 LLM
      conversationLog.push({
        role: 'tool',
        tool_call_id: tc.id,
        content: JSON.stringify(execResult)
      })
    }

    // 5. 检查反思：连续 3 轮无新信息 → 跳出
    if (round >= 3) {
      const lastThree = conversationLog.slice(-6)
      const allShort = lastThree.every(m => !m.content || m.content.length < 50)
      if (allShort) break
    }
  }

  // 超过 MAX_REACT_ROUNDS → 取最后 assistant message 作为回复
  const lastAssistant = [...conversationLog].reverse().find(m => m.role === 'assistant')
  return {
    reply: lastAssistant?.content || '（已达到最大推理轮次）',
    allToolsUsed,
    requiresConfirm,
    rounds: MAX_REACT_ROUNDS
  }
}

// ============================================================
// LLM 调用（双协议）
// ============================================================
async function callLLM(messages, llmConfig, tools) {
  const { base_url: baseUrl, api_key: apiKey, model, protocol } = llmConfig

  const controller = new AbortController()
  const timeout = setTimeout(() => controller.abort(), TOOL_TIMEOUT_MS)

  try {
    let requestUrl, requestHeaders, requestBody, response, data

    if (protocol === 'anthropic') {
      const systemPrompt = messages.find(m => m.role === 'system')?.content || ''
      // 转换 OpenAI 格式到 Anthropic 格式
      const userMessages = []
      for (const m of messages) {
        if (m.role === 'system') continue
        if (m.role === 'user') {
          userMessages.push({ role: 'user', content: m.content })
        } else if (m.role === 'assistant') {
          // 如果有 tool_calls，转成 content: [{type: 'tool_use', ...}]
          if (m.tool_calls && m.tool_calls.length > 0) {
            const content = []
            if (m.content) content.push({ type: 'text', text: m.content })
            for (const tc of m.tool_calls) {
              content.push({
                type: 'tool_use',
                id: tc.id,
                name: tc.function?.name || tc.name,
                input: (() => {
                  try { return JSON.parse(tc.function?.arguments || tc.arguments || '{}') }
                  catch { return {} }
                })()
              })
            }
            userMessages.push({ role: 'assistant', content })
          } else {
            userMessages.push({ role: 'assistant', content: m.content || '' })
          }
        } else if (m.role === 'tool') {
          // tool 消息：转成 user + content: [{type: 'tool_result', tool_use_id, content}]
          // Anthropic 要求 tool_result 必须跟在对应的 tool_use 后面
          // 我们合并连续多个 tool 结果到一个 user 消息里
          const lastUser = userMessages[userMessages.length - 1]
          const toolResult = {
            type: 'tool_result',
            tool_use_id: m.tool_call_id,
            content: m.content
          }
          if (lastUser && lastUser.role === 'user' && Array.isArray(lastUser.content)) {
            // 已有 user 的 tool_result 数组 → 追加
            lastUser.content.push(toolResult)
          } else {
            userMessages.push({ role: 'user', content: [toolResult] })
          }
        }
      }
      requestUrl = baseUrl
      requestHeaders = {
        'Content-Type': 'application/json',
        'x-api-key': apiKey,
        'anthropic-version': '2023-06-01'
      }
      const anthropicTools = tools.map(f => ({
        name: f.function.name,
        description: f.function.description,
        input_schema: f.function.parameters
      }))
      requestBody = {
        model, max_tokens: 3000, temperature: 0.3,
        system: systemPrompt, tools: anthropicTools,
        messages: userMessages
      }
    } else {
      requestUrl = `${baseUrl}/chat/completions`
      requestHeaders = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${apiKey}`
      }
      requestBody = { model, messages, tools, max_tokens: 3000, temperature: 0.3 }
    }

    response = await fetch(requestUrl, {
      method: 'POST', headers: requestHeaders,
      body: JSON.stringify(requestBody), signal: controller.signal
    })
    clearTimeout(timeout)

    if (!response.ok) {
      return { ok: false, error: `LLM ${response.status}: ${await response.text()}` }
    }

    data = await response.json()

    // 解析响应
    let assistantMsg
    if (protocol === 'anthropic') {
      const contentArr = data.content || []
      const textParts = contentArr.filter(c => c.type === 'text').map(c => c.text)
      const toolUses = contentArr.filter(c => c.type === 'tool_use')
      if (toolUses.length > 0) {
        assistantMsg = {
          role: 'assistant',
          content: textParts.join('\n') || null,
          tool_calls: toolUses.map(tu => ({
            id: tu.id, type: 'function',
            function: { name: tu.name, arguments: JSON.stringify(tu.input || {}) }
          }))
        }
      } else {
        assistantMsg = { role: 'assistant', content: textParts.join('\n') }
      }
    } else {
      assistantMsg = data.choices?.[0]?.message
    }

    return { ok: true, message: assistantMsg }
  } catch (e) {
    clearTimeout(timeout)
    return { ok: false, error: e.message }
  }
}

// ============================================================
// 读 LLM config
// ============================================================
async function getLLMConfig() {
  const defaultCfg = {
    base_url: 'https://api.minimaxi.com/anthropic/v1/messages',
    api_key: process.env.MINIMAX_API_KEY || '',
    model: 'MiniMax-M3-8k',
    protocol: 'anthropic'
  }
  try {
    const [rows] = await pool.query(
      "SELECT base_url, api_key, model, provider FROM ai_config WHERE category='llm' AND status=1 ORDER BY is_default DESC LIMIT 1"
    )
    if (rows.length > 0) {
      const cfg = rows[0]
      if (cfg.base_url) defaultCfg.base_url = cfg.base_url
      if (cfg.api_key) defaultCfg.api_key = cfg.api_key
      if (cfg.model) defaultCfg.model = cfg.model
      if (cfg.base_url?.includes('/anthropic')) defaultCfg.protocol = 'anthropic'
      else if (['minimax','openai','nvidia'].includes(cfg.provider)) defaultCfg.protocol = 'openai'
    }
  } catch (e) {}
  return defaultCfg
}

// ============================================================
// 主 endpoint
// ============================================================
router.post('/chat-react', async (req, res, next) => {
  try {
    const { message, session_id: inputSid, confirm } = req.body
    if (!message) return res.json({ code: 400, message: 'message 必填' })

    // user context
    const userCtx = {
      userId: req.user?.id || 0,
      userRole: req.user?.role || 'guest',
      userPermissions: req.user?.permissions || []
    }

    // session 处理 — 兼容 3 种 session_id: 数字 / UUID 字符串 / 业务字符串
    // (跟 ai-class.js L378-385 同样修复 — 防止 parseInt(UUID) = NaN 报 Unknown column)
    let sid = inputSid
    if (!sid || (typeof sid === 'string' && !/^\d+$/.test(sid))) {
      const [ins] = await pool.query(
        'INSERT INTO ai_class_sessions (user_id, title, created_at) VALUES (?, ?, NOW())',
        [userCtx.userId, message.slice(0, 50)]
      )
      sid = ins.insertId
    }

    // LLM config
    const llmConfig = await getLLMConfig()
    if (!llmConfig.api_key) {
      return res.json({ code: 500, message: 'AI 服务未配置 API Key' })
    }

    // 系统提示
    const systemMsg = {
      role: 'system',
      content: `你是彩美特管理系统的 AI 助手"小智"。你有 17 个工具可用，包括查商品/库存/订单/用户/考勤/销售/财务/客户/审批/工单/定时任务/网络搜索/知识库/委托 Hermes 等。\n\n工作原则：\n1. 简单查询一次完成\n2. 复杂任务可多次调用工具，直到信息充分\n3. 高风险操作（发通知/创建审批）会要求二次确认\n4. 回复用中文 + Markdown 表格化\n\n当前用户：${userCtx.userId} (${userCtx.userRole})`
    }

    // ReAct 循环
    const result = await reactLoop({
      message, sessionId: sid, userCtx,
      confirmFlag: !!confirm,
      llmConfig, pool
    })

    // 处理需要确认的情况
    if (result.waitingForConfirm) {
      return res.json({
        code: 0,
        data: {
          requires_confirm: true,
          preview: result.requiresConfirm.preview,
          tool: result.requiresConfirm.tool,
          args: result.requiresConfirm.args,
          session_id: sid,
          message: `⚠️ 高风险操作需要您确认：${result.requiresConfirm.preview.preview}\n请回复"确认"或重新调用并加 confirm=true`
        }
      })
    }

    // 错误
    if (result.error) {
      return res.json({ code: 500, message: result.error, session_id: sid })
    }

    // 正常回复：保存到 messages 表
    await pool.query(
      'INSERT INTO ai_class_messages (session_id, role, content, model) VALUES (?, ?, ?, ?)',
      [parseInt(sid), 'user', message, llmConfig.model]
    )
    await pool.query(
      'INSERT INTO ai_class_messages (session_id, role, content, model) VALUES (?, ?, ?, ?)',
      [parseInt(sid), 'assistant', result.reply, llmConfig.model]
    )

    // 工具使用统计
    const toolSummary = result.allToolsUsed.reduce((acc, t) => {
      acc[t.name] = (acc[t.name] || 0) + 1
      return acc
    }, {})

    return res.json({
      code: 0,
      data: {
        reply: result.reply,
        session_id: sid,
        rounds: result.rounds,
        tools_used: toolSummary,
        tools_detail: result.allToolsUsed
      }
    })
  } catch (err) {
    console.error('[ai-class-react] error:', err)
    next(err)
  }
})

export default router

// ============================================================
// 测试 endpoint：直接验证二次确认机制（绕过 LLM）
// ============================================================
router.post('/test-confirm-mechanism', async (req, res) => {
  // 模拟 LLM 决定调用 send_notification
  const { tool, args, confirm } = req.body
  const userCtx = {
    userId: req.user?.id || 0,
    userRole: req.user?.role || 'guest',
    userPermissions: req.user?.permissions || []
  }

  const HIGH_RISK = new Set(['send_notification', 'create_approval', 'update_user_role', 'delete_order', 'process_refund'])

  if (HIGH_RISK.has(tool) && !confirm) {
    // 模拟确认流程
    return res.json({
      code: 0,
      data: {
        requires_confirm: true,
        tool,
        args,
        preview: {
          tool,
          args,
          preview: `测试 - 即将执行高风险操作 ${tool}(${JSON.stringify(args)})`,
          note: '前端应弹窗确认'
        },
        message: 'C 二次确认机制触发 ✅（未传 confirm=true）'
      }
    })
  }

  // 真的执行
  const result = await executeTool(tool, args || {}, userCtx)
  return res.json({
    code: 0,
    data: {
      executed: true,
      tool,
      result,
      message: confirm ? '已确认执行 ✅' : '非高风险工具直接执行'
    }
  })
})
