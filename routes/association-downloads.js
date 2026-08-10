/**
 * 协会资料下载 (downloads)
 * - GET /api/association/downloads - 公开列表 (published only)
 * - GET /api/association/downloads/admin - admin 列表
 * - GET /api/association/downloads/:id - 详情
 * - POST /api/association/downloads/:id/download - 下载计数 +1
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
router.get('/', requireOptionalPermission(PERMISSIONS.ASSOCIATION_DOWNLOADS_READ), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const { keyword, category, file_type } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE server_profile_id = ? AND status = ?'
    const params = [sp, 'published']
    if (category) { where += ' AND category = ?'; params.push(category) }
    if (file_type) { where += ' AND file_type = ?'; params.push(file_type) }
    if (keyword) { where += ' AND (title LIKE ? OR description LIKE ? OR file_name LIKE ?)'; const kw = `%${keyword}%`; params.push(kw, kw, kw) }

    const sql = `SELECT id, title, description, file_url, file_name, file_size, file_type, category, cover_image, download_count, sort_order, published_at, created_at FROM association_downloads ${where} ORDER BY sort_order ASC, published_at DESC LIMIT ? OFFSET ?`
    const countSql = `SELECT COUNT(*) as total FROM association_downloads ${where}`
    const [[{ total }]] = await pool.query(countSql, params)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// admin 列表
router.get('/admin', requirePermission(PERMISSIONS.ASSOCIATION_DOWNLOADS_WRITE), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const { keyword, status, category } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE server_profile_id = ?'
    const params = [sp]
    if (status) { where += ' AND status = ?'; params.push(status) }
    if (category) { where += ' AND category = ?'; params.push(category) }
    if (keyword) { where += ' AND title LIKE ?'; const kw = `%${keyword}%`; params.push(kw) }

    const sql = `SELECT * FROM association_downloads ${where} ORDER BY sort_order ASC, created_at DESC LIMIT ? OFFSET ?`
    const countSql = `SELECT COUNT(*) as total FROM association_downloads ${where}`
    const [[{ total }]] = await pool.query(countSql, params)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// 详情
router.get('/:id', requireOptionalPermission(PERMISSIONS.ASSOCIATION_DOWNLOADS_READ), async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT * FROM association_downloads WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '资料不存在' })
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 下载计数 +1
router.post('/:id/download', requirePermission(PERMISSIONS.ASSOCIATION_DOWNLOADS_READ), async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT id, file_url, file_name FROM association_downloads WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '资料不存在' })
    await pool.query('UPDATE association_downloads SET download_count = download_count + 1 WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: { file_url: row.file_url, file_name: row.file_name }, message: 'ok' })
  } catch (err) { next(err) }
})

// 新增
router.post('/', requirePermission(PERMISSIONS.ASSOCIATION_DOWNLOADS_WRITE), async (req, res, next) => {
  try {
    const sp = req.body.server_profile_id || 7
    const { title, description, file_url, file_name, file_size, file_type, category, cover_image, status, sort_order } = req.body
    if (!title) return res.status(400).json({ code: 400, message: '标题必填' })
    if (!file_url) return res.status(400).json({ code: 400, message: '文件 URL 必填' })
    const [result] = await pool.query(
      `INSERT INTO association_downloads
        (server_profile_id, title, description, file_url, file_name, file_size, file_type, category, cover_image, status, sort_order, published_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [sp, title, description || '', file_url, file_name || '', file_size || 0, file_type || 'pdf', category || 'general', cover_image || '', status || 'draft', sort_order || 99, status === 'published' ? new Date() : null]
    )
    const [[row]] = await pool.query('SELECT * FROM association_downloads WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 更新
router.put('/:id', requirePermission(PERMISSIONS.ASSOCIATION_DOWNLOADS_WRITE), async (req, res, next) => {
  try {
    const [[exists]] = await pool.query('SELECT id FROM association_downloads WHERE id = ?', [req.params.id])
    if (!exists) return res.status(404).json({ code: 404, message: '资料不存在' })
    const { title, description, file_url, file_name, file_size, file_type, category, cover_image, status, sort_order } = req.body
    await pool.query(
      `UPDATE association_downloads SET
        title = COALESCE(?, title), description = COALESCE(?, description),
        file_url = COALESCE(?, file_url), file_name = COALESCE(?, file_name),
        file_size = COALESCE(?, file_size), file_type = COALESCE(?, file_type),
        category = COALESCE(?, category), cover_image = COALESCE(?, cover_image),
        status = COALESCE(?, status), sort_order = COALESCE(?, sort_order),
        published_at = CASE WHEN ? = 'published' AND published_at IS NULL THEN NOW() ELSE published_at END
       WHERE id = ?`,
      [title, description, file_url, file_name, file_size, file_type, category, cover_image, status, sort_order, status, req.params.id]
    )
    const [[row]] = await pool.query('SELECT * FROM association_downloads WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 删除
router.delete('/:id', requirePermission(PERMISSIONS.ASSOCIATION_DOWNLOADS_DELETE), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM association_downloads WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

export default router