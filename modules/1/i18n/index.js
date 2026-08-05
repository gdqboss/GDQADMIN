import { createI18n } from 'vue-i18n'

// 2026-08-06 i18n 懒加载版 (SGP 主仓默认简体 zh):
//   - 启动只加载 zh (static import, 跟随主 bundle)
//   - en / ms 改成 dynamic import, 用户切换时才下载
//   - 之前手动打成一捆 (i18n-Xxx.js 477KB), 现在拆成 3 个独立 chunk
//   - 用户没选过语言 → 永远只下载 zh, 看不到英文 / 马来文
import zh from './zh.js'

export const SUPPORTED_LOCALES = ['zh', 'en', 'ms']

// 启动语言: 从 localStorage 读, 没存过就用 zh (SGP 默认简体)
let savedLocale = 'zh'
try {
  const stored = localStorage.getItem('caimeite_locale')
  if (stored && SUPPORTED_LOCALES.includes(stored)) {
    savedLocale = stored
  }
} catch (e) {}

// 已加载的语言缓存
const loadedLocales = new Set(['zh'])

const messages = { zh }

export const i18n = createI18n({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'zh',
  messages,
  globalInjection: true,
  missingWarn: false,
  fallbackWarn: false,
  sync: true,
  missing: (key) => '',  // 找不到 key 不显示 key 字符串, 避免用户看到 "nav.dashboard"
})

// 加载语言包 (懒加载核心函数)
// 用户没选过的语言永远不下载
async function loadLocale(locale) {
  if (loadedLocales.has(locale)) return true
  if (!SUPPORTED_LOCALES.includes(locale)) return false
  try {
    let mod
    if (locale === 'en') {
      mod = await import(/* @vite-ignore */ './en.js')
    } else if (locale === 'ms') {
      mod = await import(/* @vite-ignore */ './ms.js')
    } else {
      return false
    }
    i18n.global.setLocaleMessage(locale, mod.default)
    loadedLocales.add(locale)
    return true
  } catch (e) {
    console.warn('[i18n] loadLocale failed:', locale, e)
    return false
  }
}

// 切换语言 (UI 调用, async 等语言包加载完)
// ponytail: 如果用户选回 zh (默认已加载), 直接切换不需要等待
export async function setLocale(locale) {
  if (!SUPPORTED_LOCALES.includes(locale)) {
    console.warn('[i18n] setLocale rejected (not in whitelist):', locale)
    return false
  }
  if (locale !== 'zh' && !loadedLocales.has(locale)) {
    await loadLocale(locale)
  }
  i18n.global.locale.value = locale
  try { localStorage.setItem('caimeite_locale', locale) } catch (e) {}
  return true
}

export default i18n

if (typeof window !== 'undefined') {
  window.__i18n = i18n
  window.__i18n_loadLocale = loadLocale
}
