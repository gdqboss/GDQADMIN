// 寺庙服务模块 - 后端 API (2026-07-22 重构版)
//
// 业务规则（波哥 2026-07-22 定）:
//  1. 任何商业性内容（下单/捐款/收费）一律不暴露给匿名访客或普通登录用户
//  2. 只有 admin 手动标注的"资深信徒"（users.temple_level=1）才能进入商业流程
//  3. 牌位家属（casket.family_member_id=user.id）能看到供奉信息 + 编辑祭文/照片
//  4. 家属编辑内容走"待审核"流程，admin 审核通过才生效
//  5. 不接任何支付通道（PayNow/Stripe/微信都不接）
//
// 路由分 3 层（按 URL 前缀区分鉴权）：
//   /api/temple/public/*  — 匿名可访问，纯介绍性质（banner/intros/monks/公开版牌位）
//   /api/temple/me/*      — 需登录 (auth)，普通用户可访问
//   /api/temple/family/*  — 需登录 + 业务鉴权（牌位家属）
//   /api/temple/admin/*   — 需登录 + admin 角色（admin 自动有所有权限）
//
// 关联主站：
//   users 表直接复用主站 + 加 users.temple_level 字段
//   主站 admin 后台 Temple.vue 模块统一管理寺庙所有数据

import { Router } from 'express'
import { pool } from '../db/connection.js'
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js'
import { auth } from '../middleware/auth.js'

const router = Router()

// ============ 工具函数 ============

// 记录牌位扫码日志（公开版也走这个）
async function logScan(casketId, ip, userAgent, source = 'qrcode') {
  try {
    await pool.query(
      `INSERT INTO temple_scan_logs (casket_id, scan_source, ip, user_agent) VALUES (?, ?, ?, ?)`,
      [casketId, source, ip || '', (userAgent || '').substring(0, 500)]
    )
  } catch (e) { /* 不阻塞主流程 */ }
}

// 鉴权：要求登录 + temple_level=1 (资深信徒)
async function requireSenior(req, res, next) {
  if (!req.user) return res.status(401).json({ code: 401, message: '请先登录' })
  try {
    const [[u]] = await pool.query(
      'SELECT id, temple_level, role FROM users WHERE id = ?',
      [req.user.id]
    )
    if (!u) return res.status(401).json({ code: 401, message: '用户不存在' })
    if (u.role === 'admin') return next()
    if (u.temple_level !== 1) {
      return res.status(403).json({
        code: 403,
        message: '该功能仅对资深信徒开放，请联系寺庙管理员申请'
      })
    }
    next()
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
}

// 鉴权：必须是牌位家属
async function requireFamilyOfCasket(req, res, next) {
  if (!req.user) return res.status(401).json({ code: 401, message: '请先登录' })
  try {
    const [[u]] = await pool.query('SELECT role FROM users WHERE id = ?', [req.user.id])
    if (u && u.role === 'admin') return next()

    const code = req.params.code
    if (!code) return res.status(400).json({ code: 400, message: '缺少牌位编号' })
    const [[casket]] = await pool.query(
      `SELECT id, family_member_id FROM temple_cinerary_caskets WHERE casket_code = ?`,
      [code]
    )
    if (!casket) return res.status(404).json({ code: 404, message: '牌位不存在' })
    if (casket.family_member_id !== req.user.id) {
      return res.status(403).json({
        code: 403,
        message: '仅牌位家属可访问此内容'
      })
    }
    req.casket = casket
    next()
  } catch (err) {
    res.status(500).json({ code: 500, message: err.message })
  }
}

// ============ 第 1 层：公开接口（匿名可访问） ============

// GET /api/temple/public/home — 首页聚合（banner + intro + 活动 + 法师）
router.get('/public/home', async (req, res) => {
  try {
    const profileId = req.query.profile_id || 1
    const [banners] = await pool.query(
      `SELECT id, title, image_url, link_type, link_target, sort_order
       FROM temple_galleries WHERE position = 'home_top' AND is_active = 1 AND server_profile_id = ?
       ORDER BY sort_order ASC LIMIT 10`,
      [profileId]
    )
    const [intros] = await pool.query(
      `SELECT intro_key, title_zh, title_en, content_zh, content_en, icon, sort_order
       FROM temple_intros WHERE server_profile_id = ? ORDER BY sort_order ASC`,
      [profileId]
    )
    const [activities] = await pool.query(
      `SELECT id, title, activity_date, description, poster_url
       FROM temple_activities WHERE is_active = 1 AND server_profile_id = ?
       ORDER BY activity_date DESC LIMIT 10`,
      [profileId]
    )
    const [monks] = await pool.query(
      `SELECT id, name, dharma_name, specialty, avatar_url, bio
       FROM temple_monks WHERE status = 'active' ORDER BY id LIMIT 20`
    )
    res.json({ success: true, banners, intros, activities, monks })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// GET /api/temple/public/services — 服务清单（匿名版）
// 2026-07-24 修订: 删 donation/ceremony (违反业务规则"不出现捐款/收费"),
//                 加 category 字段 (前端按 daily/senior 分组)
router.get('/public/services', async (req, res) => {
  try {
    const services = [
      { code: 'casket', icon: 'spa', category: 'daily',
        name_zh: '骨灰盒供奉', name_en: 'Cinerary Casket',
        desc_zh: '为先人选择永久的安息之所(请联系寺院客堂办理)',
        desc_en: 'A permanent resting place for loved ones. Please contact temple office.' },
      { code: 'memorial', icon: 'local_florist', category: 'daily',
        name_zh: '在线祭拜', name_en: 'Online Memorial',
        desc_zh: '请法师代劳祭拜,超度先人(由客堂登记安排)',
        desc_en: 'Engage monks for ancestral rites. Register at temple office.' },
      { code: 'visit', icon: 'self_improvement', category: 'daily',
        name_zh: '寺院参访', name_en: 'Temple Visit',
        desc_zh: '开放时间 06:00-21:00,欢迎十方善信莅临',
        desc_en: 'Visiting hours 06:00-21:00 daily. All devotees welcome.' },
      { code: 'study', icon: 'menu_book', category: 'senior',
        name_zh: '佛学讲座', name_en: 'Dharma Study',
        desc_zh: '资深信众专享 · 周六上午定期开讲',
        desc_en: 'Senior devotees only · Saturday mornings' },
      { code: 'meditation', icon: 'spa', category: 'senior',
        name_zh: '禅修共修', name_en: 'Meditation',
        desc_zh: '资深信众专享 · 每周三晚静坐共修',
        desc_en: 'Senior devotees only · Wednesday evenings' }
    ]
    res.json({ success: true, services })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// GET /api/temple/public/monks — 法师列表
router.get('/public/monks', async (req, res) => {
  try {
    const [monks] = await pool.query(
      `SELECT id, name, dharma_name, specialty, avatar_url, bio
       FROM temple_monks WHERE status = 'active' ORDER BY id LIMIT 50`
    )
    res.json({ success: true, monks })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// GET /api/temple/public/casket/:code — 扫码查牌位（匿名版）
router.get('/public/casket/:code', async (req, res) => {
  try {
    const { code } = req.params
    const [[row]] = await pool.query(
      `SELECT c.id, c.casket_code, c.casket_type, c.status,
              c.hall_name, c.floor_no, c.row_no, c.position_no,
              c.installed_at,
              a.id as ancestor_id, a.name as ancestor_name, a.gender,
              a.birth_date, a.death_date, a.age_at_death, a.nationality,
              a.photo_url, a.public_epitaph, a.family_relation
       FROM temple_cinerary_caskets c
       LEFT JOIN temple_ancestors a ON c.ancestor_id = a.id
       WHERE c.casket_code = ?`,
      [code]
    )
    if (!row) return res.json({ success: false, message: '牌位编号不存在' })

    const [[scanStats]] = await pool.query(
      `SELECT COUNT(*) as scan_count, MAX(created_at) as last_scan_at
       FROM temple_scan_logs WHERE casket_id = ?`,
      [row.id]
    )

    logScan(row.id, req.ip, req.headers['user-agent'])

    let isFamily = false
    if (req.user) {
      const [[c]] = await pool.query(
        'SELECT family_member_id FROM temple_cinerary_caskets WHERE id = ?',
        [row.id]
      )
      isFamily = c && c.family_member_id === req.user.id
    }

    res.json({
      success: true,
      casket: {
        casket_code: row.casket_code,
        hall_name: row.hall_name,
        floor_no: row.floor_no,
        row_no: row.row_no,
        position_no: row.position_no,
        casket_type: row.casket_type,
        installed_at: row.installed_at,
        ancestor: {
          name: row.ancestor_name,
          gender: row.gender,
          birth_date: row.birth_date,
          death_date: row.death_date,
          age_at_death: row.age_at_death,
          nationality: row.nationality,
          photo_url: row.photo_url,
          public_epitaph: row.public_epitaph,
          family_relation: row.family_relation
        },
        scan_count: scanStats.scan_count,
        last_scan_at: scanStats.last_scan_at,
        is_logged_in: !!req.user,
        is_family: isFamily
      }
    })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// GET /api/temple/public/eligibility — 当前用户身份判定
router.get('/public/eligibility', async (req, res) => {
  try {
    if (!req.user) {
      return res.json({
        success: true,
        is_logged_in: false,
        user_id: null,
        temple_level: 0,
        is_senior: false,
        is_admin: false
      })
    }
    const [[u]] = await pool.query(
      'SELECT id, temple_level, role, name FROM users WHERE id = ?',
      [req.user.id]
    )
    if (!u) {
      return res.json({ success: true, is_logged_in: false })
    }
    res.json({
      success: true,
      is_logged_in: true,
      user_id: u.id,
      name: u.name,
      temple_level: u.temple_level || 0,
      is_senior: u.temple_level === 1,
      is_admin: u.role === 'admin'
    })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// ============ 第 2 层：登录用户接口 ============

// GET /api/temple/me/caskets — 我供奉的所有牌位
router.get('/me/caskets', auth, async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT c.id, c.casket_code, c.hall_name, c.floor_no, c.row_no, c.position_no,
              c.casket_type, c.status, c.installed_at, c.contract_no, c.management_fee,
              c.next_renewal_date, c.family_contact, c.family_phone,
              c.qrcode_url, c.remark,
              a.id as ancestor_id, a.name as ancestor_name, a.gender,
              a.birth_date, a.death_date, a.photo_url
       FROM temple_cinerary_caskets c
       LEFT JOIN temple_ancestors a ON c.ancestor_id = a.id
       WHERE c.family_member_id = ?
       ORDER BY c.id DESC`,
      [req.user.id]
    )
    res.json({ success: true, list: rows, total: rows.length })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// GET /api/temple/me/edit-requests
// 2026-07-24 修订: 加 alias casket_name 兼容前端 + 列表用 requests 字段名兼容前端两种写法
router.get('/me/edit-requests', auth, async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT r.id, r.casket_id, r.field_name, r.status, r.review_note, r.created_at, r.reviewed_at,
              c.casket_code, c.casket_code as casket_name, a.name as ancestor_name
       FROM temple_edit_requests r
       JOIN temple_cinerary_caskets c ON r.casket_id = c.id
       LEFT JOIN temple_ancestors a ON c.ancestor_id = a.id
       WHERE r.user_id = ?
       ORDER BY r.id DESC LIMIT 50`,
      [req.user.id]
    )
    res.json({ success: true, list: rows, requests: rows })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// GET /api/temple/family/edit-requests?casket_code=XXX
// 2026-07-24 新增: 牌位详情页"编辑记录"用,按 casket_code + 当前用户过滤
router.get('/family/edit-requests', auth, async (req, res) => {
  try {
    const { casket_code } = req.query
    if (!casket_code) return res.status(400).json({ success: false, message: '缺少 casket_code' })
    const [[casket]] = await pool.query(
      'SELECT id FROM temple_cinerary_caskets WHERE casket_code = ?',
      [casket_code]
    )
    if (!casket) return res.json({ success: true, list: [], requests: [] })
    const [rows] = await pool.query(
      `SELECT id, field_name, new_value, status, review_note, created_at, reviewed_at
       FROM temple_edit_requests
       WHERE casket_id = ? AND user_id = ?
       ORDER BY id DESC LIMIT 50`,
      [casket.id, req.user.id]
    )
    res.json({ success: true, list: rows, requests: rows })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// GET /api/temple/family/casket/:code/qrcode — 2026-07-24 新增
//   牌位二维码生成端点(家属可见),返 data URL 供前端 img 渲染
//   扫码目标 URL = 当前 SPA 的 /#/casket/:code (单页 hash 路由)
router.get('/family/casket/:code/qrcode', auth, requireFamilyOfCasket, async (req, res) => {
  try {
    const QRCode = (await import('qrcode')).default
    const { code } = req.params
    const [[casket]] = await pool.query(
      'SELECT id, casket_code FROM temple_cinerary_caskets WHERE casket_code = ?',
      [code]
    )
    if (!casket) return res.status(404).json({ success: false, message: '牌位不存在' })
    // 扫码目标 URL — 用当前 host(从 referer 取),访客无需登录也能扫码
    const ref = req.headers.referer || req.headers.origin || ''
    let base = ''
    try { if (ref) base = new URL(ref).origin } catch { base = 'https://wecom.gdqshop.cn' }
    const targetUrl = `${base}/temple/#/casket/${encodeURIComponent(code)}`
    const dataUrl = await QRCode.toDataURL(targetUrl, {
      width: 320, margin: 2, color: { dark: '#7a2c14', light: '#ffffff' },
      errorCorrectionLevel: 'M'
    })
    res.json({ success: true, code, target_url: targetUrl, qrcode_data_url: dataUrl })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// GET /api/temple/me/orders — 我的祭拜订单
router.get('/me/orders', auth, async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT e.id, e.order_no, e.service_type, e.scheduled_date, e.scheduled_time_slot,
              e.status, e.photo_feedback_url, e.created_at,
              c.casket_code, a.name as ancestor_name, m.name as monk_name
       FROM temple_memorial_events e
       LEFT JOIN temple_cinerary_caskets c ON e.casket_id = c.id
       LEFT JOIN temple_ancestors a ON c.ancestor_id = a.id
       LEFT JOIN temple_monks m ON e.monk_id = m.id
       WHERE c.family_member_id = ?
       ORDER BY e.id DESC LIMIT 50`,
      [req.user.id]
    )
    res.json({ success: true, list: rows })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// ============ 第 3 层：家属接口 ============

// GET /api/temple/family/casket/:code — 家属版牌位详情
router.get('/family/casket/:code', auth, requireFamilyOfCasket, async (req, res) => {
  try {
    const { code } = req.params
    const [[row]] = await pool.query(
      `SELECT c.*, a.id as ancestor_id, a.name as ancestor_name, a.gender,
              a.birth_date, a.death_date, a.age_at_death, a.nationality,
              a.id_card, a.photo_url, a.public_epitaph, a.private_epitaph, a.life_photos,
              a.family_relation
       FROM temple_cinerary_caskets c
       LEFT JOIN temple_ancestors a ON c.ancestor_id = a.id
       WHERE c.casket_code = ?`,
      [code]
    )
    if (!row) return res.status(404).json({ success: false, message: '牌位不存在' })

    const [[pending]] = await pool.query(
      `SELECT COUNT(*) as pending_count
       FROM temple_edit_requests WHERE casket_id = ? AND status = 'pending'`,
      [row.id]
    )

    let lifePhotos = []
    if (row.life_photos) {
      try { lifePhotos = JSON.parse(row.life_photos) } catch {}
    }

    res.json({
      success: true,
      casket: {
        ...row,
        life_photos: lifePhotos,
        pending_edit_count: pending.pending_count,
        is_family: true
      }
    })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// POST /api/temple/family/casket/:code/edit-request
// 2026-07-24 修订: 接受前端 showEditCasketSheet 整对象提交 (name/hall/eulogy)
//                  同时兼容旧的按字段编辑 (field_name/new_value) — 弃用过渡
router.post('/family/casket/:code/edit-request', auth, requireFamilyOfCasket, async (req, res) => {
  try {
    const { code } = req.params
    const body = req.body || {}

    // 1) 新格式: 整对象提交 (前端 showEditCasketSheet)
    if (body.name || body.hall !== undefined || body.eulogy !== undefined) {
      const [[casket]] = await pool.query(
        'SELECT id, ancestor_id FROM temple_cinerary_caskets WHERE casket_code = ?',
        [code]
      )
      if (!casket) return res.status(404).json({ success: false, message: '牌位不存在' })
      const userId = req.user.id
      // 每条字段一条记录 (与 admin 审核界面字段粒度对齐)
      const inserts = []
      if (body.name) inserts.push(['name', body.name])
      if (body.hall !== undefined) inserts.push(['hall', body.hall])
      if (body.eulogy !== undefined) inserts.push(['public_epitaph', body.eulogy])
      for (const [field_name, new_value] of inserts) {
        await pool.query(
          `INSERT INTO temple_edit_requests
           (casket_id, user_id, field_name, new_value, status, created_at)
           VALUES (?, ?, ?, ?, 'pending', NOW())`,
          [casket.id, userId, field_name, String(new_value)]
        )
      }
      return res.json({ success: true, message: '已提交,等待管理员审核', count: inserts.length })
    }

    // 2) 旧格式: 按字段编辑 (兼容保留)
    const { field_name, new_value } = body
    if (!['public_epitaph', 'private_epitaph', 'life_photos', 'name', 'hall'].includes(field_name)) {
      return res.status(400).json({ success: false, message: '不支持编辑的字段' })
    }
    if (new_value === undefined || new_value === null) {
      return res.status(400).json({ success: false, message: '缺少 new_value' })
    }

    const [[casket]] = await pool.query(
      'SELECT id, ancestor_id FROM temple_cinerary_caskets WHERE casket_code = ?',
      [code]
    )
    if (!casket || !casket.ancestor_id) {
      return res.status(400).json({ success: false, message: '牌位无关联逝者' })
    }

    const [[ancestor]] = await pool.query(
      `SELECT ${field_name} as old_value FROM temple_ancestors WHERE id = ?`,
      [casket.ancestor_id]
    )

    const [r] = await pool.query(
      `INSERT INTO temple_edit_requests (casket_id, user_id, field_name, old_value, new_value, status)
       VALUES (?, ?, ?, ?, ?, 'pending')`,
      [casket.id, req.user.id, field_name, ancestor?.old_value || '', String(new_value)]
    )

    res.json({ success: true, id: r.insertId, message: '已提交，管理员审核后生效' })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// ============ 第 4 层：管理端接口 ============

// 骨灰盒 CRUD
router.get('/admin/caskets', auth, requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const { keyword, status } = req.query
    let sql = `SELECT c.*, a.name as ancestor_name, u.phone as family_user_phone, u.name as family_user_name
               FROM temple_cinerary_caskets c
               LEFT JOIN temple_ancestors a ON c.ancestor_id = a.id
               LEFT JOIN users u ON c.family_member_id = u.id`
    const where = []
    const params = []
    if (keyword) {
      where.push('(c.casket_code LIKE ? OR a.name LIKE ? OR c.family_contact LIKE ?)')
      params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`)
    }
    if (status) { where.push('c.status = ?'); params.push(status) }
    if (where.length) sql += ' WHERE ' + where.join(' AND ')
    sql += ' ORDER BY c.id DESC LIMIT 500'

    const [rows] = await pool.query(sql, params)
    res.json({ success: true, list: rows, total: rows.length })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.post('/admin/caskets', auth, requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { casket_code, hall_name, floor_no, row_no, position_no, casket_type,
            ancestor_id, status, installed_at, contract_no, management_fee,
            family_contact, family_phone, family_member_id, remark } = req.body
    if (!casket_code) return res.status(400).json({ success: false, message: '骨灰盒编号必填' })
    const [r] = await pool.query(
      `INSERT INTO temple_cinerary_caskets
       (casket_code, hall_name, floor_no, row_no, position_no, casket_type,
        ancestor_id, status, installed_at, contract_no, management_fee,
        family_contact, family_phone, family_member_id, remark)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [casket_code, hall_name || '', floor_no || '', row_no || '', position_no || '',
       casket_type || 'casket', ancestor_id || null, status || 'occupied',
       installed_at || null, contract_no || '', management_fee || 0,
       family_contact || '', family_phone || '', family_member_id || null,
       remark || '']
    )
    res.json({ success: true, id: r.insertId })
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') return res.status(400).json({ success: false, message: '编号已存在' })
    res.status(500).json({ success: false, message: err.message })
  }
})

router.put('/admin/caskets/:id', auth, requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { hall_name, floor_no, row_no, position_no, casket_type,
            ancestor_id, status, installed_at, contract_no, management_fee,
            family_contact, family_phone, family_member_id, remark, next_renewal_date } = req.body
    await pool.query(
      `UPDATE temple_cinerary_caskets SET
        hall_name=?, floor_no=?, row_no=?, position_no=?, casket_type=?,
        ancestor_id=?, status=?, installed_at=?, contract_no=?, management_fee=?,
        family_contact=?, family_phone=?, family_member_id=?, remark=?, next_renewal_date=?
       WHERE id=?`,
      [hall_name || '', floor_no || '', row_no || '', position_no || '',
       casket_type || 'casket', ancestor_id || null, status || 'occupied',
       installed_at || null, contract_no || '', management_fee || 0,
       family_contact || '', family_phone || '', family_member_id || null,
       remark || '', next_renewal_date || null, req.params.id]
    )
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.delete('/admin/caskets/:id', auth, requirePermission(PERMISSIONS.TEMPLE_DELETE), async (req, res) => {
  try {
    await pool.query('DELETE FROM temple_cinerary_caskets WHERE id = ?', [req.params.id])
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// 逝者 CRUD
router.get('/admin/ancestors', auth, requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const { keyword } = req.query
    let sql = `SELECT a.*, u.phone as family_user_phone, u.name as family_user_name
               FROM temple_ancestors a LEFT JOIN users u ON a.family_member_id = u.id`
    const params = []
    if (keyword) {
      sql += ' WHERE a.name LIKE ? OR a.public_epitaph LIKE ?'
      params.push(`%${keyword}%`, `%${keyword}%`)
    }
    sql += ' ORDER BY a.id DESC LIMIT 500'
    const [rows] = await pool.query(sql, params)
    res.json({ success: true, list: rows, total: rows.length })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.post('/admin/ancestors', auth, requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { name, gender, birth_date, death_date, age_at_death, nationality, id_card,
            photo_url, public_epitaph, private_epitaph, life_photos,
            family_member_id, family_relation } = req.body
    if (!name) return res.status(400).json({ success: false, message: '姓名必填' })
    const [r] = await pool.query(
      `INSERT INTO temple_ancestors
       (name, gender, birth_date, death_date, age_at_death, nationality, id_card,
        photo_url, public_epitaph, private_epitaph, life_photos,
        family_member_id, family_relation)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [name, gender || 'other', birth_date || null, death_date || null,
       age_at_death || null, nationality || '中国', id_card || '',
       photo_url || '', public_epitaph || '', private_epitaph || '',
       life_photos ? JSON.stringify(life_photos) : null,
       family_member_id || null, family_relation || '']
    )
    res.json({ success: true, id: r.insertId })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.put('/admin/ancestors/:id', auth, requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { name, gender, birth_date, death_date, age_at_death, nationality, id_card,
            photo_url, public_epitaph, private_epitaph, life_photos,
            family_member_id, family_relation } = req.body
    await pool.query(
      `UPDATE temple_ancestors SET
        name=?, gender=?, birth_date=?, death_date=?, age_at_death=?, nationality=?, id_card=?,
        photo_url=?, public_epitaph=?, private_epitaph=?, life_photos=?,
        family_member_id=?, family_relation=?
       WHERE id=?`,
      [name, gender || 'other', birth_date || null, death_date || null,
       age_at_death || null, nationality || '中国', id_card || '',
       photo_url || '', public_epitaph || '', private_epitaph || '',
       life_photos ? JSON.stringify(life_photos) : null,
       family_member_id || null, family_relation || '', req.params.id]
    )
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// 信众列表 + 升降级
router.get('/admin/members', auth, requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const { keyword, level } = req.query
    let sql = `SELECT id, phone, email, name, role, temple_level, server_profile_id,
                      (SELECT COUNT(*) FROM temple_cinerary_caskets WHERE family_member_id = users.id) AS casket_count
               FROM users WHERE 1=1`
    const where = []
    const params = []
    if (keyword) {
      where.push('(phone LIKE ? OR email LIKE ? OR name LIKE ?)')
      params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`)
    }
    if (level !== undefined && level !== '') {
      where.push('temple_level = ?')
      params.push(parseInt(level))
    }
    if (where.length) sql += ' AND ' + where.join(' AND ')
    sql += ' ORDER BY temple_level DESC, id DESC LIMIT 200'
    const [rows] = await pool.query(sql, params)
    res.json({ success: true, list: rows, total: rows.length })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.patch('/admin/members/:id/level', auth, requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { level } = req.body
    if (![0, 1].includes(level)) {
      return res.status(400).json({ success: false, message: 'level 必须为 0 或 1' })
    }
    await pool.query('UPDATE users SET temple_level = ? WHERE id = ?', [level, req.params.id])
    res.json({ success: true, user_id: req.params.id, temple_level: level })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// 编辑审核
router.get('/admin/edit-requests', auth, requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const { status = 'pending' } = req.query
    const [rows] = await pool.query(
      `SELECT r.*, c.casket_code, a.name as ancestor_name, u.phone as user_phone, u.name as user_name
       FROM temple_edit_requests r
       JOIN temple_cinerary_caskets c ON r.casket_id = c.id
       LEFT JOIN temple_ancestors a ON c.ancestor_id = a.id
       LEFT JOIN users u ON r.user_id = u.id
       WHERE r.status = ?
       ORDER BY r.created_at DESC LIMIT 200`,
      [status]
    )
    res.json({ success: true, list: rows })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.patch('/admin/edit-requests/:id/review', auth, requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { action, review_note } = req.body
    if (!['approve', 'reject'].includes(action)) {
      return res.status(400).json({ success: false, message: 'action 必须为 approve 或 reject' })
    }
    const [[req_row]] = await pool.query(
      `SELECT r.*, c.ancestor_id FROM temple_edit_requests r
       JOIN temple_cinerary_caskets c ON r.casket_id = c.id
       WHERE r.id = ?`,
      [req.params.id]
    )
    if (!req_row) return res.status(404).json({ success: false, message: '编辑请求不存在' })
    if (req_row.status !== 'pending') {
      return res.status(400).json({ success: false, message: '已审核过，不可重复操作' })
    }

    const newStatus = action === 'approve' ? 'approved' : 'rejected'
    await pool.query(
      `UPDATE temple_edit_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ?
       WHERE id = ?`,
      [newStatus, req.user.id, review_note || '', req.params.id]
    )

    if (action === 'approve' && req_row.ancestor_id) {
      await pool.query(
        `UPDATE temple_ancestors SET ${req_row.field_name} = ? WHERE id = ?`,
        [req_row.new_value, req_row.ancestor_id]
      )
    }

    res.json({ success: true, status: newStatus })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// 活动 CRUD
router.get('/admin/activities', auth, requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT * FROM temple_activities ORDER BY activity_date DESC LIMIT 200`
    )
    res.json({ success: true, list: rows })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.post('/admin/activities', auth, requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { title, activity_date, description, poster_url, is_active = 1 } = req.body
    if (!title) return res.status(400).json({ success: false, message: '标题必填' })
    const [r] = await pool.query(
      `INSERT INTO temple_activities (title, activity_date, description, poster_url, is_active, server_profile_id)
       VALUES (?, ?, ?, ?, ?, 1)`,
      [title, activity_date || null, description || '', poster_url || '', is_active]
    )
    res.json({ success: true, id: r.insertId })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.put('/admin/activities/:id', auth, requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { title, activity_date, description, poster_url, is_active } = req.body
    await pool.query(
      `UPDATE temple_activities SET title=?, activity_date=?, description=?, poster_url=?, is_active=?
       WHERE id=?`,
      [title, activity_date || null, description || '', poster_url || '',
       is_active !== undefined ? is_active : 1, req.params.id]
    )
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.delete('/admin/activities/:id', auth, requirePermission(PERMISSIONS.TEMPLE_DELETE), async (req, res) => {
  try {
    await pool.query('DELETE FROM temple_activities WHERE id = ?', [req.params.id])
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// 图片轮播 CRUD
router.get('/admin/galleries', auth, requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const { position = 'home_top' } = req.query
    const [rows] = await pool.query(
      `SELECT * FROM temple_galleries WHERE position = ? ORDER BY sort_order ASC LIMIT 50`,
      [position]
    )
    res.json({ success: true, list: rows })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.post('/admin/galleries', auth, requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { title, image_url, link_type, link_target, sort_order, position } = req.body
    if (!title || !image_url) return res.status(400).json({ success: false, message: '标题和图片必填' })
    const [r] = await pool.query(
      `INSERT INTO temple_galleries (title, image_url, link_type, link_target, sort_order, position, is_active, server_profile_id)
       VALUES (?, ?, ?, ?, ?, ?, 1, 1)`,
      [title, image_url, link_type || 'none', link_target || '', sort_order || 0, position || 'home_top']
    )
    res.json({ success: true, id: r.insertId })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.put('/admin/galleries/:id', auth, requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { title, image_url, link_type, link_target, sort_order, is_active } = req.body
    await pool.query(
      `UPDATE temple_galleries SET title=?, image_url=?, link_type=?, link_target=?, sort_order=?, is_active=?
       WHERE id=?`,
      [title, image_url, link_type || 'none', link_target || '', sort_order || 0,
       is_active !== undefined ? is_active : 1, req.params.id]
    )
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.delete('/admin/galleries/:id', auth, requirePermission(PERMISSIONS.TEMPLE_DELETE), async (req, res) => {
  try {
    await pool.query('DELETE FROM temple_galleries WHERE id = ?', [req.params.id])
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// 介绍 CRUD
router.get('/admin/intros', auth, requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT * FROM temple_intros ORDER BY sort_order ASC LIMIT 50`
    )
    res.json({ success: true, list: rows })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.put('/admin/intros/:id', auth, requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { title_zh, title_en, content_zh, content_en, icon, sort_order } = req.body
    await pool.query(
      `UPDATE temple_intros SET title_zh=?, title_en=?, content_zh=?, content_en=?, icon=?, sort_order=?
       WHERE id=?`,
      [title_zh || '', title_en || '', content_zh || '', content_en || '',
       icon || '', sort_order || 0, req.params.id]
    )
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// 和尚 CRUD
router.get('/admin/monks', auth, requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const [rows] = await pool.query(`SELECT * FROM temple_monks ORDER BY id DESC LIMIT 200`)
    res.json({ success: true, list: rows, total: rows.length })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.post('/admin/monks', auth, requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { name, dharma_name, specialty, phone, avatar_url, bio, status } = req.body
    if (!name) return res.status(400).json({ success: false, message: '姓名必填' })
    const [r] = await pool.query(
      `INSERT INTO temple_monks (name, dharma_name, specialty, phone, avatar_url, bio, status)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [name, dharma_name || '', specialty || '', phone || '', avatar_url || '',
       bio || '', status || 'active']
    )
    res.json({ success: true, id: r.insertId })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

router.put('/admin/monks/:id', auth, requirePermission(PERMISSIONS.TEMPLE_WRITE), async (req, res) => {
  try {
    const { name, dharma_name, specialty, phone, avatar_url, bio, status } = req.body
    await pool.query(
      `UPDATE temple_monks SET name=?, dharma_name=?, specialty=?, phone=?, avatar_url=?, bio=?, status=?
       WHERE id=?`,
      [name, dharma_name || '', specialty || '', phone || '', avatar_url || '',
       bio || '', status || 'active', req.params.id]
    )
    res.json({ success: true })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// 统计
router.get('/admin/stats', auth, requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const [[stats]] = await pool.query(`
      SELECT
        (SELECT COUNT(*) FROM temple_cinerary_caskets WHERE status = 'occupied') AS casket_used,
        (SELECT COUNT(*) FROM temple_cinerary_caskets) AS casket_total,
        (SELECT COUNT(*) FROM temple_cinerary_caskets WHERE status = 'vacant') AS casket_vacant,
        (SELECT COUNT(*) FROM temple_ancestors) AS ancestor_total,
        (SELECT COUNT(*) FROM temple_edit_requests WHERE status = 'pending') AS edit_pending,
        (SELECT COUNT(*) FROM temple_monks WHERE status = 'active') AS monk_active,
        (SELECT COUNT(*) FROM temple_scan_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) AS scan_7d,
        (SELECT COUNT(*) FROM users WHERE temple_level = 1) AS senior_count
    `)
    res.json({ success: true, stats })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

// 扫码记录
router.get('/admin/scan-logs/:casketId', auth, requirePermission(PERMISSIONS.TEMPLE_READ), async (req, res) => {
  try {
    const [rows] = await pool.query(
      `SELECT id, scan_source, ip, created_at FROM temple_scan_logs
       WHERE casket_id = ? ORDER BY id DESC LIMIT 100`,
      [req.params.casketId]
    )
    res.json({ success: true, list: rows })
  } catch (err) {
    res.status(500).json({ success: false, message: err.message })
  }
})

export default router