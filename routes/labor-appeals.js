// labor-appeals.js — 异常申诉(打卡/工时/工资/任务评分)
// Layer 3.5: 扩展现有 approvals 表,type='labor_appeal'
// 路径前缀: /api/labor-appeals
// 权限: worker:read(自己读) / worker:write(自己提交) / eval:read(班组长看本组) / hr:write(HR 受理)
import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission, PERMISSIONS as P } from '../middleware/rbac.js'

const router = express.Router()
router.use(auth)

// 申诉类型 → 必填字段映射
const APPEAL_TYPES = {
  attendance: { label: '打卡申诉',  ref_field: 'attendance_id' },
  workhours:  { label: '工时申诉',  ref_field: 'worklog_id' },
  payroll:    { label: '工资申诉',  ref_field: 'finance_id' },
  evaluation: { label: '评分申诉',  ref_field: 'evaluation_id' },
  dispatch:   { label: '派工申诉',  ref_field: 'dispatch_id' }
}

// 申诉类型 → 默认审批人(单步,自动通过到这一步)
const DEFAULT_APPROVERS = {
  attendance: '工地主管',
  workhours:  '工地主管',
  payroll:    'HR',
  evaluation: '班组长',
  dispatch:   '工地主管'
}

// ============================================================
// 1. 提交申诉 — POST /api/labor-appeals
// body: { appeal_type, ref_id, reason, attachments?, expected_action? }
// 落 approvals(type='labor_appeal', type_code=appeal_type, applicant_id=req.user.id)
// ============================================================
router.post('/', requirePermission(P.WORKER_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { appeal_type, ref_id, reason, attachments, expected_action } = req.body || {}
    if (!appeal_type || !APPEAL_TYPES[appeal_type]) {
      return res.status(400).json({ code: 400, message: `appeal_type 必填且必须在 [${Object.keys(APPEAL_TYPES).join(',')}]` })
    }
    if (!reason || reason.length < 5) {
      return res.status(400).json({ code: 400, message: 'reason 必填且至少 5 字' })
    }

    // 找到当前用户的 worker_profile
    const [[wp]] = await conn.query('SELECT id FROM worker_profiles WHERE user_id = ?', [req.user.id])
    if (!wp) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '请先完成工人入职(POST /api/labor-hr/onboard)' })
    }
    const wpId = wp.id

    // 防重复:同一 ref_id + type 处于 pending 状态的不允许再次提交
    const [[dup]] = await conn.query(
      `SELECT id FROM approvals
       WHERE type = 'labor_appeal' AND type_code = ?
         AND JSON_EXTRACT(form_data, '$.ref_id') = ?
         AND applicant_id = ? AND status = 'pending'`,
      [appeal_type, String(ref_id || ''), req.user.id]
    )
    if (dup) {
      await conn.rollback()
      return res.status(409).json({ code: 409, message: '已有该记录的在途申诉', approval_id: dup.id })
    }

    const formData = {
      appeal_type, ref_id: ref_id || null, reason,
      expected_action: expected_action || null,
      worker_profile_id: wpId
    }

    const [result] = await conn.query(
      `INSERT INTO approvals
       (title, type, type_code, applicant, applicant_id, department, form_data, attachments, urgency, status, current_step)
       VALUES (?, 'labor_appeal', ?, ?, ?, ?, ?, ?, 'normal', 'pending', 1)`,
      [
        `${APPEAL_TYPES[appeal_type].label}:${reason.slice(0, 30)}`,
        appeal_type,
        req.user.name || `user#${req.user.id}`,
        req.user.id,
        req.user.department || null,
        JSON.stringify(formData),
        attachments ? JSON.stringify(attachments) : null
      ]
    )

    // 默认审批步骤(单步,主管/HR)
    const approver = DEFAULT_APPROVERS[appeal_type] || '工地主管'
    await conn.query(
      `INSERT INTO approval_steps (approval_id, step_order, role, assignee, status)
       VALUES (?, 1, ?, ?, 'pending')`,
      [result.insertId, approver, approver]
    )

    await conn.commit()
    res.json({ code: 0, data: { approval_id: result.insertId, appeal_type, status: 'pending' } })
  } catch (e) { try { await conn.rollback() } catch {}; next(e) }
  finally { conn.release() }
})

// ============================================================
// 2. 我的申诉列表 — GET /api/labor-appeals/mine
// ============================================================
router.get('/mine', requirePermission(P.WORKER_READ), async (req, res, next) => {
  try {
    const { status, appeal_type, page = 1, size = 20 } = req.query
    const where = ["a.type = 'labor_appeal'", 'a.applicant_id = ?']
    const params = [req.user.id]
    if (status) { where.push('a.status = ?'); params.push(status) }
    if (appeal_type) { where.push('a.type_code = ?'); params.push(appeal_type) }

    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) AS total FROM approvals a WHERE ${where.join(' AND ')}`, params)
    const offset = (Math.max(1, Number(page)) - 1) * Number(size)
    const [rows] = await pool.query(
      `SELECT a.id AS approval_id, a.title, a.type_code AS appeal_type, a.status,
              a.form_data, a.urgency, a.created_at, a.updated_at,
              a.current_step,
              (SELECT JSON_OBJECT('role', s.role, 'assignee', s.assignee, 'status', s.status, 'comment', s.comment, 'acted_at', s.acted_at)
                 FROM approval_steps s WHERE s.approval_id = a.id ORDER BY s.step_order DESC LIMIT 1) AS last_step
       FROM approvals a
       WHERE ${where.join(' AND ')}
       ORDER BY a.created_at DESC LIMIT ? OFFSET ?`,
      [...params, Number(size), offset]
    )

    const data = rows.map(r => ({
      ...r,
      form_data: r.form_data ? (typeof r.form_data === 'string' ? JSON.parse(r.form_data) : r.form_data) : null,
      last_step: r.last_step ? (typeof r.last_step === 'string' ? JSON.parse(r.last_step) : r.last_step) : null
    }))
    res.json({ code: 0, data: { list: data, total, page: Number(page), size: Number(size) } })
  } catch (e) { next(e) }
})

// ============================================================
// 3. 受理端 — GET /api/labor-appeals(HR/主管/班组长看)
// query: status, appeal_type, jobsite_id(只看我管的工地)
// ============================================================
router.get('/', requirePermission(P.HR_READ), async (req, res, next) => {
  try {
    const { status, appeal_type, jobsite_id, page = 1, size = 20 } = req.query
    const where = ["a.type = 'labor_appeal'"]
    const params = []
    if (status) { where.push('a.status = ?'); params.push(status) }
    if (appeal_type) { where.push('a.type_code = ?'); params.push(appeal_type) }
    if (jobsite_id) {
      where.push(`JSON_EXTRACT(a.form_data, '$.worker_profile_id') IN
                  (SELECT id FROM worker_profiles WHERE current_jobsite_id = ?)`)
      params.push(Number(jobsite_id))
    }

    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) AS total FROM approvals a WHERE ${where.join(' AND ')}`, params)
    const offset = (Math.max(1, Number(page)) - 1) * Number(size)
    const [rows] = await pool.query(
      `SELECT a.id AS approval_id, a.title, a.type_code AS appeal_type, a.status,
              a.form_data, a.urgency, a.created_at, a.updated_at, a.applicant, a.applicant_id,
              a.current_step
       FROM approvals a
       WHERE ${where.join(' AND ')}
       ORDER BY
         FIELD(a.status, 'pending', 'approved', 'rejected'),
         FIELD(a.urgency, 'critical', 'urgent', 'normal'),
         a.created_at DESC
       LIMIT ? OFFSET ?`,
      [...params, Number(size), offset]
    )
    const data = rows.map(r => ({
      ...r,
      form_data: r.form_data ? (typeof r.form_data === 'string' ? JSON.parse(r.form_data) : r.form_data) : null
    }))
    res.json({ code: 0, data: { list: data, total, page: Number(page), size: Number(size) } })
  } catch (e) { next(e) }
})

// ============================================================
// 4. 受理动作 — POST /api/labor-appeals/:approval_id/resolve
// body: { action: 'approve'|'reject', comment, correction? }
//  特殊 approve:若带 correction={field,new_value} 同步回写源数据
//   attendance.ref_id → attendance 表修正
//   workhours.ref_id  → attendance.work_hours 修正 + worker_profiles.total_work_hours 增量
//   payroll.ref_id    → finance 表修正
// ============================================================
router.post('/:approval_id/resolve', requirePermission(P.HR_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const aId = Number(req.params.approval_id)
    const { action, comment, correction } = req.body || {}
    if (!['approve', 'reject'].includes(action)) {
      return res.status(400).json({ code: 400, message: 'action 必须是 approve/reject' })
    }

    const [[apv]] = await conn.query(
      "SELECT * FROM approvals WHERE id = ? AND type = 'labor_appeal'", [aId])
    if (!apv) { await conn.rollback(); return res.status(404).json({ code: 404, message: '申诉不存在' }) }
    if (apv.status !== 'pending') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `申诉已 ${apv.status}, 不可重复处理` })
    }

    const fd = apv.form_data ? (typeof apv.form_data === 'string' ? JSON.parse(apv.form_data) : apv.form_data) : {}

    // 更新当前步骤
    await conn.query(
      `UPDATE approval_steps
       SET status = ?, comment = ?, approver_id = ?,
           approved_at = IF(? = 'approve', NOW(), NULL),
           rejected_at = IF(? = 'reject', NOW(), NULL),
           acted_at = NOW()
       WHERE approval_id = ? AND step_order = ?`,
      [action === 'approve' ? 'approved' : 'rejected', comment || null, req.user.id,
       action, action, aId, apv.current_step]
    )

    // 更新主表
    await conn.query(
      `UPDATE approvals SET status = ?, updated_at = NOW() WHERE id = ?`,
      [action === 'approve' ? 'approved' : 'rejected', aId]
    )

    // approve + correction 时同步修正源数据
    if (action === 'approve' && correction && fd.ref_id) {
      const wpId = fd.worker_profile_id
      const refId = Number(fd.ref_id)
      if (fd.appeal_type === 'workhours' && correction.field === 'work_hours' && correction.new_value != null) {
        const newH = Number(correction.new_value)
        await conn.query(
          `UPDATE attendance SET work_hours = ? WHERE id = ? AND user_id IN
             (SELECT user_id FROM worker_profiles WHERE id = ?)`,
          [newH, refId, wpId])
        // 同步总工时(取最近 30 天 SUM)
        const [[{ sum_h }]] = await conn.query(
          `SELECT COALESCE(SUM(work_hours), 0) AS sum_h FROM attendance
           WHERE user_id IN (SELECT user_id FROM worker_profiles WHERE id = ?)
             AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)`,
          [wpId])
        await conn.query(
          `UPDATE worker_profiles SET total_work_hours = ?, updated_at = NOW() WHERE id = ?`,
          [sum_h, wpId])
      } else if (fd.appeal_type === 'payroll' && correction.field && correction.new_value != null) {
        await conn.query(
          `UPDATE finance SET ${correction.field} = ? WHERE id = ?`, [correction.new_value, refId])
      } else if (fd.appeal_type === 'attendance' && correction.field && correction.new_value != null) {
        await conn.query(
          `UPDATE attendance SET ${correction.field} = ? WHERE id = ?`, [correction.new_value, refId])
      } else if (fd.appeal_type === 'evaluation' && correction.field && correction.new_value != null) {
        // evaluation 评分申诉:直接更新 4 个分 + 重算 quality_score
        const allowedFields = ['skill_score', 'attitude_score', 'efficiency_score', 'teamwork_score']
        if (allowedFields.includes(correction.field)) {
          await conn.query(
            `UPDATE labor_evaluations SET ${correction.field} = ? WHERE id = ?`,
            [correction.new_value, refId])
          // 重算
          await conn.query(
            `UPDATE labor_evaluations
             SET quality_score = (skill_score + attitude_score + efficiency_score + teamwork_score) / 4.0
             WHERE id = ?`, [refId])
        }
      } else if (fd.appeal_type === 'dispatch' && correction.field === 'status') {
        // 派工状态变更(撤回/重派)
        await conn.query(
          `UPDATE approvals SET status = ? WHERE id = ? AND type = 'labor_dispatch'`,
          [correction.new_value, refId])
      }
    }

    await conn.commit()
    res.json({
      code: 0,
      data: {
        approval_id: aId,
        action,
        status: action === 'approve' ? 'approved' : 'rejected',
        correction_applied: !!(action === 'approve' && correction)
      }
    })
  } catch (e) { try { await conn.rollback() } catch {}; next(e) }
  finally { conn.release() }
})

// ============================================================
// 5. 申诉类型 + 状态统计 — GET /api/labor-appeals/stats
// HR 仪表盘
// ============================================================
router.get('/stats', requirePermission(P.HR_READ), async (req, res, next) => {
  try {
    const [byType] = await pool.query(
      `SELECT type_code AS appeal_type,
              SUM(status = 'pending')  AS pending,
              SUM(status = 'approved') AS approved,
              SUM(status = 'rejected') AS rejected,
              COUNT(*)                 AS total
       FROM approvals
       WHERE type = 'labor_appeal'
         AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
       GROUP BY type_code`)
    const [[{ total_pending }]] = await pool.query(
      `SELECT COUNT(*) AS total_pending FROM approvals WHERE type='labor_appeal' AND status='pending'`)
    res.json({ code: 0, data: { by_type: byType, total_pending } })
  } catch (e) { next(e) }
})

export default router
