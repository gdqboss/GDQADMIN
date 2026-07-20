// IM API - 统一即时通讯（Telegram 风格）
// 复用 smart-studio 用户体系 + 4 张 im_* 表
// 设计要点：
//   1. 全局单调递增 message_id (类似 Telegram message_id)
//   2. 全局单调递增 global_seq (类似 Telegram pts, 多端同步 delta 拉取)
//   3. 状态机 sending→sent→delivered→read (客户端乐观锁 + 服务端回包)
//   4. 历史分页: WHERE id < ? cursor, 不重不漏
//   5. 全消息单表 im_messages (channel/group/dm/bot 合一), 靠 conv_id 区分

import { Router } from 'express'
import jwt from 'jsonwebtoken'
import { pool } from '../db/connection.js'

const router = Router()

// 复用主站 JWT_SECRET（优先）+ smart-studio JWT_SECRET（fallback）
// 双 token 支持是 Step 6 加的：允许 admin 用主站 token 调 /api/im
const MAIN_JWT_SECRET = process.env.JWT_SECRET || 'gdq-default-jwt-secret-2024'
const SS_JWT_SECRET = process.env.SMART_STUDIO_JWT_SECRET || 'ss-default-secret-change-me-2026'

// ---------- auth ----------
// 支持主站 token (users.id) 或 smart-studio token (smart_studio_users.id)
// 内部统一映射到 ssUserId (主站 id 通过 mirror 查到 ss user id, 否则直接用主站 id 当 ss_user_id)
function auth(req, res, next) {
  const h = req.headers.authorization || ''
  const cookieToken = req.cookies?.smart_studio_token
  const token = h.startsWith('Bearer ') ? h.slice(7) : cookieToken
  if (!token) return res.status(401).json({ ok: false, error: '未登录' })
  // 先试主站 JWT, 再试 smart-studio JWT
  let decoded = null
  for (const secret of [MAIN_JWT_SECRET, SS_JWT_SECRET]) {
    try {
      const d = jwt.verify(token, secret)
      decoded = { ...d, _secret: secret }
      break
    } catch {}
  }
  if (!decoded) return res.status(401).json({ ok: false, error: 'token 无效' })
  // 主站 token: decoded.id = users.id; smart-studio token: decoded.uid = smart_studio_users.id
  req.ssUserId = decoded.uid || decoded.id  // 主站 fallback 用 id
  req.ssRole = decoded.role || 'user'
  req.ssUsername = decoded.username || decoded.name || decoded.phone
  req.userId = decoded.id  // 主站 user id (后端 SQL 可用)
  req.authSource = decoded._secret === MAIN_JWT_SECRET ? 'main' : 'smart_studio'
  next()
}

// ---------- helper: conv_id 生成 ----------
// dm 约定: conv_id = 'dm:<a>:<b>', a < b (按数字大小, 避免同一个会话出现两个 conv_id)
function dmConvId(uidA, uidB) {
  const a = Math.min(uidA, uidB), b = Math.max(uidA, uidB)
  return `dm:${a}:${b}`
}

// ---------- helper: 确保 dm 会话存在 ----------
async function ensureDm(uidA, uidB) {
  const convId = dmConvId(uidA, uidB)
  const [exists] = await pool.query(
    'SELECT conv_id FROM im_conversations WHERE conv_id=?',
    [convId]
  )
  if (exists.length === 0) {
    await pool.query(
      'INSERT INTO im_conversations (conv_id, conv_type, created_by, title) VALUES (?, ?, ?, ?)',
      [convId, 'dm', uidA, null]
    )
    await pool.query(
      'INSERT INTO im_members (conv_id, user_id, role) VALUES (?, ?, ?), (?, ?, ?)',
      [convId, uidA, 'owner', convId, uidB, 'owner']
    )
  }
  return convId
}

// ---------- helper: 用户在 conv 是否是成员 ----------
async function assertMember(convId, uid) {
  const [rows] = await pool.query(
    'SELECT 1 FROM im_members WHERE conv_id=? AND user_id=?',
    [convId, uid]
  )
  return rows.length > 0
}

// ---------- helper: 加载消息并 join sender info ----------
async function loadMessages(convsFilter, beforeMsgId, limit = 50) {
  const params = [...convsFilter]
  let where = `m.conv_id IN (${convsFilter.map(() => '?').join(',')})`
  if (beforeMsgId) {
    where += ' AND m.id < ?'
    params.push(beforeMsgId)
  }
  const [rows] = await pool.query(
    `SELECT m.id, m.conv_id, m.sender_id, m.body, m.media_url, m.media_type,
            m.reply_to_msg_id, m.fwd_from_msg_id, m.status, m.edited_at, m.deleted_at,
            m.global_seq, m.created_at,
            COALESCE(ss.username, main.username) AS sender_username,
            COALESCE(ss.display_name, main.display_name) AS sender_name,
            COALESCE(ss.avatar, main.avatar) AS sender_avatar
       FROM im_messages m
       LEFT JOIN smart_studio_users ss ON ss.id = m.sender_id
       LEFT JOIN users main ON main.id = m.sender_id AND ss.id IS NULL
      WHERE ${where}
      ORDER BY m.id DESC
      LIMIT ?`,
    [...params, parseInt(limit)]
  )
  return rows.reverse()
}

// ---------- /me ----------
// 双源支持：主站用户走 users 表 + mirror 出 display_name / role
// smart-studio 用户走 smart_studio_users 表
router.get('/me', auth, async (req, res) => {
  try {
    if (req.authSource === 'smart_studio') {
      const [users] = await pool.query(
        `SELECT id, username, display_name, avatar, role
           FROM smart_studio_users WHERE id=?`,
        [req.ssUserId]
      )
      if (users.length === 0) return res.json({ ok: false, error: 'user not found' })
      return res.json({ ok: true, data: users[0] })
    } else {
      // 主站用户: 从 users 表读取，im 内 ssUserId = users.id
      const [users] = await pool.query(
        `SELECT id, name AS display_name, phone AS username, avatar, role
           FROM users WHERE id=?`,
        [req.userId]
      )
      if (users.length === 0) return res.json({ ok: false, error: 'user not found' })
      return res.json({ ok: true, data: users[0] })
    }
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- 对话列表 ----------
router.get('/conversations', auth, async (req, res) => {
  try {
    const uid = req.ssUserId
    const [convs] = await pool.query(
      `SELECT c.conv_id, c.conv_type, c.title, c.last_msg_id, c.last_msg_preview,
              c.last_msg_at, c.avatar_url,
              m.last_read_msg_id, m.unread_count, m.muted
         FROM im_conversations c
         JOIN im_members m ON m.conv_id = c.conv_id AND m.user_id=?
         ORDER BY c.last_msg_at DESC`,
      [uid]
    )
    // 拿每个 dm 会话对方的 display_name — 双源
    for (const conv of convs) {
      if (conv.conv_type === 'dm') {
        const uids = conv.conv_id.replace('dm:', '').split(':').map(Number)
        const otherUid = uids.find(u => u !== uid)
        // 先试 smart_studio_users，再试 users
        let u = await pool.query(
          'SELECT id, username, display_name, avatar FROM smart_studio_users WHERE id=?',
          [otherUid]
        ).then(r => r[0])
        if (!u || u.length === 0) {
          u = await pool.query(
            'SELECT id, phone AS username, name AS display_name, avatar FROM users WHERE id=?',
            [otherUid]
          ).then(r => r[0])
          if (u && u.length > 0) u = [{ id: u[0].id, username: u[0].username, display_name: u[0].display_name, avatar: u[0].avatar }]
        }
        if (u && u[0]) {
          conv.peer = u[0]
          if (!conv.title) conv.title = u[0].display_name || u[0].username
          if (!conv.avatar_url) conv.avatar_url = u[0].avatar || null
        }
      }
    }
    res.json({ ok: true, data: convs })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- 历史消息: cursor 分页 ----------
router.get('/messages', auth, async (req, res) => {
  try {
    const { conv_id, before_msg_id, limit = '50' } = req.query
    if (!conv_id) return res.json({ ok: false, error: 'conv_id 必填' })
    if (!(await assertMember(conv_id, req.ssUserId))) {
      return res.status(403).json({ ok: false, error: '不是会话成员' })
    }
    const msgs = await loadMessages(
      [conv_id],
      before_msg_id ? parseInt(before_msg_id) : null,
      Math.min(parseInt(limit), 100)
    )
    res.json({ ok: true, data: msgs, has_more: msgs.length === parseInt(limit) })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- 发消息 ----------
// 客户端可传 temp_id (乐观锁), server 回包带正式 msg_id, 客户端将 status 从 sending 改为 sent
router.post('/send', auth, async (req, res) => {
  try {
    const { conv_id, peer_id, body, media_url, media_type, reply_to_msg_id, temp_id } = req.body || {}
    if (!body && !media_url) return res.json({ ok: false, error: '消息内容或附件不能都为空' })
    if (body && body.length > 4000) return res.json({ ok: false, error: '消息超长 (上限 4000 字符)' })

    const uid = req.ssUserId
    // 自动创建/获取 dm 会话
    let cid = conv_id
    if (!cid && peer_id) {
      cid = await ensureDm(uid, parseInt(peer_id))
    }
    if (!cid) return res.json({ ok: false, error: 'conv_id 或 peer_id 必填' })
    if (!(await assertMember(cid, uid))) {
      return res.status(403).json({ ok: false, error: '不是会话成员' })
    }

    // 简单表情/反垃圾也可后续接: 这里直接 INSERT, 用全局触发器填 global_seq
    const [r] = await pool.query(
      `INSERT INTO im_messages
       (conv_id, sender_id, body, media_url, media_type, reply_to_msg_id, temp_id)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [cid, uid, body || null, media_url || null, media_type || '', reply_to_msg_id || null, temp_id || null]
    )
    const msgId = r.insertId

    // 更新会话 last_msg (Telegram: 客户端刷新会话列表会重算)
    const preview = body ? body.slice(0, 100) : `[${media_type || 'media'}]`
    await pool.query(
      `UPDATE im_conversations
          SET last_msg_id=?, last_msg_preview=?, last_msg_at=NOW(3)
          WHERE conv_id=?`,
      [msgId, preview, cid]
    )
    // 对方 unread_count + 1 (Telegram 风格)
    await pool.query(
      `UPDATE im_members SET unread_count = unread_count + 1
        WHERE conv_id=? AND user_id<>?`,
      [cid, uid]
    )

    // 回包: 给客户端通过 temp_id 找到那条本地消息把状态改 sent
    res.json({ ok: true, data: { msg_id: msgId, temp_id: temp_id || null, status: 'sent' } })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- 编辑消息 ----------
router.post('/edit/:msg_id', auth, async (req, res) => {
  try {
    const msgId = parseInt(req.params.msg_id)
    const { body } = req.body || {}
    if (!body) return res.json({ ok: false, error: '编辑内容不能为空' })
    const [rows] = await pool.query(
      'SELECT sender_id, conv_id FROM im_messages WHERE id=? AND deleted_at IS NULL',
      [msgId]
    )
    if (rows.length === 0) return res.status(404).json({ ok: false, error: '消息不存在' })
    if (rows[0].sender_id !== req.ssUserId) return res.status(403).json({ ok: false, error: '只能编辑自己的消息' })

    await pool.query(
      'UPDATE im_messages SET body=?, edited_at=NOW() WHERE id=?',
      [body, msgId]
    )
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- 撤回/删除 ----------
router.post('/delete/:msg_id', auth, async (req, res) => {
  try {
    const msgId = parseInt(req.params.msg_id)
    const [rows] = await pool.query(
      'SELECT sender_id FROM im_messages WHERE id=?',
      [msgId]
    )
    if (rows.length === 0) return res.status(404).json({ ok: false, error: '消息不存在' })
    if (rows[0].sender_id !== req.ssUserId) return res.status(403).json({ ok: false, error: '只能撤回自己的消息' })

    // Telegram 风格: 不真删行, 设 deleted_at, 内容置空
    await pool.query(
      `UPDATE im_messages
          SET deleted_at=NOW(), body=NULL, media_url=NULL, media_type='', status='deleted'
          WHERE id=?`,
      [msgId]
    )
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- 标记已读 ----------
router.post('/read', auth, async (req, res) => {
  try {
    const { conv_id, msg_id } = req.body || {}
    if (!conv_id || !msg_id) return res.json({ ok: false, error: 'conv_id + msg_id 必填' })
    if (!(await assertMember(conv_id, req.ssUserId))) {
      return res.status(403).json({ ok: false, error: '不是会话成员' })
    }

    const conn = await pool.getConnection()
    try {
      await conn.beginTransaction()
      // 更新 last_read_msg_id
      await conn.query(
        `UPDATE im_members
            SET last_read_msg_id = GREATEST(last_read_msg_id, ?),
                unread_count = 0
            WHERE conv_id=? AND user_id=?`,
        [parseInt(msg_id), conv_id, req.ssUserId]
      )
      // 写 per-message 回执 (Telegram: "x 已读")
      // 仅写"超过上次 last_read 的消息"
      const [members] = await conn.query(
        'SELECT last_read_msg_id FROM im_members WHERE conv_id=? AND user_id=?',
        [conv_id, req.ssUserId]
      )
      const prev = members[0]?.last_read_msg_id || 0
      if (prev < parseInt(msg_id)) {
        // 找到 prev..msg_id 之间 conv 里其他人发的消息，写 receipt
        await conn.query(
          `INSERT IGNORE INTO im_msg_receipts (msg_id, user_id)
             SELECT id, ? FROM im_messages
              WHERE conv_id=? AND id > ? AND id <= ? AND sender_id <> ?`,
          [req.ssUserId, conv_id, prev, parseInt(msg_id), req.ssUserId]
        )
      }
      await conn.commit()
      res.json({ ok: true })
    } catch (e) {
      await conn.rollback()
      throw e
    } finally {
      conn.release()
    }
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- delta 拉取 (代替 WebSocket 的轮询兜底) ----------
// 客户端记录 last_seq, 每秒/几秒 poll 一次, 拿到 seq > last_seq 的新消息
// 比 WebSocket 简单, SGP 单机够用, 后续如需实时可上 SSE / WS
router.get('/delta', auth, async (req, res) => {
  try {
    const since = parseInt(req.query.since_seq || '0')
    const limit = Math.min(parseInt(req.query.limit || '100'), 500)
    const [rows] = await pool.query(
      `SELECT m.id, m.conv_id, m.sender_id, m.body, m.media_url, m.media_type,
              m.reply_to_msg_id, m.status, m.edited_at, m.deleted_at,
              m.global_seq, m.created_at,
              u.username AS sender_username, u.display_name AS sender_name, u.avatar AS sender_avatar
         FROM im_messages m
         LEFT JOIN smart_studio_users u ON u.id = m.sender_id
         JOIN im_members mem ON mem.conv_id = m.conv_id AND mem.user_id=?
         WHERE m.global_seq > ?
         ORDER BY m.global_seq ASC
         LIMIT ?`,
      [req.ssUserId, since, limit]
    )
    const newest = rows.length > 0 ? rows[rows.length - 1].global_seq : since
    res.json({ ok: true, data: rows, newest_seq: newest })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- 全局 global_seq (供前端初始化用) ----------
router.get('/global-seq', auth, async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT COALESCE(MAX(id), 0) AS seq FROM im_global_seq')
    res.json({ ok: true, data: { seq: rows[0].seq } })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

export default router
