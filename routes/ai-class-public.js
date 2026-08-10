// /root/server/routes/ai-class-public.js
// 公开 AI 课堂端点 - 免登录访客用
// 2026-08-07 立 — 解锁福慧寺 H5 / 海丰大道庵 / 通用游客 AI 助手
// mount 顺序铁律：必须先挂 /api/ai-class/public, 再挂带 auth 的父前缀 /api/ai-class
// 否则父前缀 auth 中间件会先匹配 → 401

import { Router } from 'express'
import { spawn } from 'child_process'
import { promises as fs } from 'fs'
import os from 'os'
import path from 'path'
import { pool } from '../db/connection.js'

const router = Router()

// 进程级 IP 限频 (重启清零, 足够防脚本刷)
const publicAiRate = new Map()
const RATE_LIMIT_PER_IP_HOUR = 10
const RATE_LIMIT_WINDOW_MS = 3600_000

/**
 * GET /api/ai-class/public/health
 */
router.get('/health', (req, res) => {
  res.json({
    code: 0,
    data: {
      status: 'ok',
      endpoint: 'public',
      rate_limit: `${RATE_LIMIT_PER_IP_HOUR}/hour`,
      endpoints: ['health', 'guest-chat', 'tts'],
      timestamp: new Date().toISOString()
    }
  })
})

/**
 * POST /api/ai-class/public/guest-chat
 * 公开访客对话端点 (免登录)
 * body: { message: string, history?: Array<{role, content}>, bot_name?: string }
 *
 * 限频: 每 IP 10 次/小时 (进程级 Map)
 * 模型: ai_config 表中 category='llm' AND status=1 默认端点 (走与登录用户相同的 ai-class.js callLLM 链路)
 * 不入库, 不读知识库 (游客不应读内部知识), 不入会话历史
 */
router.post('/guest-chat', async (req, res) => {
  try {
    const ip = req.headers['x-forwarded-for']?.split(',')[0].trim()
      || req.socket.remoteAddress
      || 'unknown'

    // 限频
    const now = Date.now()
    const arr = publicAiRate.get(ip) || []
    const recent = arr.filter(t => now - t < RATE_LIMIT_WINDOW_MS)
    if (recent.length >= RATE_LIMIT_PER_IP_HOUR) {
      return res.status(429).json({
        code: 429,
        msg: `访问过于频繁，每小时最多 ${RATE_LIMIT_PER_IP_HOUR} 次`
      })
    }
    recent.push(now)
    publicAiRate.set(ip, recent)

    const { message, history = [], bot_name = '智能助手' } = req.body || {}
    if (!message || typeof message !== 'string' || !message.trim()) {
      return res.status(400).json({ code: 400, msg: '消息不能为空' })
    }
    if (message.length > 2000) {
      return res.status(400).json({ code: 400, msg: '消息过长（限 2000 字）' })
    }

    // 简化版 system prompt (游客不读内部知识库, 防止泄露)
    const systemPrompt = `你是"${bot_name}"，智能商业系统的公开 AI 助手。
只回答公开信息：公司简介、产品咨询、技术支持、订单基础查询。
**严禁**透露内部架构、数据库细节、用户隐私、管理员数据。
**严禁**调用任何内部工具或查询内部数据。
回复简洁友好，3-5 句话，必要时用列表。`

    // 读 ai_config 取默认 LLM (与 ai-class.js 同样查询逻辑)
    const [cfgRows] = await pool.query(
      "SELECT base_url, api_key, model, provider FROM ai_config WHERE category='llm' AND status=1 ORDER BY is_default DESC LIMIT 1"
    )
    if (!cfgRows.length) {
      return res.status(503).json({ code: 503, msg: 'AI 服务暂未配置' })
    }
    const cfg = cfgRows[0]

    // 调 LLM (仅支持 Anthropic / OpenAI 协议 — 与 ai-class.js 一致)
    const messages = [
      { role: 'system', content: systemPrompt },
      ...history.slice(-10).map(h => ({ role: h.role, content: String(h.content || '').slice(0, 2000) })),
      { role: 'user', content: message.trim() }
    ]

    let reply = ''
    if (cfg.provider === 'minimax-anthropic' || cfg.provider === 'anthropic') {
      const resp = await fetch(cfg.base_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'x-api-key': cfg.api_key,
          'anthropic-version': '2023-06-01'
        },
        body: JSON.stringify({
          model: cfg.model,
          max_tokens: 1500,
          system: systemPrompt,
          messages: messages.filter(m => m.role !== 'system')
        })
      })
      const data = await resp.json()
      if (!resp.ok) {
        console.error('[public-ai] anthropic error:', data)
        return res.status(502).json({ code: 502, msg: 'AI 服务暂时不可用' })
      }
      reply = data.content?.[0]?.text || ''
    } else {
      // OpenAI 协议 (nvidia / openai / minimax 标准)
      const resp = await fetch(cfg.base_url.replace(/\/messages$/, '').replace(/\/v1$/, '') + '/v1/chat/completions', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${cfg.api_key}`
        },
        body: JSON.stringify({
          model: cfg.model,
          max_tokens: 1500,
          messages
        })
      })
      const data = await resp.json()
      if (!resp.ok) {
        console.error('[public-ai] openai error:', data)
        return res.status(502).json({ code: 502, msg: 'AI 服务暂时不可用' })
      }
      reply = data.choices?.[0]?.message?.content || ''
    }

    if (!reply) {
      return res.status(502).json({ code: 502, msg: 'AI 暂时没有回复' })
    }

    return res.json({
      code: 0,
      data: { reply, model: cfg.model, endpoint: 'public' }
    })
  } catch (err) {
    console.error('[public-ai] error:', err.message, err.stack)
    return res.status(500).json({ code: 500, msg: 'AI 服务异常' })
  }
})

/**
 * POST /api/ai-class/public/tts
 * 公开 TTS 端点 (免登录, Edge TTS 免 API key)
 * body: { text: string, voice?: string, rate?: string }
 * 返回 audio/mpeg (mp3)
 */
router.post('/tts', async (req, res) => {
  try {
    const ip = req.headers['x-forwarded-for']?.split(',')[0].trim()
      || req.socket.remoteAddress
      || 'unknown'
    // 限频 (TTS 单独计数, 不与 chat 共享)
    const now = Date.now()
    const arr = publicAiRate.get(`tts:${ip}`) || []
    const recent = arr.filter(t => now - t < RATE_LIMIT_WINDOW_MS)
    if (recent.length >= 30) {  // TTS 限宽一点 (用户连续听多次正常)
      return res.status(429).json({ code: 429, msg: 'TTS 访问过于频繁, 请稍后再试' })
    }
    recent.push(now)
    publicAiRate.set(`tts:${ip}`, recent)

    const { text, voice = 'zh-CN-XiaoxiaoNeural', rate = '+0%' } = req.body || {}
    if (!text || typeof text !== 'string' || !text.trim()) {
      return res.status(400).json({ code: 400, msg: 'text 不能为空' })
    }
    // 限长 (避免 Edge TTS 超时)
    const safeText = text.length > 500 ? text.slice(0, 500) + '...' : text

    // 调 node-edge-tts (直接 spawn 绝对路径避开 npx 找不到的坑)
    const tmpFile = path.join(os.tmpdir(), `public-tts-${Date.now()}-${Math.random().toString(36).slice(2,8)}.mp3`)
    const ttsBin = path.join(process.cwd(), 'node_modules', '.bin', 'node-edge-tts')

    await new Promise((resolve, reject) => {
      const child = spawn(ttsBin, [
        '--text', safeText,
        '--voice', voice,
        '--rate', rate === '+0%' ? 'default' : rate,
        '--filepath', tmpFile,
        '--timeout', '8000'
      ], { timeout: 12000 })

      let stderr = ''
      child.stderr.on('data', d => { stderr += d.toString() })

      child.on('error', err => reject(err))
      child.on('close', code => {
        if (code === 0) resolve()
        else reject(new Error(`node-edge-tts exit ${code}: ${stderr.slice(0, 200)}`))
      })
    })

    // 读文件 + 返 mp3
    const audio = await fs.readFile(tmpFile)
    await fs.unlink(tmpFile).catch(() => {})

    res.set({
      'Content-Type': 'audio/mpeg',
      'Content-Length': audio.length,
      'Cache-Control': 'public, max-age=3600'
    })
    return res.send(audio)
  } catch (err) {
    console.error('[public-tts] error:', err.message)
    return res.status(502).json({ code: 502, msg: `TTS 失败: ${err.message}` })
  }
})

export default router