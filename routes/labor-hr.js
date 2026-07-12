// labor-hr.js — HR 录入员工资料(身份证/合同/入职离职/证件扫描)
// Layer 3: 人工录入 API — HR 专用
// 路径前缀: /api/labor-hr
// 权限: hr:read / hr:write  (新增 rbac_permissions: hr:read, hr:write)
import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission, PERMISSIONS as P } from '../middleware/rbac.js'

const router = express.Router()
router.use(auth)

// ============================================================
// 1. 入职 — POST /api/labor-hr/onboard
// 入参: user_id 或 { name, phone } 自动建 user
// ============================================================
router.post('/onboard', requirePermission(P.HR_WRITE), async (req, res, next) => {
  try {
    let { user_id, name, phone } = req.body || {}
    if (!user_id) {
      if (!name || !phone) {
        return res.status(400).json({ code: 400, message: 'user_id 或 (name, phone) 二选一必填' })
      }
      const [exist] = await pool.query('SELECT id FROM users WHERE phone = ? LIMIT 1', [phone])
      if (exist[0]) {
        user_id = exist[0].id
      } else {
        const [r] = await pool.query(
          'INSERT INTO users (name, phone, role, status, created_at) VALUES (?, ?, ?, 1, NOW())',
          [name, phone, 'worker']
        )
        user_id = r.insertId
      }
    }

    // 不能重复建 worker_profile
    const [[exist2]] = await pool.query('SELECT id FROM worker_profiles WHERE user_id = ?', [user_id])
    if (exist2) return res.status(409).json({ code: 409, message: '该用户已有工人档案', worker_profile_id: exist2.id })

    const {
      id_card, emergency_contact, emergency_phone,
      contract_start, contract_end, hired_at,
      skills, hourly_rate, payment_type, monthly_salary,
      notes
    } = req.body || {}

    const [r2] = await pool.query(
      `INSERT INTO worker_profiles
       (user_id, id_card, emergency_contact, emergency_phone,
        contract_start, contract_end, hired_at,
        skills, hourly_rate, payment_type, monthly_salary, employment_status, notes,
        created_at, updated_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?, 'probation', ?, NOW(), NOW())`,
      [
        user_id, id_card || null, emergency_contact || null, emergency_phone || null,
        contract_start || null, contract_end || null, hired_at || new Date().toISOString().slice(0, 10),
        JSON.stringify(skills || []), hourly_rate || 0, payment_type || 'hourly', monthly_salary || 0,
        notes || null
      ]
    )

    res.json({ code: 0, data: { worker_profile_id: r2.insertId, user_id } })
  } catch (e) { next(e) }
})

// ============================================================
// 2. 离职 — POST /api/labor-hr/:worker_profile_id/resign
// 入参: resigned_at(默认今天) reason(可选, 写到 notes)
// ============================================================
router.post('/:worker_profile_id/resign', requirePermission(P.HR_WRITE), async (req, res, next) => {
  try {
    const wpId = Number(req.params.worker_profile_id)
    const { resigned_at, reason } = req.body || {}
    const resignDate = resigned_at || new Date().toISOString().slice(0, 10)

    const [[wp]] = await pool.query('SELECT id FROM worker_profiles WHERE id = ?', [wpId])
    if (!wp) return res.status(404).json({ code: 404, message: '工人档案不存在' })

    await pool.query(
      `UPDATE worker_profiles
       SET employment_status = 'resigned', resigned_at = ?,
           current_jobsite_id = NULL, notes = CONCAT_WS('\n', notes, ?)
       WHERE id = ?`,
      [resignDate, reason ? `[离职 ${resignDate}] ${reason}` : `[离职 ${resignDate}]`, wpId]
    )

    res.json({ code: 0, data: { worker_profile_id: wpId, resigned_at: resignDate } })
  } catch (e) { next(e) }
})

// ============================================================
// 3. 证件上传/到期登记 — POST /api/labor-hr/:worker_profile_id/certificates
// 入参: cert_name, expires_on, cert_number, attachment_url(扫描件 OSS)
// ============================================================
router.post('/:worker_profile_id/certificates', requirePermission(P.HR_WRITE), async (req, res, next) => {
  try {
    const wpId = Number(req.params.worker_profile_id)
    const { cert_name, expires_on, cert_number, attachment_url } = req.body || {}
    if (!cert_name) return res.status(400).json({ code: 400, message: 'cert_name 必填' })

    const [[wp]] = await pool.query('SELECT id, certificates FROM worker_profiles WHERE id = ?', [wpId])
    if (!wp) return res.status(404).json({ code: 404, message: '工人档案不存在' })

    const certs = wp.certificates ? (typeof wp.certificates === 'string' ? JSON.parse(wp.certificates) : wp.certificates) : {}
    certs[cert_name] = {
      expires_on: expires_on || null,
      cert_number: cert_number || null,
      attachment_url: attachment_url || null,
      updated_at: new Date().toISOString()
    }

    await pool.query(
      'UPDATE worker_profiles SET certificates = ?, updated_at = NOW() WHERE id = ?',
      [JSON.stringify(certs), wpId]
    )

    res.json({ code: 0, data: { worker_profile_id: wpId, certificate: certs[cert_name] } })
  } catch (e) { next(e) }
})

// ============================================================
// 4. 合同续签/更新 — PATCH /api/labor-hr/:worker_profile_id/contract
// ============================================================
router.patch('/:worker_profile_id/contract', requirePermission(P.HR_WRITE), async (req, res, next) => {
  try {
    const wpId = Number(req.params.worker_profile_id)
    const { contract_start, contract_end } = req.body || {}
    if (!contract_start && !contract_end) {
      return res.status(400).json({ code: 400, message: 'contract_start / contract_end 至少传一项' })
    }

    const [[wp]] = await pool.query('SELECT id FROM worker_profiles WHERE id = ?', [wpId])
    if (!wp) return res.status(404).json({ code: 404, message: '工人档案不存在' })

    const fields = []; const params = []
    if (contract_start) { fields.push('contract_start = ?'); params.push(contract_start) }
    if (contract_end) { fields.push('contract_end = ?'); params.push(contract_end) }
    fields.push('updated_at = NOW()'); params.push(wpId)

    await pool.query(`UPDATE worker_profiles SET ${fields.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: { worker_profile_id: wpId } })
  } catch (e) { next(e) }
})

// ============================================================
// 5. HR 视角查看 — GET /api/labor-hr/employees?status=
// 列表 + 关联合同 30 天内过期预警
// ============================================================
router.get('/employees', requirePermission(P.HR_READ), async (req, res, next) => {
  try {
    const { status, q, expiring_within_days } = req.query
    const where = []; const params = []
    if (status) { where.push('wp.employment_status = ?'); params.push(status) }
    if (q) { where.push('(u.name LIKE ? OR u.phone LIKE ? OR wp.id_card LIKE ?)'); params.push(`%${q}%`, `%${q}%`, `%${q}%`) }

    const days = Number(expiring_within_days) || 0
    if (days > 0) {
      where.push(`wp.contract_end IS NOT NULL AND wp.contract_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)`)
      params.push(days)
    }

    const [rows] = await pool.query(
      `SELECT wp.id AS worker_profile_id, wp.user_id, u.name, u.phone,
              wp.id_card, wp.contract_start, wp.contract_end, wp.hired_at, wp.resigned_at,
              wp.employment_status, wp.current_jobsite_id, j.name AS jobsite_name,
              DATEDIFF(wp.contract_end, CURDATE()) AS days_to_contract_end
       FROM worker_profiles wp
       JOIN users u ON u.id = wp.user_id
       LEFT JOIN jobsites j ON j.id = wp.current_jobsite_id
       ${where.length ? 'WHERE ' + where.join(' AND ') : ''}
       ORDER BY wp.employment_status = 'active' DESC, wp.contract_end ASC
       LIMIT 500`,
      params
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

// ============================================================
// 6. 单个员工完整档案 — GET /api/labor-hr/employees/:worker_profile_id
// ============================================================
router.get('/employees/:worker_profile_id', requirePermission(P.HR_READ), async (req, res, next) => {
  try {
    const wpId = Number(req.params.worker_profile_id)
    const [[row]] = await pool.query(
      `SELECT wp.*, u.name, u.phone, u.role, u.email, u.status AS user_status,
              j.code AS jobsite_code, j.name AS jobsite_name
       FROM worker_profiles wp
       JOIN users u ON u.id = wp.user_id
       LEFT JOIN jobsites j ON j.id = wp.current_jobsite_id
       WHERE wp.id = ?`,
      [wpId]
    )
    if (!row) return res.status(404).json({ code: 404, message: '工人档案不存在' })

    // 最近评价 + 最近派工
    const [evals] = await pool.query(
      'SELECT * FROM labor_evaluations WHERE worker_profile_id = ? ORDER BY created_at DESC LIMIT 5',
      [wpId]
    )
    const [dispatches] = await pool.query(
      `SELECT a.*, js.name AS jobsite_name FROM approvals a
       LEFT JOIN worker_profiles wp ON wp.id = a.ref_id
       LEFT JOIN jobsites js ON js.id = wp.current_jobsite_id
       WHERE a.type = 'labor_dispatch' AND a.ref_id = ?
       ORDER BY a.created_at DESC LIMIT 5`,
      [wpId]
    )

    res.json({ code: 0, data: { ...row, evaluations: evals, dispatches } })
  } catch (e) { next(e) }
})

export default router
