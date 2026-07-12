// labor-ai.js — 劳务 AI 配套接口(鼓励师傅/工人成长/域知识库/健康检查)
// 路径前缀: /api/labor-ai/*
// 复用已有表: encouragements / worker_profiles / member_growth_log / ai_class_knowledge
// 不新建表, 不重复实现 agent 对话(已在 labor-ai-agent.js)
import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission, PERMISSIONS as P } from '../middleware/rbac.js'

const router = express.Router()
router.use(auth)

// ============================================================
// GET /api/labor-ai/health — L4 agent 健康检查
// 复用: ai_config (已有) + ai_class_sessions (统计活跃对话数)
// ============================================================
router.get('/health', requirePermission(P.HR_READ), async (req, res, next) => {
  try {
    // LLM 配置状态
    const [[cfg]] = await pool.query(
      "SELECT model, provider FROM ai_config WHERE category = 'llm' AND status = 1 ORDER BY is_default DESC LIMIT 1"
    )
    // 工人档案数
    const [[{ worker_count }]] = await pool.query(
      "SELECT COUNT(*) as worker_count FROM worker_profiles WHERE employment_status = 'active'"
    )
    // 域知识库条目数
    const [[{ kb_count }]] = await pool.query(
      "SELECT COUNT(*) as kb_count FROM ai_class_knowledge WHERE is_public = 1 AND domain_enabled = 1"
    )
    // 最近 24h 鼓励条数
    const [[{ recent_encourage }]] = await pool.query(
      "SELECT COUNT(*) as recent_encourage FROM encouragements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    )
    res.json({
      code: 0,
      data: {
        ok: true,
        llm_model: cfg?.model || null,
        llm_provider: cfg?.provider || null,
        worker_count,
        kb_count,
        recent_encourage_24h: recent_encourage,
        checked_at: new Date().toISOString(),
      },
    })
  } catch (e) { next(e) }
})

// ============================================================
// GET /api/labor-ai/knowledge — 按 role/domain/category/q 查知识库
// 复用: ai_class_knowledge 已有 domain + sub_category + is_public 字段
// 工人端 role=worker: 仅返回 is_public=1 且 domain_enabled=1
// ============================================================
router.get('/knowledge', requirePermission(P.HR_READ), async (req, res, next) => {
  try {
    const { domain, category, q, role = 'worker' } = req.query
    const params = []
    const where = ['1=1']
    if (role === 'worker') {
      where.push('k.is_public = 1', 'k.domain_enabled = 1')
    }
    if (domain) { where.push('k.domain = ?'); params.push(domain) }
    if (category) { where.push('k.sub_category = ?'); params.push(category) }
    if (q) { where.push('(k.title LIKE ? OR k.content LIKE ?)'); params.push(`%${q}%`, `%${q}%`) }
    const [rows] = await pool.query(
      `SELECT k.id, k.domain, k.sub_category as category, k.title,
              SUBSTRING(k.content, 1, 300) as snippet,
              k.tags, k.created_at
         FROM ai_class_knowledge k
        WHERE ${where.join(' AND ')}
        ORDER BY k.id DESC
        LIMIT 100`,
      params
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

// ============================================================
// POST /api/labor-ai/encourage — 老板/班长发鼓励给工人
// body: { to_user_id, message, card_type }
// 写入: encouragements (from_user_id, to_user_id, message, card_type, visible_to_worker)
// ============================================================
router.post('/encourage', requirePermission(P.HR_WRITE), async (req, res, next) => {
  try {
    const from_user_id = req.user.id
    const { to_user_id, message, card_type } = req.body || {}
    if (!to_user_id || !message || !message.trim()) {
      return res.status(400).json({ code: 400, message: 'to_user_id 和 message 必填' })
    }
    const allowed = ['praise','cheer','care','badge']
    const card = allowed.includes(card_type) ? card_type : 'praise'
    const [r] = await pool.query(
      `INSERT INTO encouragements (from_user_id, to_user_id, message, card_type, visible_to_worker)
       VALUES (?, ?, ?, ?, 1)`,
      [from_user_id, to_user_id, message.trim(), card]
    )
    res.json({ code: 0, data: { id: r.insertId, from_user_id, to_user_id, message: message.trim(), card_type: card }, message: '鼓励已送达' })
  } catch (e) { next(e) }
})

// ============================================================
// GET /api/labor-ai/encourage/mine — 工人看我收到的鼓励
// 复用: encouragements.to_user_id + JOIN users 取发送人姓名
// ============================================================
router.get('/encourage/mine', requirePermission(P.HR_READ), async (req, res, next) => {
  try {
    const userId = req.user.id
    const [rows] = await pool.query(
      `SELECT e.id, e.message, e.card_type, e.created_at,
              u_from.name as from_name
         FROM encouragements e
         LEFT JOIN users u_from ON e.from_user_id = u_from.id
        WHERE e.to_user_id = ? AND e.visible_to_worker = 1
        ORDER BY e.created_at DESC
        LIMIT 50`,
      [userId]
    )
    res.json({ code: 0, data: rows })
  } catch (e) { next(e) }
})

// ============================================================
// GET /api/labor-ai/worker/growth — 工人成长档案(仅正向)
// 数据源: worker_profiles (efficiency_score/quality_score/impact_score)
//        + member_growth_log (正向 change_amount > 0 求和)
// 注意: 不返回 raw rating/comment 等敏感字段(按设计原则)
// ============================================================
router.get('/worker/growth', requirePermission(P.HR_READ), async (req, res, next) => {
  try {
    const userId = req.user.id
    const [[profile]] = await pool.query(
      `SELECT skill_level, total_work_hours, total_pieces,
              efficiency_score, quality_score, impact_score,
              hired_at, employment_status
         FROM worker_profiles WHERE user_id = ? LIMIT 1`,
      [userId]
    )
    const [[growth]] = await pool.query(
      `SELECT COALESCE(SUM(CASE WHEN change_amount > 0 THEN change_amount ELSE 0 END), 0) as positive_points,
              COUNT(CASE WHEN change_amount > 0 THEN 1 END) as positive_events,
              COUNT(*) as total_events
         FROM member_growth_log WHERE user_id = ?`,
      [userId]
    )
    // 鼓励条数(收到的)
    const [[{ received_count }]] = await pool.query(
      `SELECT COUNT(*) as received_count FROM encouragements WHERE to_user_id = ?`,
      [userId]
    )
    res.json({
      code: 0,
      data: {
        skill_level: profile?.skill_level || 'rookie',
        total_work_hours: Number(profile?.total_work_hours || 0),
        total_pieces: profile?.total_pieces || 0,
        efficiency_score: Number(profile?.efficiency_score || 0),
        quality_score: Number(profile?.quality_score || 0),
        impact_score: Number(profile?.impact_score || 0),
        positive_points: Number(growth?.positive_points || 0),
        positive_events: growth?.positive_events || 0,
        total_events: growth?.total_events || 0,
        received_encouragements: received_count,
        hired_at: profile?.hired_at || null,
        employment_status: profile?.employment_status || 'active',
      },
    })
  } catch (e) { next(e) }
})

export default router
