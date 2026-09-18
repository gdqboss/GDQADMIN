<script setup>
import { ref, computed, onMounted, watch, onBeforeRouteUpdate } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '../stores/user'
import { useWecomStore } from '../stores/wecom'
import { useI18n } from 'vue-i18n'
import { ROLES } from '../constants/roles.js'
import { systemSettings } from '../stores/system'
import api, { menuApi } from '../services/api.js'

const emit = defineEmits(['close'])
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const wecomStore = useWecomStore()
const { t } = useI18n()

// 左上角品牌名：协会独立站（settings.single_login_entry=true，如 macau）取 server_profiles
// 的站点名，避免显示语言包里硬编码的「智能商業系統」；其它服务器维持原行为
const brandName = computed(() => (systemSettings.brand_from_profile && systemSettings.site_name)
  ? systemSettings.site_name
  : t('system.name'))
const brandInitial = computed(() => (systemSettings.brand_from_profile && systemSettings.site_name)
  ? String(systemSettings.site_name).trim().charAt(0)
  : t('system.logoInitial'))
// 角标：优先用 server_profiles.site_logo（协会印章），加载失败或未配置则回落到文字
const logoFailed = ref(false)
const brandLogo = computed(() => (systemSettings.brand_from_profile ? systemSettings.site_logo : ''))

// 库存预警数量
const alertCount = ref(0)
// 赠送审批待审批数量
const giftApprovalCount = ref(0)

// 数据库菜单配置（从后端加载）
const dbMenuConfig = ref([])

// 服务器允许的模块列表（从后端 public-settings 加载）
const serverModules = ref([])

// 加载服务器模块列表
async function loadServerModules() {
  try {
    const res = await api.get('/public-settings')
    if (res.code === 0 && res.data && res.data.modules) {
      serverModules.value = res.data.modules
      // 2026-08-15 江小鱼 — 派生 'association' 标记:
      // profile 7 (macau 中医学会) 勾选的是具体 module_key (association-academic 等),
      // 但 sidebar group 用 'association' 单一 key 控制整组显示. 把任意 association-*
      // 视为 association 整组可见.
      const hasAnyAssoc = serverModules.value.some(m => m.startsWith('association-'))
      if (hasAnyAssoc && !serverModules.value.includes('association')) {
        serverModules.value = [...serverModules.value, 'association']
      }
    }
  } catch { /* ignore */ }
}

// 菜单显隐配置（key=菜单key, value=visible）
const menuVisibility = ref({})

// 展开的分组
const expandedGroups = ref(['dashboard'])

// 加载数据库菜单配置
async function loadMenuConfig() {
  try {
    const role = userStore.userRole || ROLES.ADMIN
    const res = await menuApi.getMenuConfig(role)
    if (res.code === 0 && Array.isArray(res.data)) {
      dbMenuConfig.value = res.data
      menuVisibility.value = {}
      res.data.forEach(item => {
        menuVisibility.value[item.menu_key] = item.visible === 1
      })
    }
  } catch {
    // 加载失败，使用后端硬编码默认值
  }
}

onMounted(async () => {
  await loadServerModules()
  await loadMenuConfig()
  try {
    const res = await api.get('/users/roles')
    if (res.code === 0 && Array.isArray(res.data)) {
      const map = {}
      res.data.forEach(r => {
        if (r.name) {
          let perms = r.permissions
          if (typeof perms === 'string') {
            try { perms = JSON.parse(perms) } catch { perms = [] }
          }
          if (Array.isArray(perms)) {
            map[r.name] = perms
          }
        }
      })
    }
  } catch { /* ignore */ }

  try {
    const alertRes = await api.get('/stock-alerts?handled=false')
    if (alertRes.code === 0 && Array.isArray(alertRes.data)) {
      alertCount.value = alertRes.data.length
    }
  } catch { /* ignore */ }

  try {
    const giftRes = await api.get('/gift-approvals/pending-count')
    if (giftRes.code === 0) {
      giftApprovalCount.value = giftRes.data.count
    }
  } catch { /* ignore */ }
})

// 路由变化时：自动展开对应的一级菜单（二级菜单激活时）
watch(() => route.path, (path) => {
  if (!path) return
  for (const group of filteredGroups.value) {
    if (!group.children || group.children.length === 0) continue
    const hasActiveChild = group.children.some(child => {
      if (!child.to) return false
      if (child.to === '/') return path === '/'
      return path.startsWith(child.to)
    })
    if (hasActiveChild && !expandedGroups.value.includes(group.key)) {
      expandedGroups.value.push(group.key)
    }
  }
}, { immediate: false })

onMounted(() => {
  // 初始化时自动展开对应的一级菜单
  const path = route.path
  if (path) {
    for (const group of filteredGroups.value) {
      if (!group.children || group.children.length === 0) continue
      const hasActiveChild = group.children.some(child => {
        if (!child.to) return false
        if (child.to === '/') return path === '/'
        return path.startsWith(child.to)
      })
      if (hasActiveChild && !expandedGroups.value.includes(group.key)) {
        expandedGroups.value.push(group.key)
      }
    }
  }
})

// 是否显示该菜单项（权限驱动）
function canAccess(permKey) {
  if (!permKey) return true
  // 数据库菜单配置优先（可配置显隐）
  if (dbMenuConfig.value.length > 0) {
    // 尝试用 permKey 匹配 menu_key
    const vis = menuVisibility.value[permKey]
    if (vis !== undefined) return vis
    return userStore.canAccess(permKey)
  }
  // 无数据库配置时，用权限数组判断
  return userStore.canAccess(permKey)
}

// 菜单排序映射
const navOrder = computed(() => {
  const m = {}
  dbMenuConfig.value.forEach(item => {
    m[item.menu_key] = item.position
  })
  return m
})

// 二级菜单分组定义（key = 权限 key，与 rbac_permissions.name 一一对应）
const menuGroups = computed(() => [
  {
    key: 'dashboard',
    icon: 'dashboard',
    label: t('nav.dashboard'),
    to: '/',
    children: []
  },
  {
    key: 'ai-classroom',
    icon: 'school',
    label: t('nav.aiClassroom'),
    to: '/ai-classroom',
    children: []
  },
  {
    key: 'ai-hr',
    icon: 'group_add',
    label: t('nav.aiHrRecruitment'),
    to: '/ai-hr/reports',
    permission: 'ai_hr:read',
    children: [
      { key: 'ai_hr:read', label: t('nav.aiHRReports') || '招聘报告', to: '/ai-hr/reports' },
      { key: 'ai_hr:write', label: t('nav.aiHRJobPresets') || '岗位配置', to: '/ai-hr/job-presets' },
    ]
  },
  {
    // AI 数据中心 (agent-memory MVP — 2026-09-18 江小鱼立)
    key: 'agent-memory',
    icon: 'hub',
    label: 'AI 数据中心',
    to: '/agent-memory',
    children: [
      { key: 'agent-memory:overview', label: '总览', to: '/agent-memory' },
      { key: 'agent-memory:profiles', label: '员工 AI 画像', to: '/agent-memory/profiles' },
      { key: 'agent-memory:wisdom', label: '知识财富', to: '/agent-memory/wisdom' },
      { key: 'agent-memory:insights', label: 'AI 洞察报告', to: '/agent-memory/insights' },
      { key: 'secure-knowledge', label: '机密配方 AI', to: '/secure-knowledge' },
    ]
  },
  {
    key: 'operations',
    icon: 'business',
    label: t('nav.operations'),
    to: null,
    children: [
      { key: 'task:read', label: t('tasks.title'), to: '/tasks' },
      { key: 'work_log:read', label: t('logs.workLog'), to: '/logs/work-logs' },
      { key: 'attendance:manage', label: t('nav.qaAttendance'), to: '/oa/attendance' },
      { key: 'attendance:today', label: t('nav.attendanceToday'), to: '/oa/attendance-today', permission: 'attendance:manage' },
      { key: 'attendance:summary', label: t('nav.attendanceSummary'), to: '/oa/attendance-summary' },
      { key: 'attendance:trip', label: t('nav.tripRecords'), to: '/oa/attendance-trip-records', permission: 'attendance:view' },
      { key: 'schedule:view', label: t('nav.scheduleCalendar'), to: '/oa/schedule' },
      { key: 'attendance:rules', label: t('nav.attendanceRules'), to: '/oa/attendance-rules' },
      { key: 'approval:read', label: t('nav.approvals'), to: '/approvals' },
    ]
  },
  {
    key: 'inventory',
    icon: 'inventory_2',
    label: t('nav.inventory'),
    to: null,
    children: [
      { key: 'product:write', label: t('nav.products'), to: '/products' },
      { key: 'inventory:inout', label: t('nav.inout'), to: '/in-out' },
      { key: 'warehouse:write', label: t('nav.warehouses'), to: '/warehouses' },
      { key: 'qrcode:write', label: t('nav.qrcode'), to: '/qrcode' },
      { key: 'stock:read', label: t('nav.alerts'), to: '/alerts', badge: alertCount.value },
      { key: 'transfer:read', label: t('nav.transfer'), to: '/transfer' },
      { key: 'inventory:return', label: t('nav.returnRecords'), to: '/inventory/returns' },
      { key: 'material_purchase:read', label: t('nav.materialPurchase'), to: '/materials/purchase' },
      { key: 'material_consumption:read', label: t('nav.materialConsume'), to: '/materials/consume' },
      { key: 'material_purchase:read', label: t('nav.materialCategories'), to: '/materials/categories' },
      { key: 'material_item:read', label: t('nav.materialItems'), to: '/materials/items' },
      { key: 'material_stocktake:read', label: t('nav.materialStocktake'), to: '/materials/stocktake' },
    ]
  },
  {
    key: 'finance',
    icon: 'payments',
    label: t('nav.financeCenter'),
    to: null,
    children: [
      { key: 'finance:read', label: t('nav.financeOverview'), to: '/finance' },
      { key: 'retail:write', label: t('nav.retail'), to: '/retail' },
      { key: 'finance:read', label: t('nav.invoices'), to: '/finance/invoices' },
    ]
  },
  {
    key: 'sales',
    icon: 'shopping_cart',
    label: t('nav.sales'),
    to: null,
    children: [
      { key: 'order:read', label: t('nav.orders'), to: '/orders' },
      { key: 'qrcode:write', label: t('nav.scanSale'), to: '/qrcode' },
      { key: 'aftersale:write', label: t('nav.aftersale'), to: '/aftersale' },
    ]
  },
  {
    key: 'partners',
    icon: 'handshake',
    label: t('nav.partners'),
    to: null,
    children: [
      { key: 'supplier:write', label: t('nav.suppliers'), to: '/suppliers' },
      { key: 'dealer:write', label: t('nav.dealers'), to: '/dealers' },
      { key: 'store:write', label: t('nav.stores'), to: '/stores' },
    ]
  },
  {
    key: 'growth',
    icon: 'trending_up',
    label: t('nav.growth'),
    to: null,
    children: [
      { key: 'bi:excel', label: t('nav.excelAnalyzer'), to: '/excel-analyzer' },
      { key: 'bi:report', label: t('nav.reportManage'), to: '/excel-report-manage' },
      { key: 'bi:excel', label: t('nav.storeSales'), to: '/store-sales' },
      { key: 'bi:excel', label: t('nav.importRecords'), to: '/import-records' },
      { key: 'referral:read', label: t('nav.referral'), to: '/referral' },
      { key: 'report:read', label: t('nav.reports'), to: '/reports' },
    ]
  },
  {
    key: 'restaurant',
    icon: 'restaurant',
    label: '餐饮管理',
    to: null,
    children: [
      { key: 'restaurant:read', label: '餐饮仪表盘', to: '/restaurant' },
      { key: 'restaurant:write', label: '桌台管理', to: '/restaurant/tables' },
      { key: 'restaurant:write', label: '菜品管理', to: '/restaurant/dishes' },
      { key: 'restaurant:read', label: '堂食订单', to: '/restaurant/dine-orders' },
      { key: 'restaurant:read', label: '外卖订单', to: '/restaurant/takeout' },
      { key: 'restaurant:read', label: '预订管理', to: '/restaurant/reservations' },
      { key: 'restaurant:read', label: '排队叫号', to: '/restaurant/queue' },
      { key: 'restaurant:write', label: '收银管理', to: '/restaurant/cashier' },
    ]
  },
  {
    key: 'hotel',
    icon: 'hotel',
    label: '酒店管理',
    to: null,
    children: [
      { key: 'hotel:read', label: '酒店仪表盘', to: '/hotel' },
      { key: 'hotel:write', label: '房型管理', to: '/hotel/room-types' },
      { key: 'hotel:write', label: '价格日历', to: '/hotel/price-calendar' },
      { key: 'hotel:read', label: '酒店订单', to: '/hotel/orders' },
      { key: 'hotel:read', label: '评价管理', to: '/hotel/reviews' },
    ]
  },
  {
    key: 'mall',
    icon: 'shopping_bag',
    label: '商城',
    to: null,
    children: [
      { key: 'mall:score', label: '积分商品', to: '/score-products' },
      { key: 'order:read', label: '积分订单', to: '/score-orders' },
      { key: 'order:read', label: '优惠券管理', to: '/coupon-manage' },
      { key: 'logistics:read', label: '物流管理', to: '/logistics' },
      { key: 'articles:read', label: '文章管理', to: '/articles' },
      { key: 'yuyue:read', label: '预约服务', to: '/yuyue' },
      { key: 'kefu:read', label: '客服消息', to: '/kefu' },
    ]
  },
  // 2026-08-15 江小鱼 — SGP 是源头 — 协会后台 10 模块入口 (macau profile 7 主用)
  // macau DB 勾选的是具体 association-* module_key (academic/activities/...),
  // 这里用单一 'association' group 标记控制整组显示. loadServerModules 派生这条.
  // 2026-09-08 江小鱼: 移到 mall 之后、system 之前. 业务模块群 (运营/库存/财务/增长/商城/协会) → 系统管理
  {
    key: 'association',
    icon: 'handshake',
    label: '協會',
    to: null,
    moduleKeys: ['association'],
    children: [
      { key: 'association-info:read', label: '協會介紹', to: '/association' },
      { key: 'association-announcements:read', label: '信息發佈', to: '/association-announcements' },
      { key: 'association-activities:read', label: '活動報名', to: '/association-activities' },
      { key: 'association-cards:read', label: '會員名片', to: '/association-cards' },
      { key: 'association-members:read', label: '會員管理', to: '/association-members' },
      { key: 'association-academic:read', label: '學術動態', to: '/association-academic' },
      { key: 'association-journals:read', label: '期刊管理', to: '/association-journals' },
      { key: 'association-downloads:read', label: '資料下載', to: '/association-downloads' },
      { key: 'association-org:read', label: '組織架構', to: '/association-org' },
      { key: 'association-inquiries:read', label: '在線咨詢', to: '/association-inquiries' },
      { key: 'association-membership:read', label: '入會申請', to: '/association-membership' },
    ]
  },
  {
    key: 'system',
    icon: 'settings',
    label: t('nav.systemManagement'),
    to: null,
    children: [
      { key: 'system:config', label: t('nav.settingsIndex'), to: '/settings' },
      { key: 'user:write', label: t('nav.userManagement'), to: '/settings/users' },
      { key: 'role:write', label: t('nav.roleManageIndex'), to: '/settings/roles' },
      { key: 'system:config', label: t('nav.serverProfiles'), to: '/settings/server-profiles' },
    ]
  },
])

// 路由路径 → module_key 映射（用于按服务器模块过滤）
// module_key 格式与 PROFILE_MODULES / server_modules.module_key 保持一致
const routeToModule = {
  '/': 'dashboard',
  '/ai-classroom': 'ai-classroom',
  '/ai-hr': 'ai-hr',
  '/ai-hr/reports': 'ai-hr',
  '/ai-hr/job-presets': 'ai-hr',
  // AI 数据中心 (agent-memory MVP — 2026-09-18 江小鱼立)
  '/agent-memory': 'agent-memory',
  '/agent-memory/profiles': 'agent-memory',
  '/agent-memory/wisdom': 'agent-memory',
  '/agent-memory/insights': 'agent-memory',
  '/secure-knowledge': 'agent-memory',
  '/tasks': 'tasks',
  '/logs/work-logs': 'tasks',
  '/logs/visit-logs': 'tasks',
  '/oa/attendance': 'oa',
  '/approvals': 'oa',
  '/products': 'products',
  '/in-out': 'in-out',
  '/warehouses': 'warehouses',
  '/alerts': 'alerts',
  '/transfer': 'transfer',
  '/inventory/returns': 'returns',
  '/finance': 'finance',
  '/retail': 'retail',
  '/finance/invoices': 'finance',
  '/orders': 'orders',
  '/qrcode': 'qrcode',
  '/aftersale': 'aftersale',
  '/suppliers': 'suppliers',
  '/dealers': 'dealers',
  '/stores': 'stores',
  '/excel-analyzer': 'excel-analyzer',
  '/referral': 'referral',
  '/reports': 'reports',
  '/restaurant': 'restaurant',
  '/restaurant/tables': 'restaurant',
  '/restaurant/dishes': 'restaurant',
  '/restaurant/dine-orders': 'restaurant',
  '/restaurant/takeout': 'restaurant',
  '/restaurant/reservations': 'restaurant',
  '/restaurant/queue': 'restaurant',
  '/restaurant/cashier': 'restaurant',
  '/hotel': 'hotel',
  '/hotel/room-types': 'hotel',
  '/hotel/price-calendar': 'hotel',
  '/hotel/orders': 'hotel',
  '/hotel/reviews': 'hotel',
  '/score-products': 'score_shop',
  '/score-orders': 'score_shop',
  '/coupon-manage': 'coupon',
  '/logistics': 'logistics',
  '/logistics/express': 'logistics',
  '/logistics/templates': 'logistics',
  '/logistics/channels': 'logistics',
  '/articles': 'article',
  '/yuyue': 'yuyue',
  '/kefu': 'kefu',
  // 2026-08-15 江小鱼 — SGP 协会 10 路由 → module_key 映射 (macau profile 7 派生显示)
  '/association': 'association-info',
  '/association-announcements': 'association-announcements',
  '/association-activities': 'association-activities',
  '/association-cards': 'association-cards',
  '/association-members': 'association-members',
  '/association-academic': 'association-academic',
  '/association-journals': 'association-journals',
  '/association-downloads': 'association-downloads',
  '/association-org': 'association-org',
  '/association-inquiries': 'association-inquiries',
  '/association-membership': 'association-membership',
  '/settings': 'settings',
  '/settings/users': 'users',
  '/settings/roles': 'roles',
  '/settings/server-profiles': 'server_profiles',
}

// 过滤后的菜单分组
const filteredGroups = computed(() => {
  const list = menuGroups.value
    .map(group => {
      // 工作台：登录用户都可见
      if (group.key === 'dashboard') {
        return { ...group, label: t('nav.dashboard') }
      }
      // 独立一级菜单（如 AI课堂）：按 module_key 过滤
      if (!group.children || group.children.length === 0) {
        const mod = routeToModule[group.to]
        // modules 为空（北京等无 server_profiles 表的服务器）→ 不过滤，显示全部
        // modules 非空 → 按模块过滤
        if (mod && serverModules.value.length > 0 && !serverModules.value.includes(mod)) {
          return null
        }
        return { ...group, label: group.label }
      }
      // 有 children 的分组：按模块过滤子菜单
      const filteredChildren = group.children
        .filter(child => {
          if (!canAccess(child.key)) return false
          const mod = routeToModule[child.to]
          // server_profiles 永远不在被管理方显示（无论 modules 是否为空）
          if (mod === 'server_profiles' && !serverModules.value.includes('server_profiles')) {
            return false
          }
          // modules 为空 → 不过滤，显示全部；modules 非空 → 按模块过滤
          if (mod && serverModules.value.length > 0 && !serverModules.value.includes(mod)) {
            return false
          }
          return true
        })
        .map(child => ({ ...child }))
      // 规则：有children但全部被过滤则隐藏；无children（独立一级菜单）始终显示
      if (group.children.length > 0 && filteredChildren.length === 0) return null
      return { ...group, children: filteredChildren }
    })
    .filter(Boolean)

  // 协会独立站（macau）：把与协会无关的通用分组统一收进一个【默认折叠】的「工作管理」
  //   只保留 工作台 / 協會 / 系統管理 在一级；其余（AI课堂、运营、库存、财务、销售、
  //   伙伴、增长、餐饮、酒店、商城…）全部并到「工作管理」下，客户真要用再逐个接出来
  //   门控 systemSettings.brand_from_profile（= settings.single_login_entry，只写在 macau 库）
  if (!systemSettings.brand_from_profile) return list

  const KEEP = ['dashboard', 'association', 'system']
  const keep = {}
  const merged = []
  for (const g of list) {
    if (KEEP.includes(g.key)) { keep[g.key] = g; continue }
    if (g.children && g.children.length) merged.push(...g.children)
    else merged.push({ key: g.key, label: g.label, to: g.to, icon: g.icon, badge: g.badge })
  }
  // 去重：多个分组里有重复路由（如 /qrcode），以先出现者为准
  const seen = new Set()
  const children = []
  for (const c of merged) {
    if (!c.to || seen.has(c.to)) continue
    seen.add(c.to)
    children.push({ ...c, key: c.to })
  }
  const out = []
  if (keep.dashboard) out.push(keep.dashboard)
  if (keep.association) out.push(keep.association)
  if (children.length) out.push({ key: 'work-manage', icon: 'work', label: '工作管理', to: null, children })
  if (keep.system) out.push(keep.system)
  return out
})

// 检查一级分组是否有任何子菜单激活（用于高亮父级）
function groupHasActiveChild(group) {
  if (!group.children || group.children.length === 0) return false
  return group.children.some(child => isActive(child.to))
}

function toggleGroup(key) {
  if (expandedGroups.value.includes(key)) {
    expandedGroups.value = expandedGroups.value.filter(k => k !== key)
  } else {
    expandedGroups.value.push(key)
  }
}

function isActive(to) {
  if (!to) return false
  if (to === '/') return route.path === '/'
  // 系统设置精确匹配，避免子页面也匹配父级
  if (to === '/settings') return route.path === '/settings'
  return route.path.startsWith(to)
}

function handleLogout() {
  userStore.logout()
  router.push('/login')
}
</script>

<template>
  <aside class="w-64 bg-sidebar text-white flex flex-col shrink-0 h-screen">
    <!-- Logo -->
    <div class="h-14 sm:h-16 flex items-center gap-2 sm:gap-3 px-4 sm:px-6 bg-sidebar-header">
      <div
        class="size-7 sm:size-8 rounded flex items-center justify-center font-bold text-lg sm:text-xl overflow-hidden shrink-0"
        :class="(brandLogo && !logoFailed) ? 'bg-white p-0.5 shadow-sm' : 'bg-primary text-white'"
      >
        <img v-if="brandLogo && !logoFailed" :src="brandLogo" alt="" class="w-full h-full object-contain" @error="logoFailed = true">
        <template v-else>{{ brandInitial }}</template>
      </div>
      <h1 class="text-base sm:text-lg font-bold tracking-wide truncate">{{ brandName }}</h1>
      <button @click="$emit('close')" class="lg:hidden ml-auto text-gray-400 hover:text-white">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>
    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-3 sm:py-4 custom-scrollbar">
      <ul class="flex flex-col gap-1 px-2">
        <li v-for="group in filteredGroups" :key="group.key">
          <!-- 一级菜单（可点击展开/折叠，或者直接跳转） -->
          <div
            v-if="group.children.length === 0"
          >
            <router-link
              :to="group.to"
              @click="$emit('close')"
              :class="[
                'flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 rounded transition-colors text-sm sm:text-base',
                'bg-primary text-white font-medium hover:bg-primary-hover'
              ]"
            >
              <span class="material-symbols-outlined text-[18px] sm:text-[20px]">{{ group.icon }}</span>
              <span>{{ group.label }}</span>
            </router-link>
          </div>
          <div v-else>
            <!-- 可展开分组 -->
            <div
              @click="toggleGroup(group.key)"
              :class="[
                'flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 rounded transition-colors text-sm sm:text-base cursor-pointer',
                'bg-primary text-white font-medium hover:bg-primary-hover'
              ]"
            >
              <span class="material-symbols-outlined text-[18px] sm:text-[20px]">{{ group.icon }}</span>
              <span class="flex-1">{{ group.label }}</span>
              <span v-if="group.children && group.children.length > 0" class="material-symbols-outlined text-[16px] transition-transform"
                :class="expandedGroups.includes(group.key) ? 'rotate-90' : ''">
                chevron_right
              </span>
            </div>
            <!-- 二级菜单 -->
            <ul v-if="expandedGroups.includes(group.key)" class="ml-4 sm:ml-6 mt-1 flex flex-col gap-0.5">
              <li v-for="child in group.children" :key="child.key">
                <router-link
                  :to="child.to"
                  @click="$emit('close')"
                  :class="[
                    'flex items-center gap-2 px-3 py-2 rounded transition-colors text-xs sm:text-sm',
                    isActive(child.to) ? 'bg-primary text-white font-medium' : 'text-gray-400 hover:text-white hover:bg-[#1890ff]/10'
                  ]"
                >
                  <span>{{ child.label }}</span>
                  <span v-if="child.badge" class="ml-auto bg-danger text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">{{ child.badge }}</span>
                </router-link>
              </li>
            </ul>
          </div>
        </li>
      </ul>
    </nav>
    <!-- User -->
    <div class="p-3 sm:p-4 border-t border-gray-700">
      <div class="flex items-center gap-2 sm:gap-3">
        <div class="size-7 sm:size-8 rounded-full bg-primary/30 flex items-center justify-center text-xs sm:text-sm font-bold">
          {{ userStore.userName?.charAt(0) || 'A' }}
        </div>
        <div class="flex flex-col flex-1 min-w-0">
          <span class="text-xs sm:text-sm font-medium text-white truncate">{{ userStore.userName || t('system.admin') }}</span>
          <span class="text-[10px] sm:text-xs text-gray-400 truncate">{{ userStore.user?.email || 'admin@caimeite.com' }}</span>
        </div>
        <button @click="handleLogout" class="text-gray-400 hover:text-white transition-colors" :title="t('common.logout')">
          <span class="material-symbols-outlined text-[18px] sm:text-[20px]">logout</span>
        </button>
      </div>
    </div>
  </aside>
</template>