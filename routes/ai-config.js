import { Router } from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'

const router = Router()

// GET /api/ai-config - 获取所有AI配置
router.get('/', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT * FROM ai_config WHERE status = 1 ORDER BY category, is_default DESC'
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// GET /api/ai-config/:category - 按分类获取
router.get('/:category', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT * FROM ai_config WHERE category = ? AND status = 1 ORDER BY is_default DESC',
      [req.params.category]
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/ai-config - 新增配置
router.post('/', auth, async (req, res, next) => {
  try {
    const { category, provider, base_url, api_key, model, is_default } = req.body
    if (is_default) {
      await pool.query('UPDATE ai_config SET is_default = 0 WHERE category = ?', [category])
    }
    const [result] = await pool.query(
      'INSERT INTO ai_config (category, provider, base_url, api_key, model, is_default) VALUES (?, ?, ?, ?, ?, ?)',
      [category, provider, base_url || '', api_key || '', model, is_default ? 1 : 0]
    )
    res.json({ code: 0, data: { id: result.insertId } })
  } catch (err) { next(err) }
})

// PUT /api/ai-config/:id - 更新配置
router.put('/:id', auth, async (req, res, next) => {
  try {
    const { category, provider, base_url, api_key, model, is_default, status } = req.body
    if (is_default) {
      await pool.query('UPDATE ai_config SET is_default = 0 WHERE category = ?', [category])
    }
    await pool.query(
      'UPDATE ai_config SET category=?, provider=?, base_url=?, api_key=?, model=?, is_default=?, status=? WHERE id=?',
      [category, provider, base_url||'', api_key||'', model, is_default?1:0, status??1, req.params.id]
    )
    res.json({ code: 0 })
  } catch (err) { next(err) }
})

// DELETE /api/ai-config/:id - 删除配置
router.delete('/:id', auth, async (req, res, next) => {
  try {
    await pool.query('UPDATE ai_config SET status = 0 WHERE id = ?', [req.params.id])
    res.json({ code: 0 })
  } catch (err) { next(err) }
})

export default router
