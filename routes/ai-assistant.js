// ai-assistant.js — 横琴湾 AI 助手（原 /root/backend/src/services/ai/assistant.js 迁移）
// 路径前缀: /api/ai-assistant/*
// 改造点 (CommonJS → ESM, 挂主站 auth + rbac):
//   1. module.exports → export default
//   2. class 实例化移到 export 时
//   3. 关键词规则保留原样（波哥 2026-07-18 指定: 保留横琴湾味儿）
//   4. 每个 endpoint 挂 requirePermission
//
// 复用主站已有资源:
//   - approvals 表 (autoApprove 检查 type/days/hours)
//   - 不新建表 (跟 labor-ai.js 同原则)
//
// tenant.js 不接进主站 — 跟 server_profiles 多租户冲突, 移到 trash
import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission, PERMISSIONS as P } from '../middleware/rbac.js'

const router = express.Router()
router.use(auth)

// ============================================================
// AI 助手核心 — 关键词匹配 + 业务分类
// (从 /root/backend/src/services/ai/assistant.js 原样移植)
// ============================================================
const AI_RULES = [
  { k: ['会议','会议室','预约'], a: '会议室预约请进入"会议室预约"页面。', c: 'venue', confidence: 0.95 },
  { k: ['考勤','打卡','请假'], a: '考勤打卡请进入"考勤管理"，请假请进入"申请审批"。', c: 'attendance', confidence: 0.95 },
  { k: ['管家','工单','维修'], a: '管家服务请进入"管家预约"。', c: 'butler', confidence: 0.95 },
  { k: ['活动','报名','签到'], a: '活动报名请进入"活动详情"页面。', c: 'activity', confidence: 0.95 },
  { k: ['红包','拼团','秒杀','积分'], a: '营销活动请进入对应页面。', c: 'marketing', confidence: 0.95 }
]

function matchRule(message) {
  for (const r of AI_RULES) {
    if (r.k.some(k => message.includes(k))) {
      return { answer: r.a, category: r.c, confidence: r.confidence }
    }
  }
  return { answer: '您好，我是横琴湾区智能助手。请问需要什么帮助？', category: 'general', confidence: 0.5 }
}

// ============================================================
// POST /api/ai-assistant/chat — 对话查询
// Body: { message: string }
//   返回: { answer, category, confidence }
// 权限: ai-assistant:read
// ============================================================
router.post('/chat', requirePermission(P.AI_ASSISTANT_READ), async (req, res, next) => {
  try {
    const { message } = req.body
    if (!message || typeof message !== 'string') {
      return res.status(400).json({ code: 400, message: 'message 必填且必须是字符串' })
    }
    const result = matchRule(message.trim())
    // 记录查询日志（写入主站 approvals 之外的简单表 - 暂不落库，保持纯函数）
    res.json({ code: 0, data: result, message: 'ok' })
  } catch (err) { next(err) }
})

// ============================================================
// POST /api/ai-assistant/auto-approve — 自动审批判定
// Body: { type: 'leave'|'overtime', days?: number, hours?: number }
//   返回: { approved, reason }
// 权限: ai-assistant:write
// 注意: 这是判定建议，不直接修改 approvals 表 — 由调用方决定是否执行
// ============================================================
router.post('/auto-approve', requirePermission(P.AI_ASSISTANT_WRITE), async (req, res, next) => {
  try {
    const { type, days, hours } = req.body
    if (!type) {
      return res.status(400).json({ code: 400, message: 'type 必填 (leave/overtime)' })
    }
    let result
    if (type === 'leave' && typeof days === 'number' && days <= 1) {
      result = { approved: true, reason: '1 天以内自动批准' }
    } else if (type === 'overtime' && typeof hours === 'number' && hours <= 4) {
      result = { approved: true, reason: '4 小时以内自动批准' }
    } else {
      result = { approved: false, reason: '需人工审批' }
    }
    res.json({ code: 0, data: result, message: 'ok' })
  } catch (err) { next(err) }
})

// ============================================================
// GET /api/ai-assistant/rules — 列出所有规则 (调试用)
// 权限: ai-assistant:read
// ============================================================
router.get('/rules', requirePermission(P.AI_ASSISTANT_READ), (req, res) => {
  res.json({ code: 0, data: AI_RULES, message: 'ok' })
})

export default router