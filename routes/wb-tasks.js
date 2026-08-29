import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// GET /api/workbuddy/tasks/summary - 任务统计（总/待办/提交/完成/逾期）
router.get('/tasks/summary', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [[total]] = await pool.query(`SELECT COUNT(*) c FROM tasks`)
    const [[pending]] = await pool.query(`SELECT COUNT(*) c FROM tasks WHERE status='pending'`)
    const [[submitted]] = await pool.query(`SELECT COUNT(*) c FROM tasks WHERE status='submitted'`)
    const [[completed]] = await pool.query(`SELECT COUNT(*) c FROM tasks WHERE status='completed'`)
    const [[milestone]] = await pool.query(`SELECT COUNT(*) c FROM tasks WHERE status='milestone'`)
    const [[overdue]] = await pool.query(`
      SELECT COUNT(*) c FROM tasks
      WHERE status IN ('pending','submitted') AND due_date IS NOT NULL AND due_date < CURDATE()
    `)
    res.json({
      total: Number(total.c),
      pending: Number(pending.c),
      submitted: Number(submitted.c),
      completed: Number(completed.c),
      milestone: Number(milestone.c),
      overdue: Number(overdue.c),
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/tasks/pending?limit=20 - 待办/进行中任务列表
router.get('/tasks/pending', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const limit = Math.min(Number(req.query.limit) || 20, 50)
    const [rows] = await pool.query(`
      SELECT t.id, t.title, t.status, t.priority, t.due_date, t.completion_note,
             t.assigned_by, ua.name AS assignee_name, ua.email AS assignee_email
      FROM tasks t
      LEFT JOIN users ua ON ua.id = t.assigned_to
      WHERE t.status IN ('pending','submitted')
      ORDER BY (t.priority='high') DESC, (t.due_date IS NULL), t.due_date ASC, t.id DESC
      LIMIT ?
    `, [limit]).catch(() => [[]])
    res.json({
      tasks: (rows||[]).map(t => ({
        id: t.id, title: t.title, status: t.status, priority: t.priority,
        due_date: t.due_date, assignee: t.assignee_name || null,
        assignee_email: t.assignee_email || null, assigned_by: t.assigned_by,
      })),
      count: (rows||[]).length,
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/tasks/overdue - 逾期任务
router.get('/tasks/overdue', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT t.id, t.title, t.status, t.priority, t.due_date,
             ua.name AS assignee_name
      FROM tasks t
      LEFT JOIN users ua ON ua.id = t.assigned_to
      WHERE t.status IN ('pending','submitted') AND t.due_date IS NOT NULL AND t.due_date < CURDATE()
      ORDER BY t.due_date ASC LIMIT 50
    `).catch(() => [[]])
    res.json({
      tasks: (rows||[]).map(t => ({
        id: t.id, title: t.title, status: t.status, priority: t.priority,
        due_date: t.due_date, assignee: t.assignee_name || null,
        overdue_by_days: t.due_date ? Math.max(0, Math.floor((Date.now() - new Date(t.due_date).getTime()) / 86400000)) : 0,
      })),
      count: (rows||[]).length,
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/tasks/:id - 任务详情
router.get('/tasks/:id', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const id = Number(req.params.id)
    if (!id) return res.status(400).json({ error: 'invalid id' })
    const [[t]] = await pool.query(`
      SELECT t.*, ua.name AS assignee_name, cb.name AS created_by_name
      FROM tasks t
      LEFT JOIN users ua ON ua.id = t.assigned_to
      LEFT JOIN users cb ON cb.id = t.created_by
      WHERE t.id=?
    `, [id]).catch(() => [[null]])
    if (!t) return res.status(404).json({ error: 'task not found' })
    res.json({
      task: {
        id: t.id, title: t.title, description: t.description || t.content,
        priority: t.priority, status: t.status, jobsite_id: t.jobsite_id,
        assignee: t.assignee_name || null, created_by: t.created_by_name || null,
        due_date: t.due_date, completion_note: t.completion_note,
        submitted_at: t.submitted_at, created_at: t.created_at,
      }
    })
  } catch (err) { next(err) }
})

export default router