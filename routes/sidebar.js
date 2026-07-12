/**
 * Sidebar 模块化 API
 *
 *   GET /api/sidebar/modules  → 返回前端 Sidebar 需要的完整菜单结构
 *
 * 数据来源:
 *   1) menu_modules              模块注册表(key/label/icon/route/sort_order)
 *   2) menu_modules.children_json 子菜单 JSON 数组
 *   3) server_modules             当前服务器启用的模块(过滤掉未启用的)
 *   4) menu_config                隐藏菜单的可见性开关(visible=true 才返回)
 *
 * 返回结构:
 *   [
 *     {
 *       key: 'rental',
 *       label: '传媒租赁',
 *       icon: 'construction',
 *       to: '/adorder',
 *       sort_order: 6,
 *       children: [ { key, label, to, icon }, ... ]
 *     },
 *     ...
 *   ]
 *
 * 设计目的: 让 Sidebar.vue 不写死任何菜单,全部由数据驱动。
 * 后台 /api/settings/menu-modules 增删改后,前端刷新立即生效。
 */
import express from 'express'
import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'

const router = express.Router()

// 软解析 token
function softResolveUser(req) {
  try {
    const h = req.headers.authorization
    if (!h || !h.startsWith('Bearer ')) return null
    return jwt.verify(h.split(' ')[1], process.env.JWT_SECRET)
  } catch { return null }
}

// GET /api/sidebar/modules?server_profile_id=1
router.get('/modules', async (req, res, next) => {
  try {
    // 当前 server_profile_id (header 优先)
    const profileId = parseInt(req.headers['x-server-profile-id'] || req.query.server_profile_id || 1)

    // 1. 读菜单可见性 (menu_config) - 没有 role 默认全部 visible=true,不强制过滤
    const user = softResolveUser(req)
    const userRole = user?.role || null
    let visRows = []
    if (userRole) {
      try {
        const [r] = await pool.query('SELECT menu_key, visible FROM menu_config WHERE role = ?', [userRole])
        visRows = r
      } catch { visRows = [] }
    }
    const visibleMap = {}
    for (const r of visRows || []) {
      visibleMap[r.menu_key] = Boolean(r.visible)
    }

    // 2. 读该 profile 启用的模块(无 is_active 列,直接读所有)
    const [enabledRows] = await pool.query(
      'SELECT module_key FROM server_modules WHERE server_profile_id = ?',
      [profileId]
    ).catch(() => [[]])
    const enabledSet = new Set((enabledRows || []).map(r => r.module_key))

    // 3. 读所有菜单模块
    const [modRows] = await pool.query(
      `SELECT \`key\`, label_zh, label_en, icon, route, sort_order, required, children_json
       FROM menu_modules
       ORDER BY sort_order, \`key\``
    )

    // 4. 过滤 + 装配 children
    const result = []
    for (const m of modRows) {
      // 只返回启用的模块(或者必有的 required)
      if (!m.required && !enabledSet.has(m.key)) continue

      // 菜单可见性(如果 menu_config 明确为 false 则隐藏)
      if (visibleMap[m.key] === false) continue

      // 解析 children_json
      let children = []
      try {
        const raw = typeof m.children_json === 'string' ? JSON.parse(m.children_json) : m.children_json
        if (Array.isArray(raw)) {
          children = raw.filter(c => {
            // 子菜单也受可见性控制(默认可见)
            return visibleMap[c.key] !== false
          })
        }
      } catch { /* ignore parse error */ }

      result.push({
        key: m.key,
        label: m.label_zh,
        label_en: m.label_en,
        icon: m.icon || 'circle',
        to: m.route || null,
        sort_order: m.sort_order || 99,
        children: children.map(c => ({
          key: c.key,
          label: c.label_zh || c.label || c.key,
          to: c.to || '#',
          icon: c.icon || null
        }))
      })
    }

    res.json({ code: 0, data: result })
  } catch (e) { next(e) }
})

export default router
