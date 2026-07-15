import axios from 'axios'
import { ROLES } from '../constants/roles.js'

// Migrate old localStorage keys to new ones (one-time migration)
if (typeof window !== 'undefined') {
  const oldToken = localStorage.getItem('gdq_token')
  const oldUser = localStorage.getItem('gdq_user')
  const oldLocale = localStorage.getItem('gdq_locale')

  // Only migrate valid tokens (not 'null' or 'undefined' strings)
  if (oldToken && oldToken !== 'null' && oldToken !== 'undefined' && !localStorage.getItem('caimeite_token')) {
    localStorage.setItem('caimeite_token', oldToken)
    localStorage.removeItem('gdq_token')
  }
  if (oldUser && oldUser !== 'null' && oldUser !== 'undefined' && !localStorage.getItem('caimeite_user')) {
    localStorage.setItem('caimeite_user', oldUser)
    localStorage.removeItem('gdq_user')
  }
  if (oldLocale && oldLocale !== 'null' && oldLocale !== 'undefined' && !localStorage.getItem('caimeite_locale')) {
    localStorage.setItem('caimeite_locale', oldLocale)
    localStorage.removeItem('gdq_locale')
  }

  // Clean up invalid tokens
  const currentToken = localStorage.getItem('caimeite_token')
  if (currentToken === 'null' || currentToken === 'undefined') {
    localStorage.removeItem('caimeite_token')
    localStorage.removeItem('caimeite_user')
  }
}

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api'
})

api.interceptors.request.use(config => {
  const token = localStorage.getItem('caimeite_token')
  // Only add Authorization header if token exists and is not 'null' string
  if (token && token !== 'null' && token !== 'undefined') {
    config.headers.Authorization = `Bearer ${token}`
  }
  // Auto-append lang=en for English locale (triggers backend translation)
  const locale = localStorage.getItem('caimeite_locale')
  if (locale === 'en' && !config.url.includes('lang=')) {
    config.url += (config.url.includes('?') ? '&' : '?') + 'lang=en'
  }
  // Auto-inject user_id for mall/orders/addresses etc. that require it
  // (后端 mall 路由无 auth 中间件，必须显式传 user_id)
  const userStr = localStorage.getItem('caimeite_user')
  if (userStr && userStr !== 'null' && userStr !== 'undefined' && config.url) {
    const needsUserId = /\/api\/(mall|h5)\//.test(config.url) || config.url.startsWith('/mall/') || config.url.startsWith('/h5/')
    const hasUserId = config.url.includes('user_id=') || (config.params && config.params.user_id)
    if (needsUserId && !hasUserId) {
      try {
        const u = JSON.parse(userStr)
        if (u && (u.id || u.user_id)) {
          const uid = u.id || u.user_id
          config.params = { ...(config.params || {}), user_id: uid }
        }
      } catch {}
    }
  }
  return config
})

api.interceptors.response.use(
  res => {
    // blob 响应直接返回原始数据，不解析 res.data
    if (res.config?.responseType === 'blob') {
      return res.data
    }
    return res.data
  },
  err => {
    if (err.response?.status === 401) {
      // Don't redirect if this is a login/auth request — let the caller handle the error
      const url = err.config?.url || ''
      if (!url.includes('/auth/login') && !url.includes('/auth/reset-password') && !url.includes('/auth/send-code')) {
        localStorage.removeItem('caimeite_token')
        localStorage.removeItem('caimeite_user')
        window.location.href = '/login'
      }
    }
    return Promise.reject(err.response?.data || err)
  }
)

export default api

// 目标服务器管理 API
export const serverProfileApi = {
  list() { return api.get('/server-profiles') },
  get(id) { return api.get(`/server-profiles/${id}`) },
  getAvailableModules() { return api.get('/server-profiles/available-modules') },
  getIndustryTemplates() { return api.get('/server-profiles/industry-templates') },
  create(data) { return api.post('/server-profiles', data) },
  update(id, data) { return api.put(`/server-profiles/${id}`, data) },
  remove(id) { return api.delete(`/server-profiles/${id}`) },
  sync(id) { return api.post(`/server-profiles/${id}/sync`) },
  execSync(id) { return api.post(`/server-profiles/${id}/exec-sync`) },
  getModules(id) { return api.get(`/server-profiles/${id}/modules`) },
  addModule(profileId, moduleKey) { return api.post(`/server-profiles/${profileId}/modules`, { module_key: moduleKey }) },
  syncModules(profileId, modules) { return api.put(`/server-profiles/${profileId}/modules`, { modules }) },
  removeModule(profileId, moduleKey) { return api.delete(`/server-profiles/${profileId}/modules/${moduleKey}`) },
}

// 服务器端点（连接地址）API
export const serverEndpointApi = {
  listByProfile(profileId) { return api.get(`/server-endpoints?profile_id=${profileId}`) },
  get(id) { return api.get(`/server-endpoints/${id}`) },
  create(data) { return api.post('/server-endpoints', data) },
  update(id, data) { return api.put(`/server-endpoints/${id}`, data) },
  remove(id) { return api.delete(`/server-endpoints/${id}`) },
}

// 菜单配置 API
export const menuApi = {
  getMenuConfig(role = ROLES.ADMIN) {
    return api.get(`/settings/menu-config?role=${role}`)
  },
  getMenuModules() {
    return api.get('/settings/menu-modules')
  },
  updateMenuConfig(selections) {
    return api.put('/settings/menu-config', { selections })
  },
  // ─── 模块化铁律：字典 + 孤儿 + 同步 ───
  getOrphans() {
    return api.get('/settings/menu-modules/orphans')
  },
  mergeOrphans(keys = null) {
    return api.post('/settings/menu-modules/merge-orphans', keys ? { keys } : {})
  },
  syncStatic(modules, dryRun = false) {
    return api.post('/settings/menu-modules/sync-static', { modules, dryRun })
  },
  createModule(data) {
    return api.post('/settings/menu-modules', data)
  },
  updateModule(key, data) {
    return api.put(`/settings/menu-modules/${key}`, data)
  },
  deleteModule(key) {
    return api.delete(`/settings/menu-modules/${key}`)
  },
  upgradeCategory(key, category) {
    return api.post('/settings/menu-modules/upgrade-category', { key, category })
  },
}
