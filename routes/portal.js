import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { PERMISSIONS, requirePermission } from '../middleware/rbac.js'

const router = Router()

// admin/* 路由先过 auth 解析 token（公开 API 不经过这段）
const adminRouter = Router()
adminRouter.use(auth)
const writePerm = requirePermission(PERMISSIONS.PORTAL_WRITE)

/**
 * Portal 模块 — 政务/企业门户渲染引擎
 * - 公开 API（无需登录）：首页聚合 / 列表 / 详情
 * - 管理 API：栏目 / 文章 / 轮播 / 图表 / 友链 CRUD
 *
 * 数据源（5 张表）：
 * - portal_categories        栏目（xwtz/xwdt 等）
 * - portal_articles          文章（绑定栏目 + URL slug）
 * - portal_banners           轮播图（4 个 slot）
 * - portal_charts            图表配置 + JSON data
 * - portal_friend_links      友情链接分组
 */

// ===== 公开 API =====

router.get('/home', async (req, res, next) => {
  try {
    const [banners] = await pool.query(
      `SELECT id, slot, title, subtitle, icon_url, image_url, link_url, sort_order
       FROM portal_banners
       WHERE is_active = 1
       ORDER BY slot ASC, sort_order ASC`
    )
    const bannersBySlot = {}
    for (const b of banners) {
      if (!bannersBySlot[b.slot]) bannersBySlot[b.slot] = []
      bannersBySlot[b.slot].push(b)
    }

    // 新闻动态 + 通知公告（最近 5 条）
    const [newsLatest] = await pool.query(
      `SELECT a.id, a.title, a.cover_url, a.published_at, c.slug AS category_slug, c.name AS category_name
       FROM portal_articles a
       JOIN portal_categories c ON c.id = a.category_id
       WHERE a.is_published = 1 AND c.slug IN ('xwdt','gzgg')
       ORDER BY a.published_at DESC
       LIMIT 10`
    )
    const [bulletinLatest] = await pool.query(
      `SELECT a.id, a.title, a.published_at, c.slug AS category_slug
       FROM portal_articles a
       JOIN portal_categories c ON c.id = a.category_id
       WHERE a.is_published = 1 AND c.slug = 'gzgg'
       ORDER BY a.published_at DESC
       LIMIT 5`
    )

    const [charts] = await pool.query(
      `SELECT id, slot, title, chart_type, source_url, config_json, data_json
       FROM portal_charts
       WHERE is_active = 1
       ORDER BY slot ASC`
    )

    const [links] = await pool.query(
      `SELECT id, group_name, title, url, logo_url, sort_order
       FROM portal_friend_links
       WHERE is_active = 1
       ORDER BY sort_order ASC`
    )
    const linksByGroup = {}
    for (const l of links) {
      if (!linksByGroup[l.group_name]) linksByGroup[l.group_name] = []
      linksByGroup[l.group_name].push(l)
    }

    res.json({
      code: 0,
      data: {
        banners: bannersBySlot,
        news_latest: newsLatest,
        bulletin_latest: bulletinLatest,
        charts,
        friend_links: linksByGroup,
      },
    })
  } catch (e) { next(e) }
})

router.get('/categories', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, parent_id, name, slug, description, sort_order
       FROM portal_categories
       WHERE is_active = 1
       ORDER BY sort_order ASC`
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

router.get('/articles', async (req, res, next) => {
  try {
    const { category, keyword, page = 1, size = 20 } = req.query
    const offset = (Number(page) - 1) * Number(size)
    const where = ['a.is_published = 1']
    const params = []
    if (category) { where.push('c.slug = ?'); params.push(category) }
    if (keyword) { where.push('a.title LIKE ?'); params.push(`%${keyword}%`) }
    const whereSql = 'WHERE ' + where.join(' AND ')

    const [countRows] = await pool.query(
      `SELECT COUNT(*) AS total FROM portal_articles a
       JOIN portal_categories c ON c.id = a.category_id ${whereSql}`,
      params
    )
    const total = countRows[0].total

    const [rows] = await pool.query(
      `SELECT a.id, a.title, a.summary, a.cover_url, a.published_at, a.view_count,
              c.slug AS category_slug, c.name AS category_name
       FROM portal_articles a
       JOIN portal_categories c ON c.id = a.category_id
       ${whereSql}
       ORDER BY a.published_at DESC LIMIT ? OFFSET ?`,
      [...params, Number(size), offset]
    )
    res.json({ code: 0, data: { list: rows, total } })
  } catch (e) { next(e) }
})

router.get('/articles/:idOrSlug', async (req, res, next) => {
  try {
    const { idOrSlug } = req.params
    const isNumeric = /^\d+$/.test(idOrSlug)
    const where = isNumeric ? 'a.id = ?' : 'a.slug = ?'
    const [rows] = await pool.query(
      `SELECT a.*, c.slug AS category_slug, c.name AS category_name
       FROM portal_articles a
       JOIN portal_categories c ON c.id = a.category_id
       WHERE ${where} AND a.is_published = 1
       LIMIT 1`,
      [idOrSlug]
    )
    if (rows.length === 0) return res.status(404).json({ code: 404, message: '文章不存在' })

    await pool.query('UPDATE portal_articles SET view_count = view_count + 1 WHERE id = ?', [rows[0].id])
    res.json({ code: 0, data: rows[0] })
  } catch (e) { next(e) }
})

router.get('/banners', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, slot, title, subtitle, icon_url, image_url, link_url, sort_order, is_active
       FROM portal_banners ORDER BY slot ASC, sort_order ASC`
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

router.get('/charts', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, slot, title, chart_type, source_url, config_json, data_json, is_active
       FROM portal_charts ORDER BY slot ASC`
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

router.get('/links', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, group_name, title, url, logo_url, sort_order, is_active
       FROM portal_friend_links ORDER BY sort_order ASC`
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

// ===== 管理 API =====
// 注：以下 admin/* 路由挂到 adminRouter 上（过 auth 中间件），统一最后 mount

// 栏目 CRUD
adminRouter.get('/categories', writePerm, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT * FROM portal_categories ORDER BY sort_order ASC, id ASC`
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})
adminRouter.post('/categories', writePerm, async (req, res, next) => {
  try {
    const { parent_id, name, slug, description, sort_order, is_active } = req.body
    const [r] = await pool.query(
      `INSERT INTO portal_categories (parent_id, name, slug, description, sort_order, is_active)
       VALUES (?, ?, ?, ?, ?, ?)`,
      [parent_id || 0, name, slug, description || '', sort_order || 0, is_active !== false ? 1 : 0]
    )
    res.json({ code: 0, data: { id: r.insertId } })
  } catch (e) { next(e) }
})
adminRouter.put('/categories/:id', writePerm, async (req, res, next) => {
  try {
    const { id } = req.params
    const { parent_id, name, slug, description, sort_order, is_active } = req.body
    // PATCH 语义：只更新传入字段
    const fields = []
    const params = []
    if (parent_id !== undefined) { fields.push('parent_id=?'); params.push(parent_id) }
    if (name !== undefined) { fields.push('name=?'); params.push(name) }
    if (slug !== undefined) { fields.push('slug=?'); params.push(slug) }
    if (description !== undefined) { fields.push('description=?'); params.push(description) }
    if (sort_order !== undefined) { fields.push('sort_order=?'); params.push(sort_order) }
    if (is_active !== undefined) { fields.push('is_active=?'); params.push(is_active ? 1 : 0) }
    if (fields.length === 0) return res.json({ code: 0, message: '无字段更新' })
    params.push(id)
    await pool.query(`UPDATE portal_categories SET ${fields.join(', ')} WHERE id=?`, params)
    res.json({ code: 0 })
  } catch (e) { next(e) }
})
adminRouter.delete('/categories/:id', writePerm, async (req, res, next) => {
  try {
    await pool.query('DELETE FROM portal_categories WHERE id = ?', [req.params.id])
    res.json({ code: 0 })
  } catch (e) { next(e) }
})

// 文章 CRUD
adminRouter.get('/articles', writePerm, async (req, res, next) => {
  try {
    const { category, keyword, status, page = 1, size = 20 } = req.query
    const where = ['1=1']
    const params = []
    if (category) { where.push('c.slug = ?'); params.push(category) }
    if (status === 'published') where.push('a.is_published = 1')
    if (status === 'draft') where.push('a.is_published = 0')
    if (keyword) { where.push('a.title LIKE ?'); params.push(`%${keyword}%`) }
    const whereSql = 'WHERE ' + where.join(' AND ')
    const offset = (Number(page) - 1) * Number(size)

    const [countRows] = await pool.query(
      `SELECT COUNT(*) AS total FROM portal_articles a
       JOIN portal_categories c ON c.id = a.category_id ${whereSql}`, params
    )
    const total = countRows[0].total
    const [rows] = await pool.query(
      `SELECT a.*, c.name AS category_name, c.slug AS category_slug
       FROM portal_articles a
       JOIN portal_categories c ON c.id = a.category_id
       ${whereSql} ORDER BY a.updated_at DESC LIMIT ? OFFSET ?`,
      [...params, Number(size), offset]
    )
    res.json({ code: 0, data: { list: rows, total } })
  } catch (e) { next(e) }
})
adminRouter.get('/articles/:id', writePerm, async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM portal_articles WHERE id = ?', [req.params.id])
    if (rows.length === 0) return res.status(404).json({ code: 404 })
    res.json({ code: 0, data: rows[0] })
  } catch (e) { next(e) }
})
adminRouter.post('/articles', writePerm, async (req, res, next) => {
  try {
    const { category_id, title, slug, summary, content, cover_url, is_published, published_at } = req.body
    const [r] = await pool.query(
      `INSERT INTO portal_articles (category_id, title, slug, summary, content, cover_url, is_published, published_at, view_count, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())`,
      [category_id, title, slug || null, summary || '', content || '', cover_url || '', is_published ? 1 : 0, published_at || null]
    )
    res.json({ code: 0, data: { id: r.insertId } })
  } catch (e) { next(e) }
})
adminRouter.put('/articles/:id', writePerm, async (req, res, next) => {
  try {
    const { id } = req.params
    const { category_id, title, slug, summary, content, cover_url, is_published, published_at } = req.body
    const fields = []
    const params = []
    if (category_id !== undefined) { fields.push('category_id=?'); params.push(category_id) }
    if (title !== undefined) { fields.push('title=?'); params.push(title) }
    if (slug !== undefined) { fields.push('slug=?'); params.push(slug) }
    if (summary !== undefined) { fields.push('summary=?'); params.push(summary) }
    if (content !== undefined) { fields.push('content=?'); params.push(content) }
    if (cover_url !== undefined) { fields.push('cover_url=?'); params.push(cover_url) }
    if (is_published !== undefined) { fields.push('is_published=?'); params.push(is_published ? 1 : 0) }
    if (published_at !== undefined) { fields.push('published_at=?'); params.push(published_at) }
    fields.push('updated_at=NOW()')
    if (fields.length === 1) return res.json({ code: 0, message: '无字段更新' })
    params.push(id)
    await pool.query(`UPDATE portal_articles SET ${fields.join(', ')} WHERE id=?`, params)
    res.json({ code: 0 })
  } catch (e) { next(e) }
})
adminRouter.delete('/articles/:id', writePerm, async (req, res, next) => {
  try {
    await pool.query('DELETE FROM portal_articles WHERE id = ?', [req.params.id])
    res.json({ code: 0 })
  } catch (e) { next(e) }
})

// 轮播 CRUD
adminRouter.post('/banners', writePerm, async (req, res, next) => {
  try {
    const { slot, title, subtitle, icon_url, image_url, link_url, sort_order, is_active } = req.body
    const [r] = await pool.query(
      `INSERT INTO portal_banners (slot, title, subtitle, icon_url, image_url, link_url, sort_order, is_active)
       VALUES (?,?,?,?,?,?,?,?)`,
      [slot, title || '', subtitle || '', icon_url || '', image_url || '', link_url || '', sort_order || 0, is_active !== false ? 1 : 0]
    )
    res.json({ code: 0, data: { id: r.insertId } })
  } catch (e) { next(e) }
})
adminRouter.put('/banners/:id', writePerm, async (req, res, next) => {
  try {
    const { slot, title, subtitle, icon_url, image_url, link_url, sort_order, is_active } = req.body
    const fields = []
    const params = []
    if (slot !== undefined) { fields.push('slot=?'); params.push(slot) }
    if (title !== undefined) { fields.push('title=?'); params.push(title) }
    if (subtitle !== undefined) { fields.push('subtitle=?'); params.push(subtitle) }
    if (icon_url !== undefined) { fields.push('icon_url=?'); params.push(icon_url) }
    if (image_url !== undefined) { fields.push('image_url=?'); params.push(image_url) }
    if (link_url !== undefined) { fields.push('link_url=?'); params.push(link_url) }
    if (sort_order !== undefined) { fields.push('sort_order=?'); params.push(sort_order) }
    if (is_active !== undefined) { fields.push('is_active=?'); params.push(is_active ? 1 : 0) }
    if (fields.length === 0) return res.json({ code: 0, message: '无字段更新' })
    params.push(req.params.id)
    await pool.query(`UPDATE portal_banners SET ${fields.join(', ')} WHERE id=?`, params)
    res.json({ code: 0 })
  } catch (e) { next(e) }
})
adminRouter.delete('/banners/:id', writePerm, async (req, res, next) => {
  try {
    await pool.query('DELETE FROM portal_banners WHERE id = ?', [req.params.id])
    res.json({ code: 0 })
  } catch (e) { next(e) }
})

// 图表 CRUD
adminRouter.post('/charts', writePerm, async (req, res, next) => {
  try {
    const { slot, title, chart_type, source_url, config_json, data_json, is_active } = req.body
    const [r] = await pool.query(
      `INSERT INTO portal_charts (slot, title, chart_type, source_url, config_json, data_json, is_active)
       VALUES (?,?,?,?,?,?,?)`,
      [slot, title, chart_type || 'column', source_url || '', JSON.stringify(config_json || {}), JSON.stringify(data_json || {}), is_active !== false ? 1 : 0]
    )
    res.json({ code: 0, data: { id: r.insertId } })
  } catch (e) { next(e) }
})
adminRouter.put('/charts/:id', writePerm, async (req, res, next) => {
  try {
    const { slot, title, chart_type, source_url, config_json, data_json, is_active } = req.body
    const fields = []
    const params = []
    if (slot !== undefined) { fields.push('slot=?'); params.push(slot) }
    if (title !== undefined) { fields.push('title=?'); params.push(title) }
    if (chart_type !== undefined) { fields.push('chart_type=?'); params.push(chart_type) }
    if (source_url !== undefined) { fields.push('source_url=?'); params.push(source_url) }
    if (config_json !== undefined) { fields.push('config_json=?'); params.push(JSON.stringify(config_json)) }
    if (data_json !== undefined) { fields.push('data_json=?'); params.push(JSON.stringify(data_json)) }
    if (is_active !== undefined) { fields.push('is_active=?'); params.push(is_active ? 1 : 0) }
    if (fields.length === 0) return res.json({ code: 0, message: '无字段更新' })
    params.push(req.params.id)
    await pool.query(`UPDATE portal_charts SET ${fields.join(', ')} WHERE id=?`, params)
    res.json({ code: 0 })
  } catch (e) { next(e) }
})
adminRouter.delete('/charts/:id', writePerm, async (req, res, next) => {
  try {
    await pool.query('DELETE FROM portal_charts WHERE id = ?', [req.params.id])
    res.json({ code: 0 })
  } catch (e) { next(e) }
})

// 友链 CRUD
adminRouter.post('/links', writePerm, async (req, res, next) => {
  try {
    const { group_name, title, url, logo_url, sort_order, is_active } = req.body
    const [r] = await pool.query(
      `INSERT INTO portal_friend_links (group_name, title, url, logo_url, sort_order, is_active)
       VALUES (?,?,?,?,?,?)`,
      [group_name, title, url, logo_url || '', sort_order || 0, is_active !== false ? 1 : 0]
    )
    res.json({ code: 0, data: { id: r.insertId } })
  } catch (e) { next(e) }
})
adminRouter.put('/links/:id', writePerm, async (req, res, next) => {
  try {
    const { group_name, title, url, logo_url, sort_order, is_active } = req.body
    const fields = []
    const params = []
    if (group_name !== undefined) { fields.push('group_name=?'); params.push(group_name) }
    if (title !== undefined) { fields.push('title=?'); params.push(title) }
    if (url !== undefined) { fields.push('url=?'); params.push(url) }
    if (logo_url !== undefined) { fields.push('logo_url=?'); params.push(logo_url) }
    if (sort_order !== undefined) { fields.push('sort_order=?'); params.push(sort_order) }
    if (is_active !== undefined) { fields.push('is_active=?'); params.push(is_active ? 1 : 0) }
    if (fields.length === 0) return res.json({ code: 0, message: '无字段更新' })
    params.push(req.params.id)
    await pool.query(`UPDATE portal_friend_links SET ${fields.join(', ')} WHERE id=?`, params)
    res.json({ code: 0 })
  } catch (e) { next(e) }
})
adminRouter.delete('/links/:id', writePerm, async (req, res, next) => {
  try {
    await pool.query('DELETE FROM portal_friend_links WHERE id = ?', [req.params.id])
    res.json({ code: 0 })
  } catch (e) { next(e) }
})

router.use("/admin", adminRouter)

export default router
