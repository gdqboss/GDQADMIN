import request from './request';

export const getBarcodeList = (params) => request.get('/barcode', { params });
export const getBarcodeDetail = (id) => request.get('/barcode/' + id);
export const createBarcode = (data) => request.post('/barcode', data);
export const updateBarcode = (id, data) => request.put('/barcode/' + id, data);
export const deleteBarcode = (id) => request.delete('/barcode/' + id);
export default { getBarcodeList, getBarcodeDetail, createBarcode, updateBarcode, deleteBarcode };
