import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// GET /api/workbuddy/approvals/pending - 待审批列表
router.get('/approvals/pending', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT id, title, type, applicant, department, amount, urgency, status,
             current_step, created_at
      FROM approvals
      WHERE status = 'pending'
      ORDER BY urgency = 'high' DESC, created_at ASC
    `)
    res.json({
      approvals: rows.map(a => ({
        id: a.id, title: a.title, type: a.type || a.type_code, applicant: a.applicant,
        department: a.department, amount: Number(a.amount)||0, urgency: a.urgency,
        current_step: a.current_step, created_at: a.created_at,
      })),
      count: rows.length,
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/approvals/recent?limit=10 - 最近审批记录
router.get('/approvals/recent', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const limit = Math.min(Number(req.query.limit) || 10, 50)
    const [rows] = await pool.query(`
      SELECT id, title, type, applicant, amount, urgency, status, created_at, updated_at
      FROM approvals
      ORDER BY updated_at DESC, id DESC LIMIT ?
    `, [limit])
    res.json({
      approvals: rows.map(a => ({
        id: a.id, title: a.title, type: a.type || a.type_code, applicant: a.applicant,
        amount: Number(a.amount)||0, urgency: a.urgency, status: a.status,
        created_at: a.created_at, updated_at: a.updated_at,
      })),
      count: rows.length,
    })
  } catch (err) { next(err) }
})

// POST /api/workbuddy/approvals/:id/approve - 审批通过（事务 + 二次确认）
// body: { confirm: true, comment?: string }
router.post('/approvals/:id/approve', auth, requirePermission('workbuddy:write'), async (req, res, next) => {
  const { confirm, comment } = req.body || {}
  if (confirm !== true) return res.status(400).json({ error: 'approve requires confirm:true' })
  const id = Number(req.params.id)
  if (!id) return res.status(400).json({ error: 'invalid id' })

  const connection = await pool.getConnection()
  try {
    await connection.beginTransaction()
    const [[a]] = await connection.query(`SELECT * FROM approvals WHERE id=? FOR UPDATE`, [id])
    if (!a) { await connection.rollback(); return res.status(404).json({ error: 'approval not found' }) }
    if (a.status !== 'pending') { await connection.rollback(); return res.status(409).json({ error: `already ${a.status}` }) }

    await connection.query(`UPDATE approvals SET status='approved', updated_at=NOW() WHERE id=?`, [id])
    // 当前待处理步骤标记通过
    await connection.query(`
      UPDATE approval_steps SET status='approved', approver_id=?, comment=COALESCE(?, comment),
        acted_at=NOW(), approved_at=NOW()
      WHERE approval_id=? AND status='pending' AND step_order=?
    `, [req.user?.id || null, comment || null, id, a.current_step])
    await connection.commit()
    res.json({ ok: true, id, action: 'approved', by: req.user?.id || null })
  } catch (err) {
    await connection.rollback()
    next(err)
  } finally {
    connection.release()
  }
})

// POST /api/workbuddy/approvals/:id/reject - 审批拒绝（事务 + 二次确认）
// body: { confirm: true, comment?: string }
router.post('/approvals/:id/reject', auth, requirePermission('workbuddy:write'), async (req, res, next) => {
  const { confirm, comment } = req.body || {}
  if (confirm !== true) return res.status(400).json({ error: 'reject requires confirm:true' })
  const id = Number(req.params.id)
  if (!id) return res.status(400).json({ error: 'invalid id' })

  const connection = await pool.getConnection()
  try {
    await connection.beginTransaction()
    const [[a]] = await connection.query(`SELECT * FROM approvals WHERE id=? FOR UPDATE`, [id])
    if (!a) { await connection.rollback(); return res.status(404).json({ error: 'approval not found' }) }
    if (a.status !== 'pending') { await connection.rollback(); return res.status(409).json({ error: `already ${a.status}` }) }

    await connection.query(`UPDATE approvals SET status='rejected', updated_at=NOW() WHERE id=?`, [id])
    await connection.query(`
      UPDATE approval_steps SET status='rejected', approver_id=?, comment=COALESCE(?, comment),
        acted_at=NOW(), rejected_at=NOW()
      WHERE approval_id=? AND status='pending' AND step_order=?
    `, [req.user?.id || null, comment || null, id, a.current_step])
    await connection.commit()
    res.json({ ok: true, id, action: 'rejected', by: req.user?.id || null })
  } catch (err) {
    await connection.rollback()
    next(err)
  } finally {
    connection.release()
  }
})

export default router