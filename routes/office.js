import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// GET /api/office/tasks - 办公端聚合任务列表 (前端 minip/pages/task-list 用)
// scope: mine | assigned | all
// 返回结构: { code:0, data: { list: [...], total, scope } }
router.get('/tasks', async (req, res, next) => {
  try {
    const scope = (req.query.scope || 'mine').toString()
    const page = Math.max(1, parseInt(req.query.page) || 1)
    const limit = Math.min(100, parseInt(req.query.limit) || 20)
    const offset = (page - 1) * limit

    let where = 'WHERE 1=1'
    const params = []
    if (scope === 'mine') {
      where += ' AND t.assigned_to = ?'
      params.push(req.user.id)
    } else if (scope === 'assigned') {
      where += ' AND t.assigned_by = ?'
      params.push(req.user.id)
    } else if (scope === 'all') {
      // all scope 仅 admin / hod / manager 角色可见全部, 其他只看自己相关的
      const role = (req.user.role || '').toString()
      if (!['admin', 'superuser', 'hod', 'general_manager', 'team_leader'].includes(role)) {
        where += ' AND (t.assigned_to = ? OR t.assigned_by = ?)'
        params.push(req.user.id, req.user.id)
      }
    }

    const [rows] = await pool.query(
      `SELECT t.*,
              u1.name as assigned_to_name,
              u2.name as assigned_by_name
       FROM tasks t
       LEFT JOIN users u1 ON t.assigned_to = u1.id
       LEFT JOIN users u2 ON t.assigned_by = u2.id
       ${where}
       ORDER BY
         FIELD(t.priority, 'urgent', 'high', 'medium', 'low'),
         t.due_date ASC,
         t.created_at DESC
       LIMIT ? OFFSET ?`,
      [...params, limit, offset]
    );
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM tasks t ${where}`,
      params
    )

    res.json({
      code: 0,
      data: { list: rows, total, page, limit, scope }
    })
  } catch (err) { next(err) }
})

export default router