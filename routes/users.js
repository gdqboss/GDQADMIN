import { Router } from 'express'
import bcrypt from 'bcryptjs'
import { auth } from '../middleware/auth.js'
import { pool } from '../db/connection.js'
import { checkPerm } from '../utils/permission.js'
import { ROLES } from '../middleware/rbac.js'

const router = Router()

// GET /api/users/roles - 角色列表（兼容老路径，新架构走 /api/rbac/roles）
router.get('/roles', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT r.id, r.name, r.label, r.description, r.sort_order, r.status, r.is_system,
       (SELECT COUNT(*) FROM rbac_role_permissions WHERE role_id=r.id) as permission_count,
       (SELECT COUNT(*) FROM users WHERE role=r.name COLLATE utf8mb4_bin) as user_count
       FROM rbac_roles r ORDER BY r.sort_order, r.id`
    )
    // 同时把每个角色的 permissions 列表也带上（前端 rolePermsMap 用）
    for (const role of rows) {
      const [perms] = await pool.query(
        `SELECT p.name FROM rbac_permissions p
         JOIN rbac_role_permissions rp ON p.id = rp.permission_id
         WHERE rp.role_id = ?`,
        [role.id]
      )
      role.permissions = perms.map(p => p.name)
    }
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// GET /api/users
router.get('/', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT u.id, u.name, u.email, u.role, u.phone, u.department, u.status, u.permissions,
              u.supplier_id, s.name as supplier_name, u.supervisor_id, u.responsibility_id,
              u.require_attendance, u.require_worklog,
              COALESCE(u.department_id, d.id) as department_id,
              d.name as department_name,
              sup.name as supervisor_name, u.last_login, u.created_at
       FROM users u
       LEFT JOIN suppliers s ON u.supplier_id = s.id
       LEFT JOIN users sup ON u.supervisor_id = sup.id
       LEFT JOIN departments d ON d.name = u.department OR d.id = u.department_id
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

      // Load multi-dealer associations
      const [dealerAssocs] = await pool.query(
        `SELECT ud.user_id, ud.dealer_id, d.name as dealer_name
         FROM user_dealers ud
         JOIN dealers d ON ud.dealer_id = d.id
         WHERE ud.user_id IN (?)`,
        [userIds]
      )
      const dealersByUser = {}
      for (const assoc of dealerAssocs) {
        if (!dealersByUser[assoc.user_id]) dealersByUser[assoc.user_id] = []
        dealersByUser[assoc.user_id].push({ id: assoc.dealer_id, name: assoc.dealer_name })
      }

      // Load multi-store associations
      const [storeAssocs] = await pool.query(
        `SELECT us.user_id, us.store_id, s.name as store_name
         FROM user_stores us
         JOIN stores s ON us.store_id = s.id
         WHERE us.user_id IN (?)`,
        [userIds]
      )
      const storesByUser = {}
      for (const assoc of storeAssocs) {
        if (!storesByUser[assoc.user_id]) storesByUser[assoc.user_id] = []
        storesByUser[assoc.user_id].push({ id: assoc.store_id, name: assoc.store_name })
      }

      // Attach to each user
      for (const user of rows) {
        user.suppliers = suppliersByUser[user.id] || []
        user.dealers = dealersByUser[user.id] || []
        user.stores = storesByUser[user.id] || []
      }
    }

    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/users
//
// Bug 修复记录（2026-07-17）：PUT 改成白名单之后，POST 必须同步对齐
// 否则新建员工的 25+ 字段又会被静默丢弃（同样的 bug，这次反向）
router.post('/', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const {
      name, email, password, role, department, department_id, permissions, phone,
      employee_code, job_level_id, is_internal, hire_date, id_card,
      can_oa_checkin, avatar, life_photos,
      customer_store_id, customer_type, customer_parent_id,
      member_level, member_label, points,
      auth_type, supplier_id, supplier_ids, dealer_ids, store_ids,
      supervisor_id, responsibility_id,
      require_attendance, require_worklog
    } = req.body || {}

    if (!name || !phone || !password) {
      return res.status(400).json({ code: 400, message: '姓名、手机号、密码必填' })
    }
    if (!/^1[3-9]\d{9}$/.test(String(phone))) {
      return res.status(400).json({ code: 400, message: '手机号格式不正确' })
    }
    if (String(password).length < 6) {
      return res.status(400).json({ code: 400, message: '密码至少 6 位' })
    }
    if (email !== undefined && email !== null && email !== '' &&
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email))) {
      return res.status(400).json({ code: 400, message: '邮箱格式不正确' })
    }
    if (id_card !== undefined && id_card !== null && id_card !== '' &&
        !/^\d{17}[\dXx]$/.test(String(id_card))) {
      return res.status(400).json({ code: 400, message: '身份证号格式不正确' })
    }
    if (hire_date !== undefined && hire_date !== null && hire_date !== '') {
      const d = new Date(hire_date)
      if (isNaN(d.getTime()) || d.getTime() > Date.now()) {
        return res.status(400).json({ code: 400, message: '入职日期格式不正确或晚于今天' })
      }
    }

    const [existing] = await conn.query('SELECT id FROM users WHERE phone = ?', [phone])
    if (existing.length) {
      return res.status(400).json({ code: 400, message: '该手机号已被注册' })
    }

    const hash = await bcrypt.hash(password, 10)
    const perms = role === 'custom' && Array.isArray(permissions) ? JSON.stringify(permissions) : null
    const autoEmail = email || `${phone}@gdqshop.cn`

    const [result] = await conn.query(
      `INSERT INTO users
       (name, email, password, role, phone,
        employee_code, job_level_id, is_internal, hire_date, id_card,
        can_oa_checkin, avatar, life_photos,
        customer_store_id, customer_type, customer_parent_id,
        member_level, member_label, points,
        auth_type, supplier_id, supervisor_id, responsibility_id,
        require_attendance, require_worklog,
        permissions)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        name, autoEmail, hash, role || ROLES.OPERATOR, phone,
        employee_code || null, job_level_id || null, is_internal ? 1 : 0,
        hire_date || null, id_card || null,
        can_oa_checkin ? 1 : 0, avatar || null, life_photos ? JSON.stringify(life_photos) : null,
        customer_store_id || null, customer_type || null, customer_parent_id || null,
        member_level || 1, member_label || null, points || 0,
        auth_type || 'phone', supplier_id || null, supervisor_id || null, responsibility_id || null,
        require_attendance ? 1 : 0, require_worklog ? 1 : 0,
        perms
      ]
    )

    const userId = result.insertId

    // 处理 department：department_id 优先（写部门外键），否则用 department 字符串（兼容老数据）
    if (department_id !== undefined) {
      await conn.query('UPDATE users SET department_id = ? WHERE id = ?', [department_id || null, userId])
    } else if (department !== undefined && department !== null && department !== '') {
      await conn.query('UPDATE users SET department = ? WHERE id = ?', [department, userId])
    }

    // 多对多关联
    if (Array.isArray(supplier_ids) && supplier_ids.length > 0) {
      const insValues = supplier_ids.map(sid => [userId, sid])
      await conn.query(
        'INSERT INTO user_suppliers (user_id, supplier_id) VALUES ? ON DUPLICATE KEY UPDATE user_id = user_id',
        [insValues]
      )
    }
    if (Array.isArray(dealer_ids) && dealer_ids.length > 0) {
      const insValues = dealer_ids.map(did => [userId, did])
      await conn.query(
        'INSERT INTO user_dealers (user_id, dealer_id) VALUES ? ON DUPLICATE KEY UPDATE user_id = user_id',
        [insValues]
      )
    }
    if (Array.isArray(store_ids) && store_ids.length > 0) {
      const insValues = store_ids.map(sid => [userId, sid])
      await conn.query(
        'INSERT INTO user_stores (user_id, store_id) VALUES ? ON DUPLICATE KEY UPDATE user_id = user_id',
        [insValues]
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
//
// Bug 修复记录（2026-07-17）：
// 1. department_id 被错写到 department 列（导致 UPDATE 含两个 `department = ?`，缺一）
// 2. email / job_level_id / employee_code / is_internal / hire_date / id_card /
//    can_oa_checkin / avatar / customer_store_id / customer_type / member_level /
//    member_label / points / life_photos 等字段被静默丢弃 → 200 假成功
// 3. permissions 在 role 切到非 custom 时被强制 NULL，会清空之前 custom 配置
// 4. 无字段格式校验（phone / email / id_card / hire_date 都能传垃圾）
//
// 字段白名单（与 GET /api/users 返回对齐 + 预留高频扩展字段）；
// 不在白名单的字段直接忽略，避免静默成功。
router.put('/:id', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const id = parseInt(req.params.id)
    if (!Number.isFinite(id) || id <= 0) {
      return res.status(400).json({ code: 400, message: '无效的用户 ID' })
    }

    // ── 1. 字段白名单解构（只接受这 30+ 个字段，其他直接忽略，杜绝静默成功） ──
    const {
      name, role, department, department_id, status, permissions, password, phone,
      email, employee_code, job_level_id, is_internal, hire_date, id_card,
      can_oa_checkin, avatar, life_photos,
      customer_store_id, customer_type, customer_parent_id,
      member_level, member_label, points,
      auth_type, supplier_id, supplier_ids, dealer_ids, store_ids,
      supervisor_id, responsibility_id,
      require_attendance, require_worklog
    } = req.body || {}

    if (status === 'disabled' && id === req.user.id) {
      return res.status(400).json({ code: 400, message: '不能禁用自己的账号' })
    }

    // ── 2. 输入校验（防垃圾数据进库） ──
    if (phone !== undefined) {
      if (!/^1[3-9]\d{9}$/.test(String(phone))) {
        return res.status(400).json({ code: 400, message: '手机号格式不正确（11位，1[3-9]开头）' })
      }
      const [dup] = await conn.query('SELECT id FROM users WHERE phone = ? AND id <> ?', [phone, id])
      if (dup.length) {
        return res.status(400).json({ code: 400, message: '该手机号已被其他用户使用' })
      }
    }
    if (email !== undefined && email !== null && email !== '') {
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email))) {
        return res.status(400).json({ code: 400, message: '邮箱格式不正确' })
      }
      const [dup] = await conn.query('SELECT id FROM users WHERE email = ? AND id <> ?', [email, id])
      if (dup.length) {
        return res.status(400).json({ code: 400, message: '该邮箱已被其他用户使用' })
      }
    }
    if (id_card !== undefined && id_card !== null && id_card !== '') {
      if (!/^\d{17}[\dXx]$/.test(String(id_card))) {
        return res.status(400).json({ code: 400, message: '身份证号格式不正确（18位，最后一位可为 X/x）' })
      }
    }
    if (hire_date !== undefined && hire_date !== null && hire_date !== '') {
      const d = new Date(hire_date)
      if (isNaN(d.getTime())) return res.status(400).json({ code: 400, message: '入职日期格式不正确' })
      if (d.getTime() > Date.now()) return res.status(400).json({ code: 400, message: '入职日期不能晚于今天' })
    }
    if (status !== undefined && !['pending', 'active', 'rejected', 'disabled'].includes(status)) {
      return res.status(400).json({ code: 400, message: '用户状态值不合法' })
    }
    if (customer_type !== undefined && customer_type !== null && !['gov', 'biz', 'peer', 'normal'].includes(customer_type)) {
      return res.status(400).json({ code: 400, message: '顾客类型不合法' })
    }
    if (member_level !== undefined && member_level !== null && (!Number.isInteger(Number(member_level)) || Number(member_level) < 1 || Number(member_level) > 99)) {
      return res.status(400).json({ code: 400, message: '会员等级必须在 1-99 之间' })
    }

    // ── 3. 字段映射 → 白名单 UPDATE ──
    //
    // 关键修复：department_id 写到 department_id 列（不再错写到 department）
    //           department（名字/字符串）仅用于向前兼容老数据，不与 id 共存
    const updates = []
    const values = []

    // 标量字段
    //
    // 注意: life_photos 是 JSON 列, 数组需 JSON.stringify (单独处理, 不能进 scalarMap)
    const scalarMap = {
      name, role, status, phone, email,
      employee_code, hire_date, id_card, avatar,
      customer_type, customer_parent_id, member_label,
      auth_type,
    }
    for (const [col, val] of Object.entries(scalarMap)) {
      if (val !== undefined) {
        updates.push(`${col} = ?`)
        values.push(val === '' ? null : val)
      }
    }
    // life_photos 数组特殊处理 (JSON 列) - 否则 SQL 语法错 (MySQL JSON 列不接受字符串化的数组)
    if (life_photos !== undefined) {
      updates.push('life_photos = ?')
      values.push(life_photos ? JSON.stringify(life_photos) : null)
    }

    // ── department 处理：department_id 优先，写到 department_id 列；department 字符串（兼容）写到 department 列 ──
    if (department_id !== undefined) {
      const v = department_id === '' ? null : department_id
      updates.push('department_id = ?')
      values.push(v)
    }
    if (department !== undefined && department_id === undefined) {
      // 仅在没传 department_id 时才写 department 字符串列（兼容老调用）
      updates.push('department = ?')
      values.push(department === '' ? null : department)
    }

    // 整数 / 布尔 字段（明确 1/0，禁止 null）
    if (job_level_id !== undefined)        { updates.push('job_level_id = ?');        values.push(job_level_id === '' || job_level_id === null ? null : Number(job_level_id)) }
    if (is_internal !== undefined)         { updates.push('is_internal = ?');         values.push(is_internal ? 1 : 0) }
    if (can_oa_checkin !== undefined)      { updates.push('can_oa_checkin = ?');      values.push(can_oa_checkin ? 1 : 0) }
    if (require_attendance !== undefined)  { updates.push('require_attendance = ?');  values.push(require_attendance ? 1 : 0) }
    if (require_worklog !== undefined)     { updates.push('require_worklog = ?');     values.push(require_worklog ? 1 : 0) }

    // NULLable 外键
    if (supplier_id !== undefined)         { updates.push('supplier_id = ?');         values.push(supplier_id || null) }
    if (supervisor_id !== undefined)       { updates.push('supervisor_id = ?');       values.push(supervisor_id || null) }
    if (responsibility_id !== undefined)   { updates.push('responsibility_id = ?');   values.push(responsibility_id || null) }
    if (customer_store_id !== undefined)   { updates.push('customer_store_id = ?');   values.push(customer_store_id || null) }

    // 数值字段
    if (member_level !== undefined)        { updates.push('member_level = ?');        values.push(member_level === '' || member_level === null ? null : Number(member_level)) }
    if (points !== undefined)              { updates.push('points = ?');              values.push(points === '' || points === null ? null : Number(points)) }

    // ── permissions：只明确传了才改；其它情况不动（不强制 NULL） ──
    if (Array.isArray(permissions)) {
      updates.push('permissions = ?')
      values.push(JSON.stringify(permissions))
    }

    // 密码（独立 hash 路径）
    if (password) {
      if (String(password).length < 6) {
        return res.status(400).json({ code: 400, message: '密码至少 6 位' })
      }
      const hash = await bcrypt.hash(password, 10)
      updates.push('password = ?')
      values.push(hash)
    }

    if (updates.length > 0) {
      values.push(id)
      await conn.query(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`, values)
    }

    // ── 4. 多对多关联表（与原行为一致） ──
    if (Array.isArray(supplier_ids)) {
      await conn.query('DELETE FROM user_suppliers WHERE user_id = ?', [id])
      if (supplier_ids.length > 0) {
        const insValues = supplier_ids.map(sid => [id, sid])
        await conn.query(
          'INSERT INTO user_suppliers (user_id, supplier_id) VALUES ? ON DUPLICATE KEY UPDATE user_id = user_id',
          [insValues]
        )
      }
    }
    if (Array.isArray(dealer_ids)) {
      await conn.query('DELETE FROM user_dealers WHERE user_id = ?', [id])
      if (dealer_ids.length > 0) {
        const insValues = dealer_ids.map(did => [id, did])
        await conn.query(
          'INSERT INTO user_dealers (user_id, dealer_id) VALUES ? ON DUPLICATE KEY UPDATE user_id = user_id',
          [insValues]
        )
      }
    }
    if (Array.isArray(store_ids)) {
      await conn.query('DELETE FROM user_stores WHERE user_id = ?', [id])
      if (store_ids.length > 0) {
        const insValues = store_ids.map(sid => [id, sid])
        await conn.query(
          'INSERT INTO user_stores (user_id, store_id) VALUES ? ON DUPLICATE KEY UPDATE user_id = user_id',
          [insValues]
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
    if (req.user.role !== ROLES.ADMIN) {
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
      'INSERT INTO job_levels (name, level, description, responsibility_desc) VALUES (?, ?, ?, ?)',
      [name, level, description || null, req.body.responsibility_desc || null]
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

    if (responsibility_desc !== undefined) {
      updates.push('responsibility_desc = ?')
      values.push(responsibility_desc)
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
