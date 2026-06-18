import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'

const router = Router()

// GET /api/aftersales — 分页列表
router.get('/', async (req, res, next) => {
  try {
    const { status, assigned_to, product_id, date_start, date_end, keyword, type, priority } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (status) { where += ' AND a.status = ?'; params.push(status); countParams.push(status) }
    if (type) { where += ' AND a.type = ?'; params.push(type); countParams.push(type) }
    if (priority) { where += ' AND a.priority = ?'; params.push(priority); countParams.push(priority) }
    if (assigned_to) { where += ' AND a.assigned_to = ?'; params.push(assigned_to); countParams.push(assigned_to) }
    if (product_id) { where += ' AND a.product_id = ?'; params.push(product_id); countParams.push(product_id) }
    if (date_start) { where += ' AND DATE(a.created_at) >= ?'; params.push(date_start); countParams.push(date_start) }
    if (date_end) { where += ' AND DATE(a.created_at) <= ?'; params.push(date_end); countParams.push(date_end) }
    if (keyword) {
      where += ' AND (h.phone LIKE ? OR h.name LIKE ? OR a.issue LIKE ? OR q.code LIKE ? OR a.ticket_no LIKE ?)'
      const kw = `%${keyword}%`
      params.push(kw, kw, kw, kw, kw); countParams.push(kw, kw, kw, kw, kw)
    }

    const sql = `
      SELECT a.*,
        q.code as qr_code,
        p.name as product_name, p.spec as product_spec,
        h.name as h5_name, h.phone as h5_phone,
        u.name as assigned_to_name,
        sys.name as handler_name
      FROM after_sale_records a
      LEFT JOIN qrcodes q ON a.qrcode_id = q.id
      LEFT JOIN products p ON a.product_id = p.id
      LEFT JOIN h5_users h ON a.h5_user_id = h.id
      LEFT JOIN users u ON a.assigned_to = u.id
      LEFT JOIN users sys ON a.handler = sys.name
      ${where}
      ORDER BY a.created_at DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `
      SELECT COUNT(*) as total FROM after_sale_records a
      LEFT JOIN qrcodes q ON a.qrcode_id = q.id
      LEFT JOIN products p ON a.product_id = p.id
      LEFT JOIN h5_users h ON a.h5_user_id = h.id
      ${where}
    `

    params.push(size, (page - 1) * size)
    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/aftersales/stats — 状态统计
router.get('/stats', async (req, res, next) => {
  try {
    const [[{ total }]] = await pool.query('SELECT COUNT(*) as total FROM after_sale_records')
    const [[{ processing }]] = await pool.query("SELECT COUNT(*) as processing FROM after_sale_records WHERE status = 'processing'")
    const [[{ resolved }]] = await pool.query("SELECT COUNT(*) as resolved FROM after_sale_records WHERE status = 'resolved'")
    const [[{ rejected }]] = await pool.query("SELECT COUNT(*) as rejected FROM after_sale_records WHERE status = 'rejected'")
    res.json({ code: 0, data: { total, processing, resolved, rejected }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/aftersales/:id
router.get('/:id', async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT a.*,
        q.code as qr_code, q.status as qr_status,
        p.name as product_name, p.spec as product_spec, p.image_main,
        h.name as h5_name, h.phone as h5_phone,
        u.name as assigned_to_name, u.email as assigned_to_email
      FROM after_sale_records a
      LEFT JOIN qrcodes q ON a.qrcode_id = q.id
      LEFT JOIN products p ON a.product_id = p.id
      LEFT JOIN h5_users h ON a.h5_user_id = h.id
      LEFT JOIN users u ON a.assigned_to = u.id
      WHERE a.id = ?
    `, [req.params.id])
    if (!rows.length) return res.status(404).json({ code: 404, message: '记录不存在' })
    res.json({ code: 0, data: rows[0], message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/aftersales/:id — 更新状态/指派/备注
router.put('/:id', async (req, res, next) => {
  try {
    const { status, assigned_to, handler_note, priority, channel_qrcodes } = req.body
    const [[record]] = await pool.query('SELECT * FROM after_sale_records WHERE id = ?', [req.params.id])
    if (!record) return res.status(404).json({ code: 404, message: '记录不存在' })

    const updates = []
    const params = []

    if (status) { updates.push('status = ?'); params.push(status) }
    if (assigned_to !== undefined) { updates.push('assigned_to = ?'); params.push(assigned_to || null) }
    if (handler_note !== undefined) { updates.push('handler_note = ?'); params.push(handler_note) }
    if (priority) { updates.push('priority = ?'); params.push(priority) }
    if (channel_qrcodes !== undefined) { updates.push('channel_qrcodes = ?'); params.push(JSON.stringify(channel_qrcodes)) }

    // 首次指派/处理时记录响应时间
    if (assigned_to && !record.responded_at) {
      updates.push('responded_at = NOW()')
    }

    // 解决/拒绝时记录解决时间 + 回退二维码状态
    if ((status === 'resolved' || status === 'rejected') && !record.resolved_at) {
      updates.push('resolved_at = NOW()')
      updates.push('handler = ?'); params.push(req.user.name)
      // 回退二维码状态
      const rollbackStatus = record.previous_status || 'sold'
      await pool.query('UPDATE qrcodes SET status = ? WHERE id = ?', [rollbackStatus, record.qrcode_id])
    }

    if (!updates.length) return res.status(400).json({ code: 400, message: '无更新内容' })
    params.push(req.params.id)
    await pool.query(`UPDATE after_sale_records SET ${updates.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE /api/aftersales/:id — 删除售后记录
router.delete('/:id', async (req, res, next) => {
  try {
    const [[record]] = await pool.query('SELECT id FROM after_sale_records WHERE id = ?', [req.params.id])
    if (!record) return res.status(404).json({ code: 404, message: '记录不存在' })

    // 先删除关联的聊天消息（外键约束）
    await pool.query('DELETE FROM aftersale_messages WHERE aftersale_id = ?', [req.params.id])
    // 再删除售后记录本身
    await pool.query('DELETE FROM after_sale_records WHERE id = ?', [req.params.id])

    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

// ─── Chat APIs ──────────────────────────────────────────────────────────────

// GET /api/aftersales/:id/messages — 获取工单聊天记录
router.get('/:id/messages', async (req, res, next) => {
  try {
    const [messages] = await pool.query(
      'SELECT id, sender_type, sender_id, sender_name, content, created_at FROM aftersale_messages WHERE aftersale_id = ? ORDER BY created_at ASC',
      [req.params.id]
    )
    res.json({ code: 0, data: messages, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/aftersales/:id/messages — 客服回复消息
router.post('/:id/messages', async (req, res, next) => {
  try {
    const { content } = req.body
    if (!content || !content.trim()) return res.status(400).json({ code: 400, message: '消息内容不能为空' })

    const [[record]] = await pool.query('SELECT id FROM after_sale_records WHERE id = ?', [req.params.id])
    if (!record) return res.status(404).json({ code: 404, message: '工单不存在' })

    const [result] = await pool.query(
      'INSERT INTO aftersale_messages (aftersale_id, sender_type, sender_id, sender_name, content) VALUES (?,?,?,?,?)',
      [req.params.id, 'staff', req.user.id, req.user.name, content.trim()]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: 'ok' })
  } catch (err) { next(err) }
})

export default router
