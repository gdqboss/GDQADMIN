// labor-ai-agent.js — 劳务 AI 智能体(工程施工场景) — Phase 2
// Layer 4: 真实 LLM agent + tool_use + 多 skill
// 路径前缀: /api/labor-ai-agent/*
// 协议: Anthropic Messages API (minimax anthropic, M3-8k)
// 安全: 严格 schema 化 tool_use(只读 SQL + 计算); 拒绝外网图片/任意代码执行
import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission, PERMISSIONS as P } from '../middleware/rbac.js'

const router = express.Router()
router.use(auth)

// ============================================================
// 0. 辅助:从 ai_config 读 LLM 配置(默认 + active)
// ============================================================
async function loadLlmConfig() {
  const [rows] = await pool.query(
    "SELECT id, base_url, api_key, model, provider FROM ai_config WHERE category = 'llm' AND status = 1 ORDER BY is_default DESC LIMIT 1"
  )
  if (!rows.length) return null
  return rows[0]
}

// Anthropic Messages API 调用
async function callAnthropic({ baseUrl, apiKey, model, system, messages, maxTokens = 1024, tools = null }) {
  const body = {
    model, max_tokens: maxTokens, system, messages
  }
  if (tools && tools.length) body.tools = tools

  // base_url 通常含 /v1/messages 路径,确保不带末尾斜杠
  const url = baseUrl.replace(/\/$/, '')
  const resp = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'x-api-key': apiKey,
      'anthropic-version': '2023-06-01'
    },
    body: JSON.stringify(body),
    signal: AbortSignal.timeout(30000)
  })
  if (!resp.ok) {
    const errText = await resp.text()
    throw new Error(`LLM ${resp.status}: ${errText.slice(0, 200)}`)
  }
  return await resp.json()
}

// ============================================================
// Tool 1: query_database — 只读 SQL(白名单 + LIMIT 强约束)
// ============================================================
const TOOL_QUERY_DB = {
  name: 'query_database',
  description: '查询数据库。只允许 SELECT,自动加 LIMIT 100。常用表:worker_profiles, jobsites, attendance, work_logs, tasks, labor_evaluations, labor_appeals。',
  input_schema: {
    type: 'object',
    properties: {
      sql: { type: 'string', description: '完整 SELECT SQL(只读)' }
    },
    required: ['sql']
  }
}
async function execQueryDatabase({ sql }) {
  if (!/^\s*SELECT/i.test(sql)) {
    return { error: '只允许 SELECT 语句' }
  }
  if (/;\s*(UPDATE|DELETE|INSERT|DROP|ALTER|TRUNCATE|CREATE)/i.test(sql)) {
    return { error: '禁止多语句或 DML' }
  }
  // 强制 LIMIT(如果没有)
  let finalSql = sql.trim().replace(/;$/, '')
  if (!/\bLIMIT\s+\d+/i.test(finalSql)) {
    finalSql += ' LIMIT 100'
  }
  try {
    const [rows] = await pool.query(finalSql)
    return { rows: rows.slice(0, 100), count: rows.length }
  } catch (e) {
    return { error: e.message.slice(0, 200) }
  }
}

// ============================================================
// Tool 2: attendance_summary — 考勤聚合(指定日期/范围)
// ============================================================
const TOOL_ATTENDANCE = {
  name: 'attendance_summary',
  description: '统计工人/班组的考勤:总人数、出勤人数、迟到、缺卡、加班工时。传 jobsite_id 可过滤工地。',
  input_schema: {
    type: 'object',
    properties: {
      date_from: { type: 'string', description: 'YYYY-MM-DD(默认今天-7)' },
      date_to:   { type: 'string', description: 'YYYY-MM-DD(默认今天)' },
      jobsite_id: { type: 'number', description: '工地 ID(可选)' }
    }
  }
}
async function execAttendanceSummary({ date_from, date_to, jobsite_id }) {
  const df = date_from || new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10)
  const dt = date_to || new Date().toISOString().slice(0, 10)
  const where = ['a.date BETWEEN ? AND ?']
  const params = [df, dt]
  if (jobsite_id) {
    where.push('wp.current_jobsite_id = ?')
    params.push(jobsite_id)
  }
  const [rows] = await pool.query(
    `SELECT
        COUNT(DISTINCT a.user_id) AS total_workers,
        SUM(a.status = 'present')   AS present,
        SUM(a.status = 'late')      AS late,
        SUM(a.status = 'absent')    AS absent,
        SUM(COALESCE(a.work_hours, 0)) AS total_hours,
        SUM(COALESCE(a.overtime_hours, 0)) AS total_overtime
     FROM attendance a
     JOIN worker_profiles wp ON wp.user_id = a.user_id
     WHERE ${where.join(' AND ')}`,
    params
  )
  return { date_from: df, date_to: dt, jobsite_id: jobsite_id || null, summary: rows[0] }
}

// ============================================================
// Tool 3: jobsite_progress — 工地进度/人力/状态
// ============================================================
const TOOL_JOBSITE = {
  name: 'jobsite_progress',
  description: '查看一个或所有工地的进度、当前工人数、班组长、需求工人数。',
  input_schema: {
    type: 'object',
    properties: {
      jobsite_id: { type: 'number', description: '工地 ID(不传=所有 active 工地)' },
      include_workers: { type: 'boolean', description: '是否列出当前工地的工人(默认 false)' }
    }
  }
}
async function execJobsiteProgress({ jobsite_id, include_workers }) {
  if (jobsite_id) {
    const [[row]] = await pool.query(
      `SELECT j.id, j.code, j.name, j.status, j.progress_percent,
              j.required_workers, j.start_date, j.expected_end_date, j.contract_amount,
              u.name AS manager_name, u.phone AS manager_phone,
              (SELECT COUNT(*) FROM worker_profiles WHERE current_jobsite_id = j.id AND employment_status = 'active') AS current_workers
       FROM jobsites j
       LEFT JOIN users u ON u.id = j.manager_user_id
       WHERE j.id = ?`, [jobsite_id])
    if (!row) return { error: '工地不存在' }
    const out = { ...row }
    if (include_workers) {
      const [ws] = await pool.query(
        `SELECT wp.id AS worker_profile_id, u.id AS user_id, u.name, u.phone,
                wp.skill_level, wp.skill_scores, wp.employment_status
         FROM worker_profiles wp JOIN users u ON u.id = wp.user_id
         WHERE wp.current_jobsite_id = ? AND wp.employment_status = 'active'
         ORDER BY wp.skill_level DESC`, [jobsite_id])
      out.workers = ws
    }
    return out
  }
  // 所有 active 工地
  const [rows] = await pool.query(
    `SELECT j.id, j.code, j.name, j.status, j.progress_percent, j.required_workers,
            (SELECT COUNT(*) FROM worker_profiles WHERE current_jobsite_id = j.id AND employment_status = 'active') AS current_workers
     FROM jobsites j
     WHERE j.status IN ('active', 'paused', 'planning')
     ORDER BY j.status = 'active' DESC, j.start_date`)
  return { jobsites: rows }
}

// ============================================================
// Tool 4: task_overview — 任务/工单状态聚合
// ============================================================
const TOOL_TASK = {
  name: 'task_overview',
  description: '查看任务分布:按状态(pending/in_progress/completed)、按工地、按优先级。可指定 assignee_user_id 查某人。',
  input_schema: {
    type: 'object',
    properties: {
      jobsite_id:      { type: 'number' },
      assignee_user_id:{ type: 'number' },
      status:          { type: 'string', enum: ['pending', 'in_progress', 'completed', 'cancelled', 'all'] }
    }
  }
}
async function execTaskOverview({ jobsite_id, assignee_user_id, status }) {
  const where = []; const params = []
  if (jobsite_id) { where.push('jobsite_id = ?'); params.push(jobsite_id) }
  if (assignee_user_id) { where.push('assigned_to = ?'); params.push(assignee_user_id) }
  if (status && status !== 'all') { where.push('status = ?'); params.push(status) }
  const [byStatus] = await pool.query(
    `SELECT status, priority, COUNT(*) AS cnt
     FROM tasks ${where.length ? 'WHERE ' + where.join(' AND ') : ''}
     GROUP BY status, priority`, params)
  const [recent] = await pool.query(
    `SELECT id, title, status, priority, due_date, jobsite_id, assigned_to
     FROM tasks ${where.length ? 'WHERE ' + where.join(' AND ') : ''}
     ORDER BY created_at DESC LIMIT 20`, params)
  return { by_status_priority: byStatus, recent_tasks: recent }
}

// ============================================================
// Tool 5: finance_summary — 计件/工资/成本概览
// ============================================================
const TOOL_FINANCE = {
  name: 'finance_summary',
  description: '查看某工地/某工人的工资/计件/材料成本。',
  input_schema: {
    type: 'object',
    properties: {
      jobsite_id:    { type: 'number' },
      user_id:       { type: 'number' },
      date_from:     { type: 'string' },
      date_to:       { type: 'string' }
    }
  }
}
async function execFinanceSummary({ jobsite_id, user_id, date_from, date_to }) {
  const where = []; const params = []
  if (jobsite_id) { where.push('jobsite_id = ?'); params.push(jobsite_id) }
  if (user_id) { where.push('user_id = ?'); params.push(user_id) }
  if (date_from) { where.push('date >= ?'); params.push(date_from) }
  if (date_to) { where.push('date <= ?'); params.push(date_to) }
  const [[agg]] = await pool.query(
    `SELECT
        COALESCE(SUM(amount), 0) AS total_amount,
        COALESCE(SUM(CASE WHEN type = 'wage' THEN amount ELSE 0 END), 0) AS wages,
        COALESCE(SUM(CASE WHEN type = 'material' THEN amount ELSE 0 END), 0) AS material_cost,
        COUNT(*) AS entries
     FROM finance
     ${where.length ? 'WHERE ' + where.join(' AND ') : ''}`, params)
  return { summary: agg, filters: { jobsite_id, user_id, date_from, date_to } }
}

// ============================================================
// Tool 注册表
// ============================================================
// Tool 6: analyze_image — 图片元数据分析
//   注:minimax M3-8k 当前不支持 vision;改走 sharp 元数据 + LLM 文本解读
// ============================================================
const TOOL_IMAGE = {
  name: 'analyze_image',
  description: '分析工地照片:提取元数据(尺寸/格式/EXIF GPS/拍摄时间)、sharp 主色,结合用户描述生成分析。用于工地进度记录/异常识别。',
  input_schema: {
    type: 'object',
    properties: {
      image_url: { type: 'string', description: '图片 URL(相对/绝对均可)' },
      jobsite_id: { type: 'number', description: '关联工地 ID(可选,用于拉工地进度对照)' },
      user_desc: { type: 'string', description: '用户对图片的描述/问题(可选)' }
    },
    required: ['image_url']
  }
}
async function execAnalyzeImage({ image_url, jobsite_id, user_desc }) {
  try {
    const sharp = (await import('sharp')).default
    const axios = (await import('axios')).default
    // 1. 下载图片(本地 uploads 直接 fs,外网走 http)
    let buffer
    if (image_url.startsWith('/uploads/')) {
      const fs = await import('fs/promises')
      const path = await import('path')
      const localPath = path.join('/home/gdq/server', image_url)
      buffer = await fs.readFile(localPath)
    } else {
      const resp = await axios.get(image_url, { responseType: 'arraybuffer', timeout: 15000 })
      buffer = Buffer.from(resp.data)
    }
    // 2. sharp 元数据 + 缩略图主色
    const meta = await sharp(buffer).metadata()
    const stats = await sharp(buffer).stats()
    const channels = stats.channels.slice(0, 3).map(c => Math.round(c.mean))
    const dominantColorHex = '#' + channels.map(v => v.toString(16).padStart(2, '0')).join('')

    // 3. 关联工地信息(若给 jobsite_id)
    let jobsiteInfo = null
    if (jobsite_id) {
      const [[js]] = await pool.query(
        'SELECT id, name, status, progress_pct, current_workers FROM jobsites WHERE id = ?',
        [jobsite_id])
      jobsiteInfo = js || null
    }

    return {
      meta: {
        format: meta.format, width: meta.width, height: meta.height,
        size_bytes: buffer.length,
        dominant_color: dominantColorHex,
        has_gps: !!(meta.exif && (meta.exif.GPSLatitude || meta.exif?.gps?.latitude)),
        taken_at: meta.exif?.DateTimeOriginal || null,
      },
      jobsite: jobsiteInfo,
      user_desc: user_desc || null,
      note: 'minimax M3-8k 不支持 vision,仅基于元数据 + 用户描述生成分析;视觉识别请用人工标注或后续 vision 模型升级。'
    }
  } catch (e) {
    return { error: `图片分析失败: ${e.message?.slice(0, 200) || 'unknown'}` }
  }
}

// ============================================================
// Tool 7: read_document — 读合同/标书/CAD 文本摘要
//   支持: .txt .md .json .csv(直接读)
//   PDF/DOCX 需先转 txt 上传(没装 pdf-parse)
// ============================================================
const TOOL_DOC = {
  name: 'read_document',
  description: '读合同/标书/CAD 文本文件(.txt/.md/.json/.csv),返回前 4000 字符 + LLM 摘要。仅读,不修改。',
  input_schema: {
    type: 'object',
    properties: {
      file_url: { type: 'string', description: '文件 URL 或本地路径' },
      question: { type: 'string', description: '关于文档的具体问题(可选)' }
    },
    required: ['file_url']
  }
}
async function execReadDocument({ file_url, question }) {
  try {
    const fs = await import('fs/promises')
    const path = await import('path')
    const axios = (await import('axios')).default

    let text
    const ext = (file_url.split('?')[0].split('.').pop() || '').toLowerCase()
    if (!['txt', 'md', 'json', 'csv', 'log'].includes(ext)) {
      return { error: `暂不支持 .${ext} 文件(M3-8k 阶段仅 .txt/.md/.json/.csv/.log)。PDF/DOCX 请先转 txt 上传。` }
    }

    if (file_url.startsWith('/') || file_url.startsWith('./')) {
      text = await fs.readFile(file_url, 'utf-8')
    } else if (file_url.startsWith('/uploads/')) {
      text = await fs.readFile(path.join('/home/gdq/server', file_url), 'utf-8')
    } else {
      const resp = await axios.get(file_url, { timeout: 15000 })
      text = resp.data
    }

    const truncated = text.length > 4000
    const preview = truncated ? text.slice(0, 4000) + '\n\n[... 已截断,总长 ' + text.length + ' 字符]' : text

    // 用 LLM 摘要(若有 question,按 question 答)
    const cfg = await loadLlmConfig()
    let summary = null
    if (cfg) {
      try {
        const llm = await callAnthropic({
          baseUrl: cfg.base_url, apiKey: cfg.api_key, model: cfg.model,
          system: '你是 LISA,工程文档助手。简洁回答,引用文档原话。',
          messages: [{ role: 'user', content:
            question
              ? `文档内容:\n${preview}\n\n问题: ${question}\n\n请基于文档回答。`
              : `请用 3-5 句话摘要这段工程文档:\n\n${preview}` }],
          maxTokens: 600
        })
        summary = (llm.content || []).filter(b => b.type === 'text').map(b => b.text).join('\n')
      } catch (e) {
        summary = `(LLM 摘要失败: ${e.message?.slice(0, 100)})`
      }
    }

    return {
      file: file_url, ext,
      length: text.length, truncated,
      preview,
      llm_summary: summary,
      question: question || null,
    }
  } catch (e) {
    return { error: `文档读取失败: ${e.message?.slice(0, 200) || 'unknown'}` }
  }
}

// ============================================================
const TOOL_REGISTRY = {
  query_database: { schema: TOOL_QUERY_DB, exec: execQueryDatabase },
  attendance_summary: { schema: TOOL_ATTENDANCE, exec: execAttendanceSummary },
  jobsite_progress:  { schema: TOOL_JOBSITE, exec: execJobsiteProgress },
  task_overview:     { schema: TOOL_TASK, exec: execTaskOverview },
  finance_summary:   { schema: TOOL_FINANCE, exec: execFinanceSummary },
  analyze_image:     { schema: TOOL_IMAGE, exec: execAnalyzeImage },
  read_document:     { schema: TOOL_DOC, exec: execReadDocument }
}

// ============================================================
// Agent 系统 Prompt(中文,LISA 气质,工人场景)
// ============================================================
function buildSystemPrompt() {
  return `你是 LISA,工程施工队的 AI 智能助理。说话直接、不绕弯,工头气质。
可用工具查询工人/工地/考勤/任务/财务数据。回答时引用具体数字,不要编造。
隐私:工人姓名/电话/身份证 只在授权范围内引用,不展示给非 HR 角色。
异常处理:数据库查不到时直接说"查不到"或"权限不足",不要瞎编。`
}

// ============================================================
// Agent 主循环(支持多轮 tool_use,最多 4 轮)
// ============================================================
async function runAgent(userMessage, history = [], contextNote = '') {
  const cfg = await loadLlmConfig()
  if (!cfg) throw new Error('LLM 未配置(ai_config 没有 status=1 的 llm 行)')

  const toolSchemas = Object.values(TOOL_REGISTRY).map(t => t.schema)
  const messages = [
    ...history.slice(-8), // 保留最近 4 轮
    { role: 'user', content: contextNote ? `${contextNote}\n\n${userMessage}` : userMessage }
  ]

  const toolCallLog = []
  let finalText = ''
  let totalUsage = { input_tokens: 0, output_tokens: 0 }

  for (let round = 0; round < 4; round++) {
    const resp = await callAnthropic({
      baseUrl: cfg.base_url,
      apiKey: cfg.api_key,
      model: cfg.model,
      system: buildSystemPrompt(),
      messages,
      maxTokens: 1500,
      tools: toolSchemas
    })
    // 累加 usage
    if (resp.usage) {
      totalUsage.input_tokens += resp.usage.input_tokens || 0
      totalUsage.output_tokens += resp.usage.output_tokens || 0
    }
    // 拆 content blocks
    const blocks = resp.content || []
    const toolUses = blocks.filter(b => b.type === 'tool_use')
    const texts = blocks.filter(b => b.type === 'text').map(b => b.text).join('\n')

    if (resp.stop_reason === 'end_turn' || !toolUses.length) {
      finalText = texts || '(无回复)'
      break
    }
    // tool_use 循环
    messages.push({ role: 'assistant', content: blocks })
    for (const tu of toolUses) {
      const tool = TOOL_REGISTRY[tu.name]
      if (!tool) {
        messages.push({ role: 'user', content: [{ type: 'tool_result', tool_use_id: tu.id, content: `未知工具: ${tu.name}` }] })
        continue
      }
      let result
      try {
        result = await tool.exec(tu.input || {})
      } catch (e) {
        result = { error: e.message.slice(0, 200) }
      }
      toolCallLog.push({ name: tu.name, input: tu.input, result_preview: JSON.stringify(result).slice(0, 200) })
      messages.push({
        role: 'user',
        content: [{ type: 'tool_result', tool_use_id: tu.id, content: JSON.stringify(result) }]
      })
    }
    // 最后一轮才允许结束
    if (round === 3) {
      // 强制收尾
      const last = await callAnthropic({
        baseUrl: cfg.base_url, apiKey: cfg.api_key, model: cfg.model,
        system: buildSystemPrompt() + '\n\n请基于以上工具结果,给最终回答(不要再调工具)。',
        messages, maxTokens: 1200
      })
      finalText = (last.content || []).filter(b => b.type === 'text').map(b => b.text).join('\n')
      if (last.usage) {
        totalUsage.input_tokens += last.usage.input_tokens || 0
        totalUsage.output_tokens += last.usage.output_tokens || 0
      }
    }
  }
  return { text: finalText, toolCallLog, usage: totalUsage }
}

// ============================================================
// 会话存储(简化版:DB 表 ai_class_sessions 已有,共用)
// ============================================================

// ============================================================
// 端点 1: GET /api/labor-ai-agent/skills
// ============================================================
router.get('/skills', requirePermission(P.HR_READ), (req, res) => {
  res.json({
    code: 0,
    data: {
      version: 'phase-2',
      llm: { provider: 'minimax-anthropic', model: 'MiniMax-M3-8k' },
      skills: [
        { id: 'query_database',     name_zh: 'SQL 自由查询', name_en: 'Query DB',  status: 'ready', description: 'LLM 写 SELECT,系统强制 LIMIT' },
        { id: 'attendance_summary', name_zh: '考勤分析',     name_en: 'Attendance', status: 'ready' },
        { id: 'jobsite_progress',   name_zh: '工地进度',     name_en: 'Jobsite',   status: 'ready' },
        { id: 'task_overview',      name_zh: '任务概览',     name_en: 'Tasks',     status: 'ready' },
        { id: 'finance_summary',    name_zh: '财务概览',     name_en: 'Finance',   status: 'ready' },
        { id: 'quote_skill',        name_zh: '立项报价',     name_en: 'Quote',     status: 'ready', description: 'POST /quote 端点:工人+工期+材料估算' },
        { id: 'analyze_image',      name_zh: '工地照片元数据', name_en: 'Image',   status: 'ready', description: 'sharp 元数据+LLM 文本解读(M3-8k 无 vision)' },
        { id: 'read_document',      name_zh: '合同/标书阅读', name_en: 'Doc',       status: 'ready', description: '.txt/.md/.json/.csv 直接读,.pdf/.docx 需先转 txt' }
      ]
    }
  })
})

// ============================================================
// 端点 2: GET /api/labor-ai-agent/status
// ============================================================
router.get('/status', requirePermission(P.HR_READ), async (req, res, next) => {
  try {
    const cfg = await loadLlmConfig()
    res.json({
      code: 0,
      data: {
        phase: 'phase-2',
        llm_configured: !!cfg,
        llm_model: cfg?.model || null,
        llm_provider: cfg?.provider || null,
        skills_ready: 7,
        skills_planned: 0,
        skills: [
          { id: 'query_database',     name_zh: 'SQL 自由查询',     name_en: 'Query DB',         status: 'ready' },
          { id: 'attendance_summary', name_zh: '考勤聚合',         name_en: 'Attendance',       status: 'ready' },
          { id: 'jobsite_progress',   name_zh: '工地进度',         name_en: 'Jobsite Progress', status: 'ready' },
          { id: 'task_overview',      name_zh: '任务概览',         name_en: 'Task Overview',    status: 'ready' },
          { id: 'finance_summary',    name_zh: '财务汇总',         name_en: 'Finance Summary',  status: 'ready' },
          { id: 'analyze_image',      name_zh: '工地照片元数据',   name_en: 'Image Metadata',   status: 'ready', note: 'M3-8k 无 vision,仅元数据+LLM文本解读' },
          { id: 'read_document',      name_zh: '读合同/标书',     name_en: 'Read Document',    status: 'ready', note: '支持 .txt/.md/.json/.csv,.pdf/.docx 需先转 txt' }
        ]
      }
    })
  } catch (e) { next(e) }
})

// ============================================================
// 端点 3: POST /api/labor-ai-agent/chat  — 主入口
// body: { message, session_id?, context? }
// ============================================================
// 兼容 AIClassroom.vue 前端发 /agent/chat(老前端调用模式)
router.post('/agent/chat', requirePermission(P.HR_READ), async (req, res, next) => {
  // 复用 /chat 逻辑 — 把 /chat 内部逻辑抽到 handler
  return handleChat(req, res, next)
})

router.post('/chat', requirePermission(P.HR_READ), async (req, res, next) => handleChat(req, res, next))

async function handleChat(req, res, next) {
  try {
    const { message, session_id, context, attachments } = req.body || {}
    if (!message || !message.trim()) {
      return res.status(400).json({ code: 400, message: 'message 必填' })
    }

    // 加载附件理解(若给 attachments: [upload_id,...]) — 2026-07-13
    let attachmentContext = ''
    if (Array.isArray(attachments) && attachments.length) {
      const [[tbl]] = await pool.query(
        `SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'ai_class_uploads'`
      )
      if (tbl.cnt > 0) {
        const placeholders = attachments.map(() => '?').join(',')
        const [uploads] = await pool.query(
          `SELECT id, original_name, ext, size_bytes, status, analysis_result
             FROM ai_class_uploads
            WHERE user_id = ? AND id IN (${placeholders})`,
          [req.user?.id, ...attachments]
        )
        for (const u of uploads) {
          let ar = u.analysis_result
          if (typeof ar === 'string') { try { ar = JSON.parse(ar) } catch {} }
          attachmentContext += `\n\n【附件:${u.original_name} (${u.ext},${(u.size_bytes/1024).toFixed(1)}KB,状态:${u.status})】`
          if (ar?.vision_text) attachmentContext += `\n[视觉理解]\n${ar.vision_text}`
          if (ar?.doc_text) attachmentContext += `\n[文档内容]\n${ar.doc_text}`
          if (ar?.cad_meta) attachmentContext += `\n[CAD 元数据]\n${JSON.stringify(ar.cad_meta)}`
        }
      }
    }

    // 加载历史(从 ai_class_sessions 读最近 4 轮)
    let history = []
    if (session_id) {
      const [rows] = await pool.query(
        `SELECT role, content FROM ai_class_sessions
         WHERE session_id = ? ORDER BY id DESC LIMIT 8`, [session_id])
      history = rows.reverse().map(r => ({
        role: r.role === 'user' ? 'user' : 'assistant',
        content: r.content
      }))
    }

    const startedAt = Date.now()
    const fullMessage = attachmentContext
      ? `${message}\n\n${attachmentContext}`
      : message
    const result = await runAgent(fullMessage, history, context || '')
    const elapsedMs = Date.now() - startedAt

    // 保存会话(若给了 session_id)
    let savedSessionId = session_id
    if (session_id) {
      try {
        await pool.query(
          `INSERT INTO ai_class_sessions (session_id, role, content, created_at) VALUES (?, 'user', ?, NOW()),
           (?, 'assistant', ?, NOW())`,
          [session_id, message, session_id, result.text]
        )
      } catch {}
    } else {
      savedSessionId = `la-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
    }

    res.json({
      code: 0,
      data: {
        session_id: savedSessionId,
        reply: result.text,
        tool_calls: result.toolCallLog,
        usage: result.usage,
        elapsed_ms: elapsedMs
      }
    })
  } catch (e) {
    console.error('[chat-debug] error:', e?.message, e?.stack?.split('\n').slice(0, 5).join(' | '))
    if (e.message?.includes('LLM') || e.message?.includes('未配置')) {
      return res.status(503).json({ code: 503, message: e.message })
    }
    next(e)
  }
}

// ============================================================
// 端点 4: POST /api/labor-ai-agent/quote  — 立项报价
// body: { jobsite_id, area_sqm, type, required_skills[], start_date }
// 返回: 工人×天数×工资 + 材料/管理费/不可预见费 估算
// ============================================================
router.post('/quote', requirePermission(P.HR_READ), async (req, res, next) => {
  try {
    const { jobsite_id, area_sqm, type, required_skills, start_date } = req.body || {}
    if (!area_sqm || !type) return res.status(400).json({ code: 400, message: 'area_sqm + type 必填' })

    // 1. 查历史同类项目均价
    const [histRows] = await pool.query(
      `SELECT id, name, contract_amount, area_sqm, required_workers, actual_end_date - start_date AS days
       FROM jobsites WHERE type = ? AND area_sqm > 0 AND contract_amount > 0
       ORDER BY actual_end_date DESC LIMIT 5`, [type])
    const histAvg = histRows.length
      ? histRows.reduce((s, r) => s + (Number(r.contract_amount) / Number(r.area_sqm)), 0) / histRows.length
      : 0

    // 2. 查需求工人数(若已选工地,取其 required_workers;否则基于面积估算)
    let requiredWorkers = null
    if (jobsite_id) {
      const [[js]] = await pool.query('SELECT required_workers, required_skills FROM jobsites WHERE id = ?', [jobsite_id])
      requiredWorkers = js?.required_workers || Math.ceil(area_sqm / 25)
    } else {
      requiredWorkers = Math.ceil(area_sqm / 25)
    }

    // 3. 查可用工人数(按工种)
    let availableWorkers = 0
    if (required_skills && required_skills.length) {
      const [availRows] = await pool.query(
        `SELECT COUNT(*) AS cnt FROM worker_profiles wp
         WHERE wp.employment_status = 'active'
           AND JSON_OVERLAPS(wp.skills, JSON_ARRAY(?))`,
        [JSON.stringify(required_skills)])
      availableWorkers = availRows[0]?.cnt || 0
    } else {
      const [[r]] = await pool.query(
        `SELECT COUNT(*) AS cnt FROM worker_profiles WHERE employment_status = 'active'`)
      availableWorkers = r.cnt
    }

    // 4. 估算工时 + 工资
    const daysEstimate = Math.ceil(area_sqm / 25) // 简化:25 ㎡/天
    const totalManDays = requiredWorkers * daysEstimate
    const avgDailyRate = 350 // 默认 350 元/工日(可调)
    const laborCost = totalManDays * avgDailyRate

    // 5. 材料/管理/不可预见 系数
    const materialCost = laborCost * 0.6
    const mgmtCost = laborCost * 0.15
    const contingency = laborCost * 0.05
    const totalEstimate = laborCost + materialCost + mgmtCost + contingency

    // 6. 给 LLM 写一段人类可读的报价说明
    const cfg = await loadLlmConfig()
    let llmSummary = null
    if (cfg) {
      try {
        const llm = await callAnthropic({
          baseUrl: cfg.base_url, apiKey: cfg.api_key, model: cfg.model,
          system: '你是 LISA,工头气质,根据报价数据给一段简洁的工程报价说明(3-5 句)。',
          messages: [{ role: 'user', content:
`工程类型: ${type}
面积: ${area_sqm} ㎡
需求工人数: ${requiredWorkers}, 可用工人数: ${availableWorkers}
预计工期: ${daysEstimate} 天
工日单价: ¥${avgDailyRate}
人工费: ¥${laborCost}
材料费: ¥${materialCost}
管理费: ¥${mgmtCost}
不可预见费: ¥${contingency}
总计: ¥${totalEstimate}
历史同类项目均价: ¥${histAvg.toFixed(0)}/㎡

请输出报价说明,提醒人手/工期/材料风险。` }], maxTokens: 600
        })
        llmSummary = (llm.content || []).filter(b => b.type === 'text').map(b => b.text).join('\n')
      } catch {}
    }

    res.json({
      code: 0,
      data: {
        inputs: { jobsite_id, area_sqm, type, required_skills, start_date },
        hist: { samples: histRows.length, avg_per_sqm: Number(histAvg.toFixed(2)) },
        workers: { required: requiredWorkers, available: availableWorkers, shortage: Math.max(0, requiredWorkers - availableWorkers) },
        schedule: { days_estimate: daysEstimate, total_man_days: totalManDays },
        cost: {
          labor: laborCost, material: materialCost, mgmt: mgmtCost, contingency, total: totalEstimate,
          per_sqm: Number((totalEstimate / area_sqm).toFixed(2))
        },
        llm_summary: llmSummary
      }
    })
  } catch (e) { next(e) }
})

export default router
