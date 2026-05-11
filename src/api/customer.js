import request from './request';
export const getCustomerList = (params) => request.get('/customer', { params });
export const addCustomer = (data) => request.post('/customer', data);
export const updateCustomer = (data) => request.put('/customer', data);
export const deleteCustomer = (id) => request.delete('/customer/' + id);
