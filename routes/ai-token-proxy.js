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
// Helper: check key validity and load its owner + quota info
// ──────────────────────────────────────────────────────────────
async function validateKey(rawKey) {
  const keyHash = hashKey(rawKey)
  const [rows] = await pool.query(
    `SELECT k.*, u.id AS owner_user_id, u.email, u.balance, u.is_active AS user_active
     FROM ai_token_keys k
     JOIN ai_token_users u ON k.user_id = u.id
     WHERE k.key_hash = ?`,
    [keyHash]
  )
  if (!rows.length) return { error: 'Invalid API key', status: 401 }
  const key = rows[0]

  if (!key.is_active) return { error: 'API key is disabled', status: 401 }
  if (!key.user_active) return { error: 'User account is disabled', status: 401 }

  // Check expiry
  if (key.expires_at && new Date(key.expires_at) < new Date()) {
    return { error: 'API key has expired', status: 401 }
  }

  // Check IP whitelist
  if (key.ip_whitelist) {
    const clientIp = (req => {
      return req.headers['x-forwarded-for']?.split(',')[0]?.trim()
        || req.socket?.remoteAddress
        || ''
    })(null) // filled in below with req
    // We need req — this is called from a function that has req
  }

  return key
}

// ──────────────────────────────────────────────────────────────
// Helper: check balance and quota
// ──────────────────────────────────────────────────────────────
async function checkQuota(key, promptTokensEstimate) {
  const user_id = key.owner_user_id
  const [[user]] = await pool.query(
    'SELECT balance FROM ai_token_users WHERE id = ?',
    [user_id]
  )

  // Rough cost estimate: assume output = input * 2, use input_multiplier=1, output_multiplier=6
  // This is an over-estimate so we don't over-charge. Real cost is computed after upstream response.
  const estimatedCost = (promptTokensEstimate / 1_000_000) * 0.1 * 1.0
                      + ((promptTokensEstimate * 2) / 1_000_000) * 0.6 * 6.0

  if (user.balance < estimatedCost && estimatedCost > 0) {
    return { error: 'Insufficient balance', status: 402 }
  }

  // Check day/month quota on key
  if (key.quota_day > 0 || key.quota_month > 0) {
    const now = new Date()
    const startOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate()).toISOString()
    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString()

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

    if (key.quota_day > 0 && parseFloat(dayUsage.day_cost) >= key.quota_day) {
      return { error: 'Daily quota exceeded', status: 429 }
    }
    if (key.quota_month > 0 && parseFloat(monthUsage.month_cost) >= key.quota_month) {
      return { error: 'Monthly quota exceeded', status: 429 }
    }
  }

  return null // OK
}

// ──────────────────────────────────────────────────────────────
// Helper: resolve model → channel
// ──────────────────────────────────────────────────────────────
async function resolveChannel(modelName) {
  // Try exact match first
  const [models] = await pool.query(
    `SELECT m.*, c.base_url, c.api_key, c.provider, c.channel_name
     FROM ai_token_models m
     JOIN ai_token_channels c ON m.channel_id = c.id
     WHERE m.provider_model_name = ? AND m.is_active = 1 AND c.is_active = 1
     ORDER BY c.priority ASC LIMIT 1`,
    [modelName]
  )

  if (!models.length) {
    return { error: `Model '${modelName}' not found or not active`, status: 400 }
  }
  return models[0]
}

// ──────────────────────────────────────────────────────────────
// Helper: compute cost from upstream usage
// ──────────────────────────────────────────────────────────────
function computeCost(modelRow, promptTokens, completionTokens) {
  // Upstream prices per million tokens (DeepSeek defaults; per-channel overrides possible)
  // Stored in model row via input_multiplier / output_multiplier which represent
  // the ratio of user price to upstream price.
  // For billing we need: user_price_per_1M = upstream_price_per_1M * multiplier
  // Upstream DeepSeek: input=0.1元/M, output=0.6元/M
  const upstreamInputPrice = 0.1   // yuan per million input tokens
  const upstreamOutputPrice = 0.6  // yuan per million output tokens

  const inputCost  = (promptTokens / 1_000_000) * upstreamInputPrice  * parseFloat(modelRow.input_multiplier)
  const outputCost = (completionTokens / 1_000_000) * upstreamOutputPrice * parseFloat(modelRow.output_multiplier)
  return parseFloat((inputCost + outputCost).toFixed(6))
}

// ──────────────────────────────────────────────────────────────
// Helper: deduct balance
// ──────────────────────────────────────────────────────────────
async function deductBalance(userId, cost) {
  await pool.query(
    'UPDATE ai_token_users SET balance = balance - ? WHERE id = ?',
    [cost, userId]
  )
}

// ──────────────────────────────────────────────────────────────
// Helper: record usage log
// ──────────────────────────────────────────────────────────────
async function recordUsage(keyId, model, promptTokens, completionTokens, cost, responseTimeMs) {
  await pool.query(
    `INSERT INTO ai_token_usage (key_id, model, input_tokens, output_tokens, cost, response_time_ms)
     VALUES (?, ?, ?, ?, ?, ?)`,
    [keyId, model, promptTokens, completionTokens, cost, responseTimeMs]
  )
}

// ──────────────────────────────────────────────────────────────
// POST /api/token/v1/chat/completions
// OpenAI-compatible chat completions proxy
// ──────────────────────────────────────────────────────────────
router.post('/chat/completions', async (req, res, next) => {
  const startTime = Date.now()

  try {
    // ── 1. Extract & validate Authorization header ──
    const authHeader = req.headers.authorization
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return res.status(401).json({
        error: { message: 'Missing or invalid Authorization header' }
      })
    }
    const rawKey = authHeader.slice(7)

    // ── 2. Validate API key ──
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
    const key = keyRows[0]

    if (!key.is_active) {
      return res.status(401).json({ error: { message: 'API key is disabled' } })
    }
    if (!key.user_active) {
      return res.status(401).json({ error: { message: 'User account is disabled' } })
    }
    if (key.expires_at && new Date(key.expires_at) < new Date()) {
      return res.status(401).json({ error: { message: 'API key has expired' } })
    }

    // IP whitelist check
    if (key.ip_whitelist) {
      const clientIp = (req.headers['x-forwarded-for'] || '').split(',')[0]?.trim()
                     || req.socket?.remoteAddress?.replace(/^::ffff:/, '') || ''
      const allowed = key.ip_whitelist.split(',').map(ip => ip.trim())
      if (!allowed.includes(clientIp)) {
        return res.status(403).json({ error: { message: 'IP not allowed' } })
      }
    }

    // ── 3. Parse request body ──
    const {
      model: requestedModel,
      messages,
      stream = false,
      max_tokens,
      temperature,
    } = req.body

    if (!requestedModel || !messages) {
      return res.status(400).json({
        error: { message: 'model and messages are required' }
      })
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
      return res.status(400).json({
        error: { message: `Model '${requestedModel}' not found or not active` }
      })
    }
    const modelRow = modelRows[0]

    // ── 5. Rough balance pre-check (estimate 1:1 input tokens for this check) ──
    // We estimate cost using prompt_tokens guess (just field count as rough proxy)
    const promptTokensEstimate = messages.reduce((acc, m) => acc + (m.content || '').length / 4, 0) || 1
    const estimatedCost = (promptTokensEstimate / 1_000_000) * 0.1 * parseFloat(modelRow.input_multiplier)
                        + (promptTokensEstimate / 1_000_000) * 0.6 * parseFloat(modelRow.output_multiplier)

    if (parseFloat(key.balance) < estimatedCost && estimatedCost > 0.000001) {
      return res.status(402).json({ error: { message: 'Insufficient balance' } })
    }

    // ── 6. Check day/month quota ──
    if (key.quota_day > 0 || key.quota_month > 0) {
      const now = new Date()
      const startOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate())
      const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)

      const [[dayUsage]] = await pool.query(
        `SELECT COALESCE(SUM(cost),0) AS day_cost FROM ai_token_usage WHERE key_id = ? AND created_at >= ?`,
        [key.id, startOfDay]
      )
      const [[monthUsage]] = await pool.query(
        `SELECT COALESCE(SUM(cost),0) AS month_cost FROM ai_token_usage WHERE key_id = ? AND created_at >= ?`,
        [key.id, startOfMonth]
      )

      if (key.quota_day > 0 && parseFloat(dayUsage.day_cost) >= key.quota_day) {
        return res.status(429).json({ error: { message: 'Daily quota exceeded' } })
      }
      if (key.quota_month > 0 && parseFloat(monthUsage.month_cost) >= key.quota_month) {
        return res.status(429).json({ error: { message: 'Monthly quota exceeded' } })
      }
    }

    // ── 7. Call upstream ──
    let upstreamResponse
    try {
      upstreamResponse = await axios.post(
        `${modelRow.base_url}/chat/completions`,
        {
          model: modelRow.provider_model_name,
          messages,
          stream,
          ...(max_tokens !== undefined && { max_tokens }),
          ...(temperature !== undefined && { temperature }),
        },
        {
          headers: {
            'Authorization': `Bearer ${modelRow.channel_api_key}`,
            'Content-Type': 'application/json',
          },
          timeout: 120_000,
          validateStatus: () => true, // handle all statuses manually
        }
      )
    } catch (err) {
      if (err.code === 'ECONNABORTED' || err.message?.includes('timeout')) {
        return res.status(504).json({ error: { message: 'Upstream timeout' } })
      }
      return res.status(502).json({ error: { message: 'Upstream error', detail: err.message } })
    }

    if (upstreamResponse.status === 429) {
      return res.status(429).json({ error: { message: 'Rate limit exceeded' } })
    }

    if (upstreamResponse.status < 200 || upstreamResponse.status >= 300) {
      return res.status(502).json({
        error: {
          message: 'Upstream error',
          detail: upstreamResponse.data?.error?.message || upstreamResponse.statusText
        }
      })
    }

    const upstreamData = upstreamResponse.data
    const usage = upstreamData.usage || {}
    const promptTokens = usage.prompt_tokens || 0
    const completionTokens = usage.completion_tokens || 0

    // ── 8. Compute & deduct cost ──
    const cost = computeCost(modelRow, promptTokens, completionTokens)
    const responseTimeMs = Date.now() - startTime

    if (cost > 0) {
      await deductBalance(key.owner_user_id, cost)
    }

    // ── 9. Record usage ──
    await recordUsage(key.id, requestedModel, promptTokens, completionTokens, cost, responseTimeMs)

    // ── 10. Return OpenAI-compatible response ──
    res.status(200).json({
      id: `chatcmpl-${crypto.randomUUID().replace(/-/g, '').substring(0, 12)}`,
      object: 'chat.completion',
      created: Math.floor(Date.now() / 1000),
      model: requestedModel,
      choices: upstreamData.choices || [{
        index: 0,
        message: upstreamData.choices?.[0]?.message || { role: 'assistant', content: '' },
        finish_reason: upstreamData.choices?.[0]?.finish_reason || 'stop',
      }],
      usage: {
        prompt_tokens: promptTokens,
        completion_tokens: completionTokens,
        total_tokens: promptTokens + completionTokens,
      },
    })
  } catch (err) {
    next(err)
  }
})

export default router
