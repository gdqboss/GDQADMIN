import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// GET /api/workbuddy/attendance/summary - 今日考勤总览
router.get('/attendance/summary', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [[today]] = await pool.query(`
      SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='normal' THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN status='late' OR late_minutes>0 THEN 1 ELSE 0 END) AS late,
        SUM(CASE WHEN status='early' OR early_minutes>0 THEN 1 ELSE 0 END) AS early
      FROM attendance WHERE date = CURDATE()
    `).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[{ total: 0, present: 0, late: 0, early: 0 }]] })
    const pend = async (tbl) => {
      const [[r]] = await pool.query(`SELECT COUNT(*) c FROM ${tbl} WHERE status='pending'`).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[{ c: 0 }]] })
      return Number(r.c)
    }
    const [leavePending, otPending] = await Promise.all([pend('leave_records'), pend('overtime_records')])

    res.json({
      date: new Date().toISOString().slice(0, 10),
      today: {
        total: Number(today.total)||0,
        present: Number(today.present)||0,
        late: Number(today.late)||0,
        early_leave: Number(today.early)||0,
      },
      pending_approvals: {
        leave: leavePending, overtime: otPending,
      },
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/attendance/pending - 待我审批的请假/加班/考勤
router.get('/attendance/pending', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [leaves] = await pool.query(`
      SELECT l.id, l.type, l.start_date, l.end_date, l.days, l.reason, l.status,
             u.name AS applicant, l.created_at
      FROM leave_records l LEFT JOIN users u ON u.id = l.user_id
      WHERE l.status='pending' ORDER BY l.created_at DESC LIMIT 20
    `).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[]] })
    const [ots] = await pool.query(`
      SELECT o.id, o.start_time, o.end_time, o.hours, o.reason, o.status,
             u.name AS applicant, o.created_at
      FROM overtime_records o LEFT JOIN users u ON u.id = o.user_id
      WHERE o.status='pending' ORDER BY o.created_at DESC LIMIT 20
    `).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[]] })
    const [att] = await pool.query(`
      SELECT a.id, a.date, a.status, u.name AS applicant,
             a.late_minutes, a.early_minutes, a.abnormal_reason, a.created_at
      FROM attendance a LEFT JOIN users u ON u.id = a.user_id
      WHERE a.status IN ('pending','absent','abnormal') ORDER BY a.date DESC LIMIT 20
    `).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[]] })
    res.json({
      leave: (leaves||[]).map(l => ({ id: l.id, type: l.type, applicant: l.applicant, start: l.start_date, end: l.end_date, days: Number(l.days)||0, reason: l.reason, created_at: l.created_at })),
      overtime: (ots||[]).map(o => ({ id: o.id, applicant: o.applicant, start: o.start_time, end: o.end_time, hours: Number(o.hours)||0, reason: o.reason })),
      abnormal: (att||[]).map(a => ({ id: a.id, applicant: a.applicant, date: a.date, status: a.status, late: Number(a.late_minutes)||0, early: Number(a.early_minutes)||0, reason: a.abnormal_reason })),
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/attendance/my?limit=10 - 我的考勤记录
router.get('/attendance/my', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const limit = Math.min(Number(req.query.limit) || 10, 30)
    const uid = req.user?.id
    const [rows] = await pool.query(`
      SELECT id, date, scheduled_in, scheduled_out, clock_in, clock_out,
             status, late_minutes, early_minutes, overtime_hours
      FROM attendance
      WHERE user_id = ?
      ORDER BY date DESC, id DESC LIMIT ?
    `, [uid, limit]).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[]] })
    res.json({
      records: (rows||[]).map(r => ({
        id: r.id, date: r.date, scheduled_in: r.scheduled_in, scheduled_out: r.scheduled_out,
        clock_in: r.clock_in, clock_out: r.clock_out, status: r.status,
        late: Number(r.late_minutes)||0, early_leave: Number(r.early_minutes)||0,
        overtime: Number(r.overtime_hours)||0,
      })),
      count: (rows||[]).length,
    })
  } catch (err) { next(err) }
})

export default router