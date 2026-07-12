/**
 * WeCom admin routes — manual sync triggers, contacts view, send message
 * All routes require admin role.
 */

import { Router } from 'express'
import { pool } from '../db/connection.js'
import { isWeComConfigured, sendMessage } from '../services/wecom.js'
import { syncAttendance, syncApprovals, syncContacts } from '../services/wecom-sync.js'

const router = Router()

// Check WeCom is configured before any operation
function requireWeComConfig(req, res, next) {
  if (!isWeComConfigured()) {
    return res.status(400).json({ code: 400, message: '企业微信未配置 (WECOM_CORP_ID / WECOM_SECRET)' })
  }
  next()
}

// POST /sync/attendance — manual attendance sync
router.post('/sync/attendance', requireWeComConfig, async (req, res, next) => {
  try {
    const { start_date, end_date } = req.body
    const today = new Date().toISOString().slice(0, 10)
    const result = await syncAttendance(start_date || today, end_date || today)
    res.json({ code: 0, data: result, message: '考勤同步完成' })
  } catch (err) { next(err) }
})

// POST /sync/approvals — manual approvals sync
router.post('/sync/approvals', requireWeComConfig, async (req, res, next) => {
  try {
    const { start_date, end_date } = req.body
    const today = new Date().toISOString().slice(0, 10)
    const yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 10)
    const result = await syncApprovals(start_date || yesterday, end_date || today)
    res.json({ code: 0, data: result, message: '审批同步完成' })
  } catch (err) { next(err) }
})

// POST /sync/contacts — manual contacts sync
router.post('/sync/contacts', requireWeComConfig, async (req, res, next) => {
  try {
    const result = await syncContacts()
    res.json({ code: 0, data: result, message: '通讯录同步完成' })
  } catch (err) { next(err) }
})

// GET /contacts — view synced WeCom contacts
router.get('/contacts', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT wc.*, u.name as local_name, u.email as local_email
       FROM wecom_contacts wc
       LEFT JOIN users u ON wc.user_id = u.id
       ORDER BY wc.name`
    )
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /departments — view synced WeCom departments
router.get('/departments', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM wecom_departments ORDER BY dept_order')
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /send-message — send message via WeCom
router.post('/send-message', requireWeComConfig, async (req, res, next) => {
  try {
    const { touser, msgtype, content } = req.body
    if (!touser || !content) {
      return res.status(400).json({ code: 400, message: 'touser 和 content 必填' })
    }
    const result = await sendMessage({ touser, msgtype: msgtype || 'text', content })
    res.json({ code: 0, data: result, message: '消息发送成功' })
  } catch (err) { next(err) }
})

export default router
