import request from './request';

export const getPointList = (params) => request.get('/point', { params });
export const getPointDetail = (id) => request.get('/point/' + id);
export const createPoint = (data) => request.post('/point', data);
export const updatePoint = (id, data) => request.put('/point/' + id, data);
export const deletePoint = (id) => request.delete('/point/' + id);
