<script setup>
import { ref, onMounted, watch } from 'vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const reservations = ref([])
const tables = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const editItem = ref(null)
const form = ref({ customer_name: '', customer_phone: '', table_id: null, people_count: 1, reserve_date: '', reserve_time: '', remark: '' })
const dateFilter = ref('')

const statusMap = {
  pending: { label: '待确认', type: 'warning' },
  confirmed: { label: '已确认', type: 'primary' },
  arrived: { label: '已到店', type: 'success' },
  cancelled: { label: '已取消', type: 'info' },
  no_show: { label: '失约', type: 'danger' },
}

async function fetchReservations() {
  loading.value = true
  try {
    const params = {}
    if (dateFilter.value) params.date = dateFilter.value
    const res = await api.get('/restaurant/reservations', { params })
    reservations.value = res.data || []
  } catch (e) { ElMessage.error(e.message || '获取预订列表失败') }
  finally { loading.value = false }
}

async function fetchTables() {
  try {
    const res = await api.get('/restaurant/tables')
    tables.value = res.data || []
  } catch (e) {}
}

function openAdd() {
  editItem.value = null
  const now = new Date()
  form.value = { customer_name: '', customer_phone: '', table_id: null, people_count: 1, reserve_date: now.toISOString().slice(0, 10), reserve_time: '12:00', remark: '' }
  dialogVisible.value = true
  fetchTables()
}

async function save() {
  try {
    if (editItem.value) {
      ElMessage.warning('编辑功能开发中')
    } else {
      await api.post('/restaurant/reservations', form.value)
      ElMessage.success('预订成功')
    }
    dialogVisible.value = false
    fetchReservations()
  } catch (e) { ElMessage.error(e.message || '操作失败') }
}

async function changeStatus(row, action, label) {
  if (!confirm(`确定${label}？`)) return
  try {
    await api.put(`/restaurant/reservations/${row.id}/status`, { action })
    ElMessage.success(`${label}成功`)
    fetchReservations()
  } catch (e) { ElMessage.error(e.message || '操作失败') }
}

watch(dateFilter, () => { fetchReservations() })
onMounted(fetchReservations)
import { onMounted } from 'vue'
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">预订管理</h1>
      <div class="flex gap-2">
        <el-date-picker v-model="dateFilter" type="date" placeholder="筛选日期" value-format="YYYY-MM-DD" class="!w-40" />
        <el-button type="primary" @click="openAdd">新建预订</el-button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table v-loading="loading" :data="reservations" stripe>
        <el-table-column label="预订号" prop="reserve_no" min-width="130" />
        <el-table-column label="客户" min-width="130">
          <template #default="{ row }">
            <div>{{ row.customer_name }}</div>
            <div class="text-gray-400 text-xs">{{ row.customer_phone }}</div>
          </template>
        </el-table-column>
        <el-table-column label="桌台" prop="table_name" width="90" />
        <el-table-column label="人数" prop="people_count" width="60" />
        <el-table-column label="预订时间" width="160">
          <template #default="{ row }">{{ row.reserve_date }} {{ row.reserve_time }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusMap[row.status]?.type" size="small">{{ statusMap[row.status]?.label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <div class="flex flex-wrap gap-1">
              <el-button v-if="row.status === 'pending'" size="small" link type="primary" @click="changeStatus(row, 'confirm', '确认')">确认</el-button>
              <el-button v-if="row.status === 'confirmed'" size="small" link type="success" @click="changeStatus(row, 'arrive', '报到')">报到</el-button>
              <el-button v-if="['pending','confirmed'].includes(row.status)" size="small" link type="danger" @click="changeStatus(row, 'cancel', '取消')">取消</el-button>
              <el-button v-if="row.status === 'confirmed'" size="small" link type="warning" @click="changeStatus(row, 'no_show', '失约')">失约</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <el-dialog v-model="dialogVisible" title="新建预订" width="450">
      <el-form :model="form" label-width="90">
        <el-form-item label="客户姓名"><el-input v-model="form.customer_name" /></el-form-item>
        <el-form-item label="客户电话"><el-input v-model="form.customer_phone" /></el-form-item>
        <el-form-item label="分配桌台">
          <el-select v-model="form.table_id" clearable placeholder="可不选">
            <el-option v-for="t in tables" :key="t.id" :label="`${t.table_name} (${t.capacity}人)`" :value="t.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="用餐人数"><el-input-number v-model="form.people_count" :min="1" /></el-form-item>
        <el-form-item label="预订日期"><el-date-picker v-model="form.reserve_date" type="date" value-format="YYYY-MM-DD" /></el-form-item>
        <el-form-item label="预订时间"><el-time-picker v-model="form.reserve_time" value-format="HH:mm" format="HH:mm" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="form.remark" type="textarea" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="save">提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>