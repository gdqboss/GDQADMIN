import { Router } from 'express'
import bcrypt from 'bcryptjs'
import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { loginLimiter } from '../middleware/rateLimit.js'
import { sendSmsCode, generateCode } from '../utils/sms.js'

const router = Router()

// 从 rbac_role_permissions 表获取角色权限（优先），fallback 到旧 roles 表
async function getRbacPermissions(roleName) {
  try {
    // 查 rbac_roles 表
    const [roleRows] = await pool.query('SELECT id FROM rbac_roles WHERE name = ?', [roleName])
    if (!roleRows.length) return null
    const [permRows] = await pool.query(
      `SELECT p.name FROM rbac_permissions p
       JOIN rbac_role_permissions rp ON p.id = rp.permission_id
       WHERE rp.role_id = ?`,
      [roleRows[0].id]
    )
    return permRows.map(r => r.name)
  } catch { return null }
}

function parsePermissions(perms) {
  if (Array.isArray(perms)) return perms
  if (!perms) return null
  try {
    return JSON.parse(perms)
  } catch {
    return perms.split(',').map(s => s.trim()).filter(Boolean)
  }
}

// 统一权限解析：从 user.permissions 优先取，没有则查 rbac_roles
async function resolvePermissions(user) {
  // 1. 用户个人权限（最高优先）
  let userPerms = parsePermissions(user.permissions)
  if (userPerms && userPerms.length > 0) return userPerms
  // 2. RBAC 动态权限
  if (user.role) {
    userPerms = await getRbacPermissions(user.role)
    if (userPerms) return userPerms
  }
  return []
}

router.post('/login', loginLimiter, async (req, res, next) => {
  try {
    const { email, password } = req.body
    // 兼容 account 字段（新加坡前端用这个）
    const loginField = email || req.body.account
    if (!loginField || !password) {
      return res.status(400).json({ code: 400, message: '请输入邮箱或手机号和密码' })
    }

    // 判断是邮箱还是手机号
    const isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(loginField)
    const isPhone = /^\+?\d{6,20}$/.test(loginField)

    if (!isEmail && !isPhone) {
      return res.status(400).json({ code: 400, message: '请输入有效的邮箱或手机号' })
    }

    // 根据邮箱或手机号查询用户
    const query = isEmail ? 'SELECT * FROM users WHERE email = ?' : 'SELECT * FROM users WHERE phone = ?'
    const [rows] = await pool.query(query, [loginField])

    if (!rows.length) {
      return res.status(401).json({ code: 401, message: '邮箱/手机号或密码错误' })
    }
    const user = rows[0]

    // 检查账号状态
    if (user.status === 'disabled') {
      return res.status(403).json({ code: 403, message: '账号已被禁用' })
    }
    if (user.status === 'pending') {
      return res.status(403).json({ code: 403, message: '账号正在审核中，请等待管理员审核' })
    }
    if (user.status === 'rejected') {
      return res.status(403).json({ code: 403, message: '账号申请已被拒绝' + (user.reject_reason ? `：${user.reject_reason}` : '') })
    }

    const valid = await bcrypt.compare(password, user.password)
    if (!valid) {
      return res.status(401).json({ code: 401, message: '邮箱/手机号或密码错误' })
    }
    await pool.query('UPDATE users SET last_login = NOW() WHERE id = ?', [user.id])

    // 解析用户权限：优先用户个人权限，没有则从 RBAC 角色表动态获取
    const userPerms = await resolvePermissions(user)

    const token = jwt.sign(
      { id: user.id, name: user.name, role: user.role, department: user.department, supplier_id: user.supplier_id || null },
      process.env.JWT_SECRET,
      { expiresIn: '9999d' }
    )
    res.json({
      code: 0,
      data: {
        token,
        user: {
          id: user.id, name: user.name, email: user.email,
          role: user.role, department: user.department,
          supplier_id: user.supplier_id || null,
          permissions: userPerms
        }
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

router.post('/logout', (req, res) => {
  res.json({ code: 0, data: null, message: 'ok' })
})

router.get('/me', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT id, name, email, phone, role, department, department_id, responsibility_id, job_level_id, status, permissions, supplier_id, hire_date, last_login, created_at, avatar, life_photos FROM users WHERE id = ?',
      [req.user.id]
    )
    if (!rows.length) return res.status(404).json({ code: 404, message: '用户不存在' })
    const u = rows[0]
    u.permissions = await resolvePermissions(u)
    res.json({ code: 0, data: u, message: 'ok' })
  } catch (err) { next(err) }
})

// 修改个人信息（姓名、手机号、邮箱、头像、生活照）
router.put('/profile', auth, async (req, res, next) => {
  try {
    const { name, phone, email, avatar, life_photos } = req.body
    const updates = []
    const params = []

    if (name !== undefined) {
      if (!name || name.length > 50) return res.status(400).json({ code: 400, message: '姓名不能为空且不超过50字' })
      updates.push('name = ?')
      params.push(name)
    }
    if (phone !== undefined) {
      if (phone && !/^\+?\d{6,20}$/.test(phone)) return res.status(400).json({ code: 400, message: '手机号格式不正确' })
      if (phone) {
        const [[dup]] = await pool.query('SELECT id FROM users WHERE phone = ? AND id != ?', [phone, req.user.id])
        if (dup) return res.status(400).json({ code: 400, message: '该手机号已被其他用户使用' })
      }
      updates.push('phone = ?')
      params.push(phone || null)
    }
    if (email !== undefined) {
      if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return res.status(400).json({ code: 400, message: '邮箱格式不正确' })
      if (email) {
        const [[dup]] = await pool.query('SELECT id FROM users WHERE email = ? AND id != ?', [email, req.user.id])
        if (dup) return res.status(400).json({ code: 400, message: '该邮箱已被其他用户使用' })
      }
      updates.push('email = ?')
      params.push(email || null)
    }
    if (avatar !== undefined) {
      updates.push('avatar = ?')
      params.push(avatar || null)
    }
    if (life_photos !== undefined) {
      updates.push('life_photos = ?')
      params.push(JSON.stringify(life_photos || []))
    }

    if (!updates.length) return res.status(400).json({ code: 400, message: '没有要更新的字段' })

    params.push(req.user.id)
    await pool.query(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: null, message: '个人信息已更新' })
  } catch (err) { next(err) }
})

// 修改密码（验证旧密码）
router.put('/change-password', auth, async (req, res, next) => {
  try {
    const { oldPassword, newPassword } = req.body
    if (!oldPassword || !newPassword) return res.status(400).json({ code: 400, message: '请输入旧密码和新密码' })
    if (newPassword.length < 6) return res.status(400).json({ code: 400, message: '新密码至少6位' })

    const [rows] = await pool.query('SELECT password FROM users WHERE id = ?', [req.user.id])
    if (!rows.length) return res.status(404).json({ code: 404, message: '用户不存在' })

    const valid = await bcrypt.compare(oldPassword, rows[0].password)
    if (!valid) return res.status(400).json({ code: 400, message: '旧密码不正确' })

    const hashed = await bcrypt.hash(newPassword, 10)
    await pool.query('UPDATE users SET password = ? WHERE id = ?', [hashed, req.user.id])
    res.json({ code: 0, data: null, message: '密码已修改' })
  } catch (err) { next(err) }
})

// 员工注册申请（无需短信验证，待管理员审核）
router.post('/register-employee', async (req, res, next) => {
  try {
    const { name, phone, id_card, password } = req.body

    // 验证必填字段
    if (!name || !phone || !password) {
      return res.status(400).json({ code: 400, message: '姓名、手机号和密码必填' })
    }

    // 验证手机号格式（支持国内外手机号，6-20位）
    if (!/^\+?\d{6,20}$/.test(phone)) {
      return res.status(400).json({ code: 400, message: '手机号格式不正确（6-20位数字，可带+号）' })
    }

    // 验证身份证格式（可选，如果填写则验证至少6位）
    if (id_card && id_card.length < 6) {
      return res.status(400).json({ code: 400, message: '身份证号至少6位' })
    }

    // 验证密码长度
    if (password.length < 6) {
      return res.status(400).json({ code: 400, message: '密码至少6位' })
    }

    // 检查手机号是否已存在
    const [[existingPhone]] = await pool.query('SELECT id FROM users WHERE phone = ?', [phone])
    if (existingPhone) {
      return res.status(400).json({ code: 400, message: '该手机号已被注册' })
    }

    // 检查身份证是否已存在（仅当提供了身份证号时）
    if (id_card && id_card.trim() !== '') {
      const [[existingIdCard]] = await pool.query('SELECT id FROM users WHERE id_card = ?', [id_card])
      if (existingIdCard) {
        return res.status(400).json({ code: 400, message: '该身份证号已被注册' })
      }
    }

    // 加密密码
    const hashedPassword = await bcrypt.hash(password, 10)

    // 生成邮箱（使用手机号@gdqshop.cn）
    const email = `${phone}@gdqshop.cn`

    // 插入用户记录，状态为pending
    const [result] = await pool.query(
      `INSERT INTO users (name, phone, id_card, email, password, role, status, applied_at)
       VALUES (?, ?, ?, ?, ?, 'operator', 'pending', NOW())`,
      [name, phone, id_card, email, hashedPassword]
    )

    res.json({
      code: 0,
      data: { id: result.insertId },
      message: '注册申请已提交，请等待管理员审核'
    })
  } catch (err) {
    next(err)
  }
})

// POST /api/auth/send-code — 发送短信验证码（忘记密码用）
router.post('/send-code', async (req, res, next) => {
  try {
    const { phone } = req.body
    if (!phone) return res.status(400).json({ code: 400, message: '请输入手机号' })
    if (!/^\+?\d{6,20}$/.test(phone)) return res.status(400).json({ code: 400, message: '手机号格式不正确' })

    // 检查用户是否存在
    const [[user]] = await pool.query('SELECT id FROM users WHERE phone = ? AND status = ?', [phone, 'active'])
    if (!user) return res.status(404).json({ code: 404, message: '该手机号未注册或账号未激活' })

    // 频率限制：60秒内不能重复发送
    const [[recent]] = await pool.query(
      'SELECT id FROM sms_codes WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)',
      [phone]
    )
    if (recent) return res.status(429).json({ code: 429, message: '发送过于频繁，请稍后再试' })

    const code = generateCode()
    const expiresAt = new Date(Date.now() + 5 * 60 * 1000)
    const ip = req.ip || req.connection.remoteAddress

    await pool.query('INSERT INTO sms_codes (phone, code, expires_at, ip) VALUES (?, ?, ?, ?)', [phone, code, expiresAt, ip])

    const sent = await sendSmsCode(phone, code)
    if (!sent) return res.status(500).json({ code: 500, message: '短信发送失败，请稍后重试' })

    res.json({ code: 0, data: null, message: '验证码已发送' })
  } catch (err) { next(err) }
})

// POST /api/auth/reset-password — 通过短信验证码重置密码
router.post('/reset-password', async (req, res, next) => {
  try {
    const { phone, code, new_password } = req.body
    if (!phone || !code || !new_password) return res.status(400).json({ code: 400, message: '手机号、验证码和新密码必填' })
    if (new_password.length < 6) return res.status(400).json({ code: 400, message: '密码至少6位' })

    // 验证短信验证码
    const [[smsCode]] = await pool.query(
      'SELECT id, code, used FROM sms_codes WHERE phone = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1',
      [phone]
    )
    if (!smsCode) return res.status(400).json({ code: 400, message: '验证码不存在或已过期' })
    if (smsCode.used) return res.status(400).json({ code: 400, message: '验证码已使用' })
    if (smsCode.code !== code) return res.status(400).json({ code: 400, message: '验证码错误' })

    // 标记验证码已使用
    await pool.query('UPDATE sms_codes SET used = 1 WHERE id = ?', [smsCode.id])

    // 更新密码
    const hashed = await bcrypt.hash(new_password, 10)
    await pool.query('UPDATE users SET password = ? WHERE phone = ?', [hashed, phone])

    res.json({ code: 0, data: null, message: '密码重置成功' })
  } catch (err) { next(err) }
})

export default router
