import request from './request';

export const getRoleList = (params) => request.get('/role', { params });
export const getRoleDetail = (id) => request.get('/role/' + id);
export const createRole = (data) => request.post('/role', data);
export const updateRole = (id, data) => request.put('/role/' + id, data);
export const deleteRole = (id) => request.delete('/role/' + id);
