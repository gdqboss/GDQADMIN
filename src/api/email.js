import request from './request';

export const getEmailList = (params) => request.get('/email', { params });
export const getEmailDetail = (id) => request.get('/email/' + id);
export const createEmail = (data) => request.post('/email', data);
export const updateEmail = (id, data) => request.put('/email/' + id, data);
export const deleteEmail = (id) => request.delete('/email/' + id);
