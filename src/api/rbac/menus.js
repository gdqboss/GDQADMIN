// 菜单 API
import request from '../request'

export function getMenuTree() {
  return request.get('/rbac/menus')
}

export function getMenuFlat() {
  return request.get('/rbac/menus/flat')
}

export function createMenu(data) {
  return request.post('/rbac/menus', data)
}

export function updateMenu(id, data) {
  return request.put(`/rbac/menus/${id}`, data)
}

export function deleteMenu(id) {
  return request.delete(`/rbac/menus/${id}`)
}
