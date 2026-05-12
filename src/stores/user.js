import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getUserPermissions, getUserMenus } from '@/api/rbac/userRoles'
import request from '@/api/request'

export const useUserStore = defineStore('user', () => {
  const token = ref(localStorage.getItem('caimeite_token') || '')
  const userInfo = ref(JSON.parse(localStorage.getItem('caimeite_user') || '{}'))
  const userName = ref(userInfo.value?.name || 'Admin')
  const userRole = ref(userInfo.value?.role || 'operator')

  // 动态权限数据
  const permissions = ref([])   // ['product:read', 'product:write', ...]
  const menus = ref([])         // 用户可见菜单树
  const menuLoaded = ref(false)

  const isLoggedIn = () => !!token.value

  /** 检查是否有指定权限 */
  const hasPermission = (perm) => {
    if (permissions.value.includes('*')) return true  // 超级管理员
    return permissions.value.includes(perm)
  }

  /** 检查是否有任意指定权限 */
  const hasAnyPermission = (...perms) => perms.some(p => permissions.value.includes(p))

  /** 加载用户权限和菜单 */
  async function loadPermissions() {
    if (!userInfo.value?.id) return
    try {
      const [permRes, menuRes] = await Promise.all([
        getUserPermissions(userInfo.value.id),
        getUserMenus(userInfo.value.id)
      ])
      permissions.value = permRes.map(p => p.name)
      menus.value = menuRes
      menuLoaded.value = true
    } catch (e) {
      console.warn('加载权限失败，使用空权限集', e)
      permissions.value = []
      menus.value = []
      menuLoaded.value = true
    }
  }

  /** 登录后调用 */
  async function login(tokenStr, user) {
    token.value = tokenStr
    userInfo.value = user
    userName.value = user.name || 'Admin'
    userRole.value = user.role || 'operator'
    localStorage.setItem('caimeite_token', tokenStr)
    localStorage.setItem('caimeite_user', JSON.stringify(user))
    await loadPermissions()
  }

  const logout = () => {
    token.value = ''
    userInfo.value = {}
    userName.value = 'Admin'
    userRole.value = 'operator'
    permissions.value = []
    menus.value = []
    menuLoaded.value = false
    localStorage.removeItem('caimeite_token')
    localStorage.removeItem('caimeite_user')
  }

  return {
    token, userInfo, userName, userRole,
    permissions, menus, menuLoaded,
    isLoggedIn, hasPermission, hasAnyPermission,
    login, logout, loadPermissions
  }
})
