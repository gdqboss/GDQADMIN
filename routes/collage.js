import { Router } from 'express'
import { pool } from '../db/connection.js'
const router = Router()

const TABLE_PRODUCT = 'collage_product'
const TABLE_CATEGORY = 'collage_category'
const TABLE_ORDER = 'collage_order'
const TABLE_ORDER_TEAM = 'collage_order_team'
const TABLE_GUIGE = 'collage_guige'
const TABLE_COMMENT = 'collage_comment'
const TABLE_CODELIST = 'collage_codelist'
const TABLE_SYSSET = 'collage_sysset'

// ─── 分类 ──────────────────────────────────────────────
router.get('/categories', async (req, res) => {
  try {
    const [rows] = await pool.query(`SELECT * FROM ${TABLE_CATEGORY} ORDER BY sort ASC, id ASC`)
    res.json({ code: 0, data: rows })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.post('/categories', async (req, res) => {
  try {
    const { pid = 0, name, pic = '', status = 1, sort = 0 } = req.body
    const [r] = await pool.query(`INSERT INTO ${TABLE_CATEGORY} (pid, name, pic, status, sort) VALUES (?, ?, ?, ?, ?)`,
      [pid, name, pic, status, sort])
    res.json({ code: 0, data: { id: r.insertId } })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.put('/categories/:id', async (req, res) => {
  try {
    const { pid, name, pic, status, sort } = req.body
    await pool.query(`UPDATE ${TABLE_CATEGORY} SET pid=?, name=?, pic=?, status=?, sort=? WHERE id=?`,
      [pid, name, pic, status, sort, req.params.id])
    res.json({ code: 0 })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.delete('/categories/:id', async (req, res) => {
  try {
    await pool.query(`DELETE FROM ${TABLE_CATEGORY} WHERE id=?`, [req.params.id])
    res.json({ code: 0 })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

// ─── 商品 ──────────────────────────────────────────────
router.get('/products', async (req, res) => {
  try {
    const { page = 1, size = 20, cid, status, keyword } = req.query
    let where = ['1=1']
    let params = []
    if (cid) { where.push('cid=?'); params.push(cid) }
    if (status !== undefined) { where.push('status=?'); params.push(status) }
    if (keyword) { where.push('name LIKE ?'); params.push('%' + keyword + '%') }
    const offset = (page - 1) * size
    const [total] = await pool.query(`SELECT COUNT(*) as n FROM ${TABLE_PRODUCT} WHERE ${where.join(' AND ')}`, params)
    const [rows] = await pool.query(
      `SELECT p.*, c.name as catname FROM ${TABLE_PRODUCT} p LEFT JOIN ${TABLE_CATEGORY} c ON p.cid=c.id WHERE ${where.join(' AND ')} ORDER BY p.sort ASC, p.id DESC LIMIT ? OFFSET ?`,
      [...params, Number(size), Number(offset)]
    )
    res.json({ code: 0, data: { list: rows, total: total[0].n } })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.get('/products/:id', async (req, res) => {
  try {
    const [rows] = await pool.query(`SELECT p.*, c.name as catname FROM ${TABLE_PRODUCT} p LEFT JOIN ${TABLE_CATEGORY} c ON p.cid=c.id WHERE p.id=?`, [req.params.id])
    if (!rows.length) return res.json({ code: 404, message: '商品不存在' })
    const [guige] = await pool.query(`SELECT * FROM ${TABLE_GUIGE} WHERE proid=?`, [req.params.id])
    rows[0].guigedata = rows[0].guigedata ? JSON.parse(rows[0].guigedata) : []
    rows[0].pics = rows[0].pics ? JSON.parse(rows[0].pics) : []
    rows[0].guige = guige
    res.json({ code: 0, data: rows[0] })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.post('/products', async (req, res) => {
  try {
    const {
      cid = 0, name, procode = '', pic = '', pics = [], detail = '',
      market_price = 0, sell_price = 0, leader_price = 0, cost_price = 0,
      weight = 0, stock = 0, sales = 0, sort = 0, status = 1,
      teamnum = 2, teamhour = 24, buymax = 0, freight_type = 0,
      freight_content = '', guigedata = [], ischecked = 1,
      starttime = 0, endtime = 0
    } = req.body
    const [r] = await pool.query(
      `INSERT INTO ${TABLE_PRODUCT} (cid, name, procode, pic, pics, detail, market_price, sell_price, leader_price, cost_price, weight, stock, sales, sort, status, teamnum, teamhour, buymax, freight_type, freight_content, guigedata, ischecked, starttime, endtime, createtime)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
      [cid, name, procode, pic, JSON.stringify(pics), detail, market_price, sell_price, leader_price, cost_price, weight, stock, sales, sort, status, teamnum, teamhour, buymax, freight_type, freight_content, JSON.stringify(guigedata), ischecked, starttime, endtime, Math.floor(Date.now() / 1000)]
    )
    res.json({ code: 0, data: { id: r.insertId } })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.put('/products/:id', async (req, res) => {
  try {
    const {
      cid, name, procode, pic, pics, detail,
      market_price, sell_price, leader_price, cost_price,
      weight, stock, sales, sort, status,
      teamnum, teamhour, buymax, freight_type,
      freight_content, guigedata, ischecked, starttime, endtime
    } = req.body
    await pool.query(
      `UPDATE ${TABLE_PRODUCT} SET cid=?, name=?, procode=?, pic=?, pics=?, detail=?, market_price=?, sell_price=?, leader_price=?, cost_price=?, weight=?, stock=?, sales=?, sort=?, status=?, teamnum=?, teamhour=?, buymax=?, freight_type=?, freight_content=?, guigedata=?, ischecked=?, starttime=?, endtime=? WHERE id=?`,
      [cid, name, procode, pic, JSON.stringify(pics || []), detail, market_price, sell_price, leader_price, cost_price, weight, stock, sales, sort, status, teamnum, teamhour, buymax, freight_type, freight_content, JSON.stringify(guigedata || []), ischecked, starttime, endtime, req.params.id]
    )
    res.json({ code: 0 })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.delete('/products/:id', async (req, res) => {
  try {
    await pool.query(`DELETE FROM ${TABLE_PRODUCT} WHERE id=?`, [req.params.id])
    res.json({ code: 0 })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

// ─── 订单 ──────────────────────────────────────────────
router.get('/orders', async (req, res) => {
  try {
    const { page = 1, size = 20, status, keyword, startDate, endDate } = req.query
    let where = ['1=1']
    let params = []
    if (status !== undefined) { where.push('status=?'); params.push(status) }
    if (keyword) { where.push('(ordernum LIKE ? OR proname LIKE ? OR linkman LIKE ? OR tel LIKE ?)'); params.push('%' + keyword + '%', '%' + keyword + '%', '%' + keyword + '%', '%' + keyword + '%') }
    if (startDate) { where.push('createtime>=?'); params.push(Math.floor(new Date(startDate).getTime() / 1000)) }
    if (endDate) { where.push('createtime<=?'); params.push(Math.floor(new Date(endDate).getTime() / 1000) + 86400) }
    const offset = (page - 1) * size
    const [total] = await pool.query(`SELECT COUNT(*) as n FROM ${TABLE_ORDER} WHERE ${where.join(' AND ')}`, params)
    const [rows] = await pool.query(
      `SELECT * FROM ${TABLE_ORDER} WHERE ${where.join(' AND ')} ORDER BY id DESC LIMIT ? OFFSET ?`,
      [...params, Number(size), Number(offset)]
    )
    res.json({ code: 0, data: { list: rows, total: total[0].n } })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.get('/orders/:id', async (req, res) => {
  try {
    const [rows] = await pool.query(`SELECT * FROM ${TABLE_ORDER} WHERE id=?`, [req.params.id])
    if (!rows.length) return res.json({ code: 404, message: '订单不存在' })
    res.json({ code: 0, data: rows[0] })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.put('/orders/:id/status', async (req, res) => {
  try {
    const { status } = req.body
    const updates = { status }
    if (status == 2) updates.send_time = Math.floor(Date.now() / 1000)
    const fields = Object.keys(updates).map(k => `${k}=?`).join(',')
    await pool.query(`UPDATE ${TABLE_ORDER} SET ${fields} WHERE id=?`, Object.values(updates).concat(req.params.id))
    res.json({ code: 0 })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.put('/orders/:id/refund', async (req, res) => {
  try {
    const { refund_status, refund_reason = '' } = req.body
    await pool.query(`UPDATE ${TABLE_ORDER} SET refund_status=?, refund_reason=? WHERE id=?`,
      [refund_status, refund_reason, req.params.id])
    res.json({ code: 0 })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.put('/orders/:id/express', async (req, res) => {
  try {
    const { express_com, express_no } = req.body
    await pool.query(`UPDATE ${TABLE_ORDER} SET express_com=?, express_no=?, send_time=? WHERE id=?`,
      [express_com, express_no, Math.floor(Date.now() / 1000), req.params.id])
    res.json({ code: 0 })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

// ─── 团购团 ────────────────────────────────────────────
router.get('/teams', async (req, res) => {
  try {
    const { page = 1, size = 20, status, proid } = req.query
    let where = ['1=1']
    let params = []
    if (status !== undefined) { where.push('t.status=?'); params.push(status) }
    if (proid) { where.push('t.proid=?'); params.push(proid) }
    const offset = (page - 1) * size
    const [total] = await pool.query(`SELECT COUNT(*) as n FROM ${TABLE_ORDER_TEAM} t WHERE ${where.join(' AND ')}`, params)
    const [rows] = await pool.query(
      `SELECT t.*, p.name as proname, p.pic as propic FROM ${TABLE_ORDER_TEAM} t LEFT JOIN ${TABLE_PRODUCT} p ON t.proid=p.id WHERE ${where.join(' AND ')} ORDER BY t.id DESC LIMIT ? OFFSET ?`,
      [...params, Number(size), Number(offset)]
    )
    res.json({ code: 0, data: { list: rows, total: total[0].n } })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.get('/teams/:id/members', async (req, res) => {
  try {
    const [rows] = await pool.query(`SELECT o.*, u.name as uname, u.phone as uphone FROM ${TABLE_ORDER} o LEFT JOIN users u ON o.mid=u.id WHERE o.teamid=? ORDER BY o.id ASC`, [req.params.id])
    res.json({ code: 0, data: rows })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

// ─── 规格 ──────────────────────────────────────────────
router.post('/guige', async (req, res) => {
  try {
    const { proid, goods_sn = '', guige_name, stock = 0, sell_price = 0, leader_price = 0, cost_price = 0, weight = 0 } = req.body
    const [r] = await pool.query(`INSERT INTO ${TABLE_GUIGE} (proid, goods_sn, guige_name, stock, sell_price, leader_price, cost_price, weight) VALUES (?,?,?,?,?,?,?,?)`,
      [proid, goods_sn, guige_name, stock, sell_price, leader_price, cost_price, weight])
    res.json({ code: 0, data: { id: r.insertId } })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.delete('/guige/:id', async (req, res) => {
  try {
    await pool.query(`DELETE FROM ${TABLE_GUIGE} WHERE id=?`, [req.params.id])
    res.json({ code: 0 })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

// ─── 评论 ──────────────────────────────────────────────
router.get('/comments', async (req, res) => {
  try {
    const { page = 1, size = 20, proid } = req.query
    let where = ['1=1']
    let params = []
    if (proid) { where.push('c.proid=?'); params.push(proid) }
    const offset = (page - 1) * size
    const [total] = await pool.query(`SELECT COUNT(*) as n FROM ${TABLE_COMMENT} c WHERE ${where.join(' AND ')}`, params)
    const [rows] = await pool.query(
      `SELECT c.*, u.name as uname FROM ${TABLE_COMMENT} c LEFT JOIN users u ON c.mid=u.id WHERE ${where.join(' AND ')} ORDER BY c.id DESC LIMIT ? OFFSET ?`,
      [...params, Number(size), Number(offset)]
    )
    res.json({ code: 0, data: { list: rows, total: total[0].n } })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.put('/comments/:id/reply', async (req, res) => {
  try {
    const { reply } = req.body
    await pool.query(`UPDATE ${TABLE_COMMENT} SET reply=? WHERE id=?`, [reply, req.params.id])
    res.json({ code: 0 })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

// ─── 系统设置 ───────────────────────────────────────────
router.get('/sysset', async (req, res) => {
  try {
    const [rows] = await pool.query(`SELECT \`key\`, value FROM ${TABLE_SYSSET}`)
    const data = {}
    rows.forEach(r => { try { data[r.key] = JSON.parse(r.value) } catch { data[r.key] = r.value } })
    res.json({ code: 0, data })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

router.post('/sysset', async (req, res) => {
  try {
    const entries = Object.entries(req.body)
    for (const [k, v] of entries) {
      const val = typeof v === 'object' ? JSON.stringify(v) : String(v)
      await pool.query(`INSERT INTO ${TABLE_SYSSET} (\`key\`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=?`, [k, val, val])
    }
    res.json({ code: 0 })
  } catch (e) {
    res.json({ code: 500, message: e.message })
  }
})

export default router