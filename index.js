import express from 'express'
import activateRoutes from './routes/activate.js'
import cors from 'cors'
import helmet from 'helmet'
import cookieParser from 'cookie-parser'
import path from 'path'
import fs from 'fs'
import { fileURLToPath } from 'url'
import 'dotenv/config'
import { pool } from './db/connection.js'
import { auth } from './middleware/auth.js'
import { errorHandler } from './middleware/errorHandler.js'
import { langMiddleware } from './middleware/translate.js'
import { globalLimiter, scanLimiter, apiLimiter } from './middleware/rateLimit.js'
import { requireRole } from './middleware/rbac.js'
import { verifySignature, decryptMessage, extractXmlField } from './services/wecom-crypto.js'
import { startCronJobs } from './cron.js'
import { startFinanceReminderJobs } from './jobs/finance-reminders.js'
import authRoutes from './routes/auth.js'
import productRoutes from './routes/products.js'
import materialRoutes from './routes/materials.js'
import warehouseRoutes from './routes/warehouses.js'
import inventoryRoutes from './routes/inventory.js'
import alertRoutes from './routes/alerts.js'
import approvalRoutes from './routes/approvals.js'
import dashboardRoutes from './routes/dashboard.js'
import qrcodeRoutes from './routes/qrcode.js'
import oaRoutes from './routes/oa.js'
import reportRoutes from './routes/reports.js'
import wecomRoutes from './routes/wecom.js'
import settingsRoutes from './routes/settings.js'
import systemRoutes from './routes/system.js'
import wecomAdminRoutes from './routes/wecom-admin.js'
import supplierRoutes from './routes/suppliers.js'
import dealerRoutes from './routes/dealers.js'
import storeRoutes from './routes/stores.js'
import mallRoutes from './routes/mall.cjs'
import userRoutes from './routes/users.js'
import categoryRoutes from './routes/categories.js'
import uploadRoutes from './routes/upload.js'
import importRoutes from './routes/import.js'
import imageRoutes from './routes/images.js'
import h5Routes from './routes/h5.js'
import wxmpRoutes from './routes/wxmp.js'
import retailRoutes from './routes/retail.js'
import aftersalesRoutes from './routes/aftersales.js'
import scanRoutes from './routes/scan.js'
import referralRoutes from './routes/referral.js'
import eduRoutes from './routes/edu.js'
import h5AdminRoutes from './routes/h5-admin.js'
import transferRoutes from './routes/transfer.js'
import identityRoutes from './routes/identity.js'
import financeSimpleRoutes from './routes/finance-simple.js'
import financeReportRoutes from './routes/finance-report.js'
import invoiceRoutes from './routes/invoices.js'
import cardRoutes from './routes/card.js'
import ordersRoutes from './routes/orders.js'
import reportsRoutes from './routes/reports.js'
import bossChatRoutes from './routes/boss-chat.js'
import aiClassRoutes from './routes/ai-class.js'
import adminSchemaRoutes from './routes/admin-schema.js'
import aiConfigRoutes from './routes/ai-config.js'
import kbRoutes from './routes/kb.js'
import jobResponsibilitiesRoutes from './routes/job-responsibilities.js'
import workLogsRoutes from './routes/work-logs.js'
import visitLogsRoutes from './routes/visit-logs.js'
import deliveryRoutes from './routes/delivery.js'
import shareLogsRoutes from './routes/share-logs.js'
import feedbackRoutes from './routes/feedback.js'
import returnsRoutes from './routes/returns.js'
import logInteractionsRoutes from './routes/log-interactions.js'
import tasksRoutes from './routes/tasks.js'
import responsibilitiesRoutes from './routes/responsibilities.js'
import giftApprovalRoutes from './routes/gift-approvals.js'
import preorderRoutes from './routes/preorder.js'
import openclawRoutes from './routes/openclaw.js'
import customerLevelRoutes from './routes/customer-level.js'
import escalationRoutes from './routes/escalation.js'
import biRoutes from './routes/bi.js'
import excelReportRoutes from './routes/excelReport.js'
import rbacPermissionRoutes from './routes/rbac/permissions.js'
import orderAggregatorRoutes from './routes/order-aggregator.js'
import rbacMenuRoutes from './routes/rbac/menus.js'
import rbacRoleRoutes from './routes/rbac/roles.js'
import rbacUserRoleRoutes from './routes/rbac/userRoles.js'
import serverProfilesRoutes from './routes/server-profiles.js'
import serverEndpointsRoutes from './routes/server-endpoints.js'
import collageRoutes from './routes/collage.js'
import restaurantRoutes from './routes/restaurant.js'
import hotelRoutes from './routes/hotel.js'
import logisticsRoutes from './routes/logistics.js'
import articleRoutes from './routes/articles.js'
import yuyueRoutes from './routes/yuyue.js'
import scoreShopRoutes from './routes/score_shop.js'
import couponRoutes from './routes/coupon.js'
import kefuRoutes from './routes/kefu.js'
import walletRoutes from './routes/wallet.js'
import inviteRoutes from './routes/invite.js'
import memberLevelRoutes from './routes/member-level.js'
import payRoutes from './routes/pay.js'
import seckillRoutes from './routes/seckill.js'
import chatRoutes from './routes/chat.js'
import smartStudioRoutes from './routes/smart-studio.js'
import onlineOrdersRoutes from './routes/online-orders.js'
import quoteRoutes from './routes/quote.js'
import rentalRoutes from './routes/rental.js'
import rentalPublicRoutes from './routes/rental-public.js'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const app = express()

// Trust proxy for rate limiting behind reverse proxy
app.set('trust proxy', 1)

app.use(helmet({
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      scriptSrc: ["'self'", "'unsafe-inline'"],
      styleSrc: ["'self'", "'unsafe-inline'"],
      imgSrc: ["'self'", "data:", "https:"],
      connectSrc: ["'self'"],
      fontSrc: ["'self'"],
      objectSrc: ["'none'"],
      mediaSrc: ["'self'"],
      frameSrc: ["'none'"],
    },
  },
  crossOriginEmbedderPolicy: false,
}))
app.use((req, res, next) => {
  res.setHeader('X-Content-Type-Options', 'nosniff')
  res.setHeader('X-Frame-Options', 'DENY')
  res.setHeader('X-XSS-Protection', '1; mode=block')
  res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
  next()
})
app.use(globalLimiter)
const corsOrigin = process.env.CORS_ORIGIN || 'http://localhost:5173'
const allowedOrigins = corsOrigin.includes(',')
  ? corsOrigin.split(',').map(s => s.trim())
  : [corsOrigin]

app.use(cors({
  origin: (origin, callback) => {
    // Allow requests with no origin (nginx proxy, mobile apps, curl)
    if (!origin) return callback(null, true)
    if (allowedOrigins.includes(origin)) {
      return callback(null, true)
    }
    // Log rejected origins for debugging
    console.log('[CORS] Rejected origin:', origin)
    callback(null, true) // Allow all for now, can restrict later
  },
  credentials: true,
}))
app.use(cookieParser())
app.use(express.json({ limit: '1mb' }))
app.use('/uploads', express.static(path.join(__dirname, 'uploads')))

// Health check endpoint (public, no auth required)
app.get('/api/health', async (req, res) => {
  try {
    // Check database connection
    await pool.query('SELECT 1')
    res.json({
      code: 0,
      message: '系统运行正常',
      data: {
        status: 'healthy',
        timestamp: new Date().toISOString(),
        uptime: process.uptime(),
        memory: process.memoryUsage(),
        database: 'connected'
      }
    })
  } catch (err) {
    res.status(503).json({
      code: 503,
      message: '服务异常',
      data: {
        status: 'unhealthy',
        timestamp: new Date().toISOString(),
        error: err.message
      }
    })
  }
})

// Language detection (set req.lang = 'en' or 'zh')
app.use(langMiddleware)

// Public routes
app.use('/api/auth', authRoutes)
app.use('/api/preorder', auth, preorderRoutes)
app.use('/api/openclaw', openclawRoutes)
app.use('/api/boss', auth, bossChatRoutes)
app.use('/api/ai-config', auth, aiConfigRoutes)
app.use('/api/ai-class', auth, aiClassRoutes)
app.use('/api/admin/schema', auth, adminSchemaRoutes)
app.use('/api/kb', auth, kbRoutes)
app.use('/api/scan', scanRoutes)
// Rental 公开端点（游客可访问）—— 必须放在 inventory 的 /api catch-all 之前
app.use('/api/rental-public', rentalPublicRoutes)

// Public referral QR endpoint (no auth) — must be before prefix router
app.get('/api/referral/qr/:token', async (req, res, next) => {
  try {
    const { token } = req.params
    const [[table]] = await pool.query('SELECT * FROM restaurant_tables WHERE qr_token = ?', [token])
    if (!table) return res.status(404).json({ code: 404, message: '桌码不存在' })

    const qrUrl = `https://wecom.gdqshop.cn/api/referral/scan/${token}`
    const filePath = path.join(__dirname, 'uploads/qrcodes', `${token}.png`)

    if (!fs.existsSync(filePath)) {
      const QRCode = (await import('qrcode')).default
      await QRCode.toFile(filePath, qrUrl, {
        type: 'png', width: 300, margin: 2,
        color: { dark: '#000000', light: '#ffffff' },
      })
    }

    res.setHeader('Content-Type', 'image/png')
    res.setHeader('Cache-Control', 'public, max-age=86400')
    res.sendFile(filePath)
  } catch (err) { next(err) }
})

app.use('/api/referral', auth, apiLimiter, referralRoutes)

// Public scan endpoint (no auth, rate-limited)
app.post('/api/qrcodes/:id/scan', scanLimiter, async (req, res, next) => {
  try {
    const { scanner, role, action, location } = req.body
    if (!scanner || !role || !action) {
      return res.status(400).json({ code: 400, message: 'scanner, role, action 必填' })
    }
    await pool.query('UPDATE qrcodes SET scan_count = scan_count + 1 WHERE id = ?', [req.params.id])
    await pool.query(
      'INSERT INTO scan_logs (qrcode_id, scanner, role, action, location) VALUES (?,?,?,?,?)',
      [req.params.id, scanner, role, action, location]
    )
    const [[qr]] = await pool.query(
      'SELECT q.*, p.name as product_name, p.sku FROM qrcodes q LEFT JOIN products p ON q.product_id = p.id WHERE q.id = ?',
      [req.params.id]
    )
    res.json({ code: 0, data: qr, message: 'ok' })
  } catch (err) { next(err) }
})

// WeCom callback — GET for URL verification
app.get('/api/wecom/callback', (req, res) => {
  const { msg_signature, timestamp, nonce, echostr } = req.query
  const token = process.env.WECOM_TOKEN
  const encodingAESKey = process.env.WECOM_ENCODING_AES_KEY

  if (!token || !encodingAESKey) {
    return res.status(500).send('WeCom callback not configured')
  }

  if (!verifySignature(token, timestamp, nonce, echostr, msg_signature)) {
    return res.status(403).send('Signature verification failed')
  }

  const { message } = decryptMessage(encodingAESKey, echostr)
  res.send(message)
})

// WeCom callback — POST for receiving messages/events (XML body)
app.post('/api/wecom/callback', express.text({ type: 'text/xml' }), async (req, res, next) => {
  try {
    const { msg_signature, timestamp, nonce } = req.query
    const token = process.env.WECOM_TOKEN
    const encodingAESKey = process.env.WECOM_ENCODING_AES_KEY
    const corpId = process.env.WECOM_CORP_ID

    if (!token || !encodingAESKey) {
      return res.status(500).json({ code: 500, message: 'WeCom callback not configured' })
    }

    // Extract encrypted content from XML body
    const xml = typeof req.body === 'string' ? req.body : ''
    const encrypt = extractXmlField(xml, 'Encrypt')

    if (!encrypt) {
      return res.status(400).json({ code: 400, message: '无法解析消息体' })
    }

    // Verify signature
    if (!verifySignature(token, timestamp, nonce, encrypt, msg_signature)) {
      return res.status(403).json({ code: 403, message: '签名验证失败' })
    }

    // Decrypt message
    const { message, corpId: msgCorpId } = decryptMessage(encodingAESKey, encrypt)

    if (msgCorpId !== corpId) {
      return res.status(403).json({ code: 403, message: 'CorpID 不匹配' })
    }

    // Parse decrypted XML to extract message fields
    const msgType = extractXmlField(message, 'MsgType')
    const fromUser = extractXmlField(message, 'FromUserName')
    const content = extractXmlField(message, 'Content')
    const event = extractXmlField(message, 'Event')

    // Handle text messages — store in local DB
    if (msgType === 'text' && content) {
      // Find or create conversation
      const [existing] = await pool.query(
        'SELECT id FROM wecom_conversations WHERE external_id = ?',
        [fromUser]
      )
      let conversationId
      if (existing.length > 0) {
        conversationId = existing[0].id
      } else {
        const [result] = await pool.query(
          'INSERT INTO wecom_conversations (external_id, name, type, last_message, last_time, unread) VALUES (?, ?, "single", ?, NOW(), 1)',
          [fromUser, fromUser, content]
        )
        conversationId = result.insertId
      }

      await pool.query(
        'INSERT INTO wecom_messages (conversation_id, sender, is_self, content) VALUES (?,?,FALSE,?)',
        [conversationId, fromUser, content]
      )
      await pool.query(
        'UPDATE wecom_conversations SET last_message = ?, last_time = NOW(), unread = unread + 1 WHERE id = ?',
        [content, conversationId]
      )
    }

    // Handle events (approval status change, etc.) — log for now
    if (msgType === 'event' && event) {
      console.log(`[wecom] Received event: ${event} from ${fromUser}`)
    }

    res.send('success')
  } catch (err) { next(err) }
})

// Public scan page info (no auth)
app.get('/api/scan/:code', async (req, res, next) => {
  try {
    const [[qr]] = await pool.query(
      `SELECT q.id, q.code, q.status, q.warranty_end, q.after_sale_contact, q.scan_count,
        p.name as product_name, p.sku, p.spec, p.category, p.image_main, p.external_links,
        q.group_qr_url, q.group_qr_type
       FROM qrcodes q
       LEFT JOIN products p ON q.product_id = p.id
       WHERE q.code = ?`,
      [req.params.code]
    )
    if (!qr) return res.status(404).json({ code: 404, message: '二维码不存在' })
    const [repairRecords] = await pool.query(
      "SELECT repair_person, issue, solution, repaired_at FROM repair_records WHERE qrcode_id = (SELECT id FROM qrcodes WHERE code = ?) AND status = 'completed' ORDER BY repaired_at DESC",
      [req.params.code]
    )
    qr.image_url = `/uploads/qrcodes/${qr.code}.png`
    res.json({ code: 0, data: { ...qr, repairRecords }, message: 'ok' })
  } catch (err) { next(err) }
})

// Health check (no auth)
app.get('/health', async (req, res) => {
  try {
    await pool.query('SELECT 1')
    res.json({ status: 'ok', timestamp: new Date().toISOString() })
  } catch {
    res.status(503).json({ status: 'error', message: 'database unreachable' })
  }
})

// H5 public routes (no auth)
app.use('/api/h5', h5Routes)
app.use('/api/wxmp', wxmpRoutes)

// Collage 拼团 (public - 商品浏览)
app.use('/api/collage', collageRoutes)
// 支付（回调公开，其他需认证）
app.use('/api/pay', payRoutes)
app.use('/api/restaurant', auth, apiLimiter, restaurantRoutes)
app.use('/api/hotel', auth, apiLimiter, hotelRoutes)
app.use('/api/logistics', auth, apiLimiter, logisticsRoutes)
app.use('/api/article', auth, apiLimiter, articleRoutes)
app.use('/api/yuyue', auth, apiLimiter, yuyueRoutes)
app.use('/api/score-shop', auth, apiLimiter, scoreShopRoutes)
app.use('/api/seckill', auth, apiLimiter, seckillRoutes)
app.use('/api/coupon', auth, apiLimiter, couponRoutes)
app.use('/api/kefu', auth, apiLimiter, kefuRoutes)

// Protected routes (with API rate limiting)
app.use('/api/products', auth, apiLimiter, productRoutes)
app.use('/api/materials', materialRoutes)
app.use('/api/warehouses', auth, apiLimiter, warehouseRoutes)
app.use('/api/stock-alerts', auth, apiLimiter, alertRoutes)
app.use('/api/approvals', auth, apiLimiter, approvalRoutes)
app.use('/api/dashboard', auth, apiLimiter, dashboardRoutes)
app.use('/api/bi', auth, apiLimiter, biRoutes)
app.use('/api/excel-report', auth, apiLimiter, excelReportRoutes)
app.use('/api/orders', auth, apiLimiter, ordersRoutes)
app.use('/api/online-orders', auth, apiLimiter, onlineOrdersRoutes)
app.use('/api/reports', auth, apiLimiter, reportsRoutes)
app.use('/api/edu', auth, apiLimiter, eduRoutes)
app.use('/api/qrcodes', auth, apiLimiter, qrcodeRoutes)
app.use('/api/delivery', auth, apiLimiter, deliveryRoutes)
app.use('/api/oa', cardRoutes)
app.use('/api/oa', auth, apiLimiter, oaRoutes)
app.use('/api/reports', auth, apiLimiter, reportRoutes)
app.use('/api/retail-records', auth, apiLimiter, retailRoutes)
app.use('/api/gift-approvals', auth, apiLimiter, giftApprovalRoutes)
app.use('/api/aftersales', auth, apiLimiter, aftersalesRoutes)
app.use('/api/wecom', auth, apiLimiter, wecomRoutes)
app.use('/api/settings', auth, apiLimiter, requireRole('admin'), settingsRoutes)
// 公开系统设置（无需登录）
app.get('/api/public-settings', async (req, res, next) => {
  try {
    const locale = req.query.locale || 'zh'
    const [rows] = await pool.query("SELECT `key`, value FROM settings")
    const data = { locale }
    for (const row of rows) {
      data[row.key] = row.value
    }
    data.site_name = data.site_name || '智能商业系统'
    data.site_name_en = data.site_name_en || 'SmartBiz'
    data.bot_name = data.bot_name || '美特'

    // Load modules + languages from server_profiles
    let profileId = null
    if (data.server_profile_id) {
      profileId = parseInt(data.server_profile_id)
    } else {
      const [[spRow]] = await pool.query('SELECT id FROM server_profiles WHERE ip = ? LIMIT 1', [process.env.SERVER_IP || ''])
      if (spRow) profileId = spRow.id
    }
    data.modules = []
    data.endpoints = []
    data.languages = ['zh', 'en']
    if (profileId) {
      const [modRows] = await pool.query('SELECT module_key FROM server_modules WHERE server_profile_id = ?', [profileId])
      data.modules = modRows.map(r => r.module_key)
      const [[spRow]] = await pool.query('SELECT language, site_name_zh, site_name_en FROM server_profiles WHERE id = ?', [profileId])
      if (spRow && spRow.language) {
        try {
          const langRaw = spRow.language.trim()
          data.languages = langRaw.startsWith('[') ? JSON.parse(langRaw) : langRaw.split(',').map(l => l.trim())
        } catch {}
      }
      // 🔧 用 server_profiles 的 site_name 覆盖 settings 的（行业模板可自定义）
      if (spRow && (spRow.site_name_zh || spRow.site_name_en)) {
        data.site_name = spRow.site_name_zh || data.site_name || '智能商业系统'
        data.site_name_en = spRow.site_name_en || data.site_name_en || 'SmartBiz'
      }
      // Load endpoints for this server profile
      try {
        const [epRows] = await pool.query('SELECT id, endpoint_type, label, url, is_primary, env, sort_order, description FROM server_endpoints WHERE server_profile_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC', [profileId])
        data.endpoints = epRows.map(r => ({ id: r.id, type: r.endpoint_type, label: r.label, url: r.url, is_primary: !!r.is_primary, env: r.env, sort_order: r.sort_order, description: r.description || '' }))
        console.log('[public-settings] profileId:', profileId, 'endpoints:', data.endpoints.length)
      } catch (e) { console.error('[public-settings] endpoints error:', e.message) }
    }

    res.json({ code: 0, data })
  } catch (err) { next(err) }
})
app.use('/api/system', auth, apiLimiter, requireRole('admin'), systemRoutes)
app.use('/api/wecom-admin', auth, apiLimiter, requireRole('admin'), wecomAdminRoutes)
app.use('/api/suppliers', auth, apiLimiter, supplierRoutes)
app.use('/api/dealers', auth, apiLimiter, dealerRoutes)
app.use('/api/stores', auth, apiLimiter, storeRoutes)
// Mall公开路由 - 不需要auth中间件
app.use('/api/mall', apiLimiter, mallRoutes)
// Allow certain /api/users endpoints for all authenticated users, require admin for others
app.use('/api/users', auth, apiLimiter, (req, res, next) => {
  const openPaths = ['/roles', '/subordinates', '/list']
  if (openPaths.includes(req.path) && req.method === 'GET') {
    return next() // Allow all authenticated users
  }
  return requireRole('admin')(req, res, next) // Require admin for other endpoints
}, userRoutes)
app.use('/api/categories', auth, apiLimiter, categoryRoutes)
app.use('/api/upload', auth, apiLimiter, uploadRoutes)
app.use('/api/import', auth, apiLimiter, importRoutes)
app.use('/api/images', auth, apiLimiter, imageRoutes)
app.use('/api/h5-admin', auth, apiLimiter, h5AdminRoutes)
app.use('/api/transfer', auth, apiLimiter, transferRoutes)
app.use('/api/identity', identityRoutes)
app.use('/api/finance-simple', auth, apiLimiter, financeSimpleRoutes)
app.use('/api/finance-report', auth, apiLimiter, financeReportRoutes)
app.use('/api/invoices', auth, apiLimiter, invoiceRoutes)
app.use('/api/job-responsibilities', auth, apiLimiter, jobResponsibilitiesRoutes)
app.use('/api/work-logs', auth, apiLimiter, workLogsRoutes)
app.use('/api/customer-level', auth, apiLimiter, customerLevelRoutes)
// /api/inventory-alert 已删除（inventory-alert.js + inventory_alerts/inventory_rules 表已清理）
app.use('/api/escalation', auth, apiLimiter, escalationRoutes)
app.use('/api/log-interactions', auth, apiLimiter, logInteractionsRoutes)
app.use('/api/order-aggregator', apiLimiter, orderAggregatorRoutes)
app.use('/api/chat', chatRoutes) // 已废弃，保留路由占位
app.use('/api/smart-studio', smartStudioRoutes)
app.use('/api/visit-logs', auth, apiLimiter, visitLogsRoutes)
app.use('/api/share-logs', shareLogsRoutes) // Mixed auth (some public, some protected)
app.use('/api/feedback', feedbackRoutes) // Mixed auth (some public, some protected)
// /api/transfer 重复注册已删除（line 469 已注册，此处为历史冗余）
// returns.js disabled — inventory.js 内置 /returns 路由接管（L313/L340）
// 原 returns.js 路由字段与 return_records 表结构不匹配，导致补货流程 500
// // 北京 L399 原本: app.use('/api/returns', auth, apiLimiter, returnsRoutes)
// 注释原因: returns.js 路由字段与 return_records 表结构不匹配，导致补货 500
// inventory.js L406 app.use('/api', inventoryRoutes) 内的 /returns 路由接管
app.use('/api/tasks', auth, apiLimiter, tasksRoutes)
app.use('/api/wallet', auth, apiLimiter, walletRoutes)
app.use('/api/invite', auth, apiLimiter, inviteRoutes)
app.use('/api/member-level', auth, apiLimiter, memberLevelRoutes)
app.use('/api/responsibilities', auth, apiLimiter, responsibilitiesRoutes)
app.use('/api/rbac/permissions', auth, apiLimiter, rbacPermissionRoutes)
app.use('/api/rbac/menus', auth, apiLimiter, rbacMenuRoutes)
app.use('/api/rbac/roles', auth, apiLimiter, rbacRoleRoutes)
app.use('/api/rbac/users', auth, apiLimiter, rbacUserRoleRoutes)
app.use('/api/server-profiles', auth, apiLimiter, requireRole('admin'), serverProfilesRoutes)
app.use('/api/server-endpoints', auth, apiLimiter, requireRole('admin'), serverEndpointsRoutes)
app.use('/api', auth, apiLimiter, inventoryRoutes)
app.use('/api/quote', auth, apiLimiter, quoteRoutes)
app.use('/api/rental', auth, apiLimiter, rentalRoutes)

// rentalPublicRoutes 已在 inventory catch-all 之前挂载（见上面的位置）

app.use(errorHandler)

const PORT = process.env.PORT || 3000
const server = app.listen(PORT, () => {
  console.log(`GDQ server running on port ${PORT}`)
  startCronJobs()
  startFinanceReminderJobs()
})

// Graceful shutdown
function shutdown(signal) {
  console.log(`${signal} received, shutting down gracefully...`)
  server.close(async () => {
    try { await pool.end() } catch {}
    process.exit(0)
  })
  // Force exit after 10s if connections don't close
  setTimeout(() => process.exit(1), 10000)
}
process.on('SIGTERM', () => shutdown('SIGTERM'))
process.on('SIGINT', () => shutdown('SIGINT'))
