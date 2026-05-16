<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '../stores/user'
import { useWecomStore } from '../stores/wecom'
import { useI18n } from 'vue-i18n'
import { ROLES } from '../constants/roles.js'
import api from '../services/api.js'

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

// Pages accessible by each preset role (fallback if API fails)
const ROLE_PAGES = {
  admin: null, // null = all pages
  manager: ['dashboard','wecom','ai-automation','excel-analyzer','oa','finance','qrcode','products','in-out','warehouses','alerts','transfer','returns','retail','gift-approvals','aftersale','reports','suppliers','dealers','stores','tasks'],
  operator: ['dashboard','oa','gift-approvals','settings'],
  warehouse: ['dashboard','in-out','warehouses','alerts','products','qrcode','retail','suppliers','dealers','stores'],
  member: ['dashboard'],  // 兜底：只显示工作台
}

// Dynamic permissions loaded from the API, keyed by role name
const rolePermissions = ref({})

onMounted(async () => {
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
      rolePermissions.value = map
    }
  } catch {
    // API failed – canAccess will fall back to ROLE_PAGES
  }

  // 加载库存预警数量
  try {
    const alertRes = await api.get('/stock-alerts?handled=false')
    if (alertRes.code === 0 && Array.isArray(alertRes.data)) {
      alertCount.value = alertRes.data.length
    }
  } catch {
    // 加载失败，保持为0
  }

  // 加载赠送审批待审批数量
  try {
    const giftRes = await api.get('/gift-approvals/pending-count')
    if (giftRes.code === 0) {
      giftApprovalCount.value = giftRes.data.count
    }
  } catch {
    // 加载失败，保持为0
  }
})

function canAccess(pageKey) {
  const role = userStore.userRole
  if (role === 'admin') return true
  if (role === 'custom') {
    const perms = userStore.userPermissions
    if (!Array.isArray(perms)) return pageKey === 'dashboard'
    return perms.includes(pageKey)
  }
  // Use dynamic permissions from the API if available for this role
  if (Object.prototype.hasOwnProperty.call(rolePermissions.value, role)) {
    return rolePermissions.value[role].includes(pageKey)
  }
  // Fallback to hardcoded ROLE_PAGES
  const allowed = ROLE_PAGES[role]
  if (!allowed) return false
  return allowed.includes(pageKey)
}

const allNavItems = computed(() => [
  { key: 'dashboard',     label: t('nav.dashboard'),     icon: 'dashboard',      to: '/' },
  // { key: 'wecom',         label: t('nav.wecom'),         icon: 'chat',           to: '/wecom', badge: wecomStore.totalUnread || 0 },
  { key: 'ai-automation', label: t('nav.aiAutomation'),  icon: 'smart_toy',      to: '/ai-automation' },
  { key: 'excel-analyzer', label: t('nav.excelAnalyzer'),  icon: 'table_chart',   to: '/excel-analyzer' },
  { key: 'oa',            label: t('nav.oa'),            icon: 'badge',          to: '/oa' },
  { key: 'finance', label: t('nav.finance'),      icon: 'payments',       to: '/finance' },
  { key: 'tasks',         label: t('tasks.title'),       icon: 'task_alt',       to: '/tasks' },
  { key: 'qrcode',        label: t('nav.qrcode'),        icon: 'qr_code_2',      to: '/qrcode' },
  { key: 'products',      label: t('nav.products'),      icon: 'inventory_2',    to: '/products' },
  { key: 'in-out',        label: t('nav.inout'),         icon: 'swap_horiz',     to: '/in-out' },
  { key: 'warehouses',    label: t('nav.warehouses'),    icon: 'warehouse',      to: '/warehouses' },
  { key: 'alerts',        label: t('nav.alerts'),        icon: 'warning',        to: '/alerts', badge: alertCount.value },
  { key: 'transfer',      label: t('nav.transfer'),      icon: 'sync_alt',       to: '/transfer' },
  { key: 'returns',       label: t('returns.title'),     icon: 'keyboard_return', to: '/inventory/returns' },
  { key: 'retail',        label: t('nav.retail'),        icon: 'receipt_long',   to: '/retail' },
  { key: 'gift-approvals', label: t('nav.giftApprovals'), icon: 'card_giftcard',  to: '/gift-approvals', badge: giftApprovalCount.value },
  { key: 'aftersale',     label: t('nav.aftersale'),     icon: 'support_agent',  to: '/aftersale' },
  { key: 'reports',       label: t('nav.reports'),       icon: 'bar_chart',      to: '/reports' },
])

const allPartnerItems = computed(() => [
  { key: 'suppliers', label: t('nav.suppliers'), icon: 'local_shipping', to: '/suppliers' },
  { key: 'dealers',   label: t('nav.dealers'),   icon: 'handshake',      to: '/dealers' },
  { key: 'stores',    label: t('nav.stores'),    icon: 'storefront',     to: '/stores' },
])

const navItems     = computed(() => allNavItems.value.filter(i => canAccess(i.key)))
const partnerItems = computed(() => allPartnerItems.value.filter(i => canAccess(i.key)))
const showSettings = computed(() => userStore.userRole === ROLES.ADMIN)

function isActive(to) {
  if (to === '/') return route.path === '/'
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
        <li v-for="item in navItems" :key="item.to">
          <router-link
            :to="item.to"
            @click="$emit('close')"
            :class="[
              'flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 rounded transition-colors text-sm sm:text-base',
              isActive(item.to) ? 'bg-primary text-white font-medium' : 'text-gray-300 hover:text-white hover:bg-[#1890ff]/20'
            ]"
          >
            <span class="material-symbols-outlined text-[18px] sm:text-[20px]">{{ item.icon }}</span>
            <span>{{ item.label }}</span>
            <span v-if="item.badge" class="ml-auto bg-danger text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">{{ item.badge }}</span>
          </router-link>
        </li>

        <template v-if="partnerItems.length">
          <li class="mt-4 sm:mt-6 px-3 sm:px-4 pb-2 text-xs font-bold text-gray-500 uppercase tracking-wider">{{ t('nav.partners') }}</li>
          <li v-for="item in partnerItems" :key="item.to">
            <router-link
              :to="item.to"
              @click="$emit('close')"
              :class="[
                'flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 rounded transition-colors text-sm sm:text-base',
                isActive(item.to) ? 'bg-primary text-white font-medium' : 'text-gray-300 hover:text-white hover:bg-[#1890ff]/20'
              ]"
            >
              <span class="material-symbols-outlined text-[18px] sm:text-[20px]">{{ item.icon }}</span>
              <span>{{ item.label }}</span>
            </router-link>
          </li>
        </template>

        <template v-if="showSettings">
          <li class="mt-3 sm:mt-4 px-3 sm:px-4 pb-2 text-xs font-bold text-gray-500 uppercase tracking-wider">{{ t('nav.settings') }}</li>
          <li>
            <router-link
              to="/settings"
              @click="$emit('close')"
              :class="[
                'flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 rounded transition-colors text-sm sm:text-base',
                isActive('/settings') ? 'bg-primary text-white font-medium' : 'text-gray-300 hover:text-white hover:bg-[#1890ff]/20'
              ]"
            >
              <span class="material-symbols-outlined text-[18px] sm:text-[20px]">settings</span>
              <span>{{ t('nav.settings') }}</span>
            </router-link>
          </li>
        </template>
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
