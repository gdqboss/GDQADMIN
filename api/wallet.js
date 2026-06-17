import request from './request';

export const walletApi = {
  // 我的钱包
  getMyWallet: () => request.get('/api/wallet/my'),
  getMyLogs: (params) => request.get('/api/wallet/my/logs', { params }),
  
  // 后台管理
  list: (params) => request.get('/api/wallet/list', { params }),
  detail: (userId) => request.get(`/api/wallet/${userId}`),
  adjust: (data) => request.post('/api/wallet/adjust', data),
  freeze: (userId) => request.post(`/api/wallet/${userId}/freeze`),
  unfreeze: (userId) => request.post(`/api/wallet/${userId}/unfreeze`),
  
  // 充值配置
  getRechargeConfigs: () => request.get('/api/wallet/recharge/configs'),
  createRechargeConfig: (data) => request.post('/api/wallet/recharge/config', data),
  updateRechargeConfig: (id, data) => request.put(`/api/wallet/recharge/config/${id}`, data),
  deleteRechargeConfig: (id) => request.delete(`/api/wallet/recharge/config/${id}`),
  
  // 充值订单
  createRechargeOrder: (data) => request.post('/api/wallet/recharge/order', data),
  getRechargeOrders: (params) => request.get('/api/wallet/recharge/orders', { params }),
  rechargeNotify: (data) => request.post('/api/wallet/recharge/notify', data),
  
  // 提现
  applyWithdraw: (data) => request.post('/api/wallet/withdraw/apply', data),
  getWithdrawList: (params) => request.get('/api/wallet/withdraw/list', { params }),
  approveWithdraw: (id, data) => request.post(`/api/wallet/withdraw/${id}/approve`, data),
  rejectWithdraw: (id, data) => request.post(`/api/wallet/withdraw/${id}/reject`, data),
};