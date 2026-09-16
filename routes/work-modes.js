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

const router = Router()

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

export default router
