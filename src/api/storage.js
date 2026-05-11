import request from './request';

export const getStorageList = (params) => request.get('/storage', { params });
export const getStorageDetail = (id) => request.get('/storage/' + id);
export const createStorage = (data) => request.post('/storage', data);
export const updateStorage = (id, data) => request.put('/storage/' + id, data);
export const deleteStorage = (id) => request.delete('/storage/' + id);
