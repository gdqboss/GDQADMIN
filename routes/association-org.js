/**
 * 协会组织架构 (org) - 树形
 * - GET /api/association/org - 公开列表 (visible=1)
 * - GET /api/association/org/tree - 树形结构
 * - GET /api/association/org/admin - admin 列表
 * - GET /api/association/org/:id - 详情
 * - POST/PUT/DELETE 增删改
 * - DELETE /:id 会级联软删子节点（is_visible=0）
 */
import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js'
import { requireOptionalPermission } from '../middleware/require-optional-permission.js'

const router = Router()

function getServerProfileId(req) {
  return Number(req.query.server_profile_id || req.body.server_profile_id || 7)
}

// 公开列表（仅可见）
router.get('/', requireOptionalPermission(PERMISSIONS.ASSOCIATION_ORG_READ), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const [rows] = await pool.query(
      'SELECT * FROM association_org WHERE server_profile_id = ? AND is_visible = 1 ORDER BY sort_order ASC, id ASC',
      [sp]
    )
    res.json({ code: 0, data: { list: rows, total: rows.length }, message: 'ok' })
  } catch (err) { next(err) }
})

// 树形结构
router.get('/tree', requirePermission(PERMISSIONS.ASSOCIATION_ORG_READ), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const [rows] = await pool.query(
      'SELECT * FROM association_org WHERE server_profile_id = ? AND is_visible = 1 ORDER BY sort_order ASC, id ASC',
      [sp]
    )

    // 构建树
    const map = new Map()
    const roots = []
    rows.forEach(r => map.set(r.id, { ...r, children: [] }))
    rows.forEach(r => {
      const node = map.get(r.id)
      if (r.parent_id && map.has(r.parent_id)) {
        map.get(r.parent_id).children.push(node)
      } else {
        roots.push(node)
      }
    })
    res.json({ code: 0, data: { tree: roots }, message: 'ok' })
  } catch (err) { next(err) }
})

// admin 列表（含全部）
router.get('/admin', requirePermission(PERMISSIONS.ASSOCIATION_ORG_WRITE), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const { keyword } = req.query
    let where = 'WHERE server_profile_id = ?'
    const params = [sp]
    if (keyword) { where += ' AND name LIKE ?'; params.push(`%${keyword}%`) }
    const [rows] = await pool.query(
      `SELECT * FROM association_org ${where} ORDER BY parent_id ASC, sort_order ASC, id ASC`,
      params
    )
    res.json({ code: 0, data: { list: rows, total: rows.length }, message: 'ok' })
  } catch (err) { next(err) }
})

// 详情
router.get('/:id', requireOptionalPermission(PERMISSIONS.ASSOCIATION_ORG_READ), async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT * FROM association_org WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '节点不存在' })
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 新增
router.post('/', requirePermission(PERMISSIONS.ASSOCIATION_ORG_WRITE), async (req, res, next) => {
  try {
    const sp = req.body.server_profile_id || 7
    const { parent_id, name, title, avatar, bio, sort_order, is_visible } = req.body
    if (!name) return res.status(400).json({ code: 400, message: '姓名/部门名必填' })
    const [result] = await pool.query(
      `INSERT INTO association_org (server_profile_id, parent_id, name, title, avatar, bio, sort_order, is_visible)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [sp, parent_id || 0, name, title || '', avatar || '', bio || '', sort_order || 99, is_visible === false ? 0 : 1]
    )
    const [[row]] = await pool.query('SELECT * FROM association_org WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 更新
router.put('/:id', requirePermission(PERMISSIONS.ASSOCIATION_ORG_WRITE), async (req, res, next) => {
  try {
    const [[exists]] = await pool.query('SELECT id FROM association_org WHERE id = ?', [req.params.id])
    if (!exists) return res.status(404).json({ code: 404, message: '节点不存在' })
    const { parent_id, name, title, avatar, bio, sort_order, is_visible } = req.body
    // 防循环：parent_id 不能等于自己的 id
    if (parent_id && Number(parent_id) === Number(req.params.id)) {
      return res.status(400).json({ code: 400, message: '父节点不能是自身' })
    }
    await pool.query(
      `UPDATE association_org SET
        parent_id = COALESCE(?, parent_id), name = COALESCE(?, name),
        title = COALESCE(?, title), avatar = COALESCE(?, avatar),
        bio = COALESCE(?, bio), sort_order = COALESCE(?, sort_order),
        is_visible = COALESCE(?, is_visible)
       WHERE id = ?`,
      [parent_id, name, title, avatar, bio, sort_order, is_visible, req.params.id]
    )
    const [[row]] = await pool.query('SELECT * FROM association_org WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 删除（级联软删所有子节点）
router.delete('/:id', requirePermission(PERMISSIONS.ASSOCIATION_ORG_DELETE), async (req, res, next) => {
  try {
    const [[node]] = await pool.query('SELECT id FROM association_org WHERE id = ?', [req.params.id])
    if (!node) return res.status(404).json({ code: 404, message: '节点不存在' })
    // 递归收集所有后代 id
    const allIds = [Number(req.params.id)]
    let frontier = [Number(req.params.id)]
    while (frontier.length > 0) {
      const placeholders = frontier.map(() => '?').join(',')
      const [children] = await pool.query(
        `SELECT id FROM association_org WHERE parent_id IN (${placeholders})`,
        frontier
      )
      frontier = children.map(c => c.id)
      allIds.push(...frontier)
    }
    // 软删：is_visible = 0（保留数据）
    await pool.query(
      `UPDATE association_org SET is_visible = 0 WHERE id IN (${allIds.map(() => '?').join(',')})`,
      allIds
    )
    res.json({ code: 0, data: { affected: allIds.length }, message: '已软删' })
  } catch (err) { next(err) }
})

export default router