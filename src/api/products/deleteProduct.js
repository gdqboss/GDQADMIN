import request from '../request';

export default (params) => request.get('/products', { params });
