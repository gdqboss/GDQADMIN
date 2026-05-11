import request from './request';

export const getWorkbenchList = (params) => request.get('/workbench', { params });
export const getWorkbenchDetail = (id) => request.get('/workbench/' + id);
export const createWorkbench = (data) => request.post('/workbench', data);
export const updateWorkbench = (id, data) => request.put('/workbench/' + id, data);
export const deleteWorkbench = (id) => request.delete('/workbench/' + id);
