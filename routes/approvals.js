import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'

const router = Router()

router.get('/', async (req, res, next) => {
  try {
    const { status, type } = req.query
    const { page, size } = parsePagination(req.query)
    let sql = 'SELECT * FROM approvals WHERE 1=1'
    let countSql = 'SELECT COUNT(*) as total FROM approvals WHERE 1=1'
    const params = []
    const countParams = []
    if (status) { sql += ' AND status = ?'; countSql += ' AND status = ?'; params.push(status); countParams.push(status) }
    if (type) { sql += ' AND type = ?'; countSql += ' AND type = ?'; params.push(type); countParams.push(type) }
    sql += ' ORDER BY created_at DESC LIMIT ? OFFSET ?'
    params.push(size, (page - 1) * size)
    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

router.get('/:id', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM approvals WHERE id = ?', [req.params.id])
    if (!rows.length) return res.status(404).json({ code: 404, message: '审批不存在' })
    const approval = rows[0]
    const [steps] = await pool.query(
      'SELECT * FROM approval_steps WHERE approval_id = ? ORDER BY step_order', [req.params.id]
    )
    approval.steps = steps
    res.json({ code: 0, data: approval, message: 'ok' })
  } catch (err) { next(err) }
})

router.post('/', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { title, type, applicant, department, amount, urgency, steps } = req.body
    if (!title) return res.status(400).json({ code: 400, message: '审批标题必填' })
    const [result] = await conn.query(
      'INSERT INTO approvals (title, type, applicant, department, amount, urgency) VALUES (?,?,?,?,?,?)',
      [title, type, applicant, department, amount, urgency || 'normal']
    )
    const defaultSteps = steps || [
      { role: '部门经理', assignee: department ? `${department}经理` : '经理' },
      { role: '总经理', assignee: '总经理' }
    ]
    for (let i = 0; i < defaultSteps.length; i++) {
      await conn.query(
        'INSERT INTO approval_steps (approval_id, step_order, role, assignee) VALUES (?,?,?,?)',
        [result.insertId, i + 1, defaultSteps[i].role, defaultSteps[i].assignee]
      )
    }
    await conn.commit()
    res.json({ code: 0, data: { id: result.insertId }, message: 'ok' })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

router.put('/:id/approve', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { comment } = req.body
    const [[approval]] = await conn.query('SELECT * FROM approvals WHERE id = ?', [req.params.id])
    if (!approval) { await conn.rollback(); return res.status(404).json({ code: 404, message: '审批不存在' }) }
    if (approval.status !== 'pending') { await conn.rollback(); return res.status(400).json({ code: 400, message: '审批已结束' }) }

    const [[currentStep]] = await conn.query(
      'SELECT * FROM approval_steps WHERE approval_id = ? AND step_order = ?',
      [req.params.id, approval.current_step]
    )
    if (currentStep && currentStep.assignee !== req.user.name) {
      await conn.rollback()
      return res.status(403).json({ code: 403, message: '您不是当前审批步骤的指定审批人' })
    }

    await conn.query(
      "UPDATE approval_steps SET status = 'approved', comment = ?, acted_at = NOW() WHERE approval_id = ? AND step_order = ?",
      [comment, req.params.id, approval.current_step]
    )
    const [[{ maxStep }]] = await conn.query(
      'SELECT MAX(step_order) as maxStep FROM approval_steps WHERE approval_id = ?', [req.params.id]
    )
    if (approval.current_step >= maxStep) {
      await conn.query("UPDATE approvals SET status = 'approved', current_step = ? WHERE id = ?",
        [approval.current_step, req.params.id])
    } else {
      await conn.query('UPDATE approvals SET current_step = current_step + 1 WHERE id = ?', [req.params.id])
    }
    await conn.commit()
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

router.put('/:id/reject', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()
    const { comment } = req.body
    const [[approval]] = await conn.query('SELECT * FROM approvals WHERE id = ?', [req.params.id])
    if (!approval) { await conn.rollback(); return res.status(404).json({ code: 404, message: '审批不存在' }) }
    if (approval.status !== 'pending') { await conn.rollback(); return res.status(400).json({ code: 400, message: '审批已结束' }) }

    const [[currentStep]] = await conn.query(
      'SELECT * FROM approval_steps WHERE approval_id = ? AND step_order = ?',
      [req.params.id, approval.current_step]
    )
    if (currentStep && currentStep.assignee !== req.user.name) {
      await conn.rollback()
      return res.status(403).json({ code: 403, message: '您不是当前审批步骤的指定审批人' })
    }

    await conn.query(
      "UPDATE approval_steps SET status = 'rejected', comment = ?, acted_at = NOW() WHERE approval_id = ? AND step_order = ?",
      [comment, req.params.id, approval.current_step]
    )
    await conn.query("UPDATE approvals SET status = 'rejected' WHERE id = ?", [req.params.id])
    await conn.commit()
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { await conn.rollback(); next(err) }
  finally { conn.release() }
})

// DELETE /:id - 删除审批记录
router.delete('/:id', async (req, res, next) => {
  try {
    const [[approval]] = await pool.query('SELECT * FROM approvals WHERE id = ?', [req.params.id])
    if (!approval) return res.status(404).json({ code: 404, message: '审批不存在' })

    // Only admin or applicant can delete
    if (req.user.role !== 'admin' && String(approval.applicant_id) !== String(req.user.id)) {
      return res.status(403).json({ code: 403, message: '无权限删除此审批' })
    }

    const conn = await pool.getConnection()
    try {
      await conn.beginTransaction()
      await conn.query('DELETE FROM approval_steps WHERE approval_id = ?', [req.params.id])
      await conn.query('DELETE FROM approvals WHERE id = ?', [req.params.id])
      await conn.commit()
      res.json({ code: 0, data: null, message: '已删除' })
    } catch (err) {
      await conn.rollback()
      throw err
    } finally {
      conn.release()
    }
  } catch (err) {
    next(err)
  }
})

export default router
