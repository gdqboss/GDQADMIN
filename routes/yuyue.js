import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { ROLES } from '../middleware/rbac.js'

const router = Router()

// 角色检查中间件
function requireRole(req, res, next) {
  const allowed = [ROLES.ADMIN, ROLES.MANAGER, ROLES.SUPERADMIN, ROLES.OPERATOR]
  if (!req.user || !allowed.includes(req.user.role)) {
    return res.status(403).json({ code: 403, message: '无权限访问' })
  }
  next()
}

// 生成预约单号
function generateYuyueNo() {
  const date = new Date()
  const dateStr = date.toISOString().slice(0, 10).replace(/-/g, '')
  const timeStr = String(date.getHours()).padStart(2, '0') + String(date.getMinutes()).padStart(2, '0') + String(date.getSeconds()).padStart(2, '0')
  const random = String(Math.floor(Math.random() * 10000)).padStart(4, '0')
  return `YY${dateStr}${timeStr}${random}`
}

// GET /api/yuyue - 预约列表（分页+多条件筛选）
router.get('/', requireRole, async (req, res, next) => {
  try {
    const { status, keyword, date_start, date_end, store_id, member_id } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (status) {
      where += ' AND y.status = ?'
      params.push(status)
      countParams.push(status)
    }
    if (member_id) {
      where += ' AND y.member_id = ?'
      params.push(member_id)
      countParams.push(member_id)
    }
    if (store_id) {
      where += ' AND y.store_id = ?'
      params.push(store_id)
      countParams.push(store_id)
    }
    if (date_start) {
      where += ' AND y.yuyue_date >= ?'
      params.push(date_start)
      countParams.push(date_start)
    }
    if (date_end) {
      where += ' AND y.yuyue_date <= ?'
      params.push(date_end)
      countParams.push(date_end)
    }
    if (keyword) {
      where += ' AND (y.yuyue_no LIKE ? OR y.member_name LIKE ? OR y.member_phone LIKE ? OR y.staff_name LIKE ?)'
      const kw = `%${keyword}%`
      params.push(kw, kw, kw, kw)
      countParams.push(kw, kw, kw, kw)
    }

    const sql = `
      SELECT y.*
      FROM yuyue y
      ${where}
      ORDER BY y.created_at DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(*) as total FROM yuyue y ${where}`

    const [[{ total }]] = await pool.query(countSql, countParams)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/yuyue/:id - 预约详情
router.get('/:id', requireRole, async (req, res, next) => {
  try {
    const [[yuyue]] = await pool.query('SELECT * FROM yuyue WHERE id = ?', [req.params.id])
    if (!yuyue) {
      return res.status(404).json({ code: 404, message: '预约不存在' })
    }
    res.json({ code: 0, data: yuyue, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/yuyue/:id/status - 状态流转
router.put('/:id/status', requireRole, async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { action } = req.body
    if (!action) return res.status(400).json({ code: 400, message: 'action 必填' })

    const allowedRoles = [ROLES.ADMIN, ROLES.MANAGER, ROLES.SUPERADMIN, ROLES.OPERATOR]
    if (!allowedRoles.includes(req.user.role)) {
      return res.status(403).json({ code: 403, message: '无权限操作' })
    }

    await conn.beginTransaction()

    const [[yuyue]] = await conn.query('SELECT * FROM yuyue WHERE id = ?', [req.params.id])
    if (!yuyue) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '预约不存在' })
    }

    let newStatus = null
    let setFields = []

    if (action === 'confirm') {
      // 待确认 -> 已确认
      if (yuyue.status !== 'pending') {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: '只有待确认状态可以确认' })
      }
      newStatus = 'confirmed'
      setFields.push("status = 'confirmed'", 'confirmed_at = NOW()')
    } else if (action === 'complete') {
      // 已确认 -> 已完成
      if (yuyue.status !== 'confirmed') {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: '只有已确认状态可以完成' })
      }
      newStatus = 'completed'
      setFields.push("status = 'completed'", 'completed_at = NOW()')
    } else if (action === 'cancel') {
      // 待确认/已确认 -> 已取消
      if (!['pending', 'confirmed'].includes(yuyue.status)) {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: '只有待确认或已确认状态可以取消' })
      }
      newStatus = 'cancelled'
      setFields.push("status = 'cancelled'", 'cancelled_at = NOW()')
    } else if (action === 'no_show') {
      // 已确认 -> 未到店
      if (yuyue.status !== 'confirmed') {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: '只有已确认状态可以标记未到店' })
      }
      newStatus = 'no_show'
      setFields.push("status = 'no_show'")
    } else {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '无效的 action' })
    }

    await conn.query(`UPDATE yuyue SET ${setFields.join(', ')} WHERE id = ?`, [req.params.id])
    await conn.commit()

    const [[updated]] = await pool.query('SELECT * FROM yuyue WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: updated, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// POST /api/yuyue - 创建预约
router.post('/', requireRole, async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const allowedRoles = [ROLES.ADMIN, ROLES.MANAGER, ROLES.SUPERADMIN, ROLES.OPERATOR]
    if (!allowedRoles.includes(req.user.role)) {
      return res.status(403).json({ code: 403, message: '无权限创建预约' })
    }

    const { member_id, member_name, member_phone, service_type, service_item, store_id, store_name, staff_id, staff_name, yuyue_date, yuyue_time, duration = 60, remark } = req.body

    if (!yuyue_date || !yuyue_time) {
      return res.status(400).json({ code: 400, message: '预约日期和时间必填' })
    }

    const yuyue_no = generateYuyueNo()

    const [result] = await conn.query(
      `INSERT INTO yuyue (yuyue_no, member_id, member_name, member_phone, service_type, service_item, store_id, store_name, staff_id, staff_name, yuyue_date, yuyue_time, duration, status, remark)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)`,
      [yuyue_no, member_id || null, member_name || null, member_phone || null, service_type || null, service_item || null, store_id || null, store_name || null, staff_id || null, staff_name || null, yuyue_date, yuyue_time, duration, remark || null]
    )

    await conn.commit()

    const [[yuyue]] = await pool.query('SELECT * FROM yuyue WHERE id = ?', [result.insertId])

    res.json({ code: 0, data: yuyue, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// PUT /api/yuyue/:id - 更新预约
router.put('/:id', requireRole, async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const allowedRoles = [ROLES.ADMIN, ROLES.MANAGER, ROLES.SUPERADMIN, ROLES.OPERATOR]
    if (!allowedRoles.includes(req.user.role)) {
      return res.status(403).json({ code: 403, message: '无权限更新预约' })
    }

    const { member_name, member_phone, service_type, service_item, store_id, store_name, staff_id, staff_name, yuyue_date, yuyue_time, duration, remark, admin_remark } = req.body

    const [[yuyue]] = await conn.query('SELECT * FROM yuyue WHERE id = ?', [req.params.id])
    if (!yuyue) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '预约不存在' })
    }

    if (yuyue.status === 'completed' || yuyue.status === 'cancelled' || yuyue.status === 'no_show') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '已完成的预约不能修改' })
    }

    const updates = []
    const values = []

    if (member_name !== undefined) { updates.push('member_name = ?'); values.push(member_name) }
    if (member_phone !== undefined) { updates.push('member_phone = ?'); values.push(member_phone) }
    if (service_type !== undefined) { updates.push('service_type = ?'); values.push(service_type) }
    if (service_item !== undefined) { updates.push('service_item = ?'); values.push(service_item) }
    if (store_id !== undefined) { updates.push('store_id = ?'); values.push(store_id) }
    if (store_name !== undefined) { updates.push('store_name = ?'); values.push(store_name) }
    if (staff_id !== undefined) { updates.push('staff_id = ?'); values.push(staff_id) }
    if (staff_name !== undefined) { updates.push('staff_name = ?'); values.push(staff_name) }
    if (yuyue_date !== undefined) { updates.push('yuyue_date = ?'); values.push(yuyue_date) }
    if (yuyue_time !== undefined) { updates.push('yuyue_time = ?'); values.push(yuyue_time) }
    if (duration !== undefined) { updates.push('duration = ?'); values.push(duration) }
    if (remark !== undefined) { updates.push('remark = ?'); values.push(remark) }
    if (admin_remark !== undefined) { updates.push('admin_remark = ?'); values.push(admin_remark) }

    if (updates.length === 0) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '没有要更新的字段' })
    }

    values.push(req.params.id)
    await conn.query(`UPDATE yuyue SET ${updates.join(', ')} WHERE id = ?`, values)
    await conn.commit()

    const [[updated]] = await pool.query('SELECT * FROM yuyue WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: updated, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// DELETE /api/yuyue/:id - 删除预约（仅 pending 可操作）
router.delete('/:id', requireRole, async (req, res, next) => {
  try {
    const [[yuyue]] = await pool.query('SELECT * FROM yuyue WHERE id = ?', [req.params.id])
    if (!yuyue) {
      return res.status(404).json({ code: 404, message: '预约不存在' })
    }
    if (yuyue.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '只有待确认状态的预约可以删除' })
    }
    await pool.query('DELETE FROM yuyue WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: '预约已删除' })
  } catch (err) { next(err) }
})

export default router