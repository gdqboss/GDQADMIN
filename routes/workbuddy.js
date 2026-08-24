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

const router = Router()

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
router.post('/chat', auth, requirePermission('workbuddy:write'), async (req, res, next) => {
  try {
    const { text } = req.body || {}
    if (!text || typeof text !== 'string') {
      return res.status(400).json({ error: 'text required' })
    }

    //  jxy-os  KB (Python subprocess,  sys.path )
    const { spawnSync } = await import('node:child_process')
    const py = spawnSync('python3', [
      '/root/jxy-os/modules/jxy_rag_kb.py',
      'search',
      ...text.split(/\s+/).filter(Boolean).slice(0, 3), //  3  token  query
    ], { encoding: 'utf-8', timeout: 8000 })

    const hits = py.stdout ? py.stdout.slice(0, 1500) : ''

    // GLM-4-flash : KB +  LLM (2-5s);  KB raw
    let assistant = ''
    let source = 'live'
    try {
      let glmKey = process.env.GLM_API_KEY || ''
      if (!glmKey) {
        for (const line of readFileSync('/root/jxy-os/.env', 'utf8').split('\n')) {
          if (line.startsWith('AI_APIKEY=')) { glmKey = line.split('=')[1].trim(); break }
        }
      }
      if (glmKey) {
        const prompt = `WorkBuddy AI\n\nKB:\n${hits || ''}\n\nQ: ${text}\n\nA 200-300 char answer in the user's language.`
        const body = JSON.stringify({ model: 'glm-4-flash', messages: [{ role: 'user', content: prompt }], max_tokens: 500 })
        const { writeFileSync } = await import('node:fs')
        const bodyFile = `/tmp/wb_glm_body_${Date.now()}.json`
        writeFileSync(bodyFile, body)
        try {
          const out = spawnSync('curl', ['-s', '-m', '25',
            'https://open.bigmodel.cn/api/paas/v4/chat/completions',
            '-H', `Authorization: Bearer ${glmKey}`,
            '-H', 'Content-Type: application/json',
            '--data-binary', `@${bodyFile}`],
            { encoding: 'utf-8', timeout: 30000 })
          var data = JSON.parse(out.stdout || '{}')
        } finally {
          try { require('node:fs').unlinkSync && null } catch {}
          spawnSync('rm', ['-f', bodyFile])
        }
        const content = data.choices?.[0]?.message?.content
        if (content && content.trim()) {
          assistant = content.trim() + (hits ? `\n\n📎 来源: ${hits.split('\u2605')[1]?.split('\n')[0] || 'KB'}` : '')
          source = 'live+llm'
        }
      }
    } catch { /* fall through to raw KB */ }

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
