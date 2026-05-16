import axios from 'axios'

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
