import request from './request';

export const getAssetList = (params) => request.get('/asset', { params });
export const getAssetDetail = (id) => request.get('/asset/' + id);
export const createAsset = (data) => request.post('/asset', data);
export const updateAsset = (id, data) => request.put('/asset/' + id, data);
export const deleteAsset = (id) => request.delete('/asset/' + id);
export default { getAssetList, getAssetDetail, createAsset, updateAsset, deleteAsset };
