import request from './request';
export const getSalesList = (params) => request.get('/sales', { params });
export const addSale = (data) => request.post('/sales', data);
export const updateSale = (data) => request.put('/sales', data);
export const deleteSale = (id) => request.delete('/sales/' + id);
