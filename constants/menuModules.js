/**
 * 菜单模块字典（dev 真理源）
 *
 * ★ 模块化铁律：dev 时静态定义，build 编译期通过 /api/settings/menu-modules/sync-static
 *   同步到 DB menu_modules 表。运行时 admin 也可以手动调整。
 *
 * ★ 孤儿处理：profile 勾选了但字典没有的 module_key 叫"孤儿"。
 *   一键「融合」= 自动 INSERT 到字典，53/42 → 53/53。
 *
 * 字段：
 *   key         - 模块唯一标识（路由前缀 / 权限码）
 *   label_zh    - 中文名
 *   label_en    - 英文名
 *   icon        - ElementPlus / material icon
 *   route       - 前端路由 path
 *   category    - main / partner / restaurant / ecommerce / sales / education / company / realestate / hotel / general / business / mall
 *   sort_order  - 排序，越小越前
 *   required    - 1 = 必装不可卸（默认 false）
 *   env_flags   - JSON 数组 ["singapore","beijing","hk3","shanghai"]，限定环境；null = 全环境
 */

export const menuModules = [
  // ─── 通用基础 ───────────────────────────────────────────────
  { key: 'dashboard', label_zh: '工作台', label_en: 'Dashboard', icon: 'odometer', route: '/dashboard', category: 'general', sort_order: 1, required: 1, env_flags: null },
  { key: 'settings', label_zh: '系统设置', label_en: 'Settings', icon: 'setting', route: '/settings', category: 'general', sort_order: 2, required: 1, env_flags: null },
  { key: 'users', label_zh: '用户管理', label_en: 'Users', icon: 'user', route: '/users', category: 'general', sort_order: 3, required: 1, env_flags: null },
  { key: 'roles', label_zh: '角色管理', label_en: 'Roles', icon: 'avatar', route: '/roles', category: 'general', sort_order: 4, required: 1, env_flags: null },
  { key: 'tasks', label_zh: '任务管理', label_en: 'Tasks', icon: 'document', route: '/tasks', category: 'company', sort_order: 5, required: 0, env_flags: null },
  { key: 'attendance', label_zh: '考勤打卡', label_en: 'Attendance', icon: 'timer', route: '/attendance', category: 'company', sort_order: 6, required: 0, env_flags: null },
  { key: 'oa', label_zh: '办公OA', label_en: 'Office OA', icon: 'office-building', route: '/oa', category: 'company', sort_order: 7, required: 0, env_flags: null },
  { key: 'kefu', label_zh: '客服消息', label_en: 'Customer Service', icon: 'chat-dot-round', route: '/kefu', category: 'general', sort_order: 8, required: 0, env_flags: null },

  // ─── 业务基础 ───────────────────────────────────────────────
  { key: 'products', label_zh: '商品管理', label_en: 'Products', icon: 'goods', route: '/products', category: 'business', sort_order: 10, required: 0, env_flags: null },
  { key: 'orders', label_zh: '订单管理', label_en: 'Orders', icon: 'list', route: '/orders', category: 'business', sort_order: 11, required: 0, env_flags: null },
  { key: 'preorder', label_zh: '产品预订', label_en: 'Pre-order', icon: 'shopping-cart-full', route: '/preorder', category: 'business', sort_order: 12, required: 0, env_flags: null },
  { key: 'coupon', label_zh: '优惠券', label_en: 'Coupon', icon: 'discount', route: '/coupon', category: 'business', sort_order: 13, required: 0, env_flags: null },
  { key: 'suppliers', label_zh: '供货商', label_en: 'Suppliers', icon: 'truck', route: '/suppliers', category: 'business', sort_order: 14, required: 0, env_flags: null },
  { key: 'dealers', label_zh: '经销商', label_en: 'Dealers', icon: 'connection', route: '/dealers', category: 'business', sort_order: 15, required: 0, env_flags: null },
  { key: 'stores', label_zh: '门店', label_en: 'Stores', icon: 'shop', route: '/stores', category: 'business', sort_order: 16, required: 0, env_flags: null },
  { key: 'warehouses', label_zh: '仓库管理', label_en: 'Warehouses', icon: 'house', route: '/warehouses', category: 'business', sort_order: 17, required: 0, env_flags: null },
  { key: 'in-out', label_zh: '出入库记录', label_en: 'Stock In/Out', icon: 'upload', route: '/in-out', category: 'business', sort_order: 18, required: 0, env_flags: null },
  { key: 'transfer', label_zh: '库存调拨', label_en: 'Stock Transfer', icon: 'refresh', route: '/transfer', category: 'business', sort_order: 19, required: 0, env_flags: null },
  { key: 'alerts', label_zh: '库存预警', label_en: 'Stock Alerts', icon: 'warning', route: '/alerts', category: 'business', sort_order: 20, required: 0, env_flags: null },
  { key: 'returns', label_zh: '退货管理', label_en: 'Returns', icon: 'refresh-left', route: '/returns', category: 'business', sort_order: 21, required: 0, env_flags: null },
  { key: 'aftersale', label_zh: '售后管理', label_en: 'Aftersale', icon: 'service', route: '/aftersale', category: 'business', sort_order: 22, required: 0, env_flags: null },
  { key: 'gift-approvals', label_zh: '赠送审批', label_en: 'Gift Approvals', icon: 'present', route: '/gift-approvals', category: 'business', sort_order: 23, required: 0, env_flags: null },
  { key: 'finance', label_zh: '财务', label_en: 'Finance', icon: 'money', route: '/finance', category: 'business', sort_order: 24, required: 0, env_flags: null },
  { key: 'reports', label_zh: '报表中心', label_en: 'Reports', icon: 'data-analysis', route: '/reports', category: 'business', sort_order: 25, required: 0, env_flags: null },
  { key: 'job-responsibilities', label_zh: '职位权责管理', label_en: 'Job Responsibilities', icon: 'briefcase', route: '/job-responsibilities', category: 'company', sort_order: 26, required: 0, env_flags: null },
  { key: 'excel-analyzer', label_zh: 'Excel分析', label_en: 'Excel Analyzer', icon: 'table', route: '/excel-analyzer', category: 'business', sort_order: 27, required: 0, env_flags: null },
  { key: 'qrcode', label_zh: '二维码', label_en: 'QR Code', icon: 'qr-code', route: '/qrcode', category: 'general', sort_order: 28, required: 0, env_flags: null },

  // ─── 餐饮 ───────────────────────────────────────────────────
  { key: 'restaurant', label_zh: '餐饮管理', label_en: 'Restaurant', icon: 'food', route: '/restaurant', category: 'restaurant', sort_order: 30, required: 0, env_flags: null },
  { key: 'takeaway', label_zh: '外卖', label_en: 'Takeaway', icon: 'takeaway-box', route: '/takeaway', category: 'restaurant', sort_order: 31, required: 0, env_flags: null },
  { key: 'online-order', label_zh: '在线订餐', label_en: 'Online Order', icon: 'food', route: '/online-order', category: 'restaurant', sort_order: 32, required: 0, env_flags: null },
  { key: 'queue', label_zh: '排号', label_en: 'Queue', icon: 'sort', route: '/queue', category: 'restaurant', sort_order: 33, required: 0, env_flags: null },
  { key: 'pickup', label_zh: '取餐柜', label_en: 'Pickup Locker', icon: 'box', route: '/pickup', category: 'restaurant', sort_order: 34, required: 0, env_flags: null },
  { key: 'cashier', label_zh: '收银', label_en: 'Cashier', icon: 'wallet', route: '/cashier', category: 'restaurant', sort_order: 35, required: 0, env_flags: null },
  { key: 'collage', label_zh: '拼团', label_en: 'Collage', icon: 'promotion', route: '/collage', category: 'restaurant', sort_order: 36, required: 0, env_flags: null },

  // ─── 商城 ───────────────────────────────────────────────────
  { key: 'mall', label_zh: '商城', label_en: 'Mall', icon: 'shopping-bag', route: '/mall', category: 'mall', sort_order: 40, required: 0, env_flags: null },
  { key: 'retail', label_zh: '零售', label_en: 'Retail', icon: 'shop', route: '/retail', category: 'mall', sort_order: 41, required: 0, env_flags: null },
  { key: 'logistics', label_zh: '物流管理', label_en: 'Logistics', icon: 'van', route: '/logistics', category: 'mall', sort_order: 42, required: 0, env_flags: null },
  { key: 'article', label_zh: '文章管理', label_en: 'Article', icon: 'document', route: '/article', category: 'mall', sort_order: 43, required: 0, env_flags: null },
  { key: 'diypage', label_zh: '页面设计', label_en: 'DIY Page', icon: 'brush', route: '/diypage', category: 'mall', sort_order: 44, required: 0, env_flags: null },
  { key: 'score_shop', label_zh: '积分商城', label_en: 'Score Shop', icon: 'star', route: '/score-shop', category: 'mall', sort_order: 45, required: 0, env_flags: null },
  { key: 'yuyue', label_zh: '预约服务', label_en: 'Appointment', icon: 'calendar', route: '/yuyue', category: 'mall', sort_order: 46, required: 0, env_flags: null },

  // ─── 酒店 ───────────────────────────────────────────────────
  { key: 'hotel', label_zh: '酒店管理', label_en: 'Hotel', icon: 'house', route: '/hotel', category: 'hotel', sort_order: 50, required: 0, env_flags: null },

  // ─── 教育 ───────────────────────────────────────────────────
  { key: 'edu', label_zh: '教育中心', label_en: 'Edu Center', icon: 'reading', route: '/edu', category: 'education', sort_order: 60, required: 0, env_flags: null },
  { key: 'ai-classroom', label_zh: 'AI课堂', label_en: 'AI Classroom', icon: 'cpu', route: '/ai-classroom', category: 'education', sort_order: 61, required: 0, env_flags: null },

  // ─── 营销 ───────────────────────────────────────────────────
  { key: 'referral', label_zh: '推荐好友', label_en: 'Referral', icon: 'share', route: '/referral', category: 'business', sort_order: 70, required: 0, env_flags: null },

  // ─── 移动端入口 ─────────────────────────────────────────────
  { key: 'h5', label_zh: 'H5 商城', label_en: 'H5 Mall', icon: 'mobile', route: '/h5', category: 'general', sort_order: 80, required: 0, env_flags: null },
  { key: 'wxapp', label_zh: '微信小程序', label_en: 'WeChat Mini-Program', icon: 'chat-dot-square', route: '/wxapp', category: 'general', sort_order: 81, required: 0, env_flags: null },
  { key: 'mp', label_zh: '微信公众号', label_en: 'WeChat Official Account', icon: 'wechat', route: '/mp', category: 'general', sort_order: 82, required: 0, env_flags: null },
  { key: 'minip', label_zh: '小程序前端', label_en: 'Mini-Program Frontend', icon: 'cellphone', route: '/minip', category: 'general', sort_order: 83, required: 0, env_flags: null },
  { key: 'hqh5', label_zh: '环球汇H5', label_en: 'HQ H5', icon: 'globe', route: '/hqh5', category: 'general', sort_order: 84, required: 0, env_flags: null },

  // ─── 特殊 ───────────────────────────────────────────────────
  { key: 'temple', label_zh: '寺庙管理', label_en: 'Temple', icon: 'sunny', route: '/temple', category: 'general', sort_order: 90, required: 0, env_flags: null },
  { key: 'portal-clone', label_zh: '门户克隆', label_en: 'Portal Clone', icon: 'copy-document', route: '/portal-clone', category: 'general', sort_order: 91, required: 0, env_flags: null },

  // ─── 系统级（admin only）────────────────────────────────────
  { key: 'server_profiles', label_zh: '目标服务器管理', label_en: 'Server Profiles', icon: 'server', route: '/server-profiles', category: 'general', sort_order: 99, required: 1, env_flags: null },
]

export default menuModules