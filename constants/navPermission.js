// 导航栏权限key常量 — 与Sidebar.vue allNavItems/allPartnerItems保持100%同步
// 格式: { key: 权限标识, labelKey: i18n key }
// 新增模块时只需在这里添加一行，SystemSettings和Sidebar自动同步

export const NAV_PERMISSION_KEYS = [
  // 主导航
  { key: 'dashboard',      labelKey: 'nav.dashboard' },
  { key: 'ai-classroom',   labelKey: 'nav.aiClassroom' },
  { key: 'excel-analyzer',  labelKey: 'nav.excelAnalyzer' },
  { key: 'oa',              labelKey: 'nav.oa' },
  { key: 'finance',         labelKey: 'nav.finance' },
  { key: 'tasks',           labelKey: 'nav.tasks' },
  { key: 'qrcode',          labelKey: 'nav.qrcode' },
  { key: 'products',        labelKey: 'nav.products' },
  { key: 'in-out',          labelKey: 'nav.inout' },
  { key: 'warehouses',      labelKey: 'nav.warehouses' },
  { key: 'alerts',          labelKey: 'nav.alerts' },
  { key: 'transfer',        labelKey: 'nav.transfer' },
  { key: 'returns',         labelKey: 'nav.returns' },
  { key: 'retail',          labelKey: 'nav.retail' },
  { key: 'gift-approvals',  labelKey: 'nav.giftApprovals' },
  { key: 'aftersale',       labelKey: 'nav.aftersale' },
  { key: 'reports',         labelKey: 'nav.reports' },
  // 合作伙伴
  { key: 'suppliers',       labelKey: 'nav.suppliers' },
  { key: 'dealers',         labelKey: 'nav.dealers' },
  { key: 'stores',          labelKey: 'nav.stores' },
]