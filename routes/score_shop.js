/**
 * 积分商城后端路由 - score_shop.js
 * API: /api/score-shop/*
 * 功能：积分商品管理 / 积分兑换 / 积分记录 / 签到
 */
import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { auth } from '../middleware/auth.js'

// 获取 pool（兼容动态 import）
let dbPool = pool

const router = Router()

// ─── 辅助函数 ───────────────────────────────────────────────────────────────

// 生成积分订单号
function generateScoreOrderNo() {
  const d = new Date()
  const dateStr = d.toISOString().slice(0, 10).replace(/-/g, '')
  const random = String(Math.floor(Math.random() * 1000000)).padStart(6, '0')
  return `SP${dateStr}${random}`
}

// 获取用户当前积分
async function getUserScore(userId) {
  const [[{ total }]] = await dbPool.query(
    'SELECT COALESCE(SUM(CASE WHEN type IN ("earn","refund") THEN score ELSE -score END), 0) as total FROM score_records WHERE user_id = ?',
    [userId]
  )
  return total || 0
}

// ─── 积分配置 ───────────────────────────────────────────────────────────────

// GET /api/score-shop/config — 获取积分规则配置
router.get('/config', async (req, res, next) => {
  try {
    const [rows] = await dbPool.query('SELECT `key`, value, description FROM score_config')
    const config = {}
    for (const r of rows) config[r.key] = r.value
    res.json({ code: 0, data: config, message: 'ok' })
  } catch (err) { next(err) }
})

// ─── 积分商品 API ────────────────────────────────────────────────────────────

// GET /api/score-shop/products — 积分商品列表
router.get('/products', async (req, res, next) => {
  try {
    const { category_id, keyword, status = 'active', page = 1, size = 20 } = req.query
    const { offset } = parsePagination(req.query)

    let where = 'WHERE p.status = ?'
    const params = [status]

    if (category_id && category_id > 0) {
      where += ' AND p.category_id = ?'
      params.push(Number(category_id))
    }
    if (keyword) {
      where += ' AND (p.name LIKE ? OR p.description LIKE ?)'
      params.push(`%${keyword}%`, `%${keyword}%`)
    }

    const [rows] = await dbPool.query(`
      SELECT p.*, c.name as category_name
      FROM score_products p
      LEFT JOIN score_categories c ON p.category_id = c.id
      ${where}
      ORDER BY p.is_recommend DESC, p.sort_order ASC, p.id DESC
      LIMIT ? OFFSET ?
    `, [...params, Number(size), Number(offset)])

    const [[{ cnt }]] = await dbPool.query(
      `SELECT COUNT(*) as cnt FROM score_products p ${where}`, params
    )

    // 解析 images JSON
    for (const p of rows) {
      if (p.images) {
        try { p.images = JSON.parse(p.images) } catch { p.images = [] }
      } else { p.images = [] }
    }

    res.json({ code: 0, data: { list: rows, total: cnt, page: Number(page), size: Number(size) }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/score-shop/products/:id — 积分商品详情
router.get('/products/:id', async (req, res, next) => {
  try {
    const [[p]] = await dbPool.query(`
      SELECT p.*, c.name as category_name
      FROM score_products p
      LEFT JOIN score_categories c ON p.category_id = c.id
      WHERE p.id = ?
    `, [req.params.id])

    if (!p) return res.status(404).json({ code: 404, message: '商品不存在' })

    if (p.images) {
      try { p.images = JSON.parse(p.images) } catch { p.images = [] }
    } else { p.images = [] }

    res.json({ code: 0, data: p, message: 'ok' })
  } catch (err) { next(err) }
})

// ─── 积分兑换 API ────────────────────────────────────────────────────────────

// GET /api/score-shop/orders — 积分兑换记录列表
router.get('/orders', auth, async (req, res, next) => {
  try {
    const { status, page = 1, size = 20 } = req.query
    const { offset } = parsePagination(req.query)

    let where = 'WHERE o.user_id = ?'
    const params = [req.user.id]

    if (status) {
      where += ' AND o.status = ?'
      params.push(status)
    }

    const [rows] = await dbPool.query(`
      SELECT o.*
      FROM score_orders o
      ${where}
      ORDER BY o.created_at DESC
      LIMIT ? OFFSET ?
    `, [...params, Number(size), Number(offset)])

    const [[{ cnt }]] = await dbPool.query(
      `SELECT COUNT(*) as cnt FROM score_orders o ${where}`, params
    )

    res.json({ code: 0, data: { list: rows, total: cnt, page: Number(page), size: Number(size) }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/score-shop/exchange — 积分兑换
router.post('/exchange', auth, async (req, res, next) => {
  const conn = await dbPool.getConnection()
  try {
    const { score_product_id, quantity = 1, address_id, remark } = req.body
    if (!score_product_id) return res.status(400).json({ code: 400, message: 'score_product_id 必填' })
    if (quantity < 1) return res.status(400).json({ code: 400, message: '数量至少为1' })

    await conn.beginTransaction()

    // 检查商品
    const [[product]] = await conn.query(
      'SELECT * FROM score_products WHERE id = ? AND status = "active"',
      [score_product_id]
    )
    if (!product) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '商品不存在或已下架' })
    }
    if (product.stock < quantity) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '库存不足' })
    }

    // 检查用户积分
    const [[scoreRow]] = await conn.query(
      'SELECT COALESCE(SUM(CASE WHEN type IN ("earn","refund") THEN score ELSE -score END), 0) as total FROM score_records WHERE user_id = ?',
      [req.user.id]
    )
    const userScore = scoreRow?.total || 0
    const totalScore = product.score_price * quantity

    if (userScore < totalScore) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `积分不足，当前${userScore}积分，需要${totalScore}积分` })
    }

    // 获取收货地址
    let receiver_name = '', receiver_phone = '', receiver_address = ''
    if (address_id) {
      const [[addr]] = await conn.query(
        'SELECT receiver_name, receiver_phone, province, city, district, address FROM addresses WHERE id = ? AND user_id = ?',
        [address_id, req.user.id]
      )
      if (addr) {
        receiver_name = addr.receiver_name
        receiver_phone = addr.receiver_phone
        receiver_address = `${addr.province}${addr.city}${addr.district}${addr.address}`
      }
    }

    // 创建兑换订单
    const order_no = generateScoreOrderNo()
    const [result] = await conn.query(`
      INSERT INTO score_orders (order_no, user_id, score_product_id, product_name, product_image, score_price, quantity, total_score, address_id, receiver_name, receiver_phone, receiver_address, remark)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, [order_no, req.user.id, score_product_id, product.name, product.image_main, product.score_price, quantity, totalScore, address_id || null, receiver_name, receiver_phone, receiver_address, remark || null])

    const order_id = result.insertId

    // 扣减库存
    await conn.query('UPDATE score_products SET stock = stock - ?, exchange_count = exchange_count + ? WHERE id = ?',
      [quantity, quantity, score_product_id])

    // 扣减用户积分（记录）
    const newBalance = userScore - totalScore
    await conn.query(
      'INSERT INTO score_records (user_id, type, score, balance, source, source_id, remark) VALUES (?, "spend", ?, ?, "exchange", ?, ?)',
      [req.user.id, totalScore, newBalance, order_id, `积分兑换：${product.name}`]
    )

    await conn.commit()
    res.json({ code: 0, data: { order_id, order_no, total_score: totalScore }, message: '兑换成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ─── 积分记录 API ────────────────────────────────────────────────────────────

// GET /api/score-shop/records — 积分变动记录
router.get('/records', auth, async (req, res, next) => {
  try {
    const { page = 1, size = 20 } = req.query
    const { offset } = parsePagination(req.query)

    const [rows] = await dbPool.query(
      `SELECT * FROM score_records WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?`,
      [req.user.id, Number(size), Number(offset)]
    )
    const [[{ cnt }]] = await dbPool.query(
      'SELECT COUNT(*) as cnt FROM score_records WHERE user_id = ?', [req.user.id]
    )

    res.json({ code: 0, data: { list: rows, total: cnt, page: Number(page), size: Number(size) }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/score-shop/balance — 当前积分余额
router.get('/balance', auth, async (req, res, next) => {
  try {
    const balance = await getUserBalance(req.user.id)
    res.json({ code: 0, data: { balance }, message: 'ok' })
  } catch (err) { next(err) }
})

async function getUserBalance(userId) {
  const [[{ total }]] = await dbPool.query(
    'SELECT COALESCE(SUM(CASE WHEN type IN ("earn","refund") THEN score ELSE -score END), 0) as total FROM score_records WHERE user_id = ?',
    [userId]
  )
  return total || 0
}

// ─── 签到 API ────────────────────────────────────────────────────────────────

// GET /api/score-shop/sign — 今日签到状态
router.get('/sign', auth, async (req, res, next) => {
  try {
    const today = new Date().toISOString().slice(0, 10)
    const [[signRecord]] = await dbPool.query(
      'SELECT * FROM score_sign_records WHERE user_id = ? AND sign_date = ?',
      [req.user.id, today]
    )

    // 获取签到积分配置
    const [[signConfig]] = await dbPool.query(
      "SELECT value FROM score_config WHERE `key` = 'sign_score' LIMIT 1"
    )
    const signScore = parseInt(signConfig?.value || '10')

    // 获取连续签到天数
    const [[{ streak }]] = await dbPool.query(`
      SELECT COUNT(*) as streak FROM score_sign_records
      WHERE user_id = ? AND sign_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      ORDER BY sign_date DESC
    `, [req.user.id])

    res.json({
      code: 0,
      data: {
        signed: !!signRecord,
        sign_score: signScore,
        streak_days: streak || 0
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// POST /api/score-shop/sign — 执行签到
router.post('/sign', auth, async (req, res, next) => {
  const conn = await dbPool.getConnection()
  try {
    const today = new Date().toISOString().slice(0, 10)

    // 检查今日是否已签到
    const [[existing]] = await conn.query(
      'SELECT id FROM score_sign_records WHERE user_id = ? AND sign_date = ?',
      [req.user.id, today]
    )
    if (existing) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '今日已签到' })
    }

    // 获取签到积分
    const [[signConfig]] = await conn.query(
      "SELECT value FROM score_config WHERE `key` = 'sign_score' LIMIT 1"
    )
    const signScore = parseInt(signConfig?.value || '10')

    await conn.beginTransaction()

    // 写入签到记录
    await conn.query(
      'INSERT INTO score_sign_records (user_id, sign_date, score) VALUES (?, ?, ?)',
      [req.user.id, today, signScore]
    )

    // 计算当前积分余额
    const [[scoreRow]] = await conn.query(
      'SELECT COALESCE(SUM(CASE WHEN type IN ("earn","refund") THEN score ELSE -score END), 0) as total FROM score_records WHERE user_id = ?',
      [req.user.id]
    )
    const newBalance = (scoreRow?.total || 0) + signScore

    // 写入积分记录
    await conn.query(
      'INSERT INTO score_records (user_id, type, score, balance, source, remark) VALUES (?, "earn", ?, ?, "sign", "每日签到")',
      [req.user.id, signScore, newBalance]
    )

    await conn.commit()
    res.json({ code: 0, data: { score: signScore, balance: newBalance }, message: '签到成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ─── 后台管理 API ────────────────────────────────────────────────────────────

// GET /api/score-shop/admin/products — 后台积分商品列表（含筛选）
router.get('/admin/products', auth, async (req, res, next) => {
  try {
    const { status, keyword, page = 1, size = 20 } = req.query
    const { offset } = parsePagination(req.query)

    let where = 'WHERE 1=1'
    const params = []
    if (status) { where += ' AND p.status = ?'; params.push(status) }
    if (keyword) { where += ' AND (p.name LIKE ? OR p.description LIKE ?)'; params.push(`%${keyword}%`, `%${keyword}%`) }

    const [rows] = await dbPool.query(`
      SELECT p.*, c.name as category_name
      FROM score_products p
      LEFT JOIN score_categories c ON p.category_id = c.id
      ${where}
      ORDER BY p.id DESC
      LIMIT ? OFFSET ?
    `, [...params, Number(size), Number(offset)])

    const [[{ cnt }]] = await dbPool.query(
      `SELECT COUNT(*) as cnt FROM score_products p ${where}`, params
    )

    res.json({ code: 0, data: { list: rows, total: cnt, page: Number(page), size: Number(size) }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/score-shop/admin/products — 新增积分商品
router.post('/admin/products', auth, async (req, res, next) => {
  try {
    const { name, description, image_main, images, score_price, stock, safe_stock, category_id, is_recommend, sort_order, status } = req.body
    if (!name || score_price == null) return res.status(400).json({ code: 400, message: 'name 和 score_price 必填' })

    let imagesJson = null
    if (images) {
      imagesJson = Array.isArray(images) ? JSON.stringify(images) : images
    }

    const [result] = await dbPool.query(`
      INSERT INTO score_products (name, description, image_main, images, score_price, stock, safe_stock, category_id, is_recommend, sort_order, status)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, [name, description || null, image_main || null, imagesJson, score_price, stock || 0, safe_stock || 0, category_id || null, is_recommend ? 1 : 0, sort_order || 0, status || 'active'])

    res.json({ code: 0, data: { id: result.insertId }, message: '创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/score-shop/admin/products/:id — 更新积分商品
router.put('/admin/products/:id', auth, async (req, res, next) => {
  try {
    const { name, description, image_main, images, score_price, stock, safe_stock, category_id, is_recommend, sort_order, status } = req.body
    const fields = [], vals = []
    if (name !== undefined) { fields.push('name=?'); vals.push(name) }
    if (description !== undefined) { fields.push('description=?'); vals.push(description) }
    if (image_main !== undefined) { fields.push('image_main=?'); vals.push(image_main) }
    if (images !== undefined) { fields.push('images=?'); vals.push(Array.isArray(images) ? JSON.stringify(images) : images) }
    if (score_price !== undefined) { fields.push('score_price=?'); vals.push(score_price) }
    if (stock !== undefined) { fields.push('stock=?'); vals.push(stock) }
    if (safe_stock !== undefined) { fields.push('safe_stock=?'); vals.push(safe_stock) }
    if (category_id !== undefined) { fields.push('category_id=?'); vals.push(category_id) }
    if (is_recommend !== undefined) { fields.push('is_recommend=?'); vals.push(is_recommend ? 1 : 0) }
    if (sort_order !== undefined) { fields.push('sort_order=?'); vals.push(sort_order) }
    if (status !== undefined) { fields.push('status=?'); vals.push(status) }

    if (!fields.length) return res.status(400).json({ code: 400, message: '没有更新字段' })
    vals.push(req.params.id)
    await dbPool.query(`UPDATE score_products SET ${fields.join(',')} WHERE id = ?`, vals)
    res.json({ code: 0, message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/score-shop/admin/products/:id — 删除积分商品
router.delete('/admin/products/:id', auth, async (req, res, next) => {
  try {
    await dbPool.query('DELETE FROM score_products WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

// GET /api/score-shop/admin/orders — 后台积分订单列表
router.get('/admin/orders', auth, async (req, res, next) => {
  try {
    const { status, keyword, date_start, date_end, page = 1, size = 20 } = req.query
    const { offset } = parsePagination(req.query)

    let where = 'WHERE 1=1'
    const params = []
    if (status) { where += ' AND o.status = ?'; params.push(status) }
    if (keyword) { where += ' AND (o.order_no LIKE ? OR o.user_id = ? OR o.product_name LIKE ?)'; params.push(`%${keyword}%`, keyword, `%${keyword}%`) }
    if (date_start) { where += ' AND DATE(o.created_at) >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND DATE(o.created_at) <= ?'; params.push(date_end) }

    const [rows] = await dbPool.query(`
      SELECT o.*, u.name as user_name, u.phone as user_phone
      FROM score_orders o
      LEFT JOIN users u ON o.user_id = u.id
      ${where}
      ORDER BY o.created_at DESC
      LIMIT ? OFFSET ?
    `, [...params, Number(size), Number(offset)])

    const [[{ cnt }]] = await dbPool.query(
      `SELECT COUNT(*) as cnt FROM score_orders o ${where}`, params
    )

    res.json({ code: 0, data: { list: rows, total: cnt, page: Number(page), size: Number(size) }, message: 'ok' })
  } catch (err) { next(err) }
})

// PUT /api/score-shop/admin/orders/:id/status — 变更积分订单状态
router.put('/admin/orders/:id/status', auth, async (req, res, next) => {
  try {
    const { status } = req.body
    if (!status) return res.status(400).json({ code: 400, message: 'status 必填' })

    const [[order]] = await dbPool.query('SELECT * FROM score_orders WHERE id = ?', [req.params.id])
    if (!order) return res.status(404).json({ code: 404, message: '订单不存在' })

    let extra = ''
    if (status === 'shipped') extra = ', shipped_at = NOW()'
    if (status === 'completed') extra = ', completed_at = NOW()'

    await dbPool.query(`UPDATE score_orders SET status = ?${extra} WHERE id = ?`, [status, req.params.id])
    res.json({ code: 0, message: '状态更新成功' })
  } catch (err) { next(err) }
})

// GET /api/score-shop/admin/categories — 后台积分商品分类
router.get('/admin/categories', async (req, res, next) => {
  try {
    const [rows] = await dbPool.query('SELECT * FROM score_categories ORDER BY sort_order, id')
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) { next(err) }
})

export default router
