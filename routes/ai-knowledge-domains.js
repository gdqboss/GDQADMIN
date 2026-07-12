// =============================================================
// ai-knowledge-domains.js — L4 智能体知识库 domain 路由
// 提供:
//   GET  /api/ai-domains                - 全 4 domain + 当前可用
//   GET  /api/ai-domains/:domain        - 某 domain 的 knowledge 列表
//   POST /api/ai-domains/:domain        - 加新知识条目
//   PATCH /api/ai-domains/:id/enabled   - 启/停 domain
//   POST /api/labor-ai-agent/chat (alias) - 转 labor-ai-agent
// =============================================================
import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

const VALID_DOMAINS = ['factory', 'store', 'company', 'project']

// 角色 → 可用 domain 映射
const ROLE_DOMAINS = {
  worker:  ['factory', 'project'],
  foreman: ['factory', 'project'],
  manager: ['factory', 'store', 'company', 'project'],
  admin:   ['factory', 'store', 'company', 'project'],
  boss:    ['factory', 'store', 'company', 'project'],
}

router.get('/', async (req, res, next) => {
  try {
    const role = req.query.role || (req.user && req.user.role) || 'worker'
    const allowed = ROLE_DOMAINS[role] || ROLE_DOMAINS.worker

    const meta = {}
    for (const d of VALID_DOMAINS) {
      const [[{ cnt }]] = await pool.query(
        `SELECT COUNT(*) AS cnt FROM ai_class_knowledge WHERE domain = ? AND domain_enabled = 1`,
        [d]
      )
      const labels = {
        factory: '🏭 工厂 / 工地',
        store:   '🏪 门店',
        company: '🏢 公司',
        project: '🏗️ 项目 / 工地档案',
      }
      meta[d] = { label: labels[d], count: cnt, allowed: allowed.includes(d) }
    }

    res.json({
      code: 0,
      data: {
        all_domains: VALID_DOMAINS,
        allowed_domains: allowed,
        user_role: role,
        domain_meta: meta,
      },
    })
  } catch (e) { next(e) }
})

router.get('/:domain', async (req, res, next) => {
  try {
    const { domain } = req.params
    if (!VALID_DOMAINS.includes(domain)) {
      return res.status(400).json({ code: 400, message: `未知 domain: ${domain}` })
    }
    const role = req.query.role || (req.user && req.user.role) || 'worker'
    const allowed = ROLE_DOMAINS[role] || ROLE_DOMAINS.worker
    if (!allowed.includes(domain)) {
      return res.status(403).json({ code: 403, message: `role=${role} 无权访问 ${domain}` })
    }
    const { category, q } = req.query
    const params = [domain]
    let where = 'WHERE domain = ? AND domain_enabled = 1'
    if (category) { where += ' AND sub_category = ?'; params.push(category) }
    if (q) { where += ' AND (title LIKE ? OR content LIKE ?)'; params.push(`%${q}%`, `%${q}%`) }
    const [rows] = await pool.query(
      `SELECT id, title, content, sub_category, tags, created_by, created_at, owner_user_id FROM ai_class_knowledge ${where} ORDER BY updated_at DESC LIMIT 50`,
      params
    )
    res.json({ code: 0, data: { domain, count: rows.length, results: rows, total: rows.length } })
  } catch (e) { next(e) }
})

router.post('/:domain', async (req, res, next) => {
  try {
    const { domain } = req.params
    if (!VALID_DOMAINS.includes(domain)) {
      return res.status(400).json({ code: 400, message: `未知 domain: ${domain}` })
    }
    const { title, content, sub_category, tags } = req.body || {}
    if (!title || !content) {
      return res.status(400).json({ code: 400, message: 'title + content 必填' })
    }
    const owner_user_id = req.user?.id || null
    const tagsJson = tags ? JSON.stringify(tags) : null
    const [r] = await pool.query(
      `INSERT INTO ai_class_knowledge (title, content, doc_type, sub_category, owner_user_id, tags, domain, domain_enabled) VALUES (?, ?, 'kb', ?, ?, ?, ?, 1)`,
      [title, content, sub_category || 'general', owner_user_id, tagsJson, domain]
    )
    res.json({ code: 0, data: { id: r.insertId, domain } })
  } catch (e) { next(e) }
})

router.patch('/:id/enabled', async (req, res, next) => {
  try {
    const { id } = req.params
    const { enabled } = req.body || {}
    await pool.query(`UPDATE ai_class_knowledge SET domain_enabled = ? WHERE id = ?`, [enabled ? 1 : 0, id])
    res.json({ code: 0, data: { id, enabled: !!enabled } })
  } catch (e) { next(e) }
})

export default router
