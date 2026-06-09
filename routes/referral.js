import { Router } from 'express'
import { pool } from '../db/connection.js'
import { h5Auth } from '../middleware/h5Auth.js'
import jwt from 'jsonwebtoken'
import crypto from 'crypto'

const router = Router()

// ─── 桌码管理 (Admin) ───────────────────────────────────────────────────────

// GET /api/referral/tables - 桌码列表
router.get('/tables', async (req, res, next) => {
  try {
    const [tables] = await pool.query(
      'SELECT * FROM restaurant_tables ORDER BY table_no'
    )
    res.json({ code: 0, data: tables, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/referral/tables - 新建桌码
router.post('/tables', async (req, res, next) => {
  try {
    const { table_no, table_name } = req.body
    if (!table_no) return res.status(400).json({ code: 400, message: '桌号必填' })

    const token = crypto.randomBytes(32).toString('hex')
    const [result] = await pool.query(
      'INSERT INTO restaurant_tables (table_no, table_name, qr_token) VALUES (?, ?, ?)',
      [table_no, table_name || table_no, token]
    )

    // 生成二维码URL（前端根据token自行生成）
    const qr_url = `/api/referral/qr/${token}`

    res.json({ code: 0, data: { id: result.insertId, table_no, table_name, qr_token: token, qr_url }, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE /api/referral/tables/:id
router.delete('/tables/:id', async (req, res, next) => {
  try {
    await pool.query('DELETE FROM restaurant_tables WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

// ─── 奖励规则管理 (Admin) ───────────────────────────────────────────────────

// GET /api/referral/rewards - 奖励规则列表
router.get('/rewards', async (req, res, next) => {
  try {
    const [rules] = await pool.query(`
      SELECT r.*, c.name as coupon_name, c.money as coupon_money
      FROM referral_rewards r
      LEFT JOIN coupons c ON r.reward_coupon_id = c.id
      ORDER BY r.created_at DESC
    `)
    res.json({ code: 0, data: rules, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/referral/rewards - 新建奖励规则
router.post('/rewards', async (req, res, next) => {
  try {
    const { product_id, product_name, required_heads, reward_type, reward_coupon_id, reward_amount, reward_desc, valid_days = 30 } = req.body
    if (!required_heads) return res.status(400).json({ code: 400, message: '人头数必填' })

    const [result] = await pool.query(
      `INSERT INTO referral_rewards (product_id, product_name, required_heads, reward_type, reward_coupon_id, reward_amount, reward_desc, valid_days)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [product_id || null, product_name || null, required_heads, reward_type || 'coupon', reward_coupon_id || null, reward_amount || null, reward_desc || null, valid_days]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE /api/referral/rewards/:id
router.delete('/rewards/:id', async (req, res, next) => {
  try {
    await pool.query('DELETE FROM referral_rewards WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

// ─── 消费券管理 (Admin) ──────────────────────────────────────────────────────

// GET /api/referral/coupons - 消费券列表
router.get('/coupons', async (req, res, next) => {
  try {
    const [coupons] = await pool.query('SELECT * FROM coupons ORDER BY created_at DESC')
    res.json({ code: 0, data: coupons, message: 'ok' })
  } catch (err) { next(err) }
})

// POST /api/referral/coupons - 新建消费券
router.post('/coupons', async (req, res, next) => {
  try {
    const {
      name, type = 'cash', money, min_price = 0, discount_rate,
      product_id, product_name, start_time, end_time, valid_days = 30,
      stock = -1, per_limit = 1, apply_all = 1, product_ids, category_ids
    } = req.body

    if (!name) return res.status(400).json({ code: 400, message: '券名称必填' })

    const [result] = await pool.query(
      `INSERT INTO coupons (name, type, money, min_price, discount_rate, product_id, product_name, start_time, end_time, valid_days, stock, per_limit, apply_all, product_ids, category_ids)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [name, type, money || null, min_price, discount_rate || null, product_id || null, product_name || null, start_time || null, end_time || null, valid_days, stock, per_limit, apply_all, JSON.stringify(product_ids || null), JSON.stringify(category_ids || null)]
    )
    res.json({ code: 0, data: { id: result.insertId }, message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE /api/referral/coupons/:id
router.delete('/coupons/:id', async (req, res, next) => {
  try {
    await pool.query('DELETE FROM coupons WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

// GET /api/referral/coupons/:id - 消费券详情
router.get('/coupons/:id', async (req, res, next) => {
  try {
    const [[coupon]] = await pool.query('SELECT * FROM coupons WHERE id = ?', [req.params.id])
    if (!coupon) return res.status(404).json({ code: 404, message: '券不存在' })
    res.json({ code: 0, data: coupon, message: 'ok' })
  } catch (err) { next(err) }
})

// ─── H5 扫码接口 ─────────────────────────────────────────────────────────────

// GET /api/referral/scan/:token - 扫码绑定推荐关系
router.get('/scan/:token', async (req, res, next) => {
  try {
    const { token } = req.params
    const { ref } = req.query   // ref = 推荐人h5_user_id

    // 查找桌码
    const [[table]] = await pool.query(
      'SELECT * FROM restaurant_tables WHERE qr_token = ?',
      [token]
    )
    if (!table) return res.status(404).json({ code: 404, message: '桌码不存在' })

    // 记录扫码（不管有没有推荐人）
    const [[existing]] = await pool.query(
      'SELECT id FROM referral_records WHERE qr_token = ? AND invited_phone = ?',
      [token, req.query.phone || null]
    )

    if (!existing) {
      await pool.query(
        `INSERT INTO referral_records (table_id, referrer_h5_user_id, invited_phone, invited_name, qr_token, status)
         VALUES (?, ?, ?, ?, ?, 'scanned')`,
        [table.id, ref || null, req.query.phone || null, req.query.name || null, token]
      )
    }

    res.json({
      code: 0,
      data: {
        table_id: table.id,
        table_no: table.table_no,
        table_name: table.table_name,
        token,
        share_url: `https://wecom.gdqshop.cn/h5/table/${token}?ref=${ref || ''}`
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// ─── 订单完成触发奖励 ────────────────────────────────────────────────────────

// POST /api/referral/order-complete - 订单完成后调用（由订单模块在支付成功后调用）
router.post('/order-complete', async (req, res, next) => {
  try {
    const { order_no, member_id, member_phone, total_amount } = req.body
    if (!order_no) return res.status(400).json({ code: 400, message: '订单号必填' })

    // 查找该订单关联的推荐记录（待奖励状态）
    const [records] = await pool.query(
      `SELECT rr.*, rt.table_no, rt.table_name,
              hu.name as referrer_name, hu.phone as referrer_phone
       FROM referral_records rr
       JOIN restaurant_tables rt ON rr.table_id = rt.id
       LEFT JOIN h5_users hu ON rr.referrer_h5_user_id = hu.id
       WHERE rr.invited_phone = ? AND rr.status IN ('scanned')
       ORDER BY rr.created_at ASC`,
      [member_phone]
    )

    if (records.length === 0) {
      return res.json({ code: 0, data: { rewarded: false, reason: '无非推荐记录' }, message: 'ok' })
    }

    const rewarded_list = []

    for (const rr of records) {
      // 更新记录为已消费
      await pool.query(
        `UPDATE referral_records SET status = 'paid', order_id = ?, order_no = ?, order_amount = ?, paid_at = NOW()
         WHERE id = ?`,
        [null, order_no, total_amount, rr.id]
      )

      // 检查是否达到奖励条件
      // 1. 查找该桌码所有已支付的推荐记录
      const [[summary]] = await pool.query(
        `SELECT COUNT(*) as paid_count FROM referral_records
         WHERE referrer_h5_user_id = ? AND table_id = ? AND status IN ('paid','rewarded')`,
        [rr.referrer_h5_user_id, rr.table_id]
      )

      // 2. 查找奖励规则（优先商品专属，兜底通用）
      const [[rule]] = await pool.query(
        `SELECT * FROM referral_rewards
         WHERE (product_id IS NULL OR product_id = ?)
           AND status = 'active'
         ORDER BY product_id DESC NULLS LAST
         LIMIT 1`,
        [null]
      )

      if (!rule) continue

      const paidCount = summary?.paid_count || 0
      if (paidCount < rule.required_heads) {
        // 人头不够，继续累计
        continue
      }

      // 达到人头，发放奖励
      if (rule.reward_type === 'coupon' && rule.reward_coupon_id) {
        // 发放优惠券
        const [[coupon]] = await pool.query('SELECT * FROM coupons WHERE id = ?', [rule.reward_coupon_id])
        if (coupon) {
          const validEnd = new Date()
          validEnd.setDate(validEnd.getDate() + (coupon.valid_days || 30))

          await pool.query(
            `INSERT INTO user_coupons (user_id, coupon_id, coupon_name, type, money, min_price, discount_rate, product_id, product_name, valid_start, valid_end, source)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'reward')`,
            [rr.referrer_h5_user_id, coupon.id, coupon.name, coupon.type, coupon.money, coupon.min_price, coupon.discount_rate, coupon.product_id, coupon.product_name, validEnd]
          )

          // 更新推荐记录为已奖励
          await pool.query(
            `UPDATE referral_records SET status = 'rewarded', reward_given = 1, reward_id = ? WHERE id = ?`,
            [coupon.id, rr.id]
          )

          rewarded_list.push({
            referrer: rr.referrer_name,
            coupon: coupon.name,
            table_no: rr.table_no
          })
        }
      } else if (rule.reward_type === 'score' && rule.reward_amount) {
        // 发放积分（直接加到h5_users）
        await pool.query(
          'UPDATE h5_users SET score = score + ? WHERE id = ?',
          [rule.reward_amount, rr.referrer_h5_user_id]
        )
        rewarded_list.push({
          referrer: rr.referrer_name,
          score: rule.reward_amount,
          table_no: rr.table_no
        })
      }
    }

    res.json({ code: 0, data: { rewarded: rewarded_list.length > 0, list: rewarded_list }, message: 'ok' })
  } catch (err) { next(err) }
})

// ─── H5 用户接口 ─────────────────────────────────────────────────────────────

// GET /api/referral/my-coupons - 我的优惠券（H5已登录）
router.get('/my-coupons', h5Auth, async (req, res, next) => {
  try {
    const [coupons] = await pool.query(
      `SELECT * FROM user_coupons
       WHERE user_id = ?
       ORDER BY status = 'unused' DESC, valid_end ASC`,
      [req.h5user.id]
    )
    res.json({ code: 0, data: coupons, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/referral/my-referrals - 我的推荐记录（H5已登录）
router.get('/my-referrals', h5Auth, async (req, res, next) => {
  try {
    const [records] = await pool.query(
      `SELECT rr.*, rt.table_no, rt.table_name
       FROM referral_records rr
       JOIN restaurant_tables rt ON rr.table_id = rt.id
       WHERE rr.referrer_h5_user_id = ?
       ORDER BY rr.created_at DESC`,
      [req.h5user.id]
    )
    res.json({ code: 0, data: records, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/referral/summary - 推荐汇总（H5已登录）
router.get('/summary', h5Auth, async (req, res, next) => {
  try {
    const [[row]] = await pool.query(
      `SELECT
         COUNT(*) as total,
         SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
         SUM(CASE WHEN status = 'rewarded' THEN 1 ELSE 0 END) as rewarded
       FROM referral_records WHERE referrer_h5_user_id = ?`,
      [req.h5user.id]
    )
    const [[couponRow]] = await pool.query(
      'SELECT COUNT(*) as unused FROM user_coupons WHERE user_id = ? AND status = ? AND valid_end > NOW()',
      [req.h5user.id, 'unused']
    )
    res.json({
      code: 0,
      data: {
        total: row.total || 0,
        paid: row.paid || 0,
        rewarded: row.rewarded || 0,
        unused_coupons: couponRow?.unused || 0
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

export default router