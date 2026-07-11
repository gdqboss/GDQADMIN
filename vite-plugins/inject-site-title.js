/**
 * Vite 插件：编译期注入 <title>
 *
 * 解决：index.html 写死 "SmartBiz"，SPA 加载前浏览器 tab 永远先闪 SmartBiz，
 *      即便 main.js 异步拉 public-settings 覆盖也来不及。
 *
 * 策略：transformIndexHtml 阶段用 mysql 查 server_profiles.site_name_zh 替换
 *      默认占位符 "彩美特管理系统"。
 *
 * 行为：
 *   - VITE_ENABLED_MODULES 存在 → 该环境变量末尾数字当作 profile_id
 *     （profile-config.js 约定：VITE_ENABLED_MODULES=products,in-out,users,profile-1）
 *   - 否则读 process.env.PROFILE_ID（默认 1 = 新加坡）
 *   - 没查到 DB → 保留占位符 "彩美特管理系统"
 *
 * 注意：必须在 server (Express) 跑时才能连 MySQL。
 *       dist build 时如果 MySQL 不可用，catch 后保留 "彩美特管理系统"。
 */

import { createConnection } from 'mysql2/promise'
import fs from 'node:fs'
import path from 'node:path'

export default function injectSiteTitlePlugin() {
  return {
    name: 'inject-site-title',
    transformIndexHtml: {
      order: 'pre',
      handler(html, ctx) {
        // 从环境变量取 profile_id
        const profileId = process.env.PROFILE_ID
          ? Number(process.env.PROFILE_ID)
          : (() => {
              const m = (process.env.VITE_ENABLED_MODULES || '').match(/profile-(\d+)/)
              return m ? Number(m[1]) : 1
            })()

        // 同步读 .env（避免依赖用户传 env）—— cwd 是 /root/src，env 在 /home/gdq/server/.env
        let dbCfg = null
        try {
          const candidates = [
            path.resolve(process.cwd(), 'server/.env'),       // /root/src/server/.env（不存在兜底）
            '/home/gdq/server/.env'                            // ★ 实际部署位置
          ]
          for (const envPath of candidates) {
            if (!fs.existsSync(envPath)) continue
            const lines = fs.readFileSync(envPath, 'utf8').split('\n')
            const env = {}
            for (const line of lines) {
              const m = line.match(/^([A-Z_]+)=(.*)$/)
              if (m) env[m[1]] = m[2].trim().replace(/^['"]|['"]$/g, '')
            }
            if (env.DB_HOST && env.DB_USER && env.DB_NAME) {
              dbCfg = {
                host: env.DB_HOST,
                port: Number(env.DB_PORT || 3306),
                user: env.DB_USER,
                password: env.DB_PASSWORD || '',
                database: env.DB_NAME
              }
              break
            }
          }
        } catch (e) { /* silent */ }

        // transformIndexHtml 必须 sync 或 async（async 也行）
        let siteName = '彩美特管理系统'
        return fetchSiteName(dbCfg, profileId).then(name => {
          if (name) siteName = name
          return html.replace(
            /<title>[\s\S]*?<\/title>/,
            `<title>${escapeHtml(siteName)}</title>`
          )
        }).catch(() => html) // 出错保留原占位符
      }
    }
  }
}

async function fetchSiteName(dbCfg, profileId) {
  if (!dbCfg) return null
  try {
    const conn = await createConnection({
      ...dbCfg,
      connectTimeout: 2000,
      enableKeepAlive: false
    })
    const [rows] = await conn.execute(
      'SELECT site_name_zh FROM server_profiles WHERE id = ? LIMIT 1',
      [profileId]
    )
    await conn.end()
    if (rows && rows[0] && rows[0].site_name_zh) return rows[0].site_name_zh
  } catch (e) { /* silent */ }
  return null
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]))
}
