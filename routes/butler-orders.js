import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requirePermission, hasPermission, PERMISSIONS } from '../middleware/rbac.js'
import { writeAuditLog } from '../utils/audit.js'

const router = Router()

// 状态 → 徽章映射（assigned 保留在 enum，本轮不再流转）
const STATUS_BADGE = {
  open:       { label: '待处理', color: '#F59E0B' },
  assigned:   { label: '已分配', color: '#6366F1' },
  processing: { label: '进行中', color: '#7E53FF' },
  completed:  { label: '已完成', color: '#22C55E' },
  cancelled:  { label: '已取消', color: '#9CA3AF' }
}

// SLA 兜底（配置表 sla_hours 为空时使用）
const SLA_HOURS = { urgent: 2, high: 8, medium: 24, low: 72 }

const isAdmin = (u) => !!u && (u.role === 'admin' || u.role === 'superuser' || u.is_super_admin === true)

// 「管家」判定：admin，或角色绑定了 butler-orders:write 的人。
// 与 requirePermission 同源（同一 getUserPermissions），避免前后不一致。
const canButler = async (me) => {
  if (!me) return false
  if (isAdmin(me)) return true
  try {
    return await hasPermission(me.id, me.role, PERMISSIONS.BUTLER_ORDERS_WRITE)
  } catch (e) {
    return false
  }
}

// 兜底解析企业 id：HK 的 auth 中间件把 profile(含 company_id) 放在 fire-and-forget 的异步块里，
// next() 不等它 → req.user.company_id 常为 undefined。这里查库兜底，保证 SGP / HK 两端行为一致。
const myCompanyId = async (me) => {
  if (!me) return null
  if (me.company_id != null) return me.company_id
  const [[u]] = await pool.query('SELECT company_id FROM users WHERE id = ?', [me.id])
  return u ? (u.company_id ?? null) : null
}

const toInt = (v) => {
  const n = parseInt(v, 10)
  return Number.isFinite(n) ? n : null
}

// 随机 8 位 base36 核销码
const randomCode = () => {
  const chars = 'abcdefghijklmnopqrstuvwxyz0123456789'
  let s = ''
  for (let i = 0; i < 8; i++) s += chars[Math.floor(Math.random() * chars.length)]
  return s
}

// 生成库内唯一核销码
async function genVerifyCode() {
  for (let i = 0; i < 12; i++) {
    const code = randomCode()
    const [[hit]] = await pool.query(
      'SELECT id FROM hqh5_butler_services WHERE verify_code = ? LIMIT 1',
      [code]
    )
    if (!hit) return code
  }
  return Date.now().toString(36) + Math.random().toString(36).slice(2, 6)
}

// 服务 code → 中文名（来自配置表）
async function typeLabelMap() {
  const [rows] = await pool.query('SELECT code, name FROM hqh5_butler_service_items')
  const map = {}
  for (const r of rows) map[r.code] = r.name
  return map
}

const decorate = (row, typeMap) => ({
  ...row,
  type_label:   (typeMap && typeMap[row.type]) || row.type,
  status_label: STATUS_BADGE[row.status]?.label || row.status,
  status_color: STATUS_BADGE[row.status]?.color || '#6B7280'
})

// [butler-dto] 2026-09-14 R3-5：按视角裁剪响应字段。
//   · 核销凭证(verify_code)：只发给「提单人 / 接单管家 / admin」——即 canSeeCredential；
//     未接单管家看池子(pool)、同企业非接单管家看详情时都拿不到，防止冒领。
//   · 内部审计字段 verified_by / verified_at：一律不外发。
const canSeeCredential = (row, me) => !!row && !!me
  && (isAdmin(me) || row.user_id === me.id || row.assigned_to === me.id)
function dtoButler(row, typeMap, withCredential) {
  const out = { ...row }
  delete out.verified_by
  delete out.verified_at
  delete out.deleted_at
  delete out.deleted_by
  delete out.delete_reason
  if (!withCredential) delete out.verify_code
  return decorate(out, typeMap)
}

/**
 * GET /api/butler-orders/service-items
 * 服务项目配置（登录即可访问）—— 必须注册在 GET /:id 之前
 */
router.get('/service-items', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT code, name, icon, sort
         FROM hqh5_butler_service_items
        WHERE enabled = 1
        ORDER BY sort ASC, id ASC`
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (e) { next(e) }
})

/**
 * GET /api/butler-orders
 * query: scope=mine(默认)|pool|handling|all, status, type, page, size
 * user_id 一律取 token，不接受 query 传入（修复 IDOR）
 * 权限：登录即可；scope=pool/handling 需管家权限，scope=all 需 admin
 */
router.get('/', async (req, res, next) => {
  try {
    const me = req.user
    const scope = (req.query.scope || 'mine').toString()
    const { status, type } = req.query
    const page = Math.max(1, toInt(req.query.page) || 1)
    const size = Math.min(100, Math.max(1, toInt(req.query.size) || 20))
    const offset = (page - 1) * size

    const where = ['deleted_at IS NULL']   // [butler-audit] R3-6 软删除行不出现在任何列表
    const params = []

    if (scope === 'pool') {
      if (!(await canButler(me))) {
        return res.status(403).json({ code: 403, message: '仅管家可查看待接单池' })
      }
      const cid = await myCompanyId(me)
      if (cid == null) {
        return res.json({ code: 0, data: { list: [], total: 0, page, size, scope }, message: 'ok' })
      }
      where.push('company_id = ?'); params.push(cid)
      where.push("status = 'open'")
    } else if (scope === 'handling') {
      if (!(await canButler(me))) {
        return res.status(403).json({ code: 403, message: '仅管家可查看接单列表' })
      }
      where.push('assigned_to = ?'); params.push(me.id)
    } else if (scope === 'all') {
      if (!isAdmin(me)) return res.status(403).json({ code: 403, message: '无权查看全部工单' })
    } else {
      where.push('user_id = ?'); params.push(me.id)
    }

    if (status) { where.push('status = ?'); params.push(status) }
    if (type)   { where.push('type = ?');   params.push(type) }

    const whereSql = where.join(' AND ')
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) AS total FROM hqh5_butler_services WHERE ${whereSql}`,
      params
    )
    const [rows] = await pool.query(
      `SELECT * FROM hqh5_butler_services
        WHERE ${whereSql}
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?`,
      [...params, size, offset]
    )
    const typeMap = await typeLabelMap()
    res.json({
      code: 0,
      data: { list: rows.map(r => dtoButler(r, typeMap, canSeeCredential(r, me))), total, page, size, scope },
      message: 'ok'
    })
  } catch (e) { next(e) }
})

/**
 * GET /api/butler-orders/:id
 * 归属校验：提单人 / 接单管家 / 同企业成员 / admin
 * 权限：登录即可（归属校验在函数内）
 */
router.get('/:id', async (req, res, next) => {
  try {
    const me = req.user
    const [[row]] = await pool.query(
      'SELECT * FROM hqh5_butler_services WHERE id = ? AND deleted_at IS NULL',
      [req.params.id]
    )
    // [butler-detail-scope] 2026-09-14 R3-4：详情可见 = admin / 提单人 / 接单管家 /「同企业且具管家能力」。
    //   原第 4 条只比 company_id → 同企业任意成员都能看到别人报修的房间号与需求；现收紧为必须同时是管家。
    //   无权限与不存在统一返回 404，避免用错误码差异枚举工单 ID。
    const cid = row ? await myCompanyId(me) : null
    const sameCompanyButler = !!row
      && row.company_id != null && cid != null && row.company_id === cid
      && (await canButler(me))
    const allowed = !!row && (
      isAdmin(me) || row.user_id === me.id || row.assigned_to === me.id || sameCompanyButler
    )
    if (!allowed) return res.status(404).json({ code: 404, message: '工单不存在或无权查看' })

    const typeMap = await typeLabelMap()
    res.json({ code: 0, data: dtoButler(row, typeMap, canSeeCredential(row, me)), message: 'ok' })
  } catch (e) { next(e) }
})

/**
 * POST /api/butler-orders
 * body: { type, title, description, appoint_time, location, priority }
 * 提单人取 token；company_id 取 token；服务端生成 verify_code
 * 权限：登录即可（任何登录用户都可提单）
 */
router.post('/', async (req, res, next) => {
  try {
    const me = req.user
    const { type, title, description, appoint_time, location, priority } = req.body || {}
    if (!type || !title) {
      return res.status(400).json({ code: 400, message: 'type / title 必填' })
    }

    const [[item]] = await pool.query(
      'SELECT code, sla_hours FROM hqh5_butler_service_items WHERE code = ? AND enabled = 1 LIMIT 1',
      [type]
    )
    if (!item) return res.status(400).json({ code: 400, message: '服务类型无效' })

    const p = priority || 'medium'
    const slaHours = item.sla_hours || SLA_HOURS[p] || 24
    const deadline = new Date(Date.now() + slaHours * 3600 * 1000)
    const verifyCode = await genVerifyCode()
    const companyId = await myCompanyId(me)

    const [result] = await pool.query(
      `INSERT INTO hqh5_butler_services
         (user_id, user_name, company_id, type, title, description, appoint_time, location, priority, sla_deadline, verify_code, status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')`,
      [
        me.id,
        me.name || '',
        companyId,
        type,
        title,
        description || '',
        appoint_time || null,
        location || '',
        p,
        deadline,
        verifyCode
      ]
    )

    res.json({
      code: 0,
      data: { id: result.insertId, verify_code: verifyCode, sla_deadline: deadline, appoint_time: appoint_time || null },
      message: '工单创建成功'
    })
  } catch (e) { next(e) }
})

/**
 * PUT /api/butler-orders/:id/claim
 * 管家自领接单（决策 5：不做派单，Body 无参数）
 */
router.put('/:id/claim', requirePermission(PERMISSIONS.BUTLER_ORDERS_WRITE), async (req, res, next) => {
  try {
    const me = req.user
    const [[row]] = await pool.query(
      'SELECT id, status, company_id FROM hqh5_butler_services WHERE id = ? AND deleted_at IS NULL',
      [req.params.id]
    )
    if (!row) return res.status(404).json({ code: 404, message: '工单不存在' })
    if (row.status !== 'open') {
      return res.status(409).json({ code: 409, message: '该工单已被接单或已结束' })
    }
    const cid = await myCompanyId(me)
    if (row.company_id == null || cid == null || row.company_id !== cid) {
      return res.status(403).json({ code: 403, message: '无权接其他企业的工单' })
    }

    const [result] = await pool.query(
      `UPDATE hqh5_butler_services
          SET assigned_to = ?, assigned_at = NOW(), status = 'processing'
        WHERE id = ? AND status = 'open'`,
      [me.id, row.id]
    )
    if (result.affectedRows === 0) {
      return res.status(409).json({ code: 409, message: '该工单刚被他人接单' })
    }
    res.json({ code: 0, message: '接单成功' })
  } catch (e) { next(e) }
})

/**
 * POST /api/butler-orders/verify
 * body: { payload } 扫码原始字符串 BWO|{orderId}|{verify_code}|{side}
 *    或 { code, side } 手输 8 位核销码（H5 无摄像头时的兜底），side 由调用方界面决定
 * side: U=用户码（管家来核销）  B=管家码（用户来核销）
 * 权限：登录即可（同侧拒绝 + 归属校验在函数内，决定谁能扫谁）
 */
router.post('/verify', async (req, res, next) => {
  try {
    const me = req.user
    const body = req.body || {}
    const raw = String(body.payload || '').trim()
    let orderId = null
    let code = ''
    let side = ''

    if (raw) {
      // 路径 1：扫码得到的完整 payload  BWO|{orderId}|{verify_code}|{side}
      const parts = raw.split('|')
      if (parts.length !== 4 || parts[0] !== 'BWO') {
        return res.status(400).json({ code: 400, message: '二维码格式不正确' })
      }
      orderId = toInt(parts[1])
      code = String(parts[2] || '').trim().toLowerCase()
      side = (parts[3] || '').toUpperCase()
      if (!orderId || !code || (side !== 'U' && side !== 'B')) {
        return res.status(400).json({ code: 400, message: '二维码内容无效' })
      }
    } else {
      // 路径 2：手输 8 位核销码（H5 无扫码能力时的兜底）；verify_code 全库唯一，可反查工单
      code = String(body.code || '').trim().toLowerCase()
      side = String(body.side || '').toUpperCase()
      if (!code || (side !== 'U' && side !== 'B')) {
        return res.status(400).json({ code: 400, message: '缺少核销码内容' })
      }
      const [[hit]] = await pool.query(
        'SELECT id FROM hqh5_butler_services WHERE verify_code = ? AND deleted_at IS NULL LIMIT 1',
        [code]
      )
      if (!hit) return res.status(404).json({ code: 404, message: '核销码不存在' })
      orderId = hit.id
    }

    const [[row]] = await pool.query(
      'SELECT * FROM hqh5_butler_services WHERE id = ? AND deleted_at IS NULL',
      [orderId]
    )
    if (!row) return res.status(404).json({ code: 404, message: '工单不存在' })
    if (!row.verify_code || String(row.verify_code).toLowerCase() !== code) {
      return res.status(403).json({ code: 403, message: '核销码无效' })
    }

    // 同侧拒绝：不能扫自己那一侧的码
    if (side === 'U' && row.user_id === me.id) {
      return res.status(403).json({ code: 403, message: '不能扫自己的二维码' })
    }
    if (side === 'B' && row.assigned_to === me.id) {
      return res.status(403).json({ code: 403, message: '不能扫自己的二维码' })
    }

    // 用户码 → 必须由接单管家核销
    if (side === 'U') {
      if (row.assigned_to == null || row.assigned_to !== me.id) {
        return res.status(403).json({ code: 403, message: '只能由接单管家核销用户码' })
      }
      const cid = await myCompanyId(me)
      if (row.company_id != null && cid != null && row.company_id !== cid) {
        return res.status(403).json({ code: 403, message: '无权核销其他企业的工单' })
      }
    } else {
      // 管家码 → 必须由提单人核销
      if (row.user_id !== me.id) {
        return res.status(403).json({ code: 403, message: '只能由提单人核销管家码' })
      }
    }

    if (row.status !== 'processing') {
      const msg = row.status === 'completed' ? '该工单已核销' : '工单当前不可核销'
      return res.status(409).json({ code: 409, message: msg })
    }

    const note = body.handle_note ? String(body.handle_note) : null
    await pool.query(
      `UPDATE hqh5_butler_services
          SET status = 'completed',
              completed_at = NOW(),
              verified_at = NOW(),
              verified_by = ?,
              handle_note = COALESCE(?, handle_note)
        WHERE id = ? AND status = 'processing'`,
      [me.id, note, row.id]
    )

    res.json({ code: 0, message: '核销成功，工单已完成' })
  } catch (e) { next(e) }
})

/**
 * PUT /api/butler-orders/:id/cancel
 * 仅提单人 / admin；已完成不可取消
 * 权限：登录即可（归属校验在函数内）
 */
router.put('/:id/cancel', async (req, res, next) => {
  try {
    const me = req.user
    const [[row]] = await pool.query(
      'SELECT id, user_id, status FROM hqh5_butler_services WHERE id = ? AND deleted_at IS NULL',
      [req.params.id]
    )
    if (!row) return res.status(404).json({ code: 404, message: '工单不存在' })
    if (!isAdmin(me) && row.user_id !== me.id) {
      return res.status(403).json({ code: 403, message: '无权取消该工单' })
    }
    if (row.status === 'completed') {
      return res.status(409).json({ code: 409, message: '已完成的工单不可取消' })
    }
    if (row.status === 'cancelled') {
      return res.status(409).json({ code: 409, message: '工单已取消' })
    }

    await pool.query(
      "UPDATE hqh5_butler_services SET status = 'cancelled' WHERE id = ? AND status <> 'completed'",
      [row.id]
    )
    res.json({ code: 0, message: '已取消' })
  } catch (e) { next(e) }
})

/**
 * DELETE /api/butler-orders/:id
 * 仅提单人 / admin
 */
router.delete('/:id', requirePermission(PERMISSIONS.BUTLER_ORDERS_DELETE), async (req, res, next) => {
  try {
    const me = req.user
    const { reason } = req.body || {}
    const [[row]] = await pool.query(
      'SELECT * FROM hqh5_butler_services WHERE id = ? AND deleted_at IS NULL',
      [req.params.id]
    )
    if (!row) return res.status(404).json({ code: 404, message: '工单不存在' })
    if (!isAdmin(me) && row.user_id !== me.id) {
      return res.status(403).json({ code: 403, message: '无权删除该工单' })
    }
    // [butler-audit] 2026-09-14 R3-6：已接单/进行中/已完成不可删除，保留服务与核销追溯
    if (row.assigned_to != null || ['assigned', 'processing', 'completed'].includes(row.status)) {
      return res.status(409).json({ code: 409, message: '已接单或已完成的工单不可删除，如需终止请使用取消' })
    }
    const dReason = reason ? String(reason).slice(0, 200) : null
    await pool.query(
      'UPDATE hqh5_butler_services SET deleted_at = NOW(), deleted_by = ?, delete_reason = ? WHERE id = ? AND deleted_at IS NULL',
      [me.id, dReason, row.id]
    )
    await writeAuditLog(req, 'DELETE', 'hqh5_butler_services', row.id, row, { soft_deleted: true, deleted_by: me.id, delete_reason: dReason })
    res.json({ code: 0, message: '已删除' })
  } catch (e) { next(e) }
})

export default router
