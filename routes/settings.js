import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

router.get('/', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT `key`, value FROM settings')
    const settings = {}
    for (const row of rows) {
      try { settings[row.key] = JSON.parse(row.value) } catch { settings[row.key] = row.value }
    }
    res.json({ code: 0, data: settings, message: 'ok' })
  } catch (err) { next(err) }
})

router.put('/', async (req, res, next) => {
  try {
    const entries = Object.entries(req.body)
    for (const [key, value] of entries) {
      const val = typeof value === 'string' ? value : JSON.stringify(value)
      await pool.query(
        'INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?',
        [key, val, val]
      )
    }
    res.json({ code: 0, data: null, message: 'ok' })
  } catch (err) { next(err) }
})

export default router
