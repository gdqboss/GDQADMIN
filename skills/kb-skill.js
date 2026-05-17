/**
 * 知识库技能 - RAG检索 + 向量化
 * 使用本地Windows LLM的embedding模型
 */

const EMBEDDING_URL = 'http://100.74.233.52:1234/v1/embeddings'
const EMBEDDING_MODEL = 'text-embedding-nomic-embed-text-v1.5'
const DEFAULT_TOP_K = 5

// 获取文本embedding
export async function getEmbedding(text) {
  try {
    const response = await fetch(EMBEDDING_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ input: text, model: EMBEDDING_MODEL })
    })
    if (!response.ok) throw new Error(`Embedding API error: ${response.status}`)
    const data = await response.json()
    return data.data[0].embedding
  } catch (err) {
    console.error('[kb-skill] embedding error:', err.message)
    return null
  }
}

// 计算余弦相似度
function cosineSimilarity(a, b) {
  if (!a || !b || a.length !== b.length) return 0
  let dot = 0, normA = 0, normB = 0
  for (let i = 0; i < a.length; i++) {
    dot += a[i] * b[i]
    normA += a[i] * a[i]
    normB += b[i] * b[i]
  }
  return dot / (Math.sqrt(normA) * Math.sqrt(normB) + 1e-8)
}

// 搜索相似chunks
export async function searchSimilarChunks(query, topK = DEFAULT_TOP_K) {
  const { pool } = await import('../db/connection.js')
  
  // 获取query embedding
  const queryEmbedding = await getEmbedding(query)
  if (!queryEmbedding) return []
  
  // 获取所有chunks（简单实现：全量查，限制数量）
  const [chunks] = await pool.query(
    'SELECT c.*, d.title as doc_title FROM kb_chunks c JOIN kb_documents d ON c.doc_id = d.id WHERE d.status = 1 ORDER BY c.id DESC LIMIT 500'
  )
  
  // 计算相似度
  const scored = chunks.map(chunk => {
    const embedding = typeof chunk.embedding === 'string' ? JSON.parse(chunk.embedding) : chunk.embedding
    const score = cosineSimilarity(queryEmbedding, embedding)
    return { ...chunk, score }
  })
  
  // 排序返回top_k
  scored.sort((a, b) => b.score - a.score)
  return scored.slice(0, topK).map(c => ({
    id: c.id,
    doc_id: c.doc_id,
    doc_title: c.doc_title,
    content: c.content,
    score: c.score,
    metadata: c.metadata
  }))
}

// 添加学习记录（Hermes模式）
export async function addLearningLog(pool, { user_id, session_id, user_message, ai_reply, source, intent, confidence, kb_doc_id }) {
  await pool.query(
    `INSERT INTO kb_learning_log (user_id, session_id, user_message, ai_reply, source, intent, confidence, kb_doc_id, validated)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)`,
    [user_id || null, session_id || '', user_message, ai_reply || '', source || 'boss_chat', intent || '', confidence || 0, kb_doc_id || null]
  )
}

export default { getEmbedding, searchSimilarChunks, addLearningLog }
