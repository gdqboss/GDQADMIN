/**
 * 会计核算工具函数
 * 基于复式记账原理和中国会计准则
 */

import db from '../db/connection.js';

/**
 * 验证借贷平衡
 * @param {Array} entries - 凭证分录数组
 * @returns {Object} { isBalanced: boolean, totalDebit: number, totalCredit: number }
 */
export function validateBalance(entries) {
  const totalDebit = entries.reduce((sum, entry) => sum + parseFloat(entry.debit_amount || 0), 0);
  const totalCredit = entries.reduce((sum, entry) => sum + parseFloat(entry.credit_amount || 0), 0);

  // 允许0.01的误差（浮点数精度问题）
  const isBalanced = Math.abs(totalDebit - totalCredit) < 0.01;

  return {
    isBalanced,
    totalDebit: parseFloat(totalDebit.toFixed(2)),
    totalCredit: parseFloat(totalCredit.toFixed(2))
  };
}

/**
 * 生成凭证号
 * @param {Date} voucherDate - 凭证日期
 * @param {string} prefix - 前缀（记、转、收、付）
 * @returns {Promise<string>} 凭证号
 */
export async function generateVoucherNo(voucherDate, prefix = '记') {
  const date = new Date(voucherDate);
  const yearMonth = `${date.getFullYear()}${String(date.getMonth() + 1).padStart(2, '0')}`;

  const [rows] = await db.query(
    `SELECT voucher_no FROM vouchers
     WHERE voucher_no LIKE ?
     ORDER BY voucher_no DESC LIMIT 1`,
    [`${prefix}-${yearMonth}-%`]
  );

  let sequence = 1;
  if (rows.length > 0) {
    const lastNo = rows[0].voucher_no;
    const lastSeq = parseInt(lastNo.split('-')[2]);
    sequence = lastSeq + 1;
  }

  return `${prefix}-${yearMonth}-${String(sequence).padStart(3, '0')}`;
}

/**
 * 获取当前开放的会计期间
 * @returns {Promise<Object>} 期间对象
 */
export async function getCurrentPeriod() {
  const [rows] = await db.query(
    `SELECT * FROM financial_periods
     WHERE status = 'open'
     ORDER BY period_year DESC, period_month DESC
     LIMIT 1`
  );

  if (rows.length === 0) {
    throw new Error('没有开放的会计期间');
  }

  return rows[0];
}

/**
 * 计算试算平衡表
 * @param {number} periodId - 期间ID
 * @returns {Promise<Object>} 试算平衡数据
 */
export async function calculateTrialBalance(periodId) {
  const [balances] = await db.query(
    `SELECT
      ab.*,
      s.code as subject_code,
      s.name as subject_name,
      s.category,
      s.direction
     FROM account_balances ab
     JOIN accounting_subjects s ON ab.subject_id = s.id
     WHERE ab.period_id = ?
     ORDER BY s.code`,
    [periodId]
  );

  let totalBeginningDebit = 0, totalBeginningCredit = 0;
  let totalPeriodDebit = 0, totalPeriodCredit = 0;
  let totalEndingDebit = 0, totalEndingCredit = 0;

  balances.forEach(row => {
    totalBeginningDebit += parseFloat(row.beginning_debit);
    totalBeginningCredit += parseFloat(row.beginning_credit);
    totalPeriodDebit += parseFloat(row.period_debit);
    totalPeriodCredit += parseFloat(row.period_credit);
    totalEndingDebit += parseFloat(row.ending_debit);
    totalEndingCredit += parseFloat(row.ending_credit);
  });

  return {
    balances,
    totals: {
      beginningDebit: parseFloat(totalBeginningDebit.toFixed(2)),
      beginningCredit: parseFloat(totalBeginningCredit.toFixed(2)),
      periodDebit: parseFloat(totalPeriodDebit.toFixed(2)),
      periodCredit: parseFloat(totalPeriodCredit.toFixed(2)),
      endingDebit: parseFloat(totalEndingDebit.toFixed(2)),
      endingCredit: parseFloat(totalEndingCredit.toFixed(2))
    },
    isBalanced: Math.abs(totalBeginningDebit - totalBeginningCredit) < 0.01 &&
                Math.abs(totalPeriodDebit - totalPeriodCredit) < 0.01 &&
                Math.abs(totalEndingDebit - totalEndingCredit) < 0.01
  };
}

/**
 * 过账凭证 - 更新科目余额
 * @param {number} voucherId - 凭证ID
 * @param {number} userId - 操作人ID
 */
export async function postVoucher(voucherId, userId) {
  const conn = await db.getConnection();

  try {
    await conn.beginTransaction();

    // 获取凭证信息
    const [vouchers] = await conn.query(
      'SELECT * FROM vouchers WHERE id = ? AND status = ?',
      [voucherId, 'approved']
    );

    if (vouchers.length === 0) {
      throw new Error('凭证不存在或未审核');
    }

    const voucher = vouchers[0];

    // 获取凭证分录
    const [entries] = await conn.query(
      'SELECT * FROM voucher_entries WHERE voucher_id = ?',
      [voucherId]
    );

    // 更新科目余额
    for (const entry of entries) {
      await updateAccountBalance(
        conn,
        voucher.period_id,
        entry.subject_id,
        entry.auxiliary_type,
        entry.auxiliary_id,
        entry.debit_amount,
        entry.credit_amount
      );
    }

    // 更新凭证状态
    await conn.query(
      'UPDATE vouchers SET status = ?, posted_by = ?, posted_at = NOW() WHERE id = ?',
      ['posted', userId, voucherId]
    );

    await conn.commit();
  } catch (error) {
    await conn.rollback();
    throw error;
  } finally {
    conn.release();
  }
}

/**
 * 更新科目余额（内部函数）
 */
async function updateAccountBalance(conn, periodId, subjectId, auxiliaryType, auxiliaryId, debitAmount, creditAmount) {
  // 查询科目方向
  const [subjects] = await conn.query(
    'SELECT direction FROM accounting_subjects WHERE id = ?',
    [subjectId]
  );

  if (subjects.length === 0) {
    throw new Error('科目不存在');
  }

  const direction = subjects[0].direction;

  // 查询或创建余额记录
  const [balances] = await conn.query(
    `SELECT * FROM account_balances
     WHERE period_id = ? AND subject_id = ?
     AND (auxiliary_type = ? OR (auxiliary_type IS NULL AND ? IS NULL))
     AND (auxiliary_id = ? OR (auxiliary_id IS NULL AND ? IS NULL))`,
    [periodId, subjectId, auxiliaryType, auxiliaryType, auxiliaryId, auxiliaryId]
  );

  if (balances.length === 0) {
    // 创建新余额记录
    await conn.query(
      `INSERT INTO account_balances
       (period_id, subject_id, auxiliary_type, auxiliary_id, period_debit, period_credit, ending_debit, ending_credit, year_debit, year_credit)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        periodId, subjectId, auxiliaryType, auxiliaryId,
        debitAmount, creditAmount,
        direction === 'debit' ? debitAmount - creditAmount : 0,
        direction === 'credit' ? creditAmount - debitAmount : 0,
        debitAmount, creditAmount
      ]
    );
  } else {
    // 更新余额
    const balance = balances[0];
    const newPeriodDebit = parseFloat(balance.period_debit) + parseFloat(debitAmount);
    const newPeriodCredit = parseFloat(balance.period_credit) + parseFloat(creditAmount);
    const newYearDebit = parseFloat(balance.year_debit) + parseFloat(debitAmount);
    const newYearCredit = parseFloat(balance.year_credit) + parseFloat(creditAmount);

    let newEndingDebit = 0, newEndingCredit = 0;
    const beginningBalance = parseFloat(balance.beginning_debit) - parseFloat(balance.beginning_credit);
    const periodChange = newPeriodDebit - newPeriodCredit;
    const endingBalance = beginningBalance + periodChange;

    if (direction === 'debit') {
      newEndingDebit = endingBalance > 0 ? endingBalance : 0;
      newEndingCredit = endingBalance < 0 ? -endingBalance : 0;
    } else {
      newEndingCredit = endingBalance > 0 ? endingBalance : 0;
      newEndingDebit = endingBalance < 0 ? -endingBalance : 0;
    }

    await conn.query(
      `UPDATE account_balances
       SET period_debit = ?, period_credit = ?,
           ending_debit = ?, ending_credit = ?,
           year_debit = ?, year_credit = ?
       WHERE id = ?`,
      [newPeriodDebit, newPeriodCredit, newEndingDebit, newEndingCredit, newYearDebit, newYearCredit, balance.id]
    );
  }
}

/**
 * 从采购单自动生成凭证
 * @param {Object} purchaseOrder - 采购单对象
 * @param {number} userId - 操作人ID
 * @returns {Promise<number>} 凭证ID
 */
export async function generateVoucherFromPurchase(purchaseOrder, userId) {
  const conn = await db.getConnection();

  try {
    await conn.beginTransaction();

    const period = await getCurrentPeriod();
    const voucherNo = await generateVoucherNo(purchaseOrder.order_date, '记');

    // 创建凭证主表
    const [voucherResult] = await conn.query(
      `INSERT INTO vouchers
       (voucher_no, voucher_date, period_id, voucher_type, source_type, source_id,
        total_debit, total_credit, entry_count, status, creator_id)
       VALUES (?, ?, ?, 'auto', 'purchase', ?, ?, ?, 3, 'draft', ?)`,
      [
        voucherNo,
        purchaseOrder.order_date,
        period.id,
        purchaseOrder.id,
        purchaseOrder.total_with_tax,
        purchaseOrder.total_with_tax,
        userId
      ]
    );

    const voucherId = voucherResult.insertId;

    // 借：库存商品
    await conn.query(
      `INSERT INTO voucher_entries
       (voucher_id, entry_no, subject_id, summary, debit_amount, credit_amount)
       SELECT ?, 1, id, ?, ?, 0
       FROM accounting_subjects WHERE code = '1405'`,
      [voucherId, `采购商品：${purchaseOrder.product_name}`, purchaseOrder.total_amount]
    );

    // 借：应交税费-应交增值税（进项税额）
    await conn.query(
      `INSERT INTO voucher_entries
       (voucher_id, entry_no, subject_id, summary, debit_amount, credit_amount)
       SELECT ?, 2, id, '进项税额', ?, 0
       FROM accounting_subjects WHERE code = '2221'`,
      [voucherId, purchaseOrder.tax_amount]
    );

    // 贷：应付账款
    await conn.query(
      `INSERT INTO voucher_entries
       (voucher_id, entry_no, subject_id, summary, debit_amount, credit_amount, auxiliary_type, auxiliary_id, auxiliary_name)
       SELECT ?, 3, id, ?, 0, ?,'supplier', ?, ?
       FROM accounting_subjects WHERE code = '2202'`,
      [voucherId, `应付账款：${purchaseOrder.supplier_name}`, purchaseOrder.total_with_tax, purchaseOrder.supplier_id, purchaseOrder.supplier_name]
    );

    await conn.commit();
    return voucherId;
  } catch (error) {
    await conn.rollback();
    throw error;
  } finally {
    conn.release();
  }
}

/**
 * 从销售单自动生成凭证
 * @param {Object} saleOrder - 销售单对象
 * @param {number} userId - 操作人ID
 * @returns {Promise<number>} 凭证ID
 */
export async function generateVoucherFromSale(saleOrder, userId) {
  const conn = await db.getConnection();

  try {
    await conn.beginTransaction();

    const period = await getCurrentPeriod();
    const voucherNo = await generateVoucherNo(saleOrder.order_date, '记');

    const totalAmount = parseFloat(saleOrder.total_with_tax);

    // 创建凭证主表
    const [voucherResult] = await conn.query(
      `INSERT INTO vouchers
       (voucher_no, voucher_date, period_id, voucher_type, source_type, source_id,
        total_debit, total_credit, entry_count, status, creator_id)
       VALUES (?, ?, ?, 'auto', 'sale', ?, ?, ?, 5, 'draft', ?)`,
      [voucherNo, saleOrder.order_date, period.id, saleOrder.id, totalAmount + saleOrder.cost_amount, totalAmount + saleOrder.cost_amount, userId]
    );

    const voucherId = voucherResult.insertId;

    // 借：应收账款
    await conn.query(
      `INSERT INTO voucher_entries
       (voucher_id, entry_no, subject_id, summary, debit_amount, credit_amount, auxiliary_type, auxiliary_id, auxiliary_name)
       SELECT ?, 1, id, ?, ?, 0, 'customer', ?, ?
       FROM accounting_subjects WHERE code = '1122'`,
      [voucherId, `销售商品：${saleOrder.product_name}`, totalAmount, saleOrder.customer_id, saleOrder.customer_name]
    );

    // 贷：主营业务收入
    await conn.query(
      `INSERT INTO voucher_entries
       (voucher_id, entry_no, subject_id, summary, debit_amount, credit_amount)
       SELECT ?, 2, id, '主营业务收入', 0, ?
       FROM accounting_subjects WHERE code = '5001'`,
      [voucherId, saleOrder.total_amount]
    );

    // 贷：应交税费-应交增值税（销项税额）
    await conn.query(
      `INSERT INTO voucher_entries
       (voucher_id, entry_no, subject_id, summary, debit_amount, credit_amount)
       SELECT ?, 3, id, '销项税额', 0, ?
       FROM accounting_subjects WHERE code = '2221'`,
      [voucherId, saleOrder.tax_amount]
    );

    // 借：主营业务成本
    await conn.query(
      `INSERT INTO voucher_entries
       (voucher_id, entry_no, subject_id, summary, debit_amount, credit_amount)
       SELECT ?, 4, id, '结转销售成本', ?, 0
       FROM accounting_subjects WHERE code = '5401'`,
      [voucherId, saleOrder.cost_amount]
    );

    // 贷：库存商品
    await conn.query(
      `INSERT INTO voucher_entries
       (voucher_id, entry_no, subject_id, summary, debit_amount, credit_amount)
       SELECT ?, 5, id, '库存商品', 0, ?
       FROM accounting_subjects WHERE code = '1405'`,
      [voucherId, saleOrder.cost_amount]
    );

    await conn.commit();
    return voucherId;
  } catch (error) {
    await conn.rollback();
    throw error;
  } finally {
    conn.release();
  }
}

/**
 * 计算资产负债表
 * @param {number} periodId - 期间ID
 * @returns {Promise<Object>} 资产负债表数据
 */
export async function calculateBalanceSheet(periodId) {
  const [balances] = await db.query(
    `SELECT
      s.code, s.name, s.category, s.direction,
      ab.ending_debit, ab.ending_credit
     FROM account_balances ab
     JOIN accounting_subjects s ON ab.subject_id = s.id
     WHERE ab.period_id = ? AND s.category IN ('asset', 'liability', 'equity')
     ORDER BY s.code`,
    [periodId]
  );

  const assets = {};
  const liabilities = {};
  const equity = {};

  balances.forEach(row => {
    const amount = parseFloat(row.ending_debit) - parseFloat(row.ending_credit);

    if (row.category === 'asset') {
      assets[row.code] = { name: row.name, amount };
    } else if (row.category === 'liability') {
      liabilities[row.code] = { name: row.name, amount: -amount };
    } else if (row.category === 'equity') {
      equity[row.code] = { name: row.name, amount: -amount };
    }
  });

  const totalAssets = Object.values(assets).reduce((sum, item) => sum + item.amount, 0);
  const totalLiabilities = Object.values(liabilities).reduce((sum, item) => sum + item.amount, 0);
  const totalEquity = Object.values(equity).reduce((sum, item) => sum + item.amount, 0);

  return {
    assets,
    liabilities,
    equity,
    totals: {
      assets: parseFloat(totalAssets.toFixed(2)),
      liabilities: parseFloat(totalLiabilities.toFixed(2)),
      equity: parseFloat(totalEquity.toFixed(2)),
      liabilitiesAndEquity: parseFloat((totalLiabilities + totalEquity).toFixed(2))
    }
  };
}

/**
 * 计算利润表
 * @param {number} startPeriodId - 开始期间ID
 * @param {number} endPeriodId - 结束期间ID
 * @returns {Promise<Object>} 利润表数据
 */
export async function calculateIncomeStatement(startPeriodId, endPeriodId) {
  const [balances] = await db.query(
    `SELECT
      s.code, s.name, s.direction,
      SUM(ab.period_debit) as total_debit,
      SUM(ab.period_credit) as total_credit
     FROM account_balances ab
     JOIN accounting_subjects s ON ab.subject_id = s.id
     WHERE ab.period_id BETWEEN ? AND ? AND s.category = 'profit_loss'
     GROUP BY s.id, s.code, s.name, s.direction
     ORDER BY s.code`,
    [startPeriodId, endPeriodId]
  );

  let revenue = 0;
  let cost = 0;
  let expenses = 0;

  const details = {};

  balances.forEach(row => {
    const amount = parseFloat(row.total_credit) - parseFloat(row.total_debit);
    details[row.code] = { name: row.name, amount };

    if (row.code.startsWith('5') && row.direction === 'credit') {
      revenue += amount;
    } else if (row.code.startsWith('5') && row.direction === 'debit') {
      cost += -amount;
    } else if (row.code.startsWith('6')) {
      expenses += -amount;
    }
  });

  const grossProfit = revenue - cost;
  const operatingProfit = grossProfit - expenses;
  const netProfit = operatingProfit;

  return {
    details,
    summary: {
      revenue: parseFloat(revenue.toFixed(2)),
      cost: parseFloat(cost.toFixed(2)),
      grossProfit: parseFloat(grossProfit.toFixed(2)),
      expenses: parseFloat(expenses.toFixed(2)),
      operatingProfit: parseFloat(operatingProfit.toFixed(2)),
      netProfit: parseFloat(netProfit.toFixed(2))
    }
  };
}

/**
 * 期末结账
 * @param {number} periodId - 期间ID
 * @param {number} userId - 操作人ID
 */
export async function closePeriod(periodId, userId) {
  const conn = await db.getConnection();

  try {
    await conn.beginTransaction();

    // 检查是否有未过账凭证
    const [unpostedVouchers] = await conn.query(
      `SELECT COUNT(*) as count FROM vouchers
       WHERE period_id = ? AND status != 'posted'`,
      [periodId]
    );

    if (unpostedVouchers[0].count > 0) {
      throw new Error('存在未过账凭证，无法结账');
    }

    // 结转损益
    await transferProfitLoss(conn, periodId, userId);

    // 更新期间状态
    await conn.query(
      'UPDATE financial_periods SET status = ?, closed_by = ?, closed_at = NOW() WHERE id = ?',
      ['closed', userId, periodId]
    );

    // 创建下期期初余额
    await createNextPeriodBeginningBalance(conn, periodId);

    await conn.commit();
  } catch (error) {
    await conn.rollback();
    throw error;
  } finally {
    conn.release();
  }
}

/**
 * 结转损益（内部函数）
 */
async function transferProfitLoss(conn, periodId, userId) {
  // 获取损益类科目余额
  const [balances] = await conn.query(
    `SELECT ab.*, s.code, s.name, s.direction
     FROM account_balances ab
     JOIN accounting_subjects s ON ab.subject_id = s.id
     WHERE ab.period_id = ? AND s.category = 'profit_loss'`,
    [periodId]
  );

  if (balances.length === 0) return;

  const [periods] = await conn.query('SELECT * FROM financial_periods WHERE id = ?', [periodId]);
  const period = periods[0];

  const voucherNo = await generateVoucherNo(period.end_date, '转');

  let totalDebit = 0;
  let totalCredit = 0;

  // 创建结转凭证
  const [voucherResult] = await conn.query(
    `INSERT INTO vouchers
     (voucher_no, voucher_date, period_id, voucher_type, total_debit, total_credit, status, creator_id)
     VALUES (?, ?, ?, 'transfer', 0, 0, 'posted', ?)`,
    [voucherNo, period.end_date, periodId, userId]
  );

  const voucherId = voucherResult.insertId;
  let entryNo = 1;

  // 收入类科目结转
  for (const balance of balances) {
    const netAmount = parseFloat(balance.ending_credit) - parseFloat(balance.ending_debit);

    if (netAmount > 0) {
      // 贷方余额，借记该科目，贷记本年利润
      await conn.query(
        `INSERT INTO voucher_entries
         (voucher_id, entry_no, subject_id, summary, debit_amount, credit_amount)
         VALUES (?, ?, ?, '结转损益', ?, 0)`,
        [voucherId, entryNo++, balance.subject_id, netAmount]
      );
      totalDebit += netAmount;
    } else if (netAmount < 0) {
      // 借方余额，贷记该科目，借记本年利润
      await conn.query(
        `INSERT INTO voucher_entries
         (voucher_id, entry_no, subject_id, summary, debit_amount, credit_amount)
         VALUES (?, ?, ?, '结转损益', 0, ?)`,
        [voucherId, entryNo++, balance.subject_id, -netAmount]
      );
      totalCredit += -netAmount;
    }
  }

  // 结转到本年利润
  const [profitSubject] = await conn.query(
    `SELECT id FROM accounting_subjects WHERE code = '3104'`
  );

  if (profitSubject.length > 0) {
    const netProfit = totalCredit - totalDebit;

    if (netProfit > 0) {
      await conn.query(
        `INSERT INTO voucher_entries
         (voucher_id, entry_no, subject_id, summary, debit_amount, credit_amount)
         VALUES (?, ?, ?, '结转本年利润', 0, ?)`,
        [voucherId, entryNo, profitSubject[0].id, netProfit]
      );
      totalCredit += netProfit;
    } else if (netProfit < 0) {
      await conn.query(
        `INSERT INTO voucher_entries
         (voucher_id, entry_no, subject_id, summary, debit_amount, credit_amount)
         VALUES (?, ?, ?, '结转本年利润', ?, 0)`,
        [voucherId, entryNo, profitSubject[0].id, -netProfit]
      );
      totalDebit += -netProfit;
    }
  }

  // 更新凭证合计
  await conn.query(
    `UPDATE vouchers SET total_debit = ?, total_credit = ?, entry_count = ?, posted_by = ?, posted_at = NOW() WHERE id = ?`,
    [totalDebit, totalCredit, entryNo, userId, voucherId]
  );
}

/**
 * 创建下期期初余额（内部函数）
 */
async function createNextPeriodBeginningBalance(conn, currentPeriodId) {
  // 获取当前期间
  const [currentPeriods] = await conn.query(
    'SELECT * FROM financial_periods WHERE id = ?',
    [currentPeriodId]
  );

  if (currentPeriods.length === 0) return;

  const currentPeriod = currentPeriods[0];

  // 查找下期
  const [nextPeriods] = await conn.query(
    `SELECT * FROM financial_periods
     WHERE (period_year = ? AND period_month = ?)
        OR (period_year = ? AND period_month = 1)
     LIMIT 1`,
    [
      currentPeriod.period_year,
      currentPeriod.period_month + 1,
      currentPeriod.period_year + 1
    ]
  );

  if (nextPeriods.length === 0) return;

  const nextPeriod = nextPeriods[0];

  // 复制当前期末余额到下期期初
  await conn.query(
    `INSERT INTO account_balances
     (period_id, subject_id, auxiliary_type, auxiliary_id, beginning_debit, beginning_credit)
     SELECT ?, subject_id, auxiliary_type, auxiliary_id, ending_debit, ending_credit
     FROM account_balances
     WHERE period_id = ?`,
    [nextPeriod.id, currentPeriodId]
  );
}

export default {
  validateBalance,
  generateVoucherNo,
  getCurrentPeriod,
  calculateTrialBalance,
  postVoucher,
  generateVoucherFromPurchase,
  generateVoucherFromSale,
  calculateBalanceSheet,
  calculateIncomeStatement,
  closePeriod
};
