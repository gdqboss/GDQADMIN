/**
 * apply-theme.js — 主题注入工具 (0.6 铁律)
 * 从 /api/theme 读 profile 的 theme_config → 写到 <html style> 让 CSS variables 立即生效
 *
 * 用法:
 *   import { applyThemeFromServer } from '@/utils/apply-theme'
 *   await applyThemeFromServer()
 *
 * 设计原则:
 *   - 不依赖 Pinia / store (避免循环依赖 + 简化测试)
 *   - 不抛错 (即使后端挂了, 也用 fallback 默认主题)
 *   - 修改 <html> 的 style, 全局生效
 */

import axios from 'axios'

const FALLBACK_THEME = {
  primary_color: '#d97706',
  secondary_color: '#0f172a',
  bg_color: '#f8fafc',
  text_color: '#1f2937',
  font_family: '-apple-system, BlinkMacSystemFont, "PingFang SC", sans-serif',
  theme_mode: 'light',
  border_radius: '8px',
}

/**
 * 应用主题配置到 <html>
 * @param {Object} theme - theme_config 行
 */
export function applyTheme(theme) {
  const t = { ...FALLBACK_THEME, ...(theme || {}) }
  const root = document.documentElement
  const style = root.style

  // 主色
  style.setProperty('--ui-primary', t.primary_color)
  style.setProperty('--ui-secondary', t.secondary_color || '#0f172a')

  // 背景 / 文字
  style.setProperty('--ui-bg-page', t.bg_color)
  style.setProperty('--ui-text', t.text_color)

  // 字体
  style.setProperty('--ui-font-family', t.font_family)

  // 圆角
  style.setProperty('--ui-radius', t.border_radius)

  // 主题模式 (light/dark) 切换 dataset, 让 theme.css 的 [data-theme=dark] 生效
  root.dataset.theme = t.theme_mode || 'light'

  // 同步 ElementPlus 的 CSS 变量 (已有 --el-color-primary 等)
  style.setProperty('--el-color-primary', t.primary_color)

  console.log('[theme] applied:', { primary: t.primary_color, mode: t.theme_mode })
}

/**
 * 从服务器拉 theme_config 并应用 (profile 1 = 当前登录服务器的 server_profile_id)
 * @returns {Promise<Object>} theme_config 行
 */
export async function applyThemeFromServer() {
  try {
    // 公开 API 不需要 token (theme 是公开的)
    const { data } = await axios.get('/api/theme', { timeout: 5000 })
    if (data && data.data) {
      applyTheme(data.data)
      return data.data
    }
  } catch (e) {
    console.warn('[theme] fetch failed, use fallback:', e.message)
  }
  applyTheme(FALLBACK_THEME)
  return FALLBACK_THEME
}

/**
 * 监听主题更新事件 (ThemeConfig.vue 保存后 emit)
 */
export function onThemeChange(handler) {
  window.addEventListener('ui:theme-change', (e) => {
    handler(e.detail)
  })
}

export function emitThemeChange(theme) {
  applyTheme(theme)
  window.dispatchEvent(new CustomEvent('ui:theme-change', { detail: theme }))
}
