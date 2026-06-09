<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const tabs = [
  { key: 'all', label: '全部' },
  { key: 'ordering', label: '点单中' },
  { key: 'confirmed', label: '已确认' },
  { key: 'preparing', label: '制作中' },
  { key: 'served', label: '已上菜' },
  { key: 'completed', label: '已完成' },
]
const activeTab = ref('all')
const orders = ref([])
const tables = ref([])
const dishes = ref([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)

const dialogVisible = ref(false)
const orderDialogVisible = ref(false)
const selectedTable = ref(null)
const cartItems = ref([])

const statusMap = {
  ordering: { label: '点单中', type: 'warning' },
  confirmed: { label: '已确认', type: 'primary' },
  preparing: { label: '制作中', type: 'info' },
  served: { label: '已上菜', type: 'success' },
  completed: { label: '已完成', type: 'info' },
  cancelled: { label: '已取消', type: 'info' },
}

async function fetchOrders() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value }
    if (activeTab.value !== 'all') params.status = activeTab.value
    const res = await api.get('/restaurant/dine-orders', { params })
    orders.value = res.data || []
    total.value = res.data?.total ?? orders.value.length
  } catch (e) { ElMessage.error(e.message || '获取订单失败') }
  finally { loading.value = false }
}

async function fetchTables() {
  try {
    const res = await api.get('/restaurant/tables')
    tables.value = res.data || []
  } catch (e) {}
}

async function fetchDishes() {
  try {
    const res = await api.get('/restaurant/dishes', { params: { is_available: 'yes' } })
    dishes.value = res.data || []
  } catch (e) {}
}

function openNewOrder() {
  selectedTable.value = null
  cartItems.value = []
  orderDialogVisible.value = true
  fetchTables()
  fetchDishes()
}

function selectTable(t) {
  selectedTable.value = t
}

function addToCart(dish) {
  const exist = cartItems.value.find(c => c.dish_id === dish.id)
  if (exist) {
    exist.number++
  } else {
    cartItems.value.push({ dish_id: dish.id, dish_name: dish.name, unit: dish.unit, price: dish.price, number: 1 })
  }
}

function removeCart(item) {
  if (item.number > 1) { item.number-- } else { cartItems.value = cartItems.value.filter(c => c.dish_id !== item.dish_id) }
}

function cartTotal() {
  return cartItems.value.reduce((s, c) => s + c.price * c.number, 0).toFixed(2)
}

async function submitOrder() {
  if (!selectedTable.value) { ElMessage.warning('请选择桌台'); return }
  if (!cartItems.value.length) { ElMessage.warning('请添加菜品'); return }
  try {
    await api.post('/restaurant/dine-orders', {
      table_id: selectedTable.value.id,
      customer_count: 1,
      items: cartItems.value,
    })
    ElMessage.success('开台成功')
    orderDialogVisible.value = false
    fetchOrders()
  } catch (e) { ElMessage.error(e.message || '开台失败') }
}

async function changeStatus(row, action, label) {
  if (!confirm(`确定${label}订单 ${row.order_no}？`)) return
  try {
    await api.put(`/restaurant/dine-orders/${row.id}/status`, { action })
    ElMessage.success(`${label}成功`)
    fetchOrders()
  } catch (e) { ElMessage.error(e.message || '操作失败') }
}

function canAction(status, action) {
  if (action === 'confirm') return status === 'ordering'
  if (action === 'prepare') return status === 'confirmed'
  if (action === 'serve') return status === 'preparing'
  if (action === 'complete') return ['served', 'confirmed'].includes(status)
  if (action === 'cancel') return ['ordering', 'confirmed'].includes(status)
  return false
}

import { watch } from 'vue'
watch([activeTab], () => { currentPage.value = 1; fetchOrders() })
watch(currentPage, fetchOrders)
onMounted(fetchOrders)
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">堂食点餐</h1>
      <el-button type="primary" @click="openNewOrder">开台点餐</el-button>
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
        <el-table-column label="桌台" prop="table_name" width="100" />
        <el-table-column label="人数" prop="customer_count" width="60" />
        <el-table-column label="总金额" width="110" align="right">
          <template #default="{ row }"><span class="text-orange-500 font-medium">S$ {{ row.total_amount }}</span></template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusMap[row.status]?.type" size="small">{{ statusMap[row.status]?.label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="下单时间" width="160">
          <template #default="{ row }">{{ new Date(row.created_at).toLocaleString('zh-CN', { hour12: false }) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <div class="flex flex-wrap gap-1">
              <el-button v-if="canAction(row.status, 'confirm')" size="small" link type="primary" @click="changeStatus(row, 'confirm', '确认')">确认</el-button>
              <el-button v-if="canAction(row.status, 'prepare')" size="small" link type="primary" @click="changeStatus(row, 'prepare', '开始制作')">制作</el-button>
              <el-button v-if="canAction(row.status, 'serve')" size="small" link type="success" @click="changeStatus(row, 'serve', '上菜')">上菜</el-button>
              <el-button v-if="canAction(row.status, 'complete')" size="small" link type="warning" @click="changeStatus(row, 'complete', '结账')">结账</el-button>
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
    <el-dialog v-model="orderDialogVisible" title="开台点餐" width="900">
      <div class="flex gap-6">
        <!-- Table selection -->
        <div class="w-48">
          <div class="font-medium mb-2 text-sm text-gray-600">选择桌台</div>
          <div class="grid grid-cols-2 gap-2">
            <div v-for="t in tables" :key="t.id"
              class="border rounded p-2 text-center text-sm cursor-pointer"
              :class="selectedTable?.id === t.id ? 'border-blue-500 bg-blue-50' : t.status === 'idle' ? 'border-green-300 hover:bg-green-50' : 'border-gray-200 opacity-50'"
              @click="t.status === 'idle' && selectTable(t)">
              <div class="font-medium">{{ t.table_name }}</div>
              <div class="text-xs text-gray-400">{{ t.capacity }}人</div>
            </div>
          </div>
        </div>
        <!-- Dish list -->
        <div class="flex-1">
          <div class="font-medium mb-2 text-sm text-gray-600">选择菜品</div>
          <div class="grid grid-cols-3 gap-2 max-h-80 overflow-y-auto">
            <div v-for="d in dishes" :key="d.id"
              class="border rounded p-2 cursor-pointer hover:bg-gray-50 text-sm"
              @click="addToCart(d)">
              <div class="font-medium truncate">{{ d.name }}</div>
              <div class="text-orange-500 text-xs">S$ {{ d.price }}</div>
            </div>
          </div>
        </div>
        <!-- Cart -->
        <div class="w-64">
          <div class="font-medium mb-2 text-sm text-gray-600">已选菜品</div>
          <div v-if="!cartItems.length" class="text-gray-400 text-sm text-center py-8">尚未选择</div>
          <div v-for="c in cartItems" :key="c.dish_id" class="flex justify-between items-center py-1 text-sm border-b">
            <div>
              <div class="truncate">{{ c.dish_name }}</div>
              <div class="text-xs text-gray-400">S$ {{ c.price }} × {{ c.number }}</div>
            </div>
            <div class="flex items-center gap-1">
              <el-button size="small" link @click="removeCart(c)">-</el-button>
              <span>{{ c.number }}</span>
              <el-button size="small" link @click="c.number++; cartItems = [...cartItems]">+</el-button>
            </div>
          </div>
          <div v-if="cartItems.length" class="mt-3 pt-2 border-t">
            <div class="flex justify-between font-medium text-orange-500">合计: S$ {{ cartTotal() }}</div>
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="orderDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitOrder">确认开台</el-button>
      </template>
    </el-dialog>
  </div>
</template>