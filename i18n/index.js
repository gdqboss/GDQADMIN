import { createI18n } from 'vue-i18n'
import zh from './zh.js'

let savedLocale = 'zh'
try {
  const stored = localStorage.getItem('caimeite_locale')
  if (stored && (stored === 'zh' || stored === 'en' || stored === 'ms')) {
    savedLocale = stored
  }
} catch (e) {}

// 已加载的语言包缓存
const loadedLocales = { zh }

async function loadLocaleAsync(locale) {
  if (loadedLocales[locale]) return loadedLocales[locale]
  const mod = await import(/* @vite-ignore */ `./${locale}.js`)
  loadedLocales[locale] = mod.default
  return mod.default
}

// 创建 i18n 实例（首屏只用 zh，en/ms 异步）
export const i18n = createI18n({
  legacy: false,
  locale: 'zh',
  fallbackLocale: 'zh',
  messages: { zh },
  globalInjection: true,
  missingWarn: false,
  fallbackWarn: false,
  missing: () => '',
})

// 首屏后立即异步切到 savedLocale
if (savedLocale !== 'zh') {
  loadLocaleAsync(savedLocale).then((messages) => {
    i18n.global.messages.value[savedLocale] = messages
    i18n.global.locale.value = savedLocale
  }).catch((err) => {
    console.warn('[i18n] 加载语言包失败:', savedLocale, err)
  })
}

// 语言切换（供组件直接 import 调用）—— 必须传 i18n 实例，因为 setLocale
// 内部用 i18n.global 修改；外部组件也用同一个实例就能保持响应式同步
export async function setLocale(locale, i18nInstance) {
  const inst = i18nInstance || i18n
  if (locale === inst.global.locale.value && inst.global.messages.value[locale]) {
    return
  }
  if (locale !== 'zh' && !loadedLocales[locale]) {
    await loadLocaleAsync(locale)
  }
  if (loadedLocales[locale]) {
    inst.global.messages.value[locale] = loadedLocales[locale]
  }
  inst.global.locale.value = locale
  try { localStorage.setItem('caimeite_locale', locale) } catch (e) {}
}

export default i18n

// 暴露到 window 上方便调试
if (typeof window !== 'undefined') {
  window.__i18n = i18n
}
