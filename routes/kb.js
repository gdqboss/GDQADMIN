import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { getEmbedding, searchSimilarChunks } from '../skills/kb-skill.js'

const router = Router()

// GET /api/kb/documents - 知识库文档列表
router.get('/documents', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT id, title, category, source, chunk_count, status, created_at FROM kb_documents WHERE status = 1 ORDER BY created_at DESC'
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/kb/documents - 新增文档（支持原始内容录入）
router.post('/documents', auth, async (req, res, next) => {
  try {
    const { title, category, source, content } = req.body
    const [result] = await pool.query(
      'INSERT INTO kb_documents (title, category, source, content) VALUES (?, ?, ?, ?)',
      [title, category, source || 'original', content || '']
    )
    res.json({ code: 0, data: { id: result.insertId } })
  } catch (err) { next(err) }
})

// PUT /api/kb/documents/:id - 更新文档
router.put('/documents/:id', auth, async (req, res, next) => {
  try {
    const { title, category, content, status } = req.body
    await pool.query(
      'UPDATE kb_documents SET title=?, category=?, content=?, status=? WHERE id=?',
      [title, category, content || '', status ?? 1, req.params.id]
    )
    res.json({ code: 0 })
  } catch (err) { next(err) }
})

// DELETE /api/kb/documents/:id - 删除文档
router.delete('/documents/:id', auth, async (req, res, next) => {
  try {
    await pool.query('UPDATE kb_documents SET status = 0 WHERE id = ?', [req.params.id])
    res.json({ code: 0 })
  } catch (err) { next(err) }
})

// POST /api/kb/documents/:id/chunk - 对文档进行分块+向量化
router.post('/documents/:id/chunk', auth, async (req, res, next) => {
  try {
    const [docs] = await pool.query('SELECT * FROM kb_documents WHERE id = ?', [req.params.id])
    if (!docs.length) return res.json({ code: 404, message: '文档不存在' })
    const doc = docs[0]
    
    // 简单分块：按段落或固定长度
    const chunks = splitIntoChunks(doc.content || '', 500)
    
    // 删除旧chunks
    await pool.query('DELETE FROM kb_chunks WHERE doc_id = ?', [doc.id])
    
    // 生成embedding并存储
    const chunkRecords = []
    for (let i = 0; i < chunks.length; i++) {
      const embedding = await getEmbedding(chunks[i])
      chunkRecords.push([doc.id, chunks[i], i, JSON.stringify(embedding), JSON.stringify({doc_title: doc.title})])
    }
    
    if (chunkRecords.length) {
      await pool.query(
        'INSERT INTO kb_chunks (doc_id, content, chunk_index, embedding, metadata) VALUES ?',
        [chunkRecords]
      )
    }
    
    await pool.query('UPDATE kb_documents SET chunk_count = ? WHERE id = ?', [chunks.length, doc.id])
    res.json({ code: 0, data: { chunks: chunks.length } })
  } catch (err) { next(err) }
})

// POST /api/kb/search - RAG检索
router.post('/search', auth, async (req, res, next) => {
  try {
    const { query, top_k = 5 } = req.body
    if (!query) return res.json({ code: 0, data: [] })
    
    const results = await searchSimilarChunks(query, parseInt(top_k))
    res.json({ code: 0, data: results })
  } catch (err) { next(err) }
})

// GET /api/kb/learning-logs - 学习记录（待审核）
router.get('/learning-logs', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT l.*, u.name as user_name FROM kb_learning_log l
       LEFT JOIN users u ON l.user_id = u.id
       WHERE l.validated = 0 ORDER BY l.created_at DESC LIMIT 100`
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/kb/learning-logs/:id/confirm - 确认学习记录，加入知识库
router.post('/learning-logs/:id/confirm', auth, async (req, res, next) => {
  try {
    const [logs] = await pool.query('SELECT * FROM kb_learning_log WHERE id = ?', [req.params.id])
    if (!logs.length) return res.json({ code: 404, message: '记录不存在' })
    const log = logs[0]
    
    // 创建新文档
    const [result] = await pool.query(
      'INSERT INTO kb_documents (title, category, source, content) VALUES (?, ?, ?, ?)',
      [`学习记录-${log.user_message.substring(0, 30)}`, 'learned', 'hermes', `Q: ${log.user_message}\n\nA: ${log.correct_answer || log.ai_reply}`]
    )
    
    await pool.query('UPDATE kb_learning_log SET validated = 1, kb_doc_id = ? WHERE id = ?', [result.insertId, log.id])
    res.json({ code: 0 })
  } catch (err) { next(err) }
})

// POST /api/kb/learning-logs/:id/reject - 忽略
router.post('/learning-logs/:id/reject', auth, async (req, res, next) => {
  try {
    await pool.query('UPDATE kb_learning_log SET validated = 2 WHERE id = ?', [req.params.id])
    res.json({ code: 0 })
  } catch (err) { next(err) }
})

// 简单分块函数
function splitIntoChunks(text, chunkSize) {
  const paragraphs = text.split(/\n\n+/)
  const chunks = []
  let current = ''
  for (const para of paragraphs) {
    if ((current + para).length <= chunkSize) {
      current += (current ? '\n\n' : '') + para
    } else {
      if (current) chunks.push(current)
      current = para
    }
  }
  if (current) chunks.push(current)
  return chunks
}

export default router
