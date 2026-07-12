import { Router } from 'express'
import { pool } from '../db/connection.js'
import crypto from 'crypto'

const router = Router()

// 生成激活码
router.post('/gen-code', async (req, res) => {
  try {
    const { customer_name, customer_contact } = req.body
    const code = 'JXY-' + crypto.randomBytes(4).toString('hex').toUpperCase()
    const expires_at = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)
    
    await pool.query(
      'INSERT INTO activation_codes (code, customer_name, customer_contact, status, expires_at) VALUES (?, ?, ?, ?, ?)',
      [code, customer_name || '', customer_contact || '', 'ready', expires_at]
    )
    
    res.json({ code: 0, data: { code, expires_at }, message: '激活码生成成功' })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// 验证激活码（公开）
router.get('/verify/:code', async (req, res) => {
  try {
    const { code } = req.params
    const [[record]] = await pool.query('SELECT * FROM activation_codes WHERE code = ?', [code])
    
    if (!record) return res.status(404).json({ code: 404, message: '激活码不存在' })
    if (record.status === 'expired') return res.status(400).json({ code: 400, message: '激活码已过期' })
    if (record.status === 'activated') return res.status(400).json({ code: 400, message: '激活码已使用' })
    
    res.json({ code: 0, data: { valid: true, customer_name: record.customer_name } })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// 握手（公开）
router.post('/handshake', async (req, res) => {
  try {
    const { code, openclaw_url, openclaw_token } = req.body
    if (!code || !openclaw_url) return res.status(400).json({ code: 400, message: '参数不完整' })
    
    const [[record]] = await pool.query('SELECT * FROM activation_codes WHERE code = ?', [code])
    if (!record) return res.status(404).json({ code: 404, message: '激活码不存在' })
    if (record.status === 'activated') return res.status(400).json({ code: 400, message: '激活码已使用' })
    
    await pool.query(
      'UPDATE activation_codes SET openclaw_url = ?, openclaw_token = ?, status = ? WHERE code = ?',
      [openclaw_url, openclaw_token || '', 'pending', code]
    )
    
    res.json({ code: 0, data: { status: 'pending' }, message: '已提交，请等待审核' })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// 激活（管理员）
router.post('/activate/:code', async (req, res) => {
  try {
    const { code } = req.params
    const [[record]] = await pool.query('SELECT * FROM activation_codes WHERE code = ?', [code])
    if (!record) return res.status(404).json({ code: 404, message: '激活码不存在' })
    if (record.status !== 'pending') return res.status(400).json({ code: 400, message: '状态不是pending' })
    
    await pool.query('UPDATE activation_codes SET status = ?, activated_at = NOW() WHERE code = ?', ['activated', code])
    
    res.json({ code: 0, data: { activated: true, openclaw_url: record.openclaw_url, openclaw_token: record.openclaw_token }, message: '激活成功' })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// 待激活列表
router.get('/pending-list', async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT * FROM activation_codes WHERE status = ? ORDER BY created_at DESC', ['pending'])
    res.json({ code: 0, data: rows })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

export default router
