import request from './request';

export const getProductnewList = (params) => request.get('/product-new', { params });
export const getProductnewDetail = (id) => request.get('/product-new/' + id);
export const createProductnew = (data) => request.post('/product-new', data);
export const updateProductnew = (id, data) => request.put('/product-new/' + id, data);
export const deleteProductnew = (id) => request.delete('/product-new/' + id);
export default { getProductnewList, getProductnewDetail, createProductnew, updateProductnew, deleteProductnew, getProductnewList, getProductnewDetail, createProductnew, updateProductnew, deleteProductnew };
export const getProductList = getProductnewList;
export const createProduct = createProductnew;
export const updateProduct = updateProductnew;
export const deleteProduct = deleteProductnew;
