import request from './request';
export const getLeaveList = (params) => request.get('/leave', { params });
export const addLeave = (data) => request.post('/leave', data);
export const updateLeave = (data) => request.put('/leave', data);
export const deleteLeave = (id) => request.delete('/leave/' + id);
