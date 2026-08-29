<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { ElMessage, ElMessageBox } from 'element-plus'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

const orderId = computed(() => route.params.id)
const loading = ref(false)
const order = ref(null)

// ─── Status ────────────────────────────────────────────────────────────────────
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

function getStatusType(s) { return statusTypeMap[s] || 'info' }
function getStatusLabel(s) { return statusLabelMap[s] || s }

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { hour12: false })
}
function formatPrice(p) {
  return p != null ? `¥ ${parseFloat(p).toFixed(2)}` : '-'
}

// ─── Dialogs ───────────────────────────────────────────────────────────────────
const checkinDialogVisible = ref(false)
const checkoutDialogVisible = ref(false)
const actionLoading = ref(false)

const remarkDialogVisible = ref(false)
const remarkForm = ref({ remark: '' })
const remarkLoading = ref(false)

// ─── Fetch detail ─────────────────────────────────────────────────────────────
async function fetchDetail() {
  if (!orderId.value) return
  loading.value = true
  try {
    const res = await api.get(`/hotel/orders/${orderId.value}`)
    if (res.code === 0) {
      order.value = res.data
      remarkForm.value.remark = res.data.remark || ''
    } else {
      ElMessage.error(res.message || '获取订单详情失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '获取订单详情失败')
  } finally {
    loading.value = false
  }
}

onMounted(fetchDetail)

// ─── Timeline ─────────────────────────────────────────────────────────────────
const timeline = computed(() => {
  if (!order.value) return []
  const items = []
  if (order.value.created_at) items.push({ label: '创建订单', time: order.value.created_at })
  if (order.value.confirmed_at) items.push({ label: '已确认', time: order.value.confirmed_at })
  if (order.value.checked_in_at) items.push({ label: '已入住', time: order.value.checked_in_at })
  if (order.value.checked_out_at) items.push({ label: '已退房', time: order.value.checked_out_at })
  if (order.value.cancelled_at) items.push({ label: '已取消', time: order.value.cancelled_at })
  return items
})

// ─── Actions ───────────────────────────────────────────────────────────────────
async function confirmOrder() {
  try {
    await ElMessageBox.confirm(`确定确认订单 ${order.value.order_no}？`, '确认订单', { type: 'info' })
    const res = await api.put(`/hotel/orders/${orderId.value}/status`, { status: 'confirmed' })
    if (res.code === 0) {
      ElMessage.success('订单已确认')
      fetchDetail()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '操作失败')
  }
}

async function cancelOrder() {
  try {
    await ElMessageBox.confirm(`确定取消订单 ${order.value.order_no}？`, '取消订单', { type: 'warning' })
    const res = await api.put(`/hotel/orders/${orderId.value}/status`, { status: 'cancelled' })
    if (res.code === 0) {
      ElMessage.success('订单已取消')
      fetchDetail()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '操作失败')
  }
}

async function checkinOrder() {
  actionLoading.value = true
  try {
    const res = await api.put(`/hotel/orders/${orderId.value}/status`, { status: 'checked_in' })
    if (res.code === 0) {
      ElMessage.success('入住登记成功')
      checkinDialogVisible.value = false
      fetchDetail()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  } finally {
    actionLoading.value = false
  }
}

async function checkoutOrder() {
  actionLoading.value = true
  try {
    const res = await api.put(`/hotel/orders/${orderId.value}/status`, { status: 'checked_out' })
    if (res.code === 0) {
      ElMessage.success('退房成功')
      checkoutDialogVisible.value = false
      fetchDetail()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  } finally {
    actionLoading.value = false
  }
}

async function saveRemark() {
  remarkLoading.value = true
  try {
    const res = await api.put(`/hotel/orders/${orderId.value}`, { remark: remarkForm.value.remark })
    if (res.code === 0) {
      ElMessage.success('备注已保存')
      remarkDialogVisible.value = false
      fetchDetail()
    } else {
      ElMessage.error(res.message || '保存失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    remarkLoading.value = false
  }
}

function canConfirm(status) { return status === 'pending' }
function canCancel(status) { return status === 'pending' || status === 'confirmed' }
function canCheckin(status) { return status === 'confirmed' }
function canCheckout(status) { return status === 'checked_in' }
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="订单详情" :subtitle="`酒店订单 ${order?.order_no || ''}`" />

    <div v-if="loading" class="flex justify-center py-20">
      <el-icon class="is-loading text-3xl text-blue-600"><Loading /></el-icon>
    </div>

    <div v-else-if="order" class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="text-xl font-semibold">订单号：{{ order.order_no }}</h2>
            <p class="text-sm text-gray-500 mt-1">创建于 {{ formatDate(order.created_at) }}</p>
          </div>
          <el-tag :type="getStatusType(order.status)" size="large">
            {{ getStatusLabel(order.status) }}
          </el-tag>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t">
          <el-button v-if="canConfirm(order.status)" type="success" @click="confirmOrder">确认订单</el-button>
          <el-button v-if="canCheckin(order.status)" type="primary" @click="checkinDialogVisible = true">办理入住</el-button>
          <el-button v-if="canCheckout(order.status)" type="warning" @click="checkoutDialogVisible = true">办理退房</el-button>
          <el-button v-if="canCancel(order.status)" type="danger" @click="cancelOrder">取消订单</el-button>
          <el-button @click="remarkDialogVisible = true">备注</el-button>
         <el-button @click="router.back()">返回</el-button>
        </div>
      </div>

      <!-- Info grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Guest info -->
        <div class="bg-white rounded-xl shadow-sm p-6">
          <h3 class="text-base font-semibold mb-4">客人信息</h3>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-gray-500">姓名</span>
              <span class="font-medium">{{ order.guest_name || '-' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">电话</span>
              <span>{{ order.guest_phone || '-' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">证件类型</span>
              <span>{{ order.id_type || '-' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">证件号</span>
              <span>{{ order.id_number || '-' }}</span>
            </div>
          </div>
        </div>

        <!-- Room info -->
        <div class="bg-white rounded-xl shadow-sm p-6">
          <h3 class="text-base font-semibold mb-4">房间信息</h3>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-gray-500">房型</span>
              <span class="font-medium">{{ order.room_type_name || '-' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">入住日期</span>
              <span>{{ order.checkin_date || '-' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">退房日期</span>
              <span>{{ order.checkout_date || '-' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">入住人数</span>
              <span>{{ order.guest_count || 1 }}人</span>
            </div>
          </div>
        </div>

        <!-- Payment info -->
        <div class="bg-white rounded-xl shadow-sm p-6">
          <h3 class="text-base font-semibold mb-4">费用信息</h3>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-gray-500">房费</span>
              <span>{{ formatPrice(order.room_price) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">押金</span>
              <span>{{ formatPrice(order.deposit) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">加床费</span>
              <span>{{ formatPrice(order.extra_bed_fee) }}</span>
            </div>
            <div class="flex justify-between border-t pt-2">
              <span class="font-semibold">合计</span>
              <span class="text-lg font-bold text-blue-600">{{ formatPrice(order.total_amount) }}</span>
            </div>
            <div v-if="order.remark" class="flex justify-between">
              <span class="text-gray-500">备注</span>
              <span class="text-gray-600">{{ order.remark }}</span>
            </div>
          </div>
        </div>

        <!-- Timeline -->
        <div class="bg-white rounded-xl shadow-sm p-6">
          <h3 class="text-base font-semibold mb-4">订单日志</h3>
          <div class="space-y-4">
            <div v-for="(item, idx) in timeline" :key="idx" class="flex gap-3">
              <div class="flex flex-col items-center">
                <div class="w-2 h-2 rounded-full bg-blue-500 mt-1.5"></div>
                <div v-if="idx < timeline.length - 1" class="w-px flex-1 bg-gray-200 mt-1"></div>
              </div>
              <div>
                <div class="text-sm font-medium">{{ item.label }}</div>
                <div class="text-xs text-gray-400">{{ formatDate(item.time) }}</div>
              </div>
            </div>
            <div v-if="timeline.length === 0" class="text-center text-gray-400 py-4">暂无日志</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Checkin dialog -->
    <el-dialog v-model="checkinDialogVisible" title="办理入住" width="400px">
      <p>确定要为订单<strong>{{ order?.order_no }}</strong> 办理入住？</p>
      <template #footer>
        <el-button @click="checkinDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="actionLoading" @click="checkinOrder">确认入住</el-button>
      </template>
    </el-dialog>

    <!-- Checkout dialog -->
    <el-dialog v-model="checkoutDialogVisible" title="办理退房" width="400px">
      <p>确定要为订单 <strong>{{ order?.order_no }}</strong> 办理退房？</p>
      <template #footer>
        <el-button @click="checkoutDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="actionLoading" @click="checkoutOrder">确认退房</el-button>
      </template>
    </el-dialog>

    <!-- Remark dialog -->
    <el-dialog v-model="remarkDialogVisible" title="订单备注" width="400px">
      <el-input
        v-model="remarkForm.remark"
        type="textarea"
        :rows="4"
        placeholder="请输入订单备注..."
      />
      <template #footer>
        <el-button @click="remarkDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="remarkLoading" @click="saveRemark">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>