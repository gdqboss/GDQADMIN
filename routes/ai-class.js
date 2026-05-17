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

// GET /api/ai-class/conversations - 获取指定session的对话历史
router.get('/conversations', auth, async (req, res, next) => {
  try {
    const { session_id } = req.query
    const userId = req.user.id

    const [rows] = await pool.query(
      `SELECT id, query, response, created_at FROM ai_class_conversations 
       WHERE user_id = ? AND session_id = ?
       ORDER BY created_at ASC`,
      [userId, session_id || 'default']
    )

    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// ==================== 对话路由 ====================
// POST /api/ai-class/chat - 发送消息（支持RAG知识检索 + Function Calling）
router.post('/chat', auth, async (req, res, next) => {
  try {
    const { message, session_id, stream } = req.body
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
      'SELECT content FROM ai_class_memory WHERE user_id = 0 AND memory_type = "public" ORDER BY created_at DESC LIMIT 10'
    )
    const [privateMemory] = await pool.query(
      'SELECT content, memory_type FROM ai_class_memory WHERE user_id = ? ORDER BY created_at DESC LIMIT 10',
      [userId]
    )
    
    // 构建分层记忆上下文（公共记忆 + 私人记忆分开）
    let memoryContext = ''
    if (publicMemory.length > 0) {
      memoryContext += `\n\n【系统公共记忆】\n${publicMemory.map(m => `- ${m.content}`).join('\n')}`
    }
    if (privateMemory.length > 0) {
      const userFacts = privateMemory.filter(m => m.memory_type === 'fact')
      const userPrefs = privateMemory.filter(m => m.memory_type !== 'fact')
      if (userFacts.length > 0) {
        memoryContext += `\n\n【用户相关事实】\n${userFacts.map(m => `- ${m.content}`).join('\n')}`
      }
      if (userPrefs.length > 0) {
        memoryContext += `\n\n【用户偏好】\n${userPrefs.map(m => `- ${m.content}`).join('\n')}`
      }
    }

    // 3. RAG知识检索 - 从ai_class_knowledge表检索相关知识
    let ragContext = ''
    try {
      // 提取关键词（中文：滑动窗口2-4字+标点分词，支持英文）
      const sentences = message.split(/[，。！？、；\n\r,.!?;]+/).filter(s => s.trim())
      const wordSet = new Set()
      for (const sentence of sentences) {
        // 滑动窗口提取2-4字词（去重）
        for (let len = 2; len <= 4; len++) {
          for (let i = 0; i <= sentence.length - len; i++) {
            const w = sentence.substring(i, i + len)
            if (/^[\u4e00-\u9fa5]+$/.test(w)) wordSet.add(w)
          }
        }
        // 英文词
        const enWords = sentence.match(/[a-zA-Z]{2,}/g) || []
        enWords.forEach(w => wordSet.add(w.toLowerCase()))
      }
      const allKeywords = [...wordSet]

      if (allKeywords.length > 0) {
        // 构建LIKE查询条件
        const keywordConditions = allKeywords.map(() => '(title LIKE ? OR content LIKE ?)').join(' OR ')
        const queryParams = allKeywords.flatMap(k => [`%${k}%`, `%${k}%`])

        const [knowledgeRows] = await pool.query(
          `SELECT title, content, doc_type FROM ai_class_knowledge
           WHERE ${keywordConditions}
           ORDER BY
             (CASE WHEN doc_type = 'product' THEN 3 WHEN doc_type = 'service' THEN 2 WHEN doc_type = 'faq' THEN 1 ELSE 0 END) DESC,
             updated_at DESC
           LIMIT 5`,
          queryParams
        )

        if (knowledgeRows.length > 0) {
          ragContext = `\n\n【知识库检索结果】\n${knowledgeRows.map(k => `[${k.doc_type || 'general'}] ${k.title}\n${k.content}`).join('\n\n')}`
        }
      }
    } catch (ragErr) {
      console.error('[ai-class] RAG retrieval error:', ragErr.message)
    }

    // 4. 获取机器人名称（从 settings 表，兜底"小智"）
    const [[botNameSetting]] = await pool.query(
      'SELECT value FROM settings WHERE `key` = "bot_name" LIMIT 1'
    )
    const botName = botNameSetting?.value || '小智'

    // 5. 获取默认LLM配置
    const [[llmConfig]] = await pool.query(
      'SELECT * FROM ai_config WHERE is_default = 1 AND category = "llm" LIMIT 1'
    )

    const baseUrl = llmConfig?.base_url || 'http://100.74.233.52:1234/v1'
    const model = llmConfig?.model || 'qwen/qwen3-vl-8b'

    // 6. 获取用户权限（用于AI智能过滤）
    const [[userRow]] = await pool.query(
      'SELECT name, permissions FROM users WHERE id = ?',
      [userId]
    )
    const userPermissions = userRow?.permissions ? JSON.parse(userRow.permissions) : []
    const userName = userRow?.name || ''

    // 6. 增强的System Prompt（>500字，通用多租户系统）
    const systemPrompt = `你是${botName}，一个通用的企业管理助手，服务于多个企业客户。

## 一、回答规则（必须严格遵守，优先级最高）

1. **不知道就说不知道**：如果知识库、记忆、系统数据都没有相关信息，请明确说"抱歉，我目前没有这方面的信息"。严禁编造、猜测、填充占位符内容。
2. **准确引用，注明来源**：知识库内容请用自己的话概括，不要原文照抄；系统数据要注明"根据系统查询结果"。
3. **使用用户的真实称呼**：在记忆中找到用户姓名则用姓名称呼，否则用"您"；不要假设用户是任何人。
4. **保护隐私**：不主动询问隐私，不透露其他用户信息。
5. **多轮对话连贯**：结合历史对话和记忆，避免重复询问已确认的信息。
6. **权限智能过滤**：如果用户询问的功能没有对应权限，明确告知"您没有权限访问该功能"。

## 二、系统模块知识（彩美特管理系统功能覆盖）

当用户询问以下功能时，请主动使用对应的Function Calling查询数据：

### 2.1 考勤打卡（attendance）
- 打卡记录表字段：user_id, date, clock_in, clock_out, status(normal/late/early/absent/leave), late_minutes, early_minutes, overtime_hours, location, device_info, check_in_time, check_out_time
- 查询今日考勤：SELECT * FROM attendance WHERE user_id=? AND date=CURDATE()
- 查询本月考勤：SELECT * FROM attendance WHERE user_id=? AND date BETWEEN DATE_FORMAT(NOW(),'%Y-%m-01') AND LAST_DAY(NOW())
- 注意：没有quick-action-check-in权限的用户不能使用打卡功能

### 2.2 工作日志（work_logs）
- 字段：id, user_id, title, content, work_date, status, created_at, updated_at
- 查询用户日志：SELECT * FROM work_logs WHERE user_id=? ORDER BY work_date DESC LIMIT 10
- 没有quick-action-worklog权限的用户不能使用工作日志功能

### 2.3 任务管理（tasks）
- 字段：id, title, description, assignee_id, creator_id, status, priority, due_date, created_at
- 查询我的任务：SELECT * FROM tasks WHERE assignee_id=? ORDER BY created_at DESC LIMIT 10
- 没有quick-action-task权限的用户不能使用任务管理功能

### 2.4 报销申请（expense_records）
- 字段：id, user_id, amount, category, description, status, created_at
- 查询我的报销：SELECT * FROM expense_records WHERE user_id=? ORDER BY created_at DESC LIMIT 10
- 没有quick-action-reimbursement权限的用户不能使用报销功能

### 2.5 产品与库存
- products表：id, name, category, price, unit, status
- inventory表：product_id, quantity, warehouse_id
- 可用Function：get_products, get_inventory

### 2.6 权限说明
当前用户的权限列表：${userPermissions.length > 0 ? userPermissions.join(', ') : '（无 explicit 权限，按角色默认）'}

权限key格式说明：
- quick-action-check-in = 考勤打卡
- quick-action-worklog = 工作日志
- quick-action-task = 我的任务
- quick-action-reimbursement = 报销申请
- quick-action-attendance = 考勤记录查看（管理员）
- quick-action-profile = 个人信息

如果用户询问某个功能，先检查权限列表，如果没有对应权限权限，告知用户。

## 三、知识库系统

知识库存放各企业的产品和业务信息。回答时：
- 优先从知识库检索相关企业/产品/客服内容
- 如果知识库中没有该企业的信息，明确告知用户"当前企业知识库中暂无此信息"
- 知识库是动态的，每个企业不同，不要假设企业业务

## 四、系统数据查询

可通过Function Calling查询实时数据：产品列表、库存、订单、用户信息、考勤等。
查询后结合结果回答，不要凭记忆编造数字。

## 五、记忆系统

系统有分层记忆：
- 【系统公共记忆】：所有企业共享的基础信息
- 【用户相关事实】：该用户的历史信息（姓名、职位、公司等）
- 【用户偏好】：用户的个人偏好设置

## 六、欢迎语规范

根据是否有用户姓名判断：
- 有姓名：使用"您好，{姓名}！我是{botName}，有什么可以帮您？"
- 无姓名：使用"您好！我是{botName}，有什么可以帮您？"
- 严禁说"波哥"或其他未经记忆确认的称呼

请严格遵循以上规则！`

    const historyContext = reversedHistory.length > 0
      ? `\n\n以下是对话历史：\n${reversedHistory.map(h => `用户: ${h.query}\n${botName}: ${h.response}`).join('\n')}`
      : ''

    // 7. 构建带RAG的完整prompt
    const fullPrompt = `${systemPrompt}${memoryContext}${ragContext}${historyContext}\n\n用户: ${message}\n${botName}:`

    // 8. 定义Function Calling工具
    const functions = [
      {
        name: 'get_products',
        description: '查询彩美特系统的产品列表，可以按产品名称或分类筛选',
        parameters: {
          type: 'object',
          properties: {
            keyword: { type: 'string', description: '产品名称关键词（可选）' },
            category: { type: 'string', description: '产品分类，如"电子产品"、"服装"等（可选）' }
          }
        }
      },
      {
        name: 'get_inventory',
        description: '查询指定产品的库存数量',
        parameters: {
          type: 'object',
          properties: {
            product_id: { type: 'integer', description: '产品ID（必填）' }
          },
          required: ['product_id']
        }
      },
      {
        name: 'get_orders',
        description: '查询用户的订单列表',
        parameters: {
          type: 'object',
          properties: {
            user_id: { type: 'integer', description: '用户ID（必填）' },
            status: { type: 'string', description: '订单状态筛选，如"pending"、"completed"、"cancelled"（可选）' }
          },
          required: ['user_id']
        }
      },
      {
        name: 'get_user_info',
        description: '查询用户的基本信息',
        parameters: {
          type: 'object',
          properties: {
            user_id: { type: 'integer', description: '用户ID（必填）' }
          },
          required: ['user_id']
        }
      },
      {
        name: 'get_attendance',
        description: '查询用户的考勤打卡记录',
        parameters: {
          type: 'object',
          properties: {
            user_id: { type: 'integer', description: '用户ID（可选，默认当前用户）' },
            date_range: { type: 'string', description: '日期范围：today/today/month/all（默认today）' }
          }
        }
      },
      {
        name: 'search_knowledge',
        description: '在知识库中搜索相关内容，用于回答用户咨询',
        parameters: {
          type: 'object',
          properties: {
            keyword: { type: 'string', description: '搜索关键词（必填）' }
          },
          required: ['keyword']
        }
      }
    ]

    // 9. 调用AI（支持function calling，带10秒超时）
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
          functions: functions,
          max_tokens: 1500,
          temperature: 0.7
        }),
        signal: controller.signal
      })
      clearTimeout(timeout)

      if (response.ok) {
        const data = await response.json()
        const assistantMessage = data.choices?.[0]?.message

        // 处理Function Calling响应
        if (assistantMessage?.function_call) {
          const fnCall = assistantMessage.function_call
          const fnName = fnCall.name
          const fnArgs = JSON.parse(fnCall.arguments || '{}')

          let functionResult = '执行失败'
          try {
            switch (fnName) {
              case 'get_products': {
                let query = 'SELECT id, name, category, price FROM products WHERE 1=1'
                const params = []
                if (fnArgs.keyword) {
                  query += ' AND name LIKE ?'
                  params.push(`%${fnArgs.keyword}%`)
                }
                if (fnArgs.category) {
                  query += ' AND category = ?'
                  params.push(fnArgs.category)
                }
                query += ' LIMIT 10'
                const [rows] = await pool.query(query, params)
                functionResult = JSON.stringify(rows)
                break
              }
              case 'get_inventory': {
                const [rows] = await pool.query(
                  'SELECT product_id, quantity FROM inventory WHERE product_id = ?',
                  [fnArgs.product_id]
                )
                functionResult = JSON.stringify(rows[0] || { message: '未找到库存信息' })
                break
              }
              case 'get_orders': {
                const [rows] = await pool.query(
                  'SELECT id, status, total_price, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10',
                  [fnArgs.user_id]
                )
                functionResult = JSON.stringify(rows)
                break
              }
              case 'get_user_info': {
                const [rows] = await pool.query(
                  'SELECT id, username, email, created_at FROM users WHERE id = ?',
                  [fnArgs.user_id]
                )
                functionResult = JSON.stringify(rows[0] || { message: '未找到用户信息' })
                break
              }
              case 'get_attendance': {
                const targetUserId = fnArgs.user_id || userId
                let dateCondition = "date = CURDATE()"
                if (fnArgs.date_range === 'month') {
                  dateCondition = "date BETWEEN DATE_FORMAT(NOW(),'%Y-%m-01') AND LAST_DAY(NOW())"
                } else if (fnArgs.date_range === 'all') {
                  dateCondition = '1=1'
                }
                const [rows] = await pool.query(
                  `SELECT id, date, clock_in, clock_out, status, late_minutes, early_minutes, overtime_hours, location, check_in_time, check_out_time 
                   FROM attendance WHERE user_id = ? AND ${dateCondition} ORDER BY date DESC LIMIT 20`,
                  [targetUserId]
                )
                functionResult = JSON.stringify(rows)
                break
              }
              case 'search_knowledge': {
                const [rows] = await pool.query(
                  'SELECT title, content, doc_type FROM ai_class_knowledge WHERE title LIKE ? OR content LIKE ? LIMIT 5',
                  [`%${fnArgs.keyword}%`, `%${fnArgs.keyword}%`]
                )
                functionResult = JSON.stringify(rows)
                break
              }
              default:
                functionResult = `未知函数: ${fnName}`
            }
          } catch (dbErr) {
            console.error('[ai-class] Function calling DB error:', dbErr.message)
            functionResult = `数据库查询失败: ${dbErr.message}`
          }

          // 将function_result告诉AI，让它生成最终回复
          const controller2 = new AbortController()
          const timeout2 = setTimeout(() => controller2.abort(), 10000)
          const response2 = await fetch(`${baseUrl}/chat/completions`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              ...(llmConfig?.api_key ? { 'Authorization': `Bearer ${llmConfig.api_key}` } : {})
            },
            body: JSON.stringify({
              model: model,
              messages: [
                { role: 'user', content: fullPrompt },
                assistantMessage,
                {
                  role: 'function',
                  name: fnName,
                  content: functionResult
                }
              ],
              max_tokens: 1000,
              temperature: 0.7
            }),
            signal: controller2.signal
          })
          clearTimeout(timeout2)

          if (response2.ok) {
            const data2 = await response2.json()
            reply = data2.choices?.[0]?.message?.content || reply
          }
        } else {
          reply = assistantMessage?.content || reply
        }
      } else {
        console.error('[ai-class] LLM API error:', response.status, await response.text())
      }
    } catch (fetchErr) {
      console.error('[ai-class] LLM fetch error:', fetchErr.message)
    }

    // 10. 保存对话
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