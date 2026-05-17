import { Router } from 'express'
import { pool } from '../db/connection.js'
import { h5Auth } from '../middleware/h5Auth.js'
import { createNativeOrder, getWechatPayConfig } from '../utils/wechat_pay.js'

const router = Router()

// GET /api/scan/:code - Get QR code details (support ref parameter for referral tracking)
router.get('/:code', async (req, res, next) => {
  try {
    const { code } = req.params
    const { ref } = req.query

    // Store referral code in cookie if provided (7 days)
    if (ref && /^\d+$/.test(ref)) {
      res.cookie('gdq_ref', ref, { maxAge: 7 * 24 * 60 * 60 * 1000, httpOnly: true })

      // Update qrcodes.referrer_h5_user_id if not already set
      const [[qr]] = await pool.query('SELECT id, referrer_h5_user_id FROM qrcodes WHERE code = ?', [code])
      if (qr && !qr.referrer_h5_user_id) {
        await pool.query('UPDATE qrcodes SET referrer_h5_user_id = ? WHERE id = ?', [parseInt(ref), qr.id])
      }
    }

    const [[qr]] = await pool.query(`
      SELECT
        q.id, q.code, q.status, q.product_id, q.bound_at,
        q.supplier_id, q.dealer_id, q.store_id, q.buyer, q.buyer_phone, q.buy_date,
        q.warranty_end, q.sales_person, q.after_sale_contact, q.scan_count,
        q.referrer_h5_user_id, q.warranty_period, q.warranty_unit,
        p.name as product_name, p.sku, p.spec, p.image_main, p.external_links, p.category_id, p.group_qr_url, p.group_qr_type,
        s.name as supplier_name, d.name as dealer_name, st.name as store_name,
        c.name as category,
        h5.name as referrer_name, h5.phone as referrer_phone
      FROM qrcodes q
      LEFT JOIN products p ON q.product_id = p.id
      LEFT JOIN suppliers s ON q.supplier_id = s.id
      LEFT JOIN dealers d ON q.dealer_id = d.id
      LEFT JOIN stores st ON q.store_id = st.id
      LEFT JOIN categories c ON p.category_id = c.id
      LEFT JOIN h5_users h5 ON q.referrer_h5_user_id = h5.id
      WHERE q.code = ?
    `, [code])

    if (!qr) return res.status(404).json({ code: 404, message: '二维码不存在' })

    // Increment scan count
    await pool.query('UPDATE qrcodes SET scan_count = scan_count + 1 WHERE id = ?', [qr.id])

    // Get repair records
    const [repairRecords] = await pool.query(
      'SELECT repair_person, issue, solution, repaired_at FROM repair_records WHERE qrcode_id = ? ORDER BY repaired_at DESC LIMIT 20',
      [qr.id]
    )

    res.json({
      code: 0,
      data: {
        ...qr,
        image_url: qr.image_main || '/default-product.png',
        // Only show group QR when product is sold
        group_qr_url: qr.status === 'sold' ? qr.group_qr_url : null,
        group_qr_type: qr.status === 'sold' ? qr.group_qr_type : null,
        repairRecords
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// GET /api/scan/:code/my-aftersale - Get my after-sale records (requires H5 auth)
router.get('/:code/my-aftersale', h5Auth, async (req, res, next) => {
  try {
    const { code } = req.params

    // Get qrcode info
    const [[qr]] = await pool.query('SELECT id, buyer_phone FROM qrcodes WHERE code = ?', [code])
    if (!qr) return res.status(404).json({ code: 404, message: '二维码不存在' })

    // Check if current user is the buyer (match phone)
    const isBuyer = qr.buyer_phone === req.h5user.phone

    if (!isBuyer) {
      return res.json({ code: 0, data: { isBuyer: false, records: [] }, message: 'ok' })
    }

    // Get after-sale records for this buyer with handler info
    const [records] = await pool.query(`
      SELECT
        asr.id, asr.issue, asr.status, asr.created_at, asr.resolved_at,
        asr.handler, asr.handler_note, asr.images,
        u.name as assigned_to_name, u.email as assigned_to_email,
        handler_user.name as handler_name, handler_user.email as handler_email
      FROM after_sale_records asr
      LEFT JOIN users u ON asr.assigned_to = u.id
      LEFT JOIN users handler_user ON asr.handler = handler_user.name
      WHERE asr.qrcode_id = ? AND asr.contact_phone = ?
      ORDER BY asr.created_at DESC
    `, [qr.id, req.h5user.phone])

    res.json({
      code: 0,
      data: {
        isBuyer: true,
        records
      },
      message: 'ok'
    })
  } catch (err) { next(err) }
})

// POST /api/scan/:code/share - Generate share data for QR code
router.post('/:code/share', async (req, res, next) => {
  try {
    const { code } = req.params

    // Get QR code and product details
    const [[qr]] = await pool.query(`
      SELECT
        q.id, q.code, q.status,
        p.id as product_id, p.name as product_name, p.sku, p.spec, p.image_main, p.external_links,
        s.name as supplier_name, d.name as dealer_name, st.name as store_name
      FROM qrcodes q
      LEFT JOIN products p ON q.product_id = p.id
      LEFT JOIN suppliers s ON q.supplier_id = s.id
      LEFT JOIN dealers d ON q.dealer_id = d.id
      LEFT JOIN stores st ON q.store_id = st.id
      WHERE q.code = ?
    `, [code])

    if (!qr) return res.status(404).json({ code: 404, message: '二维码不存在' })

    // Parse external links if available
    let externalLinks = []
    if (qr.external_links) {
      try {
        externalLinks = JSON.parse(qr.external_links)
      } catch (e) {
        // Ignore parse errors
      }
    }

    // Generate share data
    const shareData = {
      title: qr.product_name || '商品详情',
      description: qr.spec || '扫码查看商品详情',
      imageUrl: qr.image_main || '/default-product.png',
      qrCode: qr.code,
      qrImageUrl: `/api/qrcode/image/${qr.code}`, // QR code image endpoint
      productInfo: {
        name: qr.product_name,
        sku: qr.sku,
        spec: qr.spec,
        supplier: qr.supplier_name,
        dealer: qr.dealer_name,
        store: qr.store_name
      },
      externalLinks,
      // Referral link with current user's ID (if authenticated)
      referralLink: `/h5/scan/${qr.code}`,
      shareUrl: `${req.protocol}://${req.get('host')}/h5/scan/${qr.code}`
    }

    res.json({
      code: 0,
      data: shareData,
      message: 'ok'
    })
  } catch (err) { next(err) }
})


// ─── Sales Order APIs ─────────────────────────────────────────────────────────

// POST /api/scan/:code/buy - Create sales order and initiate WeChat pay
router.post('/:code/buy', async (req, res, next) => {
  try {
    const { code } = req.params
    const { sale_price, buyer_phone, buyer_id } = req.body

    if (!sale_price || !buyer_phone) {
      return res.status(400).json({ code: 400, message: '缺少必要参数' })
    }

    // Get QR code and product info
    const [[qr]] = await pool.query(`
      SELECT q.id, q.product_id, q.status, p.name as product_name, p.sale_price as retail_price
      FROM qrcodes q
      LEFT JOIN products p ON q.product_id = p.id
      WHERE q.code = ?
    `, [code])

    if (!qr) return res.status(404).json({ code: 404, message: '二维码不存在' })

    // Check if product can be sold (not already sold)
    if (qr.status === 'sold' || qr.status === 'shipped') {
      return res.status(400).json({ code: 400, message: '该商品已售出' })
    }

    // Generate order number
    const orderNo = 'ORD' + Date.now() + Math.random().toString(36).slice(2, 6).toUpperCase()
    const discountRate = qr.retail_price ? (sale_price / qr.retail_price) : 1

    // Create order
    const [result] = await pool.query(`
      INSERT INTO sales_orders 
      (order_no, qrcode_id, qrcode_code, product_id, product_name, buyer_phone, buyer_id, 
       original_price, sale_price, discount_rate, status)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_pay')
    `, [orderNo, qr.id, code, qr.product_id, qr.product_name, buyer_phone, buyer_id || null,
        qr.retail_price || sale_price, sale_price, discountRate])

    // Try to initiate WeChat pay
    let wechatPay = null
    try {
      const wechatPayConfig = await getWechatPayConfig(pool)
      if (wechatPayConfig) {
        wechatPay = await createNativeOrder(pool, {
          orderNo,
          amount: sale_price,
          description: qr.product_name || '商品购买',
          notifyUrl: 'https://claw.gdqshop.cn/api/pay/wechat/callback'
        })
      }
    } catch (payErr) {
      console.error('[WeChat Pay Error]', payErr.message)
      // Continue without wechat pay
    }

    res.json({
      code: 0,
      data: {
        order_id: result.insertId,
        order_no: orderNo,
        sale_price: sale_price,
        product_name: qr.product_name,
        wechat_pay: wechatPay ? {
          code_url: wechatPay.code_url,
          prepay_id: wechatPay.prepay_id
        } : null
      },
      message: wechatPay ? '订单创建成功，请扫码支付' : '订单创建成功（微信支付未配置）'
    })
  } catch (err) { next(err) }
})

// GET /api/scan/orders/:orderNo - Get order by order number
router.get('/orders/:orderNo', async (req, res, next) => {
  try {
    const [[order]] = await pool.query(
      'SELECT * FROM sales_orders WHERE order_no = ?',
      [req.params.orderNo]
    )

    if (!order) return res.status(404).json({ code: 404, message: '订单不存在' })

    res.json({ code: 0, data: order, message: 'ok' })
  } catch (err) { next(err) }
})

// GET /api/scan/orders/buyer/:phone - Get orders by buyer phone
router.get('/orders/buyer/:phone', async (req, res, next) => {
  try {
    const [orders] = await pool.query(
      'SELECT * FROM sales_orders WHERE buyer_phone = ? ORDER BY created_at DESC',
      [req.params.phone]
    )

    res.json({ code: 0, data: orders, message: 'ok' })
  } catch (err) { next(err) }
})



// ─── WeChat Pay Callback ──────────────────────────────────────────────────────

// POST /api/pay/wechat/callback - 微信支付回调
router.post('/pay/wechat/callback', async (req, res, next) => {
  try {
    const xml = req.body
    if (!xml || xml.return_code !== 'SUCCESS') {
      return res.send('<xml><return_code><![CDATA[FAIL]]></return_code></xml>')
    }

    const [[config]] = await pool.query(
      'SELECT api_key FROM wechat_pay_config WHERE status = "active" LIMIT 1'
    )
    
    if (!config) {
      return res.send('<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[未配置支付]]></return_msg></xml>')
    }

    // 验证签名
    const receivedSign = xml.sign
    const { sign, ...rest } = xml
    const { sign: _, ...params } = xml
    // TODO: 实现签名验证
    // const calculatedSign = sign(params, config.api_key)
    // if (calculatedSign !== receivedSign) {
    //   return res.send('<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名验证失败]]></return_msg></xml>')
    // }

    // 处理支付结果
    const orderNo = xml.out_trade_no
    const transactionId = xml.transaction_id

    // 更新订单状态
    if (xml.result_code === 'SUCCESS') {
      await pool.query(
        'UPDATE sales_orders SET status = "paid", wechat_trade_no = ?, paid_at = NOW() WHERE order_no = ?',
        [transactionId, orderNo]
      )
    }

    res.send('<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>')
  } catch (err) {
    console.error('[WeChat Pay Callback Error]', err)
    res.send('<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[系统错误]]></return_msg></xml>')
  }
})


export default router
