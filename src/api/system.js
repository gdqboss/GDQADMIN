import request from './request';
export const getSystemSettings = (params) => request.get('/system/settings', { params });
export const updateSystemSettings = (data) => request.put('/system/settings', data);
