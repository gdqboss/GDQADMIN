import express from 'express'
import { pool } from '../db/connection.js'

const router = express.Router()

// 获取或创建聊天室
router.post('/room', async (req, res) => {
  try {
    const { userId, sessionId } = req.body
    let room
    
    if (userId) {
      // 登录用户
      const [rows] = await pool.query(
        'SELECT * FROM customer_chat_rooms WHERE user_id = ? AND status = active ORDER BY updated_at DESC LIMIT 1',
        [userId]
      )
      room = rows[0]
      if (!room) {
        const [result] = await pool.query(
          'INSERT INTO customer_chat_rooms (user_id, user_type) VALUES (?, logged)',
          [userId]
        )
        room = { id: result.insertId, user_id: userId, user_type: 'logged' }
      }
    } else if (sessionId) {
      // 游客
      const [rows] = await pool.query(
        'SELECT * FROM customer_chat_rooms WHERE session_id = ? AND status = active ORDER BY updated_at DESC LIMIT 1',
        [sessionId]
      )
      room = rows[0]
      if (!room) {
        const [result] = await pool.query(
          'INSERT INTO customer_chat_rooms (session_id, user_type) VALUES (?, guest)',
          [sessionId]
        )
        room = { id: result.insertId, session_id: sessionId, user_type: 'guest' }
      }
    }
    
    res.json({ code: 0, data: room })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

// 获取聊天历史
router.get('/messages/:roomId', async (req, res) => {
  try {
    const { roomId } = req.params
    const { limit = 50 } = req.query
    
    const [rows] = await pool.query(
      'SELECT * FROM customer_chat_messages WHERE room_id = ? ORDER BY created_at DESC LIMIT ?',
      [roomId, parseInt(limit)]
    )
    
    res.json({ code: 0, data: rows.reverse() })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

// 发送消息
router.post('/messages', async (req, res) => {
  try {
    const { roomId, userId, sessionId, role, content } = req.body
    
    // 保存消息
    const [result] = await pool.query(
      'INSERT INTO customer_chat_messages (room_id, user_id, session_id, role, content) VALUES (?, ?, ?, ?, ?)',
      [roomId, userId || null, sessionId || null, role, content]
    )
    
    // 更新聊天室时间
    await pool.query(
      'UPDATE customer_chat_rooms SET updated_at = NOW() WHERE id = ?',
      [roomId]
    )
    
    res.json({ code: 0, data: { id: result.insertId } })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

export default router
