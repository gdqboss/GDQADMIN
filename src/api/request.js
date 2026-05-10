import axios from 'axios'

const request = axios.create({
  baseURL: '/api',
  timeout: 10000,
})

request.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

request.interceptors.response.use(
  (response) => {
    const { code, data, message } = response.data
    if (code === 0) {
      return data
    }
    return Promise.reject(new Error(message || 'Request failed'))
  },
  (error) => {
    const message = error.response?.data?.message || error.message || 'Network error'
    return Promise.reject(new Error(message))
  }
)

export default request
