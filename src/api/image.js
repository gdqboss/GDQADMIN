import request from './request';
export const getImageList = (params) => request.get('/image', { params });
export const addImage = (data) => request.post('/image', data);
export const updateImage = (data) => request.put('/image', data);
export const deleteImage = (id) => request.delete('/image/' + id);
