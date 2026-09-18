import { Router } from 'express'
import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requireRole, requirePermission, PERMISSIONS, ROLES } from '../middleware/rbac.js'
import { checkPerm } from '../utils/permission.js'
import { collectSubordinateIds } from '../utils/subordinates.js' // [task-team] 2026-09-15
import { getCompanyScope } from '../utils/company-scope.js' // [r6fix-b2] 2026-09-17 企业名单按角色收敛
import oaRoutes from './oa.js'
import { auditFlowDefinition } from './oa-flow.js' // [health-check]
import { JOB_TENANT as JOB_TENANT_MINIP } from './oa-flow.js'
import { listTabsForUser as listDbTabs } from './minip-tabbar-config.js'
import QRCode from 'qrcode'
import sharp from 'sharp'
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
const __dirname = path.dirname(fileURLToPath(import.meta.url))

const router = Router()

// business-card 名片海报/二维码目录 (2026-08-28 修复恢复)
const bcQrDir = path.join(__dirname, '../uploads/business-cards')
if (!fs.existsSync(bcQrDir)) fs.mkdirSync(bcQrDir, { recursive: true })

// ============================================================
// 公开接口（游客可访问，无需登录）
// ============================================================

// GET /api/minip/modules - 业务模块列表
// target=visitor 返回 both+employee（公开给游客看的所有模块）
// target=employee 返回 both+employee（登录员工看的所有模块）
// 不传 target 返回全部
router.get('/modules', async (req, res, next) => {
  try {
    const { target } = req.query
    let sql = 'SELECT id, module_key, title, subtitle, icon, path, target FROM minip_modules WHERE enabled = 1'
    const params = []
    if (target) {
      sql += ' AND target IN (?, "both")'
      params.push(target)
    }
    sql += ' ORDER BY sort_order ASC'
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// GET /api/minip/banners - 首页 banner（复用 banners 表）
router.get('/banners', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, title, image_url, link_url, sort
       FROM banners
       WHERE status = 'active'
       ORDER BY sort ASC LIMIT 10`
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// GET /api/minip/news - 新闻动态（复用 articles 表）
router.get('/news', async (req, res, next) => {
  try {
    const limit = parseInt(req.query.limit) || 10
    const [rows] = await pool.query(
      `SELECT id, title, summary, cover_image, published_at
       FROM articles
       WHERE status = 'published'
       ORDER BY published_at DESC LIMIT ?`,
      [limit]
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// GET /api/minip/news/:id - 新闻详情
router.get('/news/:id', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT id, title, summary, content, cover_image, author, category, published_at, created_at FROM articles WHERE id = ? AND status = "published"',
      [req.params.id]
    )
    if (!rows.length) return res.status(404).json({ code: 404, message: '新闻不存在' })
    res.json({ code: 0, data: rows[0] })
  } catch (err) { next(err) }
})

// GET /api/minip/activities - 营销活动列表
router.get('/activities', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, title, description, cover_image, location,
              start_date, end_date, max_participants, current_participants, status
       FROM minip_activities
       WHERE enabled = 1 AND status IN ('published', 'ongoing', 'finished')
       ORDER BY sort_order ASC LIMIT 20`
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// GET /api/minip/activities/:id - 活动详情
router.get('/activities/:id', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT * FROM minip_activities WHERE id = ? AND enabled = 1',
      [req.params.id]
    )
    if (!rows.length) return res.status(404).json({ code: 404, message: '活动不存在' })
    res.json({ code: 0, data: rows[0] })
  } catch (err) { next(err) }
})

// GET /api/minip/services - 会员服务/VIP 等级（复用 member_level 表）
router.get('/services', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, name, icon, min_points, max_points, discount_rate,
              points_ratio, birthday_double, free_shipping, exclusive_access,
              priority_customer, is_default, sort_order
       FROM member_level
       WHERE status = 'active'
       ORDER BY sort_order ASC`
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/minip/applications - 提交入会申请
router.post('/applications', async (req, res, next) => {
  try {
    const {
      company_name, contact_name, contact_phone, contact_email,
      business_type, team_size, expected_join_date, remarks
    } = req.body

    if (!company_name || !contact_name || !contact_phone) {
      return res.status(400).json({ code: 400, message: '请填写必填项' })
    }

    const [result] = await pool.query(
      `INSERT INTO minip_join_applications
       (company_name, contact_name, contact_phone, contact_email, business_type,
        team_size, expected_join_date, remarks, status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')`,
      [company_name, contact_name, contact_phone, contact_email || null,
       business_type || null, team_size || null, expected_join_date || null,
       remarks || null]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '提交成功，我们会尽快联系您' })
  } catch (err) { next(err) }
})

// ============================================================
// 管理接口（需要登录 + admin 角色，复用主站 JWT）
// ============================================================

// GET /api/minip/admin/applications - 申请列表（管理后台用）
router.get('/admin/applications', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const status = req.query.status
    let sql = `SELECT a.*, u.name as reviewer_name
               FROM minip_join_applications a
               LEFT JOIN users u ON a.reviewer_id = u.id`
    const params = []
    if (status) {
      sql += ' WHERE a.status = ?'
      params.push(status)
    }
    sql += ' ORDER BY a.created_at DESC LIMIT 100'
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// PUT /api/minip/admin/applications/:id/review - 审核申请
router.put('/admin/applications/:id/review', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const { status, review_remarks } = req.body
    if (!['approved', 'rejected', 'reviewing'].includes(status)) {
      return res.status(400).json({ code: 400, message: '状态无效' })
    }
    // 1. 更新申请状态
    const decoded = req.user
    const [r] = await pool.query(
      `UPDATE minip_join_applications
       SET status = ?, review_remarks = ?, reviewer_id = ?, reviewed_at = NOW()
       WHERE id = ?`,
      [status, review_remarks || null, decoded.id, req.params.id]
    )
    // 2. 闭环: 批准时根据 phone 自动创建 employee + 通知
    if (status === 'approved' && r.affectedRows > 0) {
      const [[app]] = await pool.query(
        `SELECT id, company_name, contact_name, contact_phone FROM minip_join_applications WHERE id = ?`,
        [req.params.id]
      )
      if (app && app.contact_phone) {
        // 通过 phone 找 user_id
        const [[userRow]] = await pool.query(
          `SELECT id FROM users WHERE phone = ? LIMIT 1`, [app.contact_phone]
        )
        if (userRow) {
          const employeeCode = `MINIP${Date.now().toString().slice(-6)}`
          await pool.query(
            `INSERT IGNORE INTO minip_employees (user_id, employee_code, employee_name, status, hired_at, created_at)
             VALUES (?, ?, ?, 'active', CURDATE(), NOW())`,
            [userRow.id, employeeCode, app.contact_name || '未命名']
          )
          await pool.query(
            `INSERT INTO notifications (user_id, type, title, content, created_at)
             VALUES (?, 'application_approved', ?, ?, NOW())`,
            [userRow.id, '申请已通过', `${app.company_name} 入驻申请已通过，员工编号: ${employeeCode}`]
          )
        }
      }
    }
    res.json({ code: 0, message: '审核成功' })
  } catch (err) { next(err) }
})

// GET /api/minip/admin/modules - 模块列表（管理用）
router.get('/admin/modules', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT * FROM minip_modules ORDER BY sort_order ASC'
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/minip/admin/modules - 新建模块
router.post('/admin/modules', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const { module_key, title, subtitle, icon, path, target, sort_order, enabled } = req.body
    if (!module_key || !title || !icon || !path) {
      return res.status(400).json({ code: 400, message: '请填写必填项' })
    }
    const [result] = await pool.query(
      `INSERT INTO minip_modules (module_key, title, subtitle, icon, path, target, sort_order, enabled)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [module_key, title, subtitle || null, icon, path, target || 'employee', sort_order || 0, enabled ? 1 : 0]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '创建成功' })
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') return res.status(400).json({ code: 400, message: 'module_key 已存在' })
    next(err)
  }
})

// PUT /api/minip/admin/modules/:id - 更新模块
router.put('/admin/modules/:id', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const { title, subtitle, icon, path, target, sort_order, enabled } = req.body
    await pool.query(
      `UPDATE minip_modules
       SET title = ?, subtitle = ?, icon = ?, path = ?, target = ?,
           sort_order = ?, enabled = ?
       WHERE id = ?`,
      [title, subtitle, icon, path, target, sort_order, enabled ? 1 : 0, req.params.id]
    )
    res.json({ code: 0, message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/minip/admin/modules/:id - 删除模块
router.delete('/admin/modules/:id', auth, requireRole('admin'), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM minip_modules WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

// GET /api/minip/admin/health-check [health-check] 2026-09-15
//   后台系统「系统体检」：配置总览（当前生效值）+ 问题清单（没配/配错），只读。
//   判定与发起/审批同一套（引擎 auditFlowDefinition），所以报"会卡住"就是真的会 400。
router.get('/admin/health-check', auth, requireRole('admin', 'superuser', 'enterprise-admin'), async (req, res, next) => {
  // mysql2 对 JSON 列会直接返回对象、对 TEXT 返回字符串 → 两种都要吃（踩过：只写 JSON.parse 会把上限读成"不限"）
  const parseJson = (v, dft) => { if (v == null) return dft; if (typeof v === 'object') return v; try { return JSON.parse(v) } catch (e) { return dft } }
  try {
    const items = []
    const config = []
    // [g4-count] 根因角色的展示名（flow_config 里的 role 是"角色名或部门名"，这里给常见英文键配中文）
    //            配词典只影响显示；没命中的原样显示 raw key，方便直接去流程配置里改
    const OA_ROLE_LABELS = {
      applicant: '发起人', direct_supervisor: '直属上级', dept_head: '部门负责人',
      hr: '人事', finance: '财务', gm: '总经理', committee: '审批委员会',
      director: '总监', cashier: '出纳', invest_director: '投资总监',
      dd_team: '尽调团队', risk: '风控', bd_manager: '商务经理',
      manager: '经理', admin: '管理员'
    }
    // [g4-count] 按「主根因角色」归组（一条问题只归 roles[0]，与排查脚本口径一致）
    const roleGroups = []
    const roleIndex = {}

    // ── 1) 流程定义逐个体检 ──
    const [defs] = await pool.query(
      "SELECT code, name, category, flow_config, is_active FROM workflow_definitions WHERE tenant_id = ? ORDER BY category, code",
      [JOB_TENANT_MINIP()])
    const active = defs.filter(d => Number(d.is_active) !== 0)
    let auditFailed = 0
    for (const d of active) {
      try {
        const r = await auditFlowDefinition(pool, d)
        for (const it of r.issues) {
          const rec = Object.assign({ scope: '流程定义', flowCode: r.code, flowName: r.name, flowCategory: r.category }, it)
          items.push(rec)
          const rcRole = rec.role || ''   // [g4-count]
          if (rcRole) {
            if (!roleIndex[rcRole]) {
              roleIndex[rcRole] = { role: rcRole, label: OA_ROLE_LABELS[rcRole] || rcRole, error: 0, warn: 0, info: 0, total: 0 }
              roleGroups.push(roleIndex[rcRole])
            }
            const g = roleIndex[rcRole]
            g.total++
            if (rec.level === 'error') g.error++
            else if (rec.level === 'warn') g.warn++
            else g.info++
          }
        }
      } catch (e) { auditFailed++ }
    }

    // ── 2) 组织：上级覆盖 ──
    const [[u1]] = await pool.query("SELECT COUNT(*) AS c FROM users WHERE status = 'active'")
    const [[u2]] = await pool.query("SELECT COUNT(*) AS c FROM users WHERE status = 'active' AND (supervisor_id IS NULL OR supervisor_id = 0)")
    const [[co]] = await pool.query('SELECT COUNT(*) AS c FROM companies')
    const staffTotal = Number(u1.c) || 0
    const noSup = Number(u2.c) || 0
    const companyCount = Number(co.c) || 0

    if (noSup > 0) {
      items.push({
        scope: '组织', level: 'warn',
        title: noSup + ' 名在职员工没有设置「直属上级」',
        detail: '用「直属上级」审批的流程（请假、补卡…），这些人发起时会提示"找不到处理人"而被拦下；他们提交时指定审批人可以正常走。',
        hint: '后台系统 → 组织管理 → 给这些员工设置上级（推荐）；或让员工提交时自行指定审批人'
      })
    }
    if (companyCount === 0) {
      items.push({ scope: '组织', level: 'error', title: '还没有企业（公司）数据', detail: '入驻企业列表为空，企业相关功能与统计都无法使用。', hint: '后台系统 → 企业管理 → 新增/审核入驻企业' })
    }

    // ── 3) 考勤：出勤规则 / 打卡时间 / 补卡上限 ──
    const [rules] = await pool.query("SELECT id, name, weekdays, start_time, end_time FROM attendance_rules WHERE status = 'active' ORDER BY id")
    // B1：班次（新机制；原称「上班模板」，2026-09-18 术语统一为「班次」）——体检要同时反映，否则会出现"体检说没规则、实际按模板判"的前后不一
    const [wmAssign] = await pool.query(
      `SELECT t.name, t.mode_type, t.start_time, t.end_time, t.clock_mode, a.target_type,
              COUNT(*) OVER (PARTITION BY t.id) AS assign_cnt
         FROM work_mode_assignments a
         JOIN work_mode_templates t ON a.template_id = t.id AND t.status = 'active'
        ORDER BY t.sort_order`)
    const wmCount = wmAssign.length
    const [[sc]] = await pool.query('SELECT COUNT(*) AS c FROM shift_schedules')
    const shiftCount = Number(sc.c) || 0
    const rule = rules[0] || null
    const hhmm = (t) => (t ? String(t).slice(0, 5) : '')
    if (!rules.length && wmCount === 0) {
      items.push({
        scope: '考勤', level: 'warn', title: '没有启用中的出勤规则',
        detail: '打卡的迟到/早退判定会退回到默认时间（09:00–18:00），与实际作息可能不符。',
        hint: '后台系统 → 考勤管理（或考勤规则页）新增一条启用中的出勤规则'
      })
    }
    const fixDef = active.find(d => d.code === 'attend-fix')
    const fixPolicy = fixDef ? (parseJson(fixDef.flow_config, {}) || {}).policy || {} : null
    const fixMax = fixPolicy ? Number(fixPolicy.fixMaxPerMonth) : null
    if (fixDef && (!Number.isFinite(fixMax) || fixMax <= 0)) {
      items.push({
        scope: '考勤', level: 'info', title: '补卡次数没有限制',
        detail: '当前每月补卡次数不限（上限 0）。若担心被滥用，可以设一个上限。',
        hint: '后台系统 → 考勤管理 → 补卡次数上限（0 = 不限）'
      })
    }

    // [r7-health] 2026-09-18：打卡时间**直接写具体时刻**、多班次逐个列（波哥口径："你就不能直接明确写？"）
    //   值优先取班次（班次优先于出勤规则，与判定链第 1 层一致）；自由工时/不打卡的班次没有时刻 → 跳过，不硬编。
    const wmTimes = []
    const wmSeen = {}
    for (const w of wmAssign) {
      const t0 = hhmm(w.start_time), t1 = hhmm(w.end_time)
      if (!t0 || !t1) continue
      const k = w.name + '|' + t0 + '|' + t1
      if (wmSeen[k]) continue
      wmSeen[k] = 1
      wmTimes.push(w.name + ' ' + t0 + '–' + t1)
    }
    const clockValue = wmTimes.length
      ? wmTimes.join(' / ')
      : (rule ? (hhmm(rule.start_time) + ' – ' + hhmm(rule.end_time)) : '未配置（默认 09:00–18:00）')
    const clockNote = wmTimes.length
      ? '按班次逐个列出（班次优先于出勤规则）'
      : (wmCount ? '班次未设上下班时刻（自由工时/不打卡）' : (rule ? ('规则：' + rule.name) : '无启用中的规则'))
    config.push({
      group: '考勤', items: [
        { label: '班次（新）', value: wmCount ? wmAssign.map(w => w.name + '(' + (w.target_type === 'all' ? '全员' : w.target_type === 'department' ? '部门' : '个人') + ')').join('、') : '未铺设', note: wmCount ? '班次优先于出勤规则判定；自由工时/不打卡口径不判迟到早退' : '可在「考勤与排班」里一键铺设' },
        { label: '打卡时间', value: clockValue, note: clockNote },
        { label: '出勤规则条数 / 排班条数', value: rules.length + ' 条 / ' + shiftCount + ' 条', note: shiftCount === 0 ? (wmCount ? '无排班时按班次判定' : '无排班时按出勤规则判定') : '有排班时优先按当天班次' },
        { label: '补卡次数上限', value: (fixDef ? (Number.isFinite(fixMax) && fixMax > 0 ? (fixMax + ' 次/月') : '不限') : '未接入该流程'), note: fixDef ? '后台系统 → 考勤管理可改' : '' }
      ]
    })

    // ── 4) 会议：场地 / 信用规则 / 高峰时段 ──
    const [[vr]] = await pool.query("SELECT COUNT(*) AS c FROM venue_rooms WHERE status = 'available'")
    const [[vcr]] = await pool.query('SELECT COUNT(*) AS c FROM venue_credit_rules')
    const [[peak]] = await pool.query("SELECT cfg_value FROM venue_configs WHERE cfg_key = 'peak_windows' LIMIT 1")
    const roomCount = Number(vr.c) || 0
    if (roomCount === 0) {
      items.push({ scope: '会议', level: 'error', title: '没有可用场地/会议室', detail: '会议预订页会没有可选空间，员工订不了会。', hint: '配置 venue_rooms（场地/房型）并在后台开启' })
    }
    if (Number(vcr.c) === 0) {
      items.push({ scope: '会议', level: 'warn', title: '没有会议室信用规则', detail: '违约扣分与门槛（信用分等级限制）不会生效。', hint: '配置 venue_credit_rules' })
    }
    config.push({
      group: '会议', items: [
        { label: '可用场地/会议室', value: roomCount + ' 个', note: '' },
        { label: '信用规则', value: Number(vcr.c) + ' 条', note: roomCount && Number(vcr.c) === 0 ? '缺失' : '' },
        { label: '高峰时段', value: peak ? '已配置' : '未配置', note: peak ? String(peak.cfg_value).slice(0, 80) : '高峰期限制等级不可约' }
      ]
    })

    // ── 5) 审批与组织总览 ──
    const hrCount = active.filter(d => d.category === 'hr').length
    config.push({
      group: '审批流程', items: [
        { label: '启用中的流程', value: active.length + ' 条', note: '共 ' + defs.length + ' 条（含停用）' },
        { label: '人事类流程', value: hrCount + ' 条', note: '含请假/加班/补卡等' },
        { label: '体检结果', value: items.filter(i => i.level === 'error').length + ' 项会卡住 / ' + items.filter(i => i.level === 'warn').length + ' 项警告', note: (auditFailed ? (auditFailed + ' 条定义体检失败 · ') : '') + '涉及 ' + roleGroups.length + ' 个角色' }   // [g4-count]
      ]
    })
    // [r7-health] 2026-09-18：企业视角首页要「本企业员工数」——按请求者企业归属计数
    //   平台管理员（无企业归属）→ 不显示这行，避免拿全站数字冒充本企业（原前端因此写着"待接入"）。
    // [r7-health-2] 2026-09-18：企业归属**不隐式依赖 auth 已挂 company_id**（口径同 utils/company-scope.js 的兜底）
    let __myCompanyId = (req.user && req.user.company_id != null) ? Number(req.user.company_id) : null
    if (__myCompanyId == null && req.user && req.user.id) {
      const [[__me]] = await pool.query('SELECT company_id FROM users WHERE id = ?', [req.user.id])
      __myCompanyId = (__me && __me.company_id != null) ? Number(__me.company_id) : null
    }
    let companyStaff = null
    if (__myCompanyId != null) {
      const [[cs]] = await pool.query("SELECT COUNT(*) AS c FROM users WHERE status = 'active' AND company_id = ?", [__myCompanyId])
      companyStaff = Number(cs.c) || 0
    }
    const orgItems = [
      { label: '入驻企业', value: companyCount + ' 家', note: '' },
      { label: '在职员工', value: staffTotal + ' 人', note: '' },
      { label: '未设上级', value: noSup + ' 人', note: noSup ? '影响"直属上级"审批的流程' : '' }
    ]
    if (companyStaff != null) orgItems.unshift({ label: '本企业在职员工', value: companyStaff + ' 人', note: '按你的企业归属统计' })
    config.push({ group: '组织', items: orgItems })

    // ── 6) [r7-health] 2026-09-18：原「系统」组两条提示**已删除**（体检门槛=每句都真，宁可不留）──
    //   ①「后台概览缺接口项 = 今日访客 / 本月活动」→ 该需求已作废（产品口径 2026-09-16：压根不需要这两个数据）
    //   ②「后台卡片未开放 = 审批 / 任务、内容管理」→ H1/H2 上线后**早已开放**，原文案与事实相反（在说假话）

    const summary = {
      companyStaff,   // [r7-health] 企业视角首页「本企业员工 N 人」；平台管理员为 null（前端显示「—」）
      error: items.filter(i => i.level === 'error').length,
      warn: items.filter(i => i.level === 'warn').length,
      info: items.filter(i => i.level === 'info').length,
      definitionsChecked: active.length,
      roles: roleGroups.length,   // [g4-count] 双口径之一：涉及多少个根因角色（另一个是 error/warn/info = 按项）
      checkedAt: new Date().toISOString()
    }
    res.json({ code: 0, data: { summary, config, items, roleGroups } })   // [g4-count]
  } catch (err) { next(err) }
})

// GET /api/minip/admin/activities - 活动列表（管理用）
router.get('/admin/activities', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT * FROM minip_activities ORDER BY sort_order ASC'
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/minip/admin/activities - 新建活动
router.post('/admin/activities', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const {
      title, description, cover_image, location,
      start_date, end_date, max_participants, status, sort_order, enabled
    } = req.body
    const [result] = await pool.query(
      `INSERT INTO minip_activities
       (title, description, cover_image, location, start_date, end_date,
        max_participants, status, sort_order, enabled)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [title, description || null, cover_image || null, location || null,
       start_date || null, end_date || null, max_participants || 0,
       status || 'draft', sort_order || 0, enabled ? 1 : 0]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/minip/admin/activities/:id - 更新活动
router.put('/admin/activities/:id', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const {
      title, description, cover_image, location,
      start_date, end_date, max_participants, status, sort_order, enabled
    } = req.body
    await pool.query(
      `UPDATE minip_activities
       SET title = ?, description = ?, cover_image = ?, location = ?,
           start_date = ?, end_date = ?, max_participants = ?, status = ?,
           sort_order = ?, enabled = ?
       WHERE id = ?`,
      [title, description || null, cover_image || null, location || null,
       start_date || null, end_date || null, max_participants || 0,
       status || 'draft', sort_order || 0, enabled ? 1 : 0, req.params.id]
    )
    res.json({ code: 0, message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/minip/admin/activities/:id - 删除活动
router.delete('/admin/activities/:id', auth, requireRole('admin'), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM minip_activities WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})
// ============================================================
// 企业端接口（需要登录 - JWT 通过 auth 中间件校验）
// 路由前缀：/api/minip/enterprise/*
// ============================================================

// ===== 财务模块 =====

// GET /api/minip/enterprise/expenses - 报销列表（当前用户提交）
router.get('/enterprise/expenses', auth, async (req, res, next) => {
  try {
    const { status, page = 1, pageSize = 20 } = req.query
    const userId = req.user.id
    let sql = `SELECT id, record_no, expense_date, category, category_name, amount, payment_method, description, approval_status, approver_id, approved_at, created_at FROM expense_records WHERE creator_id = ?`
    const params = [userId]
    if (status) { sql += ' AND approval_status = ?'; params.push(status) }
    sql += ' ORDER BY expense_date DESC LIMIT ? OFFSET ?'
    params.push(Number(pageSize), (Number(page) - 1) * Number(pageSize))
    const [rows] = await pool.query(sql, params)
    const [[{ total }]] = await pool.query('SELECT COUNT(*) as total FROM expense_records WHERE creator_id = ?', [userId])
    res.json({ code: 0, data: { list: rows, total, page: Number(page), pageSize: Number(pageSize) } })
  } catch (err) { next(err) }
})

// POST /api/minip/enterprise/expenses - 提交报销
router.post('/enterprise/expenses', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { record_no, expense_date, category, amount, payment_method, description, payee } = req.body
    if (!record_no || !expense_date || !category || !amount) return res.json({ code: 400, msg: '缺少必填字段' })
    const [r] = await pool.query(
      `INSERT INTO expense_records (record_no, expense_date, category, amount, payment_method, description, payee, approval_status, creator_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())`,
      [record_no, expense_date, category, amount, payment_method || 'cash', description || '', payee || '', userId]
    )
    res.json({ code: 0, data: { id: r.insertId } })
  } catch (err) { next(err) }
})

// PUT /api/minip/enterprise/expenses/:id/review - 审批 + 打款闭环
router.put('/enterprise/expenses/:id/review', auth, async (req, res, next) => {
  try {
    const { action } = req.body // approve / reject
    if (!['approve', 'reject'].includes(action)) {
      return res.status(400).json({ code: 400, message: 'action 必须是 approve/reject' })
    }
    const reviewerId = req.user.id
    const newStatus = action === 'approve' ? 'approved' : 'rejected'
    // 1. 更新报销状态
    const [r] = await pool.query(
      `UPDATE expense_records SET approval_status = ?, approver_id = ?, approved_at = NOW() WHERE id = ? AND approval_status = 'pending'`,
      [newStatus, reviewerId, req.params.id]
    )
    if (r.affectedRows === 0) {
      return res.status(404).json({ code: 404, message: '报销不存在或已审批' })
    }
    // 2. 闭环: 批准时写入钱包流水 + 通知员工
    if (action === 'approve') {
      const [[expense]] = await pool.query(
        `SELECT creator_id, amount, record_no FROM expense_records WHERE id = ?`,
        [req.params.id]
      )
      if (expense) {
        await pool.query(
          `INSERT INTO minip_wallet_transactions (user_id, type, amount, source_type, source_id, source_no, remark, created_at)
           VALUES (?, 'expense_refund', ?, 'expense', ?, ?, '报销审批打款', NOW())`,
          [expense.creator_id, expense.amount, req.params.id, expense.record_no]
        )
        await pool.query(
          `INSERT INTO notifications (user_id, type, title, content, created_at)
           VALUES (?, 'expense_approved', ?, ?, NOW())`,
          [expense.creator_id, '报销已批准', `您的报销单 ${expense.record_no} 金额 ¥${expense.amount} 已批准打款`]
        )
      }
    }
    res.json({ code: 0, message: action === 'approve' ? '审批通过, 已打款' : '已驳回' })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/invoices - 发票列表
router.get('/enterprise/invoices', auth, async (req, res, next) => {
  try {
    const { type, page = 1, pageSize = 20 } = req.query
    const userId = req.user.id
    let sql = `SELECT id, invoice_no, invoice_code, invoice_type, direction, invoice_date, seller_name, total_amount, tax_amount, status, created_at FROM invoices WHERE creator_id = ?`
    const params = [userId]
    if (type) { sql += ' AND invoice_type = ?'; params.push(type) }
    sql += ' ORDER BY invoice_date DESC LIMIT ? OFFSET ?'
    params.push(Number(pageSize), (Number(page) - 1) * Number(pageSize))
    const [rows] = await pool.query(sql, params)
    const [[{ total }]] = await pool.query('SELECT COUNT(*) as total FROM invoices WHERE creator_id = ?', [userId])
    res.json({ code: 0, data: { list: rows, total } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/wallet - 钱包余额 + 流水
router.get('/enterprise/wallet', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [[wallet]] = await pool.query('SELECT * FROM member_wallet WHERE user_id = ?', [userId]).catch(() => [[null]])
    const [logs] = await pool.query('SELECT id, amount, type, balance_after, remark, created_at FROM wallet_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 20', [userId])
    res.json({ code: 0, data: { wallet: wallet || { balance: 0, frozen: 0, total_in: 0, total_out: 0 }, logs } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/budget - 预算看板（部门/本月汇总）
router.get('/enterprise/budget', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [[summary]] = await pool.query(
      `SELECT COALESCE(SUM(amount), 0) as this_month_expense, COUNT(*) as count FROM expense_records WHERE creator_id = ? AND MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())`,
      [userId]
    )
    const [[pending]] = await pool.query('SELECT COUNT(*) as cnt FROM expense_records WHERE creator_id = ? AND approval_status = "pending"', [userId])
    const [[approved]] = await pool.query('SELECT COUNT(*) as cnt FROM expense_records WHERE creator_id = ? AND approval_status = "approved"', [userId])
    res.json({
      code: 0,
      data: {
        this_month_expense: summary.this_month_expense,
        pending_count: pending.cnt,
        approved_count: approved.cnt,
        monthly_budget: 100000
      }
    })
  } catch (err) { next(err) }
})

// ===== OA 模块（复用主站 oa 表）=====

// GET /api/minip/enterprise/attendance - 我的考勤（本月）
router.get('/enterprise/attendance', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    // 2026-08-25 加 worker_category + silent 字段 — 多端对齐
    const [rows] = await pool.query(
      `SELECT a.id, a.date as check_date, a.clock_in as check_in_time, a.clock_out as check_out_time,
              a.status, a.overtime_hours as work_hours, a.late_minutes, a.early_minutes,
              u.worker_category, u.require_attendance,
              (a.abnormal_reason LIKE 'non-required%') as silent
       FROM attendance a
       LEFT JOIN users u ON a.user_id = u.id
       WHERE a.user_id = ? AND MONTH(a.date) = MONTH(CURDATE()) AND YEAR(a.date) = YEAR(CURDATE())
       ORDER BY a.date DESC LIMIT 30`,
      [userId]
    )
    const [[stats]] = await pool.query(
      `SELECT SUM(CASE WHEN status='normal' THEN 1 ELSE 0 END) as normal_days, SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late_days, SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent_days, SUM(overtime_hours) as total_hours FROM attendance WHERE user_id = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())`,
      [userId]
    )
    res.json({ code: 0, data: { list: rows, stats, worker_category: rows[0]?.worker_category || 'office' } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/approvals - 待我审批 + 我提交的审批
router.get('/enterprise/approvals', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { type = 'pending' } = req.query
    let sql, params
    if (type === 'pending') {
      // 复用主站 approvals 表
      sql = `SELECT id, title, type, status, applicant_id as creator_id, created_at FROM approvals WHERE status = 'pending' ORDER BY created_at DESC LIMIT 30`
      params = [userId]
    } else if (type === 'submitted') {
      sql = `SELECT id, title, type, status, applicant_id as creator_id, created_at FROM approvals WHERE applicant_id = ? ORDER BY created_at DESC LIMIT 30`
      params = [userId]
    } else {
      sql = `SELECT id, title, type, status, applicant_id as creator_id, created_at FROM approvals WHERE applicant_id = ? OR applicant_id = ? ORDER BY created_at DESC LIMIT 30`
      params = [userId, userId]
    }
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: { list: rows, type } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/schedule - 我的日程
router.get('/enterprise/schedule', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { date } = req.query
    // 复用 oa_schedules 表（如果存在）否则用 approvals 凑
    const target = date || new Date().toISOString().split('T')[0]
    let scheduleList = []
    let meetingList = []
    try {
      const [schedules] = await pool.query(
        `SELECT id, title, start_time, end_time, description, type FROM oa_schedules WHERE user_id = ? AND DATE(start_time) = ? ORDER BY start_time ASC`,
        [userId, target]
      )
      scheduleList = schedules
    } catch (e) { /* 表可能不存在 */ }
    try {
      const [meetings] = await pool.query(
        `SELECT id, title, start_time, end_time, location, status FROM oa_meetings WHERE (host_id = ? OR JSON_CONTAINS(attendees, JSON_QUOTE(?))) AND DATE(start_time) >= ? ORDER BY start_time ASC LIMIT 20`,
        [userId, String(userId), target]
      )
      meetingList = meetings
    } catch (e) { /* 表可能不存在 */ }
    res.json({ code: 0, data: { date: target, schedules: scheduleList, meetings: meetingList } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/documents - 我的文档
router.get('/enterprise/documents', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { category } = req.query
    let sql = `SELECT id, title, category, file_size, created_at, updated_at FROM oa_documents WHERE 1=0 ORDER BY updated_at DESC LIMIT 50`
    const params = [userId, userId]
    if (category) { sql += ' AND category = ?'; params.push(category) }
    try {
      const [rows] = await pool.query(sql, params)
      res.json({ code: 0, data: { list: rows } })
    } catch (e) {
      res.json({ code: 0, data: { list: [] } })
    }
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/work-logs - 工作日志（用 orders 凑，没有就空）
router.get('/enterprise/work-logs', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    try {
      const [rows] = await pool.query(
        `SELECT id, log_date, title, content, hours, type FROM oa_work_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT 30`,
        [userId]
      )
      res.json({ code: 0, data: { list: rows } })
    } catch (e) {
      res.json({ code: 0, data: { list: [] } })
    }
  } catch (err) { next(err) }
})

// ===== 营销模块 =====

// GET /api/minip/enterprise/seckill - 限时秒杀列表
router.get('/enterprise/seckill', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, name, description, start_time, end_time, status FROM seckill_activities WHERE status = 'active' AND end_time > NOW() ORDER BY start_time ASC LIMIT 20`
    )
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/coupons - 我的优惠券
router.get('/enterprise/coupons', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [rows] = await pool.query(
      `SELECT id, coupon_id, coupon_name as name, type, money as amount, min_price as threshold, status, used_at, valid_end as expire_at FROM user_coupons WHERE user_id = ? ORDER BY valid_end DESC LIMIT 50`,
      [userId]
    )
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/referrals - 我的邀请记录
router.get('/enterprise/referrals', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [rows] = await pool.query(
      `SELECT id, invited_phone, invited_name, status, order_amount as reward_amount, paid_at as created_at FROM referral_records WHERE referrer_h5_user_id = ? ORDER BY created_at DESC LIMIT 30`,
      [userId]
    )
    const [[stats]] = await pool.query(
      `SELECT COUNT(*) as total_invites, COALESCE(SUM(order_amount), 0) as total_reward FROM referral_records WHERE referrer_h5_user_id = ?`,
      [userId]
    )
    res.json({ code: 0, data: { list: rows, stats } })
  } catch (err) { next(err) }
})

// ===== HR 模块（4 张表新建）=====

// GET /api/minip/enterprise/hr/employees - 通讯录
router.get('/enterprise/hr/employees', auth, async (req, res, next) => {
  try {
    const { dept, keyword } = req.query
    let sql = `SELECT id, employee_no, name, position, department, phone, email, avatar, status FROM minip_hr_employees WHERE status = 'active'`
    const params = []
    if (dept) { sql += ' AND department = ?'; params.push(dept) }
    if (keyword) { sql += ' AND (name LIKE ? OR employee_no LIKE ? OR phone LIKE ?)'; params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`) }
    sql += ' ORDER BY department, name LIMIT 100'
    const [rows] = await pool.query(sql, params)
    const [[{ total }]] = await pool.query('SELECT COUNT(*) as total FROM minip_hr_employees WHERE status = "active"')
    res.json({ code: 0, data: { list: rows, total } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/hr/recruit - 招聘岗位
router.get('/enterprise/hr/recruit', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, title, department, location, salary_range, headcount, status, published_at, expired_at FROM minip_hr_recruit WHERE status = 'open' ORDER BY published_at DESC LIMIT 20`
    )
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

// POST /api/minip/enterprise/hr/recruit/:id/apply - 投递简历
router.post('/enterprise/hr/recruit/:id/apply', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const userId = req.user.id
    const { name, phone, email, resume_url, cover_letter } = req.body
    await pool.query(
      `INSERT INTO minip_hr_applications (recruit_id, user_id, name, phone, email, resume_url, cover_letter, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted', NOW())`,
      [id, userId, name, phone, email, resume_url, cover_letter]
    )
    res.json({ code: 0, msg: '投递成功' })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/hr/payroll - 我的工资条
router.get('/enterprise/hr/payroll', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [rows] = await pool.query(
      `SELECT id, period, base_salary, bonus, deduction, net_salary, paid_at, status FROM minip_hr_payroll WHERE user_id = ? ORDER BY period DESC LIMIT 12`,
      [userId]
    )
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

// ============================================================
// 办公中心 — 按主站 rbac_menus + 用户角色动态返回可见菜单
// 主站后台改 visible_to 字段 → minip 端自动同步
// ============================================================

// GET /api/minip/office/menus - 返回当前用户可见的办公中心菜单分组
// 零硬编码铁律 2026-08-12: shortcuts 已经在 /config 里, 这里过滤掉跟快捷入口重复的 chip
router.get('/office/menus', auth, async (req, res, next) => {
  try {
    const userRole = (req.user.role || 'employee').toLowerCase()
    const userType = (req.user.user_type || 'staff').toLowerCase()
    const [rows] = await pool.query(
      `SELECT id, name, icon, minip_group, minip_icon, minip_path, minip_sort, visible_to
       FROM rbac_menus
       WHERE parent_id = 100 AND status = 'enabled' AND visible = 'show'
       ORDER BY minip_group, minip_sort`
    )
    // 按 visible_to 过滤
    const visible = rows.filter((r) => {
      const set = (r.visible_to || '').split(',').map((s) => s.trim().toLowerCase()).filter(Boolean)
      return set.includes(userRole) || set.includes('all')
    })

    // 动态计算快捷入口 path 集合 — 跟 config.shortcuts 完全一致（不硬编）
    //    规则：staff/admin 都隐藏 考勤/任务/日志; admin 额外隐藏 审批
    //    0 硬编：路径从 DB rbac_menus 的 minip_path 查；这里的快捷入口走 /api/minip/config.shortcuts
    const shortcutPaths = new Set()
    if (userType === 'staff' || userRole === 'admin') {
      shortcutPaths.add('/hr/attendance')  // 考勤
      shortcutPaths.add('/oa/task')        // 任务
      shortcutPaths.add('/oa/worklog')     // 日志
      if (userRole === 'admin') {
        shortcutPaths.add('/oa/approvals') // 审批（admin 专属）
      }
    }
    // 过滤掉与快捷入口重复的 chip
    const dedup = visible.filter((r) => !shortcutPaths.has(r.minip_path))

    // 按 group 分组
    const groups = {}
    const groupIcons = {
      finance: 'account_balance',
      hr: 'groups',
      oa: 'business_center',
      marketing: 'campaign'
    }
    const groupTitles = {
      finance: '财务',
      hr: '人力',
      oa: '协同',
      marketing: '营销'
    }
    dedup.forEach((r) => {
      const g = r.minip_group
      if (!groups[g]) groups[g] = []
      groups[g].push({
        key: r.name,
        icon: r.minip_icon,
        path: r.minip_path,
        sort: r.minip_sort
      })
    })
    const result = Object.keys(groups).map((g) => ({
      id: g,
      title: groupTitles[g] || g,
      icon: groupIcons[g] || 'apps',
      items: groups[g].sort((a, b) => a.sort - b.sort)
    }))
    res.json({ code: 0, data: { role: userRole, groups: result } })
  } catch (err) { next(err) }
})

// ============================================================
// GET /api/minip/config - minip 前端统一配置端点（零硬编码铁律 2026-08-12）
// 公开端点：未登录也能访问（用户相关字段 fallback）
// 一次性返回：任务优先级/任务状态/日志类型/审批状态/角色标签/tabbar 配置/分组标题/分组 icon
// 前端 useMinipConfig() 启动拉一次，缓存到 Pinia
// ============================================================
router.get('/config', async (req, res, next) => {
  try {
    // 公开访问：不强制 auth，从 Authorization 头尝试解析 user
    let userRole = 'guest'
    let userType = 'guest'
    const authHeader = req.headers.authorization
    if (authHeader) {
      try {
        const token = authHeader.replace(/^Bearer\s+/i, '')
        const decoded = jwt.verify(token, process.env.JWT_SECRET || 'caimeite-dev-secret-2026')
        userRole = (decoded.role || 'employee').toLowerCase()
        userType = (decoded.user_type || 'staff').toLowerCase()
      } catch {
        // token 无效不影响, 用 guest
      }
    }

    // 1. 任务优先级（对齐主站 office_tasks.priority enum）
    const priorities = [
      { value: 'urgent', label: '紧急', color: '#ef4444', order: 1 },
      { value: 'high', label: '高', color: '#f97316', order: 2 },
      { value: 'medium', label: '中', color: '#3b82f6', order: 3 },
      { value: 'low', label: '低', color: '#9ca3af', order: 4 }
    ]

    // 2. 任务状态（对齐主站 office_tasks.status enum）
    const taskStatuses = [
      { value: 'pending', label: '待办', color: '#f59e0b' },
      { value: 'in_progress', label: '进行中', color: '#3b82f6' },
      { value: 'completed', label: '已完成', color: '#10b981' },
      { value: 'cancelled', label: '已取消', color: '#9ca3af' }
    ]

    // 3. 日志类型（对齐主站 work_logs.log_type）
    const logTypes = [
      { value: 'work', label: '工作', color: '#6366f1' },
      { value: 'complaint', label: '投诉', color: '#ef4444' },
      { value: 'share', label: '分享', color: '#0ea5e9' }
    ]

    // 4. 审批状态（对齐主站 oa_approvals.status）
    const approvalStatuses = [
      { value: 'pending', label: '待审批', color: '#f59e0b' },
      { value: 'approved', label: '已通过', color: '#10b981' },
      { value: 'rejected', label: '已拒绝', color: '#ef4444' },
      { value: 'withdrawn', label: '已撤回', color: '#9ca3af' }
    ]

    // 5. 角色标签（从 rbac_roles 表动态拉，失败 fallback 14 个）
    let roleLabels = {
      admin: '管理员', employee: '员工', boss: '老板', manager: '经理',
      shopkeeper: '店主', member: '成员', warehouse: '仓库', experience: '体验',
      tester: '测试', dispatcher: '调度', reviewer: '审核', repairer: '维修',
      customer_service: '客服'
    }
    try {
      const [roleRows] = await pool.query("SELECT role_key, label_zh FROM rbac_roles WHERE status = 'active'")
      const map = {}
      for (const r of roleRows) map[r.role_key] = r.label_zh || r.role_key
      if (Object.keys(map).length > 0) roleLabels = map
    } catch {}

    // 6. 底部 tabbar 配置（按 user_type + role 动态返回，零硬编码）
    //    2026-08-27 江小鱼重构: 优先从 minip_tabbar_config 表读, 表空 fallback 硬编码
    //    gdqadmin 后台可管理, 改后立即生效
    let tabbar = []
    try {
      const dbTabs = await listDbTabs(userType, userRole)
      if (dbTabs.length > 0) {
        // DB 驱动 - 转换字段名匹配前端预期
        tabbar = dbTabs.map(t => ({
          key: t.key,
          path: t.pagePath,
          icon: t.icon,
          label: t.label,
        }))
      }
    } catch (e) {
      console.warn('[minip-config] tabbar from DB failed, fallback hardcode:', e.message)
    }

    // Fallback (DB 表空 或 异常): 硬编码
    if (tabbar.length === 0) {
      if (userType === 'guest' || userType === 'customer' || userRole === 'guest') {
        // 客户/游客：主页/服务/活动/我的
        tabbar = [
          { path: '/enterprise/home', icon: 'home', label: '主页' },
          { path: '/visitor/services', icon: 'workspace_premium', label: '服务' },
          { path: '/visitor/activities', icon: 'campaign', label: '活动' },
          { path: '/me', icon: 'person', label: '我的' }
        ]
      } else {
        // 员工：主页/办公/我的（admin 看 4 个 tab 加消息）
        tabbar = [
          { path: '/enterprise/home', icon: 'home', label: '主页' },
          { path: '/office', icon: 'business_center', label: '办公' },
          { path: '/me', icon: 'person', label: '我的' }
        ]
        if (userRole === 'admin') {
          tabbar.splice(2, 0, { path: '/oa/approvals', icon: 'pending_actions', label: '审批' })
        }
      }
    }

    // 7. 办公中心分组标题/icon（从 rbac_menus 表的 minip_group 枚举动态拿）
    let groupTitles = {
      finance: '财务', hr: '人力', oa: '协同', marketing: '营销'
    }
    let groupIcons = {
      finance: 'account_balance', hr: 'groups',
      oa: 'business_center', marketing: 'campaign'
    }
    try {
      const [groupRows] = await pool.query(
        `SELECT DISTINCT minip_group FROM rbac_menus
         WHERE minip_group IS NOT NULL AND minip_group != ''
           AND status = 'enabled' AND visible = 'show' AND parent_id = 100`
      )
      // 如果表里有新 group，自动发现（fallback 用 key 本身）
      for (const r of groupRows) {
        const g = r.minip_group
        if (!groupTitles[g]) groupTitles[g] = g
        if (!groupIcons[g]) groupIcons[g] = 'apps'
      }
    } catch {}

    // 8. 字段类型 → icon 映射（前端 OALog 用）
    //    零硬编码铁律 2026-08-12: 所有类型从前端动态识别,后端只提供类型清单
    //    类型分类：
    //      - 文本类: text / number / textarea
    //      - 时间类: date / time / time_range / datetime
    //      - 选择类: select / radio / checkbox
    //      - 评分类: rating
    //      - 位置类: location
    //      - 上传类: image / file
    //      - 用户类 (USER_PICKER_TYPES 派生): participants / recipients / complainants / approvers / user / users
    const fieldTypes = [
      { value: 'text', icon: 'short_text', group: 'text' },
      { value: 'number', icon: 'numbers', group: 'text' },
      { value: 'textarea', icon: 'subject', group: 'text' },
      { value: 'date', icon: 'event', group: 'time' },
      { value: 'time', icon: 'schedule', group: 'time' },
      { value: 'time_range', icon: 'timer', group: 'time' },
      { value: 'datetime', icon: 'event_available', group: 'time' },
      { value: 'select', icon: 'arrow_drop_down_circle', group: 'select' },
      { value: 'radio', icon: 'radio_button_checked', group: 'select' },
      { value: 'checkbox', icon: 'check_box', group: 'select' },
      { value: 'rating', icon: 'star', group: 'rating' },
      { value: 'location', icon: 'place', group: 'location' },
      { value: 'image', icon: 'image', group: 'upload' },
      { value: 'file', icon: 'attach_file', group: 'upload' },
      // 用户类（前端识别为多 user picker）
      { value: 'participants', icon: 'group', group: 'user', multiple: true, label: '参与人' },
      { value: 'recipients', icon: 'forward_to_inbox', group: 'user', multiple: true, label: '收件人' },
      { value: 'complainants', icon: 'gavel', group: 'user', multiple: true, label: '被投诉人' },
      { value: 'approvers', icon: 'how_to_reg', group: 'user', multiple: true, label: '审批人' },
      { value: 'user', icon: 'person', group: 'user', multiple: false, label: '指派人' },
      { value: 'users', icon: 'people', group: 'user', multiple: true, label: '选择人员' }
    ]

    // 9. 办公首页快捷入口（3 大图标：考勤/任务/日志，按角色显示）
//    零硬编码铁律 2026-08-12：所有配置在后端，前端只消费
//    客户/游客不显示，员工全显示，admin 还会多显示"审批"
    const shortcuts = []
    if (userType === 'staff' || userRole === 'admin') {
      shortcuts.push(
        { key: 'attendance', label: '考勤', path: '/hr/attendance', icon: 'event_available', gradient: ['#6366f1', '#818cf8'] },
        { key: 'task', label: '任务', path: '/oa/task', icon: 'task_alt', gradient: ['#10b981', '#34d399'] },
        { key: 'worklog', label: '日志', path: '/oa/worklog', icon: 'edit_note', gradient: ['#f59e0b', '#fbbf24'] }
      )
      if (userRole === 'admin') {
        shortcuts.push({ key: 'approval', label: '审批', path: '/oa/approvals', icon: 'pending_actions', gradient: ['#ec4899', '#f472b6'] })
      }
    }

    res.json({
      code: 0,
      data: {
        priorities,
        taskStatuses,
        logTypes,
        approvalStatuses,
        roleLabels,
        tabbar,
        groupTitles,
        groupIcons,
        fieldTypes,
        shortcuts,
        userRole,
        userType
      }
    })
  } catch (err) { next(err) }
})

// GET /api/minip/office/users/candidates - 参与人/接收人候选 (对齐 gdqadmin /users/subordinates + /users/list 兜底)
//   优先返回下属(递归), 顶置自己; 无下属则返回全部活跃用户
router.get('/office/users/candidates', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const userRole = req.user.role
    const isBroad = ['admin', 'superadmin', 'manager', 'director'].includes(userRole)
    // [r6fix-f4] 2026-09-17：① 候选名单加**企业作用域过滤**（此前是全表 LIMIT 300 → 跨企业泄露，同 B-2 同款问题）；
    //   ② 新增 ?all=1：任务派单要"全员可选"（开放工单：谁都能给谁派），此时不做"下属"收敛、直接给作用域内全员。
    const __scC = await getCompanyScope(req)
    const wantAll = req.query.all === '1'
    let __uWhere = "status='active'"
    const __uArgs = []
    if (__scC.kind === 'incubator') { __uWhere += ' AND company_id IS NULL' }
    else if (__scC.kind !== 'global') { __uWhere += ' AND company_id = ?'; __uArgs.push(__scC.companyId) }
    const __allSql = `SELECT id, name, avatar, department, role, supervisor_id FROM users WHERE ${__uWhere} ORDER BY name LIMIT 300`
    let list = []
    if (isBroad || wantAll) {
      // 管理角色 / 任务派单（all=1）: 直接返回作用域内全部 active 用户
      const [all] = await pool.query(__allSql, __uArgs)
      list = all || []
    } else {
      try {
        const [sub] = await pool.query(`
          WITH RECURSIVE subordinate_tree AS (
            SELECT id, name, avatar, department, role, supervisor_id FROM users
            WHERE supervisor_id = ? AND status = 'active'
            UNION ALL
            SELECT u.id, u.name, u.avatar, u.department, u.role, u.supervisor_id FROM users u
            INNER JOIN subordinate_tree st ON u.supervisor_id = st.id
          )
          SELECT * FROM subordinate_tree ORDER BY name`, [userId])
        list = sub || []
      } catch (e) { console.error('[minip] candidates sub err', e?.message || e) }
    }
    const [[me]] = await pool.query('SELECT id, name, avatar, department, role, supervisor_id FROM users WHERE id = ?', [userId])
    if (list.length === 0) {
      const [all] = await pool.query(__allSql, __uArgs)
      list = all || []
    }
    res.json({ code: 0, data: me ? [{ ...me, is_self: true }, ...list] : list, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/minip/office/work-log-templates - 工作日志模板列表（抄 oa.js.bak work-log-templates）
//   修复: 活跃 oa.js 无此路由, minip work-log-form 调它一直 404, 模板选择加载失败
router.get('/office/work-log-templates', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT t.*, u.name as creator_name
       FROM work_log_templates t
       LEFT JOIN users u ON t.creator_id = u.id
       WHERE t.status = 'active'
       ORDER BY t.is_default DESC, t.created_at DESC`
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/minip/office/work-logs - 工作日志列表 (抄主站 work-logs.js: 支持 type/互动计数/模板名/审核状态)
//   type: mine(自己的) | received(需要我处理的) | all(全员, 管理员) — 对齐 gdqadmin WorkLogManage 的 my/received/templates
router.get('/office/work-logs', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const userRole = req.user.role
    const isAdmin = ['admin', 'manager', 'director'].includes(userRole)
    const { page = 1, pageSize = 20, type = 'mine', status, date_from, date_to } = req.query
    const offset = (Number(page) - 1) * Number(pageSize)
    let where = 'WHERE 1=1'
    const params = []
    if (type === 'mine') { where += ' AND w.user_id = ?'; params.push(userId) }
    else if (type === 'received') { where += ' AND JSON_CONTAINS(w.recipients, ?)'; params.push(JSON.stringify(userId)) }
    else if (type === 'all' && isAdmin) { /* 全部 */ }
    else { where += ' AND w.user_id = ?'; params.push(userId) }
    if (status) { where += ' AND w.status = ?'; params.push(status) }
    const [rows] = await pool.query(
      `SELECT w.id, w.user_id, w.log_type, w.submit_date, w.content, w.today_work, w.tomorrow_plan, w.issues, w.status,
              w.attachments, w.recipients, w.created_at,
              u.name as creator_name, u.avatar as creator_avatar, u.department as creator_department,
              wlt.name as template_name,
              (SELECT COUNT(*) FROM work_log_interactions WHERE log_id = w.id AND type = 'like') as like_count,
              (SELECT COUNT(*) FROM work_log_interactions WHERE log_id = w.id AND type = 'comment') as comment_count,
              (SELECT COUNT(*) FROM work_log_interactions WHERE log_id = w.id AND type = 'dislike') as dislike_count,
              (SELECT COUNT(*) FROM work_log_interactions WHERE log_id = w.id AND type = 'forward') as forward_count,
              EXISTS(SELECT 1 FROM work_log_interactions WHERE log_id = w.id AND user_id = ? AND type = 'like') as liked_by_me,
              EXISTS(SELECT 1 FROM work_log_interactions WHERE log_id = w.id AND user_id = ? AND type = 'dislike') as disliked_by_me,
              EXISTS(SELECT 1 FROM work_log_interactions WHERE log_id = w.id AND user_id = ? AND type = 'forward') as forwarded_by_me
       FROM work_logs w
       LEFT JOIN users u ON w.user_id = u.id
       LEFT JOIN work_log_templates wlt ON w.template_id = wlt.id
       ${where}
       ORDER BY w.submit_date DESC, w.id DESC
       LIMIT ? OFFSET ?`,
      [userId, userId, userId, ...params, Number(pageSize), offset]
    )
    const [cntRows] = await pool.query(`SELECT COUNT(*) as total FROM work_logs w ${where}`, params)
    // 解析 attachments 为 images 数组 (抄主站 WorkLogManage-Dg-nx-L1.js 渲染逻辑: e.url || e)
    const logs = rows.map(r => {
      let images = []
      try { images = r.attachments ? (typeof r.attachments === 'string' ? JSON.parse(r.attachments) : r.attachments) : [] } catch (e) { images = [] }
      return {
        ...r,
        attachments: images,
        images,
        image_url: images.length ? (images[0].url || images[0]) : '',
        user_name: r.creator_name || `用户#${r.user_id}`,
        avatar_url: r.creator_avatar || '',
        creator_name: r.creator_name || `用户#${r.user_id}`
      }
    })
    res.json({
      code: 0,
      data: {
        list: logs,
        logs: logs,
        total: Number(cntRows[0].total),
        page: Number(page),
        limit: Number(pageSize)
      }
    })
  } catch (err) { next(err) }
})

// ════════════════════════════════════════════════════════════════════
// 工作日志完整版 (抄 gdqadmin 主站 work-logs.js, 用 minip auth)
//  模板列表 / 详情 / 收阅 / 点赞 / 评论 / 互动列表 / 审核 — 让移动端具备完整能力
// ════════════════════════════════════════════════════════════════════
function wlSafeParse(str, defaultVal = {}) {
  if (!str) return defaultVal
  try { return typeof str === 'object' ? str : JSON.parse(str) } catch (e) { return defaultVal }
}

// GET /office/work-logs/:id - 日志详情
router.get('/office/work-logs/:id', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const [logs] = await pool.query(
      `SELECT wl.*, u.name as creator_name, u.avatar as creator_avatar, wlt.name as template_name
       FROM work_logs wl
       LEFT JOIN users u ON wl.user_id = u.id
       LEFT JOIN work_log_templates wlt ON wl.template_id = wlt.id
       WHERE wl.id = ?`,
      [id]
    )
    if (logs.length === 0) return res.status(404).json({ code: 404, message: '日志不存在' })
    res.json({ code: 0, data: logs[0], message: 'ok' })
  } catch (err) { next(err) }
})

// POST /office/work-logs/:id/read - 标记已读 (仅接收人)
router.post('/office/work-logs/:id/read', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const [logs] = await pool.query('SELECT recipients FROM work_logs WHERE id = ?', [id])
    if (logs.length === 0) return res.status(404).json({ code: 404, message: '日志不存在' })
    const recipients = wlSafeParse(logs[0].recipients)
    if (!recipients.includes(req.user.id)) return res.status(403).json({ code: 403, message: '仅接收人可标记已读' })
    const [existing] = await pool.query('SELECT id FROM work_log_interactions WHERE log_id = ? AND user_id = ? AND type = ?', [id, req.user.id, 'read'])
    if (existing.length === 0) {
      await pool.query('INSERT INTO work_log_interactions (log_id, user_id, type) VALUES (?, ?, ?)', [id, req.user.id, 'read'])
    }
    res.json({ code: 0, data: { read: true }, message: '已标记已读' })
  } catch (err) { next(err) }
})

// POST /office/work-logs/:id/like - 点赞/取消 (toggle)
router.post('/office/work-logs/:id/like', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const [logs] = await pool.query('SELECT user_id, recipients FROM work_logs WHERE id = ?', [id])
    if (logs.length === 0) return res.status(404).json({ code: 404, message: '日志不存在' })
    const log = logs[0]
    const recipients = wlSafeParse(log.recipients)
    if (log.user_id !== req.user.id && !recipients.includes(req.user.id)) return res.status(403).json({ code: 403, message: '无权点赞' })
    const [existing] = await pool.query('SELECT id FROM work_log_interactions WHERE log_id = ? AND user_id = ? AND type = ?', [id, req.user.id, 'like'])
    if (existing.length > 0) {
      await pool.query('DELETE FROM work_log_interactions WHERE id = ?', [existing[0].id])
      return res.json({ code: 0, data: { liked: false }, message: '已取消点赞' })
    }
    await pool.query('INSERT INTO work_log_interactions (log_id, user_id, type) VALUES (?, ?, ?)', [id, req.user.id, 'like'])
    res.json({ code: 0, data: { liked: true }, message: '已点赞' })
  } catch (err) { next(err) }
})

// POST /office/work-logs/:id/comment - 发表评论
router.post('/office/work-logs/:id/comment', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const { content } = req.body
    if (!content) return res.status(400).json({ code: 400, message: '评论内容不能为空' })
    const [logs] = await pool.query('SELECT user_id, recipients FROM work_logs WHERE id = ?', [id])
    if (logs.length === 0) return res.status(404).json({ code: 404, message: '日志不存在' })
    const log = logs[0]
    const recipients = wlSafeParse(log.recipients)
    if (log.user_id !== req.user.id && !recipients.includes(req.user.id)) return res.status(403).json({ code: 403, message: '无权评论' })
    const [result] = await pool.query('INSERT INTO work_log_interactions (log_id, user_id, type, content) VALUES (?, ?, ?, ?)', [id, req.user.id, 'comment', content])
    res.json({ code: 0, data: { id: result.insertId }, message: '评论成功' })
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') return res.status(400).json({ code: 400, message: '已评论过此日志' })
    next(err)
  }
})

// GET /office/work-logs/:id/interactions - 互动列表(评论/点赞人/已读)
router.get('/office/work-logs/:id/interactions', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const [logs] = await pool.query('SELECT id FROM work_logs WHERE id = ?', [id])
    if (logs.length === 0) return res.status(404).json({ code: 404, message: '日志不存在' })
    const [interactions] = await pool.query(
      `SELECT wli.*, u.name
       FROM work_log_interactions wli
       LEFT JOIN users u ON wli.user_id = u.id
       WHERE wli.log_id = ?
       ORDER BY wli.created_at ASC`,
      [id]
    )
    res.json({ code: 0, data: interactions, message: 'ok' })
  } catch (err) { next(err) }
})

// PATCH /office/work-logs/:id/review - 审核日志 (admin/manager/director)
router.patch('/office/work-logs/:id/review', auth, requireRole(ROLES.ADMIN, ROLES.MANAGER, ROLES.DIRECTOR), async (req, res, next) => {
  try {
    const { id } = req.params
    const { status, comment } = req.body
    const allowedStatus = ['approved', 'rejected', 'submitted']
    if (!allowedStatus.includes(status)) return res.status(400).json({ code: 400, message: 'status 必须是 approved / rejected / submitted' })
    const [logs] = await pool.query('SELECT id FROM work_logs WHERE id = ?', [id])
    if (logs.length === 0) return res.status(404).json({ code: 404, message: '日志不存在' })
    await pool.query('UPDATE work_logs SET status = ?, review_comment = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?', [status, comment || null, req.user.id, id])
    res.json({ code: 0, data: { id: Number(id), status }, message: '审核完成' })
  } catch (err) { next(err) }
})

// POST /office/work-logs/:id/dislike - 踩/取消踩 (toggle, 对齐 gdqadmin work-logs dislike)
router.post('/office/work-logs/:id/dislike', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const [logs] = await pool.query('SELECT user_id, recipients FROM work_logs WHERE id = ?', [id])
    if (logs.length === 0) return res.status(404).json({ code: 404, message: '日志不存在' })
    const log = logs[0]
    const recipients = wlSafeParse(log.recipients)
    if (log.user_id !== req.user.id && !recipients.includes(req.user.id))
      return res.status(403).json({ code: 403, message: '无权操作' })
    const [existing] = await pool.query(
      'SELECT id FROM work_log_interactions WHERE log_id = ? AND user_id = ? AND type = ?',
      [id, req.user.id, 'dislike']
    )
    if (existing.length > 0) {
      await pool.query('DELETE FROM work_log_interactions WHERE id = ?', [existing[0].id])
      return res.json({ code: 0, data: { disliked: false }, message: '已取消踩' })
    }
    await pool.query(
      'INSERT INTO work_log_interactions (log_id, user_id, type) VALUES (?, ?, ?)',
      [id, req.user.id, 'dislike']
    )
    res.json({ code: 0, data: { disliked: true }, message: '已踩' })
  } catch (err) { next(err) }
})

// POST /office/work-logs/:id/forward - 转发日志 (对齐 gdqadmin work-logs forward)
router.post('/office/work-logs/:id/forward', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const [logs] = await pool.query('SELECT user_id, recipients FROM work_logs WHERE id = ?', [id])
    if (logs.length === 0) return res.status(404).json({ code: 404, message: '日志不存在' })
    const log = logs[0]
    const recipients = wlSafeParse(log.recipients)
    if (log.user_id !== req.user.id && !recipients.includes(req.user.id))
      return res.status(403).json({ code: 403, message: '无权操作' })
    const [existing] = await pool.query(
      'SELECT id FROM work_log_interactions WHERE log_id = ? AND user_id = ? AND type = ?',
      [id, req.user.id, 'forward']
    )
    if (existing.length > 0) return res.json({ code: 0, data: { forwarded: true }, message: '已转发' })
    await pool.query(
      'INSERT INTO work_log_interactions (log_id, user_id, type) VALUES (?, ?, ?)',
      [id, req.user.id, 'forward']
    )
    res.json({ code: 0, data: { forwarded: true }, message: '已转发' })
  } catch (err) { next(err) }
})

// PUT /office/work-logs/:id - 编辑日志 (对齐 gdqadmin work-logs put/:id; 有互动仅管理员, 无互动创建者可改)
router.put('/office/work-logs/:id', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const { content, recipients, attachments, status } = req.body
    const isAdmin = req.user.role === ROLES.ADMIN || req.user.role === 'admin'
    const [logs] = await pool.query('SELECT user_id FROM work_logs WHERE id = ?', [id])
    if (logs.length === 0) return res.status(404).json({ code: 404, message: '日志不存在' })
    const log = logs[0]
    // 有互动仅管理员可编辑
    const [interactions] = await pool.query(
      `SELECT COUNT(*) as cnt FROM work_log_interactions WHERE log_id = ? AND type IN ('comment','like','dislike','forward')`,
      [id]
    )
    if (interactions[0].cnt > 0 && !isAdmin)
      return res.status(403).json({ code: 403, message: '此日志已有互动，仅管理员可编辑' })
    if (log.user_id !== req.user.id && !isAdmin)
      return res.status(403).json({ code: 403, message: '仅创建者或管理员可编辑' })
    const updates = []
    const params = []
    if (content !== undefined) { updates.push('content = ?'); params.push(typeof content === 'string' ? content : JSON.stringify(content)) }
    if (recipients !== undefined) { updates.push('recipients = ?'); params.push(JSON.stringify(recipients)) }
    if (attachments !== undefined) { updates.push('attachments = ?'); params.push(JSON.stringify(attachments)) }
    if (status !== undefined) { updates.push('status = ?'); params.push(status) }
    if (updates.length === 0) return res.status(400).json({ code: 400, message: '没有要更新的字段' })
    params.push(id)
    await pool.query(`UPDATE work_logs SET ${updates.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: { id: Number(id) }, message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /office/work-logs/:id - 删除日志 (对齐 gdqadmin work-logs delete/:id; 有互动仅管理员, 无互动创建者或管理员)
router.delete('/office/work-logs/:id', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const isAdmin = req.user.role === ROLES.ADMIN || req.user.role === 'admin'
    const [logs] = await pool.query('SELECT user_id FROM work_logs WHERE id = ?', [id])
    if (logs.length === 0) return res.status(404).json({ code: 404, message: '日志不存在' })
    // 有互动仅管理员可删除
    const [interactions] = await pool.query(
      `SELECT COUNT(*) as cnt FROM work_log_interactions WHERE log_id = ? AND type IN ('comment','like','dislike','forward')`,
      [id]
    )
    if (interactions[0].cnt > 0 && !isAdmin)
      return res.status(403).json({ code: 403, message: '此日志已有互动，仅管理员可删除' })
    if (logs[0].user_id !== req.user.id && !isAdmin)
      return res.status(403).json({ code: 403, message: '仅创建者或管理员可删除' })
    await pool.query('DELETE FROM work_logs WHERE id = ?', [id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})


// POST /api/minip/office/work-logs - 提交工作日志（抄主站 work-logs.js, 支持模板字段/收阅人/参与者/附件/审核状态）
//   对齐 gdqadmin WorkLogManage 提交: template_id + content(对象) + recipients + participants + attachments + status
router.post('/office/work-logs', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { template_id, submit_date, date, content, today_work, tomorrow_plan, issues,
            log_type = 'work', attachments, cover_image, images, recipients, participants,
            status = 'submitted', location, gps_lat, gps_lng } = req.body
    if (!content && !today_work && !template_id) return res.status(400).json({ code: 400, message: '日志内容不能为空' })
    // 兼容多种图片字段名: attachments 数组 / images 数组 / cover_image 单图
    let normalizedAttachments = []
    if (Array.isArray(attachments)) normalizedAttachments = attachments
    else if (Array.isArray(images)) normalizedAttachments = images
    else if (cover_image) normalizedAttachments = [{ url: cover_image }]
    // content 支持对象(模板字段)或字符串; 对象则 JSON.stringify 存储(同 work-logs.js)
    const contentStr = (content && typeof content === 'object') ? JSON.stringify(content) : (content || today_work || '')
    const [r] = await pool.query(
      `INSERT INTO work_logs (user_id, template_id, log_type, submit_date, content, today_work, tomorrow_plan, issues,
                              recipients, participants, attachments, location, gps_lat, gps_lng, status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [userId, template_id || null, log_type,
       submit_date || date || new Date().toISOString().slice(0, 10),
       contentStr, today_work || null, tomorrow_plan || null, issues || null,
       JSON.stringify(recipients || []), JSON.stringify(participants || []),
       JSON.stringify(normalizedAttachments), location || null, gps_lat || null, gps_lng || null,
       status]
    )
    res.json({ code: 0, data: { id: r.insertId } })
  } catch (err) {
    console.error('[minip] work-logs create error:', err?.message || err)
    res.status(500).json({ code: 500, message: err?.message || '创建日志失败' })
  }
})

// GET /api/minip/office/tasks - 任务列表 (2026-08-19 三端对齐)
//   scope=mine: 我指派的; scope=assigned: 指派给我的; scope=all: 全部 (admin only); 默认 mine
//   admin 看全部, 普通员工只能看自己被指派或自己指派的
router.get('/office/tasks', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const isAdmin = req.user.role === 'admin'
    const { scope = 'mine', status, page = 1, pageSize = 20 } = req.query
    const offset = (Number(page) - 1) * Number(pageSize)
    let where = 'WHERE 1=1'
    const params = []
    // ── [r6fix-f4] 2026-09-17（波哥口径）：任务 = **开放工单** ──
    //   方向自由（老板⇄主管⇄员工互派），但**可见性按企业隔离**（避免跨租户泄露，同 B-2）。
    //   四档语义：
    //     mine / assigned —— 与原来一致（我创建的 / 分配给我的）
    //     team            —— 我 + 我的下属（作为接收人）+ 我指派的（需 task:read_team 权限）
    //     all             —— **本企业工单池 + 我相关**（我派的 / 派给我的 / 我是附加负责人）
    //                        平台方（global）看全部；企业用户只看本企业；孵化器内部人员看 company_id IS NULL
    const __scF4 = await getCompanyScope(req)
    // [r6fix-f4-fix] 2026-09-17：**不要只信 scope.companyId** —— auth 预载的公司字段为 null 时
    //   getCompanyScope 会回落 incubator（companyId=null），于是 "t.company_id = null" 永不匹配 → 池子查空。
    //   这里直接按用户真实归属取一次，作为企业维度的唯一依据。
    let __myCid = (__scF4.kind === 'company-manage' || __scF4.kind === 'company-self') ? __scF4.companyId : null
    if (__myCid == null && !(await checkPerm(req, 'system:config'))) {
      const [[__meRow]] = await pool.query('SELECT company_id FROM users WHERE id = ?', [userId])
      __myCid = (__meRow && __meRow.company_id != null) ? __meRow.company_id : null
    }
    const __isPlatform = __scF4.kind === 'global' || (await checkPerm(req, 'system:config'))
    const __mineCond = '(t.assigned_to = ? OR EXISTS (SELECT 1 FROM task_assignees ta WHERE ta.task_id = t.id AND ta.user_id = ?))'
    const __relCond = `(t.assigned_by = ? OR ${__mineCond})`
    const pushMe = (k) => { for (let i = 0; i < k; i++) params.push(userId) }
    if (scope === 'mine') {
      where += ' AND t.assigned_by = ?'
      params.push(userId)
    } else if (scope === 'assigned') {
      where += ' AND ' + __mineCond
      pushMe(2)
    } else if (scope === 'team') {
      if (await checkPerm(req, 'task:read_team')) {
        // 主管/有"查看团队任务"权限：我 + 我全部下属（作为接收人）+ 我指派的（任意接收人）
        const subIds = await collectSubordinateIds(pool, userId)
        const ids = [userId, ...subIds]
        const ph = ids.map(() => '?').join(',')
        where += ` AND (t.assigned_to IN (${ph}) OR EXISTS (SELECT 1 FROM task_assignees ta WHERE ta.task_id = t.id AND ta.user_id IN (${ph})) OR t.assigned_by = ?)`
        params.push(...ids, ...ids, userId)
      } else {
        // 无团队权限：退回"分配给我的"（不让 tab 变成死路）
        where += ' AND ' + __mineCond
        pushMe(2)
      }
    } else {
      // all（含未知 scope 兜底）：工单池 + 我相关
      if (__isPlatform) {
        // 平台方（孵化器管理员）：不限制
      } else if (__myCid == null) {
        // 无企业归属（孵化器内部人员）：看平台侧池子（company_id IS NULL）+ 我相关
        where += ` AND (t.company_id IS NULL OR ${__relCond})`
        pushMe(3)
      } else {
        // 企业用户：只看本企业池子 + 我相关（跨企业派给我的单我也能看到）
        where += ` AND (t.company_id = ? OR ${__relCond})`
        params.push(__myCid)
        pushMe(3)
      }
    }
    if (status) {
      where += ' AND t.status = ?'
      params.push(status)
    }
    const [rows] = await pool.query(
      `SELECT t.id, t.title, t.description, t.status, t.priority, t.due_date, t.created_at, t.assigned_to, t.assigned_by,
              t.company_id,   -- [r6fix-f4-fix] 归属企业（隔离口径可核对）
              t.completion_note, t.review_note, t.submitted_at, t.reviewed_at, t.is_new,   -- [task-team] 前端详情面板要用
              u.name as assignee_name, b.name as assigner_name,
              COALESCE((SELECT GROUP_CONCAT(x.name SEPARATOR '、') FROM task_assignees ta2 JOIN users x ON ta2.user_id = x.id WHERE ta2.task_id = t.id), '') AS extra_assignee_names
       FROM tasks t
       LEFT JOIN users u ON t.assigned_to = u.id
       LEFT JOIN users b ON t.assigned_by = b.id
       ${where}
       ORDER BY (CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END), t.due_date ASC LIMIT ? OFFSET ?`,
      [...params, Number(pageSize), offset]
    )
    const [cntRows] = await pool.query(
      `SELECT COUNT(*) as total FROM tasks t ${where}`,
      params
    )
    res.json({ code: 0, data: { list: rows, total: Number(cntRows[0].total) } })
  } catch (err) { next(err) }
})

// POST /api/minip/office/tasks - 创建任务 (2026-08-19 三端对齐)
//   admin 派给任意人; 普通员工只能派给自己或下属
router.post('/office/tasks', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const isAdmin = req.user.role === 'admin'
    const { title, description, priority = 'medium', due_date, assigned_to } = req.body
    if (!title) return res.status(400).json({ code: 400, message: '任务标题不能为空' })
    // 归一化指派对象为数组 (多选支持) — 首个为主负责人(assigned_to), 其余写 task_assignees
    let targetsRaw = Array.isArray(assigned_to)
      ? assigned_to.map(String).filter(v => /^\d+$/.test(v)).map(Number)
      : ((Number.isInteger(Number(assigned_to)) && Number(assigned_to) > 0) ? [Number(assigned_to)] : [userId])
    targetsRaw = [...new Set(targetsRaw)]  // 去重
    if (!targetsRaw.length) targetsRaw = [userId]
    // [r6fix-f4] 2026-09-17：**取消"只能派给自己或下属"** —— 任务是开放工单：
    //   老板可以给主管派、主管也可以反向给老板派、员工之间同理；跨企业（平台方↔入驻企业）也允许。
    //   防滥用靠"可见性隔离"（本企业池 + 我相关），不靠限制派的动作。
    // 插入任务(主负责人) + 附加负责人
    const conn = await pool.getConnection()
    try {
      await conn.beginTransaction()
      // [r6fix-f4] 任务归属企业：按创建者所在**真实企业**（孵化器人员记为 NULL = 平台侧任务）
      //   [r6fix-f4-fix] 直接查真实 company_id —— 不依赖 token 预载（预载为 null 会把企业任务错记成平台侧）
      const __scF4c = await getCompanyScope(req)
      let __taskCid = (__scF4c.kind === 'company-manage' || __scF4c.kind === 'company-self') ? __scF4c.companyId : null
      if (__taskCid == null) {
        const [[__meC]] = await conn.query('SELECT company_id FROM users WHERE id = ?', [userId])
        __taskCid = (__meC && __meC.company_id != null) ? __meC.company_id : null
      }
      const [r] = await conn.query(
        `INSERT INTO tasks (title, description, priority, status, assigned_to, created_by, assigned_by, due_date, is_new, company_id)
         VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, 1, ?)`,
        [title, description || null, priority, targetsRaw[0], userId, userId, due_date || null, __taskCid]
      )
      const extra = targetsRaw.slice(1)
      if (extra.length) {
        await conn.query(
          `INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES ${extra.map(() => '(?, ?)').join(',')}`,
          extra.flatMap(uid => [r.insertId, uid])
        )
      }
      await conn.commit()
      res.json({ code: 0, data: { id: r.insertId } })
      conn.release()
    } catch (e) {
      await conn.rollback().catch(() => {})
      conn.release()
      throw e
    }
  } catch (err) {
    console.error('[minip] tasks create error:', err?.message || err)
    res.status(500).json({ code: 500, message: err?.message || '创建任务失败' })
  }
})

// PUT /api/minip/office/tasks/:id - 更新任务状态/内容
router.put('/office/tasks/:id', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const isAdmin = req.user.role === 'admin'
    const taskId = Number(req.params.id)
    const { status, completion_note, title, description, priority, due_date, assigned_to } = req.body
    const [[task]] = await pool.query('SELECT * FROM tasks WHERE id = ?', [taskId])
    if (!task) return res.status(404).json({ code: 404, message: '任务不存在' })
    // 权限: 主负责人 / 创建者 / 附加负责人 均可操作
    const [[rel]] = await pool.query('SELECT 1 FROM task_assignees WHERE task_id = ? AND user_id = ?', [taskId, userId])
    if (task.assigned_to !== userId && task.assigned_by !== userId && !rel) {
      return res.status(403).json({ code: 403, message: '无权操作此任务' })
    }
    // R5-4 (2026-09-14) 指派变更（可选）：与 POST 完全同一套归一化与「只能派给自己或下属」校验，
    // 避免"创建时管得严、编辑时能偷着派"。targets=null 表示本次不动指派。
    let targets = null
    if (assigned_to !== undefined) {
      targets = Array.isArray(assigned_to)
        ? assigned_to.map(String).filter(v => /^\d+$/.test(v)).map(Number)
        : ((Number.isInteger(Number(assigned_to)) && Number(assigned_to) > 0) ? [Number(assigned_to)] : [])
      targets = [...new Set(targets)]
      if (!targets.length) return res.status(400).json({ code: 400, message: '指派对象无效' })
      if (!isAdmin) {
        const [[sub]] = await pool.query(
          `WITH RECURSIVE subordinate_tree AS (
            SELECT id, supervisor_id FROM users WHERE supervisor_id = ?
            UNION ALL
            SELECT u.id, u.supervisor_id FROM users u
            INNER JOIN subordinate_tree st ON u.supervisor_id = st.id
          ) SELECT COUNT(*) AS cnt FROM subordinate_tree WHERE id IN (?)`,
          [userId, targets]
        )
        const okCnt = (sub?.cnt || 0) + (targets.includes(userId) ? 1 : 0)
        if (okCnt < targets.length) return res.status(403).json({ code: 403, message: '只能派给自己或下属' })
      }
    }
    const updates= []
    const params= []
    if (status !== undefined) { updates.push('status = ?'); params.push(status) }
    if (completion_note !== undefined) { updates.push('completion_note = ?'); params.push(completion_note) }
    if (title) { updates.push('title = ?'); params.push(title) }
    if (description !== undefined) { updates.push('description = ?'); params.push(description) }
    if (priority) { updates.push('priority = ?'); params.push(priority) }
    if (due_date !== undefined) { updates.push('due_date = ?'); params.push(due_date) }
    if (!updates.length && !targets) return res.json({ code: 0, message: 'no changes' })
    // 字段改动与指派重建同事务：不会出现"主负责人换了、附加负责人还是旧的"
    const conn = await pool.getConnection()
    try {
      await conn.beginTransaction()
      if (updates.length) {
        await conn.query(`UPDATE tasks SET ${updates.join(', ')} WHERE id = ?`, [...params, taskId])
      }
      if (targets) {
        await conn.query('UPDATE tasks SET assigned_to = ? WHERE id = ?', [targets[0], taskId])
        await conn.query('DELETE FROM task_assignees WHERE task_id = ?', [taskId])
        const extra = targets.slice(1)
        if (extra.length) {
          await conn.query(
            `INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES ${extra.map(() => '(?, ?)').join(',')}`,
            extra.flatMap(uid => [taskId, uid])
          )
        }
      }
      await conn.commit()
      res.json({ code: 0, message: 'ok' })
    } catch (e) {
      await conn.rollback().catch(() => {})
      throw e
    } finally {
      conn.release()
    }
  } catch (err) { next(err) }
})

// DELETE /api/minip/office/tasks/:id - 删除任务（仅创建者/被分派人）
router.delete('/office/tasks/:id', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const taskId = Number(req.params.id)
    const [[task]] = await pool.query('SELECT * FROM tasks WHERE id = ?', [taskId])
    if (!task) return res.status(404).json({ code: 404, message: '任务不存在' })
    const [[rel]] = await pool.query('SELECT 1 FROM task_assignees WHERE task_id = ? AND user_id = ?', [taskId, userId])
    if (task.assigned_to !== userId && task.assigned_by !== userId && !rel) {
      return res.status(403).json({ code: 403, message: '无权删除' })
    }
    await pool.query('DELETE FROM tasks WHERE id = ?', [taskId])
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

// ════════════════════════════════════════════════════════════════════
// 任务完整版 (抄 gdqadmin 主站 tasks.js, 用 minip auth)
//  统计 / 团队 / 未读红点 / 标记已读 / 提交 / 确认完成 / 驳回 / 审核
// ════════════════════════════════════════════════════════════════════

// GET /office/tasks/stats - 任务统计 (注意: 必须在 /office/tasks/:id 之前定义, 避免 ':id' 吞掉 'stats')
router.get('/office/tasks/stats', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const userRole = req.user.role
    let whereClause = ''
    let params = []
    if (userRole === 'admin') whereClause = 'WHERE 1=1'
    else { whereClause = 'WHERE (assigned_to = ? OR assigned_by = ? OR EXISTS (SELECT 1 FROM task_assignees ta WHERE ta.task_id = tasks.id AND ta.user_id = ?))'; params = [userId, userId, userId] }
    const [[myStats]] = await pool.query(
      `SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
        SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('completed', 'rejected') THEN 1 ELSE 0 END) as overdue
      FROM tasks WHERE assigned_to = ? OR EXISTS (SELECT 1 FROM task_assignees ta WHERE ta.task_id = tasks.id AND ta.user_id = ?)`,
      [userId, userId]
    )
    const [[assignedStats]] = await pool.query(
      `SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
      FROM tasks WHERE assigned_by = ?`,
      [userId]
    )
    res.json({ code: 0, data: { myTasks: myStats, assignedTasks: assignedStats }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /office/tasks/unread-count - 未读任务数 (红点)
router.get('/office/tasks/unread-count', auth, async (req, res, next) => {
  try {
    const [[{ count }]] = await pool.query('SELECT COUNT(*) as count FROM tasks WHERE (assigned_to = ? OR EXISTS (SELECT 1 FROM task_assignees ta WHERE ta.task_id = tasks.id AND ta.user_id = ?)) AND is_new = 1 AND status IN ("pending", "in_progress")', [req.user.id, req.user.id])
    res.json({ code: 0, data: { count } })
  } catch (err) { next(err) }
})

// POST /office/tasks/mark-all-read - 全部标记已读
router.post('/office/tasks/mark-all-read', auth, async (req, res, next) => {
  try {
    await pool.query('UPDATE tasks SET is_new = 0 WHERE (assigned_to = ? OR EXISTS (SELECT 1 FROM task_assignees ta WHERE ta.task_id = tasks.id AND ta.user_id = ?))', [req.user.id, req.user.id])
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /office/tasks/:id/mark-read - 单条标记已读
router.post('/office/tasks/:id/mark-read', auth, async (req, res, next) => {
  try {
    await pool.query('UPDATE tasks SET is_new = 0 WHERE id = ? AND (assigned_to = ? OR EXISTS (SELECT 1 FROM task_assignees ta WHERE ta.task_id = tasks.id AND ta.user_id = ?))', [req.params.id, req.user.id, req.user.id])
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /office/tasks/team - 团队成员 (指派用)
router.get('/office/tasks/team', auth, async (req, res, next) => {
  try {
    const isAdmin = await checkPerm(req, 'system:config')
    const allowedTeam = await checkPerm(req, 'task:read_team')
    if (!isAdmin && !allowedTeam) return res.status(403).json({ code: 403, message: '无权限查看团队' })
    const [rows] = await pool.query('SELECT u.id, u.name, u.department FROM users u ORDER BY u.name ASC')
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// PUT /office/tasks/:id/submit - 被分派人提交完成
router.put('/office/tasks/:id/submit', auth, async (req, res, next) => {
  try {
    const taskId = req.params.id
    const { completion_notes, completion_note, attachments } = req.body
    const finalNote = completion_notes || completion_note || ''
    const [[task]] = await pool.query('SELECT assigned_to, status FROM tasks WHERE id = ?', [taskId])
    if (!task) return res.status(404).json({ code: 404, message: '任务不存在' })
    const [[rel]] = await pool.query('SELECT 1 FROM task_assignees WHERE task_id = ? AND user_id = ?', [taskId, req.user.id])
    if (task.assigned_to !== req.user.id && !rel) return res.status(403).json({ code: 403, message: '只能提交指派给自己的任务' })
    if (task.status === 'completed') return res.status(400).json({ code: 400, message: '任务已完成' })
    if (task.status === 'submitted') return res.status(400).json({ code: 400, message: '任务已提交，等待审核' })
    await pool.query("UPDATE tasks SET status = 'submitted', completion_note = ?, submitted_at = NOW() WHERE id = ?", [finalNote || null, taskId])
    if (attachments) {
      let attList = []
      try { attList = typeof attachments === 'string' ? JSON.parse(attachments) : attachments } catch (e) { attList = [] }
      if (Array.isArray(attList) && attList.length) {
        const values = attList.map(url => [taskId, url, url.split('/').pop(), req.user.id])
        await pool.query('INSERT INTO task_attachments (task_id, file_path, file_name, uploaded_by) VALUES ?', [values])
      }
    }
    res.json({ code: 0, data: null, message: '任务已提交，等待审核' })
  } catch (err) { next(err) }
})

// PUT /office/tasks/:id/complete - 指派人确认完成
router.put('/office/tasks/:id/complete', auth, async (req, res, next) => {
  try {
    const taskId = req.params.id
    const { review_note } = req.body
    const [[task]] = await pool.query('SELECT assigned_by, status FROM tasks WHERE id = ?', [taskId])
    if (!task) return res.status(404).json({ code: 404, message: '任务不存在' })
    if (task.assigned_by !== req.user.id && !(await checkPerm(req, 'system:config'))) return res.status(403).json({ code: 403, message: '只能审核自己指派的任务' })
    if (task.status !== 'submitted') return res.status(400).json({ code: 400, message: '只能审核已提交的任务' })
    await pool.query("UPDATE tasks SET status = 'completed', review_note = ?, completed_at = NOW() WHERE id = ?", [review_note || null, taskId])
    res.json({ code: 0, data: null, message: '任务已确认完成' })
  } catch (err) { next(err) }
})

// PUT /office/tasks/:id/reject - 指派人驳回
router.put('/office/tasks/:id/reject', auth, async (req, res, next) => {
  try {
    const taskId = req.params.id
    const { review_note } = req.body
    if (!review_note) return res.status(400).json({ code: 400, message: '请填写驳回原因' })
    const [[task]] = await pool.query('SELECT assigned_by, status FROM tasks WHERE id = ?', [taskId])
    if (!task) return res.status(404).json({ code: 404, message: '任务不存在' })
    if (task.assigned_by !== req.user.id && !(await checkPerm(req, 'system:config'))) return res.status(403).json({ code: 403, message: '只能驳回自己指派的任务' })
    if (task.status !== 'submitted') return res.status(400).json({ code: 400, message: '只能驳回已提交的任务' })
    await pool.query("UPDATE tasks SET status = 'rejected', review_note = ? WHERE id = ?", [review_note, taskId])
    res.json({ code: 0, data: null, message: '任务已驳回' })
  } catch (err) { next(err) }
})

// PUT /office/tasks/:id/review - 审核 (approve/reject)
router.put('/office/tasks/:id/review', auth, async (req, res, next) => {
  try {
    const taskId = req.params.id
    const { action, review_notes } = req.body
    if (!['approve', 'reject'].includes(action)) return res.status(400).json({ code: 400, message: '无效审核操作' })
    const [[task]] = await pool.query('SELECT * FROM tasks WHERE id = ?', [taskId])
    if (!task) return res.status(404).json({ code: 404, message: '任务不存在' })
    if (task.status !== 'submitted') return res.status(400).json({ code: 400, message: '只能审核已提交的任务' })
    if (task.assigned_by !== req.user.id && !(await checkPerm(req, 'system:config'))) return res.status(403).json({ code: 403, message: '无权审核此任务' })
    const newStatus = action === 'approve' ? 'completed' : 'rejected'
    await pool.query('UPDATE tasks SET status = ?, review_note = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?', [newStatus, review_notes || null, req.user.id, new Date(), taskId])
    res.json({ code: 0, data: { id: Number(taskId), status: newStatus }, message: newStatus === 'completed' ? '任务已通过' : '任务已驳回' })
  } catch (err) { next(err) }
})
// GET /api/minip/office/tasks/:id - 任务详情（R5-4 2026-09-14 新增）
//   task-form 编辑模式读它回填表单。原先两端都没有这个路由 → 编辑页必然加载失败，
//   且失败后空表单可提交 → 会把原任务的描述/截止/优先级覆盖成空。
//   注意：必须定义在 /office/tasks/stats、/unread-count、/team 之后，否则 ':id' 会把它们吞掉。
//   归属口径与 PUT 一致；无权限与不存在统一返回 404（不给 403/404 差异枚举 id）。
router.get('/office/tasks/:id', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const taskId = Number(req.params.id)
    if (!Number.isFinite(taskId)) return res.status(404).json({ code: 404, message: '任务不存在' })
    const [[task]] = await pool.query(
      `SELECT t.id, t.title, t.description, t.status, t.priority,
              DATE_FORMAT(t.due_date, '%Y-%m-%d') AS due_date,
              t.assigned_to, t.assigned_by, t.created_at,
              u.name AS assignee_name, b.name AS assigner_name
         FROM tasks t
         LEFT JOIN users u ON t.assigned_to = u.id
         LEFT JOIN users b ON t.assigned_by = b.id
        WHERE t.id = ?`,
      [taskId]
    )
    if (!task) return res.status(404).json({ code: 404, message: '任务不存在' })
    const [[rel]] = await pool.query('SELECT 1 FROM task_assignees WHERE task_id = ? AND user_id = ?', [taskId, userId])
    const isAdmin = req.user.role === 'admin'
    if (!isAdmin && task.assigned_to !== userId && task.assigned_by !== userId && !rel) {
      return res.status(404).json({ code: 404, message: '任务不存在' })
    }
    // 附加负责人：给前端回填用（列表接口只给名字，编辑需要 id）
    const [extras] = await pool.query(
      `SELECT ta.user_id, u.name FROM task_assignees ta LEFT JOIN users u ON ta.user_id = u.id WHERE ta.task_id = ?`,
      [taskId]
    )
    res.json({
      code: 0,
      data: {
        id: task.id,
        title: task.title,
        description: task.description,
        status: task.status,
        priority: task.priority,
        due_date: task.due_date || null,
        assigned_to: task.assigned_to,
        assigned_by: task.assigned_by,
        assignee_name: task.assignee_name || null,
        assigner_name: task.assigner_name || null,
        created_at: task.created_at,
        extra_assignee_ids: extras.map(e => e.user_id),
        extra_assignee_names: extras.map(e => e.name).filter(Boolean).join('、')
      }
    })
  } catch (err) { next(err) }
})

// 波哥原话: "把这个内容融合到 hatch.gdqshop.cn/minip"
// 这些 routes 是占位实现, 让前端不报错. 完整业务逻辑后续迭代.
// ════════════════════════════════════════════════════════════════════════

// GET /api/minip/butler/tickets - 管家工单列表 (mock)
router.get('/butler/tickets', auth, async (req, res) => {
  return res.json({
    code: 0,
    data: { list: [] }
  })
})

// POST /api/minip/butler/tickets - 创建工单 (mock, 实际写库)
router.post('/butler/tickets', auth, async (req, res) => {
  const { service_key, service_label, time_slot, description } = req.body
  if (!service_key || !time_slot) {
    return res.status(400).json({ code: 400, message: '服务类型和时间段必填' })
  }
  return res.json({
    code: 0,
    data: {
      id: Date.now(),
      service_key, service_label, time_slot, description,
      status: 'pending',
      status_label: '待接单',
      created_at: new Date().toISOString()
    },
    message: '工单已提交'
  })
})

// GET /api/minip/notifications/unread - 未读通知（2026-09-15 江小鱼：由 mock 改真查）
// 背景：写入早已是真的（入驻通过 / 报销批准），但读取一直是 mock（不查表）+ 前端无入口
//       → 通知"只写不读"，等于白写。这里补上真实读取链路。
router.get('/notifications/unread', auth, async (req, res, next) => {
  try {
    const [[{ cnt }]] = await pool.query(
      'SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0',
      [req.user.id]
    )
    const [rows] = await pool.query(
      `SELECT id, type, title, content, article_id, is_read, created_at
         FROM notifications
        WHERE user_id = ? AND is_read = 0
        ORDER BY created_at DESC, id DESC
        LIMIT 20`,
      [req.user.id]
    )
    res.json({ code: 0, data: { count: Number(cnt) || 0, list: rows, latest: rows[0] || null } })
  } catch (err) { next(err) }
})

// GET /api/minip/notifications/history - 通知历史（2026-09-15 由 mock 改真查；含已读）
router.get('/notifications/history', auth, async (req, res, next) => {
  try {
    const limit = Math.min(Number(req.query.limit) || 100, 200)
    const [rows] = await pool.query(
      `SELECT id, type, title, content, article_id, is_read, read_at, created_at
         FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT ?`,
      [req.user.id, limit]
    )
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

// POST /api/minip/notifications/read-all - 全部标记已读（必须在 /:id/read 之前不冲突：段数不同）
router.post('/notifications/read-all', auth, async (req, res, next) => {
  try {
    const [r] = await pool.query(
      'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0',
      [req.user.id]
    )
    res.json({ code: 0, data: { affected: r.affectedRows || 0 }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/minip/notifications/:id/read - 标记单条已读（只能标自己的）
router.post('/notifications/:id/read', auth, async (req, res, next) => {
  try {
    const [r] = await pool.query(
      'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ? AND is_read = 0',
      [req.params.id, req.user.id]
    )
    res.json({ code: 0, data: { affected: r.affectedRows || 0 }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/minip/notifications/send - 发送通知（2026-09-15 江小鱼：由 mock 改真）
// 之前：只 console.log，返回的 recipient_count 是编的（all→128）
// 现在：**真的落库、真的返回接收人数**
//   落库口径 = 点对点 fan-out 进 `notifications` 表（每收件人一行）。
//   【2026-09-15 更正】这里原先写"不用广播型 `hqh5_notifications`…"，实查后发现该说法不准确：
//   广播模型**早已归档为死代码** —— 仅存在于 `routes/_archived_hqh5-20260912/hqh5.js`，
//   两端 index.js 零引用（未挂载），`hqh5_notifications` / `hqh5_notification_reads` 各 0 行，桌面后台无通知页。
//   所以并不存在"两套模型并存"，本接口只是选了 `notifications` 这张表而已。
// 目标：`target_type='all'` → 全部在职用户；`'company'` → `target_ids` 各企业的在职用户。
router.post('/notifications/send', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const { title, content, target_type, target_ids, article_id } = req.body || {}
    const t = String(title || '').trim()
    const c = String(content || '').trim()
    if (!t) return res.status(400).json({ code: 400, message: '请填写通知标题' })
    if (t.length > 200) return res.status(400).json({ code: 400, message: '标题不能超过 200 字' })
    if (!c) return res.status(400).json({ code: 400, message: '请填写通知内容' })

    // 关联文章（选填，2026-09-15 江小鱼）：通知可指向一篇文章，收件人点通知直接进正文。
    // 只允许关联「已发布」：详情页走公开通道 /api/minip/news/:id，该通道只认 status='published'，
    // 关联草稿等于点了跳 404。
    let aid = null
    if (article_id !== undefined && article_id !== null && String(article_id).trim() !== '') {
      const n = parseInt(article_id, 10)
      if (!(n > 0)) return res.status(400).json({ code: 400, message: '关联文章 ID 不合法' })
      const [ar] = await pool.query(
        `SELECT id FROM articles WHERE id = ? AND status = 'published'`,
        [n]
      )
      if (!ar.length) return res.status(400).json({ code: 400, message: '关联的文章不存在或未发布' })
      aid = n
    }

    let ids = []
    if (target_type === 'company') {
      const arr = Array.isArray(target_ids) ? target_ids.map((x) => parseInt(x, 10)).filter((x) => x > 0) : []
      if (!arr.length) return res.status(400).json({ code: 400, message: '请选择推送对象' })
      const ph = arr.map(() => '?').join(',')
      const [rows] = await pool.query(
        `SELECT id FROM users WHERE company_id IN (${ph}) AND status = 'active' ORDER BY id`,
        arr
      )
      ids = rows.map((r) => r.id)
    } else {
      const [rows] = await pool.query(`SELECT id FROM users WHERE status = 'active' ORDER BY id`)
      ids = rows.map((r) => r.id)
    }
    if (!ids.length) return res.json({ code: 0, data: { recipient_count: 0 }, message: '没有可接收的用户' })

    const MAX = 5000
    if (ids.length > MAX) ids = ids.slice(0, MAX)
    const values = ids.map(() => '(?, ?, ?, ?, ?, NOW())').join(', ')
    const params = []
    for (const uid of ids) params.push(uid, 'admin_broadcast', t, c, aid)
    await pool.query(
      `INSERT INTO notifications (user_id, type, title, content, article_id, created_at) VALUES ${values}`,
      params
    )
    res.json({ code: 0, data: { recipient_count: ids.length }, message: '已发送' })
  } catch (err) { next(err) }
})

// GET /api/minip/notifications/sent - 已发送通知记录（管理端，2026-09-15 江小鱼新增）
// 为什么需要：send 是**点对点 fan-out**（每个收件人一行）——运营发 1 条给 128 人 = 表里 128 行。
//   直接列会「一条通知刷 N 行」，看不出「我到底发过哪几次」。
//   → 按 (title, created_at) 秒级聚合，还原成「一次发送 = 一条记录」。
//   秒级够用：同一次 send 是同一条 INSERT 语句，created_at 完全相同；不同次发送不可能同秒同标题。
// recipient_count = 实际送达人数；read_count = 其中已读人数。
router.get('/notifications/sent', auth, requireRole('admin'), async (req, res, next) => {
  try {
    const limit = Math.min(Number(req.query.limit) || 50, 200)
    const [rows] = await pool.query(
      `SELECT MIN(id) AS id, title, MAX(content) AS content, MAX(article_id) AS article_id,
              created_at,
              COUNT(*) AS recipient_count,
              COUNT(CASE WHEN is_read = 1 THEN 1 END) AS read_count
         FROM notifications
        WHERE type = 'admin_broadcast'
        GROUP BY title, created_at
        ORDER BY created_at DESC, id DESC
        LIMIT ?`,
      [limit]
    )
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

// GET /api/minip/permissions/me —— 我的权限点（[r6fix-b4-perms] 2026-09-17）
//   为什么需要：前端要"按权限决定入口显隐"（波哥口径），就必须能问后端"我有哪些权限"，
//   而不是在前端写死角色名单。admin/superuser 语义与 checkPerm 一致（admin 天然全权限）。
router.get('/permissions/me', auth, async (req, res, next) => {
  try {
    if (req.user.role === 'admin') {
      const [rows] = await pool.query('SELECT name FROM rbac_permissions')
      return res.json({ code: 0, data: { role: 'admin', is_super: true, permissions: rows.map(r => r.name) } })
    }
    const perms = new Set()
    if (req.user.permissions) {
      try { (JSON.parse(req.user.permissions) || []).forEach(p => perms.add(p)) } catch (e) { /* 个人权限 JSON 坏 → 忽略 */ }
    }
    const [rp] = await pool.query(
      `SELECT p.name FROM rbac_permissions p
       JOIN rbac_role_permissions rp ON p.id = rp.permission_id
       JOIN rbac_roles r ON r.id = rp.role_id
       WHERE r.name = ?`, [req.user.role])
    rp.forEach(r => perms.add(r.name))
    res.json({ code: 0, data: { role: req.user.role, is_super: req.user.role === 'superuser', permissions: [...perms] } })
  } catch (err) { next(err) }
})

// GET /api/minip/enterprise/list - 企业列表（2026-09-15 江小鱼：由 mock 改真）
// 用途：「通知推送」页的**推送对象**要从这里选（原前端是自己敲企业名、后端返回写死假数据）。
router.get('/enterprise/list', auth, async (req, res, next) => {
  try {
    // [r6fix-b2] 2026-09-17：企业名单按角色收敛（此前任何登录用户 → 全量企业名 = 租户泄露）。
    //   admin/superuser（global）= 全量；enterprise-admin（company-manage）= 仅本企业；其余 403。
    const __scope = await getCompanyScope(req)
    if (__scope.kind === 'global') {
      const [rows] = await pool.query(
        `SELECT id, name, short_name, status FROM companies WHERE status = 'active' ORDER BY id`
      )
      return res.json({ code: 0, data: { list: rows } })
    }
    if (__scope.kind === 'company-manage' && __scope.companyId != null) {
      const [rows] = await pool.query(
        `SELECT id, name, short_name, status FROM companies WHERE status = 'active' AND id = ? ORDER BY id`,
        [__scope.companyId]
      )
      return res.json({ code: 0, data: { list: rows } })
    }
    return res.status(403).json({ code: 403, message: '无权查看企业名单' })
  } catch (err) { next(err) }
})

// GET /api/minip/rental/credit-score - 信用分 (mock)
router.get('/rental/credit-score', auth, async (req, res) => {
  return res.json({
    code: 0,
    data: {
      score: 120,
      level: 'excellent',
      advance_days: 7,
      max_active: 3,
      max_per_day: 1
    }
  })
})

// GET /api/minip/rental/rooms - 会议室列表 (mock)
router.get('/rental/rooms', async (req, res) => {
  return res.json({
    code: 0,
    data: {
      list: [
        { id: 1, name: '小会议室 04-1301', capacity: 4, location: '4楼东区' },
        { id: 2, name: '大会议室 04-1302', capacity: 12, location: '4楼西区' },
        { id: 3, name: '路演厅 02-2101', capacity: 50, location: '2楼中庭' },
        { id: 4, name: '大堂接待区', capacity: 20, location: '1楼大堂' },
        { id: 5, name: 'VIP 接待室 02-2201', capacity: 8, location: '2楼北侧' }
      ]
    }
  })
})

// POST /api/minip/rental/bookings - 创建预约 (mock)
router.post('/rental/bookings', auth, async (req, res) => {
  return res.json({
    code: 0,
    data: { id: Date.now(), ...req.body, status: 'confirmed' },
    message: '预约成功'
  })
})

// GET /api/minip/rental/my-bookings - 我的预约 (mock)
router.get('/rental/my-bookings', auth, async (req, res) => {
  return res.json({ code: 0, data: { list: [] } })
})

// ============================================================
// OA 审批（整合 uni-app approval-list, 2026-08-19）
// ============================================================
//
// mock 数据：3 条审批，覆盖 pending/approved/rejected 三种状态
// 真实接 DB 后从 oa_submissions + oa_steps 联合查
const APPROVAL_MOCK = [
  {
    id: 1,
    flowId: 'leave',
    flowName: '请假申请',
    applicant: '李明',
    submitTime: Date.now() - 1000 * 60 * 60 * 2,    // 2 小时前
    status: 'pending',
    formData: { leaveType: '年假', days: 2, reason: '家中有事' },
    nodes: [
      { name: '直属上级', state: 'current' },
      { name: '部门主管', state: 'pending' }
    ]
  },
  {
    id: 2,
    flowId: 'reimburse',
    flowName: '报销申请',
    applicant: '王芳',
    submitTime: Date.now() - 1000 * 60 * 60 * 24,   // 1 天前
    status: 'pending',
    formData: { amount: 1280, reason: '客户接待' },
    nodes: [
      { name: '直属上级', state: 'approved' },
      { name: '部门主管', state: 'current' }
    ]
  },
  {
    id: 3,
    flowId: 'leave',
    flowName: '请假申请',
    applicant: '张丽',
    submitTime: Date.now() - 1000 * 60 * 60 * 24 * 3,
    status: 'approved',
    formData: { leaveType: '病假', days: 1, reason: '感冒发烧' },
    nodes: [
      { name: '直属上级', state: 'approved', completedAt: Date.now() - 1000 * 60 * 60 * 24 * 3 + 1000 * 3600 },
      { name: '部门主管', state: 'approved', completedAt: Date.now() - 1000 * 60 * 60 * 24 * 3 + 1000 * 3600 * 2 }
    ]
  },
  {
    id: 4,
    flowId: 'overtime',
    flowName: '加班申请',
    applicant: '陈强',
    submitTime: Date.now() - 1000 * 60 * 60 * 24 * 5,
    status: 'rejected',
    formData: { otType: '工作日', minutes: 120, reason: '项目上线' },
    nodes: [
      { name: '直属上级', state: 'rejected', completedAt: Date.now() - 1000 * 60 * 60 * 24 * 5 + 1000 * 3600, message: '未提前申请' }
    ]
  }
]

// GET /api/minip/oa/approvals - 拉审批列表（按 status 过滤）
//   status: pending | approved | rejected | all（默认 all）
router.get('/oa/approvals', auth, async (req, res, next) => {
  try {
    const status = (req.query.status || 'all').toLowerCase()
    let list = APPROVAL_MOCK.slice()
    if (status !== 'all') list = list.filter(a => a.status === status)
    // 按 submitTime 倒序
    list.sort((a, b) => b.submitTime - a.submitTime)
    return res.json({ code: 0, data: { list, total: list.length } })
  } catch (err) { next(err) }
})

// GET /api/minip/oa/approvals/:id - 单条审批详情
router.get('/oa/approvals/:id', auth, async (req, res, next) => {
  try {
    const id = parseInt(req.params.id)
    const item = APPROVAL_MOCK.find(a => a.id === id)
    if (!item) return res.status(404).json({ code: 404, message: '审批记录不存在' })
    return res.json({ code: 0, data: item })
  } catch (err) { next(err) }
})

// POST /api/minip/oa/approvals/:id/approve - 通过审批
router.post('/oa/approvals/:id/approve', auth, async (req, res, next) => {
  try {
    const id = parseInt(req.params.id)
    const item = APPROVAL_MOCK.find(a => a.id === id)
    if (!item) return res.status(404).json({ code: 404, message: '审批记录不存在' })
    if (item.status !== 'pending') return res.status(409).json({ code: 409, message: '该申请已处理' })
    item.status = 'approved'
    item.approvedAt = new Date().toISOString()
    item.approvedBy = req.user?.id || null
    return res.json({ code: 0, data: item, message: '已通过' })
  } catch (err) { next(err) }
})

// POST /api/minip/oa/approvals/:id/reject - 驳回审批
router.post('/oa/approvals/:id/reject', auth, async (req, res, next) => {
  try {
    const id = parseInt(req.params.id)
    const reason = req.body?.reason || ''
    const item = APPROVAL_MOCK.find(a => a.id === id)
    if (!item) return res.status(404).json({ code: 404, message: '审批记录不存在' })
    if (item.status !== 'pending') return res.status(409).json({ code: 409, message: '该申请已处理' })
    item.status = 'rejected'
    item.rejectedAt = new Date().toISOString()
    item.rejectedBy = req.user?.id || null
    item.rejectReason = reason
    return res.json({ code: 0, data: item, message: '已驳回' })
  } catch (err) { next(err) }
})

// GET /api/minip/oa/categories - OA 流程分类（uni-app oa-flow-list 用）
router.get('/oa/categories', async (req, res, next) => {
  try {
    // 内置 13 大类（来自 oa-categories.js 的 oaCategories）
    // 这里只返回分类摘要，详细 fields 由前端 oa-categories.js 提供
    return res.json({
      code: 0,
      data: {
        list: [
          { id: 'hr', name: '人事类', desc: '员工入离调转与考勤，最高频；内部用 + 平台标准模板。' },
          { id: 'finance', name: '财务类', desc: '报销、付款、用款、发票管理 — 与财务系统对接。' },
          { id: 'business', name: '业务类', desc: '招商、立项、合同、订单 — 核心业务流转。' },
          { id: 'admin', name: '行政类', desc: '物资采购、车辆、用印、场地 — 后勤保障。' },
          { id: 'travel', name: '差旅类', desc: '出差申请、行程报备、差旅报销 — 简化出行。' },
          { id: 'asset', name: '资产类', desc: '资产领用、调拨、报废 — 实物资产管理。' },
          { id: 'project', name: '项目类', desc: '项目立项、变更、验收 — 项目全周期。' },
          { id: 'vendor', name: '供应商类', desc: '供应商准入、考核、退出 — 供应链管理。' },
          { id: 'training', name: '培训类', desc: '培训申请、签到、效果评估 — 人才培养。' },
          { id: 'meeting', name: '会议类', desc: '会议申请、纪要、决议执行 — 会议效率。' },
          { id: 'event', name: '活动类', desc: '活动申请、签到、复盘 — 内外活动管理。' },
          { id: 'customer', name: '客户类', desc: '客户准入、跟进、投诉 — 客户关系。' },
          { id: 'other', name: '其它类', desc: '自定义流程与扩展。' }
        ]
      }
    })
  } catch (err) { next(err) }
})

// ============================================================
// 会议邀请（整合 uni-app accept-invite, 2026-08-19）
// ============================================================
//
// mock 数据：3 个邀请条目，按 id 路由分发到不同 mock
// 真实接 DB 后从 invite 表 + meeting 表 join 读
const INVITE_MOCK = {
  'inv_001': {
    invite_id: 'inv_001',
    inviter: '张经理',
    inviter_id: 'u_001',
    time: '2026年4月14日 14:00',
    duration: '1小时',
    location: '中会议室 04-1302',
    participantsLead: '张经理',
    participantsRest: '、李*明、赵*琳、刘*强（我）',
    title: '项目周会',
    status: 'pending'
  },
  'inv_002': {
    invite_id: 'inv_002',
    inviter: '王总',
    inviter_id: 'u_002',
    time: '2026年4月15日 10:00',
    duration: '2小时',
    location: 'VIP 接待室 02-2201',
    participantsLead: '王总',
    participantsRest: '、张经理、刘*强（我）',
    title: 'Q2 战略评审',
    status: 'pending'
  },
  'inv_003': {
    invite_id: 'inv_003',
    inviter: '陈运营',
    inviter_id: 'u_003',
    time: '2026年4月16日 16:00',
    duration: '30分钟',
    location: '小会议室 04-1301',
    participantsLead: '陈运营',
    participantsRest: '（我）',
    title: '场地预约协调',
    status: 'pending'
  }
}

// GET /api/minip/invite/:id - 获取邀请详情
router.get('/invite/:id', async (req, res, next) => {
  try {
    const id = req.params.id
    const data = INVITE_MOCK[id]
    if (!data) return res.status(404).json({ code: 404, message: '邀请不存在或已过期' })
    return res.json({ code: 0, data: { meeting: data } })
  } catch (err) { next(err) }
})

// POST /api/minip/invite/:id/accept - 接受邀请
router.post('/invite/:id/accept', auth, async (req, res, next) => {
  try {
    const id = req.params.id
    const data = INVITE_MOCK[id]
    if (!data) return res.status(404).json({ code: 404, message: '邀请不存在或已过期' })
    if (data.status !== 'pending') {
      return res.status(409).json({ code: 409, message: '该邀请已被处理' })
    }
    // mock: 标记为 accepted（真实接 DB 后写 invite_responses 表）
    data.status = 'accepted'
    data.accepted_at = new Date().toISOString()
    data.accepted_by = req.user?.id || null
    return res.json({ code: 0, data, message: '已接受邀请' })
  } catch (err) { next(err) }
})

// POST /api/minip/invite/:id/decline - 拒绝邀请
router.post('/invite/:id/decline', auth, async (req, res, next) => {
  try {
    const id = req.params.id
    const data = INVITE_MOCK[id]
    if (!data) return res.status(404).json({ code: 404, message: '邀请不存在或已过期' })
    if (data.status !== 'pending') {
      return res.status(409).json({ code: 409, message: '该邀请已被处理' })
    }
    data.status = 'declined'
    data.declined_at = new Date().toISOString()
    data.declined_by = req.user?.id || null
    return res.json({ code: 0, data, message: '已拒绝邀请' })
  } catch (err) { next(err) }
})

// ============================================================
// "我的"页面 4 大模块 (favorites / reviews / addresses / orders)
// 2026-08-19 新增 - 前端 me/ 全部接 API
// 表用 favorites/reviews/addresses/orders（自动创建，不存在则空）
// ============================================================

// GET /api/minip/me/favorites - 我的收藏
router.get('/me/favorites', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [rows] = await pool.query(
      `SELECT id, target_type, target_id, title, subtitle, cover, created_at
         FROM user_favorites WHERE user_id = ? ORDER BY created_at DESC LIMIT 100`,
      [userId]
    ).catch(() => [[]])
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

// POST /api/minip/me/favorites - 添加收藏
router.post('/me/favorites', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { target_type = 'product', target_id, title = '', subtitle = '', cover = '' } = req.body || {}
    if (!target_id) return res.status(400).json({ code: 400, message: 'target_id 必填' })
    await pool.query(
      `INSERT IGNORE INTO user_favorites (user_id, target_type, target_id, title, subtitle, cover, created_at)
       VALUES (?, ?, ?, ?, ?, ?, NOW())`,
      [userId, target_type, target_id, title, subtitle, cover]
    ).catch(async () => {
      // 表不存在则自动创建
      await pool.query(`
        CREATE TABLE IF NOT EXISTS user_favorites (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          target_type VARCHAR(32) DEFAULT 'product',
          target_id VARCHAR(64) NOT NULL,
          title VARCHAR(255) DEFAULT '',
          subtitle VARCHAR(255) DEFAULT '',
          cover VARCHAR(512) DEFAULT '',
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_fav (user_id, target_type, target_id),
          INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
      `)
      await pool.query(
        `INSERT IGNORE INTO user_favorites (user_id, target_type, target_id, title, subtitle, cover, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())`,
        [userId, target_type, target_id, title, subtitle, cover]
      )
    })
    res.json({ code: 0, message: '已收藏' })
  } catch (err) { next(err) }
})

// DELETE /api/minip/me/favorites/:id - 取消收藏
router.delete('/me/favorites/:id', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    await pool.query('DELETE FROM user_favorites WHERE id = ? AND user_id = ?', [req.params.id, userId])
    res.json({ code: 0, message: '已取消收藏' })
  } catch (err) { next(err) }
})

// GET /api/minip/me/reviews - 我的评价
router.get('/me/reviews', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [rows] = await pool.query(
      `SELECT id, target_type, target_id, target_name, rating, content, images, created_at
         FROM user_reviews WHERE user_id = ? ORDER BY created_at DESC LIMIT 50`,
      [userId]
    ).catch(() => [[]])
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

// GET /api/minip/me/addresses - 收货地址列表
router.get('/me/addresses', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [rows] = await pool.query(
      `SELECT id, name, phone, province, city, district, detail, is_default, created_at
         FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 50`,
      [userId]
    ).catch(() => [[]])
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

// POST /api/minip/me/addresses - 新增地址
router.post('/me/addresses', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { name, phone, province = '', city = '', district = '', detail = '', is_default = 0 } = req.body || {}
    if (!name || !phone) return res.status(400).json({ code: 400, message: '姓名手机号必填' })
    // 自动建表
    await pool.query(`
      CREATE TABLE IF NOT EXISTS user_addresses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(64) NOT NULL,
        phone VARCHAR(32) NOT NULL,
        province VARCHAR(64) DEFAULT '',
        city VARCHAR(64) DEFAULT '',
        district VARCHAR(64) DEFAULT '',
        detail VARCHAR(255) DEFAULT '',
        is_default TINYINT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    `).catch(() => {})
    if (is_default) await pool.query('UPDATE user_addresses SET is_default = 0 WHERE user_id = ?', [userId])
    const [r] = await pool.query(
      `INSERT INTO user_addresses (user_id, name, phone, province, city, district, detail, is_default)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [userId, name, phone, province, city, district, detail, is_default ? 1 : 0]
    )
    res.json({ code: 0, data: { id: r.insertId }, message: '地址已保存' })
  } catch (err) { next(err) }
})

// DELETE /api/minip/me/addresses/:id - 删除地址
router.delete('/me/addresses/:id', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    await pool.query('DELETE FROM user_addresses WHERE id = ? AND user_id = ?', [req.params.id, userId])
    res.json({ code: 0, message: '已删除' })
  } catch (err) { next(err) }
})

// PUT /api/minip/me/addresses/:id - 更新地址
router.put('/me/addresses/:id', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { name, phone, province, city, district, detail, is_default } = req.body || {}
    if (is_default) await pool.query('UPDATE user_addresses SET is_default = 0 WHERE user_id = ?', [userId])
    await pool.query(
      `UPDATE user_addresses SET
         name = COALESCE(?, name),
         phone = COALESCE(?, phone),
         province = COALESCE(?, province),
         city = COALESCE(?, city),
         district = COALESCE(?, district),
         detail = COALESCE(?, detail),
         is_default = COALESCE(?, is_default)
       WHERE id = ? AND user_id = ?`,
      [name, phone, province, city, district, detail, is_default != null ? (is_default ? 1 : 0) : null, req.params.id, userId]
    )
    res.json({ code: 0, message: '已更新' })
  } catch (err) { next(err) }
})

// PUT /api/minip/me/profile - 更新个人资料（name/email）
router.put('/me/profile', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { name, email } = req.body || {}
    if (name) await pool.query('UPDATE users SET name = ? WHERE id = ?', [name, userId])
    if (email) await pool.query('UPDATE users SET email = ? WHERE id = ?', [email, userId])
    const [[u]] = await pool.query(
      `SELECT id, name, email, phone, role, user_type, department, points, member_level
         FROM users WHERE id = ?`, [userId])
    res.json({ code: 0, data: u, message: '资料已更新' })
  } catch (err) { next(err) }
})

// POST /api/minip/me/feedback - 提交意见反馈
router.post('/me/feedback', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { type = 'bug', content = '', contact = '' } = req.body || {}
    if (!content.trim()) return res.status(400).json({ code: 400, message: '反馈内容不能为空' })
    await pool.query(`
      CREATE TABLE IF NOT EXISTS user_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(32) DEFAULT 'bug',
        content TEXT,
        contact VARCHAR(128) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    `).catch(() => {})
    await pool.query(
      `INSERT INTO user_feedback (user_id, type, content, contact) VALUES (?, ?, ?, ?)`,
      [userId, type, content, contact]
    )
    res.json({ code: 0, message: '感谢你的反馈' })
  } catch (err) { next(err) }
})

// ============================================================
// "我的"页面 4 大模块 (favorites / reviews / addresses / orders / feedback)
// 2026-08-19 新增 - 前端 me/ 全部接 API
// 表用 user_favorites / user_reviews / user_addresses / minip_orders / user_feedback（自动创建）
// ============================================================

// --- 意见反馈 ---
router.get('/me/feedback', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT f.*, u.name as username, u.phone
       FROM user_feedback f
       LEFT JOIN users u ON f.user_id = u.id
       ORDER BY f.created_at DESC LIMIT 200`
    )
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

router.post('/me/feedback', auth, async (req, res, next) => {
  try {
    const { type, content, contact } = req.body
    if (!content) return res.status(400).json({ code: 400, message: '内容不能为空' })
    await pool.query(
      `INSERT INTO user_feedback (user_id, type, content, contact, created_at)
       VALUES (?, ?, ?, ?, NOW())`,
      [req.user.id, type || 'bug', content, contact || null]
    )
    res.json({ code: 0, message: '反馈已提交' })
  } catch (err) { next(err) }
})

router.delete('/me/feedback/:id', auth, async (req, res, next) => {
  try {
    await pool.query('DELETE FROM user_feedback WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '已删除' })
  } catch (err) { next(err) }
})

// --- 收藏 ---
router.get('/me/favorites', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT f.*, u.name as username, u.phone
       FROM user_favorites f
       LEFT JOIN users u ON f.user_id = u.id
       ORDER BY f.created_at DESC LIMIT 200`
    )
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

router.post('/me/favorites', auth, async (req, res, next) => {
  try {
    const { title, target_type, target_id, image_url, price } = req.body
    await pool.query(
      `INSERT INTO user_favorites (user_id, title, target_type, target_id, image_url, price, created_at)
       VALUES (?, ?, ?, ?, ?, ?, NOW())`,
      [req.user.id, title, target_type, target_id, image_url, price]
    )
    res.json({ code: 0, message: '收藏成功' })
  } catch (err) { next(err) }
})

router.delete('/me/favorites/:id', auth, async (req, res, next) => {
  try {
    await pool.query('DELETE FROM user_favorites WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '已取消收藏' })
  } catch (err) { next(err) }
})

// --- 评价 ---
router.get('/me/reviews', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT r.*, u.name as username, u.phone
       FROM user_reviews r
       LEFT JOIN users u ON r.user_id = u.id
       ORDER BY r.created_at DESC LIMIT 200`
    )
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

router.post('/me/reviews', auth, async (req, res, next) => {
  try {
    const { target_type, target_id, rating, content, images } = req.body
    if (!rating || !content) return res.status(400).json({ code: 400, message: '评分和内容不能为空' })
    await pool.query(
      `INSERT INTO user_reviews (user_id, target_type, target_id, rating, content, images, created_at)
       VALUES (?, ?, ?, ?, ?, ?, NOW())`,
      [req.user.id, target_type, target_id, rating, content, images ? JSON.stringify(images) : null]
    )
    res.json({ code: 0, message: '评价成功' })
  } catch (err) { next(err) }
})

// --- 地址 ---
router.get('/me/addresses', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT a.*, u.name as username, u.phone
       FROM user_addresses a
       LEFT JOIN users u ON a.user_id = u.id
       ORDER BY a.is_default DESC, a.created_at DESC LIMIT 200`
    )
    res.json({ code: 0, data: { list: rows } })
  } catch (err) { next(err) }
})

router.post('/me/addresses', auth, async (req, res, next) => {
  try {
    const { name, phone, detail, is_default } = req.body
    if (!name || !phone || !detail) return res.status(400).json({ code: 400, message: '姓名、电话、地址不能为空' })
    if (is_default) await pool.query('UPDATE user_addresses SET is_default = 0 WHERE user_id = ?', [req.user.id])
    await pool.query(
      `INSERT INTO user_addresses (user_id, name, phone, detail, is_default, created_at)
       VALUES (?, ?, ?, ?, ?, NOW())`,
      [req.user.id, name, phone, detail, is_default ? 1 : 0]
    )
    res.json({ code: 0, message: '地址添加成功' })
  } catch (err) { next(err) }
})

router.put('/me/addresses/:id', auth, async (req, res, next) => {
  try {
    const { name, phone, detail, is_default } = req.body
    if (is_default) await pool.query('UPDATE user_addresses SET is_default = 0 WHERE user_id = ?', [req.user.id])
    await pool.query(
      `UPDATE user_addresses SET name = ?, phone = ?, detail = ?, is_default = ?
       WHERE id = ?`,
      [name, phone, detail, is_default ? 1 : 0, req.params.id]
    )
    res.json({ code: 0, message: '地址更新成功' })
  } catch (err) { next(err) }
})

router.delete('/me/addresses/:id', auth, async (req, res, next) => {
  try {
    await pool.query('DELETE FROM user_addresses WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '已删除' })
  } catch (err) { next(err) }
})

// --- 订单 (minip 专属) ---
// [orders] 2026-09-15 · 订单接口辅助（解析 / 状态机 / 缺表判断）
const parseOrderItems = (v) => {
  if (Array.isArray(v)) return v
  if (!v) return []
  try { const a = JSON.parse(v); return Array.isArray(a) ? a : [] } catch (e) { return [] }
}
const ORDER_STATUS_CN = {
  pending: '待付款', awaiting_payment: '待付款', paid: '待发货', awaiting_ship: '待发货',
  shipped: '待收货', in_transit: '待收货', received: '待评价', completed_review_pending: '待评价',
  completed: '已完成', cancelled: '已取消', refunded: '已退款'
}
// 用户自己能做的：只有"待付款 → 取消"。其余（发货/收货/完成）由平台/后续环节驱动
const ORDER_USER_TRANSITIONS = { pending: ['cancelled'], awaiting_payment: ['cancelled'] }
// 管理端可推进的合法流转（避免随手把已取消的单改成已完成）
const ORDER_ADMIN_TRANSITIONS = {
  pending: ['paid', 'cancelled'], awaiting_payment: ['paid', 'cancelled'],
  paid: ['shipped', 'cancelled'], awaiting_ship: ['shipped', 'cancelled'],
  shipped: ['received'], in_transit: ['received'],
  received: ['completed'], completed_review_pending: ['completed'],
  completed: [], cancelled: [], refunded: []
}
const isMissingTable = (e) => e && (e.code === 'ER_NO_SUCH_TABLE' || /doesn't exist|Unknown table/i.test(e.message || ''))

router.get('/me/orders', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const isAdmin = req.user.role === 'admin'
    const { status = 'all' } = req.query
    let where = isAdmin ? '' : 'WHERE o.user_id = ?'
    const params = isAdmin ? [] : [userId]
    const statusMap = {
      pending_payment: ["pending", "awaiting_payment"],
      pending_ship: ["paid", "awaiting_ship"],
      pending_receive: ["shipped", "in_transit"],
      pending_review: ["received", "completed_review_pending"],
      completed: ["completed"]
    }
    if (status !== 'all' && statusMap[status]) {
      where += (where ? ' AND ' : 'WHERE ') + `o.status IN (${statusMap[status].map(() => '?').join(',')})`
      params.push(...statusMap[status])
    }
    const [rows] = await pool.query(
      `SELECT o.*, u.name as username, u.phone
       FROM minip_orders o
       LEFT JOIN users u ON o.user_id = u.id
       ${where}
       ORDER BY o.created_at DESC LIMIT 200`,
      params
    )
    const [[stats]] = await pool.query(
      `SELECT
         SUM(o.status IN ('pending','awaiting_payment')) as pending_payment,
         SUM(o.status IN ('paid','awaiting_ship')) as pending_ship,
         SUM(o.status IN ('shipped','in_transit')) as pending_receive,
         SUM(o.status IN ('received','completed_review_pending')) as pending_review
       FROM minip_orders o
       ${isAdmin ? '' : 'WHERE o.user_id = ?'}`,
      isAdmin ? [] : [userId]
    )
    res.json({ code: 0, data: { list: rows.map(r => Object.assign({}, r, { items: parseOrderItems(r.items), statusText: ORDER_STATUS_CN[r.status] || r.status })), stats, enabled: true } })
  } catch (err) {
    // 环境没建 minip_orders（生产曾因缺表 500）→ 如实返回"未启用"，而不是 500
    if (isMissingTable(err)) return res.json({ code: 0, data: { list: [], stats: {}, enabled: false, note: '当前环境未启用商城订单' } })
    next(err)
  }
})

// [orders] 订单详情：本人或 admin；不存在/非本人统一 404
router.get('/me/orders/:id', auth, async (req, res, next) => {
  try {
    const isAdmin = req.user.role === 'admin'
    const [rows] = await pool.query('SELECT * FROM minip_orders WHERE id = ?', [req.params.id])
    const o = rows[0]
    if (!o || (!isAdmin && o.user_id !== req.user.id)) return res.status(404).json({ code: 404, message: '订单不存在' })
    res.json({ code: 0, data: Object.assign({}, o, { items: parseOrderItems(o.items), statusText: ORDER_STATUS_CN[o.status] || o.status }) })
  } catch (err) {
    if (isMissingTable(err)) return res.status(404).json({ code: 404, message: '当前环境未启用商城订单' })
    next(err)
  }
})

router.post('/me/orders', auth, async (req, res, next) => {
  try {
    const { items, total_amount, address_id, remark } = req.body
    if (!items || !items.length) return res.status(400).json({ code: 400, message: '订单商品不能为空' })
    const orderNo = 'MP' + Date.now().toString().slice(-12)
    await pool.query(
      `INSERT INTO minip_orders (user_id, order_no, items, total_amount, address_id, remark, status, created_at)
       VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())`,
      [req.user.id, orderNo, JSON.stringify(items), total_amount || 0, address_id || null, remark || null]
    )
    res.json({ code: 0, data: { order_no: orderNo }, message: '订单创建成功' })
  } catch (err) { next(err) }
})

router.put('/me/orders/:id/status', auth, async (req, res, next) => {
  try {
    // [orders] 2026-09-15：此前这里**没有任何归属与状态校验** —— 任何登录用户能改任何订单的状态。
    //   现在：本人只能「待付款 → 取消」；admin 按合法流转推进。
    const { status } = req.body
    if (!status) return res.status(400).json({ code: 400, message: 'status 必填' })
    const isAdmin = req.user.role === 'admin'
    const [rows] = await pool.query('SELECT id, user_id, status FROM minip_orders WHERE id = ?', [req.params.id])
    const o = rows[0]
    if (!o) return res.status(404).json({ code: 404, message: '订单不存在' })
    if (!isAdmin && Number(o.user_id) !== Number(req.user.id)) return res.status(404).json({ code: 404, message: '订单不存在' })
    const allowed = isAdmin ? (ORDER_ADMIN_TRANSITIONS[o.status] || []) : (ORDER_USER_TRANSITIONS[o.status] || [])
    if (allowed.indexOf(String(status)) < 0) {
      return res.status(400).json({ code: 400, message: '订单当前状态（' + (ORDER_STATUS_CN[o.status] || o.status) + '）不能改为「' + (ORDER_STATUS_CN[status] || status) + '」' })
    }
    await pool.query('UPDATE minip_orders SET status = ?, updated_at = NOW() WHERE id = ?', [status, req.params.id])
    res.json({ code: 0, message: '状态更新成功', data: { id: Number(req.params.id), status } })
  } catch (err) { next(err) }
})

// --- 个人资料 ---
router.get('/me/profile', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT id, username, phone, email, avatar, role FROM users WHERE id = ?', [req.user.id])
    res.json({ code: 0, data: rows[0] || null })
  } catch (err) { next(err) }
})

router.put('/me/profile', auth, async (req, res, next) => {
  try {
    const { name, email, avatar } = req.body
    await pool.query('UPDATE users SET username = ?, email = ?, avatar = ? WHERE id = ?', [name, email, avatar, req.user.id])
    const [rows] = await pool.query('SELECT id, username, phone, email, avatar FROM users WHERE id = ?', [req.user.id])
    res.json({ code: 0, data: rows[0] })
  } catch (err) { next(err) }
})

// 电子名片 (business-card) - 2026-08-20
// 数据复用 users 表的员工名片字段
// ============================================================

// GET /api/minip/business-card/me - 我的名片
router.get('/business-card/me', auth, async (req, res, next) => {
  try {
    const [[employee]] = await pool.query(
      `SELECT id, name, email, phone, role, department, avatar, images, title, bio, bio_en, wechat,
              card_bg, card_layout, company_name, company_name_en, company_address, company_address_en, company_phone,
              status, created_at, employee_code, card_views, endorsements
       FROM users WHERE id = ? AND status = 'active'`,
      [req.user.id]
    )
    if (!employee) return res.status(404).json({ code: 404, message: '名片不存在' })

    const images = employee.images ? (typeof employee.images === 'string' ? JSON.parse(employee.images) : employee.images) : []
    const host = req.headers.host || 'localhost'
    res.json({
      code: 0,
      data: {
        ...employee,
        images,
        card_views: employee.card_views || 0,
        endorsements: employee.endorsements || 0,
        card_url: `https://${host}/minip/#/pages/business-card/index?id=${req.user.id}`,
        can_edit: true
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// PUT /api/minip/business-card/me - 编辑我的名片
router.put('/business-card/me', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { avatar, images, title, bio, bio_en, wechat, email, card_bg, card_layout, company_name, company_name_en, company_address, company_address_en, company_phone } = req.body

    const updates = []
    const params = []
    const add = (field, val) => {
      if (val !== undefined) {
        updates.push(`${field} = ?`)
        params.push(val === '' ? null : val)
      }
    }

    add('avatar', avatar)
    if (images !== undefined) {
      updates.push('images = ?')
      params.push(Array.isArray(images) ? JSON.stringify(images) : images)
    }
    add('title', title)
    add('bio', bio)
    add('bio_en', bio_en)
    add('wechat', wechat)
    add('email', email)
    add('card_bg', card_bg)
    if (card_layout !== undefined) {
      // 名片布局持久化（2026-09-18 D4 缺口修复）：对象存 JSON 串，前端 applyLayout 兼容字符串/对象
      updates.push('card_layout = ?')
      params.push(card_layout && typeof card_layout === 'object' ? JSON.stringify(card_layout) : (card_layout || null))
    }
    add('company_name', company_name)
    add('company_name_en', company_name_en)
    add('company_address', company_address)
    add('company_address_en', company_address_en)
    add('company_phone', company_phone)

    if (!updates.length) return res.status(400).json({ code: 400, message: '没有要更新的字段' })

    params.push(userId)
    await pool.query(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`, params)

    // 返回更新后的数据
    const [[row]] = await pool.query(
      `SELECT id, name, email, phone, role, department, avatar, images, title, bio, bio_en, wechat,
              card_bg, card_layout, company_name, company_name_en, company_address, company_address_en, company_phone,
              card_views, endorsements FROM users WHERE id = ?`,
      [userId]
    )
    res.json({ code: 0, data: { ...row, images: row.images ? (typeof row.images === 'string' ? JSON.parse(row.images) : row.images) : [] }, message: '名片更新成功' })
  } catch (err) { next(err) }
})

// GET /api/minip/business-card/favorites/list - 我的收藏列表 (auth)
router.get('/business-card/favorites/list', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [rows] = await pool.query(
      `SELECT f.id AS fav_id, f.created_at AS fav_at,
              u.id, u.name, u.title, u.department, u.phone, u.email, u.avatar, u.card_bg, u.company_name
       FROM business_card_favorites f
       JOIN users u ON u.id = f.card_user_id AND u.status = 'active'
       WHERE f.user_id = ?
       ORDER BY f.created_at DESC`,
      [userId]
    )
    res.json({ code: 0, data: { list: rows, total: rows.length }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/minip/business-card/:id - 公开查看名片
router.get('/business-card/:id', async (req, res, next) => {
  try {
    const { id } = req.params
    const [[employee]] = await pool.query(
      `SELECT id, name, email, phone, role, department, avatar, images, title, bio, bio_en, wechat,
              card_bg, card_layout, company_name, company_name_en, company_address, company_address_en, company_phone,
              status, created_at, employee_code, card_views, endorsements
       FROM users WHERE id = ? AND status = 'active'`,
      [id]
    )
    if (!employee) return res.status(404).json({ code: 404, message: '名片不存在' })

    const images = employee.images ? (typeof employee.images === 'string' ? JSON.parse(employee.images) : employee.images) : []
    const host = req.headers.host || 'localhost'
    res.json({
      code: 0,
      data: {
        ...employee,
        images,
        card_views: employee.card_views || 0,
        endorsements: employee.endorsements || 0,
        card_url: `https://${host}/minip/#/pages/business-card/index?id=${id}`,
        can_edit: !!(req.user && req.user.id == id)
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// POST /api/minip/business-card/:id/view - 浏览量+1
router.post('/business-card/:id/view', async (req, res, next) => {
  try {
    await pool.query('UPDATE users SET card_views = COALESCE(card_views, 0) + 1 WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/minip/business-card/:id/endorse - 点赞/赞赏 (2026-08-29 波哥: 一天一人一次去重)
router.post('/business-card/:id/endorse', async (req, res, next) => {
  try {
    const { id } = req.params
    const cardUserId = Number(id)

    // 登录用户可选解析：有有效 token 则用 user_id 去重, 无/失效则按 IP (游客)
    let userId = null
    const hdr = req.headers.authorization || ''
    if (hdr.startsWith('Bearer ')) {
      try {
        const decoded = jwt.verify(hdr.slice(7), process.env.JWT_SECRET)
        userId = decoded.id || null
      } catch (_) { /* token 失效 → 游客 */ }
    }
    const ip = (req.headers['x-forwarded-for'] || req.socket?.remoteAddress || '').split(',')[0].trim() || null

    // 查今天是否已赞过
    let exist = false
    if (userId) {
      const [rows] = await pool.query(
        'SELECT id FROM business_card_likes WHERE card_user_id=? AND user_id=? AND like_date=CURDATE()',
        [cardUserId, userId]
      )
      exist = rows.length > 0
    } else if (ip) {
      const [rows] = await pool.query(
        'SELECT id FROM business_card_likes WHERE card_user_id=? AND ip=? AND like_date=CURDATE()',
        [cardUserId, ip]
      )
      exist = rows.length > 0
    }

    const [[u]] = await pool.query('SELECT endorsements FROM users WHERE id = ?', [cardUserId]) // 用于统一返回 count

    if (exist) {
      return res.json({ code: 0, message: '今日已点赞', count: u?.endorsements || 0, already: true })
    }

    // 新点赞: 记录 + endorsements+1
    await pool.query(
      'INSERT INTO business_card_likes (card_user_id, user_id, ip, like_date) VALUES (?,?,?,CURDATE())',
      [cardUserId, userId, ip]
    )
    await pool.query('UPDATE users SET endorsements = COALESCE(endorsements, 0) + 1 WHERE id = ?', [cardUserId])
    const [[u2]] = await pool.query('SELECT endorsements FROM users WHERE id = ?', [cardUserId])
    res.json({ code: 0, message: '点赞成功', count: u2?.endorsements || 0, already: false })
  } catch (err) { next(err) }
})

// GET /api/minip/business-card/:id/qrcode - 返回名片 QR 图 (公开)
router.get('/business-card/:id/qrcode', async (req, res, next) => {
  try {
    const { id } = req.params
    const host = req.headers.host || 'localhost'
    const protocol = req.headers['x-forwarded-pro'] || (host.includes('localhost') ? 'http' : 'https')
    const scanUrl = `${protocol}://${host}/minip/#/pages/business-card/index?id=${id}`

    const filename = `business-card-${id}.png`
    const filePath = path.join(bcQrDir, filename)
    if (!fs.existsSync(filePath)) {
      await QRCode.toFile(filePath, scanUrl, { width: 480, margin: 2, color: { dark: '#16161A', light: '#FFFFFF' } })
    }
    res.json({
      code: 0,
      data: {
        scan_url: scanUrl,
        image_url: `/uploads/business-cards/${filename}`
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// GET /api/minip/business-card/:id/poster - 名片海报图 (公开)
router.get('/business-card/:id/poster', async (req, res, next) => {
  try {
    const { id } = req.params
    const host = req.headers.host || 'localhost'
    const protocol = req.headers['x-forwarded-pro'] || (host.includes('localhost') ? 'http' : 'https')
    const scanUrl = `${protocol}://${host}/minip/#/pages/business-card/index?id=${id}`

    // 取名片数据
    const [[employee]] = await pool.query(
      `SELECT id, name, title, department, phone, email, wechat, avatar, company_name, company_phone, card_bg
       FROM users WHERE id = ? AND status = 'active'`,
      [id]
    )
    if (!employee) return res.status(404).json({ code: 404, message: '名片不存在' })

    const filename = `business-card-poster-${id}.png`
    const filePath = path.join(bcQrDir, filename)

    if (!fs.existsSync(filePath)) {
      const W = 1536, H = 768
      const bgBuf = await posterBackground(employee.card_bg || 'blue')

      const qrBuf = await QRCode.toBuffer(scanUrl, {
        width: 320, margin: 1,
        color: { dark: '#16161A', light: '#FFFFFF' }
      })

      let avatarBuf = null
      const productsDir = path.join(__dirname, '../uploads/products')
      if (employee.avatar) {
        const aPath = employee.avatar.startsWith('/')
          ? path.join(__dirname, '..', employee.avatar)
          : path.join(productsDir, employee.avatar)
        if (fs.existsSync(aPath)) {
          // 圆角遮罩：先 resize 成 180x180，再用 SVG 圆形 mask 裁剪
          const maskSvg = `<svg width="180" height="180"><circle cx="90" cy="90" r="90" fill="#FFFFFF"/></svg>`
          avatarBuf = await sharp(aPath)
            .resize(180, 180, { fit: 'cover' })
            .composite([{
              input: Buffer.from(maskSvg),
              blend: 'dest-in'
            }])
            .png()
            .toBuffer()
          // 加白色边框
          avatarBuf = await sharp({
            create: { width: 200, height: 200, channels: 4, background: { r: 0, g: 0, b: 0, alpha: 0 } }
          }).composite([
            { input: Buffer.from('<svg width="200" height="200"><circle cx="100" cy="100" r="100" fill="#FFFFFF"/></svg>'), blend: 'over' },
            { input: avatarBuf, top: 10, left: 10 }
          ]).png().toBuffer()
        }
      }

      const meta = []
      if (employee.phone) meta.push(`📱  ${employee.phone}`)
      if (employee.email) meta.push(`✉️  ${employee.email}`)
      if (employee.wechat) meta.push(`💬  ${employee.wechat}`)
      if (employee.company_phone) meta.push(`🏢  ${employee.company_phone}`)

      // QR 白底圆角
      const qrBgBuf = await sharp({
        create: { width: 360, height: 360, channels: 4, background: { r: 255, g: 255, b: 255, alpha: 1 } }
      }).composite([{
        input: qrBuf,
        top: 20, left: 20
      }]).png().toBuffer()

      const composites = []
      if (avatarBuf) composites.push({ input: avatarBuf, top: 200, left: 80 })
      composites.push({ input: Buffer.from(posterTextSvg(employee, meta)), top: 0, left: 0 })
      composites.push({ input: qrBgBuf, top: 200, left: W - 80 - 360 })

      await sharp(bgBuf)
        .resize(W, H)
        .composite(composites)
        .png()
        .toFile(filePath)
    }

    res.json({
      code: 0,
      data: {
        image_url: `/uploads/business-cards/${filename}`,
        width: 1536,
        height: 768
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

async function posterBackground(theme) {
  const palettes = {
    blue:   ['#667eea', '#764ba2'],
    dark:   ['#232526', '#414345'],
    gold:   ['#f5af19', '#f12711'],
    green:  ['#11998e', '#38ef7d'],
    purple: ['#7E53FF', '#B794F6'],
    red:    ['#eb3349', '#f45c43']
  }
  const [c1, c2] = palettes[theme] || palettes.blue
  const W = 1536, H = 768
  return await sharp({
    create: { width: W, height: H, channels: 4, background: { r: 0, g: 0, b: 0, alpha: 0 } }
  }).composite([{
    input: Buffer.from(`<svg width="${W}" height="${H}">
      <defs>
        <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="${c1}"/>
          <stop offset="100%" stop-color="${c2}"/>
        </linearGradient>
        <pattern id="dots" x="0" y="0" width="36" height="36" patternUnits="userSpaceOnUse">
          <circle cx="18" cy="18" r="1.2" fill="#FFFFFF" opacity="0.15"/>
        </pattern>
      </defs>
      <rect width="${W}" height="${H}" fill="url(#g)"/>
      <rect width="${W}" height="${H}" fill="url(#dots)"/>
      <rect x="32" y="32" width="${W - 64}" height="${H - 64}" rx="28" fill="#FFFFFF" opacity="0.06"/>
      <rect x="32" y="32" width="${W - 64}" height="80" rx="28" fill="#FFFFFF" opacity="0.10"/>
    </svg>`)
  }]).png().toBuffer()
}

function posterTextSvg(emp, meta) {
  const esc = (s) => String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const W = 1536, H = 768
  const lines = []

  // 顶部 "DIGITAL BUSINESS CARD" + 品牌名
  lines.push(`<text x="40" y="78" font-family="PingFang SC, sans-serif" font-size="22" font-weight="600" fill="#FFFFFF" opacity="0.9">DIGITAL BUSINESS CARD</text>`)
  lines.push(`<text x="${W - 40}" y="78" text-anchor="end" font-family="PingFang SC, sans-serif" font-size="18" fill="#FFFFFF" opacity="0.7">${esc(emp.company_name || 'gbaw.cn')}</text>`)

  // 姓名（大字）— 在头像右侧，x=300 起
  lines.push(`<text x="300" y="285" font-family="PingFang SC, Microsoft YaHei, sans-serif" font-size="64" font-weight="700" fill="#FFFFFF">${esc(emp.name)}</text>`)

  // 职位 · 部门
  if (emp.title || emp.department) {
    const t = `${esc(emp.title || '')}${emp.title && emp.department ? ' · ' : ''}${esc(emp.department || '')}`
    lines.push(`<text x="300" y="335" font-family="PingFang SC, sans-serif" font-size="32" fill="#FFFFFF" opacity="0.92">${t}</text>`)
  }

  // 公司（在姓名前面空白处加一行）
  if (emp.company_name) {
    lines.push(`<text x="300" y="380" font-family="PingFang SC, sans-serif" font-size="22" fill="#FFFFFF" opacity="0.8">${esc(emp.company_name)}</text>`)
  }

  // 分割线
  lines.push(`<line x1="300" y1="420" x2="1100" y2="420" stroke="#FFFFFF" stroke-width="2" opacity="0.35"/>`)

  // 联系方式（左对齐，4 行）
  let metaY = 470
  meta.slice(0, 4).forEach((line) => {
    lines.push(`<text x="300" y="${metaY}" font-family="PingFang SC, sans-serif" font-size="24" fill="#FFFFFF" opacity="0.92">${esc(line)}</text>`)
    metaY += 40
  })

  // bio（简短）
  if (emp.bio) {
    const shortBio = emp.bio.length > 80 ? emp.bio.slice(0, 80) + '...' : emp.bio
    lines.push(`<text x="300" y="${metaY + 10}" font-family="PingFang SC, sans-serif" font-size="20" fill="#FFFFFF" opacity="0.75">${esc(shortBio)}</text>`)
  }

  // 底部水印
  lines.push(`<text x="40" y="${H - 40}" font-family="PingFang SC, sans-serif" font-size="18" fill="#FFFFFF" opacity="0.65">微信扫一扫 → 查看名片</text>`)
  lines.push(`<text x="${W - 40}" y="${H - 40}" text-anchor="end" font-family="PingFang SC, sans-serif" font-size="16" fill="#FFFFFF" opacity="0.5">来自 gbaw.cn</text>`)

  return `<svg width="${W}" height="${H}">${lines.join('\n')}</svg>`
}

// POST /api/minip/business-card/:id/favorite - 收藏 (auth)
router.post('/business-card/:id/favorite', auth, async (req, res, next) => {
  try {
    const cardUserId = Number(req.params.id)
    const userId = req.user.id
    if (cardUserId === userId) return res.status(400).json({ code: 400, message: '不能收藏自己的名片' })
    await pool.query(
      'INSERT IGNORE INTO business_card_favorites (user_id, card_user_id) VALUES (?, ?)',
      [userId, cardUserId]
    )
    res.json({ code: 0, message: '已收藏' })
  } catch (err) { next(err) }
})

// DELETE /api/minip/business-card/:id/favorite - 取消收藏 (auth)
router.delete('/business-card/:id/favorite', auth, async (req, res, next) => {
  try {
    const cardUserId = Number(req.params.id)
    const userId = req.user.id
    await pool.query(
      'DELETE FROM business_card_favorites WHERE user_id = ? AND card_user_id = ?',
      [userId, cardUserId]
    )
    res.json({ code: 0, message: '已取消收藏' })
  } catch (err) { next(err) }
})

// GET /api/minip/business-card/:id/favorite - 是否已收藏 (auth)
router.get('/business-card/:id/favorite', auth, async (req, res, next) => {
  try {
    const cardUserId = Number(req.params.id)
    const userId = req.user.id
    const [[row]] = await pool.query(
      'SELECT id, created_at FROM business_card_favorites WHERE user_id = ? AND card_user_id = ?',
      [userId, cardUserId]
    )
    res.json({
      code: 0,
      data: {
        favorited: !!row,
        created_at: row?.created_at || null
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// ============================================================
// OA 路由代理 - 2026-08-19 修复
// 把 /api/minip/oa/* 全部转给 oa.js 处理
// minip 页面 (attendance-clock / task-list / work-log-list) 都是调 /api/minip/oa/... 但没 mount
// ============================================================
router.use('/oa', auth, oaRoutes)

// ============================================================
// POST /api/minip/upload — 通用图片上传 (2026-09-03 江小鱼新建)
//   form-data: file=@xxx.png
//   仅 minip 登录用户 (auth 中间件)
//   保存到 /home/ubuntu/server/uploads/business-cards/
//   返回 { code: 0, data: { url: "/uploads/business-cards/xxx.png" } }
//   复用 /uploads 静态服务 (index.js line 214)
// ============================================================
import multer from 'multer'
const bcUploadStorage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, bcQrDir),
  filename: (req, file, cb) => {
    const ext = (path.extname(file.originalname) || '.png').toLowerCase()
    const safe = ext.replace(/[^a-z0-9.]/g, '')
    cb(null, `${Date.now()}-${Math.random().toString(36).slice(2, 8)}${safe}`)
  },
})
const bcUploadFilter = (req, file, cb) => {
  const allowed = ['.jpg', '.jpeg', '.png', '.webp', '.gif']
  const ext = (path.extname(file.originalname) || '').toLowerCase()
  if (allowed.includes(ext)) cb(null, true)
  else cb(new Error(`不支持的格式: ${ext}`), false)
}
const bcUpload = multer({
  storage: bcUploadStorage,
  fileFilter: bcUploadFilter,
  limits: { fileSize: 15 * 1024 * 1024 }, // 15MB (与 products upload 一致)
})

router.post('/upload', auth, (req, res, next) => {
  bcUpload.single('file')(req, res, (err) => {
    if (err) {
      if (err.code === 'LIMIT_FILE_SIZE') {
        return res.status(413).json({ code: 413, message: '文件超 15MB 限制' })
      }
      return res.status(400).json({ code: 400, message: err.message || '上传失败' })
    }
    if (!req.file) return res.status(400).json({ code: 400, message: 'file 必传' })
    const url = `/uploads/business-cards/${req.file.filename}`
    console.log(`[minip/upload] user=${req.user?.id} file=${req.file.filename} size=${req.file.size}`)
    res.json({
      code: 0,
      data: { url, filename: req.file.filename, size: req.file.size },
      message: 'ok',
    })
  })
})

// ============================================================
// 原 export 仍在下方, 此为插桩, 不破坏现有 routes
// ============================================================
export default router

// 表结构说明（会在 server 启动时自动创建）：
/*
CREATE TABLE IF NOT EXISTS user_feedback (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  type VARCHAR(32) DEFAULT 'bug',
  content TEXT NOT NULL,
  contact VARCHAR(128),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS user_favorites (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  title VARCHAR(255),
  target_type VARCHAR(64),
  target_id INT,
  image_url VARCHAR(512),
  price DECIMAL(10,2),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS user_reviews (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  target_type VARCHAR(64),
  target_id INT,
  rating INT,
  content TEXT,
  images JSON,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS user_addresses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  name VARCHAR(128),
  phone VARCHAR(32),
  detail TEXT,
  is_default TINYINT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS minip_orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  order_no VARCHAR(64) UNIQUE,
  items JSON,
  total_amount DECIMAL(12,2),
  address_id INT,
  remark TEXT,
  status VARCHAR(32) DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
*/

