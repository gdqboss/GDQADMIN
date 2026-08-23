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

// Helper: build WHERE clause for user-scoped usage queries
function buildUsageWhere(userId, { start_date, end_date, key_id, model }) {
  const conditions = ['k.user_id = ?']
  const params = [userId]
  if (key_id) {
    conditions.push('u.key_id = ?')
    params.push(key_id)
  }
  if (model) {
    conditions.push('u.model = ?')
    params.push(model)
  }
  if (start_date) {
    conditions.push('u.created_at >= ?')
    params.push(start_date)
  }
  if (end_date) {
    conditions.push('u.created_at <= ?')
    params.push(end_date)
  }
  return { sql: conditions.join(' AND '), params }
}

// ──────────────────────────────────────────────────────────────
// GET /api/token/usage/summary — 用户维度汇总
// Query: { start_date?, end_date?, key_id? }
// ──────────────────────────────────────────────────────────────
router.get('/summary', tokenAuth, async (req, res, next) => {
  try {
    const { sql, params } = buildUsageWhere(req.user.id, req.query)
    const [rows] = await pool.query(
      `SELECT
         COALESCE(SUM(u.input_tokens),  0) AS total_input_tokens,
         COALESCE(SUM(u.output_tokens), 0) AS total_output_tokens,
         COALESCE(SUM(u.cost), 0)        AS total_cost,
         COUNT(*)                         AS total_requests,
         COALESCE(AVG(u.response_time_ms), 0) AS avg_response_time_ms
       FROM ai_token_usage u
       JOIN ai_token_keys k ON u.key_id = k.id
       WHERE ${sql}`,
      params
    )
    res.json({ code: 0, data: rows[0] })
  } catch (err) {
    next(err)
  }
})

// ──────────────────────────────────────────────────────────────
// GET /api/token/usage/by-model — 按模型分组
// ──────────────────────────────────────────────────────────────
router.get('/by-model', tokenAuth, async (req, res, next) => {
  try {
    const { sql, params } = buildUsageWhere(req.user.id, req.query)
    const [rows] = await pool.query(
      `SELECT
         u.model,
         COALESCE(SUM(u.input_tokens),  0) AS input_tokens,
         COALESCE(SUM(u.output_tokens), 0) AS output_tokens,
         COALESCE(SUM(u.cost), 0)        AS cost,
         COUNT(*)                         AS requests
       FROM ai_token_usage u
       JOIN ai_token_keys k ON u.key_id = k.id
       WHERE ${sql}
       GROUP BY u.model
       ORDER BY cost DESC`,
      params
    )
    res.json({ code: 0, data: rows })
  } catch (err) {
    next(err)
  }
})

// ──────────────────────────────────────────────────────────────
// GET /api/token/usage/daily — 每日用量趋势 (最近30天)
// ──────────────────────────────────────────────────────────────
router.get('/daily', tokenAuth, async (req, res, next) => {
  try {
    const { sql, params } = buildUsageWhere(req.user.id, req.query)
    const [rows] = await pool.query(
      `SELECT
         DATE(u.created_at) AS date,
         COALESCE(SUM(u.input_tokens),  0) AS input_tokens,
         COALESCE(SUM(u.output_tokens), 0) AS output_tokens,
         COALESCE(SUM(u.cost), 0)        AS cost,
         COUNT(*)                         AS requests
       FROM ai_token_usage u
       JOIN ai_token_keys k ON u.key_id = k.id
       WHERE ${sql}
       GROUP BY DATE(u.created_at)
       ORDER BY date DESC
       LIMIT 30`,
      params
    )
    res.json({ code: 0, data: rows })
  } catch (err) {
    next(err)
  }
})

// ──────────────────────────────────────────────────────────────
// GET /api/token/usage/history — 消费明细 (分页)
// Query: { page=1, limit=20, model?, start_date?, end_date?, key_id? }
// ──────────────────────────────────────────────────────────────
router.get('/history', tokenAuth, async (req, res, next) => {
  try {
    const page  = Math.max(parseInt(req.query.page)  || 1, 1)
    const limit = Math.min(Math.max(parseInt(req.query.limit) || 20, 1), 100)
    const offset = (page - 1) * limit

    const { sql, params } = buildUsageWhere(req.user.id, req.query)

    const [[countRow]] = await pool.query(
      `SELECT COUNT(*) AS total
       FROM ai_token_usage u
       JOIN ai_token_keys k ON u.key_id = k.id
       WHERE ${sql}`,
      params
    )

    const [rows] = await pool.query(
      `SELECT u.id, u.key_id, u.model, u.input_tokens, u.output_tokens,
              u.cost, u.response_time_ms, u.created_at,
              k.key_name, k.key_prefix
       FROM ai_token_usage u
       JOIN ai_token_keys k ON u.key_id = k.id
       WHERE ${sql}
       ORDER BY u.created_at DESC, u.id DESC
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