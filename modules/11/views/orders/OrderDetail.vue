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
function getStatusType(s) { return statusTypeMap[s] || 'info' }
function getStatusLabel(s) { return statusLabelMap[s] || s }

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { hour12: false })
}
function formatPrice(p) {
  return p != null ? `S$ ${parseFloat(p).toFixed(2)}` : '-'
}

// ─── Dialogs ───────────────────────────────────────────────────────────────────
const shipDialogVisible = ref(false)
const shipForm = ref({ company: '', tracking_no: '' })
const shipLoading = ref(false)

const refundDialogVisible = ref(false)
const refundForm = ref({ amount: '', reason: '' })
const refundLoading = ref(false)

const remarkDialogVisible = ref(false)
const remarkForm = ref({ remark: '' })
const remarkLoading = ref(false)

// ─── Fetch detail ──────────────────────────────────────────────────────────────
async function fetchDetail() {
  if (!orderId.value) return
  loading.value = true
  try {
    const res = await api.get(`/orders/${orderId.value}`)
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

// ─── Timeline ──────────────────────────────────────────────────────────────────
const timeline = computed(() => {
  if (!order.value) return []
  const items = []
  if (order.value.created_at) items.push({ label: '创建订单', time: order.value.created_at })
  if (order.value.paid_at) items.push({ label: '支付完成', time: order.value.paid_at })
  if (order.value.shipped_at) items.push({ label: '已发货', time: order.value.shipped_at })
  if (order.value.completed_at) items.push({ label: '已完成', time: order.value.completed_at })
  if (order.value.cancelled_at) items.push({ label: '已取消', time: order.value.cancelled_at })
  if (order.value.refunded_at) items.push({ label: '已退款', time: order.value.refunded_at })
  return items
})

// ─── Actions ───────────────────────────────────────────────────────────────────
async function confirmPay() {
  try {
    await ElMessageBox.confirm(`确认收到订单 ${order.value.order_no} 的付款？`, '确认支付', { type: 'info' })
    const res = await api.put(`/orders/${orderId.value}/status`, { status: 'paid' })
    if (res.code === 0) {
      ElMessage.success('支付确认成功')
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
    const res = await api.put(`/orders/${orderId.value}/status`, { status: 'cancelled' })
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

async function openShipDialog() {
  shipForm.value = { company: '', tracking_no: '' }
  shipDialogVisible.value = true
}

async function submitShip() {
  if (!shipForm.value.tracking_no) {
    ElMessage.warning('请输入快递单号')
    return
  }
  shipLoading.value = true
  try {
    const res = await api.put(`/orders/${orderId.value}/status`, {
      status: 'shipped',
      tracking_company: shipForm.value.company,
      tracking_no: shipForm.value.tracking_no,
    })
    if (res.code === 0) {
      ElMessage.success('发货成功')
      shipDialogVisible.value = false
      fetchDetail()
    } else {
      ElMessage.error(res.message || '发货失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '发货失败')
  } finally {
    shipLoading.value = false
  }
}

async function openRefundDialog() {
  refundForm.value = {
    amount: order.value.total_amount,
    reason: '',
  }
  refundDialogVisible.value = true
}

async function submitRefund() {
  if (!refundForm.value.amount) {
    ElMessage.warning('请输入退款金额')
    return
  }
  refundLoading.value = true
  try {
    const res = await api.put(`/orders/${orderId.value}/status`, {
      status: 'refunded',
      refund_amount: refundForm.value.amount,
      refund_reason: refundForm.value.reason,
    })
    if (res.code === 0) {
      ElMessage.success('退款成功')
      refundDialogVisible.value = false
      fetchDetail()
    } else {
      ElMessage.error(res.message || '退款失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '退款失败')
  } finally {
    refundLoading.value = false
  }
}

async function confirmReceive() {
  try {
    await ElMessageBox.confirm(`确认收货订单 ${order.value.order_no}？`, '确认收货', { type: 'info' })
    const res = await api.put(`/orders/${orderId.value}/status`, { status: 'completed' })
    if (res.code === 0) {
      ElMessage.success('已确认收货')
      fetchDetail()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '操作失败')
  }
}

async function applyRefund() {
  try {
    await ElMessageBox.confirm(`申请退款订单 ${order.value.order_no}？`, '申请退款', { type: 'warning' })
    const res = await api.put(`/orders/${orderId.value}/status`, { status: 'refunded' })
    if (res.code === 0) {
      ElMessage.success('退款申请已提交')
      fetchDetail()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '操作失败')
  }
}

async function submitRemark() {
  remarkLoading.value = true
  try {
    const res = await api.put(`/orders/${orderId.value}`, { remark: remarkForm.value.remark })
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

// ─── Computed action visibility ────────────────────────────────────────────────
const s = computed(() => order.value?.status)
const canConfirmPay = computed(() => s.value === 'pending_pay')
const canCancel = computed(() => s.value === 'pending_pay')
const canShip = computed(() => s.value === 'paid')
const canRefund = computed(() => s.value === 'paid' || s.value === 'shipped')
const canConfirmReceive = computed(() => s.value === 'shipped')
const canApplyRefund = computed(() => s.value === 'completed')
const isVoid = computed(() => s.value === 'cancelled' || s.value === 'refunded')
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="订单详情" :subtitle="order?.order_no || ''" back />

    <div v-loading="loading" class="space-y-4">
      <!-- Status card -->
      <div v-if="order" class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center gap-4 mb-4">
          <el-tag :type="getStatusType(order.status)" size="large" class="text-sm px-3 py-1">
            {{ getStatusLabel(order.status) }}
          </el-tag>
          <span class="text-gray-500 text-sm">订单号：{{ order.order_no }}</span>
        </div>
        <!-- Timeline -->
        <div v-if="timeline.length" class="flex items-center gap-0 overflow-x-auto">
          <div
            v-for="(item, idx) in timeline"
            :key="item.label"
            class="flex items-center"
          >
            <div class="flex flex-col items-center min-w-fit">
              <div class="w-3 h-3 rounded-full bg-blue-500 mb-1"></div>
              <div class="text-xs text-gray-500 whitespace-nowrap">{{ item.label }}</div>
              <div class="text-xs text-gray-400 whitespace-nowrap">{{ formatDate(item.time) }}</div>
            </div>
            <div
              v-if="idx < timeline.length - 1"
              class="h-0.5 w-16 bg-gray-200 mx-1"
            ></div>
          </div>
        </div>
      </div>

      <!-- Info blocks -->
      <div v-if="order" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Order info -->
        <div class="bg-white rounded-xl shadow-sm p-5">
          <h3 class="font-semibold text-gray-700 mb-3">订单信息</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex gap-2">
              <dt class="text-gray-400 w-24">订单号</dt>
              <dd class="text-gray-800">{{ order.order_no }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="text-gray-400 w-24">创建时间</dt>
              <dd class="text-gray-800">{{ formatDate(order.created_at) }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="text-gray-400 w-24">支付方式</dt>
              <dd class="text-gray-800">{{ order.payment_method || '-' }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="text-gray-400 w-24">配送地址</dt>
              <dd class="text-gray-800">{{ order.shipping_address || order.address || '-' }}</dd>
            </div>
            <div v-if="order.remark" class="flex gap-2">
              <dt class="text-gray-400 w-24">备注</dt>
              <dd class="text-gray-800">{{ order.remark }}</dd>
            </div>
          </dl>
        </div>

        <!-- Buyer info -->
        <div class="bg-white rounded-xl shadow-sm p-5">
          <h3 class="font-semibold text-gray-700 mb-3">买家信息</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex gap-2">
              <dt class="text-gray-400 w-24">买家姓名</dt>
              <dd class="text-gray-800">{{ order.buyer_name || '-' }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="text-gray-400 w-24">联系电话</dt>
              <dd class="text-gray-800">{{ order.buyer_phone || '-' }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="text-gray-400 w-24">买家邮箱</dt>
              <dd class="text-gray-800">{{ order.buyer_email || '-' }}</dd>
            </div>
          </dl>
        </div>

        <!-- Payment info -->
        <div class="bg-white rounded-xl shadow-sm p-5">
          <h3 class="font-semibold text-gray-700 mb-3">支付信息</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex gap-2">
              <dt class="text-gray-400 w-24">商品总额</dt>
              <dd class="text-gray-800">{{ formatPrice(order.subtotal) }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="text-gray-400 w-24">运费</dt>
              <dd class="text-gray-800">{{ formatPrice(order.shipping_fee) }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="text-gray-400 w-24">优惠</dt>
              <dd class="text-gray-800">{{ formatPrice(order.discount) }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="text-gray-400 w-24">实付金额</dt>
              <dd class="text-blue-600 font-semibold">{{ formatPrice(order.total_amount) }}</dd>
            </div>
            <div v-if="order.paid_at" class="flex gap-2">
              <dt class="text-gray-400 w-24">支付时间</dt>
              <dd class="text-gray-800">{{ formatDate(order.paid_at) }}</dd>
            </div>
          </dl>
        </div>

        <!-- Logistics info -->
        <div v-if="order.tracking_no" class="bg-white rounded-xl shadow-sm p-5">
          <h3 class="font-semibold text-gray-700 mb-3">物流信息</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex gap-2">
              <dt class="text-gray-400 w-24">物流公司</dt>
              <dd class="text-gray-800">{{ order.tracking_company || '-' }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="text-gray-400 w-24">快递单号</dt>
              <dd class="text-gray-800">{{ order.tracking_no }}</dd>
            </div>
            <div v-if="order.shipped_at" class="flex gap-2">
              <dt class="text-gray-400 w-24">发货时间</dt>
              <dd class="text-gray-800">{{ formatDate(order.shipped_at) }}</dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Items table -->
      <div v-if="order" class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-700">商品列表</h3>
        </div>
        <el-table :data="order.items || []" stripe>
          <el-table-column label="商品图片" width="80">
            <template #default="{ row }">
              <img
                v-if="row.image_url"
                :src="row.image_url"
                class="w-12 h-12 object-cover rounded"
                alt=""
              />
              <div v-else class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center text-gray-400 text-xs">无图</div>
            </template>
          </el-table-column>
          <el-table-column label="商品名称" min-width="180">
            <template #default="{ row }">
              <div class="font-medium text-gray-800">{{ row.product_name || row.name }}</div>
              <div v-if="row.sku" class="text-xs text-gray-400">{{ row.sku }}</div>
            </template>
          </el-table-column>
          <el-table-column label="规格" min-width="120">
            <template #default="{ row }">
              <span class="text-gray-600">{{ row.specification || row.spec || '-' }}</span>
            </template>
          </el-table-column>
          <el-table-column label="单价" width="120" align="right">
            <template #default="{ row }">
              <span>{{ formatPrice(row.unit_price) }}</span>
            </template>
          </el-table-column>
          <el-table-column label="数量" width="80" align="center">
            <template #default="{ row }">
              <span>×{{ row.quantity }}</span>
            </template>
          </el-table-column>
          <el-table-column label="小计" width="120" align="right">
            <template #default="{ row }">
              <span class="font-medium text-blue-600">{{ formatPrice(row.subtotal || (row.unit_price * row.quantity)) }}</span>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <!-- Action bar -->
      <div v-if="order && !isVoid" class="bg-white rounded-xl shadow-sm p-5">
        <div class="flex flex-wrap gap-3">
          <el-button v-if="canConfirmPay" type="success" @click="confirmPay">确认支付</el-button>
          <el-button v-if="canCancel" type="warning" @click="cancelOrder">取消订单</el-button>
          <el-button v-if="canShip" type="primary" @click="openShipDialog">发货</el-button>
          <el-button v-if="canRefund" type="danger" @click="openRefundDialog">退款</el-button>
          <el-button v-if="canConfirmReceive" type="success" @click="confirmReceive">确认收货</el-button>
          <el-button v-if="canApplyRefund" type="danger" @click="applyRefund">申请退款</el-button>
          <el-button @click="remarkDialogVisible = true">编辑备注</el-button>
          <el-button link @click="router.back()">返回</el-button>
        </div>
      </div>
    </div>

    <!-- Ship dialog -->
    <el-dialog v-model="shipDialogVisible" title="发货" width="440px" align-center>
      <el-form label-width="90px" class="space-y-4">
        <el-form-item label="物流公司">
          <el-select v-model="shipForm.company" placeholder="请选择" class="!w-full">
            <el-option label="顺丰速运" value="顺丰速运" />
            <el-option label="圆通速递" value="圆通速递" />
            <el-option label="中通快递" value="中通快递" />
            <el-option label="韵达快递" value="韵达快递" />
            <el-option label="申通快递" value="申通快递" />
            <el-option label="极兔速递" value="极兔速递" />
            <el-option label="DHL" value="DHL" />
            <el-option label="Ninja Van" value="Ninja Van" />
            <el-option label="其他" value="其他" />
          </el-select>
        </el-form-item>
        <el-form-item label="快递单号">
          <el-input v-model="shipForm.tracking_no" placeholder="请输入快递单号" clearable />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="shipDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="shipLoading" @click="submitShip">确认发货</el-button>
      </template>
    </el-dialog>

    <!-- Refund dialog -->
    <el-dialog v-model="refundDialogVisible" title="退款" width="440px" align-center>
      <el-form label-width="90px" class="space-y-4">
        <el-form-item label="退款金额">
          <el-input-number
            v-model="refundForm.amount"
            :min="0.01"
            :precision="2"
            :step="1"
            class="!w-full"
          />
        </el-form-item>
        <el-form-item label="退款原因">
          <el-input
            v-model="refundForm.reason"
            type="textarea"
            :rows="3"
            placeholder="请输入退款原因（可选）"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="refundDialogVisible = false">取消</el-button>
        <el-button type="danger" :loading="refundLoading" @click="submitRefund">确认退款</el-button>
      </template>
    </el-dialog>

    <!-- Remark dialog -->
    <el-dialog v-model="remarkDialogVisible" title="编辑备注" width="440px" align-center>
      <el-form label-width="80px">
        <el-form-item label="备注">
          <el-input
            v-model="remarkForm.remark"
            type="textarea"
            :rows="4"
            placeholder="请输入备注信息"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="remarkDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="remarkLoading" @click="submitRemark">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>