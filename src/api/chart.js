import request from './request';

export const getChartList = (params) => request.get('/chart', { params });
export const getChartDetail = (id) => request.get('/chart/' + id);
export const createChart = (data) => request.post('/chart', data);
export const updateChart = (id, data) => request.put('/chart/' + id, data);
export const deleteChart = (id) => request.delete('/chart/' + id);
