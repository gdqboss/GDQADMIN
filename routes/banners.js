// routes/banners.js — 统一轮播图 / 主图管理 (多租户)
// 2026-08-12 立 — 替代 portal/mall/hqh5/wxmp 4 套重复 banner API
import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission, PERMISSIONS as P } from '../middleware/rbac.js'
import { writeAuditLog } from '../utils/audit.js'

const router = express.Router()

// ====== 公开接口（不需 auth,用于前端展示） ======

// GET /api/banners?position=home_top — 前端 <UiBanner> 调用
router.get('/', async (req, res) => {
  try {
    const { position, server_profile_id } = req.query
    // 2026-08-08 (江小鱼 fix): 未指定 profile 时, 根据 host 自动判断 (macau 中医学会)
    let profileId = parseInt(server_profile_id || req.headers['x-server-profile-id'])
    if (!profileId || isNaN(profileId)) {
      const host = (req.headers.host || '').toLowerCase()
      if (host.includes('aippmcm') || host.includes('101.33.32.177')) {
        profileId = 7  // macau 中医学会
      } else {
        profileId = 1  // SGP 默认
      }
    }

    let sql = `SELECT * FROM banners WHERE server_profile_id = ? AND status = 'active'`
    const params = [profileId]

    if (position) {
      sql += ` AND position = ?`
      params.push(position)
    }

    sql += ` AND (start_at IS NULL OR start_at <= NOW()) AND (end_at IS NULL OR end_at >= NOW())`
    sql += ` ORDER BY \`sort\` ASC, id ASC`

    const [rows] = await pool.execute(sql, params)
    res.json({ code: 0, data: rows })
  } catch (err) {
    console.error('[banners] list error:', err)
    res.status(500).json({ code: 500, message: err.message })
  }
})

// POST /api/banners/:id/click — 统计点击
router.post('/:id/click', async (req, res) => {
  try {
    await pool.execute(`UPDATE banners SET click_count = click_count + 1 WHERE id = ?`, [req.params.id])
    res.json({ code: 0 })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// POST /api/banners/:id/view — 统计曝光
router.post('/:id/view', async (req, res) => {
  try {
    await pool.execute(`UPDATE banners SET view_count = view_count + 1 WHERE id = ?`, [req.params.id])
    res.json({ code: 0 })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// ====== 管理接口 (需 auth + permission) ======

// 2026-08-12 多租户: 显式查 DB 拿 server_profile_id (修复 auth() race bug)
async function resolveProfileId(req) {
  try {
    const [rows] = await pool.execute('SELECT server_profile_id FROM users WHERE id = ?', [req.user.id])
    if (rows.length && rows[0].server_profile_id) return rows[0].server_profile_id
  } catch (_) { /* fallthrough */ }
  return req.user.server_profile_id || 1
}

// GET /api/admin/banners — 客户后台列表
router.get('/admin/list', auth, requirePermission(P.BANNERS_READ), async (req, res) => {
  try {
    const profileId = await resolveProfileId(req)
    const { position, status } = req.query

    let sql = `SELECT b.*, u.name as creator_name FROM banners b
               LEFT JOIN users u ON b.created_by = u.id
               WHERE b.server_profile_id = ?`
    const params = [profileId]

    if (position) { sql += ` AND b.position = ?`; params.push(position) }
    if (status) { sql += ` AND b.status = ?`; params.push(status) }

    sql += ` ORDER BY b.position, b.\`sort\` ASC, b.id DESC`

    const [rows] = await pool.execute(sql, params)
    res.json({ code: 0, data: rows })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// POST /api/admin/banners — 新增
router.post('/admin', auth, requirePermission(P.BANNERS_WRITE), async (req, res) => {
  try {
    const profileId = await resolveProfileId(req)
    const {
      position, title, subtitle, image_url, image_mobile_url,
      link_type = 'none', link_target, link_params,
      sort_order = 0, status = 'active', start_at, end_at
    } = req.body

    if (!position || !image_url) {
      return res.status(400).json({ code: 400, message: 'position 和 image_url 必填' })
    }

    const [result] = await pool.execute(
      `INSERT INTO banners
       (server_profile_id, position, title, subtitle, image_url, image_mobile_url,
        link_type, link_target, link_params, \`sort\`, status, start_at, end_at, created_by)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        profileId, position, title || null, subtitle || null, image_url, image_mobile_url || null,
        link_type, link_target || null, link_params ? JSON.stringify(link_params) : null,
        sort_order, status, start_at || null, end_at || null, req.user.id || null
      ]
    )

    await writeAuditLog(req, 'CREATE', 'banners', result.insertId, null, req.body)
    res.json({ code: 0, data: { id: result.insertId } })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// PATCH /api/admin/banners/:id — 编辑
router.patch('/admin/:id', auth, requirePermission(P.BANNERS_WRITE), async (req, res) => {
  try {
    const profileId = await resolveProfileId(req)
    const id = req.params.id

    const [oldRows] = await pool.execute(
      `SELECT * FROM banners WHERE id = ? AND server_profile_id = ?`,
      [id, profileId]
    )
    if (oldRows.length === 0) {
      return res.status(404).json({ code: 404, message: 'Banner 不存在或无权访问' })
    }

    const fields = []
    const values = []
    const allowedFields = ['position','title','subtitle','image_url','image_mobile_url','link_type','link_target','link_params','sort_order','status','start_at','end_at']
    for (const k of allowedFields) {
      if (k in req.body) {
        const col = k === 'sort_order' ? '`sort`' : k
        fields.push(`${col} = ?`)
        values.push(k === 'link_params' && req.body[k] ? JSON.stringify(req.body[k]) : req.body[k])
      }
    }

    if (fields.length === 0) return res.json({ code: 0 })

    values.push(id, profileId)
    await pool.execute(`UPDATE banners SET ${fields.join(', ')} WHERE id = ? AND server_profile_id = ?`, values)

    await writeAuditLog(req, 'UPDATE', 'banners', parseInt(id), oldRows[0], req.body)
    res.json({ code: 0 })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// DELETE /api/admin/banners/:id
router.delete('/admin/:id', auth, requirePermission(P.BANNERS_DELETE), async (req, res) => {
  try {
    const profileId = await resolveProfileId(req)
    const [oldRows] = await pool.execute(`SELECT * FROM banners WHERE id = ? AND server_profile_id = ?`, [req.params.id, profileId])
    if (oldRows.length === 0) return res.status(404).json({ code: 404, message: '不存在或无权' })

    await pool.execute(`DELETE FROM banners WHERE id = ? AND server_profile_id = ?`, [req.params.id, profileId])
    await writeAuditLog(req, 'DELETE', 'banners', parseInt(req.params.id), oldRows[0], null)
    res.json({ code: 0 })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// POST /api/admin/banners/reorder — 拖拽排序
router.post('/admin/reorder', auth, requirePermission(P.BANNERS_WRITE), async (req, res) => {
  try {
    const profileId = req.user.server_profile_id || 1
    const { items } = req.body  // [{id, sort_order}]

    for (const item of items) {
      await pool.execute(
        `UPDATE banners SET \`sort\` = ? WHERE id = ? AND server_profile_id = ?`,
        [item.sort_order, item.id, profileId]
      )
    }
    res.json({ code: 0 })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

export default router