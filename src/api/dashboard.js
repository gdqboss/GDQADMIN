import request from './request';

export const getDashboardList = (params) => request.get('/dashboard', { params });
export const getDashboardDetail = (id) => request.get('/dashboard/' + id);
export const createDashboard = (data) => request.post('/dashboard', data);
export const updateDashboard = (id, data) => request.put('/dashboard/' + id, data);
export const deleteDashboard = (id) => request.delete('/dashboard/' + id);
export default { getDashboardList, getDashboardDetail, createDashboard, updateDashboard, deleteDashboard };
