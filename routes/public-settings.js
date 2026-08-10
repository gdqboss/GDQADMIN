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
      // 2026-08-10 通用化: 默认 locale 从 server_profiles.languages[0] 拉
      // macau profile 7 → ['zh-TW','zh-CN','en'][0] = 'zh-TW' (繁体默认)
      // SGP profile 1 → ['zh','en','ms'][0] = 'zh' (简体默认)
      // 修复前 hardcode 'zh' 导致 macau /gdqadmin 后台走简体, site_name 取不到繁体
      const defaultLocale = (Array.isArray(data.languages) && data.languages.length > 0) ? data.languages[0] : 'zh'
      const locale = req.query.locale || req.cookies?.locale || defaultLocale
      data.site_name = profileSiteName[locale] || profileSiteName.zh
      data.site_name_en = profileSiteName.en || 'SmartBiz'
    } else {
      data.site_name = data.site_name || '智能商业系统'
      data.site_name_en = data.site_name_en || 'SmartBiz'
    }

    // 设置默认语言: 每个服务器一个默认 (server_profiles.languages[0])
    // 2026-08-06 波哥新规: 以后不写死多语言数组, 每个 profile 默认只能 1 个, 其它运行时按需加载
    // 防御: 即使 DB 里写成了多元素数组, 也只取第一个作为默认 (保证"一个默认"铁律不破)
    if (Array.isArray(data.languages) && data.languages.length > 0) {
      if (!data.locale) {
        data.locale = data.languages[0]  // 没指定就用 profile 默认
      }
    }
    // 用户请求的非默认 locale, 前端 lazy load 后还能用, 这里是设默认 data.locale
    // 按新规"其它语言运行时按需加载", 任意 locale 都应该接受
    // 最终 fallback: 没有 profile / profile 没配语言 → zh
    if (!data.locale) {
      data.locale = 'zh'
    }

    res.json({ code: 0, data, message: 'ok' })
  } catch (err) { next(err) }
})

export default router
