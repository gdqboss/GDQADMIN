import request from './request';

export const getImportList = (params) => request.get('/import', { params });
export const getImportDetail = (id) => request.get('/import/' + id);
export const createImport = (data) => request.post('/import', data);
export const updateImport = (id, data) => request.put('/import/' + id, data);
export const deleteImport = (id) => request.delete('/import/' + id);
