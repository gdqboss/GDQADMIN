/**
 * chat-ws.js shim - 转发到 ../ws/chat-ws.js
 *
 * 历史: smart-studio.js 第 14 行 import { broadcastToUser, isUserOnline,
 * forceDisconnectUser } from './chat-ws.js' — 但 chat-ws.js 实际在 ws/ 目录,
 * 而且 export 的是 broadcastNewMessage / broadcastPresence 等不同名字。
 *
 * 这个 shim 把 ws/chat-ws.js 的真实 exports 重新命名/包装, 满足 smart-studio.js 的 import。
 * 2026-08-15 agent 临时修复 — 单独 chat 模块, 不影响 labor/minip/admin。
 */
import {
  broadcastNewMessage,
  broadcastReadReceipt,
  broadcastClear,
  broadcastMessageDeleted,
  broadcastMessageEdited,
  broadcastPresence,
  getOnlineStats,
} from '../ws/chat-ws.js'

export async function broadcastToUser(userId, payload) {
  if (payload?.type === 'new_message' && payload?.data) {
    try {
      await broadcastNewMessage(
        payload.data.sender_id || 0,
        payload.data.peer_type || 'user',
        payload.data.peer_id || 0,
        payload.data.message || payload.data,
      )
      return
    } catch (e) {
      console.warn('[chat-ws shim] broadcastNewMessage failed:', e?.message)
    }
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
  getOnlineStats,
}
