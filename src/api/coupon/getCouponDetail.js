import request from '../request';
export const getCouponDetail = (id) => request.get('/coupon/' + id);
export default { getCouponDetail };
