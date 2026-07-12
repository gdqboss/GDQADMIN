import { Router } from 'express'
import { pool } from '../db/connection.js'
import { isWeComConfigured, sendMessage } from '../services/wecom.js'

const router = Router()

router.get('/conversations', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM wecom_conversations ORDER BY last_time DESC')
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

router.get('/messages/:convId', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT * FROM wecom_messages WHERE conversation_id = ? ORDER BY created_at ASC',
      [req.params.convId]
    )
    await pool.query('UPDATE wecom_conversations SET unread = 0 WHERE id = ?', [req.params.convId])
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

router.post('/messages', async (req, res, next) => {
  try {
    const { conversation_id, content } = req.body
    if (!conversation_id || !content) {
      return res.status(400).json({ code: 400, message: '会话ID和内容必填' })
    }
    const [result] = await pool.query(
      'INSERT INTO wecom_messages (conversation_id, sender, is_self, content) VALUES (?, ?, TRUE, ?)',
      [conversation_id, req.user?.name || 'system', content]
    )
    await pool.query(
      'UPDATE wecom_conversations SET last_message = ?, last_time = NOW() WHERE id = ?',
      [content, conversation_id]
    )

    // Push to WeCom if configured
    if (isWeComConfigured()) {
      try {
        // Look up the WeCom external_id (userid) for this conversation
        const [[conv]] = await pool.query(
          'SELECT external_id, wecom_chat_id FROM wecom_conversations WHERE id = ?',
          [conversation_id]
        )
        if (conv?.external_id) {
          await sendMessage({ touser: conv.external_id, msgtype: 'text', content })
        }
      } catch (wecomErr) {
        // Log but don't fail the local operation
        console.error('[wecom] Failed to push message to WeCom:', wecomErr.message)
      }
    }

    res.json({ code: 0, data: { id: result.insertId }, message: 'ok' })
  } catch (err) { next(err) }
})

// callback route is public — handled in index.js

router.post('/ai-reply', async (req, res, next) => {
  try {
    const { message } = req.body
    const apiKey = process.env.GLM_API_KEY
    if (!apiKey) {
      return res.status(500).json({ code: 500, message: 'GLM API Key 未配置' })
    }
    const response = await fetch('https://open.bigmodel.cn/api/paas/v4/chat/completions', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${apiKey}` },
      body: JSON.stringify({
        model: 'glm-4-flash',
        messages: [{ role: 'user', content: message }]
      })
    })
    const data = await response.json()
    const reply = data.choices?.[0]?.message?.content || '无法生成回复'
    res.json({ code: 0, data: { reply }, message: 'ok' })
  } catch (err) { next(err) }
})

export default router
