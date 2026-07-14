/**
 * utils/api.js
 * uni-app 适配 axios — H5 用 axios，小程序用 uni.request
 * 同一个 import { api } from '@/utils/api'，两端行为一致
 */

const BASE_URL = ''  // 相对路径（同源）或写绝对 URL

function request({ url, method = 'GET', data = {}, header = {} }) {
  return new Promise((resolve, reject) => {
    // #ifdef MP-WEIXIN
    uni.request({
      url: BASE_URL + url,
      method,
      data,
      header,
      success: (res) => resolve({ code: res.data?.code ?? 0, data: res.data?.data, message: res.data?.message, raw: res }),
      fail: (err) => reject(err)
    })
    // #endif
    // #ifdef H5
    // H5 走原本的 axios，不经过这里
    // #endif
  })
}

export default {
  get: (url, params = {}) => request({ url, method: 'GET', data: params }),
  post: (url, data = {}) => request({ url, method: 'POST', data }),
  put: (url, data = {}) => request({ url, method: 'PUT', data }),
  delete: (url) => request({ url, method: 'DELETE' })
}
