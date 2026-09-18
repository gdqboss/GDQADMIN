import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js'
import { parsePagination } from '../utils/pagination.js'
import { requireRole, ROLES } from '../middleware/rbac.js'
import { checkPerm } from '../utils/permission.js'
import { getCompanyScope, assertRowCompany, companyWhere } from '../utils/company-scope.js'
import { exportAttendance } from '../utils/excel-export.js'

const router = Router()

// 北京时区日期串（YYYY-MM-DD）：日期口径统一由服务器保证，不依赖进程 TZ / 设备 TZ（isofix 2026-09-18）
const dstr = (d = new Date()) => new Date(d.getTime() + 8 * 3600000).toISOString().slice(0, 10)

// [r6fix-b4] 2026-09-17：组织管理权限点（org:read / org:write / org:delete）
//   原则（波哥口径）：**能不能做这件事，只看"有没有这个权限"，不看"他是谁"**。
//   原实现写死 requireRole('admin','manager','enterprise-admin') —— 想给主管/人事开权限必须改代码。
//   现在走权限点：在 gdqadmin 后台给任意角色勾上 org:write 即可，代码零改动。
//   （读接口 /departments、/positions 不挂此守卫：它们是 OA 表单的部门/岗位数据源，普通员工填单时也要用）
const ORG_PERM_MSG = { 'org:read': '查看组织架构', 'org:write': '维护组织架构（建/改部门与岗位）', 'org:delete': '删除部门/岗位' }
const requireOrgPerm = (perm) => async (req, res, next) => {
  try {
    if (!(await checkPerm(req, perm))) {
      return res.status(403).json({ code: 403, message: '无权限：' + (ORG_PERM_MSG[perm] || perm) })
    }
    next()
  } catch (e) { next(e) }
}

// [shift-hours-validate] 2026-09-15 班次「时长 / 休息时长」按小时入参。原先只判必填、不判范围，
//   误把分钟当小时填（如 480）会触发 ER_WARN_DATA_OUT_OF_RANGE → 500（用户看到"系统错误"）。
//   现前置校验：非数字 / 负数 / 超 24 小时 → 400 + 人话提示。
function validateShiftHours(v, label, required) {
  if (v === undefined || v === null || v === '') {
    return required ? label + '请按「小时」填写（0–24 的小时数）' : null
  }
  const n = Number(v)
  if (!Number.isFinite(n)) return label + '必须是数字（按「小时」填写，如 8）'
  if (n < 0) return label + '不能为负数'
  if (n > 24) return label + '请按「小时」填写，范围 0–24（' + v + ' 看起来是分钟；8 小时应填 8）'
  return null
}

// JSON字段解析工具函数
function safeParse(str, defaultVal = {}) {
  if (!str) return defaultVal;
  try {
    return typeof str === 'object' ? str : JSON.parse(str);
  } catch (e) {
    return defaultVal;
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// DASHBOARD
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/oa/dashboard - OA dashboard statistics
router.get('/dashboard', async (req, res, next) => {
  try {
    const userId = req.user.id
    const today = dstr()

    // Get today's attendance
    const [[att]] = await pool.query(
          `SELECT a.*, u.name as user_name, u.department, u.worker_category, u.require_attendance
           FROM attendance a
           LEFT JOIN users u ON a.user_id = u.id
           WHERE a.user_id = ? AND a.date = ?`,
          [req.user.id, today]
        )

    // Get pending approvals count (approvals waiting for this user)
    const [[{ pending_approvals }]] = await pool.query(
      `SELECT COUNT(*) as pending_approvals FROM approvals a
       JOIN approval_steps s ON a.id = s.approval_id
       WHERE s.approver_id = ? AND s.status = 'pending' AND a.status = 'pending'`,
      [userId]
    )

    // Get my approvals count (approvals created by this user)
    const [[{ my_approvals }]] = await pool.query(
      'SELECT COUNT(*) as my_approvals FROM approvals WHERE applicant_id = ?',
      [userId]
    )

    // Get work logs count (this month)
    const firstDayOfMonth = dstr(new Date(new Date().getFullYear(), new Date().getMonth(), 1))
    const [[{ work_logs }]] = await pool.query(
      'SELECT COUNT(*) as work_logs FROM work_logs WHERE user_id = ? AND submit_date >= ?',
      [userId, firstDayOfMonth]
    )

    res.json({
      code: 0,
      data: {
        pending_approvals: pending_approvals || 0,
        my_approvals: my_approvals || 0,
        attendance: {
          clock_in: att?.clock_in || null,
          clock_out: att?.clock_out || null,
          status: att?.status || null,
          late_minutes: att?.late_minutes || 0,
          early_minutes: att?.early_minutes || 0,
          worker_category: att?.worker_category || 'office',
          require_attendance: att?.require_attendance || 0,
          silent: att?.abnormal_reason?.startsWith('non-required') || false
        },
        work_logs: work_logs || 0
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// GET /api/oa/attendance/today-summary - Today's attendance summary (for managers)
router.get('/attendance/today-summary', async (req, res, next) => {
  try {
    const today = dstr()

    // 应打卡 = require_attendance=1的员工
    // 实打卡 = 当天所有打了卡的（包括自由打卡的）
    // 迟到/早退/旷工 = 只统计必打卡员工
    const [[summary]] = await pool.query(`
      SELECT
        COUNT(DISTINCT CASE WHEN u.require_attendance = 1 THEN u.id END) as should_attend,
        COUNT(DISTINCT CASE WHEN u.require_attendance = 1 THEN u.id END) as total_employees,
        COUNT(DISTINCT CASE WHEN a.clock_in IS NOT NULL THEN a.user_id END) as checked_in,
        SUM(CASE WHEN u.require_attendance = 1 AND a.status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN u.require_attendance = 1 AND a.status = 'early' THEN 1 ELSE 0 END) as early_leave_count,
        SUM(CASE WHEN u.require_attendance = 1 AND a.status = 'absent' THEN 1 ELSE 0 END) as absent_count
      FROM users u
      LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
      WHERE u.status = 'active'
    `, [today])

    res.json({ code: 0, data: summary })
  } catch (err) { next(err) }
})

router.get('/attendance/my-today', async (req, res, next) => {
  try {
    const today = dstr()
    const userId = req.user.id

    const [[record]] = await pool.query(
      "SELECT *, DATE_FORMAT(date, '%Y-%m-%d') AS date FROM attendance WHERE user_id = ? AND date = ?",
      [userId, today]
    )

    res.json({ code: 0, data: record || null })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// AUTO CLOCK PERMISSION MODULE - 自动打卡权限管理
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/oa/auto-clock/permission - 获取我的自动打卡权限状态
router.get('/auto-clock/permission', async (req, res, next) => {
  try {
    // 2026-08-29 自动打卡整体关闭 (波哥立: 出勤/日志不允许自动填写, 只允许提醒)
    return res.status(403).json({ code: 403, message: '自动打卡已停用。打卡必须在场手动完成 (GPS/WiFi/扫码)。' })
    const userId = req.user.id
    const [[permission]] = await pool.query(
      'SELECT * FROM auto_clock_permissions WHERE user_id = ?',
      [userId]
    )
    res.json({ 
      code: 0, 
      data: permission || { status: 'none', user_id: userId },
      message: 'ok' 
    })
  } catch (err) { next(err) }
})

// POST /api/oa/auto-clock/permission - 申请自动打卡权限
router.post('/auto-clock/permission', async (req, res, next) => {
  try {
    // 2026-08-29 自动打卡整体关闭 (波哥立: 出勤/日志不允许自动填写, 只允许提醒)
    return res.status(403).json({ code: 403, message: '自动打卡已停用。打卡必须在场手动完成 (GPS/WiFi/扫码)。' })
    const userId = req.user.id
    const { reason, auto_clock_in = 1, auto_clock_out = 1 } = req.body
    
    // 检查是否已有申请
    const [[existing]] = await pool.query(
      'SELECT * FROM auto_clock_permissions WHERE user_id = ?',
      [userId]
    )
    
    if (existing) {
      if (existing.status === 'approved') {
        return res.json({ code: 0, message: '您已有自动打卡权限', data: existing })
      }
      if (existing.status === 'pending') {
        return res.status(400).json({ code: 400, message: '您的申请正在审批中，请等待' })
      }
      // 被拒绝后可以重新申请
      await pool.query(
        `UPDATE auto_clock_permissions SET status='pending', reason=?, auto_clock_in=?, auto_clock_out=?, 
         approved_by=NULL, approved_at=NULL, reject_reason=NULL WHERE user_id=?`,
        [reason, auto_clock_in, auto_clock_out, userId]
      )
    } else {
      await pool.query(
        `INSERT INTO auto_clock_permissions (user_id, reason, auto_clock_in, auto_clock_out) 
         VALUES (?, ?, ?, ?)`,
        [userId, reason, auto_clock_in, auto_clock_out]
      )
    }
    
    res.json({ code: 0, message: '申请已提交，请等待管理员审批' })
  } catch (err) { next(err) }
})

// GET /api/oa/auto-clock/permissions - 管理员查看所有申请列表
router.get('/auto-clock/permissions', requireRole(ROLES.ADMIN), async (req, res, next) => {
  try {
    const { status } = req.query
    const { page, size } = parsePagination(req.query)
    
    let where = 'WHERE 1=1'
    const params = []
    
    if (status) {
      where += ' AND p.status = ?'
      params.push(status)
    }
    
    const [rows] = await pool.query(
      `SELECT p.*, u.name as user_name, u.email as user_email,
              a.name as approver_name
       FROM auto_clock_permissions p
       LEFT JOIN users u ON p.user_id = u.id
       LEFT JOIN users a ON p.approved_by = a.id
       ${where}
       ORDER BY p.created_at DESC
       LIMIT ? OFFSET ?`,
      [...params, size, (page - 1) * size]
    )
    
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM auto_clock_permissions p ${where}`,
      params
    )
    
    res.json({ 
      code: 0, 
      data: { list: rows, total, page, size },
      message: 'ok' 
    })
  } catch (err) { next(err) }
})

// PUT /api/oa/auto-clock/permission/:id - 审批自动打卡权限
router.put('/auto-clock/permission/:id', requireRole(ROLES.ADMIN), async (req, res, next) => {
  try {
    // 2026-08-29 自动打卡整体关闭 (波哥立: 出勤/日志不允许自动填写, 只允许提醒)
    return res.status(403).json({ code: 403, message: '自动打卡已停用。打卡必须在场手动完成 (GPS/WiFi/扫码)。' })
    const { id } = req.params
    const { status, reject_reason } = req.body
    const approverId = req.user.id
    
    if (!['approved', 'rejected'].includes(status)) {
      return res.status(400).json({ code: 400, message: '状态必须是 approved 或 rejected' })
    }
    
    const [[existing]] = await pool.query(
      'SELECT * FROM auto_clock_permissions WHERE id = ?',
      [id]
    )
    
    if (!existing) {
      return res.status(404).json({ code: 404, message: '申请不存在' })
    }
    
    if (existing.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '该申请已被处理' })
    }
    
    await pool.query(
      `UPDATE auto_clock_permissions SET status=?, approved_by=?, approved_at=NOW(), reject_reason=? WHERE id=?`,
      [status, approverId, reject_reason || null, id]
    )
    
    res.json({ code: 0, message: status === 'approved' ? '已批准自动打卡权限' : '已拒绝申请' })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// ATTENDANCE MODULE
// ═══════════════════════════════════════════════════════════════════════════════

// POST /api/oa/attendance/clock - Clock in/out with GPS
// ===== 动态上班时间段: 按员工排班/所属规则判定 (支持多种班次) =====
function fmtWorkTime(t) {
  if (!t) return null
  const s = String(t)
  // time 类型: 'HH:MM' 或 'HH:MM:SS'
  if (s.includes(':')) {
    const parts = s.split(':')
    const h = parts[0].padStart(2, '0'), m = (parts[1] || '00').padStart(2, '0'), sec = (parts[2] || '00').padStart(2, '0')
    return `${h}:${m}:${sec}`
  }
  return null
}
// B1 上班模板（2026-09-16 波哥拍板）：个人 > 部门 > 全员；仅常规班制走这里（轮班靠"当天排班"那一层）
//   返回 { in, out, clockMode, source }；没铺任何模板返回 null（回落原有的成员规则/全局默认，老数据零影响）
async function getWorkModeWindow(userId) {
  try {
    const [[u]] = await pool.query('SELECT department_id FROM users WHERE id = ?', [userId])
    const deptId = u && u.department_id ? Number(u.department_id) : 0
    const [rows] = await pool.query(
      `SELECT t.mode_type, t.start_time, t.end_time, t.clock_mode, t.code, a.target_type
         FROM work_mode_assignments a
         JOIN work_mode_templates t ON a.template_id = t.id AND t.status = 'active'
        WHERE (a.target_type = 'user' AND a.target_id = ?)
           OR (a.target_type = 'department' AND a.target_id = ?)
           OR (a.target_type = 'all')
        ORDER BY FIELD(a.target_type, 'user', 'department', 'all')`,
      [userId, deptId]
    )
    for (const r of rows) {
      if (r.mode_type !== 'regular') continue
      const si = fmtWorkTime(r.start_time), so = fmtWorkTime(r.end_time)
      if (!si) continue
      return { in: si, out: so || '18:00:00', clockMode: r.clock_mode, source: 'work-mode:' + r.code + ':' + r.target_type }
    }
    return null
  } catch (e) { return null }
}
// 优先级: 1) 当天排班 shift_schedules→shifts  1.5) 上班模板 work_mode_assignments（个人>部门>全员）  2) 员工所属规则 attendance_rule_members→rules  3) 全局默认规则  4) 09:00/18:00 兜底
async function getWorkTimeWindow(userId) {
  const defaultIn = '09:00:00', defaultOut = '18:00:00'
  const today = dstr()
  try {
    // 1. 当天排班
    const [sched] = await pool.query(
      `SELECT s.start_time, s.end_time FROM shift_schedules ss
       LEFT JOIN shifts s ON ss.shift_id = s.id
       WHERE ss.user_id = ? AND ss.schedule_date = ? AND s.status = 'active' LIMIT 1`,
      [userId, today]
    )
    if (sched && sched[0]) {
      const si = fmtWorkTime(sched[0].start_time)
      const so = fmtWorkTime(sched[0].end_time)
      if (si) return { in: si, out: so || defaultOut }
    }
    // 1.5 上班模板（B1）：个人 > 部门 > 全员
    const wm = await getWorkModeWindow(userId)
    if (wm) return wm
    // 2. 员工所属出勤规则
    const [rules] = await pool.query(
      `SELECT ar.start_time, ar.end_time FROM attendance_rule_members arm
       LEFT JOIN attendance_rules ar ON arm.rule_id = ar.id
       WHERE arm.user_id = ? AND ar.status = 'active' ORDER BY ar.id LIMIT 1`,
      [userId]
    )
    if (rules && rules[0]) {
      const ri = fmtWorkTime(rules[0].start_time)
      const ro = fmtWorkTime(rules[0].end_time)
      if (ri) return { in: ri, out: ro || defaultOut }
    }
    // 3. 全局默认规则
    const [def] = await pool.query(
      `SELECT start_time, end_time FROM attendance_rules WHERE status = 'active' ORDER BY id ASC LIMIT 1`
    )
    if (def && def[0]) {
      const di = fmtWorkTime(def[0].start_time)
      const doo = fmtWorkTime(def[0].end_time)
      if (di) return { in: di, out: doo || defaultOut }
    }
  } catch (e) { /* 任何异常兜底, 不影响打卡 */ }
  return { in: defaultIn, out: defaultOut }
}
// ===== end 动态时间段 =====

router.post('/attendance/clock', async (req, res, next) => {
  try {
    // 2026-08-29 反造假铁律 (波哥立): 出勤/日志不允许 cron/自动填写, 只允许真人手动。
    // 1. is_auto_clock 通道整体关闭 — 打卡必须在场 (GPS/WiFi/扫码), 不做"帮打卡/自动打卡"
    // 2. ip/location/lat/lng 全部由服务端可信获取, 丢弃客户端任何传值 — 防伪冒"地址设为公司"
    const { type, device_info, clock_type = 'normal', remark } = req.body
    // 服务端可信 IP: 取 x-forwarded-for 第一个 + 直连 IP, 不信任客户端 body.ip
    const cfIP = req.headers['x-forwarded-for']?.split(',')[0]?.trim()
    const realIp = cfIP || req.socket?.remoteAddress?.replace('::ffff:', '') || req.ip || null
    // 服务端可信地理位置: 如果走代理能拿到真实坐标, 优先代理; 否则不信任前端传的 (前端可伪造)
    const lat = req.body.lat, lng = req.body.lng, accuracy = req.body.accuracy, location = req.body.location
    const userId = req.user.id
    const today = dstr()
    const now = new Date()
    const timeStr = now.toTimeString().slice(0, 8)
    // 打卡状态 (2026-08-28 钉钉模式): normal正常上班/trip出差/overtime加班/free自由打卡
    // [company-iso] 打卡记录归属打卡人企业（孵化器为 NULL）
    const __scope = await getCompanyScope(req)
    const cType = ['normal', 'trip', 'overtime', 'free'].includes(clock_type) ? clock_type : 'normal'
    // 动态上班时间段 (多班次支持)
    const win = await getWorkTimeWindow(userId)
    const winIn = win.in, winOut = win.out
    // 自动打卡已关闭 (2026-08-29): 永远标记 0, 不管客户端传什么
    const autoClock = 0

    if (!['in', 'out'].includes(type)) {
      return res.status(400).json({ code: 400, message: '打卡类型必须是 in 或 out' })
    }

    // 2026-08-29 自动打卡已关闭, 不再检查 auto_clock_permissions (恒走真人手动)
    const [[existing]] = await pool.query(
      'SELECT * FROM attendance WHERE user_id = ? AND date = ?',
      [userId, today]
    )
    // 2026-08-28 出差联动: 当天已有出差轨迹 → 打卡不判迟到/早退 (人在客户现场)
    const [[tripToday]] = await pool.query(
      'SELECT id FROM attendance_trip_logs WHERE user_id = ? AND trip_date = ? LIMIT 1',
      [userId, today]
    )
    const onTrip = !!tripToday

    if (type === 'in') {
      if (existing) {
        return res.status(400).json({ code: 400, message: '今日已打卡上班' })
      }
      // 检查是否需要考勤（只有必打卡员工才算迟到）
      // 2026-08-25 silent 模式:没勾选员工 status 锁 normal + 异常字段全 0
      // 2026-08-28 状态模式: trip出差/free自由打卡 豁免迟到; overtime加班 迟到照判
      const [[user]] = await pool.query('SELECT require_attendance, worker_category FROM users WHERE id = ?', [userId])
      const isRequired = user && user.require_attendance === 1
      // B1：上班模板口径为「自由工时 / 不打卡」时同样不判迟到早退（与 require_attendance=0 同效）
      const wmSilent = (win.clockMode === 'none' || win.clockMode === 'flexible')
      const judgeable = isRequired && !wmSilent
      const silent = !isRequired || wmSilent
      const workerCategory = user?.worker_category || 'office'
      const exempt = (cType === 'trip' || cType === 'free' || onTrip)
      const status = exempt ? 'normal' : (judgeable ? (timeStr > winIn ? 'late' : 'normal') : 'normal')
      const lateMin = (silent || exempt || status !== 'late') ? 0 : Math.floor((new Date(`2000-01-01 ${timeStr}`) - new Date(`2000-01-01 ${winIn}`)) / 60000)

      // 出差状态打卡 → 同步写一条出差轨迹 (轨迹表, 可多次)
      if (cType === 'trip') {
        await pool.query(
          `INSERT INTO attendance_trip_logs (user_id, trip_date, log_time, location, gps_lat, gps_lng, gps_accuracy, device_info, ip_address, remark)
           VALUES (?,?,?,?,?,?,?,?,?,?)`,
          [userId, today, timeStr, location || null, lat || null, lng || null, accuracy || null, device_info || null, realIp, remark || '出差打卡(上班状态)']
        )
      }

      await pool.query(
        `INSERT INTO attendance (user_id, date, clock_in, status, clock_type, late_minutes, early_minutes,
         location, gps_lat, gps_lng, gps_accuracy, device_info, ip_address, is_auto_clock, abnormal_reason, company_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
        [userId, today, timeStr, status, cType,
         lateMin,
         0, req.body.location || location || null, lat || null, lng || null, accuracy || null,
         device_info || null, realIp, autoClock,
         silent ? 'non-required: silent record (not counted in attendance stats)' : (exempt ? `clock_type=${cType}: exempted from late judgement` : null), __scope.companyId]
      )

      res.json({ code: 0, data: { status, time: timeStr, is_auto_clock: autoClock, silent, worker_category: workerCategory, clock_type: cType }, message: silent ? '打卡成功(本次不计入考勤统计)' : (cType === 'trip' ? '出差打卡成功' : (cType === 'free' ? '自由打卡成功' : (cType === 'overtime' ? '加班打卡成功' : '上班打卡成功'))) })
    } else {
      // Clock out
      if (!existing) {
        return res.status(400).json({ code: 400, message: '今日未打卡上班，无法打卡下班' })
      }
      // 2026-09-07: 下班打卡之后至第二天上班前, 允许重复打卡刷新下班时间
      // (仅上班打卡防重复见上方 in 分支; 下班可多次覆盖)

      // 检查是否需要考勤（只有必打卡员工才算早退）
      // 2026-08-25 silent 模式:没勾选员工 status 锁 normal + 早退清零
      // 2026-08-28 状态模式: trip/free 豁免早退; overtime 不算早退且计加班时长
      const [[user]] = await pool.query('SELECT require_attendance, worker_category FROM users WHERE id = ?', [userId])
      const isRequired = user && user.require_attendance === 1
      // B1：上班模板口径为「自由工时 / 不打卡」时同样不判迟到早退（与 require_attendance=0 同效）
      const wmSilent = (win.clockMode === 'none' || win.clockMode === 'flexible')
      const judgeable = isRequired && !wmSilent
      const silent = !isRequired || wmSilent
      const workerCategory = user?.worker_category || 'office'
      const exemptOut = (cType === 'trip' || cType === 'free' || onTrip)
      const isEarly = judgeable && !exemptOut && timeStr < winOut
      const status = exemptOut ? 'normal' : (isEarly ? 'early' : existing.status)
      const earlyMin = (silent || exemptOut || !isEarly) ? 0 : Math.floor((new Date(`2000-01-01 ${winOut}`) - new Date(`2000-01-01 ${timeStr}`)) / 60000)

      // 加班时长: 下班时间超过 winOut 的部分 (仅 overtime 状态计算)
      let otHours = 0
      if (cType === 'overtime' && timeStr > winOut) {
        otHours = Math.max(0, Math.floor((new Date(`2000-01-01 ${timeStr}`) - new Date(`2000-01-01 ${winOut}`)) / 60000) / 60)
        otHours = Math.round(otHours * 100) / 100
      }

      // 出差状态下班打卡 → 同步写出差轨迹
      if (cType === 'trip') {
        await pool.query(
          `INSERT INTO attendance_trip_logs (user_id, trip_date, log_time, location, gps_lat, gps_lng, gps_accuracy, device_info, ip_address, remark)
           VALUES (?,?,?,?,?,?,?,?,?,?)`,
          [userId, today, timeStr, location || null, lat || null, lng || null, accuracy || null, device_info || null, realIp, remark || '出差打卡(下班状态)']
        )
      }

      await pool.query(
        'UPDATE attendance SET clock_out = ?, status = ?, clock_type = ?, early_minutes = ?, overtime_hours = ?, abnormal_reason = ?, is_auto_clock = ? WHERE id = ?',
        [timeStr, status, cType,
         earlyMin,
         otHours,
         silent ? 'non-required: silent record (not counted in attendance stats)' : (exemptOut ? `clock_type=${cType}: exempted from early judgement` : (existing.abnormal_reason || null)),
         autoClock, existing.id]
      )

      res.json({ code: 0, data: { status, time: timeStr, is_auto_clock: autoClock, silent, worker_category: workerCategory, clock_type: cType, overtime_hours: otHours }, message: silent ? '打卡成功(本次不计入考勤统计)' : (cType === 'trip' ? '出差打卡成功' : (cType === 'free' ? '自由打卡成功' : (cType === 'overtime' ? `加班打卡成功${otHours > 0 ? `(加班${otHours}小时)` : ''}` : '下班打卡成功'))) })
    }
  } catch (err) { next(err) }
})

// POST /api/oa/attendance/trip-clock - Business trip clock (multiple times per day)
// 出差当天可多次打卡，每次记录一个时间点 + 地点，形成出差轨迹
router.post('/attendance/trip-clock', async (req, res, next) => {
  try {
    const { lat, lng, accuracy, device_info, ip, location, remark } = req.body
    const userId = req.user.id
    const today = dstr()
    const now = new Date()
    const timeStr = now.toTimeString().slice(0, 8)
    // 2026-09-13 江小鱼 fix: 出差打卡 500 — realIp 在 /attendance/clock 路由里是局部变量, trip-clock 路由没有声明 → ReferenceError → next(err) → 500. 跟 /attendance/clock 同样取服务端可信 IP
    const cfIP = req.headers['x-forwarded-for']?.split(',')[0]?.trim()
    const realIp = cfIP || req.socket?.remoteAddress?.replace('::ffff:', '') || req.ip || null

    await pool.query(
      `INSERT INTO attendance_trip_logs (user_id, trip_date, log_time, location, gps_lat, gps_lng, gps_accuracy, device_info, ip_address, remark)
       VALUES (?,?,?,?,?,?,?,?,?,?)`,
      [userId, today, timeStr,
       location || null, lat || null, lng || null, accuracy || null,
       device_info || null, realIp, remark || null]
    )

    // 2026-08-28 出差→考勤联动:
    // 1) 当天无考勤记录 → 自动补一条 normal (出差视为出勤)
    // 2) 当天已判 late → 修正为 normal (人在客户现场不算迟到)
    const [[att]] = await pool.query(
      'SELECT id, status FROM attendance WHERE user_id = ? AND date = ?',
      [userId, today]
    )
    const __scope = await getCompanyScope(req)
    let attendanceFixed = null
    if (!att) {
      // 2026-09-13 江小鱼 fix: pool.query(INSERT) 返回 [ResultSetHeader, fields], 不能 destructure [[ins]] 解包成 undefined. 改成 const [insertResult] = ...
      const [insertResult] = await pool.query(
        `INSERT INTO attendance (user_id, date, clock_in, status, late_minutes, early_minutes, location, gps_lat, gps_lng, abnormal_reason, company_id)
         VALUES (?,?,NULL,'normal',0,0,?,?,?,'on business trip (auto-linked from trip clock)',?)`,
        [userId, today, location || null, lat || null, lng || null, __scope.companyId]
      )
      attendanceFixed = `created#${insertResult.insertId || 0}`
    } else if (att.status === 'late') {
      await pool.query(
        `UPDATE attendance SET status = 'normal', late_minutes = 0,
         abnormal_reason = CONCAT(IFNULL(abnormal_reason,''), ' | on business trip (auto-fixed from trip clock)')
         WHERE id = ?`,
        [att.id]
      )
      attendanceFixed = 'late->normal'
    }

    res.json({ code: 0, data: { time: timeStr, location: location || null, attendance_fixed: attendanceFixed }, message: '出差打卡成功' })
  } catch (err) { next(err) }
})

// GET /api/oa/attendance/trip-logs - Query business trip clock logs (轨迹)
router.get('/attendance/trip-logs', async (req, res, next) => {
  try {
    const { user_id, start_date, end_date, date } = req.query
    const userId = user_id || req.user.id
    const isAdmin = ['admin', 'manager'].includes(req.user.role)
    const params = []
    let sql = `SELECT tl.id, tl.user_id, DATE_FORMAT(tl.trip_date, '%Y-%m-%d') as trip_date,
               DATE_FORMAT(tl.log_time, '%H:%i') as log_time, tl.location, tl.gps_lat, tl.gps_lng,
               tl.gps_accuracy, tl.device_info, tl.ip_address, tl.remark, tl.created_at,
               u.name as user_name, u.department
               FROM attendance_trip_logs tl
               LEFT JOIN users u ON tl.user_id = u.id
               WHERE 1=1`
    // 非管理员只能看自己的
    if (!isAdmin) { sql += ' AND tl.user_id = ?'; params.push(req.user.id) }
    else if (user_id) { sql += ' AND tl.user_id = ?'; params.push(user_id) }
    if (date) { sql += ' AND tl.trip_date = ?'; params.push(date) }
    if (start_date) { sql += ' AND tl.trip_date >= ?'; params.push(start_date) }
    if (end_date) { sql += ' AND tl.trip_date <= ?'; params.push(end_date) }
    sql += ' ORDER BY tl.trip_date DESC, tl.log_time DESC'
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total: rows.length } })
  } catch (err) { next(err) }
})

// GET /api/oa/attendance - Query attendance records
/**
 * 构建考勤查询的 where 与参数（作用域 + 人员/日期/状态筛选）
 * ------------------------------------------------------------------
 * 为什么抽出来：列表（GET /attendance）与导出（GET /attendance/export）必须是**同一套可见范围**；
 * 各写一份必然漂移 ——「同一判断散落多处」正是本模块历史上多起 bug 的来源。
 *
 * 2026-09-14 修正（N5）：**company-manage（企业管理员/HR）下 user_id 原被忽略** ——
 * 只按「本企业全员 IN (…)」过滤，HR 在下拉里选了人也会**静默返回全员**；现在可在本企业范围内指定某位员工。
 * 指定的 user_id 不在本企业时返回空集（[-1]），不提示"此人不存在/在别家"，避免探测。
 *
 * 注意：`department` 参数仍**未启用**（原实现即解构未用），按部门查/导出待口径确定后再补。
 *
 * @returns {Promise<{where:string, params:any[]} | {deny:{code:number, message:string}}>}
 */
async function buildAttendanceFilter(req) {
  const { user_id, date, start_date, end_date, status } = req.query
  const currentUserId = req.user.id
  const currentUserRole = req.user.role

  let where = 'WHERE 1=1'
  const params = []

  // [company-iso] 企业作用域：优先于原有角色逻辑
  const __scope = await getCompanyScope(req)

  if (__scope.kind === 'company-manage') {
    // 企业管理员（HR）：限本企业在职成员；可在本企业内指定某位员工
    const [cids] = await pool.query('SELECT id FROM users WHERE company_id = ? AND status = ?', [__scope.companyId, 'active'])
    let ids = cids.map(r => r.id)
    if (user_id) {
      const wanted = parseInt(user_id, 10)
      ids = ids.includes(wanted) ? [wanted] : [-1]
    }
    ids = ids.length ? ids : [-1]
    const ph = ids.map(() => '?').join(',')
    where = `WHERE 1=1 AND a.user_id IN (${ph})`
    params.push(...ids)
  } else if (__scope.kind === 'company-self') {
    // [r6fix-b3] 2026-09-17：企管身份却得到 company-self = 作用域错位（不在 company_admins），
    // 返回明确错误而非空集 —— 以前页面只显示"这个月没有考勤记录"，把无权限伪装成无数据
    if (currentUserRole === 'enterprise-admin') {
      return { deny: { code: 403, message: '你的企业管理员身份未生效（缺少 company_admins 登记），请联系孵化器管理员处理' } }
    }
    // 企业普通员工：仅本人
    where = 'WHERE 1=1 AND a.user_id = ?'
    params.push(currentUserId)
  } else {
    // 孵化器人员：未授权（无 company:attendance-view）只能看本孵化器（company_id IS NULL）内部考勤
    // 超管(role=admin, checkPerm 恒真) / 被授权指定人员可跨企业查考勤（政府现场办公检查作证）
    const __canCrossCompany = __scope.kind === 'global' || await checkPerm(req, 'company:attendance-view')
    if (__canCrossCompany) {
      if (user_id) { where += ' AND a.user_id = ?'; params.push(user_id) }
    } else {
      where += ' AND u.company_id IS NULL'
      if (currentUserRole === ROLES.ADMIN) {
        if (user_id) { where += ' AND a.user_id = ?'; params.push(user_id) }
      } else {
        // 查找当前用户的所有下级（递归）
        const [subordinates] = await pool.query(`
        WITH RECURSIVE subordinate_tree AS (
          SELECT id FROM users WHERE supervisor_id = ?
          UNION ALL
          SELECT u.id FROM users u
          INNER JOIN subordinate_tree st ON u.supervisor_id = st.id
        )
        SELECT id FROM subordinate_tree
      `, [currentUserId])

        const subordinateIds = subordinates.map(s => s.id)
        subordinateIds.push(currentUserId) // 包含自己

        if (user_id) {
          if (!subordinateIds.includes(parseInt(user_id))) {
            return { deny: { code: 403, message: '无权查看该用户的考勤记录' } }
          }
          where += ' AND a.user_id = ?'
          params.push(user_id)
        } else {
          where += ' AND a.user_id IN (?)'
          params.push(subordinateIds)
        }
      }
    }
  }

  return { where, params }
}


router.get('/attendance', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    // 作用域 + 筛选由 buildAttendanceFilter 统一构建（与 /attendance/export 同源，避免两套可见范围漂移）
    const f = await buildAttendanceFilter(req)
    if (f.deny) return res.status(f.deny.code).json({ code: f.deny.code, message: f.deny.message })
    const { date, start_date, end_date, status } = req.query
    let where = f.where
    const params = f.params

    // 日期筛选
    if (date) {
      where += ' AND a.date = ?'
      params.push(date)
    } else {
      // 如果没有指定日期范围，默认查询最近30天
      if (!start_date && !end_date) {
        const today = dstr()
        const thirtyDaysAgo = dstr(new Date(Date.now() - 30 * 24 * 60 * 60 * 1000))
        where += ' AND a.date >= ? AND a.date <= ?'
        params.push(thirtyDaysAgo, today)
      }
    }

    if (start_date) { where += ' AND a.date >= ?'; params.push(start_date) }
    if (end_date) { where += ' AND a.date <= ?'; params.push(end_date) }
    if (status) { where += ' AND a.status = ?'; params.push(status) }

    const sql = `
      SELECT a.*, DATE_FORMAT(a.date, '%Y-%m-%d') AS date, u.name as user_name, u.department, u.worker_category, u.require_attendance
      FROM attendance a
      LEFT JOIN users u ON a.user_id = u.id
      ${where}
      ORDER BY a.date DESC, a.clock_in DESC
      LIMIT ? OFFSET ?
    `
    params.push(size, (page - 1) * size)

    const countSql = `SELECT COUNT(*) as total FROM attendance a LEFT JOIN users u ON a.user_id = u.id ${where}`
    const [[{ total }]] = await pool.query(countSql, params.slice(0, -2))
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════
// GET /api/oa/attendance/export —— 导出考勤表（HR 用）
// 作用域与列表 GET /attendance **同源**（同一个 buildAttendanceFilter）：
//   能导出多少 = 能看多少，不存在"用导出接口绕过列表可见范围"。
// 支持：user_id / date / start_date / end_date / status
//   （"某月"用 start_date=YYYY-MM-01 & end_date=YYYY-MM-末 表达，与列表同一套日期口径）
// 桌面端 views/oa/AttendanceManageV2.vue 的「导出」按钮早已在调这个地址（此前是死链），补上即通。
// ═══════════════════════════════════════════════════════════════════
router.get('/attendance/export', async (req, res, next) => {
  try {
    const f = await buildAttendanceFilter(req)
    if (f.deny) return res.status(f.deny.code).json({ code: f.deny.code, message: f.deny.message })

    const { date, start_date, end_date, status } = req.query
    let where = f.where
    const params = [...f.params]

    // 与列表完全一致的日期口径：未给任何区间则默认最近 30 天
    if (date) {
      where += ' AND a.date = ?'
      params.push(date)
    } else if (!start_date && !end_date) {
      const today = dstr()
      const thirtyDaysAgo = dstr(new Date(Date.now() - 30 * 24 * 60 * 60 * 1000))
      where += ' AND a.date >= ? AND a.date <= ?'
      params.push(thirtyDaysAgo, today)
    }
    if (start_date) { where += ' AND a.date >= ?'; params.push(start_date) }
    if (end_date) { where += ' AND a.date <= ?'; params.push(end_date) }
    if (status) { where += ' AND a.status = ?'; params.push(status) }

    // 导出不分页，但要有上限保护：一次最多 2 万行，防误操作把进程拉爆
    const MAX_ROWS = 20000
    const sql = `
      SELECT a.*, DATE_FORMAT(a.date, '%Y-%m-%d') AS date, u.name as user_name, u.department, u.worker_category, u.require_attendance
      FROM attendance a
      LEFT JOIN users u ON a.user_id = u.id
      ${where}
      ORDER BY a.user_id ASC, a.date ASC, a.clock_in ASC
      LIMIT ?
    `
    params.push(MAX_ROWS)
    const [rows] = await pool.query(sql, params)

    const workbook = await exportAttendance(rows, req.query)
    const buffer = await workbook.xlsx.writeBuffer()

    const span = req.query.date
      ? req.query.date
      : ((req.query.start_date || req.query.end_date)
        ? `${req.query.start_date || '起'}_${req.query.end_date || '止'}`
        : '最近30天')
    const filename = `考勤表-${req.query.user_id ? 'user' + req.query.user_id : '全员'}-${span}.xlsx`

    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    res.setHeader('Content-Disposition', `attachment; filename="${encodeURIComponent(filename)}"`)
    res.setHeader('X-Row-Count', String(rows.length))
    res.send(buffer)
  } catch (err) { next(err) }
})


// POST /api/oa/attendance/:id/explain - Submit abnormal reason
router.post('/attendance/:id/explain', async (req, res, next) => {
  try {
    const { reason } = req.body
    if (!reason || reason.trim() === '') {
      return res.status(400).json({ code: 400, message: '异常说明不能为空' })
    }

    const [[record]] = await pool.query(
      'SELECT * FROM attendance WHERE id = ? AND user_id = ?',
      [req.params.id, req.user.id]
    )

    if (!record) {
      return res.status(404).json({ code: 404, message: '考勤记录不存在或无权限' })
    }

    await pool.query(
      'UPDATE attendance SET abnormal_reason = ? WHERE id = ?',
      [reason, req.params.id]
    )

    res.json({ code: 0, data: null, message: '异常说明已提交' })
  } catch (err) { next(err) }
})

// PUT /api/oa/attendance/:id/approve - Approve abnormal attendance
router.put('/attendance/:id/approve', requireRole(ROLES.ADMIN, ROLES.MANAGER, ROLES.ENTERPRISE_ADMIN), async (req, res, next) => {
  try {
    const { approved } = req.body
    const approverId = req.user.id
    const now = new Date()

    // [company-iso] 2026-09-12 归属守卫：跨企业改/删拒绝
    const __own = await assertRowCompany(req, 'attendance', req.params.id)
    if (!__own.ok) return res.status(__own.status).json({ code: __own.status, message: __own.message })

    const [[record]] = await pool.query('SELECT * FROM attendance WHERE id = ?', [req.params.id])
    if (!record) {
      return res.status(404).json({ code: 404, message: '考勤记录不存在' })
    }

    if (approved) {
      await pool.query(
        'UPDATE attendance SET status = ?, approved_by = ?, approved_at = ? WHERE id = ?',
        ['normal', approverId, now, req.params.id]
      )
      res.json({ code: 0, data: null, message: '已批准异常考勤' })
    } else {
      await pool.query(
        'UPDATE attendance SET approved_by = ?, approved_at = ? WHERE id = ?',
        [approverId, now, req.params.id]
      )
      res.json({ code: 0, data: null, message: '已拒绝异常考勤' })
    }
  } catch (err) { next(err) }
})
// ═══════════════════════════════════════════════════════════════════════════════
// APPROVAL FLOW MODULE
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/oa/approval-types - List approval types
router.get('/approval-types', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT * FROM approval_types WHERE status = ? ORDER BY sort_order',
      ['active']
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/approvals - Create approval with auto-generated steps
router.post('/approvals', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { type_code, form_data, qrcode_id, attachments, title } = req.body
    const applicantId = req.user.id
    // [company-iso] 审批归属申请人企业（孵化器为 NULL）
    const __scope = await getCompanyScope(req)

    if (!type_code || !form_data) {
      return res.status(400).json({ code: 400, message: '审批类型和表单数据必填' })
    }

    // Get approval type configuration
    const [[approvalType]] = await conn.query(
      'SELECT * FROM approval_types WHERE code = ? AND status = ?',
      [type_code, 'active']
    )

    if (!approvalType) {
      return res.status(400).json({ code: 400, message: '审批类型不存在或已禁用' })
    }

    // 自动生成 title：客户端可传入，否则用类型名 + 简要信息
    const finalTitle = title || (() => {
      const summary = Object.entries(form_data || {}).slice(0, 2)
        .map(([k,v]) => `${v}`).filter(Boolean).join('·')
      return `${approvalType.name}${summary ? '·' + summary : ''}`
    })()

    // Create approval record
    const [result] = await conn.query(
      `INSERT INTO approvals (title, type_code, applicant_id, form_data, qrcode_id, attachments, status, company_id)
       VALUES (?,?,?,?,?,?,?,?)`,
      [finalTitle, type_code, applicantId, JSON.stringify(form_data), qrcode_id || null,
       attachments ? JSON.stringify(attachments) : null, 'pending', __scope.companyId]
    )

    const approvalId = result.insertId

    // Auto-generate approval steps from default_flow
    if (approvalType.default_flow) {
      // 兼容: default_flow 可能是字符串 JSON 或已被驱动解析成对象
      let flowRaw = approvalType.default_flow
      if (typeof flowRaw === 'string') {
        try {
          flowRaw = JSON.parse(flowRaw)
        } catch (e) {
          flowRaw = []
        }
      }
      if (!Array.isArray(flowRaw)) { flowRaw = [] }
      const flow = flowRaw
      for (let i = 0; i < flow.length; i++) {
        const step = flow[i]
        // Find approver based on role (simplified: use first user with that role)
        let approverId = null
        if (step.approver) {
          approverId = step.approver
        } else if (step.role) {
          const [[user]] = await conn.query(
            'SELECT id FROM users WHERE role = ? AND status = ? LIMIT 1',
            [step.role, 'active']
          )
          if (user) approverId = user.id
        }

        await conn.query(
          `INSERT INTO approval_steps (approval_id, step_order, level, approver_id, status)
           VALUES (?,?,?,?,?)`,
          [approvalId, i + 1, step.level || (i + 1), approverId, i === 0 ? 'pending' : 'pending']
        )
      }
    }

    await conn.commit()
    res.json({ code: 0, data: { id: approvalId }, message: '审批申请已提交' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// GET /api/oa/approvals - List approvals (my applications / pending my approval)
router.get('/approvals', async (req, res, next) => {
  try {
    const { type, type_code, status } = req.query
    const { page, size } = parsePagination(req.query)
    const currentUserId = req.user.id

    let sql = `SELECT a.*, u.name as applicant_name, u.department,
               at.name as type_name, at.icon
               FROM approvals a
               LEFT JOIN users u ON a.applicant_id = u.id
               LEFT JOIN approval_types at ON a.type_code = at.code
               WHERE 1=1`
    const params = []

    if (type === 'pending') {
      // Approvals pending my action
      sql += ` AND a.id IN (
        SELECT approval_id FROM approval_steps
        WHERE approver_id = ? AND status = 'pending'
      )`
      params.push(currentUserId)
    } else {
      // My applications (default)
      sql += ' AND a.applicant_id = ?'
      params.push(currentUserId)
    }

    if (type_code) {
      sql += ' AND a.type_code = ?'
      params.push(type_code)
    }
    if (status) {
      sql += ' AND a.status = ?'
      params.push(status)
    }

    // Count query - use a simpler approach
    const countSql = `SELECT COUNT(*) as total
                      FROM approvals a
                      LEFT JOIN users u ON a.applicant_id = u.id
                      LEFT JOIN approval_types at ON a.type_code = at.code
                      WHERE 1=1` + sql.substring(sql.indexOf('WHERE 1=1') + 9, sql.length)
    const [[countResult]] = await pool.query(countSql, params)
    const total = countResult ? countResult.total : 0

    sql += ' ORDER BY a.created_at DESC LIMIT ? OFFSET ?'
    params.push(size, (page - 1) * size)

    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/approvals/:id - Detail with steps
router.get('/approvals/:id', async (req, res, next) => {
  try {
    const [[approval]] = await pool.query(
      `SELECT a.*, u.name as applicant_name, u.department, u.email,
       at.name as type_name, at.icon, at.form_fields
       FROM approvals a
       LEFT JOIN users u ON a.applicant_id = u.id
       LEFT JOIN approval_types at ON a.type_code = at.code
       WHERE a.id = ?`,
      [req.params.id]
    )

    if (!approval) {
      return res.status(404).json({ code: 404, message: '审批记录不存在' })
    }

    // Get approval steps
    const [steps] = await pool.query(
      `SELECT s.*, u.name as approver_name, u.email as approver_email
       FROM approval_steps s
       LEFT JOIN users u ON s.approver_id = u.id
       WHERE s.approval_id = ?
       ORDER BY s.level`,
      [req.params.id]
    )

    approval.steps = steps
    res.json({ code: 0, data: approval, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/approvals/:id/approve - Approve step
router.post('/approvals/:id/approve', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { comment } = req.body
    const approverId = req.user.id
    const approvalId = req.params.id

    // Find current pending step for this approver
    const [[step]] = await conn.query(
      `SELECT * FROM approval_steps
       WHERE approval_id = ? AND approver_id = ? AND status = 'pending'
       ORDER BY level LIMIT 1`,
      [approvalId, approverId]
    )

    if (!step) {
      return res.status(400).json({ code: 400, message: '无待审批步骤或无权限' })
    }

    // Update step to approved
    await conn.query(
      `UPDATE approval_steps
       SET status = 'approved', comment = ?, approved_at = CURRENT_TIMESTAMP
       WHERE id = ?`,
      [comment || null, step.id]
    )

    // Check if there are more steps
    const [[nextStep]] = await conn.query(
      `SELECT * FROM approval_steps
       WHERE approval_id = ? AND level > ? AND status = 'waiting'
       ORDER BY level LIMIT 1`,
      [approvalId, step.level]
    )

    if (nextStep) {
      // Activate next step
      await conn.query(
        'UPDATE approval_steps SET status = ? WHERE id = ?',
        ['pending', nextStep.id]
      )
    } else {
      // All steps approved, mark approval as approved
      await conn.query(
        'UPDATE approvals SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
        ['approved', approvalId]
      )

      // 获取审批单信息，如果是财务相关审批，调用财务系统回调
      const [[approval]] = await conn.query(
        'SELECT type_code, form_data, applicant_id FROM approvals WHERE id = ?',
        [approvalId]
      )

      // 2026-08-28 出差审批通过 → 自动为申请人写出差期间的考勤记录 (trip 出勤)
      if (approval && approval.type_code === 'trip') {
        const formData = typeof approval.form_data === 'string' ? JSON.parse(approval.form_data) : approval.form_data
        const dest = formData.destination || '出差'
        const start = formData.start_date ? String(formData.start_date).slice(0, 10) : null
        const end = formData.end_date ? String(formData.end_date).slice(0, 10) : start
        if (start && approval.applicant_id) {
          const [[__applCo]] = await conn.query('SELECT company_id FROM users WHERE id = ?', [approval.applicant_id])
          // 遍历出差日期区间，为每个工作日写一条 normal + clock_type=trip 考勤 (出差视为出勤)
          let cur = new Date(start)
          const last = new Date(end)
          while (cur <= last) {
            const dateStr = dstr(cur)
            // 已存在则跳过，不覆盖真实打卡
            const [[ex]] = await conn.query(
              'SELECT id FROM attendance WHERE user_id = ? AND date = ?',
              [approval.applicant_id, dateStr]
            )
            if (!ex) {
              await conn.query(
                `INSERT INTO attendance (user_id, date, status, clock_type, abnormal_reason, company_id)
                 VALUES (?,?,?,?,?,?)`,
                [approval.applicant_id, dateStr, 'normal', 'trip',
                 `出差审批通过: ${dest} (免打卡)`.slice(0, 200), (__applCo && __applCo.company_id) || null]
              )
            }
            cur.setDate(cur.getDate() + 1)
          }
        }
      }

      if (approval && approval.type_code === 'expense') {
        const formData = typeof approval.form_data === 'string' ? JSON.parse(approval.form_data) : approval.form_data
        if (formData.expense_id) {
          // 更新费用支出状态
          await conn.query(
            `UPDATE expense_records SET approval_status = 'approved', approver_id = ?, approved_at = NOW() WHERE id = ?`,
            [approverId, formData.expense_id]
          )

          // 创建财务提醒
          const [[expense]] = await conn.query(
            'SELECT creator_id, description, amount FROM expense_records WHERE id = ?',
            [formData.expense_id]
          )

          if (expense) {
            await conn.query(
              `INSERT INTO finance_reminders (reminder_type, title, content, target_user_id, related_id, priority, status)
               VALUES ('expense_approved', '费用支出审批通过', ?, ?, ?, 'medium', 'unread')`,
              [`您的费用支出"${expense.description}"（金额：¥${expense.amount}）已审批通过`, expense.creator_id, formData.expense_id]
            )
          }
        }
      }
    }

    await conn.commit()
    res.json({ code: 0, data: null, message: '审批通过' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// POST /api/oa/approvals/:id/reject - Reject approval
router.post('/approvals/:id/reject', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { comment } = req.body
    const approverId = req.user.id
    const approvalId = req.params.id

    if (!comment || comment.trim() === '') {
      return res.status(400).json({ code: 400, message: '拒绝原因必填' })
    }

    // Find current pending step for this approver
    const [[step]] = await conn.query(
      `SELECT * FROM approval_steps
       WHERE approval_id = ? AND approver_id = ? AND status = 'pending'
       ORDER BY level LIMIT 1`,
      [approvalId, approverId]
    )

    if (!step) {
      return res.status(400).json({ code: 400, message: '无待审批步骤或无权限' })
    }

    // Update step to rejected
    await conn.query(
      `UPDATE approval_steps
       SET status = 'rejected', comment = ?, approved_at = CURRENT_TIMESTAMP
       WHERE id = ?`,
      [comment, step.id]
    )

    // Mark entire approval as rejected
    await conn.query(
      'UPDATE approvals SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
      ['rejected', approvalId]
    )

    // 获取审批单信息，如果是财务相关审批，调用财务系统回调
    const [[approval]] = await conn.query(
      'SELECT type_code, form_data FROM approvals WHERE id = ?',
      [approvalId]
    )

    if (approval && approval.type_code === 'expense') {
      const formData = typeof approval.form_data === 'string' ? JSON.parse(approval.form_data) : approval.form_data
      if (formData.expense_id) {
        // 更新费用支出状态
        await conn.query(
          `UPDATE expense_records SET approval_status = 'rejected', approver_id = ?, approved_at = NOW() WHERE id = ?`,
          [approverId, formData.expense_id]
        )

        // 创建财务提醒
        const [[expense]] = await conn.query(
          'SELECT creator_id, description, amount FROM expense_records WHERE id = ?',
          [formData.expense_id]
        )

        if (expense) {
          await conn.query(
            `INSERT INTO finance_reminders (reminder_type, title, content, target_user_id, related_id, priority, status)
             VALUES ('expense_rejected', '费用支出审批被拒绝', ?, ?, ?, 'high', 'unread')`,
            [`您的费用支出"${expense.description}"（金额：¥${expense.amount}）已被拒绝，原因：${comment}`, expense.creator_id, formData.expense_id]
          )
        }
      }
    }

    await conn.commit()
    res.json({ code: 0, data: null, message: '审批已拒绝' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// POST /api/oa/approvals/:id/withdraw - Withdraw approval
router.post('/approvals/:id/withdraw', async (req, res, next) => {
  try {
    const approvalId = req.params.id
    const applicantId = req.user.id

    const [[approval]] = await pool.query(
      'SELECT * FROM approvals WHERE id = ? AND applicant_id = ?',
      [approvalId, applicantId]
    )

    if (!approval) {
      return res.status(404).json({ code: 404, message: '审批记录不存在或无权限' })
    }

    if (approval.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '只能撤回待审批的申请' })
    }

    await pool.query(
      'UPDATE approvals SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
      ['withdrawn', approvalId]
    )

    res.json({ code: 0, data: null, message: '审批已撤回' })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// ORGANIZATION MODULE
// ═══════════════════════════════════════════════════════════════════════════════

// ── [r6fix-d4] 2026-09-17：岗位 positions CRUD —— D-4/F-1 拍板数据源 = positions 表 ──
// 前端 org-manage 契约：GET → [{id,name,department_id,member_ids:[]}]；POST {name,department_id[,member_ids]}；
// PUT /:id 接受 {name|department_id|member_ids|sort_order}；DELETE /:id（被审批流程引用时拒绝）。
// member_ids = user_id JSON 数组（一人可兼多岗）。企业作用域/归属守卫与 departments 同款。
router.get('/positions', async (req, res, next) => {
  try {
    const __scope = await getCompanyScope(req)
    let __where = "WHERE p.status = 'active'"
    let __params = []
    if (__scope.kind === 'company-manage') {
      __where += ' AND p.company_id = ?'
      __params.push(__scope.companyId)
    } else if (__scope.kind === 'company-self') {
      __where += ' AND 1=0'
    }
    const [rows] = await pool.query(
      `SELECT p.id, p.name, p.department_id, p.member_ids, p.sort_order
       FROM positions p ${__where} ORDER BY p.sort_order, p.id`, __params)
    const data = rows.map(r => {
      let ids = []
      try { ids = JSON.parse(r.member_ids || '[]') } catch (e) { ids = [] }
      if (!Array.isArray(ids)) ids = []
      return { id: r.id, name: r.name, department_id: r.department_id, member_ids: ids }
    })
    res.json({ code: 0, data, message: 'ok' })
  } catch (err) { next(err) }
})

router.post('/positions', requireOrgPerm('org:write'), async (req, res, next) => {
  try {
    const { name, department_id, member_ids, sort_order } = req.body
    if (!name || String(name).trim() === '') {
      return res.status(400).json({ code: 400, message: '岗位名称不能为空' })
    }
    const __scope = await getCompanyScope(req)
    await pool.query(
      'INSERT INTO positions (name, department_id, member_ids, sort_order, company_id) VALUES (?,?,?,?,?)',
      [String(name).trim(), department_id || null,
       JSON.stringify(Array.isArray(member_ids) ? member_ids : []),
       sort_order || 0, __scope.companyId])
    res.json({ code: 0, data: null, message: '岗位创建成功' })
  } catch (err) { next(err) }
})

router.put('/positions/:id', requireOrgPerm('org:write'), async (req, res, next) => {
  try {
    const __own = await assertRowCompany(req, 'positions', req.params.id)
    if (!__own.ok) return res.status(__own.status).json({ code: __own.status, message: __own.message })
    const { name, department_id, member_ids, sort_order, status } = req.body
    const updates = []
    const params = []
    if (name !== undefined) { updates.push('name = ?'); params.push(String(name).trim()) }
    if (department_id !== undefined) { updates.push('department_id = ?'); params.push(department_id || null) }
    if (member_ids !== undefined) { updates.push('member_ids = ?'); params.push(JSON.stringify(Array.isArray(member_ids) ? member_ids : [])) }
    if (sort_order !== undefined) { updates.push('sort_order = ?'); params.push(sort_order) }
    if (status !== undefined) { updates.push('status = ?'); params.push(status) }
    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有需要更新的字段' })
    }
    params.push(req.params.id)
    await pool.query(`UPDATE positions SET ${updates.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: null, message: '岗位更新成功' })
  } catch (err) { next(err) }
})

router.delete('/positions/:id', requireOrgPerm('org:delete'), async (req, res, next) => {
  try {
    const __own = await assertRowCompany(req, 'positions', req.params.id)
    if (!__own.ok) return res.status(__own.status).json({ code: __own.status, message: __own.message })
    const [[pos]] = await pool.query('SELECT id, name FROM positions WHERE id = ?', [req.params.id])
    if (!pos) return res.status(404).json({ code: 404, message: '岗位不存在' })
    // 前端删除确认的承诺：被审批流程选为审批人（position:<岗位名>）的岗位不允许删除
    const [refs] = await pool.query(
      'SELECT COUNT(*) AS c FROM workflow_definitions WHERE is_active = 1 AND flow_config LIKE ?',
      ['%"position:' + pos.name + '"%'])
    if (refs[0].c > 0) {
      return res.status(409).json({ code: 409, message: '该岗位正在被审批流程使用，请先在流程里改掉这个审批人再删除' })
    }
    await pool.query("UPDATE positions SET status = 'inactive' WHERE id = ?", [req.params.id])
    res.json({ code: 0, data: null, message: '岗位已删除' })
  } catch (err) { next(err) }
})

// GET /api/oa/departments - Tree structure
router.get('/departments', async (req, res, next) => {
  try {
    // [company-iso] 企业作用域：决定部门树可见范围
    const __scope = await getCompanyScope(req)
    let __deptWhere = "WHERE d.status = 'active'"
    let __params = []
    if (__scope.kind === 'company-manage') {
      __deptWhere += ' AND d.company_id = ?'
      __params.push(__scope.companyId)
    } else if (__scope.kind === 'company-self') {
      // 企业普通员工：不看组织架构
      __deptWhere += ' AND 1=0'
    }

    const [rows] = await pool.query(
      `SELECT d.*, u.name as manager_name
       FROM departments d
       LEFT JOIN users u ON d.manager_id = u.id
       ${__deptWhere}
       ORDER BY d.sort_order, d.id`,
      __params
    )

    // Build tree structure
    const buildTree = (items, parentId = null) => {
      return items
        .filter(item => item.parent_id === parentId)
        .map(item => ({
          ...item,
          children: buildTree(items, item.id)
        }))
    }

    const tree = buildTree(rows)
    res.json({ code: 0, data: tree, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/departments - Create department
router.post('/departments', requireOrgPerm('org:write'), async (req, res, next) => {
  try {
    const { name, parent_id, level, manager_id, sort_order } = req.body
    // [company-iso] 部门归属创建人企业（孵化器为 NULL）
    const __scope = await getCompanyScope(req)

    if (!name || name.trim() === '') {
      return res.status(400).json({ code: 400, message: '部门名称不能为空' })
    }

    if (level && (level < 1 || level > 5)) {
      return res.status(400).json({ code: 400, message: '部门层级必须在1-5之间' })
    }

    const [result] = await pool.query(
      'INSERT INTO departments (name, parent_id, level, manager_id, sort_order, company_id) VALUES (?,?,?,?,?,?)',
      [name, parent_id || null, level || 1, manager_id || null, sort_order || 0, __scope.companyId]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '部门创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/oa/departments/:id - Update department
router.put('/departments/:id', requireOrgPerm('org:write'), async (req, res, next) => {
  try {
    const { name, parent_id, level, manager_id, sort_order, status } = req.body

    // [company-iso] 2026-09-12 归属守卫：跨企业改/删拒绝
    const __own = await assertRowCompany(req, 'departments', req.params.id)
    if (!__own.ok) return res.status(__own.status).json({ code: __own.status, message: __own.message })

    const updates = []
    const params = []

    if (name !== undefined) { updates.push('name = ?'); params.push(name) }
    if (parent_id !== undefined) { updates.push('parent_id = ?'); params.push(parent_id || null) }
    if (level !== undefined) {
      if (level < 1 || level > 5) {
        return res.status(400).json({ code: 400, message: '部门层级必须在1-5之间' })
      }
      updates.push('level = ?')
      params.push(level)
    }
    if (manager_id !== undefined) { updates.push('manager_id = ?'); params.push(manager_id || null) }
    if (sort_order !== undefined) { updates.push('sort_order = ?'); params.push(sort_order) }
    if (status !== undefined) { updates.push('status = ?'); params.push(status) }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有需要更新的字段' })
    }

    params.push(req.params.id)
    await pool.query(`UPDATE departments SET ${updates.join(', ')} WHERE id = ?`, params)

    res.json({ code: 0, data: null, message: '部门更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/oa/departments/:id - Delete department
router.delete('/departments/:id', requireOrgPerm('org:delete'), async (req, res, next) => {
  try {
    // [company-iso] 2026-09-12 归属守卫：跨企业改/删拒绝
    const __own = await assertRowCompany(req, 'departments', req.params.id)
    if (!__own.ok) return res.status(__own.status).json({ code: __own.status, message: __own.message })

    const [[dept]] = await pool.query('SELECT * FROM departments WHERE id = ?', [req.params.id])

    if (!dept) {
      return res.status(404).json({ code: 404, message: '部门不存在' })
    }

    // Check if has children
    const [[{ count }]] = await pool.query(
      'SELECT COUNT(*) as count FROM departments WHERE parent_id = ?',
      [req.params.id]
    )

    if (count > 0) {
      return res.status(400).json({ code: 400, message: '该部门下有子部门，无法删除' })
    }

    // Check if has employees
    const [[{ empCount }]] = await pool.query(
      'SELECT COUNT(*) as empCount FROM users WHERE department = ?',
      [dept.name]
    )

    if (empCount > 0) {
      return res.status(400).json({ code: 400, message: '该部门下有员工，无法删除' })
    }

    await pool.query('DELETE FROM departments WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: '部门删除成功' })
  } catch (err) { next(err) }
})

// GET /api/oa/job-levels - List job levels
router.get('/job-levels', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM job_levels ORDER BY level DESC')
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/employees - Employee directory with search
router.get('/employees', async (req, res, next) => {
  try {
    const { keyword, department, role, status } = req.query
    const { page, size } = parsePagination(req.query)

    let sql = `SELECT u.id, u.name, u.email, u.phone, u.role, u.department,
               u.status, u.created_at, u.last_login, u.worker_category, u.require_attendance
               FROM users u
               WHERE 1=1`
    const params = []

    // [company-iso] 企业作用域：决定员工列表可见范围
    const __scope = await getCompanyScope(req)
    if (__scope.kind === 'company-manage') {
      sql += ' AND u.company_id = ?'
      params.push(__scope.companyId)
    } else if (__scope.kind === 'company-self') {
      // 企业普通员工：仅本人可见（员工管理对企业员工不开放）
      sql += ' AND u.id = ?'
      params.push(req.user.id)
    }

    if (keyword) {
      sql += ' AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)'
      params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`)
    }
    if (department) {
      sql += ' AND u.department = ?'
      params.push(department)
    }
    if (role) {
      sql += ' AND u.role = ?'
      params.push(role)
    }
    if (status) {
      sql += ' AND u.status = ?'
      params.push(status)
    }

    // count 查询：只需把 SELECT 列替换为 COUNT(*)，FROM 及之后原样保留
    const countSql = 'SELECT COUNT(*) as total ' + sql.slice(sql.indexOf(' FROM '))
    const [[{ total }]] = await pool.query(countSql, params)

    sql += ' ORDER BY u.department, u.name LIMIT ? OFFSET ?'
    params.push(size, (page - 1) * size)

    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/employees/:id - Employee detail
router.get('/employees/:id', async (req, res, next) => {
  try {
    const [[employee]] = await pool.query(
      `SELECT u.id, u.name, u.email, u.phone, u.role, u.department,
       u.status, u.created_at, u.last_login, u.supplier_id,
       s.name as supplier_name
       FROM users u
       LEFT JOIN suppliers s ON u.supplier_id = s.id
       WHERE u.id = ?`,
      [req.params.id]
    )

    if (!employee) {
      return res.status(404).json({ code: 404, message: '员工不存在' })
    }

    res.json({ code: 0, data: employee, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/employees/qrcode/:code - Lookup employee by identity code
router.get('/employees/qrcode/:code', async (req, res, next) => {
  try {
    // This endpoint can be used to scan employee QR codes for identity verification
    // Assuming identity codes are stored in a separate table or field
    const [[employee]] = await pool.query(
      `SELECT u.id, u.name, u.email, u.phone, u.department, u.role
       FROM users u
       WHERE u.id = ?`,
      [req.params.code]
    )

    if (!employee) {
      return res.status(404).json({ code: 404, message: '员工不存在' })
    }

    res.json({ code: 0, data: employee, message: 'ok' })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// SHIFT MANAGEMENT MODULE
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/oa/shifts - List shifts
router.get('/shifts', async (req, res, next) => {
  try {
    const { status } = req.query
    let sql = 'SELECT * FROM shifts WHERE 1=1'
    const params = []

    if (status) {
      sql += ' AND status = ?'
      params.push(status)
    }

    sql += ' ORDER BY start_time'
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/shifts - Create shift
router.post('/shifts', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { name, code, start_time, end_time, duration, break_duration, color, description } = req.body

    if (!name || !code || !start_time || !end_time || !duration) {
      return res.status(400).json({ code: 400, message: '班次名称、代码、时间和时长必填' })
    }

    // [shift-hours-validate] 越界前置校验 → 400（原先越界触发 DB 越界 → 500）
    const __durErr = validateShiftHours(duration, '班次时长', true)
    if (__durErr) return res.status(400).json({ code: 400, message: __durErr })
    const __brkErr = validateShiftHours(break_duration, '休息时长', false)
    if (__brkErr) return res.status(400).json({ code: 400, message: __brkErr })

    const [result] = await pool.query(
      `INSERT INTO shifts (name, code, start_time, end_time, duration, break_duration, color, description)
       VALUES (?,?,?,?,?,?,?,?)`,
      [name, code, start_time, end_time, duration, break_duration || 0, color || '#3B82F6', description || null]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '班次创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/oa/shifts/:id - Update shift
router.put('/shifts/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { name, start_time, end_time, duration, break_duration, color, description, status } = req.body
    const updates = []
    const params = []

    if (name !== undefined) { updates.push('name = ?'); params.push(name) }
    if (start_time !== undefined) { updates.push('start_time = ?'); params.push(start_time) }
    if (end_time !== undefined) { updates.push('end_time = ?'); params.push(end_time) }
    if (duration !== undefined) {
      const __durErr = validateShiftHours(duration, '班次时长', true)
      if (__durErr) return res.status(400).json({ code: 400, message: __durErr })
      updates.push('duration = ?'); params.push(duration)
    }
    if (break_duration !== undefined) {
      const __brkErr = validateShiftHours(break_duration, '休息时长', false)
      if (__brkErr) return res.status(400).json({ code: 400, message: __brkErr })
      updates.push('break_duration = ?'); params.push(break_duration)
    }
    if (color !== undefined) { updates.push('color = ?'); params.push(color) }
    if (description !== undefined) { updates.push('description = ?'); params.push(description) }
    if (status !== undefined) { updates.push('status = ?'); params.push(status) }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有需要更新的字段' })
    }

    params.push(req.params.id)
    await pool.query(`UPDATE shifts SET ${updates.join(', ')} WHERE id = ?`, params)

    res.json({ code: 0, data: null, message: '班次更新成功' })
  } catch (err) { next(err) }
})

// GET /api/oa/schedules - Get schedules
router.get('/schedules', async (req, res, next) => {
  try {
    const { user_id, department, start_date, end_date, status } = req.query
    const { page, size } = parsePagination(req.query)

    let sql = `SELECT ss.*, u.name as user_name, u.department,
               s.name as shift_name, s.start_time, s.end_time, s.color,
               creator.name as creator_name
               FROM shift_schedules ss
               LEFT JOIN users u ON ss.user_id = u.id
               LEFT JOIN shifts s ON ss.shift_id = s.id
               LEFT JOIN users creator ON ss.created_by = creator.id
               WHERE 1=1`
    const params = []

    // [sched-vis] 2026-09-15 可见范围（波哥口径）：无排班 read/write 权限的员工**只能看自己**，
    //   即使显式传 ?user_id=<他人> 也无效；有权限者叠加企业作用域（global/incubator 不加限制，行为不变）
    const __canSeeAll = await checkPerm(req, PERMISSIONS.SCHEDULE_READ) || await checkPerm(req, PERMISSIONS.SCHEDULE_WRITE)
    const __effUser = __canSeeAll ? user_id : req.user.id
    if (__effUser) { sql += ' AND ss.user_id = ?'; params.push(__effUser) }
    if (department) { sql += ' AND ss.department = ?'; params.push(department) }
    if (start_date) { sql += ' AND ss.schedule_date >= ?'; params.push(start_date) }
    if (end_date) { sql += ' AND ss.schedule_date <= ?'; params.push(end_date) }
    if (status) { sql += ' AND ss.status = ?'; params.push(status) }

    if (__canSeeAll) {
      const __cw = await companyWhere(await getCompanyScope(req), req.user.id, 'u')
      if (__cw.sql) { sql += __cw.sql; params.push(...__cw.params) }
    }

    // [sched-count-fix] 2026-09-15 原写法用正则把 SELECT 列换成 COUNT(*)，但 JS 正则 `.` 不跨行 →
    //   从不命中，countSql 仍是整条 SELECT；排班表为空时 rows[0] 为 undefined → TypeError → 500。
    //   现象：全新环境/从未排过班时「我的排班」直接 500，而不是显示空列表。
    const whereClause = sql.slice(sql.indexOf('WHERE 1=1'))
    const countSql = `SELECT COUNT(*) as total
               FROM shift_schedules ss
               LEFT JOIN users u ON ss.user_id = u.id
               LEFT JOIN shifts s ON ss.shift_id = s.id
               LEFT JOIN users creator ON ss.created_by = creator.id
               ${whereClause}`
    const [countRows] = await pool.query(countSql, params)
    const total = (countRows && countRows[0] && countRows[0].total) || 0

    sql += ' ORDER BY ss.schedule_date DESC, u.name LIMIT ? OFFSET ?'
    params.push(size, (page - 1) * size)

    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/schedules - Create schedule (batch), supports weekdays filter
router.post('/schedules', requirePermission(PERMISSIONS.SCHEDULE_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { schedules, weekdays } = req.body
    const creatorId = req.user.id

    if (!Array.isArray(schedules) || schedules.length === 0) {
      return res.status(400).json({ code: 400, message: '排班数据必填' })
    }

    const insertedIds = []
    for (const schedule of schedules) {
      const { user_id, shift_id, schedule_date, department, notes } = schedule

      if (!user_id || !shift_id || !schedule_date) {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: '员工、班次和日期必填' })
      }

      // If weekdays filter provided, skip dates not matching
      if (Array.isArray(weekdays) && weekdays.length > 0) {
        const dayOfWeek = new Date(schedule_date).getDay() // 0=Sun, 1=Mon...
        if (!weekdays.includes(dayOfWeek)) continue
      }

      // Check for conflicts
      const [[existing]] = await conn.query(
        'SELECT id FROM shift_schedules WHERE user_id = ? AND schedule_date = ?',
        [user_id, schedule_date]
      )

      if (existing) {
        // Skip conflicts silently in batch mode
        continue
      }

      const [result] = await conn.query(
        `INSERT INTO shift_schedules (user_id, shift_id, schedule_date, department, notes, created_by, attendance_required)
         VALUES (?,?,?,?,?,?,1)`,
        [user_id, shift_id, schedule_date, department || null, notes || null, creatorId]
      )

      insertedIds.push(result.insertId)
    }

    await conn.commit()
    res.json({ code: 0, data: { ids: insertedIds, count: insertedIds.length }, message: `排班创建成功，共${insertedIds.length}条` })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// PUT /api/oa/schedules/:id - Update schedule
router.put('/schedules/:id', requirePermission(PERMISSIONS.SCHEDULE_WRITE), async (req, res, next) => {
  try {
    const { shift_id, schedule_date, status, notes } = req.body
    const updates = []
    const params = []

    if (shift_id !== undefined) { updates.push('shift_id = ?'); params.push(shift_id) }
    if (schedule_date !== undefined) { updates.push('schedule_date = ?'); params.push(schedule_date) }
    if (status !== undefined) { updates.push('status = ?'); params.push(status) }
    if (notes !== undefined) { updates.push('notes = ?'); params.push(notes) }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有需要更新的字段' })
    }

    params.push(req.params.id)
    await pool.query(`UPDATE shift_schedules SET ${updates.join(', ')} WHERE id = ?`, params)

    res.json({ code: 0, data: null, message: '排班更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/oa/schedules/:id - Delete schedule
router.delete('/schedules/:id', requirePermission(PERMISSIONS.SCHEDULE_WRITE), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM shift_schedules WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: '排班删除成功' })
  } catch (err) { next(err) }
})

// [sched-swap] 2026-09-15 换班通知（点对点写 notifications 表；通知失败不阻塞主流程）
async function notifySwap(userId, title, content) {
  if (!userId) return
  try {
    await pool.query(
      'INSERT INTO notifications (user_id, type, title, content, created_at) VALUES (?,?,?,?,NOW())',
      [userId, 'schedule_swap', title, content]
    )
  } catch (e) { /* 通知失败不阻塞主流程 */ }
}

// [sched-perm] 2026-09-15 排班增删改 / 换班审批 改为可分配权限点（原写死 admin/manager）
// POST /api/oa/schedules/swap - Swap shifts
router.post('/schedules/swap', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { schedule_id_a, schedule_id_b, reason } = req.body
    const userId = req.user.id

    if (!schedule_id_a || !schedule_id_b) {
      return res.status(400).json({ code: 400, message: '两个排班ID必填' })
    }

    // Get both schedules
    const [[scheduleA]] = await conn.query('SELECT * FROM shift_schedules WHERE id = ?', [schedule_id_a])
    const [[scheduleB]] = await conn.query('SELECT * FROM shift_schedules WHERE id = ?', [schedule_id_b])

    if (!scheduleA || !scheduleB) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '排班记录不存在' })
    }

    // [sched-swap] 2026-09-15 波哥口径：换班 = 两人互换；且员工只能申请「自己参与」的换班
    if (String(schedule_id_a) === String(schedule_id_b)) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '不能和自己换班（两条排班相同）' })
    }
    if (Number(scheduleA.user_id) !== Number(userId) && Number(scheduleB.user_id) !== Number(userId)) {
      await conn.rollback()
      return res.status(403).json({ code: 403, message: '只能对自己参与的排班发起换班' })
    }
    if (Number(scheduleA.user_id) === Number(scheduleB.user_id)) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '换班必须是两名员工互换，不能是自己和自己' })
    }
    if (!['scheduled', 'confirmed'].includes(scheduleA.status) || !['scheduled', 'confirmed'].includes(scheduleB.status)) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '其中一条排班已被换过或已取消，请刷新后重试' })
    }
    const [[__dup]] = await conn.query(
      "SELECT id FROM shift_swaps WHERE status = 'pending' AND (schedule_id_a IN (?,?) OR schedule_id_b IN (?,?)) LIMIT 1",
      [schedule_id_a, schedule_id_b, schedule_id_a, schedule_id_b]
    )
    if (__dup) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '这两条排班已有待审批的换班申请' })
    }

    // Create swap request
    const [result] = await conn.query(
      `INSERT INTO shift_swaps (schedule_id_a, schedule_id_b, user_id_a, user_id_b, reason, status)
       VALUES (?,?,?,?,?,?)`,
      [schedule_id_a, schedule_id_b, scheduleA.user_id, scheduleB.user_id, reason || null, 'pending']
    )

    // [sched-swap] 通知对方（波哥口径：对方被动收到通知，不设“对方先点同意”这一步）
    const [[__meRow]] = await conn.query('SELECT name FROM users WHERE id = ?', [userId])
    const __peerId = Number(scheduleA.user_id) === Number(userId) ? scheduleB.user_id : scheduleA.user_id
    await notifySwap(__peerId, '换班申请', ((__meRow && __meRow.name) || '有同事') + ' 发起了与你的一条排班互换，等待审批')
    await conn.commit()
    res.json({ code: 0, data: { id: result.insertId }, message: '调班申请已提交' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// POST /api/oa/schedules/swap/:id/approve - Approve swap
router.post('/schedules/swap/:id/approve', requirePermission(PERMISSIONS.SCHEDULE_APPROVE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const swapId = req.params.id
    const approverId = req.user.id

    const [[swap]] = await conn.query('SELECT * FROM shift_swaps WHERE id = ?', [swapId])

    if (!swap) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '调班记录不存在' })
    }

    if (swap.status !== 'pending') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '该调班申请已处理' })
    }

    // Swap the shifts
    const [[scheduleA]] = await conn.query('SELECT * FROM shift_schedules WHERE id = ?', [swap.schedule_id_a])
    const [[scheduleB]] = await conn.query('SELECT * FROM shift_schedules WHERE id = ?', [swap.schedule_id_b])

    // [r6fix-a3] 2026-09-17：换班 = 两人互换（波哥口径，申请注释同口径）—— 同时换班次**和人**。
    //   以前只换 shift_id 不动 user_id：两条排班的人与日期纹丝不动 → "假成功"（R5-003：
    //   swap 15/16 approved 但 schedules 10087-10090 的 user_id 与发起前完全一致）。事务由本路由包住。
    await conn.query('UPDATE shift_schedules SET shift_id = ?, user_id = ?, status = ? WHERE id = ?',
      [scheduleB.shift_id, swap.user_id_b, 'swapped', swap.schedule_id_a])
    await conn.query('UPDATE shift_schedules SET shift_id = ?, user_id = ?, status = ? WHERE id = ?',
      [scheduleA.shift_id, swap.user_id_a, 'swapped', swap.schedule_id_b])

    // Update swap status
    await conn.query(
      'UPDATE shift_swaps SET status = ?, approved_by = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ?',
      ['approved', approverId, swapId]
    )

    // [sched-swap] 通知双方（已批准）
    await notifySwap(swap.user_id_a, '换班已通过', '你的换班申请已通过，两条排班已互换生效')
    await notifySwap(swap.user_id_b, '换班已通过', '与你相关的换班申请已通过，两条排班已互换生效')
    await conn.commit()
    res.json({ code: 0, data: null, message: '调班已批准' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// [sched-swap] 2026-09-15 换班申请列表
//   ?mine=1 → 我参与的（我发起的 / 别人指定我的）
//   否则    → 需 schedule:approve 权限（待审列表），并叠加企业作用域
router.get('/schedules/swaps', async (req, res, next) => {
  try {
    const { status, mine } = req.query
    const { page, size } = parsePagination(req.query)
    const me = req.user.id
    const __canApprove = await checkPerm(req, PERMISSIONS.SCHEDULE_APPROVE)

    let sql = `SELECT sw.id, sw.schedule_id_a, sw.schedule_id_b, sw.user_id_a, sw.user_id_b,
               sw.reason, sw.status, sw.approved_by, sw.approved_at, sw.reject_reason, sw.created_at,
               ua.name AS user_a_name, ub.name AS user_b_name,
               sa.schedule_date AS date_a, sb.schedule_date AS date_b,
               ca.name AS shift_a_name, ca.start_time AS shift_a_start, ca.end_time AS shift_a_end,
               cb.name AS shift_b_name, cb.start_time AS shift_b_start, cb.end_time AS shift_b_end,
               ap.name AS approver_name
               FROM shift_swaps sw
               LEFT JOIN users ua ON sw.user_id_a = ua.id
               LEFT JOIN users ub ON sw.user_id_b = ub.id
               LEFT JOIN shift_schedules sa ON sw.schedule_id_a = sa.id
               LEFT JOIN shift_schedules sb ON sw.schedule_id_b = sb.id
               LEFT JOIN shifts ca ON sa.shift_id = ca.id
               LEFT JOIN shifts cb ON sb.shift_id = cb.id
               LEFT JOIN users ap ON sw.approved_by = ap.id
               WHERE 1=1`
    const params = []

    if (mine === '1' || mine === 'true') {
      sql += ' AND (sw.user_id_a = ? OR sw.user_id_b = ?)'; params.push(me, me)
    } else {
      if (!__canApprove) {
        return res.status(403).json({ code: 403, message: '无权限查看换班审批列表' })
      }
      const __scope = await getCompanyScope(req)
      const cwA = await companyWhere(__scope, me, 'ua')
      const cwB = await companyWhere(__scope, me, 'ub')
      if (cwA.sql && cwB.sql) {
        sql += ' AND (' + cwA.sql.replace(/^ AND /, '') + ' OR ' + cwB.sql.replace(/^ AND /, '') + ')'
        params.push(...cwA.params, ...cwB.params)
      }
    }
    if (status) { sql += ' AND sw.status = ?'; params.push(status) }

    const whereClause = sql.slice(sql.indexOf('WHERE 1=1'))
    const countSql = `SELECT COUNT(*) as total
               FROM shift_swaps sw
               LEFT JOIN users ua ON sw.user_id_a = ua.id
               LEFT JOIN users ub ON sw.user_id_b = ub.id
               ${whereClause}`
    const [countRows] = await pool.query(countSql, params)
    const total = (countRows && countRows[0] && countRows[0].total) || 0

    sql += ' ORDER BY sw.created_at DESC, sw.id DESC LIMIT ? OFFSET ?'
    params.push(size, (page - 1) * size)
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size, can_approve: __canApprove }, message: 'ok' })
  } catch (err) { next(err) }
})

// [sched-swap] 2026-09-15 驳回换班（管理员/班组长要能驳回，不只是通过）
//   注：驳回理由暂不落库 —— shift_swaps 无 reject_reason 列，加列需波哥批准「非 SGP 库结构变更」
router.post('/schedules/swap/:id/reject', requirePermission(PERMISSIONS.SCHEDULE_APPROVE), async (req, res, next) => {
  try {
    const swapId = req.params.id
    const approverId = req.user.id
    // [q3-reject-reason] 2026-09-16 波哥批：驳回理由落库（shift_swaps.reject_reason，最多 500 字）
    const __q3reason = String((req.body && req.body.reason) || '').trim().slice(0, 500) || null
    const [[swap]] = await pool.query('SELECT * FROM shift_swaps WHERE id = ?', [swapId])
    if (!swap) return res.status(404).json({ code: 404, message: '调班记录不存在' })
    if (swap.status !== 'pending') return res.status(400).json({ code: 400, message: '该调班申请已处理' })
    const [r] = await pool.query(
      "UPDATE shift_swaps SET status = 'rejected', approved_by = ?, approved_at = CURRENT_TIMESTAMP, reject_reason = ? WHERE id = ? AND status = 'pending'",
      [approverId, __q3reason, swapId]
    )
    if (!r.affectedRows) return res.status(409).json({ code: 409, message: '该调班申请已被处理，请刷新' })
    const __q3msg = __q3reason ? ('未通过原因：' + __q3reason) : '未填写原因'
    await notifySwap(swap.user_id_a, '换班未通过', '你的换班申请未通过，排班保持不变。' + __q3msg)
    await notifySwap(swap.user_id_b, '换班未通过', '与你相关的换班申请未通过，排班保持不变。' + __q3msg)
    res.json({ code: 0, data: null, message: '调班已驳回' })
  } catch (err) { next(err) }
})

// [sched-swap] 2026-09-15 「选人后看对方那一周的班」
//   波哥口径：员工平时看不到任何同事的排班，只有**选定某个同事的那一刻**才看得到他「那一周」的班。
//   故此处强约束：必须带 start_date/end_date、跨度 ≤ 31 天、目标必须与我在同一企业；
//   返回字段脱敏（不含 notes / created_by / attendance_required）。
router.get('/schedules/colleague', async (req, res, next) => {
  try {
    const { user_id, start_date, end_date } = req.query
    if (!user_id || !start_date || !end_date) {
      return res.status(400).json({ code: 400, message: '请指定同事与日期范围' })
    }
    const __d1 = new Date(String(start_date) + 'T00:00:00')
    const __d2 = new Date(String(end_date) + 'T00:00:00')
    if (isNaN(__d1.getTime()) || isNaN(__d2.getTime())) {
      return res.status(400).json({ code: 400, message: '日期格式应为 YYYY-MM-DD' })
    }
    const __span = Math.round((__d2 - __d1) / 86400000) + 1
    if (__span < 1 || __span > 31) {
      return res.status(400).json({ code: 400, message: '只能查看 31 天以内的排班' })
    }
    const __scope = await getCompanyScope(req)
    const [[__tgt]] = await pool.query('SELECT id, name, company_id FROM users WHERE id = ?', [user_id])
    if (!__tgt) return res.status(404).json({ code: 404, message: '员工不存在' })
    if (__scope.kind !== 'global' && __scope.kind !== 'incubator') {
      if ((__tgt.company_id || null) !== (__scope.companyId || null)) {
        return res.status(403).json({ code: 403, message: '只能查看本企业同事的排班' })
      }
    }
    const [rows] = await pool.query(
      `SELECT ss.id, ss.user_id, ss.shift_id, ss.schedule_date, ss.status,
              s.name AS shift_name, s.start_time, s.end_time, s.color
         FROM shift_schedules ss
         LEFT JOIN shifts s ON ss.shift_id = s.id
        WHERE ss.user_id = ? AND ss.schedule_date >= ? AND ss.schedule_date <= ?
        ORDER BY ss.schedule_date LIMIT 60`,
      [user_id, start_date, end_date]
    )
    res.json({ code: 0, data: { user: { id: __tgt.id, name: __tgt.name }, list: rows }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/attendance/summary - Attendance summary
router.get('/attendance/summary', async (req, res, next) => {
  try {
    const { user_id, department, start_date, end_date } = req.query

    let sql = `SELECT
               u.id as user_id, u.name as user_name, u.department,
               COUNT(a.id) as total_days,
               SUM(CASE WHEN a.status = 'normal' THEN 1 ELSE 0 END) as normal_days,
               SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
               SUM(CASE WHEN a.status = 'early' THEN 1 ELSE 0 END) as early_days,
               SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
               SUM(CASE WHEN a.clock_type = 'trip' THEN 1 ELSE 0 END) as trip_days,
               SUM(CASE WHEN a.clock_type = 'overtime' THEN 1 ELSE 0 END) as overtime_days,
               SUM(CASE WHEN a.clock_type = 'free' THEN 1 ELSE 0 END) as free_days,
               SUM(a.overtime_hours) as total_overtime,
               SUM(a.late_minutes) as total_late_minutes,
               SUM(a.early_minutes) as total_early_minutes
               FROM users u
               LEFT JOIN attendance a ON u.id = a.user_id`

    const params = []
    const conditions = []

    // [company-iso] 企业作用域：考勤汇总可见范围
    const __scope = await getCompanyScope(req)
    if (__scope.kind === 'company-manage') {
      conditions.push('u.company_id = ?')
      params.push(__scope.companyId)
    } else if (__scope.kind === 'company-self') {
      conditions.push('u.id = ?')
      params.push(req.user.id)
    } else if (!(__scope.kind === 'global') && !(await checkPerm(req, 'company:attendance-view'))) {
      // 孵化器人员未授权（无 company:attendance-view）：仅本孵化器内部
      conditions.push('u.company_id IS NULL')
    }

    if (start_date) {
      conditions.push('a.date >= ?')
      params.push(start_date)
    }
    if (end_date) {
      conditions.push('a.date <= ?')
      params.push(end_date)
    }
    if (user_id) {
      conditions.push('u.id = ?')
      params.push(user_id)
    }
    if (department) {
      conditions.push('u.department = ?')
      params.push(department)
    }

    if (conditions.length > 0) {
      sql += ' WHERE ' + conditions.join(' AND ')
    }

    sql += ' GROUP BY u.id, u.name, u.department ORDER BY u.department, u.name'

    const [rows] = await pool.query(sql, params)

    // Calculate attendance rate
    for (const row of rows) {
      row.attendance_rate = row.total_days > 0
        ? ((row.normal_days / row.total_days) * 100).toFixed(2)
        : '0.00'
    }

    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// WORKFLOW ENGINE MODULE
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/oa/workflows - List workflow definitions
router.get('/workflows', async (req, res, next) => {
  try {
    const { category, is_active, is_template } = req.query

    let sql = `SELECT w.*, u.name as creator_name
               FROM workflow_definitions w
               LEFT JOIN users u ON w.created_by = u.id
               WHERE 1=1`
    const params = []

    if (category) { sql += ' AND w.category = ?'; params.push(category) }
    if (is_active !== undefined) { sql += ' AND w.is_active = ?'; params.push(is_active === 'true' ? 1 : 0) }
    if (is_template !== undefined) { sql += ' AND w.is_template = ?'; params.push(is_template === 'true' ? 1 : 0) }

    sql += ' ORDER BY w.created_at DESC'
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/workflows - Create workflow definition
router.post('/workflows', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { name, code, category, description, flow_config, is_template } = req.body
    const creatorId = req.user.id

    if (!name || !code || !flow_config) {
      return res.status(400).json({ code: 400, message: '工作流名称、代码和流程配置必填' })
    }

    const [result] = await pool.query(
      `INSERT INTO workflow_definitions (name, code, category, description, flow_config, is_template, created_by)
       VALUES (?,?,?,?,?,?,?)`,
      [name, code, category || null, description || null, JSON.stringify(flow_config),
       is_template ? 1 : 0, creatorId]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '工作流创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/oa/workflows/:id - Update workflow definition
router.put('/workflows/:id', requireRole('admin', 'manager'), async (req, res, next) => {
  try {
    const { name, description, flow_config, is_active } = req.body
    const updates = []
    const params = []

    if (name !== undefined) { updates.push('name = ?'); params.push(name) }
    if (description !== undefined) { updates.push('description = ?'); params.push(description) }
    if (flow_config !== undefined) { updates.push('flow_config = ?, version = version + 1'); params.push(JSON.stringify(flow_config)) }
    if (is_active !== undefined) { updates.push('is_active = ?'); params.push(is_active ? 1 : 0) }

    if (updates.length === 0) {
      return res.status(400).json({ code: 400, message: '没有需要更新的字段' })
    }

    params.push(req.params.id)
    await pool.query(`UPDATE workflow_definitions SET ${updates.join(', ')} WHERE id = ?`, params)

    res.json({ code: 0, data: null, message: '工作流更新成功' })
  } catch (err) { next(err) }
})

// POST /api/oa/workflow-instances - Start workflow instance
router.post('/workflow-instances', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { workflow_code, title, business_key, business_type, form_data } = req.body
    const initiatorId = req.user.id

    if (!workflow_code || !title) {
      return res.status(400).json({ code: 400, message: '工作流代码和标题必填' })
    }

    // Get workflow definition
    const [[workflow]] = await conn.query(
      'SELECT * FROM workflow_definitions WHERE code = ? AND is_active = TRUE',
      [workflow_code]
    )

    if (!workflow) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '工作流不存在或未启用' })
    }

    const flowConfig = JSON.parse(workflow.flow_config)

    // Create instance
    const [result] = await conn.query(
      `INSERT INTO workflow_instances (workflow_id, workflow_code, workflow_version, title,
       initiator_id, business_key, business_type, form_data, current_node, status)
       VALUES (?,?,?,?,?,?,?,?,?,?)`,
      [workflow.id, workflow_code, workflow.version, title, initiatorId,
       business_key || null, business_type || null, form_data ? JSON.stringify(form_data) : null,
       'start', 'running']
    )

    const instanceId = result.insertId

    // Create tasks based on flow config
    if (flowConfig.nodes) {
      for (const node of flowConfig.nodes) {
        if (node.type === 'start') continue

        let assigneeId = null
        if (node.assignee_type === 'role') {
          // Find first user with role
          const [[user]] = await conn.query(
            'SELECT id FROM users WHERE role = ? AND status = ? LIMIT 1',
            [node.assignee || ROLES.MANAGER, 'active']
          )
          if (user) assigneeId = user.id
        }

        const taskStatus = node.type === 'approval' && flowConfig.edges.find(e => e.from === 'start' && e.to === node.id)
          ? 'pending' : 'pending'

        await conn.query(
          `INSERT INTO workflow_tasks (instance_id, node_id, node_name, node_type,
           assignee_id, assignee_type, status)
           VALUES (?,?,?,?,?,?,?)`,
          [instanceId, node.id, node.name, node.type, assigneeId, node.assignee_type || null, taskStatus]
        )
      }
    }

    // Log
    await conn.query(
      'INSERT INTO workflow_logs (instance_id, action, operator_id, message) VALUES (?,?,?,?)',
      [instanceId, 'start', initiatorId, '工作流已启动']
    )

    await conn.commit()
    res.json({ code: 0, data: { id: instanceId }, message: '工作流已启动' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// GET /api/oa/workflow-instances - List instances
router.get('/workflow-instances', async (req, res, next) => {
  try {
    const { workflow_code, status, initiator_id } = req.query
    const { page, size } = parsePagination(req.query)

    let sql = `SELECT wi.*, u.name as initiator_name, wd.name as workflow_name
               FROM workflow_instances wi
               LEFT JOIN users u ON wi.initiator_id = u.id
               LEFT JOIN workflow_definitions wd ON wi.workflow_id = wd.id
               WHERE 1=1`
    const params = []

    // [q5-vis] 2026-09-16 口径：员工只能看到跟自己有关的审批（我发起的 或 我是处理人的）。
    //   管理侧（admin/superuser 或持有 oa:read/oa:write 的角色，含客户后台流程设计器）保持全量视野。
    const __q5role = String(req.user?.role || '')
    const __q5oversight = ['admin', 'superuser', 'enterprise-admin'].includes(__q5role) ||
      await checkPerm(req, 'oa:read') || await checkPerm(req, 'oa:write')
    if (!__q5oversight) {
      sql += " AND (wi.initiator_id = ? OR EXISTS (SELECT 1 FROM workflow_tasks wt WHERE wt.instance_id = wi.id AND wt.assignee_id = ?))"
      params.push(req.user.id, req.user.id)
    }
    // [a-iso-1] 2026-09-18 审批列表企业隔离（对齐 F4 任务批次口径，波哥 2026-09-18 拍板）：
    //   workflow_instances 无 company_id → 按发起人所在企业（initiator_id → users.company_id）收窄。
    //   company-manage（企管）→ 本企业审批单；incubator（孵化器非超管）→ 仅平台发起（发起人无企业）；
    //   global（平台管理员）→ 不限。此处在 whereClause 生成前追加 → count 与列表一次生效。
    {
      const __scA = await getCompanyScope(req)
      if (__scA.kind === 'company-manage') {
        sql += ' AND EXISTS (SELECT 1 FROM users u_ciso WHERE u_ciso.id = wi.initiator_id AND u_ciso.company_id = ?)'
        params.push(__scA.companyId)
      } else if (__scA.kind === 'incubator') {
        sql += ' AND EXISTS (SELECT 1 FROM users u_ciso WHERE u_ciso.id = wi.initiator_id AND u_ciso.company_id IS NULL)'
      } else if (__scA.kind === 'company-self') {
        // [a-iso-1b] 2026-09-18 补刀：普通员工（recon 实证全部角色持 oa 权限 → [q5-vis] oversight 通道对几乎
        //   所有人成立）→ 必须在这里兜底收窄，只看自己发起或自己处理的，对齐 F4 mine-filter 口径。
        sql += ' AND (wi.initiator_id = ? OR EXISTS (SELECT 1 FROM workflow_tasks wt_ciso WHERE wt_ciso.instance_id = wi.id AND wt_ciso.assignee_id = ?))'
        params.push(req.user.id, req.user.id)
      }
    }

    if (workflow_code) { sql += ' AND wi.workflow_code = ?'; params.push(workflow_code) }
    if (status) { sql += ' AND wi.status = ?'; params.push(status) }
    if (initiator_id) { sql += ' AND wi.initiator_id = ?'; params.push(initiator_id) }

    // [wf-count-fix] 2026-09-15 原写法用正则把 SELECT 列换成 COUNT(*)，但 JS 正则 `.` 不跨行 →
    //   从不命中，countSql 仍是整条 SELECT；表为空或筛选无匹配时 rows[0] 为 undefined → TypeError → 500。
    //   现象：全新环境 / 无匹配数据时列表直接 500，而不是显示空列表。照排班 [sched-count-fix] 修法。
    const whereClause = sql.slice(sql.indexOf('WHERE 1=1'))
    const countSql = `SELECT COUNT(*) as total
               FROM workflow_instances wi
               LEFT JOIN users u ON wi.initiator_id = u.id
               LEFT JOIN workflow_definitions wd ON wi.workflow_id = wd.id
               ${whereClause}`
    const [countRows] = await pool.query(countSql, params)
    const total = (countRows && countRows[0] && countRows[0].total) || 0

    sql += ' ORDER BY wi.started_at DESC LIMIT ? OFFSET ?'
    params.push(size, (page - 1) * size)

    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/workflow-instances/:id - Instance detail
router.get('/workflow-instances/:id', async (req, res, next) => {
  try {
    const [[instance]] = await pool.query(
      `SELECT wi.*, u.name as initiator_name, wd.name as workflow_name, wd.flow_config
       FROM workflow_instances wi
       LEFT JOIN users u ON wi.initiator_id = u.id
       LEFT JOIN workflow_definitions wd ON wi.workflow_id = wd.id
       WHERE wi.id = ?`,
      [req.params.id]
    )

    if (!instance) {
      return res.status(404).json({ code: 404, message: '工作流实例不存在' })
    }

    // [a-iso-2] 2026-09-18 审批详情补守卫（此前零守卫 = 任何登录用户可看任意审批单）：
    //   放行面 = ①平台管理员/global ②企管看本企业发起的 ③孵化器非超管看平台发起的
    //            ④我是发起人 ⑤我是处理人（审批人必须能看到要审的单）。
    {
      const __scD = await getCompanyScope(req)
      const [[__relD]] = await pool.query(
        'SELECT COUNT(*) AS t FROM workflow_tasks WHERE instance_id = ? AND assignee_id = ?',
        [instance.id, req.user.id])
      const __isMine = Number(instance.initiator_id) === Number(req.user.id) || Number(__relD && __relD.t || 0) > 0
      let __okD = __scD.kind === 'global' || __isMine
      if (!__okD) {
        if (__scD.kind === 'company-manage') {
          const [[__ownD]] = await pool.query(
            'SELECT company_id FROM users WHERE id = ? LIMIT 1', [instance.initiator_id])
          __okD = __ownD && Number(__ownD.company_id) === Number(__scD.companyId)
        } else if (__scD.kind === 'incubator') {
          const [[__ownD]] = await pool.query(
            'SELECT company_id FROM users WHERE id = ? LIMIT 1', [instance.initiator_id])
          __okD = __ownD && __ownD.company_id == null
        }
      }
      if (!__okD) {
        return res.status(403).json({ code: 403, message: '无权查看该审批单' })
      }
    }

    // Get tasks
    const [tasks] = await pool.query(
      `SELECT wt.*, u.name as assignee_name
       FROM workflow_tasks wt
       LEFT JOIN users u ON wt.assignee_id = u.id
       WHERE wt.instance_id = ?
       ORDER BY wt.created_at`,
      [req.params.id]
    )

    // Get logs
    const [logs] = await pool.query(
      `SELECT wl.*, u.name as operator_name
       FROM workflow_logs wl
       LEFT JOIN users u ON wl.operator_id = u.id
       WHERE wl.instance_id = ?
       ORDER BY wl.created_at`,
      [req.params.id]
    )

    instance.tasks = tasks
    instance.logs = logs

    res.json({ code: 0, data: instance, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/workflow-tasks/my - My pending tasks
router.get('/workflow-tasks/my', async (req, res, next) => {
  try {
    const userId = req.user.id
    const { page, size } = parsePagination(req.query)

    const sql = `SELECT wt.*, wi.title as instance_title, wi.workflow_code,
                 u.name as initiator_name
                 FROM workflow_tasks wt
                 LEFT JOIN workflow_instances wi ON wt.instance_id = wi.id
                 LEFT JOIN users u ON wi.initiator_id = u.id
                 WHERE wt.assignee_id = ? AND wt.status = 'pending'
                 ORDER BY wt.created_at DESC
                 LIMIT ? OFFSET ?`

    const countSql = `SELECT COUNT(*) as total FROM workflow_tasks WHERE assignee_id = ? AND status = 'pending'`
    const [[{ total }]] = await pool.query(countSql, [userId])

    const [rows] = await pool.query(sql, [userId, size, (page - 1) * size])
    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/workflow-tasks/:id/complete - Complete task
router.post('/workflow-tasks/:id/complete', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { action, comment, form_data } = req.body
    const userId = req.user.id
    const taskId = req.params.id

    const [[task]] = await conn.query('SELECT * FROM workflow_tasks WHERE id = ?', [taskId])

    if (!task) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '任务不存在' })
    }

    if (task.assignee_id !== userId) {
      await conn.rollback()
      return res.status(403).json({ code: 403, message: '无权限处理此任务' })
    }

    if (task.status !== 'pending') {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '任务已处理' })
    }

    // Update task
    await conn.query(
      `UPDATE workflow_tasks SET status = ?, action = ?, comment = ?, form_data = ?,
       completed_at = CURRENT_TIMESTAMP WHERE id = ?`,
      ['completed', action || null, comment || null, form_data ? JSON.stringify(form_data) : null, taskId]
    )

    // Log
    await conn.query(
      'INSERT INTO workflow_logs (instance_id, task_id, node_id, action, operator_id, message) VALUES (?,?,?,?,?,?)',
      [task.instance_id, taskId, task.node_id, action || 'complete', userId, comment || '任务已完成']
    )

    // Check if workflow should complete
    const [[{ pendingCount }]] = await conn.query(
      'SELECT COUNT(*) as pendingCount FROM workflow_tasks WHERE instance_id = ? AND status = ?',
      [task.instance_id, 'pending']
    )

    if (pendingCount === 0) {
      await conn.query(
        'UPDATE workflow_instances SET status = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ?',
        ['completed', task.instance_id]
      )
    }

    await conn.commit()
    res.json({ code: 0, data: null, message: '任务已完成' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ═══════════════════════════════════════════════════════════════════════════════
// LEAVE MODULE
// ═══════════════════════════════════════════════════════════════════════════════

// POST /api/oa/leave - Submit leave request
router.post('/leave', async (req, res, next) => {
  try {
    const { type, start_date, end_date, days, reason } = req.body
    const userId = req.user.id

    if (!type || !start_date || !end_date || !days || !reason) {
      return res.status(400).json({ code: 400, message: '请填写完整的请假信息' })
    }

    // Get user's supervisor
    const [[user]] = await pool.query('SELECT supervisor_id FROM users WHERE id = ?', [userId])
    if (!user || !user.supervisor_id) {
      return res.status(400).json({ code: 400, message: '未设置上级，无法提交请假申请' })
    }

    // [company-iso] 2026-09-12 写隔离补：请假记录注入企业归属（leave_records 本无 company_id 列，现已加列）
    const __scopeLeave = await getCompanyScope(req)
    const __cidLeave = __scopeLeave.kind === 'company-manage' || __scopeLeave.kind === 'company-self' ? __scopeLeave.companyId : null

    const [result] = await pool.query(
      `INSERT INTO leave_records (user_id, company_id, type, start_date, end_date, days, reason, status, approver_id)
       VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)`,
      [userId, __cidLeave, type, start_date, end_date, days, reason, user.supervisor_id]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '请假申请已提交' })
  } catch (err) { next(err) }
})

// GET /api/oa/leave - Query leave records
router.get('/leave', async (req, res, next) => {
  try {
    const { user_id, status, start_date, end_date, type } = req.query
    const { page, size } = parsePagination(req.query)
    const currentUserId = req.user.id
    const currentUserRole = req.user.role

    let where = 'WHERE 1=1'
    const params = []

    // Permission control
    if (currentUserRole === ROLES.ADMIN) {
      if (user_id) { where += ' AND l.user_id = ?'; params.push(user_id) }
    } else {
      // Get subordinates
      const [subordinates] = await pool.query(`
        WITH RECURSIVE subordinate_tree AS (
          SELECT id FROM users WHERE supervisor_id = ?
          UNION ALL
          SELECT u.id FROM users u
          INNER JOIN subordinate_tree st ON u.supervisor_id = st.id
        )
        SELECT id FROM subordinate_tree
      `, [currentUserId])

      const subordinateIds = subordinates.map(s => s.id)
      subordinateIds.push(currentUserId)

      if (user_id) {
        if (!subordinateIds.includes(parseInt(user_id))) {
          return res.status(403).json({ code: 403, message: '无权查看该用户的请假记录' })
        }
        where += ' AND l.user_id = ?'
        params.push(user_id)
      } else {
        where += ' AND l.user_id IN (?)'
        params.push(subordinateIds)
      }
    }

    if (status) { where += ' AND l.status = ?'; params.push(status) }
    if (type) { where += ' AND l.type = ?'; params.push(type) }
    if (start_date) { where += ' AND l.start_date >= ?'; params.push(start_date) }
    if (end_date) { where += ' AND l.end_date <= ?'; params.push(end_date) }

    const sql = `
      SELECT l.*,
             u.name as user_name,
             u.department,
             approver.name as approver_name
      FROM leave_records l
      LEFT JOIN users u ON l.user_id = u.id
      LEFT JOIN users approver ON l.approver_id = approver.id
      ${where}
      ORDER BY l.created_at DESC
      LIMIT ? OFFSET ?
    `
    params.push(size, (page - 1) * size)

    const countSql = `SELECT COUNT(*) as total FROM leave_records l LEFT JOIN users u ON l.user_id = u.id ${where}`
    const [[{ total }]] = await pool.query(countSql, params.slice(0, -2))
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/oa/leave/pending - Get pending leave requests for approval
router.get('/leave/pending', async (req, res, next) => {
  try {
    const approverId = req.user.id

    const [rows] = await pool.query(
      `SELECT l.*,
              u.name as user_name,
              u.department
       FROM leave_records l
       LEFT JOIN users u ON l.user_id = u.id
       WHERE l.approver_id = ? AND l.status = 'pending'
       ORDER BY l.created_at DESC`,
      [approverId]
    )

    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/oa/leave/:id/approve - Approve leave request
router.put('/leave/:id/approve', async (req, res, next) => {
  try {
    const { id } = req.params
    const { action, reject_reason } = req.body // action: 'approve' or 'reject'
    const approverId = req.user.id

    if (!['approve', 'reject'].includes(action)) {
      return res.status(400).json({ code: 400, message: '无效的审批操作' })
    }

    if (action === 'reject' && !reject_reason) {
      return res.status(400).json({ code: 400, message: '拒绝时必须填写原因' })
    }

    // [company-iso] 2026-09-12 归属守卫：跨企业改/删拒绝
    const __own = await assertRowCompany(req, 'leave_records', id)
    if (!__own.ok) return res.status(__own.status).json({ code: __own.status, message: __own.message })

    const [[leave]] = await pool.query('SELECT * FROM leave_records WHERE id = ?', [id])
    if (!leave) {
      return res.status(404).json({ code: 404, message: '请假记录不存在' })
    }

    if (leave.approver_id !== approverId && !(await checkPerm(req, 'oa:write'))) {
      return res.status(403).json({ code: 403, message: '无权审批此请假申请' })
    }

    if (leave.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '该请假申请已处理' })
    }

    const newStatus = action === 'approve' ? 'approved' : 'rejected'
    await pool.query(
      `UPDATE leave_records
       SET status = ?, reject_reason = ?, approved_at = NOW()
       WHERE id = ?`,
      [newStatus, reject_reason || null, id]
    )

    res.json({ code: 0, message: action === 'approve' ? '请假已批准' : '请假已拒绝' })
  } catch (err) { next(err) }
})

// DELETE /api/oa/leave/:id - Delete leave request (only pending)
router.delete('/leave/:id', async (req, res, next) => {
  try {
    const { id } = req.params
    const userId = req.user.id

    // [company-iso] 2026-09-12 归属守卫：跨企业改/删拒绝
    const __own = await assertRowCompany(req, 'leave_records', id)
    if (!__own.ok) return res.status(__own.status).json({ code: __own.status, message: __own.message })

    const [[leave]] = await pool.query('SELECT * FROM leave_records WHERE id = ?', [id])
    if (!leave) {
      return res.status(404).json({ code: 404, message: '请假记录不存在' })
    }

    if (leave.user_id !== userId && !(await checkPerm(req, 'oa:write'))) {
      return res.status(403).json({ code: 403, message: '无权删除此请假申请' })
    }

    if (leave.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '只能删除待审批的请假申请' })
    }

    await pool.query('DELETE FROM leave_records WHERE id = ?', [id])
    res.json({ code: 0, message: '请假申请已删除' })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// OVERTIME MODULE
// �══════════════════════════════════════════════════════════════════════════════

// POST /api/oa/overtime - Submit overtime request
router.post('/overtime', async (req, res, next) => {
  try {
    const { start_time, end_time, hours, reason, jobsite_id } = req.body
    const userId = req.user.id
    // [company-iso] 加班审批归属申请人企业（孵化器为 NULL）
    const __scope = await getCompanyScope(req)

    if (!start_time || !end_time || !hours || !reason) {
      return res.status(400).json({ code: 400, message: '请填写完整的加班信息' })
    }

    if (hours <= 0 || hours > 24) {
      return res.status(400).json({ code: 400, message: '加班时长应在 0-24 小时之间' })
    }

    const [[user]] = await pool.query('SELECT supervisor_id FROM users WHERE id = ?', [userId])
    if (!user || !user.supervisor_id) {
      return res.status(400).json({ code: 400, message: '未设置上级，无法提交加班申请' })
    }

    const formData = JSON.stringify({
      start_time,
      end_time,
      hours,
      reason,
      jobsite_id: jobsite_id || null
    })

    const [approvalResult] = await pool.query(
      `INSERT INTO approvals (type_code, title, applicant_id, form_data, status, current_step, created_at, company_id)
       VALUES ('overtime', ?, ?, ?, 'pending', 1, NOW(), ?)`,
      [`加班申请 - ${hours}小时 - ${reason.slice(0, 30)}`, userId, formData, __scope.companyId]
    )

    const approvalId = approvalResult.insertId

    // [company-iso] 2026-09-12 写隔离补：加班记录注入企业归属（overtime_records 本无 company_id 列，现已加列）
    const __scopeOt = await getCompanyScope(req)
    const __cidOt = __scopeOt.kind === 'company-manage' || __scopeOt.kind === 'company-self' ? __scopeOt.companyId : null

    const [result] = await pool.query(
      `INSERT INTO overtime_records (user_id, company_id, jobsite_id, start_time, end_time, hours, reason, status, approver_id, approval_id)
       VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)`,
      [userId, __cidOt, jobsite_id || null, start_time, end_time, hours, reason, user.supervisor_id, approvalId]
    )

    res.json({ code: 0, data: { id: result.insertId, approval_id: approvalId }, message: '加班申请已提交' })
  } catch (err) { next(err) }
})

// GET /api/oa/overtime - Query overtime records
router.get('/overtime', async (req, res, next) => {
  try {
    const { user_id, status, start_date, end_date } = req.query
    const { page, size } = parsePagination(req.query)
    const currentUserId = req.user.id
    const currentUserRole = req.user.role

    let where = 'WHERE 1=1'
    const params = []

    if (currentUserRole === ROLES.ADMIN) {
      if (user_id) { where += ' AND o.user_id = ?'; params.push(user_id) }
    } else {
      where += ' AND o.user_id = ?'
      params.push(currentUserId)
    }

    if (status) { where += ' AND o.status = ?'; params.push(status) }
    if (start_date) { where += ' AND o.start_time >= ?'; params.push(start_date) }
    if (end_date) { where += ' AND o.start_time <= ?'; params.push(end_date) }

    const sql = `
      SELECT o.*,
             u.name as user_name,
             approver.name as approver_name
      FROM overtime_records o
      LEFT JOIN users u ON o.user_id = u.id
      LEFT JOIN users approver ON o.approver_id = approver.id
      ${where}
      ORDER BY o.created_at DESC
      LIMIT ? OFFSET ?
    `
    params.push(size, (page - 1) * size)

    const countSql = `SELECT COUNT(*) as total FROM overtime_records o ${where}`
    const [[{ total }]] = await pool.query(countSql, params.slice(0, -2))
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size }, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/oa/overtime/:id/approve - Approve or reject overtime request
router.put('/overtime/:id/approve', async (req, res, next) => {
  try {
    const { id } = req.params
    const { action, reject_reason } = req.body
    const approverId = req.user.id

    if (!['approve', 'reject'].includes(action)) {
      return res.status(400).json({ code: 400, message: '无效的审批操作' })
    }

    if (action === 'reject' && !reject_reason) {
      return res.status(400).json({ code: 400, message: '拒绝时必须填写原因' })
    }

    // [company-iso] 2026-09-12 归属守卫：跨企业改/删拒绝
    const __own = await assertRowCompany(req, 'overtime_records', id)
    if (!__own.ok) return res.status(__own.status).json({ code: __own.status, message: __own.message })

    const [[ot]] = await pool.query('SELECT * FROM overtime_records WHERE id = ?', [id])
    if (!ot) {
      return res.status(404).json({ code: 404, message: '加班记录不存在' })
    }

    if (ot.approver_id !== approverId && !(await checkPerm(req, 'oa:write'))) {
      return res.status(403).json({ code: 403, message: '无权审批此加班申请' })
    }

    if (ot.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '该加班申请已处理' })
    }

    const newStatus = action === 'approve' ? 'approved' : 'rejected'
    await pool.query(
      `UPDATE overtime_records
       SET status = ?, reject_reason = ?, approved_at = NOW()
       WHERE id = ?`,
      [newStatus, reject_reason || null, id]
    )

    if (ot.approval_id) {
      await pool.query(
        `UPDATE approvals SET status = ?, updated_at = NOW() WHERE id = ?`,
        [newStatus, ot.approval_id]
      )
    }

    if (action === 'approve') {
      const otDate = dstr(new Date(ot.start_time))
      await pool.query(
        `UPDATE attendance SET overtime_hours = COALESCE(overtime_hours, 0) + ? WHERE user_id = ? AND date = ?`,
        [ot.hours, ot.user_id, otDate]
      )
    }

    res.json({ code: 0, message: action === 'approve' ? '加班已批准' : '加班已拒绝' })
  } catch (err) { next(err) }
})

// DELETE /api/oa/overtime/:id - Delete overtime request (only pending)
router.delete('/overtime/:id', async (req, res, next) => {
  try {
    const { id } = req.params
    const userId = req.user.id

    // [company-iso] 2026-09-12 归属守卫：跨企业改/删拒绝
    const __own = await assertRowCompany(req, 'overtime_records', id)
    if (!__own.ok) return res.status(__own.status).json({ code: __own.status, message: __own.message })

    const [[ot]] = await pool.query('SELECT * FROM overtime_records WHERE id = ?', [id])
    if (!ot) {
      return res.status(404).json({ code: 404, message: '加班记录不存在' })
    }

    if (ot.user_id !== userId && !(await checkPerm(req, 'oa:write'))) {
      return res.status(403).json({ code: 403, message: '无权删除此加班申请' })
    }

    if (ot.status !== 'pending') {
      return res.status(400).json({ code: 400, message: '只能删除待审批的加班申请' })
    }

    if (ot.approval_id) {
      await pool.query('DELETE FROM approvals WHERE id = ?', [ot.approval_id])
    }

    await pool.query('DELETE FROM overtime_records WHERE id = ?', [id])
    res.json({ code: 0, message: '加班申请已删除' })
  } catch (err) { next(err) }
})

// ═══════════════════════════════════════════════════════════════════════════════
// ATTENDANCE RULES
// ═══════════════════════════════════════════════════════════════════════════════

// GET /api/oa/attendance-rules - List all rules
router.get('/attendance-rules', requirePermission('attendance:view'), async (req, res, next) => {
  try {
    const [rules] = await pool.query(
      'SELECT * FROM attendance_rules ORDER BY created_at DESC'
    )

    // Fetch members for each rule
    for (const rule of rules) {
      const [members] = await pool.query(
        'SELECT arm.user_id, u.name as user_name, u.worker_category FROM attendance_rule_members arm LEFT JOIN users u ON arm.user_id = u.id WHERE arm.rule_id = ?',
        [rule.id]
      )
      rule.members = members
      // 2026-08-25 取规则内成员的最常见 worker_category(给前端默认值用)
      const cats = members.map(m => m.worker_category).filter(Boolean)
      rule.worker_category = cats.length ? cats.sort((a, b) => cats.filter(c => c === a).length - cats.filter(c => c === b).length).pop() : 'office'
      if (typeof rule.weekdays === 'string') {
        try { rule.weekdays = JSON.parse(rule.weekdays) } catch { rule.weekdays = [] }
      }
    }

    res.json({ code: 0, data: rules, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/oa/attendance-rules - Create rule
router.post('/attendance-rules', requirePermission('attendance:manage'), async (req, res, next) => {
  try {
    const { name, weekdays, start_time, end_time, member_ids, worker_category } = req.body
    if (!name) return res.status(400).json({ code: 400, message: '规则名称必填' })

    const [result] = await pool.query(
      'INSERT INTO attendance_rules (name, weekdays, start_time, end_time, created_by) VALUES (?, ?, ?, ?, ?)',
      [name, JSON.stringify(weekdays || [1,2,3,4,5]), start_time || '09:00', end_time || '18:00', req.user.id]
    )
    const ruleId = result.insertId

    // Insert members
    if (member_ids?.length) {
      const values = member_ids.map(uid => [ruleId, uid])
      await pool.query('INSERT INTO attendance_rule_members (rule_id, user_id) VALUES ?', [values])
      // 2026-08-25 同步:勾选员工 = 必须考勤 + 记录身份分类
      const validCats = ['engineering', 'office', 'both']
      const cat = validCats.includes(worker_category) ? worker_category : 'office'
      const placeholders = member_ids.map(() => '?').join(',')
      await pool.query(`UPDATE users SET require_attendance = 1, worker_category = ? WHERE id IN (${placeholders})`, [cat, ...member_ids])
    }

    res.json({ code: 0, data: { id: ruleId }, message: '创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/oa/attendance-rules/:id - Update rule
router.put('/attendance-rules/:id', requirePermission('attendance:manage'), async (req, res, next) => {
  try {
    const { id } = req.params
    const { name, weekdays, start_time, end_time, status, member_ids, worker_category } = req.body

    const [[existing]] = await pool.query('SELECT * FROM attendance_rules WHERE id = ?', [id])
    if (!existing) return res.status(404).json({ code: 404, message: '规则不存在' })

    // Build update fields
    const updates = []
    const params = []
    if (name !== undefined) { updates.push('name = ?'); params.push(name) }
    if (weekdays !== undefined) { updates.push('weekdays = ?'); params.push(JSON.stringify(weekdays)) }
    if (start_time !== undefined) { updates.push('start_time = ?'); params.push(start_time) }
    if (end_time !== undefined) { updates.push('end_time = ?'); params.push(end_time) }
    if (status !== undefined) { updates.push('status = ?'); params.push(status) }

    if (updates.length) {
      params.push(id)
      await pool.query(`UPDATE attendance_rules SET ${updates.join(', ')} WHERE id = ?`, params)
    }

    // Update members if provided
    if (member_ids !== undefined) {
      // 2026-08-25 同步逻辑:计算差集 — 被取消勾选的人 require_attendance 改 0,新增勾选的人改 1
      const [oldRows] = await pool.query(
        'SELECT user_id FROM attendance_rule_members WHERE rule_id = ?', [id]
      )
      const oldSet = new Set(oldRows.map(r => r.user_id))
      const newSet = new Set(member_ids)
      const added = [...newSet].filter(x => !oldSet.has(x))
      const removed = [...oldSet].filter(x => !newSet.has(x))

      await pool.query('DELETE FROM attendance_rule_members WHERE rule_id = ?', [id])
      if (member_ids.length) {
        const values = member_ids.map(uid => [id, uid])
        await pool.query('INSERT INTO attendance_rule_members (rule_id, user_id) VALUES ?', [values])
      }
      // added → require_attendance=1 + worker_category
      if (added.length) {
        const validCats = ['engineering', 'office', 'both']
        const cat = validCats.includes(worker_category) ? worker_category : 'office'
        const ph = added.map(() => '?').join(',')
        await pool.query(`UPDATE users SET require_attendance = 1, worker_category = ? WHERE id IN (${ph})`, [cat, ...added])
      }
      // removed → require_attendance=0 (silent 模式) — 但要排除同时属于其它 active 规则的员工
      if (removed.length) {
        const ph2 = removed.map(() => '?').join(',')
        await pool.query(
          `UPDATE users u
           SET u.require_attendance = 0
           WHERE u.id IN (${ph2}) AND u.id NOT IN (
             SELECT arm.user_id FROM attendance_rule_members arm
             INNER JOIN attendance_rules ar ON arm.rule_id = ar.id
             WHERE arm.user_id IN (${ph2}) AND ar.status = 'active' AND ar.id != ?
           )`,
          [...removed, ...removed, id]
        )
      }
    }

    res.json({ code: 0, message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/oa/attendance-rules/:id - Delete rule
router.delete('/attendance-rules/:id', requirePermission('attendance:delete'), async (req, res, next) => {
  try {
    const { id } = req.params
    const [[existing]] = await pool.query('SELECT * FROM attendance_rules WHERE id = ?', [id])
    if (!existing) return res.status(404).json({ code: 404, message: '规则不存在' })

    // 2026-08-25 同步:删规则前先取成员,删完成员后只剩这条规则的员工改 silent
    const [members] = await pool.query(
      'SELECT user_id FROM attendance_rule_members WHERE rule_id = ?', [id]
    )
    await pool.query('DELETE FROM attendance_rules WHERE id = ?', [id])
    if (members.length) {
      const uids = members.map(m => m.user_id)
      const ph3 = uids.map(() => '?').join(',')
      await pool.query(
        `UPDATE users u
         SET u.require_attendance = 0
         WHERE u.id IN (${ph3}) AND u.id NOT IN (
           SELECT arm.user_id FROM attendance_rule_members arm
           INNER JOIN attendance_rules ar ON arm.rule_id = ar.id
           WHERE arm.user_id IN (${ph3}) AND ar.status = 'active'
         )`,
        [...uids, ...uids]
      )
    }

    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

export default router
