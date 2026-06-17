import { reactive } from 'vue'
import i18n from '../i18n'

// 全局系统设置（从数据库加载）
const systemSettings = reactive({
  loaded: false,
  locale: 'zh',
  languages: ['zh', 'en'],
  bot_name: '美特',
  site_name: '彩美特',
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

      // 同时更新 i18n 的 system 字段（运行时覆盖静态翻译）
      // 同时更新 zh 和 en 两个 locale，确保切换语言时名字一致
      for (const l of ['zh', 'en']) {
        i18n.global.messages.value[l] = i18n.global.messages.value[l] || {}
        i18n.global.messages.value[l].system = {
          ...(i18n.global.messages.value[l].system || {}),
          name: l === 'en' && json.data.site_name_en ? json.data.site_name_en : (json.data.site_name || systemSettings.site_name),
          fullName: l === 'en' && json.data.site_name_en ? json.data.site_name_en : (json.data.site_name || systemSettings.site_name),
          companyName: l === 'en' && json.data.site_name_en ? json.data.site_name_en : (json.data.site_name || systemSettings.site_name),
          name_en: json.data.site_name_en || systemSettings.system_name_en,
          fullName_en: json.data.site_name_en || systemSettings.system_name_en,
          companyName_en: json.data.site_name_en || systemSettings.system_name_en,
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