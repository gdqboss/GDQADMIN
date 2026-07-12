// 智慧工作室 API - 隐私聊天室
import { Router } from 'express'
import 'dotenv/config'
import { createHash, randomBytes } from 'crypto'
import jwt from 'jsonwebtoken'
import bcrypt from 'bcryptjs'
import multer from 'multer'
import path from 'path'
import { fileURLToPath } from 'url'
import fs from 'fs'
import { pool } from '../db/connection.js'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const router = Router()

const JWT_SECRET=process.env.SMART_STUDIO_JWT_SECRET || 'ss-default-secret-change-me-2026'
const JWT_TTL = '7d'
const ADMIN_KEY = process.env.SMART_STUDIO_ADMIN_KEY || 'ss-admin-key-change-me'
const UPLOAD_DIR = '/home/gdq/server/uploads/smart-studio'
const PUBLIC_BASE = process.env.SMART_STUDIO_PUBLIC_BASE || '/smart-studio/uploads'

if (!fs.existsSync(UPLOAD_DIR)) fs.mkdirSync(UPLOAD_DIR, { recursive: true })

// ---------- multer ----------
const storage = multer.diskStorage({
  destination: (_req, _file, cb) => cb(null, UPLOAD_DIR),
  filename: (_req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase().slice(0, 8) || '.jpg'
    const safe = /^\.(jpg|jpeg|png|gif|webp)$/i.test(ext) ? ext : '.jpg'
    cb(null, randomBytes(16).toString('hex') + safe)
  }
})
const upload = multer({
  storage,
  limits: { fileSize: 8 * 1024 * 1024 },
  fileFilter: (_req, file, cb) => {
    if (/^image\//.test(file.mimetype)) cb(null, true)
    else cb(new Error('仅支持图片'))
  }
})

// ---------- auth middleware ----------
function auth(req, res, next) {
  const h = req.headers.authorization || ''
  const token = h.startsWith('Bearer ') ? h.slice(7) : null
  if (!token) return res.status(401).json({ ok: false, error: '未登录' })
  try {
    const decoded = jwt.verify(token, JWT_SECRET)
    req.userId = decoded.uid
    next()
  } catch (e) {
    return res.status(401).json({ ok: false, error: 'token 无效' })
  }
}

async function loadUser(userId) {
  const [rows] = await pool.query(
    'SELECT id, username, display_name, avatar, is_active FROM smart_studio_users WHERE id=?',
    [userId]
  )
  return rows[0] || null
}

// ---------- login / me ----------
router.post('/login', async (req, res) => {
  try {
    const { username, password } = req.body || {}
    if (!username || !password) return res.json({ ok: false, error: '账号密码必填' })
    const [rows] = await pool.query(
      'SELECT * FROM smart_studio_users WHERE username=? LIMIT 1',
      [username.trim()]
    )
    const u = rows[0]
    if (!u) return res.json({ ok: false, error: '账号不存在' })
    if (!u.is_active) return res.json({ ok: false, error: '账号已停用' })
    const ok = await bcrypt.compare(password, u.password_hash)
    if (!ok) return res.json({ ok: false, error: '密码错误' })
    await pool.query('UPDATE smart_studio_users SET last_login_at=NOW() WHERE id=?', [u.id])
    const token = jwt.sign({ uid: u.id }, JWT_SECRET, { expiresIn: JWT_TTL })
    res.json({
      ok: true,
      token,
      user: {
        id: u.id, username: u.username,
        display_name: u.display_name, avatar: u.avatar
      }
    })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.get('/me', auth, async (req, res) => {
  const u = await loadUser(req.userId)
  if (!u) return res.status(401).json({ ok: false, error: '用户不存在' })
  res.json({ ok: true, user: u })
})

router.post('/change-password', auth, async (req, res) => {
  try {
    const { old_password, new_password } = req.body || {}
    if (!old_password || !new_password || new_password.length < 6) {
      return res.json({ ok: false, error: '新密码至少 6 位' })
    }
    const [rows] = await pool.query(
      'SELECT password_hash FROM smart_studio_users WHERE id=?', [req.userId]
    )
    const u = rows[0]
    if (!u) return res.json({ ok: false, error: '用户不存在' })
    const ok = await bcrypt.compare(old_password, u.password_hash)
    if (!ok) return res.json({ ok: false, error: '原密码错误' })
    const hash = await bcrypt.hash(new_password, 10)
    await pool.query('UPDATE smart_studio_users SET password_hash=? WHERE id=?', [hash, req.userId])
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- friends ----------
router.get('/friends', auth, async (req, res) => {
  try {
    const me = req.userId
    const [rows] = await pool.query(
      `SELECT f.id AS fid, f.status, f.requester_id, f.addressee_id, f.created_at,
              u.id AS uid, u.username, u.display_name, u.avatar
       FROM smart_studio_friendships f
       JOIN smart_studio_users u
         ON u.id = IF(f.requester_id=?, f.addressee_id, f.requester_id)
       WHERE (f.requester_id=? OR f.addressee_id=?)
       ORDER BY f.created_at DESC`,
      [me, me, me]
    )
    const friends = [], pending_in = [], pending_out = []
    for (const r of rows) {
      const item = {
        friendship_id: r.fid,
        user: { id: r.uid, username: r.username, display_name: r.display_name, avatar: r.avatar },
        created_at: r.created_at
      }
      if (r.status === 'accepted') friends.push(item)
      else if (r.status === 'pending') {
        if (r.addressee_id === me) pending_in.push(item)
        else pending_out.push(item)
      }
    }
    res.json({ ok: true, friends, pending_in, pending_out })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.post('/friends/request', auth, async (req, res) => {
  try {
    const me = req.userId
    const { username } = req.body || {}
    if (!username) return res.json({ ok: false, error: '请输入对方账号' })
    const [rows] = await pool.query(
      'SELECT id, username, display_name, avatar FROM smart_studio_users WHERE username=?',
      [username.trim()]
    )
    const target = rows[0]
    if (!target) return res.json({ ok: false, error: '账号不存在' })
    if (target.id === me) return res.json({ ok: false, error: '不能加自己' })
    // 检查已有关系（双向都算）
    const [existing] = await pool.query(
      `SELECT id, status, requester_id, addressee_id FROM smart_studio_friendships
       WHERE (requester_id=? AND addressee_id=?)
          OR (requester_id=? AND addressee_id=?)`,
      [me, target.id, target.id, me]
    )
    if (existing.length) {
      const ex = existing[0]
      if (ex.status === 'accepted') return res.json({ ok: false, error: '已是好友' })
      if (ex.status === 'pending') {
        if (ex.requester_id === me) return res.json({ ok: false, error: '已发送过申请' })
        // 对方曾发过申请给我 → 直接通过
        await pool.query(
          `UPDATE smart_studio_friendships SET status='accepted', responded_at=NOW()
           WHERE id=?`, [ex.id]
        )
        return res.json({ ok: true, auto_accepted: true })
      }
      // rejected 状态允许重新申请
      await pool.query(
        `UPDATE smart_studio_friendships SET requester_id=?, addressee_id=?, status='pending',
                created_at=NOW(), responded_at=NULL WHERE id=?`,
        [me, target.id, ex.id]
      )
      return res.json({ ok: true })
    }
    await pool.query(
      `INSERT INTO smart_studio_friendships (requester_id, addressee_id, status)
       VALUES (?, ?, 'pending')`,
      [me, target.id]
    )
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.post('/friends/respond', auth, async (req, res) => {
  try {
    const me = req.userId
    const { friendship_id, action } = req.body || {}
    if (!friendship_id || !['accept', 'reject'].includes(action)) {
      return res.json({ ok: false, error: '参数错误' })
    }
    const [rows] = await pool.query(
      'SELECT id, addressee_id, status FROM smart_studio_friendships WHERE id=?',
      [friendship_id]
    )
    const f = rows[0]
    if (!f) return res.json({ ok: false, error: '申请不存在' })
    if (f.addressee_id !== me) return res.json({ ok: false, error: '无权操作' })
    if (f.status !== 'pending') return res.json({ ok: false, error: '已处理' })
    const newStatus = action === 'accept' ? 'accepted' : 'rejected'
    await pool.query(
      'UPDATE smart_studio_friendships SET status=?, responded_at=NOW() WHERE id=?',
      [newStatus, friendship_id]
    )
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.post('/friends/remove', auth, async (req, res) => {
  try {
    const me = req.userId
    const { friendship_id } = req.body || {}
    const [r] = await pool.query(
      'DELETE FROM smart_studio_friendships WHERE id=? AND (requester_id=? OR addressee_id=?)',
      [friendship_id, me, me]
    )
    res.json({ ok: true, removed: r.affectedRows })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- rooms / messages ----------
// 获取或创建 DM 房间（要求双方是好友）
async function getOrCreateDmRoom(userA, userB) {
  // 找出现有 dm 房间，且成员恰好是 A+B
  const [rooms] = await pool.query(
    `SELECT r.id FROM smart_studio_rooms r
     WHERE r.room_type='dm'
       AND EXISTS (SELECT 1 FROM smart_studio_room_members WHERE room_id=r.id AND user_id=?)
       AND EXISTS (SELECT 1 FROM smart_studio_room_members WHERE room_id=r.id AND user_id=?)
       AND (SELECT COUNT(*) FROM smart_studio_room_members WHERE room_id=r.id) = 2`,
    [userA, userB]
  )
  if (rooms.length) return rooms[0].id
  const [r] = await pool.query(
    "INSERT INTO smart_studio_rooms (room_type) VALUES ('dm')"
  )
  const roomId = r.insertId
  await pool.query(
    'INSERT INTO smart_studio_room_members (room_id, user_id) VALUES (?,?), (?,?)',
    [roomId, userA, roomId, userB]
  )
  return roomId
}

async function ensureFriendship(uid1, uid2) {
  const [rows] = await pool.query(
    `SELECT id FROM smart_studio_friendships
     WHERE status='accepted'
       AND ((requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?))`,
    [uid1, uid2, uid2, uid1]
  )
  return rows.length > 0
}

router.post('/rooms/with/:friendUserId', auth, async (req, res) => {
  try {
    const me = req.userId
    const other = parseInt(req.params.friendUserId, 10)
    if (!other || other === me) return res.json({ ok: false, error: '参数错误' })
    const isFriend = await ensureFriendship(me, other)
    if (!isFriend) return res.json({ ok: false, error: '需要先加好友' })
    const roomId = await getOrCreateDmRoom(me, other)
    res.json({ ok: true, room_id: roomId })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.get('/rooms', auth, async (req, res) => {
  try {
    const me = req.userId
    const [rows] = await pool.query(
      `SELECT r.id AS room_id,
              m.last_read_msg_id,
              (SELECT MAX(id) FROM smart_studio_messages WHERE room_id=r.id) AS last_msg_id,
              u.id AS peer_id, u.username AS peer_username, u.display_name AS peer_name, u.avatar AS peer_avatar,
              (SELECT message_type FROM smart_studio_messages WHERE room_id=r.id ORDER BY id DESC LIMIT 1) AS last_type,
              (SELECT content FROM smart_studio_messages WHERE room_id=r.id ORDER BY id DESC LIMIT 1) AS last_content,
              (SELECT image_url FROM smart_studio_messages WHERE room_id=r.id ORDER BY id DESC LIMIT 1) AS last_image,
              (SELECT created_at FROM smart_studio_messages WHERE room_id=r.id ORDER BY id DESC LIMIT 1) AS last_time,
              (SELECT sender_id FROM smart_studio_messages WHERE room_id=r.id ORDER BY id DESC LIMIT 1) AS last_sender,
              (SELECT COUNT(*) FROM smart_studio_messages WHERE room_id=r.id AND id > m.last_read_msg_id AND sender_id<>?) AS unread
       FROM smart_studio_rooms r
       JOIN smart_studio_room_members m ON m.room_id=r.id AND m.user_id=?
       JOIN smart_studio_room_members m2 ON m2.room_id=r.id AND m2.user_id<>?
       JOIN smart_studio_users u ON u.id=m2.user_id
       ORDER BY last_time DESC`,
      [me, me, me]
    )
    const rooms = rows.map(r => ({
      room_id: r.room_id,
      peer: { id: r.peer_id, username: r.peer_username, display_name: r.peer_name, avatar: r.peer_avatar },
      last_message: r.last_msg_id ? {
        type: r.last_type, content: r.last_content, image_url: r.last_image,
        sender_id: r.last_sender, created_at: r.last_time
      } : null,
      unread: r.unread || 0
    }))
    res.json({ ok: true, rooms })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.get('/rooms/:roomId/messages', auth, async (req, res) => {
  try {
    const me = req.userId
    const roomId = parseInt(req.params.roomId, 10)
    const [check] = await pool.query(
      'SELECT 1 FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
      [roomId, me]
    )
    if (!check.length) return res.json({ ok: false, error: '无权访问' })
    const sinceId = parseInt(req.query.since_id || '0', 10)
    const limit = Math.min(parseInt(req.query.limit || '50', 10), 200)
    const [rows] = await pool.query(
      `SELECT id, sender_id, message_type, content, image_url, created_at
       FROM smart_studio_messages
       WHERE room_id=? AND id>?
       ORDER BY id ASC LIMIT ?`,
      [roomId, sinceId, limit]
    )
    // 标记已读
    if (rows.length) {
      const maxId = rows[rows.length - 1].id
      await pool.query(
        'UPDATE smart_studio_room_members SET last_read_msg_id=? WHERE room_id=? AND user_id=?',
        [maxId, roomId, me]
      )
    }
    res.json({ ok: true, messages: rows })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// 发送文本消息（纯 JSON，express.json 已解析 req.body）
router.post('/rooms/:roomId/send-text', auth, async (req, res) => {
  try {
    const me = req.userId
    const roomId = parseInt(req.params.roomId, 10)
    const [check] = await pool.query(
      'SELECT 1 FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
      [roomId, me]
    )
    if (!check.length) return res.json({ ok: false, error: '无权访问' })
    const { content } = req.body || {}
    if (!content || !String(content).trim()) return res.json({ ok: false, error: '消息为空' })
    const text = String(content).trim().slice(0, 4000)
    const [r] = await pool.query(
      `INSERT INTO smart_studio_messages (room_id, sender_id, message_type, content, image_url)
       VALUES (?, ?, 'text', ?, NULL)`,
      [roomId, me, text]
    )
    res.json({ ok: true, message: {
      id: r.insertId, room_id: roomId, sender_id: me,
      message_type: 'text', content: text, image_url: null,
      created_at: new Date().toISOString()
    }})
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// 发送图片消息（multipart/form-data，multer 处理）
router.post('/rooms/:roomId/send-image', auth, upload.single('image'), async (req, res) => {
  console.log('[smart-studio/send-image] roomId=' + req.params.roomId + ' userId=' + req.userId + ' hasFile=' + !!req.file)
  try {
    const me = req.userId
    const roomId = parseInt(req.params.roomId, 10)
    const [check] = await pool.query(
      'SELECT 1 FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
      [roomId, me]
    )
    if (!check.length) return res.json({ ok: false, error: '无权访问' })
    if (!req.file) return res.json({ ok: false, error: '未收到图片' })
    const imageUrl = PUBLIC_BASE + '/' + req.file.filename
    const text = (req.body.content || '').toString().trim()
    const [r] = await pool.query(
      `INSERT INTO smart_studio_messages (room_id, sender_id, message_type, content, image_url)
       VALUES (?, ?, 'image', ?, ?)`,
      [roomId, me, text, imageUrl]
    )
    res.json({ ok: true, message: {
      id: r.insertId, room_id: roomId, sender_id: me,
      message_type: 'image', content: text, image_url: imageUrl,
      created_at: new Date().toISOString()
    }})
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.post('/rooms/:roomId/send', auth, upload.single('image'), async (req, res) => {
  try {
    const me = req.userId
    const roomId = parseInt(req.params.roomId, 10)
    const [check] = await pool.query(
      'SELECT 1 FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
      [roomId, me]
    )
    if (!check.length) return res.json({ ok: false, error: '无权访问' })
    // multer 处理 multipart；JSON 请求下 req.body 已被 express.json 解析
    let body = req.body || {}
    let { message_type, content } = body
    let imageUrl = null
    if (req.file) {
      message_type = 'image'
      imageUrl = PUBLIC_BASE + '/' + req.file.filename
      content = content || ''
    } else {
      message_type = 'text'
      if (!content || !content.trim()) return res.json({ ok: false, error: '消息为空' })
      content = content.trim().slice(0, 4000)
    }
    const [r] = await pool.query(
      `INSERT INTO smart_studio_messages (room_id, sender_id, message_type, content, image_url)
       VALUES (?, ?, ?, ?, ?)`,
      [roomId, me, message_type, content, imageUrl]
    )
    res.json({ ok: true, message: {
      id: r.insertId, room_id: roomId, sender_id: me,
      message_type, content, image_url: imageUrl,
      created_at: new Date().toISOString()
    }})
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// 上传纯图片（不绑定消息，由前端组合 form-data）
router.post('/upload', auth, upload.single('image'), (req, res) => {
  try {
    if (!req.file) return res.json({ ok: false, error: '未收到文件' })
    res.json({ ok: true, url: PUBLIC_BASE + '/' + req.file.filename })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- admin (X-Admin-Key) ----------
function adminOnly(req, res, next) {
  if ((req.headers['x-admin-key'] || '') !== ADMIN_KEY) {
    return res.status(403).json({ ok: false, error: 'admin key 错误' })
  }
  next()
}

router.post('/admin/users', adminOnly, async (req, res) => {
  try {
    const { username, password, display_name, avatar } = req.body || {}
    if (!username || !password || !display_name) {
      return res.json({ ok: false, error: 'username/password/display_name 必填' })
    }
    if (password.length < 6) return res.json({ ok: false, error: '密码至少 6 位' })
    const hash = await bcrypt.hash(password, 10)
    try {
      const [r] = await pool.query(
        `INSERT INTO smart_studio_users (username, password_hash, display_name, avatar)
         VALUES (?, ?, ?, ?)`,
        [username.trim(), hash, display_name, avatar || null]
      )
      res.json({ ok: true, id: r.insertId })
    } catch (e) {
      if (e.code === 'ER_DUP_ENTRY') return res.json({ ok: false, error: '账号已存在' })
      throw e
    }
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.get('/admin/users', adminOnly, async (_req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, username, display_name, avatar, is_active, created_at, last_login_at
       FROM smart_studio_users ORDER BY id DESC`
    )
    res.json({ ok: true, users: rows })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.post('/admin/users/:id/disable', adminOnly, async (req, res) => {
  try {
    await pool.query(
      'UPDATE smart_studio_users SET is_active=? WHERE id=?',
      [req.body?.is_active === false ? 0 : 1, req.params.id]
    )
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.post('/admin/users/:id/reset-password', adminOnly, async (req, res) => {
  try {
    const { new_password } = req.body || {}
    if (!new_password || new_password.length < 6) {
      return res.json({ ok: false, error: '新密码至少 6 位' })
    }
    const hash = await bcrypt.hash(new_password, 10)
    await pool.query(
      'UPDATE smart_studio_users SET password_hash=? WHERE id=?',
      [hash, req.params.id]
    )
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

export default router
