import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'

const router = express.Router()

// 获取库存预警规则
router.get('/rules', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(`SELECT * FROM inventory_rules ORDER BY id`)
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// 更新预警规则
router.put('/rules/:type', auth, async (req, res, next) => {
  try {
    const { type } = req.params
    const { threshold_value, email_notify, sms_notify, status } = req.body
    
    await pool.query(`
      UPDATE inventory_rules 
      SET threshold_value = ?, email_notify = ?, sms_notify = ?, status = ?
      WHERE alert_type = ?
    `, [threshold_value, email_notify, sms_notify, status, type])
    
    res.json({ code: 0, message: '规则更新成功' })
  } catch (err) {
    next(err)
  }
})

// 生成库存预警
router.post('/generate', auth, async (req, res, next) => {
  try {
    // 获取预警规则
    const [rules] = await pool.query(`SELECT * FROM inventory_rules WHERE status = 'active'`)
    
    // 获取产品库存
    const [products] = await pool.query(`
      SELECT p.id, p.name, p.sku, p.stock, p.safe_stock, p.turnover_days,
        COALESCE(SUM(r.sale_price), 0) as sales_amount,
        COUNT(r.id) as sales_count
      FROM products p
      LEFT JOIN retail_records r ON p.id = r.product_id 
        AND r.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
      WHERE p.status = 'active' AND p.stock IS NOT NULL
      GROUP BY p.id
    `)
    
    const alerts = []
    
    for (const product of products) {
      // 1. 紧急补货预警：库存 <= 安全库存
      if (product.stock <= product.safe_stock) {
        alerts.push({
          product_id: product.id,
          product_name: product.name,
          sku: product.sku,
          alert_type: 'urgent_replenish',
          threshold: product.safe_stock,
          current_value: product.stock,
          message: `【紧急补货】${product.name} 库存${product.stock}件，低于安全库存${product.safe_stock}件，需要立即补货！`
        })
      }
      
      // 2. 库存偏高预警：库存 > 安全库存 * 5
      if (product.stock > product.safe_stock * 5) {
        alerts.push({
          product_id: product.id,
          product_name: product.name,
          sku: product.sku,
          alert_type: 'overstock',
          threshold: product.safe_stock * 5,
          current_value: product.stock,
          message: `【库存偏高】${product.name} 库存${product.stock}件，偏高（安全库存${product.safe_stock}件的5倍），建议促销消化`
        })
      }
      
      // 3. 滞销预警：90天内没有销售或销量很低
      if (product.sales_count === 0 || (product.stock > 0 && product.sales_amount / product.stock < 10)) {
        alerts.push({
          product_id: product.id,
          product_name: product.name,
          sku: product.sku,
          alert_type: 'slow_moving',
          threshold: 30,
          current_value: product.sales_count,
          message: `【滞销预警】${product.name} 近90天销量${product.sales_count}单，可能滞销，请关注`
        })
      }
    }
    
    // 保存预警到数据库
    for (const alert of alerts) {
      // 检查是否已存在未解决的同类预警
      const [existing] = await pool.query(`
        SELECT id FROM inventory_alerts 
        WHERE product_id = ? AND alert_type = ? AND status = 'active'
      `, [alert.product_id, alert.alert_type])
      
      if (existing.length === 0) {
        await pool.query(`
          INSERT INTO inventory_alerts (product_id, alert_type, threshold, current_value, message)
          VALUES (?, ?, ?, ?, ?)
        `, [alert.product_id, alert.alert_type, alert.threshold, alert.current_value, alert.message])
      }
    }
    
    res.json({ 
      code: 0, 
      message: '预警生成完成',
      data: { 
        total_alerts: alerts.length,
        by_type: {
          urgent_replenish: alerts.filter(a => a.alert_type === 'urgent_replenish').length,
          overstock: alerts.filter(a => a.alert_type === 'overstock').length,
          slow_moving: alerts.filter(a => a.alert_type === 'slow_moving').length
        }
      }
    })
  } catch (err) {
    next(err)
  }
})

// 获取预警列表
router.get('/list', auth, async (req, res, next) => {
  try {
    const { type, status } = req.query
    
    let sql = `
      SELECT ia.*, p.name as product_name, p.sku, p.stock
      FROM inventory_alerts ia
      JOIN products p ON ia.product_id = p.id
      WHERE 1=1
    `
    const params = []
    
    if (type) {
      sql += ' AND ia.alert_type = ?'
      params.push(type)
    }
    if (status) {
      sql += ' AND ia.status = ?'
      params.push(status)
    }
    
    sql += ' ORDER BY ia.alert_type, ia.created_at DESC'
    
    const [rows] = await pool.query(sql, params)
    
    // 补充类型名称
    const typeNames = {
      urgent_replenish: '🔴 紧急补货',
      overstock: '🟡 库存偏高',
      slow_moving: '🟠 滞销预警'
    }
    
    rows.forEach(r => {
      r.alert_type_name = typeNames[r.alert_type] || r.alert_type
    })
    
    res.json({ code: 0, data: rows, message: 'ok' })
  } catch (err) {
    next(err)
  }
})

// 获取预警统计
router.get('/stats', auth, async (req, res, next) => {
  try {
    const [[total]] = await pool.query(`
      SELECT COUNT(*) as cnt FROM inventory_alerts WHERE status = 'active'
    `)
    
    const [byType] = await pool.query(`
      SELECT alert_type, COUNT(*) as cnt 
      FROM inventory_alerts 
      WHERE status = 'active'
      GROUP BY alert_type
    `)
    
    const [[todayNew]] = await pool.query(`
      SELECT COUNT(*) as cnt FROM inventory_alerts 
      WHERE DATE(created_at) = CURDATE()
    `)
    
    const typeNames = {
      urgent_replenish: '🔴 紧急补货',
      overstock: '🟡 库存偏高',
      slow_moving: '🟠 滞销预警'
    }
    
    byType.forEach(r => {
      r.alert_type_name = typeNames[r.alert_type] || r.alert_type
    })
    
    res.json({
      code: 0,
      data: {
        total_active: total.cnt,
        today_new: todayNew.cnt,
        by_type: byType
      },
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

// 处理预警
router.put('/:id/resolve', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    
    await pool.query(`
      UPDATE inventory_alerts 
      SET status = 'resolved', resolved_at = NOW(), resolved_by = ?
      WHERE id = ?
    `, [req.user.id, id])
    
    res.json({ code: 0, message: '预警已处理' })
  } catch (err) {
    next(err)
  }
})

// 忽略预警
router.put('/:id/ignore', auth, async (req, res, next) => {
  try {
    const { id } = req.params
    
    await pool.query(`
      UPDATE inventory_alerts SET status = 'ignored' WHERE id = ?
    `, [id])
    
    res.json({ code: 0, message: '预警已忽略' })
  } catch (err) {
    next(err)
  }
})

export default router
