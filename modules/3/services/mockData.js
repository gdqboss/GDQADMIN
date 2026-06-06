// Mock data for development and demo purposes

export const mockDashboardStats = {
  productCount: 156,
  warehouseCount: 5,
  totalStock: '2,458,900',
  alertCount: 12,
  pendingApprovals: 8,
  todayInbound: '245',
  todayOutbound: '189',
  recentInbound: [],
  recentOutbound: []
}

export const mockStockAlerts = [
  { id: 1, sku: 'GDQ-2024-001', product_name: 'iPhone 15 Pro Max 256GB', current_stock: 5, min_stock: 20, level: 'critical', warehouse: '深圳仓' },
  { id: 2, sku: 'GDQ-2024-002', product_name: 'MacBook Pro 14寸 M3', current_stock: 8, min_stock: 15, level: 'warning', warehouse: '广州仓' },
  { id: 3, sku: 'GDQ-2024-003', product_name: 'AirPods Pro 2代', current_stock: 12, min_stock: 30, level: 'warning', warehouse: '深圳仓' },
  { id: 4, sku: 'GDQ-2024-004', product_name: 'iPad Air 11寸 128GB', current_stock: 3, min_stock: 25, level: 'critical', warehouse: '上海仓' },
  { id: 5, sku: 'GDQ-2024-005', product_name: 'Apple Watch Series 9', current_stock: 15, min_stock: 40, level: 'warning', warehouse: '北京仓' },
  { id: 6, sku: 'GDQ-2024-006', product_name: 'Magic Keyboard 中文版', current_stock: 6, min_stock: 20, level: 'critical', warehouse: '深圳仓' },
  { id: 7, sku: 'GDQ-2024-007', product_name: 'HomePod mini 白色', current_stock: 18, min_stock: 35, level: 'warning', warehouse: '广州仓' },
  { id: 8, sku: 'GDQ-2024-008', product_name: 'AirTag 4件装', current_stock: 9, min_stock: 25, level: 'warning', warehouse: '上海仓' },
]

export const mockPendingApprovals = [
  { id: 1, title: '采购申请 - iPhone 15系列补货', applicant: '张伟', department: '采购部', urgency: 'high', created_at: '2024-02-26 09:30' },
  { id: 2, title: '出库审批 - 经销商订单 #2024022601', applicant: '李娜', department: '销售部', urgency: 'medium', created_at: '2024-02-26 10:15' },
  { id: 3, title: '调拨申请 - 深圳仓→广州仓', applicant: '王强', department: '仓储部', urgency: 'low', created_at: '2024-02-26 11:20' },
  { id: 4, title: '报废申请 - 损坏商品处理', applicant: '刘芳', department: '质检部', urgency: 'medium', created_at: '2024-02-26 13:45' },
  { id: 5, title: '采购申请 - MacBook Pro M3补货', applicant: '陈明', department: '采购部', urgency: 'high', created_at: '2024-02-26 14:30' },
]

export const mockWarehouses = [
  { id: 1, name: '深圳总仓', location: '深圳市南山区', totalQty: 8500, capacity: 10000, manager: '张伟', status: 'active' },
  { id: 2, name: '广州分仓', location: '广州市天河区', totalQty: 6200, capacity: 8000, manager: '李娜', status: 'active' },
  { id: 3, name: '上海分仓', location: '上海市浦东新区', totalQty: 4800, capacity: 8000, manager: '王强', status: 'active' },
  { id: 4, name: '北京分仓', location: '北京市朝阳区', totalQty: 3500, capacity: 6000, manager: '刘芳', status: 'active' },
  { id: 5, name: '成都分仓', location: '成都市高新区', totalQty: 2100, capacity: 5000, manager: '陈明', status: 'active' },
]

export const mockMonthlyTrend = [
  { month: '1', inbound: 1200, outbound: 980 },
  { month: '2', inbound: 1450, outbound: 1150 },
  { month: '3', inbound: 1680, outbound: 1320 },
  { month: '4', inbound: 1520, outbound: 1280 },
  { month: '5', inbound: 1890, outbound: 1560 },
  { month: '6', inbound: 2100, outbound: 1780 },
]

export const mockProducts = [
  { id: 1, sku: 'GDQ-2024-001', name: 'iPhone 15 Pro Max 256GB 钛金属', category: '手机', price: 9999, stock: 45, warehouse: '深圳总仓', status: 'active', supplier: '苹果官方', image: '/uploads/iphone15.jpg' },
  { id: 2, sku: 'GDQ-2024-002', name: 'MacBook Pro 14寸 M3 16GB 512GB', category: '电脑', price: 15999, stock: 28, warehouse: '广州分仓', status: 'active', supplier: '苹果官方', image: '/uploads/macbook.jpg' },
  { id: 3, sku: 'GDQ-2024-003', name: 'AirPods Pro 2代 USB-C', category: '配件', price: 1899, stock: 156, warehouse: '深圳总仓', status: 'active', supplier: '苹果官方', image: '/uploads/airpods.jpg' },
  { id: 4, sku: 'GDQ-2024-004', name: 'iPad Air 11寸 M2 128GB', category: '平板', price: 4799, stock: 67, warehouse: '上海分仓', status: 'active', supplier: '苹果官方', image: '/uploads/ipad.jpg' },
  { id: 5, sku: 'GDQ-2024-005', name: 'Apple Watch Series 9 GPS 45mm', category: '手表', price: 3199, stock: 89, warehouse: '北京分仓', status: 'active', supplier: '苹果官方', image: '/uploads/watch.jpg' },
  { id: 6, sku: 'GDQ-2024-006', name: 'Magic Keyboard 中文版 白色', category: '配件', price: 799, stock: 234, warehouse: '深圳总仓', status: 'active', supplier: '苹果官方', image: '/uploads/keyboard.jpg' },
  { id: 7, sku: 'GDQ-2024-007', name: 'HomePod mini 白色', category: '音响', price: 749, stock: 123, warehouse: '广州分仓', status: 'active', supplier: '苹果官方', image: '/uploads/homepod.jpg' },
  { id: 8, sku: 'GDQ-2024-008', name: 'AirTag 4件装', category: '配件', price: 799, stock: 345, warehouse: '上海分仓', status: 'active', supplier: '苹果官方', image: '/uploads/airtag.jpg' },
]

export const mockInOutRecords = [
  { id: 1, type: 'in', product_name: 'iPhone 15 Pro Max 256GB', sku: 'GDQ-2024-001', quantity: 50, warehouse: '深圳总仓', operator: '张伟', created_at: '2024-02-26 09:30', status: 'completed', supplier: '苹果官方', order_no: 'PO-2024022601' },
  { id: 2, type: 'out', product_name: 'MacBook Pro 14寸 M3', sku: 'GDQ-2024-002', quantity: 15, warehouse: '广州分仓', operator: '李娜', created_at: '2024-02-26 10:15', status: 'completed', customer: '华强北数码城', order_no: 'SO-2024022601' },
  { id: 3, type: 'in', product_name: 'AirPods Pro 2代', sku: 'GDQ-2024-003', quantity: 200, warehouse: '深圳总仓', operator: '王强', created_at: '2024-02-26 11:20', status: 'completed', supplier: '苹果官方', order_no: 'PO-2024022602' },
  { id: 4, type: 'out', product_name: 'iPad Air 11寸', sku: 'GDQ-2024-004', quantity: 30, warehouse: '上海分仓', operator: '刘芳', created_at: '2024-02-26 13:45', status: 'pending', customer: '上海电子市场', order_no: 'SO-2024022602' },
  { id: 5, type: 'in', product_name: 'Apple Watch Series 9', sku: 'GDQ-2024-005', quantity: 80, warehouse: '北京分仓', operator: '陈明', created_at: '2024-02-26 14:30', status: 'completed', supplier: '苹果官方', order_no: 'PO-2024022603' },
]

export const mockInboundRecords = mockInOutRecords.filter(r => r.type === 'in')
export const mockOutboundRecords = mockInOutRecords.filter(r => r.type === 'out')
export const mockReturnRecords = []

export const mockSuppliers = [
  { id: 1, name: '苹果官方授权经销商', contact: '张经理', phone: '13800138001', email: 'zhang@apple-dealer.com', address: '深圳市南山区科技园', status: 'active', products_count: 156, total_orders: 245 },
  { id: 2, name: '华为企业采购部', contact: '李经理', phone: '13800138002', email: 'li@huawei.com', address: '深圳市龙岗区华为基地', status: 'active', products_count: 89, total_orders: 178 },
  { id: 3, name: '小米供应链管理', contact: '王经理', phone: '13800138003', email: 'wang@xiaomi.com', address: '北京市海淀区小米科技园', status: 'active', products_count: 123, total_orders: 203 },
]

export const mockDealers = [
  { id: 1, name: '华强北数码城', contact: '赵总', phone: '13900139001', email: 'zhao@hqb.com', address: '深圳市福田区华强北路', level: 'A', status: 'active', total_orders: 456, total_amount: 12580000 },
  { id: 2, name: '上海电子市场', contact: '钱总', phone: '13900139002', email: 'qian@sh-elec.com', address: '上海市徐汇区漕溪北路', level: 'B', status: 'active', total_orders: 234, total_amount: 6780000 },
  { id: 3, name: '广州天河电脑城', contact: '孙总', phone: '13900139003', email: 'sun@gz-pc.com', address: '广州市天河区岗顶', level: 'A', status: 'active', total_orders: 389, total_amount: 9560000 },
]

export const mockStores = [
  { id: 1, name: 'GDQ旗舰店（深圳）', address: '深圳市南山区海岸城', manager: '周店长', phone: '0755-88888888', status: 'active', area: 500, staff_count: 12, monthly_sales: 2580000 },
  { id: 2, name: 'GDQ体验店（广州）', address: '广州市天河区正佳广场', manager: '吴店长', phone: '020-88888888', status: 'active', area: 350, staff_count: 8, monthly_sales: 1680000 },
  { id: 3, name: 'GDQ专卖店（上海）', address: '上海市浦东新区陆家嘴', manager: '郑店长', phone: '021-88888888', status: 'active', area: 420, staff_count: 10, monthly_sales: 2180000 },
]
