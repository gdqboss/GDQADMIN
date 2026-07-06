// 彩美特前端多服务器构建 - 模块配置文件

// 每个 profile (服务器) 对应的 module_key 列表
export const PROFILE_MODULES = {
  1: [ // 新加坡 (id=1) - 全部模块
    'aftersale', 'ai-classroom', 'alerts', 'dashboard', 'dealers',
    'excel-analyzer', 'finance', 'gift-approvals', 'in-out',
    'job-responsibilities', 'oa', 'orders', 'products', 'qrcode',
    'referral', 'reports', 'retail', 'returns', 'roles',
    'server_profiles', 'settings', 'stores', 'suppliers', 'tasks',
    'transfer', 'users', 'warehouses'
  ],
  2: [  // 北京 (id=2) - 相比新加坡少了 server_profiles
    'aftersale', 'ai-classroom', 'alerts', 'dashboard', 'dealers',
    'excel-analyzer', 'finance', 'gift-approvals', 'in-out',
    'job-responsibilities', 'oa', 'orders', 'products', 'qrcode',
    'referral', 'reports', 'retail', 'returns', 'roles',
    'settings', 'stores', 'suppliers', 'tasks',
    'transfer', 'users', 'warehouses'
  ],
  3: [  // 3号仓库 (id=3) - 相比新加坡少了 server_profiles,gift-approvals,qrcode,referral,suppliers
    'aftersale', 'ai-classroom', 'alerts', 'dashboard', 'dealers',
    'excel-analyzer', 'finance', 'in-out',
    'job-responsibilities', 'oa', 'orders', 'products',
    'reports', 'retail', 'returns', 'roles',
    'settings', 'stores', 'tasks',
    'transfer', 'users', 'warehouses'
  ]
}

// module_key → 路由路径数组的映射
// 用于从 router/index.js 中筛选需要保留的路由
export const MODULE_ROUTE_MAP = {
  // Dashboard
  dashboard: ['', 'Dashboard'],

  // aftersale
  aftersale: ['aftersale', 'AftersaleManage'],

  // ai-classroom
  'ai-classroom': ['ai-classroom', 'AiClassroom'],

  // alerts
  alerts: ['alerts', 'StockAlerts'],

  // dealers
  dealers: ['dealers', 'Dealers'],

  // excel-analyzer
  'excel-analyzer': ['excel-analyzer', 'ExcelAnalyzer', 'excel-report-manage', 'ExcelReportManage', 'import-records', 'ImportRecords', 'import-detail/:id', 'ImportDetail'],

  // finance
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
    'finance/payments', 'PaymentManage'
  ],

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
  aftersale: ['aftersale/'],
  'ai-classroom': ['AiClassroom.vue'],
  alerts: ['alerts/'],
  dashboard: ['Dashboard.vue'],
  dealers: ['dealers/'],
  'excel-analyzer': ['bi/'],
  finance: ['finance-simple/'],
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
