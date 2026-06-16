<template>
  <div class="min-h-screen bg-gray-50">
    <!-- 顶部导航 -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
      <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <span class="material-symbols-outlined text-2xl text-blue-600">shopping_bag</span>
          <span class="font-bold text-lg">{{ shopName }}</span>
        </div>
        <div class="flex items-center gap-3">
          <router-link v-if="!userId" to="/mall/login" class="text-blue-600 text-sm font-medium">登录</router-link>
          <span v-else class="text-sm text-gray-600">
            <span class="material-symbols-outlined text-lg align-middle">person</span>
            {{ userName }}
          </span>
          <router-link to="/mall/cart" class="relative">
            <span class="material-symbols-outlined text-2xl">shopping_cart</span>
            <span v-if="cartCount > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">{{ cartCount }}</span>
          </router-link>
        </div>
      </div>
    </header>

    <!-- 内容区 -->
    <main class="max-w-5xl mx-auto px-4 py-6">
      <router-view />
    </main>

    <!-- 底部Tab -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 safe-area-bottom">
      <div class="flex justify-around py-2">
        <router-link to="/mall" class="flex flex-col items-center py-1 px-4" :class="isActive('/mall') ? 'text-blue-600' : 'text-gray-400'">
          <span class="material-symbols-outlined text-2xl">home</span>
          <span class="text-xs mt-0.5">首页</span>
        </router-link>
        <router-link to="/mall/category/0" class="flex flex-col items-center py-1 px-4" :class="isActive('/mall/category') ? 'text-blue-600' : 'text-gray-400'">
          <span class="material-symbols-outlined text-2xl">category</span>
          <span class="text-xs mt-0.5">分类</span>
        </router-link>
        <router-link to="/mall/cart" class="flex flex-col items-center py-1 px-4 relative" :class="isActive('/mall/cart') ? 'text-blue-600' : 'text-gray-400'">
          <span class="material-symbols-outlined text-2xl">shopping_cart</span>
          <span class="text-xs mt-0.5">购物车</span>
          <span v-if="cartCount > 0" class="absolute top-0 right-2 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">{{ cartCount }}</span>
        </router-link>
        <router-link to="/mall/orders" class="flex flex-col items-center py-1 px-4" :class="isActive('/mall/orders') ? 'text-blue-600' : 'text-gray-400'">
          <span class="material-symbols-outlined text-2xl">receipt_long</span>
          <span class="text-xs mt-0.5">订单</span>
        </router-link>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const shopName = ref('TRAVELMATE STORE')
const userId = ref(localStorage.getItem('mall_user_id') || '')
const userName = ref(localStorage.getItem('mall_user_name') || '')

const cart = ref(JSON.parse(localStorage.getItem('mall_cart') || '[]'))
const cartCount = computed(() => cart.value.reduce((s, i) => s + i.qty, 0))

function isActive(path) {
  return route.path.startsWith(path)
}

window.addEventListener('mall_cart_updated', () => {
  cart.value = JSON.parse(localStorage.getItem('mall_cart') || '[]')
})

onMounted(() => {
  fetch('/api/mall/config')
    .then(r => r.json())
    .then(data => { if (data.shop_name) shopName.value = data.shop_name })
    .catch(() => {})
})
</script>

<style scoped>
.safe-area-bottom { padding-bottom: env(safe-area-inset-bottom, 0px); }
</style>