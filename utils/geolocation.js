/**
 * GPS定位工具函数
 */

/**
 * 获取当前GPS位置
 * @returns {Promise<{lat: number, lng: number, accuracy: number, address: string}>}
 */
export async function getCurrentPosition() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('浏览器不支持定位功能'))
      return
    }

    const options = {
      enableHighAccuracy: true, // 高精度模式
      timeout: 10000, // 10秒超时
      maximumAge: 0 // 不使用缓存
    }

    navigator.geolocation.getCurrentPosition(
      async (position) => {
        const { latitude, longitude, accuracy } = position.coords

        // 调用逆地理编码获取地址
        const address = await reverseGeocode(latitude, longitude)

        resolve({
          lat: latitude,
          lng: longitude,
          accuracy: accuracy,
          address: address || `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`
        })
      },
      (error) => {
        let message = '定位失败'
        switch (error.code) {
          case error.PERMISSION_DENIED:
            message = '用户拒绝了定位请求，请在浏览器设置中允许定位'
            break
          case error.POSITION_UNAVAILABLE:
            message = '位置信息不可用'
            break
          case error.TIMEOUT:
            message = '定位请求超时，请重试'
            break
        }
        reject(new Error(message))
      },
      options
    )
  })
}

/**
 * 逆地理编码 (lat,lng → 地名)
 * 2026-09-13 江小鱼改: 用 Nominatim (OpenStreetMap 公开 API) 替代高德/腾讯 (没 key)
 *  - 免 key, 匿名可调, 但有 1 req/s 限速
 *  - 国内可能偶尔慢 / 失败 → fallback 返回原经纬度
 *  - 后续哥拿到高德/腾讯 key 后, 改回 key 调用即可
 */
async function reverseGeocode(lat, lng) {
  try {
    // Nominatim API: lat,lon (注意顺序), 中文 zh
    const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&accept-language=zh&addressdetails=1`
    const res = await fetch(url, {
      headers: { 'Accept': 'application/json' }
    })
    if (!res.ok) {
      console.warn(`[reverseGeocode] Nominatim HTTP ${res.status}, fallback to lat/lng`)
      return null
    }
    const data = await res.json()
    const a = data.address || {}
    const parts = []
    if (a.attraction || a.amenity || a.shop || a.tourism) parts.push(a.attraction || a.amenity || a.shop || a.tourism)
    if (a.road) parts.push(a.road)
    if (a.suburb && a.suburb !== a.city) parts.push(a.suburb)
    if (a.city || a.town || a.county) parts.push(a.city || a.town || a.county)
    if (a.state) parts.push(a.state)
    if (parts.length === 0) {
      if (data.display_name) return data.display_name.split(',').slice(0, 3).join(',').trim()
      return null
    }
    return parts.join(' ')
  } catch (e) {
    console.warn('[reverseGeocode] Nominatim failed:', e.message)
    return null
  }
}

/**
 * 获取GPS精度等级
 * @param {number} accuracy - 精度值（米）
 * @returns {{level: string, color: string, text: string}}
 */
export function getAccuracyLevel(accuracy) {
  if (accuracy < 50) {
    return { level: 'excellent', color: 'text-green-600', text: '优秀' }
  } else if (accuracy < 100) {
    return { level: 'good', color: 'text-yellow-600', text: '良好' }
  } else {
    return { level: 'poor', color: 'text-orange-600', text: '较差' }
  }
}

/**
 * 格式化GPS信息显示
 * @param {number} lat - 纬度
 * @param {number} lng - 经度
 * @param {number} accuracy - 精度
 * @returns {string}
 */
export function formatGPSInfo(lat, lng, accuracy) {
  return `${lat.toFixed(6)}, ${lng.toFixed(6)} (±${Math.round(accuracy)}m)`
}
