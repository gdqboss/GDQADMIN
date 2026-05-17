import { Router } from 'express'
import bcrypt from 'bcryptjs'
import { auth } from '../middleware/auth.js'
import { pool } from '../db/connection.js'

const router = Router()

// GET /api/users
router.get('/', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT u.id, u.name, u.email, u.role, u.phone, u.department, u.status, u.permissions,
              u.supplier_id, s.name as supplier_name, u.supervisor_id,
              sup.name as supervisor_name, u.last_login, u.created_at
       FROM users u
       LEFT JOIN suppliers s ON u.supplier_id = s.id
       LEFT JOIN users sup ON u.supervisor_id = sup.id
       ORDER BY u.created_at DESC`
    )

    // Load multi-supplier associations for each user
    if (rows.length > 0) {
      const userIds = rows.map(r => r.id)
      const [associations] = await pool.query(
        `SELECT us.user_id, us.supplier_id, s.name as supplier_name
         FROM user_suppliers us
         JOIN suppliers s ON us.supplier_id = s.id
         WHERE us.user_id IN (?)`,
        [userIds]
      )

      // Group by user_id
      const suppliersByUser = {}
      for (const assoc of associations) {
        if (!suppliersByUser[assoc.user_id]) suppliersByUser[assoc.user_id] = []
        suppliersByUser[assoc.user_id].push({
          id: assoc.supplier_id,
          name: assoc.supplier_name
        })
      }

      // Attach to each user
      for (const user of rows) {
        user.suppliers = suppliersByUser[user.id] || []
      }
    }

    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/users
router.post('/', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { name, email, password, role, department, permissions, phone, supplier_id, supplier_ids, supervisor_id } = req.body
    if (!name || !phone || !password) {
      return res.status(400).json({ code: 400, message: '姓名、手机号、密码必填' })
    }
    if (!/^1[3-9]\d{9}$/.test(phone)) {
      return res.status(400).json({ code: 400, message: '手机号格式不正确' })
    }
    const [existing] = await conn.query('SELECT id FROM users WHERE phone = ?', [phone])
    if (existing.length) {
      return res.status(400).json({ code: 400, message: '该手机号已被注册' })
    }
    const hash = await bcrypt.hash(password, 10)
    const perms = role === 'custom' && Array.isArray(permissions) ? JSON.stringify(permissions) : null
    const [result] = await conn.query(
      'INSERT INTO users (name, email, password, role, department, permissions, phone, supplier_id, supervisor_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [name, email || null, hash, role || 'operator', department || '', perms, phone, supplier_id || null, supervisor_id || null]
    )

    const userId = result.insertId

    // Handle multi-supplier associations
    if (Array.isArray(supplier_ids) && supplier_ids.length > 0) {
      const values = supplier_ids.map(sid => [userId, sid])
      await conn.query(
        'INSERT INTO user_suppliers (user_id, supplier_id) VALUES ? ON DUPLICATE KEY UPDATE user_id = user_id',
        [values]
      )
    }

    await conn.commit()
    res.json({ code: 0, data: { id: userId }, message: '用户创建成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// GET /api/users/list - 简化用户列表（所有认证用户可访问）
router.get('/list', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, name, phone, department, role, supervisor_id FROM users WHERE status = 'active' ORDER BY name`
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/users/subordinates - 递归查询当前用户及所有下级
router.get('/subordinates', async (req, res, next) => {
  try {
    const userId = req.user.id

    // 递归查询所有下级（通过 supervisor_id）
    const [subordinates] = await pool.query(`
      WITH RECURSIVE subordinate_tree AS (
        SELECT id, name, phone, department, role, supervisor_id, 1 as level
        FROM users
        WHERE supervisor_id = ? AND status = 'active'

        UNION ALL

        SELECT u.id, u.name, u.phone, u.department, u.role, u.supervisor_id, st.level + 1
        FROM users u
        INNER JOIN subordinate_tree st ON u.supervisor_id = st.id
        WHERE st.level < 10 AND u.status = 'active'
      )
      SELECT * FROM subordinate_tree
      ORDER BY level, name
    `, [userId])

    // 当前用户放在列表顶部
    const [[currentUser]] = await pool.query(
      'SELECT id, name, phone, department, role FROM users WHERE id = ?',
      [userId]
    )

    const result = currentUser
      ? [{ ...currentUser, level: 0, is_self: true }, ...subordinates]
      : subordinates

    res.json({ code: 0, data: result, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/users/:id
router.put('/:id', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const id = parseInt(req.params.id)
    const { name, role, department, status, permissions, password, phone, supplier_id, supplier_ids, supervisor_id } = req.body

    if (status === 'disabled' && id === req.user.id) {
      return res.status(400).json({ code: 400, message: '不能禁用自己的账号' })
    }

    const updates = []
    const values = []
    if (name !== undefined)        { updates.push('name = ?');        values.push(name) }
    if (role !== undefined)        { updates.push('role = ?');        values.push(role) }
    if (department !== undefined)  { updates.push('department = ?');  values.push(department) }
    if (status !== undefined)      { updates.push('status = ?');      values.push(status) }
    if (phone !== undefined)       { updates.push('phone = ?');       values.push(phone) }
    if (supplier_id !== undefined) { updates.push('supplier_id = ?'); values.push(supplier_id || null) }
    if (supervisor_id !== undefined) { updates.push('supervisor_id = ?'); values.push(supervisor_id || null) }

    if (role === 'custom' && Array.isArray(permissions)) {
      updates.push('permissions = ?')
      values.push(JSON.stringify(permissions))
    } else if (role !== undefined && role !== 'custom') {
      updates.push('permissions = NULL')
    }

    if (password) {
      const hash = await bcrypt.hash(password, 10)
      updates.push('password = ?')
      values.push(hash)
    }

    if (updates.length > 0) {
      values.push(id)
      await conn.query(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`, values)
    }

    // Handle multi-supplier associations
    if (Array.isArray(supplier_ids)) {
      // Delete existing associations
      await conn.query('DELETE FROM user_suppliers WHERE user_id = ?', [id])

      // Insert new associations
      if (supplier_ids.length > 0) {
        const values = supplier_ids.map(sid => [id, sid])
        await conn.query(
          'INSERT INTO user_suppliers (user_id, supplier_id) VALUES ? ON DUPLICATE KEY UPDATE user_id = user_id',
          [values]
        )
      }
    }

    await conn.commit()
    res.json({ code: 0, data: null, message: '更新成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ── Roles CRUD ─────────────────────────────────────────────────────────────────

// GET /api/roles
router.get('/roles', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM roles ORDER BY is_system DESC, id ASC')
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/roles
router.post('/roles', async (req, res, next) => {
  try {
    const { name, label, permissions } = req.body
    if (!name || !label) return res.status(400).json({ code: 400, message: 'name 和 label 必填' })
    const [result] = await pool.query(
      'INSERT INTO roles (name, label, permissions, is_system) VALUES (?,?,?,0)',
      [name, label, permissions ? JSON.stringify(permissions) : JSON.stringify([])]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '角色创建成功' })
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') return res.status(400).json({ code: 400, message: '角色名已存在' })
    next(err)
  }
})

// PUT /api/roles/:id
router.put('/roles/:id', async (req, res, next) => {
  try {
    const { label, permissions } = req.body
    const [[role]] = await pool.query('SELECT is_system FROM roles WHERE id = ?', [req.params.id])
    if (!role) return res.status(404).json({ code: 404, message: '角色不存在' })
    const updates = []
    const values = []
    if (label !== undefined) { updates.push('label = ?'); values.push(label) }
    if (permissions !== undefined) { updates.push('permissions = ?'); values.push(JSON.stringify(permissions)) }
    if (!updates.length) return res.status(400).json({ code: 400, message: '没有更新内容' })
    values.push(req.params.id)
    await pool.query(`UPDATE roles SET ${updates.join(', ')} WHERE id = ?`, values)
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE /api/roles/:id - 删除角色（超级管理员/member不可删）
router.delete('/roles/:id', async (req, res, next) => {
  try {
    const roleId = parseInt(req.params.id)
    // 仅超级管理员(id=1)不可删除
    if (roleId === 1) {
      return res.status(400).json({ code: 400, message: '超级管理员不可删除' })
    }
    const [[role]] = await pool.query('SELECT id, name FROM roles WHERE id = ?', [roleId])
    if (!role) return res.status(404).json({ code: 404, message: '角色不存在' })

    // 先把关联用户 reassign 到"公司员工"角色(member)，如果不存在则先创建
    let [[defaultRole]] = await pool.query("SELECT name FROM roles WHERE name = 'member' LIMIT 1")
    if (!defaultRole) {
      await pool.query("INSERT INTO roles (name, label, is_system) VALUES ('member', '公司员工', 0)")
      ;[[defaultRole]] = await pool.query("SELECT name FROM roles WHERE name = 'member' LIMIT 1")
    }
    await pool.query('UPDATE users SET role = ? WHERE role = ?', [defaultRole.name, role.name])

    await pool.query('DELETE FROM roles WHERE id = ?', [roleId])
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/users/pending - 获取待审核的员工申请
router.get('/pending', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, name, phone, id_card, email, applied_at, reject_reason
       FROM users
       WHERE status = 'pending'
       ORDER BY applied_at DESC`
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/users/:id/approve - 审核通过员工申请
router.put('/:id/approve', async (req, res, next) => {
  try {
    const userId = req.params.id
    const approverId = req.user.id

    // 检查用户是否存在且状态为pending
    const [[user]] = await pool.query('SELECT id, status FROM users WHERE id = ?', [userId])
    if (!user) {
      return res.status(404).json({ code: 404, message: '用户不存在' })
    }
    if (user.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '该用户不在待审核状态' })
    }

    // 更新状态为active
    await pool.query(
      `UPDATE users
       SET status = 'active', approved_at = NOW(), approved_by = ?, reject_reason = NULL
       WHERE id = ?`,
      [approverId, userId]
    )

    res.json({ code: 0, data: null, message: '审核通过' })
  } catch (err) { next(err) }
})

// PUT /api/users/:id/reject - 拒绝员工申请
router.put('/:id/reject', async (req, res, next) => {
  try {
    const userId = req.params.id
    const approverId = req.user.id
    const { reason } = req.body

    if (!reason) {
      return res.status(400).json({ code: 400, message: '请填写拒绝原因' })
    }

    // 检查用户是否存在且状态为pending
    const [[user]] = await pool.query('SELECT id, status FROM users WHERE id = ?', [userId])
    if (!user) {
      return res.status(404).json({ code: 404, message: '用户不存在' })
    }
    if (user.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '该用户不在待审核状态' })
    }

    // 更新状态为rejected
    await pool.query(
      `UPDATE users
       SET status = 'rejected', approved_at = NOW(), approved_by = ?, reject_reason = ?
       WHERE id = ?`,
      [approverId, reason, userId]
    )

    res.json({ code: 0, data: null, message: '已拒绝申请' })
  } catch (err) { next(err) }
})

// DELETE /api/users/:id - 删除用户（仅超级管理员，且用户必须是停用状态）
router.delete('/:id', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const userId = parseInt(req.params.id)

    // 只有超级管理员可以删除用户
    if (req.user.role !== 'admin') {
      await conn.release()
      return res.status(403).json({ code: 403, message: '只有超级管理员可以删除用户' })
    }

    // 不能删除自己
    if (userId === req.user.id) {
      await conn.release()
      return res.status(400).json({ code: 400, message: '不能删除自己的账号' })
    }

    // 检查用户是否存在
    const [[user]] = await conn.query('SELECT id, status FROM users WHERE id = ?', [userId])
    if (!user) {
      await conn.release()
      return res.status(404).json({ code: 404, message: '用户不存在' })
    }

    // 只能删除停用状态的用户
    if (user.status !== 'disabled') {
      await conn.release()
      return res.status(400).json({ code: 400, message: '只能删除已停用的用户' })
    }

    await conn.beginTransaction()

    // 禁用外键检查，删除用户及其所有关联
    await conn.query("SET FOREIGN_KEY_CHECKS = 0")
    
    // 删除所有关联表数据
    await conn.query("DELETE FROM user_suppliers WHERE user_id = ?", [userId])
    await conn.query("DELETE FROM reminder_settings WHERE user_id = ?", [userId])
    await conn.query("DELETE FROM wecom_contacts WHERE user_id = ?", [userId])
    await conn.query("DELETE FROM attendance WHERE user_id = ?", [userId])
    await conn.query("DELETE FROM leave_records WHERE user_id = ?", [userId])
    await conn.query("DELETE FROM share_logs WHERE user_id = ?", [userId])
    await conn.query("DELETE FROM visit_logs WHERE user_id = ?", [userId])
    await conn.query("DELETE FROM work_logs WHERE user_id = ?", [userId])
    await conn.query("DELETE FROM work_log_interactions WHERE user_id = ?", [userId])
    await conn.query("DELETE FROM work_log_participants WHERE user_id = ?", [userId])
    await conn.query("DELETE FROM tasks WHERE assigned_to = ? OR assigned_by = ?", [userId, userId])
    
    // 删除用户
    await conn.query("DELETE FROM users WHERE id = ?", [userId])
    
    // 恢复外键检查
    await conn.query("SET FOREIGN_KEY_CHECKS = 1")

    await conn.commit()
    res.json({ code: 0, data: null, message: '用户已删除' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ── Departments CRUD ───────────────────────────────────────────────────────

// GET /api/users/departments - 获取所有部门
router.get('/departments/list', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT d.*, u.name as manager_name
       FROM departments d
       LEFT JOIN users u ON d.manager_id = u.id
       ORDER BY d.sort_order ASC, d.id ASC`
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/users/departments - 创建部门
router.post('/departments/create', async (req, res, next) => {
  try {
    const { name, parent_id, manager_id, sort_order } = req.body
    if (!name) {
      return res.status(400).json({ code: 400, message: '部门名称必填' })
    }

    const [result] = await pool.query(
      'INSERT INTO departments (name, parent_id, manager_id, sort_order) VALUES (?, ?, ?, ?)',
      [name, parent_id || null, manager_id || null, sort_order || 0]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '部门创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/users/departments/:id - 更新部门
router.put('/departments/:id', async (req, res, next) => {
  try {
    const { name, parent_id, manager_id, sort_order, status } = req.body
    const updates = []
    const values = []

    if (name !== undefined) { updates.push('name = ?'); values.push(name) }
    if (parent_id !== undefined) { updates.push('parent_id = ?'); values.push(parent_id || null) }
    if (manager_id !== undefined) { updates.push('manager_id = ?'); values.push(manager_id || null) }
    if (sort_order !== undefined) { updates.push('sort_order = ?'); values.push(sort_order) }
    if (status !== undefined) { updates.push('status = ?'); values.push(status) }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有更新内容' })
    }

    values.push(req.params.id)
    await pool.query(`UPDATE departments SET ${updates.join(', ')} WHERE id = ?`, values)

    res.json({ code: 0, data: null, message: '部门更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/users/departments/:id - 删除部门
router.delete('/departments/:id', async (req, res, next) => {
  try {
    // 检查是否有子部门
    const [[child]] = await pool.query('SELECT id FROM departments WHERE parent_id = ?', [req.params.id])
    if (child) {
      return res.status(400).json({ code: 400, message: '该部门下有子部门，无法删除' })
    }

    // 检查是否有员工
    const [[user]] = await pool.query('SELECT id FROM users WHERE department_id = ?', [req.params.id])
    if (user) {
      return res.status(400).json({ code: 400, message: '该部门下有员工，无法删除' })
    }

    await pool.query('DELETE FROM departments WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: '部门已删除' })
  } catch (err) { next(err) }
})

// ── Job Levels CRUD ────────────────────────────────────────────────────────

// GET /api/users/job-levels - 获取所有职级
router.get('/job-levels/list', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM job_levels ORDER BY level DESC')
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/users/job-levels - 创建职级
router.post('/job-levels/create', async (req, res, next) => {
  try {
    const { name, level, description } = req.body
    if (!name || level === undefined) {
      return res.status(400).json({ code: 400, message: '职级名称和等级必填' })
    }

    // 检查名称是否存在
    const [[existingName]] = await pool.query(
      'SELECT id FROM job_levels WHERE name = ?', [name]
    )
    if (existingName) {
      return res.status(400).json({
        code: 400,
        message: `职级名称"${name}"已存在，请使用不同的名称`
      })
    }

    // 检查等级是否存在
    const [[existingLevel]] = await pool.query(
      'SELECT id, name FROM job_levels WHERE level = ?', [level]
    )
    if (existingLevel) {
      return res.status(400).json({
        code: 400,
        message: `等级${level}已被"${existingLevel.name}"使用，请选择其他等级`
      })
    }

    const [result] = await pool.query(
      'INSERT INTO job_levels (name, level, description) VALUES (?, ?, ?)',
      [name, level, description || null]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '职级创建成功' })
  } catch (err) {
    next(err)
  }
})

// PUT /api/users/job-levels/:id - 更新职级
router.put('/job-levels/:id', async (req, res, next) => {
  try {
    const { name, level, description } = req.body
    const updates = []
    const values = []

    // 检查名称冲突
    if (name !== undefined) {
      const [[existingName]] = await pool.query(
        'SELECT id FROM job_levels WHERE name = ? AND id != ?', [name, req.params.id]
      )
      if (existingName) {
        return res.status(400).json({
          code: 400,
          message: `职级名称"${name}"已存在，请使用不同的名称`
        })
      }
      updates.push('name = ?')
      values.push(name)
    }

    // 检查等级冲突
    if (level !== undefined) {
      const [[existingLevel]] = await pool.query(
        'SELECT id, name FROM job_levels WHERE level = ? AND id != ?', [level, req.params.id]
      )
      if (existingLevel) {
        return res.status(400).json({
          code: 400,
          message: `等级${level}已被"${existingLevel.name}"使用，请选择其他等级`
        })
      }
      updates.push('level = ?')
      values.push(level)
    }

    if (description !== undefined) {
      updates.push('description = ?')
      values.push(description)
    }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有更新内容' })
    }

    values.push(req.params.id)
    await pool.query(`UPDATE job_levels SET ${updates.join(', ')} WHERE id = ?`, values)

    res.json({ code: 0, data: null, message: '职级更新成功' })
  } catch (err) {
    next(err)
  }
})

// DELETE /api/users/job-levels/:id - 删除职级
router.delete('/job-levels/:id', async (req, res, next) => {
  try {
    // 检查是否有员工使用该职级
    const [[user]] = await pool.query('SELECT id FROM users WHERE job_level_id = ?', [req.params.id])
    if (user) {
      return res.status(400).json({ code: 400, message: '该职级下有员工，无法删除' })
    }

    await pool.query('DELETE FROM job_levels WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: '职级已删除' })
  } catch (err) { next(err) }
})


// 获取职位权责列表
router.get('/responsibilities/list', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM job_responsibilities ORDER BY sort_order')
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

export default router
