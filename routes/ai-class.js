/**
 * AI课堂后端路由
 * 包含知识库CRUD、记忆系统CRUD、对话接口（调用本地LLM）
 */

import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'

const router = Router()

// ==================== 知识库路由 ====================
// GET /api/ai-class/knowledge - 知识库列表
router.get('/knowledge', auth, async (req, res, next) => {
  try {
    const { search, doc_type, page = 1, pageSize = 20 } = req.query
    const offset = (parseInt(page) - 1) * parseInt(pageSize)
    
    let where = 'WHERE 1=1'
    const params = []
    
    if (search) {
      where += ' AND (title LIKE ? OR content LIKE ?)'
      params.push(`%${search}%`, `%${search}%`)
    }
    if (doc_type) {
      where += ' AND doc_type = ?'
      params.push(doc_type)
    }
    
    const [rows] = await pool.query(
      `SELECT * FROM ai_class_knowledge ${where} ORDER BY created_at DESC LIMIT ? OFFSET ?`,
      [...params, parseInt(pageSize), offset]
    )
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM ai_class_knowledge ${where}`,
      params
    )
    
    res.json({ code: 0, data: { list: rows, total, page: parseInt(page), pageSize: parseInt(pageSize) } })
  } catch (err) { next(err) }
})

// POST /api/ai-class/knowledge - 新增知识
router.post('/knowledge', auth, async (req, res, next) => {
  try {
    const { title, content, doc_type, tags, is_public } = req.body
    const [result] = await pool.query(
      'INSERT INTO ai_class_knowledge (title, content, doc_type, tags, is_public, created_by) VALUES (?, ?, ?, ?, ?, ?)',
      [title, content, doc_type || 'general', tags || '', is_public ? 1 : 0, req.user.id]
    )
    res.json({ code: 0, data: { id: result.insertId } })
  } catch (err) { next(err) }
})

// GET /api/ai-class/knowledge/:id - 知识详情
router.get('/knowledge/:id', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM ai_class_knowledge WHERE id = ?', [req.params.id])
    if (!rows.length) return res.json({ code: 404, message: '知识不存在' })
    res.json({ code: 0, data: rows[0] })
  } catch (err) { next(err) }
})

// PUT /api/ai-class/knowledge/:id - 更新知识
router.put('/knowledge/:id', auth, async (req, res, next) => {
  try {
    const { title, content, doc_type, tags, is_public } = req.body
    await pool.query(
      'UPDATE ai_class_knowledge SET title=?, content=?, doc_type=?, tags=?, is_public=? WHERE id=?',
      [title, content, doc_type, tags || '', is_public ? 1 : 0, req.params.id]
    )
    res.json({ code: 0 })
  } catch (err) { next(err) }
})

// DELETE /api/ai-class/knowledge/:id - 删除知识
router.delete('/knowledge/:id', auth, async (req, res, next) => {
  try {
    // 检查是否是创建者或admin
    const [rows] = await pool.query('SELECT created_by FROM ai_class_knowledge WHERE id = ?', [req.params.id])
    if (!rows.length) return res.json({ code: 404, message: '知识不存在' })
    
    const isAdmin = req.user.role === 'admin'
    const isOwner = rows[0].created_by === req.user.id
    
    if (!isAdmin && !isOwner) {
      return res.json({ code: 403, message: '无权限删除' })
    }
    
    await pool.query('DELETE FROM ai_class_knowledge WHERE id = ?', [req.params.id])
    res.json({ code: 0 })
  } catch (err) { next(err) }
})

// ==================== 记忆路由 ====================
// GET /api/ai-class/memory - 记忆列表
router.get('/memory', auth, async (req, res, next) => {
  try {
    const { type } = req.query
    const userId = req.user.id
    
    let where = 'WHERE (user_id = 0 OR user_id = ?)'
    const params = [userId]
    
    if (type) {
      where += ' AND memory_type = ?'
      params.push(type)
    }
    
    const [rows] = await pool.query(
      `SELECT * FROM ai_class_memory ${where} ORDER BY created_at DESC`,
      params
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/ai-class/memory - 新增记忆
router.post('/memory', auth, async (req, res, next) => {
  try {
    const { memory_type, content, source } = req.body
    const [result] = await pool.query(
      'INSERT INTO ai_class_memory (user_id, memory_type, content, source) VALUES (?, ?, ?, ?)',
      [req.user.id, memory_type, content, source || 'user']
    )
    res.json({ code: 0, data: { id: result.insertId } })
  } catch (err) { next(err) }
})

// DELETE /api/ai-class/memory/:id - 删除记忆
router.delete('/memory/:id', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT user_id FROM ai_class_memory WHERE id = ?', [req.params.id])
    if (!rows.length) return res.json({ code: 404, message: '记忆不存在' })
    
    const isAdmin = req.user.role === 'admin'
    const isOwner = rows[0].user_id === req.user.id
    
    if (!isAdmin && !isOwner) {
      return res.json({ code: 403, message: '无权限删除' })
    }
    
    await pool.query('DELETE FROM ai_class_memory WHERE id = ?', [req.params.id])
    res.json({ code: 0 })
  } catch (err) { next(err) }
})

// ==================== 对话路由 ====================
// POST /api/ai-class/chat - 发送消息
router.post('/chat', auth, async (req, res, next) => {
  try {
    const { message, session_id } = req.body
    const userId = req.user.id
    
    if (!message || message.trim() === '') {
      return res.json({ code: 0, data: { reply: '请输入问题' } })
    }

    // 1. 获取用户最近20条对话历史
    const [history] = await pool.query(
      `SELECT query, response FROM ai_class_conversations 
       WHERE user_id = ? AND session_id = ? 
       ORDER BY created_at DESC LIMIT 20`,
      [userId, session_id || 'default']
    )
    const reversedHistory = history.reverse()

    // 2. 获取公共记忆和用户私人记忆
    const [publicMemory] = await pool.query(
      'SELECT content FROM ai_class_memory WHERE user_id = 0 ORDER BY created_at DESC'
    )
    const [privateMemory] = await pool.query(
      'SELECT content FROM ai_class_memory WHERE user_id = ? ORDER BY created_at DESC',
      [userId]
    )
    const allMemory = [...publicMemory, ...privateMemory]
    const memoryContext = allMemory.length > 0
      ? `\n\n以下是与用户相关的记忆信息：\n${allMemory.map(m => `- ${m.content}`).join('\n')}`
      : ''

    // 3. 获取机器人名称（从 settings 表，兜底"小智"）
    const [[botNameSetting]] = await pool.query(
      'SELECT value FROM settings WHERE `key` = "bot_name" LIMIT 1'
    )
    const botName = botNameSetting?.value || '小智'

    // 4. 获取默认LLM配置
    const [[llmConfig]] = await pool.query(
      'SELECT * FROM ai_config WHERE is_default = 1 AND category = "llm" LIMIT 1'
    )

    // 优先用数据库配置的 base_url 和 model，兜底用默认值
    const baseUrl = llmConfig?.base_url || 'http://100.74.233.52:1234/v1'
    const model = llmConfig?.model || 'qwen/qwen3-vl-8b'

    // 5. 构建prompt
    const systemPrompt = `你是彩美特公司的AI助手，叫${botName}。你有帮助用户解决问题、提供公司知识、记住用户偏好的能力。`
    const historyContext = reversedHistory.length > 0
      ? `\n\n以下是对话历史：\n${reversedHistory.map(h => `用户: ${h.query}\n${botName}: ${h.response}`).join('\n')}`
      : ''

    const fullPrompt = `${systemPrompt}${memoryContext}${historyContext}\n\n用户: ${message}\n${botName}:`

    // 6. 调用AI，带10秒超时
    let reply = 'AI服务暂时繁忙，请稍后再试。'

    try {
      const controller = new AbortController()
      const timeout = setTimeout(() => controller.abort(), 10000)

      const response = await fetch(`${baseUrl}/chat/completions`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(llmConfig?.api_key ? { 'Authorization': `Bearer ${llmConfig.api_key}` } : {})
        },
        body: JSON.stringify({
          model: model,
          messages: [
            { role: 'user', content: fullPrompt }
          ],
          max_tokens: 1000,
          temperature: 0.7
        }),
        signal: controller.signal
      })
      clearTimeout(timeout)

      if (response.ok) {
        const data = await response.json()
        reply = data.choices?.[0]?.message?.content || reply
      } else {
        console.error('[ai-class] LLM API error:', response.status, await response.text())
      }
    } catch (fetchErr) {
      console.error('[ai-class] LLM fetch error:', fetchErr.message)
    }

    // 7. 保存对话
    await pool.query(
      'INSERT INTO ai_class_conversations (user_id, session_id, query, response, model) VALUES (?, ?, ?, ?, ?)',
      [userId, session_id || 'default', message, reply, model]
    )

    res.json({ code: 0, data: { reply } })
  } catch (err) {
    next(err)
  }
})

export default router