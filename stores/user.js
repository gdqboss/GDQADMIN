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

  // 如果 caimeite_user 没有 permissions 或 permissions 是字符串（兼容历史数据），则补充/解析
  if (savedUser) {
    let needsUpdate = false
    if (!savedUser.permissions) {
      try {
        const savedPerms = JSON.parse(localStorage.getItem('caimeite_permissions') || 'null')
        if (savedPerms && Array.isArray(savedPerms)) {
          savedUser.permissions = savedPerms
          needsUpdate = true
        }
      } catch { /* ignore */ }
    } else if (typeof savedUser.permissions === 'string') {
      // 兼容：旧数据可能存的是字符串（修复 login 之前的版本）
      try {
        savedUser.permissions = JSON.parse(savedUser.permissions)
        needsUpdate = true
      } catch { savedUser.permissions = null }
    }
    if (needsUpdate) {
      user.value = savedUser
      localStorage.setItem('caimeite_user', JSON.stringify(savedUser))
    }
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

  // 统一权限检查：传入权限标识符（如 'products:delete'），返回是否有权限
  // admin 角色天然拥有所有权限
  // 容错：DB 历史脏数据存在多种权限 key 风格（'products:delete' / 'products_delete' / 'product:delete'），
  //       用同一个 normalize 函数把所有写法映射到一个内部 key，匹配任何一种即视为通过。
  function _normalizePermKey(k) {
    if (!k || typeof k !== 'string') return ''
    // 1. 全部小写
    let s = k.toLowerCase().trim()
    // 2. 冒号/下划线 都视为分隔符
    s = s.replace(/_/g, ':')
    // 3. 单复数统一：product → products, inventory 已经是 inventory
    //    仅对纯 product(s) 域做这步，避免影响其它无意义替换
    s = s.replace(/\bproduct:/g, 'products:')
    return s
  }
  function canAccess(permKey) {
    if (!permKey) return true
    if (userRole.value === ROLES.ADMIN) return true
    const perms = userPermissions.value
    if (!perms || !Array.isArray(perms)) return false
    const target = _normalizePermKey(permKey)
    return perms.some(p => _normalizePermKey(p) === target)
  }

  async function login(phone, password) {
    const res = await api.post('/auth/login', { phone, password })
    if (res.code === 0) {
      // 后端有时返回 JSON 字符串（DB longtext 字段），有时返回数组
      // 必须先解析成数组，否则 canAccess 的 Array.isArray 校验失败
      const rawPerms = res.data.permissions ?? res.data.user?.permissions ?? null
      const permsArray = (() => {
        if (Array.isArray(rawPerms)) return rawPerms
        if (typeof rawPerms === 'string') {
          try { return JSON.parse(rawPerms) } catch { return null }
        }
        return null
      })()
      const userWithPerms = {
        ...res.data.user,
        permissions: permsArray
      }
      user.value = userWithPerms
      token.value = res.data.token
      localStorage.setItem('caimeite_user', JSON.stringify(userWithPerms))
      localStorage.setItem('caimeite_token', res.data.token)
      localStorage.setItem('caimeite_permissions', JSON.stringify(permsArray || []))
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
        // fetchMe 返回的 user 可能没有 permissions 字段，需要从 localStorage 补充
        // 并兼容后端 permissions 为 JSON 字符串的情况
        const savedPerms = JSON.parse(localStorage.getItem('caimeite_permissions') || 'null')
        let perms = savedPerms ?? res.data.permissions ?? null
        if (typeof perms === 'string') {
          try { perms = JSON.parse(perms) } catch { perms = null }
        }
        const userData = {
          ...res.data,
          permissions: Array.isArray(perms) ? perms : null
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
