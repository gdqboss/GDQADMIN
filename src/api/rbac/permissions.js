// 权限 API
import request from '../request'

export function getPermissionList(params) {
  return request.get('/rbac/permissions', { params })
}

export function getPermissionCategories() {
  return request.get('/rbac/permissions/categories')
}

export function createPermission(data) {
  return request.post('/rbac/permissions', data)
}

export function updatePermission(id, data) {
  return request.put(`/rbac/permissions/${id}`, data)
}

export function deletePermission(id) {
  return request.delete(`/rbac/permissions/${id}`)
}
