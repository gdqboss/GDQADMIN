import request from './request';

export const getLevelList = (params) => request.get('/level', { params });
export const getLevelDetail = (id) => request.get('/level/' + id);
export const createLevel = (data) => request.post('/level', data);
export const updateLevel = (id, data) => request.put('/level/' + id, data);
export const deleteLevel = (id) => request.delete('/level/' + id);
export default { getLevelList, getLevelDetail, createLevel, updateLevel, deleteLevel };
