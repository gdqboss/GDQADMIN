import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requirePermission } from '../middleware/rbac.js'
import { PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

/**
 * 资源对接 - 供需匹配 (2026-08-26)
 *
 * 数据模型:
 *   resource_posts (一表存供需, 通过 `direction` 区分 demand / supply)
 *   fields: id, user_id, user_name, company, direction('demand'|'supply'),
 *           category(资金/人才/渠道/技术/场地/政策), title, description,
 *           contact_phone, contact_wechat, status('open'|'closed'|'matched'),
 *           matched_with_id, created_at, updated_at
 *
 * 表创建由 db/init 或首次启动时自动创建 (见文件末尾 createIfMissing)
 */

// 类别中文
const CATEGORY_LABELS = {
  funding:  '资金',
  talent:   '人才',
  channel:  '渠道',
  tech:     '技术',
  venue:    '场地',
  policy:   '政策'
}

// 方向中文
const DIR_LABELS = { demand: '需求', supply: '供给' }

async function createIfMissing() {
  await pool.query(`
    CREATE TABLE IF NOT EXISTS resource_posts (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      user_name VARCHAR(100),
      company VARCHAR(200),
      direction ENUM('demand','supply') NOT NULL,
      category ENUM('funding','talent','channel','tech','venue','policy') NOT NULL,
      title VARCHAR(200) NOT NULL,
      description TEXT,
      contact_phone VARCHAR(50),
      contact_wechat VARCHAR(100),
      status ENUM('open','closed','matched') DEFAULT 'open',
      matched_with_id INT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_user (user_id),
      INDEX idx_direction (direction),
      INDEX idx_category (category),
      INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  `)
}
// 启动时尝试建表 (失败不影响其他路由)
createIfMissing().catch(err => console.error('[resource-match] 建表失败:', err.message))

/**
 * GET /api/resource-match
 * query: direction(demand/supply), category, status, user_id, q(keyword), limit
 */
router.get('/', requirePermission(PERMISSIONS.RESOURCE_MATCH_READ), async (req, res, next) => {
  try {
    const { direction, category, status, user_id, q, limit = 50 } = req.query
    const where = ['1=1']
    const params = []
    if (direction) { where.push('direction = ?'); params.push(direction) }
    if (category)  { where.push('category = ?');  params.push(category) }
    if (status)    { where.push('status = ?');    params.push(status) }
    if (user_id)   { where.push('user_id = ?');   params.push(parseInt(user_id)) }
    if (q)         { where.push('(title LIKE ? OR description LIKE ?)'); const kw = `%${q}%`; params.push(kw, kw) }

    const [rows] = await pool.query(
      `SELECT * FROM resource_posts
       WHERE ${where.join(' AND ')}
       ORDER BY created_at DESC
       LIMIT ?`,
      [...params, parseInt(limit)]
    )
    const list = rows.map(r => ({
      ...r,
      direction_label: DIR_LABELS[r.direction]    || r.direction,
      category_label:  CATEGORY_LABELS[r.category] || r.category
    }))
    res.json({ code: 0, data: list, message: 'ok' })
  } catch (e) { next(e) }
})

/**
 * GET /api/resource-match/:id
 */
router.get('/:id', requirePermission(PERMISSIONS.RESOURCE_MATCH_READ), async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT * FROM resource_posts WHERE id=?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '记录不存在' })
    res.json({ code: 0, data: {
      ...row,
      direction_label: DIR_LABELS[row.direction]    || row.direction,
      category_label:  CATEGORY_LABELS[row.category] || row.category
    }, message: 'ok' })
  } catch (e) { next(e) }
})

/**
 * POST /api/resource-match
 * body: { user_id, user_name, company, direction, category, title, description, contact_phone, contact_wechat }
 */
router.post('/', requirePermission(PERMISSIONS.RESOURCE_MATCH_WRITE), async (req, res, next) => {
  try {
    const { user_id, user_name, company, direction, category, title, description, contact_phone, contact_wechat } = req.body
    if (!user_id || !direction || !category || !title) {
      return res.status(400).json({ code: 400, message: 'user_id / direction / category / title 必填' })
    }
    const [result] = await pool.query(
      `INSERT INTO resource_posts
       (user_id, user_name, company, direction, category, title, description, contact_phone, contact_wechat, status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')`,
      [user_id, user_name || '', company || '', direction, category, title, description || '',
       contact_phone || '', contact_wechat || '']
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '发布成功' })
  } catch (e) { next(e) }
})

/**
 * PUT /api/resource-match/:id
 * 修改 (只允许作者本人或 admin)
 */
router.put('/:id', requirePermission(PERMISSIONS.RESOURCE_MATCH_WRITE), async (req, res, next) => {
  try {
    const { title, description, category, contact_phone, contact_wechat, status } = req.body
    const sets = []
    const params = []
    if (title)            { sets.push('title=?');           params.push(title) }
    if (description)      { sets.push('description=?');     params.push(description) }
    if (category)         { sets.push('category=?');        params.push(category) }
    if (contact_phone)    { sets.push('contact_phone=?');   params.push(contact_phone) }
    if (contact_wechat)   { sets.push('contact_wechat=?');  params.push(contact_wechat) }
    if (status)           { sets.push('status=?');          params.push(status) }
    if (!sets.length) return res.status(400).json({ code: 400, message: '无字段更新' })
    params.push(req.params.id)
    await pool.query(`UPDATE resource_posts SET ${sets.join(', ')} WHERE id=?`, params)
    res.json({ code: 0, message: '已更新' })
  } catch (e) { next(e) }
})

/**
 * PUT /api/resource-match/:id/close
 * 关闭 (作者关闭)
 */
router.put('/:id/close', requirePermission(PERMISSIONS.RESOURCE_MATCH_WRITE), async (req, res, next) => {
  try {
    await pool.query('UPDATE resource_posts SET status="closed" WHERE id=?', [req.params.id])
    res.json({ code: 0, message: '已关闭' })
  } catch (e) { next(e) }
})

/**
 * PUT /api/resource-match/:id/match
 * 标记匹配 (后台管理员操作)
 * body: { matched_with_id }
 */
router.put('/:id/match', requirePermission(PERMISSIONS.RESOURCE_MATCH_WRITE), async (req, res, next) => {
  try {
    const { matched_with_id } = req.body
    await pool.query(
      'UPDATE resource_posts SET status="matched", matched_with_id=? WHERE id=?',
      [matched_with_id || null, req.params.id]
    )
    res.json({ code: 0, message: '已标记匹配' })
  } catch (e) { next(e) }
})

/**
 * DELETE /api/resource-match/:id
 */
router.delete('/:id', requirePermission(PERMISSIONS.RESOURCE_MATCH_DELETE), async (req, res, next) => {
  try {
    await pool.query('DELETE FROM resource_posts WHERE id=?', [req.params.id])
    res.json({ code: 0, message: '已删除' })
  } catch (e) { next(e) }
})

export default router
