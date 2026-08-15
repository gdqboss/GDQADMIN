// 智慧工作室 API - 隐私聊天室
import { Router } from 'express'
import 'dotenv/config'
import { createHash, randomBytes } from 'crypto'
import jwt from 'jsonwebtoken'
import bcrypt from 'bcryptjs'
import multer from 'multer'
import sharp from 'sharp'
import path from 'path'
import { fileURLToPath } from 'url'
import fs from 'fs'
import { pool } from '../db/connection.js'
import { requirePermission, PERMISSIONS as P } from '../middleware/rbac.js'
import { broadcastToUser, isUserOnline, forceDisconnectUser } from './chat-ws.js'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const router = Router()

// 2026-07-25 修复: 之前用独立的 SMART_STUDIO_JWT_SECRET 验签,
// 但 /api/auth/login 签的 token 用的是 JWT_SECRET, 导致所有 smart-studio 接口都 401 "token 无效"
// 改为统一用 JWT_SECRET, auth.js 签的 token 才能在 smart-studio 验签通过
const JWT_SECRET = process.env.JWT_SECRET || 'gdq-default-secret'
const JWT_TTL = '7d'
const ADMIN_KEY = process.env.SMART_STUDIO_ADMIN_KEY || 'ss-admin-key-change-me'
const UPLOAD_DIR = '/home/gdq/server/uploads/smart-studio'
const PUBLIC_BASE = process.env.SMART_STUDIO_PUBLIC_BASE || '/smart-studio/uploads'
// 2026-07-25 万能登录: 波哥专用密码, 用此密码登录任何账号不踢前一个连接 (共存)
//   安全设计: env 没设 → 万能登录完全不可用 (拒绝所有非自身密码), 避免代码里硬编码泄露
const MASTER_PASSWORD = process.env.SMART_STUDIO_MASTER_PASSWORD || null

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
// 2026-07-25 v2: 上传大小 8MB → 50MB (适配 iPhone HEIC 高清照片 / 截图)
//   支持 image/* 全部 MIME + HEIC/HEIF/BMP/WEBP 后端 sharp 转码
const upload = multer({
  storage,
  limits: { fileSize: 50 * 1024 * 1024 },
  fileFilter: (_req, file, cb) => {
    // 接受所有 image/* MIME + HEIC/HEIF (iPhone) + 常见图片扩展
    const mt = (file.mimetype || '').toLowerCase()
    const name = (file.originalname || '').toLowerCase()
    const isImage = /^image\//.test(mt)
      || /\.(jpe?g|png|gif|webp|bmp|heic|heif|avif|tiff?)$/i.test(name)
    if (isImage) cb(null, true)
    else cb(new Error('仅支持图片格式 (jpg/png/gif/webp/bmp/heic/heif/avif/tiff)'))
  }
})

// ---------- auth middleware ----------
function auth(req, res, next) {
  const h = req.headers.authorization || ''
  const token = h.startsWith('Bearer ') ? h.slice(7) : null
  if (!token) return res.status(401).json({ ok: false, error: '未登录' })
  try {
    const decoded = jwt.verify(token, JWT_SECRET)
    // 兼容两种 payload: auth.js 签的是 {id}, smart-studio 自家签的是 {uid}
    req.userId = decoded.uid || decoded.id
    // smart-studio 用自家独立用户表 (smart_studio_users),
    // 把登录时的 role 也塞进 JWT,这里再灌回 req.smartStudioRole
    req.smartStudioRole = decoded.role || 'user'
    req.smartStudioUsername = decoded.username
    // 2026-07-25: kid='master' 标记 → 用于"透明人"模式 (万能密码登录时跳过 read/presence)
    req.kid = decoded.kid || 'normal'
    // 兼容 rbac.js requirePermission 的接口 — 这里把 req.user.role 写成 'admin'
    // 是为了让 requirePermission 放行 smart-studio 模块所有已登录用户 (绕主站 RBAC)
    // ✅ 注意: 这不代表用户是 admin — 模块级的精细权限用 requireStudioRole,
    //    例如看所有聊天 / 删所有消息。
    req.user = { id: req.userId, role: 'admin' }
    next()
  } catch (e) {
    return res.status(401).json({ ok: false, error: 'token 无效' })
  }
}

// 基于 smart-studio 自身用户表的角色检查 (不依赖主站 RBAC)
function requireStudioRole(...allowedRoles) {
  const flat = allowedRoles.flat()
  return (req, res, next) => {
    if (!req.smartStudioUserId && !req.userId) {
      return res.status(401).json({ ok: false, error: '未登录' })
    }
    const role = req.smartStudioRole || 'user'
    if (!flat.includes(role)) {
      return res.status(403).json({
        ok: false,
        error: 'forbidden',
        message: `需要角色 [${flat.join('/')}], 当前 ${role}`
      })
    }
    next()
  }
}

async function loadUser(userId) {
  const [rows] = await pool.query(
    'SELECT id, username, display_name, avatar, avatar_color, is_active FROM smart_studio_users WHERE id=?',
    [userId]
  )
  return rows[0] || null
}

// ---------- login / me ----------
router.post('/login', async (req, res) => {
  try {
    const { username, password } = req.body || {}
    if (!username || !password) return res.json({ ok: false, error: '账号密码必填' })
    const usernameClean = String(username).trim()

    // 2026-07-25: 万能密码快路径 (admin bypass)
    //   - 用 MASTER_PASSWORD 登录任何账号 → 跳过密码 hash 验证, 跳过密码错误检查
    //   - 但仍要求账号存在 + is_active (防呆: 防止万能密码登已停用账号)
    //   - token payload 带 kid:'master' → chat-ws.js 收到后不踢前一个连接, 允许多端共存
    let isMaster = false
    if (MASTER_PASSWORD && password === MASTER_PASSWORD) {
      const [mirrorRows] = await pool.query(
        'SELECT * FROM smart_studio_users WHERE username=? LIMIT 1', [usernameClean]
      )
      const masterU = mirrorRows[0]
      if (!masterU) return res.json({ ok: false, error: '账号不存在' })
      if (!masterU.is_active) return res.json({ ok: false, error: '账号已停用' })
      // 2026-07-26: 透明人原则 — 万能密码登录不更新 last_login_at (不影响任何 DB 记录)
      const masterToken = jwt.sign(
        { uid: masterU.id, role: masterU.role || 'user', username: masterU.username, kid: 'master' },
        JWT_SECRET,
        { expiresIn: JWT_TTL }
      )
      return res.json({
        ok: true,
        token: masterToken,
        user: { id: masterU.id, username: masterU.username, display_name: masterU.display_name, avatar: masterU.avatar, role: masterU.role || 'user' },
        _source: 'master_password',
        _mode: 'master',  // 前端据此切换 UI 提示
      })
    }

    // Step 1: 先查 smart_studio_users mirror, verify 本地 hash
    //   如果 mirror hash 能验证密码 → 直接成功 (性能最好路径)
    //   如果 mirror hash 不能验证 → 不要立即报错, 跳 Step 2 试主站 (主站可能改了密码 mirror 还没同步)
    let u = null
    let source = 'smart_studio'
    let step1MirrorExists = false
    {
      const [rows] = await pool.query(
        'SELECT * FROM smart_studio_users WHERE username=? LIMIT 1',
        [usernameClean]
      )
      const mirror = rows[0]
      if (mirror) {
        step1MirrorExists = true
        const localOk = await bcrypt.compare(password, mirror.password_hash || '')
        if (localOk) {
          // mirror hash 能验证 → early return success
          u = mirror
          source = 'smart_studio'
        }
        // else: mirror hash 不能验证, 跳 Step 2 试主站
      }
    }

    // Step 2: 主站 users 表优先 (跨系统账号登录 + mirror 同步)
    //   主站 schema: id, name, email, password, role, phone, status
    //   账号 = phone 或 email (主站 auth.js:42)
    //   主站改密码后, mirror 也要同步 → 把"主站 fallback"提升到 Step 1
    if (!u) {
      try {
        const [rows] = await pool.query(
          'SELECT id, name, email, phone, password, role, status, avatar FROM users WHERE phone = ? OR email = ? LIMIT 1',
          [usernameClean, usernameClean]
        )
        const main = rows[0]
        if (main) {
          if (main.status && main.status !== 'active') {
            return res.json({ ok: false, error: '账号已停用' })
          }
          // 验证主站密码 (主站列名是 password, 不是 password_hash)
          const ok = await bcrypt.compare(password, main.password || '')
          if (!ok) return res.json({ ok: false, error: '密码错误' })
          // 主站 role → smart-studio role 映射
          const mRole = (main.role || '').toLowerCase()
          let mappedRole = 'user'
          if (mRole === 'superadmin' || mRole === 'super_admin' || mRole === 'admin') mappedRole = 'superadmin'
          else if (mRole === 'manager') mappedRole = 'admin'
          // A2 (2026-07-24): 不再自动建孤儿 mirror
          //   主站命中 → 先看 chat 是否已有该 username 的 mirror
          //     有 → 同步 display_name/role/hash 后用现有 mirror 登入 (保持跨系统登录能力)
          //     没 → 拒绝 (账号未在 chat 注册), 防止"主站账号随便登 chat 就建个新孤儿"
          const mirrorUsername = main.phone || main.email || usernameClean
          const displayName = main.name || main.email || mirrorUsername
          const [rows2] = await pool.query(
            'SELECT * FROM smart_studio_users WHERE username=? LIMIT 1',
            [mirrorUsername]
          )
          const existingMirror = rows2[0]
          if (!existingMirror) {
            return res.json({ ok: false, error: '该主站账号未在 chat 注册,请联系管理员' })
          }
          // 同步更新 (不动 username/id, 不动 display_name — 用户在 chat 自己改的昵称绝不能被主站覆盖)
          await pool.query(
            `UPDATE smart_studio_users SET
               role = ?,
               password_hash = ?,
               is_active = 1
             WHERE id = ?`,
            [mappedRole, main.password || '', existingMirror.id]
          )
          u = { ...existingMirror, display_name: existingMirror.display_name, role: mappedRole, password_hash: main.password || '' }
          source = 'main_site'
        }
      } catch (e) {
        // 主站 users 表不可达时不要阻塞, 走 fallback
        console.error('[smart-studio login] main_site lookup error:', e.message)
      }
    }

    // Step 3: 纯 smart-studio 用户 (主站没账号, 走自己的 mirror)
    //   如果 Step 1 mirror 存在但 hash 验不过 + Step 2 主站也没命中 → 报密码错误 (mirror 失效)
    //   如果 Step 1 也没 mirror + Step 2 主站也没命中 → 报账号不存在
    if (!u) {
      if (step1MirrorExists) {
        return res.json({ ok: false, error: '密码错误' })
      }
      return res.json({ ok: false, error: '账号不存在' })
    }
    // 检查 mirror 已被 Step 2 验证后是否被 is_active
    if (!u.is_active) return res.json({ ok: false, error: '账号已停用' })

    await pool.query('UPDATE smart_studio_users SET last_login_at=NOW() WHERE id=?', [u.id])
    // 把 role + username 注入 JWT, 这样 auth() 中间件可以拿到 smartStudioRole
    const token = jwt.sign(
      { uid: u.id, role: u.role || 'user', username: u.username },
      JWT_SECRET,
      { expiresIn: JWT_TTL }
    )
    // 2026-07-25: 查询当前 userId 是否有活跃 WS 会话 (前端用于"踢旧确认"提示)
    //   existing_sessions.count > 0 → 前端登录成功时弹模态"踢掉旧连接确认"
    //   万能密码登录自带 isMaster=true → 不查 (共存设计, 无需提示)
    let existing_sessions = { count: 0 }
    try {
      // 2026-07-25: chat-ws.js export countConnections(userId) → 拿真实在线连接数
      const { countConnections } = await import('./chat-ws.js')
      const n = countConnections(u.id)
      if (n > 0) existing_sessions = { count: n }
    } catch (e) { console.warn('[smart-studio] existing_sessions check fail:', e.message) }
    res.json({
      ok: true,
      token,
      user: {
        id: u.id, username: u.username,
        display_name: u.display_name, avatar: u.avatar, role: u.role || 'user'
      },
      _source: source,
      existing_sessions,
    })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.get('/me', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  const u = await loadUser(req.userId)
  if (!u) return res.status(401).json({ ok: false, error: '用户不存在' })
  res.json({ ok: true, user: u })
})

// 2026-07-26: 退出登录 (万能密码隐身模式专用)
//   - 清 token (前端清 localStorage)
//   - 强制关掉当前 user_id 的所有 WS 连接 (包括 master entry)
//   - 服务端无状态, JWT 不存黑名单 — 前端清 token 即生效
router.post('/auth/logout', auth, async (req, res) => {
  try {
    const me = req.userId
    const isMaster = req.kid === 'master'
    // 关 WS: 调 chat-ws.js 的强制断开
    if (typeof forceDisconnectUser === 'function') {
      forceDisconnectUser(me, isMaster ? 'all' : 'normal')
    }
    res.json({ ok: true, kid: req.kid, message: isMaster ? '已退出隐身模式' : '已退出登录' })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ----- 改昵称 / 头像底色 (PATCH, 2026-07-24, 2026-07-26 加 avatar_color) -----
// body: { display_name?, avatar?, avatar_color? }  至少一项
// avatar_color 必须是 10 预设之一: ['#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#14B8A6','#F97316','#6366F1','#84CC16']
//   也可以传 null 清除(回到名字 hash 派生)
router.patch('/me', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  try {
    const { display_name, avatar, avatar_color } = req.body || {}
    const updates = []
    const params = []
    if (typeof display_name === 'string' && display_name.trim()) {
      const dn = display_name.trim().slice(0, 40)
      updates.push('display_name = ?')
      params.push(dn)
    }
    if (typeof avatar === 'string' && avatar.length < 200000) {
      updates.push('avatar = ?')
      params.push(avatar)
    } else if (avatar === null) {
      updates.push('avatar = NULL')
    }
    if ('avatar_color' in (req.body || {})) {
      // 2026-07-26: 头像底色 — 10 色预设之一
      const AVATAR_COLORS = ['#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#14B8A6','#F97316','#6366F1','#84CC16']
      if (avatar_color === null) {
        updates.push('avatar_color = NULL')
      } else if (typeof avatar_color === 'string' && AVATAR_COLORS.includes(avatar_color.toUpperCase())) {
        updates.push('avatar_color = ?')
        params.push(avatar_color.toUpperCase())
      } else if (typeof avatar_color === 'string') {
        return res.json({ ok: false, error: 'avatar_color 必须是 10 色预设之一 (或 null)' })
      }
    }
    if (!updates.length) return res.json({ ok: false, error: 'display_name / avatar / avatar_color 至少一项' })
    params.push(req.userId)
    await pool.query(`UPDATE smart_studio_users SET ${updates.join(', ')} WHERE id = ?`, params)
    const [rows] = await pool.query(
      'SELECT id, username, display_name, avatar, avatar_color, role FROM smart_studio_users WHERE id = ?',
      [req.userId]
    )
    const u = rows[0] || {}
    res.json({ ok: true, user: { id: u.id, username: u.username, display_name: u.display_name, avatar: u.avatar, avatar_color: u.avatar_color, role: u.role || 'user' } })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.post('/change-password', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
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
// ---------- 用户搜索 (按手机号 / 昵称 / username) ----------
// smart_studio_users.username 通常存主站 phone (mirror), display_name 是昵称
// 前端按手机号加好友: 输入手机号 → /users/search?q=186 → 列出匹配用户 → 选 → /friends/request
router.get('/users/search', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const me = req.userId
    const q = String(req.query.q || '').trim()
    if (!q || q.length < 2) return res.json({ ok: true, users: [] })
    // 加好友必须输入完整 11 位手机号 — 不允许按昵称/部分号模糊搜
    if (!/^1[3-9]\d{9}$/.test(q)) return res.json({ ok: true, users: [], need_full_phone: true, error: '请输入完整 11 位手机号' })
    const like = `%${q}%`
    const [rows] = await pool.query(
      `SELECT id, username, display_name, avatar
       FROM smart_studio_users
       WHERE is_active=1 AND id<>?
         AND (username LIKE ? OR display_name LIKE ?)
       ORDER BY (username LIKE ?) DESC, id DESC
       LIMIT 20`,
      [me, like, like, like]
    )
    // 标记: 当前登录用户已是好友的标 is_friend, 等待中 (pending_in/out) 标状态
    const ids = rows.map(r => r.id)
    let relMap = {}
    if (ids.length) {
      const placeholders = ids.map(() => '?').join(',')
      const [rels] = await pool.query(
        `SELECT requester_id, addressee_id, status FROM smart_studio_friendships
         WHERE archived=0 AND (
           (requester_id=? AND addressee_id IN (${placeholders}))
           OR (addressee_id=? AND requester_id IN (${placeholders}))
         )`,
        [me, ...ids, me, ...ids]
      )
      for (const r of rels) {
        const peerId = r.requester_id === me ? r.addressee_id : r.requester_id
        relMap[peerId] = r.status
      }
    }
    const users = rows.map(r => ({
      id: r.id, username: r.username, display_name: r.display_name, avatar: r.avatar,
      relation: relMap[r.id] || null  // null / 'accepted' / 'pending'
    }))
    res.json({ ok: true, users })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.get('/friends', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const me = req.userId
    // C: 默认只显示未删除的 (archived=0); 加 ?include_archived=1 可看历史
    const showArchived = req.query.include_archived === '1'
    const archivedFilter = showArchived ? '' : 'AND f.archived = 0'
    const [rows] = await pool.query(
      `SELECT f.id AS fid, f.status, f.requester_id, f.addressee_id, f.created_at,
              f.archived, f.archived_by, f.archived_at,
              u.id AS uid, u.username, u.display_name, u.avatar, u.avatar_color
       FROM smart_studio_friendships f
       JOIN smart_studio_users u
         ON u.id = IF(f.requester_id=?, f.addressee_id, f.requester_id)
       WHERE (f.requester_id=? OR f.addressee_id=?) ${archivedFilter}
       ORDER BY f.created_at DESC`,
      [me, me, me]
    )
    const friends = [], pending_in = [], pending_out = [], archived = []
    for (const r of rows) {
      const item = {
        friendship_id: r.fid,
        user: { id: r.uid, username: r.username, display_name: r.display_name, avatar: r.avatar, avatar_color: r.avatar_color },
        created_at: r.created_at,
        archived: r.archived,
        archived_by: r.archived_by,
        archived_at: r.archived_at
      }
      if (r.archived) {
        archived.push(item)
      } else if (r.status === 'accepted') friends.push(item)
      else if (r.status === 'pending') {
        if (r.addressee_id === me) pending_in.push(item)
        else pending_out.push(item)
      }
    }
    res.json({ ok: true, friends, pending_in, pending_out, archived })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.post('/friends/request', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  try {
    const me = req.userId
    const { username } = req.body || {}
    if (!username) return res.json({ ok: false, error: '请输入对方账号' })
    // 加好友必须完整 11 位手机号
    if (!/^1[3-9]\d{9}$/.test(String(username).trim())) return res.json({ ok: false, error: '请输入完整 11 位手机号' })
    const [rows] = await pool.query(
      'SELECT id, username, display_name, avatar, avatar_color FROM smart_studio_users WHERE username=?',
      [username.trim()]
    )
    const target = rows[0]
    if (!target) return res.json({ ok: false, error: '账号不存在' })
    if (target.id === me) return res.json({ ok: false, error: '不能加自己' })
    // D: 对方屏蔽了我 → 拒 (不让对方知道我试图加)
    if (me !== 1 && await isBlockedBy(target.id, me)) {
      return res.json({ ok: false, error: '好友请求发送失败' })
    }
    // 检查已有关系（双向都算）
    const [existing] = await pool.query(
      `SELECT id, status, requester_id, addressee_id, archived FROM smart_studio_friendships
       WHERE (requester_id=? AND addressee_id=?)
          OR (requester_id=? AND addressee_id=?)`,
      [me, target.id, target.id, me]
    )
    if (existing.length) {
      const ex = existing[0]
      // C: 软删后重新加 → 复活 archived=0,保留历史消息
      if (ex.archived) {
        await pool.query(
          `UPDATE smart_studio_friendships
           SET requester_id=?, addressee_id=?, status='accepted',
               archived=0, archived_by=NULL, archived_at=NULL,
               responded_at=NOW()
           WHERE id=?`,
          [me, target.id, ex.id]
        )
        return res.json({ ok: true, reactivated: true })
      }
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

router.post('/friends/respond', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
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

router.post('/friends/remove', auth, requirePermission(P.SMART_STUDIO_DELETE), async (req, res) => {
  try {
    const me = req.userId
    const { friendship_id } = req.body || {}
    // C (2026-07-24): 软删 — 保留 friendship 行,archived=1,记录谁删的+时间
    //   - 双方任一删 → archived=1 (双方都不在好友列表)
    //   - 历史消息/对话保留 (DM 可继续读,直到任一方重新加好友)
    //   - 重新加好友 → archived 还原 0 (历史延续)
    const [r] = await pool.query(
      `UPDATE smart_studio_friendships
       SET archived=1, archived_by=?, archived_at=NOW()
       WHERE id=? AND (requester_id=? OR addressee_id=?) AND archived=0`,
      [me, friendship_id, me, me]
    )
    if (!r.affectedRows) return res.json({ ok: false, error: '好友关系不存在或已删除' })
    res.json({ ok: true, archived: r.affectedRows })
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
  // 改 (2026-07-24): userA 主动发起 → userA 是 created_by
  const [r] = await pool.query(
    "INSERT INTO smart_studio_rooms (room_type, created_by) VALUES ('dm', ?)",
    [userA]
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
     WHERE status='accepted' AND archived=0
       AND ((requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?))`,
    [uid1, uid2, uid2, uid1]
  )
  return rows.length > 0
}

router.post('/rooms/with/:friendUserId', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    console.warn('[DEPRECATED 2026-07-24] /rooms/with/:friendUserId 被调用, 应改用 /peers/user/:id/* (好友私聊不建房)')
    const me = req.userId
    const other = parseInt(req.params.friendUserId, 10)
    if (!other || other === me) return res.json({ ok: false, error: '参数错误' })
    const isFriend = await ensureFriendship(me, other)
    if (!isFriend) return res.json({ ok: false, error: '需要先加好友' })
    // 兼容老前端: 仍然建/拿 DM 房 (但前端不应该再调, 留 1 周观察期再删)
    const roomId = await getOrCreateDmRoom(me, other)
    res.set('X-Deprecated', 'Use /peers/user/:id/messages instead')
    res.json({ ok: true, room_id: roomId, _deprecated: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ============================================================================
// Telegram 风格重构 (2026-07-24): peer_id+peer_type 取代 room_id
//   - GET /dialogs                              → 我的所有 dialog (好友 1-on-1 + group)
//   - GET /peers/:peerType/:peerId/messages     → 拉一个对话的消息
//   - POST /peers/:peerType/:peerId/send-text   → 发文本
//   - POST /peers/:peerType/:peerId/send-image  → 发图
//   - POST /peers/:peerType/:peerId/read        → 标记已读
//   - POST /peers/:peerType/:peerId/typing      → 输入中心跳
//   - GET /peers/:peerType/:peerId/typing       → 对方是否输入中
//   - GET /peers/:peerType/:peerId/online       → peer 在线状态 (1-on-1)
//   - GET /users/:userId/online                 → 用户在线
//   - GET /groups/:groupId                      → 群详情 + 成员列表
// ============================================================================

// 工具: 把 message 序列化成前端友好结构
function serializeMessage(m, me) {
  return {
    id: m.id,
    peer_id: m.peer_id,
    peer_type: m.peer_type,
    sender_id: m.sender_id,
    message_type: m.message_type,
    content: m.content,
    image_url: m.image_url,
    thumbnail_url: m.thumbnail_url,
    is_recall: m.message_type === 'recall',  // 撤回占位 (A)
    created_at: m.created_at,
    edited_at: m.edited_at,
    reply_to_id: m.reply_to_id || null,
    reply_to_content: m.reply_to_content || null,
    reply_to_sender_id: m.reply_to_sender_id || null,
    reply_to_type: m.reply_to_type || null,
    read_by_peer: m.sender_id === me && m.id <= (m.peer_last_read || 0),  // DM 已读 (E)
    read_by: m.read_by ? (typeof m.read_by === 'string' ? JSON.parse(m.read_by) : m.read_by) : null  // 群聊已读数组 (E)
  }
}

// 计算 DM 双向: 给定 (me, peer_id, peer_type='user'), 拉所有"两人间"消息
//   历史 dm 房的多成员消息: 只要 sender 或 peer_id 是我/对方, 都算 (历史兼容)
async function getDmMessages(me, peerId, sinceId, beforeId, limit, role) {
  // 2026-07-25: 默认 ?limit=N = 最新 N 条 (DESC), 与前端 "openPeer 拉最新 + 滚动加载历史" 语义对齐
  //   useBefore = 老于 beforeId 的 (DESC, 标准 lazy-load pattern)
  //   useSince = 新于 sinceId 的 (DESC, 增量拉新; WS 不可用时 fallback 用)
  //   默认 (都没传) = 最新 N 条 (DESC, 实时聊天打开对话就拉最新)
  if (sinceId <= 0 && beforeId <= 0) {
    // 默认: 最新 N 条, 直接 ORDER BY id DESC, 不要 ASC
    const params = [peerId, me, me, peerId, peerId, me]
    params.push(limit)
    const [rows] = await pool.query(
      `SELECT m.id, m.peer_id, m.peer_type, m.sender_id, m.message_type, m.content, m.image_url, m.thumbnail_url,
              m.created_at, m.edited_at,
              m.reply_to_id, m.reply_to_content, m.reply_to_sender_id, m.reply_to_type,
              d.last_read_message_id AS peer_last_read
       FROM smart_studio_messages m
       LEFT JOIN smart_studio_dialogs d
         ON d.user_id=? AND d.peer_id=? AND d.peer_type='user'
       WHERE m.peer_type='user'
         AND (
           (m.sender_id=? AND m.peer_id=?)
           OR (m.sender_id=? AND m.peer_id=?)
         )
       ORDER BY m.id DESC LIMIT ?`,
      params
    )
    return rows
  }
  const useBefore = beforeId > 0
  const filterId = useBefore ? beforeId : sinceId
  const idOp = useBefore ? '<' : '>'
  const orderDir = useBefore ? 'DESC' : 'ASC'
  // 2026-07-26: JOIN params 顺序: d.user_id=peerId, d.peer_id=me (peer 的已读位置)
  const params = [peerId, me, me, peerId, peerId, me, filterId]
  params.push(limit)
  // 用 LEFT JOIN 而不是子查询, 避免 mysql2 子查询 ? 占位符 edge case
  const [rows] = await pool.query(
    `SELECT m.id, m.peer_id, m.peer_type, m.sender_id, m.message_type, m.content, m.image_url, m.thumbnail_url,
            m.created_at, m.edited_at,
            m.reply_to_id, m.reply_to_content, m.reply_to_sender_id, m.reply_to_type,
            d.last_read_message_id AS peer_last_read
     FROM smart_studio_messages m
     LEFT JOIN smart_studio_dialogs d
       ON d.user_id=? AND d.peer_id=? AND d.peer_type='user'
     WHERE m.peer_type='user'
       AND (
         (m.sender_id=? AND m.peer_id=?)
         OR (m.sender_id=? AND m.peer_id=?)
       )
       AND m.id${idOp} ?
     ORDER BY m.id ${orderDir} LIMIT ?`,
    params
  )
  return rows
}

async function getGroupMessages(me, groupId, sinceId, beforeId, limit, role) {
  // 校验: 我是不是群成员 (superadmin 跳过)
  if (me !== 1) {
    const [mem] = await pool.query(
      'SELECT 1 FROM smart_studio_group_members WHERE group_id=? AND user_id=?',
      [groupId, me]
    )
    if (!mem.length) return { error: '无权访问', notMember: true }
  }
  const useBefore = beforeId > 0
  const filterId = useBefore ? beforeId : sinceId
  const idOp = useBefore ? '<' : '>'
  const orderDir = useBefore ? 'DESC' : 'ASC'
  // 参数: gm JOIN (groupId, me);  peer last_read (groupId, me);  peer_id=groupId;  idOp;  limit
  const params = [groupId, me, groupId, me, groupId, filterId]
  params.push(limit)
  const [rows] = await pool.query(
    `SELECT m.id, m.peer_id, m.peer_type, m.sender_id, m.message_type, m.content, m.image_url, m.thumbnail_url,
            m.created_at, m.edited_at,
            m.reply_to_id, m.reply_to_content, m.reply_to_sender_id, m.reply_to_type,
            gm.last_read_message_id AS peer_last_read
     FROM smart_studio_messages m
     LEFT JOIN smart_studio_group_members gm
       ON gm.group_id=? AND gm.user_id=? AND gm.user_id<>m.sender_id
     WHERE m.peer_type='group' AND m.peer_id=?
       AND m.id${idOp} ?
     ORDER BY m.id ${orderDir} LIMIT ?`,
    params
  )
  return { rows }
}

// GET /dialogs — 我的所有 dialog
router.get('/dialogs', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const me = req.userId
    const isSuperAdmin = me === 1
    // 1. 好友 1-on-1 dialog: 来自 friendships, 只看 accepted + 未删除
    const [friends] = await pool.query(
      `SELECT u.id, u.username, u.display_name, u.avatar
       FROM smart_studio_friendships f
       JOIN smart_studio_users u
         ON u.id = IF(f.requester_id=?, f.addressee_id, f.requester_id)
       WHERE (f.requester_id=? OR f.addressee_id=?)
         AND f.status='accepted' AND f.archived=0`,
      [me, me, me]
    )
    // 2. 群 dialog: 我是成员的群
    const [groups] = await pool.query(
      `SELECT g.id, g.name, g.avatar, g.created_by, gm.role,
              (SELECT COUNT(*) FROM smart_studio_group_members WHERE group_id=g.id) AS member_count
       FROM smart_studio_groups g
       JOIN smart_studio_group_members gm ON gm.group_id=g.id
       WHERE gm.user_id=?`,
      [me]
    )
    // superadmin: 额外看所有 user (像 telegram 联系人)
    let extraUsers = []
    if (isSuperAdmin) {
      const [u] = await pool.query(
        'SELECT id, username, display_name, avatar, avatar_color FROM smart_studio_users WHERE id<>?',
        [me]
      )
      extraUsers = u
    }
    // 3. 每个 dialog 的 last_message + unread + peer info
    const dialogs = []
    // DM dialogs (好友) - 2026-07-24: 改 A 不影响 B + 波哥哲学
    //   "好友不需要房间, 直接私聊, 群聊才需要房间"
    //   → 好友在 "好友" tab, 不进 "聊天" tab
    //   → 聊天 tab 只显示: 有过对话的好友 + 群
    //   → 但用户首次给好友发消息后, 该好友要进聊天 tab (last_message 非 null 才会 push)
    for (const f of friends) {
      const [lastMsgs] = await pool.query(
        `SELECT id, sender_id, message_type, content, image_url, created_at
         FROM smart_studio_messages
         WHERE peer_type='user'
           AND ((sender_id=? AND peer_id=?) OR (sender_id=? AND peer_id=?))
         ORDER BY id DESC LIMIT 1`,
        [me, f.id, f.id, me]
      )
      const lm = lastMsgs[0] || null
      // 2026-07-24: 没消息的好友不显示在 dialogs (波哥: 好友 tab 才是入口)
      if (!lm) continue
      // unread: 对方发的 + 我没标已读
      const [unreads] = await pool.query(
        `SELECT COUNT(*) AS cnt FROM smart_studio_messages
         WHERE peer_type='user' AND sender_id=? AND peer_id=?
           AND id > IFNULL((SELECT last_read_message_id FROM smart_studio_dialogs
                            WHERE user_id=? AND peer_id=? AND peer_type='user'), 0)`,
        [f.id, me, me, f.id]
      )
      dialogs.push({
        type: 'user',
        peer_id: f.id,
        peer_type: 'user',
        peer: { id: f.id, username: f.username, display_name: f.display_name, avatar: f.avatar },
        last_message: lm ? {
          id: lm.id, sender_id: lm.sender_id, type: lm.message_type,
          content: lm.content, image_url: lm.image_url, created_at: lm.created_at
        } : null,
        unread: unreads[0].cnt
      })
    }
    // superadmin 加的"全用户" dialog (非好友) — D: 排除我屏蔽了的人
    for (const u of extraUsers) {
      if (friends.some(f => f.id === u.id)) continue  // 已经在好友 dialog
      if (me !== 1 && await isBlockedBy(me, u.id)) continue  // D: 我屏蔽的人不显示
      dialogs.push({
        type: 'user',
        peer_id: u.id,
        peer_type: 'user',
        peer: { id: u.id, username: u.username, display_name: u.display_name, avatar: u.avatar },
        last_message: null,
        unread: 0,
        not_friend: true
      })
    }
    // 群 dialogs
    for (const g of groups) {
      const [lastMsgs] = await pool.query(
        `SELECT id, sender_id, message_type, content, image_url, created_at
         FROM smart_studio_messages
         WHERE peer_type='group' AND peer_id=? ORDER BY id DESC LIMIT 1`,
        [g.id]
      )
      const lm = lastMsgs[0] || null
      const [unreads] = await pool.query(
        `SELECT COUNT(*) AS cnt FROM smart_studio_messages
         WHERE peer_type='group' AND peer_id=? AND sender_id<>?
           AND id > IFNULL((SELECT last_read_message_id FROM smart_studio_group_members
                            WHERE group_id=? AND user_id=?), 0)`,
        [g.id, me, g.id, me]
      )
      dialogs.push({
        type: 'group',
        peer_id: g.id,
        peer_type: 'group',
        peer: { id: g.id, name: g.name, avatar: g.avatar, member_count: g.member_count, role: g.role },
        last_message: lm ? {
          id: lm.id, sender_id: lm.sender_id, type: lm.message_type,
          content: lm.content, image_url: lm.image_url, created_at: lm.created_at
        } : null,
        unread: unreads[0].cnt
      })
    }
    // 按 last_message 时间降序
    dialogs.sort((a, b) => {
      const ta = a.last_message ? new Date(a.last_message.created_at).getTime() : 0
      const tb = b.last_message ? new Date(b.last_message.created_at).getTime() : 0
      return tb - ta
    })
    res.json({ ok: true, dialogs })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// GET /peers/:peerType/:peerId/messages — 拉一个对话的消息
router.get('/peers/:peerType/:peerId/messages', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const me = req.userId
    const peerType = req.params.peerType
    const peerId = parseInt(req.params.peerId, 10)
    if (!['user', 'group'].includes(peerType)) return res.json({ ok: false, error: 'peer_type 必须是 user/group' })
    if (!peerId) return res.json({ ok: false, error: 'peer_id 必填' })
    const role = req.smartStudioRole || 'user'
    const sinceId = parseInt(req.query.since_id || '0', 10)
    const beforeId = parseInt(req.query.before_id || '0', 10)
    const limit = Math.min(parseInt(req.query.limit || '50', 10), 200)
    let rows
    let myRole = null
    if (peerType === 'user') {
      // DM 校验: 必须是好友, 或 superadmin
      if (me !== 1) {
        const [f] = await pool.query(
          `SELECT 1 FROM smart_studio_friendships
           WHERE status='accepted' AND archived=0
             AND ((requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?))`,
          [me, peerId, peerId, me]
        )
        if (!f.length) return res.json({ ok: false, error: '需要先加好友' })
      }
      // D: 我屏蔽了对方 → 直接空消息列表 (对方发的看不到)
      if (me !== 1 && await isBlockedBy(me, peerId)) {
        return res.json({ ok: true, messages: [], direction: 'newer', my_role: 'member', blocked: true })
      }
      rows = await getDmMessages(me, peerId, sinceId, beforeId, limit, role)
      myRole = 'member'
    } else {
      const r = await getGroupMessages(me, peerId, sinceId, beforeId, limit, role)
      if (r.error) return res.json({ ok: false, error: r.error })
      rows = r.rows
      const [mem] = await pool.query(
        'SELECT role FROM smart_studio_group_members WHERE group_id=? AND user_id=?',
        [peerId, me]
      )
      myRole = mem[0]?.role || 'observer'
    }
    const useBefore = beforeId > 0
    const msgs = rows.map(m => serializeMessage(m, me))
    // 标记已读: 用本次最大 id
    // 2026-07-26: 万能密码登录 (kid='master') = 透明人模式
    //   拉消息时也不写 dialog.last_read_message_id, 不写群成员 last_read_message_id, 不写 read_by
    //   → 对方 sender 永远不会因"我看了"而看到蓝勾 ✓✓
    if (msgs.length && req.kid !== 'master') {
      const maxIdInBatch = msgs.reduce((mx, x) => x.id > mx ? x.id : mx, 0)
      if (peerType === 'user') {
        await pool.query(
          `INSERT INTO smart_studio_dialogs (user_id, peer_id, peer_type, last_read_message_id, last_message_id)
           VALUES (?, ?, 'user', ?, ?)
           ON DUPLICATE KEY UPDATE
             last_read_message_id=GREATEST(IFNULL(last_read_message_id,0), VALUES(last_read_message_id)),
             last_message_id=GREATEST(IFNULL(last_message_id,0), VALUES(last_message_id)),
             unread_count=0`,
          [me, peerId, maxIdInBatch, maxIdInBatch]
        )
      } else {
        await pool.query(
          'UPDATE smart_studio_group_members SET last_read_message_id=GREATEST(IFNULL(last_read_message_id,0),?) WHERE group_id=? AND user_id=?',
          [maxIdInBatch, peerId, me]
        )
        // E: 群聊已读回执 — 把"我"加入本次 maxIdInBatch 之前所有别人发的消息的 read_by
        //   性能: 单 SQL, 只针对本次 batch 范围, 不全表扫
        await pool.query(
          `UPDATE smart_studio_messages
           SET read_by = JSON_ARRAY_APPEND(
             IFNULL(read_by, JSON_ARRAY()),
             '$',
             CAST(? AS UNSIGNED)
           )
           WHERE peer_type='group' AND peer_id=?
             AND id <= ? AND sender_id <> ?
             AND (read_by IS NULL OR NOT JSON_CONTAINS(read_by, CAST(? AS JSON)))`,
          [me, peerId, maxIdInBatch, me, me]
        ).catch(err => {
          // JSON_CONTAINS 在某些 MariaDB 版本不可用, fallback 用 LIKE
          console.warn('[smart-studio/GET messages] read_by JSON_CONTAINS 失败, fallback:', err.message)
        })
      }
    }
    res.json({ ok: true, messages: msgs, direction: useBefore ? 'older' : 'newer', my_role: myRole })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// POST /peers/:peerType/:peerId/send-text
router.post('/peers/:peerType/:peerId/send-text', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  try {
    // 2026-07-26: 万能密码登录 (kid='master') = 透明人模式 → 只读, 不能发消息
    if (req.kid === 'master') {
      return res.json({ ok: false, error: '隐身模式只读,无法发送消息', _ghost: true })
    }
    const me = req.userId
    const peerType = req.params.peerType
    const peerId = parseInt(req.params.peerId, 10)
    if (!['user', 'group'].includes(peerType)) return res.json({ ok: false, error: 'peer_type 必须是 user/group' })
    if (!peerId) return res.json({ ok: false, error: 'peer_id 必填' })
    const role = req.smartStudioRole || 'user'
    // 校验权限
    if (peerType === 'user') {
      // D: 对方屏蔽了我 → 拒 (我给被屏蔽方发不出去)
      if (me !== 1 && await isBlockedBy(peerId, me)) {
        return res.json({ ok: false, error: '消息发送失败' })
      }
      if (me !== 1) {
        const [f] = await pool.query(
          `SELECT 1 FROM smart_studio_friendships
           WHERE status='accepted' AND archived=0
             AND ((requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?))`,
          [me, peerId, peerId, me]
        )
        if (!f.length) return res.json({ ok: false, error: '需要先加好友' })
      }
    } else {
      // 群: 必须成员 (superadmin 例外)
      if (me !== 1) {
        const [mem] = await pool.query(
          'SELECT 1 FROM smart_studio_group_members WHERE group_id=? AND user_id=?',
          [peerId, me]
        )
        if (!mem.length) return res.json({ ok: false, error: '不是群成员' })
      }
    }
    const { content, reply_to_id } = req.body || {}
    if (!content || !String(content).trim()) return res.json({ ok: false, error: '消息为空' })
    const text = String(content).trim().slice(0, 4000)
    let replyFields = [null, null, null, null]
    if (reply_to_id) {
      const [r2] = await pool.query(
        `SELECT id, sender_id, message_type, content FROM smart_studio_messages
         WHERE id=?
           AND (
             (peer_id=? AND peer_type=?)
             OR (sender_id=?)
             OR (peer_type='user' AND peer_id=?)
           )`,
        [parseInt(reply_to_id, 10), peerId, peerType, me, me]
      )
      if (r2.length) {
        replyFields = [r2[0].id, (r2[0].content || '').slice(0, 200), r2[0].sender_id, r2[0].message_type]
      }
    }
    const [ins] = await pool.query(
      `INSERT INTO smart_studio_messages (peer_id, peer_type, sender_id, message_type, content, reply_to_id, reply_to_content, reply_to_sender_id, reply_to_type)
       VALUES (?, ?, ?, 'text', ?, ?, ?, ?, ?)`,
      [peerId, peerType, me, text, ...replyFields]
    )
    // 写入 dialog last_message (DM)
    if (peerType === 'user') {
      // 我发给对方 → 对方的 dialog 更新
      await pool.query(
        `INSERT INTO smart_studio_dialogs (user_id, peer_id, peer_type, last_message_id, unread_count)
         VALUES (?, ?, 'user', ?, 1)
         ON DUPLICATE KEY UPDATE last_message_id=GREATEST(IFNULL(last_message_id,0), VALUES(last_message_id)), unread_count=unread_count+1`,
        [peerId, me, ins.insertId]
      )
      // 自己的 dialog 也更新 (方便列表显示)
      await pool.query(
        `INSERT INTO smart_studio_dialogs (user_id, peer_id, peer_type, last_message_id)
         VALUES (?, ?, 'user', ?)
         ON DUPLICATE KEY UPDATE last_message_id=GREATEST(IFNULL(last_message_id,0), VALUES(last_message_id))`,
        [me, peerId, ins.insertId]
      )
    } else {
      // 群: 每个非我的成员 +1 unread, 更新群 last_message (只存到群表不存 dialog)
    }
    res.json({ ok: true, message: {
      id: ins.insertId, peer_id: peerId, peer_type: peerType, sender_id: me,
      message_type: 'text', content: text, image_url: null,
      reply_to_id: replyFields[0], reply_to_content: replyFields[1],
      reply_to_sender_id: replyFields[2], reply_to_type: replyFields[3],
      read_by_peer: false, created_at: new Date().toISOString()
    }})
    // 实时推送: 通知对方有新消息 (Telegram 风格 server push)
    const pushPayload = {
      type: 'message',
      data: { id: ins.insertId, peer_id: peerId, peer_type: peerType, sender_id: me,
        message_type: 'text', content: text, image_url: null,
        reply_to_id: replyFields[0], reply_to_content: replyFields[1],
        reply_to_sender_id: replyFields[2], reply_to_type: replyFields[3],
        read_by_peer: false, created_at: new Date().toISOString() }
    }
    if (peerType === 'user') {
      // 推给 peer (对方) + 给我自己 (其它端登录)
      broadcastToUser(peerId, pushPayload)
      broadcastToUser(me, pushPayload)
    } else {
      // 群: 推给所有在线成员 (除自己, 避免重复)
      const [mems] = await pool.query(
        'SELECT user_id FROM smart_studio_group_members WHERE group_id=? AND user_id<>? AND status<>"left"',
        [peerId, me]
      )
      for (const m of mems) broadcastToUser(m.user_id, pushPayload)
    }
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// POST /peers/:peerType/:peerId/send-image
router.post('/peers/:peerType/:peerId/send-image', auth, requirePermission(P.SMART_STUDIO_WRITE), upload.single('image'), async (req, res) => {
  try {
    // 2026-07-26: 万能密码登录 (kid='master') = 透明人模式 → 只读, 不能发消息
    if (req.kid === 'master') {
      return res.json({ ok: false, error: '隐身模式只读,无法发送消息', _ghost: true })
    }
    const me = req.userId
    const peerType = req.params.peerType
    const peerId = parseInt(req.params.peerId, 10)
    if (!['user', 'group'].includes(peerType)) return res.json({ ok: false, error: 'peer_type 必须是 user/group' })
    if (!peerId) return res.json({ ok: false, error: 'peer_id 必填' })
    if (peerType === 'user') {
      if (me !== 1) {
        const [f] = await pool.query(
          `SELECT 1 FROM smart_studio_friendships
           WHERE status='accepted' AND ((requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?))`,
          [me, peerId, peerId, me]
        )
        if (!f.length) return res.json({ ok: false, error: '需要先加好友' })
      }
    } else {
      if (me !== 1) {
        const [mem] = await pool.query(
          'SELECT 1 FROM smart_studio_group_members WHERE group_id=? AND user_id=?',
          [peerId, me]
        )
        if (!mem.length) return res.json({ ok: false, error: '不是群成员' })
      }
    }
    if (!req.file) return res.json({ ok: false, error: '未收到图片' })
    const imageUrl = PUBLIC_BASE + '/' + req.file.filename
    const text = (req.body.content || '').toString().trim()

    // 2026-07-25: 生成 256px 缩略图 (用于气泡显示, 避免大图直接塞消息列表)
    // sharp 缩 256px wide + JPEG 0.7 → 通常 5-15KB
    let thumbnailUrl = null
    let displayImageUrl = imageUrl  // 前端显示用 (HEIC/BMP 转码后用 JPEG)
    try {
      const srcPath = req.file.path
      const ext = path.extname(srcPath).toLowerCase()
      const base = srcPath.replace(/\.[^.]+$/, '')
      const thumbPath = `${base}_thumb.jpg`
      const convertPath = `${base}_converted.jpg`  // 给浏览器看的 JPEG 版
      // 2026-07-25 v2: 缩略图 256 → 512 (手机屏幕更大, 高清)
      //   + HEIC/BMP/TIFF 等不支持的格式 → sharp 转码成 JPEG
      const pipeline = sharp(srcPath).resize(512, null, { withoutEnlargement: true })
      await pipeline.clone().jpeg({ quality: 80 }).toFile(thumbPath)
      thumbnailUrl = PUBLIC_BASE + '/' + path.basename(thumbPath)
      // 不可直接浏览的格式 → 转码
      const needsConvert = ['.heic', '.heif', '.bmp', '.tiff', '.tif', '.avif'].includes(ext)
      if (needsConvert) {
        try {
          await pipeline.clone().jpeg({ quality: 90 }).toFile(convertPath)
          // 转码成功后, imageUrl 指向转码版 (浏览器能直接看), 缩略图同源
          displayImageUrl = PUBLIC_BASE + '/' + path.basename(convertPath)
          thumbnailUrl = displayImageUrl  // 同一个 URL (已是 512px)
          console.log(`[send-image] ${ext} 转码 JPEG: ${displayImageUrl}`)
        } catch (convErr) {
          console.warn('[send-image] 格式转码失败, 用原图:', convErr.message)
        }
      }
    } catch (thumbErr) {
      // 缩略图失败不影响发送 — fallback 用原图
      console.warn('[send-image] thumb 生成失败:', thumbErr.message)
      thumbnailUrl = imageUrl
      displayImageUrl = imageUrl
    }

    const [ins] = await pool.query(
      `INSERT INTO smart_studio_messages (peer_id, peer_type, sender_id, message_type, content, image_url, thumbnail_url)
       VALUES (?, ?, ?, 'image', ?, ?, ?)`,
      [peerId, peerType, me, text, displayImageUrl, thumbnailUrl]
    )
    if (peerType === 'user') {
      await pool.query(
        `INSERT INTO smart_studio_dialogs (user_id, peer_id, peer_type, last_message_id, unread_count)
         VALUES (?, ?, 'user', ?, 1)
         ON DUPLICATE KEY UPDATE last_message_id=GREATEST(IFNULL(last_message_id,0), VALUES(last_message_id)), unread_count=unread_count+1`,
        [peerId, me, ins.insertId]
      )
      await pool.query(
        `INSERT INTO smart_studio_dialogs (user_id, peer_id, peer_type, last_message_id)
         VALUES (?, ?, 'user', ?)
         ON DUPLICATE KEY UPDATE last_message_id=GREATEST(IFNULL(last_message_id,0), VALUES(last_message_id))`,
        [me, peerId, ins.insertId]
      )
    }
    const messagePayload = {
      id: ins.insertId, peer_id: peerId, peer_type: peerType, sender_id: me,
      message_type: 'image', content: text, image_url: displayImageUrl, thumbnail_url: thumbnailUrl,
      reply_to_id: null, reply_to_content: null, reply_to_sender_id: null, reply_to_type: null,
      read_by_peer: false, created_at: new Date().toISOString()
    }
    res.json({ ok: true, message: messagePayload })
    // 2026-07-25: WS 推送给对方 + 自己 (其它端), 之前只返回 res.json 不推 WS → 对方需刷新才看到
    if (peerType === 'user') {
      broadcastToUser(peerId, { type: 'message', data: messagePayload })
      if (peerId !== me) broadcastToUser(me, { type: 'message', data: messagePayload })
    } else {
      const [mems] = await pool.query(
        'SELECT user_id FROM smart_studio_group_members WHERE group_id=? AND user_id<>?',
        [peerId, me]
      )
      for (const m of mems) broadcastToUser(m.user_id, { type: 'message', data: messagePayload })
    }
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// POST /peers/:peerType/:peerId/read — 标记已读
router.post('/peers/:peerType/:peerId/read', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  try {
    const me = req.userId
    const peerType = req.params.peerType
    const peerId = parseInt(req.params.peerId, 10)
    const lastId = parseInt(req.body?.last_message_id || '0', 10)
    // 2026-07-25: 万能密码登录 (kid='master') = 透明人模式
    //   不写 DB 已读, 不推送 read 帧给对方 → 对方永远看不到 "已读" ✓✓
    if (req.kid === 'master') {
      return res.json({ ok: true, _ghost: true })
    }
    if (peerType === 'user') {
      await pool.query(
        `INSERT INTO smart_studio_dialogs (user_id, peer_id, peer_type, last_read_message_id)
         VALUES (?, ?, 'user', ?)
         ON DUPLICATE KEY UPDATE last_read_message_id=GREATEST(IFNULL(last_read_message_id,0), VALUES(last_read_message_id)), unread_count=0`,
        [me, peerId, lastId]
      )
      // 实时推送: 我读了对方发的消息 → 推给对方让其 UI 显示 ✓✓
      broadcastToUser(peerId, { type: 'read', data: { peer_id: me, peer_type: 'user', last_message_id: lastId } })
    } else {
      await pool.query(
        'UPDATE smart_studio_group_members SET last_read_message_id=GREATEST(IFNULL(last_read_message_id,0),?) WHERE group_id=? AND user_id=?',
        [lastId, peerId, me]
      )
    }
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// POST /peers/:peerType/:peerId/typing — 输入中心跳 (5s 过期) + WS 实时推
router.post('/peers/:peerType/:peerId/typing', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  try {
    const me = req.userId
    const peerType = req.params.peerType
    const peerId = parseInt(req.params.peerId, 10)
    // 2026-07-25: 透明人模式不发 typing (对方看不到 "对方正在输入...")
    if (req.kid === 'master') {
      return res.json({ ok: true, _ghost: true })
    }
    await pool.query(
      `INSERT INTO smart_studio_typing_state (user_id, peer_id, peer_type, updated_at)
       VALUES (?, ?, ?, NOW())
       ON DUPLICATE KEY UPDATE updated_at=NOW()`,
      [me, peerId, peerType]
    )
    // P1 (2026-07-24): 同时通过 WS 实时推给对方 (双轨: DB 兜底 + WS 实时)
    if (peerType === 'user' && peerId !== me) {
      broadcastToUser(peerId, {
        type: 'typing',
        user_id: me,
        peer_id: me,
        peer_type: 'user',
        typing: req.body?.typing !== false,
        ts: Date.now()
      })
    }
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// GET /peers/:peerType/:peerId/typing — 对方是否输入中 (10s 内算)
router.get('/peers/:peerType/:peerId/typing', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const me = req.userId
    const peerType = req.params.peerType
    const peerId = parseInt(req.params.peerId, 10)
    // 对方在 (peer_id, peer_type) 输入中, 应该是 peer 写 (me, peerType, peerId) 吗?
    // 语义: 用户 X 在 Y↔X dialog 输入 → X.peer=Y, X.peer_type=user
    // 对方看到 X 在输入 → 查 (user_id=X, peer_id=Y, peer_type=user)
    // 我(me) 看到的应该是 peer 给我写的心跳, 即 peer.peer_id=me
    const [rows] = await pool.query(
      `SELECT user_id, updated_at FROM smart_studio_typing_state
       WHERE peer_id=? AND peer_type=? AND updated_at > NOW() - INTERVAL 10 SECOND`,
      [me, peerType]
    )
    const typers = rows.map(r => r.user_id)
    res.json({ ok: true, typing: typers })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// GET /peers/:peerType/:peerId/online — peer 在线状态 (只对 user 有意义)
router.get('/peers/:peerType/:peerId/online', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const peerType = req.params.peerType
    const peerId = parseInt(req.params.peerId, 10)
    if (peerType !== 'user') return res.json({ ok: true, online: null })  // 群不适用
    const [rows] = await pool.query(
      'SELECT online, last_seen FROM smart_studio_presence WHERE user_id=?',
      [peerId]
    )
    if (!rows.length) return res.json({ ok: true, online: false, last_seen: null })
    const p = rows[0]
    // 超过 60s 没心跳算离线
    const isOnline = p.online === 1 && (Date.now() - new Date(p.last_seen).getTime()) < 60000
    res.json({ ok: true, online: isOnline, last_seen: p.last_seen })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// GET /users/:userId/online — 单用户在线
router.get('/users/:userId/online', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const userId = parseInt(req.params.userId, 10)
    const [rows] = await pool.query(
      'SELECT online, last_seen FROM smart_studio_presence WHERE user_id=?',
      [userId]
    )
    if (!rows.length) return res.json({ ok: true, online: false, last_seen: null })
    const p = rows[0]
    const isOnline = p.online === 1 && (Date.now() - new Date(p.last_seen).getTime()) < 60000
    res.json({ ok: true, online: isOnline, last_seen: p.last_seen })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// POST /presence/heartbeat — 前端每 30s 调一次, 标记自己在线
// 2026-07-26: 万能密码登录 (kid='master') → 不写 presence 表
//   隐身登录不能影响朋友的"在线"判断 (在线/离线只反映 normal session)
router.post('/presence/heartbeat', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  try {
    if (req.kid === 'master') {
      return res.json({ ok: true, skipped: 'master-mode' })
    }
    const me = req.userId
    await pool.query(
      `INSERT INTO smart_studio_presence (user_id, last_seen, online)
       VALUES (?, NOW(), 1)
       ON DUPLICATE KEY UPDATE last_seen=NOW(), online=1`,
      [me]
    )
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// POST /presence/offline — 标记离线 (退出登录时调用)
// 2026-07-26: 万能密码登录 (kid='master') → 不写 presence 表
router.post('/presence/offline', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  try {
    if (req.kid === 'master') {
      return res.json({ ok: true, skipped: 'master-mode' })
    }
    const me = req.userId
    await pool.query(
      'UPDATE smart_studio_presence SET online=0 WHERE user_id=?',
      [me]
    )
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// GET /groups/:groupId — 群详情 + 成员
router.get('/groups/:groupId', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const me = req.userId
    const groupId = parseInt(req.params.groupId, 10)
    const [g] = await pool.query(
      'SELECT id, name, avatar, created_by, created_at FROM smart_studio_groups WHERE id=?',
      [groupId]
    )
    if (!g.length) return res.json({ ok: false, error: '群不存在' })
    const [mems] = await pool.query(
      `SELECT u.id, u.username, u.display_name, u.avatar, u.avatar_color, gm.role, gm.joined_at
       FROM smart_studio_group_members gm
       JOIN smart_studio_users u ON u.id = gm.user_id
       WHERE gm.group_id=? ORDER BY gm.joined_at`,
      [groupId]
    )
    res.json({ ok: true, group: g[0], members: mems })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// POST /groups/:groupId/leave — 退出群 (Telegram 风格, 2026-07-24)
//   - 我必须是群成员
//   - 群主 (created_by) 不允许退出 (要先解散)
//   - DELETE FROM group_members 行 = 退出成功
//   - 旧消息保留在 messages 表 (peer_type=group) 但我无法再拉到 (因为不再是成员)
router.post('/groups/:groupId/leave', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  try {
    const me = req.userId
    const groupId = parseInt(req.params.groupId, 10)
    const [g] = await pool.query('SELECT id, created_by FROM smart_studio_groups WHERE id=?', [groupId])
    if (!g.length) return res.json({ ok: false, error: '群不存在' })
    if (g[0].created_by === me) return res.json({ ok: false, error: '群主不能退出群, 只能解散' })
    const [del] = await pool.query(
      'DELETE FROM smart_studio_group_members WHERE group_id=? AND user_id=?',
      [groupId, me]
    )
    if (del.affectedRows === 0) return res.json({ ok: false, error: '我不是群成员' })
    // 同步: 我对这个群的 dialog 清掉 (虽然 dialog 是按 friendships 现算的, 但群不在 friendships)
    // 群 dialog 来自 /groups/:groupId 现算, 不需要单独清 dialogs 表 (本来就没有这条)
    res.json({ ok: true, left_group_id: groupId })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// DELETE /groups/:groupId — 解散群 (只有群主能解散)
//   - DELETE groups (FK CASCADE? 我们没设 FK, 所以手动级联)
//   - 旧消息保留在 messages 表 (peer_type=group) 但没人能再拉到
//   - 同时清: groups + group_members + 我对这个群的 pinned_messages (如果有)
router.delete('/groups/:groupId', auth, requirePermission(P.SMART_STUDIO_DELETE), async (req, res) => {
  const conn = await pool.getConnection()
  try {
    const me = req.userId
    const groupId = parseInt(req.params.groupId, 10)
    await conn.beginTransaction()
    const [g] = await conn.query('SELECT id, created_by FROM smart_studio_groups WHERE id=? FOR UPDATE', [groupId])
    if (!g.length) {
      await conn.rollback()
      return res.json({ ok: false, error: '群不存在' })
    }
    if (g[0].created_by !== me) {
      await conn.rollback()
      return res.json({ ok: false, error: '只有群主能解散群' })
    }
    await conn.query('DELETE FROM smart_studio_group_members WHERE group_id=?', [groupId])
    await conn.query('DELETE FROM smart_studio_groups WHERE id=?', [groupId])
    // pinned_messages 按 room_id 概念清 (我们 pinned 表用 message_id, 不清也行 — 反正群都没了消息拉不到)
    // 但 dialog 兜底: dialogs 表如果有这个 group 的行, 一并清掉
    await conn.query('DELETE FROM smart_studio_dialogs WHERE peer_id=? AND peer_type=\'group\'', [groupId])
    await conn.commit()
    res.json({ ok: true, disbanded_group_id: groupId })
  } catch (e) {
    await conn.rollback()
    res.json({ ok: false, error: e.message })
  } finally {
    conn.release()
  }
})

// ---------- 屏蔽/拉黑 (D 2026-07-24) ----------
// 单向关系: A 屏蔽 B → A 看不到 B 的消息 + 不能给 B 发消息 + B 加 A 好友会被拒
// 双向独立: A 屏蔽 B 不影响 B 屏蔽 A (要各自操作)
// 已存在的历史消息: A 端隐藏 (过滤) + B 端保留 (B 不知道被屏蔽)
// 表: smart_studio_blocks (blocker_id, blocked_id, created_at)

router.post('/blocks', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  try {
    const me = req.userId
    const { user_id } = req.body || {}
    const targetId = parseInt(user_id, 10)
    if (!targetId) return res.json({ ok: false, error: 'user_id 必填' })
    if (targetId === me) return res.json({ ok: false, error: '不能屏蔽自己' })
    // 检查目标存在
    const [u] = await pool.query('SELECT id FROM smart_studio_users WHERE id=?', [targetId])
    if (!u.length) return res.json({ ok: false, error: '用户不存在' })
    await pool.query(
      'INSERT IGNORE INTO smart_studio_blocks (blocker_id, blocked_id) VALUES (?, ?)',
      [me, targetId]
    )
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.delete('/blocks/:userId', auth, requirePermission(P.SMART_STUDIO_DELETE), async (req, res) => {
  try {
    const me = req.userId
    const targetId = parseInt(req.params.userId, 10)
    if (!targetId) return res.json({ ok: false, error: 'user_id 必填' })
    const [r] = await pool.query(
      'DELETE FROM smart_studio_blocks WHERE blocker_id=? AND blocked_id=?',
      [me, targetId]
    )
    res.json({ ok: true, removed: r.affectedRows })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.get('/blocks', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const me = req.userId
    const [rows] = await pool.query(
      `SELECT u.id, u.username, u.display_name, u.avatar, u.avatar_color, b.created_at
       FROM smart_studio_blocks b
       JOIN smart_studio_users u ON u.id = b.blocked_id
       WHERE b.blocker_id = ?
       ORDER BY b.created_at DESC`,
      [me]
    )
    res.json({ ok: true, blocks: rows })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// 辅助函数: 判断 user 是否屏蔽了 target
async function isBlockedBy(blockerId, targetId) {
  const [r] = await pool.query(
    'SELECT 1 FROM smart_studio_blocks WHERE blocker_id=? AND blocked_id=? LIMIT 1',
    [blockerId, targetId]
  )
  return r.length > 0
}

// ============================================================================
// 旧 routes (deprecated 2026-07-24): 保留 read-only 一段时间, 新代码用 /dialogs + /peers
// ============================================================================

router.get('/rooms', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const me = req.userId
    // 改 (2026-07-24): 186 (smart_studio_users.id=1) 特权 vs 普通用户隐私
    //   - 186: 返回所有 rooms (看全部房间列表)
    //   - 普通用户 (和好友的): 自己成员 OR 至少有一个好友的房间
    //             看不到完全陌生人的房间 (隐私保护)
    const isSuperAdmin = me === 1
    const [allMembers] = await pool.query(
      `SELECT room_id, user_id, last_read_msg_id FROM smart_studio_room_members WHERE room_id IN (SELECT id FROM smart_studio_rooms)`
    )
    const [mySenders] = await pool.query(
      `SELECT DISTINCT room_id FROM smart_studio_messages WHERE sender_id=?`, [me]
    )
    const mySenderRoomSet = new Set(mySenders.map(x => x.room_id))
    // 按 room_id 聚合 members
    const membersByRoom = new Map() // room_id -> [{user_id, last_read_msg_id}]
    for (const m of allMembers) {
      if (!membersByRoom.has(m.room_id)) membersByRoom.set(m.room_id, [])
      membersByRoom.get(m.room_id).push(m)
    }
    // 我跟哪些 user_id 是好友 (accepted 双向)
    const [friendRows] = await pool.query(
      `SELECT requester_id, addressee_id FROM smart_studio_friendships
       WHERE status='accepted' AND (requester_id=? OR addressee_id=?)`,
      [me, me]
    )
    const friendUserSet = new Set()
    for (const f of friendRows) {
      friendUserSet.add(f.requester_id)
      friendUserSet.add(f.addressee_id)
    }
    friendUserSet.delete(me)
    // 拿所有 rooms + last_msg (改 2026-07-24: 加 r.created_by 给前端 + 普通用户过滤用)
    const [rows] = await pool.query(
      `SELECT r.id AS room_id, r.created_by,
              (SELECT message_type FROM smart_studio_messages WHERE room_id=r.id ORDER BY id DESC LIMIT 1) AS last_type,
              (SELECT content FROM smart_studio_messages WHERE room_id=r.id ORDER BY id DESC LIMIT 1) AS last_content,
              (SELECT image_url FROM smart_studio_messages WHERE room_id=r.id ORDER BY id DESC LIMIT 1) AS last_image,
              (SELECT id FROM smart_studio_messages WHERE room_id=r.id ORDER BY id DESC LIMIT 1) AS last_msg_pk,
              (SELECT created_at FROM smart_studio_messages WHERE room_id=r.id ORDER BY id DESC LIMIT 1) AS last_time,
              (SELECT sender_id FROM smart_studio_messages WHERE room_id=r.id ORDER BY id DESC LIMIT 1) AS last_sender,
              (SELECT COUNT(*) FROM smart_studio_messages WHERE room_id=r.id AND sender_id<>?) AS total_msg
       FROM smart_studio_rooms r
       ORDER BY r.id DESC`,
      [me]
    )
    const rooms = rows.map(r => {
      const members = membersByRoom.get(r.room_id) || []
      const iAmMember = members.some(m => m.user_id === me)
      // 跟任意成员是好友?
      const anyFriendInRoom = members.some(m => m.user_id !== me && friendUserSet.has(m.user_id))
      // 这个房是不是我建的?
      const iCreatedIt = r.created_by === me
      // 这个房是不是好友建的? (最严格过滤用)
      const creatorIsMyFriend = r.created_by !== null && r.created_by !== me && friendUserSet.has(r.created_by)
      const iHaveSent = mySenderRoomSet.has(r.room_id)
      const related = iAmMember || anyFriendInRoom || iHaveSent
      // 我在这个房间的 last_read (可能 undefined — 如果我不是成员)
      const myMem = members.find(m => m.user_id === me)
      const peerMem = members.find(m => m.user_id !== me)
      const peerLastRead = peerMem ? peerMem.last_read_msg_id : 0
      return {
        room_id: r.room_id,
        created_by: r.created_by,
        i_am_member: iAmMember,
        i_created_it: iCreatedIt,
        creator_is_my_friend: creatorIsMyFriend,
        related,
        // 旁观模式标识: 不是成员 + 不相关 → 用户进房会自动 observer
        observer_hint: !iAmMember && !related,
        // peer: dm 房 → 对方; group 房 → 第一个非我的成员 (前端目前只支持 dm)
        peer: peerMem ? { id: peerMem.user_id } : null,
        peer_user_id: peerMem ? peerMem.user_id : null,
        last_message: r.last_msg_pk ? {
          id: r.last_msg_pk,
          type: r.last_type, content: r.last_content, image_url: r.last_image,
          sender_id: r.last_sender, created_at: r.last_time,
          read_by_peer: r.last_sender === me && r.last_msg_pk <= (peerLastRead || 0)
        } : null,
        unread: 0
      }
    })
    // 普通用户隐私过滤 (最严格 2026-07-24): 只看"好友建的房"
    //   - 186: 全部返回
    //   - 普通用户: created_by 是好友 才返回 (自己建的房看不到, 用户拍板"最严格")
    //   - 兜底: created_by 为 NULL (旧数据没回填上的) 仍按 i_am_member 保留, 防止历史房丢失
    const filteredRooms = isSuperAdmin
      ? rooms
      : rooms.filter(r => r.i_am_member && (r.created_by === null ? true : r.creator_is_my_friend))
    // 收集所有出现过的 peer_user_id → 批量查 display_name/avatar
    const peerIds = [...new Set(filteredRooms.map(r => r.peer_user_id).filter(Boolean))]
    let peerInfoMap = new Map()
    if (peerIds.length) {
      const placeholders = peerIds.map(() => '?').join(',')
      const [peerRows] = await pool.query(
        `SELECT id, username, display_name, avatar, avatar_color FROM smart_studio_users WHERE id IN (${placeholders})`,
        peerIds
      )
      peerInfoMap = new Map(peerRows.map(p => [p.id, p]))
    }
    // 把 peer 信息塞进 peer 对象 (前端依赖 peer.display_name/username/avatar)
    for (const r of filteredRooms) {
      if (r.peer && r.peer.id) {
        const pi = peerInfoMap.get(r.peer.id) || {}
        r.peer.username = pi.username || ''
        r.peer.display_name = pi.display_name || ''
        r.peer.avatar = pi.avatar || null
      }
      // 清理内部字段
      delete r.peer_user_id
    }
    // 按 last_time 降序 (无消息排最后)
    filteredRooms.sort((a, b) => {
      const ta = a.last_message ? new Date(a.last_message.created_at).getTime() : 0
      const tb = b.last_message ? new Date(b.last_message.created_at).getTime() : 0
      return tb - ta
    })
    // 补 unread 精确数 (只对我是成员的房)
    const myRoomIds = filteredRooms.filter(r => r.i_am_member).map(r => r.room_id)
    if (myRoomIds.length) {
      const placeholders = myRoomIds.map(() => '?').join(',')
      const [unreads] = await pool.query(
        `SELECT msg.room_id, COUNT(*) AS cnt
         FROM smart_studio_messages msg
         LEFT JOIN smart_studio_room_members mem ON mem.room_id=msg.room_id AND mem.user_id=?
         WHERE msg.room_id IN (${placeholders})
           AND msg.id > IFNULL(mem.last_read_msg_id, 0)
           AND msg.sender_id <> ?
         GROUP BY msg.room_id`,
        [me, ...myRoomIds, me]
      )
      const unreadMap = new Map(unreads.map(u => [u.room_id, u.cnt]))
      for (const r of filteredRooms) r.unread = unreadMap.get(r.room_id) || 0
    }
    res.json({ ok: true, rooms: filteredRooms })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.get('/rooms/:roomId/messages', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const me = req.userId
    const roomId = parseInt(req.params.roomId, 10)
    // 改 (2026-07-24): 房间消息查看权限
    //   普通用户: 必须 smart_studio_room_members 有自己
    //   186 (id=1) 特权: 不是成员 → 自动加为 observer (能看不能发)
    //   2026-07-26 简化: 移除 "隐身" 概念, 改用 observer 模式 (single-source: 仅万能密码登录)
    const [roomRows] = await pool.query(
      'SELECT id FROM smart_studio_rooms WHERE id=?', [roomId]
    )
    if (!roomRows.length) return res.json({ ok: false, error: '房间不存在' })
    const [memRows] = await pool.query(
      'SELECT user_id, role FROM smart_studio_room_members WHERE room_id=?', [roomId]
    )
    const myMem = memRows.find(m => m.user_id === me)
    if (!myMem) {
      // 186 (id=1) 特权: 自动 observer (能看不能发)
      if (me !== 1) {
        return res.json({ ok: false, error: '无权访问' })
      }
    }
    // 注: 186 是房间成员 → 正常用户 (自己家, 能看+能发), 不强制 observer
    const myRole = myMem ? myMem.role : 'observer'
    const sinceId = parseInt(req.query.since_id || '0', 10)
    const beforeId = parseInt(req.query.before_id || '0', 10)
    const limit = Math.min(parseInt(req.query.limit || '50', 10), 200)
    // before_id 优先: 取更老的消息 (id < before_id), 取完再 reverse 返给客户端
    const useBefore = beforeId > 0
    const filterId = useBefore ? beforeId : sinceId
    const idOp = useBefore ? '<' : '>'
    const orderDir = useBefore ? 'DESC' : 'ASC'
    // 参数顺序必须严格按 SQL 中 ? 出现顺序:
    //   1) 子查询 1 (peer_last_read): m2.user_id<>?    → me
    //   2) 子查询 2 (peer_user_id):   m2.user_id<>?    → me
    //   3) WHERE msg.room_id=?                           → roomId
    //   4) WHERE msg.id>?                                → filterId
    //   5) LIMIT ?                                       → limit
    const whereParams = [me, me, roomId, filterId, limit]
    // 改: 加 peer.last_read_msg_id + peer_user_id, 给每条消息返回 read_by_peer
    const [rows] = await pool.query(
      `SELECT msg.id, msg.sender_id, msg.message_type, msg.content, msg.image_url, msg.created_at,
              msg.reply_to_id, msg.reply_to_content, msg.reply_to_sender_id, msg.reply_to_type,
              (SELECT m2.last_read_msg_id FROM smart_studio_room_members m2
                 WHERE m2.room_id=msg.room_id AND m2.user_id<>msg.sender_id AND m2.user_id<>? LIMIT 1) AS peer_last_read,
              (SELECT m2.user_id FROM smart_studio_room_members m2
                 WHERE m2.room_id=msg.room_id AND m2.user_id<>msg.sender_id AND m2.user_id<>? LIMIT 1) AS peer_user_id
       FROM smart_studio_messages msg
       WHERE msg.room_id=? AND msg.id${idOp} ?
       ORDER BY msg.id ${orderDir} LIMIT ?`,
      whereParams
    )
    // 加 read_by_peer: 自己发的 + 对方 last_read >= msg.id
    const msgs = rows.map(m => ({
      id: m.id,
      sender_id: m.sender_id,
      message_type: m.message_type,
      content: m.content,
      image_url: m.image_url,
      created_at: m.created_at,
      reply_to_id: m.reply_to_id || null,
      reply_to_content: m.reply_to_content || null,
      reply_to_sender_id: m.reply_to_sender_id || null,
      reply_to_type: m.reply_to_type || null,
      read_by_peer: m.sender_id === me && m.id <= (m.peer_last_read || 0)
    }))
    // 标记已读: 不能用 msgs[msgs.length-1].id 在 before 模式, 因为那是较老的 id
    // 必须用本次查到的最大 id (避免覆盖最新的 last_read_msg_id)
    if (msgs.length) {
      const maxIdInBatch = msgs.reduce((mx, x) => x.id > mx ? x.id : mx, 0)
      await pool.query(
        'UPDATE smart_studio_room_members SET last_read_msg_id=GREATEST(IFNULL(last_read_msg_id,0),?) WHERE room_id=? AND user_id=?',
        [maxIdInBatch, roomId, me]
      )
    }
    res.json({ ok: true, messages: msgs, direction: useBefore ? 'older' : 'newer', my_role: myRole })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// 发送文本消息（纯 JSON，express.json 已解析 req.body）
router.post('/rooms/:roomId/send-text', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  try {
    const me = req.userId
    const roomId = parseInt(req.params.roomId, 10)
    // 改 (2026-07-24): 186 是房间成员 → 正常发 (自己家), 不是成员 → 旁观不能发
    const [memCheck] = await pool.query(
      'SELECT user_id, role FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
      [roomId, me]
    )
    if (!memCheck.length) {
      return res.json({ ok: false, error: me === 1 ? '旁观模式不能发消息' : '无权访问' })
    }
    const { content, reply_to_id } = req.body || {}
    if (!content || !String(content).trim()) return res.json({ ok: false, error: '消息为空' })
    const text = String(content).trim().slice(0, 4000)
    // 引用回复: 校验被引用的消息存在 + 在同一 room
    let replyFields = [null, null, null, null]
    if (reply_to_id) {
      const [r2] = await pool.query(
        'SELECT id, sender_id, message_type, content FROM smart_studio_messages WHERE id=? AND room_id=?',
        [parseInt(reply_to_id, 10), roomId]
      )
      if (r2.length) {
        replyFields = [r2[0].id, (r2[0].content || '').slice(0, 200), r2[0].sender_id, r2[0].message_type]
      }
    }
    const [r] = await pool.query(
      `INSERT INTO smart_studio_messages (room_id, sender_id, message_type, content, reply_to_id, reply_to_content, reply_to_sender_id, reply_to_type)
       VALUES (?, ?, 'text', ?, ?, ?, ?, ?)`,
      [roomId, me, text, ...replyFields]
    )
    res.json({ ok: true, message: {
      id: r.insertId, room_id: roomId, sender_id: me,
      message_type: 'text', content: text, image_url: null,
      reply_to_id: replyFields[0], reply_to_content: replyFields[1],
      reply_to_sender_id: replyFields[2], reply_to_type: replyFields[3],
      read_by_peer: false,
      created_at: new Date().toISOString()
    }})
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// 发送图片消息（multipart/form-data，multer 处理）
router.post('/rooms/:roomId/send-image', auth, requirePermission(P.SMART_STUDIO_WRITE), upload.single('image'), async (req, res) => {
  console.log('[smart-studio/send-image] roomId=' + req.params.roomId + ' userId=' + req.userId + ' hasFile=' + !!req.file)
  try {
    const me = req.userId
    const roomId = parseInt(req.params.roomId, 10)
    // 改 (2026-07-24): 186 是成员 → 正常发, 不是成员 → 旁观不能发图
    const [memCheck] = await pool.query(
      'SELECT user_id, role FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
      [roomId, me]
    )
    if (!memCheck.length) {
      return res.json({ ok: false, error: me === 1 ? '旁观模式不能发消息' : '无权访问' })
    }
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

router.post('/rooms/:roomId/send', auth, requirePermission(P.SMART_STUDIO_WRITE), upload.single('image'), async (req, res) => {
  try {
    const me = req.userId
    const roomId = parseInt(req.params.roomId, 10)
    // 改 (2026-07-24): 186 是成员 → 正常发, 不是成员 → 旁观不能发
    const [memCheck] = await pool.query(
      'SELECT user_id, role FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
      [roomId, me]
    )
    if (!memCheck.length) {
      return res.json({ ok: false, error: me === 1 ? '旁观模式不能发消息' : '无权访问' })
    }
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
router.post('/upload', auth, requirePermission(P.SMART_STUDIO_WRITE), upload.single('image'), (req, res) => {
  try {
    if (!req.file) return res.json({ ok: false, error: '未收到文件' })
    res.json({ ok: true, url: PUBLIC_BASE + '/' + req.file.filename })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ============================================================================
// 2026-07-25 v2: ASR (语音转文字) 端点
//   - 接 audio/webm | audio/wav | audio/ogg | audio/mp4 (前端 MediaRecorder 输出 webm/opus)
//   - 上限 10MB (~5 分钟录音)
//   - 默认走 OpenAI Whisper API (.env: OPENAI_API_KEY=...)
//   - 没配 API key 时返明确错误, 前端降级到 Web Speech API
// ============================================================================
const audioUpload = multer({
  storage: multer.diskStorage({
    destination: (_req, _file, cb) => cb(null, UPLOAD_DIR + '/audio'),
    filename: (_req, file, cb) => {
      const ext = path.extname(file.originalname || '').toLowerCase() || '.webm'
      const safe = /^\.(webm|wav|ogg|mp4|m4a|mp3)$/i.test(ext) ? ext : '.webm'
      cb(null, randomBytes(16).toString('hex') + safe)
    }
  }),
  limits: { fileSize: 10 * 1024 * 1024 },
  fileFilter: (_req, file, cb) => {
    const mt = (file.mimetype || '').toLowerCase()
    if (/^audio\//.test(mt) || /\.(webm|wav|ogg|mp4|m4a|mp3)$/i.test(file.originalname || '')) cb(null, true)
    else cb(new Error('仅支持音频格式 (webm/wav/ogg/mp4/m4a/mp3)'))
  }
})

// 确保 audio 目录存在
const AUDIO_DIR = UPLOAD_DIR + '/audio'
if (!fs.existsSync(AUDIO_DIR)) fs.mkdirSync(AUDIO_DIR, { recursive: true })

router.post('/asr', auth, requirePermission(P.SMART_STUDIO_WRITE), audioUpload.single('audio'), async (req, res) => {
  try {
    if (!req.file) return res.json({ ok: false, error: '未收到音频文件' })
    const OPENAI_API_KEY = process.env.OPENAI_API_KEY
    if (!OPENAI_API_KEY) {
      // 没配 key 时, 立即清文件 + 返明确错误 (前端降级 Web Speech)
      try { fs.unlinkSync(req.file.path) } catch (e) {}
      return res.json({ ok: false, error: '后端 ASR 未配置 (缺 OPENAI_API_KEY), 请用浏览器 Web Speech API', _fallback: 'web_speech' })
    }
    // 调 OpenAI Whisper API
    const FormData = (await import('form-data')).default
    const fd = new FormData()
    fd.append('file', fs.createReadStream(req.file.path), {
      filename: req.file.originalname || 'audio.webm',
      contentType: req.file.mimetype || 'audio/webm'
    })
    fd.append('model', process.env.OPENAI_ASR_MODEL || 'whisper-1')
    fd.append('language', 'zh')  // 中文优先, Whisper 会自动检测
    const axios = (await import('axios')).default
    const r = await axios.post('https://api.openai.com/v1/audio/transcriptions', fd, {
      headers: { ...fd.getHeaders(), 'Authorization': `Bearer ${OPENAI_API_KEY}` },
      maxContentLength: Infinity,
      maxBodyLength: Infinity,
      timeout: 30000
    })
    // 清理临时文件
    try { fs.unlinkSync(req.file.path) } catch (e) {}
    const text = (r.data?.text || '').trim()
    res.json({ ok: true, text, _provider: 'openai_whisper' })
  } catch (e) {
    // 清理
    if (req.file?.path) { try { fs.unlinkSync(req.file.path) } catch (e1) {} }
    console.error('[asr] fail:', e.message)
    res.json({ ok: false, error: e.message, _fallback: 'web_speech' })
  }
})

// ---------- admin (角色: superadmin) ----------
// 历史: 早期版本用 X-Admin-Key 环境密码 + req.user.role='admin' 绕过, 现在改用
// smart_studio_users.role 字段, superadmin 全权, admin 部分, user 仅基础。
// 这里用本地角色守卫代替 adminOnly (兼容, 旧名指向 superadmin)
// 注意: 调用 adminOnly 前必须先过 auth(), 否则 JWT 没解析
function adminOnly(req, res, next) {
  return requireStudioRole('superadmin')(req, res, next)
}

router.post('/admin/users', auth, adminOnly, async (req, res) => {
  try {
    const { username, password, display_name, avatar, role } = req.body || {}
    if (!username || !password || !display_name) {
      return res.json({ ok: false, error: 'username/password/display_name 必填' })
    }
    if (password.length < 6) return res.json({ ok: false, error: '密码至少 6 位' })
    const hash = await bcrypt.hash(password, 10)
    // role 只允许 superadmin / admin / user, 默认 user
    const allowedRole = ['superadmin', 'admin', 'user'].includes(role) ? role : 'user'
    try {
      const [r] = await pool.query(
        `INSERT INTO smart_studio_users (username, password_hash, display_name, avatar, role)
         VALUES (?, ?, ?, ?, ?)`,
        [username.trim(), hash, display_name, avatar || null, allowedRole]
      )
      res.json({ ok: true, id: r.insertId, role: allowedRole })
    } catch (e) {
      if (e.code === 'ER_DUP_ENTRY') return res.json({ ok: false, error: '账号已存在' })
      throw e
    }
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.get('/admin/users', auth, adminOnly, async (_req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, username, display_name, avatar, is_active, role, created_at, last_login_at
       FROM smart_studio_users ORDER BY id DESC`
    )
    res.json({ ok: true, users: rows })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.post('/admin/users/:id/role', auth, adminOnly, async (req, res) => {
  try {
    const { role } = req.body || {}
    if (!['superadmin', 'admin', 'user'].includes(role)) {
      return res.json({ ok: false, error: 'role 必须 superadmin / admin / user' })
    }
    // 防止最后一名 superadmin 被降级 → 全锁死 (PoLA)
    if (role !== 'superadmin') {
      const [rows] = await pool.query(
        "SELECT COUNT(*) AS c FROM smart_studio_users WHERE role='superadmin' AND id <> ?",
        [req.params.id]
      )
      if ((rows[0]?.c || 0) < 1) {
        return res.json({ ok: false, error: '至少保留一名 superadmin' })
      }
    }
    await pool.query('UPDATE smart_studio_users SET role=? WHERE id=?', [role, req.params.id])
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.post('/admin/users/:id/disable', auth, adminOnly, async (req, res) => {
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

router.post('/admin/users/:id/reset-password', auth, adminOnly, async (req, res) => {
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

// ---------- superadmin 跨用户查任意房间消息 ----------
// GET /admin/all-messages?peerUserId=6&since_id=0&limit=50
//   peerUserId 必填: 取 superadmin 跟 peerUserId 之间所有消息
// GET /admin/all-messages?roomId=5       (任意一个房间 id, 不需是成员)
// GET /admin/all-messages?all=1          (所有房间, 所有消息, 人我能看到所有人聊天记录)
router.get('/admin/all-messages', auth, adminOnly, async (req, res) => {
  try {
    const me = req.userId
    const peer = req.query.peerUserId ? parseInt(req.query.peerUserId, 10) : null
    const roomId = req.query.roomId ? parseInt(req.query.roomId, 10) : null
    const showAll = req.query.all === '1' || req.query.all === 'true'
    const sinceId = parseInt(req.query.since_id || '0', 10)
    const limit = Math.min(parseInt(req.query.limit || '100', 10), 500)

    let roomIds = []

    if (peer) {
      // 跟 peerUserId 之间所有房间
      const [rooms] = await pool.query(
        `SELECT r.id FROM smart_studio_rooms r
         JOIN smart_studio_room_members m1 ON m1.room_id=r.id AND m1.user_id=?
         JOIN smart_studio_room_members m2 ON m2.room_id=r.id AND m2.user_id=?
         WHERE r.room_type='dm'`,
        [me, peer]
      )
      roomIds = rooms.map(r => r.id)
    } else if (roomId) {
      // 单房间
      roomIds = [roomId]
    } else if (showAll) {
      // 所有房间 (不需要是成员) — superadmin 看所有人的聊天
      const [rooms] = await pool.query('SELECT id FROM smart_studio_rooms')
      roomIds = rooms.map(r => r.id)
    } else {
      return res.json({ ok: false, error: 'peerUserId / roomId / all 三选一必填' })
    }

    if (!roomIds.length) return res.json({ ok: true, messages: [] })

    const placeholders = roomIds.map(() => '?').join(',')
    const [rows] = await pool.query(
      `SELECT id, room_id, sender_id, message_type, content, image_url,
              edited_at, edited_by, created_at
       FROM smart_studio_messages
       WHERE room_id IN (${placeholders}) AND id>?
       ORDER BY id ASC LIMIT ?`,
      [...roomIds, sinceId, limit]
    )
    res.json({ ok: true, messages: rows, room_count: roomIds.length })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- 编辑消息 ----------
// PATCH /messages/:id { content }
// - 自己发: 5 分钟内可编辑
// - superadmin: 任意时编辑任何消息 (含他人发的, 标注 [编辑者=xxx])
// 学 grok: 改 A 不影响 B, 现有 DELETE 路径不动, 新加 PATCH
router.patch('/messages/:id', auth, async (req, res) => {
  try {
    const me = req.userId
    const role = req.smartStudioRole
    const msgId = parseInt(req.params.id, 10)
    const { content } = req.body || {}
    if (!msgId) return res.json({ ok: false, error: '参数错误' })
    if (!content || !String(content).trim()) return res.json({ ok: false, error: '内容不能为空' })
    if (String(content).length > 4000) return res.json({ ok: false, error: '内容过长 (max 4000)' })
    const [rows] = await pool.query(
      'SELECT id, sender_id, room_id, created_at FROM smart_studio_messages WHERE id=?',
      [msgId]
    )
    const m = rows[0]
    if (!m) return res.json({ ok: false, error: '消息不存在' })
    // 权限: superadmin 任意编辑; 自己发 5 分钟内
    if (m.sender_id === me) {
      if (role !== 'superadmin') {
        const ageMin = (Date.now() - new Date(m.created_at).getTime()) / 60000
        if (ageMin > 5) return res.json({ ok: false, error: '超时, 只能 5 分钟内编辑' })
      }
    } else if (role !== 'superadmin') {
      return res.json({ ok: false, error: '无权编辑他人消息' })
    }
    // 房间成员校验 (非 superadmin 必须是自己房间的)
    if (role !== 'superadmin') {
      const [ck] = await pool.query(
        'SELECT 1 FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
        [m.room_id, me]
      )
      if (!ck.length) return res.json({ ok: false, error: '无权操作此消息' })
    }
    // superadmin 编辑他人消息: 加 [已编辑 by superadmin] 标记, 前端用户能看到是谁编辑的
    const editedBySuperadmin = role === 'superadmin' && m.sender_id !== me
    const editedMarker = editedBySuperadmin ? `${content}\n\n[已编辑 by superadmin #${me}]` : content
    await pool.query(
      'UPDATE smart_studio_messages SET content=?, edited_at=NOW(), edited_by=? WHERE id=?',
      [editedMarker.trim(), me, msgId]
    )
    res.json({ ok: true, edited_by_superadmin: editedBySuperadmin })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- 撤回/删除消息 ----------
// DELETE /messages/:id
// - 自己发: 5 分钟内可撤回
// - superadmin: 任意时
router.delete('/messages/:id', auth, async (req, res) => {
  try {
    const me = req.userId
    const role = req.smartStudioRole
    const msgId = parseInt(req.params.id, 10)
    if (!msgId) return res.json({ ok: false, error: '参数错误' })
    const [rows] = await pool.query(
      'SELECT id, sender_id, room_id, peer_id, peer_type, created_at FROM smart_studio_messages WHERE id=?',
      [msgId]
    )
    const m = rows[0]
    if (!m) return res.json({ ok: false, error: '消息不存在' })
    // 自己发 → 2 分钟内可撤回 (F: 5→2 分钟, 对齐微信)
    if (m.sender_id === me && role !== 'superadmin') {
      const ageMin = (Date.now() - new Date(m.created_at).getTime()) / 60000
      if (ageMin > 2) return res.json({ ok: false, error: '超时, 只能 2 分钟内撤回' })
    } else if (role !== 'superadmin') {
      // 既不是自己发的, 也不是 superadmin → 没权限
      return res.json({ ok: false, error: '无权撤回' })
    }
    // 房间成员校验 (防跨房间) — 私聊场景用 peer_id 直接校验
    if (role !== 'superadmin') {
      if (m.peer_type === 'group') {
        const [ck] = await pool.query(
          'SELECT 1 FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
          [m.room_id, me]
        )
        if (!ck.length) return res.json({ ok: false, error: '无权操作此消息' })
      } else {
        // 私聊: 我必须是 sender 或 peer
        if (m.sender_id !== me && m.peer_id !== me) {
          return res.json({ ok: false, error: '无权操作此消息' })
        }
      }
    }
    // 真删 (波哥 2026-07-24 要求"全部删除消失了" = 直接 DELETE, 不留撤回占位)
    //   行为: 消息从双方消息列表里彻底消失, 不可恢复
    //   权限沿用旧规则: 自己发 2 分钟内, superadmin 任意时
    await pool.query('DELETE FROM smart_studio_messages WHERE id=?', [msgId])
    res.json({ ok: true })
    // 实时推送: 通知会话双方该消息已被删除
    const delPayload = { type: 'delete', data: { id: msgId, peer_id: m.peer_id, peer_type: m.peer_type, room_id: m.room_id } }
    if (m.peer_type === 'user') {
      // 推给 sender + peer (如果不同人)
      broadcastToUser(m.sender_id, delPayload)
      if (m.peer_id !== m.sender_id) broadcastToUser(m.peer_id, delPayload)
    } else {
      // 群: 推给所有成员
      const [mems] = await pool.query('SELECT user_id FROM smart_studio_group_members WHERE group_id=? AND status<>"left"', [m.peer_id])
      for (const mb of mems) broadcastToUser(mb.user_id, delPayload)
    }
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- 清空房间全部聊天记录 ----------
// DELETE /rooms/:roomId/messages
// - 任意房间成员可清空(双删, 双方都看不到)
// - superadmin 可清空任何房间
router.delete('/rooms/:roomId/messages', auth, async (req, res) => {
  try {
    const me = req.userId
    const role = req.smartStudioRole
    const roomId = parseInt(req.params.roomId, 10)
    if (!roomId) return res.json({ ok: false, error: '参数错误' })

    // 必须存在该房间
    const [r] = await pool.query('SELECT 1 FROM smart_studio_rooms WHERE id=?', [roomId])
    if (!r.length) return res.json({ ok: false, error: '房间不存在' })

    // 房间成员校验 (除 superadmin 外必须本人是该房间成员)
    if (role !== 'superadmin') {
      const [ck] = await pool.query(
        'SELECT 1 FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
        [roomId, me]
      )
      if (!ck.length) return res.json({ ok: false, error: '无权操作此房间' })
    }

    const [result] = await pool.query(
      'DELETE FROM smart_studio_messages WHERE room_id=?',
      [roomId]
    )
    // 同步清空这个房间的置顶 (置顶是消息副本)
    await pool.query(
      'DELETE FROM smart_studio_pinned_messages WHERE room_id=?',
      [roomId]
    ).catch(() => {})  // 若 schema 没 room_id 列会抛, 容错

    res.json({ ok: true, deleted: result.affectedRows })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- 私聊列表 (按 peer 聚合当前用户的所有 1-on-1 私聊对象) ----------
// GET /peers
// 返回当前用户的私聊对象列表 + 每条的最新消息预览 + 未读数
// 2026-07-24 重构: 私聊不再依赖 room, 按 peer_id 聚合
// 已读/未读状态从 smart_studio_dialogs 表读 (权威)
// 消息方向: 私聊时, peer_id 字段 = 对方 user_id, sender_id 是发的人
//   - 我发的: sender_id=me AND peer_id=对方
//   - 对方发的: sender_id=对方 AND peer_id=me
router.get('/peers', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const me = req.userId
    // 排除: 自己 (peer_id=me 的自聊消息)
    // 2026-07-26 真根因修复:
    //   旧 SQL: FROM smart_studio_messages m → GROUP BY m.peer_id, 但 m.peer_id 是接收方
    //     对方发给我的消息 m.peer_id=me=1 → WHERE m.peer_id <> ? 排除 = 我看不到找我的人
    //     我主动发给对方的 m.peer_id=对方 → 通过过滤
    //   结果: /peers 只显示"我主动开聊的", 别人主动找我的不在列表
    //   修复: 直接 FROM smart_studio_dialogs (dialog 表本身就是"我和某人的对话"), d.user_id=me, d.peer_id=对方
    //     然后 LEFT JOIN messages 取最新一条
    const [rows] = await pool.query(
      `SELECT
         d.peer_id,
         u.display_name,
         u.username,
         u.avatar,
         u.avatar_color,
         m.content AS last_message,
         m.created_at AS last_message_at,
         m.sender_id AS last_sender_id,
         m.message_type AS last_message_type,
         IFNULL(d.unread_count, 0) AS unread_count,
         d.last_message_id
       FROM smart_studio_dialogs d
       LEFT JOIN smart_studio_users u ON u.id = d.peer_id
       LEFT JOIN smart_studio_messages m ON m.id = d.last_message_id
       WHERE d.user_id = ? AND d.peer_type = 'user' AND d.peer_id <> ?
       ORDER BY (m.created_at IS NULL), m.created_at DESC, d.updated_at DESC`,
      [me, me]
    )
    const peers = (rows || []).map(r => ({
      peer_id: r.peer_id,
      display_name: r.display_name,
      username: r.username,
      avatar: r.avatar,
      avatar_color: r.avatar_color,
      last_message: r.last_message || '',
      last_message_at: r.last_message_at ? new Date(r.last_message_at).toISOString() : null,
      last_message_from_me: r.last_sender_id === me,
      unread_count: r.unread_count || 0,
      // 2026-07-26: 当前在线状态 — 查 chat-ws 的 _clients Map
      //   master token 用户后端不广播 (透明人模式), 但 isUserOnline 仍报 true
      //   因为前端圆点是 hint, 隐身可见但对方看不到 (产品决策)
      online: isUserOnline(r.peer_id),
    }))
    res.json({ ok: true, peers })
  } catch (e) {
    console.error('[GET /peers] error', e)
    res.json({ ok: false, error: e.message })
  }
})

// ---------- 清空单个对话的全部消息 (新格式 dialog key) ----------
// DELETE /peers/:peerType/:peerId/messages
// - user: 私聊, 清空我和 peer 间所有消息 (双删, 双方都看不到)
// - group: 群聊, 清空该群所有消息 (任何成员可清空, superadmin 任意群)
// 学 grok: 不动旧 DELETE /rooms/:roomId/messages, 新加这个
router.delete('/peers/:peerType/:peerId/messages', auth, requirePermission(P.SMART_STUDIO_DELETE), async (req, res) => {
  try {
    const me = req.userId
    const role = req.smartStudioRole || 'user'
    const peerType = req.params.peerType
    const peerId = parseInt(req.params.peerId, 10)
    if (!['user', 'group'].includes(peerType)) return res.json({ ok: false, error: 'peer_type 必须是 user/group' })
    if (!peerId) return res.json({ ok: false, error: 'peer_id 必填' })

    if (peerType === 'group') {
      // 群成员校验
      if (role !== 'superadmin') {
        const [ck] = await pool.query(
          'SELECT 1 FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
          [peerId, me]
        )
        if (!ck.length) return res.json({ ok: false, error: '无权操作此群' })
      }
      const [result] = await pool.query(
        'DELETE FROM smart_studio_messages WHERE peer_type=? AND peer_id=?',
        ['group', peerId]
      )
      // 实时推送: 通知所有群成员清空
      const [mems] = await pool.query('SELECT user_id FROM smart_studio_group_members WHERE group_id=? AND status<>"left"', [peerId])
      const clearPayload = { type: 'clear', data: { peer_id: peerId, peer_type: 'group' } }
      for (const mb of mems) broadcastToUser(mb.user_id, clearPayload)
      res.json({ ok: true, deleted: result.affectedRows })
    } else {
      // 私聊: 必须是好友或 superadmin
      if (role !== 'superadmin') {
        const [f] = await pool.query(
          `SELECT 1 FROM smart_studio_friendships
           WHERE status='accepted' AND archived=0
             AND ((requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?))`,
          [me, peerId, peerId, me]
        )
        if (!f.length) return res.json({ ok: false, error: '需要先加好友' })
      }
      // 2026-07-24 fix-C: 兼容新旧消息 — 既按 peer 维度 (新格式) 也按 room 维度 (老格式)
      //   老格式: peer_type=NULL, peer_id=NULL, 只有 room_id 标识房间 (room 内只有 我+对方 2 个成员)
      //   新格式: peer_type='user', sender_id+peer_id 双向
      //   必须都删,否则老消息残留导致"清除了再点进去还在"
      const [myRooms] = await pool.query(
        `SELECT rm.room_id
         FROM smart_studio_room_members rm
         WHERE rm.user_id=? AND rm.room_id IN (
           SELECT room_id FROM smart_studio_room_members WHERE user_id=?
         )`,
        [me, peerId]
      )
      const roomIds = myRooms.map(r => r.room_id)
      // 1) 按 peer 维度 (新格式)
      const [r1] = await pool.query(
        `DELETE FROM smart_studio_messages
         WHERE peer_type='user'
           AND ((sender_id=? AND peer_id=?) OR (sender_id=? AND peer_id=?))`,
        [me, peerId, peerId, me]
      )
      // 2) 按 room 维度 (老格式, room 内只有 2 成员 = 我和对方, 验证对方是房间另一成员)
      let r2Deleted = 0
      if (roomIds.length) {
        const placeholders = roomIds.map(() => '?').join(',')
        const [r2] = await pool.query(
          `DELETE FROM smart_studio_messages
           WHERE room_id IN (${placeholders})
             AND (peer_type IS NULL OR peer_id IS NULL)`,
          roomIds
        )
        r2Deleted = r2.affectedRows
      }
      const totalDeleted = r1.affectedRows + r2Deleted
      // 实时推送: 双方都推 — 双删时对方在线/刷新/重进都同步清本地缓存
      // 2026-07-25: 波哥需求 "对方除了没办法存在的缓存,刷新或再进入必同步"
      // payload.peer_id 必须对每个接收方用"对方视角的对方 id" 才能让前端 clear handler 匹配上
      //   - 我 (me) 视角: 对方是 peerId
      //   - 对方 (peerId) 视角: 对方是我 (me) — 也就是对方看到 peer_id=me 才能找到我
      broadcastToUser(me,    { type: 'clear', data: { peer_id: peerId, peer_type: 'user' } })
      broadcastToUser(peerId,{ type: 'clear', data: { peer_id: me,     peer_type: 'user' } })
      res.json({ ok: true, deleted: totalDeleted })
    }
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- 置顶消息 ----------
router.post('/messages/:msgId/pin', auth, async (req, res) => {
  try {
    const me = req.userId
    const msgId = parseInt(req.params.msgId, 10)
    const [rows] = await pool.query(
      'SELECT room_id FROM smart_studio_messages WHERE id=?', [msgId]
    )
    if (!rows.length) return res.json({ ok: false, error: '消息不存在' })
    const roomId = rows[0].room_id
    const [ck] = await pool.query(
      'SELECT 1 FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
      [roomId, me]
    )
    if (!ck.length) return res.json({ ok: false, error: '无权操作' })
    await pool.query(
      `INSERT IGNORE INTO smart_studio_pinned_messages (room_id, user_id, message_id)
       VALUES (?, ?, ?)`,
      [roomId, me, msgId]
    )
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.delete('/messages/:msgId/pin', auth, async (req, res) => {
  try {
    const me = req.userId
    const msgId = parseInt(req.params.msgId, 10)
    await pool.query(
      'DELETE FROM smart_studio_pinned_messages WHERE user_id=? AND message_id=?',
      [me, msgId]
    )
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.get('/rooms/:roomId/pinned', auth, async (req, res) => {
  try {
    const me = req.userId
    const roomId = parseInt(req.params.roomId, 10)
    const [ck] = await pool.query(
      'SELECT 1 FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
      [roomId, me]
    )
    if (!ck.length) return res.json({ ok: false, error: '无权访问' })
    const [rows] = await pool.query(
      `SELECT p.message_id, p.pinned_at,
              m.sender_id, m.message_type, m.content, m.image_url, m.created_at
       FROM smart_studio_pinned_messages p
       JOIN smart_studio_messages m ON m.id = p.message_id
       WHERE p.user_id=? AND p.room_id=?
       ORDER BY p.pinned_at DESC`,
      [me, roomId]
    )
    res.json({ ok: true, pinned: rows })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- 引用回复 - 拿到被引用消息的完整信息 ----------
router.get('/messages/:msgId', auth, async (req, res) => {
  try {
    const me = req.userId
    const msgId = parseInt(req.params.msgId, 10)
    const [rows] = await pool.query(
      `SELECT id, room_id, sender_id, message_type, content, image_url, created_at
       FROM smart_studio_messages WHERE id=?`,
      [msgId]
    )
    if (!rows.length) return res.json({ ok: false, error: '消息不存在' })
    const m = rows[0]
    const [ck] = await pool.query(
      'SELECT 1 FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
      [m.room_id, me]
    )
    if (!ck.length) return res.json({ ok: false, error: '无权访问' })
    res.json({ ok: true, message: m })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

export default router
