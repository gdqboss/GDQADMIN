<template>
  <aside class="h-full flex flex-col bg-[#001529] text-gray-300">
    <!-- Header -->
    <div class="h-14 flex items-center justify-between px-4 bg-[#002140] border-b border-[#001529] shrink-0">
      <h1 class="text-base font-bold text-white">彩美特管理系统</h1>
      <button @click="handleLogout" class="p-1.5 rounded hover:bg-white/10 text-gray-400 hover:text-white transition-colors" title="退出登录">
        <span class="material-symbols-outlined text-xl">logout</span>
      </button>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto py-2">
      <!-- 动态菜单（优先） -->
      <template v-if="userStore.menuLoaded && dynamicMenuGroups.length > 0">
        <div v-for="group in dynamicMenuGroups" :key="group.label" class="mb-2">
          <div v-if="group.label" class="px-4 py-2 text-xs text-gray-500 uppercase tracking-wider">{{ group.label }}</div>
          <router-link
            v-for="item in group.items"
            :key="item.path"
            :to="item.path"
            class="flex items-center gap-3 px-4 py-2.5 mx-1 rounded text-sm transition-colors"
            :class="isActive(item.path) ? 'bg-primary text-white font-medium' : 'hover:bg-white/10 hover:text-white'"
          >
            <span class="material-symbols-outlined text-lg">{{ item.icon || 'circle' }}</span>
            <span>{{ item.label }}</span>
          </router-link>
        </div>
      </template>

      <!-- 硬编码菜单（回退） -->
      <template v-else>
        <div v-for="group in fallbackMenus" :key="group.label" class="mb-2">
          <div v-if="group.label" class="px-4 py-2 text-xs text-gray-500 uppercase tracking-wider">{{ group.label }}</div>
          <router-link
            v-for="item in group.items"
            :key="item.path"
            :to="item.path"
            class="flex items-center gap-3 px-4 py-2.5 mx-1 rounded text-sm transition-colors"
            :class="isActive(item.path) ? 'bg-primary text-white font-medium' : 'hover:bg-white/10 hover:text-white'"
          >
            <span class="material-symbols-outlined text-lg">{{ item.icon }}</span>
            <span>{{ item.name }}</span>
          </router-link>
        </div>
      </template>
    </nav>
  </aside>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '@/stores/user'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

// 将 rbac_menus 树形数据按 parent_id 分组
const dynamicMenuGroups = computed(() => {
  if (!userStore.menus || userStore.menus.length === 0) return []

  // 按 parent_id 分组
  const topMenus = userStore.menus.filter(m => !m.parent_id && m.visible === 'show' && m.component_path)
  const groups = []
  for (const top of topMenus) {
    const children = userStore.menus.filter(m => m.parent_id === top.id && m.visible === 'show' && m.component_path)
    groups.push({
      label: top.label,
      items: [
        { path: top.path, label: top.label, icon: top.icon },
        ...children.map(c => ({ path: c.path, label: c.label, icon: c.icon }))
      ]
    })
  }
  return groups
})

// 硬编码回退菜单（原始侧边栏）
const fallbackMenus = [
  {
    label: '',
    items: [
      { path: '/', name: '工作台', icon: 'dashboard' },
      { path: '/oa', name: 'OA办公', icon: 'badge' },
      { path: '/oa/work-logs', name: '工作日志', icon: 'description' },
      { path: '/finance', name: '财务管理', icon: 'payments' },
      { path: '/tasks', name: '任务管理', icon: 'task_alt' },
      { path: '/qrcode', name: '一物一码', icon: 'qr_code_2' },
    ]
  },
  {
    label: '库存管理',
    items: [
      { path: '/products', name: '商品管理', icon: 'inventory_2' },
      { path: '/stock/inout', name: '出入库管理', icon: 'swap_horiz' },
      { path: '/stock/alerts', name: '库存预警', icon: 'warning' },
    ]
  },
  {
    label: '销售管理',
    items: [
      { path: '/sales/retail', name: '零售记录', icon: 'receipt_long' },
      { path: '/aftersale', name: '售后管理', icon: 'support_agent' },
      { path: '/sales/returns', name: '退货记录', icon: 'keyboard_return' },
    ]
  },
  {
    label: '数据报表',
    items: [
      { path: '/report/bi', name: 'BI分析', icon: 'analytics' },
      { path: '/report/excel', name: '报告管理', icon: 'folder_open' },
      { path: '/report/center', name: '报表中心', icon: 'bar_chart' },
    ]
  },
  {
    label: '合作伙伴',
    items: [
      { path: '/suppliers', name: '供货商', icon: 'local_shipping' },
      { path: '/dealers', name: '经销商', icon: 'handshake' },
      { path: '/stores', name: '门店', icon: 'storefront' },
    ]
  },
  {
    label: '系统',
    items: [
      { path: '/system', name: '系统设置', icon: 'settings' },
      { path: '/attendance/manage', name: '考勤管理', icon: 'schedule' },
    ]
  }
]

const isActive = (path) => {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}

const handleLogout = () => {
  userStore.logout()
  router.push('/login')
}
</script>
