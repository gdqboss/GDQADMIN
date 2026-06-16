<template>
  <div class="pb-20">
    <div class="flex items-center justify-between p-4 border-b bg-white sticky top-0 z-10">
      <h1 class="font-bold text-base">购物车</h1>
      <button v-if="items.length" @click="clearCart" class="text-sm text-gray-500">清空</button>
    </div>

    <div v-if="loading" class="text-center py-20 text-gray-400">
      <span class="material-symbols-outlined text-5xl animate-spin">progress_activity</span>
      <p class="mt-2">加载中...</p>
    </div>

    <div v-else-if="!items.length" class="text-center py-20">
      <span class="material-symbols-outlined text-5xl text-gray-300">shopping_cart</span>
      <p class="mt-3 text-gray-400">购物车是空的</p>
      <button @click="$router.push('/mall')" class="mt-4 px-6 py-2 bg-blue-600 text-white rounded-full text-sm">
        去逛逛
      </button>
    </div>

    <div v-else>
      <div v-for="item in items" :key="item.id"
        class="flex gap-3 p-4 bg-white border-b border-gray-100">
        <div class="w-20 h-20 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
          <img v-if="item.image_main || item.pic" :src="'/' + (item.image_main || item.pic)" class="w-full h-full object-cover" @error="e => e.target.style.display='none'" />
          <div v-else class="flex items-center justify-center h-full text-gray-300">
            <span class="material-symbols-outlined text-2xl">image</span>
          </div>
        </div>
        <div class="flex-1 flex flex-col justify-between">
          <p class="text-sm line-clamp-2 leading-tight">{{ item.name }}</p>
          <div class="flex items-end justify-between mt-1">
            <p class="text-red-500 font-bold text-sm">¥{{ item.sale_price || item.price || '--' }}</p>
            <div class="flex items-center gap-2">
              <button @click="changeQty(item, -1)" class="w-7 h-7 rounded border border-gray-200 flex items-center justify-center text-gray-500">−</button>
              <span class="text-sm w-6 text-center">{{ item.quantity || item.qty }}</span>
              <button @click="changeQty(item, 1)" class="w-7 h-7 rounded border border-gray-200 flex items-center justify-center text-gray-500">+</button>
            </div>
          </div>
        </div>
        <button @click="removeItem(item.id)" class="text-gray-300 self-start mt-1">
          <span class="material-symbols-outlined text-lg">close</span>
        </button>
      </div>

      <!-- Footer summary -->
      <div class="fixed bottom-0 left-0 right-0 bg-white border-t p-4 flex items-center justify-between">
        <div>
          <p class="text-sm text-gray-500">共 {{ totalCount }} 件</p>
          <p class="text-red-500 font-bold">¥{{ totalPrice }}</p>
        </div>
        <button @click="goCheckout" class="px-6 py-2 bg-blue-600 text-white rounded-full text-sm">
          去结算
        </button>
      </div>
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
    console.error('加载购物车失败', e)
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
