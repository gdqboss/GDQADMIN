<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const stats = ref({ tables: 0, dineOrders: 0, takeoutOrders: 0, queueCount: 0 })
const recentOrders = ref([])
const loading = ref(false)

const statusMap = {
  idle: { label: '空闲', type: 'success' },
  occupied: { label: '占用', type: 'danger' },
  reserved: { label: '已预订', type: 'warning' },
  locked: { label: '锁定', type: 'info' },
}

async function fetchStats() {
  try {
    const [tables, dineOrders, takeoutOrders, queue] = await Promise.all([
      api.get('/restaurant/tables'),
      api.get('/restaurant/dine-orders', { params: { status: 'ordering' } }),
      api.get('/restaurant/takeout-orders', { params: { status: 'pending' } }),
      api.get('/restaurant/queue', { params: { status: 'waiting' } }),
    ])
    stats.value = {
      tables: tables.data?.length || 0,
      dineOrders: dineOrders.data?.length || 0,
      takeoutOrders: takeoutOrders.data?.length || 0,
      queueCount: queue.data?.length || 0,
    }
  } catch (e) {
    // silent fail - not all endpoints may exist yet
  }
}

onMounted(fetchStats)
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800">餐饮管理</h1>
      <p class="text-gray-500 text-sm mt-1">桌台 / 点餐 / 外卖 / 预订 / 排队 / 收银</p>
    </div>

    <!-- Quick stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="text-3xl font-bold text-blue-600">{{ stats.tables }}</div>
        <div class="text-sm text-gray-500 mt-1">桌台总数</div>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="text-3xl font-bold text-orange-500">{{ stats.dineOrders }}</div>
        <div class="text-sm text-gray-500 mt-1">待处理堂食</div>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="text-3xl font-bold text-purple-500">{{ stats.takeoutOrders }}</div>
        <div class="text-sm text-gray-500 mt-1">待处理外卖</div>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="text-3xl font-bold text-green-500">{{ stats.queueCount }}</div>
        <div class="text-sm text-gray-500 mt-1">排队人数</div>
      </div>
    </div>

    <!-- Navigation cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
      <router-link to="/restaurant/tables" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow text-center">
        <div class="text-4xl mb-2">🪑</div>
        <div class="font-medium text-gray-800">桌台管理</div>
        <div class="text-xs text-gray-400 mt-1">桌台状态一览</div>
      </router-link>
      <router-link to="/restaurant/dishes" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow text-center">
        <div class="text-4xl mb-2">🍜</div>
        <div class="font-medium text-gray-800">菜品管理</div>
        <div class="text-xs text-gray-400 mt-1">菜品和分类</div>
      </router-link>
      <router-link to="/restaurant/dine-orders" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow text-center">
        <div class="text-4xl mb-2">🍽️</div>
        <div class="font-medium text-gray-800">堂食点餐</div>
        <div class="text-xs text-gray-400 mt-1">开台点餐结算</div>
      </router-link>
      <router-link to="/restaurant/takeout" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow text-center">
        <div class="text-4xl mb-2">📦</div>
        <div class="font-medium text-gray-800">外卖订单</div>
        <div class="text-xs text-gray-400 mt-1">外卖接单配送</div>
      </router-link>
      <router-link to="/restaurant/reservations" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow text-center">
        <div class="text-4xl mb-2">📅</div>
        <div class="font-medium text-gray-800">预订管理</div>
        <div class="text-xs text-gray-400 mt-1">座位预订</div>
      </router-link>
      <router-link to="/restaurant/queue" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow text-center">
        <div class="text-4xl mb-2">🎫</div>
        <div class="font-medium text-gray-800">排队叫号</div>
        <div class="text-xs text-gray-400 mt-1">取号叫号入座</div>
      </router-link>
    </div>
  </div>
</template>