import request from './request';

export const getStaffList = (params) => request.get('/staff', { params });
export const getStaffDetail = (id) => request.get('/staff/' + id);
export const createStaff = (data) => request.post('/staff', data);
export const updateStaff = (id, data) => request.put('/staff/' + id, data);
export const deleteStaff = (id) => request.delete('/staff/' + id);
export const updateStaffInfo = updateStaff;
