import { Router } from 'express'
import { pool } from '../db/connection.js'
import { buildEndpointsForServer } from '../services/system-endpoints.js'

const router = Router()

// GET /api/public-settings - 公开获取系统配置（无auth）
router.get('/', async (req, res, next) => {
  console.log('[public-settings] ENTRY route hit')
  try {
    const [rows] = await pool.query('SELECT `key`, value FROM settings')
    const data = { locale: req.query.locale || null }  // 默认 null，让 profile 决定
    for (const row of rows) {
      try { data[row.key] = JSON.parse(row.value) } catch { data[row.key] = row.value }
    }
    data.bot_name = data.bot_name || '美特'

    // Load modules + languages from server_profiles
    // 优先顺序: 1) URL query ?server_profile_id=N (多租户/独立站切换) 2) settings.server_profile_id 3) null
    let profileId = null
    if (req.query.server_profile_id) {
      profileId = parseInt(req.query.server_profile_id)
      if (isNaN(profileId) || profileId <= 0) profileId = null
    }
    if (!profileId && data.server_profile_id) {
      profileId = parseInt(data.server_profile_id)
    }
    data.endpoints = []
    data.modules = []
    data.languages = ['zh', 'en']
    let profileSiteName = null
    if (profileId) {
      const [modRows] = await pool.query('SELECT module_key FROM server_modules WHERE server_profile_id = ?', [profileId])
      data.modules = modRows.map(r => r.module_key)
      data.endpoints = await buildEndpointsForServer(pool, profileId)
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
      // 加载该服务器的连接地址列表（H5/Admin/API/Minip Frontend 等）
      data.endpoints = await buildEndpointsForServer(pool, profileId)
      console.log('[public-settings] profileId:', profileId, 'endpoints:', data.endpoints.length)

      // 返回完整 profile 信息给前端（site_logo/domain/site_name_zh/en/industry/currency/language）
      const [[profileRow]] = await pool.query(
        `SELECT id, name, ip, domain, site_logo, site_name_zh, site_name_en, language, currency, industry, env, manager
         FROM server_profiles WHERE id = ?`,
        [profileId]
      )
      if (profileRow) data.profile = profileRow
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

    // 设置默认语言：根据 profile.languages 数组的第一个元素作为 data.locale
    // 这样前端 loadSystemSettings 时会把默认 locale 切到 zh-HK (HK profile)
    console.log('[public-settings] before locale set: data.locale=', data.locale, 'languages=', data.languages, 'req.query.locale=', req.query.locale)
    if (Array.isArray(data.languages) && data.languages.length > 0) {
      if (!data.locale) {
        data.locale = data.languages[0]  // 没指定就用 profile 默认
      }
      // 验证请求的 locale 是否在该 profile 支持列表里，不在则 fallback 到第一个
      if (req.query.locale && !data.languages.includes(req.query.locale)) {
        data.locale = data.languages[0]
      }
    }
    // 最终 fallback: 没有 profile 时默认 zh
    if (!data.locale) {
      data.locale = 'zh'
    }
    console.log('[public-settings] after locale set: data.locale=', data.locale)

    res.json({ code: 0, data, message: 'ok' })
  } catch (err) { next(err) }
})

export default router
