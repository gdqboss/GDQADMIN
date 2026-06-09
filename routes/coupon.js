/**
 * 优惠券后端路由 - coupon.js
 * API: /api/coupon/*
 * 功能：管理员优惠券管理 / 用户优惠券 / 领取/使用
 */
import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import { auth } from '../middleware/auth.js'

let dbPool = pool
const router = Router()

// ─── 用户端优惠券 API ────────────────────────────────────────────────────────

// GET /api/coupon/my — 我的优惠券（用户已领取的）
router.get('/my', auth, async (req, res, next) => {
  try {
    const { status, page = 1, size = 20 } = req.query
    const { offset } = parsePagination(req.query)

    let where = 'WHERE uc.user_id = ?'
    const params = [req.user.id]

    if (status === 'unused') {
      where += ' AND uc.status = "unused" AND uc.valid_end >= NOW()'
    } else if (status === 'used') {
      where += ' AND uc.status = "used"'
    } else if (status === 'expired') {
      where += ' AND uc.status = "unused" AND uc.valid_end < NOW()'
    }

    const [rows] = await dbPool.query(`
      SELECT uc.id as user_coupon_id,
             uc.status, uc.used_order_id, uc.used_at, uc.received_at,
             uc.valid_start, uc.valid_end,
             ac.id as coupon_id, ac.name, ac.type, ac.money, ac.min_amount,
             ac.discount_rate, ac.shipping_fee, ac.apply_all
      FROM user_admin_coupons uc
      JOIN admin_coupons ac ON uc.coupon_id = ac.id
      ${where}
      ORDER BY uc.valid_end ASC
      LIMIT ? OFFSET ?
    `, [...params, Number(size), Number(offset)])

    const [[{ cnt }]] = await dbPool.query(`
      SELECT COUNT(*) as cnt FROM user_admin_coupons uc
      JOIN admin_coupons ac ON uc.coupon_id = ac.id
      ${where}
    `, params)

    res.json({ code: 0, data: { list: rows, total: cnt, page: Number(page), size: Number(size) }, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/coupon/available — 可领取的优惠券列表
router.get('/available', async (req, res, next) => {
  try {
    const { page = 1, size = 20 } = req.query
    const { offset } = parsePagination(req.query)

    const [rows] = await dbPool.query(`
      SELECT ac.*,
             (ac.remain_count > 0 AND ac.status = 'active' AND (ac.start_time IS NULL OR ac.start_time <= NOW()) AND (ac.end_time IS NULL OR ac.end_time >= NOW())) as can_get
      FROM admin_coupons ac
      WHERE ac.status = 'active'
        AND ac.remain_count > 0
        AND (ac.start_time IS NULL OR ac.start_time <= NOW())
        AND (ac.end_time IS NULL OR ac.end_time >= NOW())
      ORDER BY ac.id DESC
      LIMIT ? OFFSET ?
    `, [Number(size), Number(offset)])

    const [[{ cnt }]] = await dbPool.query(`
      SELECT COUNT(*) as cnt FROM admin_coupons ac
      WHERE ac.status = 'active'
        AND ac.remain_count > 0
        AND (ac.start_time IS NULL OR ac.start_time <= NOW())
        AND (ac.end_time IS NULL OR ac.end_time >= NOW())
    `)

    res.json({ code: 0, data: { list: rows, total: cnt, page: Number(page), size: Number(size) }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/coupon/:id/receive — 领取优惠券
router.post('/:id/receive', auth, async (req, res, next) => {
  const conn = await dbPool.getConnection()
  try {
    const couponId = req.params.id

    await conn.beginTransaction()

    const [[coupon]] = await conn.query('SELECT * FROM admin_coupons WHERE id = ? AND status = "active"', [couponId])
    if (!coupon) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '优惠券不存在或已下架' })
    }
    if (coupon.remain_count <= 0) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '优惠券已领完' })
    }
    if (coupon.start_time && new Date(coupon.start_time) > new Date()) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '优惠券还未开始领取' })
    }
    if (coupon.end_time && new Date(coupon.end_time) < new Date()) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '优惠券已过期' })
    }

    // 检查限领
    const [[{ getCount }]] = await conn.query(
      'SELECT COUNT(*) as getCount FROM user_admin_coupons WHERE user_id = ? AND coupon_id = ?',
      [req.user.id, couponId]
    )
    if (getCount >= coupon.per_limit) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: `该优惠券每人限领${coupon.per_limit}张` })
    }

    // 扣库存
    await conn.query('UPDATE admin_coupons SET remain_count = remain_count - 1 WHERE id = ?', [couponId])

    // 计算有效期
    let validStart = coupon.start_time || new Date()
    let validEnd = coupon.end_time
    if (!validEnd && coupon.valid_days > 0) {
      const d = new Date()
      d.setDate(d.getDate() + coupon.valid_days)
      validEnd = d
    }

    // 写入用户优惠券
    const [result] = await conn.query(`
      INSERT INTO user_admin_coupons (user_id, coupon_id, coupon_name, type, money, min_amount, discount_rate, shipping_fee, valid_start, valid_end)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, [req.user.id, couponId, coupon.name, coupon.type, coupon.money, coupon.min_amount, coupon.discount_rate, coupon.shipping_fee, validStart, validEnd])

    await conn.commit()
    res.json({ code: 0, data: { user_coupon_id: result.insertId }, message: '领取成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ─── 后台管理 API ────────────────────────────────────────────────────────────

// GET /api/coupon/admin — 优惠券列表（后台）
router.get('/admin', auth, async (req, res, next) => {
  try {
    const { status, keyword, page = 1, size = 20 } = req.query
    const { offset } = parsePagination(req.query)

    let where = 'WHERE 1=1'
    const params = []
    if (status) { where += ' AND ac.status = ?'; params.push(status) }
    if (keyword) { where += ' AND ac.name LIKE ?'; params.push(`%${keyword}%`) }

    const [rows] = await dbPool.query(`
      SELECT ac.*, u.name as creator_name
      FROM admin_coupons ac
      LEFT JOIN users u ON ac.created_by = u.id
      ${where}
      ORDER BY ac.id DESC
      LIMIT ? OFFSET ?
    `, [...params, Number(size), Number(offset)])

    const [[{ cnt }]] = await dbPool.query(
      `SELECT COUNT(*) as cnt FROM admin_coupons ac ${where}`, params
    )

    res.json({ code: 0, data: { list: rows, total: cnt, page: Number(page), size: Number(size) }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/coupon/admin — 新增优惠券
router.post('/admin', auth, async (req, res, next) => {
  try {
    const {
      name, type = 'cash', money, min_amount = 0, discount_rate,
      shipping_fee = 0, total_count, remain_count, per_limit = 1,
      start_time, end_time, valid_days = 30, apply_all = true,
      product_ids, category_ids, status = 'active'
    } = req.body

    if (!name || total_count == null) {
      return res.status(400).json({ code: 400, message: 'name 和 total_count 必填' })
    }

    const productIdsJson = product_ids ? JSON.stringify(product_ids) : null
    const categoryIdsJson = category_ids ? JSON.stringify(category_ids) : null

    const [result] = await dbPool.query(`
      INSERT INTO admin_coupons (name, type, money, min_amount, discount_rate, shipping_fee, total_count, remain_count, per_limit, start_time, end_time, valid_days, apply_all, product_ids, category_ids, status, created_by)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, [name, type, money || null, min_amount, discount_rate || null, shipping_fee, total_count, total_count, per_limit, start_time || null, end_time || null, valid_days, apply_all ? 1 : 0, productIdsJson, categoryIdsJson, status, req.user.id])

    res.json({ code: 0, data: { id: result.insertId }, message: '创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/coupon/admin/:id — 更新优惠券
router.put('/admin/:id', auth, async (req, res, next) => {
  try {
    const { name, type, money, min_amount, discount_rate, shipping_fee, total_count, remain_count, per_limit, start_time, end_time, valid_days, apply_all, product_ids, category_ids, status } = req.body
    const fields = [], vals = []
    const colMap = { name, type, money, min_amount, discount_rate, shipping_fee, total_count, remain_count, per_limit, start_time, end_time, valid_days, apply_all, product_ids, category_ids, status }
    for (const [k, v] of Object.entries(colMap)) {
      if (v !== undefined) {
        fields.push(`${k}=?`)
        vals.push(k === 'apply_all' ? (v ? 1 : 0) : (['product_ids', 'category_ids'].includes(k) && Array.isArray(v) ? JSON.stringify(v) : v))
      }
    }
    if (!fields.length) return res.status(400).json({ code: 400, message: '没有更新字段' })
    vals.push(req.params.id)
    await dbPool.query(`UPDATE admin_coupons SET ${fields.join(',')} WHERE id = ?`, vals)
    res.json({ code: 0, message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/coupon/admin/:id — 删除优惠券
router.delete('/admin/:id', auth, async (req, res, next) => {
  try {
    // 检查是否有用户已领取
    const [[{ cnt }]] = await dbPool.query(
      'SELECT COUNT(*) as cnt FROM user_admin_coupons WHERE coupon_id = ?',
      [req.params.id]
    )
    if (cnt > 0) {
      // 软删除：设为 inactive
      await dbPool.query('UPDATE admin_coupons SET status = "inactive" WHERE id = ?', [req.params.id])
      return res.json({ code: 0, message: '该优惠券已有用户领取，已设为无效' })
    }
    await dbPool.query('DELETE FROM admin_coupons WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

// GET /api/coupon/admin/users/:id — 查看某优惠券被领取的用户列表
router.get('/admin/users/:id', auth, async (req, res, next) => {
  try {
    const { page = 1, size = 20 } = req.query
    const { offset } = parsePagination(req.query)

    const [rows] = await dbPool.query(`
      SELECT uc.id as user_coupon_id, uc.status, uc.used_at, uc.received_at, uc.valid_start, uc.valid_end,
             u.id as uid, u.name as user_name, u.phone as user_phone
      FROM user_admin_coupons uc
      JOIN users u ON uc.user_id = u.id
      WHERE uc.coupon_id = ?
      ORDER BY uc.received_at DESC
      LIMIT ? OFFSET ?
    `, [req.params.id, Number(size), Number(offset)])

    const [[{ cnt }]] = await dbPool.query(
      'SELECT COUNT(*) as cnt FROM user_admin_coupons WHERE coupon_id = ?',
      [req.params.id]
    )

    res.json({ code: 0, data: { list: rows, total: cnt, page: Number(page), size: Number(size) }, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/coupon/admin/:id/reward — 奖励发放优惠券给用户（管理员手动发放）
router.post('/admin/:id/reward', auth, async (req, res, next) => {
  const conn = await dbPool.getConnection()
  try {
    const { user_ids } = req.body
    if (!user_ids || !Array.isArray(user_ids) || !user_ids.length) {
      return res.status(400).json({ code: 400, message: 'user_ids 必填（数组）' })
    }

    const [[coupon]] = await conn.query('SELECT * FROM admin_coupons WHERE id = ? AND status = "active"', [req.params.id])
    if (!coupon) return res.status(404).json({ code: 404, message: '优惠券不存在' })

    if (coupon.remain_count < user_ids.length) {
      return res.status(400).json({ code: 400, message: `剩余${coupon.remain_count}张，不足以发放给${user_ids.length}人` })
    }

    await conn.beginTransaction()
    await conn.query('UPDATE admin_coupons SET remain_count = remain_count - ? WHERE id = ?', [user_ids.length, req.params.id])

    let validStart = coupon.start_time || new Date()
    let validEnd = coupon.end_time
    if (!validEnd && coupon.valid_days > 0) {
      const d = new Date()
      d.setDate(d.getDate() + coupon.valid_days)
      validEnd = d
    }

    for (const userId of user_ids) {
      await conn.query(`
        INSERT INTO user_admin_coupons (user_id, coupon_id, coupon_name, type, money, min_amount, discount_rate, shipping_fee, valid_start, valid_end)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      `, [userId, req.params.id, coupon.name, coupon.type, coupon.money, coupon.min_amount, coupon.discount_rate, coupon.shipping_fee, validStart, validEnd])
    }

    await conn.commit()
    res.json({ code: 0, message: `成功发放给${user_ids.length}人` })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

export default router
