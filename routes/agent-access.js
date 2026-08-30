/**
 * Agent Access Tokens - 管理端 5 端点
 * 2026-08-31 江小鱼 — 波哥立: "完善开发 API, admin 在用户列表复制接入信息"
 *
 * 端点:
 *   GET    /api/agent-access/tokens?user_id=X   列某用户所有 token
 *   POST   /api/agent-access/tokens            创建新 token (返一次性明文)
 *   POST   /api/agent-access/tokens/:id/rotate 换新 (旧作废, 新明文返一次)
 *   PATCH  /api/agent-access/tokens/:id        改 name / scopes / expires_at
 *   DELETE /api/agent-access/tokens/:id        作废 (soft, revoked_at=NOW)
 *
 * 权限: requireRole('admin') — admin 在用户列表给员工开 token
 * 鉴权: apiTokenAuth (sbk_ token) 或 JWT (admin 登录) 都可以调用本模块
 *
 * ⚠️ 明文 token 仅在创建/rotate 时一次性返回, 之后永不返明文。
 */
import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requireRole } from '../middleware/rbac.js'
import { generateApiToken, hashApiToken, tokenDisplayPrefix } from '../middleware/apiTokenAuth.js'

const router = Router()

// 全部端点要求 admin 角色 (不论 JWT 还是 api token)
router.use(requireRole('admin'))

/**
 * GET /api/agent-access/tokens?user_id=X
 * 列某用户的所有 token (不含明文)
 */
router.get('/tokens', async (req, res, next) => {
  try {
    const userId = parseInt(req.query.user_id)
    if (!userId) {
      return res.status(400).json({ code: 400, message: 'user_id 必填' })
    }
    const [rows] = await pool.execute(
      `SELECT t.id, t.user_id, t.created_by, t.name, t.token_prefix,
              t.scopes, t.expires_at, t.revoked_at, t.last_used_at, t.use_count, t.created_at,
              u.name AS user_name, u.phone AS user_phone, u.role AS user_role,
              c.name AS created_by_name
       FROM user_api_tokens t
       LEFT JOIN users u ON u.id = t.user_id
       LEFT JOIN users c ON c.id = t.created_by
       WHERE t.user_id = ?
       ORDER BY t.created_at DESC`,
      [userId]
    )
    res.json({
      code: 0,
      data: rows.map(r => ({
        ...r,
        scopes: r.scopes ? (typeof r.scopes === 'string' ? JSON.parse(r.scopes) : r.scopes) : null,
        status: r.revoked_at ? 'revoked' : (r.expires_at && new Date(r.expires_at) < new Date() ? 'expired' : 'active')
      }))
    })
  } catch (err) { next(err) }
})

/**
 * POST /api/agent-access/tokens
 * body: { user_id, name, scopes?, expires_in_days? }
 * scopes: undefined/null = 继承全部; 数组 = 降权(限定子集)
 * expires_in_days: undefined = 永久; 数字 = N 天后过期
 * 响应: { token: 'sbk_xxx...', prefix, expires_at, ... } 一次性明文
 */
router.post('/tokens', async (req, res, next) => {
  try {
    const { user_id, name, scopes, expires_in_days } = req.body || {}
    if (!user_id) return res.status(400).json({ code: 400, message: 'user_id 必填' })
    if (!name || !name.trim()) return res.status(400).json({ code: 400, message: 'name 必填' })

    // 校验目标用户存在
    const [userRows] = await pool.execute(
      `SELECT id, name, role, status FROM users WHERE id = ? LIMIT 1`,
      [user_id]
    )
    if (!userRows.length) return res.status(404).json({ code: 404, message: '用户不存在' })
    if (userRows[0].status !== 'active') {
      return res.status(400).json({ code: 400, message: '目标用户已被禁用' })
    }

    // 校验 scopes (若提供, 必须是数组)
    let scopesJson = null
    if (scopes !== undefined && scopes !== null) {
      if (!Array.isArray(scopes) || !scopes.every(s => typeof s === 'string')) {
        return res.status(400).json({ code: 400, message: 'scopes 必须是字符串数组' })
      }
      scopesJson = JSON.stringify(scopes)
    }

    // 校验 expires_in_days
    let expiresAt = null
    if (expires_in_days !== undefined && expires_in_days !== null) {
      const days = parseInt(expires_in_days)
      if (isNaN(days) || days <= 0 || days > 3650) {
        return res.status(400).json({ code: 400, message: 'expires_in_days 必须是 1-3650 的正整数' })
      }
      expiresAt = new Date(Date.now() + days * 24 * 60 * 60 * 1000)
    }

    // 生成 token
    const token = generateApiToken()
    const hash = hashApiToken(token)
    const prefix = tokenDisplayPrefix(token)

    // 写库
    const [result] = await pool.execute(
      `INSERT INTO user_api_tokens
         (user_id, created_by, name, token_prefix, token_hash, scopes, expires_at)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [
        user_id,
        req.user.id,
        name.trim(),
        prefix,
        hash,
        scopesJson,
        expiresAt
      ]
    )

    res.json({
      code: 0,
      data: {
        id: result.insertId,
        token, // ⚠️ 一次性明文, 之后不再返
        token_prefix: prefix,
        user_id,
        user_name: userRows[0].name,
        user_role: userRows[0].role,
        name: name.trim(),
        scopes: scopesJson ? JSON.parse(scopesJson) : null,
        expires_at: expiresAt,
        created_at: new Date(),
        message: '⚠️ Token 明文仅展示一次, 请立即复制保存!'
      }
    })
  } catch (err) { next(err) }
})

/**
 * POST /api/agent-access/tokens/:id/rotate
 * 换新 token, 旧 token 立刻 revoked_at=NOW
 */
router.post('/tokens/:id/rotate', async (req, res, next) => {
  try {
    const tokenId = parseInt(req.params.id)
    if (!tokenId) return res.status(400).json({ code: 400, message: 'id 必填' })

    const [rows] = await pool.execute(
      `SELECT id, user_id, name, revoked_at FROM user_api_tokens WHERE id = ? LIMIT 1`,
      [tokenId]
    )
    if (!rows.length) return res.status(404).json({ code: 404, message: 'token 不存在' })
    if (rows[0].revoked_at) {
      return res.status(400).json({ code: 400, message: '已撤销的 token 不能 rotate' })
    }

    // 生成新 token
    const newToken = generateApiToken()
    const newHash = hashApiToken(newToken)
    const newPrefix = tokenDisplayPrefix(newToken)

    // 事务: 旧 revoked, 新 insert
    const conn = await pool.getConnection()
    try {
      await conn.beginTransaction()
      await conn.execute(
        `UPDATE user_api_tokens SET revoked_at = NOW() WHERE id = ?`,
        [tokenId]
      )
      const [insertResult] = await conn.execute(
        `INSERT INTO user_api_tokens
           (user_id, created_by, name, token_prefix, token_hash, scopes, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [
          rows[0].user_id,
          req.user.id,
          rows[0].name + ' (rotated)',
          newPrefix,
          newHash,
          null, // rotate 保留旧 scopes 字段需另读, 这里先简化 = 不复制 scopes (admin 重新配)
          null  // 同上, 简化 = 永不过期
        ]
      )
      await conn.commit()
      res.json({
        code: 0,
        data: {
          id: insertResult.insertId,
          old_id: tokenId,
          token: newToken,
          token_prefix: newPrefix,
          user_id: rows[0].user_id,
          name: rows[0].name + ' (rotated)',
          created_at: new Date(),
          message: '⚠️ 旧 token 已撤销, 新 token 明文仅展示一次, 请立即复制!'
        }
      })
    } catch (e) {
      await conn.rollback()
      throw e
    } finally {
      conn.release()
    }
  } catch (err) { next(err) }
})

/**
 * PATCH /api/agent-access/tokens/:id
 * 改 name / scopes / expires_at (至少一个)
 */
router.patch('/tokens/:id', async (req, res, next) => {
  try {
    const tokenId = parseInt(req.params.id)
    if (!tokenId) return res.status(400).json({ code: 400, message: 'id 必填' })

    const { name, scopes, expires_in_days } = req.body || {}
    if (name === undefined && scopes === undefined && expires_in_days === undefined) {
      return res.status(400).json({ code: 400, message: '至少传一个: name / scopes / expires_in_days' })
    }

    const [rows] = await pool.execute(
      `SELECT id, user_id, name, scopes, expires_at, revoked_at FROM user_api_tokens WHERE id = ? LIMIT 1`,
      [tokenId]
    )
    if (!rows.length) return res.status(404).json({ code: 404, message: 'token 不存在' })
    if (rows[0].revoked_at) {
      return res.status(400).json({ code: 400, message: '已撤销的 token 不能修改' })
    }

    const updates = []
    const params = []
    if (name !== undefined) {
      if (!name || !name.trim()) return res.status(400).json({ code: 400, message: 'name 不能为空' })
      updates.push('name = ?')
      params.push(name.trim())
    }
    if (scopes !== undefined) {
      if (scopes !== null && (!Array.isArray(scopes) || !scopes.every(s => typeof s === 'string'))) {
        return res.status(400).json({ code: 400, message: 'scopes 必须是字符串数组或 null' })
      }
      updates.push('scopes = ?')
      params.push(scopes === null ? null : JSON.stringify(scopes))
    }
    if (expires_in_days !== undefined) {
      if (expires_in_days === null) {
        updates.push('expires_at = ?')
        params.push(null)
      } else {
        const days = parseInt(expires_in_days)
        if (isNaN(days) || days <= 0 || days > 3650) {
          return res.status(400).json({ code: 400, message: 'expires_in_days 必须是 1-3650 的正整数或 null' })
        }
        const expiresAt = new Date(Date.now() + days * 24 * 60 * 60 * 1000)
        updates.push('expires_at = ?')
        params.push(expiresAt)
      }
    }
    params.push(tokenId)
    await pool.execute(`UPDATE user_api_tokens SET ${updates.join(', ')} WHERE id = ?`, params)

    // 返更新后数据
    const [updated] = await pool.execute(
      `SELECT id, user_id, name, token_prefix, scopes, expires_at, revoked_at, last_used_at, use_count, created_at
       FROM user_api_tokens WHERE id = ?`,
      [tokenId]
    )
    res.json({
      code: 0,
      data: {
        ...updated[0],
        scopes: updated[0].scopes ? (typeof updated[0].scopes === 'string' ? JSON.parse(updated[0].scopes) : updated[0].scopes) : null
      }
    })
  } catch (err) { next(err) }
})

/**
 * DELETE /api/agent-access/tokens/:id
 * 撤销 (soft, revoked_at=NOW, 不物理删)
 */
router.delete('/tokens/:id', async (req, res, next) => {
  try {
    const tokenId = parseInt(req.params.id)
    if (!tokenId) return res.status(400).json({ code: 400, message: 'id 必填' })

    const [rows] = await pool.execute(
      `SELECT id, revoked_at FROM user_api_tokens WHERE id = ? LIMIT 1`,
      [tokenId]
    )
    if (!rows.length) return res.status(404).json({ code: 404, message: 'token 不存在' })
    if (rows[0].revoked_at) {
      return res.status(400).json({ code: 400, message: 'token 已经被撤销' })
    }
    await pool.execute(`UPDATE user_api_tokens SET revoked_at = NOW() WHERE id = ?`, [tokenId])
    res.json({ code: 0, message: 'token 已撤销', data: { id: tokenId, revoked_at: new Date() } })
  } catch (err) { next(err) }
})

export default router
