// 秒杀 API 前端封装
export default {
  // 活动管理
  getActivities(params) {
    return fetch('/api/seckill/activities?' + new URLSearchParams(params)).then(r => r.json())
  },
  createActivity(data) {
    return fetch('/api/seckill/activities', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(r => r.json())
  },
  updateActivity(id, data) {
    return fetch(`/api/seckill/activities/${id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(r => r.json())
  },
  deleteActivity(id) {
    return fetch(`/api/seckill/activities/${id}`, { method: 'DELETE' }).then(r => r.json())
  },

  // 秒杀商品
  getProducts(params) {
    return fetch('/api/seckill/products?' + new URLSearchParams(params)).then(r => r.json())
  },
  addProduct(data) {
    return fetch('/api/seckill/products', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(r => r.json())
  },
  updateProduct(id, data) {
    return fetch(`/api/seckill/products/${id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(r => r.json())
  },
  deleteProduct(id) {
    return fetch(`/api/seckill/products/${id}`, { method: 'DELETE' }).then(r => r.json())
  },

  // 秒杀订单
  getOrders(params) {
    return fetch('/api/seckill/orders?' + new URLSearchParams(params)).then(r => r.json())
  },
  getOrderDetail(orderNo) {
    return fetch(`/api/seckill/orders/${orderNo}`).then(r => r.json())
  },
  placeOrder(data) {
    return fetch('/api/seckill/orders', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(r => r.json())
  },
  payOrder(orderNo, data) {
    return fetch(`/api/seckill/orders/${orderNo}/pay`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(r => r.json())
  },

  // 前台公开
  getActive() {
    return fetch('/api/seckill/active').then(r => r.json())
  },
  getUpcoming() {
    return fetch('/api/seckill/upcoming').then(r => r.json())
  }
}
