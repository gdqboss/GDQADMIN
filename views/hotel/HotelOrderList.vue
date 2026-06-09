<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const { t } = useI18n()
const router = useRouter()

// ─── Tabs ────────────────────────────────────────────────────────────────────────
const tabs = [
  { key: 'all', label: '全部' },
  { key: 'pending', label: '待处理' },
  { key: 'confirmed', label: '已确认' },
  { key: 'checked_in', label: '已入住' },
  { key: 'checked_out', label: '已退房' },
  { key: 'cancelled', label: '已取消' },
]
const activeTab = ref('all')

// ─── Search ─────────────────────────────────────────────────────────────────────
const searchKeyword = ref('')
const dateRange = ref([])

// ─── Pagination ────────────────────────────────────────────────────────────────
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)

// ─── Table data ────────────────────────────────────────────────────────────────
const orders = ref([])
const loading = ref(false)

const statusTypeMap = {
  pending: 'warning',
  confirmed: 'primary',
  checked_in: 'success',
  checked_out: 'info',
  cancelled: 'danger',
}

const statusLabelMap = {
  pending: '待处理',
  confirmed: '已确认',
  checked_in: '已入住',
  checked_out: '已退房',
  cancelled: '已取消',
}

function getStatusType(status) {
  return statusTypeMap[status] || 'info'
}

function getStatusLabel(status) {
  return statusLabelMap[status] || status
}

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { hour12: false })
}

function formatPrice(p) {
  return p != null ? `¥ ${parseFloat(p).toFixed(2)}` : '-'
}

// ─── Fetch ─────────────────────────────────────────────────────────────────────
async function fetchOrders() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      size: pageSize.value,
    }
    if (activeTab.value !== 'all') params.status = activeTab.value
    if (searchKeyword.value) params.keyword = searchKeyword.value
    if (dateRange.value?.length === 2) {
      params.start_date = dateRange.value[0]
      params.end_date = dateRange.value[1]
    }
    const res = await api.get('/hotel/orders', { params })
    if (res.code === 0) {
      orders.value = res.data.list || res.data
      total.value = res.data.total ?? orders.value.length
    }
  } catch (e) {
    ElMessage.error(e.message || '获取订单列表失败')
  } finally {
    loading.value = false
  }
}

watch([activeTab, searchKeyword, dateRange], () => {
  currentPage.value = 1
  fetchOrders()
}, { deep: true })
watch(currentPage, fetchOrders)
onMounted(fetchOrders)

// ─── Actions ───────────────────────────────────────────────────────────────────
function goDetail(id) {
  router.push(`/hotel/orders/${id}`)
}

async function confirmOrder(row) {
  if (!confirm(`确定确认订单 ${row.order_no}？`)) return
  try {
    const res = await api.put(`/hotel/orders/${row.id}/status`, { status: 'confirmed' })
    if (res.code === 0) {
      ElMessage.success('订单已确认')
      fetchOrders()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function cancelOrder(row) {
  if (!confirm(`确定取消订单 ${row.order_no}？`)) return
  try {
    const res = await api.put(`/hotel/orders/${row.id}/status`, { status: 'cancelled' })
    if (res.code === 0) {
      ElMessage.success('订单已取消')
      fetchOrders()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

function canConfirm(status) { return status === 'pending' }
function canCancel(status) { return status === 'pending' || status === 'confirmed' }
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="酒店订单" subtitle="酒店住宿订单管理" />

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
      <div class="flex overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="px-6 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
          :class="activeTab === tab.key
            ? 'border-blue-600 text-blue-600 bg-blue-50'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- Search bar -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
      <div class="flex flex-wrap gap-4 items-end">
        <div>
          <label class="block text-xs text-gray-500 mb-1">关键词搜索</label>
          <el-input
            v-model="searchKeyword"
            placeholder="订单号 / 客人姓名 / 电话"
            clearable
            class="!w-64"
          />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">日期范围</label>
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            class="!w-72"
          />
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table
        v-loading="loading"
        :data="orders"
        stripe
        empty-text="暂无订单数据"
      >
        <el-table-column label="订单号" prop="order_no" min-width="160" />
        <el-table-column label="客人信息" min-width="140">
          <template #default="{ row }">
            <div class="text-sm">
              <div>{{ row.guest_name || '-' }}</div>
              <div class="text-gray-400 text-xs">{{ row.guest_phone || '' }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="房型" prop="room_type_name" min-width="120" />
        <el-table-column label="入住日期" min-width="110">
          <template #default="{ row }">
            <span class="text-sm">{{ row.checkin_date || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="退房日期" min-width="110">
          <template #default="{ row }">
            <span class="text-sm">{{ row.checkout_date || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="总金额" width="110" align="right">
          <template #default="{ row }">
            <span class="font-medium text-blue-600">{{ formatPrice(row.total_amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="下单时间" width="160">
          <template #default="{ row }">
            <span class="text-sm text-gray-500">{{ formatDate(row.created_at) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <div class="flex flex-wrap gap-1">
              <el-button size="small" link type="primary" @click="goDetail(row.id)">查看详情</el-button>
              <el-button v-if="canConfirm(row.status)" size="small" link type="success" @click="confirmOrder(row)">确认</el-button>
              <el-button v-if="canCancel(row.status)" size="small" link type="danger" @click="cancelOrder(row)">取消</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>

      <!-- Pagination -->
      <div class="flex justify-end p-4 border-t">
        <el-pagination
          v-model:current-page="currentPage"
          :page-size="pageSize"
          :total="total"
          layout="total, prev, pager, next"
          background
        />
      </div>
    </div>
  </div>
</template>