import { createI18n } from 'vue-i18n'

// 2026-08-06 i18n 懒加载版 (SGP 主仓默认简体 zh):
//   - 启动只加载 zh (static import, 跟随主 bundle)
//   - en / ms / zh-HK 改成 dynamic import, 用户切换时才下载
//   - 之前手动打成一捆 (i18n-Xxx.js 477KB), 现在拆成独立 chunk
//   - 用户没选过语言 → 永远只下载 zh, 看不到英文 / 马来文 / 繁体
//
// 2026-08-06 加 zh-HK: 港澳服务器 (profile 6 横琴 / profile 7 澳門中醫藥學會)
//   - server_profiles.language[0] = 'zh-HK', 公共接口下发 data.locale='zh-HK'
//   - 前端 setLocale('zh-HK') → 懒加载 zh-HK.js
//   - zh-HK 翻译没 key 时 fallbackLocale='zh' 显示简体
//
// 2026-08-06 删 SUPPORTED_LOCALES 写死: setLocale 接受任意 locale
//   - LOCALE_DYNAMIC_IMPORTS 字典决定"哪些 locale 可动态 import"
//   - 加新语言只需建 i18n/<locale>.js + 在字典里加一行,不用改 SUPPORTED_LOCALES
//   - 注意: DEFAULT_LOCALE **必须**静态 import 加进 messages, 否则启动会空
import zh from './zh.js'

// 静态默认 locale 必加载 (主 bundle 跟定, 改这里要同步改 vite.config manualChunks)
const DEFAULT_LOCALE = 'zh'

// 动态 import 字典: 决定哪些 locale 走 lazy load
// 2026-08-06 删 SUPPORTED_LOCALES 写死: setLocale 接受任意 locale, 这里只决定"动态加载路径"
// 2026-08-21 加别名: macau profile 7 配 language=["zh-TW","zh-CN","en-US"]
//   - zh-TW 跟 zh-HK 一样都是繁体, 复用 zh-HK.js
//   - en-US 跟 en 一样, 复用 en.js
const LOCALE_DYNAMIC_IMPORTS = {
  'en': () => import(/* @vite-ignore */ './en.js'),
  'en-US': () => import(/* @vite-ignore */ './en.js'),   // macau 别名
  'ms': () => import(/* @vite-ignore */ './ms.js'),
  'zh-HK': () => import(/* @vite-ignore */ './zh-HK.js'),
  'zh-TW': () => import(/* @vite-ignore */ './zh-HK.js'), // macau 别名 (繁体)
}

// 启动语言: 从 localStorage 读, 没存过就用 default
let savedLocale = DEFAULT_LOCALE
try {
  const stored = localStorage.getItem('caimeite_locale')
  if (stored) {
    savedLocale = stored
  }
} catch (e) {}

// 已加载的语言缓存
const loadedLocales = new Set([DEFAULT_LOCALE])

const messages = { [DEFAULT_LOCALE]: zh }

export const i18n = createI18n({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: DEFAULT_LOCALE,  // zh-HK 找不到的 key 退回 zh 简体,避免显示 "nav.dashboard"
  fallbackWarn: false,
  messages,
  globalInjection: true,
  missingWarn: false,
  sync: true,
  missing: (key) => '',  // 找不到 key 不显示 key 字符串, 避免用户看到 "nav.dashboard"
})

// 加载语言包 (懒加载核心函数)
// 用户没选过的语言永远不下载
// 2026-08-06 改: 不再用 SUPPORTED_LOCALES 写死, 字典里有就这么加载
export async function loadLocale(locale) {
  if (loadedLocales.has(locale)) return true
  const importer = LOCALE_DYNAMIC_IMPORTS[locale]
  if (!importer) {
    console.warn('[i18n] loadLocale has no dynamic import for:', locale, '(public-settings.languages may have stale value)')
    return false
  }
  try {
    const mod = await importer()
    i18n.global.setLocaleMessage(locale, mod.default)
    loadedLocales.add(locale)
    return true
  } catch (e) {
    console.warn('[i18n] loadLocale failed:', locale, e)
    return false
  }
}

// 切换语言 (UI 调用, async 等语言包加载完)
// 2026-08-06 改: 接受任意 locale, 字典里有就加载, 没有就 warn 且不切换
export async function setLocale(locale) {
  if (locale === i18n.global.locale.value) return true
  // 当 locale 不是 default 且字典里有 → 懒加载
  if (locale !== DEFAULT_LOCALE && !loadedLocales.has(locale)) {
    const ok = await loadLocale(locale)
    if (!ok) return false  // 字典里没有, 不切
  }
  i18n.global.locale.value = locale
  try { localStorage.setItem('caimeite_locale', locale) } catch (e) {}
  // 切换后给 <html> 加 lang + 字体 class (zh-HK 走繁体字体)
  try {
    const html = document.documentElement
    html.setAttribute('lang', locale)
    html.classList.remove('locale-zh', 'locale-en', 'locale-ms', 'locale-zh-HK')
    html.classList.add(`locale-${locale}`)
  } catch (e) {}
  // 2026-08-21 同步更新 <title>: 读 /api/public-settings 的 site_name (zh/en 按 locale 切)
  // 这样 macau (site_name_zh=澳門中醫藥學會) 切到英文时 title 也变 Macau Chinese Medicine Association
  updateDocumentTitle()
  return true
}

// 2026-08-21: 监听 locale 变化 + 拉 public-settings 同步 title
let _siteNameCache = null
async function fetchSiteName() {
  if (_siteNameCache) return _siteNameCache
  try {
    const res = await fetch('/api/public-settings')
    const json = await res.json()
    if (json?.code === 0 && json.data) {
      _siteNameCache = {
        zh: json.data.site_name_zh || json.data.site_name || '',
        en: json.data.site_name_en || json.data.site_name || '',
        bot: json.data.bot_name || ''
      }
    }
  } catch (e) {
    console.warn('[i18n] fetchSiteName failed:', e)
  }
  return _siteNameCache || { zh: '', en: '', bot: '' }
}

async function updateDocumentTitle() {
  const site = await fetchSiteName()
  const locale = i18n.global.locale.value
  let title = ''
  if (locale === 'en' || locale === 'en-US') {
    title = site.en
  } else if (locale === 'ms') {
    title = site.zh  // ms 没 site_name, 用 zh 兜底
  } else {
    // zh / zh-CN / zh-TW / zh-HK 都用 site_name_zh (繁体优先)
    title = site.zh
  }
  if (title) {
    // 保留原 title 后缀逻辑: 如果原本是 "X · 后缀", 这里直接覆盖
    document.title = title
  }
}

// 启动时也跑一次 (页面刷新, locale 从 localStorage 恢复)
if (typeof window !== 'undefined') {
  updateDocumentTitle()
}

export default i18n

if (typeof window !== 'undefined') {
  window.__i18n = i18n
  window.__i18n_loadLocale = loadLocale
}
