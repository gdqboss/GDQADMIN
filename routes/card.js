import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'
import crypto from 'crypto'

const router = express.Router()

// 获取员工名片信息
router.get('/employees/:id/card', async (req, res, next) => {
  try {
    const { id } = req.params

    const [[employee]] = await pool.query(
      `SELECT id, name, email, phone, role, department, avatar, images, title, bio, bio_en, wechat,
              card_bg, company_name, company_name_en, company_address, company_address_en, company_phone,
              status, created_at, employee_code, card_views, endorsements
       FROM users WHERE id = ? AND status = 'active'`,
      [id]
    )

    if (!employee) {
      return res.status(404).json({ code: 404, message: '员工不存在' })
    }

    res.json({
      code: 0,
      data: {
        ...employee,
        images: employee.images ? (typeof employee.images === 'string' ? JSON.parse(employee.images) : employee.images) : [],
        card_views: employee.card_views || 0,
        endorsements: employee.endorsements || 0,
        card_url: `https://${req.headers.host}/card.html?id=${id}`,
        can_edit: req.user && req.user.id == id
      },
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

// 更新名片信息
router.put('/employees/:id/card', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    const userId = req.user.id

    if (userId != id && req.user.role !== 'admin') {
      return res.status(403).json({ code: 403, message: '无权修改' })
    }

    const { avatar, images, title, bio, bio_en, wechat, card_bg, company_name, company_name_en, company_address, company_address_en, company_phone } = req.body

    const updates = []
    const params = []
    
    if (avatar !== undefined) {
      updates.push('avatar = ?')
      params.push(avatar || null)
    }
    if (images !== undefined) {
      updates.push('images = ?')
      params.push(images ? JSON.stringify(images) : null)
    }
    if (title !== undefined) {
      updates.push('title = ?')
      params.push(title || null)
    }
    if (bio !== undefined) {
      updates.push('bio = ?')
      params.push(bio || null)
    }
    if (bio_en !== undefined) {
      updates.push('bio_en = ?')
      params.push(bio_en || null)
    }
    if (wechat !== undefined) {
      updates.push('wechat = ?')
      params.push(wechat || null)
    }
    if (card_bg !== undefined) {
      updates.push('card_bg = ?')
      params.push(card_bg || 'blue')
    }
    if (company_name !== undefined) {
      updates.push('company_name = ?')
      params.push(company_name || null)
    }
    if (company_name_en !== undefined) {
      updates.push('company_name_en = ?')
      params.push(company_name_en || null)
    }
    if (company_address !== undefined) {
      updates.push('company_address = ?')
      params.push(company_address || null)
    }
    if (company_address_en !== undefined) {
      updates.push('company_address_en = ?')
      params.push(company_address_en || null)
    }
    if (company_phone !== undefined) {
      updates.push('company_phone = ?')
      params.push(company_phone || null)
    }

    if (!updates.length) {
      return res.status(400).json({ code: 400, message: '没有要更新的字段' })
    }

    params.push(id)
    await pool.query(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`, params)

    res.json({ code: 0, message: '更新成功' })
  } catch (err) {
    console.error('更新名片失败:', err)
    next(err)
  }
})

// 浏览量+1
router.post('/employees/:id/view', async (req, res, next) => {
  try {
    const { id } = req.params
    await pool.query('UPDATE users SET card_views = COALESCE(card_views, 0) + 1 WHERE id = ?', [id])
    res.json({ code: 0, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// 靠谱点赞
router.post('/employees/:id/endorse', async (req, res, next) => {
  try {
    const { id } = req.params
    
    // 先确保字段存在
    try {
      await pool.query('ALTER TABLE users ADD COLUMN IF NOT EXISTS endorsements INT DEFAULT 0')
    } catch (e) {
      // 忽略字段已存在的错误
    }
    
    // 增加点赞数
    await pool.query('UPDATE users SET endorsements = COALESCE(endorsements, 0) + 1 WHERE id = ?', [id])
    
    // 获取当前点赞数
    const [rows] = await pool.query('SELECT endorsements FROM users WHERE id = ?', [id])
    
    res.json({ code: 0, message: '点赞成功', count: rows[0]?.endorsements || 0 })
  } catch (err) {
    console.error('靠谱点赞失败:', err)
    res.status(500).json({ code: 500, message: '服务器错误' })
  }
})

export default router
