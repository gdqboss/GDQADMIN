import { Router } from 'express'
import { pool } from '../db/connection.js'
import { parsePagination } from '../utils/pagination.js'
import * as XLSX from 'xlsx'
import { checkPerm } from '../utils/permission.js'
import { ROLES } from '../middleware/rbac.js'
import {
  exportPurchaseCosts,
  exportSalesRevenues,
  exportExpenses,
  exportProfitAnalysis,
  exportFinancialSummary
} from '../utils/excel-export.js'

const router = Router()

// ============================================
// 采购成本管理 (Purchase Costs)
// ============================================

// GET /api/finance-simple/export/purchase-costs - 导出采购成本
router.get('/export/purchase-costs', async (req, res, next) => {
  try {
    const { supplier_id, product_id, date_start, date_end, payment_status } = req.query

    let where = 'WHERE 1=1'
    const params = []

    if (supplier_id) { where += ' AND pc.supplier_id = ?'; params.push(supplier_id) }
    if (product_id) { where += ' AND pc.product_id = ?'; params.push(product_id) }
    if (date_start) { where += ' AND pc.purchase_date >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND pc.purchase_date <= ?'; params.push(date_end) }
    if (payment_status) { where += ' AND pc.payment_status = ?'; params.push(payment_status) }

    const sql = `
      SELECT pc.*, s.name as supplier_name, p.name as product_name, p.spec as product_spec
      FROM purchase_costs pc
      LEFT JOIN suppliers s ON pc.supplier_id = s.id
      LEFT JOIN products p ON pc.product_id = p.id
      ${where}
      ORDER BY pc.purchase_date DESC, pc.id DESC
    `

    const [rows] = await pool.query(sql, params)

    const filters = { date_start, date_end, payment_status }
    if (supplier_id && rows.length > 0) filters.supplier_name = rows[0].supplier_name
    if (product_id && rows.length > 0) filters.product_name = rows[0].product_name

    const workbook = await exportPurchaseCosts(rows, filters)
    const buffer = await workbook.xlsx.writeBuffer()

    const filename = `采购成本明细_${new Date().toISOString().slice(0, 10)}.xlsx`
    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    res.setHeader('Content-Disposition', `attachment; filename="${encodeURIComponent(filename)}"`)
    res.send(buffer)
  } catch (err) { next(err) }
})

// GET /api/finance-simple/purchase-costs - 采购成本列表
router.get('/purchase-costs', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    const { supplier_id, product_id, date_start, date_end, payment_status } = req.query

    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (supplier_id) { where += ' AND pc.supplier_id = ?'; params.push(supplier_id); countParams.push(supplier_id) }
    if (product_id) { where += ' AND pc.product_id = ?'; params.push(product_id); countParams.push(product_id) }
    if (date_start) { where += ' AND pc.purchase_date >= ?'; params.push(date_start); countParams.push(date_start) }
    if (date_end) { where += ' AND pc.purchase_date <= ?'; params.push(date_end); countParams.push(date_end) }
    if (payment_status) { where += ' AND pc.payment_status = ?'; params.push(payment_status); countParams.push(payment_status) }

    const sql = `
      SELECT pc.*, s.name as supplier_name, p.name as product_name, p.spec as product_spec,
        u.name as creator_name
      FROM purchase_costs pc
      LEFT JOIN suppliers s ON pc.supplier_id = s.id
      LEFT JOIN products p ON pc.product_id = p.id
      LEFT JOIN users u ON pc.creator_id = u.id
      ${where}
      ORDER BY pc.purchase_date DESC, pc.id DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(*) as total FROM purchase_costs pc ${where}`

    params.push(size, (page - 1) * size)
    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size } })
  } catch (err) { next(err) }
})

// POST /api/finance-simple/purchase-costs - 创建采购成本记录
router.post('/purchase-costs', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { supplier_id, purchase_date, product_id, quantity, unit_price, payment_method, account_id, note } = req.body
    if (!supplier_id || !purchase_date || !product_id || !quantity || !unit_price) {
      return res.status(400).json({ code: 400, message: '必填字段缺失' })
    }

    await conn.beginTransaction()

    const total_amount = quantity * unit_price
    const record_no = `PC${Date.now()}`
    const method = payment_method || 'credit'

    const [result] = await conn.query(
      `INSERT INTO purchase_costs (record_no, supplier_id, purchase_date, product_id, quantity, unit_price, total_amount, payment_method, account_id, creator_id, note)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [record_no, supplier_id, purchase_date, product_id, quantity, unit_price, total_amount, method, account_id || null, req.user.id, note]
    )

    const purchaseId = result.insertId

    // 赊购：自动生成应付款记录
    if (method === 'credit') {
      const [[current]] = await conn.query(
        'SELECT balance FROM accounts_payable WHERE supplier_id = ? ORDER BY id DESC LIMIT 1',
        [supplier_id]
      )
      const currentBalance = parseFloat(current?.balance || 0)
      const newBalance = currentBalance + total_amount

      await conn.query(
        `INSERT INTO accounts_payable (supplier_id, purchase_cost_id, transaction_type, transaction_date, amount, balance, note, creator_id)
         VALUES (?, ?, 'purchase', ?, ?, ?, ?, ?)`,
        [supplier_id, purchaseId, purchase_date, total_amount, newBalance, `采购 - ${record_no}`, req.user.id]
      )
    } else if (account_id) {
      // 现付：联动资金账户扣减
      const [[account]] = await conn.query('SELECT balance FROM fund_accounts WHERE id = ?', [account_id])
      if (account) {
        const newAccountBalance = parseFloat(account.balance) - total_amount
        await conn.query('UPDATE fund_accounts SET balance = ? WHERE id = ?', [newAccountBalance, account_id])
        await conn.query(
          `INSERT INTO fund_transactions (account_id, transaction_type, amount, balance_after, related_type, related_id, description, transaction_date, creator_id)
           VALUES (?, 'expense', ?, ?, 'purchase', ?, ?, ?, ?)`,
          [account_id, -total_amount, newAccountBalance, purchaseId, `采购付款 - ${record_no}`, purchase_date, req.user.id]
        )
      }
      // 标记为已付
      await conn.query('UPDATE purchase_costs SET payment_status = ?, paid_amount = ? WHERE id = ?', ['paid', total_amount, purchaseId])
    }

    await conn.commit()
    res.json({ code: 0, data: { id: purchaseId }, message: '创建成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// PUT /api/finance-simple/purchase-costs/:id - 更新采购成本记录
router.put('/purchase-costs/:id', async (req, res, next) => {
  try {
    const { supplier_id, purchase_date, product_id, quantity, unit_price, payment_method, note } = req.body
    const total_amount = quantity * unit_price

    await pool.query(
      `UPDATE purchase_costs SET supplier_id=?, purchase_date=?, product_id=?, quantity=?, unit_price=?, total_amount=?, payment_method=?, note=?
       WHERE id=?`,
      [supplier_id, purchase_date, product_id, quantity, unit_price, total_amount, payment_method, note, req.params.id]
    )

    res.json({ code: 0, message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/finance-simple/purchase-costs/:id - 删除采购成本记录
router.delete('/purchase-costs/:id', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const [[record]] = await conn.query('SELECT payment_status FROM purchase_costs WHERE id = ?', [req.params.id])
    if (!record) {
      conn.release()
      return res.status(404).json({ code: 404, message: '记录不存在' })
    }

    await conn.beginTransaction()

    // 删除关联的应付款记录
    await conn.query('DELETE FROM accounts_payable WHERE purchase_cost_id = ?', [req.params.id])
    await conn.query('DELETE FROM purchase_costs WHERE id = ?', [req.params.id])

    await conn.commit()
    res.json({ code: 0, message: '删除成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ============================================
// 销售收入管理 (Sales Revenues)
// ============================================

// GET /api/finance-simple/export/sales-revenues - 导出销售收入
router.get('/export/sales-revenues', async (req, res, next) => {
  try {
    const { store_id, product_id, date_start, date_end, payment_status } = req.query

    let where = 'WHERE 1=1'
    const params = []

    if (store_id) { where += ' AND sr.store_id = ?'; params.push(store_id) }
    if (product_id) { where += ' AND sr.product_id = ?'; params.push(product_id) }
    if (date_start) { where += ' AND sr.sale_date >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND sr.sale_date <= ?'; params.push(date_end) }
    if (payment_status) { where += ' AND sr.payment_status = ?'; params.push(payment_status) }

    const sql = `
      SELECT sr.*, st.name as store_name, p.name as product_name, p.spec as product_spec
      FROM sales_revenues sr
      LEFT JOIN stores st ON sr.store_id = st.id
      LEFT JOIN products p ON sr.product_id = p.id
      ${where}
      ORDER BY sr.sale_date DESC, sr.id DESC
    `

    const [rows] = await pool.query(sql, params)

    const filters = { date_start, date_end, payment_status }
    if (store_id && rows.length > 0) filters.store_name = rows[0].store_name
    if (product_id && rows.length > 0) filters.product_name = rows[0].product_name

    const workbook = await exportSalesRevenues(rows, filters)
    const buffer = await workbook.xlsx.writeBuffer()

    const filename = `销售收入明细_${new Date().toISOString().slice(0, 10)}.xlsx`
    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    res.setHeader('Content-Disposition', `attachment; filename="${encodeURIComponent(filename)}"`)
    res.send(buffer)
  } catch (err) { next(err) }
})

// GET /api/finance-simple/sales-revenues - 销售收入列表
router.get('/sales-revenues', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    const { store_id, product_id, date_start, date_end, payment_status } = req.query

    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (store_id) { where += ' AND sr.store_id = ?'; params.push(store_id); countParams.push(store_id) }
    if (product_id) { where += ' AND sr.product_id = ?'; params.push(product_id); countParams.push(product_id) }
    if (date_start) { where += ' AND sr.sale_date >= ?'; params.push(date_start); countParams.push(date_start) }
    if (date_end) { where += ' AND sr.sale_date <= ?'; params.push(date_end); countParams.push(date_end) }
    if (payment_status) { where += ' AND sr.payment_status = ?'; params.push(payment_status); countParams.push(payment_status) }

    const sql = `
      SELECT sr.*, st.name as store_name, p.name as product_name, p.spec as product_spec,
        u.name as creator_name
      FROM sales_revenues sr
      LEFT JOIN stores st ON sr.store_id = st.id
      LEFT JOIN products p ON sr.product_id = p.id
      LEFT JOIN users u ON sr.creator_id = u.id
      ${where}
      ORDER BY sr.sale_date DESC, sr.id DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(*) as total FROM sales_revenues sr ${where}`

    params.push(size, (page - 1) * size)
    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size } })
  } catch (err) { next(err) }
})

// POST /api/finance-simple/sales-revenues - 创建销售收入记录
router.post('/sales-revenues', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { sale_date, product_id, quantity, cost_price, sale_price, store_id, customer_phone, payment_method, account_id, note } = req.body
    if (!sale_date || !product_id || !quantity || !cost_price || !sale_price || !payment_method) {
      return res.status(400).json({ code: 400, message: '必填字段缺失' })
    }

    await conn.beginTransaction()

    const total_revenue = quantity * sale_price
    const total_cost = quantity * cost_price
    const gross_profit = total_revenue - total_cost
    const record_no = `SR${Date.now()}`
    const payment_status = customer_phone && payment_method === 'credit' ? 'unpaid' : 'paid'

    const [result] = await conn.query(
      `INSERT INTO sales_revenues (record_no, sale_date, product_id, quantity, cost_price, sale_price, total_revenue, total_cost, gross_profit, store_id, customer_phone, payment_method, account_id, payment_status, creator_id, note)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [record_no, sale_date, product_id, quantity, cost_price, sale_price, total_revenue, total_cost, gross_profit, store_id || null, customer_phone || null, payment_method, account_id || null, payment_status, req.user.id, note]
    )

    const saleId = result.insertId

    // 赊销：自动生成应收款记录
    if (payment_status === 'unpaid' && customer_phone) {
      const [[current]] = await conn.query(
        'SELECT balance FROM accounts_receivable WHERE customer_phone = ? ORDER BY id DESC LIMIT 1',
        [customer_phone]
      )
      const currentBalance = parseFloat(current?.balance || 0)
      const newBalance = currentBalance + total_revenue

      await conn.query(
        `INSERT INTO accounts_receivable (customer_phone, customer_name, sales_revenue_id, transaction_type, transaction_date, amount, balance, note, creator_id)
         VALUES (?, ?, ?, 'sale', ?, ?, ?, ?, ?)`,
        [customer_phone, '', saleId, sale_date, total_revenue, newBalance, `销售 - ${record_no}`, req.user.id]
      )
    } else if (account_id) {
      // 现收：联动资金账户增加
      const [[account]] = await conn.query('SELECT balance FROM fund_accounts WHERE id = ?', [account_id])
      if (account) {
        const newAccountBalance = parseFloat(account.balance) + total_revenue
        await conn.query('UPDATE fund_accounts SET balance = ? WHERE id = ?', [newAccountBalance, account_id])
        await conn.query(
          `INSERT INTO fund_transactions (account_id, transaction_type, amount, balance_after, related_type, related_id, description, transaction_date, creator_id)
           VALUES (?, 'income', ?, ?, 'sale', ?, ?, ?, ?)`,
          [account_id, total_revenue, newAccountBalance, saleId, `销售收入 - ${record_no}`, sale_date, req.user.id]
        )
      }
      await conn.query('UPDATE sales_revenues SET received_amount = ? WHERE id = ?', [total_revenue, saleId])
    }

    await conn.commit()
    res.json({ code: 0, data: { id: saleId }, message: '创建成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// PUT /api/finance-simple/sales-revenues/:id - 更新销售收入记录
router.put('/sales-revenues/:id', async (req, res, next) => {
  try {
    const { sale_date, product_id, quantity, cost_price, sale_price, store_id, customer_phone, payment_method, note } = req.body
    const total_revenue = quantity * sale_price
    const total_cost = quantity * cost_price
    const gross_profit = total_revenue - total_cost

    await pool.query(
      `UPDATE sales_revenues SET sale_date=?, product_id=?, quantity=?, cost_price=?, sale_price=?, total_revenue=?, total_cost=?, gross_profit=?, store_id=?, customer_phone=?, payment_method=?, note=?
       WHERE id=?`,
      [sale_date, product_id, quantity, cost_price, sale_price, total_revenue, total_cost, gross_profit, store_id, customer_phone, payment_method, note, req.params.id]
    )

    res.json({ code: 0, message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/finance-simple/sales-revenues/:id - 删除销售收入记录
router.delete('/sales-revenues/:id', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const [[record]] = await conn.query('SELECT id FROM sales_revenues WHERE id = ?', [req.params.id])
    if (!record) {
      conn.release()
      return res.status(404).json({ code: 404, message: '记录不存在' })
    }

    await conn.beginTransaction()

    // 删除关联的应收款记录
    await conn.query('DELETE FROM accounts_receivable WHERE sales_revenue_id = ?', [req.params.id])
    await conn.query('DELETE FROM sales_revenues WHERE id = ?', [req.params.id])

    await conn.commit()
    res.json({ code: 0, message: '删除成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ============================================
// 费用支出管理 (Expenses)
// ============================================

// GET /api/finance-simple/export/expenses - 导出费用支出
router.get('/export/expenses', async (req, res, next) => {
  try {
    const { category, store_id, date_start, date_end, approval_status } = req.query

    let where = 'WHERE 1=1'
    const params = []

    if (category) { where += ' AND e.category = ?'; params.push(category) }
    if (store_id) { where += ' AND e.store_id = ?'; params.push(store_id) }
    if (date_start) { where += ' AND e.expense_date >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND e.expense_date <= ?'; params.push(date_end) }
    if (approval_status) { where += ' AND e.approval_status = ?'; params.push(approval_status) }

    const sql = `
      SELECT e.*, st.name as store_name
      FROM expense_records e
      LEFT JOIN stores st ON e.store_id = st.id
      ${where}
      ORDER BY e.expense_date DESC, e.id DESC
    `

    const [rows] = await pool.query(sql, params)

    const filters = { category, date_start, date_end, approval_status }
    if (store_id && rows.length > 0) filters.store_name = rows[0].store_name

    const workbook = await exportExpenses(rows, filters)
    const buffer = await workbook.xlsx.writeBuffer()

    const filename = `费用支出明细_${new Date().toISOString().slice(0, 10)}.xlsx`
    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    res.setHeader('Content-Disposition', `attachment; filename="${encodeURIComponent(filename)}"`)
    res.send(buffer)
  } catch (err) { next(err) }
})

// GET /api/finance-simple/expenses - 费用支出列表
router.get('/expenses', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    const { category, store_id, date_start, date_end, approval_status } = req.query

    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (category) { where += ' AND e.category = ?'; params.push(category); countParams.push(category) }
    if (store_id) { where += ' AND e.store_id = ?'; params.push(store_id); countParams.push(store_id) }
    if (date_start) { where += ' AND e.expense_date >= ?'; params.push(date_start); countParams.push(date_start) }
    if (date_end) { where += ' AND e.expense_date <= ?'; params.push(date_end); countParams.push(date_end) }
    if (approval_status) { where += ' AND e.approval_status = ?'; params.push(approval_status); countParams.push(approval_status) }

    const sql = `
      SELECT e.*, st.name as store_name, u.name as creator_name, ap.name as approver_name
      FROM expense_records e
      LEFT JOIN stores st ON e.store_id = st.id
      LEFT JOIN users u ON e.creator_id = u.id
      LEFT JOIN users ap ON e.approver_id = ap.id
      ${where}
      ORDER BY e.expense_date DESC, e.id DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(*) as total FROM expense_records e ${where}`

    params.push(size, (page - 1) * size)
    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size } })
  } catch (err) { next(err) }
})

// POST /api/finance-simple/expenses - 创建费用支出
router.post('/expenses', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { expense_date, category, category_name, amount, payment_method, store_id, payee, description, note } = req.body
    if (!expense_date || !category || !amount || !payment_method || !description) {
      return res.status(400).json({ code: 400, message: '必填字段缺失' })
    }

    await conn.beginTransaction()

    // 获取审批阈值
    const [[config]] = await conn.query(
      'SELECT config_value FROM system_config WHERE config_key = ?',
      ['expense_approval_threshold']
    )
    const threshold = parseFloat(config?.config_value || 5000)
    const needsApproval = amount >= threshold

    const record_no = `EXP${Date.now()}`
    const approval_status = needsApproval ? 'pending' : 'approved'

    // 创建费用支出记录
    const [result] = await conn.query(
      `INSERT INTO expense_records (record_no, expense_date, category, category_name, amount, payment_method, store_id, payee, description, note, creator_id, approval_status, approval_required)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [record_no, expense_date, category, category_name, amount, payment_method, store_id, payee, description, note, req.user.id, approval_status, needsApproval ? 1 : 0]
    )

    const expenseId = result.insertId

    // 如果需要审批，自动创建审批单
    if (needsApproval) {
      // 获取管理员作为审批人
      const [[admin]] = await conn.query(`SELECT id FROM users WHERE role = '${ROLES.ADMIN}' LIMIT 1`)
      if (!admin) {
        await conn.rollback()
        return res.status(400).json({ code: 400, message: '未找到审批人' })
      }

      const approvalNo = `APV${Date.now()}`
      const formData = JSON.stringify({
        expense_id: expenseId,
        amount,
        category,
        description,
        payee: payee || ''
      })

      // 创建审批单
      const [approvalResult] = await conn.query(
        `INSERT INTO approvals (type_code, title, applicant_id, form_data, status, created_at)
         VALUES ('expense', ?, ?, ?, 'pending', NOW())`,
        [`费用支出审批 - ${description}`, req.user.id, formData]
      )

      const approvalId = approvalResult.insertId

      // 创建审批步骤
      await conn.query(
        `INSERT INTO approval_steps (approval_id, step_order, approver_id, status)
         VALUES (?, 1, ?, 'pending')`,
        [approvalId, admin.id]
      )

      // 更新费用记录的审批ID
      await conn.query(
        'UPDATE expense_records SET approval_id = ? WHERE id = ?',
        [approvalId, expenseId]
      )

      await conn.commit()
      res.json({ code: 0, data: { id: expenseId, approval_id: approvalId, needs_approval: true }, message: '已提交审批' })
    } else {
      await conn.commit()
      res.json({ code: 0, data: { id: expenseId, needs_approval: false }, message: '创建成功' })
    }
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// PUT /api/finance-simple/expenses/:id - 更新费用支出
router.put('/expenses/:id', async (req, res, next) => {
  try {
    const { expense_date, category, category_name, amount, payment_method, store_id, payee, description, note } = req.body

    // 校验审批状态，pending 状态不允许修改
    const [[record]] = await pool.query('SELECT approval_status FROM expense_records WHERE id = ?', [req.params.id])
    if (!record) {
      return res.status(404).json({ code: 404, message: '记录不存在' })
    }
    if (record.approval_status === 'pending') {
      return res.status(400).json({ code: 400, message: '待审批记录不可修改' })
    }

    await pool.query(
      `UPDATE expense_records SET expense_date=?, category=?, category_name=?, amount=?, payment_method=?, store_id=?, payee=?, description=?, note=?
       WHERE id=?`,
      [expense_date, category, category_name, amount, payment_method, store_id, payee, description, note, req.params.id]
    )

    res.json({ code: 0, message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/finance-simple/expenses/:id - 删除费用支出
router.delete('/expenses/:id', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    // 校验审批状态
    const [[record]] = await conn.query('SELECT approval_status, approval_id FROM expense_records WHERE id = ?', [req.params.id])
    if (!record) {
      conn.release()
      return res.status(404).json({ code: 404, message: '记录不存在' })
    }
    if (record.approval_status === 'pending') {
      conn.release()
      return res.status(400).json({ code: 400, message: '待审批记录不可删除，请先撤销审批' })
    }

    await conn.beginTransaction()

    // 如果有关联审批单，同步删除
    if (record.approval_id) {
      await conn.query('DELETE FROM approval_steps WHERE approval_id = ?', [record.approval_id])
      await conn.query('DELETE FROM approvals WHERE id = ?', [record.approval_id])
    }

    await conn.query('DELETE FROM expense_records WHERE id = ?', [req.params.id])

    await conn.commit()
    res.json({ code: 0, message: '删除成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ============================================
// 应付款管理 (Accounts Payable)
// ============================================

// GET /api/finance-simple/accounts-payable - 应付款列表（按供货商汇总）
router.get('/accounts-payable', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    const { supplier_id } = req.query

    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (supplier_id) { where += ' AND ap.supplier_id = ?'; params.push(supplier_id); countParams.push(supplier_id) }

    const sql = `
      SELECT ap.supplier_id, s.name as supplier_name, s.contact_person, s.phone,
        SUM(CASE WHEN ap.transaction_type IN ('purchase','adjustment') AND ap.amount > 0 THEN ap.amount ELSE 0 END) as total_payable,
        SUM(CASE WHEN ap.transaction_type = 'payment' THEN ABS(ap.amount) ELSE 0 END) as total_paid,
        (SELECT ap2.balance FROM accounts_payable ap2 WHERE ap2.supplier_id = ap.supplier_id ORDER BY ap2.id DESC LIMIT 1) as current_balance,
        MAX(ap.transaction_date) as last_transaction_date
      FROM accounts_payable ap
      LEFT JOIN suppliers s ON ap.supplier_id = s.id
      ${where}
      GROUP BY ap.supplier_id
      ORDER BY current_balance DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(DISTINCT ap.supplier_id) as total FROM accounts_payable ap ${where}`

    params.push(size, (page - 1) * size)
    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size } })
  } catch (err) { next(err) }
})

// GET /api/finance-simple/accounts-payable/:supplier_id/transactions - 供货商应付款明细
router.get('/accounts-payable/:supplier_id/transactions', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT ap.*, u.name as creator_name
       FROM accounts_payable ap
       LEFT JOIN users u ON ap.creator_id = u.id
       WHERE ap.supplier_id = ?
       ORDER BY ap.transaction_date DESC, ap.id DESC`,
      [req.params.supplier_id]
    )

    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// ============================================
// 应收款管理 (Accounts Receivable)
// ============================================

// GET /api/finance-simple/accounts-receivable - 应收款列表（按客户汇总）
router.get('/accounts-receivable', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    const { customer_phone } = req.query

    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (customer_phone) { where += ' AND ar.customer_phone = ?'; params.push(customer_phone); countParams.push(customer_phone) }

    const sql = `
      SELECT ar.customer_phone, ar.customer_name,
        SUM(CASE WHEN ar.transaction_type IN ('sale','adjustment') AND ar.amount > 0 THEN ar.amount ELSE 0 END) as total_receivable,
        SUM(CASE WHEN ar.transaction_type = 'payment' THEN ABS(ar.amount) ELSE 0 END) as total_received,
        (SELECT ar2.balance FROM accounts_receivable ar2 WHERE ar2.customer_phone = ar.customer_phone ORDER BY ar2.id DESC LIMIT 1) as current_balance,
        MAX(ar.transaction_date) as last_transaction_date
      FROM accounts_receivable ar
      ${where}
      GROUP BY ar.customer_phone
      ORDER BY current_balance DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(DISTINCT ar.customer_phone) as total FROM accounts_receivable ar ${where}`

    params.push(size, (page - 1) * size)
    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size } })
  } catch (err) { next(err) }
})

// GET /api/finance-simple/accounts-receivable/:customer_phone/transactions - 客户应收款明细
router.get('/accounts-receivable/:customer_phone/transactions', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT ar.*, u.name as creator_name
       FROM accounts_receivable ar
       LEFT JOIN users u ON ar.creator_id = u.id
       WHERE ar.customer_phone = ?
       ORDER BY ar.transaction_date DESC, ar.id DESC`,
      [req.params.customer_phone]
    )

    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// ============================================
// 付款记录 (Payment Records)
// ============================================

// POST /api/finance-simple/payments - 创建付款记录
router.post('/payments', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { payment_date, supplier_id, amount, payment_method, account_id, note } = req.body
    if (!payment_date || !supplier_id || !amount || !payment_method) {
      return res.status(400).json({ code: 400, message: '必填字段缺失' })
    }

    await conn.beginTransaction()

    const record_no = `PAY${Date.now()}`

    // 创建付款记录
    const [result] = await conn.query(
      `INSERT INTO payment_records (record_no, payment_date, supplier_id, amount, payment_method, account_id, note, creator_id)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [record_no, payment_date, supplier_id, amount, payment_method, account_id || null, note, req.user.id]
    )

    // 获取当前应付款余额
    const [[current]] = await conn.query(
      'SELECT balance FROM accounts_payable WHERE supplier_id = ? ORDER BY id DESC LIMIT 1',
      [supplier_id]
    )
    const currentBalance = current?.balance || 0
    const newBalance = currentBalance - amount

    // 记录应付款变动
    await conn.query(
      `INSERT INTO accounts_payable (supplier_id, transaction_type, transaction_date, amount, balance, payment_method, note, creator_id)
       VALUES (?, 'payment', ?, ?, ?, ?, ?, ?)`,
      [supplier_id, payment_date, -amount, newBalance, payment_method, note, req.user.id]
    )

    // 联动资金账户：扣减余额并记录流水
    if (account_id) {
      const [[account]] = await conn.query('SELECT balance FROM fund_accounts WHERE id = ?', [account_id])
      if (account) {
        const newAccountBalance = parseFloat(account.balance) - parseFloat(amount)
        await conn.query('UPDATE fund_accounts SET balance = ? WHERE id = ?', [newAccountBalance, account_id])
        await conn.query(
          `INSERT INTO fund_transactions (account_id, transaction_type, amount, balance_after, related_type, related_id, description, transaction_date, creator_id)
           VALUES (?, 'expense', ?, ?, 'payment', ?, ?, ?, ?)`,
          [account_id, -amount, newAccountBalance, result.insertId, `付款给供货商 - ${record_no}`, payment_date, req.user.id]
        )
      }
    }

    await conn.commit()
    res.json({ code: 0, data: { id: result.insertId }, message: '付款成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ============================================
// 收款记录 (Receipt Records)
// ============================================

// POST /api/finance-simple/receipts - 创建收款记录
router.post('/receipts', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { receipt_date, customer_phone, customer_name, amount, payment_method, account_id, note } = req.body
    if (!receipt_date || !customer_phone || !amount || !payment_method) {
      return res.status(400).json({ code: 400, message: '必填字段缺失' })
    }

    await conn.beginTransaction()

    const record_no = `REC${Date.now()}`

    // 创建收款记录
    const [result] = await conn.query(
      `INSERT INTO receipt_records (record_no, receipt_date, customer_phone, customer_name, amount, payment_method, account_id, note, creator_id)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [record_no, receipt_date, customer_phone, customer_name, amount, payment_method, account_id || null, note, req.user.id]
    )

    // 获取当前应收款余额
    const [[current]] = await conn.query(
      'SELECT balance FROM accounts_receivable WHERE customer_phone = ? ORDER BY id DESC LIMIT 1',
      [customer_phone]
    )
    const currentBalance = current?.balance || 0
    const newBalance = currentBalance - amount

    // 记录应收款变动
    await conn.query(
      `INSERT INTO accounts_receivable (customer_phone, customer_name, transaction_type, transaction_date, amount, balance, payment_method, note, creator_id)
       VALUES (?, ?, 'payment', ?, ?, ?, ?, ?, ?)`,
      [customer_phone, customer_name, receipt_date, -amount, newBalance, payment_method, note, req.user.id]
    )

    // 联动资金账户：增加余额并记录流水
    if (account_id) {
      const [[account]] = await conn.query('SELECT balance FROM fund_accounts WHERE id = ?', [account_id])
      if (account) {
        const newAccountBalance = parseFloat(account.balance) + parseFloat(amount)
        await conn.query('UPDATE fund_accounts SET balance = ? WHERE id = ?', [newAccountBalance, account_id])
        await conn.query(
          `INSERT INTO fund_transactions (account_id, transaction_type, amount, balance_after, related_type, related_id, description, transaction_date, creator_id)
           VALUES (?, 'income', ?, ?, 'receipt', ?, ?, ?, ?)`,
          [account_id, amount, newAccountBalance, result.insertId, `收款 - ${record_no}`, receipt_date, req.user.id]
        )
      }
    }

    await conn.commit()
    res.json({ code: 0, data: { id: result.insertId }, message: '收款成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// GET /api/finance-simple/receipts - 收款记录列表
router.get('/receipts', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    const { customer_phone, date_start, date_end, payment_method } = req.query

    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (customer_phone) { where += ' AND rr.customer_phone LIKE ?'; const p = `%${customer_phone}%`; params.push(p); countParams.push(p) }
    if (date_start) { where += ' AND rr.receipt_date >= ?'; params.push(date_start); countParams.push(date_start) }
    if (date_end) { where += ' AND rr.receipt_date <= ?'; params.push(date_end); countParams.push(date_end) }
    if (payment_method) { where += ' AND rr.payment_method = ?'; params.push(payment_method); countParams.push(payment_method) }

    const sql = `
      SELECT rr.*, fa.account_name, u.name as creator_name
      FROM receipt_records rr
      LEFT JOIN fund_accounts fa ON rr.account_id = fa.id
      LEFT JOIN users u ON rr.creator_id = u.id
      ${where}
      ORDER BY rr.receipt_date DESC, rr.id DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(*) as total FROM receipt_records rr ${where}`

    params.push(size, (page - 1) * size)
    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size } })
  } catch (err) { next(err) }
})

// GET /api/finance-simple/payments - 付款记录列表
router.get('/payments', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    const { supplier_id, date_start, date_end, payment_method } = req.query

    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (supplier_id) { where += ' AND pr.supplier_id = ?'; params.push(supplier_id); countParams.push(supplier_id) }
    if (date_start) { where += ' AND pr.payment_date >= ?'; params.push(date_start); countParams.push(date_start) }
    if (date_end) { where += ' AND pr.payment_date <= ?'; params.push(date_end); countParams.push(date_end) }
    if (payment_method) { where += ' AND pr.payment_method = ?'; params.push(payment_method); countParams.push(payment_method) }

    const sql = `
      SELECT pr.*, s.name as supplier_name, fa.account_name, u.name as creator_name
      FROM payment_records pr
      LEFT JOIN suppliers s ON pr.supplier_id = s.id
      LEFT JOIN fund_accounts fa ON pr.account_id = fa.id
      LEFT JOIN users u ON pr.creator_id = u.id
      ${where}
      ORDER BY pr.payment_date DESC, pr.id DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(*) as total FROM payment_records pr ${where}`

    params.push(size, (page - 1) * size)
    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size } })
  } catch (err) { next(err) }
})

// PUT /api/finance-simple/receipts/:id - 更新收款记录
router.put('/receipts/:id', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { receipt_date, customer_phone, customer_name, amount, payment_method, account_id, note } = req.body
    if (!receipt_date || !customer_phone || !amount || !payment_method) {
      return res.status(400).json({ code: 400, message: '必填字段缺失' })
    }

    await conn.beginTransaction()

    await conn.query(
      `UPDATE receipt_records SET receipt_date=?, customer_phone=?, customer_name=?, amount=?, payment_method=?, account_id=?, note=? WHERE id=?`,
      [receipt_date, customer_phone, customer_name, amount, payment_method, account_id || null, note, req.params.id]
    )

    await conn.commit()
    res.json({ code: 0, message: '更新成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// DELETE /api/finance-simple/receipts/:id - 删除收款记录
router.delete('/receipts/:id', async (req, res, next) => {
  try {
    await pool.query('DELETE FROM receipt_records WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

// PUT /api/finance-simple/payments/:id - 更新付款记录
router.put('/payments/:id', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { payment_date, supplier_id, amount, payment_method, account_id, note } = req.body
    if (!payment_date || !supplier_id || !amount || !payment_method) {
      return res.status(400).json({ code: 400, message: '必填字段缺失' })
    }

    await conn.beginTransaction()

    await conn.query(
      `UPDATE payment_records SET payment_date=?, supplier_id=?, amount=?, payment_method=?, account_id=?, note=? WHERE id=?`,
      [payment_date, supplier_id, amount, payment_method, account_id || null, note, req.params.id]
    )

    await conn.commit()
    res.json({ code: 0, message: '更新成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// DELETE /api/finance-simple/payments/:id - 删除付款记录
router.delete('/payments/:id', async (req, res, next) => {
  try {
    await pool.query('DELETE FROM payment_records WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

// ============================================
// 财务汇总报表 (Financial Summary)
// ============================================

// GET /api/finance-simple/summary - 财务汇总（按日/月/年）
router.get('/summary', async (req, res, next) => {
  try {
    const { period_type = 'month', date_start, date_end } = req.query

    let dateFormat, groupBy, expenseGroupBy
    if (period_type === 'day') {
      dateFormat = '%Y-%m-%d'
      groupBy = 'DATE(sr.sale_date)'
      expenseGroupBy = 'DATE(e.expense_date)'
    } else if (period_type === 'month') {
      dateFormat = '%Y-%m'
      groupBy = 'DATE_FORMAT(sr.sale_date, "%Y-%m")'
      expenseGroupBy = 'DATE_FORMAT(e.expense_date, "%Y-%m")'
    } else {
      dateFormat = '%Y'
      groupBy = 'YEAR(sr.sale_date)'
      expenseGroupBy = 'YEAR(e.expense_date)'
    }

    let where = 'WHERE 1=1'
    const params = []

    if (date_start) { where += ' AND sr.sale_date >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND sr.sale_date <= ?'; params.push(date_end) }

    const sql = `
      SELECT
        DATE_FORMAT(sr.sale_date, '${dateFormat}') as period,
        SUM(sr.total_revenue) as total_revenue,
        SUM(sr.total_cost) as total_cost,
        SUM(sr.gross_profit) as gross_profit,
        COUNT(sr.id) as sales_count
      FROM sales_revenues sr
      ${where}
      GROUP BY ${groupBy}
      ORDER BY period DESC
    `

    const [rows] = await pool.query(sql, params)

    // 获取费用支出
    const expenseSql = `
      SELECT
        DATE_FORMAT(e.expense_date, '${dateFormat}') as period,
        SUM(e.amount) as total_expense
      FROM expense_records e
      WHERE e.approval_status = 'approved' ${date_start ? 'AND e.expense_date >= ?' : ''} ${date_end ? 'AND e.expense_date <= ?' : ''}
      GROUP BY ${expenseGroupBy}
    `
    const expenseParams = []
    if (date_start) expenseParams.push(date_start)
    if (date_end) expenseParams.push(date_end)

    const [expenses] = await pool.query(expenseSql, expenseParams)

    // 合并数据
    const expenseMap = {}
    expenses.forEach(e => { expenseMap[e.period] = e.total_expense })

    const result = rows.map(r => ({
      ...r,
      total_expense: expenseMap[r.period] || 0,
      net_profit: r.gross_profit - (expenseMap[r.period] || 0)
    }))

    res.json({ code: 0, data: result })
  } catch (err) { next(err) }
})

// ============================================
// 利润分析 (Profit Analysis)
// ============================================

// GET /api/finance-simple/export/profit-analysis - 导出利润分析
router.get('/export/profit-analysis', async (req, res, next) => {
  try {
    const { dimension = 'product', date_start, date_end, store_id, product_id } = req.query

    let groupBy, selectFields
    if (dimension === 'product') {
      groupBy = 'sr.product_id'
      selectFields = 'sr.product_id, p.name as product_name, p.spec as product_spec'
    } else if (dimension === 'store') {
      groupBy = 'sr.store_id'
      selectFields = 'sr.store_id, st.name as store_name'
    } else if (dimension === 'date') {
      groupBy = 'DATE(sr.sale_date)'
      selectFields = 'DATE(sr.sale_date) as sale_date'
    } else {
      return res.status(400).json({ code: 400, message: '无效的维度参数' })
    }

    let where = 'WHERE 1=1'
    const params = []

    if (date_start) { where += ' AND sr.sale_date >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND sr.sale_date <= ?'; params.push(date_end) }
    if (store_id) { where += ' AND sr.store_id = ?'; params.push(store_id) }
    if (product_id) { where += ' AND sr.product_id = ?'; params.push(product_id) }

    const sql = `
      SELECT
        ${selectFields},
        SUM(sr.quantity) as total_quantity,
        SUM(sr.total_revenue) as total_revenue,
        SUM(sr.total_cost) as total_cost,
        SUM(sr.gross_profit) as gross_profit,
        AVG(sr.sale_price) as avg_sale_price,
        AVG(sr.cost_price) as avg_cost_price,
        COUNT(sr.id) as sales_count
      FROM sales_revenues sr
      LEFT JOIN products p ON sr.product_id = p.id
      LEFT JOIN stores st ON sr.store_id = st.id
      ${where}
      GROUP BY ${groupBy}
      ORDER BY gross_profit DESC
    `

    const [rows] = await pool.query(sql, params)

    const filters = { date_start, date_end }
    if (store_id && rows.length > 0) filters.store_name = rows[0].store_name
    if (product_id && rows.length > 0) filters.product_name = rows[0].product_name

    const workbook = await exportProfitAnalysis(rows, dimension, filters)
    const buffer = await workbook.xlsx.writeBuffer()

    const filename = `利润分析报表_${new Date().toISOString().slice(0, 10)}.xlsx`
    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    res.setHeader('Content-Disposition', `attachment; filename="${encodeURIComponent(filename)}"`)
    res.send(buffer)
  } catch (err) { next(err) }
})

// GET /api/finance-simple/profit-analysis - 利润分析（按商品/门店/时间段）
router.get('/profit-analysis', async (req, res, next) => {
  try {
    const { dimension = 'product', date_start, date_end, store_id, product_id } = req.query

    let groupBy, selectFields
    if (dimension === 'product') {
      groupBy = 'sr.product_id'
      selectFields = 'sr.product_id, p.name as product_name, p.spec as product_spec'
    } else if (dimension === 'store') {
      groupBy = 'sr.store_id'
      selectFields = 'sr.store_id, st.name as store_name'
    } else if (dimension === 'date') {
      groupBy = 'DATE(sr.sale_date)'
      selectFields = 'DATE(sr.sale_date) as sale_date'
    } else {
      return res.status(400).json({ code: 400, message: '无效的维度参数' })
    }

    let where = 'WHERE 1=1'
    const params = []

    if (date_start) { where += ' AND sr.sale_date >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND sr.sale_date <= ?'; params.push(date_end) }
    if (store_id) { where += ' AND sr.store_id = ?'; params.push(store_id) }
    if (product_id) { where += ' AND sr.product_id = ?'; params.push(product_id) }

    const sql = `
      SELECT
        ${selectFields},
        SUM(sr.quantity) as total_quantity,
        SUM(sr.total_revenue) as total_revenue,
        SUM(sr.total_cost) as total_cost,
        SUM(sr.gross_profit) as gross_profit,
        AVG(sr.sale_price) as avg_sale_price,
        AVG(sr.cost_price) as avg_cost_price,
        COUNT(sr.id) as sales_count
      FROM sales_revenues sr
      LEFT JOIN products p ON sr.product_id = p.id
      LEFT JOIN stores st ON sr.store_id = st.id
      ${where}
      GROUP BY ${groupBy}
      ORDER BY gross_profit DESC
    `

    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// GET /api/finance-simple/overview - 财务总览（首页卡片数据）
router.get('/overview', async (req, res, next) => {
  try {
    const { date_start, date_end } = req.query

    let where = 'WHERE 1=1'
    const params = []

    if (date_start) { where += ' AND sale_date >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND sale_date <= ?'; params.push(date_end) }

    // 销售收入汇总
    const [[revenue]] = await pool.query(
      `SELECT
        SUM(total_revenue) as total_revenue,
        SUM(total_cost) as total_cost,
        SUM(gross_profit) as gross_profit,
        COUNT(id) as sales_count
       FROM sales_revenues ${where}`,
      params
    )

    // 费用支出汇总
    const [[expense]] = await pool.query(
      `SELECT SUM(amount) as total_expense
       FROM expense_records
       WHERE approval_status = 'approved' ${date_start ? 'AND expense_date >= ?' : ''} ${date_end ? 'AND expense_date <= ?' : ''}`,
      params
    )

    // 应付款余额
    const [[payable]] = await pool.query(
      `SELECT SUM(latest_balance) as total_payable
       FROM (SELECT supplier_id, (SELECT balance FROM accounts_payable ap2 WHERE ap2.supplier_id = ap.supplier_id ORDER BY ap2.id DESC LIMIT 1) as latest_balance FROM accounts_payable ap GROUP BY supplier_id) t`
    )

    // 应收款余额
    const [[receivable]] = await pool.query(
      `SELECT SUM(latest_balance) as total_receivable
       FROM (SELECT customer_phone, (SELECT balance FROM accounts_receivable ar2 WHERE ar2.customer_phone = ar.customer_phone ORDER BY ar2.id DESC LIMIT 1) as latest_balance FROM accounts_receivable ar GROUP BY customer_phone) t`
    )

    const data = {
      total_revenue: revenue?.total_revenue || 0,
      total_cost: revenue?.total_cost || 0,
      gross_profit: revenue?.gross_profit || 0,
      total_expense: expense?.total_expense || 0,
      net_profit: (revenue?.gross_profit || 0) - (expense?.total_expense || 0),
      sales_count: revenue?.sales_count || 0,
      total_payable: payable?.total_payable || 0,
      total_receivable: receivable?.total_receivable || 0
    }

    res.json({ code: 0, data })
  } catch (err) { next(err) }
})

// ============================================
// 财务提醒管理 (Finance Reminders)
// ============================================

// GET /api/finance-simple/reminders - 获取提醒列表
router.get('/reminders', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    const { reminder_type, status, priority } = req.query

    let where = 'WHERE fr.target_user_id = ?'
    const params = [req.user.id], countParams = [req.user.id]

    if (reminder_type) { where += ' AND fr.reminder_type = ?'; params.push(reminder_type); countParams.push(reminder_type) }
    if (status) { where += ' AND fr.status = ?'; params.push(status); countParams.push(status) }
    if (priority) { where += ' AND fr.priority = ?'; params.push(priority); countParams.push(priority) }

    const sql = `
      SELECT fr.*
      FROM finance_reminders fr
      ${where}
      ORDER BY
        CASE fr.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END,
        fr.created_at DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(*) as total FROM finance_reminders fr ${where}`

    params.push(size, (page - 1) * size)
    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size } })
  } catch (err) { next(err) }
})

// GET /api/finance-simple/reminders/unread-count - 未读提醒数量
router.get('/reminders/unread-count', async (req, res, next) => {
  try {
    const [[{ count }]] = await pool.query(
      `SELECT COUNT(*) as count FROM finance_reminders WHERE target_user_id = ? AND status = 'unread'`,
      [req.user.id]
    )

    res.json({ code: 0, data: { count } })
  } catch (err) { next(err) }
})

// GET /api/finance-simple/reminders/recent - 最近提醒（用于顶部下拉）
router.get('/reminders/recent', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT * FROM finance_reminders
       WHERE target_user_id = ? AND status = 'unread'
       ORDER BY
         CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END,
         created_at DESC
       LIMIT 5`,
      [req.user.id]
    )

    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// PUT /api/finance-simple/reminders/:id/read - 标记已读
router.put('/reminders/:id/read', async (req, res, next) => {
  try {
    await pool.query(
      `UPDATE finance_reminders SET status = 'read', read_at = NOW() WHERE id = ? AND target_user_id = ?`,
      [req.params.id, req.user.id]
    )

    res.json({ code: 0, message: '已标记为已读' })
  } catch (err) { next(err) }
})

// PUT /api/finance-simple/reminders/:id/dismiss - 忽略提醒
router.put('/reminders/:id/dismiss', async (req, res, next) => {
  try {
    await pool.query(
      `UPDATE finance_reminders SET status = 'dismissed' WHERE id = ? AND target_user_id = ?`,
      [req.params.id, req.user.id]
    )

    res.json({ code: 0, message: '已忽略提醒' })
  } catch (err) { next(err) }
})

// GET /api/finance-simple/reminder-settings - 获取提醒设置
router.get('/reminder-settings', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT * FROM reminder_settings WHERE user_id = ? ORDER BY reminder_type`,
      [req.user.id]
    )

    // 如果用户没有设置，创建默认设置
    if (rows.length === 0) {
      const defaultSettings = [
        { reminder_type: 'payable_due', enabled: true, advance_days: 3, threshold_amount: 10000 },
        { reminder_type: 'receivable_overdue', enabled: true, advance_days: 30, threshold_amount: 10000 },
        { reminder_type: 'expense_abnormal', enabled: true, advance_days: 0, threshold_amount: 50000 },
        { reminder_type: 'monthly_report', enabled: true, advance_days: 0, threshold_amount: 0 }
      ]

      for (const setting of defaultSettings) {
        await pool.query(
          `INSERT INTO reminder_settings (user_id, reminder_type, enabled, advance_days, threshold_amount)
           VALUES (?, ?, ?, ?, ?)`,
          [req.user.id, setting.reminder_type, setting.enabled, setting.advance_days, setting.threshold_amount]
        )
      }

      const [newRows] = await pool.query(
        `SELECT * FROM reminder_settings WHERE user_id = ? ORDER BY reminder_type`,
        [req.user.id]
      )
      return res.json({ code: 0, data: newRows })
    }

    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// PUT /api/finance-simple/reminder-settings - 更新提醒设置
router.put('/reminder-settings', async (req, res, next) => {
  try {
    const { settings } = req.body
    if (!Array.isArray(settings)) {
      return res.status(400).json({ code: 400, message: '设置格式错误' })
    }

    for (const setting of settings) {
      await pool.query(
        `UPDATE reminder_settings
         SET enabled = ?, advance_days = ?, threshold_amount = ?
         WHERE user_id = ? AND reminder_type = ?`,
        [setting.enabled, setting.advance_days, setting.threshold_amount, req.user.id, setting.reminder_type]
      )
    }

    res.json({ code: 0, message: '设置已保存' })
  } catch (err) { next(err) }
})

// ============================================
// 系统配置管理 (System Config)
// ============================================

// GET /api/finance-simple/config - 获取财务配置
router.get('/config', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT config_key, config_value, description FROM system_config
       WHERE config_key IN ('expense_approval_threshold', 'payment_approval_threshold', 'approval_workflow_level')`
    )

    const config = {}
    rows.forEach(row => {
      config[row.config_key] = {
        value: row.config_value,
        description: row.description
      }
    })

    res.json({ code: 0, data: config })
  } catch (err) { next(err) }
})

// PUT /api/finance-simple/config - 更新财务配置（仅管理员）
router.put('/config', async (req, res, next) => {
  try {
    // 检查权限
    if (!(await checkPerm(req, 'finance:read'))) {
      return res.status(403).json({ code: 403, message: '仅管理员可修改配置' })
    }

    const { expense_approval_threshold, payment_approval_threshold, approval_workflow_level } = req.body

    if (expense_approval_threshold !== undefined) {
      await pool.query(
        `UPDATE system_config SET config_value = ? WHERE config_key = 'expense_approval_threshold'`,
        [expense_approval_threshold]
      )
    }

    if (payment_approval_threshold !== undefined) {
      await pool.query(
        `UPDATE system_config SET config_value = ? WHERE config_key = 'payment_approval_threshold'`,
        [payment_approval_threshold]
      )
    }

    if (approval_workflow_level !== undefined) {
      await pool.query(
        `UPDATE system_config SET config_value = ? WHERE config_key = 'approval_workflow_level'`,
        [approval_workflow_level]
      )
    }

    res.json({ code: 0, message: '配置已更新' })
  } catch (err) { next(err) }
})

// POST /api/finance-simple/expenses/:id/approve-callback - 审批回调
router.post('/expenses/:id/approve-callback', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { approval_status, approver_id } = req.body
    if (!approval_status || !['approved', 'rejected'].includes(approval_status)) {
      return res.status(400).json({ code: 400, message: '无效的审批状态' })
    }

    await conn.beginTransaction()

    // 更新费用支出状态
    await conn.query(
      `UPDATE expense_records SET approval_status = ?, approver_id = ?, approved_at = NOW() WHERE id = ?`,
      [approval_status, approver_id, req.params.id]
    )

    // 如果审批通过，创建财务提醒
    if (approval_status === 'approved') {
      const [[expense]] = await conn.query(
        'SELECT creator_id, description, amount FROM expense_records WHERE id = ?',
        [req.params.id]
      )

      if (expense) {
        await conn.query(
          `INSERT INTO finance_reminders (reminder_type, title, content, target_user_id, related_id, priority, status)
           VALUES ('expense_approved', '费用支出审批通过', ?, ?, ?, 'medium', 'unread')`,
          [`您的费用支出"${expense.description}"（金额：¥${expense.amount}）已审批通过`, expense.creator_id, req.params.id]
        )
      }
    } else {
      // 审批拒绝，创建提醒
      const [[expense]] = await conn.query(
        'SELECT creator_id, description, amount FROM expense_records WHERE id = ?',
        [req.params.id]
      )

      if (expense) {
        await conn.query(
          `INSERT INTO finance_reminders (reminder_type, title, content, target_user_id, related_id, priority, status)
           VALUES ('expense_rejected', '费用支出审批被拒绝', ?, ?, ?, 'high', 'unread')`,
          [`您的费用支出"${expense.description}"（金额：¥${expense.amount}）已被拒绝`, expense.creator_id, req.params.id]
        )
      }
    }

    await conn.commit()
    res.json({ code: 0, message: '回调处理成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// ============================================
// 对账单管理 (Statements)
// ============================================

// GET /api/finance-simple/statement/supplier/:supplierId - 生成供货商对账单
router.get('/statement/supplier/:supplierId', async (req, res, next) => {
  try {
    const { start_date, end_date } = req.query
    if (!start_date || !end_date) {
      return res.status(400).json({ code: 400, message: '请提供开始和结束日期' })
    }

    const supplierId = req.params.supplierId

    // 获取供货商信息
    const [[supplier]] = await pool.query(
      'SELECT name, contact, phone FROM suppliers WHERE id = ?',
      [supplierId]
    )
    if (!supplier) {
      return res.status(404).json({ code: 404, message: '供货商不存在' })
    }

    // 获取期初余额（开始日期之前的最后一条记录）
    const [[openingBalance]] = await pool.query(
      `SELECT balance FROM accounts_payable
       WHERE supplier_id = ? AND transaction_date < ?
       ORDER BY id DESC LIMIT 1`,
      [supplierId, start_date]
    )

    // 获取采购明细
    const [purchases] = await pool.query(
      `SELECT pc.purchase_date, pc.record_no, p.name as product_name, pc.quantity, pc.total_amount
       FROM purchase_costs pc
       LEFT JOIN products p ON pc.product_id = p.id
       WHERE pc.supplier_id = ? AND pc.purchase_date >= ? AND pc.purchase_date <= ?
       ORDER BY pc.purchase_date ASC`,
      [supplierId, start_date, end_date]
    )

    // 获取付款明细
    const [payments] = await pool.query(
      `SELECT pr.payment_date, pr.payment_method, pr.amount
       FROM payment_records pr
       WHERE pr.supplier_id = ? AND pr.payment_date >= ? AND pr.payment_date <= ?
       ORDER BY pr.payment_date ASC`,
      [supplierId, start_date, end_date]
    )

    // 获取期末余额
    const [[closingBalance]] = await pool.query(
      `SELECT balance FROM accounts_payable
       WHERE supplier_id = ? AND transaction_date <= ?
       ORDER BY id DESC LIMIT 1`,
      [supplierId, end_date]
    )

    const total_purchases = purchases.reduce((sum, p) => sum + parseFloat(p.total_amount), 0)
    const total_payments = payments.reduce((sum, p) => sum + parseFloat(p.amount), 0)

    const data = {
      supplier_name: supplier.name,
      contact_person: supplier.contact,
      phone: supplier.phone,
      start_date,
      end_date,
      opening_balance: openingBalance?.balance || 0,
      purchases,
      total_purchases,
      payments,
      total_payments,
      closing_balance: closingBalance?.balance || 0
    }

    res.json({ code: 0, data })
  } catch (err) { next(err) }
})

// GET /api/finance-simple/statement/customer/:customerPhone - 生成客户对账单
router.get('/statement/customer/:customerPhone', async (req, res, next) => {
  try {
    const { start_date, end_date } = req.query
    if (!start_date || !end_date) {
      return res.status(400).json({ code: 400, message: '请提供开始和结束日期' })
    }

    const customerPhone = req.params.customerPhone

    // 获取客户信息（从应收款记录中获取最新的客户名称）
    const [[customer]] = await pool.query(
      'SELECT customer_name FROM accounts_receivable WHERE customer_phone = ? ORDER BY id DESC LIMIT 1',
      [customerPhone]
    )

    // 获取期初余额
    const [[openingBalance]] = await pool.query(
      `SELECT balance FROM accounts_receivable
       WHERE customer_phone = ? AND transaction_date < ?
       ORDER BY id DESC LIMIT 1`,
      [customerPhone, start_date]
    )

    // 获取销售明细
    const [sales] = await pool.query(
      `SELECT sr.sale_date, sr.record_no, p.name as product_name, sr.quantity, sr.total_revenue
       FROM sales_revenues sr
       LEFT JOIN products p ON sr.product_id = p.id
       WHERE sr.customer_phone = ? AND sr.sale_date >= ? AND sr.sale_date <= ?
       ORDER BY sr.sale_date ASC`,
      [customerPhone, start_date, end_date]
    )

    // 获取收款明细
    const [receipts] = await pool.query(
      `SELECT rr.receipt_date, rr.payment_method, rr.amount
       FROM receipt_records rr
       WHERE rr.customer_phone = ? AND rr.receipt_date >= ? AND rr.receipt_date <= ?
       ORDER BY rr.receipt_date ASC`,
      [customerPhone, start_date, end_date]
    )

    // 获取期末余额
    const [[closingBalance]] = await pool.query(
      `SELECT balance FROM accounts_receivable
       WHERE customer_phone = ? AND transaction_date <= ?
       ORDER BY id DESC LIMIT 1`,
      [customerPhone, end_date]
    )

    const total_sales = sales.reduce((sum, s) => sum + parseFloat(s.total_revenue), 0)
    const total_receipts = receipts.reduce((sum, r) => sum + parseFloat(r.amount), 0)

    const data = {
      customer_name: customer?.customer_name || '未知客户',
      customer_phone: customerPhone,
      start_date,
      end_date,
      opening_balance: openingBalance?.balance || 0,
      sales,
      total_sales,
      receipts,
      total_receipts,
      closing_balance: closingBalance?.balance || 0
    }

    res.json({ code: 0, data })
  } catch (err) { next(err) }
})

// POST /api/finance-simple/statement/supplier/:supplierId/export - 导出供货商对账单
router.post('/statement/supplier/:supplierId/export', async (req, res, next) => {
  try {
    const { start_date, end_date } = req.body
    if (!start_date || !end_date) {
      return res.status(400).json({ code: 400, message: '请提供开始和结束日期' })
    }

    const supplierId = req.params.supplierId

    // 获取对账单数据（复用GET接口逻辑）
    const [[supplier]] = await pool.query(
      'SELECT name, contact, phone FROM suppliers WHERE id = ?',
      [supplierId]
    )
    if (!supplier) {
      return res.status(404).json({ code: 404, message: '供货商不存在' })
    }

    const [[openingBalance]] = await pool.query(
      `SELECT balance FROM accounts_payable
       WHERE supplier_id = ? AND transaction_date < ?
       ORDER BY id DESC LIMIT 1`,
      [supplierId, start_date]
    )

    const [purchases] = await pool.query(
      `SELECT pc.purchase_date, pc.record_no, p.name as product_name, pc.quantity, pc.total_amount
       FROM purchase_costs pc
       LEFT JOIN products p ON pc.product_id = p.id
       WHERE pc.supplier_id = ? AND pc.purchase_date >= ? AND pc.purchase_date <= ?
       ORDER BY pc.purchase_date ASC`,
      [supplierId, start_date, end_date]
    )

    const [payments] = await pool.query(
      `SELECT pr.payment_date, pr.payment_method, pr.amount
       FROM payment_records pr
       WHERE pr.supplier_id = ? AND pr.payment_date >= ? AND pr.payment_date <= ?
       ORDER BY pr.payment_date ASC`,
      [supplierId, start_date, end_date]
    )

    const [[closingBalance]] = await pool.query(
      `SELECT balance FROM accounts_payable
       WHERE supplier_id = ? AND transaction_date <= ?
       ORDER BY id DESC LIMIT 1`,
      [supplierId, end_date]
    )

    const total_purchases = purchases.reduce((sum, p) => sum + parseFloat(p.total_amount), 0)
    const total_payments = payments.reduce((sum, p) => sum + parseFloat(p.amount), 0)

    const data = {
      supplier_name: supplier.name,
      contact_person: supplier.contact,
      phone: supplier.phone,
      start_date,
      end_date,
      opening_balance: openingBalance?.balance || 0,
      purchases,
      total_purchases,
      payments,
      total_payments,
      closing_balance: closingBalance?.balance || 0
    }

    // 生成Excel
    const buffer = await exportSupplierStatement(data)

    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    res.setHeader('Content-Disposition', `attachment; filename="supplier_statement_${supplierId}_${start_date}_${end_date}.xlsx"`)
    res.send(buffer)
  } catch (err) { next(err) }
})

// POST /api/finance-simple/statement/customer/:customerPhone/export - 导出客户对账单
router.post('/statement/customer/:customerPhone/export', async (req, res, next) => {
  try {
    const { start_date, end_date } = req.body
    if (!start_date || !end_date) {
      return res.status(400).json({ code: 400, message: '请提供开始和结束日期' })
    }

    const customerPhone = req.params.customerPhone

    const [[customer]] = await pool.query(
      'SELECT customer_name FROM accounts_receivable WHERE customer_phone = ? ORDER BY id DESC LIMIT 1',
      [customerPhone]
    )

    const [[openingBalance]] = await pool.query(
      `SELECT balance FROM accounts_receivable
       WHERE customer_phone = ? AND transaction_date < ?
       ORDER BY id DESC LIMIT 1`,
      [customerPhone, start_date]
    )

    const [sales] = await pool.query(
      `SELECT sr.sale_date, sr.record_no, p.name as product_name, sr.quantity, sr.total_revenue
       FROM sales_revenues sr
       LEFT JOIN products p ON sr.product_id = p.id
       WHERE sr.customer_phone = ? AND sr.sale_date >= ? AND sr.sale_date <= ?
       ORDER BY sr.sale_date ASC`,
      [customerPhone, start_date, end_date]
    )

    const [receipts] = await pool.query(
      `SELECT rr.receipt_date, rr.payment_method, rr.amount
       FROM receipt_records rr
       WHERE rr.customer_phone = ? AND rr.receipt_date >= ? AND rr.receipt_date <= ?
       ORDER BY rr.receipt_date ASC`,
      [customerPhone, start_date, end_date]
    )

    const [[closingBalance]] = await pool.query(
      `SELECT balance FROM accounts_receivable
       WHERE customer_phone = ? AND transaction_date <= ?
       ORDER BY id DESC LIMIT 1`,
      [customerPhone, end_date]
    )

    const total_sales = sales.reduce((sum, s) => sum + parseFloat(s.total_revenue), 0)
    const total_receipts = receipts.reduce((sum, r) => sum + parseFloat(r.amount), 0)

    const data = {
      customer_name: customer?.customer_name || '未知客户',
      customer_phone: customerPhone,
      start_date,
      end_date,
      opening_balance: openingBalance?.balance || 0,
      sales,
      total_sales,
      receipts,
      total_receipts,
      closing_balance: closingBalance?.balance || 0
    }

    // 生成Excel
    const buffer = await exportCustomerStatement(data)

    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    res.setHeader('Content-Disposition', `attachment; filename="customer_statement_${customerPhone}_${start_date}_${end_date}.xlsx"`)
    res.send(buffer)
  } catch (err) { next(err) }
})

// GET /api/finance-simple/export/financial-summary - 导出财务汇总表
router.get('/export/financial-summary', async (req, res, next) => {
  try {
    const { period_type = 'month', date_start, date_end } = req.query

    let dateFormat, groupBy
    if (period_type === 'day') {
      dateFormat = '%Y-%m-%d'
      groupBy = 'DATE(sr.sale_date)'
    } else if (period_type === 'month') {
      dateFormat = '%Y-%m'
      groupBy = 'DATE_FORMAT(sr.sale_date, "%Y-%m")'
    } else {
      dateFormat = '%Y'
      groupBy = 'YEAR(sr.sale_date)'
    }

    let where = 'WHERE 1=1'
    const params = []

    if (date_start) { where += ' AND sr.sale_date >= ?'; params.push(date_start) }
    if (date_end) { where += ' AND sr.sale_date <= ?'; params.push(date_end) }

    const sql = `
      SELECT
        DATE_FORMAT(sr.sale_date, '${dateFormat}') as period,
        SUM(sr.total_revenue) as total_revenue,
        SUM(sr.total_cost) as total_cost,
        SUM(sr.gross_profit) as gross_profit,
        COUNT(sr.id) as sales_count
      FROM sales_revenues sr
      ${where}
      GROUP BY ${groupBy}
      ORDER BY period DESC
    `

    const [rows] = await pool.query(sql, params)

    const expenseSql = `
      SELECT
        DATE_FORMAT(e.expense_date, '${dateFormat}') as period,
        SUM(e.amount) as total_expense
      FROM expense_records e
      WHERE e.approval_status = 'approved' ${date_start ? 'AND e.expense_date >= ?' : ''} ${date_end ? 'AND e.expense_date <= ?' : ''}
      GROUP BY ${groupBy.replace('sr.sale_date', 'e.expense_date')}
    `
    const expenseParams = []
    if (date_start) expenseParams.push(date_start)
    if (date_end) expenseParams.push(date_end)

    const [expenses] = await pool.query(expenseSql, expenseParams)

    const expenseMap = {}
    expenses.forEach(e => { expenseMap[e.period] = e.total_expense })

    const result = rows.map(r => ({
      ...r,
      total_expense: expenseMap[r.period] || 0,
      net_profit: r.gross_profit - (expenseMap[r.period] || 0)
    }))

    const filters = { date_start, date_end }
    const workbook = await exportFinancialSummary(result, period_type, filters)
    const buffer = await workbook.xlsx.writeBuffer()

    const filename = `财务汇总表_${new Date().toISOString().slice(0, 10)}.xlsx`
    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    res.setHeader('Content-Disposition', `attachment; filename="${encodeURIComponent(filename)}"`)
    res.send(buffer)
  } catch (err) { next(err) }
})

export default router

// ============================================
// 资金账户管理 (Fund Accounts)
// ============================================

// GET /api/finance-simple/accounts - 获取账户列表
router.get('/accounts', async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT * FROM fund_accounts ORDER BY id ASC`
    )
    res.json({ code: 0, data: rows })
  } catch (err) { next(err) }
})

// POST /api/finance-simple/accounts - 创建账户
router.post('/accounts', async (req, res, next) => {
  try {
    const { account_name, account_type, account_number, bank_name, balance } = req.body
    if (!account_name || !account_type) {
      return res.status(400).json({ code: 400, message: '账户名称和类型为必填项' })
    }

    const [result] = await pool.query(
      `INSERT INTO fund_accounts (account_name, account_type, account_number, bank_name, balance)
       VALUES (?, ?, ?, ?, ?)`,
      [account_name, account_type, account_number, bank_name, balance || 0]
    )

    res.json({ code: 0, data: { id: result.insertId }, message: '创建成功' })
  } catch (err) { next(err) }
})

// PUT /api/finance-simple/accounts/:id - 更新账户
router.put('/accounts/:id', async (req, res, next) => {
  try {
    const { account_name, account_type, account_number, bank_name, status } = req.body

    await pool.query(
      `UPDATE fund_accounts SET account_name=?, account_type=?, account_number=?, bank_name=?, status=?
       WHERE id=?`,
      [account_name, account_type, account_number, bank_name, status, req.params.id]
    )

    res.json({ code: 0, message: '更新成功' })
  } catch (err) { next(err) }
})

// DELETE /api/finance-simple/accounts/:id - 删除账户
router.delete('/accounts/:id', async (req, res, next) => {
  try {
    // 检查余额是否为0
    const [[account]] = await pool.query('SELECT balance FROM fund_accounts WHERE id = ?', [req.params.id])
    if (!account) {
      return res.status(404).json({ code: 404, message: '账户不存在' })
    }
    if (account.balance !== 0) {
      return res.status(400).json({ code: 400, message: '账户余额不为0，无法删除' })
    }

    await pool.query('DELETE FROM fund_accounts WHERE id = ?', [req.params.id])
    res.json({ code: 0, message: '删除成功' })
  } catch (err) { next(err) }
})

// GET /api/finance-simple/accounts/:id/transactions - 获取账户流水
router.get('/accounts/:id/transactions', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    const { date_start, date_end, transaction_type } = req.query

    let where = 'WHERE ft.account_id = ?'
    const params = [req.params.id], countParams = [req.params.id]

    if (date_start) { where += ' AND ft.transaction_date >= ?'; params.push(date_start); countParams.push(date_start) }
    if (date_end) { where += ' AND ft.transaction_date <= ?'; params.push(date_end); countParams.push(date_end) }
    if (transaction_type) { where += ' AND ft.transaction_type = ?'; params.push(transaction_type); countParams.push(transaction_type) }

    const sql = `
      SELECT ft.*, u.name as creator_name
      FROM fund_transactions ft
      LEFT JOIN users u ON ft.creator_id = u.id
      ${where}
      ORDER BY ft.transaction_date DESC, ft.id DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(*) as total FROM fund_transactions ft ${where}`

    params.push(size, (page - 1) * size)
    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size } })
  } catch (err) { next(err) }
})

// POST /api/finance-simple/accounts/transfer - 账户间转账
router.post('/accounts/transfer', async (req, res, next) => {
  const conn = await pool.getConnection()
  try {
    const { from_account_id, to_account_id, amount, transaction_date, description } = req.body
    if (!from_account_id || !to_account_id || !amount || !transaction_date) {
      return res.status(400).json({ code: 400, message: '必填字段缺失' })
    }

    if (from_account_id === to_account_id) {
      return res.status(400).json({ code: 400, message: '不能转账到同一账户' })
    }

    await conn.beginTransaction()

    // 获取转出账户余额
    const [[fromAccount]] = await conn.query('SELECT balance, account_name FROM fund_accounts WHERE id = ?', [from_account_id])
    if (!fromAccount) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '转出账户不存在' })
    }
    if (fromAccount.balance < amount) {
      await conn.rollback()
      return res.status(400).json({ code: 400, message: '账户余额不足' })
    }

    // 获取转入账户
    const [[toAccount]] = await conn.query('SELECT balance, account_name FROM fund_accounts WHERE id = ?', [to_account_id])
    if (!toAccount) {
      await conn.rollback()
      return res.status(404).json({ code: 404, message: '转入账户不存在' })
    }

    // 更新转出账户余额
    const newFromBalance = fromAccount.balance - amount
    await conn.query('UPDATE fund_accounts SET balance = ? WHERE id = ?', [newFromBalance, from_account_id])

    // 更新转入账户余额
    const newToBalance = toAccount.balance + amount
    await conn.query('UPDATE fund_accounts SET balance = ? WHERE id = ?', [newToBalance, to_account_id])

    // 记录转出流水
    await conn.query(
      `INSERT INTO fund_transactions (account_id, transaction_type, amount, balance_after, related_type, description, transaction_date, creator_id)
       VALUES (?, 'transfer_out', ?, ?, 'transfer', ?, ?, ?)`,
      [from_account_id, -amount, newFromBalance, description || `转账至${toAccount.account_name}`, transaction_date, req.user.id]
    )

    // 记录转入流水
    await conn.query(
      `INSERT INTO fund_transactions (account_id, transaction_type, amount, balance_after, related_type, description, transaction_date, creator_id)
       VALUES (?, 'transfer_in', ?, ?, 'transfer', ?, ?, ?)`,
      [to_account_id, amount, newToBalance, description || `从${fromAccount.account_name}转入`, transaction_date, req.user.id]
    )

    await conn.commit()
    res.json({ code: 0, message: '转账成功' })
  } catch (err) {
    await conn.rollback()
    next(err)
  } finally {
    conn.release()
  }
})

// GET /api/finance-simple/cash-flow - 资金流水汇总（所有账户）
router.get('/cash-flow', async (req, res, next) => {
  try {
    const { page, size } = parsePagination(req.query)
    const { account_id, transaction_type, date_start, date_end } = req.query

    let where = 'WHERE 1=1'
    const params = [], countParams = []

    if (account_id) { where += ' AND ft.account_id = ?'; params.push(account_id); countParams.push(account_id) }
    if (transaction_type) { where += ' AND ft.transaction_type = ?'; params.push(transaction_type); countParams.push(transaction_type) }
    if (date_start) { where += ' AND ft.transaction_date >= ?'; params.push(date_start); countParams.push(date_start) }
    if (date_end) { where += ' AND ft.transaction_date <= ?'; params.push(date_end); countParams.push(date_end) }

    const sql = `
      SELECT ft.*, fa.account_name, fa.account_type, u.name as creator_name
      FROM fund_transactions ft
      LEFT JOIN fund_accounts fa ON ft.account_id = fa.id
      LEFT JOIN users u ON ft.creator_id = u.id
      ${where}
      ORDER BY ft.transaction_date DESC, ft.id DESC
      LIMIT ? OFFSET ?
    `
    const countSql = `SELECT COUNT(*) as total FROM fund_transactions ft ${where}`

    params.push(size, (page - 1) * size)
    const [[{ total }]] = await pool.query(countSql, countParams)
    const [rows] = await pool.query(sql, params)

    res.json({ code: 0, data: { list: rows, total, page, size } })
  } catch (err) { next(err) }
})
