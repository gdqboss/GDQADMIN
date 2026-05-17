import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { requireRole } from '../middleware/rbac.js'

const router = Router()

// JSON字段解析工具函数
function safeParse(str, defaultVal = {}) {
  if (!str) return defaultVal;
  try {
    return typeof str === 'object' ? str : JSON.parse(str);
  } catch (e) {
    return defaultVal;
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// DASHBOARD
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/oa/dashboard - OA dashboard statistics
router.get('/dashboard', async (req, res, next) => {
  try {
    const userId = req.user.id
    const today = new Date().toISOString().slice(0, 10)

    // Get today's attendance
    const [[attendance]] = await pool.query(
      'SELECT clock_in, clock_out FROM attendance WHERE user_id = ? AND date = ?',
      [userId, today]
    )

    // Get pending approvals count (approvals waiting for this user)
    const [[{ pending_approvals }]] = await pool.query(
      `SELECT COUNT(*) as pending_approvals FROM approvals a
       JOIN approval_steps s ON a.id = s.approval_id
       WHERE s.approver_id = ? AND s.status = 'pending' AND a.status = 'pending'`,
      [userId]
    )

    // Get my approvals count (approvals created by this user)
    const [[{ my_approvals }]] = await pool.query(
      'SELECT COUNT(*) as my_approvals FROM approvals WHERE applicant_id = ?',
      [userId]
    )

    // Get work logs count (this month)
    const firstDayOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10)
    const [[{ work_logs }]] = await pool.query(
      'SELECT COUNT(*) as work_logs FROM work_logs WHERE user_id = ? AND submit_date >= ?',
      [userId, firstDayOfMonth]
    )

    res.json({
      code: 0,
      data: {
        pending_approvals: pending_approvals || 0,
        my_approvals: my_approvals || 0,
        attendance: {
          clock_in: attendance?.clock_in || null,
          clock_out: attendance?.clock_out || null
        },
        work_logs: work_logs || 0
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// GET /api/oa/attendance/today-summary - Today's attendance summary (for managers)
router.get('/attendance/today-summary', async (req, res, next) => {
  try {
    const today = new Date().toISOString().slice(0, 10)

    // 应打卡 = require_attendance=1的员工
    // 实打卡 = 当天所有打了卡的（包括自由打卡的）
    // 迟到/早退/旷工 = 只统计必打卡员工
    const [[summary]] = await pool.query(`
      SELECT
        COUNT(DISTINCT CASE WHEN u.require_attendance = 1 THEN u.id END) as should_attend,
        COUNT(DISTINCT CASE WHEN u.require_attendance = 1 THEN u.id END) as total_employees,
        COUNT(DISTINCT CASE WHEN a.clock_in IS NOT NULL THEN a.user_id END) as checked_in,
        SUM(CASE WHEN u.require_attendance = 1 AND a.status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN u.require_attendance = 1 AND a.status = 'early' THEN 1 ELSE 0 END) as early_leave_count,
        SUM(CASE WHEN u.require_attendance = 1 AND a.status = 'absent' THEN 1 ELSE 0 END) as absent_count
      FROM users u
      LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
      WHERE u.status = 'active'
    `, [today])

    res.json({ code: 0, data: summary })
  } catch (err) { next(err) }
})

// GET /api/oa/work-logs/today-summary - Today's work log summary (for managers)
router.get('/work-logs/today-summary', async (req, res, next) => {
  try {
    const today = new Date().toISOString().slice(0, 10)

    const [[summary]] = await pool.query(`
      SELECT
        COUNT(DISTINCT u.id) as total_employees,
        COUNT(DISTINCT w.user_id) as submitted_count
      FROM users u
      LEFT JOIN work_logs w ON u.id = w.user_id AND w.submit_date = ?
      WHERE u.status = 'active' AND u.require_worklog = 1
    `, [today])

    res.json({ code: 0, data: summary })
  } catch (err) { next(err) }
})

// GET /api/oa/attendance/my-today - My today's attendance (for employees)
router.get('/attendance/my-today', async (req, res, next) => {
  try {
    const today = new Date().toISOString().slice(0, 10)
    const userId = req.user.id

    const [[record]] = await pool.query(
      'SELECT * FROM attendance WHERE user_id = ? AND date = ?',
      [userId, today]
    )

    res.json({ code: 0, data: record || null })
  } catch (err) { next(err) }
})

// GET /api/oa/work-logs/my-today - My today's work log (for employees)
router.get('/work-logs/my-today', async (req, res, next) => {
  try {
    const today = new Date().toISOString().slice(0, 10)
    const userId = req.user.id

    const [[log]] = await pool.query(
      'SELECT * FROM work_logs WHERE user_id = ? AND submit_date = ?',
      [userId, today]
    )

    res.json({ code: 0, data: log || null })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// AUTO CLOCK PERMISSION MODULE - 自动打卡权限管理
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/oa/auto-clock/permission - 获取我的自动打卡权限状态
router.get('/auto-clock/permission', async (req, res, next) => {
  try {
    const userId = req.user.id
    const [[permission]] = await pool.query(
      'SELECT * FROM auto_clock_permissions WHERE user_id = ?',
      [userId]
    )
    res.json({ 
      code: 0, 
      data: permission || { status: 'none', user_id: userId },
      message: 'ok' 
    })
  } catch (err) { next(err) }
})

// POST /api/oa/auto-clock/permission - 申请自动打卡权限
router.post('/auto-clock/permission', async (req, res, next) => {
  try {
    const userId = req.user.id
    const { reason, auto_clock_in = 1, auto_clock_out = 1 } = req.body
    
    // 检查是否已有申请
    const [[existing]] = await pool.query(
      'SELECT * FROM auto_clock_permissions WHERE user_id = ?',
      [userId]
    )
    
    if (existing) {
      if (existing.status === 'approved') {
        return res.json({ code: 0, message: '您已有自动打卡权限', data: existing })
      }
      if (existing.status === 'pending') {
        return res.status(400).json({ code: 400, message: '您的申请正在审批中，请等待' })
      }
      // 被拒绝后可以重新申请
      await pool.query(
        `UPDATE auto_clock_permissions SET status='pending', reason=?, auto_clock_in=?, auto_clock_out=?, 
         approved_by=NULL, approved_at=NULL, reject_reason=NULL WHERE user_id=?`,
        [reason, auto_clock_in, auto_clock_out, userId]
      )
    } else {
      await pool.query(
        `INSERT INTO auto_clock_permissions (user_id, reason, auto_clock_in, auto_clock_out) 
         VALUES (?, ?, ?, ?)`,
        [userId, reason, auto_clock_in, auto_clock_out]
      )
    }
    
    res.json({ code: 0, message: '申请已提交，请等待管理员审批' })
  } catch (err) { next(err) }
})

// GET /api/oa/auto-clock/permissions - 管理员查看所有申请列表
router.get('/auto-clock/permissions', requireRole('admin'), async (req, res, next) => {
  try {
    const { status } = req.query
    const { page, size } = parsePagination(req.query)
    
    let where = 'WHERE 1=1'
    const params = []
    
    if (status) {
      where += ' AND p.status = ?'
      params.push(status)
    }
    
    const [rows] = await pool.query(
      `SELECT p.*, u.name as user_name, u.email as user_email,
              a.name as approver_name
       FROM auto_clock_permissions p
       LEFT JOIN users u ON p.user_id = u.id
       LEFT JOIN users a ON p.approved_by = a.id
       ${where}
       ORDER BY p.created_at DESC
       LIMIT ? OFFSET ?`,
      [...params, size, (page - 1) * size]
    )
    
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM auto_clock_permissions p ${where}`,
      params
    )
    
    res.json({ 
      code: 0, 
      data: { list: rows, total, page, size },
      message: 'ok' 
    })
  } catch (err) { next(err) }
})

// PUT /api/oa/auto-clock/permission/:id - 审批自动打卡权限
router.put('/auto-clock/permission/:id', requireRole('admin'), async (req, res, next) => {
  try {
    const { id } = req.params
    const { status, reject_reason } = req.body
    const approverId = req.user.id
    
    if (!['approved', 'rejected'].includes(status)) {
      return res.status(400).json({ code: 400, message: '状态必须是 approved 或 rejected' })
    }
    
    const [[existing]] = await pool.query(
      'SELECT * FROM auto_clock_permissions WHERE id = ?',
      [id]
    )
    
    if (!existing) {
      return res.status(404).json({ code: 404, message: '申请不存在' })
    }
    
    if (existing.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '该申请已被处理' })
    }
    
    await pool.query(
      `UPDATE auto_clock_permissions SET status=?, approved_by=?, approved_at=NOW(), reject_reason=? WHERE id=?`,
      [status, approverId, reject_reason || null, id]
    )
    
    res.json({ code: 0, message: status === 'approved' ? '已批准自动打卡权限' : '已拒绝申请' })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// ATTENDANCE MODULE
// ═══════════════════════════════════════════════════════════════════════════════

// POST /api/oa/attendance/clock - Clock in/out with GPS
router.post('/attendance/clock', async (req, res, next) => {
  try {
    const { type, lat, lng, accuracy, device_info, ip, is_auto_clock = false } = req.body
    const userId = req.user.id
    const today = new Date().toISOString().slice(0, 10)
    const now = new Date()
    const timeStr = now.toTimeString().slice(0, 8)
    // 自动打卡时标记
    const autoClock = is_auto_clock ? 1 : 0

    if (!['in', 'out'].includes(type)) {
      return res.status(400).json({ code: 400, message: '打卡类型必须是 in 或 out' })
    }

    // 自动打卡时检查权限
    if (is_auto_clock) {
      const [[perm]] = await pool.query(
        `SELECT * FROM auto_clock_permissions WHERE user_id = ? AND status = 'approved'`,
        [userId]
      )
      if (!perm) {
        return res.status(403).json({ code: 403, message: '您没有自动打卡权限，请先申请' })
      }
      // 检查是上班还是下班权限
      if (type === 'in' && !perm.auto_clock_in) {
        return res.status(403).json({ code: 403, message: '您没有自动上班打卡权限' })
      }
      if (type === 'out' && !perm.auto_clock_out) {
        return res.status(403).json({ code: 403, message: '您没有自动下班打卡权限' })
      }
    }

    const [[existing]] = await pool.query(
      'SELECT * FROM attendance WHERE user_id = ? AND date = ?',
      [userId, today]
    )

    if (type === 'in') {
      if (existing) {
        return res.status(400).json({ code: 400, message: '今日已打卡上班' })
      }
      // 检查是否需要考勤（只有必打卡员工才算迟到）
      const [[user]] = await pool.query('SELECT require_attendance FROM users WHERE id = ?', [userId])
      const isRequired = user && user.require_attendance === 1
      // 只有必打卡员工才算迟到
      const status = isRequired ? (timeStr > '09:00:00' ? 'late' : 'normal') : 'normal'

      await pool.query(
        `INSERT INTO attendance (user_id, date, clock_in, status, location,
         gps_lat, gps_lng, gps_accuracy, device_info, ip_address, is_auto_clock)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)`,
        [userId, today, timeStr, status, req.body.location || null,
         lat || null, lng || null, accuracy || null, device_info || null, ip || null, autoClock]
      )

      res.json({ code: 0, data: { status, time: timeStr, is_auto_clock: autoClock }, message: '上班打卡成功' })
    } else {
      // Clock out
      if (!existing) {
        return res.status(400).json({ code: 400, message: '今日未打卡上班，无法打卡下班' })
      }
      if (existing.clock_out) {
        return res.status(400).json({ code: 400, message: '今日已打卡下班' })
      }

      // 只有必打卡员工才算早退
      const [[user]] = await pool.query('SELECT require_attendance FROM users WHERE id = ?', [userId])
      const isRequired = user && user.require_attendance === 1
      const status = isRequired ? (timeStr < '18:00:00' ? 'early' : existing.status) : 'normal'

      await pool.query(
        'UPDATE attendance SET clock_out = ?, status = ?, is_auto_clock = ? WHERE id = ?',
        [timeStr, status, autoClock, existing.id]
      )

      res.json({ code: 0, data: { status, time: timeStr, is_auto_clock: autoClock }, message: '下班打卡成功' })
    }
  } catch (err) { next(err) }
})

// GET /api/oa/attendance - Query attendance records
router.get('/attendance', async (req, res, next) => {
  try {
    const { user_id, date, start_date, end_date, status, department } = req.query
    const { page, size } = parsePagination(req.query)
    const currentUserId = req.user.id
    const currentUserRole = req.user.role

    let where = 'WHERE 1=1'
    const params = []

    // 权限控制：自己可见，上级可见，超级管理员全部可见
    if (currentUserRole === 'admin') {
      // 超级管理员可以查看所有人
      if (user_id) { where += ' AND a.user_id = ?'; params.push(user_id) }
    } else {
      // 查找当前用户的所有下级（递归）
      const [subordinates] = await pool.query(`
        WITH RECURSIVE subordinate_tree AS (
          SELECT id FROM users WHERE supervisor_id = ?
          UNION ALL
          SELECT u.id FROM users u
          INNER JOIN subordinate_tree st ON u.supervisor_id = st.id
        )
        SELECT id FROM subordinate_tree
      `, [currentUserId])

      const subordinateIds = subordinates.map(s => s.id)
      subordinateIds.push(currentUserId) // 包含自己

      if (user_id) {
        // 如果指定了user_id，检查是否有权限查看
        if (!subordinateIds.includes(parseInt(user_id))) {
          return res.status(403).json({ code: 403, message: '无权查看该用户的考勤记录' })
        }
        where += ' AND a.user_id = ?'
        params.push(user_id)
      } else {
        // 只能查看自己和下级的考勤
        where += ' AND a.user_id IN (?)'
        params.push(subordinateIds)
      }
    }

    // 日期筛选
    if (date) {
      where += ' AND a.date = ?'
      params.push(date)
    } else {
      // 如果没有指定日期范围，默认查询最近30天
      if (!start_date && !end_date) {
        const today = new Date().toISOString().slice(0, 10)
        const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10)
        where += ' AND a.date >= ? AND a.date <= ?'
        params.push(thirtyDaysAgo, today)
      }
    }

    if (start_date) { where += ' AND a.date >= ?'; params.push(start_date) }
    if (end_date) { where += ' AND a.date <= ?'; params.push(end_date) }
    if (status) { where += ' AND a.status = ?'; params.push(status) }

    const sql = `
      SELECT a.*, u.name as user_name, u.department
      FROM attendance a
      LEFT JOIN users u ON a.user_id = u.id
      ${where}
      ORDER BY a.date DESC, a.clock_in DESC
      LIMIT ? OFFSET ?
    `
    params.push(size, (page - 1) * size)

    const countSql = `SELECT COUNT(*) as total FROM attendance a LEFT JOIN users u ON a.user_id = u.id ${where}`
    const [[{ total }]] = await pool.query(countSql, params.slice(0, -2))
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/attendance/:id/explain - Submit abnormal reason
router.post('/attendance/:id/explain', async (req, res, next) => {
  try {
    const { reason } = req.body
    if (!reason || reason.trim() === '') {
      return res.status(400).json({ code: 400, message: '异常说明不能为空' })
    }

    const [[record]] = await pool.query(
      'SELECT * FROM attendance WHERE id = ? AND user_id = ?',
      [req.params.id, req.user.id]
    )

    if (!record) {
      return res.status(404).json({ code: 404, message: '考勤记录不存在或无权限' })
    }

    await pool.query(
      'UPDATE attendance SET abnormal_reason = ? WHERE id = ?',
      [reason, req.params.id]
    )

    res.json({ code: 0, data: null, message: '异常说明已提交' })
  } catch (err) { next(err) }
})

// PUT /api/oa/attendance/:id/approve - Approve abnormal attendance
router.put('/attendance/:id/approve', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { approved } = req.body
    const approverId = req.user.id
    const now = new Date()

    const [[record]] = await pool.query('SELECT * FROM attendance WHERE id = ?', [req.params.id])
    if (!record) {
      return res.status(404).json({ code: 404, message: '考勤记录不存在' })
    }

    if (approved) {
      await pool.query(
        'UPDATE attendance SET status = ?, approved_by = ?, approved_at = ? WHERE id = ?',
        ['normal', approverId, now, req.params.id]
      )
      res.json({ code: 0, data: null, message: '已批准异常考勤' })
    } else {
      await pool.query(
        'UPDATE attendance SET approved_by = ?, approved_at = ? WHERE id = ?',
        [approverId, now, req.params.id]
      )
      res.json({ code: 0, data: null, message: '已拒绝异常考勤' })
    }
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// WORK LOG MODULE
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/oa/work-log-templates - List templates
router.get('/work-log-templates', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT t.*, u.name as creator_name
       FROM work_log_templates t
       LEFT JOIN users u ON t.creator_id = u.id
       WHERE t.status = 'active'
       ORDER BY t.is_default DESC, t.created_at DESC`
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/work-log-templates - Create template
router.post('/work-log-templates', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { name, fields, is_default } = req.body
    if (!name || !Array.isArray(fields) || fields.length === 0) {
      return res.status(400).json({ code: 400, message: '模板名称和字段定义必填' })
    }

    const [result] = await pool.query(
      'INSERT INTO work_log_templates (name, creator_id, fields, is_default) VALUES (?,?,?,?)',
      [name, req.user.id, JSON.stringify(fields), is_default ? 1 : 0]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '模板创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/oa/work-log-templates/:id - Update template
router.put('/work-log-templates/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { id } = req.params;
    const { name, fields, is_default, status } = req.body;

    const updates = [];
    const params = [];

    if (name !== undefined) {
      updates.push('name = ?');
      params.push(name);
    }
    if (fields !== undefined) {
      if (!Array.isArray(fields)) {
        return res.status(400).json({ code: 400, message: 'fields must be an array' });
      }
      updates.push('fields = ?');
      params.push(JSON.stringify(fields));
    }
    if (is_default !== undefined) {
      updates.push('is_default = ?');
      params.push(is_default ? 1 : 0);
    }
    if (status !== undefined) {
      updates.push('status = ?');
      params.push(status);
    }
    if (req.body.description !== undefined) {
      updates.push('description = ?');
      params.push(req.body.description);
    }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: 'No fields to update' });
    }

    params.push(id);

    const [result] = await pool.query(
      'UPDATE work_log_templates SET ' + updates.join(', ') + ' WHERE id = ?',
      params
    );

    res.json({ code: 0, message: '模板更新成功' });
  } catch (err) { next(err) }
})

// DELETE /api/oa/work-log-templates/:id - Delete template
router.delete('/work-log-templates/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { id } = req.params;
    await pool.query('DELETE FROM work_log_templates WHERE id = ?', [id]);
    res.json({ code: 0, message: '模板删除成功' });
  } catch (err) { next(err) }
})

// POST /api/oa/work-logs - Submit work log
router.post('/work-logs', async (req, res, next) => {
  try {
    const userId = req.user.id
    const { template_id, content, recipients, date, submit_date,
            location, gps_lat, gps_lng, participants, attachments } = req.body

    if (!date) {
      return res.status(400).json({ code: 400, message: '日期必填' })
    }

    const logDate = new Date(submit_date || date)
    if (isNaN(logDate.getTime())) {
      return res.status(400).json({ code: 400, message: '日期格式错误' })
    }

    const [result] = await pool.query(
      `INSERT INTO work_logs
       (user_id, submit_date, template_id, content, recipients, status,
        location, gps_lat, gps_lng, participants, attachments)
       VALUES (?,?,?,?,?,?,?,?,?,?,?)`,
      [
        userId,
        logDate,
        template_id || null,
        typeof content === "string" ? content : JSON.stringify(content),
        recipients ? JSON.stringify(recipients) : null,
        'submitted',
        location || null,
        gps_lat || null,
        gps_lng || null,
        participants ? JSON.stringify(participants) : null,
        attachments ? JSON.stringify(attachments) : null
      ]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '日志提交成功' })
  } catch (err) {
    next(err)
  }
})

// GET /api/oa/work-logs - Query logs (my logs / received logs / subordinate logs)
router.get('/work-logs', async (req, res, next) => {
  try {
    const { type, user_id, date, start_date, end_date } = req.query
    const { page, size } = parsePagination(req.query)
    const currentUserId = req.user.id

    let sql = `SELECT w.*, u.name as user_name, u.department, u.department_id, d.name as department_name, t.name as template_name FROM work_logs w LEFT JOIN users u ON w.user_id = u.id LEFT JOIN departments d ON u.department_id = d.id LEFT JOIN work_log_templates t ON w.template_id = t.id WHERE 1=1`
    const params = []

    if (type === 'received') {
      // Logs sent to me
      sql += ' AND JSON_CONTAINS(COALESCE(w.recipients, JSON_ARRAY()), ?)' 
      params.push(JSON.stringify(currentUserId))
    } else if (type === 'all') {
      // All logs - for admin/manager only (no additional filter, return all)
      const currentUserRole = req.user.role
      if (currentUserRole !== 'admin' && currentUserRole !== 'manager') {
        // Non-admin/manager can only see their own logs
        sql += ' AND w.user_id = ?'
        params.push(currentUserId)
      }
      // admin/manager sees all logs - no user_id filter
    } else if (type === 'subordinate') {
      // Subordinate logs - check if current user is a leader
      // Method 1: Check if user is department manager
      const [[currentUser]] = await pool.query(
        `SELECT u.*, d.manager_id, jl.level as job_level
         FROM users u
         LEFT JOIN departments d ON u.department_id = d.id
         LEFT JOIN job_levels jl ON u.job_level_id = jl.id
         WHERE u.id = ?`,
        [currentUserId]
      )

      if (!currentUser) {
        return res.status(403).json({ code: 403, message: '用户不存在' })
      }

      // Get subordinate user IDs
      const subordinateIds = []

      // If user is department manager, get all users in managed departments
      const [managedDepts] = await pool.query(
        'SELECT id FROM departments WHERE manager_id = ?',
        [currentUserId]
      )

      if (managedDepts.length > 0) {
        const deptIds = managedDepts.map(d => d.id)
        const [subordinates] = await pool.query(
          'SELECT id FROM users WHERE department_id IN (?) AND id != ?',
          [deptIds, currentUserId]
        )
        subordinateIds.push(...subordinates.map(s => s.id))
      }

      // Also check by job level (higher level can see lower level)
      if (currentUser.job_level && currentUser.job_level > 1) {
        const [lowerLevelUsers] = await pool.query(
          `SELECT u.id FROM users u
           LEFT JOIN job_levels jl ON u.job_level_id = jl.id
           WHERE jl.level < ? AND u.id != ?`,
          [currentUser.job_level, currentUserId]
        )
        subordinateIds.push(...lowerLevelUsers.map(u => u.id))
      }

      if (subordinateIds.length === 0) {
        // No subordinates, return empty
        return res.json({ code: 0, data: { list: [], total: 0, page, size }, message: 'ok' })
      }

      // Remove duplicates
      const uniqueSubordinateIds = [...new Set(subordinateIds)]
      sql += ' AND w.user_id IN (?)'
      params.push(uniqueSubordinateIds)
    } else {
      // My logs (default)
      sql += ' AND w.user_id = ?'
      params.push(user_id || currentUserId)
    }

    if (date) {
      sql += ' AND w.submit_date = ?'
      params.push(date)
    }
    if (start_date) {
      sql += ' AND w.date >= ?'
      params.push(start_date)
    }
    if (end_date) {
      sql += ' AND w.date <= ?'
      params.push(end_date)
    }

    // Build count query
    const countSql = sql.replace(/SELECT w\.\*, u\.name as user_name.*?FROM/, 'SELECT COUNT(*) as total FROM')
    const countResult = await pool.query(countSql, params)
    const total = countResult[0]?.[0]?.total || 0

    sql += ' ORDER BY w.submit_date DESC, w.created_at DESC LIMIT ? OFFSET ?'
    params.push(size, (page - 1) * size)

    const [rows] = await pool.query(sql, params)

    // Fetch interaction counts and check if current user has read/liked
    if (rows.length > 0) {
      const logIds = rows.map(r => r.id)
      const [interactions] = await pool.query(
        `SELECT log_id, type, COUNT(*) as count
         FROM work_log_interactions
         WHERE log_id IN (?)
         GROUP BY log_id, type`,
        [logIds]
      )

      // Check current user's interactions
      const [userInteractions] = await pool.query(
        `SELECT log_id, type FROM work_log_interactions
         WHERE log_id IN (?) AND user_id = ?`,
        [logIds, currentUserId]
      )

      const interactionMap = {}
      for (const inter of interactions) {
        if (!interactionMap[inter.log_id]) {
          interactionMap[inter.log_id] = { read_count: 0, comment_count: 0, like_count: 0 }
        }
        interactionMap[inter.log_id][`${inter.type}_count`] = inter.count
      }

      const userInteractionMap = {}
      for (const inter of userInteractions) {
        if (!userInteractionMap[inter.log_id]) {
          userInteractionMap[inter.log_id] = { has_read: false, has_liked: false }
        }
        if (inter.type === 'read') userInteractionMap[inter.log_id].has_read = true
        if (inter.type === 'like') userInteractionMap[inter.log_id].has_liked = true
      }

      for (const row of rows) {
        const stats = interactionMap[row.id] || { read_count: 0, comment_count: 0, like_count: 0 }
        const userStats = userInteractionMap[row.id] || { has_read: false, has_liked: false }
        row.read_count = stats.read_count
        row.comment_count = stats.comment_count
        row.like_count = stats.like_count
        row.has_read = userStats.has_read
        row.has_liked = userStats.has_liked
      }
    }

    // 解析JSON字段
    const parsedRows = rows.map(row => ({
      ...row,
      content: safeParse(row.content),
      recipients: safeParse(row.recipients),
      attachments: safeParse(row.attachments),
      participants: safeParse(row.participants)
    }))


    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})


// PUT /api/oa/work-logs/:id - Update work log (only draft logs, only by creator)
router.put('/work-logs/:id', async (req, res, next) => {
  try {
    const { id } = req.params;
    const { content, recipients, attachments, participants, status } = req.body;

    // Check if log exists and is owned by user
    const [logs] = await pool.query(
      'SELECT user_id, status FROM work_logs WHERE id = ?',
      [id]
    );

    if (logs.length === 0) {
      return res.status(404).json({ code: 404, message: 'Work log not found' });
    }

    const log = logs[0];

    if (log.user_id !== req.user.id) {
      return res.status(403).json({ code: 403, message: 'Only creator can update work log' });
    }

    if (log.status !== 'draft') {
      return res.status(400).json({ code: 400, message: 'Only draft logs can be updated' });
    }

    const updates = [];
    const params = [];

    if (content !== undefined) {
      updates.push('content = ?');
      // Prevent double JSON.stringify if content is already a string
      params.push(typeof content === 'string' ? content : JSON.stringify(content));
    }
    if (recipients !== undefined) {
      updates.push('recipients = ?');
      params.push(typeof recipients === 'string' ? recipients : JSON.stringify(recipients));
    }
    if (attachments !== undefined) {
      updates.push('attachments = ?');
      params.push(typeof attachments === 'string' ? attachments : JSON.stringify(attachments));
    }
    if (participants !== undefined) {
      updates.push('participants = ?');
      params.push(typeof participants === 'string' ? participants : JSON.stringify(participants));
    }
    if (status !== undefined) {
      updates.push('status = ?');
      params.push(status);
    }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: 'No fields to update' });
    }

    params.push(id);
    await pool.query(`UPDATE work_logs SET ${updates.join(', ')} WHERE id = ?`, params);

    res.json({ code: 0, message: 'Work log updated successfully' });
  } catch (err) { next(err); }
});

// POST /api/oa/work-logs/:id/read - Mark as read
router.post('/work-logs/:id/read', async (req, res, next) => {
  try {
    await pool.query(
      `INSERT INTO work_log_interactions (log_id, user_id, type)
       VALUES (?,?,?) ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP`,
      [req.params.id, req.user.id, 'read']
    )
    res.json({ code: 0, data: null, message: '已标记为已读' })
  } catch (err) { next(err) }
})

// POST /api/oa/work-logs/:id/comment - Add comment
router.post('/work-logs/:id/comment', async (req, res, next) => {
  try {
    const { content } = req.body
    if (!content || content.trim() === '') {
      return res.status(400).json({ code: 400, message: '评论内容不能为空' })
    }

    await pool.query(
      'INSERT INTO work_log_interactions (log_id, user_id, type, content) VALUES (?,?,?,?)',
      [req.params.id, req.user.id, 'comment', content]
    )

    res.json({ code: 0, data: null, message: '评论成功' })
  } catch (err) { next(err) }
})

// POST /api/oa/work-logs/:id/like - Like/Unlike log
router.post('/work-logs/:id/like', async (req, res, next) => {
  try {
    const logId = req.params.id
    const userId = req.user.id

    // Check if already liked
    const [[existing]] = await pool.query(
      'SELECT id FROM work_log_interactions WHERE log_id = ? AND user_id = ? AND type = ?',
      [logId, userId, 'like']
    )

    if (existing) {
      // Unlike
      await pool.query(
        'DELETE FROM work_log_interactions WHERE log_id = ? AND user_id = ? AND type = ?',
        [logId, userId, 'like']
      )
      res.json({ code: 0, data: { action: 'unliked' }, message: '取消点赞' })
    } else {
      // Like
      await pool.query(
        'INSERT INTO work_log_interactions (log_id, user_id, type) VALUES (?,?,?)',
        [logId, userId, 'like']
      )
      res.json({ code: 0, data: { action: 'liked' }, message: '点赞成功' })
    }
  } catch (err) { next(err) }
})

// GET /api/oa/work-logs/:id - Get work log detail
router.get('/work-logs/:id', async (req, res, next) => {
  try {
    const logId = req.params.id

    // Get log detail
    const [[log]] = await pool.query(
      `SELECT w.*, u.name as user_name, u.department, u.department_id,
       d.name as department_name,
       t.name as template_name
       FROM work_logs w
       LEFT JOIN users u ON w.user_id = u.id
       LEFT JOIN departments d ON u.department_id = d.id
       LEFT JOIN work_log_templates t ON w.template_id = t.id
       WHERE w.id = ?`,
      [logId]
    )

    if (!log) {
      return res.status(404).json({ code: 404, message: '日志不存在' })
    }

    // Get read list
    const [readList] = await pool.query(
      `SELECT i.user_id, i.created_at as read_at, u.name as user_name
       FROM work_log_interactions i
       LEFT JOIN users u ON i.user_id = u.id
       WHERE i.log_id = ? AND i.type = ?
       ORDER BY i.created_at DESC`,
      [logId, 'read']
    )

    // Get comment list
    const [commentList] = await pool.query(
      `SELECT i.id, i.user_id, i.content, i.created_at, u.name as user_name
       FROM work_log_interactions i
       LEFT JOIN users u ON i.user_id = u.id
       WHERE i.log_id = ? AND i.type = ?
       ORDER BY i.created_at ASC`,
      [logId, 'comment']
    )

    // Get like list
    const [likeList] = await pool.query(
      `SELECT i.user_id, i.created_at as liked_at, u.name as user_name
       FROM work_log_interactions i
       LEFT JOIN users u ON i.user_id = u.id
       WHERE i.log_id = ? AND i.type = ?
       ORDER BY i.created_at DESC`,
      [logId, 'like']
    )

    // Get recipients list (if any)
    let recipientList = []
    if (log.recipients) {
      try {
        const recipientIds = JSON.parse(log.recipients)
        if (Array.isArray(recipientIds) && recipientIds.length > 0) {
          const [recipients] = await pool.query(
            `SELECT u.id, u.name, u.department
             FROM users u
             WHERE u.id IN (?)`,
            [recipientIds]
          )

          // Mark who has read
          const readUserIds = readList.map(r => r.user_id)
          recipientList = recipients.map(r => ({
            ...r,
            has_read: readUserIds.includes(r.id)
          }))
        }
      } catch (e) {
        console.error('Failed to parse recipients:', e)
      }
    }

    log.read_list = readList
    log.comment_list = commentList
    log.like_list = likeList
    log.recipient_list = recipientList
    log.read_count = readList.length
    log.comment_count = commentList.length
    log.like_count = likeList.length

    res.json({ code: 0, data: log, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/work-logs/:id/interactions - Get interaction details
router.get('/work-logs/:id/interactions', async (req, res, next) => {
  try {
    const logId = req.params.id

    // Get read list
    const [readList] = await pool.query(
      `SELECT i.user_id, i.created_at as read_at, u.name as user_name
       FROM work_log_interactions i
       LEFT JOIN users u ON i.user_id = u.id
       WHERE i.log_id = ? AND i.type = ?
       ORDER BY i.created_at DESC`,
      [logId, 'read']
    )

    // Get comment list
    const [commentList] = await pool.query(
      `SELECT i.id, i.user_id, i.content, i.created_at, u.name as user_name
       FROM work_log_interactions i
       LEFT JOIN users u ON i.user_id = u.id
       WHERE i.log_id = ? AND i.type = ?
       ORDER BY i.created_at ASC`,
      [logId, 'comment']
    )

    // Get like list
    const [likeList] = await pool.query(
      `SELECT i.user_id, i.created_at as liked_at, u.name as user_name
       FROM work_log_interactions i
       LEFT JOIN users u ON i.user_id = u.id
       WHERE i.log_id = ? AND i.type = ?
       ORDER BY i.created_at DESC`,
      [logId, 'like']
    )

    res.json({
      code: 0,
      data: {
        read_list: readList,
        comment_list: commentList,
        like_list: likeList
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// APPROVAL FLOW MODULE
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/oa/approval-types - List approval types
router.get('/approval-types', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT * FROM approval_types WHERE status = ? ORDER BY sort_order',
      ['active']
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/approvals - Create approval with auto-generated steps
router.post('/approvals', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { type_code, form_data, qrcode_id, attachments } = req.body
    const applicantId = req.user.id

    if (!type_code || !form_data) {
      return res.status(400).json({ code: 400, message: '审批类型和表单数据必填' })
    }

    // Get approval type configuration
    const [[approvalType]] = await conn.query(
      'SELECT * FROM approval_types WHERE code = ? AND status = ?',
      [type_code, 'active']
    )

    if (!approvalType) {
      return res.status(400).json({ code: 400, message: '审批类型不存在或已禁用' })
    }

    // Create approval record
    const [result] = await conn.query(
      `INSERT INTO approvals (type_code, applicant_id, form_data, qrcode_id, attachments, status)
       VALUES (?,?,?,?,?,?)`,
      [type_code, applicantId, JSON.stringify(form_data), qrcode_id || null,
       attachments ? JSON.stringify(attachments) : null, 'pending']
    )

    const approvalId = result.insertId

    // Auto-generate approval steps from default_flow
    if (approvalType.default_flow) {
      const flow = JSON.parse(approvalType.default_flow)
      for (let i = 0; i < flow.length; i++) {
        const step = flow[i]
        // Find approver based on role (simplified: use first user with that role)
        let approverId = null
        if (step.approver) {
          approverId = step.approver
        } else if (step.role) {
          const [[user]] = await conn.query(
            'SELECT id FROM users WHERE role = ? AND status = ? LIMIT 1',
            [step.role, 'active']
          )
          if (user) approverId = user.id
        }

        await conn.query(
          `INSERT INTO approval_steps (approval_id, level, approver_id, status)
           VALUES (?,?,?,?)`,
          [approvalId, step.level || (i + 1), approverId, i === 0 ? 'pending' : 'waiting']
        )
      }
    }

    await conn.commit()
    res.json({ code: 0, data: { id: approvalId }, message: '审批申请已提交' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// GET /api/oa/approvals - List approvals (my applications / pending my approval)
router.get('/approvals', async (req, res, next) => {
  try {
    const { type, type_code, status } = req.query
    const { page, size } = parsePagination(req.query)
    const currentUserId = req.user.id

    let sql = `SELECT a.*, u.name as applicant_name, u.department,
               at.name as type_name, at.icon
               FROM approvals a
               LEFT JOIN users u ON a.applicant_id = u.id
               LEFT JOIN approval_types at ON a.type_code = at.code
               WHERE 1=1`
    const params = []

    if (type === 'pending') {
      // Approvals pending my action
      sql += ` AND a.id IN (
        SELECT approval_id FROM approval_steps
        WHERE approver_id = ? AND status = 'pending'
      )`
      params.push(currentUserId)
    } else {
      // My applications (default)
      sql += ' AND a.applicant_id = ?'
      params.push(currentUserId)
    }

    if (type_code) {
      sql += ' AND a.type_code = ?'
      params.push(type_code)
    }
    if (status) {
      sql += ' AND a.status = ?'
      params.push(status)
    }

    // Count query - use a simpler approach
    const countSql = `SELECT COUNT(*) as total
                      FROM approvals a
                      LEFT JOIN users u ON a.applicant_id = u.id
                      LEFT JOIN approval_types at ON a.type_code = at.code
                      WHERE 1=1` + sql.substring(sql.indexOf('WHERE 1=1') + 9, sql.length)
    const [[countResult]] = await pool.query(countSql, params)
    const total = countResult ? countResult.total : 0

    sql += ' ORDER BY a.created_at DESC LIMIT ? OFFSET ?'
    params.push(size, (page - 1) * size)

    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/approvals/:id - Detail with steps
router.get('/approvals/:id', async (req, res, next) => {
  try {
    const [[approval]] = await pool.query(
      `SELECT a.*, u.name as applicant_name, u.department, u.email,
       at.name as type_name, at.icon, at.form_fields
       FROM approvals a
       LEFT JOIN users u ON a.applicant_id = u.id
       LEFT JOIN approval_types at ON a.type_code = at.code
       WHERE a.id = ?`,
      [req.params.id]
    )

    if (!approval) {
      return res.status(404).json({ code: 404, message: '审批记录不存在' })
    }

    // Get approval steps
    const [steps] = await pool.query(
      `SELECT s.*, u.name as approver_name, u.email as approver_email
       FROM approval_steps s
       LEFT JOIN users u ON s.approver_id = u.id
       WHERE s.approval_id = ?
       ORDER BY s.level`,
      [req.params.id]
    )

    approval.steps = steps
    res.json({ code: 0, data: approval, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/approvals/:id/approve - Approve step
router.post('/approvals/:id/approve', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { comment } = req.body
    const approverId = req.user.id
    const approvalId = req.params.id

    // Find current pending step for this approver
    const [[step]] = await conn.query(
      `SELECT * FROM approval_steps
       WHERE approval_id = ? AND approver_id = ? AND status = 'pending'
       ORDER BY level LIMIT 1`,
      [approvalId, approverId]
    )

    if (!step) {
      return res.status(400).json({ code: 400, message: '无待审批步骤或无权限' })
    }

    // Update step to approved
    await conn.query(
      `UPDATE approval_steps
       SET status = 'approved', comment = ?, approved_at = CURRENT_TIMESTAMP
       WHERE id = ?`,
      [comment || null, step.id]
    )

    // Check if there are more steps
    const [[nextStep]] = await conn.query(
      `SELECT * FROM approval_steps
       WHERE approval_id = ? AND level > ? AND status = 'waiting'
       ORDER BY level LIMIT 1`,
      [approvalId, step.level]
    )

    if (nextStep) {
      // Activate next step
      await conn.query(
        'UPDATE approval_steps SET status = ? WHERE id = ?',
        ['pending', nextStep.id]
      )
    } else {
      // All steps approved, mark approval as approved
      await conn.query(
        'UPDATE approvals SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
        ['approved', approvalId]
      )

      // 获取审批单信息，如果是财务相关审批，调用财务系统回调
      const [[approval]] = await conn.query(
        'SELECT type_code, form_data FROM approvals WHERE id = ?',
        [approvalId]
      )

      if (approval && approval.type_code === 'expense') {
        const formData = typeof approval.form_data === 'string' ? JSON.parse(approval.form_data) : approval.form_data
        if (formData.expense_id) {
          // 更新费用支出状态
          await conn.query(
            `UPDATE expense_records SET approval_status = 'approved', approver_id = ?, approved_at = NOW() WHERE id = ?`,
            [approverId, formData.expense_id]
          )

          // 创建财务提醒
          const [[expense]] = await conn.query(
            'SELECT creator_id, description, amount FROM expense_records WHERE id = ?',
            [formData.expense_id]
          )

          if (expense) {
            await conn.query(
              `INSERT INTO finance_reminders (reminder_type, title, content, target_user_id, related_id, priority, status)
               VALUES ('expense_approved', '费用支出审批通过', ?, ?, ?, 'medium', 'unread')`,
              [`您的费用支出"${expense.description}"（金额：¥${expense.amount}）已审批通过`, expense.creator_id, formData.expense_id]
            )
          }
        }
      }
    }

    await conn.commit()
    res.json({ code: 0, data: null, message: '审批通过' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// POST /api/oa/approvals/:id/reject - Reject approval
router.post('/approvals/:id/reject', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { comment } = req.body
    const approverId = req.user.id
    const approvalId = req.params.id

    if (!comment || comment.trim() === '') {
      return res.status(400).json({ code: 400, message: '拒绝原因必填' })
    }

    // Find current pending step for this approver
    const [[step]] = await conn.query(
      `SELECT * FROM approval_steps
       WHERE approval_id = ? AND approver_id = ? AND status = 'pending'
       ORDER BY level LIMIT 1`,
      [approvalId, approverId]
    )

    if (!step) {
      return res.status(400).json({ code: 400, message: '无待审批步骤或无权限' })
    }

    // Update step to rejected
    await conn.query(
      `UPDATE approval_steps
       SET status = 'rejected', comment = ?, approved_at = CURRENT_TIMESTAMP
       WHERE id = ?`,
      [comment, step.id]
    )

    // Mark entire approval as rejected
    await conn.query(
      'UPDATE approvals SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
      ['rejected', approvalId]
    )

    // 获取审批单信息，如果是财务相关审批，调用财务系统回调
    const [[approval]] = await conn.query(
      'SELECT type_code, form_data FROM approvals WHERE id = ?',
      [approvalId]
    )

    if (approval && approval.type_code === 'expense') {
      const formData = typeof approval.form_data === 'string' ? JSON.parse(approval.form_data) : approval.form_data
      if (formData.expense_id) {
        // 更新费用支出状态
        await conn.query(
          `UPDATE expense_records SET approval_status = 'rejected', approver_id = ?, approved_at = NOW() WHERE id = ?`,
          [approverId, formData.expense_id]
        )

        // 创建财务提醒
        const [[expense]] = await conn.query(
          'SELECT creator_id, description, amount FROM expense_records WHERE id = ?',
          [formData.expense_id]
        )

        if (expense) {
          await conn.query(
            `INSERT INTO finance_reminders (reminder_type, title, content, target_user_id, related_id, priority, status)
             VALUES ('expense_rejected', '费用支出审批被拒绝', ?, ?, ?, 'high', 'unread')`,
            [`您的费用支出"${expense.description}"（金额：¥${expense.amount}）已被拒绝，原因：${comment}`, expense.creator_id, formData.expense_id]
          )
        }
      }
    }

    await conn.commit()
    res.json({ code: 0, data: null, message: '审批已拒绝' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// POST /api/oa/approvals/:id/withdraw - Withdraw approval
router.post('/approvals/:id/withdraw', async (req, res, next) => {
  try {
    const approvalId = req.params.id
    const applicantId = req.user.id

    const [[approval]] = await pool.query(
      'SELECT * FROM approvals WHERE id = ? AND applicant_id = ?',
      [approvalId, applicantId]
    )

    if (!approval) {
      return res.status(404).json({ code: 404, message: '审批记录不存在或无权限' })
    }

    if (approval.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '只能撤回待审批的申请' })
    }

    await pool.query(
      'UPDATE approvals SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
      ['withdrawn', approvalId]
    )

    res.json({ code: 0, data: null, message: '审批已撤回' })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// ORGANIZATION MODULE
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/oa/departments - Tree structure
router.get('/departments', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT d.*, u.name as manager_name
       FROM departments d
       LEFT JOIN users u ON d.manager_id = u.id
       WHERE d.status = 'active'
       ORDER BY d.sort_order, d.id`
    )

    // Build tree structure
    const buildTree = (items, parentId = null) => {
      return items
        .filter(item => item.parent_id === parentId)
        .map(item => ({
          ...item,
          children: buildTree(items, item.id)
        }))
    }

    const tree = buildTree(rows)
    res.json({ code: 0, data: tree, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/departments - Create department
router.post('/departments', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { name, parent_id, level, manager_id, sort_order } = req.body

    if (!name || name.trim() === '') {
      return res.status(400).json({ code: 400, message: '部门名称不能为空' })
    }

    if (level && (level < 1 || level > 5)) {
      return res.status(400).json({ code: 400, message: '部门层级必须在1-5之间' })
    }

    const [result] = await pool.query(
      'INSERT INTO departments (name, parent_id, level, manager_id, sort_order) VALUES (?,?,?,?,?)',
      [name, parent_id || null, level || 1, manager_id || null, sort_order || 0]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '部门创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/oa/departments/:id - Update department
router.put('/departments/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { name, parent_id, level, manager_id, sort_order, status } = req.body
    const updates = []
    const params = []

    if (name !== undefined) { updates.push('name = ?'); params.push(name) }
    if (parent_id !== undefined) { updates.push('parent_id = ?'); params.push(parent_id || null) }
    if (level !== undefined) {
      if (level < 1 || level > 5) {
        return res.status(400).json({ code: 400, message: '部门层级必须在1-5之间' })
      }
      updates.push('level = ?')
      params.push(level)
    }
    if (manager_id !== undefined) { updates.push('manager_id = ?'); params.push(manager_id || null) }
    if (sort_order !== undefined) { updates.push('sort_order = ?'); params.push(sort_order) }
    if (status !== undefined) { updates.push('status = ?'); params.push(status) }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有需要更新的字段' })
    }

    params.push(req.params.id)
    await pool.query(`UPDATE departments SET ${updates.join(', ')} WHERE id = ?`, params)

    res.json({ code: 0, data: null, message: '部门更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/oa/departments/:id - Delete department
router.delete('/departments/:id', requireRole('admin'), async (req, res, next) => {
  try {
    const [[dept]] = await pool.query('SELECT * FROM departments WHERE id = ?', [req.params.id])

    if (!dept) {
      return res.status(404).json({ code: 404, message: '部门不存在' })
    }

    // Check if has children
    const [[{ count }]] = await pool.query(
      'SELECT COUNT(*) as count FROM departments WHERE parent_id = ?',
      [req.params.id]
    )

    if (count > 0) {
      return res.status(400).json({ code: 400, message: '该部门下有子部门，无法删除' })
    }

    // Check if has employees
    const [[{ empCount }]] = await pool.query(
      'SELECT COUNT(*) as empCount FROM users WHERE department = ?',
      [dept.name]
    )

    if (empCount > 0) {
      return res.status(400).json({ code: 400, message: '该部门下有员工，无法删除' })
    }

    await pool.query('DELETE FROM departments WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: '部门删除成功' })
  } catch (err) { next(err) }
})

// GET /api/oa/job-levels - List job levels
router.get('/job-levels', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM job_levels ORDER BY level DESC')
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/employees - Employee directory with search
router.get('/employees', async (req, res, next) => {
  try {
    const { keyword, department, role, status } = req.query
    const { page, size } = parsePagination(req.query)

    let sql = `SELECT u.id, u.name, u.email, u.phone, u.role, u.department,
               u.status, u.created_at, u.last_login
               FROM users u
               WHERE 1=1`
    const params = []

    if (keyword) {
      sql += ' AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)'
      params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`)
    }
    if (department) {
      sql += ' AND u.department = ?'
      params.push(department)
    }
    if (role) {
      sql += ' AND u.role = ?'
      params.push(role)
    }
    if (status) {
      sql += ' AND u.status = ?'
      params.push(status)
    }

    const countSql = sql.replace(/SELECT u\.id, u\.name.*FROM/, 'SELECT COUNT(*) as total FROM')
    const [[{ total }]] = await pool.query(countSql, params)

    sql += ' ORDER BY u.department, u.name LIMIT ? OFFSET ?'
    params.push(size, (page - 1) * size)

    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/employees/:id - Employee detail
router.get('/employees/:id', async (req, res, next) => {
  try {
    const [[employee]] = await pool.query(
      `SELECT u.id, u.name, u.email, u.phone, u.role, u.department,
       u.status, u.created_at, u.last_login, u.supplier_id,
       s.name as supplier_name
       FROM users u
       LEFT JOIN suppliers s ON u.supplier_id = s.id
       WHERE u.id = ?`,
      [req.params.id]
    )

    if (!employee) {
      return res.status(404).json({ code: 404, message: '员工不存在' })
    }

    res.json({ code: 0, data: employee, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/employees/qrcode/:code - Lookup employee by identity code
router.get('/employees/qrcode/:code', async (req, res, next) => {
  try {
    // This endpoint can be used to scan employee QR codes for identity verification
    // Assuming identity codes are stored in a separate table or field
    const [[employee]] = await pool.query(
      `SELECT u.id, u.name, u.email, u.phone, u.department, u.role
       FROM users u
       WHERE u.id = ?`,
      [req.params.code]
    )

    if (!employee) {
      return res.status(404).json({ code: 404, message: '员工不存在' })
    }

    res.json({ code: 0, data: employee, message: 'ok' })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// SHIFT MANAGEMENT MODULE
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/oa/shifts - List shifts
router.get('/shifts', async (req, res, next) => {
  try {
    const { status } = req.query
    let sql = 'SELECT * FROM shifts WHERE 1=1'
    const params = []

    if (status) {
      sql += ' AND status = ?'
      params.push(status)
    }

    sql += ' ORDER BY start_time'
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/shifts - Create shift
router.post('/shifts', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { name, code, start_time, end_time, duration, break_duration, color, description } = req.body

    if (!name || !code || !start_time || !end_time || !duration) {
      return res.status(400).json({ code: 400, message: '班次名称、代码、时间和时长必填' })
    }

    const [result] = await pool.query(
      `INSERT INTO shifts (name, code, start_time, end_time, duration, break_duration, color, description)
       VALUES (?,?,?,?,?,?,?,?)`,
      [name, code, start_time, end_time, duration, break_duration || 0, color || '#3B82F6', description || null]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '班次创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/oa/shifts/:id - Update shift
router.put('/shifts/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { name, start_time, end_time, duration, break_duration, color, description, status } = req.body
    const updates = []
    const params = []

    if (name !== undefined) { updates.push('name = ?'); params.push(name) }
    if (start_time !== undefined) { updates.push('start_time = ?'); params.push(start_time) }
    if (end_time !== undefined) { updates.push('end_time = ?'); params.push(end_time) }
    if (duration !== undefined) { updates.push('duration = ?'); params.push(duration) }
    if (break_duration !== undefined) { updates.push('break_duration = ?'); params.push(break_duration) }
    if (color !== undefined) { updates.push('color = ?'); params.push(color) }
    if (description !== undefined) { updates.push('description = ?'); params.push(description) }
    if (status !== undefined) { updates.push('status = ?'); params.push(status) }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有需要更新的字段' })
    }

    params.push(req.params.id)
    await pool.query(`UPDATE shifts SET ${updates.join(', ')} WHERE id = ?`, params)

    res.json({ code: 0, data: null, message: '班次更新成功' })
  } catch (err) { next(err) }
})

// GET /api/oa/schedules - Get schedules
router.get('/schedules', async (req, res, next) => {
  try {
    const { user_id, department, start_date, end_date, status } = req.query
    const { page, size } = parsePagination(req.query)

    let sql = `SELECT ss.*, u.name as user_name, u.department,
               s.name as shift_name, s.start_time, s.end_time, s.color,
               creator.name as creator_name
               FROM shift_schedules ss
               LEFT JOIN users u ON ss.user_id = u.id
               LEFT JOIN shifts s ON ss.shift_id = s.id
               LEFT JOIN users creator ON ss.created_by = creator.id
               WHERE 1=1`
    const params = []

    if (user_id) { sql += ' AND ss.user_id = ?'; params.push(user_id) }
    if (department) { sql += ' AND ss.department = ?'; params.push(department) }
    if (start_date) { sql += ' AND ss.schedule_date >= ?'; params.push(start_date) }
    if (end_date) { sql += ' AND ss.schedule_date <= ?'; params.push(end_date) }
    if (status) { sql += ' AND ss.status = ?'; params.push(status) }

    const countSql = sql.replace(/SELECT ss\.\*, u\.name as user_name.*FROM/, 'SELECT COUNT(*) as total FROM')
    const [[{ total }]] = await pool.query(countSql, params)

    sql += ' ORDER BY ss.schedule_date DESC, u.name LIMIT ? OFFSET ?'
    params.push(size, (page - 1) * size)

    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/schedules - Create schedule (batch), supports weekdays filter
router.post('/schedules', requireRole('admin', 'manager'), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { schedules, weekdays } = req.body
    const creatorId = req.user.id

    if (!Array.isArray(schedules) || schedules.length === 0) {
      return res.status(400).json({ code: 400, message: '排班数据必填' })
    }

    const insertedIds = []
    for (const schedule of schedules) {
      const { user_id, shift_id, schedule_date, department, notes } = schedule

      if (!user_id || !shift_id || !schedule_date) {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: '员工、班次和日期必填' })
      }

      // If weekdays filter provided, skip dates not matching
      if (Array.isArray(weekdays) && weekdays.length > 0) {
        const dayOfWeek = new Date(schedule_date).getDay() // 0=Sun, 1=Mon...
        if (!weekdays.includes(dayOfWeek)) continue
      }

      // Check for conflicts
      const [[existing]] = await conn.query(
        'SELECT id FROM shift_schedules WHERE user_id = ? AND schedule_date = ?',
        [user_id, schedule_date]
      )

      if (existing) {
        // Skip conflicts silently in batch mode
        continue
      }

      const [result] = await conn.query(
        `INSERT INTO shift_schedules (user_id, shift_id, schedule_date, department, notes, created_by, attendance_required)
         VALUES (?,?,?,?,?,?,1)`,
        [user_id, shift_id, schedule_date, department || null, notes || null, creatorId]
      )

      insertedIds.push(result.insertId)
    }

    await conn.commit()
    res.json({ code: 0, data: { ids: insertedIds, count: insertedIds.length }, message: `排班创建成功，共${insertedIds.length}条` })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// PUT /api/oa/schedules/:id - Update schedule
router.put('/schedules/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { shift_id, schedule_date, status, notes } = req.body
    const updates = []
    const params = []

    if (shift_id !== undefined) { updates.push('shift_id = ?'); params.push(shift_id) }
    if (schedule_date !== undefined) { updates.push('schedule_date = ?'); params.push(schedule_date) }
    if (status !== undefined) { updates.push('status = ?'); params.push(status) }
    if (notes !== undefined) { updates.push('notes = ?'); params.push(notes) }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有需要更新的字段' })
    }

    params.push(req.params.id)
    await pool.query(`UPDATE shift_schedules SET ${updates.join(', ')} WHERE id = ?`, params)

    res.json({ code: 0, data: null, message: '排班更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/oa/schedules/:id - Delete schedule
router.delete('/schedules/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM shift_schedules WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: '排班删除成功' })
  } catch (err) { next(err) }
})

// POST /api/oa/schedules/swap - Swap shifts
router.post('/schedules/swap', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { schedule_id_a, schedule_id_b, reason } = req.body
    const userId = req.user.id

    if (!schedule_id_a || !schedule_id_b) {
      return res.status(400).json({ code: 400, message: '两个排班ID必填' })
    }

    // Get both schedules
    const [[scheduleA]] = await conn.query('SELECT * FROM shift_schedules WHERE id = ?', [schedule_id_a])
    const [[scheduleB]] = await conn.query('SELECT * FROM shift_schedules WHERE id = ?', [schedule_id_b])

    if (!scheduleA || !scheduleB) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '排班记录不存在' })
    }

    // Create swap request
    const [result] = await conn.query(
      `INSERT INTO shift_swaps (schedule_id_a, schedule_id_b, user_id_a, user_id_b, reason, status)
       VALUES (?,?,?,?,?,?)`,
      [schedule_id_a, schedule_id_b, scheduleA.user_id, scheduleB.user_id, reason || null, 'pending']
    )

    await conn.commit()
    res.json({ code: 0, data: { id: result.insertId }, message: '调班申请已提交' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// POST /api/oa/schedules/swap/:id/approve - Approve swap
router.post('/schedules/swap/:id/approve', requireRole('admin', 'manager'), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const swapId = req.params.id
    const approverId = req.user.id

    const [[swap]] = await conn.query('SELECT * FROM shift_swaps WHERE id = ?', [swapId])

    if (!swap) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '调班记录不存在' })
    }

    if (swap.status !== 'pending') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '该调班申请已处理' })
    }

    // Swap the shifts
    const [[scheduleA]] = await conn.query('SELECT * FROM shift_schedules WHERE id = ?', [swap.schedule_id_a])
    const [[scheduleB]] = await conn.query('SELECT * FROM shift_schedules WHERE id = ?', [swap.schedule_id_b])

    await conn.query('UPDATE shift_schedules SET shift_id = ?, status = ? WHERE id = ?',
      [scheduleB.shift_id, 'swapped', swap.schedule_id_a])
    await conn.query('UPDATE shift_schedules SET shift_id = ?, status = ? WHERE id = ?',
      [scheduleA.shift_id, 'swapped', swap.schedule_id_b])

    // Update swap status
    await conn.query(
      'UPDATE shift_swaps SET status = ?, approved_by = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ?',
      ['approved', approverId, swapId]
    )

    await conn.commit()
    res.json({ code: 0, data: null, message: '调班已批准' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// GET /api/oa/attendance/summary - Attendance summary
router.get('/attendance/summary', async (req, res, next) => {
  try {
    const { user_id, department, start_date, end_date } = req.query

    let sql = `SELECT
               u.id as user_id, u.name as user_name, u.department,
               COUNT(a.id) as total_days,
               SUM(CASE WHEN a.status = 'normal' THEN 1 ELSE 0 END) as normal_days,
               SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
               SUM(CASE WHEN a.status = 'early' THEN 1 ELSE 0 END) as early_days,
               SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
               SUM(a.overtime_hours) as total_overtime,
               SUM(a.late_minutes) as total_late_minutes,
               SUM(a.early_minutes) as total_early_minutes
               FROM users u
               LEFT JOIN attendance a ON u.id = a.user_id`

    const params = []
    const conditions = []

    if (start_date) {
      conditions.push('a.date >= ?')
      params.push(start_date)
    }
    if (end_date) {
      conditions.push('a.date <= ?')
      params.push(end_date)
    }
    if (user_id) {
      conditions.push('u.id = ?')
      params.push(user_id)
    }
    if (department) {
      conditions.push('u.department = ?')
      params.push(department)
    }

    if (conditions.length > 0) {
      sql += ' WHERE ' + conditions.join(' AND ')
    }

    sql += ' GROUP BY u.id, u.name, u.department ORDER BY u.department, u.name'

    const [rows] = await pool.query(sql, params)

    // Calculate attendance rate
    for (const row of rows) {
      row.attendance_rate = row.total_days > 0
        ? ((row.normal_days / row.total_days) * 100).toFixed(2)
        : '0.00'
    }

    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// WORKFLOW ENGINE MODULE
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/oa/workflows - List workflow definitions
router.get('/workflows', async (req, res, next) => {
  try {
    const { category, is_active, is_template } = req.query

    let sql = `SELECT w.*, u.name as creator_name
               FROM workflow_definitions w
               LEFT JOIN users u ON w.created_by = u.id
               WHERE 1=1`
    const params = []

    if (category) { sql += ' AND w.category = ?'; params.push(category) }
    if (is_active !== undefined) { sql += ' AND w.is_active = ?'; params.push(is_active === 'true' ? 1 : 0) }
    if (is_template !== undefined) { sql += ' AND w.is_template = ?'; params.push(is_template === 'true' ? 1 : 0) }

    sql += ' ORDER BY w.created_at DESC'
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/workflows - Create workflow definition
router.post('/workflows', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { name, code, category, description, flow_config, is_template } = req.body
    const creatorId = req.user.id

    if (!name || !code || !flow_config) {
      return res.status(400).json({ code: 400, message: '工作流名称、代码和流程配置必填' })
    }

    const [result] = await pool.query(
      `INSERT INTO workflow_definitions (name, code, category, description, flow_config, is_template, created_by)
       VALUES (?,?,?,?,?,?,?)`,
      [name, code, category || null, description || null, JSON.stringify(flow_config),
       is_template ? 1 : 0, creatorId]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '工作流创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/oa/workflows/:id - Update workflow definition
router.put('/workflows/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { name, description, flow_config, is_active } = req.body
    const updates = []
    const params = []

    if (name !== undefined) { updates.push('name = ?'); params.push(name) }
    if (description !== undefined) { updates.push('description = ?'); params.push(description) }
    if (flow_config !== undefined) { updates.push('flow_config = ?, version = version + 1'); params.push(JSON.stringify(flow_config)) }
    if (is_active !== undefined) { updates.push('is_active = ?'); params.push(is_active ? 1 : 0) }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有需要更新的字段' })
    }

    params.push(req.params.id)
    await pool.query(`UPDATE workflow_definitions SET ${updates.join(', ')} WHERE id = ?`, params)

    res.json({ code: 0, data: null, message: '工作流更新成功' })
  } catch (err) { next(err) }
})

// POST /api/oa/workflow-instances - Start workflow instance
router.post('/workflow-instances', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { workflow_code, title, business_key, business_type, form_data } = req.body
    const initiatorId = req.user.id

    if (!workflow_code || !title) {
      return res.status(400).json({ code: 400, message: '工作流代码和标题必填' })
    }

    // Get workflow definition
    const [[workflow]] = await conn.query(
      'SELECT * FROM workflow_definitions WHERE code = ? AND is_active = TRUE',
      [workflow_code]
    )

    if (!workflow) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '工作流不存在或未启用' })
    }

    const flowConfig = JSON.parse(workflow.flow_config)

    // Create instance
    const [result] = await conn.query(
      `INSERT INTO workflow_instances (workflow_id, workflow_code, workflow_version, title,
       initiator_id, business_key, business_type, form_data, current_node, status)
       VALUES (?,?,?,?,?,?,?,?,?,?)`,
      [workflow.id, workflow_code, workflow.version, title, initiatorId,
       business_key || null, business_type || null, form_data ? JSON.stringify(form_data) : null,
       'start', 'running']
    )

    const instanceId = result.insertId

    // Create tasks based on flow config
    if (flowConfig.nodes) {
      for (const node of flowConfig.nodes) {
        if (node.type === 'start') continue

        let assigneeId = null
        if (node.assignee_type === 'role') {
          // Find first user with role
          const [[user]] = await conn.query(
            'SELECT id FROM users WHERE role = ? AND status = ? LIMIT 1',
            [node.assignee || 'manager', 'active']
          )
          if (user) assigneeId = user.id
        }

        const taskStatus = node.type === 'approval' && flowConfig.edges.find(e => e.from === 'start' && e.to === node.id)
          ? 'pending' : 'pending'

        await conn.query(
          `INSERT INTO workflow_tasks (instance_id, node_id, node_name, node_type,
           assignee_id, assignee_type, status)
           VALUES (?,?,?,?,?,?,?)`,
          [instanceId, node.id, node.name, node.type, assigneeId, node.assignee_type || null, taskStatus]
        )
      }
    }

    // Log
    await conn.query(
      'INSERT INTO workflow_logs (instance_id, action, operator_id, message) VALUES (?,?,?,?)',
      [instanceId, 'start', initiatorId, '工作流已启动']
    )

    await conn.commit()
    res.json({ code: 0, data: { id: instanceId }, message: '工作流已启动' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// GET /api/oa/workflow-instances - List instances
router.get('/workflow-instances', async (req, res, next) => {
  try {
    const { workflow_code, status, initiator_id } = req.query
    const { page, size } = parsePagination(req.query)

    let sql = `SELECT wi.*, u.name as initiator_name, wd.name as workflow_name
               FROM workflow_instances wi
               LEFT JOIN users u ON wi.initiator_id = u.id
               LEFT JOIN workflow_definitions wd ON wi.workflow_id = wd.id
               WHERE 1=1`
    const params = []

    if (workflow_code) { sql += ' AND wi.workflow_code = ?'; params.push(workflow_code) }
    if (status) { sql += ' AND wi.status = ?'; params.push(status) }
    if (initiator_id) { sql += ' AND wi.initiator_id = ?'; params.push(initiator_id) }

    const countSql = sql.replace(/SELECT wi\.\*, u\.name as initiator_name.*FROM/, 'SELECT COUNT(*) as total FROM')
    const [[{ total }]] = await pool.query(countSql, params)

    sql += ' ORDER BY wi.started_at DESC LIMIT ? OFFSET ?'
    params.push(size, (page - 1) * size)

    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/workflow-instances/:id - Instance detail
router.get('/workflow-instances/:id', async (req, res, next) => {
  try {
    const [[instance]] = await pool.query(
      `SELECT wi.*, u.name as initiator_name, wd.name as workflow_name, wd.flow_config
       FROM workflow_instances wi
       LEFT JOIN users u ON wi.initiator_id = u.id
       LEFT JOIN workflow_definitions wd ON wi.workflow_id = wd.id
       WHERE wi.id = ?`,
      [req.params.id]
    )

    if (!instance) {
      return res.status(404).json({ code: 404, message: '工作流实例不存在' })
    }

    // Get tasks
    const [tasks] = await pool.query(
      `SELECT wt.*, u.name as assignee_name
       FROM workflow_tasks wt
       LEFT JOIN users u ON wt.assignee_id = u.id
       WHERE wt.instance_id = ?
       ORDER BY wt.created_at`,
      [req.params.id]
    )

    // Get logs
    const [logs] = await pool.query(
      `SELECT wl.*, u.name as operator_name
       FROM workflow_logs wl
       LEFT JOIN users u ON wl.operator_id = u.id
       WHERE wl.instance_id = ?
       ORDER BY wl.created_at`,
      [req.params.id]
    )

    instance.tasks = tasks
    instance.logs = logs

    res.json({ code: 0, data: instance, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/workflow-tasks/my - My pending tasks
router.get('/workflow-tasks/my', async (req, res, next) => {
  try {
    const userId = req.user.id
    const { page, size } = parsePagination(req.query)

    const sql = `SELECT wt.*, wi.title as instance_title, wi.workflow_code,
                 u.name as initiator_name
                 FROM workflow_tasks wt
                 LEFT JOIN workflow_instances wi ON wt.instance_id = wi.id
                 LEFT JOIN users u ON wi.initiator_id = u.id
                 WHERE wt.assignee_id = ? AND wt.status = 'pending'
                 ORDER BY wt.created_at DESC
                 LIMIT ? OFFSET ?`

    const countSql = `SELECT COUNT(*) as total FROM workflow_tasks WHERE assignee_id = ? AND status = 'pending'`
    const [[{ total }]] = await pool.query(countSql, [userId])

    const [rows] = await pool.query(sql, [userId, size, (page - 1) * size])
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/workflow-tasks/:id/complete - Complete task
router.post('/workflow-tasks/:id/complete', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { action, comment, form_data } = req.body
    const userId = req.user.id
    const taskId = req.params.id

    const [[task]] = await conn.query('SELECT * FROM workflow_tasks WHERE id = ?', [taskId])

    if (!task) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '任务不存在' })
    }

    if (task.assignee_id !== userId) {
      await conn.rollback()
      return res.status(403).json({ code: 403, message: '无权限处理此任务' })
    }

    if (task.status !== 'pending') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '任务已处理' })
    }

    // Update task
    await conn.query(
      `UPDATE workflow_tasks SET status = ?, action = ?, comment = ?, form_data = ?,
       completed_at = CURRENT_TIMESTAMP WHERE id = ?`,
      ['completed', action || null, comment || null, form_data ? JSON.stringify(form_data) : null, taskId]
    )

    // Log
    await conn.query(
      'INSERT INTO workflow_logs (instance_id, task_id, node_id, action, operator_id, message) VALUES (?,?,?,?,?,?)',
      [task.instance_id, taskId, task.node_id, action || 'complete', userId, comment || '任务已完成']
    )

    // Check if workflow should complete
    const [[{ pendingCount }]] = await conn.query(
      'SELECT COUNT(*) as pendingCount FROM workflow_tasks WHERE instance_id = ? AND status = ?',
      [task.instance_id, 'pending']
    )

    if (pendingCount === 0) {
      await conn.query(
        'UPDATE workflow_instances SET status = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ?',
        ['completed', task.instance_id]
      )
    }

    await conn.commit()
    res.json({ code: 0, data: null, message: '任务已完成' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ═══════════════════════════════════════════════════════════════════════════════
// LEAVE MODULE
// ═══════════════════════════════════════════════════════════════════════════════

// POST /api/oa/leave - Submit leave request
router.post('/leave', async (req, res, next) => {
  try {
    const { type, start_date, end_date, days, reason } = req.body
    const userId = req.user.id

    if (!type || !start_date || !end_date || !days || !reason) {
      return res.status(400).json({ code: 400, message: '请填写完整的请假信息' })
    }

    // Get user's supervisor
    const [[user]] = await pool.query('SELECT supervisor_id FROM users WHERE id = ?', [userId])
    if (!user || !user.supervisor_id) {
      return res.status(400).json({ code: 400, message: '未设置上级，无法提交请假申请' })
    }

    const [result] = await pool.query(
      `INSERT INTO leave_records (user_id, type, start_date, end_date, days, reason, status, approver_id)
       VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)`,
      [userId, type, start_date, end_date, days, reason, user.supervisor_id]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '请假申请已提交' })
  } catch (err) { next(err) }
})

// GET /api/oa/leave - Query leave records
router.get('/leave', async (req, res, next) => {
  try {
    const { user_id, status, start_date, end_date, type } = req.query
    const { page, size } = parsePagination(req.query)
    const currentUserId = req.user.id
    const currentUserRole = req.user.role

    let where = 'WHERE 1=1'
    const params = []

    // Permission control
    if (currentUserRole === 'admin') {
      if (user_id) { where += ' AND l.user_id = ?'; params.push(user_id) }
    } else {
      // Get subordinates
      const [subordinates] = await pool.query(`
        WITH RECURSIVE subordinate_tree AS (
          SELECT id FROM users WHERE supervisor_id = ?
          UNION ALL
          SELECT u.id FROM users u
          INNER JOIN subordinate_tree st ON u.supervisor_id = st.id
        )
        SELECT id FROM subordinate_tree
      `, [currentUserId])

      const subordinateIds = subordinates.map(s => s.id)
      subordinateIds.push(currentUserId)

      if (user_id) {
        if (!subordinateIds.includes(parseInt(user_id))) {
          return res.status(403).json({ code: 403, message: '无权查看该用户的请假记录' })
        }
        where += ' AND l.user_id = ?'
        params.push(user_id)
      } else {
        where += ' AND l.user_id IN (?)'
        params.push(subordinateIds)
      }
    }

    if (status) { where += ' AND l.status = ?'; params.push(status) }
    if (type) { where += ' AND l.type = ?'; params.push(type) }
    if (start_date) { where += ' AND l.start_date >= ?'; params.push(start_date) }
    if (end_date) { where += ' AND l.end_date <= ?'; params.push(end_date) }

    const sql = `
      SELECT l.*,
             u.name as user_name,
             u.department,
             approver.name as approver_name
      FROM leave_records l
      LEFT JOIN users u ON l.user_id = u.id
      LEFT JOIN users approver ON l.approver_id = approver.id
      ${where}
      ORDER BY l.created_at DESC
      LIMIT ? OFFSET ?
    `
    params.push(size, (page - 1) * size)

    const countSql = `SELECT COUNT(*) as total FROM leave_records l LEFT JOIN users u ON l.user_id = u.id ${where}`
    const [[{ total }]] = await pool.query(countSql, params.slice(0, -2))
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/leave/pending - Get pending leave requests for approval
router.get('/leave/pending', async (req, res, next) => {
  try {
    const approverId = req.user.id

    const [rows] = await pool.query(
      `SELECT l.*,
              u.name as user_name,
              u.department
       FROM leave_records l
       LEFT JOIN users u ON l.user_id = u.id
       WHERE l.approver_id = ? AND l.status = 'pending'
       ORDER BY l.created_at DESC`,
      [approverId]
    )

    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/oa/leave/:id/approve - Approve leave request
router.put('/leave/:id/approve', async (req, res, next) => {
  try {
    const { id } = req.params
    const { action, reject_reason } = req.body // action: 'approve' or 'reject'
    const approverId = req.user.id

    if (!['approve', 'reject'].includes(action)) {
      return res.status(400).json({ code: 400, message: '无效的审批操作' })
    }

    if (action === 'reject' && !reject_reason) {
      return res.status(400).json({ code: 400, message: '拒绝时必须填写原因' })
    }

    const [[leave]] = await pool.query('SELECT * FROM leave_records WHERE id = ?', [id])
    if (!leave) {
      return res.status(404).json({ code: 404, message: '请假记录不存在' })
    }

    if (leave.approver_id !== approverId && req.user.role !== 'admin') {
      return res.status(403).json({ code: 403, message: '无权审批此请假申请' })
    }

    if (leave.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '该请假申请已处理' })
    }

    const newStatus = action === 'approve' ? 'approved' : 'rejected'
    await pool.query(
      `UPDATE leave_records
       SET status = ?, reject_reason = ?, approved_at = NOW()
       WHERE id = ?`,
      [newStatus, reject_reason || null, id]
    )

    res.json({ code: 0, message: action === 'approve' ? '请假已批准' : '请假已拒绝' })
  } catch (err) { next(err) }
})

// DELETE /api/oa/leave/:id - Delete leave request (only pending)
router.delete('/leave/:id', async (req, res, next) => {
  try {
    const { id } = req.params
    const userId = req.user.id

    const [[leave]] = await pool.query('SELECT * FROM leave_records WHERE id = ?', [id])
    if (!leave) {
      return res.status(404).json({ code: 404, message: '请假记录不存在' })
    }

    if (leave.user_id !== userId && req.user.role !== 'admin') {
      return res.status(403).json({ code: 403, message: '无权删除此请假申请' })
    }

    if (leave.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '只能删除待审批的请假申请' })
    }

    await pool.query('DELETE FROM leave_records WHERE id = ?', [id])
    res.json({ code: 0, message: '请假申请已删除' })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// ATTENDANCE RULES
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/oa/attendance-rules - List all rules
router.get('/attendance-rules', async (req, res, next) => {
  try {
    const [rules] = await pool.query(
      'SELECT * FROM attendance_rules ORDER BY created_at DESC'
    )

    // Fetch members for each rule
    for (const rule of rules) {
      const [members] = await pool.query(
        'SELECT arm.user_id, u.name as user_name FROM attendance_rule_members arm LEFT JOIN users u ON arm.user_id = u.id WHERE arm.rule_id = ?',
        [rule.id]
      )
      rule.members = members
      if (typeof rule.weekdays === 'string') {
        try { rule.weekdays = JSON.parse(rule.weekdays) } catch { rule.weekdays = [] }
      }
    }

    res.json({ code: 0, data: rules, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/attendance-rules - Create rule
router.post('/attendance-rules', async (req, res, next) => {
  try {
    const { name, weekdays, start_time, end_time, member_ids } = req.body
    if (!name) return res.status(400).json({ code: 400, message: '规则名称必填' })

    const [result] = await pool.query(
      'INSERT INTO attendance_rules (name, weekdays, start_time, end_time, created_by) VALUES (?, ?, ?, ?, ?)',
      [name, JSON.stringify(weekdays || [1,2,3,4,5]), start_time || '09:00', end_time || '18:00', req.user.id]
    )
    const ruleId = result.insertId

    // Insert members
    if (member_ids?.length) {
      const values = member_ids.map(uid => [ruleId, uid])
      await pool.query('INSERT INTO attendance_rule_members (rule_id, user_id) VALUES ?', [values])
    }

    res.json({ code: 0, data: { id: ruleId }, message: '创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/oa/attendance-rules/:id - Update rule
router.put('/attendance-rules/:id', async (req, res, next) => {
  try {
    const { id } = req.params
    const { name, weekdays, start_time, end_time, status, member_ids } = req.body

    const [[existing]] = await pool.query('SELECT * FROM attendance_rules WHERE id = ?', [id])
    if (!existing) return res.status(404).json({ code: 404, message: '规则不存在' })

    // Build update fields
    const updates = []
    const params = []
    if (name !== undefined) { updates.push('name = ?'); params.push(name) }
    if (weekdays !== undefined) { updates.push('weekdays = ?'); params.push(JSON.stringify(weekdays)) }
    if (start_time !== undefined) { updates.push('start_time = ?'); params.push(start_time) }
    if (end_time !== undefined) { updates.push('end_time = ?'); params.push(end_time) }
    if (status !== undefined) { updates.push('status = ?'); params.push(status) }

    if (updates.length) {
      params.push(id)
      await pool.query(`UPDATE attendance_rules SET ${updates.join(', ')} WHERE id = ?`, params)
    }

    // Update members if provided
    if (member_ids !== undefined) {
      await pool.query('DELETE FROM attendance_rule_members WHERE rule_id = ?', [id])
      if (member_ids.length) {
        const values = member_ids.map(uid => [id, uid])
        await pool.query('INSERT INTO attendance_rule_members (rule_id, user_id) VALUES ?', [values])
      }
    }

    res.json({ code: 0, message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/oa/attendance-rules/:id - Delete rule
router.delete('/attendance-rules/:id', async (req, res, next) => {
  try {
    const { id } = req.params
    const [[existing]] = await pool.query('SELECT * FROM attendance_rules WHERE id = ?', [id])
    if (!existing) return res.status(404).json({ code: 404, message: '规则不存在' })

    await pool.query('DELETE FROM attendance_rules WHERE id = ?', [id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

export default router
