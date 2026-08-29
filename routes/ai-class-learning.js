/**
 * AI课堂 - 认知缺口发现 + 间隔复习模块
 * PRD v2: gap_detector · card_generator · review_scheduler · answer_handler · team_dashboard
 *
 * 3 FC Tools:
 *   get_due_flashcards(user_id)       → GET  /api/ai-class-learning/flashcards/due
 *   submit_answer(card_id, answer, user_id) → POST /api/ai-class-learning/flashcards/:id/review
 *   get_gap_summary(user_id)          → GET  /api/ai-class-learning/summary/:userId
 */

import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()
const PERM = 'ai_class_learning'

// ===================== SM-2 Algorithm =====================
function sm2(quality, repetitions, easeFactor, intervalDays) {
  // quality: 0-5 (0-2 = wrong, 3-5 = correct)
  let ef = Number(easeFactor)
  let rep = Number(repetitions)
  let interval = Number(intervalDays)

  if (quality < 3) {
    rep = 0
    interval = 1
  } else {
    if (rep === 0) interval = 1
    else if (rep === 1) interval = 6
    else interval = Math.round(interval * ef)
    rep += 1
  }

  ef = Math.max(1.3, ef + (0.1 - (5 - quality) * (0.08 + (5 - quality) * 0.02)))
  return {
    repetitions: rep,
    easeFactor: Math.round(ef * 100) / 100,
    intervalDays: interval,
    nextReview: new Date(Date.now() + interval * 86400000).toISOString().slice(0, 10),
  }
}

// ===================== Gaps =====================

// POST /api/ai-class-learning/gaps — JXY writes a gap
router.post('/gaps', auth, requirePermission(`${PERM}:write`), async (req, res, next) => {
  try {
    const { user_id, conversation_id, original_text, gap_topic, tier, confidence, status } = req.body
    if (!original_text || !gap_topic || !tier) {
      return res.json({ code: 400, message: 'original_text, gap_topic, tier 必填' })
    }
    const [[r]] = await pool.query(
      `INSERT INTO ai_class_knowledge_gaps (user_id, conversation_id, original_text, gap_topic, tier, confidence, status)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [user_id || req.user.id, conversation_id || null, original_text, gap_topic, tier, confidence || 0.5, status || 'pending']
    )
    const [[row]] = await pool.query('SELECT * FROM ai_class_knowledge_gaps WHERE id = ?', [r.insertId])
    res.json({ code: 0, data: row })
  } catch (err) { next(err) }
})

// GET /api/ai-class-learning/gaps — list gaps (admin / owner)
router.get('/gaps', auth, requirePermission(`${PERM}:read`), async (req, res, next) => {
  try {
    const { user_id, tier, status, page = 1, pageSize = 20 } = req.query
    const isAdmin = req.user.role === 'admin'
    let where = 'WHERE 1=1'
    const params = []

    if (!isAdmin) {
      where += ' AND user_id = ?'
      params.push(req.user.id)
    } else if (user_id) {
      where += ' AND user_id = ?'
      params.push(user_id)
    }
    if (tier)    { where += ' AND tier = ?'; params.push(tier) }
    if (status)  { where += ' AND status = ?'; params.push(status) }

    const offset = (Number(page) - 1) * Number(pageSize)
    const [rows] = await pool.query(
      `SELECT * FROM ai_class_knowledge_gaps ${where} ORDER BY created_at DESC LIMIT ? OFFSET ?`,
      [...params, Number(pageSize), offset]
    )
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM ai_class_knowledge_gaps ${where}`, params
    )
    res.json({ code: 0, data: { list: rows, total, page: Number(page), pageSize: Number(pageSize) } })
  } catch (err) { next(err) }
})

// PATCH /api/ai-class-learning/gaps/:id — update gap status
router.patch('/gaps/:id', auth, requirePermission(`${PERM}:write`), async (req, res, next) => {
  try {
    const { status } = req.body
    if (!status) return res.json({ code: 400, message: 'status 必填' })
    await pool.query('UPDATE ai_class_knowledge_gaps SET status=? WHERE id=?', [status, req.params.id])
    res.json({ code: 0 })
  } catch (err) { next(err) }
})

// ===================== Flashcards =====================

// POST /api/ai-class-learning/flashcards — create card (from gap)
router.post('/flashcards', auth, requirePermission(`${PERM}:write`), async (req, res, next) => {
  try {
    const { gap_id, user_id, topic, tier, question, answer, hint } = req.body
    if (!question || !answer || !topic) {
      return res.json({ code: 400, message: 'question, answer, topic 必填' })
    }
    const uid = user_id || req.user.id
    const [[r]] = await pool.query(
      `INSERT INTO ai_class_flashcards (gap_id, user_id, topic, tier, question, answer, hint)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [gap_id || null, uid, topic, tier || 1, question, answer, hint || null]
    )
    if (gap_id) {
      await pool.query("UPDATE ai_class_knowledge_gaps SET status='carded' WHERE id=?", [gap_id])
    }
    const [[row]] = await pool.query('SELECT * FROM ai_class_flashcards WHERE id=?', [r.insertId])
    res.json({ code: 0, data: row })
  } catch (err) { next(err) }
})

// GET /api/ai-class-learning/flashcards/due — get_due_flashcards(user_id) FC tool
router.get('/flashcards/due', auth, requirePermission(`${PERM}:read`), async (req, res, next) => {
  try {
    const { user_id } = req.query
    const isAdmin = req.user.role === 'admin'
    let uid = isAdmin ? user_id : req.user.id
    if (!uid) return res.json({ code: 400, message: 'user_id 必填' })

    const today = new Date().toISOString().slice(0, 10)
    const [rows] = await pool.query(
      `SELECT * FROM ai_class_flashcards
       WHERE user_id=? AND next_review <= ?
       ORDER BY tier ASC, next_review ASC LIMIT 5`,
      [uid, today]
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/ai-class-learning/flashcards/:id/review — submit_answer(card_id, answer, user_id)
// JXY 判断答案对错后调用，更新 SM-2 参数
router.post('/flashcards/:id/review', auth, requirePermission(`${PERM}:write`), async (req, res, next) => {
  try {
    const cardId = Number(req.params.id)
    const { correct, quality, user_id } = req.body

    // Determine quality: if JXY passes correct (boolean), derive quality
    // quality 0-2 = wrong, 3-5 = correct (SM-2 standard)
    const q = quality !== undefined ? Number(quality)
               : correct === true ? 5
               : correct === false ? 1
               : 4  // default: "somewhat correct"

    const [[card]] = await pool.query('SELECT * FROM ai_class_flashcards WHERE id=?', [cardId])
    if (!card) return res.json({ code: 404, message: '卡片不存在' })

    const result = sm2(q, card.repetitions || 0, card.ease_factor || 2.5, card.interval_days || 1)
    const isCorrect = q >= 3

    await pool.query(
      `UPDATE ai_class_flashcards SET
         repetitions=?, ease_factor=?, interval_days=?, next_review=?,
         last_reviewed=NOW(), correct_count=correct_count+?, wrong_count=wrong_count+?
       WHERE id=?`,
      [result.repetitions, result.easeFactor, result.intervalDays, result.nextReview,
       isCorrect ? 1 : 0, isCorrect ? 0 : 1, cardId]
    )

    await pool.query(
      'INSERT INTO ai_class_review_log (card_id, user_id, result) VALUES (?, ?, ?)',
      [cardId, user_id || req.user.id, isCorrect ? 'correct' : 'wrong']
    )

    const [[updated]] = await pool.query('SELECT * FROM ai_class_flashcards WHERE id=?', [cardId])
    res.json({ code: 0, data: { ...updated, _sm2: result, isCorrect } })
  } catch (err) { next(err) }
})

// GET /api/ai-class-learning/flashcards — list cards
router.get('/flashcards', auth, requirePermission(`${PERM}:read`), async (req, res, next) => {
  try {
    const { user_id, tier, topic, page = 1, pageSize = 20 } = req.query
    const isAdmin = req.user.role === 'admin'
    let where = 'WHERE 1=1'
    const params = []
    if (!isAdmin) {
      where += ' AND user_id = ?'; params.push(req.user.id)
    } else if (user_id) {
      where += ' AND user_id = ?'; params.push(user_id)
    }
    if (tier)   { where += ' AND tier = ?'; params.push(tier) }
    if (topic)  { where += ' AND topic LIKE ?'; params.push(`%${topic}%`) }

    const offset = (Number(page) - 1) * Number(pageSize)
    const [rows] = await pool.query(
      `SELECT * FROM ai_class_flashcards ${where} ORDER BY tier, id LIMIT ? OFFSET ?`,
      [...params, Number(pageSize), offset]
    )
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM ai_class_flashcards ${where}`, params
    )
    res.json({ code: 0, data: { list: rows, total, page: Number(page), pageSize: Number(pageSize) } })
  } catch (err) { next(err) }
})

// DELETE /api/ai-class-learning/flashcards/:id
router.delete('/flashcards/:id', auth, requirePermission(`${PERM}:delete`), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM ai_class_flashcards WHERE id=?', [req.params.id])
    res.json({ code: 0 })
  } catch (err) { next(err) }
})

// ===================== Team Dashboard =====================
// GET /api/ai-class-learning/summary/:userId — get_gap_summary(user_id)
router.get('/summary/:userId', auth, requirePermission(`${PERM}:read`), async (req, res, next) => {
  try {
    const targetUid = Number(req.params.userId)
    const isAdmin = req.user.role === 'admin'
    if (!isAdmin && targetUid !== req.user.id) {
      return res.json({ code: 403, message: '只能查看自己的数据' })
    }

    // Aggregate current gap counts by tier
    const [gapStats] = await pool.query(
      `SELECT tier,
              COUNT(*) as total,
              SUM(status='pending') as pending
       FROM ai_class_knowledge_gaps
       WHERE user_id=? GROUP BY tier`,
      [targetUid]
    )

    // Flashcard stats
    const [cardStats] = await pool.query(
      `SELECT tier,
              COUNT(*) as total,
              SUM(correct_count) as correct,
              SUM(wrong_count) as wrong,
              SUM(streak >= 5) as mastered
       FROM ai_class_flashcards
       WHERE user_id=? GROUP BY tier`,
      [targetUid]
    )

    // Today's due
    const today = new Date().toISOString().slice(0, 10)
    const [[dueRow]] = await pool.query(
      `SELECT COUNT(*) as due FROM ai_class_flashcards
       WHERE user_id=? AND next_review <= ?`,
      [targetUid, today]
    )

    res.json({ code: 0, data: { user_id: targetUid, gapStats, cardStats, dueToday: dueRow.due } })
  } catch (err) { next(err) }
})

// GET /api/ai-class-learning/team/summary — all-team gap overview (admin only)
router.get('/team/summary', auth, requirePermission(`${PERM}:read`), async (req, res, next) => {
  try {
    if (req.user.role !== 'admin') return res.json({ code: 403, message: '仅管理员可用' })
    const today = new Date().toISOString().slice(0, 10)
    const [rows] = await pool.query(`
      SELECT u.id as user_id, u.name, u.phone,
        COUNT(DISTINCT g.id) as total_gaps,
        SUM(g.tier=1 AND g.status='pending') as tier1_pending,
        SUM(g.tier=2 AND g.status='pending') as tier2_pending,
        SUM(g.tier=3 AND g.status='pending') as tier3_pending,
        COUNT(DISTINCT f.id) as total_cards,
        SUM(f.next_review <= ?) as due_today
      FROM users u
      LEFT JOIN ai_class_knowledge_gaps g ON g.user_id=u.id
      LEFT JOIN ai_class_flashcards f ON f.user_id=u.id
      WHERE u.server_profile_id = ?
      GROUP BY u.id
      ORDER BY tier1_pending DESC
    `, [today, req.user.server_profile_id || 1])
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

export default router
