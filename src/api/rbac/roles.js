// 角色 API
import request from '../request'

export function getRoleList() {
  return request.get('/rbac/roles')
}

export function createRole(data) {
  return request.post('/rbac/roles', data)
}

export function updateRole(id, data) {
  return request.put(`/rbac/roles/${id}`, data)
}

export function deleteRole(id) {
  return request.delete(`/rbac/roles/${id}`)
}

export function getRolePermissions(roleId) {
  return request.get(`/rbac/roles/${roleId}/permissions`)
}

export function updateRolePermissions(roleId, permissionIds) {
  return request.put(`/rbac/roles/${roleId}/permissions`, { permission_ids: permissionIds })
}
