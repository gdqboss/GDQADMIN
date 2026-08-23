import { Router } from 'express'
import crypto from 'crypto'
import { pool } from '../db/connection.js'

const router = Router()
const JWT_SECRET = process.env.JWT_SECRET

// Middleware: JWT验证 (简化版，专用于token平台用户)
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

import jwt from 'jsonwebtoken'

// POST /api/token/keys - 创建Key
router.post('/keys', tokenAuth, async (req, res, next) => {
  try {
    const { key_name, quota_day, quota_month, expires_at, ip_whitelist } = req.body
    const user_id = req.user.id

    // 生成Key: sk-tok- + 16位uuid (无连字符)
    const rawKey = 'sk-tok-' + crypto.randomUUID().replace(/-/g, '')
    const key_hash = crypto.createHash('sha256').update(rawKey).digest('hex')
    const key_prefix = rawKey.substring(0, 16)  // sk-tok-xxxxxxxx

    const [result] = await pool.query(
      `INSERT INTO ai_token_keys
       (user_id, key_prefix, key_hash, key_name, quota_day, quota_month, expires_at, ip_whitelist)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        user_id,
        key_prefix,
        key_hash,
        key_name || null,
        quota_day || 0,
        quota_month || 0,
        expires_at || null,
        ip_whitelist || null
      ]
    )

    res.json({
      code: 0,
      data: {
        id: result.insertId,
        key_prefix,
        key_full: rawKey,   // 只在创建时返回一次
        key_hash
      }
    })
  } catch (err) {
    next(err)
  }
})

// GET /api/token/keys - 列出当前用户所有Key
router.get('/keys', tokenAuth, async (req, res, next) => {
  try {
    const user_id = req.user.id
    const [rows] = await pool.query(
      `SELECT id, key_prefix, key_name, quota_day, quota_month,
              expires_at, ip_whitelist, is_active, created_at
       FROM ai_token_keys WHERE user_id = ? ORDER BY created_at DESC`,
      [user_id]
    )
    res.json({ code: 0, data: rows })
  } catch (err) {
    next(err)
  }
})

// DELETE /api/token/keys/:id - 删除Key
router.delete('/keys/:id', tokenAuth, async (req, res, next) => {
  try {
    const user_id = req.user.id
    const [result] = await pool.query(
      'DELETE FROM ai_token_keys WHERE id = ? AND user_id = ?',
      [req.params.id, user_id]
    )
    if (result.affectedRows === 0) {
      return res.status(404).json({ code: 404, message: 'Key不存在或无权删除' })
    }
    res.json({ code: 0, message: 'Key已删除' })
  } catch (err) {
    next(err)
  }
})

// GET /api/token/balance - 查询余额
router.get('/balance', tokenAuth, async (req, res, next) => {
  try {
    const user_id = req.user.id
    const [[user]] = await pool.query('SELECT id, balance FROM ai_token_users WHERE id = ?', [user_id])
    if (!user) {
      return res.status(404).json({ code: 404, message: '用户不存在' })
    }
    res.json({ code: 0, data: { user_id: user.id, balance: user.balance } })
  } catch (err) {
    next(err)
  }
})

export default router
