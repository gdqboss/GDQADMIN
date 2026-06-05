<template>
  <div class="pb-20">
    <div class="flex items-center gap-2 p-4 border-b bg-white sticky top-0 z-10">
      <button @click="$router.back()" class="p-1">
        <span class="material-symbols-outlined">arrow_back</span>
      </button>
      <h1 class="font-bold text-base">我的订单</h1>
    </div>

    <!-- 加载状态 -->
    <div v-if="loading" class="text-center py-20">
      <span class="material-symbols-outlined text-4xl animate-spin text-gray-400">progress_activity</span>
    </div>

    <!-- 未登录 -->
    <div v-else-if="!userId" class="text-center py-20">
      <span class="material-symbols-outlined text-5xl text-gray-300">person_off</span>
      <p class="mt-3 text-gray-400">请先登录</p>
      <button @click="$router.push('/mall/login')" class="mt-4 px-6 py-2 bg-blue-600 text-white rounded-full text-sm">
        去登录
      </button>
    </div>

    <!-- 无订单 -->
    <div v-else-if="!orders.length" class="text-center py-20">
      <span class="material-symbols-outlined text-5xl text-gray-300">receipt_long</span>
      <p class="mt-3 text-gray-400">暂无订单</p>
      <button @click="$router.push('/mall')" class="mt-4 px-6 py-2 bg-blue-600 text-white rounded-full text-sm">
        去购物
      </button>
    </div>

    <!-- 订单列表 -->
    <div v-else>
      <div v-for="order in orders" :key="order.id" class="bg-white mt-3 mx-3 rounded-xl overflow-hidden">
        <div class="flex justify-between items-center p-3 border-b border-gray-100">
          <span class="text-xs text-gray-400">{{ order.order_no }}</span>
          <span class="text-xs px-2 py-0.5 rounded-full" :class="statusClass(order.status)">{{ statusText(order.status) }}</span>
        </div>
        <div class="p-3 space-y-2">
          <div v-for="item in order.items" :key="item.product_name" class="flex justify-between text-sm">
            <span class="text-gray-600 line-clamp-1 flex-1">{{ item.product_name }} x{{ item.quantity }}</span>
            <span class="text-gray-500 ml-2">¥{{ item.subtotal }}</span>
          </div>
        </div>
        <div class="flex justify-between items-center p-3 border-t border-gray-100 bg-gray-50">
          <span class="text-sm text-gray-500">{{ formatDate(order.created_at) }}</span>
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500">合计：</span>
            <span class="text-base font-bold text-red-500">¥{{ order.total_amount }}</span>
          </div>
        </div>
        <!-- 收货地址 -->
        <div v-if="order.receiver_address" class="px-3 pb-3">
          <p class="text-xs text-gray-400">{{ order.receiver_name }} {{ order.receiver_phone }}</p>
          <p class="text-xs text-gray-400 mt-0.5">{{ order.receiver_address }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const orders = ref([])
const userId = ref(localStorage.getItem('mall_user_id') || '')
const loading = ref(true)

function statusText(s) {
  const map = { pending: '待处理', confirmed: '已确认', shipped: '已发货', completed: '已完成', cancelled: '已取消' }
  return map[s] || s
}
function statusClass(s) {
  const map = { pending: 'bg-yellow-100 text-yellow-700', confirmed: 'bg-blue-100 text-blue-700',
    shipped: 'bg-purple-100 text-purple-700', completed: 'bg-green-100 text-green-700', cancelled: 'bg-gray-100 text-gray-500' }
  return map[s] || 'bg-gray-100 text-gray-500'
}
function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

onMounted(async () => {
  if (!userId.value) { loading.value = false; return }
  try {
    const res = await fetch(`/api/store-mall/orders?user_id=${userId.value}`)
    const data = await res.json()
    orders.value = data.list || []
  } finally {
    loading.value = false
  }
})
</script>