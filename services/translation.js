import Redis from 'ioredis'
import crypto from 'crypto'

const redis = new Redis({ lazyConnect: true })
const DEEPSEEK_API = 'https://api.deepseek.com/v1/chat/completions'
const API_KEY = process.env.DEEPSEEK_API_KEY || 'sk-c2e64c68aa144908b7b90b89245677cc'

// Cache: zh→en translations cached for 30 days
const CACHE_TTL = 30 * 24 * 60 * 60

// Connect to Redis silently
redis.connect().catch(() => {
  console.log('[Translation] Redis not available, translations will not be cached')
})

/**
 * Compute a short cache key from source text + target lang
 */
function cacheKey(text, targetLang) {
  const hash = crypto.createHash('md5').update(text + '|' + targetLang).digest('hex').slice(0, 12)
  return `trans:${targetLang}:${hash}`
}

/**
 * Translate a single Chinese string to English
 */
async function translateToEnglish(text) {
  if (!text || typeof text !== 'string' || !text.trim()) return text
  // Skip if already looks like English (rough heuristic)
  if (/^[a-zA-Z0-9\s\-,.()（）【】《》\/]+$/.test(text.trim())) return text.trim()

  const cache = cacheKey(text.trim(), 'en')
  try {
    const cached = await redis.get(cache)
    if (cached) return cached
  } catch (_) { /* redis down */ }

  try {
    const res = await fetch(DEEPSEEK_API, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${API_KEY}`
      },
      body: JSON.stringify({
        model: 'deepseek-chat',
        messages: [
          {
            role: 'system',
            content: `You are a professional translator. Translate the following Chinese text to English. Rules:
- Keep product/material names natural in English
- Keep abbreviations and codes as-is
- Only output the translation, nothing else
- If the text is already English, return it unchanged`
          },
          { role: 'user', content: text.trim() }
        ],
        max_tokens: 200,
        temperature: 0.3
      })
    })

    if (!res.ok) {
      console.error(`[Translation] API error: ${res.status}`)
      return text
    }

    const json = await res.json()
    const translated = json.choices?.[0]?.message?.content?.trim() || text

    // Cache the result
    try {
      await redis.setex(cache, CACHE_TTL, translated)
    } catch (_) { /* redis down */ }

    return translated
  } catch (err) {
    console.error(`[Translation] Failed:`, err.message)
    return text
  }
}

/**
 * Batch translate an array of strings (deduplicated)
 */
async function translateBatch(texts) {
  const unique = [...new Set(texts.filter(t => t && typeof t === 'string' && t.trim()))]
  if (unique.length === 0) return {}

  const results = await Promise.all(unique.map(t => translateToEnglish(t)))
  const map = {}
  unique.forEach((t, i) => { map[t.trim()] = results[i] })
  return map
}

/**
 * Translate specific fields in a data object or array.
 * @param {object|array} data - The data to translate
 * @param {string[]} fields - Field names to translate (e.g. ['name', 'remark'])
 * @returns {Promise<object|array>} - Translated data (mutates in place and returns)
 */
async function translateFields(data, fields = ['name', 'remark', 'category', 'unit']) {
  if (!data) return data

  const collectValues = (obj, fields) => {
    const values = []
    for (const key of fields) {
      if (obj && obj[key] != null) values.push(String(obj[key]))
    }
    return values
  }

  if (Array.isArray(data)) {
    const allValues = []
    const indexMap = []
    data.forEach((item, i) => {
      const vals = collectValues(item, fields)
      indexMap.push({ i, count: vals.length })
      allValues.push(...vals)
    })

    const translationMap = await translateBatch(allValues)

    data.forEach((item, i) => {
      for (const key of fields) {
        if (item[key] != null) {
          item[key] = translationMap[String(item[key]).trim()] ?? item[key]
        }
      }
    })
    return data
  } else {
    const values = collectValues(data, fields)
    const translationMap = await translateBatch(values)
    for (const key of fields) {
      if (data[key] != null) {
        data[key] = translationMap[String(data[key]).trim()] ?? data[key]
      }
    }
    return data
  }
}

export { translateToEnglish, translateBatch, translateFields }
