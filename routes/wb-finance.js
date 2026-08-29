import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// GET /api/workbuddy/finance/overview - 财务总览（营收/待审批金额/财务提醒）
// 数据来源：sales_orders 销售额 + approvals 待审批金额 + finance_reminders 未读提醒
router.get('/finance/overview', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [[today]] = await pool.query(`
      SELECT COALESCE(SUM(sale_price),0) AS rev
      FROM sales_orders
      WHERE status IN ('paid','completed') AND DATE(paid_at) = CURDATE()
    `).catch(() => [[{ rev: 0 }]])
    const [[month]] = await pool.query(`
      SELECT COALESCE(SUM(sale_price),0) AS rev
      FROM sales_orders
      WHERE status IN ('paid','completed') AND DATE_FORMAT(paid_at,'%Y%m') = DATE_FORMAT(CURDATE(),'%Y%m')
    `).catch(() => [[{ rev: 0 }]])
    const [[pending]] = await pool.query(`
      SELECT COALESCE(SUM(amount),0) AS amt
      FROM approvals WHERE status='pending'
    `).catch(() => [[{ amt: 0 }]])
    const [[reminder]] = await pool.query(`
      SELECT COUNT(*) AS c FROM finance_reminders WHERE status IN ('pending','unread') OR status IS NULL
    `).catch(() => [[{ c: 0 }]])

    res.json({
      today_revenue: Number(today.rev),
      month_revenue: Number(month.rev),
      pending_approval_amount: Number(pending.amt),
      pending_finance_reminders: Number(reminder.c),
      currency: 'CNY',
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/finance/reminders - 财务提醒列表
router.get('/finance/reminders', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT id, reminder_type, title, content, related_type, status, priority, created_at
      FROM finance_reminders
      ORDER BY status='pending' DESC, priority='high' DESC, created_at DESC
      LIMIT 30
    `).catch(() => [[]])
    res.json({
      reminders: (rows||[]).map(r => ({
        id: r.id, type: r.reminder_type, title: r.title, content: r.content,
        related: r.related_type, status: r.status, priority: r.priority, created_at: r.created_at,
      })),
      count: (rows||[]).length,
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/reports/sales - 销售报表（近 7 日销售统计）
router.get('/reports/sales', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT DATE(created_at) AS day, COUNT(*) AS orders, COALESCE(SUM(sale_price),0) AS revenue
      FROM sales_orders
      WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
      GROUP BY DATE(created_at)
      ORDER BY day
    `).catch(() => [[]])
    res.json({
      report_type: 'sales_last_7_days',
      days: (rows||[]).map(r => ({
        date: r.day ? String(r.day).slice(0,10) : null,
        orders: Number(r.orders)||0,
        revenue: Number(r.revenue)||0,
      })),
    })
  } catch (err) { next(err) }
})

export default router