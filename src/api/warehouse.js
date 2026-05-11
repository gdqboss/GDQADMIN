import request from './request';

export const getWarehouseList = (params) => request.get('/warehouse', { params });
export const getWarehouseDetail = (id) => request.get('/warehouse/' + id);
export const createWarehouse = (data) => request.post('/warehouse', data);
export const updateWarehouse = (id, data) => request.put('/warehouse/' + id, data);
export const deleteWarehouse = (id) => request.delete('/warehouse/' + id);
export default { getWarehouseList, getWarehouseDetail, createWarehouse, updateWarehouse, deleteWarehouse, getWarehouseList, getWarehouseDetail, createWarehouse, updateWarehouse, deleteWarehouse };
