import request from './request';

export const getInventoryList = (params) => request.get('/inventory', { params });
export const getInventoryDetail = (id) => request.get('/inventory/' + id);
export const createInventory = (data) => request.post('/inventory', data);
export const updateInventory = (id, data) => request.put('/inventory/' + id, data);
export const deleteInventory = (id) => request.delete('/inventory/' + id);
export default { getInventoryList, getInventoryDetail, createInventory, updateInventory, deleteInventory };
