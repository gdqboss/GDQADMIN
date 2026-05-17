import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'

const router = express.Router()

// 获取收入报表
router.get('/income', auth, async (req, res, next) => {
  try {
    const { date_from, date_to } = req.query
    
    let sql = `
      SELECT 
        DATE(created_at) as date,
        COUNT(*) as count,
        SUM(sale_price) as amount
      FROM retail_records
      WHERE type = 'sale'
    `
    const params = []
    
    if (date_from) {
      sql += ' AND DATE(created_at) >= ?'
      params.push(date_from)
    }
    if (date_to) {
      sql += ' AND DATE(created_at) <= ?'
      params.push(date_to)
    }
    
    sql += ' GROUP BY DATE(created_at) ORDER BY date DESC'
    
    const [rows] = await pool.query(sql, params)
    
    const totalAmount = rows.reduce((sum, r) => sum + Number(r.amount || 0), 0)
    const totalCount = rows.reduce((sum, r) => sum + Number(r.count || 0), 0)
    
    res.json({
      code: 0,
      data: {
        list: rows,
        summary: {
          total_count: totalCount,
          total_amount: totalAmount,
          avg_amount: totalCount > 0 ? totalAmount / totalCount : 0
        }
      },
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

// 获取支出报表
router.get('/expense', auth, async (req, res, next) => {
  try {
    const { date_from, date_to } = req.query
    
    let sql = `
      SELECT 
        DATE(transaction_date) as date,
        COUNT(*) as count,
        SUM(amount) as amount,
        description
      FROM fund_transactions
      WHERE transaction_type = 'expense'
    `
    const params = []
    
    if (date_from) {
      sql += ' AND DATE(transaction_date) >= ?'
      params.push(date_from)
    }
    if (date_to) {
      sql += ' AND DATE(transaction_date) <= ?'
      params.push(date_to)
    }
    
    sql += ' GROUP BY DATE(transaction_date), description ORDER BY date DESC'
    
    const [rows] = await pool.query(sql, params)
    
    const totalAmount = rows.reduce((sum, r) => sum + Number(r.amount || 0), 0)
    
    res.json({
      code: 0,
      data: {
        list: rows,
        summary: {
          total_count: rows.length,
          total_amount: totalAmount
        }
      },
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

// 获取利润报表
router.get('/profit', auth, async (req, res, next) => {
  try {
    const { date_from, date_to } = req.query
    
    const dateFilter = (sql) => {
      if (date_from) sql += ' AND DATE(created_at) >= ?'
      if (date_to) sql += ' AND DATE(created_at) <= ?'
      return sql
    }
    
    // 获取收入
    let incomeSql = `SELECT COALESCE(SUM(sale_price), 0) as total FROM retail_records WHERE type = 'sale'`
    incomeSql = dateFilter(incomeSql)
    
    // 获取支出
    let expenseSql = `SELECT COALESCE(SUM(amount), 0) as total FROM fund_transactions WHERE transaction_type = 'expense'`
    if (date_from) expenseSql += ' AND DATE(transaction_date) >= ?'
    if (date_to) expenseSql += ' AND DATE(transaction_date) <= ?'
    
    const params = []
    if (date_from) params.push(date_from)
    if (date_to) params.push(date_to)
    
    const [[income]] = await pool.query(incomeSql, date_from || date_to ? params : [])
    const [[expense]] = await pool.query(expenseSql, params)
    
    const profit = Number(income.total) - Number(expense.total)
    const profitRate = Number(income.total) > 0 ? (profit / Number(income.total) * 100) : 0
    
    res.json({
      code: 0,
      data: {
        income: Number(income.total),
        expense: Number(expense.total),
        profit: profit,
        profit_rate: profitRate.toFixed(2) + '%'
      },
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

// 获取应收报表（客户欠款）
router.get('/receivable', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT 
        customer_name,
        customer_phone,
        transaction_type,
        SUM(amount) as amount
      FROM accounts_receivable
      GROUP BY customer_name, customer_phone, transaction_type
    `)
    
    // 按客户汇总
    const customerMap = {}
    rows.forEach(r => {
      const key = r.customer_phone || 'unknown'
      if (!customerMap[key]) {
        customerMap[key] = {
          customer_name: r.customer_name,
          customer_phone: r.customer_phone,
          total_amount: 0,
          paid_amount: 0,
          pending_amount: 0
        }
      }
      if (r.transaction_type === 'sale') {
        customerMap[key].total_amount += Number(r.amount || 0)
      } else if (r.transaction_type === 'payment') {
        customerMap[key].paid_amount += Number(r.amount || 0)
      }
    })
    
    // 计算未收
    Object.values(customerMap).forEach(c => {
      c.pending_amount = c.total_amount - c.paid_amount
    })
    
    const result = Object.values(customerMap).sort((a, b) => b.pending_amount - a.pending_amount)
    
    res.json({
      code: 0,
      data: result,
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

// 获取应付报表（供应商欠款）
router.get('/payable', auth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(`
      SELECT 
        s.name as supplier_name,
        s.phone as supplier_phone,
        at.transaction_type,
        SUM(at.amount) as amount
      FROM accounts_payable at
      JOIN suppliers s ON at.supplier_id = s.id
      GROUP BY s.name, s.phone, at.transaction_type
    `)
    
    const supplierMap = {}
    rows.forEach(r => {
      const key = r.supplier_phone || 'unknown'
      if (!supplierMap[key]) {
        supplierMap[key] = {
          supplier_name: r.supplier_name,
          supplier_phone: r.supplier_phone,
          total_amount: 0,
          paid_amount: 0,
          pending_amount: 0
        }
      }
      if (r.transaction_type === 'purchase') {
        supplierMap[key].total_amount += Number(r.amount || 0)
      } else if (r.transaction_type === 'payment') {
        supplierMap[key].paid_amount += Number(r.amount || 0)
      }
    })
    
    Object.values(supplierMap).forEach(s => {
      s.pending_amount = s.total_amount - s.paid_amount
    })
    
    const result = Object.values(supplierMap).sort((a, b) => b.pending_amount - a.pending_amount)
    
    res.json({
      code: 0,
      data: result,
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

// 获取财务仪表盘
router.get('/dashboard', auth, async (req, res, next) => {
  try {
    // 本月收入
    const [[monthIncome]] = await pool.query(`
      SELECT COALESCE(SUM(sale_price), 0) as total 
      FROM retail_records 
      WHERE type = 'sale'
      AND YEAR(created_at) = YEAR(CURDATE()) 
      AND MONTH(created_at) = MONTH(CURDATE())
    `)
    
    // 本月支出
    const [[monthExpense]] = await pool.query(`
      SELECT COALESCE(SUM(amount), 0) as total 
      FROM fund_transactions 
      WHERE transaction_type = 'expense'
      AND YEAR(transaction_date) = YEAR(CURDATE()) 
      AND MONTH(transaction_date) = MONTH(CURDATE())
    `)
    
    // 应收总额
    const [[receivable]] = await pool.query(`
      SELECT COALESCE(SUM(CASE WHEN transaction_type = 'sale' THEN amount ELSE 0 END), 0) as total FROM accounts_receivable
    `)
    
    // 已收
    const [[received]] = await pool.query(`
      SELECT COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END), 0) as total FROM accounts_receivable
    `)
    
    // 应付总额
    const [[payable]] = await pool.query(`
      SELECT COALESCE(SUM(CASE WHEN transaction_type = 'purchase' THEN amount ELSE 0 END), 0) as total FROM accounts_payable
    `)
    
    // 已付
    const [[paid]] = await pool.query(`
      SELECT COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END), 0) as total FROM accounts_payable
    `)
    
    // 库存总额
    const [[inventory]] = await pool.query(`
      SELECT COALESCE(SUM(purchase_price * stock), 0) as total FROM products WHERE stock > 0
    `)
    
    // 账户余额
    const [[balance]] = await pool.query(`
      SELECT COALESCE(SUM(balance), 0) as total FROM fund_accounts WHERE status = 'active'
    `)
    
    const profit = Number(monthIncome.total) - Number(monthExpense.total)
    const profitRate = Number(monthIncome.total) > 0 ? (profit / Number(monthIncome.total) * 100) : 0
    const pendingReceivable = Number(receivable.total) - Number(received.total)
    const pendingPayable = Number(payable.total) - Number(paid.total)
    
    res.json({
      code: 0,
      data: {
        month_income: Number(monthIncome.total),
        month_expense: Number(monthExpense.total),
        month_profit: profit,
        profit_rate: profitRate.toFixed(1) + '%',
        receivable: pendingReceivable,
        payable: pendingPayable,
        inventory: Number(inventory.total),
        cash_balance: Number(balance.total),
        net_asset: Number(balance.total) + pendingReceivable - pendingPayable
      },
      message: 'ok'
    })
  } catch (err) {
    next(err)
  }
})

export default router
