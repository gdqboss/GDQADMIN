import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// GET /api/public-settings - 公开获取系统配置（无auth）
router.get('/', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT `key`, value FROM settings')
    const data = { locale: req.query.locale || 'zh' }
    for (const row of rows) {
      try { data[row.key] = JSON.parse(row.value) } catch { data[row.key] = row.value }
    }
    data.bot_name = data.bot_name || '美特'

    // Load modules + languages from server_profiles
    let profileId = null
    if (data.server_profile_id) {
      profileId = parseInt(data.server_profile_id)
    }
    data.modules = []
    data.languages = ['zh', 'en']
    let profileSiteName = null
    if (profileId) {
      const [modRows] = await pool.query('SELECT module_key FROM server_modules WHERE server_profile_id = ?', [profileId])
      data.modules = modRows.map(r => r.module_key)
      const [[spRow]] = await pool.query('SELECT language, site_name_zh, site_name_en FROM server_profiles WHERE id = ?', [profileId])
      if (spRow && spRow.language) {
        try {
          data.languages = JSON.parse(spRow.language)
        } catch { data.languages = ['zh', 'en'] }
      }
      // 用 server_profiles 的 site_name 覆盖 settings 的（优先）
      if (spRow && (spRow.site_name_zh || spRow.site_name_en)) {
        profileSiteName = { zh: spRow.site_name_zh || '智能商业系统', en: spRow.site_name_en || 'SmartBiz' }
      }
    }
    // site_name 以 server_profiles 里的为准（行业模板可自定义）
    if (profileSiteName) {
      const locale = req.query.locale || req.cookies?.locale || 'zh'
      data.site_name = profileSiteName[locale] || profileSiteName.zh
      data.site_name_en = profileSiteName.en || 'SmartBiz'
    } else {
      data.site_name = data.site_name || '智能商业系统'
      data.site_name_en = data.site_name_en || 'SmartBiz'
    }

    res.json({ code: 0, data, message: 'ok' })
  } catch (err) { next(err) }
})

export default router
