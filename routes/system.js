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

export default router
