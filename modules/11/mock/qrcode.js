// ===== 二维码数据 =====
export const qrcodes = [
  { id: 1, code: 'GDQ-20260220-000001', productId: 1, productName: '无线鼠标 2.4G', sku: 'SKU-001', status: 'bindProduct', scanCount: 12, createdAt: '2026-02-18 10:00', boundAt: '2026-02-18 10:30', warehouse: '深圳总仓' },
  { id: 2, code: 'GDQ-20260220-000002', productId: 1, productName: '无线鼠标 2.4G', sku: 'SKU-001', status: 'sold', scanCount: 8, createdAt: '2026-02-18 10:00', boundAt: '2026-02-18 10:31', warehouse: '深圳总仓', buyer: '李明', buyDate: '2026-02-19', warrantyEnd: '2027-02-19', salesPerson: '小李' },
  { id: 3, code: 'GDQ-20260220-000003', productId: 2, productName: 'Type-C 扩展坞 7合1', sku: 'SKU-002', status: 'shipped', scanCount: 5, createdAt: '2026-02-18 10:00', boundAt: '2026-02-18 11:00', warehouse: '深圳总仓', dealer: '华南电子', shipDate: '2026-02-19', trackingNo: 'SF1234567890' },
  { id: 4, code: 'GDQ-20260220-000004', productId: 3, productName: 'iPhone15 手机壳', sku: 'SKU-003', status: 'sold', scanCount: 3, createdAt: '2026-02-18 10:01', boundAt: '2026-02-18 11:05', warehouse: '义乌分仓', buyer: '王芳', buyDate: '2026-02-20', warrantyEnd: '2027-02-20', salesPerson: '小李' },
  { id: 5, code: 'GDQ-20260220-000005', productId: null, productName: null, sku: null, status: 'unused', scanCount: 0, createdAt: '2026-02-20 09:00', boundAt: null, warehouse: null },
  { id: 6, code: 'GDQ-20260220-000006', productId: null, productName: null, sku: null, status: 'unused', scanCount: 0, createdAt: '2026-02-20 09:00', boundAt: null, warehouse: null },
  { id: 7, code: 'GDQ-20260220-000007', productId: 4, productName: 'USB-C 快充数据线', sku: 'SKU-006', status: 'bindProduct', scanCount: 1, createdAt: '2026-02-20 09:01', boundAt: '2026-02-20 10:00', warehouse: '深圳总仓' },
  { id: 8, code: 'GDQ-20260220-000008', productId: 5, productName: 'LED 折叠台灯', sku: 'SKU-007', status: 'afterSale', scanCount: 15, createdAt: '2026-02-15 08:00', boundAt: '2026-02-15 09:00', warehouse: '深圳总仓', buyer: '赵强', buyDate: '2026-02-16', warrantyEnd: '2027-02-16', salesPerson: '小李' },
]

// 状态映射
export const qrcodeStatusMap = {
  unused: { label: '未使用', type: 'info' },
  bindProduct: { label: '已绑定', type: 'primary' },
  inStock: { label: '已入库', type: 'primary' },
  outStock: { label: '已出库', type: 'warning' },
  shipped: { label: '已发货', type: 'warning' },
  sold: { label: '已销售', type: 'success' },
  sold_out: { label: '已售罄', type: 'success' },
  activated: { label: '已激活', type: 'success' },
  afterSale: { label: '售后中', type: 'danger' },
  returned: { label: '已退货', type: 'danger' },
  disabled: { label: '已禁用', type: 'info' },
}

// ===== 扫码记录 =====
export const scanLogs = [
  { id: 1, qrcodeId: 2, code: 'GDQ-20260220-000002', scanner: '李明', role: '购买者', action: '查看商品详情', time: '2026-02-20 14:30', location: '广东深圳' },
  { id: 2, qrcodeId: 8, code: 'GDQ-20260220-000008', scanner: '赵强', role: '购买者', action: '申请售后', time: '2026-02-20 11:20', location: '广东广州' },
  { id: 3, qrcodeId: 3, code: 'GDQ-20260220-000003', scanner: '王建国', role: '仓管', action: '发货扫码', time: '2026-02-19 16:00', location: '深圳总仓' },
  { id: 4, qrcodeId: 2, code: 'GDQ-20260220-000002', scanner: '小李', role: '销售员', action: '销售确认', time: '2026-02-19 10:00', location: '深圳总仓' },
  { id: 5, qrcodeId: 1, code: 'GDQ-20260220-000001', scanner: '张姐', role: '客服', action: '查看商品信息', time: '2026-02-19 09:30', location: '深圳总仓' },
  { id: 6, qrcodeId: 4, code: 'GDQ-20260220-000004', scanner: '匿名用户', role: '普通用户', action: '查看商品详情', time: '2026-02-20 15:00', location: '浙江杭州' },
  { id: 7, qrcodeId: 8, code: 'GDQ-20260220-000008', scanner: '技术员刘工', role: '技术员', action: '维修记录', time: '2026-02-20 16:00', location: '广东广州' },
]

// ===== 售后记录 =====
export const afterSaleRecords = [
  { id: 1, qrcodeId: 8, code: 'GDQ-20260220-000008', productName: 'LED 折叠台灯', buyer: '赵强', issue: '灯光闪烁，无法正常调光', status: 'processing', createdAt: '2026-02-20 11:20', handler: '客服小王', handlerNote: '已安排技术员上门检修' },
  { id: 2, qrcodeId: 2, code: 'GDQ-20260220-000002', productName: '无线鼠标 2.4G', buyer: '李明', issue: '鼠标左键偶尔失灵', status: 'resolved', createdAt: '2026-02-17 09:00', handler: '客服小王', handlerNote: '已更换新品，旧品回收' },
]

// ===== 层级关系与佣金 =====
export const userHierarchy = [
  { id: 1, name: '刘总', role: 'VIP', level: 0, parent: null, totalCommission: 2400, totalPoints: 8500 },
  { id: 2, name: '张姐', role: 'VIP', level: 1, parent: '刘总', totalCommission: 1200, totalPoints: 4200 },
  { id: 3, name: '小李', role: '销售员', level: 2, parent: '张姐', totalCommission: 600, totalPoints: 2100 },
  { id: 4, name: '李明', role: '购买者', level: 3, parent: '小李', totalCommission: 0, totalPoints: 350 },
  { id: 5, name: '王芳', role: '购买者', level: 3, parent: '小李', totalCommission: 0, totalPoints: 200 },
  { id: 6, name: '赵强', role: '购买者', level: 3, parent: '张姐', totalCommission: 0, totalPoints: 150 },
]

export const commissionRecords = [
  { id: 1, qrcodeCode: 'GDQ-20260220-000002', productName: '无线鼠标 2.4G', buyer: '李明', amount: 25.00, beneficiary: '张姐', type: '销售佣金', status: 'settled', createdAt: '2026-02-19 10:00' },
  { id: 2, qrcodeCode: 'GDQ-20260220-000004', productName: 'iPhone15 手机壳', buyer: '王芳', amount: 2.80, beneficiary: '张姐', type: '销售佣金', status: 'settled', createdAt: '2026-02-20 14:00' },
  { id: 3, qrcodeCode: 'GDQ-20260220-000002', productName: '无线鼠标 2.4G', buyer: '李明', amount: 50.00, beneficiary: '刘总', type: '层级佣金', status: 'settled', createdAt: '2026-02-19 10:00' },
  { id: 4, qrcodeCode: 'GDQ-20260220-000008', productName: 'LED 折叠台灯', buyer: '赵强', amount: 22.00, beneficiary: '张姐', type: '销售佣金', status: 'pending', createdAt: '2026-02-16 12:00' },
]
