import { Router } from 'express'
import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'

const router = Router()
const JWT_SECRET = process.env.JWT_SECRET

// Middleware: JWT验证 (token平台用户)
function tokenAuth(req, res, next) {
  const header = req.headers.authorization
  if (!header || !header.startsWith('Bearer ')) {
    return res.status(401).json({ code: 401, message: '未授权' })
  }
  try {
    const decoded = jwt.verify(header.split(' ')[1], JWT_SECRET)
    if (decoded.type !== 'token_user') {
      return res.status(403).json({ code: 403, message: '无效的token类型' })
    }
    req.user = decoded
    next()
  } catch {
    res.status(401).json({ code: 401, message: 'Token无效' })
  }
}

// ──────────────────────────────────────────────────────────────
// POST /api/token/recharge — 创建充值记录 (status=pending)
// Body: { amount, payment_method?, payment_ref? }
// ──────────────────────────────────────────────────────────────
router.post('/', tokenAuth, async (req, res, next) => {
  try {
    const { amount, payment_method, payment_ref } = req.body
    const user_id = req.user.id

    const amt = parseFloat(amount)
    if (!amt || amt <= 0) {
      return res.status(400).json({ code: 400, message: '充值金额必须大于0' })
    }
    if (amt > 1_000_000) {
      return res.status(400).json({ code: 400, message: '单笔充值金额上限100万' })
    }

    const validMethods = ['alipay', 'wechat', 'stripe', 'manual']
    const method = validMethods.includes(payment_method) ? payment_method : 'manual'

    const [result] = await pool.query(
      `INSERT INTO ai_token_recharges (user_id, amount, payment_method, payment_ref)
       VALUES (?, ?, ?, ?)`,
      [user_id, amt.toFixed(4), method, payment_ref || null]
    )

    res.json({
      code: 0,
      data: {
        recharge_id: result.insertId,
        amount: amt,
        payment_method: method,
        status: 'pending',
        message: '请联系管理员完成充值'
      }
    })
  } catch (err) {
    next(err)
  }
})

// ──────────────────────────────────────────────────────────────
// GET /api/token/recharge — 当前用户的充值历史
// Query: { page=1, limit=20, status? }
// ──────────────────────────────────────────────────────────────
router.get('/', tokenAuth, async (req, res, next) => {
  try {
    const page  = Math.max(parseInt(req.query.page)  || 1, 1)
    const limit = Math.min(Math.max(parseInt(req.query.limit) || 20, 1), 100)
    const offset = (page - 1) * limit

    const conditions = ['user_id = ?']
    const params = [req.user.id]
    if (req.query.status && ['pending', 'completed', 'failed'].includes(req.query.status)) {
      conditions.push('payment_status = ?')
      params.push(req.query.status)
    }

    const [[countRow]] = await pool.query(
      `SELECT COUNT(*) AS total FROM ai_token_recharges WHERE ${conditions.join(' AND ')}`,
      params
    )

    const [rows] = await pool.query(
      `SELECT id, user_id, amount, payment_method, payment_status,
              payment_ref, admin_note, created_at, processed_at
       FROM ai_token_recharges
       WHERE ${conditions.join(' AND ')}
       ORDER BY created_at DESC, id DESC
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

export default router