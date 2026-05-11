import request from './request';

export const getAnalyticsList = (params) => request.get('/analytics', { params });
export const getAnalyticsDetail = (id) => request.get('/analytics/' + id);
export const createAnalytics = (data) => request.post('/analytics', data);
export const updateAnalytics = (id, data) => request.put('/analytics/' + id, data);
export const deleteAnalytics = (id) => request.delete('/analytics/' + id);
