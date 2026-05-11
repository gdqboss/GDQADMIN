import request from './request';

export const getCardList = (params) => request.get('/card', { params });
export const getCardDetail = (id) => request.get('/card/' + id);
export const createCard = (data) => request.post('/card', data);
export const updateCard = (id, data) => request.put('/card/' + id, data);
export const deleteCard = (id) => request.delete('/card/' + id);
