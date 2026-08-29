import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// 品牌隔离 (波哥 2026-08-29): SGP/HK 服务器屏蔽彩美特专属知识, 彩美特品牌只服务北京(profile 2)
const KB_BRAND_FILTER = ` AND title NOT LIKE '%彩美特%' AND content NOT LIKE '%彩美特%' AND tags NOT LIKE '%彩美特%'`
// 统计概览同样过滤
const KB_BRAND_FILTER_WHERE = ` title NOT LIKE '%彩美特%' AND content NOT LIKE '%彩美特%' AND tags NOT LIKE '%彩美特%'`

// GET /api/workbuddy/training/summary - AI 课堂培训学习概览
router.get('/training/summary', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const [[kb]] = await pool.query(`SELECT COUNT(*) c FROM ai_class_knowledge WHERE ${KB_BRAND_FILTER_WHERE}`).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[{ c: 0 }]] })
    const [[publicKb]] = await pool.query(`SELECT COUNT(*) c FROM ai_class_knowledge WHERE (is_public=1 OR is_public IS NULL) AND ${KB_BRAND_FILTER_WHERE}`).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[{ c: 0 }]] })
    const [[cards]] = await pool.query(`SELECT COUNT(*) c FROM ai_class_flashcards`).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[{ c: 0 }]] })
    const [[gaps]] = await pool.query(`SELECT COUNT(*) c FROM ai_class_knowledge_gaps`).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[{ c: 0 }]] })
    const [docTypes] = await pool.query(`
      SELECT doc_type, COUNT(*) c FROM ai_class_knowledge WHERE ${KB_BRAND_FILTER_WHERE} GROUP BY doc_type ORDER BY c DESC LIMIT 8
    `).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[]] })
    // 今日学习相关
    const [[newKb]] = await pool.query(`
      SELECT COUNT(*) c FROM ai_class_knowledge WHERE DATE(created_at) = CURDATE() AND ${KB_BRAND_FILTER_WHERE}
    `).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[{ c: 0 }]] })

    res.json({
      knowledge_base: {
        total: Number(kb.c),
        public: Number(publicKb.c),
        added_today: Number(newKb.c),
        doc_types: (docTypes||[]).map(d => ({ type: d.doc_type, count: Number(d.c) })),
      },
      training: {
        flashcards: Number(cards.c),
        knowledge_gaps: Number(gaps.c),
      },
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/training/kb?q=关键词&doc_type= - 知识库检索（培训问答素材）
router.get('/training/kb', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const q = (req.query.q || '').toString().trim()
    const docType = (req.query.doc_type || '').toString().trim()
    const limit = Math.min(Number(req.query.limit) || 8, 20)
    let rows, count
    if (q) {
      const like = `%${q}%`
      const params = [like, like, like]
      let sql = `
        SELECT id, title, doc_type, tags, LEFT(content, 500) AS content_excerpt, is_public
        FROM ai_class_knowledge
        WHERE (title LIKE ? OR content LIKE ? OR tags LIKE ?)${KB_BRAND_FILTER}
      `
      if (docType) { sql += ` AND doc_type = ?`; params.push(docType) }
      sql += ` ORDER BY id DESC LIMIT ?`; params.push(limit)
      const [r] = await pool.query(sql, params).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[]] })
      rows = r || []
      const [[cr]] = await pool.query(`
        SELECT COUNT(*) c FROM ai_class_knowledge WHERE (title LIKE ? OR content LIKE ? OR tags LIKE ?)${KB_BRAND_FILTER}
      `, [like, like, like]).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[{ c: 0 }]] })
      count = cr.c
    } else {
      // 无关键词 → 最近新增
      let sql = `SELECT id, title, doc_type, tags, LEFT(content, 500) AS content_excerpt, is_public FROM ai_class_knowledge WHERE 1=1${KB_BRAND_FILTER}`
      const params = []
      if (docType) { sql += ` AND doc_type = ?`; params.push(docType) }
      sql += ` ORDER BY id DESC LIMIT ?`; params.push(limit)
      rows = (await pool.query(sql, params).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[]] }))[0] || []
      count = rows.length
    }
    res.json({
      query: q || null,
      doc_type: docType || null,
      count: Number(count),
      results: (rows||[]).map(k => ({
        id: k.id, title: k.title, doc_type: k.doc_type,
        tags: k.tags || null, excerpt: k.content_excerpt && k.content_excerpt.length > 0 ? k.content_excerpt : null,
        is_public: k.is_public,
      })),
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/training/kb/:id - 知识条目详情（培训全文）
router.get('/training/kb/:id', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const id = Number(req.params.id)
    if (!id) return res.status(400).json({ error: 'invalid id' })
    const [[k]] = await pool.query(`
      SELECT id, title, content, doc_type, tags, is_public, created_at, updated_at
      FROM ai_class_knowledge WHERE id=?${KB_BRAND_FILTER}
    `, [id]).catch(e => { console.error('[wb] 数据查询兜底触发:', e?.message); return [[null]] })
    if (!k) return res.status(404).json({ error: 'knowledge not found' })
    res.json({
      knowledge: {
        id: k.id, title: k.title, content: k.content, doc_type: k.doc_type,
        tags: k.tags || null, is_public: k.is_public,
        created_at: k.created_at, updated_at: k.updated_at,
      }
    })
  } catch (err) { next(err) }
})

export default router