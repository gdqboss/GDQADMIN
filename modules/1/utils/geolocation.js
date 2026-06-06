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
 * 逆地理编码（简化版，返回经纬度字符串）
 * 可以接入高德地图、百度地图等API进行真实地址解析
 */
async function reverseGeocode(lat, lng) {
  // 这里可以接入地图API，例如：
  // const response = await fetch(`https://restapi.amap.com/v3/geocode/regeo?key=YOUR_KEY&location=${lng},${lat}`)
  // const data = await response.json()
  // return data.regeocode.formatted_address

  // 简化版：返回经纬度
  return `${lat.toFixed(6)}, ${lng.toFixed(6)}`
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
