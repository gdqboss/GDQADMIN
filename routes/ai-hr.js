/**
 * ai-hr.js —— AI 招聘助手（应聘者对话 + HR 报告管理）
 *
 * Slice 2 范围: 骨架
 *   - 列出开放岗位
 *   - 取岗位预设条件
 *   - 创建对话 session
 *   - 投递用户消息（echo 模式，slice 3 接 LLM）
 *   - HR 看报告列表/详情（slice 5 接前端）
 *   - HR 配置岗位预设条件
 *
 * 2026-08-27 江小鱼
 */

import { Router } from 'express'
import crypto from 'node:crypto'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission, PERMISSIONS as P } from '../middleware/rbac.js'

const router = Router()
const READ = requirePermission(P.AI_HR_READ)
const WRITE = requirePermission(P.AI_HR_WRITE)
const DEL = requirePermission(P.AI_HR_DELETE)

// ============================================================
// 公共接口（应聘者端，minip 匿名访问）
// ============================================================

/** GET /api/ai-hr/jobs —— 列出开放岗位 */
router.get('/jobs', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, title, department, location, salary_range, headcount,
              description, requirement, published_at, expired_at
         FROM minip_hr_recruit
        WHERE status = 'open'
        ORDER BY published_at DESC
        LIMIT 50`
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

/** GET /api/ai-hr/jobs/:id/presets —— 取某岗位的预设条件 */
router.get('/jobs/:id/presets', async (req, res, next) => {
  try {
    const jobId = Number(req.params.id)
    if (!Number.isInteger(jobId)) return res.status(400).json({ code: 400, message: 'job_id 不合法' })

    const [[job]] = await pool.query(
      `SELECT id, title, description, requirement, salary_range
         FROM minip_hr_recruit WHERE id = ? LIMIT 1`,
      [jobId]
    )
    if (!job) return res.status(404).json({ code: 404, message: '岗位不存在' })

    const [[presets]] = await pool.query(
      `SELECT preset_questions, soft_traits, max_questions
         FROM ai_hr_job_presets WHERE job_id = ? LIMIT 1`,
      [jobId]
    )

    // 没配预设 = 用 job.requirement + 5 个默认软素质
    const fallbackPresets = {
      preset_questions: [
        { key: 'experience_years', question: '你有几年相关工作经验？', requirement: '≥2 年', weight: 1, required: true },
        { key: 'education', question: '你的学历是什么？', requirement: '大专以上', weight: 0.6, required: true },
        { key: 'tech_stack', question: '你熟悉哪些技术？', requirement: '匹配岗位描述', weight: 1, required: true },
        { key: 'available_at', question: '最快可到岗时间？', requirement: '两周内', weight: 0.4, required: false },
        { key: 'expected_salary', question: '期望薪资？', requirement: '匹配岗位范围', weight: 0.5, required: false }
      ],
      soft_traits: [
        { key: 'communication', label: '表达能力', weight: 0.2 },
        { key: 'logic', label: '逻辑思维', weight: 0.3 },
        { key: 'learning', label: '学习意愿', weight: 0.2 },
        { key: 'teamwork', label: '团队协作', weight: 0.15 },
        { key: 'stability', label: '稳定性', weight: 0.15 }
      ],
      max_questions: 12
    }

    res.json({
      code: 0,
      data: {
        job,
        presets: presets || fallbackPresets,
        has_custom: !!presets
      }
    })
  } catch (e) { next(e) }
})

/** POST /api/ai-hr/sessions —— 创建对话 session */
router.post('/sessions', async (req, res, next) => {
  try {
    const { job_id, candidate_name, candidate_phone } = req.body || {}
    if (!Number.isInteger(Number(job_id))) {
      return res.status(400).json({ code: 400, message: 'job_id 必填且为整数' })
    }
    const [[job]] = await pool.query(
      `SELECT id, title FROM minip_hr_recruit WHERE id = ? AND status = 'open' LIMIT 1`,
      [job_id]
    )
    if (!job) return res.status(404).json({ code: 404, message: '岗位不存在或已关闭' })

    const sessionId = crypto.randomUUID()
    const [r] = await pool.query(
      `INSERT INTO ai_hr_conversations
         (session_id, job_id, candidate_name, candidate_phone, messages, status)
       VALUES (?, ?, ?, ?, JSON_ARRAY(), 'active')`,
      [sessionId, Number(job_id), candidate_name || null, candidate_phone || null]
    )

    res.json({
      code: 0,
      data: {
        session_id: sessionId,
        conversation_id: r.insertId,
        job
      }
    })
  } catch (e) { next(e) }
})

/** GET /api/ai-hr/sessions/:session_id —— 取对话历史（断线重连用） */
router.get('/sessions/:session_id', async (req, res, next) => {
  try {
    const [[row]] = await pool.query(
      `SELECT id, session_id, job_id, candidate_name, candidate_phone,
              messages, preset_answers, status, created_at, updated_at
         FROM ai_hr_conversations WHERE session_id = ? LIMIT 1`,
      [req.params.session_id]
    )
    if (!row) return res.status(404).json({ code: 404, message: 'session 不存在' })
    res.json({ code: 0, data: row })
  } catch (e) { next(e) }
})

/** POST /api/ai-hr/chat —— 投递用户消息 + 返回 AI 回复（slice 3 接 LLM）
 *  协议: OpenAI 兼容 chat/completions (同步 JSON 响应，slice 4 改 SSE)
 *  输出格式: 自然中文回复 + 可选 `<<PRESET_ANSWER key=... value=...>>` 行
 */
router.post('/chat', async (req, res, next) => {
  try {
    const { session_id, user_message } = req.body || {}
    if (!session_id || !user_message) {
      return res.status(400).json({ code: 400, message: 'session_id + user_message 必填' })
    }

    const [[conv]] = await pool.query(
      `SELECT c.id, c.job_id, c.messages, c.status,
              j.title AS job_title, j.description, j.requirement, j.salary_range,
              p.preset_questions, p.soft_traits, p.max_questions
         FROM ai_hr_conversations c
         JOIN minip_hr_recruit j ON j.id = c.job_id
         LEFT JOIN ai_hr_job_presets p ON p.job_id = c.job_id
        WHERE c.session_id = ? LIMIT 1`,
      [session_id]
    )
    if (!conv) return res.status(404).json({ code: 404, message: 'session 不存在' })
    if (conv.status !== 'active') {
      return res.status(409).json({ code: 409, message: '对话已结束，请勿再发送消息' })
    }

    // 1. 读 LLM 配置
    const llm = await loadLLMConfig()
    if (!llm) return res.status(503).json({ code: 503, message: 'LLM 未配置' })

    // 2. 构造 prompt
    const systemPrompt = buildChatSystemPrompt(conv)
    const history = (conv.messages || []).filter(m => m.role !== 'system')
    const messages = [
      { role: 'system', content: systemPrompt },
      ...history,
      { role: 'user', content: user_message }
    ]

    // 3. 调 LLM
    let aiReply = ''
    try {
      aiReply = await callLLM(llm, messages, 0.6)
    } catch (llmErr) {
      console.error('[ai-hr] LLM error:', llmErr.message)
      return res.status(502).json({ code: 502, message: 'AI 服务异常: ' + llmErr.message })
    }

    // 4. 解析 PRESET_ANSWER 标记
    const { cleaned, presetAnswers } = parsePresetAnswers(aiReply)

    // 5. 追加消息 + 提取的预设答案
    const userMsg = { role: 'user', content: user_message, ts: new Date().toISOString() }
    const aiMsg = { role: 'assistant', content: cleaned, ts: new Date().toISOString() }
    const newMessages = [...(conv.messages || []), userMsg, aiMsg]

    // 合并已存的 preset_answers
    const mergedPresets = { ...((conv.preset_answers) || {}), ...presetAnswers }

    await pool.query(
      `UPDATE ai_hr_conversations
         SET messages = ?,
             preset_answers = ?,
             candidate_name = COALESCE(candidate_name, ?)
        WHERE id = ?`,
      [JSON.stringify(newMessages), JSON.stringify(mergedPresets), extractName(user_message), conv.id]
    )

    res.json({
      code: 0,
      data: {
        session_id,
        user_message: userMsg,
        assistant_message: aiMsg,
        preset_extracted: presetAnswers,
        total_count: newMessages.length
      }
    })
  } catch (e) { next(e) }
})

/** POST /api/ai-hr/sessions/:session_id/finish —— 主动结束 + 生成最终评价 */
router.post('/sessions/:session_id/finish', async (req, res, next) => {
  try {
    const { session_id } = req.params
    const { candidate_name, candidate_phone } = req.body || {}

    const [[conv]] = await pool.query(
      `SELECT c.id, c.job_id, c.messages, c.preset_answers, c.status,
              c.candidate_name, c.candidate_phone,
              j.title AS job_title, j.description, j.requirement, j.salary_range,
              p.preset_questions, p.soft_traits, p.max_questions
         FROM ai_hr_conversations c
         JOIN minip_hr_recruit j ON j.id = c.job_id
         LEFT JOIN ai_hr_job_presets p ON p.job_id = c.job_id
        WHERE c.session_id = ? LIMIT 1`,
      [session_id]
    )
    if (!conv) return res.status(404).json({ code: 404, message: 'session 不存在' })
    if (conv.status === 'completed') {
      // 已完成 → 直接返回已有评价
      const [[exist]] = await pool.query(
        `SELECT * FROM ai_hr_evaluations WHERE conversation_id = ? LIMIT 1`, [conv.id]
      )
      if (exist) return res.json({ code: 0, data: exist, already_done: true })
      // 没找到 evaluation 但状态 completed → 重新生成
    }
    if (conv.status === 'abandoned') {
      return res.status(409).json({ code: 409, message: '对话已放弃' })
    }

    // 更新候选人信息（如有）
    if (candidate_name || candidate_phone) {
      await pool.query(
        `UPDATE ai_hr_conversations
           SET candidate_name = COALESCE(?, candidate_name),
               candidate_phone = COALESCE(?, candidate_phone)
         WHERE id = ?`,
        [candidate_name || null, candidate_phone || null, conv.id]
      )
    }

    // 1. 调 LLM 生成结构化 JSON 评价
    const llm = await loadLLMConfig()
    if (!llm) return res.status(503).json({ code: 503, message: 'LLM 未配置' })

    const evalPrompt = buildEvaluatePrompt(conv)
    let evalJson
    try {
      const raw = await callLLM(llm, evalPrompt, 0.4, 2000)
      evalJson = extractJson(raw)
    } catch (llmErr) {
      console.error('[ai-hr] evaluate LLM error:', llmErr.message)
      return res.status(502).json({ code: 502, message: '评价生成失败: ' + llmErr.message })
    }

    // 2. 落库
    const finalName = candidate_name || conv.candidate_name || '匿名'
    const [ins] = await pool.query(
      `INSERT INTO ai_hr_evaluations
         (conversation_id, job_id, candidate_name, overall_score,
          preset_results, soft_scores, red_flags, highlights,
          recommendation, ai_summary)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        conv.id,
        conv.job_id,
        finalName,
        Number(evalJson.overall_score) || null,
        JSON.stringify(evalJson.preset_results || []),
        JSON.stringify(evalJson.soft_scores || []),
        JSON.stringify(evalJson.red_flags || []),
        JSON.stringify(evalJson.highlights || []),
        evalJson.recommendation || 'consider',
        evalJson.summary || null
      ]
    )

    await pool.query(
      `UPDATE ai_hr_conversations SET status = 'completed' WHERE id = ?`,
      [conv.id]
    )

    res.json({
      code: 0,
      data: {
        evaluation_id: ins.insertId,
        conversation_id: conv.id,
        ...evalJson
      }
    })
  } catch (e) { next(e) }
})

// ============================================================
// HR 后台接口（需登录 + ai_hr 权限）
// ============================================================
router.use(auth)  // 后面所有路由都要登录

/** GET /api/ai-hr/evaluations —— 报告列表 */
router.get('/evaluations', READ, async (req, res, next) => {
  try {
    const { job_id, recommendation, min_score, limit = 50 } = req.query
    const wheres = []
    const params = []
    if (job_id) { wheres.push('e.job_id = ?'); params.push(Number(job_id)) }
    if (recommendation) { wheres.push('e.recommendation = ?'); params.push(String(recommendation)) }
    if (min_score) { wheres.push('e.overall_score >= ?'); params.push(Number(min_score)) }
    const where = wheres.length ? `WHERE ${wheres.join(' AND ')}` : ''
    const lim = Math.min(Number(limit) || 50, 200)

    const [rows] = await pool.query(
      `SELECT e.id, e.conversation_id, e.job_id, j.title AS job_title,
              e.candidate_name, e.overall_score, e.recommendation,
              e.red_flags, e.highlights, e.created_at
         FROM ai_hr_evaluations e
         LEFT JOIN minip_hr_recruit j ON j.id = e.job_id
         ${where}
         ORDER BY e.created_at DESC
         LIMIT ${lim}`,
      params
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

/** GET /api/ai-hr/evaluations/:id —— 报告详情（含完整对话） */
router.get('/evaluations/:id', READ, async (req, res, next) => {
  try {
    const evalId = Number(req.params.id)
    if (!Number.isInteger(evalId)) return res.status(400).json({ code: 400, message: 'id 不合法' })

    const [[row]] = await pool.query(
      `SELECT e.*, j.title AS job_title, j.department, j.location, j.salary_range,
              c.messages AS conversation_messages, c.session_id
         FROM ai_hr_evaluations e
         LEFT JOIN minip_hr_recruit j ON j.id = e.job_id
         LEFT JOIN ai_hr_conversations c ON c.id = e.conversation_id
        WHERE e.id = ? LIMIT 1`,
      [evalId]
    )
    if (!row) return res.status(404).json({ code: 404, message: '报告不存在' })
    res.json({ code: 0, data: row })
  } catch (e) { next(e) }
})

/** GET /api/ai-hr/admin/jobs —— HR 看所有岗位 + 是否配了 preset */
router.get('/admin/jobs', READ, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT j.id, j.title, j.department, j.location, j.salary_range,
              p.id IS NOT NULL AS has_preset,
              p.max_questions
         FROM minip_hr_recruit j
         LEFT JOIN ai_hr_job_presets p ON p.job_id = j.id
         ORDER BY j.id DESC`
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

/** GET /api/ai-hr/admin/jobs/:id/presets —— HR 取岗位预设条件 */
router.get('/admin/jobs/:id/presets', READ, async (req, res, next) => {
  try {
    const jobId = Number(req.params.id)
    if (!Number.isInteger(jobId)) return res.status(400).json({ code: 400, message: 'id 不合法' })

    const [[job]] = await pool.query(
      `SELECT id, title FROM minip_hr_recruit WHERE id = ? LIMIT 1`, [jobId]
    )
    if (!job) return res.status(404).json({ code: 404, message: '岗位不存在' })

    const [[presets]] = await pool.query(
      `SELECT * FROM ai_hr_job_presets WHERE job_id = ? LIMIT 1`, [jobId]
    )
    res.json({ code: 0, data: { job, presets: presets || null } })
  } catch (e) { next(e) }
})

/** PUT /api/ai-hr/admin/jobs/:id/presets —— HR 配预设条件 */
router.put('/admin/jobs/:id/presets', WRITE, async (req, res, next) => {
  try {
    const jobId = Number(req.params.id)
    if (!Number.isInteger(jobId)) return res.status(400).json({ code: 400, message: 'id 不合法' })

    const { preset_questions, soft_traits, max_questions = 12 } = req.body || {}
    if (!Array.isArray(preset_questions) || !Array.isArray(soft_traits)) {
      return res.status(400).json({ code: 400, message: 'preset_questions + soft_traits 必填且为数组' })
    }

    const [[job]] = await pool.query(
      `SELECT id FROM minip_hr_recruit WHERE id = ? LIMIT 1`, [jobId]
    )
    if (!job) return res.status(404).json({ code: 404, message: '岗位不存在' })

    await pool.query(
      `INSERT INTO ai_hr_job_presets (job_id, preset_questions, soft_traits, max_questions)
       VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE
         preset_questions = VALUES(preset_questions),
         soft_traits = VALUES(soft_traits),
         max_questions = VALUES(max_questions)`,
      [jobId, JSON.stringify(preset_questions), JSON.stringify(soft_traits), Number(max_questions)]
    )

    res.json({ code: 0, message: 'saved' })
  } catch (e) { next(e) }
})

/** DELETE /api/ai-hr/evaluations/:id —— 删除报告 + 对话 */
router.delete('/evaluations/:id', DEL, async (req, res, next) => {
  try {
    const evalId = Number(req.params.id)
    if (!Number.isInteger(evalId)) return res.status(400).json({ code: 400, message: 'id 不合法' })

    const [[row]] = await pool.query(
      `SELECT conversation_id FROM ai_hr_evaluations WHERE id = ? LIMIT 1`, [evalId]
    )
    if (!row) return res.status(404).json({ code: 404, message: '报告不存在' })

    await pool.query(`DELETE FROM ai_hr_evaluations WHERE id = ?`, [evalId])
    if (row.conversation_id) {
      await pool.query(`DELETE FROM ai_hr_conversations WHERE id = ?`, [row.conversation_id])
    }
    res.json({ code: 0, message: 'deleted' })
  } catch (e) { next(e) }
})

// ============================================================
// Helper functions
// ============================================================

/** 从 ai_config 表读启用的 LLM（OpenAI 兼容 + Anthropic 双协议）
 *  按 is_default DESC + id ASC 取第一个 status=1 的 LLM
 *  协议检测：base_url 含 /anthropic → anthropic；否则 openai
 */
async function loadLLMConfig() {
  try {
    const [rows] = await pool.query(
      `SELECT base_url, api_key, model, provider
         FROM ai_config
        WHERE category = 'llm' AND status = 1
        ORDER BY is_default DESC, id ASC
        LIMIT 1`
    )
    if (!rows.length || !rows[0].api_key) return null
    const c = rows[0]
    const protocol = (c.base_url || '').includes('/anthropic') ? 'anthropic' : 'openai'
    return {
      baseUrl: c.base_url,
      apiKey: c.api_key,
      model: c.model,
      provider: c.provider,
      protocol
    }
  } catch (e) {
    console.error('[ai-hr] loadLLMConfig error:', e.message)
    return null
  }
}

/** 调 LLM chat (同步 JSON 响应，双协议自适应)
 *  messages: [{role, content}] 数组 — anthropic 时 system 会被抽出
 */
async function callLLM(llm, messages, temperature = 0.5, maxTokens = 1500) {
  const controller = new AbortController()
  const t = setTimeout(() => controller.abort(), 60_000)
  try {
    let url, headers, body
    if (llm.protocol === 'anthropic') {
      // Anthropic 协议：base_url 已是完整 /v1/messages 端点
      const systemPrompt = (messages.find(m => m.role === 'system') || {}).content || ''
      const userMessages = messages.filter(m => m.role !== 'system')
      url = llm.baseUrl
      headers = {
        'Content-Type': 'application/json',
        'x-api-key': llm.apiKey,
        'anthropic-version': '2023-06-01',
        'Authorization': `Bearer ${llm.apiKey}`
      }
      body = {
        model: llm.model,
        max_tokens: maxTokens,
        temperature,
        system: systemPrompt,
        messages: userMessages
      }
    } else {
      // OpenAI 协议：base_url 是根，追加 /chat/completions
      url = `${llm.baseUrl.replace(/\/$/, '')}/chat/completions`
      headers = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${llm.apiKey}`
      }
      body = {
        model: llm.model,
        messages,
        temperature,
        max_tokens: maxTokens
      }
    }

    const resp = await fetch(url, {
      method: 'POST',
      headers,
      body: JSON.stringify(body),
      signal: controller.signal
    })
    clearTimeout(t)

    if (!resp.ok) {
      const txt = await resp.text().catch(() => '')
      throw new Error(`LLM ${resp.status}: ${txt.substring(0, 200)}`)
    }
    const data = await resp.json()

    if (llm.protocol === 'anthropic') {
      // Anthropic 响应: { content: [{type:'text', text:'...'}, ...] }
      const contentArr = data.content || []
      return contentArr.filter(c => c.type === 'text').map(c => c.text).join('\n')
    }
    // OpenAI 响应
    return data.choices?.[0]?.message?.content || ''
  } catch (e) {
    clearTimeout(t)
    throw e
  }
}

/** 构造 chat system prompt */
function buildChatSystemPrompt(conv) {
  const presetList = (conv.preset_questions || []).map((p, i) =>
    `${i + 1}. [key=${p.key}] ${p.question}（要求: ${p.requirement}，必填: ${p.required ? '是' : '否'}）`
  ).join('\n')
  const softList = (conv.soft_traits || []).map(s => `- ${s.label}（权重 ${s.weight}）`).join('\n')

  return `你是 [${conv.job_title}] 的 AI 招聘助手，正在跟应聘者多轮对话。

【岗位信息】
- 标题: ${conv.job_title}
- 描述: ${conv.description || '暂无'}
- 要求: ${conv.requirement || '暂无'}
- 薪资: ${conv.salary_range || '面议'}

【你必须主动问的硬性条件】(按优先级，最多 ${conv.max_questions || 12} 轮)
${presetList || '(无)'}

【软素质观察清单】(自然观察，不直接问)
${softList || '(无)'}

【对话规则】
1. 一次只问 1 个问题，不要连珠炮
2. 应聘者答非所问 → 礼貌拉回
3. 信息矛盾 → 委婉追问确认
4. 应聘者主动结束 → 回复"好的，感谢你的时间，我们会在 3 个工作日内联系你"
5. 不要解释技术细节，你是 HR 不是专家
6. 中文回复，语气亲和专业

【关键：当应聘者的回答覆盖到某个硬性条件时，在回复末尾追加一行标记】
格式: <<PRESET_ANSWER key={key} value={提取的值}>>
示例: 应聘者说"我干了 5 年前端" → 末尾追加: <<PRESET_ANSWER key=experience_years value=5年>>
示例: 应聘者说"本科毕业" → 末尾追加: <<PRESET_ANSWER key=education value=本科>>
示例: 应聘者说"Vue 和 React 都行，Vue 更熟" → 末尾追加: <<PRESET_ANSWER key=tech_stack value=Vue/React,Vue为主>>

【开始对话】先简单欢迎 + 第一个问题（从第 1 个硬性条件开始）`
}

/** 解析 AI 回复中的 PRESET_ANSWER 标记 */
function parsePresetAnswers(reply) {
  const re = /<<PRESET_ANSWER\s+key=([\w_]+)\s+value=([^>]+)>>/g
  const answers = {}
  let m
  while ((m = re.exec(reply)) !== null) {
    answers[m[1]] = m[2].trim()
  }
  const cleaned = reply.replace(re, '').trim()
  return { cleaned, presetAnswers: answers }
}

/** 从用户消息中提取姓名（粗略） */
function extractName(msg) {
  const m = msg.match(/我叫([^\s,，。]{1,8})/) || msg.match(/我是([^\s,，。]{1,8})/)
  return m ? m[1] : null
}

/** 构造 evaluate prompt */
function buildEvaluatePrompt(conv) {
  const presetText = (conv.preset_questions || []).map(p =>
    `- [${p.key}] ${p.question} (要求: ${p.requirement}, 权重: ${p.weight}, 必填: ${p.required})`
  ).join('\n')
  const softText = (conv.soft_traits || []).map(s =>
    `- [${s.key}] ${s.label} (权重: ${s.weight})`
  ).join('\n')

  const dialogText = (conv.messages || [])
    .map(m => `[${m.role === 'user' ? '应聘者' : 'AI'}] ${m.content}`)
    .join('\n\n')

  return [
    {
      role: 'system',
      content: `你是 HR 评估专家。基于应聘者与 AI 招聘助手的完整对话，给出结构化评价。

【输出必须是合法 JSON，不要任何额外解释文字】

JSON 结构:
{
  "overall_score": <0-100 整数>,
  "preset_results": [
    {"key":"...","question":"...","answer":"...","passed":true/false,"requirement":"..."}
  ],
  "soft_scores": [
    {"key":"...","label":"...","score":<1-10>,"evidence":"<引用对话原文片段>","weight":<0-1>}
  ],
  "red_flags": ["<基于对话的红黄牌，比如：3年换4次工作>", ...],
  "highlights": ["<亮点，比如：Vue 熟练，有团队管理经验>", ...],
  "recommendation": "<strong|consider|reject>",
  "summary": "<一句话总结，不超过 50 字>"
}

评分原则:
- overall_score = preset_results 加权分 * 0.6 + soft_scores 加权分(1-10 归一为 0-100) * 0.4
- soft_scores 每项 evidence 必须引用对话原文（不超过 30 字）
- red_flags 必须有对话依据，不要凭空捏造
- 如果对话信息严重不足（<3 轮）→ overall_score <= 40, recommendation = reject
- 如果对话中无任何预设条件被回答 → preset_results 为空数组
`
    },
    {
      role: 'user',
      content: `【岗位】 ${conv.job_title}

【预设条件】
${presetText}

【软素质维度】
${softText}

【提取到的预设条件答案】
${JSON.stringify(conv.preset_answers || {}, null, 2)}

【完整对话】
${dialogText}

【请输出 JSON】`
    }
  ]
}

/** 从 LLM 回复中提取 JSON（容忍 markdown ```json``` 包裹） */
function extractJson(text) {
  if (!text) throw new Error('LLM 返回空')
  // 去 ```json ... ``` 包裹
  let s = text.trim()
  const fence = s.match(/```(?:json)?\s*([\s\S]*?)```/)
  if (fence) s = fence[1].trim()
  // 找第一个 { 到最后一个 }
  const start = s.indexOf('{')
  const end = s.lastIndexOf('}')
  if (start === -1 || end === -1) throw new Error('LLM 输出无 JSON: ' + s.substring(0, 200))
  s = s.substring(start, end + 1)
  try {
    return JSON.parse(s)
  } catch (e) {
    throw new Error('JSON 解析失败: ' + e.message + ' / 内容: ' + s.substring(0, 200))
  }
}

export default router