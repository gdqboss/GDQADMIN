// qrcode-customer.js — 一物一码扫码购买（C 端公开 + 后台管理）
// 2026-08-14 — 个人收款码模式
// C 端：扫码查商品 + 提交"我已付款"订单；后台：看 pending 列表 + 手动确认/取消
// 复用已有 qrcodes 表的销售逻辑（POST /api/qrcodes/:id/sell）

import { Router } from 'express'
import { pool } from '../db/connection.js'

const router = Router()

// ─────────────────────────────────────────────────────────────
// C 端公开接口（无需登录）
// ─────────────────────────────────────────────────────────────

// GET /api/qrcode-customer/scan/:code — 扫码查询（公开）
// 返回：商品信息 + 全局收款码配置 + 状态
router.get('/scan/:code', async (req, res, next) => {
  try {
    const code = req.params.code
    // 扫码 +1
    const [[qr]] = await pool.query(
      `SELECT q.id, q.code, q.status, q.batch_mode, q.batch_quantity, q.remaining_qty,
              q.product_id, q.sku_id, q.scan_count,
              p.name as product_name, p.sku as product_sku, p.spec, p.sale_price, p.image_main, p.images, p.category
       FROM qrcodes q
       LEFT JOIN products p ON q.product_id = p.id
       WHERE q.code = ?`,
      [code]
    )
    if (!qr) return res.status(404).json({ code: 404, message: '二维码不存在' })

    // 扫码统计
    pool.query('UPDATE qrcodes SET scan_count = scan_count + 1 WHERE id = ?', [qr.id]).catch(() => {})

    // 全局收款码配置（公开只暴露必要字段）
    const [[cfg]] = await pool.query(
      'SELECT wechat_qr_image, alipay_qr_image, merchant_name, merchant_phone, default_amount, enabled FROM qrcode_pay_config WHERE id = 1'
    )

    // 状态可售性判断
    const sellableStatuses = ['bindProduct', 'inStock', 'shipped', 'sold']
    const isSellable = sellableStatuses.includes(qr.status) && (qr.remaining_qty || 1) > 0

    res.json({
      code: 0,
      data: {
        qrcode: {
          id: qr.id,
          code: qr.code,
          status: qr.status,
          scan_count: qr.scan_count,
          batch_mode: qr.batch_mode,
          remaining_qty: qr.remaining_qty || 1,
          batch_quantity: qr.batch_quantity || 1,
        },
        product: qr.product_id ? {
          id: qr.product_id,
          name: qr.product_name,
          sku: qr.product_sku,
          spec: qr.spec,
          image_main: qr.image_main,
          images: qr.images ? (typeof qr.images === 'string' ? JSON.parse(qr.images) : qr.images) : [],
          category: qr.category,
          sale_price: qr.sale_price,
        } : null,
        pay_config: cfg && cfg.enabled ? {
          wechat_qr_image: cfg.wechat_qr_image,
          alipay_qr_image: cfg.alipay_qr_image,
          merchant_name: cfg.merchant_name || '智能商业系统官方',
          merchant_phone: cfg.merchant_phone || null,
          default_amount: cfg.default_amount || null,
        } : null,
        is_sellable: isSellable,
      },
    })
  } catch (err) { next(err) }
})

// POST /api/qrcode-customer/orders — 顾客提交"我已付款"订单（公开）
// body: { code, amount, quantity?, pay_method, customer_note? }
router.post('/orders', async (req, res, next) => {
  try {
    const { code, amount, quantity = 1, pay_method, customer_note } = req.body
    if (!code) return res.status(400).json({ code: 400, message: '请提供二维码 code' })
    if (!amount || isNaN(parseFloat(amount)) || parseFloat(amount) <= 0) {
      return res.status(400).json({ code: 400, message: '请填写正确的付款金额' })
    }
    if (!['wechat', 'alipay'].includes(pay_method)) {
      return res.status(400).json({ code: 400, message: '支付方式仅支持 wechat 或 alipay' })
    }

    // 查找二维码
    const [[qr]] = await pool.query(
      'SELECT id, code, status, remaining_qty, batch_mode FROM qrcodes WHERE code = ?',
      [code]
    )
    if (!qr) return res.status(404).json({ code: 404, message: '二维码不存在' })

    // 状态校验
    const sellableStatuses = ['bindProduct', 'inStock', 'shipped']
    if (!sellableStatuses.includes(qr.status)) {
      return res.status(400).json({ code: 400, message: '该商品当前不可购买' })
    }

    // 批次校验
    const qty = Math.max(1, parseInt(quantity) || 1)
    if (qr.batch_mode === 'batch' && qr.remaining_qty < qty) {
      return res.status(400).json({ code: 400, message: `库存不足，仅剩 ${qr.remaining_qty} 件` })
    }

    // 生成订单号
    const orderNo = `QCO${Date.now()}${Math.floor(Math.random() * 1000).toString().padStart(3, '0')}`

    // 插入
    const [result] = await pool.query(
      `INSERT INTO qrcode_customer_orders
       (order_no, qrcode_id, qrcode_code, customer_amount, quantity, pay_method, status, customer_note, customer_ip, user_agent, created_at)
       VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())`,
      [orderNo, qr.id, qr.code, parseFloat(amount), qty, pay_method,
       customer_note || null,
       req.ip || req.headers['x-forwarded-for'] || null,
       (req.headers['user-agent'] || '').slice(0, 500)]
    )

    res.json({
      code: 0,
      data: {
        id: result.insertId,
        order_no: orderNo,
        status: 'pending',
        message: '已收到您的付款申请，请等待商家确认',
      },
      message: 'ok',
    })
  } catch (err) { next(err) }
})

// ─────────────────────────────────────────────────────────────
// 后台管理接口已拆分到 qrcode-customer-admin.js
// ─────────────────────────────────────────────────────────────

export default router
