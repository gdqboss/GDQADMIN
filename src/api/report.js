import request from './request';
export const getReportList = (params) => request.get('/report', { params });
export const addReport = (data) => request.post('/report', data);
export const updateReport = (data) => request.put('/report', data);
export const deleteReport = (id) => request.delete('/report/' + id);
