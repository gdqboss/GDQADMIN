<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import StatCard from '../../components/StatCard.vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const { t } = useI18n()

const loading = ref(false)
const stats = ref({
  today_checkins: 0,
  today_checkouts: 0,
  today_revenue: 0,
  total_rooms: 0,
  occupied_rooms: 0,
  available_rooms: 0,
  occupancy_rate: 0,
  pending_orders: 0,
})
const recentOrders = ref([])
const roomStatusSummary = ref([])

async function fetchStats() {
  loading.value = true
  try {
    const res = await api.get('/hotel/dashboard/stats')
    if (res.code === 0) {
      stats.value = { ...stats.value, ...res.data }
    }
  } catch (e) {
    // ignore
  } finally {
    loading.value = false
  }
}

async function fetchRecentOrders() {
  try {
    const res = await api.get('/hotel/orders', { params: { page: 1, size: 5 } })
    if (res.code === 0) {
      recentOrders.value = res.data.list || res.data
    }
  } catch (e) {
    // ignore
  }
}

async function fetchRoomStatus() {
  try {
    const res = await api.get('/hotel/rooms/status-summary')
    if (res.code === 0) {
      roomStatusSummary.value = res.data || []
    }
  } catch (e) {
    // ignore
  }
}

function formatPrice(p) {
  return p != null ? `¥ ${parseFloat(p).toFixed(2)}` : '¥ 0.00'
}

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { hour12: false })
}

onMounted(() => {
  fetchStats()
  fetchRecentOrders()
  fetchRoomStatus()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="酒店管理" subtitle="酒店经营数据总览" />

    <!-- Stats cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <StatCard title="今日入住" :value="stats.today_checkins" icon="login" color="success" />
      <StatCard title="今日退房" :value="stats.today_checkouts" icon="logout" color="warning" />
      <StatCard title="今日营收" :value="formatPrice(stats.today_revenue)" icon="payments" color="primary" />
      <StatCard title="待处理订单" :value="stats.pending_orders" icon="pending_actions" color="danger" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <StatCard title="客房总数" :value="stats.total_rooms" icon="hotel" color="info" />
      <StatCard title="已入住" :value="stats.occupied_rooms" icon="house" color="success" />
      <StatCard title="空房数" :value="stats.available_rooms" icon="event_available" color="primary" />
      <StatCard title="入住率" :value="stats.occupancy_rate + '%'" icon="pie_chart" color="warning" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Recent orders -->
      <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="text-base font-semibold mb-4">最近订单</h3>
        <el-table :data="recentOrders" stripe size="small" v-loading="loading">
          <el-table-column label="订单号" prop="order_no" min-width="120" />
          <el-table-column label="客人" prop="guest_name" min-width="80" />
          <el-table-column label="房型" prop="room_type_name" min-width="80" />
          <el-table-column label="状态" width="80">
            <template #default="{ row }">
              <el-tag :type="row.status === 'checked_in' ? 'success' : 'info'" size="small">
                {{ row.status === 'checked_in' ? '已入住' : row.status === 'pending' ? '待处理' : '已退房' }}
              </el-tag>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <!-- Room status summary -->
      <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="text-base font-semibold mb-4">房型概览</h3>
        <div v-for="item in roomStatusSummary" :key="item.room_type" class="flex items-center justify-between py-2 border-b last:border-0">
          <div>
            <div class="text-sm font-medium">{{ item.room_type_name }}</div>
            <div class="text-xs text-gray-400">剩余 {{ item.available }} / 总计 {{ item.total }}</div>
          </div>
          <div class="text-right">
            <span class="text-lg font-bold text-blue-600">{{ item.available }}</span>
            <span class="text-xs text-gray-400"> / {{ item.total }}</span>
          </div>
        </div>
        <div v-if="roomStatusSummary.length === 0" class="text-center text-gray-400 py-8">暂无数据</div>
      </div>

      <!-- Quick info -->
      <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="text-base font-semibold mb-4">快捷操作</h3>
        <div class="space-y-3">
          <router-link to="/hotel/orders" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition-colors cursor-pointer">
            <span class="material-symbols-outlined text-blue-600">receipt_long</span>
            <div>
              <div class="text-sm font-medium">订单管理</div>
              <div class="text-xs text-gray-400">查看全部酒店订单</div>
            </div>
          </router-link>
          <router-link to="/hotel/room-types" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition-colors cursor-pointer">
            <span class="material-symbols-outlined text-green-600">hotel</span>
            <div>
              <div class="text-sm font-medium">房型管理</div>
              <div class="text-xs text-gray-400">管理房型与价格</div>
            </div>
          </router-link>
          <router-link to="/hotel/price-calendar" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition-colors cursor-pointer">
            <span class="material-symbols-outlined text-orange-600">calendar_month</span>
            <div>
              <div class="text-sm font-medium">价格日历</div>
              <div class="text-xs text-gray-400">批量设置房态价格</div>
            </div>
          </router-link>
          <router-link to="/hotel/reviews" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition-colors cursor-pointer">
            <span class="material-symbols-outlined text-purple-600">rate_review</span>
            <div>
              <div class="text-sm font-medium">评价管理</div>
              <div class="text-xs text-gray-400">查看客人评价</div>
            </div>
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>