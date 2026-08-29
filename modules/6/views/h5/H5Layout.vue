<template>
  <div class="min-h-screen bg-slate-50">
    <!-- 主内容区 -->
    <main class="pb-16">
      <router-view />
    </main>

    <!-- 底部 TabBar（仅在主页 / 分类 / 购物车 / 我的 显示） -->
    <nav v-if="showTabBar"
      class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-sm border-t border-slate-100 z-50 safe-area-bottom shadow-[0_-2px_12px_rgba(0,0,0,0.06)]">
      <div class="flex justify-around py-1">
        <router-link v-for="tab in tabs" :key="tab.path" :to="tab.path"
          class="flex flex-col items-center py-1.5 px-3 min-w-0 transition-colors duration-150"
          :class="isActive(tab.path) ? 'text-primary' : 'text-slate-400 hover:text-slate-500'">
          <span class="material-symbols-outlined text-2xl"
            :class="isActive(tab.path) ? 'font-variation-settings' : ''"
            :style="isActive(tab.path) ? 'font-variation-settings: \'FILL\' 1, \'wght\' 500, \'GRAD\' 0' : ''">
            {{ tab.icon }}
          </span>
          <span class="text-[10px] mt-0.5 font-medium">{{ tab.label }}</span>
          <span v-if="tab.badge && tab.badge > 0"
            class="absolute -top-0.5 right-1 bg-red-500 text-white text-[9px] font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-1 shadow-sm">
            {{ tab.badge > 99 ? '99+' : tab.badge }}
          </span>
        </router-link>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const cartCount = ref(0)

// TabBar 配置 — Material Symbols Outlined 图标
const tabs = [
  { path: '/h5', name: 'H5Home', icon: 'store', label: '首页', badge: 0 },
  { path: '/h5/categories', name: 'H5Categories', icon: 'grid_view', label: '分类', badge: 0 },
  { path: '/h5/cart', name: 'H5Cart', icon: 'shopping_cart', label: '购物车', badge: 0 },
  { path: '/h5/profile', name: 'H5Profile', icon: 'person', label: '我的', badge: 0 },
]

// 只在主 Tab 页面显示底部导航
const tabPaths = ['/h5', '/h5/home', '/h5/categories', '/h5/cart', '/h5/profile']
const showTabBar = computed(() => tabPaths.includes(route.path))

function isActive(path) {
  if (path === '/h5') return route.path === '/h5' || route.path === '/h5/home'
  return route.path === path
}

// 监听购物车数量变化
let cartInterval = null
function loadCartCount() {
  fetch('/api/mall/cart/count')
    .then(r => r.json())
    .then(res => {
      if (res.code === 0) cartCount.value = res.data?.count || 0
    })
    .catch(() => {})
}

onMounted(() => {
  loadCartCount()
  cartInterval = setInterval(loadCartCount, 30000) // 每30秒更新
})

onUnmounted(() => {
  if (cartInterval) clearInterval(cartInterval)
})

// 暴露给子组件修改
watch(cartCount, (val) => {
  const cartTab = tabs.find(t => t.name === 'H5Cart')
  if (cartTab) cartTab.badge = val
})
</script>

<style scoped>
.safe-area-bottom {
  padding-bottom: env(safe-area-inset-bottom, 0px);
}

/* Material Symbols 填充变体 */
.font-variation-settings {
  font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0;
}
</style>
