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
      }))
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/finance/recent?limit=15 - 最新收支记录（费用 + 收支流水 + 应收应付）
router.get('/finance/recent', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const limit = Math.min(Number(req.query.limit) || 15, 50)
    // 费用记录（expense_records 真实字段: category/category_name/description/approval_status）
    const [expenses] = await pool.query(`
      SELECT id, record_no, category, category_name, amount, payee, description,
             approval_status, expense_date, created_at
      FROM expense_records ORDER BY created_at DESC LIMIT ?
    `, [limit]).catch(() => [[]])
    // 收支流水（income_expense_records: type/category/summary/status）
    const [incomeExp] = await pool.query(`
      SELECT id, record_no, record_date, type, category, amount, summary, status, created_at
      FROM income_expense_records ORDER BY created_at DESC LIMIT ?
    `, [limit]).catch(() => [[]])
    // 应收（accounts_receivable: customer_name/amount/transaction_date）
    const [ar] = await pool.query(`
      SELECT id, customer_name, customer_phone, amount, balance, transaction_date, created_at
      FROM accounts_receivable ORDER BY created_at DESC LIMIT ?
    `, [Math.min(limit,5)]).catch(() => [[]])
    // 应付（accounts_payable: supplier_name/amount/due_date）
    const [ap] = await pool.query(`
      SELECT id, supplier_name, amount, due_date, created_at
      FROM accounts_payable ORDER BY created_at DESC LIMIT ?
    `, [Math.min(limit,5)]).catch(() => [[]])

    const recent = [
      ...(expenses||[]).map(e => ({
        id: e.id, type: 'expense', record_no: e.record_no, category: e.category || e.category_name,
        amount: Number(e.amount)||0, party: e.payee, summary: e.description, status: e.approval_status,
        date: e.expense_date || e.created_at,
      })),
      ...(ar||[]).map(a => ({
        id: a.id, type: 'receivable', record_no: null, category: '应收',
        amount: Number(a.amount)||0, party: a.customer_name, summary: null, status: null,
        date: a.transaction_date || a.created_at,
      })),
      ...(ap||[]).map(b => ({
        id: b.id, type: 'payable', record_no: null, category: '应付',
        amount: Number(b.amount)||0, party: b.supplier_name, summary: null, status: null,
        date: b.due_date || b.created_at,
      })),
    ]
    // 按时间倒序
    recent.sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0))

    res.json({
      recent: recent.slice(0, limit),
      count: recent.length,
      totals: {
        expense: (expenses||[]).reduce((s, e) => s + (Number(e.amount)||0), 0),
        receivable: (ar||[]).reduce((s, a) => s + (Number(a.amount)||0), 0),
        payable: (ap||[]).reduce((s, b) => s + (Number(b.amount)||0), 0),
      },
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/reports/daily?date=YYYY-MM-DD - 日报
router.get('/reports/daily', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const date = String(req.query.date || '').slice(0,10) || new Date().toISOString().slice(0,10)
    // 销售（orders 聚合）
    const [[sales]] = await pool.query(`
      SELECT COUNT(*) AS orders, COALESCE(SUM(pay_amount),0) AS revenue
      FROM orders WHERE status IN ('paid','completed','shipped') AND DATE(created_at)=?
    `, [date]).catch(() => [[{ orders: 0, revenue: 0 }]])
    // 待审工作日志
    const [[logs]] = await pool.query(`
      SELECT COUNT(*) AS c FROM work_logs WHERE DATE(created_at)=?
    `, [date]).catch(() => [[{ c: 0 }]])
    // 考勤打卡
    const [[att]] = await pool.query(`
      SELECT COUNT(*) AS c FROM attendance WHERE date=?
    `, [date]).catch(() => [[{ c: 0 }]])
    // 新任务
    const [[tasks]] = await pool.query(`
      SELECT COUNT(*) AS c FROM tasks WHERE DATE(created_at)=?
    `, [date]).catch(() => [[{ c: 0 }]])
    // 新知识
    const [[kb]] = await pool.query(`
      SELECT COUNT(*) AS c FROM ai_class_knowledge WHERE DATE(created_at)=?
    `, [date]).catch(() => [[{ c: 0 }]])

    res.json({
      report_type: 'daily', date,
      sales: { orders: Number(sales.orders), revenue: Number(sales.revenue) },
      work_logs_added: Number(logs.c),
      attendance_checkins: Number(att.c),
      tasks_created: Number(tasks.c),
      knowledge_added: Number(kb.c),
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/reports/weekly - 周报（近 7 日销售 / 趋势）
router.get('/reports/weekly', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    // 近7日销售趋势
    const [sales] = await pool.query(`
      SELECT DATE(created_at) AS day, COUNT(*) AS orders, COALESCE(SUM(pay_amount),0) AS revenue
      FROM orders
      WHERE status IN ('paid','completed','shipped')
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
      GROUP BY DATE(created_at) ORDER BY day
    `).catch(() => [[]])
    // 汇总
    const [[agg]] = await pool.query(`
      SELECT COUNT(*) AS orders, COALESCE(SUM(pay_amount),0) AS revenue
      FROM orders
      WHERE status IN ('paid','completed','shipped')
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    `).catch(() => [[{ orders: 0, revenue: 0 }]])
    // 本周新增任务/日志
    const [[tasks]] = await pool.query(`
      SELECT COUNT(*) AS c FROM tasks WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    `).catch(() => [[{ c: 0 }]])
    const [[logs]] = await pool.query(`
      SELECT COUNT(*) AS c FROM work_logs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    `).catch(() => [[{ c: 0 }]])

    res.json({
      report_type: 'weekly_last_7_days',
      summary: {
        orders: Number(agg.orders), revenue: Number(agg.revenue),
        avg_daily_revenue: Math.round(Number(agg.revenue) / 7),
        tasks_created: Number(tasks.c), work_logs: Number(logs.c),
      },
      trend: (sales||[]).map(r => ({
        date: r.day ? String(r.day).slice(0,10) : null,
        orders: Number(r.orders)||0, revenue: Number(r.revenue)||0,
      })),
    })
  } catch (err) { next(err) }
})

export default router