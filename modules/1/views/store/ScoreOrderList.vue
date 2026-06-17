<template>
  <div class="pb-20">
    <!-- Header -->
    <div class="flex items-center gap-2 p-4 bg-white border-b sticky top-0 z-10">
      <button @click="$router.back()" class="p-1">
        <span class="material-symbols-outlined">arrow_back</span>
      </button>
      <h1 class="font-bold text-base">兑换记录</h1>
    </div>

    <!-- Loading -->
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

    <!-- 无记录 -->
    <div v-else-if="!orders.length" class="text-center py-20">
      <span class="material-symbols-outlined text-5xl text-gray-300">card_giftcard</span>
      <p class="mt-3 text-gray-400">暂无兑换记录</p>
      <button @click="$router.push('/mall/score-shop')" class="mt-4 px-6 py-2 bg-amber-500 text-white rounded-full text-sm">
        去积分商城
      </button>
    </div>

    <!-- 订单列表 -->
    <div v-else>
      <div v-for="o in orders" :key="o.id"
        class="bg-white mt-3 mx-3 rounded-xl overflow-hidden"
        @click="goOrderDetail(o.id)">
        <div class="flex items-start gap-3 p-3">
          <div class="w-16 h-16 rounded-lg bg-gray-100 flex-shrink-0 overflow-hidden">
            <img v-if="o.product_image" :src="'/' + o.product_image" class="w-full h-full object-cover" />
            <div v-else class="flex items-center justify-center h-full text-gray-300">
              <span class="material-symbols-outlined text-2xl">card_giftcard</span>
            </div>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-800 line-clamp-1">{{ o.product_name }}</p>
            <p class="text-xs text-gray-400 mt-0.5">兑换单号：{{ o.order_no }}</p>
            <p class="text-xs text-gray-400">{{ formatDate(o.created_at) }}</p>
          </div>
          <div class="text-right flex-shrink-0">
            <p class="text-sm font-bold text-amber-500 flex items-center gap-0.5">
              <span class="material-symbols-outlined text-xs">stars</span>{{ o.total_score }}
            </p>
            <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full"
              :class="statusClass(o.status)">{{ statusLabel(o.status) }}</span>
          </div>
        </div>

        <!-- 收货信息 -->
        <div v-if="o.receiver_address" class="px-3 pb-3 border-t border-gray-100 pt-2">
          <p class="text-xs text-gray-400">{{ o.receiver_name }} {{ o.receiver_phone }}</p>
          <p class="text-xs text-gray-400 mt-0.5">{{ o.receiver_address }}</p>
        </div>
      </div>

      <!-- 加载更多 -->
      <div v-if="hasMore" class="mt-4 text-center pb-6">
        <button @click="loadMore" :disabled="loadingMore"
          class="px-6 py-2 bg-white border border-gray-200 rounded-full text-sm text-gray-600 disabled:opacity-50">
          {{ loadingMore ? '加载中...' : '加载更多' }}
        </button>
      </div>
      <div v-else-if="orders.length > 0" class="mt-4 text-center text-sm text-gray-400 pb-6">
        — 没有更多了 —
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const router = useRouter()
const orders = ref([])
const userId = ref(localStorage.getItem('mall_user_id') || localStorage.getItem('caimeite_user_id') || '')
const loading = ref(true)
const loadingMore = ref(false)
const page = ref(1)
const hasMore = ref(false)

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

async function fetchOrders(append = false) {
  if (!userId.value) {
    loading.value = false
    return
  }
  if (!append) loading.value = true
  else loadingMore.value = true
  try {
    const res = await api.get('/score-shop/orders', {
      params: { page: page.value, size: 20 }
    })
    if (res.code === 0) {
      if (append) {
        orders.value.push(...(res.data.list || []))
      } else {
        orders.value = res.data.list || []
      }
      hasMore.value = res.data.list?.length === 20
    }
  } catch {
    if (!append) ElMessage.error('获取兑换记录失败')
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

function loadMore() {
  page.value++
  fetchOrders(true)
}

function goOrderDetail(id) {
  router.push(`/mall/score-order/${id}`)
}

onMounted(() => {
  fetchOrders()
})
</script>
