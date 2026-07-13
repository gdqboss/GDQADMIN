// routes/translations.js — 多语言翻译 (客户可改)
// 2026-08-12 立
import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission, PERMISSIONS as P } from '../middleware/rbac.js'
import { writeAuditLog } from '../utils/audit.js'

const router = express.Router()

// ====== 公开接口 (前端 i18n 加载) ======

// GET /api/translations?lang=zh — 前端启动按 lang 拉所有翻译
router.get('/', async (req, res) => {
  try {
    const profileId = parseInt(req.headers['x-server-profile-id'] || req.query.server_profile_id || 1)
    const lang = req.query.lang || 'zh'

    const [rows] = await pool.execute(
      `SELECT msg_key, msg_value FROM translations WHERE server_profile_id = ? AND lang = ?`,
      [profileId, lang]
    )

    const dict = {}
    for (const r of rows) dict[r.msg_key] = r.msg_value
    res.json({ code: 0, data: dict })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// ====== 管理接口 ======

// GET /api/admin/translations?lang=zh&module=common — 后台列表
router.get('/admin/list', auth, requirePermission(P.TRANSLATIONS_READ), async (req, res) => {
  try {
    const profileId = req.user.server_profile_id || 1
    const { lang = 'zh', module } = req.query

    let sql = `SELECT * FROM translations WHERE server_profile_id = ? AND lang = ?`
    const params = [profileId, lang]
    if (module) { sql += ` AND module = ?`; params.push(module) }
    sql += ` ORDER BY module, msg_key`

    const [rows] = await pool.execute(sql, params)
    res.json({ code: 0, data: rows })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// PUT /api/admin/translations — 批量更新/插入 (客户后台表单提交)
router.put('/admin', auth, requirePermission(P.TRANSLATIONS_WRITE), async (req, res) => {
  try {
    const profileId = req.user.server_profile_id || 1
    const { items } = req.body  // [{lang, msg_key, msg_value, module}]

    for (const item of items) {
      await pool.execute(
        `INSERT INTO translations (server_profile_id, lang, msg_key, msg_value, module)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE msg_value = VALUES(msg_value), module = VALUES(module)`,
        [profileId, item.lang, item.msg_key, item.msg_value, item.module || 'common']
      )
    }

    await writeAuditLog(req, 'UPDATE', 'translations', null, null, { count: items.length })
    res.json({ code: 0 })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// DELETE /api/admin/translations/:id
router.delete('/admin/:id', auth, requirePermission(P.TRANSLATIONS_DELETE), async (req, res) => {
  try {
    const profileId = req.user.server_profile_id || 1
    await pool.execute(
      `DELETE FROM translations WHERE id = ? AND server_profile_id = ?`,
      [req.params.id, profileId]
    )
    res.json({ code: 0 })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

export default router