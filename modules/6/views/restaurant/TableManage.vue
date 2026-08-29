<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const tables = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const editTable = ref(null)
const form = ref({ table_no: '', table_name: '', capacity: 4, area: 'main' })

const areaMap = { main: '大厅', dining: '包间', vip: 'VIP' }
const statusMap = {
  idle: { label: '空闲', type: 'success' },
  occupied: { label: '占用', type: 'danger' },
  reserved: { label: '已预订', type: 'warning' },
  locked: { label: '锁定', type: 'info' },
}

async function fetchTables() {
  loading.value = true
  try {
    const res = await api.get('/restaurant/tables')
    tables.value = res.data || []
  } catch (e) {
    ElMessage.error(e.message || '获取桌台列表失败')
  } finally {
    loading.value = false
  }
}

function openAdd() {
  editTable.value = null
  form.value = { table_no: '', table_name: '', capacity: 4, area: 'main' }
  dialogVisible.value = true
}

function openEdit(row) {
  editTable.value = row
  form.value = { table_no: row.table_no, table_name: row.table_name, capacity: row.capacity, area: row.area }
  dialogVisible.value = true
}

async function save() {
  try {
    if (editTable.value) {
      await api.put(`/restaurant/tables/${editTable.value.id}`, form.value)
      ElMessage.success('更新成功')
    } else {
      await api.post('/restaurant/tables', form.value)
      ElMessage.success('添加成功')
    }
    dialogVisible.value = false
    fetchTables()
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function setStatus(row, status) {
  try {
    await api.put(`/restaurant/tables/${row.id}`, { status })
    ElMessage.success('状态更新成功')
    fetchTables()
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function deleteTable(row) {
  if (!confirm(`确定删除桌台 ${row.table_name}？`)) return
  try {
    await api.delete(`/restaurant/tables/${row.id}`)
    ElMessage.success('删除成功')
    fetchTables()
  } catch (e) {
    ElMessage.error(e.message || e.response?.data?.message || '删除失败')
  }
}

onMounted(fetchTables)
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">桌台管理</h1>
      </div>
      <el-button type="primary" @click="openAdd">新增桌台</el-button>
    </div>

    <!-- Table grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
      <div
        v-for="t in tables"
        :key="t.id"
        class="bg-white rounded-xl shadow-sm p-4 border-2"
        :class="{
          'border-green-300': t.status === 'idle',
          'border-red-300': t.status === 'occupied',
          'border-yellow-300': t.status === 'reserved',
          'border-gray-200': t.status === 'locked',
        }"
      >
        <div class="flex justify-between items-start mb-2">
          <div>
            <div class="font-bold text-gray-800">{{ t.table_name }}</div>
            <div class="text-xs text-gray-400">{{ areaMap[t.area] || t.area }} · {{ t.capacity }}人</div>
          </div>
          <el-tag :type="statusMap[t.status]?.type" size="small">{{ statusMap[t.status]?.label }}</el-tag>
        </div>
        <div v-if="t.current_order_no" class="text-xs text-orange-500 mb-2">进行中: {{ t.current_order_no }}</div>
        <div class="flex gap-1 mt-2">
          <el-button size="small" link type="primary" @click="openEdit(t)">编辑</el-button>
          <el-button v-if="t.status === 'idle'" size="small" link type="warning" @click="setStatus(t, 'locked')">锁定</el-button>
          <el-button v-if="t.status === 'locked'" size="small" link type="success" @click="setStatus(t, 'idle')">解锁</el-button>
          <el-button v-if="t.status === 'idle'" size="small" link type="danger" @click="deleteTable(t)">删除</el-button>
        </div>
      </div>
    </div>

    <!-- Dialog -->
    <el-dialog v-model="dialogVisible" :title="editTable ? '编辑桌台' : '新增桌台'" width="400">
      <el-form :model="form" label-width="80">
        <el-form-item label="桌台编号"><el-input v-model="form.table_no" /></el-form-item>
        <el-form-item label="桌台名称"><el-input v-model="form.table_name" /></el-form-item>
        <el-form-item label="容纳人数"><el-input-number v-model="form.capacity" :min="1" :max="20" /></el-form-item>
        <el-form-item label="区域">
          <el-select v-model="form.area">
            <el-option label="大厅" value="main" />
            <el-option label="包间" value="dining" />
            <el-option label="VIP" value="vip" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>