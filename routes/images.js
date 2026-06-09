import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import { checkPerm } from '../utils/permission.js'
import { ROLES } from '../middleware/rbac.js'

const router = express.Router()

// 获取图片列表
router.get('/', async (req, res, next) => {
  try {
    const { category, user_id, page = 1, size = 50 } = req.query
    const offset = (page - 1) * size
    
    let where = '1=1'
    const params = []
    
    // 普通用户只能看自己的图片，admin/manager可以看全部
    if (req.user.role !== ROLES.ADMIN && req.user.role !== ROLES.MANAGER) {
      where += ' AND (user_id = ? OR user_id IS NULL)'
      params.push(req.user.id)
    } else if (user_id) {
      where += ' AND user_id = ?'
      params.push(user_id)
    }
    
    if (category) {
      where += ' AND category = ?'
      params.push(category)
    }
    
    const countSql = `SELECT COUNT(*) as total FROM images WHERE ${where}`
    const [countResult] = await pool.query(countSql, params)
    
    const sql = `
      SELECT id, user_id, category, url, filename, size, created_at
      FROM images WHERE ${where}
      ORDER BY created_at DESC
      LIMIT ? OFFSET ?
    `
    
    const [rows] = await pool.query(sql, [...params, parseInt(size), parseInt(offset)])
    
    res.json({
      code: 0,
      data: {
        list: rows,
        total: countResult[0].total,
        page: parseInt(page),
        size: parseInt(size)
      }
    })
  } catch (err) {
    next(err)
  }
})

// 上传图片到图片库
router.post('/', auth, async (req, res, next) => {
  try {
    const { url, category = 'other', filename, size } = req.body
    
    if (!url) {
      return res.status(400).json({ code: 400, message: '缺少图片URL' })
    }
    
    const [result] = await pool.query(
      'INSERT INTO images (user_id, category, url, filename, size) VALUES (?, ?, ?, ?, ?)',
      [req.user.id, category, url, filename || null, size || null]
    )
    
    res.json({
      code: 0,
      data: { id: result.insertId, url },
      message: '添加成功'
    })
  } catch (err) {
    next(err)
  }
})

// 删除图片（只能删除自己的图片）
router.delete('/:id', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    
    // 检查权限
    const [[img]] = await pool.query('SELECT user_id FROM images WHERE id = ?', [id])
    if (!img) {
      return res.status(404).json({ code: 404, message: '图片不存在' })
    }
    
    // 普通用户只能删除自己的图片
    if (req.user.role !== ROLES.ADMIN && req.user.role !== ROLES.MANAGER) {
      if (img.user_id !== req.user.id) {
        return res.status(403).json({ code: 403, message: '无权限删除' })
      }
    }
    
    await pool.query('DELETE FROM images WHERE id = ?', [id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) {
    next(err)
  }
})

export default router
