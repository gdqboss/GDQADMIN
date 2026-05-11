import request from './request';
export const getScheduleList = (params) => request.get('/schedule', { params });
export const addSchedule = (data) => request.post('/schedule', data);
export const updateSchedule = (data) => request.put('/schedule', data);
export const deleteSchedule = (id) => request.delete('/schedule/' + id);
