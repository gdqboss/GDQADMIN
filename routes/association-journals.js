/**
 * 协会期刊 (journals)
 * - GET /api/association/journals - 公开列表 (published only)
 * - GET /api/association/journals/admin - admin 列表
 * - GET /api/association/journals/:id - 详情
 * - GET /api/association/journals/:id/download - 下载计数 +1
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
router.get('/', requireOptionalPermission(PERMISSIONS.ASSOCIATION_JOURNALS_READ), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const { keyword, category } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE server_profile_id = ? AND status = ?'
    const params = [sp, 'published']
    if (category) { where += ' AND category = ?'; params.push(category) }
    if (keyword) { where += ' AND (title LIKE ? OR volume LIKE ? OR issue LIKE ?)'; const kw = `%${keyword}%`; params.push(kw, kw, kw) }

    const sql = `SELECT id, title, volume, issue, cover_image, description, pdf_url, publish_date, category, download_count, view_count, sort_order FROM association_journals ${where} ORDER BY sort_order ASC, publish_date DESC LIMIT ? OFFSET ?`
    const countSql = `SELECT COUNT(*) as total FROM association_journals ${where}`
    const [[{ total }]] = await pool.query(countSql, params)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// admin 列表
router.get('/admin', requirePermission(PERMISSIONS.ASSOCIATION_JOURNALS_WRITE), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const { keyword, status, category } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE server_profile_id = ?'
    const params = [sp]
    if (status) { where += ' AND status = ?'; params.push(status) }
    if (category) { where += ' AND category = ?'; params.push(category) }
    if (keyword) { where += ' AND (title LIKE ? OR volume LIKE ?)'; const kw = `%${keyword}%`; params.push(kw, kw) }

    const sql = `SELECT * FROM association_journals ${where} ORDER BY sort_order ASC, created_at DESC LIMIT ? OFFSET ?`
    const countSql = `SELECT COUNT(*) as total FROM association_journals ${where}`
    const [[{ total }]] = await pool.query(countSql, params)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// 详情
router.get('/:id', requireOptionalPermission(PERMISSIONS.ASSOCIATION_JOURNALS_READ), async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT * FROM association_journals WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '期刊不存在' })
    await pool.query('UPDATE association_journals SET view_count = view_count + 1 WHERE id = ?', [req.params.id])
    row.view_count = (row.view_count || 0) + 1
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 下载计数 +1
router.post('/:id/download', requirePermission(PERMISSIONS.ASSOCIATION_JOURNALS_READ), async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT id, pdf_url FROM association_journals WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '期刊不存在' })
    await pool.query('UPDATE association_journals SET download_count = download_count + 1 WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: { pdf_url: row.pdf_url }, message: 'ok' })
  } catch (err) { next(err) }
})

// 新增
router.post('/', requirePermission(PERMISSIONS.ASSOCIATION_JOURNALS_WRITE), async (req, res, next) => {
  try {
    const sp = req.body.server_profile_id || 7
    const { title, volume, issue, cover_image, description, pdf_url, publish_date, category, status, sort_order } = req.body
    if (!title) return res.status(400).json({ code: 400, message: '标题必填' })
    const [result] = await pool.query(
      `INSERT INTO association_journals
        (server_profile_id, title, volume, issue, cover_image, description, pdf_url, publish_date, category, status, sort_order)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [sp, title, volume || '', issue || '', cover_image || '', description || '', pdf_url || '', publish_date || null, category || 'general', status || 'draft', sort_order || 99]
    )
    const [[row]] = await pool.query('SELECT * FROM association_journals WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 更新
router.put('/:id', requirePermission(PERMISSIONS.ASSOCIATION_JOURNALS_WRITE), async (req, res, next) => {
  try {
    const [[exists]] = await pool.query('SELECT id FROM association_journals WHERE id = ?', [req.params.id])
    if (!exists) return res.status(404).json({ code: 404, message: '期刊不存在' })
    const { title, volume, issue, cover_image, description, pdf_url, publish_date, category, status, sort_order } = req.body
    await pool.query(
      `UPDATE association_journals SET
        title = COALESCE(?, title), volume = COALESCE(?, volume),
        issue = COALESCE(?, issue), cover_image = COALESCE(?, cover_image),
        description = COALESCE(?, description), pdf_url = COALESCE(?, pdf_url),
        publish_date = COALESCE(?, publish_date), category = COALESCE(?, category),
        status = COALESCE(?, status), sort_order = COALESCE(?, sort_order)
       WHERE id = ?`,
      [title, volume, issue, cover_image, description, pdf_url, publish_date, category, status, sort_order, req.params.id]
    )
    const [[row]] = await pool.query('SELECT * FROM association_journals WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 删除
router.delete('/:id', requirePermission(PERMISSIONS.ASSOCIATION_JOURNALS_DELETE), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM association_journals WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

export default router