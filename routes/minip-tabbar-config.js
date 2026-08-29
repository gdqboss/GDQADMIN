// ============================================================
// minip-tabbar-config.js (2026-08-27 江小鱼新建)
// minip 底部 tabbar 配置 - gdqadmin 后台可管理
// 零硬编码铁律 2026-08-12: tabbar 配置从 DB 读, gdqadmin 改后立即生效
// 注意: 本文件是 ESM 格式 (index.js 用 import 引入), 不能写 module.exports
// ============================================================
import { Router } from 'express'
import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'

const router = Router()

// 公共：按 user_type 过滤可见 tabs
export async function listTabsForUser(userType = 'guest', userRole = 'guest') {
  // 可见性过滤: 'all' 始终可见; 'staff' 只 staff/admin; 'customer' 只 customer; 'guest' 只未登录
  // 简化: 员工态 = userType='staff' OR userRole in ('admin','employee','manager',...)
  const isStaff = userType === 'staff' || ['admin','employee','manager','boss'].includes(userRole)
  const isCustomer = userType === 'customer'
  const isGuest = userType === 'guest' || (!isStaff && !isCustomer)

  const [rows] = await pool.query(`
    SELECT id, tab_key, label, icon, page_path, sort_order, visible_to, is_disabled
    FROM minip_tabbar_config
    WHERE is_active = 1 AND is_disabled = 0
    ORDER BY sort_order ASC
  `)
  console.log('[listTabsForUser]', userType, userRole, 'raw rows:', rows.length)

  return rows.filter(r => {
    if (r.visible_to === 'all') return true
    if (r.visible_to === 'staff') return isStaff
    if (r.visible_to === 'customer') return isCustomer
    if (r.visible_to === 'guest') return isGuest
    return false
  }).map(r => ({
    key: r.tab_key,
    label: r.label,
    icon: r.icon,
    pagePath: r.page_path,
    visibleTo: r.visible_to,
    sortOrder: r.sort_order,
  }))
}

// GET /api/minip/tabbar-config - 前端拉配置(给 UniTabbar 组件用)
// 不强制 auth, 根据 token 推断 user
router.get('/tabbar-config', async (req, res) => {
  try {
    let userType = 'guest'
    let userRole = 'guest'
    const authHeader = req.headers.authorization
    if (authHeader) {
      try {
        const token = authHeader.replace(/^Bearer\s+/i, '')
        const decoded = jwt.verify(token, process.env.JWT_SECRET || 'caimeite-dev-secret-2026')
        userType = (decoded.user_type || 'staff').toLowerCase()
        userRole = (decoded.role || 'employee').toLowerCase()
      } catch { /* invalid token, use guest */ }
    }

    const tabs = await listTabsForUser(userType, userRole)
    res.json({ code: 0, ok: true, data: { tabs, userType, userRole } })
  } catch (err) {
    console.error('[minip-tabbar-config] GET failed:', err.message)
    res.status(500).json({ code: 500, ok: false, error: err.message })
  }
})

// === gdqadmin 后台管理 CRUD (需要 admin 权限) ===

// GET /api/minip/tabbar-config/admin/list - 列出所有 tab 配置(gdqadmin)
router.get('/admin/list', async (req, res) => {
  try {
    const [rows] = await pool.query(`
      SELECT id, tab_key, label, icon, page_path, sort_order, visible_to, is_disabled, is_active,
             created_at, updated_at
      FROM minip_tabbar_config
      ORDER BY sort_order ASC
    `)
    res.json({ code: 0, ok: true, data: rows })
  } catch (err) {
    console.error('[minip-tabbar-config] admin list failed:', err.message)
    res.status(500).json({ code: 500, ok: false, error: err.message })
  }
})

// POST /api/minip/tabbar-config/admin/create - 新增 tab
router.post('/admin/create', async (req, res) => {
  try {
    const { tab_key, label, icon = '', page_path, sort_order = 0, visible_to = 'all', is_disabled = 0 } = req.body
    if (!tab_key || !label || !page_path) {
      return res.status(400).json({ code: 400, ok: false, error: 'tab_key/label/page_path 必填' })
    }
    const [result] = await pool.query(`
      INSERT INTO minip_tabbar_config (tab_key, label, icon, page_path, sort_order, visible_to, is_disabled)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    `, [tab_key, label, icon, page_path, sort_order, visible_to, is_disabled ? 1 : 0])
    res.json({ code: 0, ok: true, data: { id: result.insertId } })
  } catch (err) {
    console.error('[minip-tabbar-config] create failed:', err.message)
    res.status(500).json({ code: 500, ok: false, error: err.message })
  }
})

// PUT /api/minip/tabbar-config/admin/:id - 更新 tab
router.put('/admin/:id', async (req, res) => {
  try {
    const { id } = req.params
    const { label, icon, page_path, sort_order, visible_to, is_disabled } = req.body
    const fields = []
    const values = []
    if (label !== undefined) { fields.push('label = ?'); values.push(label) }
    if (icon !== undefined) { fields.push('icon = ?'); values.push(icon) }
    if (page_path !== undefined) { fields.push('page_path = ?'); values.push(page_path) }
    if (sort_order !== undefined) { fields.push('sort_order = ?'); values.push(sort_order) }
    if (visible_to !== undefined) { fields.push('visible_to = ?'); values.push(visible_to) }
    if (is_disabled !== undefined) { fields.push('is_disabled = ?'); values.push(is_disabled ? 1 : 0) }
    if (fields.length === 0) {
      return res.status(400).json({ code: 400, ok: false, error: '没有要更新的字段' })
    }
    values.push(id)
    const [result] = await pool.query(`UPDATE minip_tabbar_config SET ${fields.join(', ')} WHERE id = ?`, values)
    res.json({ code: 0, ok: true, data: { affectedRows: result.affectedRows } })
  } catch (err) {
    console.error('[minip-tabbar-config] update failed:', err.message)
    res.status(500).json({ code: 500, ok: false, error: err.message })
  }
})

// DELETE /api/minip/tabbar-config/admin/:id - 软删除
router.delete('/admin/:id', async (req, res) => {
  try {
    const { id } = req.params
    const [result] = await pool.query(`UPDATE minip_tabbar_config SET is_active = 0 WHERE id = ?`, [id])
    res.json({ code: 0, ok: true, data: { affectedRows: result.affectedRows } })
  } catch (err) {
    console.error('[minip-tabbar-config] delete failed:', err.message)
    res.status(500).json({ code: 500, ok: false, error: err.message })
  }
})

export default router
