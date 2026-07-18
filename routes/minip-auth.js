// /root/server/routes/minip-auth.js
// ──────────────────────────────────────────────────────────
// minip 独立登录口（前端：/root/cloned/minip-workspace/minip/）
//
// 目的：
//   1. minip 前端的 token 不污染主站 localStorage('caimeite_token')
//   2. minip 前端的 401 不影响主站登录态
//   3. 用户数据仍然共用 users 表（user_type 由管理员设置）
//
// 端点（mount: /api/minip/auth）：
//   POST /login    手机号+密码登录，按 DB user_type 自动分流（不传 userType 强制按 DB 走）
//   GET  /me       验证 token + 取用户基本信息（minip 端只返必要字段）
//   POST /logout   仅前端清 token，后端无状态，只返 200
//
// 与主站 /api/auth 的关系：
//   - DB 共享 (users 表)
//   - JWT_SECRET 共享（用户表统一）→ 同一用户在两端登录会拿到不同 token 但都能 verify
//   - 返回字段复用，minip store 直接兼容 { token, user, permissions, user_type }
//   - 不动主站 /api/auth 任何代码
// ──────────────────────────────────────────────────────────

import { Router } from 'express'
import bcrypt from 'bcryptjs'
import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'
import { loginLimiter } from '../middleware/rateLimit.js'
import { resolvePermissions } from '../middleware/auth.js'

const router = Router()

// minip 端登录权限：customer 只给基础读权限，staff 给完整 resolvePermissions
function pickPermissions(user) {
  if (user.user_type === 'customer') {
    return ['customer:read', 'rental:read']
  }
  return resolvePermissions(user)
}

// ─────────────────────────────────────
// POST /api/minip/auth/login
// 入参: { phone, password }
// 行为: 按 phone 查 DB,user_type 由 DB 决定（管理员控制,前端不传也不能传）
// 返参: { code:0, data:{ token, user, permissions, user_type } }
// ─────────────────────────────────────
router.post('/login', loginLimiter, async (req, res, next) => {
  try {
    const { phone, email, password } = req.body
    const loginKey = phone || email
    if (!loginKey || !password) {
      return res.status(400).json({ code: 400, message: '请输入手机号和密码' })
    }

    const [rows] = await pool.query(
      'SELECT * FROM users WHERE phone = ? OR email = ?',
      [loginKey, loginKey]
    )
    if (!rows.length) {
      return res.status(401).json({ code: 401, message: '手机号或密码错误' })
    }
    const user = rows[0]

    if (user.status === 'disabled') {
      return res.status(403).json({ code: 403, message: '账号已被禁用' })
    }

    const valid = await bcrypt.compare(password, user.password)
    if (!valid) {
      return res.status(401).json({ code: 401, message: '手机号或密码错误' })
    }

    await pool.query('UPDATE users SET last_login = NOW() WHERE id = ?', [user.id])

    const token = jwt.sign(
      {
        id: user.id,
        name: user.name,
        role: user.role,
        user_type: user.user_type || 'staff',
        customer_type: user.customer_type || null,
        member_level: user.member_level || 1,
        member_label: user.member_label || null,
        department: user.department,
        supplier_id: user.supplier_id || null,
        // ── minip 标记 ──
        // jwt payload 加 source:'minip' 字段,后端其他接口可识别"这个 token 是 minip 端发的"
        // 当前不强制校验,留作未来 minip 专属接口做 source 校验用
        source: 'minip'
      },
      process.env.JWT_SECRET,
      { expiresIn: '9999d' }
    )

    const { password: _, ...userData } = user
    const permissions = pickPermissions(user)

    // ── 字段精简 ──
    // minip 端只返必要字段,不暴露主站的 staff 管理字段(job_level_id / department_id / permissions 原始值等)
    const minipUser = {
      id: userData.id,
      name: userData.name,
      email: userData.email,
      phone: userData.phone,
      role: userData.role,
      user_type: userData.user_type || 'staff',
      customer_type: userData.customer_type || null,
      member_level: userData.member_level || 1,
      member_label: userData.member_label || null,
      points: userData.points || 0,
      is_internal: userData.is_internal || 0,
      customer_store_id: userData.customer_store_id || null,
      department: userData.department || null,
      avatar: userData.avatar || null,
      status: userData.status
    }

    res.json({
      code: 0,
      data: {
        token,
        user: minipUser,
        permissions,
        user_type: minipUser.user_type
      }
    })
  } catch (err) {
    next(err)
  }
})

// ─────────────────────────────────────
// GET /api/minip/auth/me
// 入参: Authorization: Bearer <token>
// 返参: { code:0, data: <minipUser> }
// ─────────────────────────────────────
router.get('/me', async (req, res, next) => {
  try {
    const authHeader = req.headers.authorization
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return res.status(401).json({ code: 401, message: '未登录或 token 缺失' })
    }
    const token = authHeader.split(' ')[1]
    const decoded = jwt.verify(token, process.env.JWT_SECRET)

    // ── 轻校验：只接 minip 端签发的 token ──
    // 如果是主站 token 调 /api/minip/auth/me → 401（不让主站 token 串到 minip 端）
    // 这条防护对应"minip 登录独立"的语义
    if (decoded.source !== 'minip') {
      return res.status(401).json({ code: 401, message: 'token 来源非 minip 端' })
    }

    const [rows] = await pool.query(
      `SELECT id, name, email, phone, role, user_type, h5_user_id, customer_type,
              member_level, member_label, points, is_internal, customer_store_id,
              department, avatar, status
         FROM users WHERE id = ?`,
      [decoded.id]
    )
    if (!rows.length) return res.status(401).json({ code: 401, message: '用户不存在' })
    const user = rows[0]
    if (user.status === 'disabled') {
      return res.status(403).json({ code: 403, message: '账号已被禁用' })
    }
    res.json({ code: 0, data: user })
  } catch (err) {
    if (err.name === 'JsonWebTokenError') {
      return res.status(401).json({ code: 401, message: 'token 无效' })
    }
    next(err)
  }
})

// ─────────────────────────────────────
// POST /api/minip/auth/logout
// JWT 是无状态的,后端不做任何事,只返 200 让前端清 localStorage
// ─────────────────────────────────────
router.post('/logout', (req, res) => {
  res.json({ code: 0, message: '已退出 minip' })
})

export default router
