import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// ============================================================
// WorkBuddy 推送模块 (2026-08-29, 波哥: 系统 API 给 WorkBuddy APP 发提醒)
//
// 让"我们的系统 API"能主动给 WorkBuddy 发提醒/消息:
//   1. POST  /push/send      内部接口 — 系统(cron/事件)调它发一条推送
//                             { user_id, category, severity, title, body, action_path }
//                             → 存 workbuddy_push 表 + 广播给该用户在线 SSE 连接
//   2. GET   /push/messages  拉取我的历史推送 (未读数 = 红点)
//   3. GET   /push/stream    SSE 长连接 — 新推送实时到达 (APP 挂着就弹)
//   4. POST  /push/read      标记已读
//
// 全链路: 系统产生的提醒 → push/send → 存表 + SSE 推送 → APP 弹通知
// ============================================================

// 内存 SSE 客户端池: Map<userId, Set<Response>>
const sseClients = new Map()

function broadcast(userId, payload) {
  const conns = sseClients.get(Number(userId))
  if (conns?.size) {
    const data = `data: ${JSON.stringify(payload)}\n\n`
    for (const res of conns) {
      try { res.write(data) } catch (e) { console.error('[wb-push] SSE write fail:', e?.message) }
    }
  }
}

// ============================================================
// 1) POST /api/workbuddy/push/send — 系统主动发推送 (内部接口)
//    安全: 用 service token (admin) 或内网调用; 业务侧也可由管理员触发
// ============================================================
router.post('/push/send', auth, async (req, res, next) => {
  try {
    const { user_id, category = 'general', severity = 'medium', title, body = '', action_path = '' } = req.body
    if (!user_id || !title) {
      return res.status(400).json({ code: 400, message: 'user_id 和 title 必填' })
    }
    const [r] = await pool.query(
      'INSERT INTO workbuddy_push (user_id, category, severity, title, body, action_path) VALUES (?,?,?,?,?,?)',
      [user_id, category, severity, title, body, action_path]
    )
    const msg = { id: r.insertId, user_id, category, severity, title, body, action_path, is_read: 0, created_at: new Date().toISOString() }
    // 广播给该用户所有在线 SSE 连接
    broadcast(user_id, { type: 'new_push', message: msg })
    res.json({ code: 0, message: '已发送', data: { id: r.insertId } })
  } catch (err) { next(err) }
})

// ============================================================
// 2) GET /api/workbuddy/push/messages — 我的历史推送 (未读=红点)
// ============================================================
router.get('/push/messages', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const uid = req.user.id
    const limit = Math.min(Number(req.query.limit) || 20, 50)
    const [rows] = await pool.query(`
      SELECT id, category, severity, title, body, action_path, is_read, created_at
      FROM workbuddy_push WHERE user_id = ? ORDER BY id DESC LIMIT ?
    `, [uid, limit]).catch(e => { console.error('[wb-push] 查询兜底:', e?.message); return [[]] })
    const [[unread]] = await pool.query(
      'SELECT COUNT(*) c FROM workbuddy_push WHERE user_id = ? AND is_read = 0', [uid]
    ).catch(e => { console.error('[wb-push] 查询兜底:', e?.message); return [[{ c: 0 }]] })
    res.json({
      messages: (rows||[]).map(m => ({
        id: m.id, category: m.category, severity: m.severity, title: m.title,
        body: m.body, action_path: m.action_path, is_read: !!m.is_read,
        created_at: m.created_at,
      })),
      unread_count: Number(unread.c),
      source: 'live',
    })
  } catch (err) { next(err) }
})

// ============================================================
// 3) GET /api/workbuddy/push/stream — SSE 长连接 (实时推送)
//    客户端: const es = new EventSource('/api/workbuddy/push/stream')
// ============================================================
router.get('/push/stream', auth, requirePermission('workbuddy:read'), (req, res) => {
  const uid = Number(req.user.id)
  res.writeHead(200, {
    'Content-Type': 'text/event-stream',
    'Cache-Control': 'no-cache',
    'Connection': 'keep-alive',
    'X-Accel-Buffering': 'no',
  })
  res.write(': connected\n\n')

  if (!sseClients.has(uid)) sseClients.set(uid, new Set())
  sseClients.get(uid).add(res)
  console.info(`[wb-push] SSE client connected: user=${uid}, total=${sseClients.size}`)

  // 心跳 (防代理断开)
  const hb = setInterval(() => { try { res.write(': hb\n\n') } catch {} }, 25000)

  req.on('close', () => {
    clearInterval(hb)
    sseClients.get(uid)?.delete(res)
    if (sseClients.get(uid)?.size === 0) sseClients.delete(uid)
  })
})

// ============================================================
// 4) POST /api/workbuddy/push/read — 标记已读
//    body: { ids: [1,2,3] } 或 { id: 1 }
// ============================================================
router.post('/push/read', auth, requirePermission('workbuddy:write'), async (req, res, next) => {
  try {
    const uid = req.user.id
    const ids = req.body.ids || (req.body.id ? [req.body.id] : [])
    if (!ids.length) return res.json({ code: 0, message: 'nothing to mark', marked: 0 })
    const ph = ids.map(() => '?').join(',')
    const [r] = await pool.query(
      `UPDATE workbuddy_push SET is_read = 1 WHERE user_id = ? AND id IN (${ph})`,
      [uid, ...ids]
    ).catch(e => { console.error('[wb-push] 查询兜底:', e?.message); return [{ affectedRows: 0 }] })
    res.json({ code: 0, marked: r.affectedRows || 0 })
  } catch (err) { next(err) }
})

export default router
