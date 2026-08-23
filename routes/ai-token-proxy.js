import { Router } from 'express'
import crypto from 'crypto'
import axios from 'axios'
import { pool } from '../db/connection.js'

const router = Router()

// ──────────────────────────────────────────────────────────────
// Helper: hash a raw API key with SHA-256
// ──────────────────────────────────────────────────────────────
function hashKey(rawKey) {
  return crypto.createHash('sha256').update(rawKey).digest('hex')
}

// ──────────────────────────────────────────────────────────────
// Helper: compute cost from upstream usage (yuan)
// ──────────────────────────────────────────────────────────────
function computeCost(modelRow, promptTokens, completionTokens) {
  // Upstream DeepSeek defaults: input=0.1元/M, output=0.6元/M
  // User price = upstream * multiplier (admin-set margin per model)
  const upstreamInputPrice = 0.1
  const upstreamOutputPrice = 0.6
  const inputCost  = (promptTokens / 1_000_000)     * upstreamInputPrice  * parseFloat(modelRow.input_multiplier)
  const outputCost = (completionTokens / 1_000_000) * upstreamOutputPrice * parseFloat(modelRow.output_multiplier)
  return parseFloat((inputCost + outputCost).toFixed(6))
}

// ──────────────────────────────────────────────────────────────
// Helper: deduct balance (atomic with balance>=0 guard)
// ──────────────────────────────────────────────────────────────
async function deductBalance(userId, cost) {
  if (cost <= 0) return
  const [result] = await pool.query(
    'UPDATE ai_token_users SET balance = balance - ? WHERE id = ? AND balance >= ?',
    [cost, userId, cost]
  )
  if (result.affectedRows === 0) {
    throw new Error('Insufficient balance during deduction')
  }
}

// ──────────────────────────────────────────────────────────────
// Helper: record usage log (always called, even on failure → cost=0)
// status: 'success' | 'failed'
// ──────────────────────────────────────────────────────────────
async function recordUsage(keyId, model, promptTokens, completionTokens, cost, responseTimeMs, status = 'success') {
  await pool.query(
    `INSERT INTO ai_token_usage (key_id, model, input_tokens, output_tokens, cost, response_time_ms)
     VALUES (?, ?, ?, ?, ?, ?)`,
    [keyId, model, promptTokens, completionTokens, cost, responseTimeMs]
  )
  // status column not in Phase 2 schema; we only record cost=0 for failed calls.
  // Keeping the param for future expansion.
  void status
}

// ──────────────────────────────────────────────────────────────
// Helper: get client IP from request (handles X-Forwarded-For)
// ──────────────────────────────────────────────────────────────
function getClientIp(req) {
  return (req.headers['x-forwarded-for'] || '').split(',')[0]?.trim()
      || req.socket?.remoteAddress?.replace(/^::ffff:/, '')
      || ''
}

// ──────────────────────────────────────────────────────────────
// POST /api/token/v1/chat/completions
// OpenAI-compatible chat completions proxy (supports streaming)
// ──────────────────────────────────────────────────────────────
router.post('/chat/completions', async (req, res, next) => {
  const startTime = Date.now()

  // We need access to key/modelRow after upstream call — declare outside try
  let key = null
  let modelRow = null
  let requestedModel = null
  let promptTokens = 0
  let completionTokens = 0

  try {
    // ── 1. Extract & validate Authorization header ──
    const authHeader = req.headers.authorization
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return res.status(401).json({ error: { message: 'Missing or invalid Authorization header' } })
    }
    const rawKey = authHeader.slice(7)

    // ── 2. Validate API key + load owner ──
    const keyHash = hashKey(rawKey)
    const [keyRows] = await pool.query(
      `SELECT k.*, u.id AS owner_user_id, u.email, u.balance, u.is_active AS user_active
       FROM ai_token_keys k
       JOIN ai_token_users u ON k.user_id = u.id
       WHERE k.key_hash = ?`,
      [keyHash]
    )
    if (!keyRows.length) {
      return res.status(401).json({ error: { message: 'Invalid API key' } })
    }
    key = keyRows[0]

    if (!key.is_active)   return res.status(401).json({ error: { message: 'API key is disabled' } })
    if (!key.user_active) return res.status(401).json({ error: { message: 'User account is disabled' } })
    if (key.expires_at && new Date(key.expires_at) < new Date()) {
      return res.status(401).json({ error: { message: 'API key has expired' } })
    }

    // ── 2b. IP whitelist check (Phase 3 fix: actually pass req) ──
    if (key.ip_whitelist) {
      const clientIp = getClientIp(req)
      const allowed = key.ip_whitelist.split(',').map(ip => ip.trim())
      if (!allowed.includes(clientIp)) {
        return res.status(403).json({ error: { message: 'IP not allowed' } })
      }
    }

    // ── 3. Parse body ──
    const {
      model: reqModel,
      messages,
      stream = false,
      max_tokens,
      temperature,
    } = req.body
    requestedModel = reqModel

    if (!requestedModel || !messages) {
      return res.status(400).json({ error: { message: 'model and messages are required' } })
    }

    // ── 4. Resolve model → channel ──
    const [modelRows] = await pool.query(
      `SELECT m.*, c.base_url, c.api_key AS channel_api_key, c.provider, c.channel_name
       FROM ai_token_models m
       JOIN ai_token_channels c ON m.channel_id = c.id
       WHERE m.provider_model_name = ? AND m.is_active = 1 AND c.is_active = 1
       ORDER BY c.priority ASC LIMIT 1`,
      [requestedModel]
    )
    if (!modelRows.length) {
      return res.status(400).json({ error: { message: `Model '${requestedModel}' not found or not active` } })
    }
    modelRow = modelRows[0]

    // ── 5. Rough balance pre-check ──
    const promptTokensEstimate = messages.reduce(
      (acc, m) => acc + (typeof m.content === 'string' ? m.content.length / 4 : 0), 0
    ) || 1
    const estimatedCost =
        (promptTokensEstimate / 1_000_000) * 0.1 * parseFloat(modelRow.input_multiplier)
      + (promptTokensEstimate / 1_000_000) * 0.6 * parseFloat(modelRow.output_multiplier)

    if (parseFloat(key.balance) < estimatedCost && estimatedCost > 0.000001) {
      return res.status(402).json({ error: { message: 'Insufficient balance' } })
    }

    // ── 6. Day/Month quota check ──
    if (key.quota_day > 0 || key.quota_month > 0) {
      const now = new Date()
      const startOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate())
      const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)

      const [[dayUsage]] = await pool.query(
        `SELECT COALESCE(SUM(cost),0) AS day_cost FROM ai_token_usage
         WHERE key_id = ? AND created_at >= ?`,
        [key.id, startOfDay]
      )
      const [[monthUsage]] = await pool.query(
        `SELECT COALESCE(SUM(cost),0) AS month_cost FROM ai_token_usage
         WHERE key_id = ? AND created_at >= ?`,
        [key.id, startOfMonth]
      )
      if (key.quota_day   > 0 && parseFloat(dayUsage.day_cost)   >= key.quota_day)   return res.status(429).json({ error: { message: 'Daily quota exceeded' } })
      if (key.quota_month > 0 && parseFloat(monthUsage.month_cost) >= key.quota_month) return res.status(429).json({ error: { message: 'Monthly quota exceeded' } })
    }

    // ── 7. STREAMING branch ──
    if (stream) {
      // Set headers BEFORE first write so client gets `text/event-stream`
      res.setHeader('Content-Type', 'text/event-stream')
      res.setHeader('Cache-Control', 'no-cache')
      res.setHeader('Connection', 'keep-alive')
      res.setHeader('X-Accel-Buffering', 'no')
      res.flushHeaders()

      let streamCost = 0
      let streamInputTokens = 0
      let streamOutputTokens = 0

      try {
        const upstream = await axios.post(
          `${modelRow.base_url}/chat/completions`,
          {
            model: modelRow.provider_model_name,
            messages,
            stream: true,
            ...(max_tokens   !== undefined && { max_tokens }),
            ...(temperature  !== undefined && { temperature }),
          },
          {
            headers: {
              'Authorization': `Bearer ${modelRow.channel_api_key}`,
              'Content-Type': 'application/json',
            },
            timeout: 120_000,
            responseType: 'stream',
            validateStatus: () => true,
          }
        )

        if (upstream.status < 200 || upstream.status >= 300) {
          // Bubble upstream error as SSE event so client sees it
          let errBody = ''
          upstream.data.on('data', c => { errBody += c.toString() })
          upstream.data.on('end', () => {
            const finalMsg = `Upstream error: ${upstream.status} ${errBody.substring(0, 200)}`
            res.write(`data: ${JSON.stringify({ error: { message: finalMsg } })}\n\n`)
            res.write('data: [DONE]\n\n')
            res.end()
            // Record zero-cost failure
            recordUsage(key.id, requestedModel, 0, 0, 0, Date.now() - startTime, 'failed').catch(() => {})
          })
          return
        }

        let buffer = ''
        let finalUsage = null

        upstream.data.on('data', (chunk) => {
          buffer += chunk.toString()
          // OpenAI SSE streams come as `data: {json}\n\n`
          const lines = buffer.split('\n')
          buffer = lines.pop() // keep incomplete line

          for (const line of lines) {
            if (line.startsWith('data: ')) {
              const payload = line.slice(6).trim()
              if (payload === '[DONE]') continue
              try {
                const obj = JSON.parse(payload)
                if (obj.usage) finalUsage = obj.usage  // last chunk usually has usage
                if (obj.choices?.[0]?.delta?.content) {
                  streamOutputTokens += Math.ceil(obj.choices[0].delta.content.length / 4)
                }
              } catch { /* malformed SSE line, skip */ }
            }
            // Forward verbatim to client
            res.write(line + '\n')
          }
        })

        upstream.data.on('end', async () => {
          // Flush any trailing buffer
          if (buffer) res.write(buffer + '\n')
          res.write('data: [DONE]\n\n')
          res.end()

          if (finalUsage) {
            streamInputTokens = finalUsage.prompt_tokens || 0
            streamOutputTokens = finalUsage.completion_tokens || streamOutputTokens
          } else {
            // Fallback: prompt from messages
            streamInputTokens = Math.ceil(promptTokensEstimate)
          }

          const cost = computeCost(modelRow, streamInputTokens, streamOutputTokens)
          try {
            if (cost > 0) await deductBalance(key.owner_user_id, cost)
            await recordUsage(key.id, requestedModel, streamInputTokens, streamOutputTokens, cost, Date.now() - startTime, 'success')
          } catch (err) {
            console.error('[ai-token-proxy] post-stream deduct/record failed:', err.message)
          }
        })

        upstream.data.on('error', (err) => {
          console.error('[ai-token-proxy] stream error:', err.message)
          if (!res.writableEnded) {
            res.write(`data: ${JSON.stringify({ error: { message: 'Stream interrupted' } })}\n\n`)
            res.end()
          }
          recordUsage(key.id, requestedModel, 0, 0, 0, Date.now() - startTime, 'failed').catch(() => {})
        })
      } catch (err) {
        const msg = err.code === 'ECONNABORTED' ? 'Upstream timeout' : 'Upstream error'
        const code = err.code === 'ECONNABORTED' ? 504 : 502
        if (!res.headersSent) {
          return res.status(code).json({ error: { message: msg, detail: err.message } })
        }
        res.write(`data: ${JSON.stringify({ error: { message: msg } })}\n\n`)
        res.end()
        recordUsage(key.id, requestedModel, 0, 0, 0, Date.now() - startTime, 'failed').catch(() => {})
      }
      return
    }

    // ── 8. NON-STREAMING branch ──
    let upstreamResponse
    try {
      upstreamResponse = await axios.post(
        `${modelRow.base_url}/chat/completions`,
        {
          model: modelRow.provider_model_name,
          messages,
          stream: false,
          ...(max_tokens  !== undefined && { max_tokens }),
          ...(temperature !== undefined && { temperature }),
        },
        {
          headers: {
            'Authorization': `Bearer ${modelRow.channel_api_key}`,
            'Content-Type': 'application/json',
          },
          timeout: 120_000,
          validateStatus: () => true,
        }
      )
    } catch (err) {
      const responseTimeMs = Date.now() - startTime
      const isTimeout = err.code === 'ECONNABORTED' || err.message?.includes('timeout')
      // Record failure (cost=0)
      await recordUsage(key.id, requestedModel, 0, 0, 0, responseTimeMs, 'failed').catch(() => {})
      return res.status(isTimeout ? 504 : 502).json({
        error: { message: isTimeout ? 'Upstream timeout' : 'Upstream error', detail: err.message }
      })
    }

    if (upstreamResponse.status === 429) {
      await recordUsage(key.id, requestedModel, 0, 0, 0, Date.now() - startTime, 'failed').catch(() => {})
      return res.status(429).json({ error: { message: 'Rate limit exceeded' } })
    }

    if (upstreamResponse.status < 200 || upstreamResponse.status >= 300) {
      await recordUsage(key.id, requestedModel, 0, 0, 0, Date.now() - startTime, 'failed').catch(() => {})
      return res.status(502).json({
        error: {
          message: 'Upstream error',
          detail: upstreamResponse.data?.error?.message || upstreamResponse.statusText
        }
      })
    }

    const upstreamData = upstreamResponse.data
    const usage = upstreamData.usage || {}
    promptTokens     = usage.prompt_tokens     || 0
    completionTokens = usage.completion_tokens || 0

    const cost = computeCost(modelRow, promptTokens, completionTokens)
    const responseTimeMs = Date.now() - startTime

    try {
      if (cost > 0) await deductBalance(key.owner_user_id, cost)
      await recordUsage(key.id, requestedModel, promptTokens, completionTokens, cost, responseTimeMs, 'success')
    } catch (deductErr) {
      // Deduct failed (concurrent use drained balance) → still return response but log
      console.error('[ai-token-proxy] post-response deduct failed:', deductErr.message)
      // Don't re-record usage since recordUsage already inserted; balance just drifts
    }

    // ── 9. OpenAI-compatible response ──
    res.status(200).json({
      id: `chatcmpl-${crypto.randomUUID().replace(/-/g, '').substring(0, 12)}`,
      object: 'chat.completion',
      created: Math.floor(Date.now() / 1000),
      model: requestedModel,
      choices: upstreamData.choices || [{
        index: 0,
        message: { role: 'assistant', content: '' },
        finish_reason: 'stop',
      }],
      usage: {
        prompt_tokens: promptTokens,
        completion_tokens: completionTokens,
        total_tokens: promptTokens + completionTokens,
      },
    })
  } catch (err) {
    // Top-level catch — record failure if we have key/model, then bubble
    if (key && requestedModel) {
      recordUsage(key.id, requestedModel, promptTokens, completionTokens, 0, Date.now() - startTime, 'failed')
        .catch(() => {})
    }
    next(err)
  }
})

export default router