/**
 * 协会在线咨询 (inquiries)
 * - POST /api/association/inquiries - 公开提交 (无需登录, 限流 + honeypot)
 * - GET /api/association/inquiries/admin - admin 列表
 * - GET /api/association/inquiries/admin/stats - 统计
 * - GET /api/association/inquiries/:id - 详情 (admin)
 * - PUT /api/association/inquiries/:id - 回复/更新状态
 * - DELETE /api/association/inquiries/:id - 删除
 */
import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

function getServerProfileId(req) {
  return Number(req.query.server_profile_id || req.body.server_profile_id || 7)
}

// 简单邮箱校验
function isValidEmail(s) {
  return typeof s === 'string' && /^[\w.+-]+@[\w-]+\.[\w.-]+$/.test(s)
}

// 简单电话校验 (允许 + 区号 + 6-20 位)
function isValidPhone(s) {
  return typeof s === 'string' && /^\+?[\d\s-]{6,20}$/.test(s)
}

/**
 * 公开提交咨询
 * 速率限制由 index.js 挂载时附带 (apiLimiter + this router limiter)
 * honeypot: 必须传 website 字段 (留空)
 */
router.post('/', async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const { name, phone, email, subject, message, website } = req.body || {}

    // honeypot: 真人不填 website, bot 会填
    if (website) {
      return res.status(400).json({ code: 400, message: '提交失败' })
    }

    if (!name || typeof name !== 'string' || name.length > 100) {
      return res.status(400).json({ code: 400, message: '请填写姓名 (1-100 字符)' })
    }
    if (!message || typeof message !== 'string' || message.length < 5 || message.length > 5000) {
      return res.status(400).json({ code: 400, message: '咨询内容 5-5000 字符' })
    }
    if (email && !isValidEmail(email)) {
      return res.status(400).json({ code: 400, message: '邮箱格式错误' })
    }
    if (phone && !isValidPhone(phone)) {
      return res.status(400).json({ code: 400, message: '电话格式错误' })
    }

    // 记录 IP / UA / Referer (运营需要, 排查 spam)
    const ip = req.headers['x-forwarded-for']?.split(',')[0]?.trim() || req.ip || req.connection?.remoteAddress
    const ua = (req.headers['user-agent'] || '').slice(0, 500)
    const ref = (req.headers['referer'] || req.headers['referrer'] || '').slice(0, 500)

    const [result] = await pool.query(
      `INSERT INTO association_inquiries
        (server_profile_id, name, phone, email, subject, message, ip_address, user_agent, referer, status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')`,
      [sp, name.trim(), phone || null, email || null, (subject || '').slice(0, 255) || null, message.trim(), ip, ua, ref]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '提交成功, 学会会尽快回复' })
  } catch (err) { next(err) }
})

/**
 * Admin 列表
 * 支持 keyword (姓名/电话/邮箱/主题/内容) + status + priority + dateRange
 */
router.get('/admin', requirePermission(PERMISSIONS.ASSOCIATION_INQUIRIES_WRITE), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const { keyword, status, priority, date_from, date_to } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE server_profile_id = ?'
    const params = [sp]
    if (status) { where += ' AND status = ?'; params.push(status) }
    if (priority !== undefined && priority !== '') { where += ' AND priority = ?'; params.push(Number(priority)) }
    if (date_from) { where += ' AND created_at >= ?'; params.push(date_from) }
    if (date_to) { where += ' AND created_at <= ?'; params.push(date_to) }
    if (keyword) {
      where += ' AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)'
      const kw = `%${keyword}%`
      params.push(kw, kw, kw, kw, kw)
    }

    const sql = `SELECT * FROM association_inquiries ${where} ORDER BY priority DESC, created_at DESC LIMIT ? OFFSET ?`
    const countSql = `SELECT COUNT(*) as total FROM association_inquiries ${where}`
    const [[{ total }]] = await pool.query(countSql, params)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

/**
 * Admin 统计
 */
router.get('/admin/stats', requirePermission(PERMISSIONS.ASSOCIATION_INQUIRIES_READ), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const [rows] = await pool.query(
      `SELECT
         COUNT(*) AS total,
         SUM(CASE WHEN status='new' THEN 1 ELSE 0 END) AS new_count,
         SUM(CASE WHEN status='read' THEN 1 ELSE 0 END) AS read_count,
         SUM(CASE WHEN status='replied' THEN 1 ELSE 0 END) AS replied_count,
         SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) AS closed_count,
         SUM(CASE WHEN status='spam' THEN 1 ELSE 0 END) AS spam_count,
         SUM(CASE WHEN priority=1 THEN 1 ELSE 0 END) AS priority_count,
         SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS last_7_days,
         SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS last_30_days
       FROM association_inquiries WHERE server_profile_id = ?`,
      [sp]
    )
    res.json({ code: 0, data: rows[0], message: 'ok' })
  } catch (err) { next(err) }
})

/**
 * 详情
 */
router.get('/:id', requirePermission(PERMISSIONS.ASSOCIATION_INQUIRIES_READ), async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT * FROM association_inquiries WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '咨询不存在' })
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

/**
 * 更新 (回复 / 改状态 / 改优先级 / 分配 / 备注)
 */
router.put('/:id', requirePermission(PERMISSIONS.ASSOCIATION_INQUIRIES_WRITE), async (req, res, next) => {
  try {
    const [[exists]] = await pool.query('SELECT id FROM association_inquiries WHERE id = ?', [req.params.id])
    if (!exists) return res.status(404).json({ code: 404, message: '咨询不存在' })

    const { status, priority, reply_message, assigned_to, notes } = req.body || {}
    const userId = req.user?.id || null

    // 状态机: new -> read -> replied -> closed (或 spam)
    const validTransitions = {
      new: ['read', 'closed', 'spam'],
      read: ['replied', 'closed', 'spam'],
      replied: ['closed', 'read'],
      closed: ['read'],
      spam: ['new', 'closed']
    }

    // 先看当前状态
    const [[cur]] = await pool.query('SELECT status, replied_by, replied_at FROM association_inquiries WHERE id = ?', [req.params.id])
    if (status && status !== cur.status && !validTransitions[cur.status]?.includes(status)) {
      return res.status(400).json({ code: 400, message: `不能从 ${cur.status} 跳到 ${status}` })
    }

    const fields = []
    const params = []
    if (status !== undefined) { fields.push('status = ?'); params.push(status) }
    if (priority !== undefined) { fields.push('priority = ?'); params.push(priority ? 1 : 0) }
    if (assigned_to !== undefined) { fields.push('assigned_to = ?'); params.push(assigned_to || null) }
    if (notes !== undefined) { fields.push('notes = ?'); params.push(notes || null) }
    if (reply_message !== undefined && reply_message !== null && reply_message !== '') {
      fields.push('reply_message = ?')
      params.push(reply_message)
      fields.push('replied_at = NOW()')
      fields.push('replied_by = ?')
      params.push(userId)
      // 自动标记 replied
      if (!status) {
        fields.push('status = ?')
        params.push('replied')
      }
    }
    // 标记 read 自动 (从 new 进入 read)
    if (status === 'read' && cur.status === 'new') {
      // 不需要额外字段, status 已设置
    }
    if (fields.length === 0) {
      return res.status(400).json({ code: 400, message: '没有可更新字段' })
    }
    params.push(req.params.id)
    await pool.query(`UPDATE association_inquiries SET ${fields.join(', ')} WHERE id = ?`, params)

    const [[row]] = await pool.query('SELECT * FROM association_inquiries WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

/**
 * 删除
 */
router.delete('/:id', requirePermission(PERMISSIONS.ASSOCIATION_INQUIRIES_DELETE), async (req, res, next) => {
  try {
    const [result] = await pool.query('DELETE FROM association_inquiries WHERE id = ?', [req.params.id])
    if (result.affectedRows === 0) return res.status(404).json({ code: 404, message: '咨询不存在' })
    res.json({ code: 0, data: { id: Number(req.params.id) }, message: '已删除' })
  } catch (err) { next(err) }
})

export default router