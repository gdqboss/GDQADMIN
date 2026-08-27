/**
 * chat-ws.js shim - 转发到 ../ws/chat-ws.js
 *
 * 历史: smart-studio.js 第 14 行 import { broadcastToUser, isUserOnline,
 * forceDisconnectUser } from './chat-ws.js' — 但 chat-ws.js 实际在 ws/ 目录,
 * 而且 export 的是 broadcastNewMessage / broadcastPresence 等不同名字。
 *
 * 这个 shim 把 ws/chat-ws.js 的真实 exports 重新命名/包装, 满足 smart-studio.js 的 import。
 * 2026-08-15 agent 临时修复 — 单独 chat 模块, 不影响 labor/minip/admin。
 *
 * 2026-08-28 大修: 原 shim 只在 payload.type === 'new_message' 时才转发,
 * 但 smart-studio.js 实际传的是 type: 'message' / 'read' / 'typing' / 'clear' / 'delete'
 * → WS 广播全部静默失效, 消息只能靠轮询兜底 (这就是"消息延迟/要刷新才看到"的根因)。
 * 现在完整映射所有 type 到 ws/chat-ws.js 的真实广播函数。
 */
import {
  broadcastNewMessage,
  broadcastReadReceipt,
  broadcastClear,
  broadcastMessageDeleted,
  broadcastMessageEdited,
  broadcastPresence,
  broadcastTyping,
  pushToUser,
  getOnlineStats,
} from '../ws/chat-ws.js'

export async function broadcastToUser(userId, payload) {
  if (!payload || !payload.type || !userId) return
  const t = payload.type
  try {
    if (t === 'message') {
      // smart-studio: { type:'message', data:{sender_id, peer_type, peer_id, ...} }
      const d = payload.data || payload
      await broadcastNewMessage(
        d.sender_id || 0,
        d.peer_type || 'user',
        d.peer_id || 0,
        d,
      )
      return
    }
    if (t === 'read') {
      // { type:'read', data:{ peer_id: readerId, peer_type:'user', last_message_id } }
      // receiver = userId (被读到消息的人, 显示 ✓✓), reader = data.peer_id
      const d = payload.data || {}
      broadcastReadReceipt(d.peer_type || 'user', userId, d.peer_id || 0, d.last_message_id || 0)
      return
    }
    if (t === 'typing') {
      // { type:'typing', user_id: me, peer_type:'user', typing: bool }  (顶层, 无 data)
      broadcastTyping(userId, { user_id: payload.user_id || 0, typing: payload.typing })
      return
    }
    if (t === 'clear') {
      // { type:'clear', data:{ peer_id: 对方, peer_type:'user' } }
      const d = payload.data || {}
      broadcastClear(d.peer_type || 'user', d.peer_id || 0, userId)
      return
    }
    if (t === 'delete') {
      // { type:'delete', data:{ id, peer_id, peer_type, room_id } }
      // 消息已从 DB 真删 → broadcastMessageDeleted 查不到, 用 pushToUser 直推双方
      const d = payload.data || {}
      const delPayload = { type: 'delete', id: d.id, message_id: d.id, by: d.by || 0, peer_type: d.peer_type, peer_id: d.peer_id, ts: Date.now() }
      pushToUser(userId, delPayload)
      if (d.peer_type === 'user' && d.peer_id && d.peer_id !== userId) pushToUser(d.peer_id, delPayload)
      return
    }
    if (t === 'message_edited' || t === 'edit') {
      const d = payload.data || payload
      broadcastMessageEdited(d.id, d.content, d.edited_by || d.by || 0).catch(() => {})
      return
    }
    // 未知 type — 原样直推 (不怕漏)
    pushToUser(userId, payload)
  } catch (e) {
    console.warn('[chat-ws shim] broadcastToUser failed:', e?.message)
  }
}

export function isUserOnline(userId) {
  try {
    const stats = getOnlineStats()
    const u = stats.users.find(x => Number(x.user_id) === Number(userId))
    return !!(u && u.sockets > 0)
  } catch {
    return false
  }
}

export function countConnections(userId) {
  try {
    const stats = getOnlineStats()
    const u = stats.users.find(x => Number(x.user_id) === Number(userId))
    return u?.sockets || 0
  } catch {
    return 0
  }
}

export function forceDisconnectUser(userId, mode = 'normal') {
  console.warn(`[chat-ws shim] forceDisconnectUser(${userId}, ${mode}) no-op`)
  return false
}

export {
  broadcastNewMessage,
  broadcastReadReceipt,
  broadcastClear,
  broadcastMessageDeleted,
  broadcastMessageEdited,
  broadcastPresence,
  broadcastTyping,
  pushToUser,
  getOnlineStats,
}
