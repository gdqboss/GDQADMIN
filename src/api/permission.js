import request from './request';

export const getPermissionList = (params) => request.get('/permission', { params });
export const getPermissionDetail = (id) => request.get('/permission/' + id);
export const createPermission = (data) => request.post('/permission', data);
export const updatePermission = (id, data) => request.put('/permission/' + id, data);
export const deletePermission = (id) => request.delete('/permission/' + id);
