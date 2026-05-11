import request from './request'

export const getProductSkusList = (params) => request.get('/product-skus', { params })
export const getProductSkusDetail = (id) => request.get('/product-skus/' + id)
export const createProductSku = (data) => request.post('/product-skus', data)
export const updateProductSku = (id, data) => request.put('/product-skus/' + id, data)
export const deleteProductSku = (id) => request.delete('/product-skus/' + id)

export default { getProductSkusList, getProductSkusDetail, createProductSku, updateProductSku, deleteProductSku }
