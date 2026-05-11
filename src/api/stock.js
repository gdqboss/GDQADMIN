import request from './request'

export function getStockList(params) {
  return request.get('/stock', { params })
}

export function getStock(id) {
  return request.get(`/stock/${id}`)
}

export function createStock(data) {
  return request.post('/stock', data)
}

export function updateStock(id, data) {
  return request.put(`/stock/${id}`, data)
}

export function deleteStock(id) {
  return request.delete(`/stock/${id}`)
}

export const addStock = createStock
