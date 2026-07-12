// 寺庙服务模块 - 后端 API
// 业务：骨灰盒管理、祭拜订单、功德捐赠、和尚管理
import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js'

const router = Router()

// 生成订单号
function genOrderNo(prefix) {
  const ts = Date.now().toString(36).toUpperCase()
  const rnd = Math.floor(Math.random() * 10000).toString().padStart(4, '0')
  return `${prefix}${ts}${rnd}`
}

// ============ 公开接口（C 端扫码，无需登录） ============

// GET /api/temple/casket/:code — 扫码查骨灰盒
router.get('/casket/:code', async (req, res) => {
  try {
    const { code } = req.params
    const [[casket]] = await pool.query(
      `SELECT c.id, c.casket_code, c.hall_name, c.row_no, c.col_no, c.position_desc,
              c.deposit_date, c.notes, c.status,
              a.id as ancestor_id, a.name as ancestor_name, a.gender, a.birth_date, a.death_date,
              a.age_at_death, a.hometown, a.photo_url, a.epitaph,
              a.family_contact_name, a.family_contact_relationship
       FROM temple_cinerary_caskets c
       LEFT JOIN temple_ancestors a ON c.ancestor_id = a.id
       WHERE c.casket_code = ?`,
      [code]
    )
    if (!casket) return res.json({ success: false, message: '未找到该骨灰盒' })

    // 记录扫码日志
    await pool.query(
      `INSERT INTO temple_scan_logs (casket_id, scan_source, ip, user_agent) VALUES (?, ?, ?, ?)`,
      [casket.id, 'qrcode', req.ip, req.headers['user-agent']?.substring(0, 500) || '']
    )

    // 脱敏：家属联系方式不返回
    delete casket.family_contact_phone
    res.json({ success: true, casket })
  } catch (err) {
    console.error('Get casket error:', err)
    res.status(500).json({ success: false, message: err.message })
  }
})

// POST /api/temple/memorial-order — C 端下单请和尚代劳
router.post('/memorial-order', async (req, res) => {
  try {
    const { casket_code, user_name, user_phone, service_type, scheduled_date, scheduled_time_slot,
            items, special_request } = req.body

    if (!casket_code || !user_name || !user_phone || !scheduled_date) {
      return res.status(400).json({ success: false, message: '缺少必填字段' })
    }

    // 查骨灰盒
    const [[casket]] = await pool.query(
      `SELECT id, ancestor_id FROM temple_cinerary_caskets WHERE casket_code = ?`,
      [casket_code]
    )
    if (!casket) return res.status(404).json({ success: false, message: '骨灰盒编号不存在' })

    // 计算总金额
    let total_amount = 0
    if (Array.isArray(items) && items.length > 0) {
      for (const it of items) {
        const price = Number(it.unit_price || 0)
        const qty = Number(it.qty || 1)
        total_amount += price * qty
      }
    }

    const order_no = genOrderNo('TM')
    const [result] = await pool.query(
      `INSERT INTO temple_memorial_events
       (order_no, casket_id, user_name, user_phone, service_type, scheduled_date,
        scheduled_time_slot, items_json, total_amount, special_request, status, payment_status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid')`,
      [order_no, casket.id, user_name, user_phone, service_type || 'full_set',
       scheduled_date, scheduled_time_slot || 'morning', JSON.stringify(items || []),
       total_amount, special_request || '']
    )

    res.json({ success: true, order_no, id: result.insertId, total_amount })
  } catch (err) {
    console.error('Create memorial order error:', err)
    res.status(500).json({ success: false, message: err.message })
  }
})

// GET /api/temple/memorial-order/:orderNo — 查订单状态（C 端用）
router.get('/memorial-order/:orderNo', async (req, res) => {
  try {
    const [[order]] = await pool.query(
      `SELECT e.id, e.order_no, e.service_type, e.scheduled_date, e.scheduled_time_slot,
              e.total_amount, e.status, e.payment_status, e.photo_feedback_url, e.feedback_at,
              e.created_at, e.special_request,
              c.casket_code, c.hall_name, c.position_desc,
              a.name as ancestor_name
       FROM temple_memorial_events e
       LEFT JOIN temple_cinerary_caskets c ON e.casket_id = c.id
       LEFT JOIN temple_ancestors a ON c.ancestor_id = a.id
       WHERE e.order_no = ?`,
      [req.params.orderNo]
    )
    if (!order) return res.json({ success: false, message: '订单不存在' })
    res.json({ success: true, order })
  } catch (err) {
    console.error('Get memorial order error:', err)
    res.status(500).json({ success: false, message: err.message })
  }
})

// POST /api/temple/donation — 在线捐赠
router.post('/donation', async (req, res) => {
  try {
    const { donor_name, donor_phone, casket_code, amount, purpose, purpose_note,
            payment_method, is_anonymous, blessing_message } = req.body

    if (!donor_name || !amount || amount <= 0) {
      return res.status(400).json({ success: false, message: '缺少必填字段' })
    }

    let casket_id = null
    if (casket_code) {
      const [[c]] = await pool.query(
        `SELECT id FROM temple_cinerary_caskets WHERE casket_code = ?`, [casket_code]
      )
      if (c) casket_id = c.id
    }

    const donation_no = genOrderNo('TD')
    const [result] = await pool.query(
      `INSERT INTO temple_donations
       (donation_no, donor_name, donor_phone, casket_id, amount, purpose, purpose_note,
        payment_method, is_anonymous, blessing_message, payment_status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid')`,
      [donation_no, donor_name, donor_phone || '', casket_id, amount,
       purpose || 'general', purpose_note || '', payment_method || 'wechat',
       is_anonymous ? 1 : 0, blessing_message || '']
    )

    res.json({ success: true, donation_no, id: result.insertId })
  } catch (err) {
    console.error('Create donation error:', err)
    res.status(500).json({ success: false, message: err.message })
  }
})

// GET /api/temple/monks/active — 列出可用和尚（C 端可选）
router.get('/monks/active', async (req, res) => {
  try {
    const [monks] = await pool.query(
      `SELECT id, name, dharma_name, specialty, avatar_url, bio
       FROM temple_monks WHERE status = 'active' ORDER BY id`
    )
    res.json({ success: true, monks })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// GET /api/temple/products — 列出鲜花/祭品商品（C 端下单用）
router.get('/products', async (req, res) => {
  try {
    const [products] = await pool.query(
      `SELECT p.id, p.name, p.image_main, p.sale_price, p.stock
       FROM products p
       WHERE p.status = 'active'
         AND (p.category_id IN (
           SELECT id FROM categories WHERE name LIKE '%祭祀%' OR name LIKE '%鲜花%' OR name LIKE '%祭品%'
         ) OR p.name LIKE '%鲜花%' OR p.name LIKE '%香%' OR p.name LIKE '%烛%' OR p.name LIKE '%祭%')
       ORDER BY p.id LIMIT 100`
    )
    res.json({ success: true, products })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// ============ 管理端接口（需登录 + 权限） ============

// 骨灰盒管理
router.get('/admin/caskets', requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT c.*, a.name as ancestor_name
       FROM temple_cinerary_caskets c
       LEFT JOIN temple_ancestors a ON c.ancestor_id = a.id
       ORDER BY c.id DESC LIMIT 500`
    )
    res.json({ success: true, list: rows, total: rows.length })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.post('/admin/caskets', requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { casket_code, hall_name, row_no, col_no, ancestor_id, status, deposit_date, notes } = req.body
    if (!casket_code) return res.status(400).json({ success: false, message: '骨灰盒编号必填' })
    const position_desc = [hall_name, row_no ? `${row_no}排` : '', col_no ? `${col_no}列` : ''].filter(Boolean).join(' ')
    const [r] = await pool.query(
      `INSERT INTO temple_cinerary_caskets (casket_code, hall_name, row_no, col_no, position_desc,
        ancestor_id, status, deposit_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [casket_code, hall_name || '', row_no || '', col_no || '', position_desc,
       ancestor_id || null, status || 'in_use', deposit_date || null, notes || '']
    )
    res.json({ success: true, id: r.insertId })
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') return res.status(400).json({ success: false, message: '骨灰盒编号已存在' })
    res.status(500).json({ success: false, message: err.message })
  }
})

router.put('/admin/caskets/:id', requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { hall_name, row_no, col_no, ancestor_id, status, deposit_date, notes } = req.body
    const position_desc = [hall_name, row_no ? `${row_no}排` : '', col_no ? `${col_no}列` : ''].filter(Boolean).join(' ')
    await pool.query(
      `UPDATE temple_cinerary_caskets SET hall_name=?, row_no=?, col_no=?, position_desc=?,
        ancestor_id=?, status=?, deposit_date=?, notes=? WHERE id=?`,
      [hall_name || '', row_no || '', col_no || '', position_desc,
       ancestor_id || null, status || 'in_use', deposit_date || null, notes || '', req.params.id]
    )
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.delete('/admin/caskets/:id', requirePermission(PERMISSIONS.TEMPLE_DELETE), async (req, res) => {
  try {
    await pool.query(`DELETE FROM temple_cinerary_caskets WHERE id = ?`, [req.params.id])
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// 逝者档案
router.get('/admin/ancestors', requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT * FROM temple_ancestors ORDER BY id DESC LIMIT 500`
    )
    res.json({ success: true, list: rows, total: rows.length })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.post('/admin/ancestors', requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { name, gender, birth_date, death_date, age_at_death, hometown, photo_url, epitaph,
            family_contact_name, family_contact_phone, family_relationship } = req.body
    if (!name) return res.status(400).json({ success: false, message: '姓名必填' })
    const [r] = await pool.query(
      `INSERT INTO temple_ancestors (name, gender, birth_date, death_date, age_at_death, hometown,
        photo_url, epitaph, family_contact_name, family_contact_phone, family_relationship)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [name, gender || 'unknown', birth_date || null, death_date || null,
       age_at_death || null, hometown || '', photo_url || '', epitaph || '',
       family_contact_name || '', family_contact_phone || '', family_relationship || '']
    )
    res.json({ success: true, id: r.insertId })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.put('/admin/ancestors/:id', requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { name, gender, birth_date, death_date, age_at_death, hometown, photo_url, epitaph,
            family_contact_name, family_contact_phone, family_relationship } = req.body
    await pool.query(
      `UPDATE temple_ancestors SET name=?, gender=?, birth_date=?, death_date=?, age_at_death=?,
        hometown=?, photo_url=?, epitaph=?, family_contact_name=?, family_contact_phone=?, family_relationship=?
       WHERE id=?`,
      [name, gender || 'unknown', birth_date || null, death_date || null,
       age_at_death || null, hometown || '', photo_url || '', epitaph || '',
       family_contact_name || '', family_contact_phone || '', family_relationship || '',
       req.params.id]
    )
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// 祭拜订单管理
router.get('/admin/memorial-orders', requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const { status } = req.query
    let sql = `SELECT e.*, c.casket_code, c.hall_name, a.name as ancestor_name, m.name as monk_name
               FROM temple_memorial_events e
               LEFT JOIN temple_cinerary_caskets c ON e.casket_id = c.id
               LEFT JOIN temple_ancestors a ON c.ancestor_id = a.id
               LEFT JOIN temple_monks m ON e.monk_id = m.id`
    const params = []
    if (status) { sql += ` WHERE e.status = ?`; params.push(status) }
    sql += ` ORDER BY e.id DESC LIMIT 500`
    const [rows] = await pool.query(sql, params)
    res.json({ success: true, list: rows, total: rows.length })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.put('/admin/memorial-orders/:id/status', requirePermission(PERMISSIONS.TEMPLE_DISPATCH), async (req, res) => {
  try {
    const { status, monk_id, photo_feedback_url } = req.body
    if (!status) return res.status(400).json({ success: false, message: '状态必填' })
    const fields = ['status = ?']
    const params = [status]
    if (monk_id !== undefined) { fields.push('monk_id = ?'); params.push(monk_id) }
    if (photo_feedback_url) { fields.push('photo_feedback_url = ?', 'feedback_at = NOW()'); params.push(photo_feedback_url) }
    params.push(req.params.id)
    await pool.query(`UPDATE temple_memorial_events SET ${fields.join(', ')} WHERE id = ?`, params)
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// 捐赠管理
router.get('/admin/donations', requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT d.*, c.casket_code, a.name as ancestor_name
       FROM temple_donations d
       LEFT JOIN temple_cinerary_caskets c ON d.casket_id = c.id
       LEFT JOIN temple_ancestors a ON c.ancestor_id = a.id
       ORDER BY d.id DESC LIMIT 500`
    )
    res.json({ success: true, list: rows, total: rows.length })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.put('/admin/donations/:id/paid', requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    await pool.query(
      `UPDATE temple_donations SET payment_status = 'paid', paid_at = NOW() WHERE id = ?`,
      [req.params.id]
    )
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// 和尚管理
router.get('/admin/monks', requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const [rows] = await pool.query(`SELECT * FROM temple_monks ORDER BY id DESC LIMIT 200`)
    res.json({ success: true, list: rows, total: rows.length })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.post('/admin/monks', requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { name, dharma_name, specialty, phone, avatar_url, bio } = req.body
    if (!name) return res.status(400).json({ success: false, message: '姓名必填' })
    const [r] = await pool.query(
      `INSERT INTO temple_monks (name, dharma_name, specialty, phone, avatar_url, bio)
       VALUES (?, ?, ?, ?, ?, ?)`,
      [name, dharma_name || '', specialty || '', phone || '', avatar_url || '', bio || '']
    )
    res.json({ success: true, id: r.insertId })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.put('/admin/monks/:id', requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { name, dharma_name, specialty, phone, avatar_url, bio, status } = req.body
    await pool.query(
      `UPDATE temple_monks SET name=?, dharma_name=?, specialty=?, phone=?, avatar_url=?, bio=?, status=?
       WHERE id=?`,
      [name, dharma_name || '', specialty || '', phone || '', avatar_url || '', bio || '',
       status || 'active', req.params.id]
    )
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// 统计
router.get('/admin/stats', requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const [[stats]] = await pool.query(`
      SELECT
        (SELECT COUNT(*) FROM temple_cinerary_caskets WHERE status = 'in_use') as casket_used,
        (SELECT COUNT(*) FROM temple_cinerary_caskets) as casket_total,
        (SELECT COUNT(*) FROM temple_memorial_events WHERE status = 'pending') as order_pending,
        (SELECT COUNT(*) FROM temple_memorial_events WHERE status = 'completed') as order_completed,
        (SELECT COALESCE(SUM(amount), 0) FROM temple_donations WHERE payment_status = 'paid') as donation_paid,
        (SELECT COALESCE(SUM(total_amount), 0) FROM temple_memorial_events WHERE payment_status = 'paid') as order_revenue,
        (SELECT COUNT(*) FROM temple_monks WHERE status = 'active') as monk_active
    `)
    res.json({ success: true, stats })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

export default router
