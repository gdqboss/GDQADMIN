import request from './request';

export const getMemberList = (params) => request.get('/member', { params });
export const getMemberDetail = (id) => request.get('/member/' + id);
export const createMember = (data) => request.post('/member', data);
export const updateMember = (id, data) => request.put('/member/' + id, data);
export const deleteMember = (id) => request.delete('/member/' + id);
