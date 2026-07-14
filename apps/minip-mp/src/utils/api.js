/**
 * utils/api.js
 * ─────────────────────────────────────────────────────
 * uni-app 跨端 API shim — H5 / mp-weixin / app-plus 全兼容
 *
 * 设计目标：
 * - 同一个 import api from '@/utils/api' 在所有端行为一致
 * - H5 端：如果有 window.uni（多端编译后 H5 也有 uni.*），用 uni.request
 * - 静默兜底：所有端都实现 get/post/put/delete + 返回 { code, data, message }
 *
 * 调用约定（与原 H5 api.request 保持一致）：
 *   const r = await api.get('/users/me')
 *   if (r.code === 0) { ... } else { ElMessage.error(r.message) }
 * ─────────────────────────────────────────────────────
 */

const BASE_URL = ''  // 同源；如需跨域在 manifest.json 配置

/**
 * 内部请求方法
 * - mp-weixin / app-plus: uni.request
 * - H5: 浏览器 fetch（uni-app 编译 H5 时 uni.request 仍可用，但 fetch 更标准）
 */
function request({ url, method = 'GET', data = {}, header = {} }) {
  return new Promise((resolve, reject) => {
    // 统一 success/fail 转换
    const onSuccess = (res) => {
      // H5 (fetch) 路径
      const body = res.data ?? res
      // 兼容直接返回 data / 全套 {code, data, message}
      const result = (body && typeof body === 'object' && 'code' in body)
        ? body
        : { code: 0, data: body, message: '' }
      // 后端约定 code === 0 = 成功，否则 message 报错
      resolve(result)
    }
    const onFail = (err) => reject(err)

    // 平台检测
    // #ifdef MP-WEIXIN || APP-PLUS
    if (typeof uni !== 'undefined' && uni.request) {
      uni.request({
        url: BASE_URL + url,
        method,
        data,
        header,
        success: (res) => onSuccess(res),
        fail: onFail,
      })
      return
    }
    // #endif

    // #ifdef H5
    // H5 用 fetch（uni-app 编译 H5 也支持 uni.request，但 window.fetch 更原生）
    if (typeof fetch !== 'undefined') {
      const opts = {
        method,
        headers: { 'Content-Type': 'application/json', ...header },
      }
      if (method !== 'GET' && data) opts.body = JSON.stringify(data)
      const fullUrl = method === 'GET' && Object.keys(data || {}).length
        ? `${BASE_URL + url}?${new URLSearchParams(data).toString()}`
        : BASE_URL + url
      fetch(fullUrl, opts).then(r => r.json().then(j => onSuccess({ data: j }))).catch(onFail)
      return
    }
    // #endif

    // 兜底：啥都没有就 reject
    reject(new Error('No HTTP client available (uni.request / fetch 都不存在)'))
  })
}

const api = {
  get:    (url, params = {}, header = {}) => request({ url, method: 'GET',  data: params, header }),
  post:   (url, data = {}, header = {})  => request({ url, method: 'POST', data,        header }),
  put:    (url, data = {}, header = {})  => request({ url, method: 'PUT',  data,        header }),
  delete: (url, data = {}, header = {})  => request({ url, method: 'DELETE', data,     header }),
  request, // 暴露底层方法，方便高级用法
}

export default api
