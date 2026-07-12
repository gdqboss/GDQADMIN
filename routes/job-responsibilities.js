import { Router } from 'express'
import { pool } from '../db/connection.js'
import { checkPerm } from '../utils/permission.js'

const router = Router()

// GET /api/job-responsibilities - 获取职位权责列表（支持筛选）
router.get('/', async (req, res, next) => {
  try {
    const { job_level_id, department_id, category } = req.query

    let sql = `
      SELECT
        jr.*,
        jl.name as job_level_name,
        d.name as department_name
      FROM job_responsibilities jr
      LEFT JOIN job_levels jl ON jr.job_level_id = jl.id
      LEFT JOIN departments d ON jr.department_id = d.id
      WHERE 1=1
    `
    const params = []

    if (job_level_id) {
      sql += ' AND jr.job_level_id = ?'
      params.push(job_level_id)
    }

    if (department_id) {
      sql += ' AND (jr.department_id = ? OR jr.department_id IS NULL)'
      params.push(department_id)
    }

    if (category) {
      sql += ' AND jr.category = ?'
      params.push(category)
    }

    sql += ' ORDER BY jr.sort_order ASC, jr.id ASC'

    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// GET /api/job-responsibilities/my - 获取当前用户的职位权责
router.get('/my', async (req, res, next) => {
  try {
    const userId = req.user.id

    // 获取用户的职级和部门
    const [[user]] = await pool.query(
      'SELECT job_level_id, department_id FROM users WHERE id = ?',
      [userId]
    )

    if (!user || !user.job_level_id) {
      return res.json({ code: 0, data: [], message: '未设置职级' })
    }

    // 获取该职级的权责（包括通用的和特定部门的）
    const [rows] = await pool.query(
      `SELECT
        jr.*,
        jl.name as job_level_name,
        d.name as department_name
      FROM job_responsibilities jr
      LEFT JOIN job_levels jl ON jr.job_level_id = jl.id
      LEFT JOIN departments d ON jr.department_id = d.id
      WHERE jr.job_level_id = ?
        AND (jr.department_id IS NULL OR jr.department_id = ?)
      ORDER BY jr.category, jr.sort_order ASC, jr.id ASC`,
      [user.job_level_id, user.department_id]
    )

    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// POST /api/job-responsibilities - 创建职位权责（管理员）
router.post('/', async (req, res, next) => {
  try {
    // 权限检查：仅管理员可操作
    if (!(await checkPerm(req, 'system:config'))) {
      return res.status(403).json({ code: 403, message: '无权限操作' })
    }

    const { job_level_id, department_id, title, description, category, sort_order } = req.body

    if (!job_level_id || !title) {
      return res.status(400).json({ code: 400, message: '职级和标题必填' })
    }

    // 验证职级是否存在
    const [[jobLevel]] = await pool.query('SELECT id FROM job_levels WHERE id = ?', [job_level_id])
    if (!jobLevel) {
      return res.status(400).json({ code: 400, message: '职级不存在' })
    }

    // 如果指定了部门，验证部门是否存在
    if (department_id) {
      const [[dept]] = await pool.query('SELECT id FROM departments WHERE id = ?', [department_id])
      if (!dept) {
        return res.status(400).json({ code: 400, message: '部门不存在' })
      }
    }

    const [result] = await pool.query(
      `INSERT INTO job_responsibilities
        (job_level_id, department_id, title, description, category, sort_order)
      VALUES (?, ?, ?, ?, ?, ?)`,
      [job_level_id, department_id || null, title, description || '', category || 'duty', sort_order || 0]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '创建成功' })
  } catch (err) {
    next(err)
  }
})

// PUT /api/job-responsibilities/:id - 更新职位权责（管理员）
router.put('/:id', async (req, res, next) => {
  try {
    // 权限检查：仅管理员可操作
    if (!(await checkPerm(req, 'system:config'))) {
      return res.status(403).json({ code: 403, message: '无权限操作' })
    }

    const { id } = req.params
    const { job_level_id, department_id, title, description, category, sort_order } = req.body

    // 检查记录是否存在
    const [[existing]] = await pool.query('SELECT id FROM job_responsibilities WHERE id = ?', [id])
    if (!existing) {
      return res.status(404).json({ code: 404, message: '记录不存在' })
    }

    if (!title) {
      return res.status(400).json({ code: 400, message: '标题必填' })
    }

    await pool.query(
      `UPDATE job_responsibilities
      SET job_level_id = ?, department_id = ?, title = ?, description = ?, category = ?, sort_order = ?
      WHERE id = ?`,
      [job_level_id, department_id || null, title, description || '', category || 'duty', sort_order || 0, id]
    )

    res.json({ code: 0, data: null, message: '更新成功' })
  } catch (err) {
    next(err)
  }
})

// DELETE /api/job-responsibilities/:id - 删除职位权责（管理员）
router.delete('/:id', async (req, res, next) => {
  try {
    // 权限检查：仅管理员可操作
    if (!(await checkPerm(req, 'system:config'))) {
      return res.status(403).json({ code: 403, message: '无权限操作' })
    }

    const { id } = req.params

    const [[existing]] = await pool.query('SELECT id FROM job_responsibilities WHERE id = ?', [id])
    if (!existing) {
      return res.status(404).json({ code: 404, message: '记录不存在' })
    }

    await pool.query('DELETE FROM job_responsibilities WHERE id = ?', [id])

    res.json({ code: 0, data: null, message: '删除成功' })
  } catch (err) {
    next(err)
  }
})

export default router
