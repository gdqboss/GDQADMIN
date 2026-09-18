// llm-caller.js — 通用 LLM 调用器 (2026-09-18 江小鱼立)
//
// 设计: 从 ai_config 表读 customer 自己配置的 LLM, 支持任意 OpenAI 兼容协议
//       (OpenAI / Anthropic / GLM / DeepSeek / 阿里通义 / 火山豆包 ...)
//       secure-knowledge 模块用它调 LLM
//
// 用法:
//   import { callLLM } from './llm-caller.js'
//   const result = await callLLM({
//     category: 'llm',                        // ai_config.category 字段
//     messages: [{ role: 'system', content: '...' }, { role: 'user', content: '...' }],
//     temperature: 0.7,
//     maxTokens: 2000,
//   })
//
// 返回:
//   { ok: true, content: '...', provider, model, tokens, latency_ms }
//   或
//   { ok: false, error: '...', http_status }
//
// 注意: 机密数据进 prompt 前, agent-memory 已加密; 本函数不做 prompt 内容检查
//        (那是 caller 的责任) — 这里只负责 LLM 协议

import { pool } from '../db/connection.js'

/**
 * 读 ai_config 表, 拿客户配置的 LLM
 * @param {string} category - ai_config.category 字段 (默认 'llm')
 * @returns {Promise<{provider, base_url, api_key, model} | null>}
 */
async function loadLLMConfig(category = 'llm') {
  const [rows] = await pool.query(
    `SELECT provider, base_url, api_key, model
     FROM ai_config
     WHERE category = ? AND status = 1
     ORDER BY is_default DESC, id ASC
     LIMIT 1`,
    [category]
  )
  if (!rows.length) return null
  return rows[0]
}

/**
 * 通用 LLM 调用 — 支持 OpenAI 兼容协议
 * @param {object} opts
 * @param {string} [opts.category='llm'] - ai_config category
 * @param {Array} opts.messages - OpenAI 格式 [{role, content}, ...]
 * @param {number} [opts.temperature=0.7]
 * @param {number} [opts.maxTokens=2000]
 * @param {boolean} [opts.stream=false]
 * @param {function} [opts.onChunk] - 流式 chunk callback
 * @returns {Promise<{ok, content?, provider?, model?, tokens?, latency_ms?, error?, http_status?}>}
 */
export async function callLLM(opts) {
  const {
    category = 'llm',
    messages,
    temperature = 0.7,
    maxTokens = 2000,
    stream = false,
    onChunk = null,
  } = opts

  if (!Array.isArray(messages) || messages.length === 0) {
    return { ok: false, error: 'messages required' }
  }

  const config = await loadLLMConfig(category)
  if (!config) {
    return { ok: false, error: `no LLM config for category='${category}'` }
  }

  const { provider, base_url, api_key, model } = config
  if (!api_key) {
    return { ok: false, error: `LLM '${provider}/${model}' has no api_key configured` }
  }

  // 协议: OpenAI 兼容 (大多数 LLM 都走这个, 包括 GLM / DeepSeek / 通义 / 豆包)
  // endpoint: {base_url}/chat/completions
  // 但有些客户填的是完整 path (e.g. https://api.minimaxi.com/anthropic/v1/messages)
  // → 智能识别: 已含 /chat/completions 或 /messages → 不再拼
  let endpoint = (base_url || 'https://api.openai.com/v1').replace(/\/$/, '')
  if (!/\/chat\/completions$|\/messages$/.test(endpoint)) {
    endpoint = endpoint + '/chat/completions'
  }

  const start = Date.now()
  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${api_key}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        model,
        messages,
        temperature,
        max_tokens: maxTokens,
        stream,
      }),
    })

    if (!response.ok) {
      const errBody = await response.text().catch(() => '')
      return {
        ok: false,
        error: `LLM HTTP ${response.status}: ${errBody || response.statusText}`,
        http_status: response.status,
        provider,
        model,
      }
    }

    if (stream) {
      // 流式响应处理 (跟 services/glm.js 类似)
      const reader = response.body.getReader()
      const decoder = new TextDecoder()
      let buffer = ''
      let fullContent = ''
      while (true) {
        const { done, value } = await reader.read()
        if (done) break
        buffer += decoder.decode(value, { stream: true })
        const lines = buffer.split('\n')
        buffer = lines.pop()
        for (const line of lines) {
          const trimmed = line.trim()
          if (!trimmed || !trimmed.startsWith('data:')) continue
          const data = trimmed.slice(5).trim()
          if (data === '[DONE]') continue
          try {
            const parsed = JSON.parse(data)
            const delta = parsed.choices?.[0]?.delta?.content
            if (delta) {
              fullContent += delta
              if (onChunk) onChunk(delta)
            }
          } catch { /* skip malformed */ }
        }
      }
      return {
        ok: true,
        content: fullContent,
        provider,
        model,
        latency_ms: Date.now() - start,
      }
    }

    // 非流式
    const data = await response.json()
    return {
      ok: true,
      content: data.choices?.[0]?.message?.content || '',
      provider,
      model,
      tokens: data.usage?.total_tokens || null,
      latency_ms: Date.now() - start,
    }
  } catch (e) {
    return {
      ok: false,
      error: `LLM call exception: ${e.message}`,
      provider,
      model,
      latency_ms: Date.now() - start,
    }
  }
}