// routes/theme.js — 主题 / UI 风格配置 (多租户,客户可自管)
// 2026-08-12 立
import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission, PERMISSIONS as P } from '../middleware/rbac.js'
import { writeAuditLog } from '../utils/audit.js'

const router = express.Router()

// ====== 公开接口 (前端启动读 theme) ======

// GET /api/theme — 前端 main.js 启动时调用
router.get('/', async (req, res) => {
  try {
    const profileId = parseInt(req.headers['x-server-profile-id'] || req.query.server_profile_id || 1)
    const [rows] = await pool.execute(
      `SELECT * FROM theme_config WHERE server_profile_id = ?`,
      [profileId]
    )

    if (rows.length === 0) {
      // 没配置返默认值
      return res.json({
        code: 0,
        data: {
          ui_kit: 'element-plus',
          theme_mode: 'light',
          primary_color: '#d97706',
          secondary_color: '#0f172a',
          font_family: '"PingFang SC", sans-serif',
          layout_type: 'admin'
        }
      })
    }
    res.json({ code: 0, data: rows[0] })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// ====== 管理接口 (客户后台改主题) ======

// GET /api/admin/theme — 客户当前主题
router.get('/admin', auth, requirePermission(P.THEME_READ), async (req, res) => {
  try {
    const profileId = req.user.server_profile_id || 1
    const [rows] = await pool.execute(
      `SELECT * FROM theme_config WHERE server_profile_id = ?`,
      [profileId]
    )
    res.json({ code: 0, data: rows[0] || null })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// PUT /api/admin/theme — 客户改主题(整个 upsert)
router.put('/admin', auth, requirePermission(P.THEME_WRITE), async (req, res) => {
  try {
    const profileId = req.user.server_profile_id || 1
    const [rows] = await pool.execute(
      `SELECT * FROM theme_config WHERE server_profile_id = ?`,
      [profileId]
    )
    const old = rows[0]

    const fields = [
      'ui_kit','theme_mode','primary_color','secondary_color','success_color','warning_color','danger_color',
      'bg_color','text_color','font_family','border_radius','layout_type','custom_css','logo_url','favicon_url'
    ]

    if (!old) {
      // INSERT
      const cols = ['server_profile_id', ...fields, 'updated_by']
      const placeholders = fields.map(() => '?').join(', ')
      const values = fields.map(f => req.body[f] ?? null)
      await pool.execute(
        `INSERT INTO theme_config (${cols.join(', ')}) VALUES (?, ${placeholders}, ?)`,
        [profileId, ...values, req.user.id || null]
      )
    } else {
      // UPDATE
      const setClause = fields.map(f => `${f} = ?`).join(', ')
      const values = fields.map(f => req.body[f] ?? old[f])
      await pool.execute(
        `UPDATE theme_config SET ${setClause}, updated_by = ? WHERE server_profile_id = ?`,
        [...values, req.user.id || null, profileId]
      )
    }

    await writeAuditLog(req, old ? 'UPDATE' : 'CREATE', 'theme_config', profileId, old || null, req.body)
    res.json({ code: 0 })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// POST /api/admin/theme/preset — 一键套用预设
router.post('/admin/preset', auth, requirePermission(P.THEME_WRITE), async (req, res) => {
  try {
    const profileId = req.user.server_profile_id || 1
    const { preset } = req.body  // 'caimeite-classic' / 'dark' / 'minimal' / 'business'

    const presets = {
      'caimeite-classic': { primary_color: '#d97706', secondary_color: '#0f172a', theme_mode: 'light', ui_kit: 'element-plus' },
      'dark': { primary_color: '#fbbf24', secondary_color: '#1e293b', theme_mode: 'dark', ui_kit: 'element-plus' },
      'minimal': { primary_color: '#18181b', secondary_color: '#71717a', theme_mode: 'light', ui_kit: 'naive-ui' },
      'business': { primary_color: '#1677ff', secondary_color: '#1f2937', theme_mode: 'light', ui_kit: 'ant-design' }
    }

    if (!presets[preset]) return res.status(400).json({ code: 400, message: '未知预设' })

    const cfg = presets[preset]
    await pool.execute(
      `UPDATE theme_config SET primary_color=?, secondary_color=?, theme_mode=?, ui_kit=?, updated_by=? WHERE server_profile_id=?`,
      [cfg.primary_color, cfg.secondary_color, cfg.theme_mode, cfg.ui_kit, req.user.id, profileId]
    )

    await writeAuditLog(req, 'UPDATE', 'theme_config', profileId, null, { preset, ...cfg })
    res.json({ code: 0 })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

export default router