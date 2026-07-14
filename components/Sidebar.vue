<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '../stores/user'
import { useWecomStore } from '../stores/wecom'
import { systemSettings } from '../stores/system.js'
import { useI18n } from 'vue-i18n'
import { ROLES } from '../constants/roles.js'
import api, { menuApi } from '../services/api.js'

const emit = defineEmits(['close'])
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const wecomStore = useWecomStore()
const { t } = useI18n()

// 库存预警数量
const alertCount = ref(0)
// 赠送审批待审批数量
const giftApprovalCount = ref(0)

// 数据库菜单配置（从后端加载）
const dbMenuConfig = ref([])

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
        // 兼容后端两种格式：true/false（布尔）或 1/0（数字）→ 都归一为布尔
        menuVisibility.value[item.menu_key] = Boolean(item.visible)
      })
    }
  } catch {
    // 加载失败，使用后端硬编码默认值
  }
}

onMounted(async () => {
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
  for (const group of menuGroups.value) {
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
    for (const group of menuGroups.value) {
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
// 用模块原则：每个 group 必须显式声明 moduleKeys（绑定到 server_modules 里的 module_key）
// 过滤逻辑：group.moduleKeys 至少一个在 enabledModules → 显示；否则隐藏
const menuGroups = computed(() => [
  {
    key: 'dashboard:view',
    icon: 'dashboard',
    label: t('nav.dashboard'),
    to: '/',
    moduleKeys: ['dashboard'],
    children: []
  },
  {
    key: 'ai-classroom',
    icon: 'school',
    label: t('nav.aiClassroom'),
    to: '/ai-classroom',
    moduleKeys: ['ai-classroom'],
    children: []
  },
  {
    key: 'operations',
    icon: 'business',
    label: t('nav.operations'),
    to: null,
    moduleKeys: ['oa', 'tasks'],
    children: [
      { key: 'task:read', label: t('tasks.title'), to: '/tasks' },
      { key: 'work_log:read', label: t('logs.workLog'), to: '/logs/work-logs' },
      { key: 'attendance:manage', label: t('nav.qaAttendance'), to: '/oa/attendance' },
      { key: 'approval:read', label: t('nav.approvals'), to: '/approvals' },
    ]
  },
  {
    key: 'inventory',
    icon: 'inventory_2',
    label: t('nav.inventory'),
    to: null,
    moduleKeys: ['products', 'in-out', 'warehouses', 'qrcode', 'alerts', 'transfer', 'returns'],
    children: [
      { key: 'product:write', label: t('nav.products'), to: '/products' },
      { key: 'inventory:inout', label: t('nav.inout'), to: '/in-out' },
      { key: 'warehouse:write', label: t('nav.warehouses'), to: '/warehouses' },
      { key: 'qrcode:write', label: t('nav.qrcode'), to: '/qrcode' },
      { key: 'stock:read', label: t('nav.alerts'), to: '/alerts', badge: alertCount.value },
      { key: 'transfer:read', label: t('nav.transfer'), to: '/transfer' },
      { key: 'inventory:return', label: t('nav.returnRecords'), to: '/inventory/returns' },
    ]
  },
  {
    key: 'finance',
    icon: 'payments',
    label: t('nav.financeCenter'),
    to: null,
    moduleKeys: ['finance', 'retail'],
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
    moduleKeys: ['orders', 'aftersale'],
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
    moduleKeys: ['suppliers', 'dealers', 'stores'],
    children: [
      { key: 'supplier:write', label: t('nav.suppliers'), to: '/suppliers' },
      { key: 'dealer:write', label: t('nav.dealers'), to: '/dealers' },
      { key: 'store:write', label: t('nav.stores'), to: '/stores' },
    ]
  },
  {
    key: 'preorder',
    icon: 'inventory_2',
    label: t('nav.preorder'),
    to: null,
    moduleKeys: ['preorder'],
    children: [
      { key: 'preorder:create', label: t('nav.preorderCreate'), to: '/orders/create' },
      { key: 'preorder:aggregate', label: t('nav.preorderSummary'), to: '/preorder/summary' },
      { key: 'preorder:demo', label: t('nav.preorderStockDemo'), to: '/preorder/stock-demo' },
      { key: 'preorder:read', label: t('nav.preorderUpcoming'), to: '/preorder/upcoming' },
    ]
  },
  {
    key: 'growth',
    icon: 'trending_up',
    label: t('nav.growth'),
    to: null,
    moduleKeys: ['excel-analyzer', 'reports', 'referral'],
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
    label: t('nav.restaurant'),
    to: null,
    moduleKeys: ['restaurant'],
    children: [
      { key: 'restaurant:read', label: t('nav.restaurantDashboard'), to: '/restaurant' },
      { key: 'restaurant:write', label: t('nav.restaurantTables'), to: '/restaurant/tables' },
      { key: 'restaurant:write', label: t('nav.restaurantDishes'), to: '/restaurant/dishes' },
      { key: 'restaurant:read', label: t('nav.dineOrders'), to: '/restaurant/dine-orders' },
      { key: 'restaurant:read', label: t('nav.takeoutOrders'), to: '/restaurant/takeout' },
      { key: 'restaurant:read', label: t('nav.reservations'), to: '/restaurant/reservations' },
      { key: 'restaurant:read', label: t('nav.queue'), to: '/restaurant/queue' },
      { key: 'restaurant:write', label: t('nav.cashier'), to: '/restaurant/cashier' },
    ]
  },
  {
    key: 'hotel',
    icon: 'hotel',
    label: t('nav.hotel'),
    to: null,
    moduleKeys: ['hotel'],
    children: [
      { key: 'hotel:read', label: t('nav.hotelDashboard'), to: '/hotel' },
      { key: 'hotel:write', label: t('nav.roomTypes'), to: '/hotel/room-types' },
      { key: 'hotel:write', label: t('nav.priceCalendar'), to: '/hotel/price-calendar' },
      { key: 'hotel:read', label: t('nav.hotelOrders'), to: '/hotel/orders' },
      { key: 'hotel:read', label: t('nav.hotelReviews'), to: '/hotel/reviews' },
    ]
  },
  {
    key: 'mall',
    icon: 'shopping_bag',
    label: t('nav.mall'),
    to: null,
    moduleKeys: ['mall'],
    children: [
      { key: 'mall:score', label: t('nav.scoreProducts'), to: '/score-products' },
      { key: 'order:read', label: t('nav.scoreOrders'), to: '/score-orders' },
      { key: 'order:read', label: t('nav.coupons'), to: '/coupon-manage' },
      { key: 'logistics:read', label: t('nav.logistics'), to: '/logistics' },
      { key: 'articles:read', label: t('nav.articles'), to: '/articles' },
      { key: 'yuyue:read', label: t('nav.yuyue'), to: '/yuyue' },
      { key: 'kefu:read', label: t('nav.kefu'), to: '/kefu' },
    ]
  },
  {
    key: 'hqh5',
    icon: 'location_city',
    label: t('nav.hqh5Center'),
    to: null,
    moduleKeys: ['hqh5'],
    children: [
      { key: 'hqh5:read',  label: t('nav.hqh5Dashboard'),  to: '/hqh5/backend-dashboard' },
      { key: 'hqh5:read',  label: t('nav.hqh5Approval'),   to: '/hqh5/approval-list' },
      { key: 'hqh5:read',  label: t('nav.hqh5Crm'),        to: '/hqh5/crm-dashboard' },
      { key: 'hqh5:write', label: t('nav.hqh5Article'),    to: '/hqh5/article-publish' },
      { key: 'hqh5:write', label: t('nav.hqh5Notification'), to: '/hqh5/notification-push' },
    ]
  },
  {
    key: 'minip',
    icon: 'smartphone',
    label: '小程序管理',
    to: null,
    moduleKeys: ['minip', 'banners'],
    children: [
      { key: 'banners:write', label: '小程序轮播图',   to: '/settings/minip-banners' },
      { key: 'minip:write',   label: '小程序业务模块', to: '/settings/minip-modules',  moduleKey: 'minip' },
      { key: 'minip:write',   label: '小程序活动',     to: '/settings/minip-activities', moduleKey: 'minip' },
      { key: 'minip:write',   label: '入会申请审核',   to: '/settings/minip-applications', moduleKey: 'minip' },
    ]
  },
  {
    key: 'system',
    icon: 'settings',
    label: t('nav.systemManagement'),
    to: null,
    moduleKeys: ['settings', 'server_profiles', 'users', 'roles'],
    children: [
      { key: 'system:config', label: t('nav.settingsIndex'), to: '/settings' },
      { key: 'user:write', label: t('nav.userManagement'), to: '/settings/users' },
      { key: 'role:write', label: t('nav.roleManageIndex'), to: '/settings/roles' },
      // 目标服务器管理：仅启用 server_profiles 模块的服务器显示（北京/3号仓库无）
      { key: 'system:config', label: t('nav.serverProfiles'), to: '/settings/server-profiles', moduleKey: 'server_profiles' },
    ]
  },
  {
    key: 'temple',
    icon: 'temple_buddhist',
    label: t('nav.temple'),
    to: null,
    moduleKeys: ['temple'],
    children: [
      { key: 'temple:read', label: t('nav.templeDashboard'), to: '/temple' },
      { key: 'temple:read', label: t('nav.templeCaskets'), to: '/temple/caskets' },
      { key: 'temple:read', label: t('nav.templeAncestors'), to: '/temple/ancestors' },
      { key: 'temple:read', label: t('nav.templeOrders'), to: '/temple/orders' },
      { key: 'temple:read', label: t('nav.templeDonations'), to: '/temple/donations' },
      { key: 'temple:read', label: t('nav.templeMonks'), to: '/temple/monks' },
    ]
  },
])

// 过滤后的菜单分组（用模块原则：模块驱动 + 权限补充）
// 原则：
// 1. 一级菜单强制走 moduleKeys ∩ enabledModules（必须命中至少一个才显示）
// 2. enabledModules 为空（loadSystemSettings 失败/未完成）= 默认全部隐藏，**避免出现"全显示"bug**
//    唯一例外：admin 角色在 modules 未加载完成时仍展示全部菜单（波哥铁律：admin 必须有一切权限）
// 3. 二级菜单的 canAccess 只用于权限细化（管理员/仓管/店长差异），不再作 menu_key fallback
const filteredGroups = computed(() => {
  const enabledModules = systemSettings.modules || []
  const enabledSet = new Set(enabledModules)
  const modulesLoaded = enabledModules.length > 0
  // admin 短路：必须有一切权限（无 modules/无 permission 都不能影响 admin 看见全部菜单）
  const isAdmin = userStore.isAdmin.value

  return menuGroups.value
    .map(group => {
      // 一级菜单模块过滤（强制）：未加载到 enabledModules → 不显示（避免乱显示）
      if (!Array.isArray(group.moduleKeys) || group.moduleKeys.length === 0) {
        return null
      }
      // modules 未加载：仅 admin 可见，其他角色全部隐藏
      if (!modulesLoaded) {
        if (!isAdmin) return null
      } else {
        const hasEnabled = group.moduleKeys.some(k => enabledSet.has(k))
        if (!hasEnabled) return null
      }

      // 独立一级菜单（无 children）— 模块通过即显示
      if (!group.children || group.children.length === 0) {
        return { ...group, label: group.label }
      }
      // 有 children 的分组：按子菜单权限 + 子菜单 moduleKey 双重过滤
      const filteredChildren = group.children
        .filter(child => {
          // 权限过滤：admin 永远过；非 admin 走 userStore.canAccess
          if (!canAccess(child.key)) return false
          // 子菜单 moduleKey 过滤：设了但当前服务器没启用 → 隐藏（admin 永远过）
          if (child.moduleKey && !isAdmin && !enabledSet.has(child.moduleKey)) return false
          return true
        })
        .map(child => ({ ...child }))
      // 规则：有 children 但全部被过滤 → 隐藏
      if (group.children.length > 0 && filteredChildren.length === 0) return null
      return { ...group, children: filteredChildren }
    })
    .filter(Boolean)
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
      <div class="size-7 sm:size-8 rounded bg-primary flex items-center justify-center text-white font-bold text-lg sm:text-xl">{{ t('system.logoInitial') }}</div>
      <h1 class="text-base sm:text-lg font-bold tracking-wide truncate">{{ t('system.name') }}</h1>
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