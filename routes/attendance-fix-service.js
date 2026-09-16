/**
 * 考勤补卡回写 · OA 归档动作 attendance_fix 的实现
 * 2026-09-15 江小鱼
 *
 * 由 routes/oa-flow.js 在 end 节点执行 `actions: ['attendance_fix']` 时调用：
 * 补卡申请经直属上级审批通过后，**真的**把打卡时间补进 attendance 表（不是只改状态）。
 *
 * 设计约束（铁律 #7：不动生产表结构）：
 *   - 只用 attendance 表**既有列**：clock_in / clock_out / status / late_minutes /
 *     early_minutes / abnormal_reason / approved_by / approved_at / company_id / clock_type。
 *     check_in_time / check_out_time 保持与 /attendance/clock 一致（打卡路径也不写，故此处不写）。
 *   - 幂等：同一实例只回写一次（在 abnormal_reason 里留 `[补卡#<instanceId>]` 标记）。
 *   - 判迟到/早退与打卡接口同口径：优先当天排班 → 员工出勤规则 → 全局默认规则（缺省 09:00/18:00），
 *     且 require_attendance != 1 的员工按 silent 口径（状态 normal、迟到早退清零），不凭空判人迟到。
 */

/** 补卡标记（幂等依据） */
const fixMark = (instanceId) => `[补卡#${instanceId}]`

/** '9:05' / '09:05' / '09:05:00' → '09:05:00'；非法 → null */
function toTimeStr(v) {
  if (v === undefined || v === null || v === '') return null
  const m = String(v).trim().match(/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/)
  if (!m) return null
  const hh = Math.min(23, parseInt(m[1], 10))
  const mm = Math.min(59, parseInt(m[2], 10))
  const ss = m[3] === undefined ? 0 : Math.min(59, parseInt(m[3], 10))
  return [hh, mm, ss].map(n => String(n).padStart(2, '0')).join(':')
}

/** 任意常见输入 → 'YYYY-MM-DD'；非法 → null */
function toDateStr(v) {
  if (v === undefined || v === null || v === '') return null
  const s = String(v).trim()
  let m = s.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (m) return `${m[1]}-${m[2]}-${m[3]}`
  m = s.match(/^(\d{4})[/.](\d{1,2})[/.](\d{1,2})/)
  if (m) return `${m[1]}-${String(m[2]).padStart(2, '0')}-${String(m[3]).padStart(2, '0')}`
  const d = new Date(s)
  if (!isNaN(d.getTime())) return d.toISOString().slice(0, 10)
  return null
}

/** a→b 的分钟数（'HH:MM:SS'） */
function minutesBetween(a, b) {
  const sec = (t) => { const [h, m, s] = String(t).split(':').map(Number); return h * 3600 + m * 60 + (s || 0) }
  return Math.round((sec(b) - sec(a)) / 60)
}

/**
 * 指定日期的上/下班时间（与 routes/oa.js getWorkTimeWindow 同优先级，但按传入日期而非"今天"）
 * 只读，不抛：任何异常都退回默认 09:00/18:00 —— 与打卡接口的兜底口径一致
 */
async function windowForDate(conn, userId, dateStr) {
  const defaultIn = '09:00:00', defaultOut = '18:00:00'
  const fmt = (t) => {
    if (!t) return null
    const s = String(t)
    const m = s.match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/)
    if (!m) return null
    return [m[1], m[2], m[3] || '00'].map(n => String(parseInt(n, 10)).padStart(2, '0')).join(':')
  }
  try {
    const [sched] = await conn.query(
      `SELECT s.start_time, s.end_time FROM shift_schedules ss
       LEFT JOIN shifts s ON ss.shift_id = s.id
       WHERE ss.user_id = ? AND ss.schedule_date = ? AND s.status = 'active' LIMIT 1`,
      [userId, dateStr]
    )
    if (sched && sched[0]) {
      const si = fmt(sched[0].start_time)
      if (si) return { in: si, out: fmt(sched[0].end_time) || defaultOut }
    }
    // B1：上班模板层（个人 > 部门 > 全员；仅常规班制）——与打卡接口同一口径，避免"体检/补卡说一套、打卡判一套"
    try {
      const [[u]] = await conn.query('SELECT department_id FROM users WHERE id = ?', [userId])
      const deptId = u && u.department_id ? Number(u.department_id) : 0
      const [wms] = await conn.query(
        `SELECT t.start_time, t.end_time, t.mode_type
           FROM work_mode_assignments a
           JOIN work_mode_templates t ON a.template_id = t.id AND t.status = 'active'
          WHERE (a.target_type = 'user' AND a.target_id = ?)
             OR (a.target_type = 'department' AND a.target_id = ?)
             OR (a.target_type = 'all')
          ORDER BY FIELD(a.target_type, 'user', 'department', 'all')`,
        [userId, deptId])
      for (const r of wms) {
        if (r.mode_type !== 'regular') continue
        const wi = fmt(r.start_time)
        if (wi) return { in: wi, out: fmt(r.end_time) || defaultOut }
      }
    } catch (e) { /* 模板层读不到 → 回落原规则链 */ }
    const [rules] = await conn.query(
      `SELECT ar.start_time, ar.end_time FROM attendance_rule_members arm
       LEFT JOIN attendance_rules ar ON arm.rule_id = ar.id
       WHERE arm.user_id = ? AND ar.status = 'active' ORDER BY ar.id LIMIT 1`,
      [userId]
    )
    if (rules && rules[0]) {
      const ri = fmt(rules[0].start_time)
      if (ri) return { in: ri, out: fmt(rules[0].end_time) || defaultOut }
    }
    const [def] = await conn.query(
      `SELECT start_time, end_time FROM attendance_rules WHERE status = 'active' ORDER BY id ASC LIMIT 1`
    )
    if (def && def[0]) {
      const di = fmt(def[0].start_time)
      if (di) return { in: di, out: fmt(def[0].end_time) || defaultOut }
    }
  } catch (e) { /* 读不到就用默认，与打卡接口一致 */ }
  return { in: defaultIn, out: defaultOut }
}

/** 补卡类型的宽松判定：兼容 '上班卡'/'下班卡'/'上下班都补' 及英文 in/out/both */
function wantKinds(kind) {
  const s = String(kind || '').trim().toLowerCase()
  const both = s.includes('都') || s === 'both' || s.includes('上下班')
  const wantIn = both || s.includes('上班') || s === 'in'
  const wantOut = both || s.includes('下班') || s === 'out'
  return { wantIn, wantOut }
}

/**
 * 执行补卡回写
 * @param {*} conn 事务连接（由引擎传入，保证与审批同事务：写失败 → 整个审批回滚）
 * @param {{ userId:number, companyId?:number|null, instanceId:number, approverId?:number|null, formData?:object }} opts
 * @returns {Promise<{skipped?:boolean, created?:boolean, attendanceId:number, status?:string, clockIn?:string|null, clockOut?:string|null, lateMinutes?:number, earlyMinutes?:number}>}
 * @throws {Error} 表单缺关键字段、或写入失败 → 由引擎包装成 400 并回滚，**不允许"审批过了但没补上卡"**
 */
export async function applyAttendanceFix(conn, opts = {}) {
  const { userId, instanceId, formData = {} } = opts
  const companyId = opts.companyId === undefined ? null : opts.companyId
  const approverId = opts.approverId === undefined ? null : opts.approverId

  if (!userId) throw new Error('补卡回写缺少申请人（userId）')
  const dateStr = toDateStr(formData.fixDate || formData.date)
  if (!dateStr) throw new Error('补卡申请缺少有效日期（fixDate）')
  const { wantIn, wantOut } = wantKinds(formData.fixKind || formData.kind)
  if (!wantIn && !wantOut) throw new Error('补卡申请缺少补卡类型（fixKind：上班卡 / 下班卡 / 上下班都补）')
  const inT = toTimeStr(formData.fixIn)
  const outT = toTimeStr(formData.fixOut)
  if (wantIn && !inT) throw new Error('补上班卡必须填写上班时间（fixIn）')
  if (wantOut && !outT) throw new Error('补下班卡必须填写下班时间（fixOut）')
  const reason = String(formData.reason || formData.fixReason || '').trim()
  const attach = formData.attach || null

  const [[exist]] = await conn.query(
    'SELECT id, clock_in, clock_out, status, late_minutes, early_minutes, abnormal_reason FROM attendance WHERE user_id = ? AND date = ?',
    [userId, dateStr]
  )

  // 幂等：同一申请实例重复归档（重提后再通过等）不重复写
  if (exist && String(exist.abnormal_reason || '').includes(fixMark(instanceId))) {
    return { skipped: true, attendanceId: exist.id }
  }

  const [[u]] = await conn.query('SELECT require_attendance FROM users WHERE id = ?', [userId])
  const isRequired = !!(u && u.require_attendance === 1)
  const win = await windowForDate(conn, userId, dateStr)

  const clockIn = wantIn ? inT : toTimeStr(exist && exist.clock_in)
  const clockOut = wantOut ? outT : toTimeStr(exist && exist.clock_out)

  let status = (exist && exist.status) || 'normal'
  let lateMin = (exist && exist.late_minutes) || 0
  let earlyMin = (exist && exist.early_minutes) || 0
  if (!isRequired) {
    // 与打卡接口 silent 口径一致：不要求打卡的员工不判迟到早退、不凭空产生异常
    status = 'normal'; lateMin = 0; earlyMin = 0
  } else {
    lateMin = (clockIn && win.in && clockIn > win.in) ? minutesBetween(win.in, clockIn) : 0
    earlyMin = (clockOut && win.out && clockOut < win.out) ? minutesBetween(clockOut, win.out) : 0
    // 与打卡接口一致：早退优先（下班打卡会把 late 覆盖为 early）
    status = earlyMin > 0 ? 'early' : (lateMin > 0 ? 'late' : 'normal')
  }

  const note = [
    (exist && exist.abnormal_reason) ? String(exist.abnormal_reason) : null,
    fixMark(instanceId),
    `补卡通过（${[wantIn ? '上班' : null, wantOut ? '下班' : null].filter(Boolean).join('+')}）：${reason || '未填事由'}`,
    attach ? `附件：${typeof attach === 'string' ? attach : JSON.stringify(attach)}` : null,
    `审批人#${approverId === null ? '-' : approverId}`
  ].filter(Boolean).join(' | ')

  if (exist) {
    await conn.query(
      `UPDATE attendance
         SET clock_in = COALESCE(?, clock_in),
             clock_out = COALESCE(?, clock_out),
             status = ?, late_minutes = ?, early_minutes = ?,
             abnormal_reason = ?, approved_by = ?, approved_at = NOW()
       WHERE id = ?`,
      [wantIn ? inT : null, wantOut ? outT : null, status, lateMin, earlyMin, note, approverId, exist.id]
    )
    return { created: false, attendanceId: exist.id, status, clockIn, clockOut, lateMinutes: lateMin, earlyMinutes: earlyMin }
  }

  const [ins] = await conn.query(
    `INSERT INTO attendance
       (user_id, company_id, date, clock_in, clock_out, status, clock_type, late_minutes, early_minutes,
        abnormal_reason, approved_by, approved_at, is_auto_clock)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),0)`,
    [userId, companyId, dateStr, clockIn, clockOut, status, 'normal', lateMin, earlyMin, note, approverId]
  )
  return { created: true, attendanceId: ins.insertId, status, clockIn, clockOut, lateMinutes: lateMin, earlyMinutes: earlyMin }
}

export default { applyAttendanceFix }
