import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/login/Login.vue'),
    meta: { public: true }
  },
  {
    path: '/',
    component: () => import('../components/layout/MainLayout.vue'),
    children: [
      { path: '', redirect: '/products' },
      { path: 'products', name: 'Products', component: () => import('../modules/products/components/ProductList.vue') },
      { path: 'products/new', name: 'ProductNew', component: () => import('../modules/products/components/ProductNew.vue') },
      { path: 'products/:id/edit', name: 'ProductEdit', component: () => import('../modules/products/components/ProductEdit.vue') },
      { path: 'products/:id/skus', name: 'ProductSkus', component: () => import('../modules/products/components/ProductSkus.vue') },
      { path: 'dealers', name: 'Dealers', component: () => import('../modules/dealers/index.vue') },
      { path: 'stores', name: 'Stores', component: () => import('../modules/stores/index.vue') },
      { path: 'suppliers', name: 'Suppliers', component: () => import('../modules/suppliers/index.vue') },
      { path: 'employees', name: 'Employees', component: () => import('../modules/employees/index.vue') },
      { path: 'sales/revenues', name: 'SalesRevenues', component: () => import('../modules/sales/revenues/index.vue') },
      { path: 'sales/retail', name: 'RetailRecords', component: () => import('../modules/sales/retail/index.vue') },
      { path: 'sales/returns', name: 'ReturnList', component: () => import('../modules/sales/returns/index.vue') },
      { path: 'stock/alerts', name: 'StockAlerts', component: () => import('../modules/stock/alerts/index.vue') },
      { path: 'stock/inout', name: 'InOutList', component: () => import('../modules/stock/inout/index.vue') },
      { path: 'stock/costs', name: 'PurchaseCosts', component: () => import('../modules/stock/costs/index.vue') },
      { path: 'finance/dashboard', name: 'FinanceDashboard', component: () => import('../modules/finance/dashboard/index.vue') },
      { path: 'finance/receivable', name: 'AccountsReceivable', component: () => import('../modules/finance/receivable/index.vue') },
      { path: 'finance/payable', name: 'AccountsPayable', component: () => import('../modules/finance/payable/index.vue') },
      { path: 'finance/cashflow', name: 'CashFlow', component: () => import('../modules/finance/cashflow/index.vue') },
      { path: 'finance/profit', name: 'ProfitAnalysis', component: () => import('../modules/finance/profit/index.vue') },
      { path: 'finance/invoice', name: 'InvoiceManage', component: () => import('../modules/finance/invoice/index.vue') },
      { path: 'attendance/manage', name: 'AttendanceManage', component: () => import('../modules/attendance/manage/index.vue') },
      { path: 'attendance/summary', name: 'AttendanceSummary', component: () => import('../modules/attendance/summary/index.vue') },
      { path: 'attendance/rule', name: 'AttendanceRule', component: () => import('../modules/attendance/rule/index.vue') },
      { path: 'schedule', name: 'ScheduleCalendar', component: () => import('../modules/schedule/index.vue') },
      { path: 'shift', name: 'ShiftManage', component: () => import('../modules/shift/index.vue') },
      { path: 'leave', name: 'LeaveManage', component: () => import('../modules/leave/index.vue') },
      { path: 'approval/list', name: 'ApprovalList', component: () => import('../modules/approval/list/index.vue') },
      { path: 'approval/create', name: 'ApprovalCreate', component: () => import('../modules/approval/create/index.vue') },
      { path: 'approval/detail/:id', name: 'ApprovalDetail', component: () => import('../modules/approval/detail/index.vue') },
      { path: 'approval/manage', name: 'ApprovalManage', component: () => import('../modules/approval/manage/index.vue') },
      { path: 'approval/settings', name: 'ApprovalSettings', component: () => import('../modules/approval/settings/index.vue') },
      { path: 'report/center', name: 'ReportCenter', component: () => import('../modules/report/center/index.vue') },
      { path: 'report/excel', name: 'ExcelReportManage', component: () => import('../modules/report/excel/index.vue') },
      { path: 'report/bi', name: 'BiDashboard', component: () => import('../modules/report/bi/index.vue') },
      { path: 'customer/statement', name: 'CustomerStatement', component: () => import('../modules/customer/statement/index.vue') },
      { path: 'customer/feedback', name: 'FeedbackManage', component: () => import('../modules/customer/feedback/index.vue') },
      { path: 'aftersale', name: 'AftersaleManage', component: () => import('../modules/aftersale/index.vue') },
      { path: 'system', name: 'SystemSettings', component: () => import('../modules/system/index.vue') },
      { path: 'qrcode', name: 'QrcodeManage', component: () => import('../modules/qrcode/ScanPage.vue') },
      { path: 'scan/:code', name: 'ScanPage', component: () => import('../modules/qrcode/ScanPage.vue') },
      { path: 'chat', name: 'CustomerChat', component: () => import('../modules/customer/chat/ChatRoom.vue') },
      { path: 'image', name: 'ImageLibrary', component: () => import('../modules/image/index.vue') },
      { path: 'ai/automation', name: 'AiAutomation', component: () => import('../modules/ai/automation/index.vue') },
      { path: 'ai/openclaw', name: 'OpenClawAssistant', component: () => import('../modules/ai/openclaw/index.vue') },
      { path: 'activate', name: 'Activate', component: () => import('../modules/tools/activate/index.vue') },
      { path: 'boss-chat', name: 'BossChat', component: () => import('../modules/tools/bosschat/index.vue') },
      { path: 'card', name: 'Card', component: () => import('../modules/tools/card/index.vue') },
      { path: 'company', name: 'Company', component: () => import('../modules/tools/company/index.vue') },
      { path: 'cmtintro', name: 'CmtIntro', component: () => import('../modules/tools/cmtintro/index.vue') },
      { path: 'aver', name: 'Aver', component: () => import('../modules/tools/aver/index.vue') },
      { path: 'excel-analyzer', name: 'ExcelAnalyzer', component: () => import('../modules/tools/excelanalyzer/index.vue') },
      { path: 'excel-merge', name: 'ExcelMerge', component: () => import('../modules/tools/excelmerge/index.vue') },
    ]
  },
  { path: '/:pathMatch(.*)*', redirect: '/products' }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// 路由守卫
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('caimeite_token')
  if (to.meta.public) {
    next()
  } else if (!token) {
    next('/login')
  } else {
    next()
  }
})

export default router
