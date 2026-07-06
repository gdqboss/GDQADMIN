// System settings API routes
import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// GET /api/system/wechat-config - Get WeChat merchant config
router.get('/wechat-config', async (req, res, next) => {
  try {
    const [[config]] = await pool.query(
      'SELECT id, appid, mchid, api_key, cert_path, status FROM wechat_pay_config WHERE status = "active" LIMIT 1'
    )
    if (config) {
      // Don't return the actual API key, just mask it
      config.api_key = config.api_key ? '********' : ''
    }
    res.json({ code: 0, data: config || { appid: '', mchid: '', api_key: '', cert_path: '', status: 'active' } })
  } catch (err) { next(err) }
})

// POST /api/system/wechat-config - Save WeChat merchant config
router.post('/wechat-config', async (req, res, next) => {
  try {
    const { appid, mchid, api_key, cert_path, status } = req.body

    if (!appid || !mchid || !api_key) {
      return res.status(400).json({ code: 400, message: 'AppID、商户号和API密钥不能为空' })
    }

    // Check if config exists
    const [[existing]] = await pool.query('SELECT id FROM wechat_pay_config LIMIT 1')

    if (existing) {
      // Update
      await pool.query(
        'UPDATE wechat_pay_config SET appid = ?, mchid = ?, api_key = ?, cert_path = ?, status = ? WHERE id = ?',
        [appid, mchid, api_key, cert_path || '', status || 'active', existing.id]
      )
    } else {
      // Insert
      await pool.query(
        'INSERT INTO wechat_pay_config (appid, mchid, api_key, cert_path, status) VALUES (?, ?, ?, ?, ?)',
        [appid, mchid, api_key, cert_path || '', status || 'active']
      )
    }

    res.json({ code: 0, message: '保存成功' })
  } catch (err) { next(err) }
})

// GET /api/system/settings - Get general system settings (bot_name 等)
router.get('/settings', async (req, res, next) => {
  try {
    const [rows] = await pool.query("SELECT `key`, value FROM settings WHERE `key` IN ('bot_name', 'site_name', 'site_name_en', 'system_name_en')")
    const data = {}
    for (const row of rows) {
      data[row.key] = row.value
    }
    data.bot_name = data.bot_name || '美特'
    data.site_name = data.site_name || '彩美特'
    res.json({ code: 0, data })
  } catch (err) { next(err) }
})

// PUT /api/system/settings - Update general system settings
router.put('/settings', async (req, res, next) => {
  try {
    const allowed = ['bot_name', 'site_name', 'site_name_en', 'system_name_en']
    const updates = Object.entries(req.body).filter(([k]) => allowed.includes(k))

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有可更新的字段' })
    }

    for (const [key, value] of updates) {
      await pool.query(
        `INSERT INTO settings (\`key\`, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)`,
        [key, String(value)]
      )
    }

    res.json({ code: 0, message: '保存成功' })
  } catch (err) { next(err) }
})

export default router
