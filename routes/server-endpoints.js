import express from 'express'
import { auth } from '../middleware/auth.js'
import { requireRole } from '../middleware/rbac.js'
import { pool } from '../db/connection.js'

const router = express.Router()

router.get('/', auth, requireRole('admin'), async (req, res) => {
  try {
    const { profile_id } = req.query
    const params = []
    let where = '1=1'
    if (profile_id) {
      where += ' AND server_profile_id = ?'
      params.push(Number(profile_id))
    }
    const [rows] = await pool.query(
      `SELECT id, server_profile_id, endpoint_type, label, url, is_primary, is_active, env, sort_order, description, created_at, updated_at
       FROM server_endpoints WHERE ${where} ORDER BY server_profile_id, sort_order, endpoint_type`,
      params
    )
    res.json({ code: 0, data: rows })
  } catch (e) {
    console.error('[server-endpoints list]', e.message)
    res.status(500).json({ code: 1, message: e.message })
  }
})

router.get('/:id', auth, requireRole('admin'), async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT * FROM server_endpoints WHERE id=?', [Number(req.params.id)])
    if (!rows.length) return res.status(404).json({ code: 1, message: 'not found' })
    res.json({ code: 0, data: rows[0] })
  } catch (e) {
    res.status(500).json({ code: 1, message: e.message })
  }
})

router.post('/', auth, requireRole('admin'), async (req, res) => {
  try {
    const { server_profile_id, endpoint_type, label, url, is_primary, is_active, env, sort_order, description } = req.body
    if (!server_profile_id || !endpoint_type || !url) {
      return res.status(400).json({ code: 1, message: 'server_profile_id / endpoint_type / url 必填' })
    }
    await pool.query(
      `INSERT INTO server_endpoints (server_profile_id, endpoint_type, label, url, is_primary, is_active, env, sort_order, description)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        Number(server_profile_id),
        endpoint_type,
        label || endpoint_type,
        url,
        is_primary ? 1 : 0,
        is_active === false || is_active === 0 ? 0 : 1,
        env || 'production',
        sort_order ? Number(sort_order) : 100,
        description || null
      ]
    )
    res.json({ code: 0, message: 'ok' })
  } catch (e) {
    console.error('[server-endpoints create]', e.message)
    res.status(500).json({ code: 1, message: e.message })
  }
})

router.put('/:id', auth, requireRole('admin'), async (req, res) => {
  try {
    const id = Number(req.params.id)
    const allowed = ['server_profile_id', 'endpoint_type', 'label', 'url', 'is_primary', 'is_active', 'env', 'sort_order', 'description']
    const sets = []
    const params = []
    for (const f of allowed) {
      if (req.body[f] !== undefined) {
        sets.push(f + ' = ?')
        let v = req.body[f]
        if (f === 'is_primary' || f === 'is_active') v = v ? 1 : 0
        if (f === 'server_profile_id' || f === 'sort_order') v = v === null || v === '' ? null : Number(v)
        params.push(v)
      }
    }
    if (!sets.length) return res.json({ code: 0, message: 'nothing to update' })
    params.push(id)
    await pool.query('UPDATE server_endpoints SET ' + sets.join(', ') + ' WHERE id=?', params)
    res.json({ code: 0, message: 'ok' })
  } catch (e) {
    res.status(500).json({ code: 1, message: e.message })
  }
})

router.delete('/:id', auth, requireRole('admin'), async (req, res) => {
  try {
    await pool.query('DELETE FROM server_endpoints WHERE id=?', [Number(req.params.id)])
    res.json({ code: 0, message: 'ok' })
  } catch (e) {
    res.status(500).json({ code: 1, message: e.message })
  }
})

export default router