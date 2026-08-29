import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { requirePermission } from '../middleware/rbac.js'

const router = Router()

// GET /api/workbuddy/wecom/unread - 企微未读消息（按会话汇总 is_self=0 的消息）
router.get('/wecom/unread', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const limit = Math.min(Number(req.query.limit) || 20, 50)
    // 未读数优先取会话表 unread>0，其次未读消息数
    const [convs] = await pool.query(`
      SELECT c.id, c.name, c.type, c.last_message, c.last_time, c.unread,
             (SELECT COUNT(*) FROM wecom_messages m
               WHERE m.conversation_id=c.id AND m.is_self=0
                 AND m.created_at > COALESCE(c.last_time, m.created_at)) AS pending_msgs
      FROM wecom_conversations c
      ORDER BY COALESCE(c.unread,0) DESC, c.last_time DESC
      LIMIT ?
    `, [limit]).catch(() => [[]])
    // 全局最近未读消息（非自己发的，按时间倒序）
    const [msgs] = await pool.query(`
      SELECT m.id, m.conversation_id, c.name AS conversation, c.type AS conv_type,
             m.sender, m.content, m.created_at
      FROM wecom_messages m
        LEFT JOIN wecom_conversations c ON c.id = m.conversation_id
      WHERE m.is_self = 0
      ORDER BY m.created_at DESC LIMIT ?
    `, [limit]).catch(() => [[]])

    const unreadTotal = (convs||[]).reduce((s, c) => s + (Number(c.unread)||0) + (Number(c.pending_msgs)||0), 0)

    res.json({
      unread_total: unreadTotal,
      conversations: (convs||[]).map(c => ({
        id: c.id, name: c.name, type: c.type,
        last_message: c.last_message, last_time: c.last_time,
        unread: (Number(c.unread)||0) + (Number(c.pending_msgs)||0),
      })),
      recent_incoming: (msgs||[]).map(m => ({
        id: m.id, conversation_id: m.conversation_id, conversation: m.conversation || `#${m.conversation_id}`,
        conv_type: m.conv_type, sender: m.sender, content: m.content, created_at: m.created_at,
      })),
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/wecom/contacts - 企微联系人列表
router.get('/wecom/contacts', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const q = String(req.query.q || '').trim()
    const limit = Math.min(Number(req.query.limit) || 50, 100)
    let rows, count
    if (q) {
      const like = `%${q}%`
      rows = (await pool.query(`
        SELECT wecom_userid, name, position, mobile, email, status, synced_at
        FROM wecom_contacts
        WHERE name LIKE ? OR position LIKE ? OR mobile LIKE ?
        ORDER BY name LIMIT ?
      `, [like, like, like, limit]).catch(() => [[]]))[0] || []
      count = rows.length
    } else {
      rows = (await pool.query(`
        SELECT wecom_userid, name, position, mobile, email, status, synced_at
        FROM wecom_contacts ORDER BY name LIMIT ?
      `, [limit]).catch(() => [[]]))[0] || []
      count = rows.length
    }
    res.json({
      query: q || null,
      count,
      contacts: (rows||[]).map(u => ({
        wecom_userid: u.wecom_userid, name: u.name, position: u.position,
        mobile: u.mobile, email: u.email, status: u.status, synced_at: u.synced_at,
      })),
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/wecom/conversations?limit= - 最近会话列表
router.get('/wecom/conversations', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const limit = Math.min(Number(req.query.limit) || 20, 50)
    const [convs] = await pool.query(`
      SELECT c.id, c.external_id, c.name, c.type, c.last_message, c.last_time,
             c.unread, c.wecom_chat_id,
             (SELECT COUNT(*) FROM wecom_messages m WHERE m.conversation_id=c.id) AS msg_count
      FROM wecom_conversations c
      ORDER BY c.last_time DESC LIMIT ?
    `, [limit]).catch(() => [[]])
    res.json({
      count: (convs||[]).length,
      conversations: (convs||[]).map(c => ({
        id: c.id, name: c.name, type: c.type, external_id: c.external_id,
        wecom_chat_id: c.wecom_chat_id, last_message: c.last_message, last_time: c.last_time,
        unread: Number(c.unread)||0, message_count: Number(c.msg_count)||0,
      })),
    })
  } catch (err) { next(err) }
})

// GET /api/workbuddy/wecom/messages/:conversationId?limit= - 某会话消息记录
router.get('/wecom/messages/:conversationId', auth, requirePermission('workbuddy:read'), async (req, res, next) => {
  try {
    const convId = Number(req.params.conversationId)
    if (!convId) return res.status(400).json({ error: 'invalid conversationId' })
    const limit = Math.min(Number(req.query.limit) || 20, 100)
    const [[conv]] = await pool.query(`
      SELECT id, name, type, external_id FROM wecom_conversations WHERE id=?
    `, [convId]).catch(() => [[null]])
    if (!conv) return res.status(404).json({ error: 'conversation not found' })
    const [msgs] = await pool.query(`
      SELECT id, sender, is_self, content, created_at
      FROM wecom_messages WHERE conversation_id=? ORDER BY created_at DESC LIMIT ?
    `, [convId, limit]).catch(() => [[]])
    res.json({
      conversation: conv,
      message_count: (msgs||[]).length,
      messages: (msgs||[]).map(m => ({
        id: m.id, sender: m.sender, is_self: Boolean(m.is_self), content: m.content, created_at: m.created_at,
      })).reverse(),  // 按时间正序返回
    })
  } catch (err) { next(err) }
})

// POST /api/workbuddy/wecom/send - 发送企微消息
// body: { conversation_id?: number, conversation_name?: string, content: string, sender?: string }
router.post('/wecom/send', auth, requirePermission('workbuddy:write'), async (req, res, next) => {
  try {
    const { conversation_id, conversation_name, content, sender } = req.body || {}
    if (!content || !String(content).trim()) return res.status(400).json({ error: 'content required' })

    let convId = Number(conversation_id) || null
    // 如果没有 conversation_id，尝试按 name 找会话，找不到则新建
    if (!convId && conversation_name) {
      const [found] = await pool.query(`SELECT id FROM wecom_conversations WHERE name=? OR external_id=?`, [conversation_name, conversation_name]).catch(() => [[]])
      if (found && found.length > 0) {
        convId = found[0].id
      } else {
        const [ins] = await pool.query(`
          INSERT INTO wecom_conversations (name, type, last_message, last_time)
          VALUES (?, 'single', ?, NOW())
        `, [conversation_name, content])
        convId = ins.insertId
      }
    }
    if (!convId) return res.status(400).json({ error: 'conversation_id or conversation_name required' })

    const senderName = sender || '江清波'
    const [r] = await pool.query(`
      INSERT INTO wecom_messages (conversation_id, sender, is_self, content, created_at)
      VALUES (?, ?, 1, ?, NOW())
    `, [convId, senderName, content])
    // 更新会话 last_message
    await pool.query(`
      UPDATE wecom_conversations SET last_message=?, last_time=NOW() WHERE id=?
    `, [content, convId]).catch(()=>{})

    res.json({ ok: true, message_id: r.insertId, conversation_id: convId, sender: senderName, sent_at: new Date().toISOString() })
  } catch (err) { next(err) }
})

export default router