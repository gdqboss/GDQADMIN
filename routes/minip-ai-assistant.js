/**
 * Minip 员工 AI 助理 + 跨模块智能
 *
 * 功能：
 * 1. 员工 AI 问答（"我这周工资到没？"）
 * 2. 财务 AI 审计（报销异常检测）
 * 3. HR AI 助手（招聘匹配）
 * 4. 营销 AI 投手（活动 ROI）
 * 5. 跨模块联动（labor 工资自动算 minip 钱包）
 *
 * Endpoint: /api/minip-ai/*
 */

import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// ============================================================
// 1. 员工 AI 助理（自然语言查询）
// ============================================================
router.post('/ask', async (req, res) => {
  try {
    const { question, user_id } = req.body
    if (!question) return res.json({ code: 400, message: 'question 必填' })

    const uid = user_id || req.user?.id
    if (!uid) return res.json({ code: 400, message: 'user_id 必填' })

    const intent = detectIntent(question)
    let answer = ''

    if (intent === 'salary') {
      // 查工资
      const [rows] = await pool.query(
        `SELECT period, base_salary, bonus, deduction, net_salary, paid_at, status
         FROM minip_hr_payroll WHERE user_id = ? ORDER BY period DESC LIMIT 3`,
        [uid]
      )
      answer = formatSalary(rows)
    } else if (intent === 'wallet') {
      const [rows] = await pool.query(
        `SELECT type, amount, balance_after, source_type, remark, created_at
         FROM minip_wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5`,
        [uid]
      )
      answer = formatWallet(rows)
    } else if (intent === 'leave') {
      // 用 minip_hr_applications 当请假表
      const [rows] = await pool.query(
        `SELECT id, type, status, created_at FROM minip_hr_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 3`,
        [uid]
      )
      answer = formatApplication(rows)
    } else if (intent === 'expense') {
      // 没有 expense 表，用 wallet 替代
      const [rows] = await pool.query(
        `SELECT type, amount, source_type, remark, created_at
         FROM minip_wallet_transactions WHERE user_id = ? AND source_type LIKE '%expense%' ORDER BY created_at DESC LIMIT 5`,
        [uid]
      )
      answer = formatExpense(rows)
    } else if (intent === 'attendance') {
      // 没有 attendance 表
      answer = '考勤模块数据暂未接入'
    } else if (intent === 'work_log') {
      // 没有 office work_logs 表
      answer = '工作日志模块数据暂未接入（labor 模块有，写日志去那边）'
    } else {
      answer = `我目前能回答的问题：
1. 工资（"我这月工资多少"）
2. 钱包（"钱包余额"）
3. 请假（"我最近的请假"）
4. 报销（"我的报销进度"）
5. 考勤（"本周考勤"）
6. 日志（"我最近的工作日志"）`
    }

    res.json({
      code: 0,
      data: {
        intent,
        question,
        answer,
        user_id: uid
      }
    })
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
})

// ============================================================
// 2. 财务 AI 审计（自动检测异常报销）
// ============================================================
router.get('/audit/expenses', async (req, res) => {
  if (req.user?.role !== 'admin') {
    return res.status(403).json({ code: 403, message: '仅 admin' })
  }

  const alerts = []

  // a. 检测大额钱包流水（>10000）替代 expense 检测
  const [large] = await pool.query(`
    SELECT user_id, SUM(amount) as total, COUNT(*) as cnt
    FROM minip_wallet_transactions
    WHERE type IN ('expense','withdraw','payment')
      AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY user_id
    HAVING total > 10000
    ORDER BY total DESC LIMIT 20
  `)
  if (large.length > 0) {
    alerts.push({
      type: 'large_expense',
      severity: 'medium',
      msg: `${large.length} 个用户近 30 天支出超过 10000 元`,
      details: large
    })
  }

  // b. 检测同金额重复流水
  const [dup] = await pool.query(`
    SELECT user_id, amount, COUNT(*) as cnt
    FROM minip_wallet_transactions
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    GROUP BY user_id, amount, DATE(created_at)
    HAVING cnt > 1
    LIMIT 20
  `)
  if (dup.length > 0) {
    alerts.push({
      type: 'duplicate',
      severity: 'high',
      msg: `${dup.length} 笔同金额同日重复流水，疑似异常`,
      details: dup
    })
  }

  // c. 深夜流水（非工作时间）
  const [oddHour] = await pool.query(`
    SELECT user_id, COUNT(*) as cnt
    FROM minip_wallet_transactions
    WHERE (DAYOFWEEK(created_at) IN (1,7)
       OR HOUR(created_at) < 7 OR HOUR(created_at) > 22)
      AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY user_id
    HAVING cnt > 3
    LIMIT 20
  `)
  if (oddHour.length > 0) {
    alerts.push({
      type: 'odd_hour',
      severity: 'low',
      msg: `${oddHour.length} 个用户经常在深夜/周末交易`,
      details: oddHour
    })
  }

  res.json({
    code: 0,
    data: {
      alert_count: alerts.length,
      alerts,
      audit_time: new Date().toISOString(),
      note: '基于 minip_wallet_transactions 审计（expense 表未建）'
    }
  })
})

// ============================================================
// 3. HR AI 招聘助手（简历评分）
// ============================================================
router.post('/hr/score-resume', async (req, res) => {
  const { job_id, candidate_skills, candidate_experience } = req.body
  
  // 简化版评分：技能匹配度 + 经验年限
  const [[job]] = await pool.query(
    `SELECT title, requirement FROM minip_hr_recruit WHERE id = ?`,
    [job_id]
  )
  if (!job) return res.status(404).json({ code: 404, message: '职位不存在' })

  const jobReqs = (job.requirement || '').toLowerCase()
  const candSkills = (candidate_skills || '').toLowerCase()
  
  // 简单的关键词匹配评分
  const skillKeywords = jobReqs.split(/[,，、\s]+/).filter(w => w.length > 1)
  const matched = skillKeywords.filter(k => candSkills.includes(k))
  const skillScore = skillKeywords.length > 0 ? Math.round(matched.length / skillKeywords.length * 100) : 50

  const expScore = Math.min(((candidate_experience || 0) / 3) * 100, 100)
  const totalScore = Math.round(skillScore * 0.6 + expScore * 0.4)

  res.json({
    code: 0,
    data: {
      job: job.title,
      scores: {
        skill: skillScore,
        experience: expScore,
        total: totalScore
      },
      matched_skills: matched,
      recommendation: totalScore >= 80 ? '强烈推荐面试' : totalScore >= 60 ? '可考虑' : '不建议',
      ai_summary: `${candidate_skills || '候选人'} 与 ${job.title} 匹配度 ${totalScore}分，${matched.length > 0 ? '优势在 ' + matched.join('、') : '需进一步评估'}`
    }
  })
})

// ============================================================
// 4. 营销 AI 投手（活动 ROI）
// ============================================================
router.get('/marketing/roi', async (req, res) => {
  if (req.user?.role !== 'admin') {
    return res.status(403).json({ code: 403, message: '仅 admin' })
  }

  const [activities] = await pool.query(`
    SELECT id, title, status, start_date, end_date, max_participants, current_participants
    FROM minip_activities
    ORDER BY created_at DESC LIMIT 20
  `)

  // 简化版 ROI（基于 max_participants / current_participants 估算）
  const enriched = activities.map(a => {
    const participation = a.max_participants > 0 
      ? a.current_participants / a.max_participants 
      : 0
    const roi = participation > 0.7 ? 2.5 : participation > 0.4 ? 1.5 : 0.6
    return {
      ...a,
      participation_rate: (participation * 100).toFixed(1) + '%',
      estimated_roi: roi.toFixed(2),
      recommendation: roi > 2 ? '🟢 优秀，可复用模板' : roi > 1 ? '🟡 一般，可优化' : '🔴 参与度低，建议复盘'
    }
  })

  res.json({
    code: 0,
    data: enriched,
    summary: {
      total: enriched.length,
      excellent: enriched.filter(a => parseFloat(a.estimated_roi) > 2).length,
      loss: enriched.filter(a => parseFloat(a.estimated_roi) < 1).length
    }
  })
})

// ============================================================
// 5. 跨模块联动（labor + minip 工资自动算）
// ============================================================
router.get('/cross/payroll-summary', async (req, res) => {
  // labor 工人的工资 = 工时 * 时薪
  const [laborSummary] = await pool.query(`
    SELECT 
      COUNT(*) as worker_count,
      COALESCE(SUM(total_work_hours),0) as total_hours,
      COALESCE(AVG(hourly_rate),0) as avg_rate,
      COALESCE(SUM(total_work_hours * hourly_rate),0) as estimated_total_pay
    FROM worker_profiles
    WHERE employment_status = 'active'
  `)

  // minip 工资单统计
  const [payrollStats] = await pool.query(`
    SELECT
      COUNT(DISTINCT user_id) as paid_user_count,
      COALESCE(SUM(net_salary),0) as total_net_payroll
    FROM minip_hr_payroll
    WHERE status = 'paid'
  `)

  const [activeEmployees] = await pool.query(`
    SELECT COUNT(*) as active_emp FROM minip_employees WHERE status = 'active'
  `)

  res.json({
    code: 0,
    data: {
      labor: laborSummary[0],
      minip: {
        active_employee_count: activeEmployees[0]?.active_emp || 0,
        paid_user_count: payrollStats[0]?.paid_user_count || 0,
        total_net_payroll: parseFloat(payrollStats[0]?.total_net_payroll || 0)
      },
      combined_estimated_payroll: parseFloat(laborSummary[0].estimated_total_pay || 0) + 
                                  parseFloat(payrollStats[0]?.total_net_payroll || 0),
      note: 'labor 按工时估算，minip 按已发工资单统计'
    }
  })
})

// ============================================================
// Helpers
// ============================================================
function detectIntent(q) {
  const lower = (q || '').toLowerCase()
  if (lower.includes('工资') || lower.includes('薪水') || lower.includes('salary')) return 'salary'
  if (lower.includes('钱包') || lower.includes('余额') || lower.includes('wallet')) return 'wallet'
  if (lower.includes('请假') || lower.includes('休假') || lower.includes('leave')) return 'leave'
  if (lower.includes('报销') || lower.includes('expense')) return 'expense'
  if (lower.includes('考勤') || lower.includes('打卡') || lower.includes('attendance')) return 'attendance'
  if (lower.includes('日志') || lower.includes('work_log') || lower.includes('工作报告')) return 'work_log'
  return 'unknown'
}

function formatSalary(rows) {
  if (rows.length === 0) return '暂无工资记录'
  let s = '💰 最近工资：\n\n'
  for (const r of rows) {
    s += `📅 ${r.period}\n`
    s += `   基本: ¥${r.base_salary || 0} | 奖金: ¥${r.bonus || 0} | 扣除: ¥${r.deduction || 0}\n`
    s += `   实发: ¥${r.net_salary || 0} | ${r.paid_at ? '✅ 已发' : '⏳ 待发'} (${r.status})\n\n`
  }
  return s
}

function formatWallet(rows) {
  if (rows.length === 0) return '钱包暂无流水'
  let s = '💳 最近钱包流水：\n\n'
  for (const r of rows) {
    const d = r.created_at instanceof Date ? r.created_at.toISOString().slice(0,10) : String(r.created_at).slice(0,10)
    s += `${d} | ${r.type} | ¥${r.amount} | 余额 ¥${r.balance_after || '-'}\n`
    if (r.remark) s += `   ${r.remark}\n`
  }
  return s
}

function formatApplication(rows) {
  if (rows.length === 0) return '暂无申请记录'
  return `📋 最近申请：\n${rows.map(r => {
    const d = r.created_at instanceof Date ? r.created_at.toISOString().slice(0,10) : String(r.created_at).slice(0,10)
    return `${d} | ${r.type} | ${r.status}`
  }).join('\n')}`
}

function formatExpense(rows) {
  if (rows.length === 0) return '暂无报销/支出流水'
  let s = '🧾 最近支出：\n'
  for (const r of rows) {
    const d = r.created_at instanceof Date ? r.created_at.toISOString().slice(0,10) : String(r.created_at).slice(0,10)
    s += `${d} | ${r.type} | ¥${r.amount} | ${r.source_type || '-'}\n`
    if (r.remark) s += `   ${r.remark}\n`
  }
  return s
}

router.get('/health', (req, res) => {
  res.json({ ok: true, module: 'minip-ai-assistant', version: '0.1' })
})

export default router
