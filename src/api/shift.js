import request from './request';
export const getShiftList = (params) => request.get('/shift', { params });
export const addShift = (data) => request.post('/shift', data);
export const updateShift = (data) => request.put('/shift', data);
export const deleteShift = (id) => request.delete('/shift/' + id);
