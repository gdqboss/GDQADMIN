import request from './request';

export const couponApi = {
  // 后台管理
  list: (params) => request.get('/api/coupons', { params }),
  create: (data) => request.post('/api/coupons', data),
  update: (id, data) => request.put(`/api/coupons/${id}`, data),
  delete: (id) => request.delete(`/api/coupons/${id}`),
  toggle: (id) => request.post(`/api/coupons/${id}/toggle`),
  detail: (id) => request.get(`/api/coupons/${id}`),
  
  // 用户端
  claim: (coupon_id) => request.post('/api/coupons/claim', { coupon_id }),
  myList: (params) => request.get('/api/coupons/my/list', { params }),
  
  // 下单时
  validate: (data) => request.post('/api/coupons/validate', data),
  apply: (data) => request.post('/api/coupons/apply', data),
  use: (data) => request.post('/api/coupons/use', data),
};