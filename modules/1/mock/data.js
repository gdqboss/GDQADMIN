// ===== 商品数据 =====
export const products = [
  { id: 1, sku: 'SKU-001', name: '无线静音鼠标', category: '电子配件', spec: '2.4G/蓝牙双模', unit: '个', supplier: '深圳科创电子', purchasePrice: 25.00, salePrice: 59.90, stock: 5, safeStock: 50, image: '', status: 'low' },
  { id: 2, sku: 'SKU-002', name: 'Type-C 扩展坞', category: '电子配件', spec: '7合1 HDMI+USB3.0', unit: '个', supplier: '东莞联达科技', purchasePrice: 45.00, salePrice: 129.00, stock: 230, safeStock: 30, image: '', status: 'normal' },
  { id: 3, sku: 'SKU-003', name: '蓝牙耳机', category: '电子配件', spec: 'TWS 5.3 降噪', unit: '个', supplier: '深圳科创电子', purchasePrice: 38.00, salePrice: 99.00, stock: 180, safeStock: 40, image: '', status: 'normal' },
  { id: 4, sku: 'SKU-004', name: '硅胶手机壳', category: '手机配件', spec: 'iPhone 15 Pro Max 透明', unit: '个', supplier: '义乌百汇日用', purchasePrice: 3.50, salePrice: 19.90, stock: 1500, safeStock: 200, image: '', status: 'normal' },
  { id: 5, sku: 'SKU-005', name: '钢化膜', category: '手机配件', spec: 'iPhone 15 全系列 高清', unit: '片', supplier: '义乌百汇日用', purchasePrice: 1.20, salePrice: 9.90, stock: 3200, safeStock: 500, image: '', status: 'normal' },
  { id: 6, sku: 'SKU-006', name: 'USB-C 数据线', category: '电子配件', spec: '1.5m 100W 快充', unit: '条', supplier: '东莞联达科技', purchasePrice: 5.00, salePrice: 24.90, stock: 12, safeStock: 100, image: '', status: 'low' },
  { id: 7, sku: 'SKU-007', name: '桌面收纳盒', category: '家居用品', spec: '三层 ABS材质 白色', unit: '个', supplier: '义乌百汇日用', purchasePrice: 12.00, salePrice: 39.90, stock: 420, safeStock: 50, image: '', status: 'normal' },
  { id: 8, sku: 'SKU-008', name: 'LED 台灯', category: '家居用品', spec: '三色调光 折叠式', unit: '个', supplier: '中山光明照明', purchasePrice: 28.00, salePrice: 79.90, stock: 85, safeStock: 30, image: '', status: 'normal' },
  { id: 9, sku: 'SKU-009', name: '便携风扇', category: '家居用品', spec: 'USB充电 三档风力', unit: '个', supplier: '中山光明照明', purchasePrice: 15.00, salePrice: 49.90, stock: 8, safeStock: 60, image: '', status: 'low' },
  { id: 10, sku: 'SKU-010', name: '笔记本支架', category: '电子配件', spec: '铝合金 可调节', unit: '个', supplier: '深圳科创电子', purchasePrice: 35.00, salePrice: 89.90, stock: 145, safeStock: 25, image: '', status: 'normal' },
  { id: 11, sku: 'SKU-011', name: '手机支架', category: '手机配件', spec: '车载磁吸式', unit: '个', supplier: '义乌百汇日用', purchasePrice: 8.00, salePrice: 29.90, stock: 2, safeStock: 80, image: '', status: 'low' },
  { id: 12, sku: 'SKU-012', name: '无线充电器', category: '电子配件', spec: '15W 快充 兼容Qi', unit: '个', supplier: '深圳科创电子', purchasePrice: 22.00, salePrice: 69.90, stock: 95, safeStock: 30, image: '', status: 'normal' },
]

export const categories = ['电子配件', '手机配件', '家居用品', '办公用品', '服装', '食品']

// ===== 仓库数据 =====
export const warehouses = [
  { id: 1, name: '深圳总仓', address: '深圳市宝安区XX路XX号', type: '国内仓', manager: '王建国', totalItems: 8420, totalValue: 680000, usagePercent: 72, status: 'normal' },
  { id: 2, name: '义乌分仓', address: '义乌市国际商贸城D区', type: '国内仓', manager: '李秀芳', totalItems: 4150, totalValue: 320000, usagePercent: 55, status: 'normal' },
  { id: 3, name: '海外仓 (USA)', address: 'Los Angeles, CA 90001', type: '海外仓', manager: 'Jack Chen', totalItems: 2430, totalValue: 195000, usagePercent: 38, status: 'normal' },
  { id: 4, name: '保税仓', address: '深圳前海保税区', type: '保税仓', manager: '张伟', totalItems: 1200, totalValue: 45000, usagePercent: 25, status: 'warning' },
]

export const warehouseStockDetail = {
  1: [
    { sku: 'SKU-001', name: '无线静音鼠标', stock: 3, safeStock: 50, location: 'A-01-03', lastUpdated: '2026-02-20' },
    { sku: 'SKU-002', name: 'Type-C 扩展坞', stock: 150, safeStock: 30, location: 'A-02-01', lastUpdated: '2026-02-19' },
    { sku: 'SKU-003', name: '蓝牙耳机', stock: 120, safeStock: 40, location: 'A-03-02', lastUpdated: '2026-02-20' },
    { sku: 'SKU-010', name: '笔记本支架', stock: 100, safeStock: 25, location: 'B-01-01', lastUpdated: '2026-02-18' },
    { sku: 'SKU-012', name: '无线充电器', stock: 65, safeStock: 30, location: 'B-02-03', lastUpdated: '2026-02-20' },
  ],
  2: [
    { sku: 'SKU-004', name: '硅胶手机壳', stock: 1200, safeStock: 200, location: 'C-01-01', lastUpdated: '2026-02-20' },
    { sku: 'SKU-005', name: '钢化膜', stock: 2800, safeStock: 500, location: 'C-02-01', lastUpdated: '2026-02-19' },
    { sku: 'SKU-007', name: '桌面收纳盒', stock: 300, safeStock: 50, location: 'D-01-02', lastUpdated: '2026-02-20' },
    { sku: 'SKU-011', name: '手机支架', stock: 2, safeStock: 80, location: 'D-03-01', lastUpdated: '2026-02-18' },
  ],
  3: [
    { sku: 'SKU-002', name: 'Type-C 扩展坞', stock: 80, safeStock: 20, location: 'E-01-01', lastUpdated: '2026-02-17' },
    { sku: 'SKU-003', name: '蓝牙耳机', stock: 60, safeStock: 20, location: 'E-02-01', lastUpdated: '2026-02-16' },
    { sku: 'SKU-008', name: 'LED 台灯', stock: 45, safeStock: 15, location: 'F-01-01', lastUpdated: '2026-02-18' },
  ],
  4: [
    { sku: 'SKU-006', name: 'USB-C 数据线', stock: 10, safeStock: 50, location: 'G-01-01', lastUpdated: '2026-02-20' },
    { sku: 'SKU-009', name: '便携风扇', stock: 5, safeStock: 30, location: 'G-02-01', lastUpdated: '2026-02-19' },
  ],
}

// ===== 出入库记录 =====
export const inboundRecords = [
  { id: 'RK-20260220-001', date: '2026-02-20', warehouse: '深圳总仓', supplier: '深圳科创电子', items: [{ sku: 'SKU-001', name: '无线静音鼠标', qty: 200 }, { sku: 'SKU-012', name: '无线充电器', qty: 100 }], totalQty: 300, operator: '王建国', status: 'completed', remark: '采购入库' },
  { id: 'RK-20260219-001', date: '2026-02-19', warehouse: '义乌分仓', supplier: '义乌百汇日用', items: [{ sku: 'SKU-004', name: '硅胶手机壳', qty: 500 }, { sku: 'SKU-005', name: '钢化膜', qty: 1000 }], totalQty: 1500, operator: '李秀芳', status: 'completed', remark: '补货入库' },
  { id: 'RK-20260218-001', date: '2026-02-18', warehouse: '深圳总仓', supplier: '东莞联达科技', items: [{ sku: 'SKU-002', name: 'Type-C 扩展坞', qty: 100 }], totalQty: 100, operator: '王建国', status: 'pending', remark: '采购入库-待验收' },
  { id: 'RK-20260217-001', date: '2026-02-17', warehouse: '海外仓 (USA)', supplier: '深圳科创电子', items: [{ sku: 'SKU-003', name: '蓝牙耳机', qty: 60 }], totalQty: 60, operator: 'Jack Chen', status: 'completed', remark: '海外仓补货' },
  { id: 'RK-20260215-001', date: '2026-02-15', warehouse: '保税仓', supplier: '东莞联达科技', items: [{ sku: 'SKU-006', name: 'USB-C 数据线', qty: 500 }], totalQty: 500, operator: '张伟', status: 'completed', remark: '保税入库' },
]

export const outboundRecords = [
  { id: 'CK-20260220-001', date: '2026-02-20', warehouse: '深圳总仓', customer: '亚马逊 FBA', items: [{ sku: 'SKU-002', name: 'Type-C 扩展坞', qty: 50 }, { sku: 'SKU-003', name: '蓝牙耳机', qty: 30 }], totalQty: 80, operator: '王建国', status: 'completed', remark: '出口发货' },
  { id: 'CK-20260219-001', date: '2026-02-19', warehouse: '义乌分仓', customer: '速卖通订单', items: [{ sku: 'SKU-004', name: '硅胶手机壳', qty: 200 }], totalQty: 200, operator: '李秀芳', status: 'shipping', remark: '国际物流' },
  { id: 'CK-20260218-001', date: '2026-02-18', warehouse: '海外仓 (USA)', customer: 'Amazon US', items: [{ sku: 'SKU-002', name: 'Type-C 扩展坞', qty: 20 }], totalQty: 20, operator: 'Jack Chen', status: 'completed', remark: '本地配送' },
  { id: 'CK-20260217-001', date: '2026-02-17', warehouse: '深圳总仓', customer: '内销-京东', items: [{ sku: 'SKU-010', name: '笔记本支架', qty: 30 }], totalQty: 30, operator: '王建国', status: 'pending', remark: '待审批' },
]

export const returnRecords = [
  { id: 'TH-20260220-001', date: '2026-02-20', warehouse: '深圳总仓', source: '客户退货', items: [{ sku: 'SKU-003', name: '蓝牙耳机', qty: 5 }], totalQty: 5, operator: '王建国', status: 'completed', remark: '质量问题退货' },
  { id: 'TH-20260218-001', date: '2026-02-18', warehouse: '义乌分仓', source: '客户退货', items: [{ sku: 'SKU-004', name: '硅胶手机壳', qty: 20 }], totalQty: 20, operator: '李秀芳', status: 'pending', remark: '包装破损' },
]

// ===== 审批数据 =====
export const approvals = [
  { id: 'SP-20260220-001', title: '采购申请 - 无线静音鼠标 500件', type: '采购审批', applicant: '张姐', department: '采购部', date: '2026-02-20', amount: 12500, status: 'pending', urgency: 'high', currentStep: 1, steps: ['提交申请', '采购经理审批', '财务确认', '执行采购'] },
  { id: 'SP-20260220-002', title: '出库申请 - 亚马逊 FBA 发货', type: '出库审批', applicant: '小李', department: '销售部', date: '2026-02-20', amount: 8500, status: 'pending', urgency: 'medium', currentStep: 1, steps: ['提交申请', '仓库确认', '发货'] },
  { id: 'SP-20260219-001', title: '仓库调拨 - 深圳→义乌 手机壳', type: '调拨审批', applicant: '王建国', department: '仓储部', date: '2026-02-19', amount: 0, status: 'approved', urgency: 'low', currentStep: 3, steps: ['提交申请', '审批', '执行调拨'] },
  { id: 'SP-20260218-001', title: '采购申请 - USB-C 数据线 1000条', type: '采购审批', applicant: '张姐', department: '采购部', date: '2026-02-18', amount: 5000, status: 'approved', urgency: 'medium', currentStep: 4, steps: ['提交申请', '采购经理审批', '财务确认', '执行采购'] },
  { id: 'SP-20260217-001', title: '盘点差异调整 - 深圳总仓', type: '盘点审批', applicant: '王建国', department: '仓储部', date: '2026-02-17', amount: 0, status: 'rejected', urgency: 'low', currentStep: 2, steps: ['提交报告', '审批调整', '执行'] },
  { id: 'SP-20260215-001', title: '采购申请 - LED 台灯 200个', type: '采购审批', applicant: '张姐', department: '采购部', date: '2026-02-15', amount: 5600, status: 'approved', urgency: 'low', currentStep: 4, steps: ['提交申请', '采购经理审批', '财务确认', '执行采购'] },
]

// ===== 库存预警 =====
export const stockAlerts = [
  { id: 1, sku: 'SKU-001', name: '无线静音鼠标', warehouse: '深圳总仓', currentStock: 5, safeStock: 50, suggestQty: 200, level: 'critical', createdAt: '2026-02-20 08:30', handled: false },
  { id: 2, sku: 'SKU-011', name: '手机支架', warehouse: '义乌分仓', currentStock: 2, safeStock: 80, suggestQty: 300, level: 'critical', createdAt: '2026-02-20 08:30', handled: false },
  { id: 3, sku: 'SKU-006', name: 'USB-C 数据线', warehouse: '保税仓', currentStock: 10, safeStock: 50, suggestQty: 500, level: 'critical', createdAt: '2026-02-20 08:30', handled: false },
  { id: 4, sku: 'SKU-009', name: '便携风扇', warehouse: '保税仓', currentStock: 5, safeStock: 30, suggestQty: 100, level: 'critical', createdAt: '2026-02-20 09:00', handled: false },
  { id: 5, sku: 'SKU-006', name: 'USB-C 数据线', warehouse: '深圳总仓', currentStock: 12, safeStock: 100, suggestQty: 500, level: 'warning', createdAt: '2026-02-19 14:20', handled: false },
  { id: 6, sku: 'SKU-009', name: '便携风扇', warehouse: '深圳总仓', currentStock: 8, safeStock: 60, suggestQty: 200, level: 'warning', createdAt: '2026-02-19 14:20', handled: false },
  { id: 7, sku: 'SKU-001', name: '无线静音鼠标', warehouse: '义乌分仓', currentStock: 15, safeStock: 50, suggestQty: 150, level: 'warning', createdAt: '2026-02-19 08:30', handled: true },
  { id: 8, sku: 'SKU-005', name: '钢化膜', warehouse: '海外仓 (USA)', currentStock: 100, safeStock: 200, suggestQty: 500, level: 'warning', createdAt: '2026-02-18 10:15', handled: true },
  { id: 9, sku: 'SKU-004', name: '硅胶手机壳', warehouse: '海外仓 (USA)', currentStock: 80, safeStock: 150, suggestQty: 300, level: 'warning', createdAt: '2026-02-18 10:15', handled: true },
  { id: 10, sku: 'SKU-002', name: 'Type-C 扩展坞', warehouse: '海外仓 (USA)', currentStock: 25, safeStock: 40, suggestQty: 80, level: 'warning', createdAt: '2026-02-17 16:00', handled: true },
  { id: 11, sku: 'SKU-008', name: 'LED 台灯', warehouse: '深圳总仓', currentStock: 18, safeStock: 30, suggestQty: 100, level: 'warning', createdAt: '2026-02-16 09:30', handled: true },
  { id: 12, sku: 'SKU-003', name: '蓝牙耳机', warehouse: '海外仓 (USA)', currentStock: 10, safeStock: 30, suggestQty: 60, level: 'warning', createdAt: '2026-02-15 11:00', handled: true },
]

// ===== AI 自动化 =====
export const aiTasks = [
  { id: 1, name: '1688 商品采集', skill: 'gdq-1688-scraper', platform: '1688', status: 'running', lastRun: '2026-02-20 10:00', nextRun: '2026-02-20 22:00', schedule: '每12小时', successRate: 94, totalRuns: 156 },
  { id: 2, name: '淘宝价格监控', skill: 'gdq-taobao-scraper', platform: '淘宝/天猫', status: 'idle', lastRun: '2026-02-20 06:00', nextRun: '2026-02-21 06:00', schedule: '每天', successRate: 88, totalRuns: 45 },
  { id: 3, name: '国际站数据采集', skill: 'gdq-alibaba-intl-scraper', platform: '阿里国际站', status: 'error', lastRun: '2026-02-19 18:00', nextRun: '-', schedule: '每天', successRate: 72, totalRuns: 30 },
  { id: 4, name: '库存预警通知', skill: 'gdq-stock-alert', platform: '钉钉', status: 'idle', lastRun: '2026-02-20 08:30', nextRun: '2026-02-20 20:30', schedule: '每12小时', successRate: 100, totalRuns: 60 },
]

export const aiCollectedData = [
  { id: 1, source: '1688', productName: '无线鼠标 静音办公 2.4G', price: 18.50, supplier: '深圳XX电子科技有限公司', category: '电子配件', collectedAt: '2026-02-20 10:15', priceChange: -2.5 },
  { id: 2, source: '1688', productName: 'Type-C 扩展坞 7合1', price: 38.00, supplier: '东莞YY科技有限公司', category: '电子配件', collectedAt: '2026-02-20 10:16', priceChange: 0 },
  { id: 3, source: '淘宝', productName: 'iPhone15 手机壳 透明硅胶', price: 2.80, supplier: '义乌ZZ百货店', category: '手机配件', collectedAt: '2026-02-20 06:20', priceChange: -0.5 },
  { id: 4, source: '淘宝', productName: '100W USB-C 快充线 1.5m', price: 4.20, supplier: '深圳快充科技', category: '电子配件', collectedAt: '2026-02-20 06:22', priceChange: +0.3 },
  { id: 5, source: '1688', productName: 'LED 折叠台灯 三色调光', price: 22.00, supplier: '中山AA照明', category: '家居用品', collectedAt: '2026-02-20 10:18', priceChange: -3.0 },
  { id: 6, source: '国际站', productName: 'Wireless Bluetooth Earbuds TWS', price: 4.20, supplier: 'Shenzhen Electronics Co.', category: '电子配件', collectedAt: '2026-02-19 18:05', priceChange: 0 },
]

export const aiAutomationRules = [
  { id: 1, name: '低库存自动采购申请', trigger: '库存低于安全线', action: '自动生成采购申请并提交钉钉审批', enabled: true, lastTriggered: '2026-02-20 08:35' },
  { id: 2, name: '价格异动通知', trigger: '采集价格变动 > 10%', action: '钉钉消息通知采购经理', enabled: true, lastTriggered: '2026-02-19 10:20' },
  { id: 3, name: '每日库存摘要', trigger: '每天 09:00', action: '钉钉群发送库存日报', enabled: true, lastTriggered: '2026-02-20 09:00' },
  { id: 4, name: '审批超时提醒', trigger: '审批单超过24小时未处理', action: '钉钉消息提醒审批人', enabled: false, lastTriggered: '2026-02-18 09:00' },
]

export const aiLogs = [
  { time: '2026-02-20 10:18', level: 'info', message: '[1688采集] 完成第3批采集，获取 15 条商品数据' },
  { time: '2026-02-20 10:16', level: 'info', message: '[1688采集] 完成第2批采集，获取 12 条商品数据' },
  { time: '2026-02-20 10:15', level: 'info', message: '[1688采集] 开始第1批采集，关键词: 无线鼠标' },
  { time: '2026-02-20 10:00', level: 'info', message: '[1688采集] 定时任务触发，开始执行采集' },
  { time: '2026-02-20 09:00', level: 'success', message: '[库存日报] 已发送至钉钉群，包含 4 个仓库数据' },
  { time: '2026-02-20 08:35', level: 'warning', message: '[自动采购] 检测到 4 项库存预警，已生成采购申请 SP-20260220-001' },
  { time: '2026-02-20 08:30', level: 'info', message: '[库存预警] 扫描完成，发现 6 项低于安全线' },
  { time: '2026-02-20 06:22', level: 'info', message: '[淘宝采集] 完成价格监控，采集 28 条数据' },
  { time: '2026-02-19 18:05', level: 'error', message: '[国际站采集] 采集失败: 页面加载超时，将在下次周期重试' },
  { time: '2026-02-19 14:20', level: 'warning', message: '[库存预警] 新增 2 项预警: SKU-006 USB-C数据线, SKU-009 便携风扇' },
]

// ===== 报表数据 =====
export const monthlyInOutData = [
  { month: '2025-09', inbound: 2100, outbound: 1800 },
  { month: '2025-10', inbound: 2800, outbound: 2200 },
  { month: '2025-11', inbound: 3200, outbound: 2500 },
  { month: '2025-12', inbound: 3600, outbound: 3100 },
  { month: '2026-01', inbound: 3100, outbound: 2700 },
  { month: '2026-02', inbound: 3450, outbound: 2100 },
]

export const categoryStockData = [
  { name: '电子配件', value: 667 },
  { name: '手机配件', value: 4702 },
  { name: '家居用品', value: 513 },
]

export const priceAnalysisData = [
  { month: '2025-09', sku001: 28, sku002: 48, sku003: 42 },
  { month: '2025-10', sku001: 27, sku002: 47, sku003: 40 },
  { month: '2025-11', sku001: 26, sku002: 46, sku003: 39 },
  { month: '2025-12', sku001: 25, sku002: 45, sku003: 38 },
  { month: '2026-01', sku001: 25, sku002: 45, sku003: 38 },
  { month: '2026-02', sku001: 25, sku002: 45, sku003: 38 },
]

export const warehouseStockSummary = [
  { warehouse: '深圳总仓', totalItems: 8420, totalValue: 680000, categories: { '电子配件': 3200, '手机配件': 2800, '家居用品': 2420 } },
  { warehouse: '义乌分仓', totalItems: 4150, totalValue: 320000, categories: { '电子配件': 800, '手机配件': 2500, '家居用品': 850 } },
  { warehouse: '海外仓 (USA)', totalItems: 2430, totalValue: 195000, categories: { '电子配件': 1200, '手机配件': 680, '家居用品': 550 } },
  { warehouse: '保税仓', totalItems: 1200, totalValue: 45000, categories: { '电子配件': 600, '手机配件': 300, '家居用品': 300 } },
]

// ===== 系统设置 =====
export const users = [
  { id: 1, name: '管理员', email: 'admin@gdq.com', role: '超级管理员', department: '管理层', status: 'active', lastLogin: '2026-02-20 09:00' },
  { id: 2, name: '张姐', email: 'zhang@gdq.com', role: '采购经理', department: '采购部', status: 'active', lastLogin: '2026-02-20 08:45' },
  { id: 3, name: '王建国', email: 'wang@gdq.com', role: '仓库管理员', department: '仓储部', status: 'active', lastLogin: '2026-02-20 07:30' },
  { id: 4, name: '小李', email: 'li@gdq.com', role: '销售', department: '销售部', status: 'active', lastLogin: '2026-02-19 18:00' },
  { id: 5, name: '李秀芳', email: 'lixf@gdq.com', role: '仓库管理员', department: '仓储部', status: 'active', lastLogin: '2026-02-20 07:50' },
  { id: 6, name: 'Jack Chen', email: 'jack@gdq.com', role: '海外仓管理员', department: '仓储部', status: 'active', lastLogin: '2026-02-19 22:00' },
  { id: 7, name: '刘总', email: 'liu@gdq.com', role: '管理层', department: '管理层', status: 'active', lastLogin: '2026-02-20 10:00' },
]

export const roles = [
  { name: '超级管理员', permissions: ['全部权限'], userCount: 1 },
  { name: '采购经理', permissions: ['商品管理', '采购审批', '报表查看', '预警管理'], userCount: 1 },
  { name: '仓库管理员', permissions: ['出入库操作', '库存查看', '盘点管理'], userCount: 3 },
  { name: '销售', permissions: ['库存查看', '出库申请', '订单管理'], userCount: 1 },
  { name: '管理层', permissions: ['报表查看', '审批操作', '系统设置'], userCount: 1 },
]
