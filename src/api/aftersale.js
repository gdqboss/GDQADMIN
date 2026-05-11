import request from './request'

export function getAftersaleList(params) {
  return request.get('/aftersale', { params })
}
export function getAftersale(id) {
  return request.get('/aftersale/' + id)
}
export function addAftersale(data) {
  return request.post('/aftersale', data)
}
export function updateAftersale(id, data) {
  return request.put('/aftersale/' + id, data)
}
export function deleteAftersale(id) {
  return request.delete('/aftersale/' + id)
}

// stubs for module compatibility
export const getList = getAftersaleList
export const getAfterSaleList = getAftersaleList
export const addAfterSale = addAftersale
export const updateAfterSale = updateAftersale
export const deleteAfterSale = deleteAftersale
export const removeAftersale = deleteAftersale
