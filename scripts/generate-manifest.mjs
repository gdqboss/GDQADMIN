/**
 * 构建后生成模块manifest
 * 输出: dist/module-manifest.json
 * 格式: { "模块key": ["chunk文件名", ...], "shared": ["共享chunk"] }
 */
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const distDir = path.join(__dirname, '..', 'dist')
const manifestPath = path.join(distDir, 'module-manifest.json')

// 页面chunk → 模块key 的映射（根据路由懒加载路径）
const pageToModule = {
  // 核心共享（每个页面都会加载）
  'Dashboard':        'dashboard',
  'MainLayout':       '_layout',
  'Login':            'auth',
  'PageHeader':       '_shared',
  'Pagination':        '_shared',
  'StatCard':         '_shared',

  // 库存管理
  'ProductList':      'products',
  'InOutList':        'in-out',
  'WarehouseList':    'warehouses',
  'WarehouseDetail':  'warehouses',
  'StockAlerts':      'alerts',
  'TransferList':     'transfer',
  'TransferDetail':   'transfer',
  'TransferCreate':   'transfer',

  // 订单与零售
  'OrderList':        'orders',
  'OrderDetail':      'orders',
  'RetailRecords':    'retail',
  'ReturnList':       'returns',
  'ReferralManage':   'referral',

  // 售后
  'AftersaleManage':  'aftersale',

  // 审批
  'ApprovalList':     'oa',
  'ApprovalDetail':   'oa',
  'ApprovalCreate':   'oa',
  'ApprovalSettings': 'oa',
  'ApprovalManage':   'oa',
  'GiftApprovalList': 'gift-approvals',
  'ReviewList':       'oa',

  // 财务
  'FinanceOverview':  'finance',
  'AccountsReceivable':'finance',
  'AccountsPayable':  'finance',
  'FundAccounts':     'finance',
  'CashFlow':         'finance',
  'PaymentManage':    'finance',
  'ReceiptManage':    'finance',
  'ExpenseManage':    'finance',
  'PurchaseCosts':    'finance',
  'InvoiceManage':    'finance',
  'InvoiceStatistics': 'finance',
  'ProfitAnalysis':   'finance',
  'FinanceReminders': 'finance',
  'ReminderSettings': 'finance',
  'StoreSalesReport': 'reports',

  // 客服
  'KefuChat':         'kefu',

  // 餐饮
  'RestaurantDashboard': 'restaurant',
  'TableManage':      'restaurant',
  'DishManage':       'restaurant',
  'DineOrderList':    'restaurant',
  'TakeoutOrderList': 'restaurant',
  'ReservationManage':'restaurant',
  'QueueManage':      'restaurant',

  // 酒店
  'HotelDashboard':   'hotel',
  'RoomTypeList':     'hotel',
  'HotelOrderList':   'hotel',
  'HotelOrderDetail': 'hotel',

  // 商城H5
  'StoreLayout':      'mall',
  'MallHome':         'mall',
  'MallCategory':     'mall',
  'ProductDetail':    'mall',
  'MallCart':         'mall',
  'Checkout':         'mall',
  'MallLogin':        'mall',
  'MallRegister':     'mall',
  'MallOrderList':    'mall',

  // 积分商城
  'ScoreShopHome':    'score_shop',
  'ScoreProductDetail':'score_shop',
  'ScoreProductManage':'score_shop',
  'ScoreOrderList':   'score_shop',
  'ScoreOrderDetail': 'score_shop',
  'ScoreOrderManage': 'score_shop',
  'CouponList':       'coupon',
  'CouponManage':     'coupon',

  // 团购
  'CollageDashboard': 'collage',
  'CollageProductList':'collage',
  'CollageOrderList': 'collage',

  // 外卖
  'TakeawayDashboard':'takeaway',
  'ExpressList':      'logistics',
  'ChannelLogistics': 'logistics',

  // 预约
  'YuyueList':        'yuyue',
  'YuyueDetail':      'yuyue',

  // 员工管理
  'EmployeeDirectory':'users',
  'UserManagement':   'users',
  'UserProfile':       'users',
  'H5UserManage':     'users',
  'AttendanceManage': 'attendance',
  'LeaveManage':      'attendance',
  'ShiftManage':      'attendance',
  'AttendanceSummary': 'attendance',
  'AttendanceRuleManage':'attendance',
  'ScheduleCalendar': 'attendance',

  // 角色权限
  'RoleManage':        'roles',
  'ResponsibilityManage':'job-responsibilities',
  'JobResponsibilities': 'job-responsibilities',
  'MyResponsibility':  'job-responsibilities',

  // 任务
  'TaskManage':        'tasks',
  'TaskStats':        'tasks',

  // 供货商
  'SupplierList':     'suppliers',
  'SupplierStatement': 'suppliers',
  'CustomerStatement': 'dealers',
  'DealerList':       'dealers',

  // 门店
  'StoreList':        'stores',

  // 二维码
  'QrcodeManage':     'qrcode',

  // 工作日志
  'WorkLogManage':    'logs',
  'VisitLogManage':   'logs',
  'ShareLogManage':   'logs',
  'FeedbackManage':   'logs',

  // AI课堂
  'AiClassroom':      'ai-classroom',
  'AiAutomation':     'ai-classroom',

  // Excel报告
  'ExcelAnalyzer':    'excel-analyzer',
  'ImportRecords':    'excel-analyzer',
  'ImportDetail':     'excel-analyzer',
  'ImportDetailMulti':'excel-analyzer',
  'ExcelReportManage':'excel-analyzer',

  // 报表中心
  'ReportCenter':     'reports',

  // 系统设置
  'SystemSettings':   'settings',
  'ServerProfiles':   'server_profiles',

  // 文章
  'ArticleList':      'article',
  'ArticleDetail':    'article',
}

// 共享chunk（每次构建都变，不能按需）
const sharedChunks = [
  'index',
  'i18n',
  'xlsx',
]

// 读取dist/assets下的所有js文件
const assetsDir = path.join(distDir, 'assets')
const files = fs.readdirSync(assetsDir).filter(f => f.endsWith('.js'))

const manifest = {
  generated: new Date().toISOString(),
  build: fs.existsSync(path.join(distDir, 'index.html'))
    ? fs.readFileSync(path.join(distDir, 'index.html'), 'utf8').match(/index-([a-zA-Z0-9_-]+)\.js/)?.[1] || 'unknown'
    : 'unknown',
  modules: {},
  shared: [],
}

for (const file of files) {
  const baseName = file.replace(/\.js$/, '').replace(/-[a-zA-Z0-9_-]+$/, '') // strip hash
  const moduleKey = pageToModule[baseName] || null

  if (moduleKey) {
    if (!manifest.modules[moduleKey]) manifest.modules[moduleKey] = []
    manifest.modules[moduleKey].push(file)
  }

  // 共享chunk（index, i18n, xlsx, vendor）
  if (sharedChunks.some(s => file.startsWith(s))) {
    manifest.shared.push(file)
  }
}

fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2))
console.log(`✅ Manifest generated: ${manifestPath}`)
console.log(`   Modules: ${Object.keys(manifest.modules).length}`)
console.log(`   Shared: ${manifest.shared.length} files`)

// 列出各模块的chunk
for (const [mod, chunks] of Object.entries(manifest.modules).sort()) {
  console.log(`  ${mod}: ${chunks.length} chunk(s)`)
}
