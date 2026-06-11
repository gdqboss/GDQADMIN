import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../services/api.js'
import { ROLES } from '../constants/roles.js'

export const useUserStore = defineStore('user', () => {
  let savedUser = null
  try {
    savedUser = JSON.parse(localStorage.getItem('caimeite_user') || 'null')
  } catch {
    savedUser = null
  }
  const user = ref(savedUser)

  // Get token and validate it's not 'null' or 'undefined' string
  let savedToken = localStorage.getItem('caimeite_token') || ''
  if (savedToken === 'null' || savedToken === 'undefined') {
    savedToken = ''
    localStorage.removeItem('caimeite_token')
    localStorage.removeItem('caimeite_user')
  }
  const token = ref(savedToken)

  const isLoggedIn = computed(() => !!user.value && !!token.value)
  const userName = computed(() => user.value?.name || '')
  const userRole = computed(() => user.value?.role || '')
  const userPermissions = computed(() => user.value?.permissions || null)

  // 统一权限检查：传入权限标识符（如 'product:write'），返回是否有权限
  // admin 角色天然拥有所有权限
  function canAccess(permKey) {
    if (!permKey) return true
    if (userRole.value === ROLES.ADMIN) return true
    const perms = userPermissions.value
    if (!perms || !Array.isArray(perms)) return false
    return perms.includes(permKey)
  }

  async function login(phone, password) {
    const res = await api.post('/auth/login', { phone, password })
    if (res.code === 0) {
      user.value = res.data.user
      token.value = res.data.token
      localStorage.setItem('caimeite_user', JSON.stringify(res.data.user))
      localStorage.setItem('caimeite_token', res.data.token)
    }
    return res
  }

  function logout() {
    user.value = null
    token.value = ''
    localStorage.removeItem('caimeite_user')
    localStorage.removeItem('caimeite_token')
  }

  async function fetchMe() {
    try {
      const res = await api.get('/auth/me')
      if (res.code === 0) {
        user.value = res.data
        localStorage.setItem('caimeite_user', JSON.stringify(res.data))
      }
    } catch { /* token invalid, will redirect */ }
  }

  const userId = computed(() => user.value?.id || null)
  const isAdmin = computed(() => userRole.value === ROLES.ADMIN)
  return { user, userId, token, isLoggedIn, userName, userRole, userPermissions, isAdmin, canAccess, login, logout, fetchMe }
})
