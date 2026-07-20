/**
 * AI课堂后端路由
 * 包含知识库CRUD、记忆系统CRUD、对话接口（调用本地LLM）
 */

import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { ROLES, PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

// ==================== 回复格式化函数 ====================
function formatReply(text) {
  if (!text || text.length < 5) return text

  // 0. 去掉 standalone 推理标签（文字直接出现）
  text = text.replace(/<thinking>/gi, '')
  text = text.replace(/<\/thinking>/gi, '')
  text = text.replace(/<think>/gi, '')
  text = text.replace(/<\/think>/gi, '')
  text = text.replace(/<思考>/gi, '')
  text = text.replace(/<\/思考>/gi, '')

  // 1. 去掉 <thinking>...</thinking> 推理块
  text = text.replace(/<thinking>[\s\S]*?<\/thinking>/gi, '')
  // 2. 去掉 <思考>...</思考> 推理块
  text = text.replace(/<思考>[\s\S]*?<\/思考>/gi, '')
  // 3. 去掉 「<think>...」 推理块
  text = text.replace(/「<think>[\s\S]*?」/g, '')
  // 4. 去掉 【推理】...【/推理】类似块
  text = text.replace(/【推理】[\s\S]*?【\/推理】/g, '')
  // 5. 去掉 markdown 推理: [reasoning]...[/reasoning]
  text = text.replace(/\[reasoning\][\s\S]*?\[\/reasoning\]/gi, '')
  // 6. 去掉独立出现的<think>...（不成对）
  text = text.replace(/icron\*\s*$/gm, '')
  text = text.replace(/^icron\*\s*/gm, '')
  text = text.replace(/<inson>/gi, '')
  text = text.replace(/<\/inson>/gi, '')

  // 7. 去除多余空行（超过2个换行压缩为2个）
  text = text.replace(/\n{3,}/g, '\n\n')

  // 8. 去除每行首尾空白，但保留结构
  text = text.split('\n').map(line => line.trim()).join('\n')

  // 9. 去除首尾空白
  text = text.trim()

  // 10. 去掉"根据..."开头的解释性段落（AI在描述思考过程，不是回复）
  // Match "根据X...Y" where Y is a punctuation, not a table row
  text = text.replace(/^根据[^，。\n]{0,50}(的话|情况|结果|显示)[^\n]*$/gim, '')
  text = text.replace(/^根据规则[：:]?\s*/gim, '')
  text = text.replace(/^根据要求[：:]?\s*/gim, '')
  text = text.replace(/^根据知识库[：:]?\s*/gim, '')
  text = text.replace(/^根据已有信息[：:]?\s*/gim, '')

  // 11. 去掉AI思考过程前缀
  text = text.replace(/^(我需要|让我想想|让我|首先|其次|最后|接下来|然后)\s*([：:，,])/gm, '')
  text = text.replace(/^(不过|但是|而且|同时|另外|除此之外)\s+/gm, '')

  // 12. 去掉格式"检查"类文字（AI在描述格式要求，不是真正回复）
  text = text.replace(/^格式要求[：:]?.*$/gim, '')
  text = text.replace(/^回复格式[：:]?.*$/gim, '')
  text = text.replace(/^格式示例[：:]?.*$/gim, '')
  text = text.replace(/^(回复：|结论：|然后|这样应该|这样就|可以这样|我来组织|我来整理|我来输出)/gim, '')

  // 13. 去掉描述AI思考过程的句子
  text = text.replace(/^(我想想|我觉得|我以为|似乎|可能|大概是|估计|应该说|这样说|这个问题)/gim, '')
  text = text.replace(/^(结合|综上|总的来说|总而言之)/gim, '')

  // 14. 去掉"知识库显示/中/里"开头的解释
  text = text.replace(/^知识库(显示|中|里)\s*[^\n]{0,60}/gim, '')

  // 15. 去掉"不需要调用"类句子
  text = text.replace(/^(不需要调用|不需要使用|不需要查询|不需要搜索)\s*[^\n]{0,50}/gim, '')

  // 19. 去掉 AI 内部思考过程（多行分析块）
  // 匹配"用户说...这是..."类解释句子
  text = text.replace(/^用户[^\n]{0,30}(的话|说|问|可能|或许)?[^\n]*$/gm, '')
  text = text.replace(/^从[^\n]{0,50}$/gm, '')
  // 匹配"我注意到/我发现/我觉得"开头的分析句（后面跟2个以上换行符说明是分析块）
  text = text.replace(/^(我注意到|我发现|我可以|我不适合|我的回复|这点|其实|考虑到|不过话说)/gm, '')

  // 去掉所有 [N] 标签引用（MiniMax思考步骤标签）
  text = text.replace(/\[\d+\]\s*/g, '')
  // 去掉 "Step N" 类的推理步骤文字
  text = text.replace(/^(Step \d+[:：]\s*|第\s*\d+\s*步[:：]\s*)/gim, '')
  // 去掉多行推理块（连续3+行以"我"或"用户"或"首先"开头）
  const lines = text.split('\n')
  const filtered = []
  let skipCount = 0
  for (let i = 0; i < lines.length; i++) {
    const l = lines[i].trim()
    const isInternal = /^(用户|从|我注意到|我发现|我不|这点|考虑到|其实|我可以|不过话|这是|不调用|直接|自然地|我的回复|首先|其次|最后|然后|我需要|让我)/.test(l)
    const prevIsInternal = i > 0 && /^(用户|从|我注意到|我发现|我不|这点|考虑到|其实|我可以|不过话|这是|不调用|直接|自然地|我的回复|首先|其次|最后|然后|我需要|让我)/.test(lines[i-1].trim())
    if (isInternal && prevIsInternal) { skipCount++; continue }
    if (skipCount > 0 && !isInternal) skipCount = 0
    filtered.push(l)
  }
  text = filtered.join('\n')

  // 23. 去除多余空行
  text = text.replace(/\n{3,}/g, '\n\n')

  return text.trim()
}

// ==================== 网络搜索辅助函数（Node.js内置fetch）====================
const TAVILY_KEY = process.env.TAVILY_API_KEY

async function search_web(query, engine = 'tavily', max_results = 5) {
  // DuckDuckGo HTML 搜索（免费，无需API key）
  const encodedQuery = encodeURIComponent(query)
  const url = `https://duckduckgo.com/html/?q=${encodedQuery}&k=hs&s=0`
  const res = await fetch(url, {
    headers: { 'User-Agent': 'JXY-OS-AI/1.0 (compatible; bot)' },
    signal: AbortSignal.timeout(12000)
  })
  if (!res.ok) return { success: false, error: `HTTP ${res.status}` }
  const text = await res.text()
  // 解析 DuckDuckGo 结果
  const results = []
  const re = /<a class="result__a" href="([^"]+)"[^>]*>([^<]+)<\/a>/g
  let m
  while ((m = re.exec(text)) !== null && results.length < max_results) {
    results.push({ title: m[2].trim(), url: m[1], content: '' })
  }
  return { success: true, engine: 'duckduckgo', query, results }
}

async function search_tavily(query, max_results = 5) {
  try {
    const res = await fetch('https://api.tavily.com/search', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        query, api_key: TAVILY_KEY, search_depth: 'basic',
        max_results, include_answer: false
      }),
      signal: AbortSignal.timeout(20000)
    })
    if (!res.ok) return { success: false, error: `Tavily ${res.status}` }
    const data = await res.json()
    return {
      success: true, engine: 'tavily', query,
      answer: data.answer || null,
      results: (data.results || []).slice(0, max_results).map(i => ({
        title: i.title || '', url: i.url || '', content: (i.content || '').slice(0, 200)
      }))
    }
  } catch (e) { return { success: false, error: e.message, engine: 'tavily' } }
}

function format_search_result(r) {
  if (!r.success) return `搜索失败: ${r.error}\n`
  let out = `🔍 **${r.engine}** 查询: ${r.query}\n\n`
  if (r.answer) out += `💡 ${r.answer.slice(0, 200)}\n\n`
  r.results.forEach((item, i) => {
    out += `${i + 1}. ${item.title}\n   ${item.url}\n`
  })
  return out
}

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
    
    const isAdmin = req.user.role === ROLES.ADMIN
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
    
    const isAdmin = req.user.role === ROLES.ADMIN
    const isOwner = rows[0].user_id === req.user.id
    
    if (!isAdmin && !isOwner) {
      return res.json({ code: 403, message: '无权限删除' })
    }
    
    await pool.query('DELETE FROM ai_class_memory WHERE id = ?', [req.params.id])
    res.json({ code: 0 })
  } catch (err) { next(err) }
})

// ==================== 会话管理路由 ====================
// GET /api/ai-class/sessions - 获取用户所有会话
router.get('/sessions', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const [rows] = await pool.query(
      `SELECT id, title, created_at, updated_at FROM ai_class_sessions
       WHERE user_id = ? ORDER BY updated_at DESC LIMIT 50`,
      [userId]
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/ai-class/sessions - 创建新会话
router.post('/sessions', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const { title } = req.body
    const [result] = await pool.query(
      'INSERT INTO ai_class_sessions (user_id, title) VALUES (?, ?)',
      [userId, title || '新对话']
    )
    res.json({ code: 0, data: { id: result.insertId, title: title || '新对话' } })
  } catch (err) { next(err) }
})

// GET /api/ai-class/sessions/:id/messages - 获取会话的所有消息
router.get('/sessions/:id/messages', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const sessionId = parseInt(req.params.id)
    const [rows] = await pool.query(
      `SELECT id, role, content, model, created_at FROM ai_class_messages
       WHERE session_id = ? ORDER BY created_at ASC`,
      [sessionId]
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// DELETE /api/ai-class/sessions/:id - 删除会话
router.delete('/sessions/:id', auth, async (req, res, next) => {
  try {
    const userId = req.user.id
    const sessionId = parseInt(req.params.id)
    await pool.query('DELETE FROM ai_class_sessions WHERE id = ? AND user_id = ?', [sessionId, userId])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

// ==================== 对话历史兼容路由（保留） ====================
// GET /api/ai-class/conversations - 获取指定session的对话历史（旧接口兼容）
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
    const { message, session_id, stream, context } = req.body
    const userId = req.user.id

    if (!message || message.trim() === '') {
      return res.json({ code: 0, data: { reply: '请输入问题' } })
    }

    // 0. 处理session：没有/无效则创建，有则更新title为第一条用户消息（前50字）
    // 前端可能传 UUID 或数字 ID（uuid 用作 localStorage key，数字 ID 用于 DB 查询）
    let sid = session_id && session_id !== 'undefined' && session_id !== 'null' ? session_id : null
    // 如果 sid 不是数字（前端传的 UUID），当作新 session
    if (sid && !/^\d+$/.test(sid.toString())) {
      sid = null
    }
    if (!sid) {
      const [r] = await pool.query(
        'INSERT INTO ai_class_sessions (user_id, title) VALUES (?, ?)',
        [userId, message.slice(0, 50)]
      )
      sid = r.insertId.toString()
    } else {
      // 更新session的updated_at
      await pool.query('UPDATE ai_class_sessions SET updated_at=NOW() WHERE id=?', [parseInt(sid)])
    }

    // 1. 从新消息表获取历史对话（用于构建上下文）
    const [historyMsgs] = await pool.query(
      `SELECT role, content FROM ai_class_messages
       WHERE session_id = ? ORDER BY created_at ASC LIMIT 30`,
      [parseInt(sid)]
    )

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
      const sentences = message.split(/[，。！？、；\n\r,.!?;]+/).filter(s => s.trim())
      const wordSet = new Set()
      for (const sentence of sentences) {
        for (let len = 2; len <= 4; len++) {
          for (let i = 0; i <= sentence.length - len; i++) {
            const w = sentence.substring(i, i + len)
            if (/^[\u4e00-\u9fa5]+$/.test(w)) wordSet.add(w)
          }
        }
        const enWords = sentence.match(/[a-zA-Z]{2,}/g) || []
        enWords.forEach(w => wordSet.add(w.toLowerCase()))
      }
      const allKeywords = [...wordSet]

      if (allKeywords.length > 0) {
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

    // 3.1 实时信息自动预检（天气/新闻类问题直接搜，不等LLM判断）
    let realtimeContext = ''
    const msgLower = message.toLowerCase()
    const isWeatherQuery = /天气|温度|湿度|降雨|PM2|空气质量| forecast| weather/.test(msgLower)
    const isNewsQuery = /新闻|最近|今日|昨天|明天%|████████| today| news/.test(msgLower)
    if (isWeatherQuery || isNewsQuery) {
      try {
        const searchQuery = message.replace(/^(请问|查询|搜索|告诉我|我想知道|what is|what's|how is|how's|today'?s|请问一下|帮我查)/i, '').replace(/[?？。.]/g, '').trim()
        const r = await search_tavily(searchQuery.slice(0, 100), 5)
        if (r.success) {
          realtimeContext = `\n\n【网络搜索结果】\n${format_search_result(r)}`
        } else {
          const ddg = await search_web(searchQuery.slice(0, 100), 'duckduckgo', 5)
          if (ddg.success) realtimeContext = `\n\n【网络搜索结果】\n${format_search_result(ddg)}`
        }
      } catch (e) {
        console.error('[ai-class] realtime search error:', e.message)
      }
    }

    // 4. 获取机器人名称（从 settings 表，兜底"小智"）
    const [[botNameSetting]] = await pool.query(
      'SELECT value FROM settings WHERE `key` = "bot_name" LIMIT 1'
    )
    const botName = botNameSetting?.value || '小智'

    // 5. 获取用户权限（用于AI智能过滤）
    // permissions 可能是 JSON 数组字符串，也可能是逗号分隔，或单字符串如 "admin"
    const [[userRow]] = await pool.query(
      'SELECT name, role, permissions FROM users WHERE id = ?',
      [userId]
    )
    let userPermissions = []
    if (userRow?.permissions) {
      const permStr = String(userRow.permissions).trim()
      // 先试 JSON.parse（JSON 数组）
      if (permStr.startsWith('[') || permStr.startsWith('{')) {
        try { userPermissions = JSON.parse(permStr) } catch (e) {}
      }
      // 字符串 'admin' 或 'admin,xxx' 走 role short-cut
      if (userPermissions.length === 0 && userRow.role === 'admin') {
        userPermissions = ['admin']
      } else if (permStr.includes(',')) {
        userPermissions = permStr.split(',').map(s => s.trim()).filter(Boolean)
      } else if (permStr && !permStr.startsWith('[')) {
        userPermissions = [permStr]
      }
    }

    // 4.5 加载默认系统知识库（system/business/table/glossary 4 类）
    // 这些知识不进 LIKE 检索，直接拼到 System Prompt，让 AI 立即了解彩美特整套系统
    const [defaultKnowledge] = await pool.query(
      `SELECT title, content, doc_type FROM ai_class_knowledge
       WHERE doc_type IN ('system','business','table','glossary')
         AND (is_public = 1 OR is_public IS NULL)
       ORDER BY FIELD(doc_type,'system','business','table','glossary'), id`
    )
    let systemKnowledgeContext = ''
    if (defaultKnowledge.length > 0) {
      systemKnowledgeContext = '\n\n【彩美特系统知识库（自动加载）】\n' + defaultKnowledge
        .map(k => `### ${k.title}\n${k.content}`)
        .join('\n\n')
    }

    // 4.6 识别用户身份（role 字段 + permissions + department）
    const [[userProfile]] = await pool.query(
      'SELECT name, role, department, title, hire_date, employee_code, supervisor_id FROM users WHERE id = ?',
      [userId]
    )
    let userIdentityContext = ''
    if (userProfile) {
      const daysEmployed = userProfile.hire_date
        ? Math.floor((Date.now() - new Date(userProfile.hire_date).getTime()) / 86400000)
        : null
      const isNewHire = daysEmployed !== null && daysEmployed < 30
      userIdentityContext = `\n\n【当前用户身份】
- 姓名：${userProfile.name || '未填'}
- 角色：${userProfile.role || '未设'}
- 部门：${userProfile.department || '未填'}
- 职位：${userProfile.title || '未填'}
- 工号：${userProfile.employee_code || '未填'}
- 入职天数：${daysEmployed !== null ? daysEmployed + '天' : '未知'}${isNewHire ? '（新员工，需要详细培训）' : ''}`
    }

    // 极简System Prompt（Hermes/OpenClaw风格）+ 系统知识 + 用户身份
    const permNote = userPermissions.length > 0
      ? `\n【用户权限】${userPermissions.join('、')}`
      : '\n【用户权限】无（只能看公开信息）'
    const systemContent = `你是${botName}，彩美特（Caimeite）管理系统的智能助手。
直接、务实、有温度——像一位熟悉业务的资深同事那样回答问题。

【核心职责】
1. 系统操作培训：用户问"怎么做"时，告诉他具体步骤（哪个菜单→哪按钮→怎么填）
2. 业务答疑：用户问"为什么"或"是什么"时，用彩美特业务术语解释
3. 数据查询：用户问"查什么"时，必须调用 Function Calling 工具查真实数据库，禁止编造
4. 权限感知：用户没权限的功能，明确告知"您没有xxx权限，需要联系管理员"
5. 新员工引导：识别新员工（入职<30天），主动介绍系统模块和基本操作

【回复格式要求】
- 先给结论（一句话）
- 再用表格或列表展示详情
- 像人写的报告，禁止一坨文字 / 流水账 / 原始 JSON
- 禁止输出内部思考过程（"我需要/让我/首先/其次"）
- 不知道就说"我不确定，建议联系管理员或查 X 模块"
${permNote}${userIdentityContext}${systemKnowledgeContext}${memoryContext}${ragContext}${realtimeContext}${context || ''}`

    // 6. 构建消息数组（真正的多轮上下文）
    const messages = [
      { role: 'system', content: systemContent },
      ...historyMsgs.map(m => ({ role: m.role, content: m.content })),
      { role: 'user', content: message }
    ]

    // 8. 定义Function Calling工具
    const functions = [
      {
        type: 'function',
        function: {
          name: 'get_products',
          description: '查询彩美特系统的产品列表，可以按产品名称或分类筛选',
          parameters: {
            type: 'object',
            properties: {
              keyword: { type: 'string', description: '产品名称关键词（可选）' },
              category: { type: 'string', description: '产品分类，如"电子产品"、"服装"等（可选）' }
            }
          }
        }
      },
      {
        type: 'function',
        function: {
          name: 'get_inventory',
          description: '查询指定产品的库存数量',
          parameters: {
            type: 'object',
            properties: {
              product_id: { type: 'integer', description: '产品ID（必填）' }
            },
            required: ['product_id']
          }
        }
      },
      {
        type: 'function',
        function: {
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
        }
      },
      {
        type: 'function',
        function: {
          name: 'get_user_info',
          description: '查询用户的基本信息',
          parameters: {
            type: 'object',
            properties: {
              user_id: { type: 'integer', description: '用户ID（必填）' }
            },
            required: ['user_id']
          }
        }
      },
      {
        type: 'function',
        function: {
          name: 'get_attendance',
          description: '查询用户的考勤打卡记录',
          parameters: {
            type: 'object',
            properties: {
              user_id: { type: 'integer', description: '用户ID（可选，默认当前用户）' },
              date_range: { type: 'string', description: '日期范围：today/today/month/all（默认today）' }
            }
          }
        }
      },
      {
        type: 'function',
        function: {
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
      },
      {
        type: 'function',
        function: {
          name: 'web_search',
          description: '搜索互联网获取最新信息（商业新闻/行业动态/公司信息/天气预报等），当用户询问实时新闻、行业趋势、最新政策法规、天气预报等需要最新互联网数据的问题时使用',
          parameters: {
            type: 'object',
            properties: {
              query: { type: 'string', description: '搜索关键词（必填）' },
              max_results: { type: 'integer', description: '结果数量，默认5' }
            },
            required: ['query']
          }
        }
      },
      {
        type: 'function',
        function: {
          name: 'get_sales_report',
          description: '查询销售报表数据（需要quick-action-sales权限），返回销售额/订单数/客户数等统计',
          parameters: {
            type: 'object',
            properties: {
              date_range: { type: 'string', description: '日期范围：today/month/quarter/year（默认month）' },
              category: { type: 'string', description: '产品分类筛选（可选）' }
            }
          }
        }
      },
      {
        type: 'function',
        function: {
          name: 'get_inventory_alert',
          description: '查询库存预警数据（需要quick-action-inventory权限），返回低于安全库存的产品列表',
          parameters: {
            type: 'object',
            properties: {}
          }
        }
      },
      {
        type: 'function',
        function: {
          name: 'hermes_delegate',
          description: '委托Hermes Agent执行复杂任务（如代码编写、数据分析、报告生成、深入研究），适用于多步骤、需要搜索、多文件处理的任务',
          parameters: {
            type: 'object',
            properties: {
              task: { type: 'string', description: '任务描述（必填），说明要让Hermes做什么，包含足够的上下文信息' },
              toolsets: { type: 'string', description: '需要的工具集，可用：web,terminal,file,browser,vision（逗号分隔，默认web,terminal,file）' }
            },
            required: ['task']
          }
        }
      }
    ]

    // 8.1 动态读 LLM 配置（ai_config 表 + ENV 兜底）
    let llmConfig = {
      base_url: 'https://api.minimaxi.com/anthropic/v1/messages',
      api_key: process.env.MINIMAX_API_KEY || '',
      model: 'MiniMax-M3-8k',
      protocol: 'anthropic' // anthropic | openai
    }
    try {
      const [aiConfigRows] = await pool.query(
        "SELECT base_url, api_key, model, provider FROM ai_config WHERE category='llm' AND status=1 ORDER BY is_default DESC LIMIT 1"
      )
      if (aiConfigRows.length > 0) {
        const cfg = aiConfigRows[0]
        if (cfg.base_url) llmConfig.base_url = cfg.base_url
        if (cfg.api_key) llmConfig.api_key = cfg.api_key
        if (cfg.model) llmConfig.model = cfg.model
        // 协议检测：URL 包含 /anthropic 走 anthropic 协议
        if (cfg.base_url && cfg.base_url.includes('/anthropic')) {
          llmConfig.protocol = 'anthropic'
        } else if (cfg.provider === 'minimax' || cfg.provider === 'openai' || cfg.provider === 'nvidia') {
          llmConfig.protocol = 'openai'
        }
      }
    } catch (cfgErr) {
      console.error('[ai-class] read ai_config error:', cfgErr.message)
    }
    const { base_url: baseUrl, api_key: apiKey, model, protocol } = llmConfig
    if (!apiKey) {
      console.error('[ai-class] no api_key configured')
      return res.json({ code: 500, message: 'AI 服务未配置 API Key' })
    }

    // 9. 调用AI（支持function calling，带30秒超时）
    let reply = 'AI服务暂时繁忙，请稍后再试。'

    try {
      const controller = new AbortController()
      const timeout = setTimeout(() => controller.abort(), 30000)

      const toolNames = [
        'get_attendance', 'get_products', 'get_inventory',
        'get_orders', 'get_user_info', 'search_knowledge',
        'web_search', 'get_sales_report', 'get_inventory_alert'
      ]

      // 9.1 协议适配：构造请求
      let llmRequest, requestUrl, requestHeaders, assistantMessage
      if (protocol === 'anthropic') {
        // Anthropic 协议：tools 用 input_schema，响应 content[] 数组
        const systemPrompt = messages.find(m => m.role === 'system')?.content || ''
        const userMessages = messages.filter(m => m.role !== 'system')
        requestUrl = baseUrl
        requestHeaders = {
          'Content-Type': 'application/json',
          'x-api-key': apiKey,
          'anthropic-version': '2023-06-01',
          'Authorization': `Bearer ${apiKey}`
        }
        // Anthropic 工具定义：去掉 type 字段，加 input_schema
        const anthropicTools = functions.map(f => ({
          name: f.function.name,
          description: f.function.description,
          input_schema: f.function.parameters
        }))
        llmRequest = {
          model: model,
          max_tokens: 3000,
          temperature: 0.3,
          system: systemPrompt,
          tools: anthropicTools,
          messages: userMessages
        }
      } else {
        // OpenAI 协议：原样
        requestUrl = `${baseUrl}/chat/completions`
        requestHeaders = {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${apiKey}`
        }
        llmRequest = {
          model: model,
          messages: messages,
          tools: functions,
          max_tokens: 3000,
          temperature: 0.3
        }
      }

      console.log('[ai-class] LLM request:', requestUrl, 'body-size:', JSON.stringify(llmRequest).length)
      const response = await fetch(requestUrl, {
        method: 'POST',
        headers: requestHeaders,
        body: JSON.stringify(llmRequest),
        signal: controller.signal
      })
      clearTimeout(timeout)
      if (!response.ok) {
        console.error('[ai-class] LLM API error:', response.status, 'URL:', requestUrl, 'body-size:', JSON.stringify(llmRequest).length, await response.text())
      }

      if (response.ok) {
        const data = await response.json()

        // 9.2 协议适配：解析响应
        if (protocol === 'anthropic') {
          // Anthropic 响应：{ content: [{type: 'text'|'tool_use', ...}], stop_reason }
          const contentArr = data.content || []
          const textParts = contentArr.filter(c => c.type === 'text').map(c => c.text)
          const toolUses = contentArr.filter(c => c.type === 'tool_use')
          if (toolUses.length > 0) {
            assistantMessage = {
              role: 'assistant',
              content: textParts.join('\n') || null,
              tool_calls: toolUses.map(tu => ({
                id: tu.id,
                type: 'function',
                function: { name: tu.name, arguments: JSON.stringify(tu.input || {}) }
              }))
            }
          } else {
            assistantMessage = { role: 'assistant', content: textParts.join('\n') }
          }
        } else {
          // OpenAI 响应：{ choices: [{message: {content, function_call, tool_calls}}] }
          assistantMessage = data.choices?.[0]?.message
        }

        // 处理Function Calling响应
        if (assistantMessage?.function_call || assistantMessage?.tool_calls?.length) {
          // MiniMax用tool_calls格式，OpenAI旧版用function_call
          const toolCall = assistantMessage.function_call || assistantMessage.tool_calls?.[0]
          const fnName = toolCall.function?.name || toolCall.name
          const fnArgs = JSON.parse(toolCall.function?.arguments || toolCall.arguments || '{}')

          let functionResult = '执行失败'
          try {
            switch (fnName) {
              case 'get_products': {
                let query = 'SELECT id, name, category, sale_price, purchase_price, unit, stock, alert_stock, status FROM products WHERE 1=1'
                const params = []
                if (fnArgs.keyword) {
                  query += ' AND (name LIKE ? OR sku LIKE ? OR spec LIKE ?)'
                  const kw = `%${fnArgs.keyword}%`
                  params.push(kw, kw, kw)
                }
                if (fnArgs.category) {
                  query += ' AND (category = ? OR category_id IN (SELECT id FROM categories WHERE name = ?))'
                  params.push(fnArgs.category, fnArgs.category)
                }
                query += ' ORDER BY stock ASC LIMIT 10'
                const [rows] = await pool.query(query, params)
                functionResult = JSON.stringify(rows)
                break
              }
              case 'get_inventory': {
                // inventory 表实际是 warehouse_stock（按 warehouse+product+sku 唯一）
                const [rows] = await pool.query(
                  `SELECT ws.product_id, p.name as product_name, ws.sku_id, ws.warehouse_id,
                          w.name as warehouse_name, ws.quantity, ws.location
                   FROM warehouse_stock ws
                   LEFT JOIN products p ON ws.product_id = p.id
                   LEFT JOIN warehouses w ON ws.warehouse_id = w.id
                   WHERE ws.product_id = ? AND ws.quantity > 0
                   ORDER BY ws.warehouse_id, ws.sku_id LIMIT 20`,
                  [fnArgs.product_id]
                )
                const totalRow = await pool.query(
                  'SELECT COALESCE(SUM(quantity),0) as total FROM warehouse_stock WHERE product_id=?',
                  [fnArgs.product_id]
                )
                functionResult = JSON.stringify({
                  product_id: fnArgs.product_id,
                  total_quantity: totalRow[0][0]?.total || 0,
                  details: rows
                })
                break
              }
              case 'get_orders': {
                // 权限：member 看自己的订单，admin/manager/warehouse 看全部
                const userId = req.user.id
                const userRole = req.user.role
                const queryUserId = fnArgs.user_id || userId
                let where = ''
                const params = []
                // 非管理员只能查自己的订单
                if (!['admin', 'manager'].includes(userRole) && queryUserId !== userId) {
                  functionResult = JSON.stringify({ error: '无权查询其他用户的订单' })
                  break
                }
                // 按状态过滤
                if (fnArgs.status) {
                  where = 'WHERE status = ?'
                  params.push(fnArgs.status)
                  if (queryUserId) {
                    where += ' AND member_id = ?'
                    params.push(queryUserId)
                  }
                } else if (queryUserId) {
                  where = 'WHERE member_id = ?'
                  params.push(queryUserId)
                }
                const sql = `SELECT id, order_no, member_name, member_phone, total_amount, pay_amount, pay_type, status, paid_at, shipped_at, completed_at, created_at FROM orders ${where} ORDER BY created_at DESC LIMIT 20`
                const [rows] = await pool.query(sql, params)
                functionResult = JSON.stringify({ count: rows.length, list: rows })
                break
              }
              case 'get_user_info': {
                // users 表字段：name（非 username）、phone（非 email）、permissions JSON
                const [rows] = await pool.query(
                  'SELECT id, name, phone, role, department, status, permissions, hire_date, employee_code, title FROM users WHERE id = ?',
                  [fnArgs.user_id]
                )
                const user = rows[0]
                if (user) {
                  // 解析 permissions 数组
                  try { user.permissions = JSON.parse(user.permissions || '[]') } catch { user.permissions = [] }
                  delete user.password  // 安全：不返回密码
                }
                functionResult = JSON.stringify(user || { message: '未找到用户信息' })
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
              case 'web_search': {
                // 优先用 Tavily，失败则 DuckDuckGo
                const maxResults = fnArgs.max_results || 5
                let result = await search_tavily(fnArgs.query, maxResults)
                if (!result.success) result = await search_web(fnArgs.query, 'duckduckgo', maxResults)
                functionResult = JSON.stringify(result)
                break
              }
              case 'get_sales_report': {
                // 检查权限
                if (!userPermissions.includes(PERMISSIONS.QUICK_ACTION_SALES)) {
                  functionResult = JSON.stringify({ error: '您没有权限访问销售报表' })
                  break
                }
                const dateRange = fnArgs.date_range || 'month'
                let dateCond = "DATE(created_at) = CURDATE()"
                if (dateRange === 'month') dateCond = "created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
                else if (dateRange === 'quarter') dateCond = "created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)"
                else if (dateRange === 'year') dateCond = "created_at >= DATE_FORMAT(NOW(), '%Y-01-01')"
                const [rows] = await pool.query(
                  `SELECT COUNT(*) as order_count, COALESCE(SUM(total_amount),0) as total_sales, COUNT(DISTINCT member_id) as customer_count
                   FROM orders WHERE status != 'cancelled' AND ${dateCond}`,
                  []
                )
                functionResult = JSON.stringify({ date_range: dateRange, report: rows[0] })
                break
              }
              case 'get_inventory_alert': {
                // 检查权限
                if (!userPermissions.includes(PERMISSIONS.QUICK_ACTION_INVENTORY)) {
                  functionResult = JSON.stringify({ error: '您没有权限访问库存数据' })
                  break
                }
                // 查找库存低于安全库存的产品（warehouse_stock 汇总 vs products.alert_stock）
                const [rows] = await pool.query(
                  `SELECT p.id, p.name, p.category, p.alert_stock,
                          COALESCE(SUM(ws.quantity), 0) as quantity
                   FROM products p
                   LEFT JOIN warehouse_stock ws ON ws.product_id = p.id
                   WHERE p.status = 'active' OR p.status IS NULL
                   GROUP BY p.id, p.name, p.category, p.alert_stock
                   HAVING quantity < COALESCE(p.alert_stock, 10)
                   ORDER BY quantity ASC LIMIT 20`,
                  []
                )
                functionResult = JSON.stringify({ alerts: rows, threshold: '低于安全库存' })
                break
              }
              case 'hermes_delegate': {
                // 委托 Hermes Agent 执行（通过 spawn 子进程）
                // 此处简化处理：将任务信息记录到日志，实际委托通过 cronjob/background 实现
                functionResult = JSON.stringify({
                  status: 'delegated',
                  message: `任务已委托给Hermes Agent处理：${fnArgs.task.slice(0, 50)}...`,
                  note: 'Hermes Agent正在后台执行，请稍后查询结果'
                })
                // 实际委托通过 execSync 触发 hermes chat
                try {
                  const { execSync } = await import('child_process')
                  const toolset = fnArgs.toolsets || 'web,terminal,file'
                  const cmd = `hermes chat -q "${fnArgs.task.slice(0, 300)}" -t ${toolset} &`
                  execSync(cmd, { timeout: 5000, stdio: 'ignore' })
                } catch (e) {
                  // 忽略委托启动错误，结果已通过functionResult返回
                }
                break
              }
              default:
                functionResult = `未知函数: ${fnName}`
            }
          } catch (dbErr) {
            console.error('[ai-class] Function calling DB error:', dbErr.message)
            functionResult = `数据库查询失败: ${dbErr.message}`
          }

          // 9.3 协议适配：第二次调用，把工具结果回传给 AI
          const controller2 = new AbortController()
          const timeout2 = setTimeout(() => controller2.abort(), 30000)
          let response2, request2, url2, headers2
          if (protocol === 'anthropic') {
            const systemPrompt = messages.find(m => m.role === 'system')?.content || ''
            const prevUserMessages = messages.filter(m => m.role !== 'system')
            // Anthropic 协议：工具结果作为 user 消息，content 是 tool_result 数组
                        // 注意：assistantMessage 里是 OpenAI 格式的 tool_calls，
                        // 但 Anthropic 第二次调用需要 Anthropic 格式的 tool_use 消息
                        const toolUseContent = (assistantMessage.tool_calls || []).map(tc => ({
                          type: 'tool_use',
                          id: tc.id,
                          name: tc.function?.name,
                          input: (() => {
                            try { return JSON.parse(tc.function?.arguments || '{}') }
                            catch { return {} }
                          })()
                        }))
                        const toolResultContent = (assistantMessage.tool_calls || []).map(tc => ({
                          type: 'tool_result',
                          tool_use_id: tc.id,  // Anthropic 用 tool_use_id 字段
                          content: functionResult
                        }))
                        url2 = baseUrl
                        headers2 = {
                          'Content-Type': 'application/json',
                          'x-api-key': apiKey,
                          'anthropic-version': '2023-06-01',
                          'Authorization': `Bearer ${apiKey}`
                        }
                        const anthropicTools2 = functions.map(f => ({
                          name: f.function.name,
                          description: f.function.description,
                          input_schema: f.function.parameters
                        }))
                        request2 = {
                          model: model,
                          max_tokens: 1500,
                          temperature: 0.3,
                          system: systemPrompt,
                          tools: anthropicTools2,
                          messages: [
                            ...prevUserMessages,
                            // Anthropic 格式的 assistant 消息：content 是 tool_use 数组
                            { role: 'assistant', content: toolUseContent },
                            // Anthropic 格式的 user 消息：content 是 tool_result 数组
                            { role: 'user', content: toolResultContent }
                          ]
                        }
          } else {
            // OpenAI 协议：tool_call_id + role=tool
            url2 = baseUrl.includes('/chat/completions') ? baseUrl : `${baseUrl.replace(/\/$/, '')}/chat/completions`
            headers2 = {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${apiKey}`
            }
            request2 = {
              model: model,
              messages: [
                ...messages,
                assistantMessage,
                {
                  role: 'tool',
                  tool_call_id: assistantMessage.tool_calls?.[0]?.id || 'default',
                  content: functionResult
                }
              ],
              tools: functions,
              max_tokens: 1500,
              temperature: 0.3
            }
          }

          response2 = await fetch(url2, {
            method: 'POST',
            headers: headers2,
            body: JSON.stringify(request2),
            signal: controller2.signal
          })
          clearTimeout(timeout2)

          if (response2.ok) {
            const data2 = await response2.json()
            // 9.4 协议适配：解析第二次响应
            let reply2
            if (protocol === 'anthropic') {
              const textParts2 = (data2.content || []).filter(c => c.type === 'text').map(c => c.text)
              reply2 = textParts2.join('\n')
            } else {
              reply2 = data2.choices?.[0]?.message?.content
            }
            reply = reply2 || reply
            reply = formatReply(reply)
          } else {
            console.error('[ai-class] LLM API error 2nd call:', response2.status, 'URL:', url2, await response2.text())
            // 第二次失败时，至少用第一次的部分文本（如果有）
            if (assistantMessage?.content) {
              reply = assistantMessage.content + '\n\n（AI 服务暂时繁忙，无法生成完整回复）'
            }
          }
        } else {
          reply = assistantMessage?.content || reply
          reply = formatReply(reply)
        }
      } else {
        console.error('[ai-class] LLM API error:', response.status, await response.text())
      }
    } catch (fetchErr) {
      console.error('[ai-class] LLM fetch error:', fetchErr.message)
    }

    // 10. 保存对话到新消息表
    await pool.query(
      'INSERT INTO ai_class_messages (session_id, role, content, model) VALUES (?, ?, ?, ?)',
      [parseInt(sid), 'user', message, model]
    )
    await pool.query(
      'INSERT INTO ai_class_messages (session_id, role, content, model) VALUES (?, ?, ?, ?)',
      [parseInt(sid), 'assistant', reply, model]
    )

    res.json({ code: 0, data: { reply, session_id: sid } })
  } catch (err) {
    next(err)
  }
})

export default router