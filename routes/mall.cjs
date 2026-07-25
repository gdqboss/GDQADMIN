/**
 * 商城后端路由 - mall.js
 * 包含：商品/分类/购物车/订单/地址/优惠券/用户 API
 * 表结构基于 init.sql
 */
const { Router } = require('express')
const bcrypt = require('bcryptjs')

// pool 优先从 connection.js 导入，失败则用本地 mysql2/promise
let pool
try {
  ;({ pool } = require('../db/connection.js'))
} catch {
  const mysql = require('mysql2/promise')
  pool = mysql.createPool({
    host: 'localhost',
    user: 'gdq',
    password: 'Re78g0A1XcNmr1T8',
    database: 'gdq',
    socketPath: '/run/mysqld/mysqld.sock',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
  })
}

const router = Router()

// ─── 商城配置 ───────────────────────────────────────────────────────────────

// GET /api/mall/config
router.get('/config', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM store_config WHERE id=1')
    res.json(rows[0] || { shop_name: 'TRAVELMATE STORE', enabled: 1 })
  } catch (err) { next(err) }
})

// PUT /api/mall/config
router.put('/config', async (req, res, next) => {
  try {
    const { shop_name, logo, slogan, contact_phone, notice } = req.body
    const fields = [], vals = []
    if (shop_name !== undefined) { fields.push('shop_name=?'); vals.push(shop_name) }
    if (logo !== undefined) { fields.push('logo=?'); vals.push(logo) }
    if (slogan !== undefined) { fields.push('slogan=?'); vals.push(slogan) }
    if (contact_phone !== undefined) { fields.push('contact_phone=?'); vals.push(contact_phone) }
    if (notice !== undefined) { fields.push('notice=?'); vals.push(notice) }
    if (!fields.length) return res.status(400).json({ message: '没有更新字段' })
    vals.push(1)
    await pool.query(`UPDATE store_config SET ${fields.join(',')} WHERE id=?`, vals)
    res.json({ message: 'ok' })
  } catch (err) { next(err) }
})

// ─── 商品 API ───────────────────────────────────────────────────────────────

// GET /api/mall/products — 商品列表
router.get('/products', async (req, res, next) => {
  try {
    const { category_id, keyword, page = 1, size = 20, status = 'active' } = req.query
    const offset = (page - 1) * size
    let where = 'WHERE p.status=?'
    const params = [status]

    if (category_id && Number(category_id) > 0) {
      // 递归查询子分类
      const [catRows] = await pool.query(`
        WITH RECURSIVE subtree AS (
          SELECT id FROM categories WHERE id = ?
          UNION ALL
          SELECT c.id FROM categories c JOIN subtree s ON c.parent_id = s.id
        )
        SELECT id FROM subtree
      `, [Number(category_id)])
      const catIds = catRows.map(r => r.id)
      if (catIds.length) {
        where += ` AND p.category_id IN (${catIds.map(() => '?').join(',')})`
        params.push(...catIds)
      }
    }
    if (keyword) {
      where += ' AND (p.name LIKE ? OR p.sku LIKE ?)'
      params.push(`%${keyword}%`, `%${keyword}%`)
    }

    const [rows] = await pool.query(`
      SELECT p.id, p.sku, p.name, p.category, p.category_id, p.spec, p.unit,
             p.supplier, p.purchase_price, p.sale_price, p.stock, p.alert_stock,
             p.image_main, p.images, p.status, p.created_at,
             c.name as category_name
      FROM products p
      LEFT JOIN categories c ON c.id = p.category_id
      ${where}
      ORDER BY p.id DESC
      LIMIT ? OFFSET ?
    `, [...params, Number(size), Number(offset)])

    const [[{ cnt }]] = await pool.query(
      `SELECT COUNT(*) as cnt FROM products p ${where}`, params
    )

    // 解析 images JSON
    for (const p of rows) {
      if (p.images) {
        try { p.images = JSON.parse(p.images) } catch { p.images = [] }
      } else { p.images = [] }
    }

    res.json({ list: rows, total: cnt, page: Number(page), size: Number(size) })
  } catch (err) { next(err) }
})

// GET /api/mall/products/:id — 商品详情
router.get('/products/:id', async (req, res, next) => {
  try {
    const [[p]] = await pool.query(`
      SELECT p.*, c.name as category_name
      FROM products p
      LEFT JOIN categories c ON c.id = p.category_id
      WHERE p.id = ?
    `, [req.params.id])

    if (!p) return res.status(404).json({ message: '商品不存在' })

    if (p.images) {
      try { p.images = JSON.parse(p.images) } catch { p.images = [] }
    } else { p.images = [] }

    // 加载 SKU 规格
    const [skus] = await pool.query(`
      SELECT id, sku, sku_key as \`key\`, specs, image,
             purchase_price, sale_price, stock, status
      FROM product_skus WHERE product_id = ? ORDER BY id
    `, [req.params.id])
    p.skus = skus

    res.json(p)
  } catch (err) { next(err) }
})

// GET /api/mall/products/:id/specs — 规格和SKU
router.get('/products/:id/specs', async (req, res, next) => {
  try {
    const pid = req.params.id
    const [specs] = await pool.query(
      'SELECT * FROM sku_names WHERE product_id = ? ORDER BY sort_order', [pid]
    )
    if (specs.length) {
      const sids = specs.map(s => s.id)
      const [values] = await pool.query(
        'SELECT * FROM sku_name_values WHERE spec_id IN (?) ORDER BY sort_order', [sids]
      )
      for (const s of specs) s.values = values.filter(v => v.spec_id === s.id)
    }
    const [skus] = await pool.query(`
      SELECT id, sku, sku_key as \`key\`, specs, image,
             purchase_price, sale_price, stock, status
      FROM product_skus WHERE product_id = ? ORDER BY id
    `, [pid])
    res.json({ specs, skus })
  } catch (err) { next(err) }
})

// ─── 分类 API ───────────────────────────────────────────────────────────────

// GET /api/mall/categories — 分类树
router.get('/categories', async (req, res, next) => {
  try {
    const [cats] = await pool.query(
      'SELECT id, name, parent_id, level, sort_order FROM categories ORDER BY level, sort_order, id'
    )
    const map = {}, roots = []
    cats.forEach(c => { map[c.id] = { ...c, children: [] } })
    cats.forEach(c => {
      if (c.parent_id && map[c.parent_id]) map[c.parent_id].children.push(map[c.id])
      else roots.push(map[c.id])
    })
    res.json(roots)
  } catch (err) { next(err) }
})

// GET /api/mall/categories/flat — 扁平分类列表
router.get('/categories/flat', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      'SELECT id, name, parent_id, level, sort_order FROM categories ORDER BY level, sort_order, id'
    )
    res.json(rows)
  } catch (err) { next(err) }
})

// ─── 购物车 API ─────────────────────────────────────────────────────────────

// GET /api/mall/cart — 获取用户购物车
router.get('/cart', async (req, res, next) => {
  try {
    const user_id = req.query.user_id || req.user?.id
    if (!user_id) return res.status(400).json({ message: 'user_id required' })

    const [items] = await pool.query(`
      SELECT ci.*, p.name as product_name, p.sku, p.image_main, p.sale_price,
             p.stock, p.status as product_status,
             ps.id as sku_id, ps.sku as sku_code, ps.sale_price as sku_price,
             ps.stock as sku_stock, ps.specs as sku_specs
      FROM cart_items ci
      LEFT JOIN products p ON ci.product_id = p.id
      LEFT JOIN product_skus ps ON ci.sku_id = ps.id
      WHERE ci.user_id = ?
      ORDER BY ci.id DESC
    `, [user_id])

    // 计算总金额
    let total_amount = 0
    for (const item of items) {
      if (item.sku_price) {
        item.subtotal = parseFloat(item.sku_price) * item.quantity
      } else {
        item.subtotal = parseFloat(item.sale_price || 0) * item.quantity
      }
      total_amount += item.subtotal
    }

    res.json({ list: items, total: total_amount, count: items.length })
  } catch (err) { next(err) }
})

// POST /api/mall/cart — 添加到购物车
router.post('/cart', async (req, res, next) => {
  try {
    const user_id = req.body.user_id || req.user?.id
    const { product_id, sku_id, quantity = 1 } = req.body
    if (!user_id || !product_id) return res.status(400).json({ message: 'user_id 和 product_id 必填' })
    if (quantity < 1) return res.status(400).json({ message: '数量至少为1' })

    // 检查商品是否存在
    const [[product]] = await pool.query('SELECT id, name, status FROM products WHERE id = ?', [product_id])
    if (!product) return res.status(404).json({ message: '商品不存在' })
    if (product.status !== 'active') return res.status(400).json({ message: '商品已下架' })

    // 检查是否已在购物车（同商品同规格）
    let where = 'user_id = ? AND product_id = ?'
    const params = [user_id, product_id]
    if (sku_id) {
      where += ' AND sku_id = ?'
      params.push(sku_id)
    } else {
      where += ' AND (sku_id IS NULL OR sku_id = 0)'
    }

    const [[existing]] = await pool.query(`SELECT id, quantity FROM mall_cart WHERE ${where}`, params)

    if (existing) {
      // 更新数量
      const newQty = existing.quantity + quantity
      await pool.query('UPDATE mall_cart SET quantity = ? WHERE id = ?', [newQty, existing.id])
      res.json({ message: '已更新数量', cart_id: existing.id, quantity: newQty })
    } else {
      // 新增
      const [result] = await pool.query(
        'INSERT INTO mall_cart (user_id, product_id, sku_id, quantity) VALUES (?, ?, ?, ?)',
        [user_id, product_id, sku_id || null, quantity]
      )
      res.json({ message: '已加入购物车', cart_id: result.insertId, quantity })
    }
  } catch (err) { next(err) }
})

// PUT /api/mall/cart/:id — 更新购物车商品数量
router.put('/cart/:id', async (req, res, next) => {
  try {
    const { quantity } = req.body
    if (!quantity || quantity < 1) return res.status(400).json({ message: '数量至少为1' })
    const [[item]] = await pool.query('SELECT id FROM mall_cart WHERE id = ?', [req.params.id])
    if (!item) return res.status(404).json({ message: '购物车商品不存在' })
    await pool.query('UPDATE mall_cart SET quantity = ? WHERE id = ?', [quantity, req.params.id])
    res.json({ message: 'ok', quantity })
  } catch (err) { next(err) }
})

// DELETE /api/mall/cart/:id — 删除购物车商品
router.delete('/cart/:id', async (req, res, next) => {
  try {
    await pool.query('DELETE FROM mall_cart WHERE id = ?', [req.params.id])
    res.json({ message: '已删除' })
  } catch (err) { next(err) }
})

// DELETE /api/mall/cart — 清空购物车
router.delete('/cart', async (req, res, next) => {
  try {
    const user_id = req.query.user_id || req.user?.id
    if (!user_id) return res.status(400).json({ message: 'user_id required' })
    await pool.query('DELETE FROM mall_cart WHERE user_id = ?', [user_id])
    res.json({ message: '购物车已清空' })
  } catch (err) { next(err) }
})

// POST /api/mall/cart/clear — 清空已选中的购物车商品（下单后）
router.post('/cart/clear', async (req, res, next) => {
  try {
    const { cart_ids } = req.body
    if (!cart_ids || !Array.isArray(cart_ids) || !cart_ids.length) {
      return res.status(400).json({ message: 'cart_ids 必填' })
    }
    const placeholders = cart_ids.map(() => '?').join(',')
    await pool.query(`DELETE FROM mall_cart WHERE id IN (${placeholders})`, cart_ids)
    res.json({ message: 'ok' })
  } catch (err) { next(err) }
})

// ─── 地址 API ───────────────────────────────────────────────────────────────

// GET /api/mall/addresses — 获取用户地址列表
router.get('/addresses', async (req, res, next) => {
  try {
    const user_id = req.query.user_id || req.user?.id
    if (!user_id) return res.status(400).json({ message: 'user_id required' })
    const [rows] = await pool.query(
      'SELECT * FROM mall_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC',
      [user_id]
    )
    res.json(rows)
  } catch (err) { next(err) }
})

// GET /api/mall/addresses/:id — 地址详情
router.get('/addresses/:id', async (req, res, next) => {
  try {
    const [[row]] = await pool.query('SELECT * FROM mall_addresses WHERE id = ?', [req.params.id])
    if (!row) return res.status(404).json({ message: '地址不存在' })
    res.json(row)
  } catch (err) { next(err) }
})

// POST /api/mall/addresses — 新增地址
router.post('/addresses', async (req, res, next) => {
  try {
    const { user_id, receiver_name, receiver_phone, province, city, district, detail, is_default = false } = req.body
    if (!user_id || !receiver_name || !receiver_phone || !detail) {
      return res.status(400).json({ message: '参数不完整' })
    }

    // 如果设为默认，先取消其他默认
    if (is_default) {
      await pool.query('UPDATE mall_addresses SET is_default = 0 WHERE user_id = ?', [user_id])
    }

    const [result] = await pool.query(
      `INSERT INTO mall_addresses (user_id, receiver_name, receiver_phone, province, city, district, detail, is_default)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [user_id, receiver_name, receiver_phone, province || '', city || '', district || '', address, is_default ? 1 : 0]
    )
    res.json({ id: result.insertId, message: '地址添加成功' })
  } catch (err) { next(err) }
})

// PUT /api/mall/addresses/:id — 更新地址
router.put('/addresses/:id', async (req, res, next) => {
  try {
    const { receiver_name, receiver_phone, province, city, district, detail, is_default } = req.body
    const [[existing]] = await pool.query('SELECT user_id FROM mall_addresses WHERE id = ?', [req.params.id])
    if (!existing) return res.status(404).json({ message: '地址不存在' })

    if (is_default) {
      await pool.query('UPDATE mall_addresses SET is_default = 0 WHERE user_id = ?', [existing.user_id])
    }

    const fields = [], vals = []
    if (receiver_name !== undefined) { fields.push('receiver_name=?'); vals.push(receiver_name) }
    if (receiver_phone !== undefined) { fields.push('receiver_phone=?'); vals.push(receiver_phone) }
    if (province !== undefined) { fields.push('province=?'); vals.push(province) }
    if (city !== undefined) { fields.push('city=?'); vals.push(city) }
    if (district !== undefined) { fields.push('district=?'); vals.push(district) }
    if (detail !== undefined) { fields.push('detail=?'); vals.push(detail) }
    if (is_default !== undefined) { fields.push('is_default=?'); vals.push(is_default ? 1 : 0) }

    if (!fields.length) return res.status(400).json({ message: '没有更新字段' })
    vals.push(req.params.id)
    await pool.query(`UPDATE mall_addresses SET ${fields.join(',')} WHERE id = ?`, vals)
    res.json({ message: 'ok' })
  } catch (err) { next(err) }
})

// DELETE /api/mall/addresses/:id — 删除地址
router.delete('/addresses/:id', async (req, res, next) => {
  try {
    await pool.query('DELETE FROM mall_addresses WHERE id = ?', [req.params.id])
    res.json({ message: '已删除' })
  } catch (err) { next(err) }
})

// PUT /api/mall/addresses/:id/default — 设为默认地址
router.put('/addresses/:id/default', async (req, res, next) => {
  try {
    const [[addr]] = await pool.query('SELECT user_id FROM mall_addresses WHERE id = ?', [req.params.id])
    if (!addr) return res.status(404).json({ message: '地址不存在' })
    await pool.query('UPDATE mall_addresses SET is_default = 0 WHERE user_id = ?', [addr.user_id])
    await pool.query('UPDATE mall_addresses SET is_default = 1 WHERE id = ?', [req.params.id])
    res.json({ message: '已设为默认' })
  } catch (err) { next(err) }
})

// ─── 优惠券 API ─────────────────────────────────────────────────────────────

// GET /api/mall/coupons — 优惠券列表
router.get('/coupons', async (req, res, next) => {
  try {
    const { user_id, status, page = 1, size = 20 } = req.query
    const offset = (page - 1) * size

    let sql = `
      SELECT c.*, ucl.id as user_coupon_id, ucl.used_at, ucl.used_order_id as order_id
      FROM coupons c
      LEFT JOIN user_coupons ucl ON c.id = ucl.coupon_id AND ucl.user_id = ?
    `
    const params = [user_id || 0]
    const countParams = [user_id || 0]

    if (status === 'available') {
      sql += ' WHERE (ucl.id IS NULL OR ucl.used_at IS NOT NULL)'
      sql += ' AND c.start_time <= NOW() AND c.end_time >= NOW() AND c.stock > 0'
    } else if (status === 'used') {
      sql += ' WHERE ucl.used_at IS NOT NULL'
    } else if (status === 'expired') {
      sql += ' WHERE c.end_time < NOW()'
    }

    sql += ' ORDER BY c.id DESC LIMIT ? OFFSET ?'
    params.push(Number(size), Number(offset))

    const [rows] = await pool.query(sql, params)
    // count 用 where + 同样的 join，但不重复 join 也不需要 ORDER BY
    const whereMatch = sql.match(/WHERE[^]*?(?= ORDER BY|$)/i)
    const whereSql = whereMatch ? whereMatch[0] : ''
    const countSql = `SELECT COUNT(*) as cnt FROM coupons c LEFT JOIN user_coupons ucl ON c.id = ucl.coupon_id AND ucl.user_id = ? ${whereSql.replace(/^WHERE/i, 'AND')}`
    const [[{ cnt }]] = await pool.query(countSql, countParams)

    res.json({ list: rows, total: cnt, page: Number(page), size: Number(size) })
  } catch (err) { next(err) }
})

// GET /api/mall/coupons/:id — 优惠券详情
router.get('/coupons/:id', async (req, res, next) => {
  try {
    const [[coupon]] = await pool.query('SELECT * FROM coupons WHERE id = ?', [req.params.id])
    if (!coupon) return res.status(404).json({ message: '优惠券不存在' })
    res.json(coupon)
  } catch (err) { next(err) }
})

// POST /api/mall/coupons — 领取优惠券
router.post('/coupons', async (req, res, next) => {
  try {
    const { user_id, coupon_id } = req.body
    if (!user_id || !coupon_id) return res.status(400).json({ message: '参数不完整' })

    // 检查优惠券是否存在且可领
    const [[coupon]] = await pool.query('SELECT * FROM coupons WHERE id = ?', [coupon_id])
    if (!coupon) return res.status(404).json({ message: '优惠券不存在' })
    if (coupon.stock <= 0) return res.status(400).json({ message: '优惠券已领完' })
    if (new Date(coupon.end_time) < new Date()) return res.status(400).json({ message: '优惠券已过期' })

    // 检查是否已领取
    const [[existing]] = await pool.query(
      'SELECT id FROM mall_user_coupons WHERE user_id = ? AND coupon_id = ?',
      [user_id, coupon_id]
    )
    if (existing) return res.status(400).json({ message: '已领取过该优惠券' })

    // 领取（事务）
    const conn = await pool.getConnection()
    try {
      await conn.beginTransaction()
      await conn.query('UPDATE coupons SET stock = stock - 1 WHERE id = ? AND stock > 0', [coupon_id])
      const [[couponAfter]] = await conn.query('SELECT stock FROM coupons WHERE id = ?', [coupon_id])
      if (couponAfter.stock < 0) throw new Error('库存不足')

      const [result] = await conn.query(
        'INSERT INTO mall_user_coupons (user_id, coupon_id) VALUES (?, ?)',
        [user_id, coupon_id]
      )
      await conn.commit()
      res.json({ user_coupon_id: result.insertId, message: '领取成功' })
    } catch (err) {
      await conn.rollback()
      throw err
    } finally {
      conn.release()
    }
  } catch (err) { next(err) }
})

// GET /api/mall/user-coupons — 我的优惠券
router.get('/user-coupons', async (req, res, next) => {
  try {
    const { user_id, status, page = 1, size = 20 } = req.query
    if (!user_id) return res.status(400).json({ message: 'user_id required' })
    const offset = (page - 1) * size

    let where = 'WHERE uc.user_id = ?'
    const params = [user_id]

    if (status === 'available') {
      where += ' AND uc.used_at IS NULL AND c.end_time >= NOW()'
    } else if (status === 'used') {
      where += ' AND uc.used_at IS NOT NULL'
    } else if (status === 'expired') {
      where += ' AND c.end_time < NOW() AND uc.used_at IS NULL'
    }

    const [rows] = await pool.query(`
      SELECT uc.id as user_coupon_id, uc.used_at, uc.used_order_id as order_id,
             c.id as coupon_id, c.name, c.type, c.money as discount_amount,
             c.min_price as min_amount, c.start_time, c.end_time, c.stock
      FROM user_coupons uc
      JOIN coupons c ON uc.coupon_id = c.id
      ${where}
      ORDER BY uc.id DESC
      LIMIT ? OFFSET ?
    `, [...params, Number(size), Number(offset)])

    const [[{ cnt }]] = await pool.query(`
      SELECT COUNT(*) as cnt FROM user_coupons uc
      JOIN coupons c ON uc.coupon_id = c.id
      ${where}
    `, params)

    res.json({ list: rows, total: cnt, page: Number(page), size: Number(size) })
  } catch (err) { next(err) }
})

// ─── 订单 API ───────────────────────────────────────────────────────────────

// 生成订单号
function generateOrderNo() {
  const d = new Date()
  const dateStr = d.toISOString().slice(0, 10).replace(/-/g, '')
  const random = String(Math.floor(Math.random() * 1000000)).padStart(6, '0')
  return `ORD${dateStr}${random}`
}

// POST /api/mall/orders — 创建订单
router.post('/orders', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    await conn.beginTransaction()

    const { user_id, items, address_id, coupon_id, remark, from_cart = false, cart_ids } = req.body
    if (!user_id) return res.status(400).json({ message: 'user_id 必填' })
    if (!items || !items.length) return res.status(400).json({ message: '商品不能为空' })

    // 获取用户信息
    const [[user]] = await conn.query('SELECT name, phone FROM users WHERE id = ?', [user_id])
    if (!user) return res.status(404).json({ message: '用户不存在' })

    // 计算订单金额
    let total_amount = 0
    const orderItems = []
    for (const item of items) {
      let price, product_name, sku_name, image
      if (item.sku_id) {
        const [[sku]] = await conn.query(
          'SELECT sale_price, specs FROM product_skus WHERE id = ?', [item.sku_id]
        )
        price = parseFloat(sku?.sale_price || 0)
        sku_name = sku?.specs || null
      } else {
        const [[prod]] = await conn.query('SELECT sale_price FROM products WHERE id = ?', [item.product_id])
        price = parseFloat(prod?.sale_price || 0)
      }
      const [[prod]] = await conn.query('SELECT name, image_main FROM products WHERE id = ?', [item.product_id])
      product_name = prod?.name || ''
      image = prod?.image_main || null

      const subtotal = price * item.quantity
      total_amount += subtotal
      orderItems.push({
        product_id: item.product_id,
        product_name,
        sku_name,
        image,
        price,
        quantity: item.quantity,
        subtotal
      })
    }

    // 优惠券抵扣
    let discount_amount = 0
    let freight_amount = 0
    if (coupon_id) {
      const [[coupon]] = await conn.query('SELECT * FROM coupons WHERE id = ?', [coupon_id])
      if (coupon && new Date(coupon.end_time) > new Date() && coupon.stock >= 0) {
        if (!coupon.min_amount || total_amount >= coupon.min_amount) {
          discount_amount = parseFloat(coupon.discount_amount || 0)
        }
      }
    }

    // 运费（满99免运费，这里简化处理）
    freight_amount = total_amount >= 99 ? 0 : 10

    const pay_amount = total_amount + freight_amount - discount_amount
    const order_no = generateOrderNo()

    // 收货地址
    let receiver_name = '', receiver_phone = '', receiver_address = ''
    if (address_id) {
      const [[addr]] = await conn.query(
        'SELECT receiver_name, receiver_phone, province, city, district, detail FROM mall_addresses WHERE id = ? AND user_id = ?',
        [address_id, user_id]
      )
      if (addr) {
        receiver_name = addr.receiver_name
        receiver_phone = addr.receiver_phone
        receiver_address = `${addr.province}${addr.city}${addr.district}${addr.detail}`
      }
    }

    // 插入订单
    const [orderResult] = await conn.query(`
      INSERT INTO mall_orders (order_no, user_id, user_name, user_phone, total_amount,
        freight_amount, discount_amount, pay_amount,
        receiver_name, receiver_phone, receiver_address, status, remark, created_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_pay', ?, NOW())
    `, [order_no, user_id, user.name, user.phone || '', total_amount,
        freight_amount, discount_amount, pay_amount,
        receiver_name, receiver_phone, receiver_address, remark || null])

    const order_id = orderResult.insertId

    // 插入订单商品
    for (const item of orderItems) {
      await conn.query(`
        INSERT INTO mall_order_items (order_id, product_id, name, sku_name, price, quantity, pic, subtotal)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      `, [order_id, item.product_id, item.name || item.product_name, item.sku_name || '', item.price, item.quantity || 1, item.pic || item.image, item.subtotal || (item.price * (item.quantity || 1))])
    }

    // 标记优惠券已用
    if (coupon_id) {
      await conn.query(
        'INSERT INTO mall_user_coupons (user_id, coupon_id, order_id, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE order_id=VALUES(order_id)',
        [user_id, coupon_id, order_id]
      )
    }

    // 如果来自购物车，删除已下单的商品
    if (from_cart && cart_ids && cart_ids.length) {
      const placeholders = cart_ids.map(() => '?').join(',')
      await conn.query(`DELETE FROM mall_cart WHERE id IN (${placeholders})`, cart_ids)
    }

    await conn.commit()
    res.json({ order_id, order_no, pay_amount, message: '订单创建成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// GET /api/mall/orders — 订单列表
router.get('/orders', async (req, res, next) => {
  try {
    const { user_id, status, page = 1, size = 20 } = req.query
    if (!user_id) return res.status(400).json({ message: 'user_id required' })
    const offset = (page - 1) * size

    let where = 'WHERE o.user_id = ?'
    const params = [user_id]
    if (status) { where += ' AND o.status = ?'; params.push(status) }

    const [rows] = await pool.query(`
      SELECT o.id, o.order_no, o.total_amount, o.freight_amount, o.discount_amount,
             o.pay_amount, o.user_name as receiver_name, o.user_phone as receiver_phone,
             o.receiver_address, o.remark, o.status, o.pay_time as paid_at,
             o.ship_time as shipped_at, o.receive_time as completed_at, o.created_at
      FROM mall_orders o
      ${where}
      ORDER BY o.created_at DESC
      LIMIT ? OFFSET ?
    `, [...params, Number(size), Number(offset)])

    const [[{ cnt }]] = await pool.query(
      `SELECT COUNT(*) as cnt FROM mall_orders o ${where}`, params
    )

    // 加载每个订单的商品
    for (const o of rows) {
      const [items] = await pool.query(
        'SELECT product_name, sku_name, image, price, quantity, subtotal FROM mall_order_items WHERE order_id = ?',
        [o.id]
      )
      o.items = items
    }

    res.json({ list: rows, total: cnt, page: Number(page), size: Number(size) })
  } catch (err) { next(err) }
})

// GET /api/mall/orders/:id — 订单详情
router.get('/orders/:id', async (req, res, next) => {
  try {
    const [[order]] = await pool.query('SELECT * FROM mall_orders WHERE id = ?', [req.params.id])
    if (!order) return res.status(404).json({ message: '订单不存在' })
    const [items] = await pool.query(
      'SELECT * FROM mall_order_items WHERE order_id = ?', [req.params.id]
    )
    res.json({ ...order, items })
  } catch (err) { next(err) }
})

// PUT /api/mall/orders/:id/pay — 支付订单
router.put('/orders/:id/pay', async (req, res, next) => {
  try {
    const { pay_type, wechat_trade_no } = req.body
    const [[order]] = await pool.query('SELECT status FROM mall_orders WHERE id = ?', [req.params.id])
    if (!order) return res.status(404).json({ message: '订单不存在' })
    if (order.status !== 'pending_pay') return res.status(400).json({ message: '订单状态不是待支付' })

    await pool.query(
      `UPDATE mall_orders SET status='paid', pay_type=?, wechat_trade_no=?, paid_at=NOW() WHERE id=?`,
      [pay_type || null, wechat_trade_no || null, req.params.id]
    )
    res.json({ message: '支付成功' })
  } catch (err) { next(err) }
})

// PUT /api/mall/orders/:id/cancel — 取消订单
router.put('/orders/:id/cancel', async (req, res, next) => {
  try {
    const [[order]] = await pool.query('SELECT status FROM mall_orders WHERE id = ?', [req.params.id])
    if (!order) return res.status(404).json({ message: '订单不存在' })
    if (!['pending_pay', 'paid'].includes(order.status)) {
      return res.status(400).json({ message: '当前状态无法取消' })
    }
    await pool.query("UPDATE mall_orders SET status='cancelled' WHERE id=?", [req.params.id])
    res.json({ message: '订单已取消' })
  } catch (err) { next(err) }
})

// PUT /api/mall/orders/:id/confirm — 确认收货
router.put('/orders/:id/confirm', async (req, res, next) => {
  try {
    const [[order]] = await pool.query('SELECT status FROM mall_orders WHERE id = ?', [req.params.id])
    if (!order) return res.status(404).json({ message: '订单不存在' })
    if (order.status !== 'shipped') return res.status(400).json({ message: '只有已发货状态可以确认收货' })
    await pool.query("UPDATE mall_orders SET status='completed', completed_at=NOW() WHERE id=?", [req.params.id])
    res.json({ message: '确认收货成功' })
  } catch (err) { next(err) }
})

// ─── 用户 API ───────────────────────────────────────────────────────────────

// POST /api/mall/register — 用户注册
router.post('/register', async (req, res, next) => {
  try {
    const { name, phone, password } = req.body
    if (!name || !phone || !password) return res.status(400).json({ message: '请填写完整信息' })
    if (!/^1[3-9]\d{9}$/.test(phone)) return res.status(400).json({ message: '手机号格式不正确' })

    const [[{ cnt }]] = await pool.query('SELECT COUNT(*) as cnt FROM users WHERE phone=?', [phone])
    if (cnt > 0) return res.status(400).json({ message: '该手机号已注册' })

    const hash = bcrypt.hashSync(password, 10)
    const [result] = await pool.query(
      'INSERT INTO users (name, phone, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
      [name, phone, hash, 'member', 'active']
    )
    res.json({ user_id: result.insertId, name, phone })
  } catch (err) { next(err) }
})

// 内存验证码存储（生产环境换Redis）
const verificationCodes = new Map()
function generateCode() {
  return Math.floor(100000 + Math.random() * 900000).toString()
}

// POST /api/mall/send-login-code — 发送登录验证码
router.post('/send-login-code', async (req, res, next) => {
  try {
    const { phone } = req.body
    if (!phone || !/^1[3-9]\d{9}$/.test(phone)) {
      return res.status(400).json({ message: '手机号格式不正确' })
    }
    const code = generateCode()
    verificationCodes.set(phone, { code, expires: Date.now() + 5 * 60 * 1000 })
    console.log(`[Mall] 验证码 ${phone}: ${code}`)
    // TODO: 生产环境替换为真实SMS发送（如阿里云/腾讯云）
    res.json({ message: '验证码已发送', code }) // 调试模式直接返回验证码
  } catch (err) { next(err) }
})

// POST /api/mall/login — 用户登录
router.post('/login', async (req, res, next) => {
  try {
    const { phone, code } = req.body
    if (code) {
      // 验证码登录
      const stored = verificationCodes.get(phone)
      if (!stored || stored.code !== code || stored.expires < Date.now()) {
        return res.status(401).json({ message: '验证码无效或已过期' })
      }
      verificationCodes.delete(phone)
      const [[user]] = await pool.query(
        'SELECT id, name, phone, role, status FROM users WHERE phone=? LIMIT 1', [phone]
      )
      if (!user) return res.status(404).json({ message: '用户不存在' })
      if (user.status === 'disabled') return res.status(403).json({ message: '账号已被禁用' })
      return res.json({ user_id: user.id, name: user.name, phone: user.phone, role: user.role })
    }
    // 密码登录
    const { password } = req.body
    if (!phone || !password) return res.status(400).json({ message: '手机号和密码必填' })
    const [[user]] = await pool.query(
      'SELECT id, name, phone, password, role, status FROM users WHERE phone=? LIMIT 1',
      [phone]
    )
    if (!user || !bcrypt.compareSync(password, user.password)) {
      return res.status(401).json({ message: '手机号或密码错误' })
    }
    if (user.status === 'disabled') return res.status(403).json({ message: '账号已被禁用' })
    await pool.query('UPDATE users SET last_login=NOW() WHERE id=?', [user.id])
    res.json({ user_id: user.id, name: user.name, phone: user.phone, role: user.role })
  } catch (err) { next(err) }
})
// GET /api/mall/profile — 获取用户资料
router.get('/profile', async (req, res, next) => {
  try {
    const user_id = req.query.user_id || req.user?.id
    if (!user_id) return res.status(400).json({ message: 'user_id required' })
    const [[user]] = await pool.query(
      'SELECT id, name, phone, role, status, last_login, created_at FROM users WHERE id = ?',
      [user_id]
    )
    if (!user) return res.status(404).json({ message: '用户不存在' })
    res.json(user)
  } catch (err) { next(err) }
})

// PUT /api/mall/profile — 更新用户资料
router.put('/profile', async (req, res, next) => {
  try {
    const user_id = req.body.user_id || req.user?.id
    if (!user_id) return res.status(400).json({ message: 'user_id required' })
    const { name, password } = req.body
    const fields = [], vals = []
    if (name) { fields.push('name=?'); vals.push(name) }
    if (password) {
      const hash = bcrypt.hashSync(password, 10)
      fields.push('password=?'); vals.push(hash)
    }
    if (!fields.length) return res.status(400).json({ message: '没有更新内容' })
    vals.push(user_id)
    await pool.query(`UPDATE users SET ${fields.join(',')} WHERE id=?`, vals)
    res.json({ message: '更新成功' })
  } catch (err) { next(err) }
})

// GET /api/mall/stats — 用户商城统计
router.get('/stats', async (req, res, next) => {
  try {
    const user_id = req.query.user_id || req.user?.id
    if (!user_id) return res.status(400).json({ message: 'user_id required' })

    const [[orderStats]] = await pool.query(`
      SELECT COUNT(*) as total_orders,
             SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed_orders,
             SUM(CASE WHEN status='pending_pay' THEN 1 ELSE 0 END) as pending_orders,
             SUM(pay_amount) as total_spent
      FROM mall_orders WHERE user_id = ?
    `, [user_id])

    const [[{ cart_count }]] = await pool.query(
      'SELECT COUNT(*) as cart_count FROM mall_cart WHERE user_id = ?', [user_id]
    )

    const [[{ coupon_count }]] = await pool.query(`
      SELECT COUNT(*) as coupon_count FROM user_coupons uc
      JOIN coupons c ON uc.coupon_id = c.id
      WHERE uc.user_id = ? AND uc.used_at IS NULL AND c.end_time >= NOW()
    `, [user_id])

    res.json({
      total_orders: orderStats.total_orders || 0,
      completed_orders: orderStats.completed_orders || 0,
      pending_orders: orderStats.pending_orders || 0,
      total_spent: orderStats.total_spent || 0,
      cart_count: cart_count || 0,
      coupon_count: coupon_count || 0
    })
  } catch (err) { next(err) }
})

// GET /api/mall/index — 商城首页数据
router.get('/index', async (req, res, next) => {
  try {
    // 轮播图（从系统设置读）
    const [[bannerSetting]] = await pool.query(`SELECT value FROM settings WHERE \`key\`='mall_banners'`)
    let banners = []
    try { banners = JSON.parse(bannerSetting?.value || '[]') } catch { banners = [] }

    // 分类（取前8个）
    const [categories] = await pool.query(`SELECT id, name FROM categories ORDER BY sort_order DESC, id ASC LIMIT 8`)

    // 热门商品（取前8个）
    const [hotProducts] = await pool.query(`
      SELECT id, name, sale_price as price, stock, image_main as pic, turnover_days as sales
      FROM products WHERE status='active' AND sale_price IS NOT NULL
      ORDER BY turnover_days DESC, id DESC LIMIT 8
    `)

    res.json({ code: 0, data: { banners, categories, hotProducts } })
  } catch (err) { next(err) }
})

module.exports = router
