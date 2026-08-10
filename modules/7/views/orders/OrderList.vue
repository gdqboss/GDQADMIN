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
  { key: 'pending_pay', label: '待付款' },
  { key: 'paid', label: '已付款' },
  { key: 'shipped', label: '已发货' },
  { key: 'completed', label: '已完成' },
  { key: 'refunded_cancelled', label: '已退款/取消' },
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
  pending_pay: 'warning',
  paid: 'success',
  shipped: 'primary',
  completed: 'info',
  cancelled: 'info',
  refunded: 'danger',
}

const statusLabelMap = {
  pending_pay: '待付款',
  paid: '已付款',
  shipped: '已发货',
  completed: '已完成',
  cancelled: '已取消',
  refunded: '已退款',
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
  return p != null ? `S$ ${parseFloat(p).toFixed(2)}` : '-'
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
    const res = await api.get('/orders', { params })
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

onMounted(() => {
  fetchOrders()
})

// ─── Actions ───────────────────────────────────────────────────────────────────
function goDetail(id) {
  router.push(`/orders/${id}`)
}

async function cancelOrder(row) {
  if (!confirm(`确定取消订单 ${row.order_no}？`)) return
  try {
    const res = await api.put(`/orders/${row.id}/status`, { status: 'cancelled' })
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

async function shipOrder(row) {
  if (!confirm(`确定发货订单 ${row.order_no}？`)) return
  try {
    const res = await api.put(`/orders/${row.id}/status`, { status: 'shipped' })
    if (res.code === 0) {
      ElMessage.success('订单已发货')
      fetchOrders()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function refundOrder(row) {
  if (!confirm(`确定退款订单 ${row.order_no}？`)) return
  try {
    const res = await api.put(`/orders/${row.id}/status`, { status: 'refunded' })
    if (res.code === 0) {
      ElMessage.success('退款成功')
      fetchOrders()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

function canShowShip(status) { return status === 'paid' }
function canShowRefund(status) { return status === 'paid' || status === 'shipped' }
function canShowCancel(status) { return status === 'pending_pay' }

// ─── New order ─────────────────────────────────────────────────────────────────
function goNewOrder() {
  router.push('/orders/new')
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="订单管理" subtitle="澳門中醫藥學會訂單列表" />

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
            placeholder="订单号 / 买家名 / 电话"
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
        <div class="ml-auto">
          <el-button type="primary" @click="goNewOrder">新增订单</el-button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table
        v-loading="loading"
        :data="orders"
        stripe
        class="w-full"
        empty-text="暂无订单数据"
      >
        <el-table-column label="订单号" prop="order_no" min-width="160" />
        <el-table-column label="买家信息" min-width="160">
          <template #default="{ row }">
            <div class="text-sm">
              <div>{{ row.buyer_name || '-' }}</div>
              <div class="text-gray-400 text-xs">{{ row.buyer_phone || '' }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="商品摘要" min-width="200">
          <template #default="{ row }">
            <div class="text-sm space-y-1">
              <div
                v-for="(item, idx) in (row.items || []).slice(0, 3)"
                :key="idx"
                class="truncate"
              >
                {{ item.product_name || item.name }} × {{ item.quantity }}
              </div>
              <div v-if="(row.items || []).length > 3" class="text-gray-400 text-xs">
                +{{ row.items.length - 3 }} 更多
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="总金额" width="120" align="right">
          <template #default="{ row }">
            <span class="font-medium text-blue-600">{{ formatPrice(row.total_amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="110">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="160">
          <template #default="{ row }">
            <span class="text-sm text-gray-500">{{ formatDate(row.created_at) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <div class="flex flex-wrap gap-1">
              <el-button size="small" link type="primary" @click="goDetail(row.id)">查看详情</el-button>
              <el-button v-if="canShowShip(row.status)" size="small" link type="primary" @click="shipOrder(row)">发货</el-button>
              <el-button v-if="canShowRefund(row.status)" size="small" link type="danger" @click="refundOrder(row)">退款</el-button>
              <el-button v-if="canShowCancel(row.status)" size="small" link type="warning" @click="cancelOrder(row)">取消</el-button>
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