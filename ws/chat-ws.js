/**
 * smart-studio /xchat WebSocket server
 * 2026-08-11 波哥原话「通信都没及时收到」: 前端 WS 连接没后端, 只能 5s 轮询兜底
 *
 * 设计:
 *   - 路径: /ws/chat?token=xxx  (沿用前端, 不破坏 SPA)
 *   - JWT 验证: token → userId (master 也支持, master 收到所有事件但不能发)
 *   - 推送事件:
 *     - new_message: { peer_type, peer_id, message } (发图/发文字后 broadcast)
 *     - read_receipt: { peer_id, last_read_message_id, by } (标记已读后广播)
 *     - presence: { user_id, online, last_seen }
 *     - message_edited: { id, content, edited_by }
 *     - friendship: { type: 'request'|'accept'|'remove', by }
 *
 *   - 接收事件 (前端发来):
 *     - ping → pong (心跳)
 *     - mark_read: { peer_type, peer_id, last_read_message_id }
 *
 *   - 路由策略:
 *     - room 群组: broadcast 给 room 所有成员 (除自己)
 *     - DM: 只 broadcast 给 sender + receiver
 *     - presence: broadcast 给 sender 所有朋友
 *     - admin/all-messages?all=1: master uid=0 收所有
 */

import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'

const JWT_SECRET = process.env.SMART_STUDIO_JWT_SECRET || 'ss-default-secret-change-me-2026'

// 所有在线 ws 连接: user_id (master=0) → Set<ws>
const clients = new Map() // userId → Set<ws>
const wsToUser = new Map() // ws → userId (master=0)

// 全局消息路由: 推送用
function addClient(userId, ws) {
  if (!clients.has(userId)) clients.set(userId, new Set())
  clients.get(userId).add(ws)
  wsToUser.set(ws, userId)
  console.log(`[chat-ws] connect user=${userId} (online=${clients.get(userId).size})`)
}

function removeClient(ws) {
  const userId = wsToUser.get(ws)
  if (userId == null) return
  wsToUser.delete(ws)
  if (clients.has(userId)) {
    clients.get(userId).delete(ws)
    if (clients.get(userId).size === 0) clients.delete(userId)
  }
  console.log(`[chat-ws] disconnect user=${userId} (online=${clients.get(userId)?.size || 0})`)
}

/**
 * broadcastNewMessage — 发图/发文字后调用
 * @param senderId {number}
 * @param peerType {'user'|'group'}
 * @param peerId {number}
 * @param message {object} 已序列化的 message 对象
 */
export async function broadcastNewMessage(senderId, peerType, peerId, message) {
  try {
    const targets = new Set()
    if (peerType === 'user') {
      // DM: 推给 sender + receiver + master
      targets.add(senderId)
      targets.add(peerId)
    } else if (peerType === 'group') {
      // 群: 推给所有群成员 (除 sender)
      const [rows] = await pool.query(
        'SELECT user_id FROM smart_studio_group_members WHERE group_id=? AND user_id<>?',
        [peerId, senderId]
      )
      rows.forEach(r => targets.add(r.user_id))
      targets.add(senderId) // 自己也得有 (确认已发)
    }
    // master (uid=0) 总能看到所有
    if (clients.has(0)) targets.add(0)

    const payload = JSON.stringify({
      type: 'new_message',
      peer_type: peerType,
      peer_id: peerId,
      sender_id: senderId,
      message,
      ts: Date.now()
    })

    let sent = 0
    targets.forEach(uid => {
      const set = clients.get(uid)
      if (!set) return
      set.forEach(ws => {
        if (ws.readyState === 1) {  // OPEN
          try { ws.send(payload); sent++ } catch (e) {}
        }
      })
    })
    console.log(`[chat-ws] broadcast new_message → ${targets.size} users, ${sent} sockets`)
  } catch (e) {
    console.error('[chat-ws] broadcastNewMessage failed:', e.message)
  }
}

/**
 * broadcastReadReceipt — 标记已读后广播
 */
export function broadcastReadReceipt(peerType, peerId, readerId, lastReadMessageId) {
  const targets = new Set()
  if (peerType === 'user') targets.add(peerId) // 推给对方 (sender)
  // master 总能看到
  if (clients.has(0)) targets.add(0)
  // 推给 reader 自己 (确认)
  targets.add(readerId)

  const payload = JSON.stringify({
    type: 'read_receipt',
    peer_type: peerType,
    peer_id: peerId,
    by: readerId,
    last_read_message_id: lastReadMessageId,
    ts: Date.now()
  })

  targets.forEach(uid => {
    const set = clients.get(uid)
    if (!set) return
    set.forEach(ws => {
      if (ws.readyState === 1) {
        try { ws.send(payload) } catch (e) {}
      }
    })
  })
}

/**
 * broadcastClear — 清空对话广播 (DM 推对方 + 自己, 群推所有成员)
 * @param peerType {'user'|'group'}
 * @param peerId {number}
 * @param byUserId {number} 谁清的
 * @param deletedCount {number}
 */
export async function broadcastClear(peerType, peerId, byUserId, deletedCount = 0) {
  try {
    const targets = new Set()
    if (peerType === 'user') {
      // DM: 推 by 自己 (确认) + 对方
      targets.add(byUserId)
      // 找 DM 对方的 user_id (smart_studio_dialogs 反查)
      const [rows] = await pool.query(
        `SELECT user_id, peer_id FROM smart_studio_dialogs
         WHERE peer_type='user' AND ((user_id=? AND peer_id=?) OR (user_id=? AND peer_id=?))
         LIMIT 1`,
        [byUserId, peerId, peerId, byUserId]
      )
      if (rows.length) {
        targets.add(rows[0].user_id === byUserId ? rows[0].peer_id : rows[0].user_id)
      }
    } else if (peerType === 'group') {
      // 群: 推所有成员 + by 自己
      const [mem] = await pool.query(
        'SELECT user_id FROM smart_studio_group_members WHERE group_id=?',
        [peerId]
      )
      mem.forEach(r => targets.add(r.user_id))
    }
    if (clients.has(0)) targets.add(0) // master

    const payload = JSON.stringify({
      type: 'clear',
      peer_type: peerType,
      peer_id: peerId,
      by: byUserId,
      deleted: deletedCount,
      ts: Date.now()
    })
    targets.forEach(uid => {
      const set = clients.get(uid)
      if (!set) return
      set.forEach(ws => {
        if (ws.readyState === 1) {
          try { ws.send(payload) } catch (e) {}
        }
      })
    })
    console.log(`[chat-ws] broadcast clear → ${targets.size} users`)
  } catch (e) {
    console.error('[chat-ws] broadcastClear failed:', e.message)
  }
}

/**
 * broadcastMessageDeleted — 单条消息删除广播
 */
export async function broadcastMessageDeleted(messageId, byUserId) {
  try {
    // 查消息的 peer + sender
    const [rows] = await pool.query(
      `SELECT id, peer_id, peer_type, sender_id FROM smart_studio_messages WHERE id=?`,
      [messageId]
    )
    if (!rows.length) return // 消息已被删
    const m = rows[0]
    const targets = new Set()
    targets.add(m.sender_id)
    if (m.peer_type === 'user') targets.add(m.peer_id)
    else {
      const [mem] = await pool.query(
        'SELECT user_id FROM smart_studio_group_members WHERE group_id=?',
        [m.peer_id]
      )
      mem.forEach(r => targets.add(r.user_id))
    }
    if (clients.has(0)) targets.add(0)

    const payload = JSON.stringify({
      type: 'message_deleted',
      id: messageId,
      by: byUserId,
      peer_type: m.peer_type,
      peer_id: m.peer_id,
      ts: Date.now()
    })
    targets.forEach(uid => {
      const set = clients.get(uid)
      if (!set) return
      set.forEach(ws => {
        if (ws.readyState === 1) {
          try { ws.send(payload) } catch (e) {}
        }
      })
    })
  } catch (e) {
    console.error('[chat-ws] broadcastMessageDeleted failed:', e.message)
  }
}

/**
 * broadcastMessageEdited — 编辑消息后广播
 */
export async function broadcastMessageEdited(messageId, newContent, editedBy) {
  try {
    const [rows] = await pool.query(
      `SELECT peer_id, peer_type, sender_id FROM smart_studio_messages WHERE id=?`,
      [messageId]
    )
    if (!rows.length) return
    const m = rows[0]
    const targets = new Set()
    if (m.peer_type === 'user') {
      targets.add(m.sender_id)
      targets.add(m.peer_id)
    } else {
      const [mem] = await pool.query(
        'SELECT user_id FROM smart_studio_group_members WHERE group_id=?',
        [m.peer_id]
      )
      mem.forEach(r => targets.add(r.user_id))
    }
    if (clients.has(0)) targets.add(0)

    const payload = JSON.stringify({
      type: 'message_edited',
      id: messageId,
      content: newContent,
      edited_by: editedBy,
      peer_type: m.peer_type,
      peer_id: m.peer_id,
      ts: Date.now()
    })
    targets.forEach(uid => {
      const set = clients.get(uid)
      if (!set) return
      set.forEach(ws => {
        if (ws.readyState === 1) {
          try { ws.send(payload) } catch (e) {}
        }
      })
    })
  } catch (e) {
    console.error('[chat-ws] broadcastMessageEdited failed:', e.message)
  }
}

/**
 * broadcastPresence — 上下线广播
 * 推送 reader 所有朋友 (DM 关系)
 */
export async function broadcastPresence(userId, online) {
  try {
    const [rows] = await pool.query(
      `SELECT requester_id, addressee_id FROM smart_studio_friendships
       WHERE status='accepted' AND (requester_id=? OR addressee_id=?)`,
      [userId, userId]
    )
    const friends = new Set()
    rows.forEach(r => {
      friends.add(r.requester_id === userId ? r.addressee_id : r.requester_id)
    })
    friends.add(userId) // 自己也收到 (确认状态)
    if (clients.has(0)) friends.add(0) // master

    const payload = JSON.stringify({
      type: 'presence',
      user_id: userId,
      online,
      ts: Date.now()
    })
    friends.forEach(uid => {
      const set = clients.get(uid)
      if (!set) return
      set.forEach(ws => {
        if (ws.readyState === 1) {
          try { ws.send(payload) } catch (e) {}
        }
      })
    })
  } catch (e) {
    console.error('[chat-ws] broadcastPresence failed:', e.message)
  }
}

/**
 * WebSocket connection handler
 * /ws/chat?token=xxx
 */
export function attachChatWS(wss) {
  wss.on('connection', async (ws, req) => {
    // 解析 token (from URL query)
    const url = new URL(req.url, 'http://localhost')
    const token = url.searchParams.get('token')
    let userId = null
    let masterMode = false

    if (token) {
      try {
        const payload = jwt.verify(token, JWT_SECRET)
        // master token: kid='master' (zero DB lookup)
        if (payload.kid === 'master') {
          userId = 0
          masterMode = true
        } else {
          userId = payload.uid || payload.userId || payload.id
        }
      } catch (e) {
        // token 无效 → 关闭连接
        ws.send(JSON.stringify({ type: 'error', error: 'invalid token' }))
        ws.close(4001, 'invalid token')
        return
      }
    }

    if (!userId && userId !== 0) {
      ws.close(4002, 'no token')
      return
    }

    addClient(userId, ws)

    // hello 包
    ws.send(JSON.stringify({
      type: 'hello',
      user_id: userId,
      master_mode: masterMode,
      server_time: Date.now()
    }))

    // ping/pong + 接收事件
    let isAlive = true
    ws.on('pong', () => { isAlive = true })

    ws.on('message', async (raw) => {
      try {
        const data = JSON.parse(raw.toString())
        if (data.type === 'ping') {
          ws.send(JSON.stringify({ type: 'pong', ts: Date.now() }))
        } else if (data.type === 'mark_read') {
          // { peer_type, peer_id, last_read_message_id }
          broadcastReadReceipt(data.peer_type, data.peer_id, userId, data.last_read_message_id)
        }
      } catch (e) {}
    })

    // heartbeat 每 30s
    const heartbeat = setInterval(() => {
      if (!isAlive) {
        try { ws.terminate() } catch (e) {}
        clearInterval(heartbeat)
        return
      }
      isAlive = false
      try { ws.ping() } catch (e) {}
    }, 30000)

    ws.on('close', () => {
      clearInterval(heartbeat)
      removeClient(ws)
    })

    ws.on('error', (e) => {
      console.error('[chat-ws] error:', e.message)
    })
  })

  console.log('[chat-ws] WebSocket server attached to wssChat')
}

// 导出 client 统计 (admin 用)
export function getOnlineStats() {
  return {
    total_users: clients.size,
    total_sockets: wsToUser.size,
    users: Array.from(clients.entries()).map(([uid, set]) => ({
      user_id: uid,
      sockets: set.size
    }))
  }
}