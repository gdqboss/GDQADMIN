import { Router } from 'express'
import crypto from 'crypto'
import bcrypt from 'bcryptjs'
import { pool } from '../db/connection.js'

const router = Router()

// 获取商城配置
router.get('/config', async (req, res, next) => {
  try {
    const [rows] = await pool.query('SELECT * FROM store_config WHERE id=1')
    res.json(rows[0] || { shop_name: 'TRAVELMATE STORE', enabled: 1 })
  } catch (err) { next(err) }
})

// 获取分类列表（tree结构）
router.get('/categories', async (req, res, next) => {
  try {
    const [cats] = await pool.query(
      'SELECT id, name, parent_id, sort_order FROM categories ORDER BY sort_order, id'
    )
    // 构建tree
    const map = {}, roots = []
    cats.forEach(c => { map[c.id] = { ...c, children: [] } })
    cats.forEach(c => {
      if (c.parent_id && map[c.parent_id]) map[c.parent_id].children.push(map[c.id])
      else roots.push(map[c.id])
    })
    res.json(roots)
  } catch (err) { next(err) }
})

// 获取商品列表
router.get('/products', async (req, res, next) => {
  try {
    const { category_id, keyword, page = 1, size = 20 } = req.query
    const offset = (page - 1) * size
    let where = "WHERE p.status='active'"
    const params = []
    // category_id=0 or absent = all products
    if (category_id && Number(category_id) > 0) {
      where += ' AND p.category_id=?'
      params.push(Number(category_id))
    }
    if (keyword) { where += ' AND p.name LIKE ?'; params.push(`%${keyword}%`) }

    const [rows] = await pool.query(
      `SELECT p.id, p.name, p.sale_price, p.stock, p.image_main, p.category_id,
              c.name as category_name
       FROM products p
       LEFT JOIN categories c ON c.id=p.category_id
       ${where}
       ORDER BY p.id DESC
       LIMIT ? OFFSET ?`, [...params, Number(size), Number(offset)]
    )
    const [[{ cnt }]] = await pool.query(`SELECT COUNT(*) as cnt FROM products p ${where}`, params)
    res.json({ list: rows, total: cnt, page: Number(page), size: Number(size) })
  } catch (err) { next(err) }
})

// 商品详情
router.get('/products/:id', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT p.*, c.name as category_name
       FROM products p LEFT JOIN categories c ON c.id=p.category_id
       WHERE p.id=? AND p.status='active'`, [req.params.id]
    )
    if (!rows.length) return res.status(404).json({ message: '商品不存在' })
    const p = rows[0]
    // 处理图片
    if (p.images) {
      try { p.images = JSON.parse(p.images) } catch { p.images = [] }
    } else { p.images = [] }
    res.json(p)
  } catch (err) { next(err) }
})

// 下单
router.post('/orders', async (req, res, next) => {
  try {
    const { user_id, items, total_amount, receiver_name, receiver_phone, receiver_address } = req.body
    if (!items || !items.length) return res.status(400).json({ message: '购物车为空' })
    const [users] = await pool.query('SELECT name, phone FROM users WHERE id=?', [user_id])
    if (!users.length) return res.status(404).json({ message: '用户不存在' })

    const order_no = 'ORD' + Date.now() + String(Math.floor(Math.random()*10000)).padStart(4,'0')
    const [[result]] = await pool.query(
      `INSERT INTO orders (order_no, member_id, member_name, member_phone, total_amount,
        receiver_name, receiver_phone, receiver_address, status, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())`,
      [order_no, user_id, users[0].name, users[0].phone || '', total_amount || 0,
       receiver_name || '', receiver_phone || '', receiver_address || '']
    )

    // 插入订单商品
    for (const item of items) {
      await pool.query(
        `INSERT INTO order_items (order_id, product_id, product_name, sku, quantity, unit_price, subtotal)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [result.insertId, item.id, item.name, item.sku || '', item.qty, item.price, (item.qty * item.price)]
      )
    }

    res.json({ order_id: result.insertId, order_no })
  } catch (err) { next(err) }
})

// 订单列表（用户）
router.get('/orders', async (req, res, next) => {
  try {
    const { user_id, page = 1, size = 20 } = req.query
    if (!user_id) return res.status(400).json({ message: 'user_id required' })
    const offset = (page - 1) * size

    const [rows] = await pool.query(
      `SELECT id, order_no, total_amount, status, created_at,
              receiver_name, receiver_phone, receiver_address
       FROM orders WHERE member_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?`,
      [user_id, Number(size), Number(offset)]
    )
    const [[{ cnt }]] = await pool.query('SELECT COUNT(*) as cnt FROM orders WHERE member_id=?', [user_id])

    // 加载每个订单的商品
    for (const o of rows) {
      const [items] = await pool.query(
        'SELECT product_name, sku, quantity, unit_price, subtotal FROM order_items WHERE order_id=?',
        [o.id]
      )
      o.items = items
    }

    res.json({ list: rows, total: cnt })
  } catch (err) { next(err) }
})

// 商城登录
router.post('/login', async (req, res, next) => {
  try {
    const { phone, password } = req.body
    if (!phone || !password) return res.status(400).json({ message: '手机号和密码必填' })
    const [users] = await pool.query(
      'SELECT id, name, phone, password FROM users WHERE phone=? LIMIT 1',
      [phone]
    )
    if (!users.length || !bcrypt.compareSync(password, users[0].password)) {
      return res.status(401).json({ message: '手机号或密码错误' })
    }
    const u = users[0]
    res.json({ user_id: u.id, name: u.name, phone: u.phone })
  } catch (err) { next(err) }
})

// 商城注册
router.post('/register', async (req, res, next) => {
  try {
    const { name, phone, password } = req.body
    if (!name || !phone || !password) return res.status(400).json({ message: '请填写完整信息' })
    const [[{ cnt }]] = await pool.query('SELECT COUNT(*) as cnt FROM users WHERE phone=?', [phone])
    if (cnt > 0) return res.status(400).json({ message: '该手机号已注册' })
    const hash = bcrypt.hashSync(password, 10)
    const [result] = await pool.query(
      'INSERT INTO users (name, phone, password, role, created_at) VALUES (?, ?, ?, ?, NOW())',
      [name, phone, hash, 'member']
    )
    res.json({ user_id: result.insertId, name, phone })
  } catch (err) { next(err) }
})

export default router