import request from './request';
export const getQrcodeList = (params) => request.get('/qrcode', { params });
export const addQrcode = (data) => request.post('/qrcode', data);
export const updateQrcode = (data) => request.put('/qrcode', data);
export const deleteQrcode = (id) => request.delete('/qrcode/' + id);
