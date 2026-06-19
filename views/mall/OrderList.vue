<template>
  <div class="pb-20">
    <div class="flex items-center justify-between p-4 border-b bg-white sticky top-0 z-10">
      <h1 class="font-bold text-base">我的订单</h1>
    </div>

    <div v-if="loading" class="text-center py-20 text-gray-400">
      <span class="material-symbols-outlined text-5xl animate-spin">progress_activity</span>
      <p class="mt-2">加载中...</p>
    </div>

    <div v-else-if="!userId" class="text-center py-20">
      <p class="text-gray-500">请先登录</p>
      <button @click="$router.push('/mall/login')" class="mt-3 px-6 py-2 bg-primary text-white rounded-full text-sm">去登录</button>
    </div>

    <div v-else-if="!orders.length" class="text-center py-20">
      <span class="material-symbols-outlined text-5xl text-gray-300">receipt_long</span>
      <p class="mt-3 text-gray-400">暂无订单</p>
      <button @click="$router.push('/mall')" class="mt-4 px-6 py-2 bg-primary text-white rounded-full text-sm">去购物</button>
    </div>

    <div v-else>
      <div v-for="order in orders" :key="order.id" class="bg-white rounded-2xl mt-2 p-4 shadow-sm">
        <div class="flex justify-between items-center mb-3">
          <span class="text-xs text-gray-500">{{ formatDate(order.created_at) }}</span>
          <span :class="statusClass(order.status)" class="text-xs px-2 py-1 rounded-full">{{ statusText(order.status) }}</span>
        </div>
        <div class="flex gap-3">
          <div v-if="order.items && order.items.length">
            <div v-for="item in order.items.slice(0,3)" :key="item.id" class="flex gap-2 mb-2">
              <img v-if="item.image" :src="'/' + item.image" class="w-12 h-12 rounded bg-gray-100 object-cover" @error="e => e.target.style.display='none'" />
              <div class="flex-1">
                <p class="text-xs line-clamp-1">{{ item.product_name || item.name }}</p>
                <p class="text-xs text-gray-500">x{{ item.quantity }}</p>
              </div>
              <p class="text-xs font-bold">¥{{ item.price || item.sale_price }}</p>
            </div>
            <p v-if="order.items.length > 3" class="text-xs text-gray-400">+{{ order.items.length - 3 }}件</p>
          </div>
        </div>
        <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-100">
          <p class="text-sm">共{{ order.items ? order.items.length : 0 }}件</p>
          <p class="font-bold text-red-500">¥{{ order.pay_amount || order.total_amount }}</p>
        </div>
        <div class="flex gap-2 mt-3 justify-end">
          <button v-if="order.status === 'pending_pay'" @click="$router.push('/h5/pay/' + order.id)" class="px-4 py-1 border border-red-500 text-red-500 rounded-full text-xs">去支付</button>
          <button @click="$router.push('/mall/order/' + order.id)" class="px-4 py-1 border border-gray-300 text-gray-600 rounded-full text-xs">查看详情</button>
          <button v-if="order.status === 'pending_pay'" @click="cancelOrder(order.id)" class="px-4 py-1 border border-gray-300 text-gray-400 rounded-full text-xs">取消</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const orders = ref([])
const userId = ref(localStorage.getItem('mall_user_id') || '')
const token = localStorage.getItem('mall_token') || ''
const loading = ref(true)

const statusMap = {
  pending_pay: '待支付', paid: '已支付', shipped: '已发货',
  completed: '已完成', cancelled: '已取消', refund: '退款中'
}
function statusText(s) { return statusMap[s] || s || '未知' }
function statusClass(s) {
  const map = {
    pending_pay: 'bg-orange-100 text-orange-700',
    paid: 'bg-blue-100 text-blue-700',
    shipped: 'bg-purple-100 text-purple-700',
    completed: 'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-500',
    refund: 'bg-red-100 text-red-700'
  }
  return map[s] || 'bg-gray-100 text-gray-500'
}
function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

async function loadOrders() {
  if (!userId.value) { loading.value = false; return }
  try {
    const res = await fetch(`/api/mall/orders?user_id=${userId.value}`, {
      headers: token ? { Authorization: `Bearer ${token}` } : {}
    })
    const data = await res.json()
    orders.value = Array.isArray(data) ? data : (data.list || [])
  } catch (e) {
    orders.value = []
  } finally {
    loading.value = false
  }
}

async function cancelOrder(id) {
  if (!confirm('确定取消该订单？')) return
  try {
    await fetch(`/api/mall/orders/${id}/cancel`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) }
    })
    loadOrders()
  } catch (e) {
    alert('取消失败')
  }
}

onMounted(() => { loadOrders() })
</script>
