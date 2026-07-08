// 彩美特前端多服务器构建 - 模块配置文件
//
// 历史：
//   - 2026-08-12 P1.5 修复：补 12+ 个 router 活跃目录缺声明的模块（避免 profile build 白屏）
//   - 新增模块：admin/approval/articles/automation/collage/finance/h5/hotel/import/kefu/logistics
//                /logs/mall/marketing/member/portal/preorder/qrcode-customer/restaurant/scan/store
//                /temple/wecom/yuyue
//   - 模块名 = views 子目录名（或驼峰）, 跨 profile 默认三 profile 都启用
//   - profile 1 (sgp) 全开；profile 2/3 保留原有限制 (profile 2 减 server_profiles；profile 3 减 5 个)

// 每个 profile (服务器) 对应的 module_key 列表
export const PROFILE_MODULES = {
  1: [ // 新加坡 (id=1) - 全部模块
    'aftersale', 'ai-classroom', 'alerts', 'articles', 'automation',
    'chat',
    'collage',
    'dashboard', 'dealers', 'excel-analyzer',
    'finance',
    'gift-approvals',
    'h5', 'hotel',
    'in-out',
    'job-responsibilities',
    'kefu', 'logistics', 'logs',
    'mall', 'marketing', 'member',
    'oa', 'orders',
    'portal', 'preorder', 'products',
    'qrcode', 'qrcode-customer',
    'referral', 'reports', 'restaurant', 'retail', 'returns', 'roles',
    'scan', 'server_profiles', 'settings', 'store', 'stores', 'suppliers',
    'tasks', 'temple', 'transfer',
    'users', 'warehouses', 'wecom', 'yuyue',
    'admin', 'approval', 'import'
  ],
  2: [  // 北京 (id=2) - 相比新加坡少了 server_profiles
    'aftersale', 'ai-classroom', 'alerts', 'articles', 'automation',
    'chat',
    'collage',
    'dashboard', 'dealers', 'excel-analyzer',
    'finance',
    'gift-approvals',
    'h5', 'hotel',
    'in-out',
    'job-responsibilities',
    'kefu', 'logistics', 'logs',
    'mall', 'marketing', 'member',
    'oa', 'orders',
    'portal', 'preorder', 'products',
    'qrcode', 'qrcode-customer',
    'referral', 'reports', 'restaurant', 'retail', 'returns', 'roles',
    'scan', 'settings', 'store', 'stores', 'suppliers',
    'tasks', 'temple', 'transfer',
    'users', 'warehouses', 'wecom', 'yuyue',
    'admin', 'approval', 'import'
  ],
  3: [  // 3号仓库 (id=3) - 相比新加坡少了 server_profiles,gift-approvals,qrcode,referral,suppliers
    'aftersale', 'ai-classroom', 'alerts', 'articles', 'automation',
    'chat',
    'collage',
    'dashboard', 'dealers', 'excel-analyzer',
    'finance',
    'h5', 'hotel',
    'in-out',
    'job-responsibilities',
    'kefu', 'logistics', 'logs',
    'mall', 'marketing', 'member',
    'oa', 'orders',
    'portal', 'preorder', 'products',
    'qrcode-customer',
    'reports', 'restaurant', 'retail', 'returns', 'roles',
    'scan', 'settings', 'store', 'stores',
    'tasks', 'temple', 'transfer',
    'users', 'warehouses', 'wecom', 'yuyue',
    'admin', 'approval', 'import'
  ]
}

// module_key → 路由路径数组的映射
// 用于从 router/index.js 中筛选需要保留的路由
export const MODULE_ROUTE_MAP = {
  // Dashboard
  dashboard: ['', 'Dashboard'],

  // admin (新的 portal admin 页面)
  admin: ['portal/manage', 'PortalManage'],
  // approval (审批列表/详情，OA 模块的子路由)
  approval: ['approvals', 'Approvals', 'approvals/:id', 'ApprovalDetail'],
  // articles
  articles: ['articles', 'ArticleList', 'articles/new', 'ArticleNew', 'articles/:id', 'ArticleDetail'],
  // automation (AI 自动化)
  automation: ['ai-automation', 'AiAutomation'],
  // chat (web chat / H5 在线客服 — 当前未在 router 中显式引用)
  chat: [],
  // collage (拼团)
  collage: ['collage', 'CollageDashboard', 'collage/orders', 'CollageOrderList', 'collage/products', 'CollageProductList'],
  // finance (扩充：含 finance-simple 外的 finance/)
  finance: [
    'finance', 'FinanceOverview',
    'finance/purchase-costs', 'PurchaseCosts',
    'finance/sales-revenues', 'SalesRevenues',
    'finance/expenses', 'ExpenseManage',
    'finance/accounts-payable', 'AccountsPayable',
    'finance/accounts-receivable', 'AccountsReceivable',
    'finance/profit-analysis', 'ProfitAnalysis',
    'finance/fund-accounts', 'FundAccounts',
    'finance/cash-flow', 'CashFlow',
    'finance/supplier-statement', 'SupplierStatement',
    'finance/customer-statement', 'CustomerStatement',
    'finance/invoices', 'InvoiceManage',
    'finance/invoice-statistics', 'InvoiceStatistics',
    'finance/reminders', 'FinanceReminders',
    'finance/reminder-settings', 'ReminderSettings',
    'finance/approval-settings', 'ApprovalSettings',
    'finance/receipts', 'ReceiptManage',
    'finance/payments', 'PaymentManage',
    'finance/wallet', 'WalletManage'
  ],
  // h5 (H5 移动端 — 后台 mobile)
  h5: ['h5', 'H5Home', 'h5/categories', 'H5Categories', 'h5/cart', 'H5Cart', 'h5/profile', 'H5Profile', 'h5/product/:id', 'H5ProductDetail', 'h5/checkout', 'H5Checkout', 'h5/order-pay', 'H5OrderPay', 'h5/orders', 'H5Orders', 'h5/order/:id', 'H5OrderDetail', 'h5/seckill', 'H5Seckill', 'h5/collage/:id', 'H5Collage', 'h5/address', 'H5AddressList', 'h5/address/edit', 'H5AddressEdit', 'h5/bind-phone', 'H5BindPhone', 'h5/password', 'H5Password', 'h5/coupons', 'H5Coupons', 'h5/score-detail', 'H5ScoreDetail', 'h5/score-history', 'H5ScoreHistory', 'h5/settings', 'H5Settings', 'h5/wallet', 'H5Wallet', 'h5/profile-edit', 'H5ProfileEdit'],
  // hotel
  hotel: ['hotel', 'HotelDashboard', 'hotel/room-types', 'RoomTypeList', 'hotel/price-calendar', 'PriceCalendar', 'hotel/orders', 'HotelOrderList', 'hotel/orders/:id', 'HotelOrderDetail', 'hotel/reviews', 'ReviewList'],
  // import (import 目录归 excel-analyzer 同源)
  import: ['import-records', 'ImportRecords', 'import-detail/:id', 'ImportDetail', 'import-detail-multi/:ids', 'ImportDetailMulti', 'store-sales', 'StoreSalesReport'],
  // kefu (客服消息)
  kefu: ['kefu', 'Kefu'],
  // logistics
  logistics: ['logistics', 'LogisticsDashboard', 'logistics/express', 'ExpressList', 'logistics/templates', 'ShippingTemplate', 'logistics/channels', 'ChannelLogistics'],
  // logs (日志系统)
  logs: ['logs/work-logs', 'LogsWorkLogManage', 'logs/visit-logs', 'LogsVisitLogManage', 'logs/share-logs', 'LogsShareLogManage', 'logs/feedback', 'LogsFeedbackManage'],
  // mall (前台商城路由)
  mall: ['mall', 'MallHome', 'mall/category/:id', 'MallCategory', 'mall/product/:id', 'MallProductDetail', 'mall/cart', 'MallCart', 'mall/checkout', 'Checkout', 'mall/orders', 'MallOrderList', 'mall/order/:id', 'MallOrderDetail', 'mall/login', 'MallLogin', 'mall/register', 'MallRegister', 'mall/coupons', 'CouponManage', 'mall/score-orders', 'ScoreOrderManage', 'mall/score-products', 'ScoreProductManage'],
  // marketing
  marketing: ['invite-manage', 'InviteManage', 'seckill-manage', 'SeckillManage'],
  // member
  member: ['member-level-manage', 'MemberLevelManage'],
  // portal (内容门户前台)
  portal: ['portal', 'PortalHome', 'portal/article/:id', 'ArticleDetail', 'portal/articles', 'ArticleList'],
  // preorder
  preorder: ['preorder/summary', 'PreorderSummary', 'preorder/scan', 'CartonScan', 'preorder/carton-print/:drf_id', 'CartonPrint'],
  // qrcode-customer (前台扫码页)
  'qrcode-customer': ['qrcode-customer', 'QrcodeCustomer'],
  // restaurant (餐厅管理)
  restaurant: ['restaurant', 'RestaurantManage', 'restaurant/tables', 'restaurant/dishes', 'restaurant/dine-orders', 'restaurant/takeout', 'restaurant/reservations', 'restaurant/queue', 'restaurant/cashier'],
  // scan (前台扫码页 ScanPage)
  scan: [],
  // store (StoreTransfer — 门店调拨)
  store: ['store-transfer', 'StoreTransfer', 'store-transfer/list', 'StoreTransferList', 'store-transfer/detail', 'StoreTransferDetail'],
  // temple (寺庙预约)
  temple: ['temple', 'TempleCreate', 'temple/caskets', 'temple/ancestors', 'temple/orders', 'temple/donations', 'temple/monks', 'temple/detail', 'TempleDetail', 'temple/list', 'TempleList', 'temple/stats', 'TempleStats'],
  // wecom (企业微信对接)
  wecom: ['wecom', 'WeComChat'],
  // yuyue (预约)
  yuyue: ['yuyue', 'YuyueCreate', 'yuyue/:id', 'YuyueDetail', 'yuyue/list', 'YuyueList', 'yuyue/stats', 'YuyueStats'],

  // ---- 原有模块 (位置不变) ----

  // aftersale
  aftersale: ['aftersale', 'AftersaleManage'],

  // ai-classroom
  'ai-classroom': ['ai-classroom', 'AiClassroom'],

  // alerts
  alerts: ['alerts', 'StockAlerts'],

  // dealers
  dealers: ['dealers', 'Dealers'],

  // excel-analyzer
  'excel-analyzer': ['excel-analyzer', 'ExcelAnalyzer', 'excel-report-manage', 'ExcelReportManage'],

  // gift-approvals (view is GiftApprovalList.vue, not GiftApprovals)
  'gift-approvals': ['gift-approvals', 'GiftApprovalList'],

  // in-out (库存出入库)
  'in-out': ['in-out', 'InOut', 'inventory/returns', 'ReturnList'],

  // job-responsibilities
  'job-responsibilities': ['settings/job-responsibilities', 'JobResponsibilities', 'settings/responsibilities', 'ResponsibilityManage'],

  // oa
  oa: [
    'oa', 'OaCenter',
    'oa/attendance', 'AttendanceManage',
    'oa/my-responsibility', 'MyResponsibility',
    'oa/approvals', 'OaApprovalManage',
    'oa/approvals/create', 'ApprovalCreate',
    'oa/directory', 'EmployeeDirectory',
    'oa/shifts', 'ShiftManage',
    'oa/schedule', 'ScheduleCalendar',
    'oa/attendance-summary', 'AttendanceSummary',
    'oa/attendance-rules', 'AttendanceRuleManage',
    'oa/leave', 'LeaveManage',
    'oa/workflow', 'WorkflowDesigner'
  ],

  // orders
  orders: ['orders', 'OrderList', 'orders/:id', 'OrderDetail'],

  // products
  products: ['products', 'Products'],

  // qrcode
  qrcode: ['qrcode', 'QrcodeManage'],

  // referral
  referral: ['referral', 'ReferralManage'],

  // reports
  reports: ['reports', 'Reports'],

  // retail
  retail: ['retail', 'RetailRecords'],

  // returns (退货)
  returns: ['inventory/returns', 'ReturnList'],

  // roles
  roles: ['settings/roles', 'RoleManage'],

  // server_profiles
  server_profiles: ['settings/server-profiles', 'ServerProfiles'],

  // settings
  settings: ['settings', 'Settings'],

  // stores
  stores: ['stores', 'Stores'],

  // suppliers (view is SupplierList.vue, not Suppliers)
  suppliers: ['suppliers', 'SupplierList'],

  // tasks
  tasks: ['tasks', 'TaskManage', 'tasks/stats', 'TaskStats'],

  // transfer
  transfer: ['transfer', 'TransferList', 'transfer/create', 'TransferCreate', 'transfer/:id', 'TransferDetail'],

  // users
  users: ['settings/users', 'UserManagement', 'settings/h5-users', 'H5UserManage', 'profile', 'UserProfile'],

  // warehouses
  warehouses: ['warehouses', 'Warehouses', 'warehouses/:id', 'WarehouseDetail']
}

// module_key → 需要保留的 views 目录下文件/夹的映射
// key 是 module_key，value 是相对于 views/目录的路径数组
export const MODULE_FILE_MAP = {
  // ---- 新增模块（认领 router 活跃目录）----
  admin: ['admin/'],
  approval: ['approval/ApprovalList.vue', 'approval/ApprovalDetail.vue'],
  articles: ['articles/'],
  automation: ['automation/'],
  chat: ['chat/'],
  collage: ['collage/'],
  finance: ['finance-simple/', 'finance/WalletManage.vue'],
  h5: ['h5/', 'scan/H5ForgotPassword.vue', 'scan/H5Login.vue', 'scan/H5Register.vue'],
  hotel: ['hotel/'],
  import: ['import/'],
  kefu: ['kefu/'],
  logistics: ['logistics/'],
  logs: ['logs/'],
  mall: ['mall/'],
  marketing: ['marketing/'],
  member: ['member/'],
  portal: ['portal/'],
  preorder: ['preorder/'],
  'qrcode-customer': ['qrcode-customer/'],
  restaurant: ['restaurant/'],
  scan: ['scan/ScanPage.vue'],
  store: ['store/'],
  temple: ['temple/'],
  wecom: ['wecom/WeComChat.vue'],
  yuyue: ['yuyue/'],

  // ---- 原有模块 ----
  aftersale: ['aftersale/'],
  'ai-classroom': ['AiClassroom.vue'],
  alerts: ['alerts/'],
  dashboard: ['Dashboard.vue', 'Login.vue'],  // Login.vue 登录页是 core，永远保留
  dealers: ['dealers/'],
  'excel-analyzer': ['bi/'],
  'gift-approvals': ['approval/GiftApprovalList.vue'],
  'in-out': ['inventory/'],
  'job-responsibilities': ['settings/JobResponsibilities.vue', 'settings/ResponsibilityManage.vue'],
  oa: ['oa/'],
  orders: ['orders/'],
  products: ['products/'],
  qrcode: ['qrcode/'],
  referral: ['orders/ReferralManage.vue'],
  reports: ['reports/'],
  retail: ['retail/'],
  returns: ['inventory/ReturnList.vue'],
  roles: ['settings/RoleManage.vue'],
  server_profiles: ['settings/ServerProfiles.vue'],
  settings: ['settings/SystemSettings.vue'],
  stores: ['stores/'],
  suppliers: ['suppliers/'],
  tasks: ['tasks/'],
  transfer: ['transfer/'],
  users: ['settings/UserManagement.vue', 'settings/H5UserManage.vue', 'profile/'],
  warehouses: ['warehouse/']
}
