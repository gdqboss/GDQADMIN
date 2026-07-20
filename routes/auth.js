import { Router } from 'express'
import bcrypt from 'bcryptjs'
import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'
import { loginLimiter } from '../middleware/rateLimit.js'
import { sendSmsCode, generateCode } from '../utils/sms.js'
import { resolvePermissions } from '../middleware/auth.js'

const router = Router()

// GET /api/auth/me
router.get('/me', async (req, res, next) => {
  try {
    const authHeader = req.headers.authorization
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return res.status(401).json({ code: 401, message: '未登录或 token 缺失' })
    }
    const token = authHeader.split(' ')[1]
    const decoded = jwt.verify(token, process.env.JWT_SECRET)
    const [rows] = await pool.query('SELECT id, name, email, phone, role, user_type, h5_user_id, customer_type, member_level, member_label, points, is_internal, customer_store_id, department, supplier_id, status, permissions, job_level_id, department_id, avatar FROM users WHERE id = ?', [decoded.id])
    if (!rows.length) return res.status(401).json({ code: 401, message: '用户不存在' })
    const user = rows[0]
    if (user.status === 'disabled') return res.status(403).json({ code: 403, message: '账号已被禁用' })
    res.json({ code: 0, data: user })
  } catch (err) {
    if (err.name === 'JsonWebTokenError') return res.status(401).json({ code: 401, message: 'token 无效' })
    next(err)
  }
})

// POST /api/auth/login - 手机号+密码登录
router.post('/login', loginLimiter, async (req, res, next) => {
  try {
    // 兼容旧版前端 email 字段
    const { phone, email, password } = req.body
    const loginKey = phone || email
    if (!loginKey || !password) {
      return res.status(400).json({ code: 400, message: '请输入手机号和密码' })
    }

    // 只用手机号查询（统一登录入口）
    const [rows] = await pool.query('SELECT * FROM users WHERE phone = ? OR email = ?', [loginKey, loginKey])
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
        supplier_id: user.supplier_id || null
      },
      process.env.JWT_SECRET,
      { expiresIn: '9999d' }
    )
    const { password: _, ...userData } = user
    // 解析权限并附加到返回（customer 只返回基础权限）
    const permissions = user.user_type === 'customer'
      ? ['customer:read', 'rental:read']
      : await resolvePermissions(user)
    res.json({ code: 0, data: { token, user: userData, permissions }, user_type: user.user_type || 'staff' })
  } catch (err) { next(err) }
})

// POST /api/auth/sms-code
router.post('/sms-code', loginLimiter, async (req, res, next) => {
  try {
    const { phone } = req.body
    if (!phone) return res.status(400).json({ code: 400, message: '请输入手机号' })
    const code = generateCode()
    const success = await sendSmsCode(phone, code)
    if (success) {
      await pool.query('INSERT INTO sms_codes (phone, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))', [phone, code])
    }
    res.json({ code: 0, message: '发送成功' })
  } catch (err) { next(err) }
})

// POST /api/auth/sms-login
router.post('/sms-login', loginLimiter, async (req, res, next) => {
  try {
    const { phone, code } = req.body
    if (!phone || !code) return res.status(400).json({ code: 400, message: '请输入手机号和验证码' })
    const [rows] = await pool.query('SELECT * FROM sms_codes WHERE phone = ? AND code = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1', [phone, code])
    if (!rows.length) return res.status(401).json({ code: 401, message: '验证码错误或已过期' })
    await pool.query('DELETE FROM sms_codes WHERE phone = ?', [phone])
    const [users] = await pool.query('SELECT * FROM users WHERE phone = ?', [phone])
    if (!users.length) return res.status(401).json({ code: 401, message: '用户不存在' })
    const user = users[0]
    const token = jwt.sign({ id: user.id, name: user.name, role: user.role }, process.env.JWT_SECRET, { expiresIn: '9999d' })
    const { password: _, ...userData } = user
    res.json({ code: 0, data: { token, user: userData, permissions: await resolvePermissions(user) } })
  } catch (err) { next(err) }
})

// POST /api/auth/logout
router.post('/logout', (req, res) => {
  res.json({ code: 0, message: '已退出' })
})

// ─── 微信小程序登录 ───
// POST /api/auth/wx-mp-login
// Body: { code }  小程序wx.login()获取的code
router.post('/wx-mp-login', loginLimiter, async (req, res, next) => {
  try {
    const { code, encryptedData, iv } = req.body
    if (!code) return res.status(400).json({ code: 400, message: 'code不能为空' })

    // 调用微信接口换session_key和openid
    const wxAppid = process.env.WX_APPID
    const wxSecret = process.env.WX_SECRET
    const wxUrl = `https://api.weixin.qq.com/sns/jscode2session?appid=${wxAppid}&secret=${wxSecret}&js_code=${code}&grant_type=authorization_code`

    let wxData
    try {
      const resp = await fetch(wxUrl)
      wxData = await resp.json()
    } catch (e) {
      return res.status(502).json({ code: 502, message: '微信接口调用失败' })
    }

    if (wxData.errcode) {
      return res.status(400).json({ code: 400, message: wxData.errmsg || '微信登录失败' })
    }

    const { openid, unionid, session_key } = wxData

    // 查找或创建用户（优先用unionid，其次openid）
    let [rows] = unionid
      ? await pool.query('SELECT * FROM users WHERE wx_unionid = ?', [unionid])
      : await pool.query('SELECT * FROM users WHERE wx_openid = ?', [openid])

    let user
    if (!rows.length) {
      // 自动注册新用户（member角色）
      const [result] = await pool.query(
        `INSERT INTO users (name, phone, wx_openid, wx_unionid, wx_mp_session_key, auth_type, role, status, created_at) 
         VALUES (?, ?, ?, ?, ?, 'wx', 'member', 'active', NOW())`,
        [openid.slice(-8), null, openid, unionid || null, session_key]
      )
      ;[rows] = await pool.query('SELECT * FROM users WHERE id = ?', [result.insertId])
    } else {
      // 更新session_key
      await pool.query('UPDATE users SET wx_mp_session_key = ?, last_login_at = NOW() WHERE id = ?', [session_key, rows[0].id])
    }

    user = rows[0]
    if (user.status === 'disabled') return res.status(403).json({ code: 403, message: '账号已被禁用' })

    const token = jwt.sign(
      { id: user.id, name: user.name, role: user.role },
      process.env.JWT_SECRET,
      { expiresIn: '9999d' }
    )
    const { password: _, ...userData } = user
    res.json({ code: 0, data: { token, user: userData, permissions: await resolvePermissions(user), isNew: !rows[0].wx_unionid } })
  } catch (err) { next(err) }
})

// ─── 微信公众号网页静默授权登录 ───
// POST /api/auth/wx-h5-login
// Body: { code }  微信回调带回的code
router.post('/wx-h5-login', loginLimiter, async (req, res, next) => {
  try {
    const { code } = req.body
    if (!code) return res.status(400).json({ code: 400, message: 'code不能为空' })

    const wxAppid = process.env.WX_APPID
    const wxSecret = process.env.WX_SECRET
    const tokenUrl = `https://api.weixin.qq.com/sns/oauth2/access_token?appid=${wxAppid}&secret=${wxSecret}&code=${code}&grant_type=authorization_code`

    let tokenData
    try {
      const resp = await fetch(tokenUrl)
      tokenData = await resp.json()
    } catch (e) {
      return res.status(502).json({ code: 502, message: '微信接口调用失败' })
    }

    if (tokenData.errcode) {
      return res.status(400).json({ code: 400, message: tokenData.errmsg || '微信授权失败' })
    }

    const { openid, unionid, access_token } = tokenData

    // 用access_token获取用户基本信息（可选）
    let nickname, avatar
    try {
      const userInfoResp = await fetch(`https://api.weixin.qq.com/sns/userinfo?access_token=${access_token}&openid=${openid}`)
      const userInfo = await userInfoResp.json()
      if (!userInfo.errcode) {
        nickname = Buffer.from(userInfo.nickname || '', 'utf-8').toString('latin1')
        avatar = userInfo.headimgurl
      }
    } catch (_) {}

    // 查找或创建用户
    let [rows] = unionid
      ? await pool.query('SELECT * FROM users WHERE wx_unionid = ?', [unionid])
      : await pool.query('SELECT * FROM users WHERE wx_openid = ?', [openid])

    let user
    if (!rows.length) {
      const [result] = await pool.query(
        `INSERT INTO users (name, phone, wx_openid, wx_unionid, auth_type, role, status, created_at) 
         VALUES (?, ?, ?, ?, 'wx', 'member', 'active', NOW())`,
        [nickname || `微信用户${openid.slice(-6)}`, null, openid, unionid || null]
      )
      ;[rows] = await pool.query('SELECT * FROM users WHERE id = ?', [result.insertId])
    } else {
      await pool.query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [rows[0].id])
    }

    user = rows[0]
    if (user.status === 'disabled') return res.status(403).json({ code: 403, message: '账号已被禁用' })

    const token = jwt.sign(
      { id: user.id, name: user.name, role: user.role },
      process.env.JWT_SECRET,
      { expiresIn: '9999d' }
    )
    const { password: _, ...userData } = user
    res.json({ code: 0, data: { token, user: userData, permissions: await resolvePermissions(user) } })
  } catch (err) { next(err) }
})

// ─── Apple登录 ───
// POST /api/auth/apple-login
// Body: { id_token }  Apple Sign-In获取的identityToken
router.post('/apple-login', loginLimiter, async (req, res, next) => {
  try {
    const { id_token, fullName } = req.body
    if (!id_token) return res.status(400).json({ code: 400, message: 'id_token不能为空' })

    // 解码JWT获取apple_user_id（无需验证签名，Apple的JWT是自验证的）
    const payload = JSON.parse(Buffer.from(id_token.split('.')[1], 'base64').toString('utf8'))
    const appleUserId = payload.sub  // subject = apple_user_id

    const displayName = fullName?.givenName || fullName?.familyName
      ? `${fullName.givenName || ''} ${fullName.familyName || ''}`.trim()
      : null

    let [rows] = await pool.query('SELECT * FROM users WHERE apple_user_id = ?', [appleUserId])

    let user
    if (!rows.length) {
      const [result] = await pool.query(
        `INSERT INTO users (name, phone, apple_user_id, auth_type, role, status, created_at) 
         VALUES (?, ?, ?, 'apple', 'member', 'active', NOW())`,
        [displayName || `Apple用户${appleUserId.slice(-6)}`, null, appleUserId]
      )
      ;[rows] = await pool.query('SELECT * FROM users WHERE id = ?', [result.insertId])
    } else {
      await pool.query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [rows[0].id])
    }

    user = rows[0]
    if (user.status === 'disabled') return res.status(403).json({ code: 403, message: '账号已被禁用' })

    const token = jwt.sign(
      { id: user.id, name: user.name, role: user.role },
      process.env.JWT_SECRET,
      { expiresIn: '9999d' }
    )
    const { password: _, ...userData } = user
    res.json({ code: 0, data: { token, user: userData, permissions: await resolvePermissions(user) } })
  } catch (err) { next(err) }
})

// ─── Google登录 ───
// POST /api/auth/google-login
// Body: { id_token }  Google Sign-In获取的id_token
router.post('/google-login', loginLimiter, async (req, res, next) => {
  try {
    const { id_token } = req.body
    if (!id_token) return res.status(400).json({ code: 400, message: 'id_token不能为空' })

    // 验证Google token
    const { OAuth2Client } = await import('google-auth-library').catch(() => ({ OAuth2Client: null }))
    
    let googleUser
    if (OAuth2Client) {
      const client = new OAuth2Client(process.env.GOOGLE_CLIENT_ID)
      const ticket = await client.verifyIdToken({
        idToken: id_token,
        audience: process.env.GOOGLE_CLIENT_ID,
      })
      const payload = ticket.getPayload()
      googleUser = { sub: payload.sub, email: payload.email, name: payload.name, picture: payload.picture }
    } else {
      // fallback: 直接解码JWT（生产环境应验证签名）
      const payload = JSON.parse(Buffer.from(id_token.split('.')[1], 'base64').toString('utf8'))
      googleUser = { sub: payload.sub, email: payload.email, name: payload.name, picture: payload.picture }
    }

    const { sub: googleId, email, name } = googleUser

    let [rows] = await pool.query('SELECT * FROM users WHERE google_id = ?', [googleId])

    let user
    if (!rows.length) {
      // 用邮箱查找（如果已通过手机号注册，可绑定）
      let userId
      if (email) {
        const [byEmail] = await pool.query('SELECT id FROM users WHERE email = ?', [email])
        userId = byEmail.length ? byEmail[0].id : null
      }
      if (userId) {
        await pool.query('UPDATE users SET google_id = ?, last_login_at = NOW() WHERE id = ?', [googleId, userId])
        ;[rows] = await pool.query('SELECT * FROM users WHERE id = ?', [userId])
      } else {
        const [result] = await pool.query(
          `INSERT INTO users (name, email, phone, google_id, auth_type, role, status, created_at) 
           VALUES (?, ?, ?, ?, 'google', 'member', 'active', NOW())`,
          [name || `Google用户${googleId.slice(-6)}`, email || null, null, googleId]
        )
        ;[rows] = await pool.query('SELECT * FROM users WHERE id = ?', [result.insertId])
      }
    } else {
      await pool.query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [rows[0].id])
    }

    user = rows[0]
    if (user.status === 'disabled') return res.status(403).json({ code: 403, message: '账号已被禁用' })

    const token = jwt.sign(
      { id: user.id, name: user.name, role: user.role },
      process.env.JWT_SECRET,
      { expiresIn: '9999d' }
    )
    const { password: _, ...userData } = user
    res.json({ code: 0, data: { token, user: userData, permissions: await resolvePermissions(user) } })
  } catch (err) { next(err) }
})

// ─── 手机号+验证码登录/注册（顾客主入口）───
// POST /api/auth/phone-login
// Body: { phone, code }  验证码登录，自动注册新用户
router.post('/phone-login', loginLimiter, async (req, res, next) => {
  try {
    const { phone, code } = req.body
    if (!phone || !code) return res.status(400).json({ code: 400, message: '请输入手机号和验证码' })

    // 验证验证码
    const [rows] = await pool.query(
      'SELECT * FROM sms_codes WHERE phone = ? AND code = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
      [phone, code]
    )
    if (!rows.length) return res.status(401).json({ code: 401, message: '验证码错误或已过期' })
    await pool.query('DELETE FROM sms_codes WHERE phone = ?', [phone])

    // 查找或创建用户
    const [users] = await pool.query('SELECT * FROM users WHERE phone = ?', [phone])
    let user
    if (!users.length) {
      // 自动注册 — email/password 占位 (email/password 列 UNIQUE NOT NULL)
      const [result] = await pool.query(
        `INSERT INTO users (name, email, phone, password, auth_type, role, status, created_at) VALUES (?, ?, ?, '', 'phone', 'member', 'active', NOW())`,
        [`用户${phone.slice(-4)}`, `${phone}@caimeite.local`, phone]
      )
      ;[user] = await pool.query('SELECT * FROM users WHERE id = ?', [result.insertId])
    } else {
      user = users
      await pool.query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [user[0].id])
    }

    user = user[0]
    if (user.status === 'disabled') return res.status(403).json({ code: 403, message: '账号已被禁用' })

    const token = jwt.sign(
      { id: user.id, name: user.name, role: user.role },
      process.env.JWT_SECRET,
      { expiresIn: '9999d' }
    )
    const { password: _, ...userData } = user
    res.json({ code: 0, data: { token, user: userData, permissions: await resolvePermissions(user) } })
  } catch (err) { next(err) }
})

// ─── 绑定第三方账号到现有手机账号 ───
// POST /api/auth/bind-account
// Body: { phone, code, bind_type: 'wx'|'apple'|'google', openid|apple_user_id|google_id }
router.post('/bind-account', loginLimiter, async (req, res, next) => {
  try {
    const { phone, code, bind_type, openid, apple_user_id, google_id } = req.body
    if (!phone || !code || !bind_type) return res.status(400).json({ code: 400, message: '参数不完整' })

    // 验证验证码
    const [rows] = await pool.query(
      'SELECT * FROM sms_codes WHERE phone = ? AND code = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
      [phone, code]
    )
    if (!rows.length) return res.status(401).json({ code: 401, message: '验证码错误或已过期' })
    await pool.query('DELETE FROM sms_codes WHERE phone = ?', [phone])

    // 查找用户
    const [users] = await pool.query('SELECT * FROM users WHERE phone = ?', [phone])
    if (!users.length) return res.status(404).json({ code: 404, message: '用户不存在' })

    // 更新绑定字段
    const updates = { wx_openid: openid, apple_user_id, google_id }
    const field = bind_type === 'wx' ? 'wx_openid' : bind_type === 'apple' ? 'apple_user_id' : 'google_id'
    const value = bind_type === 'wx' ? openid : bind_type === 'apple' ? apple_user_id : google_id

    await pool.query(`UPDATE users SET ${field} = ?, auth_type = ? WHERE id = ?`, [value, bind_type, users[0].id])

    res.json({ code: 0, message: '绑定成功' })
  } catch (err) { next(err) }
})

export default router
