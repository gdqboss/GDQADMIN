<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '../stores/user'
import { useWecomStore } from '../stores/wecom'
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
        menuVisibility.value[item.menu_key] = item.visible === 1
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
const menuGroups = computed(() => [
  {
    key: 'dashboard:view',
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
    key: 'operations',
    icon: 'business',
    label: t('nav.operations'),
    to: null,
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
    key: 'preorder',
    icon: 'inventory_2',
    label: '产品预订',
    to: null,
    children: [
      { key: 'preorder:create', label: '新增订货单', to: '/orders/create' },
      { key: 'preorder:aggregate', label: '订货单汇总表', to: '/preorder/summary' },
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

// 过滤后的菜单分组（单一过滤源：canAccess）
const filteredGroups = computed(() => {
  return menuGroups.value
    .map(group => {
      // 独立一级菜单（无 children）— 全部按 group.key 权限过滤
      if (!group.children || group.children.length === 0) {
        if (!canAccess(group.key)) return null
        return { ...group, label: group.label }
      }
      // 有 children 的分组：按子菜单权限过滤
      const filteredChildren = group.children
        .filter(child => canAccess(child.key))
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