import { Router } from 'express'
import bcrypt from 'bcryptjs'
import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'

const router = Router()
const JWT_SECRET = process.env.JWT_SECRET

// POST /api/token/register
router.post('/register', async (req, res, next) => {
  try {
    const { email, password } = req.body
    if (!email || !password) {
      return res.status(400).json({ code: 400, message: 'email 和 password 必填' })
    }
    if (password.length < 6) {
      return res.status(400).json({ code: 400, message: '密码至少6位' })
    }

    // 检查email是否已存在
    const [existing] = await pool.query('SELECT id FROM ai_token_users WHERE email = ?', [email])
    if (existing.length > 0) {
      return res.status(409).json({ code: 409, message: '该邮箱已注册' })
    }

    const password_hash = await bcrypt.hash(password, 10)
    const [result] = await pool.query(
      'INSERT INTO ai_token_users (email, password_hash) VALUES (?, ?)',
      [email, password_hash]
    )

    const user_id = result.insertId
    const token = jwt.sign(
      { id: user_id, email, type: 'token_user' },
      JWT_SECRET,
      { expiresIn: '30d' }
    )

    res.json({ code: 0, data: { user_id, token } })
  } catch (err) {
    next(err)
  }
})

// POST /api/token/login
router.post('/login', async (req, res, next) => {
  try {
    const { email, password } = req.body
    if (!email || !password) {
      return res.status(400).json({ code: 400, message: 'email 和 password 必填' })
    }

    const [rows] = await pool.query('SELECT * FROM ai_token_users WHERE email = ?', [email])
    if (!rows.length) {
      return res.status(401).json({ code: 401, message: '邮箱或密码错误' })
    }
    const user = rows[0]

    if (!user.is_active) {
      return res.status(403).json({ code: 403, message: '账号已被禁用' })
    }

    const valid = await bcrypt.compare(password, user.password_hash)
    if (!valid) {
      return res.status(401).json({ code: 401, message: '邮箱或密码错误' })
    }

    const token = jwt.sign(
      { id: user.id, email: user.email, type: 'token_user' },
      JWT_SECRET,
      { expiresIn: '30d' }
    )

    res.json({
      code: 0,
      data: {
        user_id: user.id,
        email: user.email,
        balance: user.balance,
        token
      }
    })
  } catch (err) {
    next(err)
  }
})

export default router
