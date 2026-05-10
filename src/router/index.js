import { createRouter, createWebHistory } from 'vue-router'

// 路由懒加载辅助函数
const loadModule = (module, component) => () => import(`../modules/${module}/${component}.vue`)

const routes = [
  { path: '/', redirect: '/products' },
  { path: '/login', name: 'Login', component: () => import('../views/login/Login.vue') },

  // ===== 核心模块 =====
  {
    path: '/products',
    children: [
      { path: '', name: 'ProductList', component: () => import('../modules/products/components/ProductList.vue') },
      { path: 'new', name: 'ProductNew', component: () => import('../modules/products/components/ProductNew.vue') },
      { path: ':id/edit', name: 'ProductEdit', component: () => import('../modules/products/components/ProductEdit.vue') },
      { path: ':id/skus', name: 'ProductSkus', component: () => import('../modules/products/components/ProductSkus.vue') },
    ]
  },

  // ===== 合作伙伴 =====
  { path: '/dealers', name: 'DealerList', component: () => import('../modules/dealers/index.vue') },
  { path: '/stores', name: 'StoreList', component: () => import('../modules/stores/index.vue') },
  { path: '/suppliers', name: 'SupplierList', component: () => import('../modules/suppliers/index.vue') },
  { path: '/employees', name: 'EmployeeDirectory', component: () => import('../modules/employees/index.vue') },

  // ===== 销售管理 =====
  { path: '/sales/revenues', name: 'SalesRevenues', component: () => import('../modules/sales/revenues/index.vue') },
  { path: '/sales/retail', name: 'RetailRecords', component: () => import('../modules/sales/retail/index.vue') },
  { path: '/sales/returns', name: 'ReturnList', component: () => import('../modules/sales/returns/index.vue') },

  // ===== 库存管理 =====
  { path: '/stock/alerts', name: 'StockAlerts', component: () => import('../modules/stock/alerts/index.vue') },
  { path: '/stock/inout', name: 'InOutList', component: () => import('../modules/stock/inout/index.vue') },
  { path: '/stock/costs', name: 'PurchaseCosts', component: () => import('../modules/stock/costs/index.vue') },

  // ===== 财务报表 =====
  { path: '/finance/dashboard', name: 'FinanceDashboard', component: () => import('../modules/finance/dashboard/index.vue') },
  { path: '/finance/receivable', name: 'AccountsReceivable', component: () => import('../modules/finance/receivable/index.vue') },
  { path: '/finance/payable', name: 'AccountsPayable', component: () => import('../modules/finance/payable/index.vue') },
  { path: '/finance/cashflow', name: 'CashFlow', component: () => import('../modules/finance/cashflow/index.vue') },
  { path: '/finance/profit', name: 'ProfitAnalysis', component: () => import('../modules/finance/profit/index.vue') },
  { path: '/finance/invoice', name: 'InvoiceManage', component: () => import('../modules/finance/invoice/index.vue') },

  // ===== 人事考勤 =====
  { path: '/attendance/manage', name: 'AttendanceManage', component: () => import('../modules/attendance/manage/index.vue') },
  { path: '/attendance/summary', name: 'AttendanceSummary', component: () => import('../modules/attendance/summary/index.vue') },
  { path: '/attendance/rule', name: 'AttendanceRule', component: () => import('../modules/attendance/rule/index.vue') },
  { path: '/schedule', name: 'ScheduleCalendar', component: () => import('../modules/schedule/index.vue') },
  { path: '/shift', name: 'ShiftManage', component: () => import('../modules/shift/index.vue') },
  { path: '/leave', name: 'LeaveManage', component: () => import('../modules/leave/index.vue') },

  // ===== 审批流 =====
  { path: '/approval/list', name: 'ApprovalList', component: () => import('../modules/approval/list/index.vue') },
  { path: '/approval/create', name: 'ApprovalCreate', component: () => import('../modules/approval/create/index.vue') },
  { path: '/approval/detail/:id', name: 'ApprovalDetail', component: () => import('../modules/approval/detail/index.vue') },
  { path: '/approval/manage', name: 'ApprovalManage', component: () => import('../modules/approval/manage/index.vue') },
  { path: '/approval/settings', name: 'ApprovalSettings', component: () => import('../modules/approval/settings/index.vue') },

  // ===== 报表中心 =====
  { path: '/report/center', name: 'ReportCenter', component: () => import('../modules/report/center/index.vue') },
  { path: '/report/excel', name: 'ExcelReportManage', component: () => import('../modules/report/excel/index.vue') },
  { path: '/report/bi', name: 'BiDashboard', component: () => import('../modules/report/bi/index.vue') },

  // ===== 客户关系 =====
  { path: '/customer/statement', name: 'CustomerStatement', component: () => import('../modules/customer/statement/index.vue') },
  { path: '/customer/feedback', name: 'FeedbackManage', component: () => import('../modules/customer/feedback/index.vue') },

  // ===== 售后 =====
  { path: '/aftersale', name: 'AftersaleManage', component: () => import('../modules/aftersale/index.vue') },

  // ===== 系统工具 =====
  { path: '/system', name: 'SystemSettings', component: () => import('../modules/system/index.vue') },
  { path: '/qrcode', name: 'QrcodeManage', component: () => import('../modules/qrcode/ScanPage.vue') },
  { path: '/scan/:code', name: 'ScanPage', component: () => import('../modules/qrcode/ScanPage.vue') },
  { path: '/chat', name: 'CustomerChat', component: () => import('../modules/customer/chat/ChatRoom.vue') },
  { path: '/image', name: 'ImageLibrary', component: () => import('../modules/image/index.vue') },

  // ===== AI功能 =====
  { path: '/ai/automation', name: 'AiAutomation', component: () => import('../modules/ai/automation/index.vue') },
  { path: '/ai/openclaw', name: 'OpenClawAssistant', component: () => import('../modules/ai/openclaw/index.vue') },

  // ===== 工具页面（HTML内化）=====
  { path: '/activate', name: 'Activate', component: () => import('../modules/tools/activate/index.vue') },
  { path: '/boss-chat', name: 'BossChat', component: () => import('../modules/tools/bosschat/index.vue') },
  { path: '/card', name: 'Card', component: () => import('../modules/tools/card/index.vue') },
  { path: '/company', name: 'Company', component: () => import('../modules/tools/company/index.vue') },
  { path: '/cmtintro', name: 'CmtIntro', component: () => import('../modules/tools/cmtintro/index.vue') },
  { path: '/aver', name: 'Aver', component: () => import('../modules/tools/aver/index.vue') },
  { path: '/excel-analyzer', name: 'ExcelAnalyzer', component: () => import('../modules/tools/excelanalyzer/index.vue') },
  { path: '/excel-merge', name: 'ExcelMerge', component: () => import('../modules/tools/excelmerge/index.vue') },

  // ===== 兜底 =====
  { path: '/:pathMatch(.*)*', redirect: '/products' }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

export default router
