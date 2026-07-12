import cron from 'node-cron'
import { pool } from '../db/connection.js'

// 创建提醒记录
async function createReminder(userId, reminderType, title, content, relatedType, relatedId, priority = 'medium') {
  try {
    await pool.query(
      `INSERT INTO finance_reminders (target_user_id, reminder_type, title, content, related_type, related_id, priority)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [userId, reminderType, title, content, relatedType, relatedId, priority]
    )
  } catch (err) {
    console.error('创建提醒失败:', err)
  }
}

// 检查应付款到期提醒
async function checkPayableDue() {
  try {
    // 获取所有启用了应付款提醒的用户设置
    const [settings] = await pool.query(
      `SELECT user_id, advance_days, threshold_amount
       FROM reminder_settings
       WHERE reminder_type = 'payable_due' AND enabled = TRUE`
    )

    for (const setting of settings) {
      // 查找即将到期的应付款（根据用户设置的提前天数）
      const [payables] = await pool.query(
        `SELECT ap.supplier_id, s.name as supplier_name, MAX(ap.balance) as balance
         FROM accounts_payable ap
         LEFT JOIN suppliers s ON ap.supplier_id = s.id
         WHERE ap.balance > ?
         GROUP BY ap.supplier_id
         HAVING balance > 0`,
        [setting.threshold_amount]
      )

      for (const payable of payables) {
        // 检查是否已经有未读提醒
        const [[existing]] = await pool.query(
          `SELECT id FROM finance_reminders
           WHERE target_user_id = ? AND reminder_type = 'payable_due'
           AND related_type = 'payable' AND related_id = ?
           AND status = 'unread' AND DATE(created_at) = CURDATE()`,
          [setting.user_id, payable.supplier_id]
        )

        if (!existing) {
          const title = `应付款提醒: ${payable.supplier_name}`
          const content = `供货商 ${payable.supplier_name} 当前应付款余额为 ¥${payable.balance.toFixed(2)}，请及时处理。`
          const priority = payable.balance > setting.threshold_amount * 2 ? 'high' : 'medium'

          await createReminder(
            setting.user_id,
            'payable_due',
            title,
            content,
            'payable',
            payable.supplier_id,
            priority
          )
        }
      }
    }

    console.log('应付款到期检查完成')
  } catch (err) {
    console.error('检查应付款到期失败:', err)
  }
}

// 检查应收款逾期提醒
async function checkReceivableOverdue() {
  try {
    const [settings] = await pool.query(
      `SELECT user_id, advance_days, threshold_amount
       FROM reminder_settings
       WHERE reminder_type = 'receivable_overdue' AND enabled = TRUE`
    )

    for (const setting of settings) {
      // 查找逾期的应收款（超过设置的天数未收款）
      const [receivables] = await pool.query(
        `SELECT ar.customer_phone, ar.customer_name, MAX(ar.balance) as balance,
                MAX(ar.transaction_date) as last_transaction_date,
                DATEDIFF(NOW(), MAX(ar.transaction_date)) as overdue_days
         FROM accounts_receivable ar
         WHERE ar.balance > ?
         GROUP BY ar.customer_phone
         HAVING balance > 0 AND overdue_days > ?`,
        [setting.threshold_amount, setting.advance_days]
      )

      for (const receivable of receivables) {
        const [[existing]] = await pool.query(
          `SELECT id FROM finance_reminders
           WHERE target_user_id = ? AND reminder_type = 'receivable_overdue'
           AND related_type = 'receivable' AND related_id = ?
           AND status = 'unread' AND DATE(created_at) = CURDATE()`,
          [setting.user_id, receivable.customer_phone]
        )

        if (!existing) {
          const title = `应收款逾期提醒: ${receivable.customer_name || receivable.customer_phone}`
          const content = `客户 ${receivable.customer_name || receivable.customer_phone} 应收款余额为 ¥${receivable.balance.toFixed(2)}，已逾期 ${receivable.overdue_days} 天，请及时跟进。`
          const priority = receivable.overdue_days > setting.advance_days * 2 ? 'high' : 'medium'

          await createReminder(
            setting.user_id,
            'receivable_overdue',
            title,
            content,
            'receivable',
            receivable.customer_phone,
            priority
          )
        }
      }
    }

    console.log('应收款逾期检查完成')
  } catch (err) {
    console.error('检查应收款逾期失败:', err)
  }
}

// 检查异常支出预警
async function checkExpenseAbnormal() {
  try {
    const [settings] = await pool.query(
      `SELECT user_id, threshold_amount
       FROM reminder_settings
       WHERE reminder_type = 'expense_abnormal' AND enabled = TRUE`
    )

    for (const setting of settings) {
      // 查找今天的异常支出（超过阈值）
      const [expenses] = await pool.query(
        `SELECT e.id, e.record_no, e.category_name, e.amount, e.description, st.name as store_name
         FROM expense_records e
         LEFT JOIN stores st ON e.store_id = st.id
         WHERE DATE(e.expense_date) = CURDATE() AND e.amount > ?`,
        [setting.threshold_amount]
      )

      for (const expense of expenses) {
        const [[existing]] = await pool.query(
          `SELECT id FROM finance_reminders
           WHERE target_user_id = ? AND reminder_type = 'expense_abnormal'
           AND related_type = 'expense' AND related_id = ?
           AND status = 'unread'`,
          [setting.user_id, expense.id]
        )

        if (!existing) {
          const title = `异常支出预警: ${expense.category_name}`
          const content = `${expense.store_name || '总部'} 发生异常支出 ¥${expense.amount.toFixed(2)}，类别: ${expense.category_name}，说明: ${expense.description}。`

          await createReminder(
            setting.user_id,
            'expense_abnormal',
            title,
            content,
            'expense',
            expense.id,
            'high'
          )
        }
      }
    }

    console.log('异常支出检查完成')
  } catch (err) {
    console.error('检查异常支出失败:', err)
  }
}

// 生成月度财务报表提醒
async function generateMonthlyReport() {
  try {
    const [settings] = await pool.query(
      `SELECT user_id FROM reminder_settings
       WHERE reminder_type = 'monthly_report' AND enabled = TRUE`
    )

    // 获取上个月的财务数据
    const [[summary]] = await pool.query(
      `SELECT
         SUM(total_revenue) as total_revenue,
         SUM(total_cost) as total_cost,
         SUM(gross_profit) as gross_profit
       FROM sales_revenues
       WHERE DATE_FORMAT(sale_date, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')`
    )

    const [[expense]] = await pool.query(
      `SELECT SUM(amount) as total_expense
       FROM expense_records
       WHERE approval_status = 'approved'
       AND DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')`
    )

    const lastMonth = new Date()
    lastMonth.setMonth(lastMonth.getMonth() - 1)
    const monthStr = lastMonth.toLocaleDateString('zh-CN', { year: 'numeric', month: 'long' })

    const totalRevenue = summary?.total_revenue || 0
    const totalCost = summary?.total_cost || 0
    const grossProfit = summary?.gross_profit || 0
    const totalExpense = expense?.total_expense || 0
    const netProfit = grossProfit - totalExpense

    for (const setting of settings) {
      const [[existing]] = await pool.query(
        `SELECT id FROM finance_reminders
         WHERE target_user_id = ? AND reminder_type = 'monthly_report'
         AND DATE(created_at) = CURDATE()`,
        [setting.user_id]
      )

      if (!existing) {
        const title = `${monthStr}财务月报`
        const content = `${monthStr}财务概况：\n销售收入: ¥${totalRevenue.toFixed(2)}\n销售成本: ¥${totalCost.toFixed(2)}\n毛利润: ¥${grossProfit.toFixed(2)}\n费用支出: ¥${totalExpense.toFixed(2)}\n净利润: ¥${netProfit.toFixed(2)}`

        await createReminder(
          setting.user_id,
          'monthly_report',
          title,
          content,
          'report',
          null,
          'low'
        )
      }
    }

    console.log('月度报表提醒生成完成')
  } catch (err) {
    console.error('生成月度报表提醒失败:', err)
  }
}

// 启动定时任务
export function startFinanceReminderJobs() {
  // 每天早上9点执行应付款和应收款检查
  cron.schedule('0 9 * * *', async () => {
    console.log('开始执行财务提醒定时任务...')
    await checkPayableDue()
    await checkReceivableOverdue()
  })

  // 每天下午6点执行异常支出检查
  cron.schedule('0 18 * * *', async () => {
    console.log('开始执行异常支出检查...')
    await checkExpenseAbnormal()
  })

  // 每月1号早上9点生成月度报表提醒
  cron.schedule('0 9 1 * *', async () => {
    console.log('开始生成月度财务报表提醒...')
    await generateMonthlyReport()
  })

  console.log('财务提醒定时任务已启动')
}
