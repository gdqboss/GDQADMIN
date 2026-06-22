import { createI18n } from 'vue-i18n'
import zh from './zh.js'

// 默认只用 zh（主语言），en/ms 按需加载减少首屏体积
let savedLocale = 'zh'
try {
  const stored = localStorage.getItem('caimeite_locale')
  if (stored && (stored === 'zh' || stored === 'en' || stored === 'ms')) {
    savedLocale = stored
  }
} catch (e) {}

// 已加载的语言包缓存（按需填充）
const loadedLocales = { zh }

// 异步加载语言包的工厂
async function loadLocaleAsync(locale) {
  if (loadedLocales[locale]) return loadedLocales[locale]
  // 后台 fetch（不阻塞当前渲染）
  const mod = await import(/* @vite-ignore */ `./${locale}.js`)
  loadedLocales[locale] = mod.default
  return mod.default
}

const i18n = createI18n({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'zh',
  messages: { zh },   // 首屏只注册 zh
  globalInjection: true,
  missingWarn: false,
  fallbackWarn: false,
  missing: () => '',
})

// 语言切换（异步，en/ms 按需 import）
export async function setLocale(locale) {
  if (locale === i18n.global.locale.value) return
  if (locale !== 'zh' && !loadedLocales[locale]) {
    await loadLocaleAsync(locale)
  }
  if (loadedLocales[locale]) {
    i18n.global.messages.value[locale] = loadedLocales[locale]
  }
  i18n.global.locale.value = locale
  try { localStorage.setItem('caimeite_locale', locale) } catch (e) {}
}

// 如果保存的 locale 不是 zh，异步加载并切换
if (savedLocale !== 'zh') {
  loadLocaleAsync(savedLocale).then((messages) => {
    i18n.global.messages.value[savedLocale] = messages
  }).catch(() => {})
}

// 保留旧的同步入口（兼容现有调用方，但走 zh 单包路径）
i18n.setLocaleMessage = (locale) => {
  if (locale === 'zh' || loadedLocales[locale]) {
    if (loadedLocales[locale]) {
      i18n.global.messages.value[locale] = loadedLocales[locale]
    }
    i18n.global.locale.value = locale
  }
}

export default i18n
