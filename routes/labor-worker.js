// labor-worker.js — 工人档案 / 工地 / 派工 / 评价 API
// Layer 3: 人工录入 API + Layer 4 数据基础
// 路径前缀: /api/labor-worker (worker_profiles)
//           /api/labor-jobsites (jobsites)
//           /api/labor-dispatch (派工)
//           /api/labor-evaluations (评价)
import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission, PERMISSIONS as P } from '../middleware/rbac.js'

const workerRouter = express.Router()
const jobsiteRouter = express.Router()
const dispatchRouter = express.Router()
const evalRouter = express.Router()

// 所有路由要求登录
;[workerRouter, jobsiteRouter, dispatchRouter, evalRouter].forEach((r) => r.use(auth))

// ============================================================
// worker_profiles CRUD — worker:read / write / delete
// ============================================================

// GET /api/labor-worker — 列出工人档案（支持 ?jobsite=&skill_level=&employment_status=&q=）
workerRouter.get('/', requirePermission(P.WORKER_READ), async (req, res, next) => {
  try {
    const { jobsite_id, skill_level, employment_status, q } = req.query
    const where = []
    const params = []
    if (jobsite_id) { where.push('wp.current_jobsite_id = ?'); params.push(jobsite_id) }
    if (skill_level) { where.push('wp.skill_level = ?'); params.push(skill_level) }
    if (employment_status) { where.push('wp.employment_status = ?'); params.push(employment_status) }
    if (q) { where.push('(u.name LIKE ? OR u.phone LIKE ? OR wp.id_card LIKE ?)'); params.push(`%${q}%`, `%${q}%`, `%${q}%`) }

    const sql = `
      SELECT wp.*, u.name AS user_name, u.phone AS user_phone, u.role AS user_role,
             j.code AS jobsite_code, j.name AS jobsite_name
      FROM worker_profiles wp
      JOIN users u ON u.id = wp.user_id
      LEFT JOIN jobsites j ON j.id = wp.current_jobsite_id
      ${where.length ? 'WHERE ' + where.join(' AND ') : ''}
      ORDER BY wp.updated_at DESC
      LIMIT 500`
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

// GET /api/labor-worker/me — 当前登录用户的工人档案
workerRouter.get('/me', requirePermission(P.WORKER_READ), async (req, res, next) => {
  try {
    const userId = req.user.id
    const [rows] = await pool.query(
      `SELECT wp.*, j.code AS jobsite_code, j.name AS jobsite_name
       FROM worker_profiles wp
       LEFT JOIN jobsites j ON j.id = wp.current_jobsite_id
       WHERE wp.user_id = ?`,
      [userId]
    )
    res.json({ code: 0, data: rows[0] || null })
  } catch (e) { next(e) }
})

// GET /api/labor-worker/:id — 单个工人档案
workerRouter.get('/:id', requirePermission(P.WORKER_READ), async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT wp.*, u.name AS user_name, u.phone AS user_phone, u.role AS user_role,
              j.code AS jobsite_code, j.name AS jobsite_name
       FROM worker_profiles wp
       JOIN users u ON u.id = wp.user_id
       LEFT JOIN jobsites j ON j.id = wp.current_jobsite_id
       WHERE wp.id = ?`,
      [req.params.id]
    )
    if (!rows[0]) return res.status(404).json({ code: 404, message: '工人档案不存在' })
    res.json({ code: 0, data: rows[0] })
  } catch (e) { next(e) }
})

// POST /api/labor-worker — HR 创建工人档案
workerRouter.post('/', requirePermission(P.WORKER_WRITE), async (req, res, next) => {
  try {
    const {
      user_id, skills, skill_level, hourly_rate, piece_rate, monthly_salary, payment_type,
      current_jobsite_id, id_card, emergency_contact, emergency_phone, certificates,
      contract_start, contract_end, employment_status, hired_at, notes
    } = req.body || {}

    if (!user_id) return res.status(400).json({ code: 400, message: 'user_id 必填' })

    // 检查 user_id 是否存在
    const [[u]] = await pool.query('SELECT id, name FROM users WHERE id = ?', [user_id])
    if (!u) return res.status(400).json({ code: 400, message: '用户不存在' })

    const [result] = await pool.query(
      `INSERT INTO worker_profiles
       (user_id, skills, skill_level, hourly_rate, piece_rate, monthly_salary, payment_type,
        current_jobsite_id, id_card, emergency_contact, emergency_phone, certificates,
        contract_start, contract_end, employment_status, hired_at, notes, created_at, updated_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())`,
      [
        user_id,
        skills ? JSON.stringify(skills) : null,
        skill_level || 'rookie',
        hourly_rate || 0,
        piece_rate ? JSON.stringify(piece_rate) : null,
        monthly_salary || 0,
        payment_type || 'hourly',
        current_jobsite_id || null,
        id_card || null,
        emergency_contact || null,
        emergency_phone || null,
        certificates ? JSON.stringify(certificates) : null,
        contract_start || null,
        contract_end || null,
        employment_status || 'active',
        hired_at || new Date().toISOString().slice(0, 10),
        notes || null
      ]
    )
    res.json({ code: 0, data: { id: result.insertId, user_id, user_name: u.name } })
  } catch (e) {
    if (e.code === 'ER_DUP_ENTRY') return res.status(400).json({ code: 400, message: '该用户已有工人档案' })
    next(e)
  }
})

// PATCH /api/labor-worker/:id — 更新工人档案
workerRouter.patch('/:id', requirePermission(P.WORKER_WRITE), async (req, res, next) => {
  try {
    const id = req.params.id
    const allowed = ['skills', 'skill_level', 'hourly_rate', 'piece_rate', 'monthly_salary',
                     'payment_type', 'current_jobsite_id', 'id_card', 'emergency_contact',
                     'emergency_phone', 'certificates', 'contract_start', 'contract_end',
                     'employment_status', 'hired_at', 'resigned_at', 'notes']
    const fields = []
    const params = []
    for (const k of allowed) {
      if (req.body[k] !== undefined) {
        fields.push(`${k} = ?`)
        const v = req.body[k]
        if (['skills', 'piece_rate', 'certificates'].includes(k) && typeof v === 'object') {
          params.push(JSON.stringify(v))
        } else {
          params.push(v)
        }
      }
    }
    if (!fields.length) return res.status(400).json({ code: 400, message: '无字段可更新' })
    params.push(id)
    await pool.query(`UPDATE worker_profiles SET ${fields.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: { id } })
  } catch (e) { next(e) }
})

// DELETE /api/labor-worker/:id — 删除工人档案
workerRouter.delete('/:id', requirePermission(P.WORKER_DELETE), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM worker_profiles WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: { id: req.params.id } })
  } catch (e) { next(e) }
})

// ============================================================
// jobsites CRUD — jobsite:read / write / delete
// ============================================================

// GET /api/labor-jobsites — 工地列表（支持过滤）
jobsiteRouter.get('/', requirePermission(P.JOBSITE_READ), async (req, res, next) => {
  try {
    const { status, type, manager_user_id, q } = req.query
    const where = []
    const params = []
    if (status) { where.push('j.status = ?'); params.push(status) }
    if (type) { where.push('j.type = ?'); params.push(type) }
    if (manager_user_id) { where.push('j.manager_user_id = ?'); params.push(manager_user_id) }
    if (q) { where.push('(j.name LIKE ? OR j.code LIKE ? OR j.address LIKE ? OR j.client_name LIKE ?)'); params.push(`%${q}%`, `%${q}%`, `%${q}%`, `%${q}%`) }

    const sql = `
      SELECT j.*, u.name AS manager_name, u.phone AS manager_phone,
             (SELECT COUNT(*) FROM worker_profiles wp WHERE wp.current_jobsite_id = j.id AND wp.employment_status = 'active') AS active_workers
      FROM jobsites j
      LEFT JOIN users u ON u.id = j.manager_user_id
      ${where.length ? 'WHERE ' + where.join(' AND ') : ''}
      ORDER BY j.status = 'active' DESC, j.updated_at DESC
      LIMIT 500`
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

// GET /api/labor-jobsites/:id — 工地详情
jobsiteRouter.get('/:id', requirePermission(P.JOBSITE_READ), async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT j.*, u.name AS manager_name, u.phone AS manager_phone
       FROM jobsites j
       LEFT JOIN users u ON u.id = j.manager_user_id
       WHERE j.id = ?`,
      [req.params.id]
    )
    if (!rows[0]) return res.status(404).json({ code: 404, message: '工地不存在' })

    // 该工地下的工人
    const [workers] = await pool.query(
      `SELECT wp.*, u.name AS user_name, u.phone AS user_phone
       FROM worker_profiles wp
       JOIN users u ON u.id = wp.user_id
       WHERE wp.current_jobsite_id = ?`,
      [req.params.id]
    )

    res.json({ code: 0, data: { ...rows[0], workers } })
  } catch (e) { next(e) }
})

// POST /api/labor-jobsites — 创建工地
jobsiteRouter.post('/', requirePermission(P.JOBSITE_WRITE), async (req, res, next) => {
  try {
    const {
      code, name, address, client_name, client_phone, manager_user_id, supervisor_user_id,
      start_date, expected_end_date, contract_amount, status, type, area_sqm,
      gps_lat, gps_lng, gps_radius_m, blueprints, contract_docs,
      required_workers, required_skills, progress_percent, notes
    } = req.body || {}

    if (!code || !name) return res.status(400).json({ code: 400, message: 'code/name 必填' })

    const [result] = await pool.query(
      `INSERT INTO jobsites
       (code, name, address, client_name, client_phone, manager_user_id, supervisor_user_id,
        start_date, expected_end_date, contract_amount, status, type, area_sqm,
        gps_lat, gps_lng, gps_radius_m, blueprints, contract_docs,
        required_workers, required_skills, progress_percent, notes, created_by, created_at, updated_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())`,
      [
        code, name, address || null, client_name || null, client_phone || null,
        manager_user_id || null, supervisor_user_id || null,
        start_date || null, expected_end_date || null, contract_amount || 0,
        status || 'planning', type || 'decoration', area_sqm || null,
        gps_lat || null, gps_lng || null, gps_radius_m || 100,
        blueprints ? JSON.stringify(blueprints) : null,
        contract_docs ? JSON.stringify(contract_docs) : null,
        required_workers || 1,
        required_skills ? JSON.stringify(required_skills) : null,
        progress_percent || 0,
        notes || null,
        req.user.id
      ]
    )
    res.json({ code: 0, data: { id: result.insertId, code, name } })
  } catch (e) {
    if (e.code === 'ER_DUP_ENTRY') return res.status(400).json({ code: 400, message: '工地编号已存在' })
    next(e)
  }
})

// PATCH /api/labor-jobsites/:id — 更新工地
jobsiteRouter.patch('/:id', requirePermission(P.JOBSITE_WRITE), async (req, res, next) => {
  try {
    const id = req.params.id
    const allowed = ['name', 'address', 'client_name', 'client_phone', 'manager_user_id', 'supervisor_user_id',
                     'start_date', 'expected_end_date', 'actual_end_date', 'contract_amount',
                     'status', 'type', 'area_sqm', 'gps_lat', 'gps_lng', 'gps_radius_m',
                     'blueprints', 'contract_docs', 'required_workers', 'required_skills',
                     'progress_percent', 'notes']
    const fields = []
    const params = []
    for (const k of allowed) {
      if (req.body[k] !== undefined) {
        fields.push(`${k} = ?`)
        const v = req.body[k]
        if (['blueprints', 'contract_docs', 'required_skills'].includes(k) && typeof v === 'object') {
          params.push(JSON.stringify(v))
        } else {
          params.push(v)
        }
      }
    }
    if (!fields.length) return res.status(400).json({ code: 400, message: '无字段可更新' })
    params.push(id)
    await pool.query(`UPDATE jobsites SET ${fields.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: { id } })
  } catch (e) { next(e) }
})

// DELETE /api/labor-jobsites/:id — 删除工地
jobsiteRouter.delete('/:id', requirePermission(P.JOBSITE_DELETE), async (req, res, next) => {
  try {
    // 检查是否有人在该工地
    const [[{ cnt }]] = await pool.query(
      'SELECT COUNT(*) AS cnt FROM worker_profiles WHERE current_jobsite_id = ?',
      [req.params.id]
    )
    if (cnt > 0) return res.status(400).json({ code: 400, message: `该工地下还有 ${cnt} 名工人,无法删除` })
    await pool.query('DELETE FROM jobsites WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: { id: req.params.id } })
  } catch (e) { next(e) }
})

// ============================================================
// 派工: 把工人分配到工地 — dispatch:read / write (派工无 delete, 调岗用 PATCH 或新派工覆盖)
// ============================================================

// POST /api/labor-dispatch — 班组长/主管派工
dispatchRouter.post('/', requirePermission(P.DISPATCH_WRITE), async (req, res, next) => {
  try {
    const { worker_profile_id, jobsite_id, note } = req.body || {}
    if (!worker_profile_id || !jobsite_id) {
      return res.status(400).json({ code: 400, message: 'worker_profile_id/jobsite_id 必填' })
    }

    // 校验工人和工地都存在
    const [[wp]] = await pool.query('SELECT user_id, current_jobsite_id FROM worker_profiles WHERE id = ?', [worker_profile_id])
    if (!wp) return res.status(400).json({ code: 400, message: '工人档案不存在' })
    const [[js]] = await pool.query('SELECT id, name, required_workers FROM jobsites WHERE id = ?', [jobsite_id])
    if (!js) return res.status(400).json({ code: 400, message: '工地不存在' })

    // 容量检查
    const [[{ cnt }]] = await pool.query(
      `SELECT COUNT(*) AS cnt FROM worker_profiles
       WHERE current_jobsite_id = ? AND employment_status = 'active'`,
      [jobsite_id]
    )
    if (wp.current_jobsite_id !== jobsite_id && cnt >= js.required_workers) {
      return res.status(400).json({ code: 400, message: `工地「${js.name}」已达人数上限 (${js.required_workers})` })
    }

    const from = wp.current_jobsite_id
    await pool.query(
      'UPDATE worker_profiles SET current_jobsite_id = ?, updated_at = NOW() WHERE id = ?',
      [jobsite_id, worker_profile_id]
    )

    // 记录派工历史 (复用 approvals 表)
    await pool.query(
      `INSERT INTO approvals (type, ref_id, status, applicant_id, approver_id, reason, created_at, updated_at)
       VALUES ('labor_dispatch', ?, 'approved', ?, ?, ?, NOW(), NOW())`,
      [worker_profile_id, req.user.id, req.user.id, note || `派工: 从 #${from || '无'} 到 #${jobsite_id}`]
    )

    res.json({ code: 0, data: { worker_profile_id, from_jobsite_id: from, to_jobsite_id: jobsite_id } })
  } catch (e) { next(e) }
})

// GET /api/labor-dispatch — 派工记录列表
dispatchRouter.get('/', requirePermission(P.DISPATCH_READ), async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT a.*, u.name AS applicant_name, w.user_id AS worker_user_id, u2.name AS worker_name, js.name AS jobsite_name
       FROM approvals a
       JOIN worker_profiles w ON w.id = a.ref_id
       JOIN users u2 ON u2.id = w.user_id
       LEFT JOIN users u ON u.id = a.applicant_id
       LEFT JOIN jobsites js ON js.id = w.current_jobsite_id
       WHERE a.type = 'labor_dispatch'
       ORDER BY a.created_at DESC
       LIMIT 200`
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

// ============================================================
// 上级评价 — eval:read / write (评价无 delete, 历史评价不能改)
// ============================================================

// 创建评价表 (job 完成后追加, 不在 migration 中)
async function ensureEvaluationsTable() {
  await pool.query(`
    CREATE TABLE IF NOT EXISTS labor_evaluations (
      id INT AUTO_INCREMENT PRIMARY KEY,
      worker_profile_id INT NOT NULL,
      jobsite_id INT DEFAULT NULL,
      evaluator_user_id INT NOT NULL,
      evaluator_role VARCHAR(50) DEFAULT 'supervisor' COMMENT '评价人角色',
      score_attitude TINYINT DEFAULT NULL COMMENT '态度分 1-5',
      score_quality TINYINT DEFAULT NULL COMMENT '质量分 1-5',
      score_speed TINYINT DEFAULT NULL COMMENT '速度分 1-5',
      score_teamwork TINYINT DEFAULT NULL COMMENT '协作分 1-5',
      overall_score TINYINT DEFAULT NULL COMMENT '综合分 1-5',
      comment TEXT DEFAULT NULL,
      eval_period VARCHAR(50) DEFAULT NULL COMMENT '评价周期, 如 2026-07',
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      KEY idx_worker (worker_profile_id),
      KEY idx_jobsite (jobsite_id),
      KEY idx_evaluator (evaluator_user_id),
      KEY idx_period (eval_period)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT '工人评价记录'`)
}
ensureEvaluationsTable().catch((e) => console.error('ensureEvaluationsTable error:', e.message))

// POST /api/labor-evaluations — 上级评价
evalRouter.post('/', requirePermission(P.EVAL_WRITE), async (req, res, next) => {
  try {
    const {
      worker_profile_id, jobsite_id,
      score_attitude, score_quality, score_speed, score_teamwork,
      overall_score, comment, eval_period
    } = req.body || {}

    if (!worker_profile_id) return res.status(400).json({ code: 400, message: 'worker_profile_id 必填' })

    const [result] = await pool.query(
      `INSERT INTO labor_evaluations
       (worker_profile_id, jobsite_id, evaluator_user_id, evaluator_role,
        score_attitude, score_quality, score_speed, score_teamwork, overall_score,
        comment, eval_period)
       VALUES (?,?,?,?,?,?,?,?,?,?,?)`,
      [
        worker_profile_id, jobsite_id || null, req.user.id, req.user.role,
        score_attitude || null, score_quality || null, score_speed || null,
        score_teamwork || null, overall_score || null,
        comment || null, eval_period || new Date().toISOString().slice(0, 7)
      ]
    )

    // 同步更新 worker_profiles.quality_score (取最近 5 次评价平均)
    const [recent] = await pool.query(
      `SELECT AVG(overall_score) AS avg_q FROM labor_evaluations WHERE worker_profile_id = ? ORDER BY created_at DESC LIMIT 5`,
      [worker_profile_id]
    )
    if (recent[0]?.avg_q) {
      await pool.query(
        'UPDATE worker_profiles SET quality_score = ? WHERE id = ?',
        [Number((recent[0].avg_q * 20).toFixed(2)), worker_profile_id] // 1-5 映射到 0-100
      )
    }

    res.json({ code: 0, data: { id: result.insertId } })
  } catch (e) { next(e) }
})

// GET /api/labor-evaluations — 评价列表（?worker_profile_id=&jobsite_id=）
evalRouter.get('/', requirePermission(P.EVAL_READ), async (req, res, next) => {
  try {
    const { worker_profile_id, jobsite_id } = req.query
    const where = []
    const params = []
    if (worker_profile_id) { where.push('e.worker_profile_id = ?'); params.push(worker_profile_id) }
    if (jobsite_id) { where.push('e.jobsite_id = ?'); params.push(jobsite_id) }

    const [rows] = await pool.query(
      `SELECT e.*, u.name AS worker_name, u2.name AS evaluator_name, js.name AS jobsite_name
       FROM labor_evaluations e
       JOIN worker_profiles wp ON wp.id = e.worker_profile_id
       JOIN users u ON u.id = wp.user_id
       JOIN users u2 ON u2.id = e.evaluator_user_id
       LEFT JOIN jobsites js ON js.id = e.jobsite_id
       ${where.length ? 'WHERE ' + where.join(' AND ') : ''}
       ORDER BY e.created_at DESC
       LIMIT 200`,
      params
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

export { workerRouter, jobsiteRouter, dispatchRouter, evalRouter }