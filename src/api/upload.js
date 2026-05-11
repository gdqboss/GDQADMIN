import request from './request';

export const getUploadList = (params) => request.get('/upload', { params });
export const getUploadDetail = (id) => request.get('/upload/' + id);
export const createUpload = (data) => request.post('/upload', data);
export const updateUpload = (id, data) => request.put('/upload/' + id, data);
export const deleteUpload = (id) => request.delete('/upload/' + id);
export default { getUploadList, getUploadDetail, createUpload, updateUpload, deleteUpload, getUploadList, getUploadDetail, createUpload, updateUpload, deleteUpload };
