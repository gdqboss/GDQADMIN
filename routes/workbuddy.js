/**
 * WorkBuddy  - staging  ( 2026-08-25 workbuddy-day3)
 *
 * : **staging** -  mount  SGP, INSERT rbac, INSERT server_modules
 */
import { Router } from 'express'
import { readFileSync } from 'node:fs'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'
import wbInventoryRouter from './wb-inventory.js'
import wbOrdersRouter from './wb-orders.js'
import wbProductsRouter from './wb-products.js'
import wbWarehouseRouter from './wb-warehouse.js'
import wbApprovalsRouter from './wb-approvals.js'
import wbFinanceRouter from './wb-finance.js'
import wbActionsRouter from './wb-actions.js'
import wbAttendanceRouter from './wb-attendance.js'
import wbTasksRouter from './wb-tasks.js'
import wbWorklogsRouter from './wb-worklogs.js'
import wbTrainingRouter from './wb-training.js'
import wbWecomRouter from './wb-wecom.js'
import wbMcpRouter, { MCP_TOOLS } from './wb-mcp.js'

const router = Router()

// 2026-08-29: Phase-1 核心业务模块 — 库存/订单/商品/仓库/审批/财务/AI Action
// 2026-08-29: Phase-1.5 — 考勤/任务/日志（波哥指定重点）
router.use(wbInventoryRouter)
router.use(wbOrdersRouter)
router.use(wbProductsRouter)
router.use(wbWarehouseRouter)
router.use(wbApprovalsRouter)
router.use(wbFinanceRouter)
router.use(wbActionsRouter)
router.use(wbAttendanceRouter)
router.use(wbTasksRouter)
router.use(wbWorklogsRouter)
router.use(wbTrainingRouter)
router.use(wbWecomRouter)

// Phase-3: MCP 协议封装（必须放最后，避免 /mcp/* 被其它路由 catch）
router.use(wbMcpRouter)

/**
 * GET /api/workbuddy/health -  ( auth)
 */
router.get('/health', async (req, res) => {
  res.json({ ok: true, mounted: true, version: 'workbuddy-v1-staging' })
})

/**
 * GET /api/workbuddy/stats - WorkBuddy dashboard 4-tile stats
 * : SGP  (orders / products / warehouses)
 */
router.get('/stats', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [
      [[{ unread }]],
      [[{ meetings }]],
      [[{ pending_tasks }]],
      [[{ active_projects }]]
    ] = await Promise.all([
      // "Unread" - inbox : workflow_inbox 
      pool.query(`
        SELECT COUNT(*) AS unread
        FROM workflow_inbox
        WHERE status = 'pending' AND deleted_at IS NULL
      `).catch(() => [[{ unread: 0 }]]),
      // "Meetings" -  calendar events ( system_settings ,)
      pool.query(`
        SELECT COUNT(*) AS meetings
        FROM calendar_events
        WHERE DATE(start_time) = CURDATE()
      `).catch(() => [[{ meetings: 0 }]]),
      // "Tasks" -  ( alerts  approvals ,)
      pool.query(`
        SELECT COUNT(*) AS pending_tasks
        FROM approvals
        WHERE status = 'pending'
      `).catch(() => [[{ pending_tasks: 0 }]]),
      // "Projects" - :  (/)
      pool.query(`
        SELECT COUNT(DISTINCT store_id) AS active_projects
        FROM user_stores
        WHERE user_id = ? AND status = 'active'
      `, [req.user.id]).catch(() => [[{ active_projects: 0 }]])
    ])

    res.json({
      unread,
      meetings,
      tasks: pending_tasks,
      projects: active_projects,
      updated_at: new Date().toISOString(),
      source: 'live',
    })
  } catch (err) {
    next(err)
  }
})

/**
 * GET /api/workbuddy/today-events -  + cron 
 */
router.get('/today-events', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    // calendar_events table doesn't exist; real "today" = Hermes cron jobs firing today
    let jobs = []
    try { jobs = JSON.parse(readFileSync('/root/.hermes/cron/jobs.json', 'utf8')) } catch {}
    if (!Array.isArray(jobs)) jobs = jobs.jobs || []

    const now = new Date()
    const events = []
    for (const j of jobs) {
      if (j.enabled === false) continue
      const sched = j.schedule || {}
      const name = j.name || (j.prompt || '').slice(0, 30) || 'job'
      if (sched.kind === 'cron' && typeof sched.expr === 'string') {
        // parse "m h * * *" style - only handle fixed hour lists / single hours
        const parts = sched.expr.trim().split(/\s+/)
        if (parts.length === 5 && /^[\d*,]+$/.test(parts[0]) && /^[\d*,]+$/.test(parts[1])) {
          const doms = parts[2].split(',').filter(x => x !== '*')
          if (!doms.length || doms.includes(String(now.getDate()))) {
            const hours = parts[1] === '*' ? [] : parts[1].split(',').flatMap(h => {
              if (h.includes('/')) return []
              if (h.includes('-')) { const [a, b] = h.split('-').map(Number); return Array.from({length: b-a+1}, (_, i) => a+i) }
              return [Number(h)]
            })
            const minutes = Number(parts[0]) || 0
            for (const h of (hours.length ? hours : [])) {
              const t = new Date(now); t.setHours(h, minutes, 0, 0)
              if (t >= now) events.push({ id: j.id, time: t.toTimeString().slice(0,5), title: name, type: 'cron', color: 'blue' })
            }
          }
        }
      } else if (sched.kind === 'interval') {
        events.push({ id: j.id, time: `every ${sched.minutes}m`, title: name, type: 'watchdog', color: 'amber' })
      }
    }
    events.sort((a, b) => String(a.time).localeCompare(String(b.time)))

    res.json({
      events: events.slice(0, 12).map(e => ({
        id: e.id, time: e.time, title: e.title,
        location: '', type: e.type,
        duration: e.type === 'watchdog' ? e.time : `${e.time} slot`,
        color: e.color,
      })),
      updated_at: new Date().toISOString(),
      source: 'live',
    })
  } catch (err) {
    next(err)
  }
})

/**
 * GET /api/workbuddy/email-summary - inbox 
 * : workflow_inbox  ( email-like entries)
 */
router.get('/email-summary', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    // Real INBOX via himalaya v2 (--json global flag); PATH needs /root/.local/bin for pm2
    const { spawnSync } = await import('node:child_process')
    const env = { ...process.env, PATH: `${process.env.PATH || ''}:/root/.local/bin` }
    const py = spawnSync('himalaya', ['envelope', 'list', '--page-size', '10', '--json'],
      { encoding: 'utf-8', timeout: 25000, env })

    let items = []
    let source = 'live'
    try {
      const data = JSON.parse(py.stdout || '{}')
      const envs = data.envelopes || []
      items = envs.map(e => ({
        id: e.id,
        sender: ((e.from?.[0]?.name || e.from?.[0]?.email || '??')).slice(0, 2).toUpperCase(),
        subject: e.subject || '(no subject)',
        preview: `From: ${e.from?.[0]?.name || e.from?.[0]?.email || '?'} <${e.from?.[0]?.email || ''}>`,
        tags: e.flags?.includes('\u005cSeen')
          ? [{ text: 'Read', cls: 'gray' }]
          : [{ text: 'New', cls: 'green' }],
        received_at: e.date,
      }))
    } catch {
      source = 'empty'
    }

    if (!items.length) source = 'empty'

    res.json({ items, updated_at: new Date().toISOString(), source })
  } catch (err) {
    next(err)
  }
})

/**
 * GET /api/workbuddy/task-summary - 
 */
router.get('/task-summary', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    // approvals table has no assigned_to - use global status counts + recent items as the task list
    const [rows] = await pool.query(`
      SELECT
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
        COUNT(*) AS total
      FROM approvals
    `).catch(() => [[]])

    const agg = rows?.[0] || { completed: 0, in_progress: 0, rejected: 0, total: 0 }
    const recent = await pool.query(`
      SELECT id, title, urgency, status, created_at
      FROM approvals ORDER BY created_at DESC LIMIT 6
    `).then(([rs]) => rs).catch(() => [])

    const total = agg.total || 1
    res.json({
      completed: Number(agg.completed) || 0,
      inProgress: Number(agg.in_progress) || 0,
      pending: Number(agg.rejected) || 0,
      pctDone: Math.round(((Number(agg.completed) || 0) / total) * 100),
      tasks: (recent || []).map(t => ({
        id: t.id,
        title: t.title || '(untitled)',
        due: t.created_at ? String(t.created_at).slice(5, 10) : '',
        status: t.status === 'approved' ? 'completed' : t.status === 'pending' ? 'in-progress' : 'rejected',
      })),
      updated_at: new Date().toISOString(),
      source: 'live',
    })
  } catch (err) {
    next(err)
  }
})

/**
 * POST /api/workbuddy/chat - WorkBuddy AI chat ( WorkBuddy KB)
 * body: { text: string }
 * :  jxy_rag_kb.py  kb_search  KB, 5  + 
 */
// 精选给 AI 决策的 function-calling 工具（从 MCP_TOOLS 里选问答高频的实时数据工具）
// 注: GLM function calling 一次调用即可命中, 不需要全部 46 个全喂（省 token 且避免误选）
const CHAT_TOOLS = [
  'get_dashboard_stats','get_boss_todo_priorities','get_ai_suggestions',
  'get_inventory_summary','get_inventory_alerts','search_inventory','get_warehouse_inventory',
  'get_orders_summary','get_recent_orders','get_order_detail','search_orders',
  'get_products_summary','search_products','get_product_detail',
  'list_warehouses','get_warehouse_detail',
  'get_pending_approvals','get_approval_history',
  'get_finance_overview','get_finance_recent','get_finance_reminders',
  'get_sales_report','get_daily_report','get_weekly_report',
  'get_attendance_summary','get_attendance_pending',
  'get_tasks_summary','get_pending_tasks','get_overdue_tasks',
  'get_logs_summary','get_today_logs','get_pending_logs',
  'search_training_kb','get_wecom_unread','list_wecom_conversations','list_wecom_contacts',
]
// 转成 GLM function calling schema
const CHAT_TOOLS_SCHEMA = CHAT_TOOLS
  .map(name => MCP_TOOLS.find(t => t.name === name))
  .filter(Boolean)
  .map(({ name, description, inputSchema }) => ({
    type: 'function',
    function: { name, description, parameters: inputSchema || { type: 'object', properties: {} } },
  }))

// 调用 GLM (open.bigmodel.cn), 返回完整响应 (含 tool_calls)
async function callGlm(messages, tools) {
  // GLM key 跨服务器兼容: 优先 server/.env 的 GLM_API_KEY / AI_APIKEY, 再 fallback SGP jxy-os/.env
  let glmKey = process.env.GLM_API_KEY || ''
  if (!glmKey) {
    try {
      for (const line of readFileSync('/root/server/.env', 'utf8').split('\n')) {
        if (line.startsWith('GLM_API_KEY=')) { glmKey = line.split('=')[1].trim(); break }
        if (line.startsWith('AI_APIKEY=')) { glmKey = line.split('=')[1].trim(); break }
      }
    } catch {}
  }
  if (!glmKey) {
    try {
      for (const line of readFileSync('/root/jxy-os/.env', 'utf8').split('\n')) {
        if (line.startsWith('AI_APIKEY=')) { glmKey = line.split('=')[1].trim(); break }
      }
    } catch {}
  }
  if (!glmKey) return null
  const { spawnSync } = await import('node:child_process')
  const { writeFileSync } = await import('node:fs')
  const body = JSON.stringify({ model: 'glm-4-flash', messages, ...(tools ? { tools, tool_choice: 'auto' } : {}), max_tokens: 600 })
  const bodyFile = `/tmp/wb_glm_body_${Date.now()}.json`
  writeFileSync(bodyFile, body)
  try {
    const out = spawnSync('curl', ['-s', '-m', '25',
      'https://open.bigmodel.cn/api/paas/v4/chat/completions',
      '-H', `Authorization: Bearer ${glmKey}`,
      '-H', 'Content-Type: application/json',
      '--data-binary', `@${bodyFile}`],
      { encoding: 'utf-8', timeout: 30000 })
    return JSON.parse(out.stdout || '{}')
  } finally {
    spawnSync('rm', ['-f', bodyFile])
  }
}

// Knowledge Base 检索: 查本服务器 ai_class_knowledge 表 (跨服务器可用, 不再依赖 SGP 本地文件)
// 中文关键词提取: 中文整句无空格 → 用停用词切分提取实词 (比 n-gram 滑窗干净)
const KB_STOP = '的了吗呢吧啊哦呀是在有和与及或这那个我你他她它请帮问想什么怎么哪些介绍一下请问现在今天当前情况看看知道了解可以已经进行了解介绍详细说是为关于对给要着过地得才'
function extractKbKeywords(text) {
  // 1. 英文: 按空格拆 (≥2 字母)
  const en = text.split(/\s+/).filter(w => /^[a-zA-Z]{2,}$/.test(w))
  // 2. 中文: 用停用词切分 → 取 ≥2 字的实词片段
  const cjk = text
  const segs = cjk.split(new RegExp(`[${KB_STOP}]+`)).map(s => s.trim()).filter(s => s.length >= 2)
  // 去重 (保留顺序, 长词优先)
  const cn = []
  const seen = new Set()
  for (const s of segs) {
    for (let l = Math.min(s.length, 6); l >= 2; l--) {
      const sub = s.slice(0, l)
      if (!seen.has(sub)) { seen.add(sub); cn.push(sub) }
    }
  }
  return [...new Set([...en, ...cn, ...segs])].slice(0, 10)
}
async function kbSearch(text) {
  try {
    const words = extractKbKeywords(text)
    if (!words.length) return ''
  // LIKE 检索 title + content (OR 关系, 任一命中即可)
    const conds = words.map(w => `(title LIKE ? OR content LIKE ? OR tags LIKE ?)`)
    const params = []
    for (const w of words) { const like = `%${w}%`; params.push(like, like, like) }
    // 品牌隔离铁律 (波哥 2026-08-29): 彩美特专属品牌内容只服务北京(profile 2), SGP/HK 服务器 WorkBuddy 屏蔽
    // 通过 domain 字段: 彩美特条目 domain='factory', 非 factory 域(如孵化器)天然屏蔽; 但同 factory 域 SGP/HK 也共享,
    // 故额外加 NOT LIKE 彩美特 过滤标签/标题, 双保险
    const sql = `SELECT title, content, doc_type, tags, is_public FROM ai_class_knowledge
      WHERE domain_enabled=1 AND (${conds.join(' OR ')})
        AND title NOT LIKE '%彩美特%' AND content NOT LIKE '%彩美特%' AND tags NOT LIKE '%彩美特%'
      ORDER BY id DESC LIMIT 5`
    const [rows] = await pool.query(sql, params)
    if (!rows.length) return ''
    // 过滤后内容里仍含彩美特的行再剔除一层 (title 可能不含但 content 含)
    const clean = rows.filter(r => !JSON.stringify(r).includes('彩美特'))
    if (!clean.length) return ''
    return clean.map((r, i) => `${'⭐'.repeat(Math.max(1, 3 - i))} [${r.doc_type || 'doc'}] ${r.title}\n${(r.content || '').slice(0, 300)}`).join('\n\n')
  } catch (e) {
    return ''
  }
}

// 内部调用 MCP 实时数据工具（复用当前请求的 Authorization，保留权限语义）
async function mcpFetch(toolName, args, authHeader) {
  try {
    const selfPort = process.env.PORT || 3200
    const resp = await fetch(`http://localhost:${selfPort}/api/workbuddy/mcp/execute`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...(authHeader ? { Authorization: authHeader } : {}) },
      body: JSON.stringify({ tool: toolName, args: args || {} }),
    })
    const json = await resp.json().catch(() => ({}))
    return { status: resp.status, data: json.result ?? json }
  } catch (e) {
    return { status: 0, data: { error: e.message } }
  }
}

router.post('/chat', auth, requirePermission('workbuddy:write'), async (req, res, next) => {
  try {
    const { text } = req.body || {}
    if (!text || typeof text !== 'string') {
      return res.status(400).json({ error: 'text required' })
    }

    // ── 1. KB 检索（查本服务器 ai_class_knowledge 表, 跨服务器可用）──
    const hits = await kbSearch(text)

    // ── 2. GLM function calling 决策: 该问题需要实时经营数据吗? ──
    let assistant = ''
    let source = 'live'
    let toolUsed = null
    let mcpArgs = null

    // 判断该问题是否属于"经营数据查询"类 (节省一次无谓 GLM 调用的启发式)
    const businessKeywords = /库存|预警|订单|商品|产品|仓库|审批|待办|财务|营收|报表|日报|周报|考勤|任务|日志|团队|知识|企微|消息|回收|低库存|销售|提醒|收支|项目|人员|业绩|数据|统计|today|inventory|order|product|finance|alert|approval|task|attendance|report|stock|sales/i
    const needsData = businessKeywords.test(text)

    if (needsData) {
      try {
        const first = await callGlm(
          [{ role: 'user', content: `你是 WorkBuddy 经营助手。判断用户问题是否需要实时业务数据。
如果涉及库存/订单/商品/仓库/审批/财务/报表/考勤/任务/日志/企微 等实时数据, 选择最合适的工具调用(可多选)。
如果问题只是知识/闲聊/公司介绍, 不要调用工具。
用户问题: ${text}` }],
          CHAT_TOOLS_SCHEMA,
        )
        const tc = first?.choices?.[0]?.message?.tool_calls
        if (tc && tc.length) {
          // GLM 决定要数据 → 依次调用工具拿实时数据
          toolUsed = tc.map(x => x.function?.name).filter(Boolean)
          const argsList = tc.map(x => {
            try { return JSON.parse(x.function?.arguments || '{}') } catch { return {} }
          })
          mcpArgs = argsList
          const toolResults = []
          for (let i = 0; i < tc.length; i++) {
            const r = await mcpFetch(tc[i].function.name, argsList[i], req.headers['authorization'])
            toolResults.push({ name: tc[i].function.name, result: r.data })
          }
          // 把工具结果喂回 GLM 合成回答
          const toolMsg = toolResults.map(t => `【${t.name} 返回】\n${JSON.stringify(t.result, null, 1).slice(0, 2000)}`).join('\n\n')
          const second = await callGlm([
            { role: 'system', content: '你是 WorkBuddy 经营助手。根据实时返回的经营数据, 用中文给出准确、简洁、对老板有用的回答。数据为空就明确说"当前暂无数据"。' },
            { role: 'user', content: `${text}\n\n实时数据:\n${toolMsg}` },
          ])
          const answer = second?.choices?.[0]?.message?.content
          if (answer && answer.trim()) {
            assistant = answer.trim() + (hits ? `\n\n📎 知识库: ${hits.split('\u2605')[1]?.split('\n')[0] || ''}` : '')
            source = 'live+llm+tool'
          }
        }
      } catch { /* fall through */ }
    }

    // ── 3. 否则走原 KB 流程 ──
    if (!assistant) {
      // 未触发 function calling (纯 KB 问答 或 GLM 判断无需数据) → 原 KB+LLM 合成
      try {
        const prompt = `WorkBuddy AI\n\nKB:\n${hits || ''}\n\nQ: ${text}\n\nA 200-300 char answer in the user's language.`
        const secondRes = await callGlm([{ role: 'user', content: prompt }])
        const content = secondRes?.choices?.[0]?.message?.content
        if (content && content.trim()) {
          assistant = content.trim() + (hits ? `\n\n📎 来源: ${hits.split('\u2605')[1]?.split('\n')[0] || 'KB'}` : '')
          source = 'live+llm'
        }
      } catch { /* fall through to raw KB */ }
    }

    if (!assistant) {
      assistant =
        (hits.includes('\u2605')
          ? ` KB  ${hits.split('\u2605')[1]?.split('\n')[0] || ''}\n\n`
          : ',KB \n\n') +
        (hits || '')
    }

    res.json({
      user_text: text,
      assistant,
      kb_hits_raw: hits,
      source,
      tool_used: toolUsed,
      mcp_args: mcpArgs,
    })
  } catch (err) {
    next(err)
  }
})

/**
 * GET /api/workbuddy/search?q= - unified: emails + tasks + events
 */
router.get('/search', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const q = String(req.query.q || '').trim()
    if (!q) return res.json({ results: [], source: 'live' })

    // 1) emails via himalaya DSL (subject/body/from)
    const results = []
    try {
      const { spawnSync } = await import('node:child_process')
      const safe = q.replace(/["'\\]/g, '').slice(0, 40)
      const env = { ...process.env, PATH: `${process.env.PATH || ''}:/root/.local/bin` }
      for (const clause of [`subject ${safe}`, `body ${safe}`, `from ${safe}`]) {
        const out = spawnSync('timeout', ['12', 'himalaya', 'envelope', 'search',
          '--page-size', '5', '--json', clause], { encoding: 'utf-8', timeout: 15000, env })
        if (!out.stdout) continue
        const parsed = JSON.parse(out.stdout)
        for (const e of parsed.envelopes || []) {
          if (!results.some(r => r.ref === e.id)) {
            results.push({
              kind: 'Email', ref: e.id,
              title: e.subject || '(no subject)',
              meta: e.from?.[0]?.name || e.from?.[0]?.email || '',
              tag: 'Inbox', date: e.date ? String(e.date).slice(0, 10) : '',
            })
          }
        }
      }
    } catch { /* email search optional */ }

    // 2) tasks via approvals
    try {
      const [rows] = await pool.query(
        `SELECT id, title, status FROM approvals WHERE title LIKE ? ORDER BY id DESC LIMIT 5`,
        [`%${q}%`])
      for (const t of rows || []) {
        results.push({ kind: 'Task', ref: String(t.id), title: t.title || '(untitled)', meta: t.status, tag: 'Approvals' })
      }
    } catch { /* tasks optional */ }

    res.json({
      query: q,
      results: results.slice(0, 15),
      updated_at: new Date().toISOString(),
      source: 'live',
    })
  } catch (err) {
    next(err)
  }
})

/**
 * GET /api/workbuddy/team - staff directory (users user_type='staff')
 */
router.get('/team', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT id, name, email, role,
        CASE WHEN role = 'admin' THEN 'admin' ELSE 'member' END AS kind
      FROM users WHERE user_type = 'staff'
      ORDER BY role = 'admin' DESC, id ASC LIMIT 20
    `).catch(() => [[]])

    res.json({
      members: (rows || []).map(u => ({
        id: u.id,
        name: u.name || `User ${u.id}`,
        contact: u.email || '',
        role: u.role || 'member',
        initials: (u.name || 'U')
          .split(/\s+/).slice(0, 2).map(w => w[0] || '').join('').toUpperCase() || 'U',
        color: u.role === 'admin' ? 'blue' : 'green',
      })),
      updated_at: new Date().toISOString(),
      source: 'live',
    })
  } catch (err) {
    next(err)
  }
})

export default router
