import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// 获取会话列表
router.get('/conversations', async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT c.*, 
        (SELECT content FROM kefu_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM kefu_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_time
      FROM kefu_conversations c 
      ORDER BY last_time DESC, c.created_at DESC
    `)
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// 获取消息历史
router.get('/messages/:convId', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT * FROM kefu_messages WHERE conversation_id = ? ORDER BY created_at ASC',
      [req.params.convId]
    )
    await pool.query('UPDATE kefu_conversations SET unread = 0 WHERE id = ?', [req.params.convId])
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// 发送消息
router.post('/messages', async (req, res, next) => {
  try {
    const { conversation_id, content, role = 'customer' } = req.body
    if (!conversation_id || !content) {
      return res.status(400).json({ code: 400, message: '会话ID和内容必填' })
    }
    
    const [result] = await pool.query(
      'INSERT INTO kefu_messages (conversation_id, role, content) VALUES (?, ?, ?)',
      [conversation_id, role, content]
    )
    
    await pool.query(
      'UPDATE kefu_conversations SET last_message = ?, last_time = NOW() WHERE id = ?',
      [content, conversation_id]
    )
    
    res.json({ code: 0, data: { id: result.insertId }, message: 'ok' })
  } catch (err) { next(err) }
})

// 创建新会话
router.post('/conversations', async (req, res, next) => {
  try {
    const { user_id, user_name, user_type = 'customer' } = req.body
    
    // 检查是否有活跃会话
    let conversation
    if (user_id) {
      const [existing] = await pool.query(
        'SELECT * FROM kefu_conversations WHERE user_id = ? AND status = "active" ORDER BY created_at DESC LIMIT 1',
        [user_id]
      )
      if (existing.length > 0) {
        conversation = existing[0]
      }
    }
    
    if (!conversation) {
      const [result] = await pool.query(
        'INSERT INTO kefu_conversations (user_id, user_name, user_type, status) VALUES (?, ?, ?, "active")',
        [user_id || null, user_name || '访客', user_type]
      )
      conversation = { id: result.insertId, user_id, user_name, user_type, status: 'active' }
    }
    
    res.json({ code: 0, data: conversation, message: 'ok' })
  } catch (err) { next(err) }
})

// AI 智能回复
router.post('/ai-reply', async (req, res, next) => {
  try {
    const { message, context } = req.body
    const apiKey = process.env.GLM_API_KEY
    
    if (!apiKey) {
      return res.status(500).json({ code: 500, message: 'AI 未配置' })
    }
    
    const response = await fetch('https://open.bigmodel.cn/api/paas/v4/chat/completions', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${apiKey}` },
      body: JSON.stringify({
        model: 'glm-4-flash',
        messages: [
          { role: 'system', content: '你是一个专业的客服助手，帮助用户解答问题。回复要简洁、专业、友好。' },
          ...(context || []),
          { role: 'user', content: message }
        ]
      })
    })
    
    const data = await response.json()
    const reply = data.choices?.[0]?.message?.content || '抱歉，暂时无法回复'
    res.json({ code: 0, data: { reply }, message: 'ok' })
  } catch (err) { next(err) }
})

export default router