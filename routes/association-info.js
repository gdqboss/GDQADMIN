/**
 * 协会介绍 (单行配置表 association_profile)
 * - GET /api/association/info - 读取 (优先取当前 server_profile_id 对应行)
 * - PUT /api/association/info - 更新 (admin only)
 */
import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

function getServerProfileId(req) {
  return Number(req.query.server_profile_id || req.body.server_profile_id || 7)
}

// 读取 — 公开接口 (协会官网 PortalHome 必须未登录可访问)
//   2026-08-03 fix: /soc 等公开路由的 PortalHome onMounted 调此接口,
//   之前 requirePermission → 401 → api.js 401 interceptor 跳 /login → 整页空白
//   PUT 仍走 admin only 权限校验
//   2026-08-03+ 多语言: 返回原始行 (含 name_zh_tw / name_en / name_ms / slogan_*)
//   前端按 locale 自己挑字段, 缺语言 fallback 到 zh
router.get('/', async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const [[row]] = await pool.query('SELECT * FROM association_profile WHERE server_profile_id = ? LIMIT 1', [sp])
    if (!row) {
      return res.json({ code: 0, data: { server_profile_id: sp }, message: 'ok' })
    }
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 更新 (admin)
router.put('/', requirePermission(PERMISSIONS.ASSOCIATION_INFO_WRITE), async (req, res, next) => {
  try {
    const sp = req.body.server_profile_id || 7
    const {
      name_zh, name_en, name_zh_tw, name_ms,
      slogan, slogan_zh_tw, slogan_en, slogan_ms,
      intro, history, vision, logo, address, phone, email, website, founded_year
    } = req.body
    const [[exists]] = await pool.query('SELECT id FROM association_profile WHERE server_profile_id = ?', [sp])
    if (exists) {
      await pool.query(
        `UPDATE association_profile SET
          name_zh = COALESCE(?, name_zh), name_en = COALESCE(?, name_en),
          name_zh_tw = COALESCE(?, name_zh_tw), name_ms = COALESCE(?, name_ms),
          slogan = COALESCE(?, slogan), slogan_zh_tw = COALESCE(?, slogan_zh_tw),
          slogan_en = COALESCE(?, slogan_en), slogan_ms = COALESCE(?, slogan_ms),
          intro = COALESCE(?, intro), history = COALESCE(?, history), vision = COALESCE(?, vision),
          logo = COALESCE(?, logo), address = COALESCE(?, address),
          phone = COALESCE(?, phone), email = COALESCE(?, email),
          website = COALESCE(?, website), founded_year = COALESCE(?, founded_year),
          updated_by = ? WHERE server_profile_id = ?`,
        [name_zh, name_en, name_zh_tw, name_ms,
         slogan, slogan_zh_tw, slogan_en, slogan_ms,
         intro, history, vision, logo, address, phone, email, website, founded_year,
         req.user?.id || null, sp]
      )
    } else {
      await pool.query(
        `INSERT INTO association_profile (server_profile_id, name_zh, name_en, name_zh_tw, name_ms, slogan, slogan_zh_tw, slogan_en, slogan_ms, intro, history, vision, logo, address, phone, email, website, founded_year, updated_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [sp, name_zh, name_en, name_zh_tw, name_ms, slogan, slogan_zh_tw, slogan_en, slogan_ms,
         intro, history, vision, logo, address, phone, email, website, founded_year, req.user?.id || null]
      )
    }
    const [[row]] = await pool.query('SELECT * FROM association_profile WHERE server_profile_id = ?', [sp])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

export default router