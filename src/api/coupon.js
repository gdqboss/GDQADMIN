import request from './request';

export const getCouponList = (params) => request.get('/coupon', { params });
export const getCouponDetail = (id) => request.get('/coupon/' + id);
export const createCoupon = (data) => request.post('/coupon', data);
export const updateCoupon = (id, data) => request.put('/coupon/' + id, data);
export const deleteCoupon = (id) => request.delete('/coupon/' + id);
