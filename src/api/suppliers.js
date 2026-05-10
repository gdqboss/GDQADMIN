import request from './request'

export function getSuppliers(params) {
  return request.get('/suppliers', { params })
}

export function getSupplier(id) {
  return request.get(`/suppliers/${id}`)
}
