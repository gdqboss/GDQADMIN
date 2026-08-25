<template>
  <div class="p-6">
    <PageHeader title="积分订单管理" subtitle="用户积分兑换记录管理" />

    <!-- 操作栏 -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4 flex flex-wrap gap-4 items-end">
      <div>
        <label class="block text-xs text-gray-500 mb-1">状态</label>
        <el-select v-model="filterStatus" placeholder="全部" clearable class="!w-32" @change="resetAndFetch">
          <el-option label="全部" value="" />
          <el-option label="待发货" value="pending" />
          <el-option label="处理中" value="processing" />
          <el-option label="已发货" value="shipped" />
          <el-option label="已完成" value="completed" />
          <el-option label="已取消" value="cancelled" />
        </el-select>
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">关键词</label>
        <el-input v-model="keyword" placeholder="订单号/商品名称/用户ID" clearable class="!w-48" @clear="resetAndFetch" @keyup.enter="resetAndFetch" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">日期范围</label>
        <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始" end-placeholder="结束"
          value-format="YYYY-MM-DD" class="!w-64" @change="resetAndFetch" />
      </div>
      <button @click="resetAndFetch" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">搜索</button>
    </div>

    <!-- 表格 -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table :data="orders" v-loading="loading" stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column label="订单信息" min-width="200">
          <template #default="{ row }">
            <p class="text-sm font-medium text-gray-800">{{ row.order_no }}</p>
            <div class="flex items-center gap-2 mt-1">
              <div class="w-10 h-10 rounded bg-gray-100 overflow-hidden flex-shrink-0">
                <img v-if="row.product_image" :src="'/' + row.product_image" class="w-full h-full object-cover" />
              </div>
              <p class="text-xs text-gray-600 line-clamp-1">{{ row.product_name }}</p>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="用户信息" width="140">
          <template #default="{ row }">
            <p class="text-sm text-gray-800">{{ row.user_name || `ID:${row.user_id}` }}</p>
            <p class="text-xs text-gray-400">{{ row.user_phone || '-' }}</p>
          </template>
        </el-table-column>
        <el-table-column label="积分" width="100">
          <template #default="{ row }">
            <span class="text-amber-600 font-bold">{{ row.total_score }}</span>
          </template>
        </el-table-column>
        <el-table-column label="数量" width="70">
          <template #default="{ row }">
            <span class="text-sm">×{{ row.quantity }}</span>
          </template>
        </el-table-column>
        <el-table-column label="收货信息" min-width="150">
          <template #default="{ row }">
            <p class="text-xs text-gray-600">{{ row.receiver_name || '-' }} {{ row.receiver_phone || '' }}</p>
            <p class="text-xs text-gray-400 line-clamp-1">{{ row.receiver_address || '-' }}</p>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="兑换时间" width="160">
          <template #default="{ row }">
            <span class="text-xs text-gray-500">{{ formatDate(row.created_at) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <button @click="openStatusDialog(row)" class="text-blue-600 text-sm hover:underline">变更状态</button>
          </template>
        </el-table-column>
      </el-table>

      <div class="p-4 flex justify-end">
        <el-pagination
          v-model:current-page="currentPage"
          :page-size="pageSize"
          :total="total"
          layout="total, prev, pager, next"
          @current-change="fetchOrders"
        />
      </div>
    </div>

    <!-- 变更状态弹窗 -->
    <el-dialog v-model="showStatusDialog" title="变更订单状态" width="400px">
      <el-form-item label="当前状态">
        <el-tag :type="statusTagType(selectedOrder?.status)" size="small">{{ statusLabel(selectedOrder?.status) }}</el-tag>
      </el-form-item>
      <el-form-item label="变更为">
        <el-select v-model="newStatus" class="!w-full">
          <el-option label="待发货" value="pending" />
          <el-option label="处理中" value="processing" />
          <el-option label="已发货" value="shipped" />
          <el-option label="已完成" value="completed" />
          <el-option label="已取消" value="cancelled" />
        </el-select>
      </el-form-item>
      <template #footer>
        <el-button @click="showStatusDialog = false">取消</el-button>
        <el-button type="primary" @click="submitStatusChange" :loading="submitting">确认</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const orders = ref([])
const loading = ref(false)
const filterStatus = ref('')
const keyword = ref('')
const dateRange = ref([])
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)
const showStatusDialog = ref(false)
const selectedOrder = ref(null)
const newStatus = ref('')
const submitting = ref(false)

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { hour12: false })
}
function statusTagType(s) {
  return { pending: 'warning', processing: 'primary', shipped: 'success', completed: 'info', cancelled: 'danger' }[s] || 'info'
}
function statusLabel(s) {
  return { pending: '待发货', processing: '处理中', shipped: '已发货', completed: '已完成', cancelled: '已取消' }[s] || s
}

async function fetchOrders() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value }
    if (filterStatus.value) params.status = filterStatus.value
    if (keyword.value) params.keyword = keyword.value
    if (dateRange.value?.length === 2) {
      params.date_start = dateRange.value[0]
      params.date_end = dateRange.value[1]
    }
    const res = await api.get('/score-shop/admin/orders', { params })
    if (res.code === 0) {
      orders.value = res.data.list || []
      total.value = res.data.total
    }
  } finally { loading.value = false }
}

function openStatusDialog(order) {
  selectedOrder.value = order
  newStatus.value = order.status
  showStatusDialog.value = true
}

async function submitStatusChange() {
  if (!newStatus.value) return ElMessage.warning('请选择状态')
  submitting.value = true
  try {
    const res = await api.put(`/score-shop/admin/orders/${selectedOrder.value.id}/status`, { status: newStatus.value })
    if (res.code === 0) {
      ElMessage.success('状态更新成功')
      showStatusDialog.value = false
      fetchOrders()
    } else {
      ElMessage.error(res.message || '更新失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '更新失败')
  } finally { submitting.value = false }
}

function resetAndFetch() {
  currentPage.value = 1
  fetchOrders()
}

onMounted(() => {
  fetchOrders()
})
</script>
