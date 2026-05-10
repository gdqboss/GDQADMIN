import request from './request'

export function getCategories(params) {
  return request.get('/categories', { params })
}

export function getCategoryTree() {
  return request.get('/categories/tree')
}
