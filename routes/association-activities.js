/**
 * 协会活动 (activities + 报名 registrations)
 * - GET /api/association/activities - 公开列表
 * - GET /api/association/activities/admin - admin 列表
 * - GET /api/association/activities/:id - 详情
 * - POST /api/association/activities - 新建 (write)
 * - PUT /api/association/activities/:id - 更新 (write)
 * - DELETE /api/association/activities/:id - 删除 (delete)
 * - POST /api/association/activities/:id/register - 用户报名
 * - GET /api/association/activities/:id/registrations - 报名列表 (write)
 */
import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js'
import { requireOptionalPermission } from '../middleware/require-optional-permission.js'

const router = Router()

function getServerProfileId(req) {
  return Number(req.query.server_profile_id || req.body.server_profile_id || 7)
}

function buildWhere(serverProfileId, status) {
  return { where: 'WHERE server_profile_id = ? AND status = ?', params: [serverProfileId, status] }
}

// 公开列表 (仅 open + future)
router.get('/', requireOptionalPermission(PERMISSIONS.ASSOCIATION_ACTIVITIES_READ), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const { keyword, category } = req.query
    const { page, size } = parsePagination(req.query)

    let { where, params } = buildWhere(sp, 'open')
    where += ' AND start_time >= NOW()'
    if (category) { where += ' AND category = ?'; params.push(category) }
    if (keyword) { where += ' AND (title LIKE ? OR description LIKE ?)'; const kw = `%${keyword}%`; params.push(kw, kw) }

    const sql = `SELECT * FROM association_activities ${where} ORDER BY start_time ASC LIMIT ? OFFSET ?`
    const countSql = `SELECT COUNT(*) as total FROM association_activities ${where}`
    const [[{ total }]] = await pool.query(countSql, params)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// admin 列表 (含全部状态)
router.get('/admin', requirePermission(PERMISSIONS.ASSOCIATION_ACTIVITIES_WRITE), async (req, res, next) => {
  try {
    const sp = getServerProfileId(req)
    const { keyword, status, category } = req.query
    const { page, size } = parsePagination(req.query)

    let where = 'WHERE server_profile_id = ?'
    const params = [sp]
    if (status) { where += ' AND status = ?'; params.push(status) }
    if (category) { where += ' AND category = ?'; params.push(category) }
    if (keyword) { where += ' AND (title LIKE ? OR description LIKE ?)'; const kw = `%${keyword}%`; params.push(kw, kw) }

    const sql = `SELECT * FROM association_activities ${where} ORDER BY start_time DESC LIMIT ? OFFSET ?`
    const countSql = `SELECT COUNT(*) as total FROM association_activities ${where}`
    const [[{ total }]] = await pool.query(countSql, params)
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// 详情
router.get('/:id', requireOptionalPermission(PERMISSIONS.ASSOCIATION_ACTIVITIES_READ), async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT * FROM association_activities WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '活动不存在' })
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 新建
router.post('/', requirePermission(PERMISSIONS.ASSOCIATION_ACTIVITIES_WRITE), async (req, res, next) => {
  try {
    const sp = req.body.server_profile_id || 7
    const {
      title, subtitle, description, cover_image, location,
      start_time, end_time, registration_deadline, max_participants,
      fee, status, category, organizer, contact_person, contact_phone
    } = req.body
    if (!title) return res.status(400).json({ code: 400, message: '标题必填' })
    if (!start_time) return res.status(400).json({ code: 400, message: '开始时间必填' })
    const [result] = await pool.query(
      `INSERT INTO association_activities
        (server_profile_id, title, subtitle, description, cover_image, location,
         start_time, end_time, registration_deadline, max_participants,
         fee, status, category, organizer, contact_person, contact_phone)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [sp, title, subtitle || '', description || '', cover_image || '', location || '',
       start_time, end_time || null, registration_deadline || null, max_participants || 0,
       fee || 0, status || 'draft', category || 'general', organizer || '', contact_person || '', contact_phone || '']
    )
    const [[row]] = await pool.query('SELECT * FROM association_activities WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 更新
router.put('/:id', requirePermission(PERMISSIONS.ASSOCIATION_ACTIVITIES_WRITE), async (req, res, next) => {
  try {
    const [[exists]] = await pool.query('SELECT id FROM association_activities WHERE id = ?', [req.params.id])
    if (!exists) return res.status(404).json({ code: 404, message: '活动不存在' })
    const {
      title, subtitle, description, cover_image, location,
      start_time, end_time, registration_deadline, max_participants,
      fee, status, category, organizer, contact_person, contact_phone
    } = req.body
    await pool.query(
      `UPDATE association_activities SET
        title = COALESCE(?, title), subtitle = COALESCE(?, subtitle),
        description = COALESCE(?, description), cover_image = COALESCE(?, cover_image),
        location = COALESCE(?, location), start_time = COALESCE(?, start_time),
        end_time = COALESCE(?, end_time), registration_deadline = COALESCE(?, registration_deadline),
        max_participants = COALESCE(?, max_participants), fee = COALESCE(?, fee),
        status = COALESCE(?, status), category = COALESCE(?, category),
        organizer = COALESCE(?, organizer), contact_person = COALESCE(?, contact_person),
        contact_phone = COALESCE(?, contact_phone)
       WHERE id = ?`,
      [title, subtitle, description, cover_image, location, start_time, end_time, registration_deadline, max_participants, fee, status, category, organizer, contact_person, contact_phone, req.params.id]
    )
    const [[row]] = await pool.query('SELECT * FROM association_activities WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// 删除 (拒绝有报名时删除)
router.delete('/:id', requirePermission(PERMISSIONS.ASSOCIATION_ACTIVITIES_DELETE), async (req, res, next) => {
  try {
    const [[reg]] = await pool.query('SELECT COUNT(*) as n FROM activity_registrations WHERE activity_id = ? AND status != ?', [req.params.id, 'cancelled'])
    if (reg.n > 0) return res.status(400).json({ code: 400, message: '该活动存在有效报名,无法删除,请先取消活动' })
    await pool.query('DELETE FROM association_activities WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

// 报名
router.post('/:id/register', requirePermission(PERMISSIONS.ASSOCIATION_ACTIVITIES_READ), async (req, res, next) => {
  try {
    const [[activity]] = await pool.query('SELECT * FROM association_activities WHERE id = ?', [req.params.id])
    if (!activity) return res.status(404).json({ code: 404, message: '活动不存在' })
    if (activity.status !== 'open') return res.status(400).json({ code: 400, message: '活动未开放报名' })
    if (activity.registration_deadline && new Date(activity.registration_deadline) < new Date()) {
      return res.status(400).json({ code: 400, message: '已过报名截止时间' })
    }
    if (activity.max_participants > 0 && activity.current_participants >= activity.max_participants) {
      return res.status(400).json({ code: 400, message: '名额已满' })
    }

    const { member_name, member_phone, member_company, member_title, remarks } = req.body
    if (!member_name || !member_phone) return res.status(400).json({ code: 400, message: '姓名与电话必填' })

    const [result] = await pool.query(
      `INSERT INTO activity_registrations
        (activity_id, server_profile_id, member_id, member_name, member_phone, member_company, member_title, remarks)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [req.params.id, activity.server_profile_id, req.user?.id || null, member_name, member_phone, member_company || '', member_title || '', remarks || '']
    )
    // 人数+1
    await pool.query('UPDATE association_activities SET current_participants = current_participants + 1 WHERE id = ?', [req.params.id])
    const [[reg]] = await pool.query('SELECT * FROM activity_registrations WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: reg, message: '报名成功' })
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') return res.status(400).json({ code: 400, message: '您已报名过该活动' })
    next(err)
  }
})

// 报名列表
router.get('/:id/registrations', requirePermission(PERMISSIONS.ASSOCIATION_ACTIVITIES_WRITE), async (req, res, next) => {
  try {
    const { status } = req.query
    let where = 'WHERE activity_id = ?'
    const params = [req.params.id]
    if (status) { where += ' AND status = ?'; params.push(status) }
    const sql = `SELECT * FROM activity_registrations ${where} ORDER BY created_at DESC`
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total: rows.length }, message: 'ok' })
  } catch (err) { next(err) }
})

// 报名状态流转 (admin)
router.put('/:id/registrations/:regId', requirePermission(PERMISSIONS.ASSOCIATION_ACTIVITIES_WRITE), async (req, res, next) => {
  try {
    const { status, admin_remark } = req.body
    const updateAt = status === 'confirmed' ? 'confirmed_at = NOW()' :
                      status === 'cancelled' ? 'cancelled_at = NOW()' : 'updated_at = NOW()'
    await pool.query(
      `UPDATE activity_registrations SET status = COALESCE(?, status), admin_remark = COALESCE(?, admin_remark), ${updateAt} WHERE id = ? AND activity_id = ?`,
      [status, admin_remark, req.params.regId, req.params.id]
    )
    const [[row]] = await pool.query('SELECT * FROM activity_registrations WHERE id = ?', [req.params.regId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

export default router