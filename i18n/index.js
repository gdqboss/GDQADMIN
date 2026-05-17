import { createI18n } from 'vue-i18n'
import zh from './zh.js'

// 默认只用 zh（主语言），en 按需动态加载
let savedLocale = 'zh'
try {
  const stored = localStorage.getItem('caimeite_locale')
  if (stored && (stored === 'zh' || stored === 'en')) {
    savedLocale = stored
  }
} catch (e) {}

const loadedLocales = { zh }

const i18n = createI18n({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'zh',
  messages: loadedLocales,
  globalInjection: true,
  missingWarn: false,
  fallbackWarn: false,
  missing: () => '',
})

// 语言切换：动态 import 次语言包，已加载则跳过
i18n.setLocaleMessage = async (locale) => {
  if (loadedLocales[locale]) {
    i18n.global.locale.value = locale
    return
  }
  const mod = await import(/* @vite-ignore */ `./${locale}.js`)
  loadedLocales[locale] = mod.default
  i18n.global.locale.value = locale
}

// 初始化：如果上次选了 en，立即后台预加载（不等渲染）
if (savedLocale === 'en') {
  import('./en.js').then(mod => {
    loadedLocales.en = mod.default
  }).catch(() => {})
}

export default i18n