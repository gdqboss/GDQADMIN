<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- 顶部 -->
    <div class="bg-white p-4 sticky top-0 z-10 shadow-sm">
      <h1 class="text-lg font-bold text-gray-800">申请审批</h1>
      <div class="flex gap-2 mt-3 overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          @click="activeTab = tab.value"
          :class="['px-4 py-1.5 rounded-full text-sm whitespace-nowrap transition',
                   activeTab === tab.value ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600']"
        >{{ tab.label }}</button>
      </div>
    </div>

    <!-- 新建按钮 -->
    <div class="p-4">
      <button @click="showCreate = true" class="w-full bg-gradient-to-r from-purple-600 to-purple-700 text-white py-3 rounded-xl font-medium shadow-md active:scale-95 transition">
        + 新建申请
      </button>
    </div>

    <!-- 列表 -->
    <div class="px-4 space-y-3">
      <div v-if="loading" class="text-center py-8 text-gray-400">加载中...</div>
      <div v-else-if="filteredList.length === 0" class="text-center py-12 text-gray-400 bg-white rounded-xl">
        暂无{{ tabs.find(t => t.value === activeTab)?.label }}
      </div>
      <div
        v-for="ap in filteredList"
        :key="ap.id"
        class="bg-white rounded-xl p-4 shadow-sm"
      >
        <div class="flex items-start justify-between mb-2">
          <div class="flex items-center gap-2">
            <span class="text-xs px-2 py-0.5 rounded" :class="typeClass(ap.type)">{{ typeText(ap.type) }}</span>
            <span class="text-sm font-medium text-gray-800">{{ ap.title }}</span>
          </div>
          <span
            class="text-xs px-2 py-0.5 rounded-full"
            :class="{
              'bg-yellow-100 text-yellow-700': ap.status === 'pending',
              'bg-green-100 text-green-700': ap.status === 'approved',
              'bg-red-100 text-red-700': ap.status === 'rejected'
            }"
          >{{ statusText(ap.status) }}</span>
        </div>
        <p class="text-xs text-gray-500 mb-2">{{ ap.reason || '无' }}</p>
        <div class="flex items-center justify-between text-xs text-gray-400">
          <span>{{ ap.user_name }} · {{ formatTime(ap.created_at) }}</span>
          <div v-if="ap.status === 'pending'" class="flex gap-2">
            <button @click="handleApprove(ap, 'approve')" class="text-green-600">批准</button>
            <button @click="handleApprove(ap, 'reject')" class="text-red-600">拒绝</button>
          </div>
          <span v-else-if="ap.approver_name" class="text-gray-500">{{ ap.approver_name }} 处理</span>
        </div>
      </div>
    </div>

    <!-- 新建申请弹窗 -->
    <el-dialog v-model="showCreate" title="新建申请" width="90%">
      <el-form :model="newAp" label-width="80px">
        <el-form-item label="类型">
          <el-select v-model="newAp.type" placeholder="请选择类型">
            <el-option label="请假" value="leave" />
            <el-option label="加班" value="overtime" />
            <el-option label="出差" value="business_trip" />
            <el-option label="报销" value="expense" />
            <el-option label="远程办公" value="remote" />
          </el-select>
        </el-form-item>
        <el-form-item label="标题">
          <el-input v-model="newAp.title" placeholder="简要说明" />
        </el-form-item>
        <el-form-item label="原因">
          <el-input v-model="newAp.reason" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item v-if="newAp.type === 'leave' || newAp.type === 'business_trip' || newAp.type === 'remote'" label="起止时间">
          <el-date-picker v-model="dateRange" type="datetimerange" range-separator="至" />
        </el-form-item>
        <el-form-item v-if="newAp.type === 'expense'" label="金额">
          <el-input-number v-model="newAp.amount" :min="0" :precision="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreate = false">取消</el-button>
        <el-button type="primary" @click="submitCreate" :loading="submitting">提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { ElMessage } from 'element-plus'

const tabs = [
  { label: '全部', value: 'all' },
  { label: '待审批', value: 'pending' },
  { label: '已批准', value: 'approved' },
  { label: '已拒绝', value: 'rejected' }
]
const activeTab = ref('all')
const list = ref([])
const loading = ref(false)
const showCreate = ref(false)
const submitting = ref(false)
const dateRange = ref([])
const newAp = ref({ type: 'leave', title: '', reason: '', amount: 0 })

const filteredList = computed(() => {
  if (activeTab.value === 'all') return list.value
  return list.value.filter(a => a.status === activeTab.value)
})

const typeText = (t) => ({ leave: '请假', overtime: '加班', expense: '报销', business_trip: '出差', remote: '远程' }[t] || t)
const typeClass = (t) => ({
  leave: 'bg-blue-100 text-blue-700',
  overtime: 'bg-orange-100 text-orange-700',
  expense: 'bg-green-100 text-green-700',
  business_trip: 'bg-purple-100 text-purple-700',
  remote: 'bg-cyan-100 text-cyan-700'
}[t] || 'bg-gray-100 text-gray-700')
const statusText = (s) => ({ pending: '待审批', approved: '已批准', rejected: '已拒绝', cancelled: '已取消' }[s] || s)
const formatTime = (d) => d ? new Date(d).toLocaleString('zh-CN', { month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''

const loadData = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/hqh5/approvals/list?page=1&pageSize=50')
    list.value = res.data?.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const submitCreate = async () => {
  if (!newAp.value.type || !newAp.value.title) {
    ElMessage.warning('请填写类型和标题')
    return
  }
  submitting.value = true
  try {
    const body = {
      user_id: 1,
      user_name: '江清波',
      type: newAp.value.type,
      title: newAp.value.title,
      reason: newAp.value.reason
    }
    if (dateRange.value && dateRange.value.length === 2) {
      body.start_date = new Date(dateRange.value[0]).toISOString().slice(0, 19).replace('T', ' ')
      body.end_date = new Date(dateRange.value[1]).toISOString().slice(0, 19).replace('T', ' ')
      const days = (dateRange.value[1] - dateRange.value[0]) / 86400000
      body.days = Math.max(0.5, Math.round(days * 2) / 2)
    }
    if (newAp.value.amount) body.amount = newAp.value.amount
    const res = await axios.post('/api/hqh5/approvals/create', body)
    if (res.data?.code === 0) {
      ElMessage.success('申请提交成功')
      showCreate.value = false
      newAp.value = { type: 'leave', title: '', reason: '', amount: 0 }
      dateRange.value = []
      loadData()
    }
  } catch (e) {
    ElMessage.error('提交失败')
  } finally {
    submitting.value = false
  }
}

const handleApprove = async (ap, action) => {
  try {
    const res = await axios.post(`/api/hqh5/approvals/${ap.id}/approve`, {
      approver_id: 99,
      approver_name: '管理员',
      action,
      comment: action === 'approve' ? '同意' : '不同意'
    })
    if (res.data?.code === 0) {
      ElMessage.success(action === 'approve' ? '已批准' : '已拒绝')
      loadData()
    }
  } catch (e) {
    ElMessage.error('操作失败')
  }
}

onMounted(loadData)
</script>