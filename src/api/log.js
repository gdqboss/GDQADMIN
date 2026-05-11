import request from './request';

export const getLogList = (params) => request.get('/log', { params });
export const getLogDetail = (id) => request.get('/log/' + id);
export const createLog = (data) => request.post('/log', data);
export const updateLog = (id, data) => request.put('/log/' + id, data);
export const deleteLog = (id) => request.delete('/log/' + id);
