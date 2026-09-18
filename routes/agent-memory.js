// agent-memory.js — 全员 AI 数据中心 (2026-09-18 江小鱼 MVP 立)
// 路径前缀: /api/agent-memory/*
//
// 设计: 4 张表 (memory_events / memory_profiles / memory_insights / memory_wisdom)
//       4 类数据源 hook: attendance / task / work_log / agent_chat
//       5 个 endpoint MVP:
//         GET  /overview       总览 (公司级 AI 数据中心 dashboard)
//         GET  /profiles       员工画像列表 (含 AI 评分)
//         GET  /profile/:uid   单个员工画像 + 最近 events
//         GET  /wisdom         知识财富列表 (可复用思维)
//         POST /analyze/:uid   触发 AI 分析某员工 (读 events → GLM 提炼 → 更新 profile)
//
// 触发: 波哥 2026-09-18 "通过出勤/任务/日志/员工个人 agent 交流,把员工的优秀思维和经验
//        累积到 AI 数据中心"
//
// 实操: MVP 阶段不接 LLM (避免 token 成本 + 调试复杂), analyze endpoint 先 stub
//       下一步加 GLM-4-flash 调用 + memory_events 累积 7 天后才有 AI 价值

import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
// MVP 阶段不接 requirePermission — 等老板确认产品形态后再加 perm 绑定
//   之前 ai-class / workbuddy 早期也是 admin bypass, 后才补 rbac
//   requirePermission('agent-memory:read') 等加好再开

const router = express.Router()
router.use(auth)

// ─── 工具: 标准返 code/data/message ─────────────────────────────────────────
const ok = (data, message = 'OK') => ({ code: 0, data, message })
const err = (message, code = 500) => ({ code, data: null, message })

// ─── GET /overview — 公司级总览 ────────────────────────────────────────────
router.get('/overview', async (req, res) => {
  try {
    const [totals] = await pool.query(`
      SELECT
        (SELECT COUNT(*) FROM memory_events) AS total_events,
        (SELECT COUNT(*) FROM memory_events WHERE is_wisdom=1) AS total_wisdom,
        (SELECT COUNT(*) FROM memory_profiles) AS total_profiles,
        (SELECT COUNT(*) FROM memory_insights) AS total_insights,
        (SELECT COUNT(*) FROM memory_wisdom) AS total_wisdom_entries
    `)
    const [sourceBreakdown] = await pool.query(`
      SELECT source, COUNT(*) AS count
      FROM memory_events
      GROUP BY source
      ORDER BY count DESC
    `)
    const [topProfiles] = await pool.query(`
      SELECT user_id, thinking_style, reliability_score, total_events, wisdom_count
      FROM memory_profiles
      ORDER BY reliability_score DESC
      LIMIT 10
    `)
    res.json(ok({
      totals: totals[0],
      source_breakdown: sourceBreakdown,
      top_profiles: topProfiles,
      note: 'MVP 阶段: events 累积 7 天后 AI 才有显著价值。当前数据为开发期样本。'
    }))
  } catch (e) {
    console.error('[agent-memory overview]', e)
    res.status(500).json(err(e.message))
  }
})

// ─── GET /profiles — 员工画像列表 ──────────────────────────────────────────
router.get('/profiles', async (req, res) => {
  try {
    const [rows] = await pool.query(`
      SELECT mp.*, u.name AS user_name, u.phone AS user_phone, u.user_type, u.role
      FROM memory_profiles mp
      LEFT JOIN users u ON u.id = mp.user_id
      ORDER BY mp.reliability_score DESC, mp.total_events DESC
      LIMIT 200
    `)
    res.json(ok(rows))
  } catch (e) {
    console.error('[agent-memory profiles]', e)
    res.status(500).json(err(e.message))
  }
})

// ─── GET /profile/:uid — 单员工画像 + 最近 events ─────────────────────────
router.get('/profile/:uid', async (req, res) => {
  try {
    const uid = parseInt(req.params.uid)
    if (!uid) return res.status(400).json(err('uid required', 400))

    const [profileRows] = await pool.query(`
      SELECT mp.*, u.name AS user_name, u.phone AS user_phone
      FROM memory_profiles mp
      LEFT JOIN users u ON u.id = mp.user_id
      WHERE mp.user_id = ?
    `, [uid])

    const [events] = await pool.query(`
      SELECT id, source, event_type, event_data, ai_tags, ai_score, is_wisdom, occurred_at
      FROM memory_events
      WHERE user_id = ?
      ORDER BY occurred_at DESC
      LIMIT 50
    `, [uid])

    const [wisdomRows] = await pool.query(`
      SELECT id, title, situation, action, category, tags, is_inheritance
      FROM memory_wisdom
      WHERE source_user_id = ?
      ORDER BY inheritance_priority DESC, created_at DESC
      LIMIT 20
    `, [uid])

    res.json(ok({
      profile: profileRows[0] || null,
      events,
      wisdom: wisdomRows
    }))
  } catch (e) {
    console.error('[agent-memory profile]', e)
    res.status(500).json(err(e.message))
  }
})

// ─── GET /wisdom — 知识财富列表 (可复用思维) ─────────────────────────────
router.get('/wisdom', async (req, res) => {
  try {
    const onlyInheritance = req.query.inheritance === '1'
    const where = onlyInheritance ? 'WHERE is_inheritance = 1' : ''
    const [rows] = await pool.query(`
      SELECT w.*, u.name AS user_name
      FROM memory_wisdom w
      LEFT JOIN users u ON u.id = w.source_user_id
      ${where}
      ORDER BY w.is_inheritance DESC, w.inheritance_priority DESC, w.applied_count DESC
      LIMIT 100
    `)
    res.json(ok(rows))
  } catch (e) {
    console.error('[agent-memory wisdom]', e)
    res.status(500).json(err(e.message))
  }
})

// ─── POST /wisdom — 老板手动标记 wisdom 为传承 ─────────────────────────────
router.post('/wisdom/:wid/inheritance', async (req, res) => {
  try {
    const wid = parseInt(req.params.wid)
    const { is_inheritance, priority } = req.body
    if (!wid) return res.status(400).json(err('wid required', 400))
    await pool.query(`
      UPDATE memory_wisdom
      SET is_inheritance = ?, inheritance_priority = ?
      WHERE id = ?
    `, [is_inheritance ? 1 : 0, priority || 5, wid])
    res.json(ok({ id: wid, is_inheritance: !!is_inheritance, priority: priority || 5 }))
  } catch (e) {
    console.error('[agent-memory inheritance]', e)
    res.status(500).json(err(e.message))
  }
})

// ─── POST /analyze/:uid — 触发 AI 分析 (MVP stub) ────────────────────────
// TODO (Phase 2): 调 GLM-4-flash 读 events → 提炼 profile + 标 wisdom
router.post('/analyze/:uid', async (req, res) => {
  try {
    const uid = parseInt(req.params.uid)
    if (!uid) return res.status(400).json(err('uid required', 400))

    // 统计 events
    const [stats] = await pool.query(`
      SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN is_wisdom=1 THEN 1 ELSE 0 END) AS wisdom,
        AVG(ai_score) AS avg_score
      FROM memory_events WHERE user_id = ?
    `, [uid])

    // MVP: 不真调 GLM, 但更新 last_analyzed_at 让前端有反应
    await pool.query(`
      INSERT INTO memory_profiles (user_id, total_events, last_analyzed_at)
      VALUES (?, ?, NOW())
      ON DUPLICATE KEY UPDATE
        total_events = VALUES(total_events),
        last_analyzed_at = NOW()
    `, [uid, stats[0].total || 0])

    res.json(ok({
      uid,
      stats: stats[0],
      note: 'MVP stub: 真实 AI 分析 (GLM 调用) Phase 2 立。当前只更新 last_analyzed_at。'
    }))
  } catch (e) {
    console.error('[agent-memory analyze]', e)
    res.status(500).json(err(e.message))
  }
})

// ─── GET /insights — AI 洞察列表 (周/月报) ────────────────────────────────
router.get('/insights', async (req, res) => {
  try {
    const [rows] = await pool.query(`
      SELECT * FROM memory_insights
      ORDER BY period_end DESC, generated_at DESC
      LIMIT 50
    `)
    res.json(ok(rows))
  } catch (e) {
    console.error('[agent-memory insights]', e)
    res.status(500).json(err(e.message))
  }
})

export default router