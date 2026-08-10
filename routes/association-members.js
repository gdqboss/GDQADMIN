/**
 * 协会会员管理 (扩展 members 表 + 协会元数据)
 * - GET /api/association/members - 会员列表 (server_profile_id 隔离)
 * - GET /api/association/members/:id - 详情
 * - POST/PUT/DELETE
 * - GET /api/association/members/levels - 等级下拉 (从 member_level)
 */
import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js'
import { requireOptionalPermission } from '../middleware/require-optional-permission.js'

const router = Router()

function getServerProfileId(req) {
  return Number(req.query.server_profile_id || req.body.server_profile_id || 7)
}

// 会员列表 (注意: members 表本身没有 server_profile_id,这里是面向"协会"语义的视图)
// 现阶段简单按 id 区间或全表 + 关键词筛
router.get('/', requireOptionalPermission(PERMISSIONS.ASSOCIATION_MEMBERS_READ), async (req, res, next) => {
  try {
    const { keyword, member_level, customer_type } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE 1=1'
    const params = []
    if (member_level) { where += ' AND m.member_level = ?'; params.push(Number(member_level)) }
    if (customer_type) { where += ' AND m.customer_type = ?'; params.push(customer_type) }
    if (keyword) { where += ' AND (m.name LIKE ? OR m.phone LIKE ?)'; const kw = `%${keyword}%`; params.push(kw, kw) }

    const sql = `
      SELECT m.*, ml.name as level_name, ml.icon as level_icon, ml.discount_rate
      FROM members m
      LEFT JOIN member_level ml ON ml.id = m.member_level
      ${where}
      ORDER BY m.id DESC LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(*) as total FROM members m ${where}`
    const [[{ total }]] = await pool.query(countSql, params)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// 详情
router.get('/:id', requireOptionalPermission(PERMISSIONS.ASSOCIATION_MEMBERS_READ), async (req, res, next) => {
  try {
    const [[row]] = await pool.query(
      `SELECT m.*, ml.name as level_name, ml.icon as level_icon, ml.discount_rate
       FROM members m
       LEFT JOIN member_level ml ON ml.id = m.member_level
       WHERE m.id = ?`, [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '会员不存在' })
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 新增
router.post('/', requirePermission(PERMISSIONS.ASSOCIATION_MEMBERS_WRITE), async (req, res, next) => {
  try {
    const { name, phone, customer_type, member_level, points } = req.body
    if (!name) return res.status(400).json({ code: 400, message: '姓名必填' })
    const [result] = await pool.query(
      `INSERT INTO members (name, phone, customer_type, member_level, points) VALUES (?, ?, ?, ?, ?)`,
      [name, phone || '', customer_type || 'normal', member_level || 1, points || 0]
    )
    const [[row]] = await pool.query('SELECT * FROM members WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 更新
router.put('/:id', requirePermission(PERMISSIONS.ASSOCIATION_MEMBERS_WRITE), async (req, res, next) => {
  try {
    const [[exists]] = await pool.query('SELECT id FROM members WHERE id = ?', [req.params.id])
    if (!exists) return res.status(404).json({ code: 404, message: '会员不存在' })
    const { name, phone, customer_type, member_level, points } = req.body
    await pool.query(
      `UPDATE members SET
        name = COALESCE(?, name), phone = COALESCE(?, phone),
        customer_type = COALESCE(?, customer_type), member_level = COALESCE(?, member_level),
        points = COALESCE(?, points)
       WHERE id = ?`,
      [name, phone, customer_type, member_level, points, req.params.id]
    )
    const [[row]] = await pool.query('SELECT * FROM members WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 删除
router.delete('/:id', requirePermission(PERMISSIONS.ASSOCIATION_MEMBERS_DELETE), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM members WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

// 等级下拉
router.get('/levels/all', requirePermission(PERMISSIONS.ASSOCIATION_MEMBERS_READ), async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT id, name, icon, min_points, max_points, discount_rate FROM member_level WHERE status = ? ORDER BY sort_order, id', ['active'])
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

export default router