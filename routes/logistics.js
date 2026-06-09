import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { ROLES, requirePermission } from '../middleware/rbac.js'

const router = Router()

// ─── 权限常量 ─────────────────────────────────────────────────────────────────
const P = {
  EXPRESS_READ: 'express_read',
  EXPRESS_WRITE: 'express_write',
  FREIGHT_READ: 'freight_read',
  FREIGHT_WRITE: 'freight_write',
  CHANNEL_READ: 'channel_read',
  CHANNEL_WRITE: 'channel_write',
}

// ─── 快递公司管理 ─────────────────────────────────────────────────────────────

// GET /api/logistics/express-companies - 快递公司列表
router.get('/express-companies', requirePermission(P.EXPRESS_READ), async (req, res, next) => {
  try {
    const { page = 1, size = 50, status } = req.query
    const { page: p, size: s } = parsePagination({ page, size })
    let where = 'WHERE 1=1'
    const params = []

    if (status) {
      where += ' AND status = ?'
      params.push(status)
    }

    const sql = `SELECT * FROM express_companies ${where} ORDER BY sort ASC, id ASC LIMIT ? OFFSET ?`
    const countSql = `SELECT COUNT(*) as total FROM express_companies ${where}`

    const [[{ total }]] = await pool.query(countSql, params)
    params.push(s, (p - 1) * s)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page: p, size: s }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/logistics/express-companies/:id - 快递公司详情
router.get('/express-companies/:id', requirePermission(P.EXPRESS_READ), async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT * FROM express_companies WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '快递公司不存在' })
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/logistics/express-companies - 新增快递公司
router.post('/express-companies', requirePermission(P.EXPRESS_WRITE), async (req, res, next) => {
  try {
    const { name, code, website, phone, status = 'enabled', sort = 0, remark } = req.body
    if (!name || !code) return res.status(400).json({ code: 400, message: '名称和代码必填' })

    const [existing] = await pool.query('SELECT id FROM express_companies WHERE code = ?', [code])
    if (existing.length > 0) return res.status(400).json({ code: 400, message: '快递公司代码已存在' })

    const [result] = await pool.query(
      'INSERT INTO express_companies (name, code, website, phone, status, sort, remark) VALUES (?,?,?,?,?,?,?)',
      [name, code, website || null, phone || null, status, sort, remark || null]
    )

    const [[row]] = await pool.query('SELECT * FROM express_companies WHERE id = ?', [result.insertId])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/logistics/express-companies/:id - 更新快递公司
router.put('/express-companies/:id', requirePermission(P.EXPRESS_WRITE), async (req, res, next) => {
  try {
    const { name, code, website, phone, status, sort, remark } = req.body
    const [[existing]] = await pool.query('SELECT * FROM express_companies WHERE id = ?', [req.params.id])
    if (!existing) return res.status(404).json({ code: 404, message: '快递公司不存在' })

    // 检查代码冲突（排除自己）
    if (code && code !== existing.code) {
      const [dup] = await pool.query('SELECT id FROM express_companies WHERE code = ? AND id != ?', [code, req.params.id])
      if (dup.length > 0) return res.status(400).json({ code: 400, message: '快递公司代码已存在' })
    }

    await pool.query(
      'UPDATE express_companies SET name=COALESCE(?,name), code=COALESCE(?,code), website=COALESCE(?,website), phone=COALESCE(?,phone), status=COALESCE(?,status), sort=COALESCE(?,sort), remark=COALESCE(?,remark) WHERE id=?',
      [name, code, website, phone, status, sort, remark, req.params.id]
    )

    const [[row]] = await pool.query('SELECT * FROM express_companies WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE /api/logistics/express-companies/:id - 删除快递公司
router.delete('/express-companies/:id', requirePermission(P.EXPRESS_WRITE), async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT * FROM express_companies WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '快递公司不存在' })

    await pool.query('DELETE FROM express_companies WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: '删除成功' })
  } catch (err) { next(err) }
})

// ─── 运费模板管理 ─────────────────────────────────────────────────────────────

// GET /api/logistics/freight-templates - 运费模板列表
router.get('/freight-templates', requirePermission(P.FREIGHT_READ), async (req, res, next) => {
  try {
    const { page = 1, size = 20, name, status } = req.query
    const { page: p, size: s } = parsePagination({ page, size })
    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (name) {
      where += ' AND name LIKE ?'
      const kw = `%${name}%`
      params.push(kw)
      countParams.push(kw)
    }
    if (status) {
      where += ' AND status = ?'
      params.push(status)
      countParams.push(status)
    }

    const sql = `SELECT * FROM freight_templates ${where} ORDER BY id DESC LIMIT ? OFFSET ?`
    const countSql = `SELECT COUNT(*) as total FROM freight_templates ${where}`

    const [[{ total }]] = await pool.query(countSql, countParams)
    params.push(s, (p - 1) * s)
    const [rows] = await pool.query(sql, params)

    // 格式化地区名称
    const formatRegions = (rows) => {
      return rows.map(r => {
        if (r.region_names) {
          try { r.region_names = JSON.parse(r.region_names) } catch {}
        } else {
          r.region_names = []
        }
        return r
      })
    }

    res.json({ code: 0, data: { list: formatRegions(rows), total, page: p, size: s }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/logistics/freight-templates/:id - 运费模板详情（含规则）
router.get('/freight-templates/:id', requirePermission(P.FREIGHT_READ), async (req, res, next) => {
  try {
    const [[tpl]] = await pool.query('SELECT * FROM freight_templates WHERE id = ?', [req.params.id])
    if (!tpl) return res.status(404).json({ code: 404, message: '运费模板不存在' })

    if (tpl.region_names) {
      try { tpl.region_names = JSON.parse(tpl.region_names) } catch { tpl.region_names = [] }
    } else {
      tpl.region_names = []
    }

    const [rules] = await pool.query('SELECT * FROM freight_template_rules WHERE template_id = ? ORDER BY first_weight ASC', [req.params.id])
    tpl.rules = rules

    res.json({ code: 0, data: tpl, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/logistics/freight-templates - 新建运费模板
router.post('/freight-templates', requirePermission(P.FREIGHT_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { name, calc_type, dispatch_type, dispatch_time, min_free_amount, min_free_number, status = 'enabled', remark, rules = [] } = req.body
    if (!name) return res.status(400).json({ code: 400, message: '模板名称必填' })
    if (!rules || rules.length === 0) return res.status(400).json({ code: 400, message: '至少需要一条计费规则' })

    await conn.beginTransaction()

    const [result] = await conn.query(
      `INSERT INTO freight_templates (name, calc_type, dispatch_type, dispatch_time, min_free_amount, min_free_number, status, remark)
       VALUES (?,?,?,?,?,?,?,?)`,
      [name, calc_type || 'by_weight', dispatch_type || 'estimated', dispatch_time || 3, min_free_amount || null, min_free_number || null, status, remark || null]
    )

    const template_id = result.insertId

    for (const rule of rules) {
      const { region_names = [], first_weight, first_fee, continue_weight, continue_fee, item_first, item_first_fee, item_continue, item_continue_fee } = rule

      let regionJson = []
      if (Array.isArray(region_names)) {
        regionJson = region_names
      } else if (typeof region_names === 'string') {
        try { regionJson = JSON.parse(region_names) } catch {}
      }

      await conn.query(
        `INSERT INTO freight_template_rules (template_id, region_names, first_weight, first_fee, continue_weight, continue_fee, item_first, item_first_fee, item_continue, item_continue_fee)
         VALUES (?,?,?,?,?,?,?,?,?,?)`,
        [template_id, JSON.stringify(regionJson), first_weight || 1, first_fee || 0, continue_weight || 1, continue_fee || 0, item_first || 0, item_first_fee || 0, item_continue || 0, item_continue_fee || 0]
      )
    }

    await conn.commit()

    const [[tpl]] = await pool.query('SELECT * FROM freight_templates WHERE id = ?', [template_id])
    const [tplRules] = await pool.query('SELECT * FROM freight_template_rules WHERE template_id = ?', [template_id])
    tpl.rules = tplRules
    if (tpl.region_names) {
      try { tpl.region_names = JSON.parse(tpl.region_names) } catch { tpl.region_names = [] }
    }

    res.json({ code: 0, data: tpl, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// PUT /api/logistics/freight-templates/:id - 更新运费模板
router.put('/freight-templates/:id', requirePermission(P.FREIGHT_WRITE), async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const [[existing]] = await conn.query('SELECT * FROM freight_templates WHERE id = ?', [req.params.id])
    if (!existing) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '运费模板不存在' })
    }

    const { name, calc_type, dispatch_type, dispatch_time, min_free_amount, min_free_number, status, remark, rules } = req.body

    await conn.beginTransaction()

    await conn.query(
      `UPDATE freight_templates SET
        name=COALESCE(?,name), calc_type=COALESCE(?,calc_type), dispatch_type=COALESCE(?,dispatch_type),
        dispatch_time=COALESCE(?,dispatch_time), min_free_amount=COALESCE(?,min_free_amount),
        min_free_number=COALESCE(?,min_free_number), status=COALESCE(?,status), remark=COALESCE(?,remark)
       WHERE id=?`,
      [name, calc_type, dispatch_type, dispatch_time, min_free_amount, min_free_number, status, remark, req.params.id]
    )

    // 如果传了 rules，全量替换
    if (rules && Array.isArray(rules)) {
      await conn.query('DELETE FROM freight_template_rules WHERE template_id = ?', [req.params.id])
      for (const rule of rules) {
        const { region_names = [], first_weight, first_fee, continue_weight, continue_fee, item_first, item_first_fee, item_continue, item_continue_fee } = rule
        let regionJson = Array.isArray(region_names) ? region_names : (typeof region_names === 'string' ? (() => { try { return JSON.parse(region_names) } catch { return [] } })() : [])
        await conn.query(
          `INSERT INTO freight_template_rules (template_id, region_names, first_weight, first_fee, continue_weight, continue_fee, item_first, item_first_fee, item_continue, item_continue_fee)
           VALUES (?,?,?,?,?,?,?,?,?,?)`,
          [req.params.id, JSON.stringify(regionJson), first_weight || 1, first_fee || 0, continue_weight || 1, continue_fee || 0, item_first || 0, item_first_fee || 0, item_continue || 0, item_continue_fee || 0]
        )
      }
    }

    await conn.commit()

    const [[tpl]] = await pool.query('SELECT * FROM freight_templates WHERE id = ?', [req.params.id])
    const [tplRules] = await pool.query('SELECT * FROM freight_template_rules WHERE template_id = ?', [req.params.id])
    tpl.rules = tplRules
    if (tpl.region_names) {
      try { tpl.region_names = JSON.parse(tpl.region_names) } catch { tpl.region_names = [] }
    }

    res.json({ code: 0, data: tpl, message: 'ok' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// DELETE /api/logistics/freight-templates/:id - 删除运费模板
router.delete('/freight-templates/:id', requirePermission(P.FREIGHT_WRITE), async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT * FROM freight_templates WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '运费模板不存在' })

    await pool.query('DELETE FROM freight_template_rules WHERE template_id = ?', [req.params.id])
    await pool.query('DELETE FROM freight_templates WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: '删除成功' })
  } catch (err) { next(err) }
})

// ─── 渠道物流管理 ─────────────────────────────────────────────────────────────

// GET /api/logistics/channel-logistics - 渠道物流列表
router.get('/channel-logistics', requirePermission(P.CHANNEL_READ), async (req, res, next) => {
  try {
    const { page = 1, size = 20, name, status, express_company_id } = req.query
    const { page: p, size: s } = parsePagination({ page, size })
    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (name) {
      where += ' AND cl.name LIKE ?'
      const kw = `%${name}%`
      params.push(kw)
      countParams.push(kw)
    }
    if (status) {
      where += ' AND cl.status = ?'
      params.push(status)
      countParams.push(status)
    }
    if (express_company_id) {
      where += ' AND cl.express_company_id = ?'
      params.push(express_company_id)
      countParams.push(express_company_id)
    }

    const sql = `
      SELECT cl.*, ec.name as express_company_name, ec.code as express_company_code
      FROM channel_logistics cl
      LEFT JOIN express_companies ec ON cl.express_company_id = ec.id
      ${where}
      ORDER BY cl.id DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(*) as total FROM channel_logistics cl ${where}`

    const [[{ total }]] = await pool.query(countSql, countParams)
    params.push(s, (p - 1) * s)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page: p, size: s }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/logistics/channel-logistics/:id - 渠道物流详情
router.get('/channel-logistics/:id', requirePermission(P.CHANNEL_READ), async (req, res, next) => {
  try {
    const [[row]] = await pool.query(
      `SELECT cl.*, ec.name as express_company_name, ec.code as express_company_code
       FROM channel_logistics cl
       LEFT JOIN express_companies ec ON cl.express_company_id = ec.id
       WHERE cl.id = ?`,
      [req.params.id]
    )
    if (!row) return res.status(404).json({ code: 404, message: '渠道物流不存在' })
    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/logistics/channel-logistics - 新建渠道物流
router.post('/channel-logistics', requirePermission(P.CHANNEL_WRITE), async (req, res, next) => {
  try {
    const { name, express_company_id, channel_code, channel_name, website, tracking_url, status = 'enabled', min_days, max_days, first_weight_fee, continue_weight_fee, estimate_days, remark } = req.body
    if (!name) return res.status(400).json({ code: 400, message: '渠道名称必填' })

    const [result] = await pool.query(
      `INSERT INTO channel_logistics (name, express_company_id, channel_code, channel_name, website, tracking_url, status, min_days, max_days, first_weight_fee, continue_weight_fee, estimate_days, remark)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)`,
      [name, express_company_id || null, channel_code || null, channel_name || null, website || null, tracking_url || null, status, min_days || null, max_days || null, first_weight_fee || null, continue_weight_fee || null, estimate_days || null, remark || null]
    )

    const [[row]] = await pool.query(
      `SELECT cl.*, ec.name as express_company_name, ec.code as express_company_code
       FROM channel_logistics cl
       LEFT JOIN express_companies ec ON cl.express_company_id = ec.id
       WHERE cl.id = ?`,
      [result.insertId]
    )

    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/logistics/channel-logistics/:id - 更新渠道物流
router.put('/channel-logistics/:id', requirePermission(P.CHANNEL_WRITE), async (req, res, next) => {
  try {
    const [[existing]] = await pool.query('SELECT * FROM channel_logistics WHERE id = ?', [req.params.id])
    if (!existing) return res.status(404).json({ code: 404, message: '渠道物流不存在' })

    const { name, express_company_id, channel_code, channel_name, website, tracking_url, status, min_days, max_days, first_weight_fee, continue_weight_fee, estimate_days, remark } = req.body

    await pool.query(
      `UPDATE channel_logistics SET
        name=COALESCE(?,name), express_company_id=COALESCE(?,express_company_id), channel_code=COALESCE(?,channel_code),
        channel_name=COALESCE(?,channel_name), website=COALESCE(?,website), tracking_url=COALESCE(?,tracking_url),
        status=COALESCE(?,status), min_days=COALESCE(?,min_days), max_days=COALESCE(?,max_days),
        first_weight_fee=COALESCE(?,first_weight_fee), continue_weight_fee=COALESCE(?,continue_weight_fee),
        estimate_days=COALESCE(?,estimate_days), remark=COALESCE(?,remark)
       WHERE id=?`,
      [name, express_company_id, channel_code, channel_name, website, tracking_url, status, min_days, max_days, first_weight_fee, continue_weight_fee, estimate_days, remark, req.params.id]
    )

    const [[row]] = await pool.query(
      `SELECT cl.*, ec.name as express_company_name, ec.code as express_company_code
       FROM channel_logistics cl
       LEFT JOIN express_companies ec ON cl.express_company_id = ec.id
       WHERE cl.id = ?`,
      [req.params.id]
    )

    res.json({ code: 0, data: row, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE /api/logistics/channel-logistics/:id - 删除渠道物流
router.delete('/channel-logistics/:id', requirePermission(P.CHANNEL_WRITE), async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT * FROM channel_logistics WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ code: 404, message: '渠道物流不存在' })

    await pool.query('DELETE FROM channel_logistics WHERE id = ?', [req.params.id])
    res.json({ code: 0, data: null, message: '删除成功' })
  } catch (err) { next(err) }
})

// ─── 物流工具 ─────────────────────────────────────────────────────────────────

// POST /api/logistics/calculate-freight - 计算运费
router.post('/calculate-freight', async (req, res, next) => {
  try {
    const { template_id, weight = 0, item_count = 0, region, total_amount = 0 } = req.body
    if (!template_id) return res.status(400).json({ code: 400, message: 'template_id 必填' })

    const [[tpl]] = await pool.query('SELECT * FROM freight_templates WHERE id = ? AND status = "enabled"', [template_id])
    if (!tpl) return res.status(404).json({ code: 404, message: '运费模板不存在或已禁用' })

    // 查询匹配的规则（按 region_names 模糊匹配）
    let rules = []
    if (region) {
      [rules] = await pool.query(
        `SELECT * FROM freight_template_rules WHERE template_id = ? AND (region_names = '[]' OR region_names LIKE ?)`,
        [template_id, `%"${region}"%`]
      )
    }
    if (rules.length === 0) {
      [rules] = await pool.query('SELECT * FROM freight_template_rules WHERE template_id = ? ORDER BY first_weight ASC LIMIT 1', [template_id])
    }
    if (rules.length === 0) {
      return res.json({ code: 0, data: { freight: 0, is_free: false }, message: 'ok' })
    }

    const rule = rules[0]
    let freight = 0

    if (tpl.calc_type === 'by_weight') {
      // 按重量计费
      const firstWeight = parseFloat(rule.first_weight) || 1
      const firstFee = parseFloat(rule.first_fee) || 0
      const continueWeight = parseFloat(rule.continue_weight) || 1
      const continueFee = parseFloat(rule.continue_fee) || 0

      if (weight <= firstWeight) {
        freight = firstFee
      } else {
        const extra = Math.ceil((weight - firstWeight) / continueWeight) * continueFee
        freight = firstFee + extra
      }
    } else {
      // 按件计费
      const itemFirst = parseInt(rule.item_first) || 0
      const itemFirstFee = parseFloat(rule.item_first_fee) || 0
      const itemContinue = parseInt(rule.item_continue) || 0
      const itemContinueFee = parseFloat(rule.item_continue_fee) || 0

      const count = parseInt(item_count) || 1
      if (count <= itemFirst) {
        freight = itemFirstFee
      } else {
        freight = itemFirstFee + Math.ceil((count - itemFirst) / itemContinue) * itemContinueFee
      }
    }

    // 满免判断
    let is_free = false
    if (tpl.min_free_amount && total_amount >= parseFloat(tpl.min_free_amount)) {
      is_free = true
      freight = 0
    } else if (tpl.min_free_number && item_count >= parseInt(tpl.min_free_number)) {
      is_free = true
      freight = 0
    }

    res.json({ code: 0, data: { freight, is_free, rule }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/logistics/track - 物流轨迹查询（模拟）
router.post('/track', async (req, res, next) => {
  try {
    const { tracking_number, express_code } = req.body
    if (!tracking_number) return res.status(400).json({ code: 400, message: '运单号必填' })

    // 这里可对接真实快递API（示例返回模拟数据）
    const mockData = {
      status: 'in_transit',
      traces: [
        { time: new Date(Date.now() - 86400000 * 3).toISOString(), location: '深圳转运中心', description: '快件已发出，正在运输途中' },
        { time: new Date(Date.now() - 86400000 * 2).toISOString(), location: '广州转运中心', description: '快件到达广州转运中心' },
        { time: new Date(Date.now() - 86400000).toISOString(), location: '广州花都区', description: '快件正在派送途中' },
      ]
    }

    res.json({ code: 0, data: { tracking_number, express_code, ...mockData }, message: 'ok' })
  } catch (err) { next(err) }
})

export default router