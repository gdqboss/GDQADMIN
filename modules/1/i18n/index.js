import { createI18n } from 'vue-i18n'
import zh from './zh.js'
import en from './en.js'
import ms from './ms.js'

// 默认只用 zh（主语言），en/ms 按需加载减少首屏体积
let savedLocale = 'zh'
try {
  const stored = localStorage.getItem('caimeite_locale')
  if (stored && (stored === 'zh' || stored === 'en' || stored === 'ms')) {
    savedLocale = stored
  }
} catch (e) {}

// 已加载的语言包缓存
const loadedLocales = { zh, en, ms }

const i18n = createI18n({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'zh',
  messages: { zh },   // 先只注册 zh
  globalInjection: true,
  missingWarn: false,
  fallbackWarn: false,
  missing: () => '',
})

// 语言切换（同步，因为 en 已预加载）
i18n.setLocaleMessage = (locale) => {
  if (!loadedLocales[locale]) return
  if (!i18n.global.messages.value[locale]) {
    i18n.global.messages.value[locale] = loadedLocales[locale]
  }
  i18n.global.locale.value = locale
}

// 立即预加载 en（后台异步，不阻塞渲染）
import('./en.js').then(mod => {
  loadedLocales.en = mod.default
  // 如果当前 locale 是 en，注入到 i18n
  if (i18n.global.locale.value === 'en') {
    i18n.global.messages.value.en = mod.default
  }
}).catch(() => {})

// 立即预加载 ms（后台异步，不阻塞渲染）
import('./ms.js').then(mod => {
  loadedLocales.ms = mod.default
  // 如果当前 locale 是 ms，注入到 i18n
  if (i18n.global.locale.value === 'ms') {
    i18n.global.messages.value.ms = mod.default
  }
}).catch(() => {})

export default i18n