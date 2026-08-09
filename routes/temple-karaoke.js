// 佛经卡拉OK同步系统 — 2026-08-07 大道庵 (dda.gdqshop.cn)
// 设计: 主持人 APP 控制 → 中央 session → N 个大屏 WebSocket 同步播放经文字幕
// 数据源: temple_sutras (经典列表) + temple_sutra_lines (每句原文+拼音+时间)
//         + temple_karaoke_sessions (主持会话: 当前句/位置/速度) + temple_karaoke_clients (在线终端)
// AI 工具联动: get_temple_dashboard 已识别 "功德/扫码/牌位" 三大问题,本模块是给"寺"再加一个 AI 化维度 = "事·宗教生活"
import express from 'express'
import { WebSocketServer } from 'ws'
import http from 'http'
import { pool } from '../db.js'

const router = express.Router()

// ============ 内存中的会话广播器 ============
// 每个 session 一个 Map<client_id, ws>, 用于广播
const sessionClients = new Map() // session_id -> Map<client_id, ws>

function broadcastToSession(sessionId, msg) {
  const map = sessionClients.get(sessionId)
  if (!map) return 0
  const data = JSON.stringify(msg)
  let count = 0
  for (const [cid, ws] of map.entries()) {
    if (ws.readyState === 1) { // OPEN
      try { ws.send(data); count++ } catch (e) { /* skip */ }
    }
  }
  return count
}

// ============ REST 端点 ============

// GET /api/temple/karaoke/sutras — 经典列表
router.get('/sutras', async (req, res) => {
  try {
    const profileId = req.query.profile_id || parseInt(req.headers['x-default-profile-id']) || 11
    const [rows] = await pool.query(
      `SELECT id, code, title_zh, title_pinyin, author, description,
              total_lines, estimated_duration_sec, cover_url, audio_url, view_count
       FROM temple_sutras
       WHERE is_active = 1 AND (server_profile_id = ? OR server_profile_id = 1)
       ORDER BY sort_order DESC, id ASC`,
      [profileId]
    )
    res.json({ success: true, data: rows, count: rows.length })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// GET /api/temple/karaoke/sutra/:id — 经典详情 + 全部经文
router.get('/sutra/:id', async (req, res) => {
  try {
    const [sutraRows] = await pool.query(
      `SELECT id, code, title_zh, title_pinyin, author, description,
              total_lines, estimated_duration_sec
       FROM temple_sutras WHERE id = ?`,
      [req.params.id]
    )
    if (sutraRows.length === 0) return res.status(404).json({ success: false, message: '经典不存在' })
    const [lines] = await pool.query(
      `SELECT id, line_no, content_zh, pinyin, start_sec, end_sec
       FROM temple_sutra_lines WHERE sutra_id = ? ORDER BY line_no`,
      [req.params.id]
    )
    res.json({ success: true, data: { ...sutraRows[0], lines } })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// POST /api/temple/karaoke/session/start — 主持人开新 session
router.post('/session/start', async (req, res) => {
  try {
    const profileId = req.body.profile_id || parseInt(req.headers['x-default-profile-id']) || 11
    const { sutra_id, chapter_no = 1, host_user_id = null, host_name = 'Anonymous' } = req.body
    if (!sutra_id) return res.status(400).json({ success: false, message: 'sutra_id 必填' })
    // 关掉旧 session
    await pool.query(
      `UPDATE temple_karaoke_sessions SET status='ended', ended_at=NOW()
       WHERE server_profile_id=? AND status IN ('idle','playing','paused')`,
      [profileId]
    )
    // 开新 session
    const [r] = await pool.query(
      `INSERT INTO temple_karaoke_sessions
        (server_profile_id, sutra_id, chapter_no, host_user_id, host_name, status, current_line_no, position_sec)
       VALUES (?, ?, ?, ?, ?, 'idle', 0, 0)`,
      [profileId, sutra_id, chapter_no, host_user_id, host_name]
    )
    res.json({ success: true, data: { session_id: r.insertId, sutra_id, chapter_no, status: 'idle' } })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// GET /api/temple/karaoke/session/:id — 获取 session 当前状态
router.get('/session/:id', async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT s.id, s.sutra_id, s.chapter_no, s.status, s.current_line_no, s.position_sec,
              s.speed, s.is_audio_enabled, s.show_pinyin, s.font_size, s.host_name,
              s.started_at, s.updated_at,
              su.title_zh AS sutra_title, su.title_pinyin AS sutra_pinyin
       FROM temple_karaoke_sessions s
       LEFT JOIN temple_sutras su ON su.id = s.sutra_id
       WHERE s.id = ?`,
      [req.params.id]
    )
    if (rows.length === 0) return res.status(404).json({ success: false, message: 'session 不存在' })
    // 拿前后句 (当前句 ±5)
    const curLine = rows[0].current_line_no
    const [lines] = await pool.query(
      `SELECT id, line_no, content_zh, pinyin, start_sec, end_sec
       FROM temple_sutra_lines
       WHERE sutra_id = ? AND line_no BETWEEN ? AND ?
       ORDER BY line_no`,
      [rows[0].sutra_id, Math.max(1, curLine - 5), curLine + 5]
    )
    // 拿全部句数
    const [totalRows] = await pool.query(
      `SELECT COUNT(*) AS total FROM temple_sutra_lines WHERE sutra_id = ?`,
      [rows[0].sutra_id]
    )
    res.json({
      success: true,
      data: {
        ...rows[0],
        context_lines: lines,
        total_lines: totalRows[0].total,
        ws_clients: sessionClients.get(parseInt(req.params.id))?.size || 0
      }
    })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// POST /api/temple/karaoke/session/:id/control — 主持人控制 (play/pause/seek/speed/set-line)
router.post('/session/:id/control', async (req, res) => {
  try {
    const sessionId = parseInt(req.params.id)
    const { action, line_no, position_sec, speed, show_pinyin, font_size } = req.body
    if (!action) return res.status(400).json({ success: false, message: 'action 必填' })

    const updates = []
    const params = []
    let newStatus = null

    if (action === 'play') {
      updates.push("status='playing'", 'started_at=COALESCE(started_at, NOW())')
      newStatus = 'playing'
    } else if (action === 'pause') {
      updates.push("status='paused'")
      newStatus = 'paused'
    } else if (action === 'stop') {
      updates.push("status='ended'", 'ended_at=NOW()')
      newStatus = 'ended'
    } else if (action === 'seek_line' && line_no != null) {
      updates.push('current_line_no=?', 'position_sec=(SELECT start_sec FROM temple_sutra_lines WHERE sutra_id=(SELECT sutra_id FROM temple_karaoke_sessions WHERE id=?) AND line_no=?)')
      params.push(line_no, sessionId, line_no)
    } else if (action === 'seek_pos' && position_sec != null) {
      updates.push('position_sec=?')
      params.push(position_sec)
    } else if (action === 'speed' && speed != null) {
      updates.push('speed=?')
      params.push(Math.max(0.25, Math.min(2.0, speed)))
    } else if (action === 'toggle_pinyin' && show_pinyin != null) {
      updates.push('show_pinyin=?')
      params.push(show_pinyin ? 1 : 0)
    } else if (action === 'font_size' && font_size) {
      updates.push('font_size=?')
      params.push(font_size)
    } else if (action === 'tick' && position_sec != null && line_no != null) {
      // 自动播放 tick — 大屏自己调
      updates.push('position_sec=?', 'current_line_no=?')
      params.push(position_sec, line_no)
    } else {
      return res.status(400).json({ success: false, message: 'action 无效: ' + action })
    }

    updates.push('updated_at=NOW()')
    const sql = `UPDATE temple_karaoke_sessions SET ${updates.join(', ')} WHERE id=?`
    params.push(sessionId)
    await pool.query(sql, params)

    // 广播给所有订阅者
    const clientCount = broadcastToSession(sessionId, {
      type: 'control',
      action,
      line_no,
      position_sec,
      speed,
      show_pinyin,
      font_size,
      status: newStatus,
      ts: Date.now()
    })

    res.json({ success: true, broadcast_clients: clientCount, action })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// POST /api/temple/karaoke/session/:id/join — 终端加入 (注册 device)
router.post('/session/:id/join', async (req, res) => {
  try {
    const { client_id, device_name, client_type } = req.body
    if (!client_id) return res.status(400).json({ success: false, message: 'client_id 必填' })
    await pool.query(
      `INSERT INTO temple_karaoke_clients (session_id, client_id, device_name, client_type, last_seen_at)
       VALUES (?, ?, ?, ?, NOW())
       ON DUPLICATE KEY UPDATE last_seen_at=NOW(), device_name=VALUES(device_name)`,
      [req.params.id, client_id, device_name || 'Unknown', client_type || 'display']
    )
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

export default router

// ============ WebSocket 服务 ============
// 单独挂载在 /ws/temple/karaoke/:session_id
export function attachKaraokeWS(wss) {
  wss.on('connection', async (ws, req) => {
    // 解析 session_id from URL: /ws/temple/karaoke/123
    const m = req.url.match(/\/ws\/temple\/karaoke\/(\d+)/)
    if (!m) { ws.close(1008, 'bad url'); return }
    const sessionId = parseInt(m[1])
    const clientId = req.headers['sec-websocket-key'] || `anon-${Date.now()}-${Math.random().toString(36).slice(2,8)}`

    // 注册到广播 map
    if (!sessionClients.has(sessionId)) sessionClients.set(sessionId, new Map())
    sessionClients.get(sessionId).set(clientId, ws)

    console.log(`[KARAOKE WS] session=${sessionId} client=${clientId} 已连接 (总 ${sessionClients.get(sessionId).size})`)

    // 立即推一条 welcome
    try {
      const [rows] = await pool.query(
        `SELECT s.*, su.title_zh AS sutra_title, su.title_pinyin AS sutra_pinyin
         FROM temple_karaoke_sessions s
         LEFT JOIN temple_sutras su ON su.id = s.sutra_id
         WHERE s.id = ?`,
        [sessionId]
      )
      if (rows.length > 0) {
        ws.send(JSON.stringify({ type: 'welcome', session: rows[0], client_id: clientId, ts: Date.now() }))
      } else {
        ws.send(JSON.stringify({ type: 'error', message: 'session 不存在', ts: Date.now() }))
      }
    } catch (e) {
      ws.send(JSON.stringify({ type: 'error', message: e.message }))
    }

    // 心跳
    let isAlive = true
    ws.on('pong', () => { isAlive = true })
    const pingInterval = setInterval(() => {
      if (!isAlive) { ws.terminate(); return }
      isAlive = false
      try { ws.ping() } catch (e) { /* skip */ }
    }, 30000)

    // 接收消息 (大屏 tick / 主持人 control)
    ws.on('message', async (data) => {
      try {
        const msg = JSON.parse(data.toString())
        if (msg.type === 'tick') {
          // 大屏自动播放上报
          await pool.query(
            `UPDATE temple_karaoke_sessions SET position_sec=?, current_line_no=? WHERE id=?`,
            [msg.position_sec, msg.line_no, sessionId]
          )
          // 转发给其他终端 (除自己)
          const map = sessionClients.get(sessionId)
          if (map) {
            const payload = JSON.stringify({ type: 'tick', position_sec: msg.position_sec, line_no: msg.line_no, ts: Date.now() })
            for (const [cid, w] of map.entries()) if (cid !== clientId && w.readyState === 1) w.send(payload)
          }
        } else if (msg.type === 'control') {
          // 主持人从 APP 推送
          const updates = []
          const params = []
          if (msg.action === 'play') { updates.push("status='playing'"); updates.push('started_at=COALESCE(started_at, NOW())') }
          else if (msg.action === 'pause') updates.push("status='paused'")
          else if (msg.action === 'stop') { updates.push("status='ended'"); updates.push('ended_at=NOW()') }
          else if (msg.action === 'next_line') updates.push('current_line_no = current_line_no + 1')
          else if (msg.action === 'prev_line') updates.push('current_line_no = GREATEST(1, current_line_no - 1)')
          if (msg.speed != null) updates.push('speed=?'), params.push(msg.speed)
          if (updates.length > 0) {
            updates.push('updated_at=NOW()')
            await pool.query(`UPDATE temple_karaoke_sessions SET ${updates.join(', ')} WHERE id=?`, [...params, sessionId])
          }
          broadcastToSession(sessionId, { type: 'control', ...msg, ts: Date.now() })
        }
      } catch (e) {
        ws.send(JSON.stringify({ type: 'error', message: e.message }))
      }
    })

    // 断开
    ws.on('close', () => {
      clearInterval(pingInterval)
      const map = sessionClients.get(sessionId)
      if (map) { map.delete(clientId); if (map.size === 0) sessionClients.delete(sessionId) }
      console.log(`[KARAOKE WS] session=${sessionId} client=${clientId} 已断开 (剩 ${map?.size || 0})`)
    })

    ws.on('error', (e) => {
      console.error(`[KARAOKE WS] session=${sessionId} client=${clientId} 错误:`, e.message)
    })
  })
  console.log('[KARAOKE WS] 已挂载 /ws/temple/karaoke/:session_id')
}