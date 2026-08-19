/**
 * jobsite-extra.js — 工地维度全功能路由 (闭环)
 *
 * 2026-08-16 新增, 一站式解决 8 大缺口:
 *   1. tasks 挂 jobsite_id (PATCH/POST 接口扩展)
 *   2. 工地阶段 phases CRUD
 *   3. 里程碑 milestones CRUD + 完成
 *   4. 预算 budgets CRUD
 *   5. 问题清单 issues CRUD
 *   6. 交付 handover CRUD
 *   7. 工地核算报表 (利润 = 合同 - 采购 - 工资 - 报销)
 *   8. 工人档案 worker_profiles 创建 (HK 此前为空)
 *
 * 路径前缀: /api/jobsite-extra
 */
import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'

const router = express.Router()
router.use(auth)

// ============================================================
// 1. 工地任务 (扩展 /api/tasks, 让前端能传 jobsite_id)
//    这里不重写 POST /api/tasks (老逻辑稳定)
//    提供单独的 GET /api/jobsite-extra/jobsites/:id/tasks 视图
// ============================================================

// GET /api/jobsite-extra/jobsites/:id/tasks — 工地任务列表
router.get('/jobsites/:id/tasks', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT t.*, u.name AS assignee_name, c.name AS creator_name
       FROM tasks t
       LEFT JOIN users u ON u.id = t.assigned_to
       LEFT JOIN users c ON c.id = t.created_by
       WHERE t.jobsite_id = ?
       ORDER BY t.due_date ASC, t.priority DESC, t.id DESC`,
      [req.params.id]
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

// GET /api/jobsite-extra/jobsites/:id/attendance — 工地考勤
router.get('/jobsites/:id/attendance', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT a.*, u.name AS user_name
       FROM attendance a
       JOIN users u ON u.id = a.user_id
       WHERE a.jobsite_id = ?
       ORDER BY a.date DESC, a.id DESC
       LIMIT 500`,
      [req.params.id]
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

// GET /api/jobsite-extra/jobsites/:id/work-logs — 工地日报
router.get('/jobsites/:id/work-logs', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT w.*, u.name AS user_name
       FROM work_logs w
       JOIN users u ON u.id = w.user_id
       WHERE w.jobsite_id = ?
       ORDER BY w.date DESC, w.id DESC
       LIMIT 200`,
      [req.params.id]
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

// ============================================================
// 2. 工地阶段 — phases
// ============================================================

// GET /api/jobsite-extra/jobsites/:id/phases — 工地阶段列表
router.get('/jobsites/:id/phases', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT * FROM jobsite_phases WHERE jobsite_id = ? ORDER BY sequence ASC, id ASC`,
      [req.params.id]
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

// POST /api/jobsite-extra/jobsites/:id/phases — 新增阶段
router.post('/jobsites/:id/phases', async (req, res, next) => {
  try {
    const { phase_type, name, sequence, planned_start, planned_end, responsible_user_id, worker_ids, notes } = req.body || {}
    if (!phase_type || !name) return res.status(400).json({ code: 400, message: 'phase_type/name 必填' })
    const [result] = await pool.query(
      `INSERT INTO jobsite_phases (jobsite_id, phase_type, name, sequence, planned_start, planned_end, responsible_user_id, worker_ids, notes)
       VALUES (?,?,?,?,?,?,?,?,?)`,
      [req.params.id, phase_type, name, sequence || 0, planned_start || null, planned_end || null,
       responsible_user_id || null, worker_ids ? JSON.stringify(worker_ids) : null, notes || null]
    )
    res.json({ code: 0, data: { id: result.insertId } })
  } catch (e) { next(e) }
})

// PATCH /api/jobsite-extra/phases/:phaseId — 更新阶段 (含进度/状态)
router.patch('/phases/:phaseId', async (req, res, next) => {
  try {
    const allowed = ['phase_type', 'name', 'sequence', 'planned_start', 'planned_end', 'actual_start', 'actual_end',
                     'progress_percent', 'status', 'responsible_user_id', 'worker_ids', 'notes']
    const fields = [], params = []
    for (const k of allowed) {
      if (req.body[k] !== undefined) {
        fields.push(`${k} = ?`)
        params.push(k === 'worker_ids' && typeof req.body[k] === 'object' ? JSON.stringify(req.body[k]) : req.body[k])
      }
    }
    if (!fields.length) return res.status(400).json({ code: 400, message: '无字段可更新' })
    params.push(req.params.phaseId)
    await pool.query(`UPDATE jobsite_phases SET ${fields.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: { id: Number(req.params.phaseId) } })
  } catch (e) { next(e) }
})

// DELETE /api/jobsite-extra/phases/:phaseId — 删除阶段
router.delete('/phases/:phaseId', async (req, res, next) => {
  try {
    await pool.query('DELETE FROM jobsite_milestones WHERE phase_id = ?', [req.params.phaseId])
    await pool.query('DELETE FROM jobsite_phases WHERE id = ?', [req.params.phaseId])
    res.json({ code: 0, data: { id: Number(req.params.phaseId) } })
  } catch (e) { next(e) }
})

// ============================================================
// 3. 里程碑 — milestones
// ============================================================

// GET /api/jobsite-extra/jobsites/:id/milestones
router.get('/jobsites/:id/milestones', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT m.*, p.name AS phase_name, u.name AS signer_name
       FROM jobsite_milestones m
       LEFT JOIN jobsite_phases p ON p.id = m.phase_id
       LEFT JOIN users u ON u.id = m.signed_off_by
       WHERE m.jobsite_id = ?
       ORDER BY m.due_date ASC, m.id ASC`,
      [req.params.id]
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

// POST /api/jobsite-extra/jobsites/:id/milestones
router.post('/jobsites/:id/milestones', async (req, res, next) => {
  try {
    const { phase_id, name, description, due_date } = req.body || {}
    if (!name) return res.status(400).json({ code: 400, message: 'name 必填' })
    const [result] = await pool.query(
      `INSERT INTO jobsite_milestones (jobsite_id, phase_id, name, description, due_date)
       VALUES (?,?,?,?,?)`,
      [req.params.id, phase_id || null, name, description || null, due_date || null]
    )
    res.json({ code: 0, data: { id: result.insertId } })
  } catch (e) { next(e) }
})

// PATCH /api/jobsite-extra/milestones/:milestoneId — 完成里程碑 (核心: 工地验收)
router.patch('/milestones/:milestoneId', async (req, res, next) => {
  try {
    const { status, photos, notes } = req.body || {}
    const updates = [], params = []
    if (status) {
      updates.push('status = ?')
      params.push(status)
      if (status === 'completed') {
        updates.push('completed_at = NOW()', 'signed_off_by = ?', 'signed_off_at = NOW()')
        params.push(req.user.id, req.user.id)
      }
    }
    if (photos !== undefined) { updates.push('photos = ?'); params.push(typeof photos === 'object' ? JSON.stringify(photos) : photos) }
    if (notes !== undefined) { updates.push('description = CONCAT(IFNULL(description,\"\"), \"\\n\", ?)'); params.push(notes) }
    if (!updates.length) return res.status(400).json({ code: 400, message: '无字段可更新' })
    params.push(req.params.milestoneId)
    await pool.query(`UPDATE jobsite_milestones SET ${updates.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: { id: Number(req.params.milestoneId) } })
  } catch (e) { next(e) }
})

// DELETE /api/jobsite-extra/milestones/:milestoneId
router.delete('/milestones/:milestoneId', async (req, res, next) => {
  try {
    await pool.query('DELETE FROM jobsite_milestones WHERE id = ?', [req.params.milestoneId])
    res.json({ code: 0, data: { id: Number(req.params.milestoneId) } })
  } catch (e) { next(e) }
})

// ============================================================
// 4. 预算 — budgets
// ============================================================

router.get('/jobsites/:id/budgets', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT * FROM jobsite_budgets WHERE jobsite_id = ? ORDER BY category, id`,
      [req.params.id]
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

router.post('/jobsites/:id/budgets', async (req, res, next) => {
  try {
    const { category, name, planned_amount, notes } = req.body || {}
    if (!category || !name) return res.status(400).json({ code: 400, message: 'category/name 必填' })
    const [result] = await pool.query(
      `INSERT INTO jobsite_budgets (jobsite_id, category, name, planned_amount, notes, created_by)
       VALUES (?,?,?,?,?,?)`,
      [req.params.id, category, name, planned_amount || 0, notes || null, req.user.id]
    )
    res.json({ code: 0, data: { id: result.insertId } })
  } catch (e) { next(e) }
})

router.patch('/budgets/:budgetId', async (req, res, next) => {
  try {
    const allowed = ['category', 'name', 'planned_amount', 'actual_amount', 'notes']
    const fields = [], params = []
    for (const k of allowed) if (req.body[k] !== undefined) { fields.push(`${k} = ?`); params.push(req.body[k]) }
    if (!fields.length) return res.status(400).json({ code: 400, message: '无字段' })
    params.push(req.params.budgetId)
    await pool.query(`UPDATE jobsite_budgets SET ${fields.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: { id: Number(req.params.budgetId) } })
  } catch (e) { next(e) }
})

router.delete('/budgets/:budgetId', async (req, res, next) => {
  try {
    await pool.query('DELETE FROM jobsite_budgets WHERE id = ?', [req.params.budgetId])
    res.json({ code: 0, data: { id: Number(req.params.budgetId) } })
  } catch (e) { next(e) }
})

// ============================================================
// 5. 问题清单 — issues
// ============================================================

router.get('/jobsites/:id/issues', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT i.*, r.name AS reporter_name, a.name AS assignee_name, p.name AS phase_name
       FROM jobsite_issues i
       LEFT JOIN users r ON r.id = i.reported_by
       LEFT JOIN users a ON a.id = i.assigned_to
       LEFT JOIN jobsite_phases p ON p.id = i.phase_id
       WHERE i.jobsite_id = ?
       ORDER BY FIELD(i.status,'open','in_progress','resolved','closed'), FIELD(i.severity,'critical','high','medium','low'), i.id DESC`,
      [req.params.id]
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

router.post('/jobsites/:id/issues', async (req, res, next) => {
  try {
    const { phase_id, title, description, severity, assigned_to, photos } = req.body || {}
    if (!title) return res.status(400).json({ code: 400, message: 'title 必填' })
    const [result] = await pool.query(
      `INSERT INTO jobsite_issues (jobsite_id, phase_id, title, description, severity, reported_by, assigned_to, photos)
       VALUES (?,?,?,?,?,?,?,?)`,
      [req.params.id, phase_id || null, title, description || null, severity || 'medium', req.user.id,
       assigned_to || null, photos ? JSON.stringify(photos) : null]
    )
    res.json({ code: 0, data: { id: result.insertId } })
  } catch (e) { next(e) }
})

router.patch('/issues/:issueId', async (req, res, next) => {
  try {
    const allowed = ['title', 'description', 'severity', 'status', 'assigned_to', 'photos']
    const fields = [], params = []
    for (const k of allowed) if (req.body[k] !== undefined) {
      fields.push(`${k} = ?`)
      params.push(k === 'photos' && typeof req.body[k] === 'object' ? JSON.stringify(req.body[k]) : req.body[k])
    }
    if (req.body.status === 'resolved') {
      fields.push('resolved_at = NOW()')
    }
    if (!fields.length) return res.status(400).json({ code: 400, message: '无字段' })
    params.push(req.params.issueId)
    await pool.query(`UPDATE jobsite_issues SET ${fields.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: { id: Number(req.params.issueId) } })
  } catch (e) { next(e) }
})

router.delete('/issues/:issueId', async (req, res, next) => {
  try {
    await pool.query('DELETE FROM jobsite_issues WHERE id = ?', [req.params.issueId])
    res.json({ code: 0, data: { id: Number(req.params.issueId) } })
  } catch (e) { next(e) }
})

// ============================================================
// 6. 交付 — handover
// ============================================================

router.get('/jobsites/:id/handover', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT h.*, u.name AS signer_name
       FROM jobsite_handovers h
       LEFT JOIN users u ON u.id = h.signed_off_by
       WHERE h.jobsite_id = ?`,
      [req.params.id]
    )
    res.json({ code: 0, data: rows[0] || null })
  } catch (e) { next(e) }
})

router.post('/jobsites/:id/handover', async (req, res, next) => {
  try {
    const { handover_date, client_name, client_phone, signature_photo, keys_count,
            has_water_leak_check, has_electric_check, has_cleaning_done, warranty_until, notes } = req.body || {}
    if (!handover_date) return res.status(400).json({ code: 400, message: 'handover_date 必填' })
    // upsert
    await pool.query(
      `INSERT INTO jobsite_handovers
       (jobsite_id, handover_date, client_name, client_phone, signature_photo, keys_count,
        has_water_leak_check, has_electric_check, has_cleaning_done, warranty_until, notes, signed_off_by)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE
         handover_date=VALUES(handover_date), client_name=VALUES(client_name), client_phone=VALUES(client_phone),
         signature_photo=VALUES(signature_photo), keys_count=VALUES(keys_count),
         has_water_leak_check=VALUES(has_water_leak_check), has_electric_check=VALUES(has_electric_check),
         has_cleaning_done=VALUES(has_cleaning_done), warranty_until=VALUES(warranty_until),
         notes=VALUES(notes), signed_off_by=VALUES(signed_off_by)`,
      [req.params.id, handover_date, client_name || null, client_phone || null, signature_photo || null,
       keys_count || 0, has_water_leak_check ? 1 : 0, has_electric_check ? 1 : 0, has_cleaning_done ? 1 : 0,
       warranty_until || null, notes || null, req.user.id]
    )
    // 同步更新 jobsites.status = 'completed'
    await pool.query(`UPDATE jobsites SET status = 'completed', actual_end_date = ? WHERE id = ?`,
                     [handover_date, req.params.id])
    res.json({ code: 0, data: { jobsite_id: Number(req.params.id) } })
  } catch (e) { next(e) }
})

// ============================================================
// 7. 工地核算报表 (利润计算)
//    合同金额 - 采购 - 报销 - 工资 = 利润
// ============================================================

router.get('/jobsites/:id/profit', async (req, res, next) => {
  try {
    const jobsiteId = req.params.id
    // 1. 合同金额
    const [[js]] = await pool.query('SELECT contract_amount, name FROM jobsites WHERE id = ?', [jobsiteId])
    // 2. 采购 (purchase_costs)
    const [[purchase]] = await pool.query(
      'SELECT COALESCE(SUM(total_amount), 0) AS total FROM purchase_costs WHERE jobsite_id = ?',
      [jobsiteId]
    )
    // 3. 报销 (expense_records)
    const [[expense]] = await pool.query(
      'SELECT COALESCE(SUM(amount), 0) AS total FROM expense_records WHERE jobsite_id = ?',
      [jobsiteId]
    )
    // 4. 工资 (payment_records)
    const [[payment]] = await pool.query(
      'SELECT COALESCE(SUM(amount), 0) AS total FROM payment_records WHERE jobsite_id = ?',
      [jobsiteId]
    )
    // 5. 回款 (income_expense_records type=income)
    const [[income]] = await pool.query(
      `SELECT COALESCE(SUM(amount), 0) AS total FROM income_expense_records
       WHERE jobsite_id = ? AND type = 'income'`,
      [jobsiteId]
    )
    // 6. 预算总额 + 已花费
    const [[budget]] = await pool.query(
      `SELECT COALESCE(SUM(planned_amount), 0) AS planned,
              COALESCE(SUM(actual_amount), 0) AS actual
       FROM jobsite_budgets WHERE jobsite_id = ?`,
      [jobsiteId]
    )
    // 7. 工人考勤汇总
    const [[att]] = await pool.query(
      `SELECT COUNT(DISTINCT user_id) AS workers,
              COUNT(*) AS days,
              COALESCE(SUM(overtime_hours), 0) AS overtime_hours
       FROM attendance WHERE jobsite_id = ?`,
      [jobsiteId]
    )

    const revenue = Number(js?.contract_amount || 0)
    const costPurchase = Number(purchase.total || 0)
    const costExpense = Number(expense.total || 0)
    const costPayment = Number(payment.total || 0)
    const collected = Number(income.total || 0)
    const totalCost = costPurchase + costExpense + costPayment
    const profit = revenue - totalCost
    const profitRate = revenue > 0 ? (profit / revenue * 100) : 0
    const outstanding = revenue - collected

    res.json({
      code: 0,
      data: {
        jobsite: { id: Number(jobsiteId), name: js?.name, contract_amount: revenue },
        revenue,
        collected,
        outstanding,
        costs: {
          purchase: costPurchase,
          expense: costExpense,
          payment: costPayment,
          total: totalCost,
        },
        profit,
        profit_rate: Number(profitRate.toFixed(2)),
        budget: {
          planned: Number(budget.planned || 0),
          actual: Number(budget.actual || 0),
          variance: Number((budget.planned - budget.actual).toFixed(2)),
        },
        attendance: {
          workers: Number(att.workers || 0),
          days: Number(att.days || 0),
          overtime_hours: Number(att.overtime_hours || 0),
        },
      },
    })
  } catch (e) { next(e) }
})

// ============================================================
// 8. 工人档案创建 (HK 此前为空)
//    POST /api/jobsite-extra/workers — 快速创建工人档案
// ============================================================

router.post('/workers', async (req, res, next) => {
  try {
    const { user_id, skills, skill_level, hourly_rate, monthly_salary, payment_type,
            id_card, emergency_contact, emergency_phone, notes } = req.body || {}
    if (!user_id) return res.status(400).json({ code: 400, message: 'user_id 必填 (用户必须先注册)' })

    // 检查 user 存在
    const [[u]] = await pool.query('SELECT id, name, status FROM users WHERE id = ?', [user_id])
    if (!u) return res.status(400).json({ code: 400, message: '用户不存在' })

    // 检查 worker_profile 是否已存在
    const [[existing]] = await pool.query('SELECT id FROM worker_profiles WHERE user_id = ?', [user_id])
    if (existing) return res.status(400).json({ code: 400, message: '该用户已建档案', data: { id: existing.id } })

    const [result] = await pool.query(
      `INSERT INTO worker_profiles (user_id, skills, skill_level, hourly_rate, monthly_salary, payment_type,
        id_card, emergency_contact, emergency_phone, notes, hired_at, employment_status)
       VALUES (?,?,?,?,?,?,?,?,?,?,CURDATE(),'active')`,
      [user_id, skills ? JSON.stringify(skills) : null, skill_level || 'rookie',
       hourly_rate || 0, monthly_salary || 0, payment_type || 'hourly',
       id_card || null, emergency_contact || null, emergency_phone || null, notes || null]
    )
    res.json({ code: 0, data: { id: result.insertId, user_name: u.name } })
  } catch (e) { next(e) }
})

// GET /api/jobsite-extra/workers — 工人列表
router.get('/workers', async (req, res, next) => {
  try {
    const { jobsite_id, q } = req.query
    const where = ['wp.employment_status = \'active\'']
    const params = []
    if (jobsite_id) { where.push('wp.current_jobsite_id = ?'); params.push(jobsite_id) }
    if (q) { where.push('(u.name LIKE ? OR u.phone LIKE ?)'); params.push(`%${q}%`, `%${q}%`) }
    const [rows] = await pool.query(
      `SELECT wp.*, u.name AS user_name, u.phone AS user_phone, j.name AS jobsite_name
       FROM worker_profiles wp
       JOIN users u ON u.id = wp.user_id
       LEFT JOIN jobsites j ON j.id = wp.current_jobsite_id
       WHERE ${where.join(' AND ')}
       ORDER BY wp.id DESC LIMIT 500`,
      params
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

export default router