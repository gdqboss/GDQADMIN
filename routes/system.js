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

// GET /api/system/settings - 获取公开的系统设置（无需登录）
router.get('/settings', async (req, res, next) => {
  try {
    const [rows] = await pool.query("SELECT `key`, value FROM settings")
    const data = {}
    for (const row of rows) {
      data[row.key] = row.value
    }
    // 默认值（如果数据库没有配置）
    data.site_name = data.site_name || '智能商业系统'
    data.site_name_en = data.site_name_en || 'SmartBiz'
    data.bot_name = data.bot_name || '美特'

// 返回服务器配置的 modules 列表
    // 优先从 settings.server_profile_id 读取，否则按 IP 匹配
    let profileId = null
    const [[profileIdRow]] = await pool.query("SELECT value FROM settings WHERE `key` = 'server_profile_id' LIMIT 1")
    if (profileIdRow) {
      profileId = parseInt(profileIdRow.value)
    } else {
      // fallback: 按当前服务器 IP 匹配（需要通过环境变量传入）
      const serverIp = process.env.SERVER_IP || ''
      const [[spRow]] = await pool.query('SELECT id FROM server_profiles WHERE ip = ? LIMIT 1', [serverIp])
      if (spRow) profileId = spRow.id
    }
    data.modules = []
    data.languages = ['zh', 'en'] // default
    if (profileId) {
      const [modRows] = await pool.query(
        'SELECT module_key FROM server_modules WHERE server_profile_id = ?',
        [profileId]
      )
      data.modules = modRows.map(r => r.module_key)
      const [[spRow]] = await pool.query('SELECT language FROM server_profiles WHERE id = ?', [profileId])
      if (spRow && spRow.language) {
        try {
          const langRaw = spRow.language.trim()
          // Handle both JSON array string ["zh","en"] and comma-separated "zh,en"
          if (langRaw.startsWith('[')) {
            data.languages = JSON.parse(langRaw)
          } else {
            data.languages = langRaw.split(',').map(l => l.trim())
          }
        } catch {}
      }
    }

    res.json({ code: 0, data })
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

export default router
