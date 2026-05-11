import request from './request';

export const getDictList = (params) => request.get('/dict', { params });
export const getDictDetail = (id) => request.get('/dict/' + id);
export const createDict = (data) => request.post('/dict', data);
export const updateDict = (id, data) => request.put('/dict/' + id, data);
export const deleteDict = (id) => request.delete('/dict/' + id);
