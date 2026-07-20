import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { h5Auth } from '../middleware/h5Auth.js'
import { uploadFeedback } from '../middleware/upload.js'
import { parsePagination } from '../utils/pagination.js'
import { checkPerm } from '../utils/permission.js'

const router = Router()

// Optional auth middleware - allows both authenticated and public access
const optionalAuth = async (req, res, next) => {
  const token = req.headers['authorization']?.replace('Bearer ', '')
  if (!token) return next()

  try {
    const jwt = await import('jsonwebtoken')
    const decoded = jwt.default.verify(token, process.env.JWT_SECRET)

    // Determine if it's system user or H5 user based on token structure
    if (decoded.role) {
      req.user = decoded // System user
    } else if (decoded.phone) {
      req.h5user = decoded // H5 user
    }
  } catch (err) {
    // Invalid token, continue as public
  }
  next()
}

// POST /api/feedback - Submit feedback
router.post('/', uploadFeedback.array('images', 5), async (req, res, next) => {
  try {
    const { type, title, content, contact_phone, target_user_id, recipients } = req.body

    if (!type || !title || !content) {
      return res.status(400).json({ code: 400, message: '类型、标题和内容为必填项' })
    }

    const validTypes = ['complaint', 'suggestion', 'bug', 'other']
    if (!validTypes.includes(type)) {
      return res.status(400).json({ code: 400, message: '无效的反馈类型' })
    }

    // Get user IDs from auth
    const userId = req.user?.id || null
    const h5UserId = req.h5user?.id || null

    // Process uploaded images
    const images = req.files ? req.files.map(f => `/uploads/feedback/${f.filename}`) : []

    const [result] = await pool.query(
      `INSERT INTO feedback_records (type, title, content, images, contact_phone, user_id, h5_user_id, target_user_id, recipients, status, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())`,
      [type, title, content, JSON.stringify(images), contact_phone, userId, h5UserId, target_user_id || null, recipients ? JSON.stringify(recipients) : null]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '提交成功' })
  } catch (err) {
    next(err)
  }
})

// GET /api/feedback - Get feedback list
router.get('/', auth, async (req, res, next) => {
  try {
    const { type, status, assigned_to, date_from, date_to } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE 1=1'
    const params = []
    const countParams = []

    // Non-admin users only see their own feedback
    if (!(await checkPerm(req, 'system:config'))) {
      where += ' AND (f.user_id = ? OR f.assigned_to = ?)'
      params.push(req.user.id, req.user.id)
      countParams.push(req.user.id, req.user.id)
    }

    if (type) {
      where += ' AND f.type = ?'
      params.push(type)
      countParams.push(type)
    }

    if (status) {
      where += ' AND f.status = ?'
      params.push(status)
      countParams.push(status)
    }

    if (assigned_to) {
      where += ' AND f.assigned_to = ?'
      params.push(assigned_to)
      countParams.push(assigned_to)
    }

    if (date_from) {
      where += ' AND DATE(f.created_at) >= ?'
      params.push(date_from)
      countParams.push(date_from)
    }

    if (date_to) {
      where += ' AND DATE(f.created_at) <= ?'
      params.push(date_to)
      countParams.push(date_to)
    }

    const sql = `
      SELECT f.*,
        u.name as user_name, u.email as user_email,
        h.name as h5_user_name, h.phone as h5_user_phone,
        a.name as assigned_to_name
      FROM feedback_records f
      LEFT JOIN users u ON f.user_id = u.id
      LEFT JOIN h5_users h ON f.h5_user_id = h.id
      LEFT JOIN users a ON f.assigned_to = a.id
      ${where}
      ORDER BY f.created_at DESC
      LIMIT ? OFFSET ?
    `

    const countSql = `
      SELECT COUNT(*) as total
      FROM feedback_records f
      ${where}
    `

    params.push(size, (page - 1) * size)

    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)

    // Parse images JSON
    rows.forEach(row => {
      if (row.images) {
        try {
          row.images = JSON.parse(row.images)
        } catch {
          row.images = []
        }
      } else {
        row.images = []
      }
    })

    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// GET /api/feedback/stats - Feedback statistics (admin only)
router.get('/stats', auth, async (req, res, next) => {
  try {
    if (!(await checkPerm(req, 'system:config'))) {
      return res.status(403).json({ code: 403, message: '权限不足' })
    }

    const { date_from, date_to } = req.query
    let dateFilter = ''
    const params = []

    if (date_from) {
      dateFilter += ' AND DATE(created_at) >= ?'
      params.push(date_from)
    }

    if (date_to) {
      dateFilter += ' AND DATE(created_at) <= ?'
      params.push(date_to)
    }

    // Total by type
    const [typeStats] = await pool.query(
      `SELECT type, COUNT(*) as count FROM feedback_records WHERE 1=1 ${dateFilter} GROUP BY type`,
      params
    )

    // Total by status
    const [statusStats] = await pool.query(
      `SELECT status, COUNT(*) as count FROM feedback_records WHERE 1=1 ${dateFilter} GROUP BY status`,
      params
    )

    // Average resolution time (in hours)
    const [[{ avg_resolution_time }]] = await pool.query(
      `SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_resolution_time
       FROM feedback_records
       WHERE status = 'resolved' AND resolved_at IS NOT NULL ${dateFilter}`,
      params
    )

    const byType = {}
    typeStats.forEach(row => {
      byType[row.type] = row.count
    })

    const byStatus = {}
    statusStats.forEach(row => {
      byStatus[row.status] = row.count
    })

    res.json({
      code: 0,
      data: {
        byType,
        byStatus,
        avgResolutionTime: avg_resolution_time ? Math.round(avg_resolution_time * 10) / 10 : null
      },
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

// GET /api/feedback/:id - Get feedback detail
router.get('/:id', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT f.*,
        u.name as user_name, u.email as user_email, u.phone as user_phone,
        h.name as h5_user_name, h.phone as h5_user_phone,
        a.name as assigned_to_name, a.email as assigned_to_email
       FROM feedback_records f
       LEFT JOIN users u ON f.user_id = u.id
       LEFT JOIN h5_users h ON f.h5_user_id = h.id
       LEFT JOIN users a ON f.assigned_to = a.id
       WHERE f.id = ?`,
      [req.params.id]
    )

    if (!rows.length) {
      return res.status(404).json({ code: 404, message: '反馈不存在' })
    }

    const feedback = rows[0]

    // Check permission
    if (!(await checkPerm(req, 'system:config')) &&
        feedback.user_id !== req.user.id &&
        feedback.assigned_to !== req.user.id) {
      return res.status(403).json({ code: 403, message: '权限不足' })
    }

    // Parse images JSON
    if (feedback.images) {
      try {
        feedback.images = JSON.parse(feedback.images)
      } catch {
        feedback.images = []
      }
    } else {
      feedback.images = []
    }

    res.json({ code: 0, data: feedback, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// PUT /api/feedback/:id/assign - Assign feedback (admin only)
router.put('/:id/assign', auth, async (req, res, next) => {
  try {
    if (!(await checkPerm(req, 'system:config'))) {
      return res.status(403).json({ code: 403, message: '权限不足' })
    }

    const { assigned_to } = req.body

    if (!assigned_to) {
      return res.status(400).json({ code: 400, message: '请指定处理人' })
    }

    // Verify user exists
    const [[user]] = await pool.query('SELECT id FROM users WHERE id = ?', [assigned_to])
    if (!user) {
      return res.status(404).json({ code: 404, message: '用户不存在' })
    }

    await pool.query(
      `UPDATE feedback_records SET assigned_to = ?, status = 'processing', updated_at = NOW() WHERE id = ?`,
      [assigned_to, req.params.id]
    )

    res.json({ code: 0, message: '分配成功' })
  } catch (err) {
    next(err)
  }
})

// PUT /api/feedback/:id/reply - Reply to feedback (assigned user or admin)
router.put('/:id/reply', auth, async (req, res, next) => {
  try {
    const { reply } = req.body

    if (!reply) {
      return res.status(400).json({ code: 400, message: '回复内容不能为空' })
    }

    // Check if user is assigned or admin
    const [[feedback]] = await pool.query('SELECT assigned_to FROM feedback_records WHERE id = ?', [req.params.id])

    if (!feedback) {
      return res.status(404).json({ code: 404, message: '反馈不存在' })
    }

    if (!(await checkPerm(req, 'system:config')) && feedback.assigned_to !== req.user.id) {
      return res.status(403).json({ code: 403, message: '权限不足' })
    }

    await pool.query(
      `UPDATE feedback_records SET reply = ?, status = 'resolved', resolved_at = NOW(), updated_at = NOW() WHERE id = ?`,
      [reply, req.params.id]
    )

    res.json({ code: 0, message: '回复成功' })
  } catch (err) {
    next(err)
  }
})

// PUT /api/feedback/:id/close - Close feedback (admin only)
router.put('/:id/close', auth, async (req, res, next) => {
  try {
    if (!(await checkPerm(req, 'system:config'))) {
      return res.status(403).json({ code: 403, message: '权限不足' })
    }

    await pool.query(
      `UPDATE feedback_records SET status = 'closed', updated_at = NOW() WHERE id = ?`,
      [req.params.id]
    )

    res.json({ code: 0, message: '已关闭' })
  } catch (err) {
    next(err)
  }
})

export default router
