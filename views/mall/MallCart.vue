<template>
  <div class="pb-4">
    <!-- 顶栏 -->
    <div class="flex items-center justify-between mb-4">
      <h1 class="font-bold text-base text-gray-800 flex items-center gap-2">
        <span class="material-symbols-outlined text-xl text-primary" style="font-variation-settings: 'FILL' 1">shopping_cart</span>
        购物车
      </h1>
      <button v-if="items.length" @click="clearCart" class="text-xs text-gray-400 hover:text-red-500 transition-colors flex items-center gap-1">
        <span class="material-symbols-outlined text-base">delete_sweep</span>
        清空
      </button>
    </div>

    <!-- 加载中 -->
    <div v-if="loading" class="text-center py-16 text-gray-400">
      <div class="flex justify-center gap-1">
        <span class="w-2 h-2 bg-primary rounded-full animate-bounce" style="animation-delay:0ms"></span>
        <span class="w-2 h-2 bg-primary rounded-full animate-bounce" style="animation-delay:150ms"></span>
        <span class="w-2 h-2 bg-primary rounded-full animate-bounce" style="animation-delay:300ms"></span>
      </div>
    </div>

    <!-- 空状态 -->
    <div v-else-if="!items.length" class="flex flex-col items-center py-20 text-gray-300">
      <span class="material-symbols-outlined text-6xl mb-4" style="font-variation-settings: 'FILL' 1">shopping_cart</span>
      <p class="text-sm text-gray-400 mb-6">购物车是空的</p>
      <button @click="$router.push('/mall')" class="px-8 py-2.5 bg-primary text-white rounded-full text-sm font-medium shadow-sm shadow-primary/20 hover:shadow-md transition-all active:scale-95">
        去逛逛
      </button>
    </div>

    <!-- 购物车列表 -->
    <div v-else class="space-y-3">
      <div v-for="item in items" :key="item.id"
        class="flex gap-3 p-3 bg-white rounded-2xl shadow-sm">
        <div class="w-20 h-20 rounded-xl overflow-hidden flex-shrink-0 bg-gray-100"
          @click="$router.push(`/mall/product/${item.product_id || item.id}`)">
          <img v-if="item.image_main || item.pic || item.image_url"
            :src="item.image_main ? '/' + item.image_main : (item.pic ? '/' + item.pic : item.image_url)"
            class="w-full h-full object-cover"
            @error="e => { e.target.style.display = 'none' }" />
          <div v-else class="flex items-center justify-center h-full">
            <span class="material-symbols-outlined text-3xl text-gray-300">image</span>
          </div>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-800 line-clamp-2 leading-tight cursor-pointer"
            @click="$router.push(`/mall/product/${item.product_id || item.id}`)">
            {{ item.name || item.product_name }}
          </p>
          <p class="text-xs text-gray-400 mt-1">{{ item.sku_name || '' }}</p>
          <div class="flex items-center justify-between mt-2">
            <p class="text-red-500 font-bold text-sm">¥{{ item.sale_price || item.price || '--' }}</p>
            <div class="flex items-center gap-2 bg-gray-100 rounded-full px-1">
              <button @click="changeQty(item, -1)"
                class="w-7 h-7 rounded-full flex items-center justify-center text-gray-500 hover:bg-white hover:shadow-sm transition-all active:scale-90">
                <span class="material-symbols-outlined text-base">remove</span>
              </button>
              <span class="w-7 text-center text-sm font-medium text-gray-700">{{ item.quantity || item.qty || 0 }}</span>
              <button @click="changeQty(item, 1)"
                class="w-7 h-7 rounded-full flex items-center justify-center text-gray-500 hover:bg-white hover:shadow-sm transition-all active:scale-90">
                <span class="material-symbols-outlined text-base">add</span>
              </button>
            </div>
          </div>
        </div>
        <button @click="removeItem(item.id)" class="text-gray-300 self-start mt-1 hover:text-red-400 transition-colors">
          <span class="material-symbols-outlined text-lg">close</span>
        </button>
      </div>
    </div>

    <!-- 结算栏 -->
    <div v-if="items.length" class="fixed bottom-16 left-0 right-0 bg-white/95 backdrop-blur-sm border-t border-gray-100 z-30 px-4 py-3 shadow-[0_-2px_12px_rgba(0,0,0,0.06)] mall-safe-bottom">
      <div class="flex items-center justify-between mb-2">
        <div>
          <p class="text-xs text-gray-400">共 {{ totalCount }} 件</p>
        </div>
        <div class="text-right">
          <p class="text-[11px] text-gray-400">合计</p>
          <p class="text-xl text-red-500 font-bold">¥{{ totalPrice }}</p>
        </div>
      </div>
      <button @click="goCheckout"
        class="w-full py-3 bg-gradient-to-r from-primary to-blue-500 text-white rounded-2xl font-medium text-sm shadow-sm shadow-primary/20 hover:shadow-md transition-all active:scale-[0.98]">
        去结算
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const items = ref([])
const loading = ref(true)
const userId = ref(localStorage.getItem('mall_user_id') || '')
const token = localStorage.getItem('mall_token') || ''

const totalCount = computed(() => items.value.reduce((s, i) => s + (i.quantity || i.qty || 0), 0))
const totalPrice = computed(() => items.value.reduce((s, i) => s + ((i.sale_price || i.price || 0) * (i.quantity || i.qty || 0)), 0).toFixed(2))

async function loadCart() {
  if (!userId.value) { loading.value = false; return }
  try {
    const res = await fetch(`/api/mall/cart?user_id=${userId.value}`, {
      headers: token ? { Authorization: `Bearer ${token}` } : {}
    })
    const data = await res.json()
    items.value = Array.isArray(data) ? data : (data.list || [])
  } catch (e) {
    items.value = []
  } finally {
    loading.value = false
  }
}

async function changeQty(item, delta) {
  const newQty = (item.quantity || item.qty || 0) + delta
  if (newQty <= 0) { removeItem(item.id); return }
  try {
    await fetch(`/api/mall/cart/${item.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: JSON.stringify({ quantity: newQty })
    })
    item.quantity = newQty
  } catch (e) {
    console.error('修改数量失败', e)
  }
}

async function removeItem(id) {
  try {
    await fetch(`/api/mall/cart/${id}`, {
      method: 'DELETE',
      headers: token ? { Authorization: `Bearer ${token}` } : {}
    })
    items.value = items.value.filter(i => i.id !== id)
    window.dispatchEvent(new Event('mall_cart_updated'))
  } catch (e) {
    console.error('删除失败', e)
  }
}

async function clearCart() {
  if (!confirm('确定清空购物车？')) return
  try {
    await fetch('/api/mall/cart/clear', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: JSON.stringify({ user_id: userId.value })
    })
    items.value = []
    window.dispatchEvent(new Event('mall_cart_updated'))
  } catch (e) {
    console.error('清空失败', e)
  }
}

function goCheckout() {
  if (!userId.value) { router.push('/mall/login'); return }
  router.push('/mall/checkout')
}

function onCartUpdate() { loadCart() }

onMounted(() => {
  loadCart()
  window.addEventListener('mall_cart_updated', onCartUpdate)
  window.addEventListener('mall_login_success', onCartUpdate)
})
onUnmounted(() => {
  window.removeEventListener('mall_cart_updated', onCartUpdate)
  window.removeEventListener('mall_login_success', onCartUpdate)
})
</script>

<style scoped>
.mall-safe-bottom {
  padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>
