import { Router } from 'express'
import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'

const router = Router()
const JWT_SECRET = process.env.JWT_SECRET

// ──────────────────────────────────────────────────────────────
// Middleware: 管理员鉴权 (token平台用户 + is_admin=1)
// ──────────────────────────────────────────────────────────────
async function adminAuth(req, res, next) {
  const header = req.headers.authorization
  if (!header || !header.startsWith('Bearer ')) {
    return res.status(401).json({ code: 401, message: '未授权' })
  }
  try {
    const decoded = jwt.verify(header.split(' ')[1], JWT_SECRET)
    if (decoded.type !== 'token_user') {
      return res.status(403).json({ code: 403, message: '无效的token类型' })
    }
    // Check is_admin from DB (don't trust JWT for privilege)
    const [[user]] = await pool.query(
      'SELECT id, email, is_admin FROM ai_token_users WHERE id = ?',
      [decoded.id]
    )
    if (!user) return res.status(401).json({ code: 401, message: '用户不存在' })
    if (!user.is_admin) return res.status(403).json({ code: 403, message: '需要管理员权限' })
    req.user = user
    next()
  } catch {
    res.status(401).json({ code: 401, message: 'Token无效' })
  }
}

// ──────────────────────────────────────────────────────────────
// GET /api/token/admin/recharges — 列出所有充值记录
// Query: { status?, page=1, limit=20 }
// ──────────────────────────────────────────────────────────────
router.get('/recharges', adminAuth, async (req, res, next) => {
  try {
    const page  = Math.max(parseInt(req.query.page)  || 1, 1)
    const limit = Math.min(Math.max(parseInt(req.query.limit) || 20, 1), 100)
    const offset = (page - 1) * limit

    const conditions = ['1=1']
    const params = []
    if (req.query.status && ['pending', 'completed', 'failed'].includes(req.query.status)) {
      conditions.push('r.payment_status = ?')
      params.push(req.query.status)
    }

    const [[countRow]] = await pool.query(
      `SELECT COUNT(*) AS total FROM ai_token_recharges r WHERE ${conditions.join(' AND ')}`,
      params
    )

    const [rows] = await pool.query(
      `SELECT r.id, r.user_id, r.amount, r.payment_method, r.payment_status,
              r.payment_ref, r.admin_note, r.created_at, r.processed_at, r.processed_by,
              u.email AS user_email
       FROM ai_token_recharges r
       JOIN ai_token_users u ON r.user_id = u.id
       WHERE ${conditions.join(' AND ')}
       ORDER BY r.created_at DESC, r.id DESC
       LIMIT ? OFFSET ?`,
      [...params, limit, offset]
    )

    res.json({
      code: 0,
      data: {
        total: countRow.total,
        page,
        limit,
        data: rows
      }
    })
  } catch (err) {
    next(err)
  }
})

// ──────────────────────────────────────────────────────────────
// POST /api/token/admin/recharge/:id/approve — 审核通过
// Body: { admin_note? }
// ──────────────────────────────────────────────────────────────
router.post('/recharge/:id/approve', adminAuth, async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    // Lock the row
    const [[recharge]] = await conn.query(
      `SELECT id, user_id, amount, payment_status FROM ai_token_recharges
       WHERE id = ? FOR UPDATE`,
      [req.params.id]
    )
    if (!recharge) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '充值记录不存在' })
    }
    if (recharge.payment_status === 'completed') {
      await conn.rollback()
      return res.status(409).json({ code: 409, message: '该充值已审核通过' })
    }
    if (recharge.payment_status === 'failed') {
      await conn.rollback()
      return res.status(409).json({ code: 409, message: '该充值已失败，不可审核' })
    }

    // 1. Mark completed
    await conn.query(
      `UPDATE ai_token_recharges
       SET payment_status = 'completed',
           admin_note = ?,
           processed_at = NOW(),
           processed_by = ?
       WHERE id = ?`,
      [req.body.admin_note || null, req.user.id, recharge.id]
    )

    // 2. Add to user balance
    await conn.query(
      'UPDATE ai_token_users SET balance = balance + ? WHERE id = ?',
      [recharge.amount, recharge.user_id]
    )

    await conn.commit()

    res.json({
      code: 0,
      data: {
        recharge_id: recharge.id,
        user_id: recharge.user_id,
        amount: recharge.amount,
        status: 'completed',
        message: '充值已审核通过，余额已到账'
      }
    })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ──────────────────────────────────────────────────────────────
// POST /api/token/admin/recharge/:id/reject — 审核拒绝
// Body: { admin_note (required) }
// ──────────────────────────────────────────────────────────────
router.post('/recharge/:id/reject', adminAuth, async (req, res, next) => {
  try {
    const note = (req.body.admin_note || '').trim()
    if (!note) {
      return res.status(400).json({ code: 400, message: '拒绝时必须填写备注 admin_note' })
    }

    const [result] = await pool.query(
      `UPDATE ai_token_recharges
       SET payment_status = 'failed',
           admin_note = ?,
           processed_at = NOW(),
           processed_by = ?
       WHERE id = ? AND payment_status = 'pending'`,
      [note, req.user.id, req.params.id]
    )

    if (result.affectedRows === 0) {
      return res.status(404).json({ code: 404, message: '充值记录不存在或已审核' })
    }

    res.json({
      code: 0,
      data: { recharge_id: Number(req.params.id), status: 'failed' }
    })
  } catch (err) {
    next(err)
  }
})

// ──────────────────────────────────────────────────────────────
// GET /api/token/admin/stats — 全局统计
// ──────────────────────────────────────────────────────────────
router.get('/stats', adminAuth, async (req, res, next) => {
  try {
    const [[users]] = await pool.query(
      `SELECT COUNT(*) AS total_users,
              SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) AS active_users,
              SUM(CASE WHEN is_admin=1  THEN 1 ELSE 0 END) AS admin_users,
              COALESCE(SUM(balance),0) AS total_balance
       FROM ai_token_users`
    )
    const [[keys]] = await pool.query(
      `SELECT COUNT(*) AS total_keys,
              SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) AS active_keys
       FROM ai_token_keys`
    )
    const [[usage]] = await pool.query(
      `SELECT COUNT(*) AS total_requests,
              COALESCE(SUM(input_tokens),0)  AS total_input_tokens,
              COALESCE(SUM(output_tokens),0) AS total_output_tokens,
              COALESCE(SUM(cost),0)         AS total_cost
       FROM ai_token_usage`
    )
    const [[recharges]] = await pool.query(
      `SELECT COUNT(*) AS total_recharges,
              COALESCE(SUM(CASE WHEN payment_status='completed' THEN amount ELSE 0 END),0) AS completed_amount,
              COALESCE(SUM(CASE WHEN payment_status='pending'   THEN amount ELSE 0 END),0) AS pending_amount
       FROM ai_token_recharges`
    )

    res.json({
      code: 0,
      data: { users, keys, usage, recharges }
    })
  } catch (err) {
    next(err)
  }
})

export default router