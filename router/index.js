import { createRouter, createWebHashHistory } from 'vue-router'
import { useUserStore } from '@/stores/user'

const MainLayout = () => import('../layouts/MainLayout.vue')
const lazyLoad = (loader) => () => loader()

// ============ 横琴湾区 H5 模块 ============
const hqh5Routes = [
  { path: '/hqh5/demo', name: 'Hqh5Demo', component: () => import('../views/hqh5/Demo.vue'), meta: { public: true, h5: true } },
  { path: '/hqh5/guest-home', name: 'Hqh5GuestHome', component: () => import('../views/hqh5/GuestHome.vue'), meta: { public: true, h5: true } },
  { path: '/hqh5/login', name: 'Hqh5Login', component: () => import('../views/hqh5/Login.vue'), meta: { public: true, h5: true } },
  { path: '/hqh5/enterprise-home', name: 'Hqh5EnterpriseHome', component: () => import('../views/hqh5/EnterpriseHome.vue'), meta: { public: true, h5: true } },
  { path: '/hqh5/attendance-manage', name: 'Hqh5Attendance', component: () => import('../views/hqh5/AttendanceManage.vue'), meta: { public: true, h5: true } },
  { path: '/hqh5/approval-list', name: 'Hqh5Approval', component: () => import('../views/hqh5/ApprovalList.vue'), meta: { public: true, h5: true } },
  { path: '/hqh5/venue-booking', name: 'Hqh5Venue', component: () => import('../views/hqh5/VenueBooking.vue'), meta: { public: true, h5: true } },
  { path: '/hqh5/butler-booking', name: 'Hqh5Butler', component: () => import('../views/hqh5/ButlerBooking.vue'), meta: { public: true, h5: true } },
  { path: '/hqh5/enterprise-center', name: 'Hqh5Center', component: () => import('../views/hqh5/EnterpriseCenter.vue'), meta: { public: true, h5: true } },
  { path: '/hqh5/article-publish', name: 'Hqh5Article', component: () => import('../views/hqh5/ArticlePublish.vue'), meta: { public: true, h5: true } },
  { path: '/hqh5/notification-push', name: 'Hqh5Notification', component: () => import('../views/hqh5/NotificationPush.vue'), meta: { public: true, h5: true } },
  { path: '/hqh5/backend-dashboard', name: 'Hqh5Dashboard', component: () => import('../views/hqh5/BackendDashboard.vue'), meta: { public: true, h5: true } },
  { path: '/hqh5/booking-success', name: 'Hqh5BookingSuccess', component: () => import('../views/hqh5/BookingSuccess.vue'), meta: { public: true, h5: true } },
  { path: '/hqh5/crm-dashboard', name: 'Hqh5Crm', component: () => import('../views/hqh5/CrmDashboard.vue'), meta: { public: true, h5: true } }
]  

// ============ 广告物料租赁报价下单 (adOrder) ============
// 完全模仿 minip 模式：nginx /adorder → index.html + SPA 接管
const adorderRoutes = [
  { path: '/adorder', name: 'AdorderClient', component: () => import('../views/rental/ClientBrowser.vue'), meta: { public: true, h5: true, title: '物料租赁询价' } },
  { path: '/adorder/admin', name: 'AdorderAdmin', component: () => import('../views/rental/AdminBoard.vue'), meta: { public: true, h5: true, title: '租赁订单看板' } },
  { path: '/adorder/admin/quote-templates', name: 'AdorderTemplates', component: () => import('../views/rental/AdminBoard.vue'), meta: { public: true, h5: true } },
]

// ============ 小程序端 (Minip) - 企业/财务/HR/OA/营销/我的 ============
// 完全模仿 adorder 模式：nginx /minip → index.html + SPA 接管
// 所有页面内部已 import <MinipLayout> 包裹，无需嵌套路由
const minipRoutes = [
  { path: '/minip/login', name: 'MinipLogin', component: () => import('../views/minip/MeLogin.vue'), meta: { public: true, h5: true, title: '小程序登录' } },
  { path: '/minip', name: 'MinipEnterprise', component: () => import('../views/minip/EnterpriseHome.vue'), meta: { public: true, h5: true, title: '企业服务' } },
  // 财务
  { path: '/minip/finance', name: 'MinipFinance', component: () => import('../views/minip/Finance.vue'), meta: { public: true, h5: true, title: '财务中心' } },
  { path: '/minip/finance/expense', name: 'MinipFinanceExpense', component: () => import('../views/minip/FinanceExpense.vue'), meta: { public: true, h5: true } },
  { path: '/minip/finance/receipt', name: 'MinipFinanceReceipt', component: () => import('../views/minip/FinanceReceipt.vue'), meta: { public: true, h5: true } },
  { path: '/minip/finance/wallet', name: 'MinipFinanceWallet', component: () => import('../views/minip/FinanceWallet.vue'), meta: { public: true, h5: true } },
  { path: '/minip/finance/invoice', name: 'MinipFinanceInvoice', component: () => import('../views/minip/FinanceInvoice.vue'), meta: { public: true, h5: true } },
  // HR
  { path: '/minip/hr', name: 'MinipHr', component: () => import('../views/minip/Hr.vue'), meta: { public: true, h5: true, title: '人力中心' } },
  { path: '/minip/hr/attendance', name: 'MinipHrAttendance', component: () => import('../views/minip/HrAttendance.vue'), meta: { public: true, h5: true } },
  { path: '/minip/hr/directory', name: 'MinipHrDirectory', component: () => import('../views/minip/HrDirectory.vue'), meta: { public: true, h5: true } },
  { path: '/minip/hr/leave', name: 'MinipHrLeave', component: () => import('../views/minip/HrLeave.vue'), meta: { public: true, h5: true } },
  { path: '/minip/hr/salary', name: 'MinipHrSalary', component: () => import('../views/minip/HrSalary.vue'), meta: { public: true, h5: true } },
  // OA
  { path: '/minip/oa', name: 'MinipOa', component: () => import('../views/minip/Oa.vue'), meta: { public: true, h5: true, title: 'OA 办公' } },
  { path: '/minip/oa/approval', name: 'MinipOaApproval', component: () => import('../views/minip/OaApproval.vue'), meta: { public: true, h5: true } },
  { path: '/minip/oa/meeting', name: 'MinipOaMeeting', component: () => import('../views/minip/OaMeeting.vue'), meta: { public: true, h5: true } },
  { path: '/minip/oa/schedule', name: 'MinipOaSchedule', component: () => import('../views/minip/OaSchedule.vue'), meta: { public: true, h5: true } },
  { path: '/minip/oa/workflow', name: 'MinipOaWorkflow', component: () => import('../views/minip/OaWorkflow.vue'), meta: { public: true, h5: true } },
  // 营销
  { path: '/minip/marketing', name: 'MinipMarketing', component: () => import('../views/minip/Marketing.vue'), meta: { public: true, h5: true, title: '营销中心' } },
  { path: '/minip/marketing/activity', name: 'MinipMarketingActivity', component: () => import('../views/minip/MarketingActivity.vue'), meta: { public: true, h5: true } },
  { path: '/minip/marketing/coupon', name: 'MinipMarketingCoupon', component: () => import('../views/minip/MarketingCoupon.vue'), meta: { public: true, h5: true } },
  { path: '/minip/marketing/member', name: 'MinipMarketingMember', component: () => import('../views/minip/MarketingMember.vue'), meta: { public: true, h5: true } },
  { path: '/minip/marketing/referral', name: 'MinipMarketingReferral', component: () => import('../views/minip/MarketingReferral.vue'), meta: { public: true, h5: true } },
  // 我的
  { path: '/minip/me', name: 'MinipMe', component: () => import('../views/minip/Me.vue'), meta: { public: true, h5: true, title: '个人中心' } },
  { path: '/minip/me/address', name: 'MinipMeAddress', component: () => import('../views/minip/MeAddress.vue'), meta: { public: true, h5: true } },
  { path: '/minip/me/coupons', name: 'MinipMeCoupons', component: () => import('../views/minip/MeCoupons.vue'), meta: { public: true, h5: true } },
  { path: '/minip/me/favorites', name: 'MinipMeFavorites', component: () => import('../views/minip/MeFavorites.vue'), meta: { public: true, h5: true } },
  { path: '/minip/me/orders', name: 'MinipMeOrders', component: () => import('../views/minip/MeOrders.vue'), meta: { public: true, h5: true } },
  { path: '/minip/me/points', name: 'MinipMePoints', component: () => import('../views/minip/MePoints.vue'), meta: { public: true, h5: true } },
  { path: '/minip/me/profile', name: 'MinipMeProfile', component: () => import('../views/minip/MeProfile.vue'), meta: { public: true, h5: true } },
  { path: '/minip/me/reviews', name: 'MinipMeReviews', component: () => import('../views/minip/MeReviews.vue'), meta: { public: true, h5: true } },
  { path: '/minip/me/wallet', name: 'MinipMeWallet', component: () => import('../views/minip/MeWallet.vue'), meta: { public: true, h5: true } },
  // 其他
  { path: '/minip/about', name: 'MinipAbout', component: () => import('../views/minip/MiscAbout.vue'), meta: { public: true, h5: true, title: '关于' } },
  { path: '/minip/feedback', name: 'MinipFeedback', component: () => import('../views/minip/MiscFeedback.vue'), meta: { public: true, h5: true, title: '反馈' } },
]

const routes = [].concat(hqh5Routes, adorderRoutes, minipRoutes, [
  {
    path: '/mall',
    alias: '/mall/',
    component: lazyLoad(() => import('../views/mall/MallLayout.vue')),
    meta: { public: true },
    children: [
      { path: '', name: 'MallHome', component: lazyLoad(() => import('../views/mall/MallHome.vue')), meta: { public: true } },
      { path: 'category/:id', name: 'MallCategory', component: lazyLoad(() => import('../views/mall/MallCategory.vue')), meta: { public: true } },
      { path: 'product/:id', name: 'MallProductDetail', component: lazyLoad(() => import('../views/mall/ProductDetail.vue')), meta: { public: true } },
      { path: 'cart', name: 'MallCart', component: lazyLoad(() => import('../views/mall/MallCart.vue')), meta: { public: true } },
      { path: 'checkout', name: 'Checkout', component: lazyLoad(() => import('../views/mall/Checkout.vue')), meta: { public: true } },
      { path: 'orders', name: 'MallOrderList', component: lazyLoad(() => import('../views/mall/OrderList.vue')), meta: { public: true } },
      { path: 'order/:id', name: 'MallOrderDetail', component: lazyLoad(() => import('../views/h5/H5OrderDetail.vue')), meta: { public: true } },
      { path: 'address', name: 'H5AddressList', component: lazyLoad(() => import('../views/h5/H5AddressList.vue')), meta: { public: true } },
      { path: 'address/edit', name: 'H5AddressEdit', component: lazyLoad(() => import('../views/h5/H5AddressEdit.vue')), meta: { public: true } },
      { path: 'login', name: 'MallLogin', component: lazyLoad(() => import('../views/mall/MallLogin.vue')), meta: { public: true } },
      { path: 'register', name: 'MallRegister', component: lazyLoad(() => import('../views/mall/MallRegister.vue')), meta: { public: true } },
      { path: 'profile', name: 'MallProfile', component: lazyLoad(() => import('../views/h5/H5Profile.vue')), meta: { public: true } },
      { path: 'score-shop', name: 'ScoreShopHome', component: lazyLoad(() => import('../views/store/ScoreShopHome.vue')), meta: { public: true } },
      { path: 'score-product/:id', name: 'ScoreProductDetail', component: lazyLoad(() => import('../views/store/ScoreProductDetail.vue')), meta: { public: true } },
      { path: 'coupons', name: 'CouponList', component: lazyLoad(() => import('../views/store/CouponList.vue')), meta: { public: true } },
      { path: 'score-orders', name: 'ScoreOrderList', component: lazyLoad(() => import('../views/store/ScoreOrderList.vue')), meta: { public: true } },
      { path: 'score-order/:id', name: 'ScoreOrderDetail', component: lazyLoad(() => import('../views/store/ScoreOrderDetail.vue')), meta: { public: true } },
    ],
  },
  // ── H5移动端商城 ──────────────────────────────────────────────────
  {
    path: '/h5',
    component: lazyLoad(() => import('../views/h5/H5Layout.vue')),
    meta: { public: true, h5: true },
    children: [
      { path: '', name: 'H5Home', component: lazyLoad(() => import('../views/h5/H5Home.vue')), meta: { public: true } },
      { path: 'home', name: 'H5Home2', redirectTo: '/h5' },
      { path: 'categories', name: 'H5Categories', component: lazyLoad(() => import('../views/h5/H5Categories.vue')), meta: { public: true } },
      { path: 'cart', name: 'H5Cart', component: lazyLoad(() => import('../views/h5/H5Cart.vue')), meta: { public: true } },
      { path: 'profile', name: 'H5Profile', component: lazyLoad(() => import('../views/h5/H5Profile.vue')), meta: { public: true } },
      { path: 'product/:id', name: 'H5ProductDetail', component: lazyLoad(() => import('../views/h5/H5ProductDetail.vue')), meta: { public: true } },
      { path: 'checkout', name: 'H5Checkout', component: lazyLoad(() => import('../views/h5/H5Checkout.vue')), meta: { public: true } },
      { path: 'order-pay', name: 'H5OrderPay', component: lazyLoad(() => import('../views/h5/H5OrderPay.vue')), meta: { public: true } },
      { path: 'orders', name: 'H5Orders', component: lazyLoad(() => import('../views/h5/H5Orders.vue')), meta: { public: true } },
      { path: 'order/:id', name: 'H5OrderDetail', component: lazyLoad(() => import('../views/h5/H5OrderDetail.vue')), meta: { public: true } },
      { path: 'seckill', name: 'H5Seckill', component: lazyLoad(() => import('../views/h5/H5Seckill.vue')), meta: { public: true } },
      { path: 'collage/:id', name: 'H5Collage', component: lazyLoad(() => import('../views/h5/H5Collage.vue')), meta: { public: true } },
      { path: 'address/list', name: 'H5AddressList', component: lazyLoad(() => import('../views/h5/H5AddressList.vue')), meta: { public: true } },
      { path: 'address/edit', name: 'H5AddressEdit', component: lazyLoad(() => import('../views/h5/H5AddressEdit.vue')), meta: { public: true } },
      { path: 'coupons', name: 'H5Coupons', component: lazyLoad(() => import('../views/h5/H5Coupons.vue')), meta: { public: true } },
      { path: 'score-history', name: 'H5ScoreHistory', component: lazyLoad(() => import('../views/h5/H5ScoreHistory.vue')), meta: { public: true } },
      { path: 'score-detail', name: 'H5ScoreDetail', component: lazyLoad(() => import('../views/h5/H5ScoreDetail.vue')), meta: { public: true } },
      { path: 'wallet', name: 'H5Wallet', component: lazyLoad(() => import('../views/h5/H5Wallet.vue')), meta: { public: true } },
      { path: 'settings', name: 'H5Settings', component: lazyLoad(() => import('../views/h5/H5Settings.vue')), meta: { public: true } },
      { path: 'profile/edit', name: 'H5ProfileEdit', component: lazyLoad(() => import('../views/h5/H5ProfileEdit.vue')), meta: { public: true } },
      { path: 'password', name: 'H5Password', component: lazyLoad(() => import('../views/h5/H5Password.vue')), meta: { public: true } },
      { path: 'bind-phone', name: 'H5BindPhone', component: lazyLoad(() => import('../views/h5/H5BindPhone.vue')), meta: { public: true } },
    ],
  },
  // 登录页独立于 MainLayout，不带侧边栏
  { path: '/login', name: 'Login', component: lazyLoad(() => import('../views/Login.vue')), meta: { public: true } },
  // 扫码页公开路由（不登录也能查看产品信息）
  { path: '/scan/:code', name: 'ScanPage', component: lazyLoad(() => import('../views/scan/ScanPage.vue')), meta: { public: true } },

  // 寺庙服务 - C 端公开路由（扫码查骨灰盒 + 请和尚代劳 + 捐赠）
  { path: '/temple/memorial', name: 'TempleMemorial', component: lazyLoad(() => import('../views/temple/MemorialPage.vue')), meta: { public: true } },
  { path: '/temple/memorial-order', name: 'TempleMemorialOrder', component: lazyLoad(() => import('../views/temple/MemorialOrder.vue')), meta: { public: true } },
  { path: '/temple/memorial-status', name: 'TempleMemorialStatus', component: lazyLoad(() => import('../views/temple/MemorialStatus.vue')), meta: { public: true } },
  { path: '/temple/donation', name: 'TempleDonation', component: lazyLoad(() => import('../views/temple/DonationPage.vue')), meta: { public: true } },
  {
    path: '/',
    component: MainLayout,
    children: [
      // ── 核心模块 ────────────────────────────────────────────
      { path: '', name: 'Dashboard', component: lazyLoad(() => import('../views/Dashboard.vue')), meta: { title: '工作台', icon: 'dashboard', permission: 'dashboard:view' } },

      // 商品
      { path: 'products', name: 'Products', component: lazyLoad(() => import('../views/products/ProductList.vue')), meta: { title: '商品管理', parent: '库存管理', permission: 'product:write' } },

      // 库存
      { path: 'in-out', name: 'InOut', component: lazyLoad(() => import('../views/inventory/InOutList.vue')), meta: { title: '出入库管理', parent: '库存管理', permission: 'inventory:write' } },
      { path: 'stock', name: 'StockManage', component: lazyLoad(() => import('../views/inventory/StockManage.vue')), meta: { title: '库存管理', parent: '库存管理', permission: 'stock:read' } },
      { path: 'stocktake', name: 'Stocktake', component: lazyLoad(() => import('../views/inventory/Stocktake.vue')), meta: { title: '库存盘点', parent: '库存管理', permission: 'stocktake:run' } },
      { path: 'warehouses', name: 'Warehouses', component: lazyLoad(() => import('../views/warehouse/WarehouseList.vue')), meta: { title: '仓库列表', parent: '仓库管理', permission: 'warehouse:write' } },
      { path: 'warehouses/:id', name: 'WarehouseDetail', component: lazyLoad(() => import('../views/warehouse/WarehouseDetail.vue')), meta: { title: '仓库详情', parent: '仓库管理', permission: 'warehouse:read' } },
      { path: 'alerts', name: 'StockAlerts', component: lazyLoad(() => import('../views/alerts/StockAlerts.vue')), meta: { title: '库存预警', parent: '库存管理', permission: 'stock:read' } },

      // ── 审批 ────────────────────────────────────────────────
      { path: 'approvals', name: 'Approvals', component: lazyLoad(() => import('../views/approval/ApprovalList.vue')), meta: { title: '审批列表', parent: '审批中心', permission: 'approval:read' } },
      { path: 'approvals/:id', name: 'ApprovalDetail', component: lazyLoad(() => import('../views/approval/ApprovalDetail.vue')), meta: { title: '审批详情', parent: '审批中心', permission: 'approval:read' } },
      { path: 'gift-approvals', name: 'GiftApprovals', component: lazyLoad(() => import('../views/approval/GiftApprovalList.vue')), meta: { title: '赠送审批', permission: 'approval:gift' } },

      // ── 零售/售后/订单 ─────────────────────────────────────────
      { path: 'retail', name: 'RetailRecords', component: lazyLoad(() => import('../views/retail/RetailRecords.vue')), meta: { title: '零售记录', permission: 'retail:write' } },
      { path: 'aftersale', name: 'AftersaleManage', component: lazyLoad(() => import('../views/aftersale/AftersaleManage.vue')), meta: { title: '售后管理', permission: 'aftersale:write' } },
      { path: 'orders', name: 'OrderList', component: lazyLoad(() => import('../views/orders/OrderList.vue')), meta: { title: '订单管理', parent: '商城', permission: 'order:read' } },
      // 店长勾 preorder:create 就能看到「新增订货单」（店长无需 order:write 完整订单权限）
      { path: 'orders/create', name: 'OnlineOrderCreate', component: lazyLoad(() => import('../views/orders/OnlineOrderCreate.vue')), meta: { title: '新建订货单', parent: '产品预订', permission: 'preorder:create' } },
      { path: 'orders/:id', name: 'OrderDetail', component: lazyLoad(() => import('../views/orders/OrderDetail.vue')), meta: { title: '订单详情', parent: '商城', permission: 'order:read' } },
      { path: 'referral', name: 'ReferralManage', component: lazyLoad(() => import('../views/orders/ReferralManage.vue')), meta: { title: '推荐裂变', parent: '商城', permission: 'referral:read' } },

      // ── 传媒物料租赁报价下单系统（核心新模块）────────────────
      { path: 'rental', name: 'RentalWorkspace', component: lazyLoad(() => import('../views/rental/RentalWorkspace.vue')), meta: { title: '传媒租赁', icon: 'construction', permission: 'quote:read' } },
      { path: 'rental/products',  name: 'RentalProducts',  component: lazyLoad(() => import('../views/rental/ProductsTab.vue')),  meta: { title: '广告物料', parent: '传媒租赁', permission: 'product:write' } },
      { path: 'rental/quotes',    name: 'RentalQuotes',    component: lazyLoad(() => import('../views/rental/QuotesTab.vue')),    meta: { title: '报价管理', parent: '传媒租赁', permission: 'quote:read' } },
      { path: 'rental/orders',    name: 'RentalOrders',    component: lazyLoad(() => import('../views/rental/OrdersTab.vue')),    meta: { title: '我的订单', parent: '传媒租赁', permission: 'quote:read' } },
      { path: 'rental/inout',     name: 'RentalInOut',     component: lazyLoad(() => import('../views/rental/InOutTab.vue')),     meta: { title: '出入库',   parent: '传媒租赁', permission: 'inventory:write' } },
      { path: 'rental/dashboard', name: 'RentalDashboard', component: lazyLoad(() => import('../views/rental/DashboardTab.vue')), meta: { title: '库存看板', parent: '传媒租赁', permission: 'warehouse:visual' } },
      { path: 'quote', name: 'QuoteList', component: lazyLoad(() => import('../views/quote/QuoteList.vue')), meta: { title: '报价管理', parent: '传媒租赁', permission: 'quote:read' } },
      { path: 'quote/create', name: 'QuoteCreate', component: lazyLoad(() => import('../views/quote/QuoteCreate.vue')), meta: { title: '新建报价', parent: '传媒租赁', permission: 'quote:write' } },
      { path: 'quote/detail/:id', name: 'QuoteDetail', component: lazyLoad(() => import('../views/quote/QuoteDetail.vue')), meta: { title: '报价详情', parent: '传媒租赁', permission: 'quote:read' } },
      { path: 'warehouse-visual', name: 'WarehouseVisual', component: lazyLoad(() => import('../views/warehouse-visual/WarehouseVisual.vue')), meta: { title: '仓库可视化', parent: '传媒租赁', permission: 'warehouse:visual' } },

      // ── 门店预订单（独立模块：产品预订）─────────────────────
      // 「新增订货单」复用 /orders/create 的 OnlineOrderCreate.vue（昨日已开发）
      { path: 'preorder/summary', name: 'PreorderSummary', component: lazyLoad(() => import('../views/preorder/PreorderSummary.vue')), meta: { title: '订货单汇总表', parent: '产品预订', permission: 'preorder:aggregate' } },
      // 箱唛打印页（A4 横版排 2 张贴码）
      { path: 'preorder/carton-print/:drf_id', name: 'CartonPrint', component: lazyLoad(() => import('../views/preorder/CartonPrint.vue')), meta: { title: '箱唛打印', parent: '产品预订', permission: 'preorder:confirm_warehouse' } },
      // 扫码收货页（仓管扫码枪录入 BDSP 条码）
      { path: 'preorder/scan', name: 'CartonScan', component: lazyLoad(() => import('../views/preorder/CartonScan.vue')), meta: { title: '扫码收货', parent: '产品预订', permission: 'preorder:confirm_warehouse' } },

      // ── 餐饮管理 ──────────────────────────────────────────────
      { path: 'restaurant', name: 'RestaurantDashboard', component: lazyLoad(() => import('../views/restaurant/RestaurantDashboard.vue')), meta: { title: '餐饮管理', permission: 'restaurant:read' } },
      { path: 'restaurant/tables', name: 'TableManage', component: lazyLoad(() => import('../views/restaurant/TableManage.vue')), meta: { title: '桌台管理', parent: '餐饮管理', permission: 'restaurant:write' } },
      { path: 'restaurant/dishes', name: 'DishManage', component: lazyLoad(() => import('../views/restaurant/DishManage.vue')), meta: { title: '菜品管理', parent: '餐饮管理', permission: 'restaurant:write' } },
      { path: 'restaurant/dine-orders', name: 'DineOrderList', component: lazyLoad(() => import('../views/restaurant/DineOrderList.vue')), meta: { title: '堂食订单', parent: '餐饮管理', permission: 'restaurant:read' } },
      { path: 'restaurant/takeout', name: 'TakeoutOrderList', component: lazyLoad(() => import('../views/restaurant/TakeoutOrderList.vue')), meta: { title: '外卖订单', parent: '餐饮管理', permission: 'restaurant:read' } },
      { path: 'restaurant/reservations', name: 'ReservationManage', component: lazyLoad(() => import('../views/restaurant/ReservationManage.vue')), meta: { title: '预订管理', parent: '餐饮管理', permission: 'restaurant:read' } },
      { path: 'restaurant/queue', name: 'QueueManage', component: lazyLoad(() => import('../views/restaurant/QueueManage.vue')), meta: { title: '排队叫号', parent: '餐饮管理', permission: 'restaurant:read' } },
      { path: 'restaurant/cashier', name: 'CashierManage', component: lazyLoad(() => import('../views/restaurant/CashierManage.vue')), meta: { title: '收银管理', parent: '餐饮管理', permission: 'restaurant:write' } },

      // ── 物流管理 ──────────────────────────────────────────────
      { path: 'logistics', name: 'LogisticsDashboard', component: lazyLoad(() => import('../views/logistics/LogisticsDashboard.vue')), meta: { title: '物流管理', parent: '商城', permission: 'logistics:read' } },
      { path: 'logistics/express', name: 'ExpressList', component: lazyLoad(() => import('../views/logistics/ExpressList.vue')), meta: { title: '快递公司', parent: '物流管理', permission: 'express_read' } },
      { path: 'logistics/templates', name: 'ShippingTemplate', component: lazyLoad(() => import('../views/logistics/ShippingTemplate.vue')), meta: { title: '运费模板', parent: '物流管理', permission: 'freight_read' } },
      { path: 'logistics/channels', name: 'ChannelLogistics', component: lazyLoad(() => import('../views/logistics/ChannelLogistics.vue')), meta: { title: '渠道物流', parent: '物流管理', permission: 'channel_read' } },

      // ── 预约 ────────────────────────────────────────────────
      { path: 'yuyue', name: 'YuyueList', component: lazyLoad(() => import('../views/yuyue/YuyueList.vue')), meta: { title: '预约管理', permission: 'yuyue:read' } },
      { path: 'yuyue/:id', name: 'YuyueDetail', component: lazyLoad(() => import('../views/yuyue/YuyueDetail.vue')), meta: { title: '预约详情', permission: 'yuyue:read' } },
      { path: 'articles', name: 'ArticleList', component: lazyLoad(() => import('../views/articles/ArticleList.vue')), meta: { title: '文章管理', parent: '商城', permission: 'articles:read' } },
      { path: 'articles/new', name: 'ArticleNew', component: lazyLoad(() => import('../views/articles/ArticleDetail.vue')), meta: { title: '新增文章', parent: '商城', permission: 'articles:write' } },
      { path: 'articles/:id', name: 'ArticleDetail', component: lazyLoad(() => import('../views/articles/ArticleDetail.vue')), meta: { title: '文章详情', parent: '商城', permission: 'articles:read' } },

      // ── 积分商城 / 优惠券 ─────────────────────────────────────
      { path: 'score-products', name: 'ScoreProductManage', component: lazyLoad(() => import('../views/mall/ScoreProductManage.vue')), meta: { title: '积分商品管理', parent: '商城', permission: 'product:write' } },
      { path: 'score-orders', name: 'ScoreOrderManage', component: lazyLoad(() => import('../views/mall/ScoreOrderManage.vue')), meta: { title: '积分订单管理', parent: '商城', permission: 'order:read' } },
      { path: 'coupon-manage', name: 'CouponManage', component: lazyLoad(() => import('../views/mall/CouponManage.vue')), meta: { title: '优惠券管理', parent: '商城', permission: 'order:read' } },
      { path: 'wallet-manage', name: 'WalletManage', component: lazyLoad(() => import('../views/finance/WalletManage.vue')), meta: { title: '会员钱包管理', parent: '财务管理', permission: 'finance:read' } },
      { path: 'invite-manage', name: 'InviteManage', component: lazyLoad(() => import('../views/marketing/InviteManage.vue')), meta: { title: '邀请返现管理', parent: '营销', permission: 'marketing:read' } },
      { path: 'seckill-manage', name: 'SeckillManage', component: lazyLoad(() => import('../views/marketing/SeckillManage/index.vue')), meta: { title: '秒杀管理', parent: '营销', permission: 'marketing:read' } },
      { path: 'member-level-manage', name: 'MemberLevelManage', component: lazyLoad(() => import('../views/member/MemberLevelManage.vue')), meta: { title: '会员等级管理', parent: '会员', permission: 'user:read' } },

      // ── 调货/退货 ────────────────────────────────────────────
      { path: 'transfer', name: 'TransferList', component: lazyLoad(() => import('../views/transfer/TransferList.vue')), meta: { title: '调货管理', permission: 'transfer:read' } },
      { path: 'transfer/create', name: 'TransferCreate', component: lazyLoad(() => import('../views/transfer/TransferCreate.vue')), meta: { title: '创建调货单', permission: 'transfer:write' } },
      { path: 'transfer/:id', name: 'TransferDetail', component: lazyLoad(() => import('../views/transfer/TransferDetail.vue')), meta: { title: '调货详情', permission: 'transfer:read' } },
      { path: 'inventory/returns', name: 'ReturnList', component: lazyLoad(() => import('../views/inventory/ReturnList.vue')), meta: { title: '退货记录', parent: '库存管理', permission: 'inventory:return' } },

      // 寺庙服务 - 管理端
      { path: 'temple', name: 'TempleDashboard', component: lazyLoad(() => import('../views/temple/admin/Dashboard.vue')), meta: { title: '寺庙服务', parent: '寺庙', permission: 'temple:read' } },
      { path: 'temple/caskets', name: 'TempleCaskets', component: lazyLoad(() => import('../views/temple/admin/CasketManage.vue')), meta: { title: '骨灰盒管理', parent: '寺庙', permission: 'temple:read' } },
      { path: 'temple/ancestors', name: 'TempleAncestors', component: lazyLoad(() => import('../views/temple/admin/AncestorManage.vue')), meta: { title: '逝者档案', parent: '寺庙', permission: 'temple:read' } },
      { path: 'temple/orders', name: 'TempleOrders', component: lazyLoad(() => import('../views/temple/admin/OrderManage.vue')), meta: { title: '祭拜订单', parent: '寺庙', permission: 'temple:read' } },
      { path: 'temple/donations', name: 'TempleDonations', component: lazyLoad(() => import('../views/temple/admin/DonationManage.vue')), meta: { title: '捐赠管理', parent: '寺庙', permission: 'temple:read' } },
      { path: 'temple/monks', name: 'TempleMonks', component: lazyLoad(() => import('../views/temple/admin/MonkManage.vue')), meta: { title: '和尚管理', parent: '寺庙', permission: 'temple:read' } },

      // ── AI / BI ─────────────────────────────────────────────
      { path: 'ai-classroom', name: 'AiClassroom', component: lazyLoad(() => import('../views/AiClassroom.vue')), meta: { title: 'AI 课堂', permission: 'ai-classroom' } },
      { path: 'excel-analyzer', name: 'ExcelAnalyzer', component: lazyLoad(() => import('../views/bi/ExcelAnalyzer.vue')), meta: { title: 'Excel 分析器', parent: 'BI', permission: 'bi:excel' } },
      { path: 'excel-report-manage', name: 'ExcelReportManage', component: lazyLoad(() => import('../views/bi/ExcelReportManage.vue')), meta: { title: '报告管理', parent: 'BI', permission: 'bi:report' } },
      { path: 'import-records', name: 'ImportRecords', component: lazyLoad(() => import('../views/import/ImportRecords.vue')), meta: { title: '导入记录', parent: 'BI', permission: 'bi:excel' } },
      { path: 'import-detail/:id', name: 'ImportDetail', component: lazyLoad(() => import('../views/import/ImportDetail.vue')), meta: { title: '导入明细', parent: 'BI', permission: 'bi:excel' } },
      { path: 'import-detail-multi/:ids', name: 'ImportDetailMulti', component: lazyLoad(() => import('../views/import/ImportDetailMulti.vue')), meta: { title: '多选门店分析', parent: 'BI', permission: 'bi:excel' } },
      { path: 'store-sales', name: 'StoreSales', component: lazyLoad(() => import('../views/import/StoreSalesReport.vue')), meta: { title: '门店销售报表', parent: 'BI', permission: 'bi:excel' } },

      // ── 消息 ────────────────────────────────────────────────
      { path: 'wecom', name: 'WeCom', component: lazyLoad(() => import('../views/wecom/WeComChat.vue')), meta: { title: '企业微信', parent: '消息', permission: 'wecom:read' } },
      { path: 'kefu', name: 'Kefu', component: lazyLoad(() => import('../views/kefu/KefuChat.vue')), meta: { title: '客服消息', parent: '消息', permission: 'kefu:read' } },
      { path: 'ai-automation', name: 'AiAutomation', component: lazyLoad(() => import('../views/automation/AiAutomation.vue')), meta: { title: 'AI 自动化', parent: 'OpenClaw', permission: 'ai-automation:write' } },

      // ── OA 办公 ──────────────────────────────────────────────
      { path: 'oa', name: 'OaCenter', component: lazyLoad(() => import('../views/oa/OaCenter.vue')), meta: { title: 'OA 办公', permission: 'oa:read' } },
      { path: 'oa/attendance', name: 'AttendanceManage', component: lazyLoad(() => import('../views/oa/AttendanceManage.vue')), meta: { title: '考勤管理', permission: 'attendance:view' } },
      { path: 'oa/my-responsibility', name: 'MyResponsibility', component: lazyLoad(() => import('../views/oa/MyResponsibility.vue')), meta: { title: '我的权责', permission: 'oa:read' } },
      { path: 'oa/approvals', name: 'OaApprovalManage', component: lazyLoad(() => import('../views/oa/ApprovalManage.vue')), meta: { title: '审批管理', permission: 'approval:write' } },
      { path: 'oa/approvals/create', name: 'ApprovalCreate', component: lazyLoad(() => import('../views/oa/ApprovalCreate.vue')), meta: { title: '发起审批', permission: 'approval:write' } },
      { path: 'oa/directory', name: 'EmployeeDirectory', component: lazyLoad(() => import('../views/oa/EmployeeDirectory.vue')), meta: { title: '通讯录', permission: 'oa:read' } },
      { path: 'oa/shifts', name: 'ShiftManage', component: lazyLoad(() => import('../views/oa/ShiftManage.vue')), meta: { title: '班次管理', permission: 'shift:write' } },
      { path: 'oa/schedule', name: 'ScheduleCalendar', component: lazyLoad(() => import('../views/oa/ScheduleCalendar.vue')), meta: { title: '排班日历', permission: 'schedule:write' } },
      { path: 'oa/attendance-summary', name: 'AttendanceSummary', component: lazyLoad(() => import('../views/oa/AttendanceSummary.vue')), meta: { title: '考勤统计', permission: 'attendance:view' } },
      { path: 'oa/attendance-rules', name: 'AttendanceRuleManage', component: lazyLoad(() => import('../views/oa/AttendanceRuleManage.vue')), meta: { title: '出勤管理', permission: 'attendance:view' } },
      { path: 'oa/leave', name: 'LeaveManage', component: lazyLoad(() => import('../views/oa/LeaveManage.vue')), meta: { title: '请假管理', permission: 'leave:write' } },
      { path: 'oa/workflow', name: 'WorkflowDesigner', component: lazyLoad(() => import('../views/oa/WorkflowDesigner.vue')), meta: { title: '工作流管理', permission: 'workflow:write' } },

      // ── 任务 ────────────────────────────────────────────────
      { path: 'tasks', name: 'TaskManage', component: lazyLoad(() => import('../views/tasks/TaskManage.vue')), meta: { title: '任务管理', permission: 'task:read' } },
      { path: 'tasks/stats', name: 'TaskStats', component: lazyLoad(() => import('../views/tasks/TaskStats.vue')), meta: { title: '任务统计', permission: 'task:stats' } },

      // ── 日志 ────────────────────────────────────────────────
      { path: 'logs/work-logs', name: 'LogsWorkLogManage', component: lazyLoad(() => import('../views/logs/WorkLogManage.vue')), meta: { title: '工作日志', parent: '日志系统', permission: 'work_log:read' } },
      { path: 'logs/visit-logs', name: 'LogsVisitLogManage', component: lazyLoad(() => import('../views/logs/VisitLogManage.vue')), meta: { title: '拜访日志', parent: '日志系统', permission: 'work_log:read' } },
      { path: 'logs/share-logs', name: 'LogsShareLogManage', component: lazyLoad(() => import('../views/logs/ShareLogManage.vue')), meta: { title: '分享日志', parent: '日志系统', permission: 'work_log:read' } },
      { path: 'logs/feedback', name: 'LogsFeedbackManage', component: lazyLoad(() => import('../views/logs/FeedbackManage.vue')), meta: { title: '投诉建议', parent: '日志系统', permission: 'work_log:read' } },

      // ── 二维码/报表 ───────────────────────────────────────────
      { path: 'qrcode', name: 'QrcodeManage', component: lazyLoad(() => import('../views/qrcode/QrcodeManage.vue')), meta: { title: '一物一码', permission: 'qrcode:read' } },
      { path: 'reports', name: 'Reports', component: lazyLoad(() => import('../views/reports/ReportCenter.vue')), meta: { title: '报表中心', permission: 'report:read' } },

      // ── 个人信息 ────────────────────────────────────────────
      { path: 'profile', name: 'UserProfile', component: lazyLoad(() => import('../views/profile/UserProfile.vue')), meta: { title: '个人信息', permission: 'quick-action-profile' } },

      // ── 系统设置（admin only）────────────────────────────────
      { path: 'settings', name: 'Settings', component: lazyLoad(() => import('../views/settings/SystemSettings.vue')), meta: { title: '系统设置', permission: 'system:config' } },
      { path: 'settings/roles', name: 'RoleManage', component: lazyLoad(() => import('../views/settings/RoleManage.vue')), meta: { title: '角色管理', permission: 'role:write' } },
      { path: 'settings/users', name: 'UserManagement', component: lazyLoad(() => import('../views/settings/UserManagement.vue')), meta: { title: '用户管理', permission: 'user:write' } },
      { path: 'settings/h5-users', name: 'H5UserManage', component: lazyLoad(() => import('../views/settings/H5UserManage.vue')), meta: { title: 'H5用户管理', permission: 'user:read' } },
      { path: 'settings/job-responsibilities', name: 'JobResponsibilities', component: lazyLoad(() => import('../views/settings/JobResponsibilities.vue')), meta: { title: '职位权责管理', permission: 'permission:read' } },
      { path: 'settings/responsibilities', name: 'ResponsibilityManage', component: lazyLoad(() => import('../views/settings/ResponsibilityManage.vue')), meta: { title: '权责管理', permission: 'permission:read' } },
      { path: 'settings/server-profiles', name: 'ServerProfiles', component: lazyLoad(() => import('../views/settings/ServerProfiles.vue')), meta: { title: '目标服务器管理', permission: 'system:config' } },

      // ── 财务 ────────────────────────────────────────────────
      { path: 'finance', name: 'FinanceOverview', component: lazyLoad(() => import('../views/finance-simple/FinanceOverview.vue')), meta: { title: '财务总览', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/purchase-costs', name: 'PurchaseCosts', component: lazyLoad(() => import('../views/finance-simple/PurchaseCosts.vue')), meta: { title: '采购成本', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/sales-revenues', name: 'SalesRevenues', component: lazyLoad(() => import('../views/finance-simple/SalesRevenues.vue')), meta: { title: '销售收入', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/expenses', name: 'ExpenseManage', component: lazyLoad(() => import('../views/finance-simple/ExpenseManage.vue')), meta: { title: '费用支出', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/accounts-payable', name: 'AccountsPayable', component: lazyLoad(() => import('../views/finance-simple/AccountsPayable.vue')), meta: { title: '应付款管理', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/accounts-receivable', name: 'AccountsReceivable', component: lazyLoad(() => import('../views/finance-simple/AccountsReceivable.vue')), meta: { title: '应收款管理', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/profit-analysis', name: 'ProfitAnalysis', component: lazyLoad(() => import('../views/finance-simple/ProfitAnalysis.vue')), meta: { title: '利润分析', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/fund-accounts', name: 'FundAccounts', component: lazyLoad(() => import('../views/finance-simple/FundAccounts.vue')), meta: { title: '资金账户', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/cash-flow', name: 'CashFlow', component: lazyLoad(() => import('../views/finance-simple/CashFlow.vue')), meta: { title: '资金流水', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/supplier-statement', name: 'SupplierStatement', component: lazyLoad(() => import('../views/finance-simple/SupplierStatement.vue')), meta: { title: '供货商对账', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/customer-statement', name: 'CustomerStatement', component: lazyLoad(() => import('../views/finance-simple/CustomerStatement.vue')), meta: { title: '客户对账', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/invoices', name: 'InvoiceManage', component: lazyLoad(() => import('../views/finance-simple/InvoiceManage.vue')), meta: { title: '发票管理', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/invoice-statistics', name: 'InvoiceStatistics', component: lazyLoad(() => import('../views/finance-simple/InvoiceStatistics.vue')), meta: { title: '发票统计', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/reminders', name: 'FinanceReminders', component: lazyLoad(() => import('../views/finance-simple/FinanceReminders.vue')), meta: { title: '财务提醒', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/reminder-settings', name: 'ReminderSettings', component: lazyLoad(() => import('../views/finance-simple/ReminderSettings.vue')), meta: { title: '提醒设置', parent: '财务管理', permission: 'finance:write' } },
      { path: 'finance/approval-settings', name: 'ApprovalSettings', component: lazyLoad(() => import('../views/finance-simple/ApprovalSettings.vue')), meta: { title: '审批设置', parent: '财务管理', permission: 'finance:write' } },
      { path: 'finance/receipts', name: 'ReceiptManage', component: lazyLoad(() => import('../views/finance-simple/ReceiptManage.vue')), meta: { title: '收款管理', parent: '财务管理', permission: 'finance:read' } },
      { path: 'finance/payments', name: 'PaymentManage', component: lazyLoad(() => import('../views/finance-simple/PaymentManage.vue')), meta: { title: '付款管理', parent: '财务管理', permission: 'finance:read' } },

      // ── 合作伙伴 ──────────────────────────────────────────────
      { path: 'suppliers', name: 'Suppliers', component: lazyLoad(() => import('../views/suppliers/SupplierList.vue')), meta: { title: '供货商管理', parent: '合作伙伴', permission: 'supplier:write' } },
      { path: 'dealers', name: 'Dealers', component: lazyLoad(() => import('../views/dealers/DealerList.vue')), meta: { title: '经销商管理', parent: '合作伙伴', permission: 'dealer:write' } },
      { path: 'stores', name: 'Stores', component: lazyLoad(() => import('../views/stores/StoreList.vue')), meta: { title: '门店管理', parent: '合作伙伴', permission: 'store:write' } },

      // ── 拼团管理 ──────────────────────────────────────────────
      { path: 'collage', name: 'CollageDashboard', component: lazyLoad(() => import('../views/collage/CollageDashboard.vue')), meta: { title: '拼团管理', permission: 'collage:read' } },
      { path: 'collage/products', name: 'CollageProductList', component: lazyLoad(() => import('../views/collage/CollageProductList.vue')), meta: { title: '拼团商品', parent: '拼团管理', permission: 'collage:write' } },
      { path: 'collage/orders', name: 'CollageOrderList', component: lazyLoad(() => import('../views/collage/CollageOrderList.vue')), meta: { title: '拼团订单', parent: '拼团管理', permission: 'collage:read' } },
      // ── 酒店管理 ──────────────────────────────────────────────
      { path: 'hotel', name: 'HotelDashboard', component: lazyLoad(() => import('../views/hotel/HotelDashboard.vue')), meta: { title: '酒店管理', permission: 'hotel:read' } },
      { path: 'hotel/room-types', name: 'HotelRoomTypes', component: lazyLoad(() => import('../views/hotel/RoomTypeList.vue')), meta: { title: '房型管理', parent: '酒店管理', permission: 'hotel:write' } },
      { path: 'hotel/price-calendar', name: 'HotelPriceCalendar', component: lazyLoad(() => import('../views/hotel/PriceCalendar.vue')), meta: { title: '价格日历', parent: '酒店管理', permission: 'hotel:write' } },
      { path: 'hotel/orders', name: 'HotelOrderList', component: lazyLoad(() => import('../views/hotel/HotelOrderList.vue')), meta: { title: '酒店订单', parent: '酒店管理', permission: 'hotel:read' } },
      { path: 'hotel/orders/:id', name: 'HotelOrderDetail', component: lazyLoad(() => import('../views/hotel/HotelOrderDetail.vue')), meta: { title: '订单详情', parent: '酒店管理', permission: 'hotel:read' } },
      { path: 'hotel/reviews', name: 'HotelReviews', component: lazyLoad(() => import('../views/hotel/ReviewList.vue')), meta: { title: '评价管理', parent: '酒店管理', permission: 'hotel:read' } },
    ],
  },
])

const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

// Catch-all for failed chunk loads → reload page once
router.onError((err) => {
  if (err.message && err.message.includes('Failed to fetch dynamically imported module')) {
    window.location.reload()
  }
})

// 路由切换加载状态
let loadingTimeout = null

// 统一权限守卫：permission meta 驱动
router.beforeEach((to, from, next) => {
  // 显示加载状态
  loadingTimeout = setTimeout(() => {
    document.body.style.cursor = 'wait'
  }, 100)

  const userStore = useUserStore()
  // public 路由直接放行
  if (to.meta.public) {
    return next()
  }

  // 双轨判断：store + localStorage（防止 Pinia 首次初始化时机问题）
  const hasTokenFromLS = !!localStorage.getItem('caimeite_token')
  const isLoggedInEffective = userStore.isLoggedIn || hasTokenFromLS

  // 未登录引导到登录页
  if (to.path !== '/login' && !isLoggedInEffective) {
    return next('/login')
  }

  // 已登录访问登录页则跳转首页
  if (to.path === '/login' && isLoggedInEffective) {
    return next('/')
  }

  // 权限检查 — 无权限时跳转到第一个有权限的菜单（按波哥"出现就能操作"原则）
    if (to.meta.permission) {
      if (userStore.canAccess(to.meta.permission)) {
        return next()
      } else {
        // 找第一个有权限的路由
        const firstAllowed = router.options.routes
          .find(r => r.children?.some(c => c.meta?.permission && userStore.canAccess(c.meta.permission)))
        const fallback = firstAllowed?.children?.find(c => c.meta?.permission && userStore.canAccess(c.meta.permission))
        if (fallback) {
          import('element-plus').then(({ ElMessage }) => {
            ElMessage.warning({
              message: `无权访问「${to.meta.title || to.path}」，已跳转到您有权限的页面`,
              duration: 3000,
            })
          })
          return next({ path: '/' + fallback.path.replace(/^\//, '') })
        }
        // 实在找不到任何有权限的路由
        return next(false)
      }
    }

    return next()
})

router.afterEach(() => {
  if (loadingTimeout) {
    clearTimeout(loadingTimeout)
    loadingTimeout = null
  }
  document.body.style.cursor = ''
})

export default router
