import request from './request';

export const getAccountList = (params) => request.get('/account', { params });
export const getAccountDetail = (id) => request.get('/account/' + id);
export const addAccount = (data) => request.post('/account', data);
export const createAccount = (data) => request.post('/account', data);
export const updateAccount = (id, data) => request.put('/account/' + id, data);
export const deleteAccount = (id) => request.delete('/account/' + id);
