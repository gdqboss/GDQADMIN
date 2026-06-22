import { Router } from 'express'
import { pool } from '../db/connection.js'
import { checkPerm } from '../utils/permission.js'
import { ROLES } from '../middleware/rbac.js'

const router = Router()

// Helper function to check if user can access task
async function canAccessTask(userId, userRole, taskId) {
  const [[task]] = await pool.query(
    'SELECT assigned_to, assigned_by FROM tasks WHERE id = ?',
    [taskId]
  )
  if (!task) return false

  // Admin can access all tasks
  if (userRole === ROLES.ADMIN) return true

  // User can access tasks assigned to them or by them
  if (task.assigned_to === userId || task.assigned_by === userId) return true

  return false
}

// Helper function to check if assignerUserId is in the supervisor chain of targetUserId
async function isInSupervisorChain(assignerUserId, targetUserId) {
  // Recursively check supervisor chain
  let currentUserId = targetUserId
  const visited = new Set() // Prevent infinite loops

  while (currentUserId) {
    if (visited.has(currentUserId)) break // Circular reference detected
    visited.add(currentUserId)

    const [[user]] = await pool.query(
      'SELECT supervisor_id FROM users WHERE id = ?',
      [currentUserId]
    )

    if (!user) break
    if (user.supervisor_id === assignerUserId) return true

    currentUserId = user.supervisor_id
  }

  return false
}

// GET /api/tasks/my - 获取我的任务（被指派给我的）
router.get('/my', async (req, res, next) => {
  try {
    const { status, priority, page = 1, limit = 20 } = req.query
    const offset = (page - 1) * limit

    let whereClause = 'WHERE t.assigned_to = ?'
    const params = [req.user.id]

    if (status) {
      whereClause += ' AND t.status = ?'
      params.push(status)
    }

    if (priority) {
      whereClause += ' AND t.priority = ?'
      params.push(priority)
    }

    const [rows] = await pool.query(
      `SELECT t.*,
              u1.name as assigned_to_name,
              u2.name as assigned_by_name
       FROM tasks t
       LEFT JOIN users u1 ON t.assigned_to = u1.id
       LEFT JOIN users u2 ON t.assigned_by = u2.id
       ${whereClause}
       ORDER BY
         FIELD(t.priority, 'urgent', 'high', 'medium', 'low'),
         t.due_date ASC,
         t.created_at DESC
       LIMIT ? OFFSET ?`,
      [...params, parseInt(limit), parseInt(offset)]
    )

    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM tasks t ${whereClause}`,
      params
    )

    res.json({
      code: 0,
      data: {
        tasks: rows,
        total,
        page: parseInt(page),
        limit: parseInt(limit)
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// GET /api/tasks/assigned - 获取我指派的任务
router.get('/assigned', async (req, res, next) => {
  try {
    const { status, priority, page = 1, limit = 20 } = req.query
    const offset = (page - 1) * limit

    let whereClause = 'WHERE t.assigned_by = ?'
    const params = [req.user.id]

    if (status) {
      whereClause += ' AND t.status = ?'
      params.push(status)
    }

    if (priority) {
      whereClause += ' AND t.priority = ?'
      params.push(priority)
    }

    const [rows] = await pool.query(
      `SELECT t.*,
              u1.name as assigned_to_name,
              u2.name as assigned_by_name
       FROM tasks t
       LEFT JOIN users u1 ON t.assigned_to = u1.id
       LEFT JOIN users u2 ON t.assigned_by = u2.id
       ${whereClause}
       ORDER BY
         FIELD(t.priority, 'urgent', 'high', 'medium', 'low'),
         t.due_date ASC,
         t.created_at DESC
       LIMIT ? OFFSET ?`,
      [...params, parseInt(limit), parseInt(offset)]
    )

    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM tasks t ${whereClause}`,
      params
    )

    res.json({
      code: 0,
      data: {
        tasks: rows,
        total,
        page: parseInt(page),
        limit: parseInt(limit)
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// GET /api/tasks/all - 获取所有任务（仅管理员）
// GET /api/tasks — 任务管理根路径（Dashboard.vue L322 调 /tasks?page=1&limit=1 拿任务数）
// 根据用户角色分流：管理员看全部，普通用户看自己的
router.get('/', async (req, res, next) => {
  try {
    const isAdmin = await checkPerm(req, 'system:config')
    if (isAdmin) {
      // 管理员：转给 /all 逻辑（不直接复用避免循环）
      req.url = req.url.replace(/^\?/, '') + (req.url.includes('?') ? '&' : '?') + '_admin=1'
    } else {
      // 普通用户：转给 /my 逻辑
      req.url = req.url.replace(/^\?/, '') + (req.url.includes('?') ? '&' : '?') + '_my=1'
    }
    // 简单实现：直接调数据库
    const {
      status, priority, assigned_to, assigned_by,
      date_start, date_end, keyword,
      page = 1, pageSize = 20, size = 20, limit,
    } = req.query
    const where = []
    const params = []
    if (!isAdmin) {
      where.push('(t.assigned_to = ? OR t.assigned_by = ?)')
      params.push(req.user.id, req.user.id)
    }
    if (status) { where.push('t.status = ?'); params.push(status) }
    if (priority) { where.push('t.priority = ?'); params.push(priority) }
    if (assigned_to) { where.push('t.assigned_to = ?'); params.push(assigned_to) }
    if (assigned_by) { where.push('t.assigned_by = ?'); params.push(assigned_by) }
    if (date_start) { where.push('DATE(t.created_at) >= ?'); params.push(date_start) }
    if (date_end) { where.push('DATE(t.created_at) <= ?'); params.push(date_end) }
    if (keyword) { where.push('(t.title LIKE ? OR t.description LIKE ?)'); params.push(`%${keyword}%`, `%${keyword}%`) }

    const whereSql = where.length ? 'WHERE ' + where.join(' AND ') : ''
    const pageNum = Math.max(1, parseInt(page) || 1)
    const pageSizeNum = Math.min(100, Math.max(1, parseInt(size || limit || pageSize) || 20))
    const offset = (pageNum - 1) * pageSizeNum

    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) AS total FROM tasks t ${whereSql}`, params
    )
    const [rows] = await pool.query(
      `SELECT t.*, u1.name AS assigned_to_name, u2.name AS assigned_by_name
       FROM tasks t
       LEFT JOIN users u1 ON t.assigned_to = u1.id
       LEFT JOIN users u2 ON t.assigned_by = u2.id
       ${whereSql}
       ORDER BY t.created_at DESC
       LIMIT ? OFFSET ?`,
      [...params, pageSizeNum, offset]
    )
    res.json({ code: 0, data: { list: rows, total, page: pageNum, size: pageSizeNum } })
  } catch (err) { next(err) }
})

router.get('/all', async (req, res, next) => {
  try {
    if (!(await checkPerm(req, 'system:config'))) {
      return res.status(403).json({ code: 403, message: '仅管理员可查看所有任务' })
    }

    const {
      status,
      priority,
      assigned_to,
      assigned_by,
      date_start,
      date_end,
      keyword,
      page = 1,
      limit = 20
    } = req.query
    const offset = (page - 1) * limit

    let whereClause = 'WHERE 1=1'
    const params = []

    if (status && status !== 'all') {
      whereClause += ' AND t.status = ?'
      params.push(status)
    }

    if (priority && priority !== 'all') {
      whereClause += ' AND t.priority = ?'
      params.push(priority)
    }

    if (assigned_to) {
      whereClause += ' AND t.assigned_to = ?'
      params.push(assigned_to)
    }

    if (assigned_by) {
      whereClause += ' AND t.assigned_by = ?'
      params.push(assigned_by)
    }

    if (date_start) {
      whereClause += ' AND DATE(t.created_at) >= ?'
      params.push(date_start)
    }

    if (date_end) {
      whereClause += ' AND DATE(t.created_at) <= ?'
      params.push(date_end)
    }

    if (keyword) {
      whereClause += ' AND (t.title LIKE ? OR t.description LIKE ?)'
      const kw = `%${keyword}%`
      params.push(kw, kw)
    }

    const [rows] = await pool.query(
      `SELECT t.*,
              u1.name as assigned_to_name,
              u2.name as assigned_by_name
       FROM tasks t
       LEFT JOIN users u1 ON t.assigned_to = u1.id
       LEFT JOIN users u2 ON t.assigned_by = u2.id
       ${whereClause}
       ORDER BY
         FIELD(t.priority, 'urgent', 'high', 'medium', 'low'),
         t.due_date ASC,
         t.created_at DESC
       LIMIT ? OFFSET ?`,
      [...params, parseInt(limit), parseInt(offset)]
    )

    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM tasks t ${whereClause}`,
      params
    )

    res.json({
      code: 0,
      data: {
        tasks: rows,
        total,
        page: parseInt(page),
        limit: parseInt(limit)
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// GET /api/tasks/stats - 任务统计
router.get('/stats', async (req, res, next) => {
  try {
    const userId = req.user.id
    const userRole = req.user.role

    let whereClause = ''
    let params = []

    if (userRole === ROLES.ADMIN) {
      whereClause = 'WHERE 1=1'
    } else {
      whereClause = 'WHERE (assigned_to = ? OR assigned_by = ?)'
      params = [userId, userId]
    }

    // 我的任务统计
    const [[myStats]] = await pool.query(
      `SELECT
         COUNT(*) as total,
         SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
         SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
         SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
         SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
         SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
         SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
         SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('completed', 'rejected') THEN 1 ELSE 0 END) as overdue
       FROM tasks
       WHERE assigned_to = ?`,
      [userId]
    )

    // 我指派的任务统计
    const [[assignedStats]] = await pool.query(
      `SELECT
         COUNT(*) as total,
         SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
         SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
         SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
         SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
         SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
       FROM tasks
       WHERE assigned_by = ?`,
      [userId]
    )

    res.json({
      code: 0,
      data: {
        myTasks: myStats,
        assignedTasks: assignedStats
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// GET /api/tasks/unread-count - 获取当前用户新任务数量（红点用）
router.get('/unread-count', async (req, res, next) => {
  try {
    const [[{ count }]] = await pool.query(
      'SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND is_new = 1 AND status IN ("pending", "in_progress")',
      [req.user.id]
    )
    res.json({ code: 0, data: { count } })
  } catch (err) { next(err) }
})

// POST /api/tasks/:id/mark-read - 标记任务为已读
router.post('/:id/mark-read', async (req, res, next) => {
  try {
    await pool.query(
      'UPDATE tasks SET is_new = 0 WHERE id = ? AND assigned_to = ?',
      [req.params.id, req.user.id]
    )
    res.json({ code: 0, message: '已标记已读' })
  } catch (err) { next(err) }
})

// POST /api/tasks/mark-all-read - 标记当前用户所有新任务为已读（进入"我的任务"Tab时调用）
router.post('/mark-all-read', async (req, res, next) => {
  try {
    await pool.query(
      'UPDATE tasks SET is_new = 0 WHERE assigned_to = ? AND is_new = 1',
      [req.user.id]
    )
    res.json({ code: 0, message: '全部已标记已读' })
  } catch (err) { next(err) }
})

// GET /api/tasks/:id - 获取任务详情
router.get('/:id', async (req, res, next) => {
  try {
    const taskId = req.params.id

    const [[task]] = await pool.query(
      `SELECT t.*,
              u1.name as assigned_to_name,
              u2.name as assigned_by_name,
              u2.name as created_by_name,
              u3.name as reviewed_by_name
       FROM tasks t
       LEFT JOIN users u1 ON t.assigned_to = u1.id
       LEFT JOIN users u2 ON t.assigned_by = u2.id
       LEFT JOIN users u3 ON t.reviewed_by = u3.id
       WHERE t.id = ?`,
      [taskId]
    )

    if (!task) {
      return res.status(404).json({ code: 404, message: '任务不存在' })
    }

    // Check access permission
    const hasAccess = await canAccessTask(req.user.id, req.user.role, taskId)
    if (!hasAccess) {
      return res.status(403).json({ code: 403, message: '无权访问此任务' })
    }

    // Get attachments
    const [attachments] = await pool.query(
      `SELECT a.*, u.name as uploaded_by_name
       FROM task_attachments a
       LEFT JOIN users u ON a.uploaded_by = u.id
       WHERE a.task_id = ?
       ORDER BY a.created_at DESC`,
      [taskId]
    )

    task.attachments = attachments

    res.json({ code: 0, data: task, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/tasks - 创建任务
router.post('/', async (req, res, next) => {
  try {
    const { title, description, assigned_to, due_date, priority } = req.body

    if (!title || !assigned_to) {
      return res.status(400).json({ code: 400, message: '标题和指派对象必填' })
    }

    // Check if assigned_to user exists
    const [[user]] = await pool.query('SELECT id FROM users WHERE id = ?', [assigned_to])
    if (!user) {
      return res.status(400).json({ code: 400, message: '指派对象不存在' })
    }

    // Check permission: admin can assign to anyone, others can only assign to their subordinates
    if (!(await checkPerm(req, 'system:config'))) {
      const isSubordinate = await isInSupervisorChain(req.user.id, assigned_to)
      if (!isSubordinate) {
        return res.status(403).json({ code: 403, message: '只能给自己的下级或下下级分配任务' })
      }
    }

    const [result] = await pool.query(
      `INSERT INTO tasks (title, description, assigned_to, assigned_by, created_by, due_date, priority, status, is_new)
       VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 1)`,
      [title, description || null, assigned_to, req.user.id, req.user.id, due_date || null, priority || 'medium']
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '任务创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/tasks/:id - 更新任务
router.put('/:id', async (req, res, next) => {
  try {
    const taskId = req.params.id
    const { title, content, assigned_to, scheduled_date, due_date, priority, status } = req.body

    // Check if task exists and user has permission
    const [[task]] = await pool.query('SELECT assigned_by, status FROM tasks WHERE id = ?', [taskId])
    if (!task) {
      return res.status(404).json({ code: 404, message: '任务不存在' })
    }

    // Only task creator or admin can update
    if (task.assigned_by !== req.user.id && !(await checkPerm(req, 'system:config'))) {
      return res.status(403).json({ code: 403, message: '无权修改此任务' })
    }

    const updates = []
    const values = []

    if (title !== undefined) { updates.push('title = ?'); values.push(title) }
    if (content !== undefined) { updates.push('description = ?'); values.push(content) }
    if (assigned_to !== undefined) { updates.push('assigned_to = ?'); values.push(assigned_to) }
    if (due_date !== undefined) { updates.push('due_date = ?'); values.push(due_date) }
    if (priority !== undefined) { updates.push('priority = ?'); values.push(priority) }
    if (status !== undefined) { updates.push('status = ?'); values.push(status) }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有更新内容' })
    }

    values.push(taskId)
    await pool.query(`UPDATE tasks SET ${updates.join(', ')} WHERE id = ?`, values)

    res.json({ code: 0, data: null, message: '任务更新成功' })
  } catch (err) { next(err) }
})

// PUT /api/tasks/:id/submit - 提交任务（员工）
router.put('/:id/submit', async (req, res, next) => {
  try {
    const taskId = req.params.id
    const { completion_notes, completion_note, attachments } = req.body
    const finalNote = completion_notes || completion_note || '' // 兼容两种命名

    const [[task]] = await pool.query(
      'SELECT assigned_to, status FROM tasks WHERE id = ?',
      [taskId]
    )

    if (!task) {
      return res.status(404).json({ code: 404, message: '任务不存在' })
    }

    // Only assigned user can submit
    if (task.assigned_to !== req.user.id) {
      return res.status(403).json({ code: 403, message: '只能提交指派给自己的任务' })
    }

    // Check status
    if (task.status === 'completed') {
      return res.status(400).json({ code: 400, message: '任务已完成，无需提交' })
    }

    if (task.status === 'submitted') {
      return res.status(400).json({ code: 400, message: '任务已提交，等待审核' })
    }

    await pool.query(
      `UPDATE tasks
       SET status = 'submitted',
           completion_note = ?,
           submitted_at = NOW()
       WHERE id = ?`,
      [finalNote || null, taskId]
    )

    // 保存附件到数据库
    if (attachments) {
      let attachmentList = []
      try {
        attachmentList = typeof attachments === 'string' ? JSON.parse(attachments) : attachments
      } catch (e) {
        attachmentList = []
      }

      if (Array.isArray(attachmentList) && attachmentList.length > 0) {
        const attachmentValues = attachmentList.map(url => [
          taskId,
          url,
          url.split('/').pop(), // 文件名
          req.user.id
        ])

        await pool.query(
          'INSERT INTO task_attachments (task_id, file_path, file_name, uploaded_by) VALUES ?',
          [attachmentValues]
        )
      }
    }

    res.json({ code: 0, data: null, message: '任务已提交，等待审核' })
  } catch (err) { next(err) }
})

// PUT /api/tasks/:id/complete - 确认完成（上级）
router.put('/:id/complete', async (req, res, next) => {
  try {
    const taskId = req.params.id
    const { review_note } = req.body

    const [[task]] = await pool.query(
      'SELECT assigned_by, status FROM tasks WHERE id = ?',
      [taskId]
    )

    if (!task) {
      return res.status(404).json({ code: 404, message: '任务不存在' })
    }

    // Only task creator or admin can complete
    if (task.assigned_by !== req.user.id && !(await checkPerm(req, 'system:config'))) {
      return res.status(403).json({ code: 403, message: '只能审核自己指派的任务' })
    }

    // Check status
    if (task.status !== 'submitted') {
      return res.status(400).json({ code: 400, message: '只能审核已提交的任务' })
    }

    await pool.query(
      `UPDATE tasks
       SET status = 'completed',
           review_note = ?,
           completed_at = NOW()
       WHERE id = ?`,
      [review_note || null, taskId]
    )

    res.json({ code: 0, data: null, message: '任务已确认完成' })
  } catch (err) { next(err) }
})

// PUT /api/tasks/:id/reject - 驳回任务（上级）
router.put('/:id/reject', async (req, res, next) => {
  try {
    const taskId = req.params.id
    const { review_note } = req.body

    if (!review_note) {
      return res.status(400).json({ code: 400, message: '请填写驳回原因' })
    }

    const [[task]] = await pool.query(
      'SELECT assigned_by, status FROM tasks WHERE id = ?',
      [taskId]
    )

    if (!task) {
      return res.status(404).json({ code: 404, message: '任务不存在' })
    }

    // Only task creator or admin can reject
    if (task.assigned_by !== req.user.id && !(await checkPerm(req, 'system:config'))) {
      return res.status(403).json({ code: 403, message: '只能驳回自己指派的任务' })
    }

    // Check status
    if (task.status !== 'submitted') {
      return res.status(400).json({ code: 400, message: '只能驳回已提交的任务' })
    }

    await pool.query(
      `UPDATE tasks
       SET status = 'rejected',
           review_note = ?
       WHERE id = ?`,
      [review_note, taskId]
    )

    res.json({ code: 0, data: null, message: '任务已驳回' })
  } catch (err) { next(err) }
})

// PUT /api/tasks/:id/review - 审核任务（批准或拒绝）
router.put('/:id/review', async (req, res, next) => {
  try {
    const taskId = req.params.id
    const { action, review_notes } = req.body // action: 'approve' 或 'reject'

    if (!['approve', 'reject'].includes(action)) {
      return res.status(400).json({ code: 400, message: '无效的审核操作' })
    }

    // 检查任务是否存在且为submitted状态
    const [[task]] = await pool.query(
      'SELECT * FROM tasks WHERE id = ?',
      [taskId]
    )

    if (!task) {
      return res.status(404).json({ code: 404, message: '任务不存在' })
    }

    if (task.status !== 'submitted') {
      return res.status(400).json({ code: 400, message: '只能审核已提交的任务' })
    }

    // 检查权限：只有指派人可以审核
    if (task.assigned_by !== req.user.id && !(await checkPerm(req, 'system:config'))) {
      return res.status(403).json({ code: 403, message: '无权审核此任务' })
    }

    const newStatus = action === 'approve' ? 'completed' : 'rejected'
    const now = new Date()

    await pool.query(
      `UPDATE tasks
       SET status = ?,
           review_note = ?,
           reviewed_by = ?,
           reviewed_at = ?
       WHERE id = ?`,
      [newStatus, review_notes || null, req.user.id, now, taskId]
    )

    res.json({ code: 0, message: action === 'approve' ? '任务已批准' : '任务已拒绝' })
  } catch (err) {
    next(err)
  }
})

// DELETE /api/tasks/:id - 删除任务
router.delete('/:id', async (req, res, next) => {
  try {
    const taskId = req.params.id

    const [[task]] = await pool.query(
      'SELECT assigned_by FROM tasks WHERE id = ?',
      [taskId]
    )

    if (!task) {
      return res.status(404).json({ code: 404, message: '任务不存在' })
    }

    // Only task creator or admin can delete
    if (task.assigned_by !== req.user.id && !(await checkPerm(req, 'system:config'))) {
      return res.status(403).json({ code: 403, message: '只能删除自己创建的任务' })
    }

    // Delete attachments first
    await pool.query('DELETE FROM task_attachments WHERE task_id = ?', [taskId])

    // Delete task
    await pool.query('DELETE FROM tasks WHERE id = ?', [taskId])

    res.json({ code: 0, data: null, message: '任务已删除' })
  } catch (err) { next(err) }
})

export default router
