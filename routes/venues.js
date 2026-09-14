import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requirePermission, PERMISSIONS, ROLES } from '../middleware/rbac.js'
import { getCompanyScope } from '../utils/company-scope.js'
import { writeAuditLog } from '../utils/audit.js'

const router = Router()

// ════════ 会议室信用制（文档：横琴湾区创新中心/03-小程序/会议室信用制.md） ════════
// 分数 = 90 + 最近12个月 venue_credit_logs 的 Σdelta，截断在 [0, 120]。
// 加扣分力度存 venue_credit_rules 表（随时可调，无需改代码）；等级权限矩阵为政策口径，放这里。
const CREDIT = {
  INITIAL: 90,
  MAX: 120,
  MIN: 0,
  WINDOW_MONTHS: 12,
  // 等级权限矩阵（score 降序匹配第一个 min）
  LEVELS: [
    { min: 100, key: 'excellent', label: '优秀', advanceDays: 7, maxDurationMin: 240, dailyLimit: 3 },
    { min: 80,  key: 'good',      label: '良好', advanceDays: 5, maxDurationMin: 180, dailyLimit: 2 },
    { min: 60,  key: 'normal',    label: '一般', advanceDays: 3, maxDurationMin: 120, dailyLimit: 1 },
    { min: 40,  key: 'warning',   label: '预警', advanceDays: 0, maxDurationMin: 120, dailyLimit: 1 },
    { min: 20,  key: 'limited',   label: '受限', advanceDays: 0, maxDurationMin: 120, dailyLimit: 1 },
    { min: -999, key: 'blacklist', label: '黑名单', advanceDays: 0, maxDurationMin: 0, dailyLimit: 0 }
  ]
}
const levelFor = (score) => CREDIT.LEVELS.find(l => score >= l.min) || CREDIT.LEVELS[CREDIT.LEVELS.length - 1]

// 高峰时段（venue_configs.peak_windows 可调；缺省周一至五 9:00-11:00，信用制文档口径）
const DEFAULT_PEAK = [{ weekdays: [1, 2, 3, 4, 5], start: '09:00', end: '11:00' }]
async function getPeakWindows() {
  try {
    const [[row]] = await pool.query("SELECT cfg_value FROM venue_configs WHERE cfg_key = 'peak_windows'")
    const v = row ? JSON.parse(row.cfg_value) : null
    return Array.isArray(v) && v.length ? v : DEFAULT_PEAK
  } catch (e) { return DEFAULT_PEAK }
}

// 信用主体：企业用户按 company_id 汇总，无企业的按 user_id（与多企业隔离口径一致）
async function computeCredit(scope, userId) {
  const isCompany = !!scope.companyId
  const col = isCompany ? 'company_id' : 'user_id'
  const val = isCompany ? scope.companyId : userId
  const [[{ sum }]] = await pool.query(
    `SELECT COALESCE(SUM(delta),0) AS sum FROM venue_credit_logs
     WHERE ${col} = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)`,
    [val, CREDIT.WINDOW_MONTHS]
  )
  const score = Math.max(CREDIT.MIN, Math.min(CREDIT.MAX, CREDIT.INITIAL + Number(sum) || 0))
  return { score, level: levelFor(score), ownerType: isCompany ? 'company' : 'user', ownerId: val }
}

// 信用流水（力度从 venue_credit_rules 取，取不到用 fallbackDelta）
async function addCreditLog({ company_id = null, user_id, booking_id = null, action_key, fallbackDelta, reason, created_by = null }) {
  let delta = fallbackDelta
  if (action_key) {
    const [[rule]] = await pool.query('SELECT delta FROM venue_credit_rules WHERE action_key = ? AND enabled = 1', [action_key])
    if (rule) delta = rule.delta
  }
  if (delta == null) return null
  const [ret] = await pool.query(
    'INSERT INTO venue_credit_logs (company_id, user_id, booking_id, action_key, delta, reason, created_by) VALUES (?,?,?,?,?,?,?)',
    [company_id, user_id, booking_id, action_key || null, delta, reason, created_by]
  )
  return ret.insertId
}

// 预订单号：M + YYYYMMDD + 当日 3 位序号（重试兜底并发）
async function genBookingNo() {
  for (let attempt = 0; attempt < 5; attempt++) {
    const [[{ n }]] = await pool.query("SELECT COUNT(*) AS n FROM meeting_bookings WHERE DATE(created_at) = CURDATE()")
    const no = 'M' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + String(n + 1 + attempt).padStart(3, '0')
    const [[dup]] = await pool.query('SELECT id FROM meeting_bookings WHERE booking_no = ?', [no])
    if (!dup) return no
  }
  throw new Error('预订号生成失败')
}

const fmtTime = (minutes) => String(Math.floor(minutes / 60) % 24).padStart(2, '0') + ':' + String(minutes % 60).padStart(2, '0')
const toMin = (hhmm) => { const [h, m] = String(hhmm).split(':').map(Number); return h * 60 + (m || 0) }
const isAdmin = (u) => ['admin', 'superuser'].includes(u.role)

// [credit-scope] 2026-09-14 R3-2 信用操作范围：global 可跨企业调分；company-manage 仅本企业；其余不可调分
function creditScopeAllows(scope, targetCompanyId) {
  if (!scope) return false
  if (scope.kind === 'global') return true
  if (scope.kind === 'company-manage') {
    return targetCompanyId != null && Number(targetCompanyId) === Number(scope.companyId)
  }
  return false
}

// 目标信用分（审计前后分值用）：target=company 按公司汇总，target=user 按人汇总
async function creditScoreOf(target, companyId, userId) {
  const scope = target === 'company' ? { companyId } : { companyId: null }
  const { score, ownerType, ownerId } = await computeCredit(scope, userId)
  return { score, ownerType, ownerId }
}

// 审计用请求 ID（无中间件时取头/生成）
function auditReqId(req) {
  return req.headers['x-request-id'] || req.id || ('r' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8))
}

// [credit-scope] R3-2 修复：venue_credit_logs.user_id 为 NOT NULL，而公司级调分没有具体用户
//   → 用 0 作「公司级」哨兵。company 汇总只按 company_id；user 汇总永不命中 0，二者不互相污染。
const CREDIT_COMPANY_LEVEL_UID = 0

// ════════ 场地 ════════

// GET /api/venues/rooms?category= — 场地列表
router.get('/rooms', async (req, res, next) => {
  try {
    const { category } = req.query
    let sql = "SELECT id, name, category, spec, capacity, location, facilities, image, status FROM venue_rooms WHERE status = 'available'"
    const params = []
    if (category) { sql += ' AND category = ?'; params.push(category) }
    sql += ' ORDER BY id'
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/venues/rooms/:id/slots?date= — 某日已占时段（最小披露：普通用户只见忙闲）
router.get('/rooms/:id/slots', async (req, res, next) => {
  try {
    const date = String(req.query.date || '').slice(0, 10)
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return res.status(400).json({ code: 400, message: 'date 必填(YYYY-MM-DD)' })
    // [slots-min-disclosure] 2026-09-14 R3-3：普通用户只返回占用时段；主题统一「已占用」；
    //   姓名仅平台管理员或该预订本人可见（参会人只存姓名无 user_id，无法可靠判定，暂不放行）。
    const scope = await getCompanyScope(req)
    const canSeeDetail = scope.kind === 'global' || isAdmin(req.user)
    const me = req.user.id
    const [rows] = await pool.query(
      `SELECT start_time, end_time,
              CASE WHEN ? = 1 OR user_id = ? THEN title ELSE '已占用' END AS title,
              CASE WHEN ? = 1 OR user_id = ? THEN user_name ELSE NULL END AS user_name
       FROM meeting_bookings WHERE room_id = ? AND date = ? AND status IN ('pending','approved') ORDER BY start_time`,
      [canSeeDetail ? 1 : 0, me, canSeeDetail ? 1 : 0, me, req.params.id, date]
    )
    res.json({ code: 0, data: { date, slots: rows }, message: 'ok' })
  } catch (err) { next(err) }
})

// ════════ 预订 ════════

// POST /api/venues/bookings — 创建预订（提交即生效；审批字段预留）
router.post('/bookings', async (req, res, next) => {
  try {
    const { room_id, title, date, start_time, duration_minutes, participants, remark } = req.body || {}
    if (!room_id || !title || !date || !start_time || !duration_minutes) {
      return res.status(400).json({ code: 400, message: 'room_id/title/date/start_time/duration_minutes 必填' })
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(String(date))) return res.status(400).json({ code: 400, message: 'date 格式 YYYY-MM-DD' })
    if (!/^\d{1,2}:\d{2}$/.test(String(start_time))) return res.status(400).json({ code: 400, message: 'start_time 格式 HH:MM' })

    const scope = await getCompanyScope(req)
    const userId = req.user.id
    const companyId = ['company-self', 'company-manage'].includes(scope.kind) ? scope.companyId : null

    // 信用等级闸门
    const credit = await computeCredit(scope, userId)
    const lv = credit.level
    if (lv.dailyLimit <= 0) return res.status(403).json({ code: 403, message: `信用等级「${lv.label}」暂停预约权限` })

    const today = new Date(); today.setHours(0, 0, 0, 0)
    const day = new Date(date + 'T00:00:00')
    if (day < today) return res.status(400).json({ code: 400, message: '不能预约过去的日期' })
    const maxDate = new Date(today); maxDate.setDate(maxDate.getDate() + lv.advanceDays)
    if (day > maxDate) return res.status(403).json({ code: 403, message: `信用等级「${lv.label}」最多提前 ${lv.advanceDays} 天预约` })
    // 当天已开始的时段不可约
    const startAt = new Date(date + 'T' + String(start_time).slice(0, 5))
    if (startAt.getTime() <= Date.now()) return res.status(400).json({ code: 400, message: '预约时间已过' })

    const [[{ cnt: dayCnt }]] = await pool.query(
      "SELECT COUNT(*) AS cnt FROM meeting_bookings WHERE user_id = ? AND date = ? AND status IN ('pending','approved')",
      [userId, date]
    )
    if (dayCnt >= lv.dailyLimit) return res.status(403).json({ code: 403, message: `信用等级「${lv.label}」每日最多 ${lv.dailyLimit} 次预约` })

    const dur = Number(duration_minutes)
    if (!Number.isFinite(dur) || dur <= 0) return res.status(400).json({ code: 400, message: '时长无效' })
    if (dur > lv.maxDurationMin) return res.status(403).json({ code: 403, message: `信用等级「${lv.label}」单次最长 ${lv.maxDurationMin} 分钟` })

    const startMin = toMin(start_time)
    const endMin = startMin + dur
    if (endMin > 24 * 60) return res.status(400).json({ code: 400, message: '结束时间不能跨天' })
    const startTime = fmtTime(startMin)
    const endTime = fmtTime(endMin)

    const [[room]] = await pool.query("SELECT id, name, status FROM venue_rooms WHERE id = ?", [room_id])
    if (!room) return res.status(404).json({ code: 404, message: '场地不存在' })
    if (room.status !== 'available') return res.status(400).json({ code: 400, message: '场地当前不可预约（维护中）' })

    // 受限等级（20-39分）不可约工作日高峰时段（口径 venue_configs.peak_windows，可调）
    if (lv.key === 'limited') {
      const peaks = await getPeakWindows()
      const weekday = day.getDay() === 0 ? 7 : day.getDay()
      const inPeak = peaks.some(pk =>
        (pk.weekdays || []).includes(weekday) && startMin < toMin(pk.end) && endMin > toMin(pk.start)
      )
      if (inPeak) return res.status(403).json({ code: 403, message: '信用等级「受限」不可预约工作日高峰时段' })
    }

    // [booking-race] 2026-09-14 R4-3 重叠预订：对 (场地,日期) 取 MySQL 建议锁，把"查冲突 + 插入"串行化。
    //   否则两个并发请求都可能查不到冲突、都插入 → 时段重叠（uk_room_slot 只能防"完全相同起始时刻"）。
    //   注意：GET_LOCK 是连接级的，四步必须落在同一个连接上，否则锁无效 —— 故用 pool.getConnection()。
    const __lockName = `venue_slot_${room_id}_${date}`
    const conn = await pool.getConnection()
    try {
      const [[__lk]] = await conn.query('SELECT GET_LOCK(?, 5) AS ok', [__lockName])
      if (!__lk || Number(__lk.ok) !== 1) {
        return res.status(409).json({ code: 409, message: '系统繁忙，请稍后重试' })
      }
      try {
        // 时段重叠冲突（同场地同日，与 pending/approved 的单子比较）
        const [conflicts] = await conn.query(
          "SELECT id, booking_no, title, start_time, end_time FROM meeting_bookings WHERE room_id = ? AND date = ? AND status IN ('pending','approved') AND start_time < ? AND end_time > ?",
          [room_id, date, endTime, startTime]
        )
        if (conflicts.length) {
          return res.status(409).json({ code: 409, message: '该时段已被预订', data: { conflicts } })
        }

        const parts = Array.isArray(participants) ? JSON.stringify(participants.map(p => String(p)).slice(0, 50)) : null
        // [booking-race] 2026-09-14 R4-2 编号竞争：booking_no 已加唯一索引；
        //   撞号(uk_booking_no) → 重取重试；撞时段(uk_room_slot) → 409
        let insId = null
        for (let attempt = 0; attempt < 5 && insId == null; attempt++) {
          const bookingNo = await genBookingNo()
          try {
            const [ret] = await conn.query(
              `INSERT INTO meeting_bookings
               (booking_no, room_id, company_id, user_id, user_name, title, date, start_time, end_time, duration_minutes, participants, remark, status)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'approved')`,
              [bookingNo, room_id, companyId, userId, req.user.name || '', title, date, startTime, endTime, dur, parts, remark || '']
            )
            insId = ret.insertId
          } catch (e) {
            if (e && e.code === 'ER_DUP_ENTRY') {
              if (String(e.message || '').includes('uk_booking_no')) continue
              return res.status(409).json({ code: 409, message: '该时段已被预订' })
            }
            throw e
          }
        }
        if (insId == null) {
          return res.status(409).json({ code: 409, message: '预订号生成失败，请稍后重试' })
        }

        const [[row]] = await conn.query(
          'SELECT id, booking_no, room_id, title, date, start_time, end_time, duration_minutes, status FROM meeting_bookings WHERE id = ?',
          [insId]
        )
        res.json({ code: 0, data: { ...row, room_name: room.name }, message: 'ok' })
      } finally {
        try { await conn.query('SELECT RELEASE_LOCK(?)', [__lockName]) } catch (e2) { /* 释放失败不改变结果 */ }
      }
    } finally {
      conn.release()
    }
  } catch (err) { next(err) }
})

// GET /api/venues/bookings/mine — 我的预订
router.get('/bookings/mine', async (req, res, next) => {
  try {
    const { status } = req.query
    let sql = `SELECT b.id, b.booking_no, b.room_id, r.name AS room_name, b.title, DATE_FORMAT(b.date, '%Y-%m-%d') AS date, b.start_time, b.end_time,
                      b.duration_minutes, b.participants, b.remark, b.status, b.checkin_at, b.created_at
               FROM meeting_bookings b LEFT JOIN venue_rooms r ON r.id = b.room_id
               WHERE b.user_id = ?`
    const params = [req.user.id]
    if (status) { sql += ' AND b.status = ?'; params.push(status) }
    sql += ' ORDER BY b.date DESC, b.start_time DESC LIMIT 100'
    const [rows] = await pool.query(sql, params)
    rows.forEach(r => { try { r.participants = r.participants ? JSON.parse(r.participants) : [] } catch (e) { r.participants = [] } })
    res.json({ code: 0, data: { list: rows }, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/venues/bookings/:id/cancel — 取消（发起人或 admin；开始前 30 分钟内取消记临时取消扣分）
router.put('/bookings/:id/cancel', async (req, res, next) => {
  try {
    const [[b]] = await pool.query(
      "SELECT *, TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(date, ' ', start_time)) AS mins_to_start FROM meeting_bookings WHERE id = ?",
      [req.params.id]
    )
    if (!b) return res.status(404).json({ code: 404, message: '预订不存在' })
    if (String(b.user_id) !== String(req.user.id) && !isAdmin(req.user)) {
      return res.status(403).json({ code: 403, message: '只能取消自己的预订' })
    }
    if (!['pending', 'approved'].includes(b.status)) {
      return res.status(400).json({ code: 400, message: '当前状态不可取消' })
    }
    // [booking-race] 2026-09-14 R4-1：条件更新——并发重复取消时只有一个请求 affectedRows=1，
    //   只有它才写信用流水，避免同一次取消被重复扣分（原先"先读状态再更新"存在竞态）
    const [cancelRet] = await pool.query(
      "UPDATE meeting_bookings SET status = 'cancelled', cancelled_at = NOW() WHERE id = ? AND status IN ('pending','approved')",
      [b.id]
    )
    if (!cancelRet || cancelRet.affectedRows !== 1) {
      return res.status(409).json({ code: 409, message: '该预订已被取消或状态已变更' })
    }
    // 信用：开始前 30 分钟内取消 = 临时取消（力度 venue_credit_rules.temp_cancel，可调）
    const minsToStart = Number(b.mins_to_start)
    if (minsToStart < 30 && minsToStart > -60) {
      await addCreditLog({
        company_id: b.company_id, user_id: b.user_id, booking_id: b.id,
        action_key: 'temp_cancel', fallbackDelta: -10,
        reason: `临时取消（距开始 ${Math.max(0, Math.round(minsToStart))} 分钟）：${b.title} ${b.booking_no}`,
        created_by: req.user.id
      })
    }
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/venues/bookings/:id/checkin — 签到打点（留接口：门禁刷卡/扫码回调接这里）
// 信用制用途：预约开始后 15 分钟内未签到 → no_show 扣分（自动判定后续接门禁流水）
router.post('/bookings/:id/checkin', async (req, res, next) => {
  try {
    const [[b]] = await pool.query('SELECT * FROM meeting_bookings WHERE id = ?', [req.params.id])
    if (!b) return res.status(404).json({ code: 404, message: '预订不存在' })
    if (String(b.user_id) !== String(req.user.id) && !isAdmin(req.user)) {
      return res.status(403).json({ code: 403, message: '只能为本人预订签到' })
    }
    if (b.status !== 'approved') return res.status(400).json({ code: 400, message: '当前状态不可签到' })
    if (b.checkin_at) return res.json({ code: 0, data: { checkin_at: b.checkin_at, duplicated: true }, message: 'ok' })
    await pool.query('UPDATE meeting_bookings SET checkin_at = NOW() WHERE id = ?', [b.id])
    res.json({ code: 0, data: { checkin_at: new Date() }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/venues/credit/event — 扣分/加分事件入口（留接口：门禁/超时/保洁等系统或人工回调查这里）
// body: { booking_id, action_key, reason? } → 目标(公司/人)取自预订，力度取自 venue_credit_rules
router.post('/credit/event', requirePermission(PERMISSIONS.VENUES_WRITE), async (req, res, next) => {
  try {
    const { booking_id, action_key, reason } = req.body || {}
    if (!booking_id || !action_key) return res.status(400).json({ code: 400, message: 'booking_id/action_key 必填' })
    const [[b]] = await pool.query('SELECT * FROM meeting_bookings WHERE id = ?', [booking_id])
    if (!b) return res.status(404).json({ code: 404, message: '预订不存在' })
    const [[rule]] = await pool.query('SELECT delta FROM venue_credit_rules WHERE action_key = ? AND enabled = 1', [action_key])
    if (!rule) return res.status(400).json({ code: 400, message: 'action_key 不存在或未启用' })
    // [credit-scope] R3-2：只能处置自己范围内的预订所属企业
    const scope = await getCompanyScope(req)
    if (!creditScopeAllows(scope, b.company_id)) {
      return res.status(403).json({ code: 403, message: '无权对该预订所属企业操作信用分' })
    }
    const ownerKind = b.company_id ? 'company' : 'user'
    const before = await creditScoreOf(ownerKind, b.company_id, b.user_id)
    const logId = await addCreditLog({
      company_id: b.company_id, user_id: b.user_id, booking_id: b.id,
      action_key, fallbackDelta: rule.delta,
      reason: reason || (b.title + ' ' + b.booking_no), created_by: req.user.id
    })
    const after = await creditScoreOf(ownerKind, b.company_id, b.user_id)
    await writeAuditLog(req, 'CREATE', 'venue_credit_logs', logId || null,
      { score: before.score, owner: before.ownerType + ':' + before.ownerId },
      { score: after.score, delta: rule.delta, action_key, reason: reason || (b.title + ' ' + b.booking_no), source: 'credit/event', booking_id: b.id, request_id: auditReqId(req) })
    res.json({ code: 0, data: { delta: rule.delta }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/venues/config — 高峰时段等口径（前台提示/管理端展示用）
router.get('/config', async (req, res, next) => {
  try {
    const peaks = await getPeakWindows()
    res.json({ code: 0, data: { peak_windows: peaks, credit: CREDIT }, message: 'ok' })
  } catch (err) { next(err) }
})

// ════════ 管理面（gdqadmin 配套） ════════

// GET /api/venues/bookings — 全量列表（分页/筛选）
router.get('/bookings', requirePermission(PERMISSIONS.VENUES_READ), async (req, res, next) => {
  try {
    const { date, status, company_id, page = 1, size = 20 } = req.query
    const limit = Math.min(Number(size) || 20, 100)
    const offset = (Math.max(Number(page), 1) - 1) * limit
    let where = 'WHERE 1=1'
    const params = []
    if (date) { where += ' AND b.date = ?'; params.push(date) }
    if (status) { where += ' AND b.status = ?'; params.push(status) }
    // [company-iso] 2026-09-14 R3-1 会议管理企业范围：服务端强制注入范围；
    //   客户端 company_id 仅平台管理员(global)可作筛选，不能扩大可见范围
    const __scope = await getCompanyScope(req)
    if (__scope.kind === 'company-manage') {
      where += ' AND b.company_id = ?'; params.push(__scope.companyId)
    } else if (__scope.kind === 'company-self') {
      where += ' AND b.user_id = ?'; params.push(req.user.id)
    } else if (__scope.kind === 'incubator') {
      where += ' AND b.company_id IS NULL'
    } else if (company_id) {
      where += ' AND b.company_id = ?'; params.push(company_id)
    }
    const [[{ total }]] = await pool.query(`SELECT COUNT(*) AS total FROM meeting_bookings b ${where}`, params)
    const [rows] = await pool.query(
      `SELECT b.*, r.name AS room_name, c.name AS company_name, u.name AS creator_name, DATE_FORMAT(b.date, '%Y-%m-%d') AS date
       FROM meeting_bookings b
       LEFT JOIN venue_rooms r ON r.id = b.room_id
       LEFT JOIN companies c ON c.id = b.company_id
       LEFT JOIN users u ON u.id = b.user_id
       ${where} ORDER BY b.date DESC, b.start_time DESC LIMIT ? OFFSET ?`,
      [...params, limit, offset]
    )
    rows.forEach(r => { try { r.participants = r.participants ? JSON.parse(r.participants) : [] } catch (e) { r.participants = [] } })
    res.json({ code: 0, data: { list: rows, total, page: Number(page), size: limit }, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/venues/bookings/:id/status — 审批（预留：pending → approved/rejected）
router.put('/bookings/:id/status', requirePermission(PERMISSIONS.VENUES_WRITE), async (req, res, next) => {
  try {
    const { status, reason } = req.body || {}
    if (!['approved', 'rejected'].includes(status)) return res.status(400).json({ code: 400, message: 'status 仅支持 approved/rejected' })
    if (status === 'rejected' && !reason) return res.status(400).json({ code: 400, message: '驳回必须填写原因' })
    const [[b]] = await pool.query('SELECT * FROM meeting_bookings WHERE id = ?', [req.params.id])
    if (!b) return res.status(404).json({ code: 404, message: '预订不存在' })
    if (b.status !== 'pending') return res.status(400).json({ code: 400, message: `当前状态 ${b.status}，仅 pending 可审批` })
    await pool.query('UPDATE meeting_bookings SET status = ?, reject_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ?',
      [status, reason || null, req.user.id, b.id])
    const [[row]] = await pool.query('SELECT * FROM meeting_bookings WHERE id = ?', [b.id])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// ════════ 信用分 ════════

// GET /api/venues/credit — 当前用户的信用分/等级/权限
router.get('/credit', async (req, res, next) => {
  try {
    const scope = await getCompanyScope(req)
    const { score, level, ownerType, ownerId } = await computeCredit(scope, req.user.id)
    res.json({
      code: 0,
      data: {
        score, level: level.key, level_label: level.label,
        permissions: { advance_days: level.advanceDays, max_duration_minutes: level.maxDurationMin, daily_limit: level.dailyLimit },
        initial: CREDIT.INITIAL, max: CREDIT.MAX, window_months: CREDIT.WINDOW_MONTHS,
        owner: { type: ownerType, id: ownerId }
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// GET /api/venues/credit/rules — 规则表（力度可调，前台展示/管理用）
router.get('/credit/rules', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT action_key, title, delta, monthly_cap, note, enabled FROM venue_credit_rules ORDER BY delta')
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/venues/credit/adjust — 手工加减分（管理员；违规录入/奖励/修复）
router.post('/credit/adjust', requirePermission(PERMISSIONS.VENUES_WRITE), async (req, res, next) => {
  try {
    const { target, company_id, user_id, action_key, delta, reason, booking_id } = req.body || {}
    if (!reason) return res.status(400).json({ code: 400, message: 'reason 必填' })
    if (!['company', 'user'].includes(target)) return res.status(400).json({ code: 400, message: "target 仅支持 company/user" })
    if (target === 'company' && !company_id) return res.status(400).json({ code: 400, message: 'company_id 必填' })
    if (target === 'user' && !user_id) return res.status(400).json({ code: 400, message: 'user_id 必填' })
    // [credit-scope] R3-2：解析目标归属企业并做范围校验（global 跨企业；企业管理员仅本企业）
    const scope = await getCompanyScope(req)
    let targetCompanyId = null
    if (target === 'company') {
      targetCompanyId = Number(company_id)
    } else {
      const [[tu]] = await pool.query('SELECT company_id FROM users WHERE id = ?', [user_id])
      if (!tu) return res.status(404).json({ code: 404, message: '用户不存在' })
      targetCompanyId = tu.company_id ?? null
    }
    if (!creditScopeAllows(scope, targetCompanyId)) {
      return res.status(403).json({ code: 403, message: '无权调整该企业/用户的信用分' })
    }
    let finalDelta = Number(delta)
    if (action_key) {
      const [[rule]] = await pool.query('SELECT delta FROM venue_credit_rules WHERE action_key = ? AND enabled = 1', [action_key])
      if (!rule) return res.status(400).json({ code: 400, message: 'action_key 不存在或未启用' })
      finalDelta = rule.delta
    }
    if (!Number.isFinite(finalDelta) || finalDelta === 0) return res.status(400).json({ code: 400, message: 'delta 无效（0 不记流水）' })
    const before = await creditScoreOf(target, targetCompanyId, target === 'user' ? Number(user_id) : null)
    const [ret] = await pool.query(
      'INSERT INTO venue_credit_logs (company_id, user_id, booking_id, action_key, delta, reason, created_by) VALUES (?,?,?,?,?,?,?)',
      [target === 'company' ? company_id : null, target === 'company' ? CREDIT_COMPANY_LEVEL_UID : Number(user_id), booking_id || null, action_key || null, finalDelta, reason, req.user.id]
    )
    const after = await creditScoreOf(target, targetCompanyId, target === 'user' ? Number(user_id) : null)
    await writeAuditLog(req, 'CREATE', 'venue_credit_logs', ret && ret.insertId ? ret.insertId : null,
      { score: before.score, owner: before.ownerType + ':' + before.ownerId },
      { score: after.score, delta: finalDelta, action_key: action_key || null, reason, source: 'credit/adjust', target, target_company_id: targetCompanyId, target_user_id: target === 'user' ? Number(user_id) : null, request_id: auditReqId(req) })
    res.json({ code: 0, data: { delta: finalDelta }, message: 'ok' })
  } catch (err) { next(err) }
})

export default router
