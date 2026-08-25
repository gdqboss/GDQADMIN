<template>
  <div class="pb-20">
    <!-- Header -->
    <div class="flex items-center gap-2 p-4 bg-white border-b sticky top-0 z-10">
      <button @click="$router.back()" class="p-1">
        <span class="material-symbols-outlined">arrow_back</span>
      </button>
      <h1 class="font-bold text-base">兑换详情</h1>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-20">
      <span class="material-symbols-outlined text-4xl animate-spin text-gray-400">progress_activity</span>
    </div>

    <!-- 订单不存在 -->
    <div v-else-if="!order" class="text-center py-20">
      <span class="material-symbols-outlined text-5xl text-gray-300">info</span>
      <p class="mt-3 text-gray-400">订单不存在</p>
      <button @click="$router.back()" class="mt-4 px-6 py-2 bg-gray-200 text-gray-600 rounded-full text-sm">
        返回
      </button>
    </div>

    <!-- 订单详情 -->
    <div v-else>
      <!-- 商品信息 -->
      <div class="bg-white mt-3 mx-3 rounded-xl p-4">
        <div class="flex items-start gap-3">
          <div class="w-16 h-16 rounded-lg bg-gray-100 flex-shrink-0 overflow-hidden">
            <img v-if="order.product_image" :src="'/' + order.product_image" class="w-full h-full object-cover" />
            <div v-else class="flex items-center justify-center h-full text-gray-300">
              <span class="material-symbols-outlined text-2xl">card_giftcard</span>
            </div>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-800">{{ order.product_name }}</p>
            <p class="text-xs text-gray-400 mt-1">兑换单号：{{ order.order_no }}</p>
            <p class="text-sm font-bold text-amber-500 flex items-center gap-0.5 mt-1">
              <span class="material-symbols-outlined text-sm">stars</span>{{ order.total_score }} 积分
            </p>
          </div>
          <span class="text-xs px-2 py-0.5 rounded-full" :class="statusClass(order.status)">
            {{ statusLabel(order.status) }}
          </span>
        </div>
      </div>

      <!-- 收货信息 -->
      <div class="bg-white mt-3 mx-3 rounded-xl p-4">
        <p class="text-sm font-medium text-gray-800 mb-2">收货信息</p>
        <div class="space-y-1">
          <p class="text-sm text-gray-600">
            <span class="text-gray-400">收货人：</span>{{ order.receiver_name || '-' }} {{ order.receiver_phone || '' }}
          </p>
          <p class="text-sm text-gray-600">
            <span class="text-gray-400">地&nbsp;&nbsp;址：</span>{{ order.receiver_address || '-' }}
          </p>
          <p v-if="order.remark" class="text-sm text-gray-600">
            <span class="text-gray-400">备&nbsp;&nbsp;注：</span>{{ order.remark }}
          </p>
        </div>
      </div>

      <!-- 订单时间线 -->
      <div class="bg-white mt-3 mx-3 rounded-xl p-4">
        <p class="text-sm font-medium text-gray-800 mb-3">订单信息</p>
        <div class="space-y-2">
          <div class="flex justify-between text-sm">
            <span class="text-gray-400">兑换时间</span>
            <span class="text-gray-600">{{ formatDate(order.created_at) }}</span>
          </div>
          <div v-if="order.shipped_at" class="flex justify-between text-sm">
            <span class="text-gray-400">发货时间</span>
            <span class="text-gray-600">{{ formatDate(order.shipped_at) }}</span>
          </div>
          <div v-if="order.completed_at" class="flex justify-between text-sm">
            <span class="text-gray-400">完成时间</span>
            <span class="text-gray-600">{{ formatDate(order.completed_at) }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-400">兑换数量</span>
            <span class="text-gray-600">×{{ order.quantity }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-400">消耗积分</span>
            <span class="text-amber-600 font-bold">{{ order.total_score }} 积分</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const route = useRoute()
const order = ref(null)
const loading = ref(true)

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { hour12: false })
}

function statusClass(s) {
  const map = {
    pending: 'bg-yellow-100 text-yellow-600',
    processing: 'bg-blue-100 text-blue-600',
    shipped: 'bg-green-100 text-green-600',
    completed: 'bg-gray-100 text-gray-500',
    cancelled: 'bg-gray-100 text-gray-400'
  }
  return map[s] || 'bg-gray-100 text-gray-500'
}

function statusLabel(s) {
  const map = {
    pending: '待发货',
    processing: '处理中',
    shipped: '已发货',
    completed: '已完成',
    cancelled: '已取消'
  }
  return map[s] || s
}

async function fetchOrder() {
  loading.value = true
  try {
    // 先获取订单列表中是否有此订单，或通过详情接口
    const res = await api.get('/score-shop/orders', { params: { page: 1, size: 50 } })
    if (res.code === 0) {
      const list = res.data.list || []
      order.value = list.find(o => String(o.id) === String(route.params.id)) || null
    }
    // 如果上述没找到，尝试直接用订单ID调详情（如果后端有的话）
    if (!order.value) {
      try {
        const r2 = await api.get(`/score-shop/orders/${route.params.id}`)
        if (r2.code === 0) order.value = r2.data
      } catch {}
    }
    if (!order.value) {
      ElMessage.error('订单不存在')
    }
  } catch {
    ElMessage.error('获取订单详情失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchOrder()
})
</script>
