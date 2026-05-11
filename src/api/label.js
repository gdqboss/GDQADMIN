import request from './request';

export const getLabelList = (params) => request.get('/label', { params });
export const getLabelDetail = (id) => request.get('/label/' + id);
export const createLabel = (data) => request.post('/label', data);
export const updateLabel = (id, data) => request.put('/label/' + id, data);
export const deleteLabel = (id) => request.delete('/label/' + id);
export default { getLabelList, getLabelDetail, createLabel, updateLabel, deleteLabel, getLabelList, getLabelDetail, createLabel, updateLabel, deleteLabel };
