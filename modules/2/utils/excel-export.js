import ExcelJS from 'exceljs'

/**
 * 生成供货商对账单Excel
 */
export async function exportSupplierStatement(data) {
  const workbook = new ExcelJS.Workbook()
  const worksheet = workbook.addWorksheet('供货商对账单')

  // 设置列宽
  worksheet.columns = [
    { width: 15 },
    { width: 20 },
    { width: 15 },
    { width: 15 },
    { width: 15 },
  ]

  // 标题
  worksheet.mergeCells('A1:E1')
  const titleCell = worksheet.getCell('A1')
  titleCell.value = '供货商对账单'
  titleCell.font = { size: 16, bold: true }
  titleCell.alignment = { horizontal: 'center', vertical: 'middle' }
  worksheet.getRow(1).height = 30

  // 供货商信息
  worksheet.getCell('A3').value = '供货商名称：'
  worksheet.getCell('B3').value = data.supplier_name
  worksheet.getCell('A4').value = '联系人：'
  worksheet.getCell('B4').value = data.contact_person || '-'
  worksheet.getCell('A5').value = '联系电话：'
  worksheet.getCell('B5').value = data.phone || '-'
  worksheet.getCell('A6').value = '对账期间：'
  worksheet.getCell('B6').value = `${data.start_date} 至 ${data.end_date}`

  // 期初余额
  worksheet.getCell('A8').value = '期初应付款余额：'
  worksheet.getCell('B8').value = `¥${data.opening_balance.toFixed(2)}`
  worksheet.getCell('B8').font = { bold: true, color: { argb: 'FFFF0000' } }

  // 采购明细表头
  let currentRow = 10
  worksheet.getCell(`A${currentRow}`).value = '采购明细'
  worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 12 }
  currentRow++

  const purchaseHeaders = ['日期', '单号', '商品', '数量', '金额']
  purchaseHeaders.forEach((header, index) => {
    const cell = worksheet.getCell(currentRow, index + 1)
    cell.value = header
    cell.font = { bold: true }
    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE0E0E0' } }
    cell.border = {
      top: { style: 'thin' },
      left: { style: 'thin' },
      bottom: { style: 'thin' },
      right: { style: 'thin' }
    }
  })
  currentRow++

  // 采购明细数据
  data.purchases.forEach(item => {
    worksheet.getCell(currentRow, 1).value = item.purchase_date
    worksheet.getCell(currentRow, 2).value = item.record_no
    worksheet.getCell(currentRow, 3).value = item.product_name
    worksheet.getCell(currentRow, 4).value = item.quantity
    worksheet.getCell(currentRow, 5).value = `¥${item.total_amount.toFixed(2)}`
    currentRow++
  })

  // 采购小计
  worksheet.getCell(currentRow, 4).value = '小计：'
  worksheet.getCell(currentRow, 4).font = { bold: true }
  worksheet.getCell(currentRow, 5).value = `¥${data.total_purchases.toFixed(2)}`
  worksheet.getCell(currentRow, 5).font = { bold: true, color: { argb: 'FFFF0000' } }
  currentRow += 2

  // 付款明细表头
  worksheet.getCell(`A${currentRow}`).value = '付款明细'
  worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 12 }
  currentRow++

  const paymentHeaders = ['日期', '付款方式', '金额', '', '']
  paymentHeaders.forEach((header, index) => {
    if (header) {
      const cell = worksheet.getCell(currentRow, index + 1)
      cell.value = header
      cell.font = { bold: true }
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE0E0E0' } }
      cell.border = {
        top: { style: 'thin' },
        left: { style: 'thin' },
        bottom: { style: 'thin' },
        right: { style: 'thin' }
      }
    }
  })
  currentRow++

  // 付款明细数据
  data.payments.forEach(item => {
    worksheet.getCell(currentRow, 1).value = item.payment_date
    worksheet.getCell(currentRow, 2).value = item.payment_method
    worksheet.getCell(currentRow, 3).value = `¥${item.amount.toFixed(2)}`
    currentRow++
  })

  // 付款小计
  worksheet.getCell(currentRow, 2).value = '小计：'
  worksheet.getCell(currentRow, 2).font = { bold: true }
  worksheet.getCell(currentRow, 3).value = `¥${data.total_payments.toFixed(2)}`
  worksheet.getCell(currentRow, 3).font = { bold: true, color: { argb: 'FF00AA00' } }
  currentRow += 2

  // 期末余额
  worksheet.getCell(`A${currentRow}`).value = '期末应付款余额：'
  worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 12 }
  worksheet.getCell(`B${currentRow}`).value = `¥${data.closing_balance.toFixed(2)}`
  worksheet.getCell(`B${currentRow}`).font = { bold: true, size: 12, color: { argb: 'FFFF0000' } }

  return await workbook.xlsx.writeBuffer()
}

/**
 * 生成客户对账单Excel
 */
export async function exportCustomerStatement(data) {
  const workbook = new ExcelJS.Workbook()
  const worksheet = workbook.addWorksheet('客户对账单')

  // 设置列宽
  worksheet.columns = [
    { width: 15 },
    { width: 20 },
    { width: 15 },
    { width: 15 },
    { width: 15 },
  ]

  // 标题
  worksheet.mergeCells('A1:E1')
  const titleCell = worksheet.getCell('A1')
  titleCell.value = '客户对账单'
  titleCell.font = { size: 16, bold: true }
  titleCell.alignment = { horizontal: 'center', vertical: 'middle' }
  worksheet.getRow(1).height = 30

  // 客户信息
  worksheet.getCell('A3').value = '客户姓名：'
  worksheet.getCell('B3').value = data.customer_name || '-'
  worksheet.getCell('A4').value = '联系电话：'
  worksheet.getCell('B4').value = data.customer_phone
  worksheet.getCell('A5').value = '对账期间：'
  worksheet.getCell('B5').value = `${data.start_date} 至 ${data.end_date}`

  // 期初余额
  worksheet.getCell('A7').value = '期初应收款余额：'
  worksheet.getCell('B7').value = `¥${data.opening_balance.toFixed(2)}`
  worksheet.getCell('B7').font = { bold: true, color: { argb: 'FF00AA00' } }

  // 销售明细表头
  let currentRow = 9
  worksheet.getCell(`A${currentRow}`).value = '销售明细'
  worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 12 }
  currentRow++

  const salesHeaders = ['日期', '单号', '商品', '数量', '金额']
  salesHeaders.forEach((header, index) => {
    const cell = worksheet.getCell(currentRow, index + 1)
    cell.value = header
    cell.font = { bold: true }
    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE0E0E0' } }
    cell.border = {
      top: { style: 'thin' },
      left: { style: 'thin' },
      bottom: { style: 'thin' },
      right: { style: 'thin' }
    }
  })
  currentRow++

  // 销售明细数据
  data.sales.forEach(item => {
    worksheet.getCell(currentRow, 1).value = item.sale_date
    worksheet.getCell(currentRow, 2).value = item.record_no
    worksheet.getCell(currentRow, 3).value = item.product_name
    worksheet.getCell(currentRow, 4).value = item.quantity
    worksheet.getCell(currentRow, 5).value = `¥${item.total_revenue.toFixed(2)}`
    currentRow++
  })

  // 销售小计
  worksheet.getCell(currentRow, 4).value = '小计：'
  worksheet.getCell(currentRow, 4).font = { bold: true }
  worksheet.getCell(currentRow, 5).value = `¥${data.total_sales.toFixed(2)}`
  worksheet.getCell(currentRow, 5).font = { bold: true, color: { argb: 'FF00AA00' } }
  currentRow += 2

  // 收款明细表头
  worksheet.getCell(`A${currentRow}`).value = '收款明细'
  worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 12 }
  currentRow++

  const receiptHeaders = ['日期', '收款方式', '金额', '', '']
  receiptHeaders.forEach((header, index) => {
    if (header) {
      const cell = worksheet.getCell(currentRow, index + 1)
      cell.value = header
      cell.font = { bold: true }
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE0E0E0' } }
      cell.border = {
        top: { style: 'thin' },
        left: { style: 'thin' },
        bottom: { style: 'thin' },
        right: { style: 'thin' }
      }
    }
  })
  currentRow++

  // 收款明细数据
  data.receipts.forEach(item => {
    worksheet.getCell(currentRow, 1).value = item.receipt_date
    worksheet.getCell(currentRow, 2).value = item.payment_method
    worksheet.getCell(currentRow, 3).value = `¥${item.amount.toFixed(2)}`
    currentRow++
  })

  // 收款小计
  worksheet.getCell(currentRow, 2).value = '小计：'
  worksheet.getCell(currentRow, 2).font = { bold: true }
  worksheet.getCell(currentRow, 3).value = `¥${data.total_receipts.toFixed(2)}`
  worksheet.getCell(currentRow, 3).font = { bold: true, color: { argb: 'FFFF0000' } }
  currentRow += 2

  // 期末余额
  worksheet.getCell(`A${currentRow}`).value = '期末应收款余额：'
  worksheet.getCell(`A${currentRow}`).font = { bold: true, size: 12 }
  worksheet.getCell(`B${currentRow}`).value = `¥${data.closing_balance.toFixed(2)}`
  worksheet.getCell(`B${currentRow}`).font = { bold: true, size: 12, color: { argb: 'FF00AA00' } }

  return await workbook.xlsx.writeBuffer()
}

// 通用样式配置
const HEADER_STYLE = {
  font: { bold: true, size: 12, color: { argb: 'FFFFFFFF' } },
  fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF4F46E5' } },
  alignment: { vertical: 'middle', horizontal: 'center' },
  border: {
    top: { style: 'thin' },
    left: { style: 'thin' },
    bottom: { style: 'thin' },
    right: { style: 'thin' }
  }
}

const TITLE_STYLE = {
  font: { bold: true, size: 14 },
  alignment: { vertical: 'middle', horizontal: 'center' }
}

const CELL_STYLE = {
  alignment: { vertical: 'middle', horizontal: 'left' },
  border: {
    top: { style: 'thin', color: { argb: 'FFE5E7EB' } },
    left: { style: 'thin', color: { argb: 'FFE5E7EB' } },
    bottom: { style: 'thin', color: { argb: 'FFE5E7EB' } },
    right: { style: 'thin', color: { argb: 'FFE5E7EB' } }
  }
}

const TOTAL_STYLE = {
  font: { bold: true },
  fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF9FAFB' } },
  alignment: { vertical: 'middle', horizontal: 'left' },
  border: {
    top: { style: 'thin' },
    left: { style: 'thin' },
    bottom: { style: 'thin' },
    right: { style: 'thin' }
  }
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('zh-CN')
}

function addFilterInfo(worksheet, filters, startRow) {
  const filterTexts = []
  if (filters.date_start || filters.date_end) {
    filterTexts.push(`日期范围: ${filters.date_start || '不限'} 至 ${filters.date_end || '不限'}`)
  }
  if (filters.supplier_name) filterTexts.push(`供货商: ${filters.supplier_name}`)
  if (filters.store_name) filterTexts.push(`门店: ${filters.store_name}`)
  if (filters.product_name) filterTexts.push(`商品: ${filters.product_name}`)
  if (filters.category) filterTexts.push(`分类: ${filters.category}`)
  if (filters.payment_status) filterTexts.push(`付款状态: ${filters.payment_status}`)
  if (filters.approval_status) filterTexts.push(`审批状态: ${filters.approval_status}`)

  if (filterTexts.length > 0) {
    const filterRow = worksheet.getRow(startRow)
    filterRow.getCell(1).value = '筛选条件: ' + filterTexts.join(' | ')
    filterRow.getCell(1).font = { size: 10, color: { argb: 'FF6B7280' } }
    return startRow + 1
  }
  return startRow
}

// 采购成本导出
export async function exportPurchaseCosts(data, filters = {}) {
  const workbook = new ExcelJS.Workbook()
  const worksheet = workbook.addWorksheet('采购成本明细')

  worksheet.mergeCells('A1:H1')
  const titleCell = worksheet.getCell('A1')
  titleCell.value = '采购成本明细表'
  titleCell.style = TITLE_STYLE
  worksheet.getRow(1).height = 25

  worksheet.mergeCells('A2:H2')
  const timeCell = worksheet.getCell('A2')
  timeCell.value = `导出时间: ${new Date().toLocaleString('zh-CN')}`
  timeCell.font = { size: 10, color: { argb: 'FF6B7280' } }

  let currentRow = 3
  currentRow = addFilterInfo(worksheet, filters, currentRow)
  currentRow++

  const headers = ['记录编号', '采购日期', '供货商', '商品名称', '数量', '单价', '总金额', '付款状态']
  const headerRow = worksheet.getRow(currentRow)
  headers.forEach((header, idx) => {
    const cell = headerRow.getCell(idx + 1)
    cell.value = header
    cell.style = HEADER_STYLE
  })
  headerRow.height = 20
  currentRow++

  let totalAmount = 0
  data.forEach(record => {
    const row = worksheet.getRow(currentRow)
    row.getCell(1).value = record.record_no
    row.getCell(2).value = formatDate(record.purchase_date)
    row.getCell(3).value = record.supplier_name
    row.getCell(4).value = record.product_name + (record.product_spec ? ` (${record.product_spec})` : '')
    row.getCell(5).value = record.quantity
    row.getCell(6).value = Number(record.unit_price)
    row.getCell(7).value = Number(record.total_amount)
    row.getCell(8).value = record.payment_status === 'paid' ? '已付款' : record.payment_status === 'partial' ? '部分付款' : '未付款'

    row.eachCell({ includeEmpty: true }, cell => {
      cell.style = CELL_STYLE
    })

    totalAmount += Number(record.total_amount)
    currentRow++
  })

  const totalRow = worksheet.getRow(currentRow)
  totalRow.getCell(1).value = '合计'
  totalRow.getCell(7).value = totalAmount
  totalRow.eachCell({ includeEmpty: true }, cell => {
    cell.style = TOTAL_STYLE
  })

  worksheet.columns = [
    { width: 18 }, { width: 12 }, { width: 20 }, { width: 30 },
    { width: 10 }, { width: 12 }, { width: 15 }, { width: 12 }
  ]

  for (let i = currentRow - data.length; i < currentRow; i++) {
    worksheet.getRow(i).getCell(6).numFmt = '¥#,##0.00'
    worksheet.getRow(i).getCell(7).numFmt = '¥#,##0.00'
  }
  totalRow.getCell(7).numFmt = '¥#,##0.00'

  return workbook
}

// 销售收入导出
export async function exportSalesRevenues(data, filters = {}) {
  const workbook = new ExcelJS.Workbook()
  const worksheet = workbook.addWorksheet('销售收入明细')

  worksheet.mergeCells('A1:H1')
  const titleCell = worksheet.getCell('A1')
  titleCell.value = '销售收入明细表'
  titleCell.style = TITLE_STYLE
  worksheet.getRow(1).height = 25

  worksheet.mergeCells('A2:H2')
  const timeCell = worksheet.getCell('A2')
  timeCell.value = `导出时间: ${new Date().toLocaleString('zh-CN')}`
  timeCell.font = { size: 10, color: { argb: 'FF6B7280' } }

  let currentRow = 3
  currentRow = addFilterInfo(worksheet, filters, currentRow)
  currentRow++

  const headers = ['记录编号', '销售日期', '门店', '商品名称', '数量', '售价', '成本价', '毛利润']
  const headerRow = worksheet.getRow(currentRow)
  headers.forEach((header, idx) => {
    const cell = headerRow.getCell(idx + 1)
    cell.value = header
    cell.style = HEADER_STYLE
  })
  headerRow.height = 20
  currentRow++

  let totalRevenue = 0, totalCost = 0, totalProfit = 0
  data.forEach(record => {
    const row = worksheet.getRow(currentRow)
    row.getCell(1).value = record.record_no
    row.getCell(2).value = formatDate(record.sale_date)
    row.getCell(3).value = record.store_name || '-'
    row.getCell(4).value = record.product_name + (record.product_spec ? ` (${record.product_spec})` : '')
    row.getCell(5).value = record.quantity
    row.getCell(6).value = Number(record.sale_price)
    row.getCell(7).value = Number(record.cost_price)
    row.getCell(8).value = Number(record.gross_profit)

    row.eachCell({ includeEmpty: true }, cell => {
      cell.style = CELL_STYLE
    })

    totalRevenue += Number(record.sale_price) * record.quantity
    totalCost += Number(record.cost_price) * record.quantity
    totalProfit += Number(record.gross_profit)
    currentRow++
  })

  const totalRow = worksheet.getRow(currentRow)
  totalRow.getCell(1).value = '合计'
  totalRow.getCell(6).value = totalRevenue
  totalRow.getCell(7).value = totalCost
  totalRow.getCell(8).value = totalProfit
  totalRow.eachCell({ includeEmpty: true }, cell => {
    cell.style = TOTAL_STYLE
  })

  worksheet.columns = [
    { width: 18 }, { width: 12 }, { width: 20 }, { width: 30 },
    { width: 10 }, { width: 12 }, { width: 12 }, { width: 15 }
  ]

  for (let i = currentRow - data.length; i < currentRow; i++) {
    worksheet.getRow(i).getCell(6).numFmt = '¥#,##0.00'
    worksheet.getRow(i).getCell(7).numFmt = '¥#,##0.00'
    worksheet.getRow(i).getCell(8).numFmt = '¥#,##0.00'
  }
  totalRow.getCell(6).numFmt = '¥#,##0.00'
  totalRow.getCell(7).numFmt = '¥#,##0.00'
  totalRow.getCell(8).numFmt = '¥#,##0.00'

  return workbook
}

// 费用支出导出
export async function exportExpenses(data, filters = {}) {
  const workbook = new ExcelJS.Workbook()
  const worksheet = workbook.addWorksheet('费用支出明细')

  worksheet.mergeCells('A1:H1')
  const titleCell = worksheet.getCell('A1')
  titleCell.value = '费用支出明细表'
  titleCell.style = TITLE_STYLE
  worksheet.getRow(1).height = 25

  worksheet.mergeCells('A2:H2')
  const timeCell = worksheet.getCell('A2')
  timeCell.value = `导出时间: ${new Date().toLocaleString('zh-CN')}`
  timeCell.font = { size: 10, color: { argb: 'FF6B7280' } }

  let currentRow = 3
  currentRow = addFilterInfo(worksheet, filters, currentRow)
  currentRow++

  const headers = ['记录编号', '支出日期', '分类', '描述', '金额', '付款方式', '门店', '审批状态']
  const headerRow = worksheet.getRow(currentRow)
  headers.forEach((header, idx) => {
    const cell = headerRow.getCell(idx + 1)
    cell.value = header
    cell.style = HEADER_STYLE
  })
  headerRow.height = 20
  currentRow++

  let totalAmount = 0
  data.forEach(record => {
    const row = worksheet.getRow(currentRow)
    row.getCell(1).value = record.record_no
    row.getCell(2).value = formatDate(record.expense_date)
    row.getCell(3).value = record.category_name || record.category
    row.getCell(4).value = record.description
    row.getCell(5).value = Number(record.amount)
    row.getCell(6).value = record.payment_method
    row.getCell(7).value = record.store_name || '-'
    row.getCell(8).value = record.approval_status === 'approved' ? '已批准' : record.approval_status === 'rejected' ? '已拒绝' : '待审批'

    row.eachCell({ includeEmpty: true }, cell => {
      cell.style = CELL_STYLE
    })

    totalAmount += Number(record.amount)
    currentRow++
  })

  const totalRow = worksheet.getRow(currentRow)
  totalRow.getCell(1).value = '合计'
  totalRow.getCell(5).value = totalAmount
  totalRow.eachCell({ includeEmpty: true }, cell => {
    cell.style = TOTAL_STYLE
  })

  worksheet.columns = [
    { width: 18 }, { width: 12 }, { width: 15 }, { width: 30 },
    { width: 15 }, { width: 12 }, { width: 20 }, { width: 12 }
  ]

  for (let i = currentRow - data.length; i < currentRow; i++) {
    worksheet.getRow(i).getCell(5).numFmt = '¥#,##0.00'
  }
  totalRow.getCell(5).numFmt = '¥#,##0.00'

  return workbook
}

// 利润分析导出
export async function exportProfitAnalysis(data, dimension, filters = {}) {
  const workbook = new ExcelJS.Workbook()
  const worksheet = workbook.addWorksheet('利润分析报表')

  worksheet.mergeCells('A1:H1')
  const titleCell = worksheet.getCell('A1')
  titleCell.value = '利润分析报表'
  titleCell.style = TITLE_STYLE
  worksheet.getRow(1).height = 25

  worksheet.mergeCells('A2:H2')
  const timeCell = worksheet.getCell('A2')
  timeCell.value = `导出时间: ${new Date().toLocaleString('zh-CN')} | 分析维度: ${dimension === 'product' ? '按商品' : dimension === 'store' ? '按门店' : '按日期'}`
  timeCell.font = { size: 10, color: { argb: 'FF6B7280' } }

  let currentRow = 3
  currentRow = addFilterInfo(worksheet, filters, currentRow)
  currentRow++

  const dimensionLabel = dimension === 'product' ? '商品名称' : dimension === 'store' ? '门店名称' : '日期'
  const headers = [dimensionLabel, '销售次数', '销售数量', '销售收入', '销售成本', '毛利润', '利润率', '平均售价']
  const headerRow = worksheet.getRow(currentRow)
  headers.forEach((header, idx) => {
    const cell = headerRow.getCell(idx + 1)
    cell.value = header
    cell.style = HEADER_STYLE
  })
  headerRow.height = 20
  currentRow++

  let totalSalesCount = 0, totalQuantity = 0, totalRevenue = 0, totalCost = 0, totalProfit = 0
  data.forEach(record => {
    const row = worksheet.getRow(currentRow)

    if (dimension === 'product') {
      row.getCell(1).value = record.product_name + (record.product_spec ? ` (${record.product_spec})` : '')
    } else if (dimension === 'store') {
      row.getCell(1).value = record.store_name || '-'
    } else {
      row.getCell(1).value = formatDate(record.sale_date)
    }

    row.getCell(2).value = record.sales_count
    row.getCell(3).value = record.total_quantity
    row.getCell(4).value = Number(record.total_revenue)
    row.getCell(5).value = Number(record.total_cost)
    row.getCell(6).value = Number(record.gross_profit)
    row.getCell(7).value = record.total_revenue > 0 ? (Number(record.gross_profit) / Number(record.total_revenue) * 100).toFixed(2) + '%' : '0%'
    row.getCell(8).value = Number(record.avg_sale_price)

    row.eachCell({ includeEmpty: true }, cell => {
      cell.style = CELL_STYLE
    })

    totalSalesCount += record.sales_count
    totalQuantity += record.total_quantity
    totalRevenue += Number(record.total_revenue)
    totalCost += Number(record.total_cost)
    totalProfit += Number(record.gross_profit)
    currentRow++
  })

  const totalRow = worksheet.getRow(currentRow)
  totalRow.getCell(1).value = '合计'
  totalRow.getCell(2).value = totalSalesCount
  totalRow.getCell(3).value = totalQuantity
  totalRow.getCell(4).value = totalRevenue
  totalRow.getCell(5).value = totalCost
  totalRow.getCell(6).value = totalProfit
  totalRow.getCell(7).value = totalRevenue > 0 ? (totalProfit / totalRevenue * 100).toFixed(2) + '%' : '0%'
  totalRow.eachCell({ includeEmpty: true }, cell => {
    cell.style = TOTAL_STYLE
  })

  worksheet.columns = [
    { width: 30 }, { width: 12 }, { width: 12 }, { width: 15 },
    { width: 15 }, { width: 15 }, { width: 12 }, { width: 12 }
  ]

  for (let i = currentRow - data.length; i < currentRow; i++) {
    worksheet.getRow(i).getCell(4).numFmt = '¥#,##0.00'
    worksheet.getRow(i).getCell(5).numFmt = '¥#,##0.00'
    worksheet.getRow(i).getCell(6).numFmt = '¥#,##0.00'
    worksheet.getRow(i).getCell(8).numFmt = '¥#,##0.00'
  }
  totalRow.getCell(4).numFmt = '¥#,##0.00'
  totalRow.getCell(5).numFmt = '¥#,##0.00'
  totalRow.getCell(6).numFmt = '¥#,##0.00'

  return workbook
}

// 财务汇总导出
export async function exportFinancialSummary(data, periodType, filters = {}) {
  const workbook = new ExcelJS.Workbook()
  const worksheet = workbook.addWorksheet('财务汇总表')

  worksheet.mergeCells('A1:H1')
  const titleCell = worksheet.getCell('A1')
  titleCell.value = '财务汇总表'
  titleCell.style = TITLE_STYLE
  worksheet.getRow(1).height = 25

  worksheet.mergeCells('A2:H2')
  const timeCell = worksheet.getCell('A2')
  timeCell.value = `导出时间: ${new Date().toLocaleString('zh-CN')} | 汇总周期: ${periodType === 'day' ? '按日' : periodType === 'month' ? '按月' : '按年'}`
  timeCell.font = { size: 10, color: { argb: 'FF6B7280' } }

  let currentRow = 3
  currentRow = addFilterInfo(worksheet, filters, currentRow)
  currentRow++

  const headers = ['周期', '销售收入', '销售成本', '毛利润', '费用支出', '净利润', '销售次数']
  const headerRow = worksheet.getRow(currentRow)
  headers.forEach((header, idx) => {
    const cell = headerRow.getCell(idx + 1)
    cell.value = header
    cell.style = HEADER_STYLE
  })
  headerRow.height = 20
  currentRow++

  let totalRevenue = 0, totalCost = 0, totalGrossProfit = 0, totalExpense = 0, totalNetProfit = 0, totalSalesCount = 0
  data.forEach(record => {
    const row = worksheet.getRow(currentRow)
    row.getCell(1).value = record.period
    row.getCell(2).value = Number(record.total_revenue)
    row.getCell(3).value = Number(record.total_cost)
    row.getCell(4).value = Number(record.gross_profit)
    row.getCell(5).value = Number(record.total_expense)
    row.getCell(6).value = Number(record.net_profit)
    row.getCell(7).value = record.sales_count

    row.eachCell({ includeEmpty: true }, cell => {
      cell.style = CELL_STYLE
    })

    totalRevenue += Number(record.total_revenue)
    totalCost += Number(record.total_cost)
    totalGrossProfit += Number(record.gross_profit)
    totalExpense += Number(record.total_expense)
    totalNetProfit += Number(record.net_profit)
    totalSalesCount += record.sales_count
    currentRow++
  })

  const totalRow = worksheet.getRow(currentRow)
  totalRow.getCell(1).value = '合计'
  totalRow.getCell(2).value = totalRevenue
  totalRow.getCell(3).value = totalCost
  totalRow.getCell(4).value = totalGrossProfit
  totalRow.getCell(5).value = totalExpense
  totalRow.getCell(6).value = totalNetProfit
  totalRow.getCell(7).value = totalSalesCount
  totalRow.eachCell({ includeEmpty: true }, cell => {
    cell.style = TOTAL_STYLE
  })

  worksheet.columns = [
    { width: 15 }, { width: 15 }, { width: 15 }, { width: 15 },
    { width: 15 }, { width: 15 }, { width: 12 }
  ]

  for (let i = currentRow - data.length; i < currentRow; i++) {
    for (let j = 2; j <= 6; j++) {
      worksheet.getRow(i).getCell(j).numFmt = '¥#,##0.00'
    }
  }
  for (let j = 2; j <= 6; j++) {
    totalRow.getCell(j).numFmt = '¥#,##0.00'
  }

  return workbook
}

