<template>
  <div class="min-h-screen bg-gray-50">
    <!-- 顶部导航 -->
    <header class="bg-white/95 backdrop-blur-sm shadow-sm sticky top-0 z-50 border-b border-gray-100">
      <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <span class="material-symbols-outlined text-2xl text-primary" style="font-variation-settings: 'FILL' 1">shopping_bag</span>
          <span class="font-bold text-lg text-gray-800">{{ shopName }}</span>
        </div>
        <div class="flex items-center gap-3">
          <router-link v-if="!userId" to="/mall/login" class="text-primary text-sm font-medium">登录</router-link>
          <span v-else class="text-sm text-gray-600 flex items-center gap-1">
            <span class="material-symbols-outlined text-lg align-middle text-primary">person</span>
            {{ userName }}
          </span>
          <router-link to="/mall/cart" class="relative p-1">
            <span class="material-symbols-outlined text-2xl text-gray-600">shopping_cart</span>
            <span v-if="cartCount > 0" class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-1 shadow-sm">{{ cartCount > 99 ? '99+' : cartCount }}</span>
          </router-link>
        </div>
      </div>
    </header>

    <!-- 内容区 -->
    <main class="max-w-5xl mx-auto px-4 py-4 pb-20">
      <router-view />
    </main>

    <!-- 底部Tab -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-sm border-t border-gray-100 z-50 shadow-[0_-2px_12px_rgba(0,0,0,0.06)] safe-area-bottom">
      <div class="flex justify-around py-1">
        <router-link to="/mall"
          class="flex flex-col items-center py-1.5 px-3 transition-colors duration-150"
          :class="isActive('/mall') && !isActive('/mall/category') && !isActive('/mall/cart') && !isActive('/mall/orders') && !isActive('/mall/profile') ? 'text-primary' : 'text-gray-400'">
          <span class="material-symbols-outlined text-2xl"
            :class="isActive('/mall') && !isActive('/mall/category') ? '' : ''"
            :style="isActive('/mall') && !isActive('/mall/category') && !isActive('/mall/cart') && !isActive('/mall/orders') && !isActive('/mall/profile') ? 'font-variation-settings: \'FILL\' 1, \'wght\' 500' : ''">store</span>
          <span class="text-[10px] mt-0.5 font-medium">首页</span>
        </router-link>
        <router-link to="/mall/category/0"
          class="flex flex-col items-center py-1.5 px-3 transition-colors duration-150"
          :class="isActive('/mall/category') ? 'text-primary' : 'text-gray-400'">
          <span class="material-symbols-outlined text-2xl"
            :style="isActive('/mall/category') ? 'font-variation-settings: \'FILL\' 1, \'wght\' 500' : ''">grid_view</span>
          <span class="text-[10px] mt-0.5 font-medium">分类</span>
        </router-link>
        <router-link to="/mall/cart"
          class="flex flex-col items-center py-1.5 px-3 relative transition-colors duration-150"
          :class="isActive('/mall/cart') ? 'text-primary' : 'text-gray-400'">
          <span class="material-symbols-outlined text-2xl"
            :style="isActive('/mall/cart') ? 'font-variation-settings: \'FILL\' 1, \'wght\' 500' : ''">shopping_cart</span>
          <span class="text-[10px] mt-0.5 font-medium">购物车</span>
          <span v-if="cartCount > 0" class="absolute top-0 right-1 bg-red-500 text-white text-[9px] font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-1 shadow-sm">{{ cartCount > 99 ? '99+' : cartCount }}</span>
        </router-link>
        <router-link to="/mall/orders"
          class="flex flex-col items-center py-1.5 px-3 transition-colors duration-150"
          :class="isActive('/mall/orders') ? 'text-primary' : 'text-gray-400'">
          <span class="material-symbols-outlined text-2xl"
            :style="isActive('/mall/orders') ? 'font-variation-settings: \'FILL\' 1, \'wght\' 500' : ''">receipt_long</span>
          <span class="text-[10px] mt-0.5 font-medium">订单</span>
        </router-link>
        <router-link to="/mall/profile"
          class="flex flex-col items-center py-1.5 px-3 transition-colors duration-150"
          :class="isActive('/mall/profile') ? 'text-primary' : 'text-gray-400'">
          <span class="material-symbols-outlined text-2xl"
            :style="isActive('/mall/profile') ? 'font-variation-settings: \'FILL\' 1, \'wght\' 500' : ''">person</span>
          <span class="text-[10px] mt-0.5 font-medium">我的</span>
        </router-link>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const shopName = ref('TRAVELMATE STORE')
const userId = ref(localStorage.getItem('mall_user_id') || '')
const userName = ref(localStorage.getItem('mall_user_name') || '')
const cartCount = ref(0)

let cartInterval = null

function isActive(path) {
  return route.path.startsWith(path)
}

function loadCartCount() {
  const token = localStorage.getItem('mall_token')
  if (!token) return
  fetch('/api/mall/cart/count', {
    headers: { Authorization: 'Bearer ' + token }
  })
    .then(r => r.json())
    .then(res => {
      if (res.code === 0) cartCount.value = res.data?.count || 0
    })
    .catch(() => {})
}

function onCartUpdate() { loadCartCount() }
function onLogin() {
  userId.value = localStorage.getItem('mall_user_id') || ''
  userName.value = localStorage.getItem('mall_user_name') || ''
  loadCartCount()
}

onMounted(() => {
  fetch('/api/mall/config')
    .then(r => r.json())
    .then(data => { if (data.shop_name) shopName.value = data.shop_name })
    .catch(() => {})
  loadCartCount()
  cartInterval = setInterval(loadCartCount, 30000)
  window.addEventListener('mall_cart_updated', onCartUpdate)
  window.addEventListener('mall_user_login', onLogin)
})

onUnmounted(() => {
  if (cartInterval) clearInterval(cartInterval)
  window.removeEventListener('mall_cart_updated', onCartUpdate)
  window.removeEventListener('mall_user_login', onLogin)
})
</script>

<style scoped>
.safe-area-bottom {
  padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>
