import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requirePermission } from '../middleware/rbac.js'
import { PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

// 工单类型 → 中文映射
const TYPE_LABELS = {
  it: 'IT支持',
  express: '快递',
  moving: '搬迁',
  cleaning: '保洁',
  parking: '停车',
  repair: '维修',
  other: '其他'
}

// 状态 → 徽章颜色映射
const STATUS_BADGE = {
  open:       { label: '待处理', color: '#F59E0B' },
  assigned:   { label: '已分配', color: '#6366F1' },
  processing: { label: '进行中', color: '#7E53FF' },
  completed:  { label: '已完成', color: '#22C55E' },
  cancelled:  { label: '已取消', color: '#9CA3AF' }
}

// SLA 时长（小时）
const SLA_HOURS = { urgent: 2, high: 8, medium: 24, low: 72 }

/**
 * GET /api/butler-orders
 * query: user_id(只看自己), status, type, limit
 */
router.get('/', requirePermission(PERMISSIONS.BUTLER_ORDERS_READ), async (req, res, next) => {
  try {
    const { user_id, status, type, limit = 20 } = req.query
    const where = ['1=1']
    const params = []
    if (user_id) { where.push('user_id = ?'); params.push(parseInt(user_id)) }
    if (status)  { where.push('status = ?');  params.push(status) }
    if (type)    { where.push('type = ?');    params.push(type) }

    const [rows] = await pool.query(
      `SELECT * FROM hqh5_butler_services
       WHERE ${where.join(' AND ')}
       ORDER BY created_at DESC
       LIMIT ?`,
      [...params, parseInt(limit)]
    )

    // 转为前端友好的结构 (附加 badge / label)
    const list = rows.map(r => ({
      ...r,
      type_label:   TYPE_LABELS[r.type]    || r.type,
      status_label: STATUS_BADGE[r.status]?.label   || r.status,
      status_color: STATUS_BADGE[r.status]?.color   || '#6B7280'
    }))
    res.json({ code: 0, data: list, message: 'ok' })
  } catch (e) { next(e) }
})

/**
 * GET /api/butler-orders/:id
 */
router.get('/:id', requirePermission(PERMISSIONS.BUTLER_ORDERS_READ), async (req, res, next) => {
  try {
    const [[row]] = await pool.query(
      'SELECT * FROM hqh5_butler_services WHERE id = ?',
      [req.params.id]
    )
    if (!row) return res.status(404).json({ code: 404, message: '工单不存在' })
    res.json({ code: 0, data: {
      ...row,
      type_label:   TYPE_LABELS[row.type] || row.type,
      status_label: STATUS_BADGE[row.status]?.label || row.status,
      status_color: STATUS_BADGE[row.status]?.color || '#6B7280'
    }, message: 'ok' })
  } catch (e) { next(e) }
})

/**
 * POST /api/butler-orders
 * body: { user_id, user_name, type, title, description, location, priority }
 */
router.post('/', requirePermission(PERMISSIONS.BUTLER_ORDERS_WRITE), async (req, res, next) => {
  try {
    const { user_id, user_name, type, title, description, location, priority } = req.body
    if (!user_id || !type || !title) {
      return res.status(400).json({ code: 400, message: 'user_id / type / title 必填' })
    }
    const p = priority || 'medium'
    const slaHours = SLA_HOURS[p] || 24
    const deadline = new Date(Date.now() + slaHours * 3600 * 1000)
    const [result] = await pool.query(
      `INSERT INTO hqh5_butler_services
       (user_id, user_name, type, title, description, location, priority, sla_deadline)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [user_id, user_name || '', type, title, description || '', location || '', p, deadline]
    )
    res.json({
      code: 0,
      data: { id: result.insertId, sla_deadline: deadline },
      message: `工单创建成功，SLA 截止 ${deadline.toLocaleString('zh-CN')}`
    })
  } catch (e) { next(e) }
})

/**
 * PUT /api/butler-orders/:id/assign
 * body: { assigned_to }
 */
router.put('/:id/assign', requirePermission(PERMISSIONS.BUTLER_ORDERS_WRITE), async (req, res, next) => {
  try {
    const { assigned_to } = req.body
    if (!assigned_to) return res.status(400).json({ code: 400, message: 'assigned_to 必填' })
    await pool.query(
      'UPDATE hqh5_butler_services SET assigned_to=?, status="assigned" WHERE id=?',
      [assigned_to, req.params.id]
    )
    res.json({ code: 0, message: `已分配给 ${assigned_to}` })
  } catch (e) { next(e) }
})

/**
 * PUT /api/butler-orders/:id/complete
 * body: { reply }
 */
router.put('/:id/complete', requirePermission(PERMISSIONS.BUTLER_ORDERS_WRITE), async (req, res, next) => {
  try {
    const { reply } = req.body
    await pool.query(
      'UPDATE hqh5_butler_services SET status="completed", completed_at=NOW() WHERE id=?',
      [req.params.id]
    )
    // 可选: 写回复到 hqh5_messages 或 audit 表 (此处简化)
    res.json({ code: 0, message: reply ? `已完成 · ${reply}` : '已完成' })
  } catch (e) { next(e) }
})

/**
 * DELETE /api/butler-orders/:id
 */
router.delete('/:id', requirePermission(PERMISSIONS.BUTLER_ORDERS_DELETE), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM hqh5_butler_services WHERE id=?', [req.params.id])
    res.json({ code: 0, message: '已删除' })
  } catch (e) { next(e) }
})

export default router
