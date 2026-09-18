import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// 公开只读：已发布文章（湾创首页 banner/资讯，免鉴权，游客可见）
router.get('/', async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, title, category, summary, cover_image, created_at
       FROM articles
       WHERE status = 'published'
       ORDER BY created_at DESC
       LIMIT 10`
    )
    res.json({ code: 0, data: { list: rows, total: rows.length, page: 1, size: 10 }, message: 'ok' })
  } catch (e) {
    res.status(500).json({ code: 500, message: '文章加载失败' })
  }
})

export default router