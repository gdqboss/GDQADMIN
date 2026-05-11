// 用户-角色关联 API
import request from '../request'

export function getUserRoles(userId) {
  return request.get(`/rbac/users/${userId}/roles`)
}

export function updateUserRoles(userId, roleIds) {
  return request.put(`/rbac/users/${userId}/roles`, { role_ids: roleIds })
}

export function getUserPermissions(userId) {
  return request.get(`/rbac/users/${userId}/permissions`)
}

export function getUserMenus(userId) {
  return request.get(`/rbac/users/${userId}/menus`)
}
