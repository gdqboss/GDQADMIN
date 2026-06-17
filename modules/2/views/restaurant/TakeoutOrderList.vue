<script setup>
import { ref, onMounted, watch } from 'vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const tabs = [
  { key: 'all', label: '全部' },
  { key: 'pending', label: '待确认' },
  { key: 'confirmed', label: '已确认' },
  { key: 'preparing', label: '制作中' },
  { key: 'delivering', label: '配送中' },
  { key: 'completed', label: '已完成' },
]
const activeTab = ref('all')
const orders = ref([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)
const searchKeyword = ref('')
const dateRange = ref([])
const dialogVisible = ref(false)
const form = ref({ customer_name: '', customer_phone: '', delivery_address: '', items: [], freight_amount: 0, discount_amount: 0, pay_type: 'cash', remark: '' })
const dishes = ref([])

const statusMap = {
  pending: { label: '待确认', type: 'warning' },
  confirmed: { label: '已确认', type: 'primary' },
  preparing: { label: '制作中', type: 'info' },
  delivering: { label: '配送中', type: 'primary' },
  completed: { label: '已完成', type: 'success' },
  cancelled: { label: '已取消', type: 'info' },
}

async function fetchOrders() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value }
    if (activeTab.value !== 'all') params.status = activeTab.value
    if (searchKeyword.value) params.keyword = searchKeyword.value
    if (dateRange.value?.length === 2) { params.date_start = dateRange.value[0]; params.date_end = dateRange.value[1] }
    const res = await api.get('/restaurant/takeout-orders', { params })
    orders.value = res.data || []
    total.value = res.data?.total ?? orders.value.length
  } catch (e) { ElMessage.error(e.message || '获取订单失败') }
  finally { loading.value = false }
}

async function fetchDishes() {
  try {
    const res = await api.get('/restaurant/dishes', { params: { is_available: 'yes' } })
    dishes.value = res.data || []
  } catch (e) {}
}

function openNew() {
  form.value = { customer_name: '', customer_phone: '', delivery_address: '', items: [], freight_amount: 0, discount_amount: 0, pay_type: 'cash', remark: '' }
  dialogVisible.value = true
  fetchDishes()
}

function addDishItem(dish) {
  const exist = form.value.items.find(i => i.dish_id === dish.id)
  if (exist) { exist.number++ } else { form.value.items.push({ dish_id: dish.id, dish_name: dish.name, unit: dish.unit, price: dish.price, number: 1 }) }
}

function removeItem(idx) { form.value.items.splice(idx, 1) }

function orderTotal() {
  const goods = form.value.items.reduce((s, i) => s + i.price * i.number, 0)
  return (goods + parseFloat(form.value.freight_amount || 0) - parseFloat(form.value.discount_amount || 0)).toFixed(2)
}

async function submitOrder() {
  if (!form.value.customer_name || !form.value.customer_phone) { ElMessage.warning('请填写客户信息'); return }
  if (!form.value.items.length) { ElMessage.warning('请选择菜品'); return }
  try {
    await api.post('/restaurant/takeout-orders', form.value)
    ElMessage.success('创建成功')
    dialogVisible.value = false
    fetchOrders()
  } catch (e) { ElMessage.error(e.message || '创建失败') }
}

async function changeStatus(row, action, label) {
  if (!confirm(`确定${label}订单 ${row.order_no}？`)) return
  try {
    await api.put(`/restaurant/takeout-orders/${row.id}/status`, { action })
    ElMessage.success(`${label}成功`)
    fetchOrders()
  } catch (e) { ElMessage.error(e.message || '操作失败') }
}

function canAction(status, action) {
  if (action === 'confirm') return status === 'pending'
  if (action === 'prepare') return status === 'confirmed'
  if (action === 'deliver') return status === 'preparing'
  if (action === 'complete') return status === 'delivering'
  if (action === 'cancel') return ['pending', 'confirmed'].includes(status)
  return false
}

watch([activeTab, searchKeyword, dateRange], () => { currentPage.value = 1; fetchOrders() })
watch(currentPage, fetchOrders)
onMounted(fetchOrders)
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">外卖订单</h1>
      <el-button type="primary" @click="openNew">新建外卖单</el-button>
    </div>

    <!-- Search -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4 flex flex-wrap gap-4 items-end">
      <div>
        <label class="block text-xs text-gray-500 mb-1">关键词</label>
        <el-input v-model="searchKeyword" placeholder="订单号/客户名/电话" clearable class="!w-56" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">日期</label>
        <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始" end-placeholder="结束" value-format="YYYY-MM-DD" class="!w-72" />
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
      <div class="flex overflow-x-auto">
        <button v-for="tab in tabs" :key="tab.key"
          class="px-6 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
          :class="activeTab === tab.key ? 'border-blue-600 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="activeTab = tab.key">{{ tab.label }}</button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table v-loading="loading" :data="orders" stripe>
        <el-table-column label="订单号" prop="order_no" min-width="150" />
        <el-table-column label="客户" min-width="140">
          <template #default="{ row }">
            <div class="text-sm">{{ row.customer_name }}</div>
            <div class="text-gray-400 text-xs">{{ row.customer_phone }}</div>
          </template>
        </el-table-column>
        <el-table-column label="地址" prop="delivery_address" min-width="180" show-overflow-tooltip />
        <el-table-column label="总金额" width="110" align="right">
          <template #default="{ row }"><span class="text-orange-500 font-medium">S$ {{ row.pay_amount }}</span></template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusMap[row.status]?.type" size="small">{{ statusMap[row.status]?.label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="下单时间" width="160">
          <template #default="{ row }">{{ new Date(row.created_at).toLocaleString('zh-CN', { hour12: false }) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <div class="flex flex-wrap gap-1">
              <el-button v-if="canAction(row.status, 'confirm')" size="small" link type="primary" @click="changeStatus(row, 'confirm', '接单')">接单</el-button>
              <el-button v-if="canAction(row.status, 'prepare')" size="small" link type="primary" @click="changeStatus(row, 'prepare', '制作')">制作</el-button>
              <el-button v-if="canAction(row.status, 'deliver')" size="small" link type="primary" @click="changeStatus(row, 'deliver', '配送')">配送</el-button>
              <el-button v-if="canAction(row.status, 'complete')" size="small" link type="success" @click="changeStatus(row, 'complete', '完成')">完成</el-button>
              <el-button v-if="canAction(row.status, 'cancel')" size="small" link type="danger" @click="changeStatus(row, 'cancel', '取消')">取消</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
      <div class="flex justify-end p-4 border-t">
        <el-pagination v-model:current-page="currentPage" :page-size="pageSize" :total="total" layout="total, prev, pager, next" background />
      </div>
    </div>

    <!-- New order dialog -->
    <el-dialog v-model="dialogVisible" title="新建外卖单" width="800">
      <div class="flex gap-6">
        <div class="flex-1">
          <el-form :model="form" label-width="90">
            <el-form-item label="客户姓名"><el-input v-model="form.customer_name" /></el-form-item>
            <el-form-item label="客户电话"><el-input v-model="form.customer_phone" /></el-form-item>
            <el-form-item label="配送地址"><el-input v-model="form.delivery_address" type="textarea" /></el-form-item>
            <el-form-item label="支付方式">
              <el-select v-model="form.pay_type">
                <el-option label="现金" value="cash" />
                <el-option label="银行卡" value="card" />
                <el-option label="微信" value="wechat" />
                <el-option label="支付宝" value="alipay" />
              </el-select>
            </el-form-item>
            <el-form-item label="配送费"><el-input-number v-model="form.freight_amount" :min="0" :precision="2" /></el-form-item>
            <el-form-item label="优惠"><el-input-number v-model="form.discount_amount" :min="0" :precision="2" /></el-form-item>
            <el-form-item label="备注"><el-input v-model="form.remark" type="textarea" /></el-form-item>
          </el-form>
        </div>
        <div class="w-72">
          <div class="font-medium mb-2 text-sm text-gray-600">选择菜品</div>
          <div class="grid grid-cols-2 gap-2 max-h-72 overflow-y-auto">
            <div v-for="d in dishes" :key="d.id" class="border rounded p-2 cursor-pointer hover:bg-gray-50 text-sm" @click="addDishItem(d)">
              <div class="font-medium truncate">{{ d.name }}</div>
              <div class="text-orange-500 text-xs">S$ {{ d.price }}</div>
            </div>
          </div>
          <div class="mt-3">
            <div v-for="(item, idx) in form.items" :key="item.dish_id" class="flex justify-between items-center py-1 text-sm border-b">
              <div class="truncate flex-1">{{ item.dish_name }}</div>
              <div class="text-gray-500 text-xs">×{{ item.number }}</div>
              <el-button size="small" link type="danger" @click="removeItem(idx)">删</el-button>
            </div>
          </div>
          <div class="mt-2 font-medium text-right text-orange-500">实付: S$ {{ orderTotal() }}</div>
        </div>
      </div>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitOrder">提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>