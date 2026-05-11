import request from './request';

export const getMonitorList = (params) => request.get('/monitor', { params });
export const getMonitorDetail = (id) => request.get('/monitor/' + id);
export const createMonitor = (data) => request.post('/monitor', data);
export const updateMonitor = (id, data) => request.put('/monitor/' + id, data);
export const deleteMonitor = (id) => request.delete('/monitor/' + id);
