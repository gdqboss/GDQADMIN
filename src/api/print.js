import request from './request';

export const getPrintList = (params) => request.get('/print', { params });
export const getPrintDetail = (id) => request.get('/print/' + id);
export const createPrint = (data) => request.post('/print', data);
export const updatePrint = (id, data) => request.put('/print/' + id, data);
export const deletePrint = (id) => request.delete('/print/' + id);
export default { getPrintList, getPrintDetail, createPrint, updatePrint, deletePrint, getPrintList, getPrintDetail, createPrint, updatePrint, deletePrint };
