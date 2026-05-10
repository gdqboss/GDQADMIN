import request from './request'

export function getProductList(params) {
  return request.get('/bi/products', { params })
}

export function getProduct(id) {
  return request.get(`/bi/products/${id}`)
}

export function createProduct(data) {
  return request.post('/bi/products', data)
}

export function updateProduct(id, data) {
  return request.put(`/bi/products/${id}`, data)
}

export function deleteProduct(id) {
  return request.delete(`/bi/products/${id}`)
}

export function batchDeleteProducts(ids) {
  return request.post('/bi/products/batch-delete', { ids })
}

export function getCategories(params) {
  return request.get('/categories', { params })
}

export function getSuppliers(params) {
  return request.get('/suppliers', { params })
}

export function saveProductSpecs(id, specs) {
  return request.post(`/products/${id}/specs`, specs)
}

export function getProductSpecs(id) {
  return request.get(`/products/${id}/specs`)
}
