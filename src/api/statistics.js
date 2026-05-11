import request from './request';

export const getStatisticsList = (params) => request.get('/statistics', { params });
export const getStatisticsDetail = (id) => request.get('/statistics/' + id);
export const createStatistics = (data) => request.post('/statistics', data);
export const updateStatistics = (id, data) => request.put('/statistics/' + id, data);
export const deleteStatistics = (id) => request.delete('/statistics/' + id);
export const createStatistic = createStatistics;
export const deleteStatistic = deleteStatistics;
export const updateStatistic = updateStatistics;
