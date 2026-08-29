<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const tickets = ref([])
const tables = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const form = ref({ customer_name: '', customer_phone: '', people_count: 1 })
const assignDialogVisible = ref(false)
const selectedTicket = ref(null)
const selectedTableId = ref(null)

const statusMap = {
  waiting: { label: '等待中', type: 'warning' },
  called: { label: '已叫号', type: 'primary' },
  served: { label: '已入座', type: 'success' },
  cancelled: { label: '已取消', type: 'info' },
}

async function fetchTickets() {
  loading.value = true
  try {
    const res = await api.get('/restaurant/queue')
    tickets.value = res.data || []
  } catch (e) { ElMessage.error(e.message || '获取排队列表失败') }
  finally { loading.value = false }
}

async function fetchTables() {
  try {
    const res = await api.get('/restaurant/tables')
    tables.value = res.data || []
  } catch (e) {}
}

async function takeNumber() {
  try {
    await api.post('/restaurant/queue', form.value)
    ElMessage.success('取号成功')
    dialogVisible.value = false
    form.value = { customer_name: '', customer_phone: '', people_count: 1 }
    fetchTickets()
  } catch (e) { ElMessage.error(e.message || '取号失败') }
}

function openAssign(ticket) {
  selectedTicket.value = ticket
  selectedTableId.value = null
  assignDialogVisible.value = true
  fetchTables()
}

async function assignSeat() {
  if (!selectedTableId.value) { ElMessage.warning('请选择桌台'); return }
  try {
    await api.put(`/restaurant/queue/${selectedTicket.value.id}`, { action: 'serve', table_id: selectedTableId.value })
    ElMessage.success('入座成功')
    assignDialogVisible.value = false
    fetchTickets()
  } catch (e) { ElMessage.error(e.message || '入座失败') }
}

async function callTicket(ticket) {
  try {
    await api.put(`/restaurant/queue/${ticket.id}`, { action: 'call' })
    ElMessage.success('已叫号')
    fetchTickets()
  } catch (e) { ElMessage.error(e.message || '叫号失败') }
}

async function cancelTicket(ticket) {
  if (!confirm(`确定取消 ${ticket.ticket_no}？`)) return
  try {
    await api.put(`/restaurant/queue/${ticket.id}`, { action: 'cancel' })
    ElMessage.success('已取消')
    fetchTickets()
  } catch (e) { ElMessage.error(e.message || '操作失败') }
}

const waitingCount = ref(0)
async function pollTickets() {
  await fetchTickets()
  waitingCount.value = tickets.value.filter(t => t.status === 'waiting').length
}

onMounted(() => { fetchTickets(); setInterval(pollTickets, 10000) })
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">排队叫号</h1>
        <p class="text-gray-500 text-sm mt-1">当前等待: {{ waitingCount }} 人</p>
      </div>
      <el-button type="primary" @click="dialogVisible = true">取号</el-button>
    </div>

    <!-- Stats cards -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-yellow-50 rounded-xl p-4 text-center">
        <div class="text-4xl font-bold text-yellow-500">{{ tickets.filter(t => t.status === 'waiting').length }}</div>
        <div class="text-sm text-yellow-600 mt-1">等待中</div>
      </div>
      <div class="bg-blue-50 rounded-xl p-4 text-center">
        <div class="text-4xl font-bold text-blue-500">{{ tickets.filter(t => t.status === 'called').length }}</div>
        <div class="text-sm text-blue-600 mt-1">已叫号</div>
      </div>
      <div class="bg-green-50 rounded-xl p-4 text-center">
        <div class="text-4xl font-bold text-green-500">{{ tickets.filter(t => t.status === 'served').length }}</div>
        <div class="text-sm text-green-600 mt-1">已入座</div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table v-loading="loading" :data="tickets" stripe>
        <el-table-column label="票号" prop="ticket_no" width="100">
          <template #default="{ row }">
            <span class="font-bold text-lg text-blue-600">{{ row.ticket_no }}</span>
          </template>
        </el-table-column>
        <el-table-column label="客户" min-width="130">
          <template #default="{ row }">
            <div>{{ row.customer_name || '-' }}</div>
            <div class="text-gray-400 text-xs">{{ row.customer_phone || '' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="人数" prop="people_count" width="60" align="center" />
        <el-table-column label="桌台" prop="table_name" width="90">
          <template #default="{ row }">{{ row.table_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusMap[row.status]?.type" size="small">{{ statusMap[row.status]?.label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="排队时间" width="160">
          <template #default="{ row }">{{ new Date(row.created_at).toLocaleString('zh-CN', { hour12: false }) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <div class="flex flex-wrap gap-1">
              <el-button v-if="row.status === 'waiting'" size="small" link type="primary" @click="callTicket(row)">叫号</el-button>
              <el-button v-if="row.status === 'called'" size="small" link type="success" @click="openAssign(row)">入座</el-button>
              <el-button v-if="['waiting','called'].includes(row.status)" size="small" link type="danger" @click="cancelTicket(row)">取消</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- Take number dialog -->
    <el-dialog v-model="dialogVisible" title="取号" width="350">
      <el-form :model="form" label-width="80">
        <el-form-item label="姓名"><el-input v-model="form.customer_name" placeholder="可不填" /></el-form-item>
        <el-form-item label="电话"><el-input v-model="form.customer_phone" placeholder="可不填" /></el-form-item>
        <el-form-item label="人数"><el-input-number v-model="form.people_count" :min="1" :max="20" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="takeNumber">确认取号</el-button>
      </template>
    </el-dialog>

    <!-- Assign seat dialog -->
    <el-dialog v-model="assignDialogVisible" title="选择桌台入座" width="400">
      <div class="grid grid-cols-3 gap-3">
        <div v-for="t in tables" :key="t.id"
          class="border rounded-lg p-3 text-center cursor-pointer transition-colors"
          :class="selectedTableId === t.id ? 'border-blue-500 bg-blue-50' : t.status === 'idle' ? 'border-green-300 hover:bg-green-50' : 'border-gray-200 opacity-50'"
          @click="t.status === 'idle' && (selectedTableId = t.id)">
          <div class="font-bold">{{ t.table_name }}</div>
          <div class="text-xs text-gray-400">{{ t.capacity }}人</div>
        </div>
      </div>
      <template #footer>
        <el-button @click="assignDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="assignSeat">确认入座</el-button>
      </template>
    </el-dialog>
  </div>
</template>