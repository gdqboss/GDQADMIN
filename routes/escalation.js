import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'

const router = express.Router()

// 获取升级配置
router.get('/config', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(`SELECT * FROM escalation_config ORDER BY level`)
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// 更新升级配置
router.put('/config/:level', auth, async (req, res, next) => {
  try {
    const { level } = req.params
    const { timeout_hours, auto_escalate, notify_boss } = req.body
    
    await pool.query(`
      UPDATE escalation_config 
      SET timeout_hours = ?, auto_escalate = ?, notify_boss = ?
      WHERE level = ?
    `, [timeout_hours, auto_escalate, notify_boss, level])
    
    res.json({ code: 0, message: '配置更新成功' })
  } catch (err) {
    next(err)
  }
})

// 提交问题升级
router.post('/submit', auth, async (req, res, next) => {
  try {
    const { work_log_id, from_level, reason, urgency } = req.body
    
    // 获取当前用户信息
    const [users] = await pool.query(`SELECT * FROM users WHERE id = ?`, [req.user.id])
    if (users.length === 0) {
      return res.status(404).json({ code: 404, message: '用户不存在' })
    }
    
    // 获取上级信息
    const [reporting] = await pool.query(`
      SELECT r.*, u.name as manager_name 
      FROM reporting_lines r 
      JOIN users u ON r.manager_id = u.id 
      WHERE r.user_id = ? AND r.manager_id > 0
    `, [req.user.id])
    
    if (reporting.length === 0) {
      return res.status(400).json({ code: 400, message: '无上级可升级' })
    }
    
    const manager = reporting[0]
    const to_level = from_level + 1
    
    // 插入升级记录
    const [result] = await pool.query(`
      INSERT INTO log_escalations (log_id, from_level, to_level, to_user_id, reason, status, urgency)
      VALUES (?, ?, ?, ?, ?, 'pending', ?)
    `, [work_log_id, from_level, to_level, manager.manager_id, reason, urgency || 'normal'])
    
    // 创建SLA记录
    await pool.query(`
      INSERT INTO escalation_sla (escalation_id, assigned_to, sla_hours)
      VALUES (?, ?, 24)
    `, [result.insertId, manager.manager_id])
    
    res.json({ 
      code: 0, 
      message: '问题已升级给' + manager.manager_name,
      data: { escalation_id: result.insertId }
    })
  } catch (err) {
    next(err)
  }
})

// 获取待处理升级列表
router.get('/pending', auth, async (req, res, next) => {
  try {
    const { level } = req.query
    
    let sql = `
      SELECT e.*, 
        wl.content as log_content,
        u1.name as from_user,
        u2.name as to_user,
        TIMESTAMPDIFF(HOUR, e.created_at, NOW()) as hours_pending,
        sla.first_response_at
      FROM log_escalations e
      JOIN work_logs wl ON e.log_id = wl.id
      JOIN users u1 ON wl.user_id = u1.id
      JOIN users u2 ON e.to_user_id = u2.id
      LEFT JOIN escalation_sla sla ON e.id = sla.escalation_id
      WHERE e.status = 'pending'
    `
    const params = []
    
    if (level) {
      sql += ' AND e.to_level = ?'
      params.push(level)
    }
    
    sql += ' ORDER BY e.urgency DESC, e.created_at ASC'
    
    const [rows] = await pool.query(sql, params)
    
    // 标记是否超时
    const [configs] = await pool.query(`SELECT * FROM escalation_config`)
    const configMap = {}
    configs.forEach(c => configMap[c.level] = c)
    
    rows.forEach(r => {
      const config = configMap[r.to_level]
      r.is_overdue = config ? r.hours_pending > config.timeout_hours : false
      r.urgency_label = r.urgency === 'high' ? '🔴紧急' : r.urgency === 'normal' ? '🟡普通' : '🟢低'
    })
    
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// 获取升级统计
router.get('/stats', auth, async (req, res, next) => {
  try {
    // 总待处理
    const [[pending]] = await pool.query(`
      SELECT COUNT(*) as cnt FROM log_escalations WHERE status = 'pending'
    `)
    
    // 按级别统计
    const [byLevel] = await pool.query(`
      SELECT to_level, COUNT(*) as cnt 
      FROM log_escalations WHERE status = 'pending'
      GROUP BY to_level
    `)
    
    // 已解决统计
    const [resolved] = await pool.query(`
      SELECT COUNT(*) as cnt FROM log_escalations WHERE status = 'resolved'
    `)
    
    // 平均解决时长
    const [[avgTime]] = await pool.query(`
      SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours
      FROM log_escalations WHERE status = 'resolved' AND resolved_at IS NOT NULL
    `)
    
    // 超时未处理
    const [overdue] = await pool.query(`
      SELECT COUNT(*) as cnt FROM log_escalations e
      JOIN escalation_config c ON e.to_level = c.level
      WHERE e.status = 'pending' 
      AND TIMESTAMPDIFF(HOUR, e.created_at, NOW()) > c.timeout_hours
    `)
    
    res.json({
      code: 0,
      data: {
        pending: pending.cnt,
        resolved: resolved[0]?.cnt || 0,
        overdue: overdue[0]?.cnt || 0,
        avg_resolution_hours: avgTime?.avg_hours ? Math.round(avgTime.avg_hours) : 0,
        by_level: byLevel
      },
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

// 获取升级人员表现统计
router.get('/performance', auth, async (req, res, next) => {
  try {
    // 每人处理升级次数和平均时长
    const [rows] = await pool.query(`
      SELECT 
        u.id, u.name,
        COUNT(e.id) as total_assigned,
        SUM(CASE WHEN e.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
        AVG(CASE WHEN e.resolved_at IS NOT NULL 
          THEN TIMESTAMPDIFF(HOUR, e.created_at, e.resolved_at) 
          ELSE NULL END) as avg_hours
      FROM users u
      LEFT JOIN log_escalations e ON u.id = e.to_user_id
      WHERE e.to_level > 0
      GROUP BY u.id, u.name
      ORDER BY total_assigned DESC
    `)
    
    rows.forEach(r => {
      r.avg_hours = r.avg_hours ? Math.round(r.avg_hours) : null
      r.resolution_rate = r.total_assigned > 0 
        ? (r.resolved_count / r.total_assigned * 100).toFixed(0) + '%' 
        : '0%'
    })
    
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// 标记升级已处理
router.put('/:id/resolve', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    
    await pool.query(`
      UPDATE log_escalations 
      SET status = 'resolved', resolved_at = NOW()
      WHERE id = ?
    `, [id])
    
    // 更新SLA
    await pool.query(`
      UPDATE escalation_sla 
      SET resolved_at = NOW(), is_on_time = 1
      WHERE escalation_id = ?
    `, [id])
    
    res.json({ code: 0, message: '升级已处理' })
  } catch (err) {
    next(err)
  }
})

// 首次响应（标记为已读）
router.put('/:id/read', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    
    await pool.query(`
      UPDATE escalation_sla 
      SET first_response_at = NOW()
      WHERE escalation_id = ?
    `, [id])
    
    res.json({ code: 0, message: '已标记已读' })
  } catch (err) {
    next(err)
  }
})

export default router
