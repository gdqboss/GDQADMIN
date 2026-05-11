import request from './request';

export const getOrderList = (params) => request.get('/order', { params });
export const getOrderDetail = (id) => request.get('/order/' + id);
export const createOrder = (data) => request.post('/order', data);
export const updateOrder = (id, data) => request.put('/order/' + id, data);
export const deleteOrder = (id) => request.delete('/order/' + id);
