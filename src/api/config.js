import request from './request';

export const getConfigList = (params) => request.get('/config', { params });
export const getConfigDetail = (id) => request.get('/config/' + id);
export const createConfig = (data) => request.post('/config', data);
export const updateConfig = (id, data) => request.put('/config/' + id, data);
export const deleteConfig = (id) => request.delete('/config/' + id);
