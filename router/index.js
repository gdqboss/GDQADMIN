import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '../stores/user'
import MainLayout from '../layouts/MainLayout.vue'
import Login from '../views/Login.vue'

// 懒加载重试：chunk 加载失败时自动重试，避免部署后旧缓存 404
function lazyLoad(importFn, retries = 2) {
  return () => importFn().catch((err) => {
    if (retries > 0) {
      return new Promise(resolve => setTimeout(resolve, 1000))
        .then(() => lazyLoad(importFn, retries - 1)())
    }
    throw err
  })
}

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: { layout: 'auth' },
  },
  {
    path: '/scan/:code',
    name: 'ScanPage',
    component: lazyLoad(() => import('../views/scan/ScanPage.vue')),
    meta: { public: true },
  },
  {
    path: '/h5/login',
    name: 'H5Login',
    component: lazyLoad(() => import('../views/scan/H5Login.vue')),
    meta: { public: true },
  },
  {
    path: '/h5/register',
    name: 'H5Register',
    component: lazyLoad(() => import('../views/scan/H5Register.vue')),
    meta: { public: true },
  },
  {
    path: '/h5/forgot-password',
    name: 'H5ForgotPassword',
    component: lazyLoad(() => import('../views/scan/H5ForgotPassword.vue')),
    meta: { public: true },
  },
  {
    path: '/h5/profile',
    name: 'H5Profile',
    component: lazyLoad(() => import('../views/h5/H5Profile.vue')),
    meta: { public: true },
  },
  {
    path: '/',
    component: MainLayout,
    children: [
      { path: '', name: 'Dashboard', component: lazyLoad(() => import('../views/Dashboard.vue')), meta: { title: '工作台', icon: 'dashboard' } },
      { path: 'products', name: 'Products', component: lazyLoad(() => import('../views/products/ProductList.vue')), meta: { title: '商品管理', parent: '库存管理' } },
      { path: 'in-out', name: 'InOut', component: lazyLoad(() => import('../views/inventory/InOutList.vue')), meta: { title: '出入库管理', parent: '库存管理' } },
      { path: 'warehouses', name: 'Warehouses', component: lazyLoad(() => import('../views/warehouse/WarehouseList.vue')), meta: { title: '仓库列表', parent: '仓库管理' } },
      { path: 'warehouses/:id', name: 'WarehouseDetail', component: lazyLoad(() => import('../views/warehouse/WarehouseDetail.vue')), meta: { title: '仓库详情', parent: '仓库管理' } },
      { path: 'alerts', name: 'StockAlerts', component: lazyLoad(() => import('../views/alerts/StockAlerts.vue')), meta: { title: '库存预警', parent: '库存管理' } },
      { path: 'approvals', name: 'Approvals', component: lazyLoad(() => import('../views/approval/ApprovalList.vue')), meta: { title: '审批列表', parent: '审批中心' } },
      { path: 'approvals/:id', name: 'ApprovalDetail', component: lazyLoad(() => import('../views/approval/ApprovalDetail.vue')), meta: { title: '审批详情', parent: '审批中心' } },
      { path: 'retail', name: 'RetailRecords', component: lazyLoad(() => import('../views/retail/RetailRecords.vue')), meta: { title: '零售记录' } },
      { path: 'gift-approvals', name: 'GiftApprovals', component: lazyLoad(() => import('../views/approval/GiftApprovalList.vue')), meta: { title: '赠送审批' } },
      { path: 'aftersale', name: 'AftersaleManage', component: lazyLoad(() => import('../views/aftersale/AftersaleManage.vue')), meta: { title: '售后管理' } },
      { path: 'transfer', name: 'TransferList', component: lazyLoad(() => import('../views/transfer/TransferList.vue')), meta: { title: '调货管理' } },
      { path: 'transfer/create', name: 'TransferCreate', component: lazyLoad(() => import('../views/transfer/TransferCreate.vue')), meta: { title: '创建调货单' } },
      { path: 'transfer/:id', name: 'TransferDetail', component: lazyLoad(() => import('../views/transfer/TransferDetail.vue')), meta: { title: '调货详情' } },
      { path: 'inventory/returns', name: 'ReturnList', component: lazyLoad(() => import('../views/inventory/ReturnList.vue')), meta: { title: '退货记录', parent: '库存管理' } },
      { path: 'h5/my-team', name: 'MyTeam', component: lazyLoad(() => import('../views/h5/MyTeam.vue')), meta: { public: true, title: '我的团队' } },
      { path: 'ai-automation', name: 'AiAutomation', component: lazyLoad(() => import('../views/automation/AiAutomation.vue')), meta: { title: 'AI 自动化', parent: 'OpenClaw' } },
      { path: 'excel-analyzer', name: 'ExcelAnalyzer', component: lazyLoad(() => import('../views/bi/ExcelAnalyzer.vue')), meta: { title: 'Excel 分析器', parent: 'BI' } },
      { path: 'wecom', name: 'WeCom', component: lazyLoad(() => import('../views/wecom/WeComChat.vue')), meta: { title: '企业微信', parent: '消息' } },
      { path: 'oa', name: 'OaCenter', component: lazyLoad(() => import('../views/oa/OaCenter.vue')), meta: { title: 'OA 办公' } },
      { path: 'oa/attendance', name: 'AttendanceManage', component: lazyLoad(() => import('../views/oa/AttendanceManage.vue')), meta: { title: '考勤管理' } },
      { path: 'oa/my-responsibility', name: 'MyResponsibility', component: lazyLoad(() => import('../views/oa/MyResponsibility.vue')), meta: { title: '我的权责' } },
      { path: 'oa/approvals', name: 'OaApprovalManage', component: lazyLoad(() => import('../views/oa/ApprovalManage.vue')), meta: { title: '审批管理' } },
      { path: 'oa/approvals/create', name: 'ApprovalCreate', component: lazyLoad(() => import('../views/oa/ApprovalCreate.vue')), meta: { title: '发起审批' } },
      { path: 'oa/directory', name: 'EmployeeDirectory', component: lazyLoad(() => import('../views/oa/EmployeeDirectory.vue')), meta: { title: '通讯录' } },
      { path: 'oa/shifts', name: 'ShiftManage', component: lazyLoad(() => import('../views/oa/ShiftManage.vue')), meta: { title: '班次管理' } },
      { path: 'oa/schedule', name: 'ScheduleCalendar', component: lazyLoad(() => import('../views/oa/ScheduleCalendar.vue')), meta: { title: '排班日历' } },
      { path: 'oa/attendance-summary', name: 'AttendanceSummary', component: lazyLoad(() => import('../views/oa/AttendanceSummary.vue')), meta: { title: '考勤统计' } },
      { path: 'oa/attendance-rules', name: 'AttendanceRuleManage', component: lazyLoad(() => import('../views/oa/AttendanceRuleManage.vue')), meta: { title: '出勤管理' } },
      { path: 'oa/leave', name: 'LeaveManage', component: lazyLoad(() => import('../views/oa/LeaveManage.vue')), meta: { title: '请假管理' } },
      { path: 'oa/workflow', name: 'WorkflowDesigner', component: lazyLoad(() => import('../views/oa/WorkflowDesigner.vue')), meta: { title: '工作流管理' } },
      { path: 'tasks', name: 'TaskManage', component: lazyLoad(() => import('../views/tasks/TaskManage.vue')), meta: { title: '任务管理' } },
      { path: 'tasks/stats', name: 'TaskStats', component: lazyLoad(() => import('../views/tasks/TaskStats.vue')), meta: { title: '任务统计', roles: ['admin'] } },
      { path: 'logs/work-logs', name: 'LogsWorkLogManage', component: lazyLoad(() => import('../views/logs/WorkLogManage.vue')), meta: { title: '工作日志', parent: '日志系统' } },
      { path: 'logs/visit-logs', name: 'LogsVisitLogManage', component: lazyLoad(() => import('../views/logs/VisitLogManage.vue')), meta: { title: '拜访日志', parent: '日志系统' } },
      { path: 'logs/share-logs', name: 'LogsShareLogManage', component: lazyLoad(() => import('../views/logs/ShareLogManage.vue')), meta: { title: '分享日志', parent: '日志系统' } },
      { path: 'logs/feedback', name: 'LogsFeedbackManage', component: lazyLoad(() => import('../views/logs/FeedbackManage.vue')), meta: { title: '投诉建议', parent: '日志系统' } },
      { path: 'qrcode', name: 'QrcodeManage', component: lazyLoad(() => import('../views/qrcode/QrcodeManage.vue')), meta: { title: '一物一码' } },
      { path: 'reports', name: 'Reports', component: lazyLoad(() => import('../views/reports/ReportCenter.vue')), meta: { title: '报表中心' } },
      { path: 'profile', name: 'UserProfile', component: lazyLoad(() => import('../views/profile/UserProfile.vue')), meta: { title: '个人信息' } },
      { path: 'settings', name: 'Settings', component: lazyLoad(() => import('../views/settings/SystemSettings.vue')), meta: { title: '系统设置', roles: ['admin'] } },
      { path: 'settings/users', name: 'UserManagement', component: lazyLoad(() => import('../views/settings/UserManagement.vue')), meta: { title: '用户管理', roles: ['admin'] } },
      { path: 'settings/h5-users', name: 'H5UserManage', component: lazyLoad(() => import('../views/settings/H5UserManage.vue')), meta: { title: 'H5用户管理', roles: ['admin', 'manager'] } },
      { path: 'settings/job-responsibilities', name: 'JobResponsibilities', component: lazyLoad(() => import('../views/settings/JobResponsibilities.vue')), meta: { title: '职位权责管理', roles: ['admin'] } },
      { path: 'settings/responsibilities', name: 'ResponsibilityManage', component: lazyLoad(() => import('../views/settings/ResponsibilityManage.vue')), meta: { title: '权责管理', roles: ['admin'] } },
      { path: 'finance', name: 'FinanceOverview', component: lazyLoad(() => import('../views/finance-simple/FinanceOverview.vue')), meta: { title: '财务总览', parent: '财务管理' } },
      { path: 'finance/purchase-costs', name: 'PurchaseCosts', component: lazyLoad(() => import('../views/finance-simple/PurchaseCosts.vue')), meta: { title: '采购成本', parent: '财务管理' } },
      { path: 'finance/sales-revenues', name: 'SalesRevenues', component: lazyLoad(() => import('../views/finance-simple/SalesRevenues.vue')), meta: { title: '销售收入', parent: '财务管理' } },
      { path: 'finance/expenses', name: 'ExpenseManage', component: lazyLoad(() => import('../views/finance-simple/ExpenseManage.vue')), meta: { title: '费用支出', parent: '财务管理' } },
      { path: 'finance/accounts-payable', name: 'AccountsPayable', component: lazyLoad(() => import('../views/finance-simple/AccountsPayable.vue')), meta: { title: '应付款管理', parent: '财务管理' } },
      { path: 'finance/accounts-receivable', name: 'AccountsReceivable', component: lazyLoad(() => import('../views/finance-simple/AccountsReceivable.vue')), meta: { title: '应收款管理', parent: '财务管理' } },
      { path: 'finance/profit-analysis', name: 'ProfitAnalysis', component: lazyLoad(() => import('../views/finance-simple/ProfitAnalysis.vue')), meta: { title: '利润分析', parent: '财务管理' } },
      { path: 'finance/fund-accounts', name: 'FundAccounts', component: lazyLoad(() => import('../views/finance-simple/FundAccounts.vue')), meta: { title: '资金账户', parent: '财务管理' } },
      { path: 'finance/cash-flow', name: 'CashFlow', component: lazyLoad(() => import('../views/finance-simple/CashFlow.vue')), meta: { title: '资金流水', parent: '财务管理' } },
      { path: 'finance/supplier-statement', name: 'SupplierStatement', component: lazyLoad(() => import('../views/finance-simple/SupplierStatement.vue')), meta: { title: '供货商对账', parent: '财务管理' } },
      { path: 'finance/customer-statement', name: 'CustomerStatement', component: lazyLoad(() => import('../views/finance-simple/CustomerStatement.vue')), meta: { title: '客户对账', parent: '财务管理' } },
      { path: 'finance/invoices', name: 'InvoiceManage', component: lazyLoad(() => import('../views/finance-simple/InvoiceManage.vue')), meta: { title: '发票管理', parent: '财务管理' } },
      { path: 'finance/invoice-statistics', name: 'InvoiceStatistics', component: lazyLoad(() => import('../views/finance-simple/InvoiceStatistics.vue')), meta: { title: '发票统计', parent: '财务管理' } },
      { path: 'finance/reminders', name: 'FinanceReminders', component: lazyLoad(() => import('../views/finance-simple/FinanceReminders.vue')), meta: { title: '财务提醒', parent: '财务管理' } },
      { path: 'finance/reminder-settings', name: 'ReminderSettings', component: lazyLoad(() => import('../views/finance-simple/ReminderSettings.vue')), meta: { title: '提醒设置', parent: '财务管理' } },
      { path: 'finance/approval-settings', name: 'ApprovalSettings', component: lazyLoad(() => import('../views/finance-simple/ApprovalSettings.vue')), meta: { title: '审批设置', parent: '财务管理', roles: ['admin'] } },
      { path: 'suppliers', name: 'Suppliers', component: lazyLoad(() => import('../views/suppliers/SupplierList.vue')), meta: { title: '供货商管理', parent: '合作伙伴' } },
      { path: 'dealers', name: 'Dealers', component: lazyLoad(() => import('../views/dealers/DealerList.vue')), meta: { title: '经销商管理', parent: '合作伙伴' } },
      { path: 'stores', name: 'Stores', component: lazyLoad(() => import('../views/stores/StoreList.vue')), meta: { title: '门店管理', parent: '合作伙伴' } },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// 路由切换加载状态
let loadingTimeout = null

router.beforeEach((to, from, next) => {
  // 显示加载状态（延迟显示，避免快速切换时闪烁）
  loadingTimeout = setTimeout(() => {
    document.body.style.cursor = 'wait'
  }, 100)

  const userStore = useUserStore()
  if (to.meta.public) {
    next()
  } else if (to.path !== '/login' && !userStore.isLoggedIn) {
    next('/login')
  } else if (to.path === '/login' && userStore.isLoggedIn) {
    next('/')
  } else if (to.meta.roles && !to.meta.roles.includes(userStore.userRole)) {
    next('/')
  } else {
    next()
  }
})

router.afterEach(() => {
  // 清除加载状态
  if (loadingTimeout) {
    clearTimeout(loadingTimeout)
    loadingTimeout = null
  }
  document.body.style.cursor = ''
})

export default router
