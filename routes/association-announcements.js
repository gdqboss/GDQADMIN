/**
 * 协会信息发布 (announcements)
 * - GET /api/association/announcements - 公开列表 (published only)
 * - GET /api/association/announcements/admin - admin 列表 (含 draft)
 * - GET /api/association/announcements/:id - 详情
 * - POST/PUT/DELETE 增删改
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

// 公开列表
router.get('/', requireOptionalPermission(PERMISSIONS.ASSOCIATION_ANNOUNCEMENTS_READ), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const { keyword, category } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE server_profile_id = ? AND status = ?'
    const params = [sp, 'published']
    if (category) { where += ' AND category = ?'; params.push(category) }
    if (keyword) { where += ' AND (title LIKE ? OR summary LIKE ?)'; const kw = `%${keyword}%`; params.push(kw, kw) }

    const sql = `SELECT * FROM association_announcements ${where} ORDER BY priority DESC, published_at DESC LIMIT ? OFFSET ?`
    const countSql = `SELECT COUNT(*) as total FROM association_announcements ${where}`
    const [[{ total }]] = await pool.query(countSql, params)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// admin 列表 (含 draft)
router.get('/admin', requirePermission(PERMISSIONS.ASSOCIATION_ANNOUNCEMENTS_WRITE), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const { keyword, status, category } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE server_profile_id = ?'
    const params = [sp]
    if (status) { where += ' AND status = ?'; params.push(status) }
    if (category) { where += ' AND category = ?'; params.push(category) }
    if (keyword) { where += ' AND (title LIKE ? OR summary LIKE ?)'; const kw = `%${keyword}%`; params.push(kw, kw) }

    const sql = `SELECT * FROM association_announcements ${where} ORDER BY created_at DESC LIMIT ? OFFSET ?`
    const countSql = `SELECT COUNT(*) as total FROM association_announcements ${where}`
    const [[{ total }]] = await pool.query(countSql, params)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// 详情
router.get('/:id', requireOptionalPermission(PERMISSIONS.ASSOCIATION_ANNOUNCEMENTS_READ), async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT * FROM association_announcements WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '公告不存在' })
    await pool.query('UPDATE association_announcements SET view_count = view_count + 1 WHERE id = ?', [req.params.id])
    row.view_count = (row.view_count || 0) + 1
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 新增
router.post('/', requirePermission(PERMISSIONS.ASSOCIATION_ANNOUNCEMENTS_WRITE), async (req, res, next) => {
  try {
    const sp = req.body.server_profile_id || 7
    const { title, content, summary, cover_image, category, priority, status } = req.body
    if (!title) return res.status(400).json({ code: 400, message: '标题必填' })
    const [result] = await pool.query(
      `INSERT INTO association_announcements
        (server_profile_id, title, content, summary, cover_image, category, priority, status, author_id, published_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [sp, title, content || '', summary || '', cover_image || '', category || 'general', priority ? 1 : 0, status || 'draft', req.user?.id || null, status === 'published' ? new Date() : null]
    )
    const [[row]] = await pool.query('SELECT * FROM association_announcements WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 更新
router.put('/:id', requirePermission(PERMISSIONS.ASSOCIATION_ANNOUNCEMENTS_WRITE), async (req, res, next) => {
  try {
    const [[exists]] = await pool.query('SELECT id FROM association_announcements WHERE id = ?', [req.params.id])
    if (!exists) return res.status(404).json({ code: 404, message: '公告不存在' })
    const { title, content, summary, cover_image, category, priority, status } = req.body
    await pool.query(
      `UPDATE association_announcements SET
        title = COALESCE(?, title), content = COALESCE(?, content),
        summary = COALESCE(?, summary), cover_image = COALESCE(?, cover_image),
        category = COALESCE(?, category), priority = COALESCE(?, priority),
        status = COALESCE(?, status),
        published_at = CASE WHEN ? = 'published' AND published_at IS NULL THEN NOW() ELSE published_at END
       WHERE id = ?`,
      [title, content, summary, cover_image, category, priority, status, status, req.params.id]
    )
    const [[row]] = await pool.query('SELECT * FROM association_announcements WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 删除
router.delete('/:id', requirePermission(PERMISSIONS.ASSOCIATION_ANNOUNCEMENTS_DELETE), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM association_announcements WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

export default router