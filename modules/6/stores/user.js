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

  // 如果 caimeite_user 没有 permissions 但 caimeite_permissions 有值，则补充
  if (savedUser && !savedUser.permissions) {
    try {
      const savedPerms = JSON.parse(localStorage.getItem('caimeite_permissions') || 'null')
      if (savedPerms && Array.isArray(savedPerms)) {
        savedUser.permissions = savedPerms
        user.value = savedUser
        localStorage.setItem('caimeite_user', JSON.stringify(savedUser))
      }
    } catch { /* ignore */ }
  }

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

  async function login(phone, password, opts = {}) {
    let res
    try {
      res = await api.post('/auth/login', { phone, password, force_login: opts.force_login ? 1 : 0 })
    } catch (err) {
      // axios 拿到 4xx 时 axios 走 reject, 但 response body 仍在 err.response.data
      // 这里把 err.response.data 重新包成 {code, ...} 形式给前端用
      // 2026-08-25 SSO: 必须支持 409 (needConfirm) 不被 throw 走, 否则前端拿不到 data
      if (err && err.code !== undefined) {
        res = err
      } else if (err && err.response && err.response.data) {
        res = err.response.data
      } else {
        throw err
      }
    }
    if (res.code === 0) {
      // 优先使用后端返回的 permissions 字段（解析后的权限数组）
      const userWithPerms = {
        ...res.data.user,
        permissions: res.data.permissions ?? res.data.user?.permissions ?? null
      }
      user.value = userWithPerms
      token.value = res.data.token
      localStorage.setItem('caimeite_user', JSON.stringify(userWithPerms))
      localStorage.setItem('caimeite_token', res.data.token)
      localStorage.setItem('caimeite_permissions', JSON.stringify(res.data.permissions || []))
    }
    return res
  }

  // 2026-08-25 SSO: 主动清 token 时同步标记 session invalidated
  function logout() {
    const oldToken = token.value
    user.value = null
    token.value = ''
    localStorage.removeItem('caimeite_user')
    localStorage.removeItem('caimeite_token')
    // fire-and-forget 通知后端 invalidate session
    if (oldToken) {
      fetch('/api/auth/logout', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${oldToken}` }
      }).catch(() => {})
    }
  }

  async function fetchMe() {
    try {
      const res = await api.get('/auth/me')
      if (res.code === 0) {
        // fetchMe 返回的 user 没有 permissions 字段，需要从 localStorage 补充
        const savedPerms = JSON.parse(localStorage.getItem('caimeite_permissions') || 'null')
        const userData = {
          ...res.data,
          permissions: savedPerms ?? res.data.permissions ?? null
        }
        user.value = userData
        localStorage.setItem('caimeite_user', JSON.stringify(userData))
      }
    } catch { /* token invalid, will redirect */ }
  }

  const userId = computed(() => user.value?.id || null)
  const isAdmin = computed(() => userRole.value === ROLES.ADMIN)
  return { user, userId, token, isLoggedIn, userName, userRole, userPermissions, isAdmin, canAccess, login, logout, fetchMe }
})
