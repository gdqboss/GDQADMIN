import { createI18n } from 'vue-i18n'
import zh from './zh.js'
import en from './en.js'
import ms from './ms.js'

// 启动语言：从 localStorage 读，三选一
let savedLocale = 'zh'
try {
  const stored = localStorage.getItem('caimeite_locale')
  if (stored && ['zh', 'en', 'ms'].includes(stored)) {
    savedLocale = stored
  }
} catch (e) {}

// 三个语言包全部 static import，gzip 后约 150K，不做异步加载
// ponytail: 删 setLocale async，删 dynamic import，删 import.meta.glob
// 三种语言打包进同一 chunk，避免 Vite hash 命名导致运行时找不到文件
const messages = { zh, en, ms }

export const i18n = createI18n({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'zh',
  messages,
  globalInjection: true,
  missingWarn: false,
  fallbackWarn: false,
  missing: () => '',
})

// 切语言：直接改 locale + 写 localStorage，调用方自己 reload 或刷新
// ponytail: 不引新依赖、不写异步、不抽函数；用 3 行实现
export function setLocale(locale) {
  if (!['zh', 'en', 'ms'].includes(locale)) return
  i18n.global.locale.value = locale
  try { localStorage.setItem('caimeite_locale', locale) } catch (e) {}
}

export default i18n

if (typeof window !== 'undefined') {
  window.__i18n = i18n
}
