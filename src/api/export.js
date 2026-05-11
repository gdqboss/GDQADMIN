import request from './request';

export const getExportList = (params) => request.get('/export', { params });
export const getExportDetail = (id) => request.get('/export/' + id);
export const createExport = (data) => request.post('/export', data);
export const updateExport = (id, data) => request.put('/export/' + id, data);
export const deleteExport = (id) => request.delete('/export/' + id);
export const createExportRecord = createExport;
export const updateExportRecord = updateExport;
export const deleteExportRecord = deleteExport;
