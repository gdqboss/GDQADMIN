<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const records = ref([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)
const dateRange = ref([])
const dialogVisible = ref(false)
const checkoutDialogVisible = ref(false)
const checkoutForm = ref({ order_type: 'dine', order_id: null, received_amount: 0, pay_type: 'cash', discount_amount: 0 })
constdineOrders = ref([])
const takeoutOrders = ref([])
const selectedOrder = ref(null)

async function fetchRecords() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value }
    if (dateRange.value?.length === 2) { params.date_start = dateRange.value[0]; params.date_end = dateRange.value[1] }
    const res = await api.get('/restaurant/cashier', { params })
    records.value = res.data || []
    total.value = res.data?.total ?? records.value.length
  } catch (e) { ElMessage.error(e.message || '获取收银记录失败') }
  finally { loading.value = false }
}

async function fetchDineOrders() {
  try {
    const res = await api.get('/restaurant/dine-orders', { params: { status: 'served' } })
    dineOrders.value = res.data || []
  } catch (e) {}
}

async function fetchTakeoutOrders() {
  try {
    const res = await api.get('/restaurant/takeout-orders', { params: { status: 'pending' } })
    takeoutOrders.value = res.data || []
  } catch (e) {}
}

function openCheckout() {
  checkoutForm.value = { order_type: 'dine', order_id: null, received_amount: 0, pay_type: 'cash', discount_amount: 0 }
  selectedOrder.value = null
  checkoutDialogVisible.value = true
  fetchDineOrders()
  fetchTakeoutOrders()
}

function selectOrder(order, type) {
  selectedOrder.value = { ...order, order_type: type }
  checkoutForm.value.order_type = type
  checkoutForm.value.order_id = order.id
  checkoutForm.value.received_amount = order.pay_amount || order.total_amount
  checkoutForm.value.discount_amount = 0
}

function orderTotal() {
  if (!selectedOrder.value) return '0.00'
  const total = parseFloat(selectedOrder.value.pay_amount || selectedOrder.value.total_amount || 0)
  const discount = parseFloat(checkoutForm.value.discount_amount || 0)
  return (total - discount).toFixed(2)
}

async function submitCheckout() {
  if (!checkoutForm.value.order_id) { ElMessage.warning('请选择订单'); return }
  try {
    await api.post('/restaurant/cashier', checkoutForm.value)
    ElMessage.success('结账成功')
    checkoutDialogVisible.value = false
    fetchRecords()
  } catch (e) { ElMessage.error(e.message || '结账失败') }
}

onMounted(fetchRecords)
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">收银管理</h1>
      <el-button type="primary" @click="openCheckout">收银结账</el-button>
    </div>

    <!-- Filter -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4 flex gap-4 items-end">
      <div>
        <label class="block text-xs text-gray-500 mb-1">日期范围</label>
        <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD" class="!w-72" />
      </div>
      <el-button @click="currentPage = 1; fetchRecords()">查询</el-button>
    </div>

    <!-- Records table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table v-loading="loading" :data="records" stripe>
        <el-table-column label="收银单号" prop="record_no" min-width="160" />
        <el-table-column label="订单类型" width="100">
          <template #default="{ row }">
            <el-tag :type="row.order_type === 'dine' ? 'success' : 'warning'" size="small">
              {{ row.order_type === 'dine' ? '堂食' : '外卖' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="订单号" :prop="`order_id`" min-width="130">
          <template #default="{ row }">#{{ row.order_id }}</template>
        </el-table-column>
        <el-table-column label="总金额" width="100" align="right">
          <template #default="{ row }"><span class="text-gray-400 line-through">S$ {{ row.total_amount }}</span></template>
        </el-table-column>
        <el-table-column label="优惠" width="80" align="right">
          <template #default="{ row }"><span class="text-green-500">-S$ {{ row.discount_amount }}</span></template>
        </el-table-column>
        <el-table-column label="实收" width="100" align="right">
          <template #default="{ row }"><span class="text-orange-500 font-bold">S$ {{ row.received_amount }}</span></template>
        </el-table-column>
        <el-table-column label="找零" width="80" align="right">
          <template #default="{ row }"><span class="text-gray-400">S$ {{ row.change_amount }}</span></template>
        </el-table-column>
        <el-table-column label="支付方式" prop="pay_type" width="80" />
        <el-table-column label="操作员" prop="operator_name" width="90" />
        <el-table-column label="收银时间" width="160">
          <template #default="{ row }">{{ new Date(row.created_at).toLocaleString('zh-CN', { hour12: false }) }}</template>
        </el-table-column>
      </el-table>
      <div class="flex justify-end p-4 border-t">
        <el-pagination v-model:current-page="currentPage" :page-size="pageSize" :total="total" layout="total, prev, pager, next" background @current-change="fetchRecords" />
      </div>
    </div>

    <!-- Checkout dialog -->
    <el-dialog v-model="checkoutDialogVisible" title="收银结账" width="700">
      <div class="flex gap-6">
        <!-- Order selection -->
        <div class="w-64">
          <div class="font-medium mb-3 text-sm text-gray-600">选择订单</div>
          <el-tabs>
            <el-tab-pane label="堂食">
              <div class="max-h-60 overflow-y-auto space-y-2">
                <div v-for="o in dineOrders" :key="o.id"
                  class="border rounded p-2 cursor-pointer hover:bg-gray-50 text-sm"
                  :class="selectedOrder?.id === o.id && selectedOrder?.order_type === 'dine' ? 'border-blue-500 bg-blue-50' : ''"
                  @click="selectOrder(o, 'dine')">
                  <div class="flex justify-between">
                    <span class="font-medium">{{ o.table_name }}</span>
                    <span class="text-orange-500">S$ {{ o.total_amount }}</span>
                  </div>
                  <div class="text-xs text-gray-400">{{ o.order_no }}</div>
                </div>
                <div v-if="!dineOrders.length" class="text-gray-400 text-sm text-center py-4">暂无待结账堂食</div>
              </div>
            </el-tab-pane>
            <el-tab-pane label="外卖">
              <div class="max-h-60 overflow-y-auto space-y-2">
                <div v-for="o in takeoutOrders" :key="o.id"
                  class="border rounded p-2 cursor-pointer hover:bg-gray-50 text-sm"
                  :class="selectedOrder?.id === o.id && selectedOrder?.order_type === 'takeout' ? 'border-blue-500 bg-blue-50' : ''"
                  @click="selectOrder(o, 'takeout')">
                  <div class="flex justify-between">
                    <span>{{ o.customer_name }}</span>
                    <span class="text-orange-500">S$ {{ o.pay_amount }}</span>
                  </div>
                  <div class="text-xs text-gray-400">{{ o.order_no }}</div>
                </div>
                <div v-if="!takeoutOrders.length" class="text-gray-400 text-sm text-center py-4">暂无待结账外卖</div>
              </div>
            </el-tab-pane>
          </el-tabs>
        </div>
        <!-- Payment form -->
        <div class="flex-1">
          <div v-if="!selectedOrder" class="text-gray-400 text-center py-12">请从左侧选择订单</div>
          <div v-else>
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
              <div class="flex justify-between mb-2">
                <span class="text-gray-500">订单类型</span>
                <span>{{ selectedOrder.order_type === 'dine' ? '堂食' : '外卖' }}</span>
              </div>
              <div class="flex justify-between mb-2">
                <span class="text-gray-500">订单号</span>
                <span>{{ selectedOrder.order_no }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">应付金额</span>
                <span class="text-orange-500 font-bold text-lg">S$ {{ selectedOrder.pay_amount || selectedOrder.total_amount }}</span>
              </div>
            </div>
            <el-form :model="checkoutForm" label-width="90">
              <el-form-item label="实收金额">
                <el-input-number v-model="checkoutForm.received_amount" :min="0" :precision="2" class="!w-full" />
              </el-form-item>
              <el-form-item label="优惠金额">
                <el-input-number v-model="checkoutForm.discount_amount" :min="0" :precision="2" class="!w-full" />
              </el-form-item>
              <el-form-item label="支付方式">
                <el-select v-model="checkoutForm.pay_type" class="!w-full">
                  <el-option label="现金" value="cash" />
                  <el-option label="银行卡" value="card" />
                  <el-option label="微信" value="wechat" />
                  <el-option label="支付宝" value="alipay" />
                </el-select>
              </el-form-item>
              <div class="bg-blue-50 rounded-lg p-4 mt-4">
                <div class="flex justify-between text-sm mb-1">
                  <span>应收金额</span><span>S$ {{ orderTotal() }}</span>
                </div>
                <div class="flex justify-between text-sm mb-1">
                  <span>实收</span><span>S$ {{ checkoutForm.received_amount }}</span>
                </div>
                <div class="flex justify-between text-lg font-bold">
                  <span>找零</span><span class="text-green-500">S$ {{ Math.max(0, checkoutForm.received_amount - parseFloat(orderTotal())).toFixed(2) }}</span>
                </div>
              </div>
            </el-form>
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="checkoutDialogVisible = false">取消</el-button>
        <el-button type="primary" :disabled="!selectedOrder" @click="submitCheckout">确认结账</el-button>
      </template>
    </el-dialog>
  </div>
</template>