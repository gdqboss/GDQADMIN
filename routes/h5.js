import { Router } from 'express'
import { processCustomerMessage, saveAIMessage, notifyBoge } from '../utils/ai-customer-service.js'

// 通知员工有新客户接入
async function notifyEmployee(empId, { name, phone, productCode }) {
  const TELEGRAM_BOT_TOKEN = process.env.TELEGRAM_BOT_TOKEN || ''
  const TELEGRAM_CHAT_ID = process.env.TELEGRAM_CHAT_ID || '861063885'
  
  if (!TELEGRAM_BOT_TOKEN) {
    console.log('[通知] Telegram bot token 未配置，跳过通知')
    return
  }
  
  const text = `🦈 *新客户接入通知*

👤 客户: ${name || '游客'}${phone ? ` (${phone})` : ''}
📦 产品码: ${productCode || '未知'}
💬 新会话已分配给您，请及时处理`

  try {
    await fetch(`https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        chat_id: TELEGRAM_CHAT_ID,
        text,
        parse_mode: 'Markdown'
      })
    })
  } catch (e) {
    console.error('[通知] Telegram通知失败:', e.message)
  }
}
import bcrypt from 'bcryptjs'
import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'
import { uploadAftersale } from '../middleware/upload.js'
import { sendSmsCode, generateCode } from '../utils/sms.js'
import { h5Auth } from '../middleware/h5Auth.js'

const router = Router()

const JWT_SECRET = process.env.JWT_SECRET

// POST /api/h5/register - Role-based registration with SMS verification
router.post('/register', async (req, res, next) => {
  try {
    const { name, phone, password, code, invite_code, role = 'customer' } = req.body
    if (!phone || !password || !code) {
      return res.status(400).json({ code: 400, message: '手机号、密码和验证码必填' })
    }
    if (!/^1[3-9]\d{9}$/.test(phone)) {
      return res.status(400).json({ code: 400, message: '手机号格式不正确' })
    }
    if (password.length < 6) {
      return res.status(400).json({ code: 400, message: '密码至少6位' })
    }

    // 验证短信验证码
    const [[smsCode]] = await pool.query(
      'SELECT id, code, used FROM sms_codes WHERE phone = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1',
      [phone]
    )

    if (!smsCode) {
      return res.status(400).json({ code: 400, message: '验证码不存在或已过期' })
    }

    if (smsCode.used) {
      return res.status(400).json({ code: 400, message: '验证码已使用' })
    }

    if (smsCode.code !== code) {
      return res.status(400).json({ code: 400, message: '验证码错误' })
    }

    // 检查手机号是否已注册
    const [existing] = await pool.query('SELECT id FROM h5_users WHERE phone = ?', [phone])
    if (existing.length) {
      return res.status(400).json({ code: 400, message: '该手机号已注册' })
    }

    // Validate role exists
    const [[roleExists]] = await pool.query('SELECT id FROM h5_roles WHERE name = ?', [role])
    if (!roleExists) {
      return res.status(400).json({ code: 400, message: '角色不存在' })
    }

    // 标记验证码为已使用
    await pool.query('UPDATE sms_codes SET used = 1 WHERE id = ?', [smsCode.id])

    let parent_id = null

    // Priority 1: invite_code parameter
    if (invite_code) {
      const [[inviter]] = await pool.query('SELECT id FROM h5_users WHERE id = ?', [parseInt(invite_code)])
      if (inviter) parent_id = inviter.id
    }

    // Priority 2: gdq_ref cookie
    if (!parent_id && req.cookies?.gdq_ref) {
      const [[inviter]] = await pool.query('SELECT id FROM h5_users WHERE id = ?', [parseInt(req.cookies.gdq_ref)])
      if (inviter) parent_id = inviter.id
    }

    // Priority 3: recent QR code scan (last 24 hours)
    if (!parent_id) {
      const [[recentScan]] = await pool.query(`
        SELECT q.referrer_h5_user_id
        FROM scan_logs sl
        JOIN qrcodes q ON sl.qrcode_id = q.id
        WHERE sl.scanner = ? AND q.referrer_h5_user_id IS NOT NULL
        ORDER BY sl.created_at DESC
        LIMIT 1
      `, [phone])
      if (recentScan?.referrer_h5_user_id) parent_id = recentScan.referrer_h5_user_id
    }

    const hash = await bcrypt.hash(password, 10)
    const [result] = await pool.query(
      'INSERT INTO h5_users (name, phone, password, parent_id, role) VALUES (?,?,?,?,?)',
      [name || phone, phone, hash, parent_id, role]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '注册成功' })
  } catch (err) { next(err) }
})

// POST /api/h5/send-sms - 发送短信验证码
router.post('/send-sms', async (req, res, next) => {
  try {
    const { phone } = req.body
    if (!phone) return res.status(400).json({ code: 400, message: '手机号必填' })
    if (!/^1[3-9]\d{9}$/.test(phone)) return res.status(400).json({ code: 400, message: '手机号格式不正确' })

    // 检查频率限制（1分钟内只能发送一次）
    const [[recent]] = await pool.query(
      'SELECT id FROM sms_codes WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE) ORDER BY created_at DESC LIMIT 1',
      [phone]
    )
    if (recent) {
      return res.status(429).json({ code: 429, message: '发送过于频繁，请稍后再试' })
    }

    // 生成验证码
    const code = generateCode()
    const expiresAt = new Date(Date.now() + 5 * 60 * 1000) // 5分钟后过期
    const ip = req.ip || req.connection.remoteAddress

    // 保存到数据库
    await pool.query(
      'INSERT INTO sms_codes (phone, code, expires_at, ip) VALUES (?, ?, ?, ?)',
      [phone, code, expiresAt, ip]
    )

    // 发送短信
    const sent = await sendSmsCode(phone, code)
    if (!sent) {
      return res.status(500).json({ code: 500, message: '短信发送失败，请稍后重试' })
    }

    res.json({ code: 0, data: null, message: '验证码已发送' })
  } catch (err) { next(err) }
})

// POST /api/h5/login-sms - 短信验证码登录（不自动注册）
router.post('/login-sms', async (req, res, next) => {
  try {
    const { phone, code } = req.body
    if (!phone || !code) return res.status(400).json({ code: 400, message: '手机号和验证码必填' })

    // 验证验证码
    const [[smsCode]] = await pool.query(
      'SELECT id, code, used FROM sms_codes WHERE phone = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1',
      [phone]
    )

    if (!smsCode) {
      return res.status(400).json({ code: 400, message: '验证码不存在或已过期' })
    }

    if (smsCode.used) {
      return res.status(400).json({ code: 400, message: '验证码已使用' })
    }

    if (smsCode.code !== code) {
      return res.status(400).json({ code: 400, message: '验证码错误' })
    }

    // 检查用户是否存在
    const [[user]] = await pool.query('SELECT * FROM h5_users WHERE phone = ?', [phone])

    if (!user) {
      // 用户不存在，返回错误，要求先注册
      return res.status(404).json({ code: 404, message: '该手机号未注册，请先注册' })
    }

    if (user.status === 'disabled') {
      return res.status(403).json({ code: 403, message: '账号已禁用' })
    }

    // 标记验证码为已使用
    await pool.query('UPDATE sms_codes SET used = 1 WHERE id = ?', [smsCode.id])

    // 生成 token
    const token = jwt.sign(
      { id: user.id, phone: user.phone, name: user.name, role: user.role },
      JWT_SECRET,
      { expiresIn: '365d' }
    )

    res.json({
      code: 0,
      data: {
        token,
        id: user.id,
        name: user.name,
        phone: user.phone,
        role: user.role
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// POST /api/h5/reset-password - 找回密码（通过短信验证码）
router.post('/reset-password', async (req, res, next) => {
  try {
    const { phone, code, new_password } = req.body
    if (!phone || !code || !new_password) {
      return res.status(400).json({ code: 400, message: '手机号、验证码和新密码必填' })
    }
    if (!/^1[3-9]\d{9}$/.test(phone)) {
      return res.status(400).json({ code: 400, message: '手机号格式不正确' })
    }
    if (new_password.length < 6) {
      return res.status(400).json({ code: 400, message: '密码至少6位' })
    }

    // 验证短信验证码
    const [[smsCode]] = await pool.query(
      'SELECT id, code, used FROM sms_codes WHERE phone = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1',
      [phone]
    )

    if (!smsCode) {
      return res.status(400).json({ code: 400, message: '验证码不存在或已过期' })
    }

    if (smsCode.used) {
      return res.status(400).json({ code: 400, message: '验证码已使用' })
    }

    if (smsCode.code !== code) {
      return res.status(400).json({ code: 400, message: '验证码错误' })
    }

    // 检查用户是否存在
    const [[user]] = await pool.query('SELECT id FROM h5_users WHERE phone = ?', [phone])
    if (!user) {
      return res.status(404).json({ code: 404, message: '该手机号未注册' })
    }

    // 标记验证码为已使用
    await pool.query('UPDATE sms_codes SET used = 1 WHERE id = ?', [smsCode.id])

    // 更新密码
    const hash = await bcrypt.hash(new_password, 10)
    await pool.query(
      'UPDATE h5_users SET password = ?, password_updated_at = NOW() WHERE id = ?',
      [hash, user.id]
    )

    // 记录密码修改日志
    const ip = req.ip || req.connection.remoteAddress
    await pool.query(
      'INSERT INTO password_change_logs (user_type, user_id, changed_by, ip) VALUES (?, ?, ?, ?)',
      ['h5', user.id, 'reset', ip]
    )

    res.json({ code: 0, data: null, message: '密码重置成功' })
  } catch (err) { next(err) }
})

// POST /api/h5/login
router.post('/login', async (req, res, next) => {
  try {
    const { phone, password } = req.body
    if (!phone || !password) return res.status(400).json({ code: 400, message: '手机号和密码必填' })
    const [[user]] = await pool.query('SELECT * FROM h5_users WHERE phone = ?', [phone])
    if (!user) return res.status(401).json({ code: 401, message: '手机号或密码错误' })
    if (user.status === 'disabled') return res.status(403).json({ code: 403, message: '账号已禁用' })
    const ok = await bcrypt.compare(password, user.password)
    if (!ok) return res.status(401).json({ code: 401, message: '手机号或密码错误' })

    const token = jwt.sign({ id: user.id, phone: user.phone, name: user.name, role: user.role }, JWT_SECRET, { expiresIn: '365d' })
    res.json({ code: 0, data: { token, id: user.id, name: user.name, phone: user.phone, role: user.role }, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/h5/set-password - Set password for first time (no old password required)
router.put('/set-password', h5Auth, async (req, res, next) => {
  try {
    const { password, name } = req.body
    if (!password) {
      return res.status(400).json({ code: 400, message: '密码必填' })
    }
    if (password.length < 6) {
      return res.status(400).json({ code: 400, message: '密码至少6位' })
    }

    // Check if user already has a password
    const [[user]] = await pool.query('SELECT password FROM h5_users WHERE id = ?', [req.h5user.id])
    if (!user) return res.status(404).json({ code: 404, message: '用户不存在' })

    if (user.password && user.password !== '') {
      return res.status(400).json({ code: 400, message: '密码已设置，请使用修改密码功能' })
    }

    // Set password
    const hash = await bcrypt.hash(password, 10)
    const updateFields = ['password = ?', 'password_updated_at = NOW()']
    const updateValues = [hash]

    // Update name if provided
    if (name && name.trim() !== '') {
      updateFields.push('name = ?')
      updateValues.push(name.trim())
    }

    updateValues.push(req.h5user.id)

    await pool.query(
      `UPDATE h5_users SET ${updateFields.join(', ')} WHERE id = ?`,
      updateValues
    )

    // Log password change
    const ip = req.ip || req.connection.remoteAddress
    await pool.query(
      'INSERT INTO password_change_logs (user_type, user_id, changed_by, ip) VALUES (?, ?, ?, ?)',
      ['h5', req.h5user.id, 'self', ip]
    )

    res.json({ code: 0, data: null, message: '密码设置成功' })
  } catch (err) { next(err) }
})

// PUT /api/h5/change-password - Change password
router.put('/change-password', h5Auth, async (req, res, next) => {
  try {
    const { old_password, new_password } = req.body
    if (!old_password || !new_password) {
      return res.status(400).json({ code: 400, message: '旧密码和新密码必填' })
    }
    if (new_password.length < 6) {
      return res.status(400).json({ code: 400, message: '新密码至少6位' })
    }

    // Verify old password
    const [[user]] = await pool.query('SELECT password FROM h5_users WHERE id = ?', [req.h5user.id])
    if (!user) return res.status(404).json({ code: 404, message: '用户不存在' })

    const ok = await bcrypt.compare(old_password, user.password)
    if (!ok) return res.status(401).json({ code: 401, message: '旧密码错误' })

    // Update password
    const hash = await bcrypt.hash(new_password, 10)
    await pool.query(
      'UPDATE h5_users SET password = ?, password_updated_at = NOW() WHERE id = ?',
      [hash, req.h5user.id]
    )

    // Log password change
    const ip = req.ip || req.connection.remoteAddress
    await pool.query(
      'INSERT INTO password_change_logs (user_type, user_id, changed_by, ip) VALUES (?, ?, ?, ?)',
      ['h5', req.h5user.id, 'self', ip]
    )

    res.json({ code: 0, data: null, message: '密码修改成功' })
  } catch (err) { next(err) }
})

// GET /api/h5/me - Get current user info with role permissions
router.get('/me', h5Auth, async (req, res, next) => {
  try {
    const [[user]] = await pool.query(
      'SELECT u.id, u.name, u.phone, u.parent_id, u.status, u.created_at, u.role, u.store_id, u.level, u.is_internal, r.label as role_label, r.can_sell, r.can_adjust_price, r.can_view_cost FROM h5_users u LEFT JOIN h5_roles r ON u.role COLLATE utf8mb4_unicode_ci = r.name COLLATE utf8mb4_unicode_ci WHERE u.id = ?',
      [req.h5user.id]
    )
    if (!user) return res.status(404).json({ code: 404, message: '用户不存在' })
    res.json({ code: 0, data: user, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/h5/after-sale (with image upload support)
router.post('/after-sale', h5Auth, uploadAftersale.array('images', 5), async (req, res, next) => {
  try {
    const { qrcode_id, issue } = req.body
    if (!qrcode_id || !issue) return res.status(400).json({ code: 400, message: '二维码ID和问题描述必填' })

    const images = req.files ? req.files.map(f => `/uploads/aftersale/${f.filename}`) : []

    await pool.query(
      'INSERT INTO after_sale_records (qrcode_id, buyer, issue, status, images, h5_user_id, contact_phone) VALUES (?,?,?,?,?,?,?)',
      [qrcode_id, req.h5user.phone, issue, 'processing', JSON.stringify(images), req.h5user.id, req.h5user.phone]
    )
    res.json({ code: 0, data: null, message: '售后申请提交成功' })
  } catch (err) { next(err) }
})

// GET /api/h5/roles - Get all H5 roles
router.get('/roles', async (req, res, next) => {
  try {
    const [roles] = await pool.query('SELECT * FROM h5_roles ORDER BY id')
    res.json({ code: 0, data: roles, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/h5/my-team - View my team (requires level >= 3)
router.get('/my-team', h5Auth, async (req, res, next) => {
  try {
    const [[user]] = await pool.query('SELECT level FROM h5_users WHERE id = ?', [req.h5user.id])
    if (!user || user.level < 3) {
      return res.status(403).json({ code: 403, message: '需要VIP等级3及以上才能查看团队' })
    }

    const [team] = await pool.query(
      'SELECT id, name, phone, role, level, created_at FROM h5_users WHERE parent_id = ? ORDER BY created_at DESC',
      [req.h5user.id]
    )
    res.json({ code: 0, data: team, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/h5/my-tree - View referral tree (recursive)
router.get('/my-tree', h5Auth, async (req, res, next) => {
  try {
    const [[user]] = await pool.query('SELECT level FROM h5_users WHERE id = ?', [req.h5user.id])
    if (!user || user.level < 3) {
      return res.status(403).json({ code: 403, message: '需要VIP等级3及以上才能查看推荐树' })
    }

    // Recursive CTE to get full tree
    const [tree] = await pool.query(`
      WITH RECURSIVE team_tree AS (
        SELECT id, name, phone, role, level, parent_id, 1 as depth
        FROM h5_users WHERE id = ?
        UNION ALL
        SELECT u.id, u.name, u.phone, u.role, u.level, u.parent_id, t.depth + 1
        FROM h5_users u
        INNER JOIN team_tree t ON u.parent_id = t.id
        WHERE t.depth < 5
      )
      SELECT * FROM team_tree ORDER BY depth, id
    `, [req.h5user.id])

    res.json({ code: 0, data: tree, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/h5/bind-qrcode/:code - 绑定二维码到当前用户
router.post('/bind-qrcode/:code', h5Auth, async (req, res, next) => {
  try {
    const { code } = req.params

    // 查询二维码
    const [[qr]] = await pool.query('SELECT id, buyer_phone, status FROM qrcodes WHERE code = ?', [code])
    if (!qr) {
      return res.status(404).json({ code: 404, message: '二维码不存在' })
    }

    // 检查是否已绑定
    if (qr.buyer_phone) {
      return res.status(400).json({ code: 400, message: '该二维码已被绑定' })
    }

    // 只有已售出的二维码才能绑定
    if (qr.status !== 'sold') {
      return res.status(400).json({ code: 400, message: '只有已售出的商品才能绑定' })
    }

    // 绑定到当前用户
    await pool.query(
      'UPDATE qrcodes SET buyer_phone = ?, buyer = ? WHERE id = ?',
      [req.h5user.phone, req.h5user.name || req.h5user.phone, qr.id]
    )

    res.json({ code: 0, data: null, message: '绑定成功' })
  } catch (err) { next(err) }
})

// GET /api/h5/my-products - 获取我的商品列表
router.get('/my-products', h5Auth, async (req, res, next) => {
  try {
    const [products] = await pool.query(`
      SELECT
        q.id,
        q.code,
        q.status,
        q.bound_at as purchase_date,
        p.name as product_name,
        p.image_main,
        p.sku,
        p.category_id
      FROM qrcodes q
      LEFT JOIN products p ON q.product_id = p.id
      WHERE q.buyer_phone = ?
      ORDER BY q.bound_at DESC
    `, [req.h5user.phone])

    res.json({ code: 0, data: products, message: 'ok' })
  } catch (err) { next(err) }
})

// ─── Chat APIs ──────────────────────────────────────────────────────────────

// GET /api/h5/chat/:qrcodeId — 获取或自动创建售后工单 + 历史消息
// 支持 ?emp_id=  参数，自动分配给指定员工
router.get('/chat/:qrcodeId', h5Auth, async (req, res, next) => {
  try {
    const qrcodeId = parseInt(req.params.qrcodeId)
    const empId = req.query.emp_id ? parseInt(req.query.emp_id) : null
    if (!qrcodeId) return res.status(400).json({ code: 400, message: '无效的二维码ID' })

    // 查找该用户在此二维码下的最新售后工单
    let [[record]] = await pool.query(
      'SELECT id FROM after_sale_records WHERE qrcode_id = ? AND h5_user_id = ? ORDER BY created_at DESC LIMIT 1',
      [qrcodeId, req.h5user.id]
    )

    // 不存在则自动创建
    if (!record) {
      // 如果有 emp_id，自动分配给该员工
      const fields = ['qrcode_id', 'buyer', 'issue', 'status', 'h5_user_id', 'contact_phone']
      const values = [qrcodeId, req.h5user.phone, '在线咨询', 'processing', req.h5user.id, req.h5user.phone]
      if (empId) {
        fields.push('assigned_to')
        values.push(empId)
      }
      const [result] = await pool.query(
        `INSERT INTO after_sale_records (${fields.join(',')}) VALUES (${fields.map(() => '?').join(',')})`,
        values
      )
      record = { id: result.insertId }
    }

    // 获取历史消息
    const [messages] = await pool.query(
      'SELECT id, sender_type, sender_name, content, created_at FROM aftersale_messages WHERE aftersale_id = ? ORDER BY created_at ASC',
      [record.id]
    )

    res.json({ code: 0, data: { aftersaleId: record.id, messages }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/h5/chat/:aftersaleId/messages?since= — 增量拉取新消息
router.get('/chat/:aftersaleId/messages', h5Auth, async (req, res, next) => {
  try {
    const aftersaleId = parseInt(req.params.aftersaleId)
    const since = req.query.since || '1970-01-01'

    // 权限校验
    const [[record]] = await pool.query(
      'SELECT id FROM after_sale_records WHERE id = ? AND h5_user_id = ?',
      [aftersaleId, req.h5user.id]
    )
    if (!record) return res.status(403).json({ code: 403, message: '无权访问' })

    const [messages] = await pool.query(
      'SELECT id, sender_type, sender_name, content, created_at FROM aftersale_messages WHERE aftersale_id = ? AND created_at > ? ORDER BY created_at ASC',
      [aftersaleId, since]
    )

    res.json({ code: 0, data: messages, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/h5/chat/:aftersaleId/messages — 消费者发送消息（AI客服增强版）
router.post('/chat/:aftersaleId/messages', h5Auth, async (req, res, next) => {
  try {
    const aftersaleId = parseInt(req.params.aftersaleId)
    const { content } = req.body
    if (!content || !content.trim()) return res.status(400).json({ code: 400, message: '消息内容不能为空' })

    // 权限校验
    const [[record]] = await pool.query(
      'SELECT id, qrcode_id FROM after_sale_records WHERE id = ? AND h5_user_id = ?',
      [aftersaleId, req.h5user.id]
    )
    if (!record) return res.status(403).json({ code: 403, message: '无权访问' })

    // 保存顾客消息
    const [result] = await pool.query(
      'INSERT INTO aftersale_messages (aftersale_id, sender_type, sender_id, sender_name, content) VALUES (?,?,?,?,?)',
      [aftersaleId, 'customer', req.h5user.id, req.h5user.name || req.h5user.phone, content.trim()]
    )

    // 异步调用AI客服（不阻塞响应）
    ;(async () => {
      try {
        const { reply, shouldNotify } = await processCustomerMessage(
          aftersaleId,
          record.qrcode_id,
          req.h5user.id,
          content.trim()
        )
        
        // 保存AI回复
        await saveAIMessage(aftersaleId, reply)
        
        // 如果需要通知波哥
        if (shouldNotify) {
          const userInfo = { name: req.h5user.name, phone: req.h5user.phone }
          const [[qr]] = await pool.query('SELECT p.name FROM qrcodes q JOIN products p ON q.product_id = p.id WHERE q.id = ?', [record.qrcode_id])
          await notifyBoge(content.trim(), userInfo, qr)
        }
      } catch (e) {
        console.error('[AI客服] 处理消息失败:', e.message)
      }
    })()

    res.json({ code: 0, data: { id: result.insertId }, message: 'ok' })
  } catch (err) { next(err) }
})


// ─── Anonymous Chat APIs (免登录客服) ───────────────────────────────────────

// GET /api/h5/chat/:qrcodeId/anonymous — 匿名获取/创建售后工单（免登录）
router.get('/chat/:qrcodeId/anonymous', async (req, res, next) => {
  try {
    const qrcodeId = parseInt(req.params.qrcodeId)
    const deviceId = req.query.device_id || 'anonymous'
    const empId = req.query.emp_id ? parseInt(req.query.emp_id) : null
    if (!qrcodeId) return res.status(400).json({ code: 400, message: '无效的二维码ID' })

    // 查找该设备在此二维码下的最新售后工单
    let [[record]] = await pool.query(
      'SELECT id, assigned_to FROM after_sale_records WHERE qrcode_id = ? AND device_id = ? AND h5_user_id IS NULL ORDER BY created_at DESC LIMIT 1',
      [qrcodeId, deviceId]
    )

    let isNewChat = false

    // 不存在则自动创建
    if (!record) {
      const [[qr]] = await pool.query('SELECT buyer, buyer_phone FROM qrcodes WHERE id = ?', [qrcodeId])
      const fields = ['qrcode_id', 'buyer', 'issue', 'status', 'device_id', 'contact_phone']
      const values = [qrcodeId, qr?.buyer_phone || '匿名用户', '在线咨询', 'processing', deviceId, qr?.buyer_phone || '']
      if (empId) {
        fields.push('assigned_to')
        values.push(empId)
      }
      const [result] = await pool.query(
        `INSERT INTO after_sale_records (${fields.join(',')}) VALUES (${fields.map(() => '?').join(',')})`,
        values
      )
      record = { id: result.insertId, assigned_to: empId }
      isNewChat = true
    }

    // 获取历史消息
    let [messages] = await pool.query(
      'SELECT id, sender_type, sender_name, content, created_at FROM aftersale_messages WHERE aftersale_id = ? ORDER BY created_at ASC',
      [record.id]
    )

    // 如果是员工首次接入（emp_id存在且是新会话），发送欢迎词
    if (isNewChat && empId) {
      const [[employee]] = await pool.query('SELECT name FROM users WHERE id = ?', [empId])
      const empName = employee?.name || '员工'
      const welcomeMsg = `👋 欢迎回来，${empName}！

🏠 您已成功接入**彩美特智能家园**

📋 **您的功能：**
• 💬 接收并回复客户消息
• 📦 查看客户产品信息
• 🔧 处理售后申请

有客户咨询时，我会立即通知您 🌟`

      await pool.query(
        'INSERT INTO aftersale_messages (aftersale_id, sender_type, sender_id, sender_name, content) VALUES (?,?,?,?,?)',
        [record.id, 'ai', '江小鱼AI', '江小鱼AI', welcomeMsg]
      )
      
      // 重新获取消息（包含欢迎词）
      ;[messages] = await pool.query(
        'SELECT id, sender_type, sender_name, content, created_at FROM aftersale_messages WHERE aftersale_id = ? ORDER BY created_at ASC',
        [record.id]
      )
    }

    res.json({ code: 0, data: { aftersaleId: record.id, messages }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/h5/chat/:qrcodeId/anonymous/messages — 匿名发送消息（免登录）
router.post('/chat/:qrcodeId/anonymous/messages', async (req, res, next) => {
  try {
    const qrcodeId = parseInt(req.params.qrcodeId)
    const deviceId = req.headers['x-device-id'] || 'anonymous'
    const empId = req.body.emp_id ? parseInt(req.body.emp_id) : null
    const { content } = req.body
    if (!content || !content.trim()) return res.status(400).json({ code: 400, message: '消息内容不能为空' })

    // 查找该设备在此二维码下的最新售后工单
    let [[record]] = await pool.query(
      'SELECT id, assigned_to FROM after_sale_records WHERE qrcode_id = ? AND device_id = ? AND h5_user_id IS NULL ORDER BY created_at DESC LIMIT 1',
      [qrcodeId, deviceId]
    )

    let isNewChat = false

    // 不存在则自动创建
    if (!record) {
      const [[qr]] = await pool.query('SELECT buyer, buyer_phone FROM qrcodes WHERE id = ?', [qrcodeId])
      const fields = ['qrcode_id', 'buyer', 'issue', 'status', 'device_id', 'contact_phone']
      const values = [qrcodeId, qr?.buyer_phone || '匿名用户', '在线咨询', 'processing', deviceId, qr?.buyer_phone || '']
      if (empId) {
        fields.push('assigned_to')
        values.push(empId)
      }
      const [result] = await pool.query(
        `INSERT INTO after_sale_records (${fields.join(',')}) VALUES (${fields.map(() => '?').join(',')})`,
        values
      )
      record = { id: result.insertId, assigned_to: empId }
      isNewChat = true
    }

    // 保存匿名用户消息
    const [result] = await pool.query(
      'INSERT INTO aftersale_messages (aftersale_id, sender_type, sender_id, sender_name, content) VALUES (?,?,?,?,?)',
      [record.id, 'anonymous', deviceId, '访客', content.trim()]
    )

    // 如果是新会话且有分配客服，发送通知
    if (isNewChat && record.assigned_to) {
      ;(async () => {
        try {
          const [[employee]] = await pool.query('SELECT name, phone FROM users WHERE id = ?', [record.assigned_to])
          const [[qr]] = await pool.query('SELECT code FROM qrcodes WHERE id = ?', [qrcodeId])
          if (employee) {
            await notifyEmployee(record.assigned_to, {
              name: employee.name,
              phone: employee.phone,
              productCode: qr?.code
            })
          }
        } catch (e) {
          console.error('[通知] 发送客服通知失败:', e.message)
        }
      })()
    }

    // 异步调用AI客服（不阻塞响应）
    ;(async () => {
      try {
        const { reply } = await processCustomerMessage(record.id, qrcodeId, deviceId, content.trim())
        if (reply) await saveAIMessage(record.id, reply)
      } catch (e) {
        console.error('[AI客服] 处理匿名消息失败:', e.message)
      }
    })()

    res.json({ code: 0, data: { id: result.insertId }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/h5/chat/:qrcodeId/anonymous/messages — 匿名获取新消息（免登录轮询）
router.get('/chat/:qrcodeId/anonymous/messages', async (req, res, next) => {
  try {
    const qrcodeId = parseInt(req.params.qrcodeId)
    const deviceId = req.headers['x-device-id'] || 'anonymous'
    const since = req.query.since || '1970-01-01'

    let [[record]] = await pool.query(
      'SELECT id FROM after_sale_records WHERE qrcode_id = ? AND device_id = ? AND h5_user_id IS NULL ORDER BY created_at DESC LIMIT 1',
      [qrcodeId, deviceId]
    )

    if (!record) return res.json({ code: 0, data: [], message: 'ok' })

    const [messages] = await pool.query(
      'SELECT id, sender_type, sender_name, content, created_at FROM aftersale_messages WHERE aftersale_id = ? AND created_at > ? ORDER BY created_at ASC',
      [record.id, since]
    )

    res.json({ code: 0, data: messages, message: 'ok' })
  } catch (err) { next(err) }
})


export default router
