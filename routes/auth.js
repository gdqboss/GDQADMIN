import { Router } from 'express'
import bcrypt from 'bcryptjs'
import jwt from 'jsonwebtoken'
import crypto from 'crypto'
import { pool } from '../db/connection.js'
import { loginLimiter } from '../middleware/rateLimit.js'
import { sendSmsCode, generateCode } from '../utils/sms.js'
import { resolvePermissions } from '../middleware/auth.js'

const router = Router()

// SHA256(token) — 不存明文, 用于 SSO session revoke
export function hashToken(token) {
  return crypto.createHash('sha256').update(token).digest('hex')
}

// 提取客户端 device 指纹 (frontend 在 header 传 X-Device-Label, 否则 fallback to ua)
function extractDevice(req) {
  const ua = req.headers['user-agent'] || ''
  const label = req.headers['x-device-label'] || ''
  // 简易 fingerprint: ua 前 60 字符 + accept-language
  const fp = crypto.createHash('sha256')
    .update(ua.slice(0, 60) + (req.headers['accept-language'] || ''))
    .digest('hex')
  let autoLabel = ''
  if (/iPhone/.test(ua)) autoLabel = 'iPhone'
  else if (/iPad/.test(ua)) autoLabel = 'iPad'
  else if (/Android/.test(ua)) autoLabel = /Mobile/.test(ua) ? 'Android' : 'Android Tablet'
  else if (/Edg\//.test(ua)) autoLabel = 'Edge'
  else if (/Chrome\//.test(ua)) autoLabel = 'Chrome'
  else if (/Firefox\//.test(ua)) autoLabel = 'Firefox'
  else if (/Safari\//.test(ua)) autoLabel = 'Safari'
  else autoLabel = 'Unknown'
  return {
    fingerprint: fp,
    label: label || autoLabel,
    ip: req.ip || req.headers['x-forwarded-for'] || '',
    ua
  }
}

// GET /api/auth/permissions - 当前用户完整权限点列表 (供 labor SmartBiz SPA 用)
// admin 永远返所有 enabled permissions (跟 /api/auth/login 行为一致)
// 其他用户返 merge rbac_role_permissions + user.permissions
router.get('/permissions', async (req, res, next) => {
  try {
    const authHeader = req.headers.authorization
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return res.status(401).json({ code: 401, message: '未登录或 token 缺失' })
    }
    const token = authHeader.split(' ')[1]
    const decoded = jwt.verify(token, process.env.JWT_SECRET)
    const [rows] = await pool.query(
      'SELECT id, role, permissions, user_type, status FROM users WHERE id = ?',
      [decoded.id]
    )
    if (!rows.length) return res.status(401).json({ code: 401, message: '用户不存在' })
    const user = rows[0]
    if (user.status === 'disabled') return res.status(403).json({ code: 403, message: '账号已被禁用' })
    // customer 给基础权限(对齐 login 行为)
    const permissions = user.user_type === 'customer'
      ? ['customer:read', 'rental:read']
      : await resolvePermissions(user)
    res.json({ code: 0, data: permissions })
  } catch (err) {
    if (err.name === 'JsonWebTokenError') return res.status(401).json({ code: 401, message: 'token 无效' })
    next(err)
  }
})

// GET /api/auth/me
router.get('/me', async (req, res, next) => {
  try {
    const authHeader = req.headers.authorization
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return res.status(401).json({ code: 401, message: '未登录或 token 缺失' })
    }
    const token = authHeader.split(' ')[1]
    const decoded = jwt.verify(token, process.env.JWT_SECRET)

    // 2026-08-25 SSO: 检查 token 是否已被踢 (session invalidated)
    const [sessRows] = await pool.query(
      'SELECT invalidated FROM user_sessions WHERE token_hash = ? LIMIT 1',
      [hashToken(token)]
    )
    if (sessRows.length > 0 && sessRows[0].invalidated === 1) {
      return res.status(401).json({ code: 401, message: '登录已失效, 账号在另一设备登录' })
    }
    // 更新 last_active_at (fire-and-forget)
    pool.query('UPDATE user_sessions SET last_active_at = NOW() WHERE token_hash = ?', [hashToken(token)]).catch(() => {})

    const [rows] = await pool.query('SELECT id, name, email, phone, role, user_type, h5_user_id, customer_type, member_level, member_label, points, is_internal, customer_store_id, department, supplier_id, status, job_level_id, department_id, avatar FROM users WHERE id = ?', [decoded.id])
    if (!rows.length) return res.status(401).json({ code: 401, message: '用户不存在' })
    const user = rows[0]
    if (user.status === 'disabled') return res.status(403).json({ code: 403, message: '账号已被禁用' })
    // 2026-08-25 修复: 用 resolvePermissions 动态算权限, 而不是 SELECT users.permissions 列
    // 原因: users.permissions 列历史上很多用户是 NULL, 但用户其实有 role + role_permissions 关联
    // login 路由已经用 resolvePermissions, /me 必须保持一致, 否则刷新后 userStore.canAccess 失效
    const permissions = user.user_type === 'customer'
      ? ['customer:read', 'rental:read']
      : await resolvePermissions(user)
    res.json({ code: 0, data: { ...user, permissions } })
  } catch (err) {
    if (err.name === 'JsonWebTokenError') return res.status(401).json({ code: 401, message: 'token 无效' })
    next(err)
  }
})

// PUT /api/auth/profile - 更新当前用户个人资料（含名片字段）
// 2026-08-28 JXY: 名片数据源 = users 表, gdqadmin 个人信息页编辑此处即联动电子名片
// 白名单更新, 未传字段不覆盖
const PROFILE_UPDATABLE = [
  'avatar', 'life_photos',                  // 头像 + 生活照
  'name', 'title', 'department',            // 姓名 / 职位 / 部门
  'company_name', 'company_name_en',        // 公司名(中/英) — 名片
  'company_address', 'company_address_en',  // 公司地址(中/英)
  'company_phone', 'wechat',                // 公司电话 / 微信(名片)
  'bio', 'bio_en',                          // 简介(中/英) — 名片
  'card_bg', 'images',                      // 名片背景图 / 名片图片集
]
router.put('/profile', async (req, res, next) => {
  try {
    const authHeader = req.headers.authorization
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return res.status(401).json({ code: 401, message: '未登录或 token 缺失' })
    }
    const token = authHeader.split(' ')[1]
    const decoded = jwt.verify(token, process.env.JWT_SECRET)
    const uid = decoded.id
    if (!uid) return res.status(401).json({ code: 401, message: '未登录' })

    const body = req.body || {}
    const set = []
    const params = []
    for (const field of PROFILE_UPDATABLE) {
      if (!(field in body)) continue
      let val = body[field]
      if ((field === 'life_photos' || field === 'images') && Array.isArray(val)) {
        val = JSON.stringify(val.slice(0, 9))
      }
      set.push(`\`${field}\` = ?`)
      params.push(val === undefined ? null : val)
    }
    if (!set.length) return res.status(400).json({ code: 400, message: '没有可更新的字段' })
    params.push(uid)
    const sql = `UPDATE users SET ${set.join(', ')} WHERE id = ?`
    await pool.query(sql, params)

    const [rows] = await pool.query(
      'SELECT id, name, email, phone, role, user_type, avatar, title, department, company_name, company_name_en, company_address, company_phone, wechat, bio, bio_en, card_bg, images, life_photos, endorsements, card_views FROM users WHERE id = ?', [uid])
    const user = rows[0]
    if (user) {
      if (user.images) { try { user.images = JSON.parse(user.images) } catch { user.images = [] } }
      if (user.life_photos) { try { user.life_photos = JSON.parse(user.life_photos) } catch { user.life_photos = [] } }
    }
    res.json({ code: 0, data: user, message: 'ok' })
  } catch (err) {
    if (err.name === 'JsonWebTokenError') return res.status(401).json({ code: 401, message: 'token 无效' })
    next(err)
  }
})

// POST /api/auth/login - 手机号+密码登录
// 2026-08-25 gbaw.cn/gdqadmin SSO: 同账号已有 active session → 返 409 needConfirm
//  - 前端弹"该账号已在 [设备] 于 [时间] 登录，是否强制登录？"
//  - 强制登录: 客户端带 force_login=1 重发, 后端将旧 session 标记 invalidated=1 再签新 token
router.post('/login', loginLimiter, async (req, res, next) => {
  try {
    // 兼容旧版前端 email 字段
    const { phone, email, password, force_login } = req.body
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

    // ─── SSO: 检查同账号是否有 active session ───
    const device = extractDevice(req)
    const [activeSessions] = await pool.query(
      `SELECT id, device_label, ip, created_at, device_fingerprint
       FROM user_sessions
       WHERE user_id = ? AND invalidated = 0
       ORDER BY created_at DESC`,
      [user.id]
    )
    // 同设备 (fingerprint 相同) → 静默踢掉旧 session, 直接放行 (典型场景: 用户清缓存重登)
    // 不同设备 → 返 409 让前端弹确认框
    const sameDeviceSessions = activeSessions.filter(s => s.device_fingerprint === device.fingerprint)
    const otherDeviceSessions = activeSessions.filter(s => s.device_fingerprint !== device.fingerprint)

    if (otherDeviceSessions.length > 0 && !force_login) {
      const s = otherDeviceSessions[0]
      return res.status(409).json({
        code: 409,
        message: '该账号已在其他设备登录',
        data: {
          needConfirm: true,
          activeSession: {
            deviceLabel: s.device_label,
            ip: s.ip,
            loginAt: s.created_at
          },
          activeSessionCount: otherDeviceSessions.length
        }
      })
    }

    // force_login 或 同设备 或 首次登录 → 先踢掉所有旧 session (同账号)
    if (activeSessions.length > 0) {
      await pool.query(
        `UPDATE user_sessions SET invalidated = 1, invalidated_at = NOW(), invalidated_reason = ?
         WHERE user_id = ? AND invalidated = 0`,
        [force_login ? 'new_login_force' : 'new_login_same_device', user.id]
      )
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

    // 写新 session
    await pool.query(
      `INSERT INTO user_sessions (user_id, token_hash, device_fingerprint, device_label, ip, user_agent)
       VALUES (?, ?, ?, ?, ?, ?)`,
      [user.id, hashToken(token), device.fingerprint, device.label, device.ip, device.ua.slice(0, 250)]
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
// 2026-08-25 SSO: 标记当前 token 对应 session 为 invalidated=1
router.post('/logout', async (req, res) => {
  try {
    const header = req.headers.authorization
    if (header && header.startsWith('Bearer ')) {
      const token = header.slice(7)
      await pool.query(
        `UPDATE user_sessions SET invalidated = 1, invalidated_at = NOW(), invalidated_reason = 'logout'
         WHERE token_hash = ? AND invalidated = 0`,
        [hashToken(token)]
      )
    }
  } catch (e) { /* 静默失败, 前端无感知 */ }
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

// POST /api/auth/workbuddy-token - 2026-08-29
// 给 WorkBuddy APP 用的"用户级 token"生成入口。
// 用户用自己的登录用户名(手机/邮箱)+ 密码, 拿一个长期 JWT
// 默认 365 天, 最长 3650 天 (10年), 直到管理员禁用该用户或主动 revoke。
// 这个 token 用的是用户自己的 rbac 权限, 跟 gdqadmin 后台一致。
//
// Body: { login_key, password, expires_days? }
// Response: { code:0, data: { token, user_id, name, role, permissions, server_url, expires_at, expires_in_days } }
//
// 设计要点 (波哥 2026-08-29 修订):
// 1. 不复用 service-token (那是 admin 全权, 所有用户共用 = 权限泄露)
// 2. 用登录密码鉴权 — 只有知道密码的人才能生成 (admin 不能替别人生)
// 3. **长期有效** — 默认 365 天, 直到 (a) 管理员禁用户 (b) 管理员主动 revoke (c) 改密码
// 4. auth 中间件实时校验 users.status='active' + user_sessions.invalidated=0
//    → 用户被禁用或 token 被踢下线 → 立即 403, 无需等 JWT 过期
// 5. token 走标准 jwt, 中间件 auth() 自动按 req.user.role + resolvePermissions 判权
router.post('/workbuddy-token', loginLimiter, async (req, res, next) => {
  try {
    const { login_key, password, expires_days } = req.body
    if (!login_key || !password) {
      return res.status(400).json({ code: 400, message: 'login_key 和 password 必填' })
    }

    // 长期有效: 默认 365 天, 范围 1-3650 天 (10年), 传 0 或负数 = 用默认
    const days = Math.max(1, Math.min(3650, parseInt(expires_days) || 365))

    // 查找用户 — 2026-09-13 扩 H5: 先查 users (staff 内网), 再查 h5_users (H5 外部用户)
    // 两个表结构平行 (id/name/phone/password/role/status) 但完全独立, 不能 JOIN
    const isEmail = login_key.includes('@')
    let user = null
    let userSource = null // 'staff' | 'h5'
    if (isEmail) {
      // email 字段只有 users 表有, h5_users 没 email 列
      const [rows] = await pool.query(
        'SELECT id, name, phone, email, password, role, user_type, status FROM users WHERE email = ? LIMIT 1',
        [login_key]
      )
      if (rows.length) { user = rows[0]; userSource = 'staff' }
    } else {
      // phone 同时存在两张表 — 先 staff, 找不到再 h5
      const [staffRows] = await pool.query(
        'SELECT id, name, phone, email, password, role, user_type, status FROM users WHERE phone = ? LIMIT 1',
        [login_key]
      )
      if (staffRows.length) { user = staffRows[0]; userSource = 'staff' }
      else {
        const [h5Rows] = await pool.query(
          'SELECT id, name, phone, password, role, status FROM h5_users WHERE phone = ? LIMIT 1',
          [login_key]
        )
        if (h5Rows.length) { user = h5Rows[0]; userSource = 'h5' }
      }
    }
    if (!user) return res.status(401).json({ code: 401, message: '账号或密码错误' })

    // 状态校验
    if (user.status === 'disabled') {
      return res.status(403).json({ code: 403, message: '账号已被禁用' })
    }
    if (user.status === 'pending') {
      return res.status(403).json({ code: 403, message: '账号待审核, 请联系管理员' })
    }

    // 校验密码
    const valid = await bcrypt.compare(password, user.password)
    if (!valid) return res.status(401).json({ code: 401, message: '账号或密码错误' })

    // 生成短期 JWT (days 天过期) — 2026-09-13 加 user_source 让 middleware 知道去哪查 status
    const expiresIn = `${days}d`
    const token = jwt.sign(
      { id: user.id, name: user.name, role: user.role, kind: 'workbuddy', user_source: userSource },
      process.env.JWT_SECRET,
      { expiresIn }
    )

    // 算权限 (复用 resolvePermissions, 跟 /api/auth/login 完全一致)
    const permissions = await resolvePermissions(user)

    // 记录 session (用于 SSO revoke — 用户在 gdqadmin 撤销后, token 失效)
    // 复用 user_sessions 表 (跟 /api/auth/login 一样的机制)
    const tokenHash = hashToken(token)
    const expiresAt = new Date(Date.now() + days * 24 * 60 * 60 * 1000)
    const device = extractDevice(req)
    // 2026-09-13 江小鱼 fix: user_sessions 表无 login_at 列 (只有 created_at + last_active_at), 改用 created_at NOW() 兼 INSERT ON DUPLICATE 续期
    pool.query(
      `INSERT INTO user_sessions (user_id, token_hash, device_label, device_fingerprint, ip, user_agent, created_at, last_active_at, invalidated)
       VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)
       ON DUPLICATE KEY UPDATE last_active_at = NOW()`,
      [user.id, tokenHash, `WorkBuddy-${device.label}`, device.fingerprint, device.ip, device.ua]
    ).catch(err => console.error('[workbuddy-token] session insert failed:', err.message))

    // 返回 — 带上 server_url (APP 复制时知道连哪)
    res.json({
      code: 0,
      data: {
        token,
        user_id: user.id,
        name: user.name,
        role: user.role,
        user_source: userSource, // 2026-09-13 加: 'staff' 内网 / 'h5' 外部, 方便 APP 端分流
        permissions,
        server_url: `${req.protocol}://${req.get('host')}`,
        expires_at: expiresAt.toISOString(),
        expires_in_days: days,
        // 给 APP 的"复制链接"格式, 用户粘到 APP 即可
        connect_link: `workbuddy://connect?server=${encodeURIComponent(req.protocol + '://' + req.get('host'))}&token=${token}`,
      },
    })
  } catch (err) { next(err) }
})

// POST /api/auth/register-employee - 员工注册申请
// 2026-08-16 波哥实测发现: 前端 Login.vue 调此端点但后端缺失, 返 401
// 行为: 入库 users 表 (status='pending' 等 admin 后台审核), 不发 token
// 字段: { name, phone, id_card?, password }
router.post('/register-employee', async (req, res, next) => {
  try {
    const { name, phone, id_card, password } = req.body
    if (!name || !phone || !password) {
      return res.status(400).json({ code: 400, message: '姓名、手机号、密码必填' })
    }
    if (!/^1[3-9]\d{9}$/.test(phone)) {
      return res.status(400).json({ code: 400, message: '手机号格式不正确' })
    }
    if (password.length < 6) {
      return res.status(400).json({ code: 400, message: '密码至少6位' })
    }
    if (id_card && id_card.length > 0 && id_card.length !== 18) {
      return res.status(400).json({ code: 400, message: '身份证号必须为18位' })
    }

    // 检查手机号是否已注册
    const [existing] = await pool.query('SELECT id, status FROM users WHERE phone = ?', [phone])
    if (existing.length) {
      const u = existing[0]
      if (u.status === 'pending') {
        return res.status(400).json({ code: 400, message: '该手机号已提交申请, 请等待管理员审核' })
      }
      if (u.status === 'active') {
        return res.status(400).json({ code: 400, message: '该手机号已注册, 请直接登录' })
      }
      if (u.status === 'rejected') {
        return res.status(400).json({ code: 400, message: '该手机号的注册申请已被拒绝' })
      }
      return res.status(400).json({ code: 400, message: '该手机号已被使用' })
    }

    // 加密密码
    const passwordHash = await bcrypt.hash(password, 10)

    // 入库 (status='pending' 等审核)
    const email = `${phone}@pending.caimeite.local`
    const [r] = await pool.query(
      `INSERT INTO users (name, phone, email, password, id_card, role, user_type, status, auth_type, created_at, applied_at)
       VALUES (?, ?, ?, ?, ?, 'member', 'staff', 'pending', 'phone', NOW(), NOW())`,
      [name, phone, email, passwordHash, id_card || null]
    )
    res.json({ code: 0, message: '注册申请已提交, 请等待管理员审核', data: { user_id: r.insertId, phone } })
  } catch (err) { next(err) }
})

export default router
