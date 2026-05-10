import request from './request'

// ===== 销售收入 =====
export function getSalesRevenues(params) {
  return request.get('/finance-simple/sales-revenues', { params })
}

export function createSalesRevenue(data) {
  return request.post('/finance-simple/sales-revenues', data)
}

export function updateSalesRevenue(id, data) {
  return request.put(`/finance-simple/sales-revenues/${id}`, data)
}

export function deleteSalesRevenue(id) {
  return request.delete(`/finance-simple/sales-revenues/${id}`)
}

export function exportSalesRevenues(params) {
  return request.get('/finance-simple/export/sales-revenues', { params, responseType: 'blob' })
}

// ===== 采购成本 =====
export function getPurchaseCosts(params) {
  return request.get('/finance-simple/purchase-costs', { params })
}

export function createPurchaseCost(data) {
  return request.post('/finance-simple/purchase-costs', data)
}

export function updatePurchaseCost(id, data) {
  return request.put(`/finance-simple/purchase-costs/${id}`, data)
}

export function deletePurchaseCost(id) {
  return request.delete(`/finance-simple/purchase-costs/${id}`)
}

export function exportPurchaseCosts(params) {
  return request.get('/finance-simple/export/purchase-costs', { params, responseType: 'blob' })
}

// ===== 账户 =====
export function getAccounts(params) {
  return request.get('/finance-simple/accounts', { params })
}

// ===== 零售记录 =====
export function getRetailRecords(params) {
  return request.get('/retail-records', { params })
}

export function revokeRetailRecord(id) {
  return request.put(`/retail-records/${id}/revoke`)
}

export function deleteRetailRecord(id) {
  return request.delete(`/retail-records/${id}`)
}

export function exportRetailRecords(params) {
  return request.get('/retail-records/export', { params })
}

// ===== 退货列表 =====
export function getReturns(params) {
  return request.get('/returns', { params })
}

export function createReturn(data) {
  return request.post('/returns', data)
}

// ===== 库存预警 =====
export function getStockAlerts(params) {
  return request.get('/stock-alerts', { params })
}

export function replenishStockAlert(id) {
  return request.put(`/stock-alerts/${id}/replenish`)
}

// ===== 出入库记录 =====
export function getInbound(params) {
  return request.get('/inbound', { params })
}

export function getOutbound(params) {
  return request.get('/outbound', { params })
}

export function createInbound(data) {
  return request.post('/inbound', data)
}

export function createOutbound(data) {
  return request.post('/outbound', data)
}

export function deleteRecord(type, id) {
  return request.delete(`/${type}/${id}`)
}

export function previewOutbound(data) {
  return request.post('/inventory/outbound/preview', data)
}

// ===== 客户对账 =====
export function getAccountsReceivable(params) {
  return request.get('/finance-simple/accounts-receivable', { params })
}

export function getCustomerStatement(phone, params) {
  return request.get(`/finance-simple/statement/customer/${phone}`, { params })
}

export function exportCustomerStatement(phone, data) {
  return request.post(`/finance-simple/statement/customer/${phone}/export`, data, { responseType: 'blob' })
}

// ===== 发票管理 =====
export function getInvoices(params) {
  return request.get('/invoices', { params })
}

export function createInvoice(data) {
  return request.post('/invoices', data)
}

export function updateInvoice(id, data) {
  return request.put(`/invoices/${id}`, data)
}

export function verifyInvoice(id) {
  return request.put(`/invoices/${id}/verify`)
}

export function voidInvoice(id) {
  return request.put(`/invoices/${id}/void`)
}

export function deleteInvoice(id) {
  return request.delete(`/invoices/${id}`)
}

// ===== 通用 =====
export function getStores(params) {
  return request.get('/stores', { params })
}

export function getWarehouses(params) {
  return request.get('/warehouses', { params })
}

export function getProductsSimple(params) {
  return request.get('/products', { params })
}
