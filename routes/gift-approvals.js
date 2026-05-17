import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'

const router = Router()

// GET /api/gift-approvals/approvers — 可选审批人列表（自己上级链 + 管理员）
router.get('/approvers', async (req, res, next) => {
  try {
    const userId = req.user.id
    const approvers = []
    const seen = new Set()

    // 1. 沿 supervisor_id 向上找上级链
    let currentId = userId
    for (let i = 0; i < 10; i++) {
      const [[user]] = await pool.query(
        'SELECT supervisor_id FROM users WHERE id = ? AND status = ?',
        [currentId, 'active']
      )
      if (!user || !user.supervisor_id) break
      currentId = user.supervisor_id
      if (!seen.has(currentId)) {
        seen.add(currentId)
        const [[sup]] = await pool.query('SELECT id, name, role, department FROM users WHERE id = ? AND status = ?', [currentId, 'active'])
        if (sup) approvers.push(sup)
      }
    }

    // 2. 所有管理员
    const [admins] = await pool.query(
      "SELECT id, name, role, department FROM users WHERE role IN ('admin','manager') AND status = 'active' AND id != ?",
      [userId]
    )
    for (const a of admins) {
      if (!seen.has(a.id)) {
        seen.add(a.id)
        approvers.push(a)
      }
    }

    res.json({ code: 0, data: approvers, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/gift-approvals/can-gift — 当前用户是否可赠送（无未解决的拒绝）
router.get('/can-gift', async (req, res, next) => {
  try {
    const [[{ cnt }]] = await pool.query(
      "SELECT COUNT(*) as cnt FROM gift_approvals WHERE requested_by = ? AND status = 'rejected'",
      [req.user.id]
    )
    res.json({ code: 0, data: { canGift: cnt === 0, rejectedCount: cnt }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/gift-approvals/pending-count — 当前用户待审批数
router.get('/pending-count', async (req, res, next) => {
  try {
    const [[{ cnt }]] = await pool.query(
      "SELECT COUNT(*) as cnt FROM gift_approvals WHERE approver_id = ? AND status = 'pending'",
      [req.user.id]
    )
    res.json({ code: 0, data: { count: cnt }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/gift-approvals/pending — 审批列表（支持 status 筛选）
router.get('/pending', async (req, res, next) => {
  try {
    const { status } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE ga.approver_id = ?'
    const params = [req.user.id]
    const countParams = [req.user.id]

    if (status) {
      where += ' AND ga.status = ?'
      params.push(status)
      countParams.push(status)
    }

    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM gift_approvals ga ${where}`,
      countParams
    )

    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(`
      SELECT ga.*,
        rr.buyer_name, rr.buyer_phone, rr.note as retail_note, rr.created_at as retail_created_at,
        p.name as product_name, p.spec as product_spec, p.image_main,
        q.code as qr_code,
        u.name as requested_by_name, u.department as requested_by_dept
      FROM gift_approvals ga
      LEFT JOIN retail_records rr ON ga.retail_record_id = rr.id
      LEFT JOIN products p ON rr.product_id = p.id
      LEFT JOIN qrcodes q ON rr.qrcode_id = q.id
      LEFT JOIN users u ON ga.requested_by = u.id
      ${where}
      ORDER BY ga.created_at DESC
      LIMIT ? OFFSET ?
    `, params)

    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/gift-approvals/my-requests — 我发起的赠送申请
router.get('/my-requests', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)

    const [[{ total }]] = await pool.query(
      'SELECT COUNT(*) as total FROM gift_approvals WHERE requested_by = ?',
      [req.user.id]
    )

    const [rows] = await pool.query(`
      SELECT ga.*,
        rr.buyer_name, rr.buyer_phone, rr.note as retail_note, rr.created_at as retail_created_at,
        p.name as product_name, p.spec as product_spec, p.image_main,
        q.code as qr_code,
        a.name as approver_name, a.department as approver_dept
      FROM gift_approvals ga
      LEFT JOIN retail_records rr ON ga.retail_record_id = rr.id
      LEFT JOIN products p ON rr.product_id = p.id
      LEFT JOIN qrcodes q ON rr.qrcode_id = q.id
      LEFT JOIN users a ON ga.approver_id = a.id
      WHERE ga.requested_by = ?
      ORDER BY ga.created_at DESC
      LIMIT ? OFFSET ?
    `, [req.user.id, size, (page - 1) * size])

    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/gift-approvals/:id/approve — 批准赠送
router.put('/:id/approve', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const [[ga]] = await conn.query(
      'SELECT * FROM gift_approvals WHERE id = ? AND approver_id = ?',
      [req.params.id, req.user.id]
    )
    if (!ga) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '审批记录不存在或无权操作' })
    }
    if (ga.status !== 'pending') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '该申请已被处理' })
    }

    const { comment } = req.body

    // 更新审批状态
    await conn.query(
      'UPDATE gift_approvals SET status = ?, comment = ?, acted_at = NOW() WHERE id = ?',
      ['approved', comment || null, ga.id]
    )

    // 更新零售记录 gift_status
    await conn.query(
      'UPDATE retail_records SET gift_status = ? WHERE id = ?',
      ['approved', ga.retail_record_id]
    )

    // 更新二维码为 sold
    const [[rr]] = await conn.query('SELECT qrcode_id, buyer_phone FROM retail_records WHERE id = ?', [ga.retail_record_id])
    if (rr && rr.qrcode_id) {
      await conn.query(
        'UPDATE qrcodes SET status = ?, buyer_phone = COALESCE(?, buyer_phone) WHERE id = ?',
        ['sold', rr.buyer_phone, rr.qrcode_id]
      )
    }

    await conn.commit()
    res.json({ code: 0, data: null, message: '已批准赠送' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// PUT /api/gift-approvals/:id/reject — 拒绝赠送（必填原因）
router.put('/:id/reject', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const [[ga]] = await conn.query(
      'SELECT * FROM gift_approvals WHERE id = ? AND approver_id = ?',
      [req.params.id, req.user.id]
    )
    if (!ga) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '审批记录不存在或无权操作' })
    }
    if (ga.status !== 'pending') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '该申请已被处理' })
    }

    const { comment } = req.body
    if (!comment || !comment.trim()) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '拒绝原因必填' })
    }

    await conn.query(
      'UPDATE gift_approvals SET status = ?, comment = ?, acted_at = NOW() WHERE id = ?',
      ['rejected', comment.trim(), ga.id]
    )

    await conn.query(
      'UPDATE retail_records SET gift_status = ? WHERE id = ?',
      ['rejected', ga.retail_record_id]
    )

    await conn.commit()
    res.json({ code: 0, data: null, message: '已拒绝赠送' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// PUT /api/gift-approvals/:id/resolve — 解决拒绝（审批人/管理员）
router.put('/:id/resolve', async (req, res, next) => {
  try {
    const [[ga]] = await pool.query('SELECT * FROM gift_approvals WHERE id = ?', [req.params.id])
    if (!ga) return res.status(404).json({ code: 404, message: '审批记录不存在' })
    if (ga.status !== 'rejected') return res.status(400).json({ code: 400, message: '只能解决被拒绝的申请' })

    // 仅审批人或管理员可解决
    if (ga.approver_id !== req.user.id && req.user.role !== 'admin') {
      return res.status(403).json({ code: 403, message: '无权操作' })
    }

    const { comment } = req.body
    await pool.query(
      'UPDATE gift_approvals SET status = ?, comment = CONCAT(COALESCE(comment,""), ?, ?), acted_at = NOW() WHERE id = ?',
      ['resolved', '\n[已解决] ', (comment || '').trim(), ga.id]
    )

    res.json({ code: 0, data: null, message: '已解决' })
  } catch (err) { next(err) }
})

export default router
