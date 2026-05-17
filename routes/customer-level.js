import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'

const router = express.Router()

// 获取客户等级列表
router.get('/levels', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT * FROM customer_levels ORDER BY sort_order
    `)
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// 更新客户等级定义
router.put('/levels/:code', auth, async (req, res, next) => {
  try {
    const { code } = req.params
    const { level_name, min_monthly_amount, discount_rate, priority_service, color, sort_order } = req.body
    
    await pool.query(`
      UPDATE customer_levels 
      SET level_name = ?, min_monthly_amount = ?, discount_rate = ?, 
          priority_service = ?, color = ?, sort_order = ?
      WHERE level_code = ?
    `, [level_name, min_monthly_amount, discount_rate, priority_service, color, sort_order, code])
    
    res.json({ code: 0, message: '等级更新成功' })
  } catch (err) {
    next(err)
  }
})

// 计算并更新所有客户等级
router.post('/calculate', auth, async (req, res, next) => {
  try {
    // 获取所有客户销售数据
    const [salesData] = await pool.query(`
      SELECT 
        buyer_phone,
        buyer_name,
        SUM(sale_price) as total_amount,
        COUNT(*) as order_count
      FROM retail_records
      WHERE buyer_phone IS NOT NULL AND buyer_phone != ''
      GROUP BY buyer_phone, buyer_name
    `)
    
    // 获取等级阈值
    const [levels] = await pool.query(`SELECT * FROM customer_levels ORDER BY min_monthly_amount DESC`)
    
    // 更新每个客户的等级
    for (const customer of salesData) {
      // 计算月均销售额（简化：总销售额/月份数）
      const months = 3 // 假设统计近3个月
      const monthlyAmount = customer.total_amount / months
      
      // 确定等级
      let level = 'NORMAL'
      for (const l of levels) {
        if (monthlyAmount >= l.min_monthly_amount) {
          level = l.level_code
          break
        }
      }
      
      // 更新汇总表
      await pool.query(`
        INSERT INTO customer_sales_summary 
        (customer_phone, customer_name, total_amount, monthly_amount, order_count, avg_order_amount, current_level)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        total_amount = VALUES(total_amount),
        monthly_amount = VALUES(monthly_amount),
        order_count = VALUES(order_count),
        avg_order_amount = VALUES(avg_order_amount),
        current_level = VALUES(current_level)
      `, [customer.buyer_phone, customer.buyer_name, customer.total_amount, monthlyAmount, 
          customer.order_count, customer.total_amount / customer.order_count, level])
    }
    
    res.json({ code: 0, message: '客户等级计算完成', data: { count: salesData.length } })
  } catch (err) {
    next(err)
  }
})

// 获取所有客户分层列表
router.get('/list', auth, async (req, res, next) => {
  try {
    const { region, level } = req.query
    
    let sql = `SELECT css.*, cl.level_name, cl.color 
               FROM customer_sales_summary css
               LEFT JOIN customer_levels cl ON css.current_level = cl.level_code
               WHERE 1=1`
    const params = []
    
    if (region) {
      sql += ' AND css.region = ?'
      params.push(region)
    }
    if (level) {
      sql += ' AND css.current_level = ?'
      params.push(level)
    }
    
    sql += ' ORDER BY css.monthly_amount DESC'
    
    const [rows] = await pool.query(sql, params)
    
    // 补充等级名称
    const [levels] = await pool.query(`SELECT * FROM customer_levels`)
    const levelMap = {}
    levels.forEach(l => levelMap[l.level_code] = l)
    
    rows.forEach(r => {
      r.level_name = levelMap[r.current_level]?.level_name || '未分类'
      r.color = levelMap[r.current_level]?.color || '#999'
    })
    
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// 获取客户分层统计
router.get('/stats', auth, async (req, res, next) => {
  try {
    // 按等级统计
    const [byLevel] = await pool.query(`
      SELECT 
        COALESCE(css.current_level, 'UNKNOW') as level,
        COUNT(*) as customer_count,
        SUM(css.monthly_amount) as total_monthly,
        AVG(css.monthly_amount) as avg_monthly
      FROM customer_sales_summary css
      GROUP BY css.current_level
    `)
    
    // 获取等级名称
    const [levels] = await pool.query(`SELECT * FROM customer_levels`)
    const levelMap = {}
    levels.forEach(l => { levelMap[l.level_code] = l })
    
    byLevel.forEach(r => {
      r.level_name = levelMap[r.level]?.level_name || '未分类'
      r.color = levelMap[r.level]?.color || '#999'
    })
    
    // 总客户数
    const [[total]] = await pool.query(`SELECT COUNT(*) as cnt FROM customer_sales_summary`)
    
    res.json({
      code: 0,
      data: {
        total_customers: total.cnt,
        by_level: byLevel.sort((a, b) => {
          const order = ['VIP', 'KEY', 'NORMAL', 'RISK', 'UNKNOW']
          return order.indexOf(a.level) - order.indexOf(b.level)
        })
      },
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

// 获取单个客户详情
router.get('/detail/:phone', auth, async (req, res, next) => {
  try {
    const { phone } = req.params
    
    // 客户基本信息
    const [customers] = await pool.query(`
      SELECT css.*, cl.level_name, cl.color, cl.discount_rate
      FROM customer_sales_summary css
      LEFT JOIN customer_levels cl ON css.current_level = cl.level_code
      WHERE css.customer_phone = ?
    `, [phone])
    
    if (customers.length === 0) {
      return res.status(404).json({ code: 404, message: '客户不存在' })
    }
    
    // 购买历史
    const [history] = await pool.query(`
      SELECT * FROM retail_records 
      WHERE buyer_phone = ?
      ORDER BY created_at DESC
      LIMIT 20
    `, [phone])
    
    res.json({
      code: 0,
      data: {
        customer: customers[0],
        history: history
      },
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

export default router
