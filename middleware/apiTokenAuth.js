/**
 * API Token 鉴权 middleware
 * 2026-08-31 江小鱼 — 波哥立 "完善开发 API，给各种 agent 根据不同用户 token 一句话接人"
 *
 * 与 JWT 完全独立的鉴权路径：
 *   - 前端：Authorization: Bearer sbk_xxx (新)
 *   - JWT:  Authorization: Bearer eyJ...  (旧, 保留)
 *
 * 优先级：先看 token 前缀 (sbk_) 走本 middleware；否则 JWT。
 * 鉴权成功：req.user = { ...user, via: 'api_token', scopes?, token_id }
 *
 * 权限解析策略：
 *   - token.scopes = NULL       → 继承用户全部权限（跟本人登录一样）
 *   - token.scopes = [...]      → 与用户有效权限求交集（降权）
 *   - admin / superuser         → 永真（auth.js 已有逻辑）
 *
 * 调用：app.use('/api/<feature>', apiTokenAuth, ...)  OR  app.get('/x', apiTokenAuth, handler)
 */
import crypto from 'node:crypto'
import { pool } from '../db/connection.js'

const TOKEN_PREFIX = 'sbk_'

/**
 * 生成新 token 字符串（明文，不存）
 * 格式：sbk_ + 32 字节随机 → base64url → 总长约 47 字符
 */
export function generateApiToken() {
  const raw = crypto.randomBytes(32).toString('base64url')
  return `sbk_${raw}`
}

/**
 * 生成 token 的存储 hash (SHA256 hex)
 */
export function hashApiToken(token) {
  return crypto.createHash('sha256').update(token).digest('hex')
}

/**
 * 提取展示用前缀（前 8 字符）
 */
export function tokenDisplayPrefix(token) {
  if (!token || token.length < 12) return token || ''
  return `${token.slice(0, 8)}...${token.slice(-4)}`
}

/**
 * 解析 scopes 字段（JSON 字符串或 NULL）
 */
function parseScopes(scopes) {
  if (scopes === null || scopes === undefined) return null
  if (Array.isArray(scopes)) return scopes
  try { return JSON.parse(scopes) } catch { return null }
}

/**
 * middleware：校验 Bearer sbk_xxx，挂 req.user
 * 失败：401 (无 token / 无效 / 已撤销 / 已过期)
 */
export async function apiTokenAuth(req, res, next) {
  const header = req.headers.authorization
  if (!header || !header.startsWith('Bearer ')) {
    return next() // 不是 token 也不拦，交给下一层 JWT 鉴权
  }
  const token = header.slice(7).trim()
  if (!token.startsWith(TOKEN_PREFIX)) {
    return next() // 不是 sbk_ token，交给 JWT
  }

  const hash = hashApiToken(token)
  try {
    const [rows] = await pool.execute(
      `SELECT t.id AS token_id, t.user_id, t.scopes, t.expires_at, t.revoked_at,
              u.id, u.role, u.server_profile_id, u.status, u.name, u.permissions
       FROM user_api_tokens t
       JOIN users u ON u.id = t.user_id
       WHERE t.token_hash = ? LIMIT 1`,
      [hash]
    )
    if (!rows.length) {
      return res.status(401).json({ code: 401, message: 'API token 无效' })
    }
    const r = rows[0]

    if (r.revoked_at) {
      return res.status(401).json({ code: 401, message: 'API token 已被撤销' })
    }
    if (r.status !== 'active') {
      return res.status(401).json({ code: 401, message: 'token 所属用户已被禁用' })
    }
    if (r.expires_at && new Date(r.expires_at) < new Date()) {
      return res.status(401).json({ code: 401, message: 'API token 已过期' })
    }

    // 异步更新最后使用时间（不 await，fire-and-forget）
    pool.execute(
      `UPDATE user_api_tokens SET last_used_at = NOW(), use_count = use_count + 1 WHERE id = ?`,
      [r.token_id]
    ).catch(() => {})

    // 挂用户身份
    const scopes = parseScopes(r.scopes)
    req.user = {
      id: r.id,
      role: r.role,
      server_profile_id: r.server_profile_id,
      status: r.status,
      name: r.name,
      permissions: r.permissions ? (typeof r.permissions === 'string' ? JSON.parse(r.permissions) : r.permissions) : [],
      via: 'api_token',
      token_id: r.token_id,
      scopes: scopes // null = 继承全部；数组 = 降权集合
    }
    next()
  } catch (err) {
    console.error('[apiTokenAuth] error:', err.message)
    res.status(500).json({ code: 500, message: 'token 鉴权失败' })
  }
}

/**
 * 兼容 requirePermission：若 token 有 scopes 限制，做交集
 * 接入位置：在 requirePermission 之前或替代 requirePermission
 *
 * 用法：
 *   router.get('/x', apiTokenAuth, requireTokenPermission('module:read'), handler)
 *
 * 注意：admin 角色永真（resolvePermissions 已处理）；只对非 admin 做交集
 */
export function requireTokenPermission(permKey) {
  return async (req, res, next) => {
    if (!req.user) {
      return res.status(401).json({ code: 401, message: '未鉴权' })
    }
    // admin 永真
    if (req.user.role === 'admin' || req.user.role === 'superuser') return next()
    // 没有 token scopes 限制 → 跟 JWT 走 rbac 检查
    if (!req.user.scopes || !Array.isArray(req.user.scopes)) {
      // 走标准 requirePermission
      const { requirePermission } = await import('./rbac.js')
      return requirePermission(permKey)(req, res, next)
    }
    // 有 token scopes → 仅当 permKey 在 scopes 中才放行
    if (req.user.scopes.includes(permKey)) return next()
    return res.status(403).json({ code: 403, message: `token 权限不足: 缺少 ${permKey}` })
  }
}
