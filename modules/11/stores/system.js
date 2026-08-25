import { reactive } from 'vue'
import i18n from '../i18n'

// 全局系统设置（从数据库加载）
const systemSettings = reactive({
  loaded: false,
  locale: 'zh',
  languages: ['zh', 'en'],
  bot_name: '美特',
  site_name: '智能商业系统',
  system_name_en: 'TRAVELMATE',
  site_name_en: 'TRAVELMATE',
})

// 加载系统设置（从后端公开接口）
async function loadSystemSettings(locale = 'zh') {
  try {
    const baseURL = import.meta.env.VITE_API_BASE_URL || ''
    const res = await fetch(`${baseURL}/api/public-settings?locale=${locale}`)
    const json = await res.json()
    if (json.code === 0 && json.data) {
      systemSettings.loaded = true
      systemSettings.locale = json.data.locale || locale
      systemSettings.languages = json.data.languages || ['zh', 'en']
      systemSettings.bot_name = json.data.bot_name || systemSettings.bot_name
      systemSettings.site_name = json.data.site_name || systemSettings.site_name
      systemSettings.system_name_en = json.data.system_name_en || systemSettings.system_name_en
      systemSettings.site_name_en = json.data.site_name_en || systemSettings.site_name_en

      // 只更新当前 locale 的 system 字段, 不写死遍历 ['zh', 'en']
      // 之前会污染 messages.value 让 i18n 误判 en/zh-HK 等未下载语言"已加载"
      // 现在只更新当前 locale, 切语言时由 AppHeader.setLocale() 触发懒加载
      const currentLocale = i18n.global.locale.value
      if (i18n.global.messages.value[currentLocale]) {
        i18n.global.messages.value[currentLocale].system = {
          ...(i18n.global.messages.value[currentLocale].system || {}),
          name: json.data.site_name || systemSettings.site_name,
          fullName: json.data.site_name || systemSettings.site_name,
          companyName: json.data.site_name || systemSettings.site_name,
          name_en: json.data.site_name_en || systemSettings.system_name_en,
          fullName_en: json.data.site_name_en || systemSettings.system_name_en,
          companyName_en: json.data.site_name_en || systemSettings.system_name_en,
        }
      }

      // 如果后端下发的 locale 跟当前 i18n locale 不一致, 主动 setLocale 切过去
      // 例如 profile 6/7 默认 zh-HK, 启动 zh, 加载公共接口后立刻切 zh-HK
      // zh-HK.js 已被 lazy load 配置好, 这次切换会下载 zh-HK.js
      const desiredLocale = json.data.locale || (json.data.languages && json.data.languages[0]) || locale
      console.log('[system] locale sync: currentLocale=', currentLocale, 'desiredLocale=', desiredLocale, 'savedLocale=', locale)
      if (desiredLocale !== currentLocale) {
        try {
          const { setLocale } = await import('../i18n/index.js')
          console.log('[system] calling setLocale(', desiredLocale, ')')
          const ok = await setLocale(desiredLocale)
          console.log('[system] setLocale returned:', ok, 'final locale=', i18n.global.locale.value)
        } catch (e) {
          console.warn('[system] setLocale failed:', e)
        }
      }

      // 设置浏览器 Tab 标题：优先使用 site_name_en，否则用 site_name
      const tabTitle = systemSettings.site_name_en || systemSettings.site_name
      if (tabTitle) {
        document.title = tabTitle
      }
    }
  } catch (e) {
    console.warn('系统设置加载失败，使用默认值')
  }
}

export { systemSettings, loadSystemSettings }