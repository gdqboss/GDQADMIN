import request from './request';

export const getPurchaseList = (params) => request.get('/purchase', { params });
export const getPurchaseDetail = (id) => request.get('/purchase/' + id);
export const createPurchase = (data) => request.post('/purchase', data);
export const updatePurchase = (id, data) => request.put('/purchase/' + id, data);
export const deletePurchase = (id) => request.delete('/purchase/' + id);
export default { getPurchaseList, getPurchaseDetail, createPurchase, updatePurchase, deletePurchase, getPurchaseList, getPurchaseDetail, createPurchase, updatePurchase, deletePurchase };
