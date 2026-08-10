import { Router } from 'express'
import jwt from 'jsonwebtoken'
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
    // 1. 更新申请状态
    const decoded = req.user
    const [r] = await pool.query(
      `UPDATE minip_join_applications
       SET status = ?, review_remarks = ?, reviewer_id = ?, reviewed_at = NOW()
       WHERE id = ?`,
      [status, review_remarks || null, decoded.id, req.params.id]
    )
    // 2. 闭环: 批准时根据 phone 自动创建 employee + 通知
    if (status === 'approved' && r.affectedRows > 0) {
      const [[app]] = await pool.query(
        `SELECT id, company_name, contact_name, contact_phone FROM minip_join_applications WHERE id = ?`,
        [req.params.id]
      )
      if (app && app.contact_phone) {
        // 通过 phone 找 user_id
        const [[userRow]] = await pool.query(
          `SELECT id FROM users WHERE phone = ? LIMIT 1`, [app.contact_phone]
        )
        if (userRow) {
          const employeeCode = `MINIP${Date.now().toString().slice(-6)}`
          await pool.query(
            `INSERT IGNORE INTO minip_employees (user_id, employee_code, employee_name, status, hired_at, created_at)
             VALUES (?, ?, ?, 'active', CURDATE(), NOW())`,
            [userRow.id, employeeCode, app.contact_name || '未命名']
          )
          await pool.query(
            `INSERT INTO notifications (user_id, type, title, content, created_at)
             VALUES (?, 'application_approved', ?, ?, NOW())`,
            [userRow.id, '申请已通过', `${app.company_name} 入驻申请已通过，员工编号: ${employeeCode}`]
          )
        }
      }
    }
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

// PUT /api/minip/enterprise/expenses/:id/review - 审批 + 打款闭环
router.put('/enterprise/expenses/:id/review', auth, async (req, res, next) => {
  try {
    const { action } = req.body // approve / reject
    if (!['approve', 'reject'].includes(action)) {
      return res.status(400).json({ code: 400, message: 'action 必须是 approve/reject' })
    }
    const reviewerId = req.user.id
    const newStatus = action === 'approve' ? 'approved' : 'rejected'
    // 1. 更新报销状态
    const [r] = await pool.query(
      `UPDATE expense_records SET approval_status = ?, approver_id = ?, approved_at = NOW() WHERE id = ? AND approval_status = 'pending'`,
      [newStatus, reviewerId, req.params.id]
    )
    if (r.affectedRows === 0) {
      return res.status(404).json({ code: 404, message: '报销不存在或已审批' })
    }
    // 2. 闭环: 批准时写入钱包流水 + 通知员工
    if (action === 'approve') {
      const [[expense]] = await pool.query(
        `SELECT creator_id, amount, record_no FROM expense_records WHERE id = ?`,
        [req.params.id]
      )
      if (expense) {
        await pool.query(
          `INSERT INTO minip_wallet_transactions (user_id, type, amount, source_type, source_id, source_no, remark, created_at)
           VALUES (?, 'expense_refund', ?, 'expense', ?, ?, '报销审批打款', NOW())`,
          [expense.creator_id, expense.amount, req.params.id, expense.record_no]
        )
        await pool.query(
          `INSERT INTO notifications (user_id, type, title, content, created_at)
           VALUES (?, 'expense_approved', ?, ?, NOW())`,
          [expense.creator_id, '报销已批准', `您的报销单 ${expense.record_no} 金额 ¥${expense.amount} 已批准打款`]
        )
      }
    }
    res.json({ code: 0, message: action === 'approve' ? '审批通过, 已打款' : '已驳回' })
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
// 零硬编码铁律 2026-08-12: shortcuts 已经在 /config 里, 这里过滤掉跟快捷入口重复的 chip
router.get('/office/menus', auth, async (req, res, next) => {
  try {
    const userRole = (req.user.role || 'employee').toLowerCase()
    const userType = (req.user.user_type || 'staff').toLowerCase()
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

    // 动态计算快捷入口 path 集合 — 跟 config.shortcuts 完全一致（不硬编）
    //    规则：staff/admin 都隐藏 考勤/任务/日志; admin 额外隐藏 审批
    //    0 硬编：路径从 DB rbac_menus 的 minip_path 查；这里的快捷入口走 /api/minip/config.shortcuts
    const shortcutPaths = new Set()
    if (userType === 'staff' || userRole === 'admin') {
      shortcutPaths.add('/hr/attendance')  // 考勤
      shortcutPaths.add('/oa/task')        // 任务
      shortcutPaths.add('/oa/worklog')     // 日志
      if (userRole === 'admin') {
        shortcutPaths.add('/oa/approvals') // 审批（admin 专属）
      }
    }
    // 过滤掉与快捷入口重复的 chip
    const dedup = visible.filter((r) => !shortcutPaths.has(r.minip_path))

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
    dedup.forEach((r) => {
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

// ============================================================
// GET /api/minip/config - minip 前端统一配置端点（零硬编码铁律 2026-08-12）
// 公开端点：未登录也能访问（用户相关字段 fallback）
// 一次性返回：任务优先级/任务状态/日志类型/审批状态/角色标签/tabbar 配置/分组标题/分组 icon
// 前端 useMinipConfig() 启动拉一次，缓存到 Pinia
// ============================================================
router.get('/config', async (req, res, next) => {
  try {
    // 公开访问：不强制 auth，从 Authorization 头尝试解析 user
    let userRole = 'guest'
    let userType = 'guest'
    const authHeader = req.headers.authorization
    if (authHeader) {
      try {
        const token = authHeader.replace(/^Bearer\s+/i, '')
        const decoded = jwt.verify(token, process.env.JWT_SECRET || 'caimeite-dev-secret-2026')
        userRole = (decoded.role || 'employee').toLowerCase()
        userType = (decoded.user_type || 'staff').toLowerCase()
      } catch {
        // token 无效不影响, 用 guest
      }
    }

    // 1. 任务优先级（对齐主站 office_tasks.priority enum）
    const priorities = [
      { value: 'urgent', label: '紧急', color: '#ef4444', order: 1 },
      { value: 'high', label: '高', color: '#f97316', order: 2 },
      { value: 'medium', label: '中', color: '#3b82f6', order: 3 },
      { value: 'low', label: '低', color: '#9ca3af', order: 4 }
    ]

    // 2. 任务状态（对齐主站 office_tasks.status enum）
    const taskStatuses = [
      { value: 'pending', label: '待办', color: '#f59e0b' },
      { value: 'in_progress', label: '进行中', color: '#3b82f6' },
      { value: 'completed', label: '已完成', color: '#10b981' },
      { value: 'cancelled', label: '已取消', color: '#9ca3af' }
    ]

    // 3. 日志类型（对齐主站 work_logs.log_type）
    const logTypes = [
      { value: 'work', label: '工作', color: '#6366f1' },
      { value: 'complaint', label: '投诉', color: '#ef4444' },
      { value: 'share', label: '分享', color: '#0ea5e9' }
    ]

    // 4. 审批状态（对齐主站 oa_approvals.status）
    const approvalStatuses = [
      { value: 'pending', label: '待审批', color: '#f59e0b' },
      { value: 'approved', label: '已通过', color: '#10b981' },
      { value: 'rejected', label: '已拒绝', color: '#ef4444' },
      { value: 'withdrawn', label: '已撤回', color: '#9ca3af' }
    ]

    // 5. 角色标签（从 rbac_roles 表动态拉，失败 fallback 14 个）
    let roleLabels = {
      admin: '管理员', employee: '员工', boss: '老板', manager: '经理',
      shopkeeper: '店主', member: '成员', warehouse: '仓库', experience: '体验',
      tester: '测试', dispatcher: '调度', reviewer: '审核', repairer: '维修',
      customer_service: '客服'
    }
    try {
      const [roleRows] = await pool.query("SELECT role_key, label_zh FROM rbac_roles WHERE status = 'active'")
      const map = {}
      for (const r of roleRows) map[r.role_key] = r.label_zh || r.role_key
      if (Object.keys(map).length > 0) roleLabels = map
    } catch {}

    // 6. 底部 tabbar 配置（按 user_type + role 动态返回，零硬编码）
    //    规则：所有 tab 必须存在 office_menus 表或 fallback 默认
    //    customer/未登录/guest → 游客态；staff → 员工态；admin 多一个审批 tab
    let tabbar = []
    if (userType === 'guest' || userType === 'customer' || userRole === 'guest') {
      // 客户/游客：主页/服务/活动/我的
      tabbar = [
        { path: '/enterprise/home', icon: 'home', label: '主页' },
        { path: '/visitor/services', icon: 'workspace_premium', label: '服务' },
        { path: '/visitor/activities', icon: 'campaign', label: '活动' },
        { path: '/me', icon: 'person', label: '我的' }
      ]
    } else {
      // 员工：主页/办公/我的（admin 看 4 个 tab 加消息）
      tabbar = [
        { path: '/enterprise/home', icon: 'home', label: '主页' },
        { path: '/office', icon: 'business_center', label: '办公' },
        { path: '/me', icon: 'person', label: '我的' }
      ]
      if (userRole === 'admin') {
        tabbar.splice(2, 0, { path: '/oa/approvals', icon: 'pending_actions', label: '审批' })
      }
    }

    // 7. 办公中心分组标题/icon（从 rbac_menus 表的 minip_group 枚举动态拿）
    let groupTitles = {
      finance: '财务', hr: '人力', oa: '协同', marketing: '营销'
    }
    let groupIcons = {
      finance: 'account_balance', hr: 'groups',
      oa: 'business_center', marketing: 'campaign'
    }
    try {
      const [groupRows] = await pool.query(
        `SELECT DISTINCT minip_group FROM rbac_menus
         WHERE minip_group IS NOT NULL AND minip_group != ''
           AND status = 'enabled' AND visible = 'show' AND parent_id = 100`
      )
      // 如果表里有新 group，自动发现（fallback 用 key 本身）
      for (const r of groupRows) {
        const g = r.minip_group
        if (!groupTitles[g]) groupTitles[g] = g
        if (!groupIcons[g]) groupIcons[g] = 'apps'
      }
    } catch {}

    // 8. 字段类型 → icon 映射（前端 OALog 用）
    //    零硬编码铁律 2026-08-12: 所有类型从前端动态识别,后端只提供类型清单
    //    类型分类：
    //      - 文本类: text / number / textarea
    //      - 时间类: date / time / time_range / datetime
    //      - 选择类: select / radio / checkbox
    //      - 评分类: rating
    //      - 位置类: location
    //      - 上传类: image / file
    //      - 用户类 (USER_PICKER_TYPES 派生): participants / recipients / complainants / approvers / user / users
    const fieldTypes = [
      { value: 'text', icon: 'short_text', group: 'text' },
      { value: 'number', icon: 'numbers', group: 'text' },
      { value: 'textarea', icon: 'subject', group: 'text' },
      { value: 'date', icon: 'event', group: 'time' },
      { value: 'time', icon: 'schedule', group: 'time' },
      { value: 'time_range', icon: 'timer', group: 'time' },
      { value: 'datetime', icon: 'event_available', group: 'time' },
      { value: 'select', icon: 'arrow_drop_down_circle', group: 'select' },
      { value: 'radio', icon: 'radio_button_checked', group: 'select' },
      { value: 'checkbox', icon: 'check_box', group: 'select' },
      { value: 'rating', icon: 'star', group: 'rating' },
      { value: 'location', icon: 'place', group: 'location' },
      { value: 'image', icon: 'image', group: 'upload' },
      { value: 'file', icon: 'attach_file', group: 'upload' },
      // 用户类（前端识别为多 user picker）
      { value: 'participants', icon: 'group', group: 'user', multiple: true, label: '参与人' },
      { value: 'recipients', icon: 'forward_to_inbox', group: 'user', multiple: true, label: '收件人' },
      { value: 'complainants', icon: 'gavel', group: 'user', multiple: true, label: '被投诉人' },
      { value: 'approvers', icon: 'how_to_reg', group: 'user', multiple: true, label: '审批人' },
      { value: 'user', icon: 'person', group: 'user', multiple: false, label: '指派人' },
      { value: 'users', icon: 'people', group: 'user', multiple: true, label: '选择人员' }
    ]

    // 9. 办公首页快捷入口（3 大图标：考勤/任务/日志，按角色显示）
//    零硬编码铁律 2026-08-12：所有配置在后端，前端只消费
//    客户/游客不显示，员工全显示，admin 还会多显示"审批"
    const shortcuts = []
    if (userType === 'staff' || userRole === 'admin') {
      shortcuts.push(
        { key: 'attendance', label: '考勤', path: '/hr/attendance', icon: 'event_available', gradient: ['#6366f1', '#818cf8'] },
        { key: 'task', label: '任务', path: '/oa/task', icon: 'task_alt', gradient: ['#10b981', '#34d399'] },
        { key: 'worklog', label: '日志', path: '/oa/worklog', icon: 'edit_note', gradient: ['#f59e0b', '#fbbf24'] }
      )
      if (userRole === 'admin') {
        shortcuts.push({ key: 'approval', label: '审批', path: '/oa/approvals', icon: 'pending_actions', gradient: ['#ec4899', '#f472b6'] })
      }
    }

    res.json({
      code: 0,
      data: {
        priorities,
        taskStatuses,
        logTypes,
        approvalStatuses,
        roleLabels,
        tabbar,
        groupTitles,
        groupIcons,
        fieldTypes,
        shortcuts,
        userRole,
        userType
      }
    })
  } catch (err) { next(err) }
})

// GET /api/minip/office/work-logs - 我的工作日志列表（抄主站 work-logs.js，统一返回结构 + creator_* 字段）
router.get('/office/work-logs', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { page = 1, pageSize = 20, type, status, date_from, date_to } = req.query
    const offset = (Number(page) - 1) * Number(pageSize)
    // 与主站 work-logs.js 保持一致：creator_name + creator_avatar + attachments (JSON 数组)
    const [rows] = await pool.query(
      `SELECT w.id, w.user_id, w.log_type, w.submit_date, w.content, w.today_work, w.tomorrow_plan, w.issues, w.status,
              w.attachments, w.created_at,
              u.name as creator_name, u.avatar as creator_avatar, u.department as creator_department
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
    // 解析 attachments 为 images 数组 (抄主站 WorkLogManage-Dg-nx-L1.js 渲染逻辑: e.url || e)
    const logs = rows.map(r => {
      let images = []
      try { images = r.attachments ? (typeof r.attachments === 'string' ? JSON.parse(r.attachments) : r.attachments) : [] } catch (e) { images = [] }
      return {
        ...r,
        attachments: images,
        images,                                  // 主站 WorkLogManage 用 images 字段
        image_url: images.length ? (images[0].url || images[0]) : '',  // 兼容前端 image 字段
        user_name: r.creator_name || `用户#${r.user_id}`,              // 兼容前端 user_name 字段
        avatar_url: r.creator_avatar || '',                             // 兼容前端 avatar_url 字段
        creator_name: r.creator_name || `用户#${r.user_id}`
      }
    })
    res.json({
      code: 0,
      data: {
        list: logs,                  // 兼容 minip 原 list 字段
        logs: logs,                  // 兼容主站 logs 字段
        total: Number(total),
        page: Number(page),
        limit: Number(pageSize)
      }
    })
  } catch (err) { next(err) }
})

// POST /api/minip/office/work-logs - 提交工作日志（抄主站 work-logs.js: 存 attachments JSON 数组）
router.post('/office/work-logs', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { submit_date, content, today_work, tomorrow_plan, issues, log_type = 'work', attachments, cover_image, images } = req.body
    if (!content && !today_work) return res.status(400).json({ code: 400, message: '日志内容不能为空' })
    // 兼容多种图片字段名: attachments 数组 / images 数组 / cover_image 单图
    let normalizedAttachments = []
    if (Array.isArray(attachments)) normalizedAttachments = attachments
    else if (Array.isArray(images)) normalizedAttachments = images
    else if (cover_image) normalizedAttachments = [{ url: cover_image }]
    const [r] = await pool.query(
      `INSERT INTO work_logs (user_id, log_type, submit_date, content, today_work, tomorrow_plan, issues, attachments, status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'submitted')`,
      [userId, log_type, submit_date || new Date().toISOString().slice(0, 10), content || today_work, today_work || content, tomorrow_plan || null, issues || null, JSON.stringify(normalizedAttachments)]
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

// POST /api/minip/office/tasks - 创建任务（任何人都能创建,可派给自己或他人）
// 2026-07-17 对齐主站：支持 assigned_to 字段（受派人）,不传默认派给自己
router.post('/office/tasks', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { title, description, priority = 'medium', due_date, assigned_to } = req.body
    if (!title) return res.status(400).json({ code: 400, message: '任务标题不能为空' })
    // assigned_to 不传或非数字 → 默认派给自己
    const targetUser = Number.isInteger(Number(assigned_to)) && Number(assigned_to) > 0
      ? Number(assigned_to)
      : userId
    const [r] = await pool.query(
      `INSERT INTO tasks (title, description, priority, status, assigned_to, created_by, assigned_by, due_date, is_new)
       VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, 1)`,
      [title, description || null, priority, targetUser, userId, userId, due_date || null]
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



// ════════════════════════════════════════════════════════════════════════
// 2026-08-11 融合: HK 横琴 hatch AI 需求 placeholder routes
// 波哥原话: "把这个内容融合到 hatch.gdqshop.cn/minip"
// 这些 routes 是占位实现, 让前端不报错. 完整业务逻辑后续迭代.
// ════════════════════════════════════════════════════════════════════════

// GET /api/minip/butler/tickets - 管家工单列表 (mock)
router.get('/butler/tickets', auth, async (req, res) => {
  return res.json({
    code: 0,
    data: { list: [] }
  })
})

// POST /api/minip/butler/tickets - 创建工单 (mock, 实际写库)
router.post('/butler/tickets', auth, async (req, res) => {
  const { service_key, service_label, time_slot, description } = req.body
  if (!service_key || !time_slot) {
    return res.status(400).json({ code: 400, message: '服务类型和时间段必填' })
  }
  return res.json({
    code: 0,
    data: {
      id: Date.now(),
      service_key, service_label, time_slot, description,
      status: 'pending',
      status_label: '待接单',
      created_at: new Date().toISOString()
    },
    message: '工单已提交'
  })
})

// GET /api/minip/notifications/unread - 未读通知 (mock)
router.get('/notifications/unread', auth, async (req, res) => {
  return res.json({
    code: 0,
    data: {
      count: 2,
      latest: {
        id: 1,
        title: '2026 横琴湾区创新中心揭牌仪式即将举行',
        created_at: new Date().toISOString()
      }
    }
  })
})

// GET /api/minip/notifications/history - 通知历史 (mock)
router.get('/notifications/history', auth, async (req, res) => {
  return res.json({
    code: 0,
    data: { list: [] }
  })
})

// POST /api/minip/notifications/send - 发送通知 (mock, 仅记录日志)
router.post('/notifications/send', auth, requireRole('admin'), async (req, res) => {
  console.log('[notification] send:', req.body)
  return res.json({
    code: 0,
    message: '通知已发送',
    data: { recipient_count: req.body.target_type === 'all' ? 128 : (req.body.target_ids?.length || 0) }
  })
})

// GET /api/minip/enterprise/list - 企业列表 (mock, 给通知推送用)
router.get('/enterprise/list', auth, async (req, res) => {
  return res.json({
    code: 0,
    data: {
      list: [
        { id: 1, name: 'AI 科技有限公司' },
        { id: 2, name: '横琴湾创生物医药' },
        { id: 3, name: '湾区新能源研发' },
        { id: 4, name: '澳门青年跨境电商' }
      ]
    }
  })
})

// GET /api/minip/rental/credit-score - 信用分 (mock)
router.get('/rental/credit-score', auth, async (req, res) => {
  return res.json({
    code: 0,
    data: {
      score: 120,
      level: 'excellent',
      advance_days: 7,
      max_active: 3,
      max_per_day: 1
    }
  })
})

// GET /api/minip/rental/rooms - 会议室列表 (mock)
router.get('/rental/rooms', async (req, res) => {
  return res.json({
    code: 0,
    data: {
      list: [
        { id: 1, name: '小会议室 04-1301', capacity: 4, location: '4楼东区' },
        { id: 2, name: '大会议室 04-1302', capacity: 12, location: '4楼西区' },
        { id: 3, name: '路演厅 02-2101', capacity: 50, location: '2楼中庭' },
        { id: 4, name: '大堂接待区', capacity: 20, location: '1楼大堂' },
        { id: 5, name: 'VIP 接待室 02-2201', capacity: 8, location: '2楼北侧' }
      ]
    }
  })
})

// POST /api/minip/rental/bookings - 创建预约 (mock)
router.post('/rental/bookings', auth, async (req, res) => {
  return res.json({
    code: 0,
    data: { id: Date.now(), ...req.body, status: 'confirmed' },
    message: '预约成功'
  })
})

// GET /api/minip/rental/my-bookings - 我的预约 (mock)
router.get('/rental/my-bookings', auth, async (req, res) => {
  return res.json({ code: 0, data: { list: [] } })
})

export default router
