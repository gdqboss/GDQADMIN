/**
 * 协会会员名片 (association_cards)
 * - GET /api/association/cards - 名片墙 (公开 + is_visible=1)
 * - GET /api/association/cards/admin - admin 列表 (含隐藏)
 * - GET /api/association/cards/:id - 详情
 * - POST/PUT/DELETE
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

// 名片墙 (公开 + 隐藏电话/微信 - 只展示公司/职位/简介)
router.get('/', requireOptionalPermission(PERMISSIONS.ASSOCIATION_CARDS_READ), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const { keyword, card_level, industry } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE server_profile_id = ? AND is_visible = 1'
    const params = [sp]
    if (card_level) { where += ' AND card_level = ?'; params.push(card_level) }
    if (industry) { where += ' AND industry = ?'; params.push(industry) }
    if (keyword) { where += ' AND (name LIKE ? OR company LIKE ? OR title LIKE ?)'; const kw = `%${keyword}%`; params.push(kw, kw, kw) }

    const sql = `SELECT id, server_profile_id, member_id, name, avatar, title, company, industry, bio, interests, card_level, sort_order FROM association_cards ${where} ORDER BY sort_order ASC, id DESC LIMIT ? OFFSET ?`
    const countSql = `SELECT COUNT(*) as total FROM association_cards ${where}`
    const [[{ total }]] = await pool.query(countSql, params)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// admin 列表 (含隐藏 + 联系方式)
router.get('/admin', requirePermission(PERMISSIONS.ASSOCIATION_CARDS_WRITE), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const { keyword, is_visible } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE server_profile_id = ?'
    const params = [sp]
    if (is_visible !== undefined && is_visible !== '') { where += ' AND is_visible = ?'; params.push(Number(is_visible)) }
    if (keyword) { where += ' AND (name LIKE ? OR company LIKE ?)'; const kw = `%${keyword}%`; params.push(kw, kw) }

    const sql = `SELECT * FROM association_cards ${where} ORDER BY sort_order ASC, id DESC LIMIT ? OFFSET ?`
    const countSql = `SELECT COUNT(*) as total FROM association_cards ${where}`
    const [[{ total }]] = await pool.query(countSql, params)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// 详情 (公开版脱敏)
router.get('/:id', requireOptionalPermission(PERMISSIONS.ASSOCIATION_CARDS_READ), async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT * FROM association_cards WHERE id = ? AND is_visible = 1', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '名片不存在' })
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 新增
router.post('/', requirePermission(PERMISSIONS.ASSOCIATION_CARDS_WRITE), async (req, res, next) => {
  try {
    const sp = req.body.server_profile_id || 7
    const { member_id, name, avatar, title, company, industry, phone, email, wechat, bio, interests, card_level, is_visible, sort_order } = req.body
    if (!name) return res.status(400).json({ code: 400, message: '姓名必填' })
    const [result] = await pool.query(
      `INSERT INTO association_cards
        (server_profile_id, member_id, name, avatar, title, company, industry,
         phone, email, wechat, bio, interests, card_level, is_visible, sort_order)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [sp, member_id || null, name, avatar || '', title || '', company || '', industry || '',
       phone || '', email || '', wechat || '', bio || '', interests || '',
       card_level || 'member', is_visible ? 1 : 0, sort_order || 99]
    )
    const [[row]] = await pool.query('SELECT * FROM association_cards WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 更新
router.put('/:id', requirePermission(PERMISSIONS.ASSOCIATION_CARDS_WRITE), async (req, res, next) => {
  try {
    const [[exists]] = await pool.query('SELECT id FROM association_cards WHERE id = ?', [req.params.id])
    if (!exists) return res.status(404).json({ code: 404, message: '名片不存在' })
    const { name, avatar, title, company, industry, phone, email, wechat, bio, interests, card_level, is_visible, sort_order } = req.body
    await pool.query(
      `UPDATE association_cards SET
        name = COALESCE(?, name), avatar = COALESCE(?, avatar),
        title = COALESCE(?, title), company = COALESCE(?, company),
        industry = COALESCE(?, industry), phone = COALESCE(?, phone),
        email = COALESCE(?, email), wechat = COALESCE(?, wechat),
        bio = COALESCE(?, bio), interests = COALESCE(?, interests),
        card_level = COALESCE(?, card_level), is_visible = COALESCE(?, is_visible),
        sort_order = COALESCE(?, sort_order)
       WHERE id = ?`,
      [name, avatar, title, company, industry, phone, email, wechat, bio, interests, card_level, is_visible, sort_order, req.params.id]
    )
    const [[row]] = await pool.query('SELECT * FROM association_cards WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 删除
router.delete('/:id', requirePermission(PERMISSIONS.ASSOCIATION_CARDS_DELETE), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM association_cards WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

export default router