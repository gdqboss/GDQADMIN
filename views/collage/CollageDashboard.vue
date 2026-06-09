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
  total_products: 0,
  total_orders: 0,
  pending_orders: 0,
  total_revenue: 0,
})
const recentOrders = ref([])

async function fetchStats() {
  loading.value = true
  try {
    const res = await api.get("/collage/products", { params: { page: 1, size: 1 } })
    if (res.code === 0) {
      stats.value.total_products = res.data.total || 0
    }
  } catch (e) {}
  try {
    const res = await api.get("/collage/orders", { params: { page: 1, size: 1 } })
    if (res.code === 0) {
      stats.value.total_orders = res.data.total || 0
    }
  } catch (e) {}
  try {
    const res = await api.get("/collage/orders", { params: { page: 1, size: 5, status: 0 } })
    if (res.code === 0) {
      stats.value.pending_orders = res.data.total || 0
    }
  } catch (e) {}
  finally {
    loading.value = false
  }
}

async function fetchRecentOrders() {
  try {
    const res = await api.get("/collage/orders", { params: { page: 1, size: 5 } })
    if (res.code === 0) {
      recentOrders.value = res.data.list || []
    }
  } catch (e) {}
}

function formatPrice(p) {
  return p != null ? "¥ " + parseFloat(p).toFixed(2) : "¥ 0.00"
}

function formatDate(d) {
  if (!d) return "-"
  return new Date(d * 1000).toLocaleString("zh-CN", { hour12: false })
}

onMounted(() => {
  fetchStats()
  fetchRecentOrders()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="拼团管理" subtitle="拼团商品经营数据总览" />

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <StatCard title="商品总数" :value="stats.total_products" icon="inventory_2" color="info" />
      <StatCard title="订单总数" :value="stats.total_orders" icon="receipt_long" color="primary" />
      <StatCard title="待处理订单" :value="stats.pending_orders" icon="pending_actions" color="danger" />
      <StatCard title="总营收" :value="formatPrice(stats.total_revenue)" icon="payments" color="success" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="bg-white rounded-xl shadow-sm p-4 lg:col-span-2">
        <h3 class="text-base font-semibold mb-4">最近订单</h3>
        <el-table :data="recentOrders" stripe size="small" v-loading="loading">
          <el-table-column label="订单号" prop="ordernum" min-width="140" />
          <el-table-column label="商品" prop="proname" min-width="120" />
          <el-table-column label="金额" min-width="90" align="right">
            <template #default="{ row }">
              <span class="text-blue-600 font-medium">{{ formatPrice(row.money) }}</span>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="80">
            <template #default="{ row }">
              <el-tag :type="row.status == 0 ? 'warning' : row.status == 1 ? 'success' : 'info'" size="small">
                {{ ["待付款","已付款","已完成"][row.status] || row.status }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="时间" min-width="140">
            <template #default="{ row }">
              {{ formatDate(row.createtime) }}
            </template>
          </el-table-column>
        </el-table>
      </div>

      <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="text-base font-semibold mb-4">快捷操作</h3>
        <div class="space-y-3">
          <router-link to="/collage/products" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition-colors cursor-pointer">
            <span class="material-symbols-outlined text-blue-600">inventory</span>
            <div>
              <div class="text-sm font-medium">商品管理</div>
              <div class="text-xs text-gray-400">管理拼团商品</div>
            </div>
          </router-link>
          <router-link to="/collage/orders" class="flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition-colors cursor-pointer">
            <span class="material-symbols-outlined text-green-600">receipt_long</span>
            <div>
              <div class="text-sm font-medium">订单管理</div>
              <div class="text-xs text-gray-400">查看拼团订单</div>
            </div>
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>
