import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

// GET /api/articles - 文章列表（分页+多条件筛选）
router.get('/', requirePermission(PERMISSIONS.ARTICLES_READ), async (req, res, next) => {
  try {
    const { keyword, status, category, tag, date_start, date_end } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (status) {
      where += ' AND a.status = ?'
      params.push(status)
      countParams.push(status)
    }
    if (category) {
      where += ' AND a.category = ?'
      params.push(category)
      countParams.push(category)
    }
    if (keyword) {
      where += ' AND (a.title LIKE ? OR a.summary LIKE ? OR a.author LIKE ?)'
      const kw = `%${keyword}%`
      params.push(kw, kw, kw)
      countParams.push(kw, kw, kw)
    }
    if (date_start) {
      where += ' AND DATE(a.created_at) >= ?'
      params.push(date_start)
      countParams.push(date_start)
    }
    if (date_end) {
      where += ' AND DATE(a.created_at) <= ?'
      params.push(date_end)
      countParams.push(date_end)
    }

    const sql = `
      SELECT a.*
      FROM articles a
      ${where}
      ORDER BY a.created_at DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(*) as total FROM articles a ${where}`

    const [[{ total }]] = await pool.query(countSql, countParams)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/articles/:id - 文章详情
router.get('/:id', requirePermission(PERMISSIONS.ARTICLES_READ), async (req, res, next) => {
  try {
    const [[article]] = await pool.query('SELECT * FROM articles WHERE id = ?', [req.params.id])
    if (!article) {
      return res.status(404).json({ code: 404, message: '文章不存在' })
    }
    // 浏览量+1
    await pool.query('UPDATE articles SET view_count = view_count + 1 WHERE id = ?', [req.params.id])
    article.view_count = (article.view_count || 0) + 1
    res.json({ code: 0, data: article, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/articles - 新增文章
router.post('/', requirePermission(PERMISSIONS.ARTICLES_WRITE), async (req, res, next) => {
  try {
    const { title, content, summary, cover_image, author, category, tags, status } = req.body
    if (!title) {
      return res.status(400).json({ code: 400, message: '标题必填' })
    }
    const [result] = await pool.query(
      `INSERT INTO articles (title, content, summary, cover_image, author, category, tags, status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [title, content || '', summary || '', cover_image || '', author || '', category || '', tags || '', status || 'draft']
    )
    const [[article]] = await pool.query('SELECT * FROM articles WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: article, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/articles/:id - 更新文章
router.put('/:id', requirePermission(PERMISSIONS.ARTICLES_WRITE), async (req, res, next) => {
  try {
    const { title, content, summary, cover_image, author, category, tags, status } = req.body
    const [[existing]] = await pool.query('SELECT * FROM articles WHERE id = ?', [req.params.id])
    if (!existing) {
      return res.status(404).json({ code: 404, message: '文章不存在' })
    }
    await pool.query(
      `UPDATE articles SET title=?, content=?, summary=?, cover_image=?, author=?, category=?, tags=?, status=?
       WHERE id=?`,
      [title ?? existing.title, content ?? existing.content, summary ?? existing.summary,
       cover_image ?? existing.cover_image, author ?? existing.author, category ?? existing.category,
       tags ?? existing.tags, status ?? existing.status, req.params.id]
    )
    const [[article]] = await pool.query('SELECT * FROM articles WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: article, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/articles/:id/status - 状态流转（发布/下架/归档）
router.put('/:id/status', requirePermission(PERMISSIONS.ARTICLES_WRITE), async (req, res, next) => {
  try {
    const { status } = req.body
    if (!status) return res.status(400).json({ code: 400, message: 'status 必填' })

    const [[existing]] = await pool.query('SELECT * FROM articles WHERE id = ?', [req.params.id])
    if (!existing) {
      return res.status(404).json({ code: 404, message: '文章不存在' })
    }

    const allowed = ['draft', 'published', 'archived']
    if (!allowed.includes(status)) {
      return res.status(400).json({ code: 400, message: '无效的状态' })
    }

    const updates = ['status = ?']
    const params = [status]
    if (status === 'published' && existing.status !== 'published') {
      updates.push('published_at = NOW()')
    }
    params.push(req.params.id)

    await pool.query(`UPDATE articles SET ${updates.join(', ')} WHERE id = ?`, params)
    const [[article]] = await pool.query('SELECT * FROM articles WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: article, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE /api/articles/:id - 删除文章
router.delete('/:id', requirePermission(PERMISSIONS.ARTICLES_DELETE), async (req, res, next) => {
  try {
    const [[existing]] = await pool.query('SELECT * FROM articles WHERE id = ?', [req.params.id])
    if (!existing) {
      return res.status(404).json({ code: 404, message: '文章不存在' })
    }
    await pool.query('DELETE FROM articles WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

export default router