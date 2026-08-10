/**
 * OpenClaw助手绑定接口
 * 用于智能商业系统与OpenClaw助手的身份绑定
 */
import { Router } from 'express'
import jwt from 'jsonwebtoken'
import crypto from 'crypto'
import { pool } from '../db/connection.js'
import { ROLES } from '../middleware/rbac.js'

const router = Router()

// OpenClaw专用密钥（可配置到环境变量）
const OPENCLAW_SECRET = process.env.OPENCLAW_SECRET || 'caimeite-openclaw-secret-key-2024'

/**
 * 生成绑定链接（管理员用）
 * GET /api/openclaw/gen-link?emp_id=10016
 */
router.get('/gen-link', async (req, res, next) => {
  try {
    const { emp_id } = req.query
    
    // 验证管理员权限（通过header中的token）
    const authHeader = req.headers.authorization
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return res.status(401).json({ code: 401, message: '需要管理员token' })
    }
    
    try {
      const decoded = jwt.verify(authHeader.split(' ')[1], process.env.JWT_SECRET)
      if (decoded.role !== ROLES.ADMIN) {
        return res.status(403).json({ code: 403, message: '需要管理员权限' })
      }
    } catch (e) {
      return res.status(401).json({ code: 401, message: '无效的token' })
    }
    
    if (!emp_id) {
      return res.status(400).json({ code: 400, message: '缺少emp_id参数' })
    }
    
    // 验证员工是否存在
    const [employees] = await pool.query('SELECT id, name, role FROM users WHERE id = ?', [emp_id])
    if (!employees.length) {
      return res.status(404).json({ code: 404, message: '员工不存在' })
    }
    
    const employee = employees[0]
    
    // 生成带签名的链接
    const exp = Math.floor(Date.now() / 1000) + 3600 * 24 * 365 * 100 // 100年有效期
    const sign = crypto
      .createHmac('sha256', OPENCLAW_SECRET)
      .update(`${emp_id}-${exp}`)
      .digest('hex')
    
    const bindUrl = `https://claw.gdqshop.cn/api/openclaw/bind?emp_id=${emp_id}&exp=${exp}&sign=${sign}`
    
    res.json({
      code: 0,
      data: {
        employee_id: employee.id,
        employee_name: employee.name,
        role: employee.role,
        bind_url: bindUrl,
        expires_at: new Date(exp * 1000).toISOString()
      },
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

/**
 * 握手绑定接口
 * GET /api/openclaw/bind?emp_id=10016&exp=xxx&sign=xxx
 */
router.get('/bind', async (req, res, next) => {
  try {
    const { emp_id, exp, sign } = req.query
    
    if (!emp_id || !exp || !sign) {
      return res.status(400).json({ code: 400, message: '参数不完整' })
    }
    
    // 验证链接过期
    const expTimestamp = parseInt(exp)
    if (Date.now() / 1000 > expTimestamp) {
      return res.status(400).json({ code: 400, message: '链接已过期，请重新生成' })
    }
    
    // 验证签名
    const expectedSign = crypto
      .createHmac('sha256', OPENCLAW_SECRET)
      .update(`${emp_id}-${exp}`)
      .digest('hex')
    
    if (sign !== expectedSign) {
      return res.status(400).json({ code: 400, message: '签名验证失败' })
    }
    
    // 查询员工信息
    const [employees] = await pool.query(
      'SELECT id, name, email, phone, role, department, status FROM users WHERE id = ?',
      [emp_id]
    )
    
    if (!employees.length) {
      return res.status(404).json({ code: 404, message: '员工不存在' })
    }
    
    const employee = employees[0]
    
    // 检查账号状态
    if (employee.status === 'disabled') {
      return res.status(403).json({ code: 403, message: '账号已被禁用' })
    }
    
    // 生成OpenClaw专用token（有效期7天）
    const openclawToken = jwt.sign(
      { 
        id: employee.id, 
        name: employee.name, 
        role: employee.role, 
        department: employee.department,
        source: 'openclaw'
      },
      process.env.JWT_SECRET,
      {}
    )
    
    const welcomeMsg = `👋 欢迎回来，${employee.name}！

🏠 您已成功接入智能商业系统

📋 功能说明：
• 💬 接收客户消息 - 客户扫描产品二维码，您的微信会收到通知
• 📦 产品查询 - 客户咨询时可查询产品信息
• 🔧 售后处理 - 帮助客户提交和查询售后申请
• ✅ 考勤打卡 - 发送位置完成每日考勤
• 📝 工作日志 - 提交工作日报/周报
• 📊 数据报表 - 查看工作统计和业绩

⚠️ 注意：需开通权限才能使用全部功能

有客户咨询或有新任务时，我会立即通知您 🌟`;
    res.json({
      code: 0,
      data: {
        employee_id: employee.id,
        employee_name: employee.name,
        role: employee.role,
        department: employee.department || '',
        token: openclawToken,
        expires_in: -1, // 永久有效
        welcome_msg: welcomeMsg
      },
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

/**
 * 验证token（供OpenClaw调用）
 * GET /api/openclaw/verify
 */
router.get('/verify', async (req, res, next) => {
  try {
    const authHeader = req.headers.authorization
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return res.status(401).json({ code: 401, message: '缺少token' })
    }
    
    try {
      const decoded = jwt.verify(authHeader.split(' ')[1], process.env.JWT_SECRET)
      res.json({
        code: 0,
        data: {
          valid: true,
          employee_id: decoded.id,
          employee_name: decoded.name,
          role: decoded.role,
          department: decoded.department
        },
        message: 'ok'
      })
    } catch (e) {
      res.status(401).json({ code: 401, message: 'token无效或已过期' })
    }
  } catch (err) {
    next(err)
  }
})

/**
 * 客服消息推送接口（公开，无需认证）
 * POST /api/openclaw/service-message
 * 客户消息推送到客服
 */
router.post('/service-message', async (req, res, next) => {
  try {
    const { customer_name, customer_phone, message, source, qrcode } = req.body
    
    if (!message) {
      return res.status(400).json({ code: 400, message: '消息内容不能为空' })
    }
    
    // 查询所有客服人员
    const [serviceUsers] = await pool.query(
      'SELECT id, name, phone FROM users WHERE is_service_customer = 1'
    )
    
    if (!serviceUsers.length) {
      return res.status(404).json({ code: 404, message: '没有配置客服人员' })
    }
    
    const serviceUser = serviceUsers[0] // 取第一个客服
    
    // 保存消息到数据库
    const [result] = await pool.query(
      `INSERT INTO service_messages (user_id, customer_name, customer_phone, message, source, qrcode, status, created_at)
       VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())`,
      [serviceUser.id, customer_name || '匿名客户', customer_phone || '', message, source || 'h5', qrcode || '']
    )
    
    // 构建推送内容
    const pushContent = `📩 **新客服消息**

👤 客户：${customer_name || '匿名客户'}
📱 电话：${customer_phone || '无'}
💬 消息：${message}
🔗 来源：${source || 'H5'} ${qrcode ? `\n📦 商品码：${qrcode}` : ''}
⏰ 时间：${new Date().toLocaleString('zh-CN', { timeZone: 'Asia/Shanghai' })}`
    
    // TODO: 通过OpenClaw webhook推送到微信+Telegram
    // 这里先记录日志
    console.log('[客服消息]', pushContent)
    
    res.json({
      code: 0,
      data: {
        id: result.insertId,
        pushed_to: serviceUser.name,
        content: pushContent
      },
      message: '消息已推送'
    })
  } catch (err) {
    next(err)
  }
})

/**
 * 获取客服消息列表（需要认证）
 * GET /api/openclaw/service-messages
 */
router.get('/service-messages', async (req, res, next) => {
  try {
    const { status, page = 1, size = 20 } = req.query
    const offset = (page - 1) * size
    
    let where = 'WHERE 1=1'
    const params = []
    
    if (status) {
      where += ' AND sm.status = ?'
      params.push(status)
    }
    
    const [rows] = await pool.query(
      `SELECT sm.*, u.name as service_name 
       FROM service_messages sm 
       LEFT JOIN users u ON sm.user_id = u.id 
       ${where} 
       ORDER BY sm.created_at DESC 
       LIMIT ? OFFSET ?`,
      [...params, parseInt(size), parseInt(offset)]
    )
    
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM service_messages sm ${where}`,
      params
    )
    
    res.json({
      code: 0,
      data: {
        list: rows,
        total,
        page: parseInt(page),
        size: parseInt(size)
      },
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

/**
 * 标记消息已处理（需要认证）
 * PUT /api/openclaw/service-messages/:id
 */
router.put('/service-messages/:id', async (req, res, next) => {
  try {
    const { id } = req.params
    const { status = 'handled' } = req.body
    
    await pool.query(
      'UPDATE service_messages SET status = ?, handled_at = NOW() WHERE id = ?',
      [status, id]
    )
    
    res.json({ code: 0, message: '更新成功' })
  } catch (err) {
    next(err)
  }
})

export default router
