import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// GET /api/workbuddy/logs/summary - 工作日志统计
router.get('/logs/summary', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [[today]] = await pool.query(`
      SELECT COUNT(*) c FROM work_logs
      WHERE submit_date = CURDATE() OR DATE(created_at) = CURDATE()
    `).catch(() => [[{ c: 0 }]])
    const [[pending]] = await pool.query(`
      SELECT COUNT(*) c FROM work_logs WHERE status IN ('pending','submitted')
    `).catch(() => [[{ c: 0 }]])
    const [[total]] = await pool.query(`SELECT COUNT(*) c FROM work_logs`).catch(() => [[{ c: 0 }]])
    const [[perUser]] = await pool.query(`
      SELECT COUNT(DISTINCT user_id) c FROM work_logs
      WHERE submit_date = CURDATE() OR DATE(created_at) = CURDATE()
    `).catch(() => [[{ c: 0 }]])
    const [[noLog]] = await pool.query(`
      SELECT COUNT(*) c FROM users u
      WHERE u.user_type='staff' AND u.id NOT IN (
        SELECT user_id FROM work_logs WHERE submit_date = CURDATE() OR DATE(created_at) = CURDATE()
      )
    `).catch(() => [[{ c: 0 }]])
    res.json({
      today_submitted: Number(today.c),
      today_users: Number(perUser.c),
      pending_review: Number(pending.c),
      not_yet_submitted_today: Number(noLog.c),
      total: Number(total.c),
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/logs/today - 今日工作日志
router.get('/logs/today', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT w.id, w.submit_date, w.status, w.today_work, w.tomorrow_plan, w.issues,
             w.created_at, u.name AS author, u.email AS author_email
      FROM work_logs w
      LEFT JOIN users u ON u.id = w.user_id
      WHERE w.submit_date = CURDATE() OR DATE(w.created_at) = CURDATE()
      ORDER BY w.created_at DESC LIMIT 30
    `).catch(() => [[]])
    res.json({
      logs: (rows||[]).map(l => ({
        id: l.id, author: l.author || l.author_email || null, submit_date: l.submit_date,
        status: l.status,
        today_work: l.today_work ? String(l.today_work).slice(0, 500) : null,
        tomorrow_plan: l.tomorrow_plan ? String(l.tomorrow_plan).slice(0, 300) : null,
        issues: l.issues ? String(l.issues).slice(0, 300) : null,
        created_at: l.created_at,
      })),
      count: (rows||[]).length,
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/logs/pending - 待审核工作日志
router.get('/logs/pending', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT w.id, w.submit_date, w.status, w.today_work, w.created_at,
             u.name AS author, u.email AS author_email
      FROM work_logs w
      LEFT JOIN users u ON u.id = w.user_id
      WHERE w.status IN ('pending','submitted')
      ORDER BY w.created_at DESC LIMIT 30
    `).catch(() => [[]])
    res.json({
      logs: (rows||[]).map(l => ({
        id: l.id, author: l.author || l.author_email || null, submit_date: l.submit_date,
        status: l.status,
        today_work: l.today_work ? String(l.today_work).slice(0, 400) : null,
        created_at: l.created_at,
      })),
      count: (rows||[]).length,
    })
  } catch (err) { next(err) }
})

export default router