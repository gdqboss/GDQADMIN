import request from './request';

export const getContractList = (params) => request.get('/contract', { params });
export const getContractDetail = (id) => request.get('/contract/' + id);
export const createContract = (data) => request.post('/contract', data);
export const updateContract = (id, data) => request.put('/contract/' + id, data);
export const deleteContract = (id) => request.delete('/contract/' + id);
