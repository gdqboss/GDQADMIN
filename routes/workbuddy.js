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
    const [rows] = await pool.query(`
      SELECT id, title, start_time, end_time, location, type
      FROM calendar_events
      WHERE DATE(start_time) = CURDATE()
      ORDER BY start_time ASC
      LIMIT 20
    `).catch(() => [[]])

    res.json({
      events: (rows || []).map(r => ({
        id: r.id,
        time: r.start_time,
        title: r.title,
        location: r.location || '',
        type: r.type || 'meeting',
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
    const [rows] = await pool.query(`
      SELECT id, sender, subject, preview, priority, status, created_at
      FROM workflow_inbox
      WHERE status IN ('pending', 'snoozed')
        AND deleted_at IS NULL
      ORDER BY priority DESC, created_at DESC
      LIMIT 10
    `).catch(() => [[]])

    res.json({
      items: (rows || []).map(r => ({
        id: r.id,
        sender: (r.sender || '??').slice(0, 2).toUpperCase(),
        subject: r.subject || '()',
        preview: r.preview || '',
        tags: r.priority === 'high'
          ? [{ text: 'Action', cls: 'green' }]
          : r.priority === 'medium'
          ? [{ text: 'Review', cls: 'amber' }]
          : [{ text: 'Update', cls: 'gray' }],
        received_at: r.created_at,
      })),
      updated_at: new Date().toISOString(),
      source: 'live',
    })
  } catch (err) {
    next(err)
  }
})

/**
 * GET /api/workbuddy/task-summary - 
 */
router.get('/task-summary', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [[row]] = await pool.query(`
      SELECT
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        COUNT(*) AS total
      FROM approvals
      WHERE assigned_to = ?
    `, [req.user.id]).catch(() => [[{ completed: 0, in_progress: 0, pending: 0, total: 0 }]])

    const r = row || { completed: 0, in_progress: 0, pending: 0, total: 0 }
    const total = r.total || 1
    res.json({
      completed: r.completed,
      inProgress: r.in_progress,
      pending: r.pending,
      pctDone: Math.round((r.completed / total) * 100),
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

export default router
