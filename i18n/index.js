import { createI18n } from 'vue-i18n'
import zh from './zh.js'
import en from './en.js'

// 同步读取 locale，避免异步延迟
let savedLocale = 'zh'
try {
  const stored = localStorage.getItem('caimeite_locale')
  if (stored && (stored === 'zh' || stored === 'en')) {
    savedLocale = stored
  }
} catch (e) {
  console.warn('Failed to read locale from localStorage:', e)
}

const i18n = createI18n({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'zh',
  messages: { zh, en },
  // 关键配置：确保同步渲染
  globalInjection: true,
  missingWarn: false,
  fallbackWarn: false,
  // key 找不到时返回空字符串，不暴露 key
  missing: () => '',
})

export default i18n
