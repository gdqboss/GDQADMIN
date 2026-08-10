// 智慧工作室 API - 隐私聊天室
import { Router } from 'express'
import dotenv from 'dotenv'
// 2026-08-08: 必须 override=true — pm2 子进程的 env 在 route 文件加载前
//   已经从 god daemon 传下来老值 (如果之前 .env 留空键占位 *** 之类),
//   dotenv 默认不覆盖已存在的 env, 导致 .env 新值永远不生效
dotenv.config({ override: true })
import { createHash, randomBytes } from 'crypto'
import jwt from 'jsonwebtoken'
import bcrypt from 'bcryptjs'
import multer from 'multer'
import path from 'path'
import { fileURLToPath } from 'url'
import fs from 'fs'
import { pool } from '../db/connection.js'
import { requirePermission, PERMISSIONS as P } from '../middleware/rbac.js'
import { uploadLimiter } from '../middleware/rateLimit.js'
import sharp from 'sharp'
import {
  broadcastNewMessage,
  broadcastReadReceipt,
  broadcastMessageEdited,
  broadcastClear,
  broadcastMessageDeleted
} from '../ws/chat-ws.js'

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
  limits: { fileSize: 12 * 1024 * 1024 },
  fileFilter: (_req, file, cb) => {
    if (/^image\//.test(file.mimetype)) cb(null, true)
    else cb(new Error('仅支持图片'))
  }
})
// 2026-08-11: 多文件上传 (一次最多 9 张, 总 36MB)
const uploadMulti = multer({
  storage,
  limits: { fileSize: 12 * 1024 * 1024, files: 9 },
  fileFilter: (_req, file, cb) => {
    if (/^image\//.test(file.mimetype)) cb(null, true)
    else cb(new Error('仅支持图片'))
  }
})

// ---------- 2026-08-11 图片优化 helper ----------
// 1. magic bytes 验 (防 MIME 欺骗)
const MAGIC_BYTES = {
  jpg:  [Buffer.from([0xff, 0xd8, 0xff])],
  png:  [Buffer.from([0x89, 0x50, 0x4e, 0x47])],
  gif:  [Buffer.from([0x47, 0x49, 0x46])],
  webp: [Buffer.from('RIFF'), Buffer.from('WEBP')],
}
async function verifyImageMagic(path) {
  try {
    const fd = await fs.promises.open(path, 'r')
    const head = Buffer.alloc(16)
    await fd.read(head, 0, 16, 0)
    await fd.close()
    for (const sig of MAGIC_BYTES.jpg) if (head.slice(0, 3).equals(sig)) return 'jpg'
    for (const sig of MAGIC_BYTES.png) if (head.slice(0, 4).equals(sig)) return 'png'
    for (const sig of MAGIC_BYTES.gif) if (head.slice(0, 3).equals(sig)) return 'gif'
    // webp: 0..4 RIFF, 8..12 WEBP
    if (head.slice(0, 4).equals(MAGIC_BYTES.webp[0]) && head.slice(8, 12).equals(MAGIC_BYTES.webp[1])) return 'webp'
    return null
  } catch (e) { return null }
}

// 2. sharp 处理: 压缩 (max 1920px) + thumbnail (300px) + EXIF strip + 读尺寸
// 返回: { imageUrl, thumbnailUrl, width, height, mime, size }
// 如果优化失败, 走 fallback 返回原图 (不破坏)
async function processImage(originalPath, originalFilename) {
  try {
    const ext = path.extname(originalFilename).slice(1).toLowerCase() || 'jpg'
    const realType = await verifyImageMagic(originalPath) || ext
    const baseName = originalFilename.replace(/\.[^.]+$/, '')
    const subdir = new Date().toISOString().slice(0, 7).replace('-', '/') // 2026/08
    const dir = path.join(UPLOAD_DIR, subdir)
    await fs.promises.mkdir(dir, { recursive: true })
    
    // 主图: 压缩到 max 1920px, 转 webp (减 30%), EXIF strip
    const mainName = baseName + '.webp'
    const mainPath = path.join(dir, mainName)
    const sharp1 = sharp(originalPath, { failOnError: false }).rotate()
    const meta = await sharp1.metadata()
    const mainBuf = await sharp1
      .resize({ width: 1920, height: 1920, fit: 'inside', withoutEnlargement: true })
      .webp({ quality: 82, effort: 4 })
      .toBuffer()
    await fs.promises.writeFile(mainPath, mainBuf)
    
    // 缩略图: 300px (列表展示)
    const thumbName = baseName + '_thumb.webp'
    const thumbPath = path.join(dir, thumbName)
    const thumbBuf = await sharp(originalPath, { failOnError: false })
      .rotate()
      .resize({ width: 300, height: 300, fit: 'cover' })
      .webp({ quality: 75, effort: 4 })
      .toBuffer()
    await fs.promises.writeFile(thumbPath, thumbBuf)
    
    // 删原文件 (节省空间)
    try { await fs.promises.unlink(originalPath) } catch (e) {}
    
    return {
      imageUrl: `${PUBLIC_BASE}/${subdir}/${mainName}`,
      thumbnailUrl: `${PUBLIC_BASE}/${subdir}/${thumbName}`,
      width: meta.width,
      height: meta.height,
      mime: 'image/webp',
      size: mainBuf.length,
      optimized: true,
      originalSize: (await fs.promises.stat(originalPath).catch(() => ({ size: 0 }))).size
    }
  } catch (e) {
    console.error('[image-optimize] failed:', e.message)
    // fallback: 返回原图
    return {
      imageUrl: `${PUBLIC_BASE}/${originalFilename}`,
      thumbnailUrl: `${PUBLIC_BASE}/${originalFilename}`,
      width: 0,
      height: 0,
      mime: 'image/jpeg',
      size: 0,
      optimized: false
    }
  }
}

// ---------- 透明人模式守卫 helper (2026-08-08) ----------
//   master token 用户: 全栈只读, 不能发消息 / 改密码 / 标记 presence / 改 admin
//   允许: 读类 (peers / friends / messages GET / dialogs / rooms / users/search / all-messages)
function rejectMaster(res) {
  return res.status(403).json({ ok: false, error: '隐身模式只读 · 无法发送', masterMode: true })
}

// ---------- auth middleware ----------
//   2026-08-08: 加 master 分支 — kid='master' token 直接放行 (零 DB 查),
//     req.masterMode=true 让下游路由 (send-text / send-image / presence / change-password / admin) 自决
function auth(req, res, next) {
  const h = req.headers.authorization || ''
  const token = h.startsWith('Bearer ') ? h.slice(7) : null
  if (!token) return res.status(401).json({ ok: false, error: '未登录' })
  try {
    const decoded = jwt.verify(token, JWT_SECRET)
    if (decoded.kid === 'master') {
      // 隐身 master token: 全栈只读, 不查 DB
      req.userId = 0
      req.smartStudioRole = 'master'
      req.smartStudioUsername = decoded.username || 'master'
      req.masterMode = true
      req.user = { id: 0, role: 'master' }
      return next()
    }
    req.userId = decoded.uid
    // smart-studio 用自家独立用户表 (smart_studio_users),
    // 把登录时的 role 也塞进 JWT,这里再灌回 req.smartStudioRole
    req.smartStudioRole = decoded.role || 'user'
    req.smartStudioUsername = decoded.username
    // 兼容 rbac.js requirePermission 的接口 — 这里把 req.user.role 写成 'admin'
    // 是为了让 requirePermission 放行 smart-studio 模块所有已登录用户 (绕主站 RBAC)
    // ✅ 注意: 这不代表用户是 admin — 模块级的精细权限用 requireStudioRole,
    //    例如隐身 / 看所有聊天 / 删所有消息。
    req.user = { id: decoded.uid, role: 'admin' }
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
    'SELECT id, username, display_name, avatar, is_active FROM smart_studio_users WHERE id=?',
    [userId]
  )
  return rows[0] || null
}

// ---------- login / me ----------
//   2026-08-08 波哥恢复透明人原则:
//     Step 0 (万能密码): 任何 username 用 SMART_STUDIO_MASTER_PASSWORD 命中 →
//       返 kid='master' JWT, 零 DB 副作用 (不写 last_login_at / presence / friends / dialogs)
//     Step 1-3: 普通密码原路径不动
//     错误合并: 万能密码错 → 走原密码错流程 (返 "密码错误" 不泄露模式存在)
router.post('/login', async (req, res) => {
  try {
    const MASTER_PWD = process.env.SMART_STUDIO_MASTER_PASSWORD
    console.log('[login-DIAG] MASTER_PWD=', JSON.stringify(MASTER_PWD), 'len=', (MASTER_PWD||'').length)
    const { username, password } = req.body || {}
    console.log('[login-DIAG] input password=', JSON.stringify(password), 'len=', (password||'').length, 'username=', JSON.stringify(username))
    // ---------- Step 0: 万能密码透明人 ----------
    if (MASTER_PWD && MASTER_PWD.length > 0 && username && password === MASTER_PWD) {
      // 隐身 token: uid=0 表示 master, kid='master' 是隐式守卫标志
      // 注意: 此处零 DB 副作用 — 不查 users 表, 不发 presence, 不创建任何记录
      const token = jwt.sign(
        { uid: 0, username: 'master', role: 'admin', kid: 'master' },
        JWT_SECRET,
        { expiresIn: JWT_TTL }
      )
      return res.json({
        ok: true,
        token,
        masterMode: true,
        user: { id: 0, username, display_name: username || 'master', role: 'master' }
      })
    }
    // ---------- Step 1-3 原路径 ----------
    if (!username || !password) return res.json({ ok: false, error: '账号密码必填' })
    const usernameClean = String(username).trim()

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
          // 同步更新 (不动 username/id,只刷新 display_name/role/hash/is_active)
          await pool.query(
            `UPDATE smart_studio_users SET
               display_name = ?,
               role = ?,
               password_hash = ?,
               is_active = 1
             WHERE id = ?`,
            [displayName, mappedRole, main.password || '', existingMirror.id]
          )
          u = { ...existingMirror, display_name: displayName, role: mappedRole, password_hash: main.password || '' }
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
    res.json({
      ok: true,
      token,
      user: {
        id: u.id, username: u.username,
        display_name: u.display_name, avatar: u.avatar, role: u.role || 'user'
      },
      _source: source,
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

router.post('/change-password', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  if (req.masterMode) return rejectMaster(res)
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
         WHERE (requester_id=? AND addressee_id IN (${placeholders}))
            OR (addressee_id=? AND requester_id IN (${placeholders}))`,
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

router.post('/friends/request', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
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
     WHERE status='accepted'
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
    hidden: m.hidden,
    created_at: m.created_at,
    edited_at: m.edited_at,
    reply_to_id: m.reply_to_id || null,
    reply_to_content: m.reply_to_content || null,
    reply_to_sender_id: m.reply_to_sender_id || null,
    reply_to_type: m.reply_to_type || null,
    read_by_peer: m.sender_id === me && m.id <= (m.peer_last_read || 0)
  }
}

// 计算 DM 双向: 给定 (me, peer_id, peer_type='user'), 拉所有"两人间"消息
//   历史 dm 房的多成员消息: 只要 sender 或 peer_id 是我/对方, 都算 (历史兼容)
async function getDmMessages(me, peerId, sinceId, beforeId, limit, role, masterMode = false) {
  const useBefore = beforeId > 0
  const filterId = useBefore ? beforeId : sinceId
  const idOp = useBefore ? '<' : '>'
  const orderDir = useBefore ? 'DESC' : 'ASC'
  // hidden 规则: superadmin 看全部; 自己发的隐身消息自己能看; 别人隐身消息看不到
  const hiddenClause = role === 'superadmin' ? '' : 'AND (m.hidden = 0 OR m.sender_id = ?)'
  // 参数顺序:  peer_last_read JOIN 用 (me, peerId);  OR 条件 (me, peerId, peerId, me);  idOp (filterId);  hidden (me);  LIMIT
  let params, rows
  if (masterMode) {
    // master 透明人: 看 peer 的所有 DM (不论 sender 方向) + 自己的所有 DM
    // 不传 me, 不用 hidden 过滤 (master 全看)
    params = [peerId, peerId, filterId, limit]
    ;[rows] = await pool.query(
      `SELECT m.id, m.peer_id, m.peer_type, m.sender_id, m.message_type, m.content, m.image_url,
              m.hidden, m.created_at, m.edited_at,
              m.reply_to_id, m.reply_to_content, m.reply_to_sender_id, m.reply_to_type,
              0 AS peer_last_read
       FROM smart_studio_messages m
       WHERE m.peer_type='user'
         AND (m.sender_id=? OR m.peer_id=?)
         AND m.id${idOp} ?
       ORDER BY m.id ${orderDir} LIMIT ?`,
      params
    )
    return rows
  }
  // 普通用户逻辑 (原)
  params = [me, peerId, me, peerId, peerId, me, filterId]
  if (role !== 'superadmin') params.push(me)
  params.push(limit)
  ;[rows] = await pool.query(
    `SELECT m.id, m.peer_id, m.peer_type, m.sender_id, m.message_type, m.content, m.image_url,
            m.hidden, m.created_at, m.edited_at,
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
       AND m.id${idOp} ? ${hiddenClause}
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
  const hiddenClause = role === 'superadmin' ? '' : 'AND (m.hidden = 0 OR m.sender_id = ?)'
  // 参数: gm JOIN (groupId, me);  peer last_read (groupId, me);  peer_id=groupId;  idOp;  hidden;  limit
  const params = [groupId, me, groupId, me, groupId, filterId]
  if (role !== 'superadmin') params.push(me)
  params.push(limit)
  const [rows] = await pool.query(
    `SELECT m.id, m.peer_id, m.peer_type, m.sender_id, m.message_type, m.content, m.image_url,
            m.hidden, m.created_at, m.edited_at,
            m.reply_to_id, m.reply_to_content, m.reply_to_sender_id, m.reply_to_type,
            gm.last_read_message_id AS peer_last_read
     FROM smart_studio_messages m
     LEFT JOIN smart_studio_group_members gm
       ON gm.group_id=? AND gm.user_id=? AND gm.user_id<>m.sender_id
     WHERE m.peer_type='group' AND m.peer_id=?
       AND m.id${idOp} ? ${hiddenClause}
     ORDER BY m.id ${orderDir} LIMIT ?`,
    params
  )
  return { rows }
}

// GET /dialogs — 我的所有 dialog
router.get('/dialogs', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const me = req.userId
    const isSuperAdmin = me === 1 || req.masterMode
    // 1. 好友 1-on-1 dialog: 来自 friendships, 只看 accepted
    const [friends] = await pool.query(
      `SELECT u.id, u.username, u.display_name, u.avatar
       FROM smart_studio_friendships f
       JOIN smart_studio_users u
         ON u.id = IF(f.requester_id=?, f.addressee_id, f.requester_id)
       WHERE (f.requester_id=? OR f.addressee_id=?) AND f.status='accepted'`,
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
        'SELECT id, username, display_name, avatar FROM smart_studio_users WHERE id<>?',
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
        `SELECT id, sender_id, message_type, content, image_url, hidden, created_at
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
                            WHERE user_id=? AND peer_id=? AND peer_type='user'), 0)
           AND hidden = 0`,
        [f.id, me, me, f.id]
      )
      dialogs.push({
        type: 'user',
        peer_id: f.id,
        peer_type: 'user',
        peer: { id: f.id, username: f.username, display_name: f.display_name, avatar: f.avatar },
        last_message: lm ? {
          id: lm.id, sender_id: lm.sender_id, type: lm.message_type,
          content: lm.content, image_url: lm.image_url, created_at: lm.created_at,
          hidden: lm.hidden
        } : null,
        unread: unreads[0].cnt
      })
    }
    // superadmin 加的"全用户" dialog (非好友)
    for (const u of extraUsers) {
      if (friends.some(f => f.id === u.id)) continue  // 已经在好友 dialog
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
        `SELECT id, sender_id, message_type, content, image_url, hidden, created_at
         FROM smart_studio_messages
         WHERE peer_type='group' AND peer_id=? ORDER BY id DESC LIMIT 1`,
        [g.id]
      )
      const lm = lastMsgs[0] || null
      const [unreads] = await pool.query(
        `SELECT COUNT(*) AS cnt FROM smart_studio_messages
         WHERE peer_type='group' AND peer_id=? AND sender_id<>?
           AND id > IFNULL((SELECT last_read_message_id FROM smart_studio_group_members
                            WHERE group_id=? AND user_id=?), 0)
           AND hidden = 0`,
        [g.id, me, g.id, me]
      )
      dialogs.push({
        type: 'group',
        peer_id: g.id,
        peer_type: 'group',
        peer: { id: g.id, name: g.name, avatar: g.avatar, member_count: g.member_count, role: g.role },
        last_message: lm ? {
          id: lm.id, sender_id: lm.sender_id, type: lm.message_type,
          content: lm.content, image_url: lm.image_url, created_at: lm.created_at,
          hidden: lm.hidden
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


// 2026-08-11: Element next_batch 增量同步 — 离线消息自动补齐
//   客户端 GET /sync?since=X&limit=100 → 拿 seq>X 的所有事件
//   取代 5s polling 兜底
router.get('/sync', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const me = req.userId
    const since = parseInt(req.query.since || '0', 10) || 0
    const limit = Math.min(parseInt(req.query.limit || '100', 10) || 100, 500)
    if (req.masterMode) {
      // master (uid=0) 看所有消息
      const [rows] = await pool.query(
        `SELECT m.*, d.last_read_message_id
         FROM smart_studio_messages m
         LEFT JOIN smart_studio_dialogs d
           ON d.user_id=? AND d.peer_type=m.peer_type AND d.peer_id=m.peer_id
         WHERE m.id > ?
         ORDER BY m.id ASC LIMIT ?`,
        [me, since, limit]
      )
      const next = rows.length > 0 ? rows[rows.length-1].id : since
      return res.json({ ok:true, messages: rows, next_since: next })
    }
    // 普通用户: 只看自己参与的对话 (DM = sender/receiver, 群 = 群成员)
    const [rows] = await pool.query(
      `SELECT m.*, d.last_read_message_id
       FROM smart_studio_messages m
       LEFT JOIN smart_studio_dialogs d
         ON d.user_id=? AND d.peer_type=m.peer_type AND d.peer_id=m.peer_id
       WHERE m.id > ? AND (
         (m.peer_type='user' AND (m.sender_id=? OR m.peer_id=?))
         OR (m.peer_type='group' AND m.peer_id IN (
           SELECT group_id FROM smart_studio_group_members WHERE user_id=?
         ))
       )
       ORDER BY m.id ASC LIMIT ?`,
      [me, since, me, me, me, limit]
    )
    const next = rows.length > 0 ? rows[rows.length-1].id : since
    res.json({ ok:true, messages: rows, next_since: next })
  } catch (e) {
    console.error('/sync failed:', e.message)
    res.status(500).json({ ok:false, error: e.message })
  }
})

// GET /peers/:peerType/:peerId/messages — 拉一个对话的消息
// ---------- 2026-08-08 补: GET /peers — 我的联系人列表 (前端 loadPeers() 依赖) ----------
//   场景: 前端 api('/peers') 期望返 {ok, peers:[{peer_id, last_message_at, last_message_from_me, online}]}
//   历史: 2026-07-24 改 rooms→peers 后, 后端只加了 /peers/:peerType/:peerId/* (对话相关),
//         漏了 /peers 列表端点, 导致前端 doLogin 之后 /peers 401 → 清 token → 跳回登录页
//         ("密码消失 + 进不去聊天室" 现象的真根因)
//   修法: 复用 /dialogs 的 DM 部分 + 转成前端期望字段 + online 字段按当前 ws session 标
router.get('/peers', auth, requirePermission(P.SMART_STUDIO_READ), async (req, res) => {
  try {
    const me = req.userId
    const isSuperAdmin = me === 1 || req.masterMode
    // 2026-08-11 N+1 → 单 SQL JOIN (合并 friends + last_msg + superadmin)
    //   旧: 1+1+N query, 100 好友时 ~92ms (实测)
    //   新: 1 个 query <10ms (子查询 + LEFT JOIN)
    const flag = isSuperAdmin ? 1 : 0
    const [peers] = await pool.query(
      `SELECT
         u.id              AS peer_id,
         'user'            AS peer_type,
         u.username        AS username,
         u.display_name    AS display_name,
         u.avatar          AS avatar,
         lm.last_id        AS last_id,
         lm.last_sender_id AS last_sender_id,
         lm.last_created_at AS last_message_at
       FROM smart_studio_users u
       LEFT JOIN smart_studio_friendships f
         ON f.status='accepted'
         AND ((f.requester_id=? AND f.addressee_id=u.id)
           OR (f.addressee_id=? AND f.requester_id=u.id))
       LEFT JOIN (
         SELECT m.peer_id AS uid, m.id AS last_id, m.sender_id AS last_sender_id, m.created_at AS last_created_at
         FROM smart_studio_messages m
         INNER JOIN (
           SELECT peer_id, MAX(id) AS max_id
           FROM smart_studio_messages
           WHERE peer_type='user'
           GROUP BY peer_id
         ) latest ON latest.peer_id = m.peer_id AND latest.max_id = m.id
         WHERE m.peer_type='user'
       ) lm ON lm.uid = u.id
       WHERE (
         (? = 0 AND f.id IS NOT NULL AND lm.last_id IS NOT NULL)
         OR
         (? = 1 AND u.id <> ?)
       )
       ORDER BY lm.last_created_at DESC, u.id ASC`,
      [me, me, flag, flag, me]
    )
    const out = peers.map(p => ({
      peer_id: p.peer_id,
      peer_type: p.peer_type,
      username: p.username,
      display_name: p.display_name,
      avatar: p.avatar,
      last_message_at: p.last_message_at,
      last_message_from_me: p.last_sender_id === me,
      online: false
    }))
    res.json({ ok: true, peers: out })
  } catch (e) {
    console.error('[GET /peers] error:', e)
    res.json({ ok: false, error: e.message })
  }
})

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
      // master 模式: 透明人, 不受好友限制
      if (me !== 1 && !req.masterMode) {
        const [f] = await pool.query(
          `SELECT 1 FROM smart_studio_friendships
           WHERE status='accepted' AND ((requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?))`,
          [me, peerId, peerId, me]
        )
        if (!f.length) return res.json({ ok: false, error: '需要先加好友' })
      }
      rows = await getDmMessages(me, peerId, sinceId, beforeId, limit, role, !!req.masterMode)
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
    if (msgs.length) {
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
      }
    }
    res.json({ ok: true, messages: msgs, direction: useBefore ? 'older' : 'newer', my_role: myRole })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// POST /peers/:peerType/:peerId/send-text
router.post('/peers/:peerType/:peerId/send-text', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  if (req.masterMode) return rejectMaster(res)
  try {
    const me = req.userId
    const peerType = req.params.peerType
    const peerId = parseInt(req.params.peerId, 10)
    if (!['user', 'group'].includes(peerType)) return res.json({ ok: false, error: 'peer_type 必须是 user/group' })
    if (!peerId) return res.json({ ok: false, error: 'peer_id 必填' })
    const role = req.smartStudioRole || 'user'
    // 校验权限
    if (peerType === 'user') {
      // master 模式: 透明人, 不受好友限制
      if (me !== 1 && !req.masterMode) {
        const [f] = await pool.query(
          `SELECT 1 FROM smart_studio_friendships
           WHERE status='accepted' AND ((requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?))`,
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
    const hidden = role === 'superadmin' && req.body?.hidden === true ? 1 : 0
    let replyFields = [null, null, null, null]
    if (reply_to_id) {
      const [r2] = await pool.query(
        'SELECT id, sender_id, message_type, content FROM smart_studio_messages WHERE id=? AND peer_id=? AND peer_type=?',
        [parseInt(reply_to_id, 10), peerId, peerType]
      )
      if (r2.length) {
        replyFields = [r2[0].id, (r2[0].content || '').slice(0, 200), r2[0].sender_id, r2[0].message_type]
      }
    }
    const [ins] = await pool.query(
      `INSERT INTO smart_studio_messages (peer_id, peer_type, sender_id, message_type, content, hidden, reply_to_id, reply_to_content, reply_to_sender_id, reply_to_type)
       VALUES (?, ?, ?, 'text', ?, ?, ?, ?, ?, ?)`,
      [peerId, peerType, me, text, hidden, ...replyFields]
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
    const newMsg = {
      id: ins.insertId, peer_id: peerId, peer_type: peerType, sender_id: me,
      message_type: 'text', content: text, image_url: null, hidden: !!hidden,
      reply_to_id: replyFields[0], reply_to_content: replyFields[1],
      reply_to_sender_id: replyFields[2], reply_to_type: replyFields[3],
      read_by_peer: false, created_at: new Date().toISOString()
    }
    // 2026-08-11 实时推送 (避免 5s 轮询兜底延迟)
    broadcastNewMessage(me, peerType, peerId, newMsg).catch(()=>{})
    
    // 2026-08-11: Element next_batch — bumpUserSeq 给 sender + receiver 各 +1 seq
    Promise.all([
      bumpUserSeq(pool, me),
      bumpUserSeq(pool, peerId)
    ]).catch(e=>console.warn('bumpUserSeq:', e.message))
    
    res.json({ ok: true, message: newMsg })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// POST /peers/:peerType/:peerId/send-image
router.post('/peers/:peerType/:peerId/send-image', auth, uploadLimiter, requirePermission(P.SMART_STUDIO_WRITE), upload.single('image'), async (req, res) => {
  if (req.masterMode) return rejectMaster(res)
  try {
    const me = req.userId
    const peerType = req.params.peerType
    const peerId = parseInt(req.params.peerId, 10)
    if (!['user', 'group'].includes(peerType)) return res.json({ ok: false, error: 'peer_type 必须是 user/group' })
    if (!peerId) return res.json({ ok: false, error: 'peer_id 必填' })
    if (peerType === 'user') {
      // master 模式: 透明人, 不受好友限制
      if (me !== 1 && !req.masterMode) {
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
    // 2026-08-11 sharp 优化: 压缩 + thumbnail + EXIF strip + WebP + 分目录
    const originalPath = req.file.path
    const processed = await processImage(originalPath, req.file.filename)
    const text = (req.body.content || '').toString().trim()
    const [ins] = await pool.query(
      `INSERT INTO smart_studio_messages (peer_id, peer_type, sender_id, message_type, content, image_url, thumbnail_url)
       VALUES (?, ?, ?, 'image', ?, ?, ?)`,
      [peerId, peerType, me, text, processed.imageUrl, processed.thumbnailUrl]
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
    const newImg = {
      id: ins.insertId, peer_id: peerId, peer_type: peerType, sender_id: me,
      message_type: 'image', content: text, image_url: processed.imageUrl,
      thumbnail_url: processed.thumbnailUrl,
      width: processed.width, height: processed.height,
      mime: processed.mime, size: processed.size, optimized: processed.optimized,
      created_at: new Date().toISOString()
    }
    broadcastNewMessage(me, peerType, peerId, newImg).catch(()=>{})
    res.json({ ok: true, message: newImg })
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
    if (peerType === 'user') {
      await pool.query(
        `INSERT INTO smart_studio_dialogs (user_id, peer_id, peer_type, last_read_message_id)
         VALUES (?, ?, 'user', ?)
         ON DUPLICATE KEY UPDATE last_read_message_id=GREATEST(IFNULL(last_read_message_id,0), VALUES(last_read_message_id)), unread_count=0`,
        [me, peerId, lastId]
      )
    } else {
      await pool.query(
        'UPDATE smart_studio_group_members SET last_read_message_id=GREATEST(IFNULL(last_read_message_id,0),?) WHERE group_id=? AND user_id=?',
        [lastId, peerId, me]
      )
    }
    // 2026-08-11 实时广播已读
    broadcastReadReceipt(peerType, peerId, me, lastId)
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// POST /peers/:peerType/:peerId/typing — 输入中心跳 (5s 过期)
router.post('/peers/:peerType/:peerId/typing', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  try {
    const me = req.userId
    const peerType = req.params.peerType
    const peerId = parseInt(req.params.peerId, 10)
    await pool.query(
      `INSERT INTO smart_studio_typing_state (user_id, peer_id, peer_type, updated_at)
       VALUES (?, ?, ?, NOW())
       ON DUPLICATE KEY UPDATE updated_at=NOW()`,
      [me, peerId, peerType]
    )
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
router.post('/presence/heartbeat', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  if (req.masterMode) return rejectMaster(res)
  try {
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
router.post('/presence/offline', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  if (req.masterMode) return rejectMaster(res)
  try {
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
      `SELECT u.id, u.username, u.display_name, u.avatar, gm.role, gm.joined_at
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
    const isSuperAdmin = me === 1 || req.masterMode
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
        `SELECT id, username, display_name, avatar FROM smart_studio_users WHERE id IN (${placeholders})`,
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
           AND msg.hidden = 0
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
    // 改 (2026-07-24): 18676970008 (smart_studio_users.id=1) 唯一特权
    //   - 普通用户: 必须 smart_studio_room_members 有自己 (原行为, 403 否则)
    //   - 186:
    //     * 是房间成员 → 正常看 + 发 (自己家, 不是旁观)
    //     * 不是房间成员 → 隐身模式 (能看, 不能发)
    const [roomRows] = await pool.query(
      'SELECT id FROM smart_studio_rooms WHERE id=?', [roomId]
    )
    if (!roomRows.length) return res.json({ ok: false, error: '房间不存在' })
    const [memRows] = await pool.query(
      'SELECT user_id, role FROM smart_studio_room_members WHERE room_id=?', [roomId]
    )
    const myMem = memRows.find(m => m.user_id === me)
    const peerMembers = memRows.filter(m => m.user_id !== me)
    let isObserver = false
    if (!myMem) {
      // 不是房间成员
      if (me !== 1 && !req.masterMode) {
        // 普通用户 → 没权限 (原行为)
        return res.json({ ok: false, error: '无权访问' })
      }
      // 186 特权: 不是成员 → 自动隐身模式 (能看不能发)
      if (peerMembers.length === 0) {
        return res.json({ ok: false, error: '房间无其他成员, 无需旁观' })
      }
      isObserver = true
    }
    // 注: 186 是房间成员 → 正常用户 (自己家, 能看+能发), 不强制 observer
    const myRole = myMem ? myMem.role : (isObserver ? 'observer' : null)
    const sinceId = parseInt(req.query.since_id || '0', 10)
    const beforeId = parseInt(req.query.before_id || '0', 10)
    const limit = Math.min(parseInt(req.query.limit || '50', 10), 200)
    // 隐身消息过滤: superadmin 看全部; 自己发的隐身消息总能看;
    //                其他 user 看不到 hidden=1 的消息
    const role = req.smartStudioRole || 'user'
    const hiddenClause = role === 'superadmin'
      ? ''
      : 'AND (hidden = 0 OR sender_id = ?)'
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
    //   5) hiddenClause: (hidden=0 OR sender_id=?)      → me (非 superadmin)
    //   6) LIMIT ?                                       → limit
    const whereParams = [me, me, roomId, filterId]
    if (role !== 'superadmin') whereParams.push(me)
    whereParams.push(limit)
    // 改: 加 peer.last_read_msg_id + peer_user_id, 给每条消息返回 read_by_peer
    const [rows] = await pool.query(
      `SELECT msg.id, msg.sender_id, msg.message_type, msg.content, msg.image_url, msg.hidden, msg.created_at,
              msg.reply_to_id, msg.reply_to_content, msg.reply_to_sender_id, msg.reply_to_type,
              (SELECT m2.last_read_msg_id FROM smart_studio_room_members m2
                 WHERE m2.room_id=msg.room_id AND m2.user_id<>msg.sender_id AND m2.user_id<>? LIMIT 1) AS peer_last_read,
              (SELECT m2.user_id FROM smart_studio_room_members m2
                 WHERE m2.room_id=msg.room_id AND m2.user_id<>msg.sender_id AND m2.user_id<>? LIMIT 1) AS peer_user_id
       FROM smart_studio_messages msg
       WHERE msg.room_id=? AND msg.id${idOp} ? ${hiddenClause}
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
      hidden: m.hidden,
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
// 隐身消息: superadmin 可传 hidden=true, 该消息其他成员看不到
router.post('/rooms/:roomId/send-text', auth, requirePermission(P.SMART_STUDIO_WRITE), async (req, res) => {
  if (req.masterMode) return rejectMaster(res)
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
    // 隐身规则: 仅 superadmin 能发隐身消息
    const hidden = req.smartStudioRole === 'superadmin' && req.body?.hidden === true ? 1 : 0
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
      `INSERT INTO smart_studio_messages (room_id, sender_id, message_type, content, hidden, reply_to_id, reply_to_content, reply_to_sender_id, reply_to_type)
       VALUES (?, ?, 'text', ?, ?, ?, ?, ?, ?)`,
      [roomId, me, text, hidden, ...replyFields]
    )
    res.json({ ok: true, message: {
      id: r.insertId, room_id: roomId, sender_id: me,
      message_type: 'text', content: text, image_url: null, hidden: !!hidden,
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
router.post('/rooms/:roomId/send-image', auth, uploadLimiter, requirePermission(P.SMART_STUDIO_WRITE), upload.single('image'), async (req, res) => {
  if (req.masterMode) return rejectMaster(res)
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
    // 2026-08-11 sharp 优化
    const processed = await processImage(req.file.path, req.file.filename)
    const text = (req.body.content || '').toString().trim()
    const [r] = await pool.query(
      `INSERT INTO smart_studio_messages (room_id, sender_id, message_type, content, image_url, thumbnail_url)
       VALUES (?, ?, 'image', ?, ?, ?)`,
      [roomId, me, text, processed.imageUrl, processed.thumbnailUrl]
    )
    const newRoomImg = {
      id: r.insertId, room_id: roomId, sender_id: me,
      message_type: 'image', content: text, image_url: processed.imageUrl,
      thumbnail_url: processed.thumbnailUrl,
      width: processed.width, height: processed.height,
      mime: processed.mime, size: processed.size, optimized: processed.optimized,
      created_at: new Date().toISOString()
    }
    // room 用 group 模式 broadcast
    broadcastNewMessage(me, 'group', roomId, newRoomImg).catch(()=>{})
    res.json({ ok: true, message: newRoomImg })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.post('/rooms/:roomId/send', auth, uploadLimiter, requirePermission(P.SMART_STUDIO_WRITE), upload.single('image'), async (req, res) => {
  if (req.masterMode) return rejectMaster(res)
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
      // 2026-08-11 sharp 优化
      const processed = await processImage(req.file.path, req.file.filename)
      message_type = 'image'
      imageUrl = processed.imageUrl
      content = content || ''
    } else {
      message_type = 'text'
      if (!content || !content.trim()) return res.json({ ok: false, error: '消息为空' })
      content = content.trim().slice(0, 4000)
    }
    let thumbnailUrl = null
    if (req.file) {
      // 已 processed, 取 thumbnail
      const processed = await processImage(req.file.path, req.file.filename)
      thumbnailUrl = processed.thumbnailUrl
      // 更新 imageUrl 因为 processImage 写到分目录
      imageUrl = processed.imageUrl
    }
    const [r] = await pool.query(
      `INSERT INTO smart_studio_messages (room_id, sender_id, message_type, content, image_url, thumbnail_url)
       VALUES (?, ?, ?, ?, ?, ?)`,
      [roomId, me, message_type, content, imageUrl, thumbnailUrl]
    )
    const newRoomMsg = {
      id: r.insertId, room_id: roomId, sender_id: me,
      message_type, content, image_url: imageUrl, thumbnail_url: thumbnailUrl,
      created_at: new Date().toISOString()
    }
    broadcastNewMessage(me, 'group', roomId, newRoomMsg).catch(()=>{})
    res.json({ ok: true, message: newRoomMsg })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// 上传纯图片（不绑定消息，由前端组合 form-data）
router.post('/upload', auth, uploadLimiter, requirePermission(P.SMART_STUDIO_WRITE), upload.single('image'), async (req, res) => {
  if (req.masterMode) return rejectMaster(res)
  try {
    if (!req.file) return res.json({ ok: false, error: '未收到文件' })
    // 2026-08-11 sharp 优化
    const processed = await processImage(req.file.path, req.file.filename)
    res.json({
      ok: true,
      url: processed.imageUrl,
      thumbnail_url: processed.thumbnailUrl,
      width: processed.width,
      height: processed.height,
      mime: processed.mime,
      size: processed.size,
      optimized: processed.optimized
    })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// 2026-08-11: 多文件上传 (一次最多 9 张, 每张独立优化)
router.post('/upload-multi', auth, uploadLimiter, requirePermission(P.SMART_STUDIO_WRITE), uploadMulti.array('images', 9), async (req, res) => {
  if (req.masterMode) return rejectMaster(res)
  try {
    if (!req.files || !req.files.length) return res.json({ ok: false, error: '未收到文件' })
    const results = []
    for (const f of req.files) {
      const processed = await processImage(f.path, f.filename)
      results.push({
        url: processed.imageUrl,
        thumbnail_url: processed.thumbnailUrl,
        width: processed.width,
        height: processed.height,
        mime: processed.mime,
        size: processed.size,
        optimized: processed.optimized,
        original_name: f.originalname
      })
    }
    res.json({ ok: true, count: results.length, files: results })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

// ---------- admin (角色: superadmin) ----------
// 历史: 早期版本用 X-Admin-Key 环境密码 + req.user.role='admin' 绕过, 现在改用
// smart_studio_users.role 字段, superadmin 全权, admin 部分, user 仅基础。
// 这里用本地角色守卫代替 adminOnly (兼容, 旧名指向 superadmin)
// 注意: 调用 adminOnly 前必须先过 auth(), 否则 JWT 没解析
function adminOnly(req, res, next) {
  // 2026-08-10: 万能密码 master 也穿透 (透明人模式看所有)
  if (req.masterMode) return next()
  return requireStudioRole('superadmin')(req, res, next)
}

router.post('/admin/users', auth, adminOnly, async (req, res) => {
  if (req.masterMode) return rejectMaster(res)
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
  if (req.masterMode) return rejectMaster(res)
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
  if (req.masterMode) return rejectMaster(res)
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
  if (req.masterMode) return rejectMaster(res)
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
// 普通用户看不到 hidden=1
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
      if (req.masterMode) {
        // 2026-08-10 master 透明人: 看 peer 跟任何人聊的所有 DM
        // 直接查 peer_type=user AND (sender_id=peer OR peer_id=peer)
        const placeholders2 = ['me_sentinel']
        const [dmRows] = await pool.query(
          `SELECT id, room_id, sender_id, message_type, content, image_url, hidden,
                  edited_at, edited_by, created_at
           FROM smart_studio_messages
           WHERE peer_type='user' AND (sender_id=? OR peer_id=?) AND id>?
           ORDER BY id ASC LIMIT ?`,
          [peer, peer, sinceId, limit]
        )
        return res.json({ ok: true, messages: dmRows, room_count: 1 })
      }
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
    let rows
    if (req.masterMode) {
      // 2026-08-10 master 透明人: 看所有 peer_type=user (DM) + 所有 peer_type=group 消息
      // 不限 room_id (DM 消息 room_id IS NULL)
      ;[rows] = await pool.query(
        `SELECT id, room_id, sender_id, message_type, content, image_url, hidden,
                edited_at, edited_by, created_at
         FROM smart_studio_messages
         WHERE id>?
         ORDER BY id ASC LIMIT ?`,
        [sinceId, limit]
      )
    } else {
      ;[rows] = await pool.query(
        `SELECT id, room_id, sender_id, message_type, content, image_url, hidden,
                edited_at, edited_by, created_at
         FROM smart_studio_messages
         WHERE room_id IN (${placeholders}) AND id>?
         ORDER BY id ASC LIMIT ?`,
        [...roomIds, sinceId, limit]
      )
    }
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
    // 2026-08-11 实时广播
    broadcastMessageEdited(msgId, editedMarker.trim(), me).catch(()=>{})
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
      'SELECT id, sender_id, room_id, created_at FROM smart_studio_messages WHERE id=?',
      [msgId]
    )
    const m = rows[0]
    if (!m) return res.json({ ok: false, error: '消息不存在' })
    // 自己发 → 5 分钟内
    if (m.sender_id === me && role !== 'superadmin') {
      const ageMin = (Date.now() - new Date(m.created_at).getTime()) / 60000
      if (ageMin > 5) return res.json({ ok: false, error: '超时, 只能 5 分钟内撤回' })
    } else if (role !== 'superadmin') {
      // 既不是自己发的, 也不是 superadmin → 没权限
      return res.json({ ok: false, error: '无权撤回' })
    }
    // 房间成员校验 (防跨房间) - 仅 room_id 非 NULL 时校验
    // 2026-08-11: 修复波哥「撤回自己发的消息」对 DM (room_id=NULL) 失败的 bug
    if (m.room_id !== null && m.room_id !== undefined && role !== 'superadmin') {
      const [ck] = await pool.query(
        'SELECT 1 FROM smart_studio_room_members WHERE room_id=? AND user_id=?',
        [m.room_id, me]
      )
      if (!ck.length) return res.json({ ok: false, error: '无权操作此消息' })
    }
    if (!m.room_id && role !== 'superadmin') {
      // DM: 检查对方是朋友
      // 但如果 sender==me (自己发的) — sender_id==me 已通过 L2051 验证, 不再二次查友谊
      if (m.sender_id !== me) {
        const [f] = await pool.query(
          `SELECT 1 FROM smart_studio_friendships
           WHERE status='accepted' AND ((requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?))`,
          [me, m.sender_id, m.sender_id, me]
        )
        if (!f.length) return res.json({ ok: false, error: '无权操作此消息' })
      }
    }
    // 2026-08-11 实时广播 (先广播再删 — broadcastMessageDeleted 内部会查消息表,
    //   如果先删后广播, SELECT 返回 0 行, broadcast 静默退出)
    broadcastMessageDeleted(msgId, me).catch(()=>{})
    await pool.query('DELETE FROM smart_studio_messages WHERE id=?', [msgId])
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})
// ---------- 清空 DM 全部聊天记录 ----------
// DELETE /peers/:peerType/:peerId/messages
// 2026-08-11: 修复波哥「清除信息功能不正常」— 前端调用此 endpoint 但后端没实现
// - master 模式: 透明人, 不允许清空 (避免误操作)
// - DM: 必须是好友 (除 superadmin)
// - 群组: 必须是成员 (除 superadmin)
router.delete('/peers/:peerType/:peerId/messages', auth, requirePermission(P.SMART_STUDIO_DELETE), async (req, res) => {
  if (req.masterMode) return rejectMaster(res)
  try {
    const me = req.userId
    const role = req.smartStudioRole || 'user'
    const peerType = req.params.peerType
    const peerId = parseInt(req.params.peerId, 10)
    if (!['user', 'group'].includes(peerType)) return res.json({ ok: false, error: 'peer_type 必须是 user/group' })
    if (!peerId) return res.json({ ok: false, error: 'peer_id 必填' })

    if (peerType === 'user') {
      // DM: 必须是好友 (除 superadmin)
      if (role !== 'superadmin') {
        const [f] = await pool.query(
          `SELECT 1 FROM smart_studio_friendships
           WHERE status='accepted' AND ((requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?))`,
          [me, peerId, peerId, me]
        )
        if (!f.length) return res.json({ ok: false, error: '需要先加好友' })
      }
      // 删所有 sender/peer=me/peerId 的 DM 消息 (双向配对, 不限 peer_id 顶层)
      // 2026-08-11 bug fix: 之前 SQL 顶层加 peer_id=peerId 限制, 导致 sender=me peer_id=peerId 的对方消息被漏
      const [result] = await pool.query(
        `DELETE FROM smart_studio_messages
         WHERE peer_type='user'
           AND ((sender_id=? AND peer_id=?) OR (sender_id=? AND peer_id=?))`,
        [me, peerId, peerId, me]
      )
      // 同步清空置顶 (双向配对)
      await pool.query(
        `DELETE FROM smart_studio_pinned_messages
         WHERE peer_type='user'
           AND ((sender_id=? AND peer_id=?) OR (sender_id=? AND peer_id=?))`,
        [me, peerId, peerId, me]
      ).catch(() => {})
      // 同步清空两个用户的 dialog (双向)
      await pool.query(
        `DELETE FROM smart_studio_dialogs
         WHERE peer_type='user'
           AND ((user_id=? AND peer_id=?) OR (user_id=? AND peer_id=?))`,
        [me, peerId, peerId, me]
      ).catch(() => {})
      // 2026-08-11 实时广播清空 (推给 by+对方+master)
      broadcastClear('user', peerId, me, result.affectedRows).catch(()=>{})
      res.json({ ok: true, deleted: result.affectedRows })
    } else {
      // 群组 (peerType='group')
      const [r] = await pool.query('SELECT 1 FROM smart_studio_groups WHERE id=?', [peerId])
      if (!r.length) return res.json({ ok: false, error: '群组不存在' })
      if (role !== 'superadmin') {
        const [mem] = await pool.query(
          'SELECT 1 FROM smart_studio_group_members WHERE group_id=? AND user_id=?',
          [peerId, me]
        )
        if (!mem.length) return res.json({ ok: false, error: '不是群成员' })
      }
      const [result] = await pool.query(
        'DELETE FROM smart_studio_messages WHERE peer_type=? AND peer_id=?',
        ['group', peerId]
      )
      await pool.query(
        'DELETE FROM smart_studio_pinned_messages WHERE peer_type=? AND peer_id=?',
        ['group', peerId]
      ).catch(() => {})
      broadcastClear('group', peerId, me, result.affectedRows).catch(()=>{})
      res.json({ ok: true, deleted: result.affectedRows })
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
    // 2026-08-11 实时广播清空
    broadcastClear('group', roomId, me, result.affectedRows).catch(()=>{})
    res.json({ ok: true, deleted: result.affectedRows })
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


// 2026-08-11: Element next_batch 模式 — per-user 单调递增 seq
//   send-text / 撤回 / 输入状态 都会 bumpUserSeq
//   前端 GET /sync?since=X → 拿 seq>X 的所有事件 (消息 + 撤回 + 已读 + 输入 + presence)
async function bumpUserSeq(pool, userId){
  if(!userId || userId < 0) return
  await pool.query(
    `INSERT INTO smart_studio_user_seq (user_id, seq) VALUES (?, 1)
     ON DUPLICATE KEY UPDATE seq = seq + 1`,
    [userId]
  )
}

export default router
