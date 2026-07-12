import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requireRole } from '../middleware/rbac.js'

const router = Router()

// ============================================================
// 公开接口（游客可访问，无需登录）
// ============================================================

// GET /api/minip/modules - 业务模块列表
// target=visitor 返回 both+employee（公开给游客看的所有模块）
// target=employee 返回 both+employee（登录员工看的所有模块）
// 不传 target 返回全部
router.get('/modules', async (req, res, next) => {
  try {
    const { target } = req.query
    let sql = 'SELECT id, module_key, title, subtitle, icon, path, target FROM minip_modules WHERE enabled = 1'
    const params = []
    if (target) {
      sql += ' AND target IN (?, "both")'
      params.push(target)
    }
    sql += ' ORDER BY sort_order ASC'
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// GET /api/minip/banners - 首页 banner（复用 banners 表）
router.get('/banners', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, title, image_url, link_url, sort
       FROM banners
       WHERE status = 'active'
       ORDER BY sort ASC LIMIT 10`
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// GET /api/minip/news - 新闻动态（复用 articles 表）
router.get('/news', async (req, res, next) => {
  try {
    const limit = parseInt(req.query.limit) || 10
    const [rows] = await pool.query(
      `SELECT id, title, summary, cover_image, published_at
       FROM articles
       WHERE status = 'published'
       ORDER BY published_at DESC LIMIT ?`,
      [limit]
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// GET /api/minip/news/:id - 新闻详情
router.get('/news/:id', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT id, title, summary, content, cover_image, author, published_at FROM articles WHERE id = ? AND status = "published"',
      [req.params.id]
    )
    if (!rows.length) return res.status(404).json({ code: 404, message: '新闻不存在' })
    res.json({ code: 0, data: rows[0] })
  } catch (err) { next(err) }
})

// GET /api/minip/activities - 营销活动列表
router.get('/activities', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, title, description, cover_image, location,
              start_date, end_date, max_participants, current_participants, status
       FROM minip_activities
       WHERE enabled = 1 AND status IN ('published', 'ongoing', 'finished')
       ORDER BY sort_order ASC LIMIT 20`
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// GET /api/minip/activities/:id - 活动详情
router.get('/activities/:id', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT * FROM minip_activities WHERE id = ? AND enabled = 1',
      [req.params.id]
    )
    if (!rows.length) return res.status(404).json({ code: 404, message: '活动不存在' })
    res.json({ code: 0, data: rows[0] })
  } catch (err) { next(err) }
})

// GET /api/minip/services - 会员服务/VIP 等级（复用 member_level 表）
router.get('/services', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, name, icon, min_points, max_points, discount_rate,
              points_ratio, birthday_double, free_shipping, exclusive_access,
              priority_customer, is_default, sort_order
       FROM member_level
       WHERE status = 'active'
       ORDER BY sort_order ASC`
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/minip/applications - 提交入会申请
router.post('/applications', async (req, res, next) => {
  try {
    const {
      company_name, contact_name, contact_phone, contact_email,
      business_type, team_size, expected_join_date, remarks
    } = req.body

    if (!company_name || !contact_name || !contact_phone) {
      return res.status(400).json({ code: 400, message: '请填写必填项' })
    }

    const [result] = await pool.query(
      `INSERT INTO minip_join_applications
       (company_name, contact_name, contact_phone, contact_email, business_type,
        team_size, expected_join_date, remarks, status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')`,
      [company_name, contact_name, contact_phone, contact_email || null,
       business_type || null, team_size || null, expected_join_date || null,
       remarks || null]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '提交成功，我们会尽快联系您' })
  } catch (err) { next(err) }
})

// ============================================================
// 管理接口（需要登录 + admin 角色，复用主站 JWT）
// ============================================================

// GET /api/minip/admin/applications - 申请列表（管理后台用）
router.get('/admin/applications', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const status = req.query.status
    let sql = `SELECT a.*, u.name as reviewer_name
               FROM minip_join_applications a
               LEFT JOIN users u ON a.reviewer_id = u.id`
    const params = []
    if (status) {
      sql += ' WHERE a.status = ?'
      params.push(status)
    }
    sql += ' ORDER BY a.created_at DESC LIMIT 100'
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// PUT /api/minip/admin/applications/:id/review - 审核申请
router.put('/admin/applications/:id/review', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const { status, review_remarks } = req.body
    if (!['approved', 'rejected', 'reviewing'].includes(status)) {
      return res.status(400).json({ code: 400, message: '状态无效' })
    }
    const decoded = req.user
    await pool.query(
      `UPDATE minip_join_applications
       SET status = ?, review_remarks = ?, reviewer_id = ?, reviewed_at = NOW()
       WHERE id = ?`,
      [status, review_remarks || null, decoded.id, req.params.id]
    )
    res.json({ code: 0, message: '审核成功' })
  } catch (err) { next(err) }
})

// GET /api/minip/admin/modules - 模块列表（管理用）
router.get('/admin/modules', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT * FROM minip_modules ORDER BY sort_order ASC'
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/minip/admin/modules - 新建模块
router.post('/admin/modules', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const { module_key, title, subtitle, icon, path, target, sort_order, enabled } = req.body
    if (!module_key || !title || !icon || !path) {
      return res.status(400).json({ code: 400, message: '请填写必填项' })
    }
    const [result] = await pool.query(
      `INSERT INTO minip_modules (module_key, title, subtitle, icon, path, target, sort_order, enabled)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [module_key, title, subtitle || null, icon, path, target || 'employee', sort_order || 0, enabled ? 1 : 0]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '创建成功' })
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') return res.status(400).json({ code: 400, message: 'module_key 已存在' })
    next(err)
  }
})

// PUT /api/minip/admin/modules/:id - 更新模块
router.put('/admin/modules/:id', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const { title, subtitle, icon, path, target, sort_order, enabled } = req.body
    await pool.query(
      `UPDATE minip_modules
       SET title = ?, subtitle = ?, icon = ?, path = ?, target = ?,
           sort_order = ?, enabled = ?
       WHERE id = ?`,
      [title, subtitle, icon, path, target, sort_order, enabled ? 1 : 0, req.params.id]
    )
    res.json({ code: 0, message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/minip/admin/modules/:id - 删除模块
router.delete('/admin/modules/:id', auth, requireRole('admin'), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM minip_modules WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

// GET /api/minip/admin/activities - 活动列表（管理用）
router.get('/admin/activities', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT * FROM minip_activities ORDER BY sort_order ASC'
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/minip/admin/activities - 新建活动
router.post('/admin/activities', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const {
      title, description, cover_image, location,
      start_date, end_date, max_participants, status, sort_order, enabled
    } = req.body
    const [result] = await pool.query(
      `INSERT INTO minip_activities
       (title, description, cover_image, location, start_date, end_date,
        max_participants, status, sort_order, enabled)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [title, description || null, cover_image || null, location || null,
       start_date || null, end_date || null, max_participants || 0,
       status || 'draft', sort_order || 0, enabled ? 1 : 0]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/minip/admin/activities/:id - 更新活动
router.put('/admin/activities/:id', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const {
      title, description, cover_image, location,
      start_date, end_date, max_participants, status, sort_order, enabled
    } = req.body
    await pool.query(
      `UPDATE minip_activities
       SET title = ?, description = ?, cover_image = ?, location = ?,
           start_date = ?, end_date = ?, max_participants = ?, status = ?,
           sort_order = ?, enabled = ?
       WHERE id = ?`,
      [title, description || null, cover_image || null, location || null,
       start_date || null, end_date || null, max_participants || 0,
       status || 'draft', sort_order || 0, enabled ? 1 : 0, req.params.id]
    )
    res.json({ code: 0, message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/minip/admin/activities/:id - 删除活动
router.delete('/admin/activities/:id', auth, requireRole('admin'), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM minip_activities WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

export default router
// ============================================================
// 企业端接口（需要登录 - JWT 通过 auth 中间件校验）
// 路由前缀：/api/minip/enterprise/*
// ============================================================

// ===== 财务模块 =====

// GET /api/minip/enterprise/expenses - 报销列表（当前用户提交）
router.get('/enterprise/expenses', auth, async (req, res, next) => {
  try {
    const { status, page = 1, pageSize = 20 } = req.query
    const userId = req.user.id
    let sql = `SELECT id, record_no, expense_date, category, category_name, amount, payment_method, description, approval_status, approver_id, approved_at, created_at FROM expense_records WHERE creator_id = ?`
    const params = [userId]
    if (status) { sql += ' AND approval_status = ?'; params.push(status) }
    sql += ' ORDER BY expense_date DESC LIMIT ? OFFSET ?'
    params.push(Number(pageSize), (Number(page) - 1) * Number(pageSize))
    const [rows] = await pool.query(sql, params)
    const [[{ total }]] = await pool.query('SELECT COUNT(*) as total FROM expense_records WHERE creator_id = ?', [userId])
    res.json({ code: 0, data: { list: rows, total, page: Number(page), pageSize: Number(pageSize) } })
  } catch (err) { next(err) }
})

// POST /api/minip/enterprise/expenses - 提交报销
router.post('/enterprise/expenses', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { record_no, expense_date, category, amount, payment_method, description, payee } = req.body
    if (!record_no || !expense_date || !category || !amount) return res.json({ code: 400, msg: '缺少必填字段' })
    const [r] = await pool.query(
      `INSERT INTO expense_records (record_no, expense_date, category, amount, payment_method, description, payee, approval_status, creator_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())`,
      [record_no, expense_date, category, amount, payment_method || 'cash', description || '', payee || '', userId]
    )
    res.json({ code: 0, data: { id: r.insertId } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/invoices - 发票列表
router.get('/enterprise/invoices', auth, async (req, res, next) => {
  try {
    const { type, page = 1, pageSize = 20 } = req.query
    const userId = req.user.id
    let sql = `SELECT id, invoice_no, invoice_code, invoice_type, direction, invoice_date, seller_name, total_amount, tax_amount, status, created_at FROM invoices WHERE creator_id = ?`
    const params = [userId]
    if (type) { sql += ' AND invoice_type = ?'; params.push(type) }
    sql += ' ORDER BY invoice_date DESC LIMIT ? OFFSET ?'
    params.push(Number(pageSize), (Number(page) - 1) * Number(pageSize))
    const [rows] = await pool.query(sql, params)
    const [[{ total }]] = await pool.query('SELECT COUNT(*) as total FROM invoices WHERE creator_id = ?', [userId])
    res.json({ code: 0, data: { list: rows, total } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/wallet - 钱包余额 + 流水
router.get('/enterprise/wallet', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [[wallet]] = await pool.query('SELECT * FROM member_wallet WHERE user_id = ?', [userId]).catch(() => [[null]])
    const [logs] = await pool.query('SELECT id, amount, type, balance_after, remark, created_at FROM wallet_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 20', [userId])
    res.json({ code: 0, data: { wallet: wallet || { balance: 0, frozen: 0, total_in: 0, total_out: 0 }, logs } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/budget - 预算看板（部门/本月汇总）
router.get('/enterprise/budget', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [[summary]] = await pool.query(
      `SELECT COALESCE(SUM(amount), 0) as this_month_expense, COUNT(*) as count FROM expense_records WHERE creator_id = ? AND MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())`,
      [userId]
    )
    const [[pending]] = await pool.query('SELECT COUNT(*) as cnt FROM expense_records WHERE creator_id = ? AND approval_status = "pending"', [userId])
    const [[approved]] = await pool.query('SELECT COUNT(*) as cnt FROM expense_records WHERE creator_id = ? AND approval_status = "approved"', [userId])
    res.json({
      code: 0,
      data: {
        this_month_expense: summary.this_month_expense,
        pending_count: pending.cnt,
        approved_count: approved.cnt,
        monthly_budget: 100000
      }
    })
  } catch (err) { next(err) }
})

// ===== OA 模块（复用主站 oa 表）=====

// GET /api/minip/enterprise/attendance - 我的考勤（本月）
router.get('/enterprise/attendance', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [rows] = await pool.query(
      `SELECT id, date as check_date, clock_in as check_in_time, clock_out as check_out_time, status, overtime_hours as work_hours FROM attendance WHERE user_id = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE()) ORDER BY date DESC LIMIT 30`,
      [userId]
    )
    const [[stats]] = await pool.query(
      `SELECT SUM(CASE WHEN status='normal' THEN 1 ELSE 0 END) as normal_days, SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late_days, SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent_days, SUM(overtime_hours) as total_hours FROM attendance WHERE user_id = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())`,
      [userId]
    )
    res.json({ code: 0, data: { list: rows, stats } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/approvals - 待我审批 + 我提交的审批
router.get('/enterprise/approvals', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { type = 'pending' } = req.query
    let sql, params
    if (type === 'pending') {
      // 复用主站 approvals 表
      sql = `SELECT id, title, type, status, applicant_id as creator_id, created_at FROM approvals WHERE status = 'pending' ORDER BY created_at DESC LIMIT 30`
      params = [userId]
    } else if (type === 'submitted') {
      sql = `SELECT id, title, type, status, applicant_id as creator_id, created_at FROM approvals WHERE applicant_id = ? ORDER BY created_at DESC LIMIT 30`
      params = [userId]
    } else {
      sql = `SELECT id, title, type, status, applicant_id as creator_id, created_at FROM approvals WHERE applicant_id = ? OR applicant_id = ? ORDER BY created_at DESC LIMIT 30`
      params = [userId, userId]
    }
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, type } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/schedule - 我的日程
router.get('/enterprise/schedule', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { date } = req.query
    // 复用 oa_schedules 表（如果存在）否则用 approvals 凑
    const target = date || new Date().toISOString().split('T')[0]
    let scheduleList = []
    let meetingList = []
    try {
      const [schedules] = await pool.query(
        `SELECT id, title, start_time, end_time, description, type FROM oa_schedules WHERE user_id = ? AND DATE(start_time) = ? ORDER BY start_time ASC`,
        [userId, target]
      )
      scheduleList = schedules
    } catch (e) { /* 表可能不存在 */ }
    try {
      const [meetings] = await pool.query(
        `SELECT id, title, start_time, end_time, location, status FROM oa_meetings WHERE (host_id = ? OR JSON_CONTAINS(attendees, JSON_QUOTE(?))) AND DATE(start_time) >= ? ORDER BY start_time ASC LIMIT 20`,
        [userId, String(userId), target]
      )
      meetingList = meetings
    } catch (e) { /* 表可能不存在 */ }
    res.json({ code: 0, data: { date: target, schedules: scheduleList, meetings: meetingList } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/documents - 我的文档
router.get('/enterprise/documents', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { category } = req.query
    let sql = `SELECT id, title, category, file_size, created_at, updated_at FROM oa_documents WHERE 1=0 ORDER BY updated_at DESC LIMIT 50`
    const params = [userId, userId]
    if (category) { sql += ' AND category = ?'; params.push(category) }
    try {
      const [rows] = await pool.query(sql, params)
      res.json({ code: 0, data: { list: rows } })
    } catch (e) {
      res.json({ code: 0, data: { list: [] } })
    }
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/work-logs - 工作日志（用 orders 凑，没有就空）
router.get('/enterprise/work-logs', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    try {
      const [rows] = await pool.query(
        `SELECT id, log_date, title, content, hours, type FROM oa_work_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT 30`,
        [userId]
      )
      res.json({ code: 0, data: { list: rows } })
    } catch (e) {
      res.json({ code: 0, data: { list: [] } })
    }
  } catch (err) { next(err) }
})

// ===== 营销模块 =====

// GET /api/minip/enterprise/seckill - 限时秒杀列表
router.get('/enterprise/seckill', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, name, description, start_time, end_time, status FROM seckill_activities WHERE status = 'active' AND end_time > NOW() ORDER BY start_time ASC LIMIT 20`
    )
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/coupons - 我的优惠券
router.get('/enterprise/coupons', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [rows] = await pool.query(
      `SELECT id, coupon_id, coupon_name as name, type, money as amount, min_price as threshold, status, used_at, valid_end as expire_at FROM user_coupons WHERE user_id = ? ORDER BY valid_end DESC LIMIT 50`,
      [userId]
    )
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/referrals - 我的邀请记录
router.get('/enterprise/referrals', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [rows] = await pool.query(
      `SELECT id, invited_phone, invited_name, status, order_amount as reward_amount, paid_at as created_at FROM referral_records WHERE referrer_h5_user_id = ? ORDER BY created_at DESC LIMIT 30`,
      [userId]
    )
    const [[stats]] = await pool.query(
      `SELECT COUNT(*) as total_invites, COALESCE(SUM(order_amount), 0) as total_reward FROM referral_records WHERE referrer_h5_user_id = ?`,
      [userId]
    )
    res.json({ code: 0, data: { list: rows, stats } })
  } catch (err) { next(err) }
})

// ===== HR 模块（4 张表新建）=====

// GET /api/minip/enterprise/hr/employees - 通讯录
router.get('/enterprise/hr/employees', auth, async (req, res, next) => {
  try {
    const { dept, keyword } = req.query
    let sql = `SELECT id, employee_no, name, position, department, phone, email, avatar, status FROM minip_hr_employees WHERE status = 'active'`
    const params = []
    if (dept) { sql += ' AND department = ?'; params.push(dept) }
    if (keyword) { sql += ' AND (name LIKE ? OR employee_no LIKE ? OR phone LIKE ?)'; params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`) }
    sql += ' ORDER BY department, name LIMIT 100'
    const [rows] = await pool.query(sql, params)
    const [[{ total }]] = await pool.query('SELECT COUNT(*) as total FROM minip_hr_employees WHERE status = "active"')
    res.json({ code: 0, data: { list: rows, total } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/hr/recruit - 招聘岗位
router.get('/enterprise/hr/recruit', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, title, department, location, salary_range, headcount, status, published_at, expired_at FROM minip_hr_recruit WHERE status = 'open' ORDER BY published_at DESC LIMIT 20`
    )
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

// POST /api/minip/enterprise/hr/recruit/:id/apply - 投递简历
router.post('/enterprise/hr/recruit/:id/apply', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const userId = req.user.id
    const { name, phone, email, resume_url, cover_letter } = req.body
    await pool.query(
      `INSERT INTO minip_hr_applications (recruit_id, user_id, name, phone, email, resume_url, cover_letter, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted', NOW())`,
      [id, userId, name, phone, email, resume_url, cover_letter]
    )
    res.json({ code: 0, msg: '投递成功' })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/hr/payroll - 我的工资条
router.get('/enterprise/hr/payroll', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [rows] = await pool.query(
      `SELECT id, period, base_salary, bonus, deduction, net_salary, paid_at, status FROM minip_hr_payroll WHERE user_id = ? ORDER BY period DESC LIMIT 12`,
      [userId]
    )
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

// ============================================================
// 办公中心 — 按主站 rbac_menus + 用户角色动态返回可见菜单
// 主站后台改 visible_to 字段 → minip 端自动同步
// ============================================================

// GET /api/minip/office/menus - 返回当前用户可见的办公中心菜单分组
router.get('/office/menus', auth, async (req, res, next) => {
  try {
    const userRole = (req.user.role || 'employee').toLowerCase()
    const [rows] = await pool.query(
      `SELECT id, name, icon, minip_group, minip_icon, minip_path, minip_sort, visible_to
       FROM rbac_menus
       WHERE parent_id = 100 AND status = 'enabled' AND visible = 'show'
       ORDER BY minip_group, minip_sort`
    )
    // 按 visible_to 过滤
    const visible = rows.filter((r) => {
      const set = (r.visible_to || '').split(',').map((s) => s.trim().toLowerCase()).filter(Boolean)
      return set.includes(userRole) || set.includes('all')
    })
    // 按 group 分组
    const groups = {}
    const groupIcons = {
      finance: 'account_balance',
      hr: 'groups',
      oa: 'business_center',
      marketing: 'campaign'
    }
    const groupTitles = {
      finance: '财务',
      hr: '人力',
      oa: '协同',
      marketing: '营销'
    }
    visible.forEach((r) => {
      const g = r.minip_group
      if (!groups[g]) groups[g] = []
      groups[g].push({
        key: r.name,
        icon: r.minip_icon,
        path: r.minip_path,
        sort: r.minip_sort
      })
    })
    const result = Object.keys(groups).map((g) => ({
      id: g,
      title: groupTitles[g] || g,
      icon: groupIcons[g] || 'apps',
      items: groups[g].sort((a, b) => a.sort - b.sort)
    }))
    res.json({ code: 0, data: { role: userRole, groups: result } })
  } catch (err) { next(err) }
})

// GET /api/minip/office/work-logs - 我的工作日志列表（用 work_logs 表，统一主站）
router.get('/office/work-logs', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { page = 1, pageSize = 20 } = req.query
    const offset = (Number(page) - 1) * Number(pageSize)
    const [rows] = await pool.query(
      `SELECT w.id, w.submit_date, w.content, w.today_work, w.tomorrow_plan, w.issues, w.status, w.log_type, w.created_at,
              u.name as user_name
       FROM work_logs w
       LEFT JOIN users u ON w.user_id = u.id
       WHERE w.user_id = ?
       ORDER BY w.submit_date DESC, w.id DESC
       LIMIT ? OFFSET ?`,
      [userId, Number(pageSize), offset]
    )
    const [[{ total }]] = await pool.query(
      'SELECT COUNT(*) as total FROM work_logs WHERE user_id = ?',
      [userId]
    )
    res.json({ code: 0, data: { list: rows, total: Number(total) } })
  } catch (err) { next(err) }
})

// POST /api/minip/office/work-logs - 提交工作日志
router.post('/office/work-logs', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { submit_date, content, today_work, tomorrow_plan, issues, log_type = 'work' } = req.body
    if (!content && !today_work) return res.status(400).json({ code: 400, message: '日志内容不能为空' })
    const [r] = await pool.query(
      `INSERT INTO work_logs (user_id, log_type, submit_date, content, today_work, tomorrow_plan, issues, status)
       VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted')`,
      [userId, log_type, submit_date || new Date().toISOString().slice(0, 10), content || today_work, today_work || content, tomorrow_plan || null, issues || null]
    )
    res.json({ code: 0, data: { id: r.insertId } })
  } catch (err) {
    console.error('[minip] work-logs create error:', err?.message || err)
    res.status(500).json({ code: 500, message: err?.message || '创建日志失败' })
  }
})

// GET /api/minip/office/tasks - 我的任务列表
router.get('/office/tasks', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { status, page = 1, pageSize = 20 } = req.query
    const offset = (Number(page) - 1) * Number(pageSize)
    let sql = `SELECT t.id, t.title, t.description, t.status, t.priority, t.due_date, t.created_at, t.assigned_to, t.assigned_by,
                      u.name as assignee_name, b.name as assigner_name
               FROM tasks t
               LEFT JOIN users u ON t.assigned_to = u.id
               LEFT JOIN users b ON t.assigned_by = b.id
               WHERE t.assigned_to = ?`
    const params = [userId]
    if (status) {
      sql += ' AND t.status = ?'
      params.push(status)
    }
    sql += ' ORDER BY (CASE t.priority WHEN \'high\' THEN 1 WHEN \'medium\' THEN 2 WHEN \'low\' THEN 3 ELSE 4 END), t.due_date ASC LIMIT ? OFFSET ?'
    params.push(Number(pageSize), offset)
    const [rows] = await pool.query(sql, params)
    const [[{ total }]] = await pool.query(
      'SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ?' + (status ? ' AND status = ?' : ''),
      status ? [userId, status] : [userId]
    )
    res.json({ code: 0, data: { list: rows, total: Number(total) } })
  } catch (err) { next(err) }
})

// POST /api/minip/office/tasks - 创建任务（任何人都能给自己创建待办）
router.post('/office/tasks', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { title, description, priority = 'medium', due_date } = req.body
    if (!title) return res.status(400).json({ code: 400, message: '任务标题不能为空' })
    const [r] = await pool.query(
      `INSERT INTO tasks (title, description, priority, status, assigned_to, created_by, assigned_by, due_date, is_new)
       VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, 1)`,
      [title, description || null, priority, userId, userId, userId, due_date || null]
    )
    res.json({ code: 0, data: { id: r.insertId } })
  } catch (err) {
    console.error('[minip] tasks create error:', err?.message || err)
    res.status(500).json({ code: 500, message: err?.message || '创建任务失败' })
  }
})

// PUT /api/minip/office/tasks/:id - 更新任务状态/内容
router.put('/office/tasks/:id', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const taskId = Number(req.params.id)
    const { status, completion_note, title, description, priority, due_date } = req.body
    const [[task]] = await pool.query('SELECT * FROM tasks WHERE id = ?', [taskId])
    if (!task) return res.status(404).json({ code: 404, message: '任务不存在' })
    if (task.assigned_to !== userId && task.assigned_by !== userId) {
      return res.status(403).json({ code: 403, message: '无权操作此任务' })
    }
    const updates= []
    const params= []
    if (status !== undefined) { updates.push('status = ?'); params.push(status) }
    if (completion_note !== undefined) { updates.push('completion_note = ?'); params.push(completion_note) }
    if (title) { updates.push('title = ?'); params.push(title) }
    if (description !== undefined) { updates.push('description = ?'); params.push(description) }
    if (priority) { updates.push('priority = ?'); params.push(priority) }
    if (due_date !== undefined) { updates.push('due_date = ?'); params.push(due_date) }
    if (!updates.length) return res.json({ code: 0, message: 'no changes' })
    params.push(taskId)
    await pool.query(`UPDATE tasks SET ${updates.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE /api/minip/office/tasks/:id - 删除任务（仅创建者/被分派人）
router.delete('/office/tasks/:id', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const taskId = Number(req.params.id)
    const [[task]] = await pool.query('SELECT * FROM tasks WHERE id = ?', [taskId])
    if (!task) return res.status(404).json({ code: 404, message: '任务不存在' })
    if (task.assigned_to !== userId && task.assigned_by !== userId) {
      return res.status(403).json({ code: 403, message: '无权删除' })
    }
    await pool.query('DELETE FROM tasks WHERE id = ?', [taskId])
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

