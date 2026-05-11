import request from './request';

export const getMessageList = (params) => request.get('/message', { params });
export const getMessageDetail = (id) => request.get('/message/' + id);
export const createMessage = (data) => request.post('/message', data);
export const updateMessage = (id, data) => request.put('/message/' + id, data);
export const deleteMessage = (id) => request.delete('/message/' + id);
