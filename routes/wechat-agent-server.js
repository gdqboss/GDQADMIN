// ============================================================
// wechat-agent-server.js — 多用户多 Agent 个人微信服务模块
// 路径前缀: /api/wechat-agent/*
// 设计: 江小鱼 2026-08-29
//
// 架构: 微信用户 → [通道层 ChannelAdapter] → 消息进系统 → [身份层 wx_user]
//       → [Agent 层 每用户专属 agent] → AI 回复 → 通道推回
//
// 通道可插拔:
//   - 内置 MockAdapter (模拟微信通道, 供测试 / 演示 / 跑通全流程)
//   - 真实微信通道后接 = 写一个新 ChannelAdapter + 在 wechat_channels 表登记
//     所有消息入口统一走 POST /api/wechat-agent/webhook
//
// 复用体系:
//   - ai-token-* 计费 (预留 token_used 字段)
//   - AI 课堂 / LLM 体系 (agent 引擎预留, 当前用可配置规则驱动, 可跑可测)
// ============================================================
import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission, PERMISSIONS as P } from '../middleware/rbac.js'

const router = express.Router()

// 管理台端点走 auth + rbac; webhook 通道入口是外部回调, 不能要求登录管理员 token
const adminRouter = express.Router()
adminRouter.use(auth)

// ════════════════════════════════════════════════════════════
// 通道层抽象 (ChannelAdapter)
// ════════════════════════════════════════════════════════════
// 每个真实微信通道 = 一个 adapter, 实现统一接口:
//   send(wxUserId, text)  — 通过该通道把 agent 回复推给微信用户
//   name()               — 通道标识
// 内置 MockAdapter 只做"收下消息 + 返回可读日志", 不真连微信。
// 以后接真实微信(padlocal/ilink/公众号) = 新写一个 adapter + 登记 wechat_channels。
// ════════════════════════════════════════════════════════════

class MockChannelAdapter {
  constructor(key) { this.key = key || 'mock' }
  name() { return this.key }
  async send(wxUserId, text) {
    // mock 通道: 只记一条"已投递"日志, 不真发微信
    return { ok: true, channel: this.key, wxUserId, delivered: true, note: 'mock 通道 (未接真实微信)' }
  }
}

// 通道注册表: 实例化所有启用的通道 adapter
const channelRegistry = new Map()
async function loadChannels() {
  try {
    const [rows] = await pool.query('SELECT channel_key, channel_type, status FROM wechat_channels WHERE status="active"')
    for (const r of rows) {
      if (r.channel_type === 'mock' && !channelRegistry.has(r.channel_key)) {
        channelRegistry.set(r.channel_key, new MockChannelAdapter(r.channel_key))
      }
    }
  } catch (e) { /* 表可能还没建, 忽略 */ }
}

async function ensureMockChannel() {
  // 保证至少有一个 mock 通道可用 (首次自动注册)
  const [rows] = await pool.query('SELECT id FROM wechat_channels WHERE channel_key="mock"')
  if (rows.length === 0) {
    await pool.query(
      `INSERT INTO wechat_channels (channel_key, channel_type, display_name, status, remark)
       VALUES (?, ?, ?, 'active', ?)`,
      ['mock', 'mock', '内置 Mock 通道', '测试/演示用, 未接真实微信, 模拟全流程']
    )
  }
  await loadChannels()
}

// ════════════════════════════════════════════════════════════
// Agent 引擎
// ════════════════════════════════════════════════════════════
// 每个微信用户 = 一个专属 agent (wechat_users 表存储 per-user 配置)
// 当前引擎: 规则/关键词驱动 (可跑可测, 不依赖外部 LLM)
//   - 若用户配置了 system_prompt 且开启 agent, 用规则引擎给出引导性回复
//   - 预留 AI 接口: 之后把 replyByLLM() 接到 AI 课堂 / ai-token 体系
// ════════════════════════════════════════════════════════════

function agentReply(user, message) {
  const text = (message || '').trim()
  const name = user.agent_name || user.nickname || '亲爱的用户'
  const lower = text.toLowerCase()

  // 最简单的"身份识别 + 引导" —— 让 agent 有"自己的"角色感
  if (!text) return '您好, 请输入内容。'

  // 通用引导
  const greeting = /^(你好|hi|hello|嗨|在吗|哈喽)/i.test(text)
  if (greeting) {
    return `你好, ${name}! 我是你的专属 AI 助手。请问有什么可以帮你?`
  }

  // 帮助
  if (/帮助|help|你能|做什么|功能/.test(text)) {
    return `我可以帮你: 回答问题 / 处理业务 / 查询信息。你可以直接问我任何问题, 或告诉我需要办理什么业务。`
  }

  // 查询天气 (演示规则)
  if (/天气|温度/.test(text)) {
    return `抱歉, ${name}, 天气查询需要对接第三方服务, 我目前还没接入。你可以先问其它我能帮上忙的。`
  }

  // 身份确认
  if (/你是谁|你叫什么|介绍/.test(text)) {
    return `我是${name}的专属 AI 助手 (agent_name=${user.agent_name || '默认助手'})。我的职责是根据你的专属配置和知识, 为你提供帮助。`
  }

  // 默认
  const custom = user.system_prompt ? ` (已按你的专属配置: ${String(user.system_prompt).slice(0, 50)}...)` : ''
  return `收到: "${text.slice(0, 60)}"。${name}, 我是你的专属 agent${custom}——目前是规则引擎演示版, 更多能力(LLM 对话/业务对接)后续接入。`
}

// ════════════════════════════════════════════════════════════
// 消息处理核心: 微信消息进来 → 找/建用户 → 触发 agent → 记录 + 投递
// ════════════════════════════════════════════════════════════
async function handleIncomingMessage({ openid, senderName, content, msgType = 'text', channelKey = 'mock' }) {
  // 1. 找到或创建微信用户
  let [users] = await pool.query('SELECT * FROM wechat_users WHERE openid = ?', [openid])
  let wxUser = users[0]
  if (!wxUser) {
    const [r] = await pool.query(
      `INSERT INTO wechat_users (openid, nickname, channel_key, status, agent_enabled)
       VALUES (?, ?, ?, 'active', 1)`,
      [openid, senderName || ('微信用户_' + String(openid).slice(-4)), channelKey]
    )
    ;[users] = await pool.query('SELECT * FROM wechat_users WHERE id = ?', [r.insertId])
    wxUser = users[0]
  }

  // 2. 记录 inbound 消息
  const [ins] = await pool.query(
    `INSERT INTO wechat_messages (wx_user_id, channel_key, direction, msg_type, content)
     VALUES (?, ?, 'in', ?, ?)`,
    [wxUser.id, channelKey, msgType, content || '']
  )
  const inMsgId = ins.insertId

  // 3. 触发 agent 回复
  let reply = null
  let agentStatus = 'skipped'
  let tokenUsed = 0
  if (wxUser.agent_enabled === 1 && wxUser.status === 'active') {
    try {
      reply = agentReply(wxUser, content)
      agentStatus = 'ok'
      // 预留: 真实 LLM 回复时在这里消耗 token, 对接 ai-token 计费
      tokenUsed = Math.ceil(String(reply).length / 4)
    } catch (e) {
      reply = '抱歉, agent 暂时无法回复, 请稍后再试。'
      agentStatus = 'error'
    }
  } else if (wxUser.status === 'blocked') {
    agentStatus = 'skipped'
    reply = null // 拉黑用户不回复
  }

  // 4. 回写 ai_reply 到 inbound 消息
  if (reply) {
    await pool.query(
      'UPDATE wechat_messages SET ai_reply=?, token_used=?, agent_status=? WHERE id=?',
      [reply, tokenUsed, agentStatus, inMsgId]
    )
    // 5. 记录 outbound 消息
    const [outs] = await pool.query(
      `INSERT INTO wechat_messages (wx_user_id, channel_key, direction, msg_type, content, agent_status)
       VALUES (?, ?, 'out', 'text', ?, ?)`,
      [wxUser.id, channelKey, reply, agentStatus]
    )
    const outMsgId = outs.insertId
    // 6. 通过通道把回复投递给微信用户
    let delivery = null
    try {
      const adapter = channelRegistry.get(channelKey) || channelRegistry.get('mock')
      delivery = adapter ? await adapter.send(wxUser.id, reply) : null
    } catch (e) { /* 投递失败不阻断 */ }
    return { wxUserId: wxUser.id, inMsgId, outMsgId, reply, agentStatus, tokenUsed, delivery }
  }

  return { wxUserId: wxUser.id, inMsgId, reply: null, agentStatus, tokenUsed, delivery: null }
}

// ════════════════════════════════════════════════════════════
// 通道 webhook 入口 (外部微信通道回调到这里)
// 真实微信通道(以后写 adapter 时)把收到的消息 POST 到这里:
//   { openid, senderName, content, msgType, channelKey }
// ════════════════════════════════════════════════════════════
router.post('/webhook', async (req, res, next) => {
  try {
    const { openid, senderName, content, msgType = 'text', channelKey = 'mock' } = req.body || {}
    if (!openid || !content) {
      return res.status(400).json({ code: 400, message: 'openid 和 content 必填' })
    }
    const result = await handleIncomingMessage({ openid, senderName, content, msgType, channelKey })
    res.json({ code: 0, data: result, message: 'ok' })
  } catch (err) { next(err) }
})

// ════════════════════════════════════════════════════════════
// 管理台端点 (需登录 + 权限)
// ════════════════════════════════════════════════════════════

// 模拟一个微信用户发消息 (管理台测试按钮用) — 等价于外部通道调 webhook
adminRouter.post('/simulate-message', requirePermission(P.WECHAT_AGENT_WRITE), async (req, res, next) => {
  try {
    const { openid, senderName, content } = req.body || {}
    if (!openid || !content) {
      return res.status(400).json({ code: 400, message: 'openid 和 content 必填(模拟微信用户发消息)' })
    }
    const result = await handleIncomingMessage({ openid, senderName, content, msgType: 'text', channelKey: 'mock' })
    res.json({ code: 0, data: result, message: '模拟微信消息已处理' })
  } catch (err) { next(err) }
})

// 微信用户列表
adminRouter.get('/users', requirePermission(P.WECHAT_AGENT_READ), async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT wu.*,
              (SELECT COUNT(*) FROM wechat_messages wm WHERE wm.wx_user_id = wu.id) AS msg_count,
              (SELECT MAX(created_at) FROM wechat_messages wm WHERE wm.wx_user_id = wu.id) AS last_active
       FROM wechat_users wu ORDER BY wu.id DESC`
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// 用户详情 + 该用户的消息流
adminRouter.get('/users/:id', requirePermission(P.WECHAT_AGENT_READ), async (req, res, next) => {
  try {
    const id = req.params.id
    const [users] = await pool.query('SELECT * FROM wechat_users WHERE id = ?', [id])
    if (!users[0]) return res.status(404).json({ code: 404, message: '用户不存在' })
    const [msgs] = await pool.query(
      'SELECT * FROM wechat_messages WHERE wx_user_id = ? ORDER BY id ASC', [id]
    )
    res.json({ code: 0, data: { user: users[0], messages: msgs } })
  } catch (err) { next(err) }
})

// 新增/更新微信用户 (手动添加或改 agent 配置)
adminRouter.post('/users', requirePermission(P.WECHAT_AGENT_WRITE), async (req, res, next) => {
  try {
    const { openid, nickname, avatar, agent_enabled, agent_name, system_prompt, model_key, status } = req.body || {}
    if (!openid) return res.status(400).json({ code: 400, message: 'openid 必填' })
    const [r] = await pool.query(
      `INSERT INTO wechat_users (openid, nickname, avatar, status, agent_enabled, agent_name, system_prompt, model_key)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE
         nickname=VALUES(nickname), avatar=VALUES(avatar), status=VALUES(status),
         agent_enabled=VALUES(agent_enabled), agent_name=VALUES(agent_name),
         system_prompt=VALUES(system_prompt), model_key=VALUES(model_key)`,
      [openid, nickname || null, avatar || null, status || 'active',
       agent_enabled === undefined ? 1 : (agent_enabled ? 1 : 0),
       agent_name || null, system_prompt || null, model_key || null]
    )
    res.json({ code: 0, data: { id: r.insertId }, message: 'ok' })
  } catch (err) { next(err) }
})

// 更新用户 (agent 配置/状态)
adminRouter.patch('/users/:id', requirePermission(P.WECHAT_AGENT_WRITE), async (req, res, next) => {
  try {
    const id = req.params.id
    const allowed = ['nickname', 'avatar', 'status', 'agent_enabled', 'agent_name', 'system_prompt', 'model_key']
    const sets = []
    const vals = []
    for (const k of allowed) {
      if (req.body[k] !== undefined) {
        sets.push(`${k} = ?`)
        if (k === 'agent_enabled') vals.push(req.body[k] ? 1 : 0)
        else vals.push(req.body[k])
      }
    }
    if (sets.length === 0) return res.status(400).json({ code: 400, message: '没有可更新字段' })
    sets.push('updated_at = CURRENT_TIMESTAMP')
    vals.push(id)
    const [r] = await pool.query(`UPDATE wechat_users SET ${sets.join(', ')} WHERE id = ?`, vals)
    if (r.affectedRows === 0) return res.status(404).json({ code: 404, message: '用户不存在' })
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

// 删除用户 (连同消息记录可选)
adminRouter.delete('/users/:id', requirePermission(P.WECHAT_AGENT_DELETE), async (req, res, next) => {
  try {
    const id = req.params.id
    const { cascade } = req.query
    if (cascade === '1') {
      await pool.query('DELETE FROM wechat_messages WHERE wx_user_id = ?', [id])
    }
    const [r] = await pool.query('DELETE FROM wechat_users WHERE id = ?', [id])
    if (r.affectedRows === 0) return res.status(404).json({ code: 404, message: '用户不存在' })
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

// 消息记录(全量, 可按用户/通道/方向过滤)
adminRouter.get('/messages', requirePermission(P.WECHAT_AGENT_READ), async (req, res, next) => {
  try {
    const { wxUserId, channel_key, direction, limit = 50 } = req.query
    let sql = 'SELECT wm.*, wu.nickname FROM wechat_messages wm LEFT JOIN wechat_users wu ON wu.id = wm.wx_user_id WHERE 1=1'
    const par = []
    if (wxUserId) { sql += ' AND wm.wx_user_id = ?'; par.push(wxUserId) }
    if (channel_key) { sql += ' AND wm.channel_key = ?'; par.push(channel_key) }
    if (direction) { sql += ' AND wm.direction = ?'; par.push(direction) }
    sql += ' ORDER BY wm.id DESC LIMIT ?'
    par.push(parseInt(limit) || 50)
    const [rows] = await pool.query(sql, par)
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// 通道实例列表(可插拔插槽)
adminRouter.get('/channels', requirePermission(P.WECHAT_AGENT_READ), async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM wechat_channels ORDER BY id ASC')
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// 新增通道实例(登记新的可插拔通道)
adminRouter.post('/channels', requirePermission(P.WECHAT_AGENT_WRITE), async (req, res, next) => {
  try {
    const { channel_key, channel_type = 'mock', display_name, status = 'active', remark } = req.body || {}
    if (!channel_key) return res.status(400).json({ code: 400, message: 'channel_key 必填' })
    const [r] = await pool.query(
      `INSERT INTO wechat_channels (channel_key, channel_type, display_name, status, remark)
       VALUES (?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE channel_type=VALUES(channel_type), display_name=VALUES(display_name),
         status=VALUES(status), remark=VALUES(remark)`,
      [channel_key, channel_type, display_name || null, status, remark || null]
    )
    await loadChannels()
    res.json({ code: 0, data: { id: r.insertId }, message: 'ok' })
  } catch (err) { next(err) }
})

// 更新通道状态(启用/停用)
adminRouter.patch('/channels/:id', requirePermission(P.WECHAT_AGENT_WRITE), async (req, res, next) => {
  try {
    const id = req.params.id
    const { status, display_name, remark } = req.body || {}
    const sets = []
    const vals = []
    if (status !== undefined) { sets.push('status = ?'); vals.push(status) }
    if (display_name !== undefined) { sets.push('display_name = ?'); vals.push(display_name) }
    if (remark !== undefined) { sets.push('remark = ?'); vals.push(remark) }
    if (sets.length === 0) return res.status(400).json({ code: 400, message: '没有可更新字段' })
    vals.push(id)
    const [r] = await pool.query(`UPDATE wechat_channels SET ${sets.join(', ')} WHERE id = ?`, vals)
    if (r.affectedRows === 0) return res.status(404).json({ code: 404, message: '通道不存在' })
    await loadChannels()
    res.json({ code: 0, message: 'ok' })
  } catch (err) { next(err) }
})

// 通道健康/就绪检查: 真实通道接入后在此验证是否连接
adminRouter.get('/channels/:id/health', requirePermission(P.WECHAT_AGENT_READ), async (req, res, next) => {
  try {
    const id = req.params.id
    const [rows] = await pool.query('SELECT * FROM wechat_channels WHERE id = ?', [id])
    if (!rows[0]) return res.status(404).json({ code: 404, message: '通道不存在' })
    const ch = rows[0]
    const adapter = channelRegistry.get(ch.channel_key)
    const status = adapter ? 'connected' : (ch.status === 'active' ? 'adapter-not-loaded' : 'disabled')
    res.json({ code: 0, data: { channel: ch.channel_key, status, adapter: adapter ? adapter.name() : null } })
  } catch (err) { next(err) }
})

// 统计面板
adminRouter.get('/stats', requirePermission(P.WECHAT_AGENT_READ), async (req, res, next) => {
  try {
    const [[uc]] = await pool.query('SELECT COUNT(*) AS c FROM wechat_users')
    const [[mc]] = await pool.query('SELECT COUNT(*) AS c FROM wechat_messages')
    const [[inc]] = await pool.query("SELECT COUNT(*) AS c FROM wechat_messages WHERE direction='in'")
    const [[oc]] = await pool.query("SELECT COUNT(*) AS c FROM wechat_messages WHERE direction='out'")
    const [[chc]] = await pool.query('SELECT COUNT(*) AS c FROM wechat_channels')
    const [[bad]] = await pool.query("SELECT COUNT(*) AS c FROM wechat_messages WHERE agent_status='error'")
    res.json({ code: 0, data: {
      users: uc.c, messages: mc.c, inbound: inc.c, outbound: oc.c,
      channels: chc.c, agentErrors: bad.c
    } })
  } catch (err) { next(err) }
})

// 启动时确保有 mock 通道
ensureMockChannel().catch(() => {})

// 导出两个 router (admin 带 auth, webhook 不带)
export default { router, adminRouter }
