// utils/server-url.js
// 集中管理"当前服务器对外访问 URL"，避免二维码/链接写错服务器
//
// 优先级：
//   1. 环境变量 SCAN_BASE_URL（手动指定，最高优先级）
//   2. settings.server_profile_id → server_profiles.domain（自动推断当前服务器）
//   3. 默认 claw.gdqshop.cn（生产兜底，保留历史兼容）
//
// 一物一码二维码、扫码通知 URL、支付回调 URL 都应该走这个工具，不要再硬编码。

import { pool } from '../db/connection.js'

let cachedDomain = null
let cachedAt = 0
const CACHE_TTL_MS = 30 * 1000 // 30 秒缓存，避免每次都查 DB

/**
 * 获取当前服务器对外访问 base URL（不带尾斜杠）
 * @returns {Promise<string>} e.g. 'https://claw.gdqshop.cn' / 'https://wecom.gdqshop.cn' / 'https://www.mywh3.com'
 */
export async function getServerBaseUrl() {
  // 1. 手动指定（CI/迁移/测试用）
  if (process.env.SCAN_BASE_URL) {
    return process.env.SCAN_BASE_URL.replace(/\/+$/, '')
  }

  // 2. 查 settings.server_profile_id → server_profiles.domain
  const now = Date.now()
  if (cachedDomain && now - cachedAt < CACHE_TTL_MS) {
    return cachedDomain
  }

  try {
    const [[setting]] = await pool.query(
      "SELECT value FROM settings WHERE `key` = 'server_profile_id'"
    )
    if (setting && setting.value) {
      const profileId = parseInt(setting.value, 10)
      if (!isNaN(profileId)) {
        const [[profile]] = await pool.query(
          'SELECT domain FROM server_profiles WHERE id = ?',
          [profileId]
        )
        if (profile && profile.domain) {
          cachedDomain = `https://${profile.domain}`.replace(/\/+$/, '')
          cachedAt = now
          return cachedDomain
        }
      }
    }
  } catch (err) {
    console.warn('[server-url] failed to read settings.server_profile_id:', err.message)
  }

  // 3. 默认兜底（历史兼容）
  return 'https://claw.gdqshop.cn'
}

/**
 * 拼接扫码链接（hash 路由：/#/scan/<code>）
 * @param {string} code 二维码号
 */
export async function buildScanUrl(code) {
  const base = await getServerBaseUrl()
  return `${base}/#/scan/${code}`
}

/**
 * 清除缓存（settings 更新时手动调用）
 */
export function clearServerUrlCache() {
  cachedDomain = null
  cachedAt = 0
}