import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// GET list
router.get('/', async (req, res, next) => {
  try {
    const { keyword, status } = req.query
    let sql = 'SELECT * FROM dealers WHERE 1=1'
    const params = []
    if (keyword) { sql += ' AND (name LIKE ? OR contact LIKE ? OR region LIKE ?)'; params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`) }
    if (status) { sql += ' AND status = ?'; params.push(status) }
    sql += ' ORDER BY created_at DESC'
    const [rows] = await pool.query(sql, params)
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST create
router.post('/', async (req, res, next) => {
  try {
    const { name, contact, phone, email, address, region, remark } = req.body
    if (!name) return res.status(400).json({ code: 400, message: '经销商名称必填' })
    const [result] = await pool.query(
      'INSERT INTO dealers (name, contact, phone, email, address, region, remark) VALUES (?,?,?,?,?,?,?)',
      [name, contact || null, phone || null, email || null, address || null, region || null, remark || null]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT update
router.put('/:id', async (req, res, next) => {
  try {
    const allowed = ['name', 'contact', 'phone', 'email', 'address', 'region', 'status', 'remark']
    const fields = []; const params = []
    for (const f of allowed) {
      if (req.body[f] !== undefined) { fields.push(`${f} = ?`); params.push(req.body[f]) }
    }
    if (!fields.length) return res.status(400).json({ code: 400, message: '无更新内容' })
    params.push(req.params.id)
    await pool.query(`UPDATE dealers SET ${fields.join(', ')} WHERE id = ?`, params)
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE
router.delete('/:id', async (req, res, next) => {
  try {
    await pool.query('DELETE FROM dealers WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

export default router
