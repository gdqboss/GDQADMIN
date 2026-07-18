/**
 * Labor AI 监督引擎 - 计算每个工人的多维画像
 *
 * 输入：worker_profiles + work_logs + attendance + jobsites
 * 输出：每个工人的 ai_worker_profiles 记录
 *
 * 调用：每晚 cron + 手动触发
 */

import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// ============================================================
// 画像计算（核心）
// ============================================================
async function computeWorkerProfile(workerId) {
  const profile = {
    score_activity: 0,
    score_punctuality: 0,
    score_quality: 0,
    score_efficiency: 0,
    score_collaboration: 0,
    score_overall: 0,
    traits: [],
    metrics: {},
    alerts: [],
    ai_summary: ''
  }

  try {
    // 1. 工人基础信息
    const [[worker]] = await pool.query(
      'SELECT id, user_id, skills, skill_level, employment_status, current_jobsite_id, efficiency_score, quality_score, impact_score, total_work_hours FROM worker_profiles WHERE id = ?',
      [workerId]
    )
    if (!worker) return null
    profile.user_id = worker.user_id
    profile.name = worker.user_id // 简化

    // 2. 活跃度：最近 30 天日志数 / 提交率 / 平均字数
    const [[logStats]] = await pool.query(`
      SELECT
        COUNT(*) as log_count,
        AVG(LENGTH(today_work)) as avg_length,
        SUM(CASE WHEN status='submitted' THEN 1 ELSE 0 END) as submitted_count,
        SUM(CASE WHEN attachments IS NOT NULL AND attachments != '[]' AND attachments != '' THEN 1 ELSE 0 END) as with_photos,
        MAX(created_at) as last_log_at
      FROM work_logs
      WHERE user_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    `, [worker.user_id])
    
    const logCount = logStats?.log_count || 0
    const expectedDaily = 22 // 月工作日
    const logRatio = Math.min(logCount / expectedDaily, 1)
    profile.score_activity = Math.round(logRatio * 100)
    profile.metrics.log_count_30d = logCount
    profile.metrics.log_avg_length = Math.round(logStats?.avg_length || 0)
    profile.metrics.log_with_photo_rate = logCount > 0 ? Math.round((logStats?.with_photos || 0) / logCount * 100) : 0
    
    if (profile.score_activity >= 80) profile.traits.push('日志积极')
    else if (profile.score_activity < 30) {
      profile.traits.push('日志少')
      profile.alerts.push({ type: 'low_activity', msg: '近 30 天日志极少，可能影响项目跟踪' })
    }
    if (profile.metrics.log_avg_length > 200) profile.traits.push('记录详细')
    if (profile.metrics.log_with_photo_rate > 70) profile.traits.push('习惯拍照')

    // 3. 准时度：考勤准时率
    const [[attendStats]] = await pool.query(`
      SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('present','normal') OR clock_in IS NOT NULL THEN 1 ELSE 0 END) as on_time,
        SUM(CASE WHEN status IN ('late','absent') THEN 1 ELSE 0 END) as late_or_absent,
        SUM(overtime_hours) as total_overtime
      FROM attendance
      WHERE user_id = ?
        AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    `, [worker.user_id])
    
    const totalAttend = attendStats?.total || 0
    const onTime = attendStats?.on_time || 0
    profile.score_punctuality = totalAttend > 0 ? Math.round(onTime / totalAttend * 100) : 50
    profile.metrics.attendance_rate = profile.score_punctuality
    profile.metrics.total_overtime_30d = attendStats?.total_overtime || 0
    
    if (profile.score_punctuality >= 90) profile.traits.push('出勤稳定')
    else if (profile.score_punctuality < 70) profile.traits.push('出勤不稳')
    
    if (profile.metrics.total_overtime_30d > 50) {
      profile.alerts.push({ type: 'overwork', msg: `近 30 天加班 ${profile.metrics.total_overtime_30d}h，可能疲劳` })
      profile.traits.push('加班多')
    }

    // 4. 质量：从 worker_profiles 已有评分
    const effScore = parseFloat(worker.efficiency_score) || 0
    const qualScore = parseFloat(worker.quality_score) || 0
    const impScore = parseFloat(worker.impact_score) || 0
    
    profile.score_efficiency = Math.min(Math.round(effScore), 100)
    profile.score_quality = Math.min(Math.round(qualScore), 100)
    profile.metrics.efficiency_score = effScore
    profile.metrics.quality_score = qualScore
    profile.metrics.impact_score = impScore
    
    // work_log_evaluations 评分
    const [[evalStats]] = await pool.query(`
      SELECT AVG(CHAR_LENGTH(content)) as avg_eval_length, COUNT(*) as eval_count
      FROM work_log_evaluations
      WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    `, [worker.user_id])
    profile.metrics.eval_count_90d = evalStats?.eval_count || 0

    // 5. 协作：参与项目数 + 评价
    profile.score_collaboration = Math.min(profile.metrics.eval_count_90d * 20, 100)
    if (profile.metrics.eval_count_90d > 0) profile.traits.push('有工作评价')

    // 6. 综合分（加权）
    profile.score_overall = Math.round(
      profile.score_activity * 0.25 +
      profile.score_punctuality * 0.25 +
      profile.score_quality * 0.25 +
      profile.score_efficiency * 0.15 +
      profile.score_collaboration * 0.10
    )

    // 7. AI 评语（规则生成）
    const summaries = []
    if (profile.score_overall >= 80) summaries.push('⭐ 综合表现优秀')
    else if (profile.score_overall < 50) summaries.push('⚠️ 综合表现待提升')
    
    if (profile.traits.length > 0) summaries.push(`特点：${profile.traits.join('、')}`)
    if (profile.alerts.length > 0) summaries.push(`预警：${profile.alerts.map(a => a.msg).join('；')}`)
    
    profile.ai_summary = summaries.join('；') || '数据较少，建议持续观察'

    // 8. 风险信号检测
    const recentLogs = await pool.query(`
      SELECT COUNT(*) as cnt FROM work_logs
      WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    `, [worker.user_id])
    const recentLogCount = recentLogs[0][0]?.cnt || 0
    if (recentLogCount === 0 && logCount > 0) {
      profile.alerts.push({ type: 'silent', msg: '近 7 天无日志，可能状态异常' })
    }

  } catch (err) {
    console.error(`[labor-ai] compute worker ${workerId} error:`, err.message)
    profile.ai_summary = `计算异常: ${err.message}`
  }

  return profile
}

// ============================================================
// 批量计算所有工人
// ============================================================
async function computeAllWorkers() {
  const [workers] = await pool.query('SELECT id, user_id FROM worker_profiles')
  const results = { success: 0, failed: 0, details: [] }
  
  for (const w of workers) {
    try {
      const profile = await computeWorkerProfile(w.id)
      if (!profile) continue

      await pool.query(`
        INSERT INTO ai_worker_profiles
          (user_id, worker_profile_id, name, score_activity, score_punctuality, score_quality, score_efficiency, score_collaboration, score_overall, traits_json, metrics_json, alerts_json, ai_summary, computed_at, last_event_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE
          name=VALUES(name), score_activity=VALUES(score_activity), score_punctuality=VALUES(score_punctuality),
          score_quality=VALUES(score_quality), score_efficiency=VALUES(score_efficiency),
          score_collaboration=VALUES(score_collaboration), score_overall=VALUES(score_overall),
          traits_json=VALUES(traits_json), metrics_json=VALUES(metrics_json), alerts_json=VALUES(alerts_json),
          ai_summary=VALUES(ai_summary), computed_at=NOW(), last_event_at=VALUES(last_event_at)
      `, [
        profile.user_id, w.id, profile.name,
        profile.score_activity, profile.score_punctuality, profile.score_quality,
        profile.score_efficiency, profile.score_collaboration, profile.score_overall,
        JSON.stringify(profile.traits), JSON.stringify(profile.metrics),
        JSON.stringify(profile.alerts), profile.ai_summary,
        profile.metrics.last_log_at || null
      ])
      results.success++
      results.details.push({ worker_id: w.id, score: profile.score_overall })
    } catch (err) {
      results.failed++
      results.details.push({ worker_id: w.id, error: err.message })
    }
  }
  return results
}

// ============================================================
// Endpoints
// ============================================================
router.get('/health', (req, res) => {
  res.json({ ok: true, module: 'labor-ai-supervisor', version: '0.1' })
})

// 触发全量计算（admin only）
router.post('/compute-all', async (req, res) => {
  if (req.user?.role !== 'admin') {
    return res.status(403).json({ code: 403, message: '仅 admin' })
  }
  const result = await computeAllWorkers()
  res.json({ code: 0, data: result })
})

// 单个工人画像
router.get('/profile/:user_id', async (req, res) => {
  const [rows] = await pool.query(
    'SELECT * FROM ai_worker_profiles WHERE user_id = ?',
    [req.params.user_id]
  )
  if (rows.length === 0) {
    // 自动计算
    const [[worker]] = await pool.query('SELECT id FROM worker_profiles WHERE user_id = ?', [req.params.user_id])
    if (!worker) return res.status(404).json({ code: 404, message: '工人不存在' })
    const profile = await computeWorkerProfile(worker.id)
    if (profile) {
      await pool.query(`
        INSERT INTO ai_worker_profiles
          (user_id, worker_profile_id, name, score_activity, score_punctuality, score_quality, score_efficiency, score_collaboration, score_overall, traits_json, metrics_json, alerts_json, ai_summary, computed_at, last_event_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE
          score_activity=VALUES(score_activity), score_punctuality=VALUES(score_punctuality),
          score_quality=VALUES(score_quality), score_efficiency=VALUES(score_efficiency),
          score_collaboration=VALUES(score_collaboration), score_overall=VALUES(score_overall),
          traits_json=VALUES(traits_json), metrics_json=VALUES(metrics_json), alerts_json=VALUES(alerts_json),
          ai_summary=VALUES(ai_summary), computed_at=NOW()
      `, [
        profile.user_id, worker.id, profile.name,
        profile.score_activity, profile.score_punctuality, profile.score_quality,
        profile.score_efficiency, profile.score_collaboration, profile.score_overall,
        JSON.stringify(profile.traits), JSON.stringify(profile.metrics),
        JSON.stringify(profile.alerts), profile.ai_summary, profile.metrics.last_log_at || null
      ])
    }
    return res.json({ code: 0, data: profile })
  }
  res.json({ code: 0, data: rows[0] })
})

// TOP 工人
router.get('/top', async (req, res) => {
  const limit = parseInt(req.query.limit) || 10
  const dimension = req.query.dimension || 'overall'
  const scoreCol = `score_${dimension}`
  const [rows] = await pool.query(`
    SELECT * FROM ai_worker_profiles
    WHERE ${scoreCol} > 0
    ORDER BY ${scoreCol} DESC
    LIMIT ?
  `, [limit])
  res.json({ code: 0, data: rows })
})

// 风险工人
router.get('/risk', async (req, res) => {
  const [rows] = await pool.query(`
    SELECT * FROM ai_worker_profiles
    WHERE JSON_LENGTH(alerts_json) > 0
       OR score_overall < 50
    ORDER BY score_overall ASC
    LIMIT 50
  `)
  res.json({ code: 0, data: rows })
})

// 总览仪表盘
router.get('/dashboard', async (req, res) => {
  const [stats] = await pool.query(`
    SELECT
      COUNT(*) as total_workers,
      AVG(score_overall) as avg_overall,
      AVG(score_activity) as avg_activity,
      AVG(score_punctuality) as avg_punctuality,
      AVG(score_quality) as avg_quality,
      SUM(CASE WHEN score_overall >= 80 THEN 1 ELSE 0 END) as excellent,
      SUM(CASE WHEN score_overall >= 60 AND score_overall < 80 THEN 1 ELSE 0 END) as good,
      SUM(CASE WHEN score_overall < 60 THEN 1 ELSE 0 END) as need_improve,
      SUM(CASE WHEN JSON_LENGTH(alerts_json) > 0 THEN 1 ELSE 0 END) as with_alerts
    FROM ai_worker_profiles
  `)
  res.json({ code: 0, data: stats[0] })
})

export default router
