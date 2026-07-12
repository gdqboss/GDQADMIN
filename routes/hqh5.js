import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// ============ 简易限流（防止 demo 期间被恶意刷脏数据） ============
// 每个 IP 每分钟最多 60 次写操作（读操作不限）
const writeRateLimit = new Map()
const WRITE_LIMIT = 60
const WINDOW_MS = 60 * 1000

function checkRateLimit(ip) {
  const now = Date.now()
  const rec = writeRateLimit.get(ip) || { count: 0, resetAt: now + WINDOW_MS }
  if (now > rec.resetAt) {
    rec.count = 0
    rec.resetAt = now + WINDOW_MS
  }
  rec.count++
  writeRateLimit.set(ip, rec)
  return rec.count <= WRITE_LIMIT
}

// 定期清理过期限流记录
setInterval(() => {
  const now = Date.now()
  for (const [ip, rec] of writeRateLimit.entries()) {
    if (now > rec.resetAt) writeRateLimit.delete(ip)
  }
}, 5 * 60 * 1000)

// 简易输入净化（防止 XSS 入库）
function sanitize(str, maxLen = 500) {
  if (typeof str !== 'string') return ''
  return str.replace(/[<>]/g, '').trim().slice(0, maxLen)
}

// ============ 健康检查 ============
router.get('/health', async (req, res) => {
  try {
    const [[row]] = await pool.query('SELECT 1 as ok')
    res.json({
      code: 0,
      data: { status: 'ok', db: row.ok === 1 ? 'connected' : 'error', timestamp: new Date().toISOString() },
      message: '横琴湾区 H5 模块运行正常'
    })
  } catch (e) {
    res.status(500).json({ code: 500, message: '数据库连接失败: ' + e.message })
  }
})

// ============ 11 张核心表 schema (按需创建) ============
const TABLES = [
  // 1. 入驻企业
  `CREATE TABLE IF NOT EXISTS hqh5_enterprises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    logo VARCHAR(500),
    industry VARCHAR(100),
    address VARCHAR(500),
    contact_phone VARCHAR(20),
    contact_email VARCHAR(100),
    employee_count INT DEFAULT 0,
    status ENUM('pending','active','suspended','left') DEFAULT 'active',
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_name (name)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='入驻企业表'`,

  // 2. 考勤记录
  `CREATE TABLE IF NOT EXISTS hqh5_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_name VARCHAR(100),
    date DATE NOT NULL,
    clock_in TIME,
    clock_out TIME,
    type ENUM('work','remote','business') DEFAULT 'work',
    location VARCHAR(200),
    status ENUM('normal','late','early','absent','leave') DEFAULT 'normal',
    remark VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_date (user_id, date),
    INDEX idx_date (date)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='考勤记录'`,

  // 3. 申请审批
  `CREATE TABLE IF NOT EXISTS hqh5_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_name VARCHAR(100),
    type ENUM('leave','overtime','expense','business_trip','remote') NOT NULL,
    title VARCHAR(200) NOT NULL,
    reason TEXT,
    start_date DATETIME,
    end_date DATETIME,
    days DECIMAL(3,1),
    amount DECIMAL(10,2),
    status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    approver_id INT,
    approver_name VARCHAR(100),
    approved_at DATETIME,
    approver_comment VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_type (type)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='申请审批'`,

  // 4. 审批历史轨迹
  `CREATE TABLE IF NOT EXISTS hqh5_approval_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    approval_id INT NOT NULL,
    operator_id INT,
    operator_name VARCHAR(100),
    action VARCHAR(50),
    comment VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_approval (approval_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='审批历史'`,

  // 5. 会议室
  `CREATE TABLE IF NOT EXISTS hqh5_venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    capacity INT,
    location VARCHAR(200),
    facilities VARCHAR(500),
    image VARCHAR(500),
    status ENUM('available','maintenance') DEFAULT 'available',
    INDEX idx_status (status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会议室'`,

  // 6. 会议室预约
  `CREATE TABLE IF NOT EXISTS hqh5_venue_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    user_id INT NOT NULL,
    user_name VARCHAR(100),
    title VARCHAR(200),
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    attendees INT DEFAULT 1,
    remark VARCHAR(500),
    status ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_venue_date (venue_id, date),
    INDEX idx_user (user_id),
    INDEX idx_date (date)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会议室预约'`,

  // 7. 管家服务工单
  `CREATE TABLE IF NOT EXISTS hqh5_butler_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_name VARCHAR(100),
    type ENUM('it','express','moving','cleaning','parking') NOT NULL,
    title VARCHAR(200),
    description TEXT,
    location VARCHAR(200),
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('open','assigned','processing','completed','cancelled') DEFAULT 'open',
    assigned_to VARCHAR(100),
    sla_deadline DATETIME,
    completed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_type (type)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管家服务工单'`,

  // 8. 文章
  `CREATE TABLE IF NOT EXISTS hqh5_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(300) NOT NULL,
    category VARCHAR(100),
    tags VARCHAR(500),
    summary VARCHAR(500),
    content TEXT,
    cover_image VARCHAR(500),
    author VARCHAR(100),
    view_count INT DEFAULT 0,
    status ENUM('draft','published','archived') DEFAULT 'draft',
    published_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_published (published_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章'`,

  // 9. 通知
  `CREATE TABLE IF NOT EXISTS hqh5_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    type ENUM('system','activity','announcement','urgent') DEFAULT 'system',
    target ENUM('all','employee','admin','specific') DEFAULT 'all',
    priority ENUM('low','medium','high') DEFAULT 'medium',
    status ENUM('draft','sent','scheduled') DEFAULT 'draft',
    scheduled_at DATETIME,
    sent_at DATETIME,
    sender_id INT,
    sender_name VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_target (target)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通知'`,

  // 10. 通知已读记录
  `CREATE TABLE IF NOT EXISTS hqh5_notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_notif_user (notification_id, user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通知已读'`,

  // 11. 活动
  `CREATE TABLE IF NOT EXISTS hqh5_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    subtitle VARCHAR(500),
    cover_image VARCHAR(500),
    content TEXT,
    location VARCHAR(200),
    start_at DATETIME,
    end_at DATETIME,
    max_participants INT DEFAULT 0,
    current_participants INT DEFAULT 0,
    status ENUM('draft','open','full','closed','cancelled') DEFAULT 'draft',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_start (start_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='活动'`,

  // ===== 12. CRM 客户线索 =====
  `CREATE TABLE IF NOT EXISTS hqh5_crm_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    company VARCHAR(200),
    phone VARCHAR(20),
    email VARCHAR(100),
    interest VARCHAR(200),
    source VARCHAR(50) DEFAULT '微信H5',
    stage ENUM('new','contacted','demo','negotiation','won','lost') DEFAULT 'new',
    notes TEXT,
    assigned_to VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_stage (stage),
    INDEX idx_assigned (assigned_to)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='CRM 客户线索'`,

  // ===== 13. 跟进记录 =====
  `CREATE TABLE IF NOT EXISTS hqh5_crm_followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    operator VARCHAR(100),
    action VARCHAR(50),
    content TEXT,
    next_step VARCHAR(200),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lead (lead_id),
    INDEX idx_created (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='CRM 跟进记录'`
]  

// 表初始化（启动时自动建表）
async function initTables() {
  for (const sql of TABLES) {
    try {
      await pool.query(sql)
    } catch (e) {
      console.error('[hqh5] 建表失败:', e.message)
    }
  }
  // ============ 在线 ALTER（幂等） ============
  // 给 attendance 加唯一键（防重复打卡）
  const alters = [
    // 已存在重复数据时先去重
    `DELETE a1 FROM hqh5_attendance a1 INNER JOIN hqh5_attendance a2
       WHERE a1.id > a2.id AND a1.user_id = a2.user_id AND a1.date = a2.date`,
    `ALTER TABLE hqh5_attendance DROP INDEX idx_user_date`,
    `ALTER TABLE hqh5_attendance ADD UNIQUE KEY uniq_user_date (user_id, date)`
  ]
  for (const sql of alters) {
    try {
      await pool.query(sql)
    } catch (e) {
      // 索引不存在 / 重复键已存在 等是可恢复错误
      console.log('[hqh5] ALTER skip:', e.code || e.message.slice(0, 80))
    }
  }
  console.log('[hqh5] 11 张表初始化完成')
}
initTables().catch(console.error)

// ============ 游客首页 API ============
router.get('/banners', async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, title, subtitle, cover_image, location as link_url FROM hqh5_activities
       WHERE status = 'open' AND cover_image IS NOT NULL
       ORDER BY start_at DESC LIMIT 5`
    )
    if (rows.length === 0) {
      return res.json({
        code: 0,
        data: [
          { id: 1, title: '横琴湾区创新中心', subtitle: '共建大湾区国际科技创新中心', cover_image: 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=800', link_url: '/hqh5/activity/1' },
          { id: 2, title: '企业服务月启动', subtitle: '10 大服务助力企业发展', cover_image: 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=800', link_url: '/hqh5/activity/2' },
          { id: 3, title: '科技沙龙邀请函', subtitle: 'AI 时代的企业转型之路', cover_image: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800', link_url: '/hqh5/activity/3' }
        ],
        message: 'banner (mock fallback)'
      })
    }
    res.json({ code: 0, data: rows })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

router.get('/activities', async (req, res) => {
  try {
    const { status = 'open', limit = 10 } = req.query
    const [rows] = await pool.query(
      `SELECT id, title, subtitle, cover_image, location, start_at, end_at, max_participants, current_participants
       FROM hqh5_activities WHERE status = ? ORDER BY start_at DESC LIMIT ?`,
      [status, parseInt(limit)]
    )
    res.json({ code: 0, data: rows })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

router.get('/articles', async (req, res) => {
  try {
    const { page = 1, pageSize = 10, category } = req.query
    const offset = (parseInt(page) - 1) * parseInt(pageSize)
    let where = `status = 'published'`
    const params = []
    if (category) { where += ` AND category = ?`; params.push(category) }
    const [rows] = await pool.query(
      `SELECT id, title, summary, cover_image, author, view_count, published_at, category
       FROM hqh5_articles WHERE ${where} ORDER BY published_at DESC LIMIT ? OFFSET ?`,
      [...params, parseInt(pageSize), offset]
    )
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM hqh5_articles WHERE ${where}`, params
    )
    res.json({ code: 0, data: { list: rows, total, page: parseInt(page), pageSize: parseInt(pageSize) } })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// ============ 考勤 API ============
router.get('/attendance/list', async (req, res) => {
  try {
    const { user_id, month } = req.query
    const ym = month || new Date().toISOString().slice(0, 7)
    const where = [`DATE_FORMAT(date, '%Y-%m') = ?`]
    const params = [ym]
    if (user_id) { where.push('user_id = ?'); params.push(parseInt(user_id)) }
    const [rows] = await pool.query(
      `SELECT * FROM hqh5_attendance WHERE ${where.join(' AND ')} ORDER BY date DESC LIMIT 100`,
      params
    )
    res.json({ code: 0, data: rows })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

router.post('/attendance/clock', async (req, res) => {
  try {
    const { user_id, user_name, type, location } = req.body
    if (!user_id) return res.status(400).json({ code: 400, message: 'user_id 必填' })
    const today = new Date().toISOString().slice(0, 10)
    const now = new Date().toTimeString().slice(0, 8)
    const isClockIn = !req.body.clock_in
    const field = isClockIn ? 'clock_in' : 'clock_out'
    await pool.query(
      `INSERT INTO hqh5_attendance (user_id, user_name, date, ${field}, type, location)
       VALUES (?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE ${field} = VALUES(${field})`,
      [user_id, user_name || '', today, now, type || 'work', location || '']
    )
    res.json({ code: 0, data: { date: today, [field]: now }, message: isClockIn ? '上班打卡成功' : '下班打卡成功' })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// ============ 审批 API ============
router.get('/approvals/list', async (req, res) => {
  try {
    const { user_id, status, page = 1, pageSize = 20 } = req.query
    const offset = (parseInt(page) - 1) * parseInt(pageSize)
    const where = ['1=1']
    const params = []
    if (user_id) { where.push('user_id = ?'); params.push(parseInt(user_id)) }
    if (status) { where.push('status = ?'); params.push(status) }
    const [rows] = await pool.query(
      `SELECT * FROM hqh5_approvals WHERE ${where.join(' AND ')} ORDER BY created_at DESC LIMIT ? OFFSET ?`,
      [...params, parseInt(pageSize), offset]
    )
    res.json({ code: 0, data: rows })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

router.post('/approvals/create', async (req, res) => {
  try {
    const { user_id, user_name, type, title, reason, start_date, end_date, days, amount } = req.body
    if (!user_id || !type || !title) return res.status(400).json({ code: 400, message: 'user_id/type/title 必填' })
    const [result] = await pool.query(
      `INSERT INTO hqh5_approvals (user_id, user_name, type, title, reason, start_date, end_date, days, amount)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [user_id, user_name || '', type, title, reason || '', start_date || null, end_date || null, days || 0, amount || 0]
    )
    await pool.query(
      `INSERT INTO hqh5_approval_history (approval_id, operator_id, operator_name, action, comment) VALUES (?, ?, ?, ?, ?)`,
      [result.insertId, user_id, user_name, 'submit', '提交申请']
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '申请提交成功' })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

router.post('/approvals/:id/approve', async (req, res) => {
  try {
    const { approver_id, approver_name, comment, action } = req.body
    const newStatus = action === 'approve' ? 'approved' : 'rejected'
    await pool.query(
      `UPDATE hqh5_approvals SET status=?, approver_id=?, approver_name=?, approver_comment=?, approved_at=NOW() WHERE id=?`,
      [newStatus, approver_id, approver_name, comment || '', req.params.id]
    )
    await pool.query(
      `INSERT INTO hqh5_approval_history (approval_id, operator_id, operator_name, action, comment) VALUES (?, ?, ?, ?, ?)`,
      [req.params.id, approver_id, approver_name, newStatus, comment || '']
    )
    res.json({ code: 0, message: action === 'approve' ? '已批准' : '已拒绝' })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// ============ 会议室 API（含冲突检测） ============
router.get('/venues/list', async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT v.*,
        (SELECT COUNT(*) FROM hqh5_venue_bookings b
         WHERE b.venue_id = v.id AND b.date = CURDATE() AND b.status IN ('pending','confirmed')) as today_bookings
       FROM hqh5_venues v WHERE v.status = 'available' ORDER BY v.id`
    )
    res.json({ code: 0, data: rows })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

router.get('/venues/availability', async (req, res) => {
  try {
    const { venue_id, date } = req.query
    if (!venue_id || !date) return res.status(400).json({ code: 400, message: 'venue_id 和 date 必填' })
    const [booked] = await pool.query(
      `SELECT start_time, end_time, user_name, title FROM hqh5_venue_bookings
       WHERE venue_id = ? AND date = ? AND status IN ('pending','confirmed') ORDER BY start_time`,
      [parseInt(venue_id), date]
    )
    const slots = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00']
    const availability = slots.map(slot => {
      const isBooked = booked.some(b => b.start_time <= slot && b.end_time > slot)
      return { time: slot, available: !isBooked, booked_by: isBooked ? booked.find(b => b.start_time <= slot && b.end_time > slot)?.user_name : null }
    })
    res.json({ code: 0, data: { date, venue_id: parseInt(venue_id), slots: availability } })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

router.post('/venues/book', async (req, res) => {
  try {
    const { venue_id, user_id, user_name, title, date, start_time, end_time, attendees, remark } = req.body
    if (!venue_id || !user_id || !date || !start_time || !end_time) {
      return res.status(400).json({ code: 400, message: '必填字段缺失' })
    }
    // 冲突检测
    const [conflicts] = await pool.query(
      `SELECT id, user_name, title FROM hqh5_venue_bookings
       WHERE venue_id = ? AND date = ? AND status IN ('pending','confirmed')
       AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?) OR (start_time >= ? AND end_time <= ?))`,
      [parseInt(venue_id), date, end_time, start_time, end_time, start_time, start_time, end_time]
    )
    if (conflicts.length > 0) {
      return res.status(409).json({
        code: 409,
        message: `时段冲突：已被 ${conflicts[0].user_name} 预约为「${conflicts[0].title}」`,
        data: { conflicts }
      })
    }
    const [result] = await pool.query(
      `INSERT INTO hqh5_venue_bookings (venue_id, user_id, user_name, title, date, start_time, end_time, attendees, remark)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [parseInt(venue_id), user_id, user_name || '', title || '', date, start_time, end_time, attendees || 1, remark || '']
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '预约成功' })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// ============ 管家服务 API（含 SLA） ============
router.get('/butler/services', async (req, res) => {
  try {
    const { user_id, status, type } = req.query
    const where = ['1=1']
    const params = []
    if (user_id) { where.push('user_id = ?'); params.push(parseInt(user_id)) }
    if (status) { where.push('status = ?'); params.push(status) }
    if (type) { where.push('type = ?'); params.push(type) }
    const [rows] = await pool.query(
      `SELECT * FROM hqh5_butler_services WHERE ${where.join(' AND ')} ORDER BY created_at DESC LIMIT 100`,
      params
    )
    res.json({ code: 0, data: rows })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

router.post('/butler/create', async (req, res) => {
  try {
    const { user_id, user_name, type, title, description, location, priority } = req.body
    if (!user_id || !type || !title) return res.status(400).json({ code: 400, message: '必填字段缺失' })
    // SLA: 根据优先级设置截止时间
    const slaHours = { urgent: 2, high: 8, medium: 24, low: 72 }
    const deadline = new Date(Date.now() + slaHours[priority || 'medium'] * 3600 * 1000)
    const [result] = await pool.query(
      `INSERT INTO hqh5_butler_services (user_id, user_name, type, title, description, location, priority, sla_deadline)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [user_id, user_name || '', type, title, description || '', location || '', priority || 'medium', deadline]
    )
    res.json({ code: 0, data: { id: result.insertId, sla_deadline: deadline }, message: `工单创建成功，SLA 截止 ${deadline.toLocaleString('zh-CN')}` })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

router.post('/butler/:id/assign', async (req, res) => {
  try {
    const { assigned_to } = req.body
    await pool.query(
      `UPDATE hqh5_butler_services SET assigned_to=?, status='assigned' WHERE id=?`,
      [assigned_to, req.params.id]
    )
    res.json({ code: 0, message: '已分配给 ' + assigned_to })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

router.post('/butler/:id/complete', async (req, res) => {
  try {
    await pool.query(
      `UPDATE hqh5_butler_services SET status='completed', completed_at=NOW() WHERE id=?`,
      [req.params.id]
    )
    res.json({ code: 0, message: '已完成' })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// ============ 文章 API ============
router.post('/articles/create', async (req, res) => {
  try {
    const { title, category, tags, summary, content, cover_image, author, status } = req.body
    if (!title || !content) return res.status(400).json({ code: 400, message: 'title 和 content 必填' })
    const isPublished = status === 'published'
    const [result] = await pool.query(
      `INSERT INTO hqh5_articles (title, category, tags, summary, content, cover_image, author, status, published_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [title, category || '', tags || '', summary || '', content, cover_image || '', author || '', status || 'draft', isPublished ? new Date() : null]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '文章已' + (isPublished ? '发布' : '保存草稿') })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

router.get('/articles/:id', async (req, res) => {
  try {
    await pool.query(`UPDATE hqh5_articles SET view_count = view_count + 1 WHERE id=?`, [req.params.id])
    const [[row]] = await pool.query(`SELECT * FROM hqh5_articles WHERE id=?`, [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '文章不存在' })
    res.json({ code: 0, data: row })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// ============ 通知 API ============
router.get('/notifications/list', async (req, res) => {
  try {
    const { user_id, status, page = 1, pageSize = 20 } = req.query
    const offset = (parseInt(page) - 1) * parseInt(pageSize)
    const where = ['1=1']
    const params = []
    if (status) { where.push('status = ?'); params.push(status) }
    const [rows] = await pool.query(
      `SELECT n.*,
        (SELECT COUNT(*) FROM hqh5_notification_reads WHERE notification_id = n.id AND user_id = ?) as is_read
       FROM hqh5_notifications n WHERE ${where.join(' AND ')} ORDER BY n.created_at DESC LIMIT ? OFFSET ?`,
      [parseInt(user_id) || 0, ...params, parseInt(pageSize), offset]
    )
    res.json({ code: 0, data: rows })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

router.post('/notifications/push', async (req, res) => {
  try {
    const { title, content, type, target, priority, sender_id, sender_name } = req.body
    if (!title || !content) return res.status(400).json({ code: 400, message: 'title 和 content 必填' })
    const [result] = await pool.query(
      `INSERT INTO hqh5_notifications (title, content, type, target, priority, status, sent_at, sender_id, sender_name)
       VALUES (?, ?, ?, ?, ?, 'sent', NOW(), ?, ?)`,
      [title, content, type || 'system', target || 'all', priority || 'medium', sender_id, sender_name || '']
    )
    res.json({ code: 0, data: { id: result.insertId, sent_at: new Date() }, message: `通知已发送给 ${target === 'all' ? '所有人' : target}` })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

router.post('/notifications/:id/read', async (req, res) => {
  try {
    const { user_id } = req.body
    if (!user_id) return res.status(400).json({ code: 400, message: 'user_id 必填' })
    await pool.query(
      `INSERT IGNORE INTO hqh5_notification_reads (notification_id, user_id) VALUES (?, ?)`,
      [req.params.id, user_id]
    )
    res.json({ code: 0, message: '已读' })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// ============ 看板 API（多维聚合） ============
router.get('/dashboard/overview', async (req, res) => {
  try {
    const today = new Date().toISOString().slice(0, 10)
    const [[stats]] = await pool.query(`
      SELECT
        (SELECT COUNT(*) FROM hqh5_enterprises WHERE status='active') as enterprises,
        (SELECT COUNT(*) FROM hqh5_attendance WHERE date = ?) as today_attendance,
        (SELECT COUNT(*) FROM hqh5_approvals WHERE status='pending') as pending_approvals,
        (SELECT COUNT(*) FROM hqh5_venue_bookings WHERE date = ?) as today_venues,
        (SELECT COUNT(*) FROM hqh5_butler_services WHERE status='open') as open_butler,
        (SELECT COUNT(*) FROM hqh5_articles WHERE status='published') as articles,
        (SELECT COUNT(*) FROM hqh5_activities WHERE status='open') as active_activities,
        (SELECT COUNT(*) FROM hqh5_notifications WHERE status='sent') as sent_notifications
    `, [today, today])

    const [butlerByType] = await pool.query(`
      SELECT type, COUNT(*) as count FROM hqh5_butler_services
      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      GROUP BY type
    `)

    const [weeklyAttendance] = await pool.query(`
      SELECT DATE_FORMAT(date, '%m-%d') as day, COUNT(*) as count
      FROM hqh5_attendance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
      GROUP BY date ORDER BY date
    `)

    res.json({
      code: 0,
      data: {
        stats,
        butler_by_type: butlerByType,
        weekly_attendance: weeklyAttendance,
        timestamp: new Date().toISOString()
      }
    })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// ============ 企业 API ============
router.get('/enterprises/list', async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT * FROM hqh5_enterprises ORDER BY joined_at DESC LIMIT 100`
    )
    res.json({ code: 0, data: rows })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

router.post('/enterprises/create', async (req, res) => {
  try {
    const { name, industry, address, contact_phone, contact_email, employee_count } = req.body
    if (!name) return res.status(400).json({ code: 400, message: '企业名称必填' })
    const [result] = await pool.query(
      `INSERT INTO hqh5_enterprises (name, industry, address, contact_phone, contact_email, employee_count, status)
       VALUES (?, ?, ?, ?, ?, ?, 'pending')`,
      [name, industry || '', address || '', contact_phone || '', contact_email || '', employee_count || 0]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '企业入驻申请已提交' })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// ============ CRM 客户线索 ============

// 列表（按阶段筛选）
router.get('/crm/leads', async (req, res) => {
  try {
    const { stage, assigned_to, page = 1, pageSize = 20 } = req.query
    const where = ['1=1']
    const params = []
    if (stage) { where.push('stage = ?'); params.push(stage) }
    if (assigned_to) { where.push('assigned_to = ?'); params.push(assigned_to) }
    const offset = (parseInt(page) - 1) * parseInt(pageSize)
    const [rows] = await pool.query(
      `SELECT * FROM hqh5_crm_leads WHERE ${where.join(' AND ')}
       ORDER BY updated_at DESC LIMIT ? OFFSET ?`,
      [...params, parseInt(pageSize), offset]
    )
    const [[{ total }]] = await pool.query(
      `SELECT COUNT(*) as total FROM hqh5_crm_leads WHERE ${where.join(' AND ')}`,
      params
    )
    const [[stats]] = await pool.query(
      `SELECT stage, COUNT(*) as count FROM hqh5_crm_leads GROUP BY stage`
    )
    res.json({
      code: 0,
      data: {
        list: rows,
        total,
        stats: stats || []
      }
    })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// 创建线索
router.post('/crm/leads', async (req, res) => {
  try {
    const { name, company, phone, email, interest, source, assigned_to, notes } = req.body
    if (!name || !phone) return res.status(400).json({ code: 400, message: 'name/phone 必填' })
    const [result] = await pool.query(
      `INSERT INTO hqh5_crm_leads (name, company, phone, email, interest, source, assigned_to, notes)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [sanitize(name, 100), sanitize(company, 200), sanitize(phone, 20), sanitize(email, 100),
       sanitize(interest, 200), source || '微信H5', sanitize(assigned_to, 100), sanitize(notes, 500)]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: '客户线索已记录' })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// 更新线索阶段
router.patch('/crm/leads/:id', async (req, res) => {
  try {
    const { stage, notes, assigned_to } = req.body
    const update = []
    const params = []
    if (stage) { update.push('stage = ?'); params.push(stage) }
    if (notes) { update.push('notes = ?'); params.push(sanitize(notes, 500)) }
    if (assigned_to) { update.push('assigned_to = ?'); params.push(sanitize(assigned_to, 100)) }
    if (!update.length) return res.status(400).json({ code: 400, message: '无更新内容' })
    params.push(req.params.id)
    await pool.query(
      `UPDATE hqh5_crm_leads SET ${update.join(', ')} WHERE id = ?`,
      params
    )
    res.json({ code: 0, message: '已更新' })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// 添加跟进
router.post('/crm/leads/:id/followup', async (req, res) => {
  try {
    const { operator, action, content, next_step } = req.body
    if (!content) return res.status(400).json({ code: 400, message: '跟进内容必填' })
    const [result] = await pool.query(
      `INSERT INTO hqh5_crm_followups (lead_id, operator, action, content, next_step)
       VALUES (?, ?, ?, ?, ?)`,
      [req.params.id, sanitize(operator, 100), sanitize(action, 50),
       sanitize(content, 1000), sanitize(next_step, 200)]
    )
    // 触达/演示自动推进阶段
    if (action === 'demo' || action === 'visit') {
      await pool.query(`UPDATE hqh5_crm_leads SET stage='contacted' WHERE id=? AND stage='new'`, [req.params.id])
    }
    res.json({ code: 0, data: { id: result.insertId }, message: '跟进已记录' })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// 查询跟进历史
router.get('/crm/leads/:id/followups', async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT * FROM hqh5_crm_followups WHERE lead_id = ? ORDER BY created_at DESC`,
      [req.params.id]
    )
    res.json({ code: 0, data: rows })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// 看板统计
router.get('/crm/dashboard', async (req, res) => {
  try {
    const [stats] = await pool.query(`
      SELECT
        COUNT(*) as total,
        SUM(CASE WHEN stage='new' THEN 1 ELSE 0 END) as new_count,
        SUM(CASE WHEN stage='contacted' THEN 1 ELSE 0 END) as contacted,
        SUM(CASE WHEN stage='demo' THEN 1 ELSE 0 END) as demo_count,
        SUM(CASE WHEN stage='negotiation' THEN 1 ELSE 0 END) as negotiation,
        SUM(CASE WHEN stage='won' THEN 1 ELSE 0 END) as won,
        SUM(CASE WHEN stage='lost' THEN 1 ELSE 0 END) as lost
      FROM hqh5_crm_leads
    `)
    const [recent] = await pool.query(`
      SELECT l.*, (SELECT COUNT(*) FROM hqh5_crm_followups WHERE lead_id=l.id) as followup_count
      FROM hqh5_crm_leads l ORDER BY updated_at DESC LIMIT 5
    `)
    res.json({ code: 0, data: { stats: stats[0], recent } })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

// ============ Seed 演示数据（按需触发） ============
router.post('/seed', async (req, res) => {
  try {
    // 企业 5 个
    const enterprises = [
      ['横琴科技公司', '人工智能', '横琴新区', '13800138001', 'contact@hqtech.com', 50],
      ['澳门跨境电商', '电子商务', '横琴口岸', '13800138002', 'info@mc-ec.com', 30],
      ['湾区文创工作室', '文化创意', '横琴创意谷', '13800138003', 'hi@gzbay.cc', 12],
      ['生物医药研究院', '生物医药', '横琴科学城', '13800138004', 'lab@biomed.cn', 80],
      ['智能制造企业', '智能制造', '横琴产业园', '13800138005', 'mfg@smart.cn', 200]
    ]
    for (const e of enterprises) {
      await pool.query(`INSERT IGNORE INTO hqh5_enterprises (name, industry, address, contact_phone, contact_email, employee_count) VALUES (?, ?, ?, ?, ?, ?)`, e)
    }

    // 会议室 5 个
    const venues = [
      ['一号会议室', 20, 'A 栋 3 楼', '投影/白板/视频会议'],
      ['二号会议室', 12, 'A 栋 3 楼', '白板/视频会议'],
      ['贵宾接待室', 8, 'B 栋 1 楼', '沙发/茶水/投影'],
      ['路演大厅', 100, 'C 栋 1 楼', '舞台/灯光/音响/直播设备'],
      ['小型讨论室', 6, 'A 栋 4 楼', '白板/电视']
    ]
    for (const v of venues) {
      await pool.query(`INSERT IGNORE INTO hqh5_venues (name, capacity, location, facilities) VALUES (?, ?, ?, ?)`, v)
    }

    // 文章 8 篇
    const articles = [
      ['横琴粤澳深度合作区总体方案', '政策', '横琴,湾区,合作区', '中共中央 国务院印发《横琴粤澳深度合作区建设总体方案》', '<h2>横琴粤澳深度合作区</h2><p>这是中央政府支持澳门经济适度多元发展的重要举措...</p>', 'https://images.unsplash.com/photo-1531973576160-7125cd663d86?w=800', 'admin'],
      ['2026 横琴湾区创新创业大赛启动', '活动', '创业,大赛,创新', '报名通道已开启，奖金池 1000 万', '<p>2026 横琴湾区创新创业大赛正式启动...</p>', 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=800', 'admin'],
      ['企业服务月 10 大福利汇总', '服务', '服务月,福利,补贴', '租金减免、税收优惠、人才补贴等 10 项福利', '<h2>企业服务月 10 大福利</h2><ol><li>租金减免</li><li>税收优惠</li>...</ol>', 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=800', 'admin'],
      ['横琴人才公寓申请指南', '生活', '人才,公寓,住房', '三类人才可申请，最低月租 800 元', '<p>横琴人才公寓申请指南...</p>', 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=800', 'admin'],
      ['湾区科技沙龙第 12 期：AI 时代的企业转型', '沙龙', 'AI,沙龙,科技', '7 月 15 日晚 7 点，C 栋路演大厅', '<p>本期沙龙邀请了 5 位 AI 领域专家...</p>', 'https://images.unsplash.com/photo-1591115765373-5207764f72e7?w=800', 'admin'],
      ['横琴跨境电商税收政策解读', '政策', '跨境电商,税收', '澳门居民个税补贴政策详解', '<p>跨境电商税收优惠政策...</p>', 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?w=800', 'admin'],
      ['横琴湾区办公空间推荐 TOP10', '推荐', '办公空间,推荐', '从创业孵化器到独立办公室，总有一款适合你', '<p>横琴湾区办公空间推荐...</p>', 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=800', 'admin'],
      ['2026 年第二季度园区运营报告', '报告', '运营报告,季度', '新增企业 23 家，营收增长 35%', '<p>2026 年第二季度运营报告...</p>', 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800', 'admin']
    ]
    for (const a of articles) {
      await pool.query(`INSERT IGNORE INTO hqh5_articles (title, category, tags, summary, content, cover_image, author, status, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'published', NOW())`, a)
    }

    // 活动 5 个
    const activities = [
      ['横琴湾区创新中心启动仪式', '共建大湾区国际科技创新中心', 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=800', '<p>横琴湾区创新中心启动仪式...</p>', '横琴主会场', '2026-08-01 09:00:00', '2026-08-01 18:00:00', 500, 0],
      ['2026 创新创业大赛', '奖金池 1000 万，报名截止 7 月 31 日', 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=800', '<p>创新创业大赛详情...</p>', '横琴路演大厅', '2026-08-15 09:00:00', '2026-09-15 18:00:00', 100, 0],
      ['AI 时代企业转型沙龙', '5 位 AI 专家分享企业转型之路', 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800', '<p>AI 沙龙详情...</p>', 'C 栋路演大厅', '2026-07-15 19:00:00', '2026-07-15 21:30:00', 100, 0],
      ['跨境电商税收政策宣讲会', '澳门居民个税补贴政策详解', 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?w=800', '<p>政策宣讲详情...</p>', 'B 栋报告厅', '2026-07-20 14:00:00', '2026-07-20 17:00:00', 80, 0],
      ['横琴人才公寓配租仪式', '三类人才可申请，最低月租 800 元', 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=800', '<p>人才公寓配租详情...</p>', '横琴人才公寓', '2026-07-25 09:00:00', '2026-07-25 12:00:00', 200, 0]
    ]
    for (const ac of activities) {
      await pool.query(`INSERT IGNORE INTO hqh5_activities (title, subtitle, cover_image, content, location, start_at, end_at, max_participants, current_participants, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')`, ac)
    }

    // 通知 5 条
    const notifications = [
      ['园区 7 月 15 日临时停电通知', '因电力检修，7 月 15 日 8:00-12:00 园区临时停电，请各企业提前做好准备。', 'urgent', 'all', 'high', 1, 'admin'],
      ['2026 创新创业大赛报名通道已开启', '奖金池 1000 万，报名截止 7 月 31 日，立即报名。', 'activity', 'all', 'medium', 1, 'admin'],
      ['园区 WiFi 升级通知', '7 月 10 日 23:00-02:00 WiFi 系统升级，期间可能短暂掉线。', 'system', 'all', 'low', 1, 'admin'],
      ['7 月份园区活动汇总', '沙龙 / 大赛 / 宣讲会 5+ 场活动已上线，欢迎报名。', 'announcement', 'all', 'medium', 1, 'admin'],
      ['横琴湾区企业服务月启动', '10 大福利助力企业发展，详情请见服务专区。', 'announcement', 'all', 'medium', 1, 'admin']
    ]
    for (const n of notifications) {
      await pool.query(`INSERT IGNORE INTO hqh5_notifications (title, content, type, target, priority, status, sent_at, sender_id, sender_name) VALUES (?, ?, ?, ?, ?, 'sent', NOW(), ?, ?)`, n)
    }

    // 考勤数据 12 条（最近 12 天）
    const today = new Date()
    for (let i = 0; i < 12; i++) {
      const d = new Date(today)
      d.setDate(d.getDate() - i)
      const dateStr = d.toISOString().slice(0, 10)
      const clockIn = `09:${String(Math.floor(Math.random() * 30)).padStart(2, '0')}`
      const clockOut = `18:${String(Math.floor(Math.random() * 30)).padStart(2, '0')}`
      const status = Math.random() > 0.85 ? 'late' : 'normal'
      await pool.query(`INSERT IGNORE INTO hqh5_attendance (user_id, user_name, date, clock_in, clock_out, type, status) VALUES (1, '江清波', ?, ?, ?, 'work', ?)`, [dateStr, clockIn, clockOut, status])
    }

    // 工单 8 条
    const butlerServices = [
      [1, '江清波', 'it', '电脑无法连接打印机', '办公室打印机驱动异常，无法打印文件', 'A 栋 305', 'high'],
      [1, '江清波', 'express', '代收顺丰快递', '前台有顺丰快递需要代收', '前台', 'medium'],
      [2, '李明', 'moving', '会议室桌椅搬运', '需将 5 把椅子从 305 搬到 401', 'A 栋 305-401', 'low'],
      [2, '李明', 'cleaning', '会议室深度清洁', '路演大厅使用后需要深度清洁', 'C 栋路演大厅', 'medium'],
      [3, '张总', 'parking', '申请临时停车位', '明天下午有访客 3 人', 'B1 停车场', 'medium'],
      [3, '张总', 'it', '投影仪无法显示', '会议室投影仪无信号', 'A 栋 301', 'urgent'],
      [1, '江清波', 'it', '申请 VPN 账号', '在家办公需要 VPN', '远程', 'low'],
      [4, '王经理', 'moving', '办公室搬迁协助', '从 405 搬到 502', 'A 栋 405-502', 'medium']
    ]
    for (const b of butlerServices) {
      await pool.query(`INSERT IGNORE INTO hqh5_butler_services (user_id, user_name, type, title, description, location, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'open')`, b)
    }

    // 审批 6 条
    const approvals = [
      [1, '江清波', 'leave', '年假申请 - 7/20-7/22', '家庭旅行', '2026-07-20', '2026-07-22', 3, 0, 'pending'],
      [1, '江清波', 'overtime', '加班申请 - 7/12', '客户上线', '2026-07-12 19:00', '2026-07-12 22:00', 0, 3, 'pending'],
      [2, '李明', 'expense', '差旅报销 - 6 月深圳出差', '客户拜访差旅费', null, null, 0, 2350, 'pending'],
      [2, '李明', 'remote', '远程办公申请 - 7/15', '家里装修', '2026-07-15', '2026-07-15', 1, 0, 'approved'],
      [3, '张总', 'business_trip', '出差申请 - 北京 7/25-7/28', '总部汇报', '2026-07-25', '2026-07-28', 4, 0, 'pending'],
      [4, '王经理', 'leave', '病假 - 7/18', '感冒发烧', '2026-07-18', '2026-07-18', 1, 0, 'approved']
    ]
    for (const a of approvals) {
      await pool.query(`INSERT IGNORE INTO hqh5_approvals (user_id, user_name, type, title, reason, start_date, end_date, days, amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`, a)
    }

    res.json({
      code: 0,
      message: '演示数据已注入',
      data: {
        enterprises: enterprises.length,
        venues: venues.length,
        articles: articles.length,
        activities: activities.length,
        notifications: notifications.length,
        attendance: 12,
        butler: butlerServices.length,
        approvals: approvals.length
      }
    })
  } catch (e) {
    res.status(500).json({ code: 500, message: e.message })
  }
})

export default router