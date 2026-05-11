import request from './request';

export const getSmsList = (params) => request.get('/sms', { params });
export const getSmsDetail = (id) => request.get('/sms/' + id);
export const createSms = (data) => request.post('/sms', data);
export const updateSms = (id, data) => request.put('/sms/' + id, data);
export const deleteSms = (id) => request.delete('/sms/' + id);
