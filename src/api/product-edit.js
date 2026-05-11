import request from './request';

export const getProducteditList = (params) => request.get('/product-edit', { params });
export const getProducteditDetail = (id) => request.get('/product-edit/' + id);
export const createProductedit = (data) => request.post('/product-edit', data);
export const updateProductedit = (id, data) => request.put('/product-edit/' + id, data);
export const deleteProductedit = (id) => request.delete('/product-edit/' + id);
export default { getProducteditList, getProducteditDetail, createProductedit, updateProductedit, deleteProductedit, getProducteditList, getProducteditDetail, createProductedit, updateProductedit, deleteProductedit };
