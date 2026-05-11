import request from './request';

export const getMenuList = (params) => request.get('/menu', { params });
export const getMenuDetail = (id) => request.get('/menu/' + id);
export const createMenu = (data) => request.post('/menu', data);
export const updateMenu = (id, data) => request.put('/menu/' + id, data);
export const deleteMenu = (id) => request.delete('/menu/' + id);
