// 上班模板体系（B1）——把"考勤打卡设置"与"排班"收成一层模板
//
// 设计口径（波哥 2026-09-16 拍板）：
//   ① 一层模板：双休/单休/自由工时(常规班制) + 早班/晚班/周末班(轮班班制) 同为「上班模板」
//        - 常规班制 → 只记 assignment（判定读模板时间/打卡口径），**不产排班记录**
//        - 轮班班制 → 生成逐日排班 shift_schedules
//   ② 铺的范围：全员 / 某部门全部人 / 个人；**部门动态跟随**（绑部门，判定时按 users.department_id 查）
//   ③ 先后覆盖，不是优先级：铺常规班制时可清理该人"今天及以后"的排班（需 HR 在影响清单里确认）
//   ④ 历史保护：所有写入/清理一律 `schedule_date >= CURDATE()`，历史排班与考勤记录绝不动
//   ⑤ 严禁静默删数据：清理前必须由 preview 给出影响清单，apply 需带 confirm_override
import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js'
import multer from 'multer'
import ExcelJS from 'exceljs'

const router = Router()
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 10 * 1024 * 1024 } })

// 本地日期（不用 toISOString：那是 UTC，东八区会差一天）
function fmtDate(d) {
  const y = d.getFullYear(), m = String(d.getMonth() + 1).padStart(2, '0'), dd = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${dd}`
}
const todayStr = () => fmtDate(new Date())

function parseWeekdays(v) {
  if (Array.isArray(v)) return v.map(Number).filter(n => n >= 1 && n <= 7)
  if (typeof v === 'string' && v.trim()) {
    try { const a = JSON.parse(v); return Array.isArray(a) ? a.map(Number).filter(n => n >= 1 && n <= 7) : [] } catch (e) { return [] }
  }
  return []
}

// 「全员」口径：在职员工，排除企业方账号（enterprise-admin 不是本公司员工）
async function resolveTargets(conn, targetType, targetId) {
  const base = `SELECT u.id, u.name, u.department_id, d.name AS department_name, u.role
                  FROM users u LEFT JOIN departments d ON u.department_id = d.id
                 WHERE u.status = 'active' AND u.role <> 'enterprise-admin'`
  if (targetType === 'all') {
    const [rows] = await conn.query(base + ' ORDER BY u.id')
    return rows
  }
  if (targetType === 'department') {
    const [rows] = await conn.query(base + ' AND u.department_id = ? ORDER BY u.id', [targetId])
    return rows
  }
  const [rows] = await conn.query(base + ' AND u.id = ?', [targetId])
  return rows
}

async function getTemplate(conn, id) {
  const [[t]] = await conn.query('SELECT * FROM work_mode_templates WHERE id = ? AND status = ?', [id, 'active'])
  return t || null
}

// 生成日期序列（含历史保护：只出今天及以后）
function enumerateDates(startDate, endDate, weekdays) {
  const out = []
  const t0 = todayStr()
  const s = new Date(startDate + 'T00:00:00')
  const e = new Date(endDate + 'T00:00:00')
  if (isNaN(s) || isNaN(e)) return out
  for (const d = new Date(s); d <= e; d.setDate(d.getDate() + 1)) {
    const iso = fmtDate(d)
    if (iso < t0) continue                     // 历史保护
    const jsDay = d.getDay()                   // 0=周日
    const wd = jsDay === 0 ? 7 : jsDay         // 1=周一..7=周日（与 attendance_rules 同口径）
    if (weekdays && weekdays.length && !weekdays.includes(wd)) continue
    out.push(iso)
  }
  return out
}

// 校验入参（preview 与 apply 共用）
function validateBody(body) {
  const { template_id, target_type, target_id = 0, start_date, end_date } = body
  const errs = []
  if (!template_id) errs.push('缺少 template_id')
  if (!['all', 'department', 'user'].includes(target_type)) errs.push('target_type 只能是 all / department / user')
  if (target_type !== 'all' && !Number(target_id)) errs.push('缺少 target_id')
  if (start_date && end_date && end_date < start_date) errs.push('结束日期不能早于开始日期')
  return errs
}

/* ══════════════════════════════════════════════════════
   GET /api/oa/work-modes —— 模板列表 + 当前铺设情况
   ══════════════════════════════════════════════════════ */
router.get('/', requirePermission(PERMISSIONS.SCHEDULE_WRITE), async (req, res, next) => {
  try {
    const [templates] = await pool.query('SELECT * FROM work_mode_templates WHERE status = ? ORDER BY sort_order, id', ['active'])
    const [assignments] = await pool.query(
      `SELECT a.*, DATE_FORMAT(a.effective_from, '%Y-%m-%d') AS effective_from_str,
              t.name AS template_name, t.code AS template_code,
              d.name AS department_name, u.name AS user_name
         FROM work_mode_assignments a
         LEFT JOIN work_mode_templates t ON a.template_id = t.id
         LEFT JOIN departments d ON a.target_type = 'department' AND a.target_id = d.id
         LEFT JOIN users u ON a.target_type = 'user' AND a.target_id = u.id
        ORDER BY a.target_type, a.target_id`)

    // 每个模板当前覆盖人数（部门要看实时人数）
    const [[allCnt]] = await pool.query(
      `SELECT COUNT(*) c FROM users WHERE status='active' AND role <> 'enterprise-admin'`)
    const [deptCnt] = await pool.query(
      `SELECT department_id, COUNT(*) c FROM users
        WHERE status='active' AND role <> 'enterprise-admin' AND department_id IS NOT NULL
        GROUP BY department_id`)
    const deptMap = new Map(deptCnt.map(r => [Number(r.department_id), Number(r.c)]))

    const list = templates.map(t => {
      const mine = assignments.filter(a => a.template_id === t.id).map(a => {
        let count = 1, label = ''
        if (a.target_type === 'all') { count = Number(allCnt.c); label = '全体员工' }
        else if (a.target_type === 'department') { count = deptMap.get(Number(a.target_id)) || 0; label = a.department_name || ('部门#' + a.target_id) }
        else label = a.user_name || ('用户#' + a.target_id)
        return { target_type: a.target_type, target_id: Number(a.target_id), label, count, effective_from: a.effective_from_str || null }
      })
      return {
        id: t.id, name: t.name, code: t.code, mode_type: t.mode_type,
        weekdays: parseWeekdays(t.weekdays), start_time: t.start_time, end_time: t.end_time,
        clock_mode: t.clock_mode, shift_code: t.shift_code, is_system: !!t.is_system,
        assignments: mine,
        covered_users: mine.reduce((s, m) => s + m.count, 0)
      }
    })

    const [departments] = await pool.query(
      `SELECT id, name FROM departments WHERE status='active' ORDER BY sort_order, id`)

    const [[schedCnt]] = await pool.query(
      `SELECT COUNT(*) c FROM shift_schedules WHERE schedule_date >= CURDATE()`)
    const [[schedUsers]] = await pool.query(
      `SELECT COUNT(DISTINCT user_id) c FROM shift_schedules WHERE schedule_date >= CURDATE()`)

    res.json({
      code: 0,
      data: {
        templates: list,
        departments,
        stats: { total_users: Number(allCnt.c), scheduled_users: Number(schedUsers.c), scheduled_rows: Number(schedCnt.c) }
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

/* ══════════════════════════════════════════════════════
   POST /api/oa/work-modes/preview —— 影响清单（严禁静默删数据）
   ══════════════════════════════════════════════════════ */
router.post('/preview', requirePermission(PERMISSIONS.SCHEDULE_WRITE), async (req, res, next) => {
  try {
    const errs = validateBody(req.body)
    if (errs.length) return res.status(400).json({ code: 400, message: errs.join('；') })
    const { template_id, target_type, target_id = 0, start_date, end_date } = req.body

    const t = await getTemplate(pool, template_id)
    if (!t) return res.status(404).json({ code: 404, message: '模板不存在或已停用' })

    const users = await resolveTargets(pool, target_type, Number(target_id))
    const ids = users.map(u => u.id)
    const errors = []

    // 已有排班（今天及以后）——影响清单的核心
    let hasSchedule = [], scheduleTotal = 0
    if (ids.length) {
      const [rows] = await pool.query(
        `SELECT user_id, COUNT(*) c FROM shift_schedules
          WHERE user_id IN (?) AND schedule_date >= CURDATE() AND status <> 'cancelled'
          GROUP BY user_id`, [ids])
      const nameMap = new Map(users.map(u => [Number(u.id), u]))
      hasSchedule = rows.map(r => ({
        user_id: Number(r.user_id),
        name: (nameMap.get(Number(r.user_id)) || {}).name || ('#' + r.user_id),
        department_name: (nameMap.get(Number(r.user_id)) || {}).department_name || null,
        count: Number(r.c)
      }))
      scheduleTotal = hasSchedule.reduce((s, x) => s + x.count, 0)
    }

    // 轮班：校验班次 + 估算将生成条数
    let scheduleToCreate = 0, shiftInfo = null
    if (t.mode_type === 'rotating') {
      if (!t.shift_code) errors.push('该轮班模板未关联班次代码，无法生成排班')
      else {
        const [[sh]] = await pool.query('SELECT id, name, start_time, end_time FROM shifts WHERE code = ? AND status = ?', [t.shift_code, 'active'])
        if (!sh) errors.push('系统里找不到启用中的班次「' + t.shift_code + '」，请先到「班次管理」创建')
        else shiftInfo = sh
      }
      if (!start_date || !end_date) errors.push('铺轮班需要选择起止日期')
      else if (!errors.length) {
        const wd = parseWeekdays(req.body.weekdays)
        scheduleToCreate = enumerateDates(start_date, end_date, wd.length ? wd : null).length * ids.length
      }
    }

    res.json({
      code: 0,
      data: {
        template: {
          id: t.id, name: t.name, code: t.code, mode_type: t.mode_type,
          weekdays: parseWeekdays(t.weekdays), start_time: t.start_time, end_time: t.end_time,
          clock_mode: t.clock_mode, shift_code: t.shift_code
        },
        shift: shiftInfo,
        target: { type: target_type, id: Number(target_id) },
        affected: { count: users.length, users: users.slice(0, 300) },
        hasSchedule: { count: hasSchedule.length, rows: scheduleTotal, users: hasSchedule },
        scheduleToCreate,
        errors
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

/* ══════════════════════════════════════════════════════
   POST /api/oa/work-modes/apply —— 执行铺设
   入参：preview 同款 + confirm_override(是否一并覆盖已有排班) + keep_user_ids(保留哪些人的排班)
   ══════════════════════════════════════════════════════ */
router.post('/apply', requirePermission(PERMISSIONS.SCHEDULE_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const errs = validateBody(req.body)
    if (errs.length) return res.status(400).json({ code: 400, message: errs.join('；') })
    const {
      template_id, target_type, target_id = 0, start_date, end_date,
      confirm_override = false, keep_user_ids = []
    } = req.body

    await conn.beginTransaction()
    const t = await getTemplate(conn, template_id)
    if (!t) { await conn.rollback(); return res.status(404).json({ code: 404, message: '模板不存在或已停用' }) }

    const users = await resolveTargets(conn, target_type, Number(target_id))
    if (!users.length) { await conn.rollback(); return res.status(400).json({ code: 400, message: '该范围下没有可铺设的员工' }) }

    const keepSet = new Set((Array.isArray(keep_user_ids) ? keep_user_ids : []).map(Number))
    const ids = users.map(u => Number(u.id)).filter(id => !keepSet.has(id))

    // ① 先写 assignment（先删同目标旧绑定 → 后写覆盖先写；唯一键 uk_target 也兜一层）
    await conn.query('DELETE FROM work_mode_assignments WHERE target_type = ? AND target_id = ?', [target_type, Number(target_id)])
    await conn.query(
      'INSERT INTO work_mode_assignments (template_id, target_type, target_id, effective_from, created_by) VALUES (?,?,?,?,?)',
      [t.id, target_type, Number(target_id), start_date || todayStr(), req.user.id])

    let cleared = 0, created = 0

    if (t.mode_type === 'regular') {
      // ② 常规班制：可选清理该人「今天及以后」的排班（先后覆盖的落地；历史不动）
      if (confirm_override && ids.length) {
        const [r] = await conn.query(
          `DELETE FROM shift_schedules WHERE user_id IN (?) AND schedule_date >= CURDATE()`, [ids])
        cleared = r.affectedRows || 0
      }
    } else {
      // ② 轮班班制：生成逐日排班（历史保护 + 后写覆盖先写）
      if (!t.shift_code) { await conn.rollback(); return res.status(400).json({ code: 400, message: '该轮班模板未关联班次代码' }) }
      const [[sh]] = await conn.query('SELECT id, name FROM shifts WHERE code = ? AND status = ?', [t.shift_code, 'active'])
      if (!sh) { await conn.rollback(); return res.status(400).json({ code: 400, message: '找不到启用中的班次「' + t.shift_code + '」，请先创建班次' }) }
      if (!start_date || !end_date) { await conn.rollback(); return res.status(400).json({ code: 400, message: '铺轮班需要选择起止日期' }) }
      if (end_date < todayStr()) { await conn.rollback(); return res.status(400).json({ code: 400, message: '结束日期已过去（历史保护：只能铺今天及以后）' }) }

      const wd = parseWeekdays(req.body.weekdays)
      const dates = enumerateDates(start_date, end_date, wd.length ? wd : null)
      if (!dates.length) { await conn.rollback(); return res.status(400).json({ code: 400, message: '所选区间内没有可排的日期（可能全部落在历史，或与所选星期不匹配）' }) }

      for (const uid of ids) {
        for (const d of dates) {
          await conn.query(
            `INSERT INTO shift_schedules (user_id, shift_id, schedule_date, status, attendance_required, created_by)
             VALUES (?,?,?,'scheduled',1,?)
             ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id), status = 'scheduled', created_by = VALUES(created_by)`,
            [uid, sh.id, d, req.user.id])
          created++
        }
      }
    }

    await conn.commit()
    res.json({
      code: 0,
      data: {
        template: { id: t.id, name: t.name, mode_type: t.mode_type, shift_code: t.shift_code },
        target: { type: target_type, id: Number(target_id) },
        affected_users: ids.length,
        kept_users: keepSet.size,
        cleared, created
      },
      message: '已铺设：' + t.name
    })
  } catch (err) {
    try { await conn.rollback() } catch (e) { /* noop */ }
    next(err)
  } finally {
    conn.release()
  }
})

/* ══════════════════════════════════════════════════════
   POST /api/oa/work-modes/unassign —— 撤销某个目标的铺设
   ══════════════════════════════════════════════════════ */
router.post('/unassign', requirePermission(PERMISSIONS.SCHEDULE_WRITE), async (req, res, next) => {
  try {
    const { target_type, target_id = 0 } = req.body
    if (!['all', 'department', 'user'].includes(target_type)) return res.status(400).json({ code: 400, message: 'target_type 无效' })
    const [r] = await pool.query('DELETE FROM work_mode_assignments WHERE target_type = ? AND target_id = ?', [target_type, Number(target_id)])
    res.json({ code: 0, data: { removed: r.affectedRows || 0 }, message: r.affectedRows ? '已撤销该铺设（排班记录未动）' : '该目标本来就没有铺设' })
  } catch (err) { next(err) }
})


/* ══════════════════════════════════════════════════════════════════
   B2：排班表模板下载 + 导入（波哥 2026-09-16：导入与下载模板成对）
   口径：矩阵式（左列手机号 + 每列一天）；空格 = 不动作；只允许今天及以后；
        person 按手机号匹配；班次按名称/代码匹配；两段式（先体检单再确认）
   ══════════════════════════════════════════════════════════════════ */

function cellText(v) {
  if (v === null || v === undefined) return ''
  if (v instanceof Date) return fmtDate(v)
  if (typeof v === 'object') {
    if (Array.isArray(v.richText)) return v.richText.map(t => t.text).join('')
    if (v.text !== undefined) return String(v.text)
    if (v.result !== undefined) return String(v.result)
    return ''
  }
  return String(v).trim()
}

function normalizeDate(v) {
  if (v instanceof Date) return fmtDate(v)
  const s = cellText(v)
  const m = s.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})/)
  if (m) return m[1] + '-' + String(m[2]).padStart(2, '0') + '-' + String(m[3]).padStart(2, '0')
  return null
}

// 解析排班表工作簿 → { dates, changes, errors, unknownShifts, unknownPhones }
async function parseScheduleWorkbook(conn, buffer) {
  const wb = new ExcelJS.Workbook()
  await wb.xlsx.load(buffer)
  const ws = wb.worksheets[0]
  if (!ws) return { dates: [], changes: [], errors: [{ row: 0, msg: '文件里没有工作表' }], unknownShifts: [], unknownPhones: [] }

  const headerRow = ws.getRow(1)
  const dateCols = []
  let phoneCol = 0, nameCol = 0
  for (let c = 1; c <= headerRow.cellCount; c++) {
    const t = cellText(headerRow.getCell(c).value)
    if (/手机|phone/i.test(t)) phoneCol = c
    else if (/姓名|name/i.test(t)) nameCol = c
    else { const d = normalizeDate(headerRow.getCell(c).value); if (d) dateCols.push({ col: c, date: d }) }
  }
  if (!phoneCol) phoneCol = 1
  if (!dateCols.length) {
    return { dates: [], changes: [], errors: [{ row: 1, msg: '表头里没找到日期列（需要类似 2026-09-21 的列头）' }], unknownShifts: [], unknownPhones: [] }
  }

  const [users] = await conn.query(
    `SELECT id, name, phone FROM users WHERE status = 'active' AND phone IS NOT NULL AND phone <> ''`)
  const phoneMap = new Map(users.map(u => [String(u.phone).trim(), u]))
  const [shifts] = await conn.query(`SELECT id, name, code FROM shifts WHERE status = 'active'`)
  const shiftMap = new Map()
  shifts.forEach(s => { shiftMap.set(String(s.name).trim(), s); shiftMap.set(String(s.code).trim().toUpperCase(), s) })

  const today = todayStr()
  const errors = [], changes = []
  const unknownShifts = new Set(), unknownPhones = new Set()

  for (let r = 2; r <= ws.rowCount; r++) {
    const row = ws.getRow(r)
    const phone = cellText(row.getCell(phoneCol).value).replace(/[\s-]/g, '')
    const nameTxt = nameCol ? cellText(row.getCell(nameCol).value) : ''
    const hasAny = dateCols.some(dc => cellText(row.getCell(dc.col).value) !== '')
    if (!phone && !hasAny) continue
    if (/示例|填写说明|说明/.test(nameTxt)) continue          // 示例行跳过
    if (!phone) { errors.push({ row: r, msg: '这一行没填手机号' }); continue }
    if (/示例/.test(phone) && !phoneMap.has(phone)) { errors.push({ row: r, phone, msg: '看起来是示例行，已跳过（可删除本行）' }); continue }
    const u = phoneMap.get(phone)
    if (!u) { unknownPhones.add(phone); errors.push({ row: r, phone, msg: '手机号不在系统员工名单里' }); continue }

    for (const dc of dateCols) {
      const val = cellText(row.getCell(dc.col).value).trim()
      if (!val) continue                                       // 空格 = 不动作
      if (/^(休|休息|x|X|—|-|\/|无)$/.test(val)) continue        // 显式"休息"也视为不动作
      const sh = shiftMap.get(val) || shiftMap.get(val.toUpperCase())
      if (!sh) { unknownShifts.add(val); errors.push({ row: r, phone, date: dc.date, msg: '班次名「' + val + '」不存在' }); continue }
      if (dc.date < today) { errors.push({ row: r, phone, date: dc.date, msg: '过去日期（历史保护，不会导入）' }); continue }
      changes.push({ row: r, user_id: u.id, name: u.name, phone, date: dc.date, shift_id: sh.id, shift_name: sh.name })
    }
  }
  return { dates: dateCols.map(d => d.date), changes, errors, unknownShifts: [...unknownShifts], unknownPhones: [...unknownPhones] }
}

// 标注每条是「新增」还是「覆盖」（查今天及以后已有排班）
async function markActions(conn, changes) {
  const byUser = new Map()
  for (const c of changes) {
    if (!byUser.has(c.user_id)) byUser.set(c.user_id, [])
    byUser.get(c.user_id).push(c)
  }
  let toCreate = 0, toUpdate = 0
  for (const [uid, list] of byUser) {
    const [exist] = await conn.query(
      `SELECT DATE_FORMAT(schedule_date, '%Y-%m-%d') AS d FROM shift_schedules
        WHERE user_id = ? AND schedule_date >= CURDATE() AND schedule_date IN (?)`,
      [uid, list.map(c => c.date)])
    const ex = new Set(exist.map(x => x.d))
    for (const c of list) {
      if (ex.has(c.date)) { c.action = 'update'; toUpdate++ } else { c.action = 'create'; toCreate++ }
    }
  }
  return { toCreate, toUpdate }
}

/* ── GET /api/oa/work-modes/schedule-template?start=&days= ── 下载排班表模板 ── */
router.get('/schedule-template', requirePermission(PERMISSIONS.SCHEDULE_WRITE), async (req, res, next) => {
  try {
    const start = /^\d{4}-\d{2}-\d{2}$/.test(req.query.start || '') ? req.query.start : todayStr()
    const days = Math.min(Math.max(Number(req.query.days) || 14, 1), 62)
    const dates = []
    const d0 = new Date(start + 'T00:00:00')
    for (let i = 0; i < days; i++) {
      const d = new Date(d0); d.setDate(d0.getDate() + i); dates.push(fmtDate(d))
    }

    const [users] = await pool.query(
      `SELECT u.name, u.phone, d.name AS dept FROM users u LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.status = 'active' AND u.role <> 'enterprise-admin' ORDER BY u.department_id, u.id`)
    const [shifts] = await pool.query(
      `SELECT name, code, start_time, end_time FROM shifts WHERE status = 'active' ORDER BY id`)

    const wb = new ExcelJS.Workbook()
    const ws = wb.addWorksheet('排班表')
    ws.addRow(['手机号', '姓名（仅参考）', ...dates])
    ws.getRow(1).font = { bold: true }
    ws.getRow(1).alignment = { horizontal: 'center' }
    ws.getColumn(1).width = 16
    ws.getColumn(2).width = 16
    dates.forEach((_, i) => { ws.getColumn(3 + i).width = 12 })
    users.forEach(u => ws.addRow([u.phone || '', u.name || '', ...dates.map(() => '')]))
    const demo = ['18600000000', '（示例行·请删除）', ...dates.map((_, i) => i === 0 ? (shifts[0] ? shifts[0].name : '行政班') : '')]
    ws.addRow(demo)
    ws.getRow(ws.rowCount).font = { italic: true, color: { argb: 'FF999999' } }

    const ws2 = wb.addWorksheet('填写说明')
    const rows = [
      ['排班表 · 填写说明（请先读我）'], [''],
      ['1. 只填「要上班」的格子；空着的格子 = 不排班，不会改动已有排班'],
      ['2. 格子里填「班次名称」，当前可用班次如下：'],
      ...shifts.map(s => ['', '· ' + s.name + '（' + String(s.start_time).slice(0, 5) + '–' + String(s.end_time).slice(0, 5) + '，代码 ' + s.code + '）']),
      [''],
      ['3. 第一列手机号用来匹配员工，请勿修改；匹配不到的会在导入时单独列出'],
      ['4. 只能填今天及以后的日期（历史排班受保护）'],
      ['5. 同一人同一天已有排班时，导入会覆盖它（导入前会先给你影响清单确认）'],
      ['6. 「姓名（仅参考）」只是给人看的，导入只认手机号'],
      ['7. 想要"这天不上班"？留空即可（不要在格子里写"休"）'],
    ]
    rows.forEach(r => ws2.addRow(r))
    ws2.getColumn(1).width = 64
    ws2.getRow(1).font = { bold: true, size: 14 }

    const buf = await wb.xlsx.writeBuffer()
    const fn = '排班表模板_' + dates[0] + '_至_' + dates[dates.length - 1] + '.xlsx'
    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    res.setHeader('Content-Disposition', "attachment; filename=\"schedule-template.xlsx\"; filename*=UTF-8''" + encodeURIComponent(fn))
    res.send(Buffer.from(buf))
  } catch (err) { next(err) }
})

/* ── POST /api/oa/work-modes/import-preview ── 上传解析 → 体检单（不写库）── */
router.post('/import-preview', requirePermission(PERMISSIONS.SCHEDULE_WRITE), upload.single('file'), async (req, res, next) => {
  try {
    if (!req.file || !req.file.buffer) return res.status(400).json({ code: 400, message: '请选择要导入的 xlsx 文件' })
    const parsed = await parseScheduleWorkbook(pool, req.file.buffer)
    const { toCreate, toUpdate } = parsed.changes.length ? await markActions(pool, parsed.changes) : { toCreate: 0, toUpdate: 0 }
    const userCount = new Set(parsed.changes.map(c => c.user_id)).size
    const shown = parsed.changes.slice(0, 100).map(c => ({ row: c.row, name: c.name, phone: c.phone, date: c.date, shift_name: c.shift_name, action: c.action }))
    res.json({
      code: 0,
      data: {
        file: req.file.originalname || '',
        dates: parsed.dates,
        summary: { cells: parsed.changes.length, users: userCount, toCreate, toUpdate, errors: parsed.errors.length },
        changes: shown,
        changesTotal: parsed.changes.length,
        errors: parsed.errors.slice(0, 200),
        errorsTotal: parsed.errors.length,
        unknownShifts: parsed.unknownShifts,
        unknownPhones: parsed.unknownPhones
      },
      message: '解析完成'
    })
  } catch (err) {
    if (err && /File too large|LIMIT_FILE_SIZE/i.test(String(err.message))) return res.status(413).json({ code: 413, message: '文件太大（上限 10MB）' })
    next(err)
  }
})

/* ── POST /api/oa/work-modes/import-apply ── 确认后写库（重新上传同一文件，服务端重新解析）── */
router.post('/import-apply', requirePermission(PERMISSIONS.SCHEDULE_WRITE), upload.single('file'), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    if (!req.file || !req.file.buffer) { conn.release(); return res.status(400).json({ code: 400, message: '请重新选择同一份 xlsx 文件' }) }
    const parsed = await parseScheduleWorkbook(conn, req.file.buffer)
    if (!parsed.changes.length) { conn.release(); return res.status(400).json({ code: 400, message: '这份文件里没有可导入的排班（' + (parsed.errors[0] ? parsed.errors[0].msg : '全是空格或都落在历史') + '）' }) }

    await conn.beginTransaction()
    let created = 0, updated = 0
    for (const c of parsed.changes) {
      const [r] = await conn.query(
        `INSERT INTO shift_schedules (user_id, shift_id, schedule_date, status, attendance_required, created_by)
         VALUES (?,?,?,'scheduled',1,?)
         ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id), status = 'scheduled', created_by = VALUES(created_by)`,
        [c.user_id, c.shift_id, c.date, req.user.id])
      if (r.affectedRows === 1) created++
      else if (r.affectedRows === 2) updated++
      else created++
    }
    await conn.commit()
    res.json({
      code: 0,
      data: { created, updated, skippedErrors: parsed.errors.length, users: new Set(parsed.changes.map(c => c.user_id)).size, dates: parsed.dates.length },
      message: '导入完成：新增 ' + created + ' 条，覆盖 ' + updated + ' 条'
    })
  } catch (err) {
    try { await conn.rollback() } catch (e) { /* noop */ }
    next(err)
  } finally {
    conn.release()
  }
})

export default router
