import request from './request'

export function getDealerList(params) {
  return request.get('/dealers', { params })
}

export function getDealer(id) {
  return request.get(`/dealers/${id}`)
}

export function createDealer(data) {
  return request.post('/dealers', data)
}

export function updateDealer(id, data) {
  return request.put(`/dealers/${id}`, data)
}

export function deleteDealer(id) {
  return request.delete(`/dealers/${id}`)
}

export function batchDeleteDealers(ids) {
  return request.post('/dealers/batch-delete', { ids })
}

export const addDealer = createDealer
