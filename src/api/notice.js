import request from './request';

export const getNoticeList = (params) => request.get('/notice', { params });
export const getNoticeDetail = (id) => request.get('/notice/' + id);
export const createNotice = (data) => request.post('/notice', data);
export const updateNotice = (id, data) => request.put('/notice/' + id, data);
export const deleteNotice = (id) => request.delete('/notice/' + id);
