import request from './request'

export const getCategoryList = (params) => request.get('/categories', { params })
export const getCategoryTree = () => request.get('/categories/tree')
export const createCategory = (data) => request.post('/categories', data)
export const updateCategory = (id, data) => request.put('/categories/' + id, data)
export const deleteCategory = (id) => request.delete('/categories/' + id)
export const getCategories = getCategoryList;
