import request from './request';
export const getApprovalList = (params) => request.get('/approval', { params });
export const addApproval = (data) => request.post('/approval', data);
export const updateApproval = (data) => request.put('/approval', data);
export const deleteApproval = (id) => request.delete('/approval/' + id);
