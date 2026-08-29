<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api.js'

const loading = ref(false)
const loadError = ref('')
const list = ref([])
const filterStatus = ref('all')
const filterType = ref('all')
const selectedOrder = ref(null)
const showAssignModal = ref(false)
const showCompleteModal = ref(false)
const replyText = ref('')
const assignTo = ref('')

// 类型映射
const TYPE_LABELS = {
  it: 'IT支持', express: '快递', moving: '搬迁',
  cleaning: '保洁', parking: '停车', repair: '维修', other: '其他'
}

// 状态选项
const STATUS_TABS = [
  { value: 'all', label: '全部' },
  { value: 'open', label: '待处理' },
  { value: 'assigned', label: '已分配' },
  { value: 'processing', label: '进行中' },
  { value: 'completed', label: '已完成' },
  { value: 'cancelled', label: '已取消' }
]

// 优先级颜色
function priorityBadge(p) {
  return {
    urgent: 'bg-red-50 text-red-700 border-red-200',
    high: 'bg-orange-50 text-orange-700 border-orange-200',
    medium: 'bg-amber-50 text-amber-700 border-amber-200',
    low: 'bg-slate-50 text-slate-600 border-slate-200'
  }[p] || 'bg-slate-50 text-slate-600 border-slate-200'
}

function priorityLabel(p) {
  return { urgent: '紧急', high: '高', medium: '中', low: '低' }[p] || p || '-'
}

// 状态徽章颜色 (后端已返回 status_color)
function statusClass(order) {
  const color = order.status_color || '#6B7280'
  // 转成 Tailwind class (硬编码常用映射)
  const map = {
    '#F59E0B': 'bg-amber-50 text-amber-700 border-amber-200',
    '#6366F1': 'bg-indigo-50 text-indigo-700 border-indigo-200',
    '#7E53FF': 'bg-purple-50 text-purple-700 border-purple-200',
    '#22C55E': 'bg-green-50 text-green-700 border-green-200',
    '#9CA3AF': 'bg-gray-50 text-gray-600 border-gray-200'
  }
  return map[color] || 'bg-gray-50 text-gray-700 border-gray-200'
}

function formatDate(s) {
  if (!s) return '-'
  return s.slice(0, 16).replace('T', ' ')
}

// 计算筛选后的列表
const filtered = computed(() => {
  return list.value.filter(o => {
    if (filterStatus.value !== 'all' && o.status !== filterStatus.value) return false
    if (filterType.value !== 'all' && o.type !== filterType.value) return false
    return true
  })
})

// 统计
const stats = computed(() => {
  const s = { total: list.value.length, open: 0, processing: 0, completed: 0 }
  list.value.forEach(o => {
    if (o.status === 'open') s.open++
    else if (o.status === 'processing' || o.status === 'assigned') s.processing++
    else if (o.status === 'completed') s.completed++
  })
  return s
})

async function loadList() {
  loading.value = true
  loadError.value = ''
  try {
    const res = await api.get('/butler-orders', { params: { limit: 100 } })
    list.value = res.data || res.list || []
  } catch (e) {
    loadError.value = e.message || '加载失败'
    list.value = []
  } finally {
    loading.value = false
  }
}

function openAssign(order) {
  selectedOrder.value = order
  assignTo.value = order.assigned_to || ''
  showAssignModal.value = true
}

async function submitAssign() {
  if (!assignTo.value.trim()) {
    alert('请填写分配给谁')
    return
  }
  try {
    await api.put(`/butler-orders/${selectedOrder.value.id}/assign`, { assigned_to: assignTo.value.trim() })
    showAssignModal.value = false
    await loadList()
  } catch (e) {
    alert('分配失败: ' + (e.message || ''))
  }
}

function openComplete(order) {
  selectedOrder.value = order
  replyText.value = ''
  showCompleteModal.value = true
}

async function submitComplete() {
  try {
    await api.put(`/butler-orders/${selectedOrder.value.id}/complete`, {
      reply: replyText.value.trim()
    })
    showCompleteModal.value = false
    await loadList()
  } catch (e) {
    alert('完成失败: ' + (e.message || ''))
  }
}

async function deleteOrder(order) {
  if (!confirm(`确认删除工单 "${order.title}"?`)) return
  try {
    await api.delete(`/butler-orders/${order.id}`)
    await loadList()
  } catch (e) {
    alert('删除失败: ' + (e.message || ''))
  }
}

onMounted(loadList)
</script>

<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- 顶部 -->
    <div class="bg-white border-b px-4 py-3">
      <h1 class="text-lg font-bold text-gray-800">管家工单管理</h1>
      <p class="text-xs text-gray-500 mt-1">管理企业用户提交的管家工单 (维修/IT支持/快递等)</p>
    </div>

    <!-- 统计卡片 -->
    <div class="grid grid-cols-4 gap-2 p-4">
      <div class="bg-white rounded-lg p-3 text-center border">
        <div class="text-2xl font-bold text-gray-800">{{ stats.total }}</div>
        <div class="text-xs text-gray-500 mt-1">总单数</div>
      </div>
      <div class="bg-white rounded-lg p-3 text-center border">
        <div class="text-2xl font-bold text-amber-600">{{ stats.open }}</div>
        <div class="text-xs text-gray-500 mt-1">待处理</div>
      </div>
      <div class="bg-white rounded-lg p-3 text-center border">
        <div class="text-2xl font-bold text-purple-600">{{ stats.processing }}</div>
        <div class="text-xs text-gray-500 mt-1">处理中</div>
      </div>
      <div class="bg-white rounded-lg p-3 text-center border">
        <div class="text-2xl font-bold text-green-600">{{ stats.completed }}</div>
        <div class="text-xs text-gray-500 mt-1">已完成</div>
      </div>
    </div>

    <!-- 状态筛选 -->
    <div class="bg-white border-b sticky top-0 z-10">
      <div class="flex overflow-x-auto">
        <button v-for="tab in STATUS_TABS" :key="tab.value" @click="filterStatus = tab.value"
          :class="['px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2',
                   filterStatus === tab.value ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500']">
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- 列表 -->
    <div class="p-4 space-y-3">
      <div v-if="loading" class="text-center py-12 text-gray-400">加载中…</div>
      <div v-else-if="loadError" class="text-center py-12">
        <div class="text-red-500 mb-2">{{ loadError }}</div>
        <button @click="loadList" class="text-blue-600 text-sm">重试</button>
      </div>
      <div v-else-if="filtered.length === 0" class="text-center py-12 text-gray-400">暂无工单</div>

      <div v-for="order in filtered" :key="order.id" class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-start justify-between mb-2">
          <div class="font-medium text-gray-800 flex-1">{{ order.title }}</div>
          <span :class="['px-2 py-0.5 text-xs rounded-full border', statusClass(order)]">
            {{ order.status_label }}
          </span>
        </div>
        <div class="text-sm text-gray-600 mb-2">{{ order.description || '(无描述)' }}</div>
        <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
          <div class="flex gap-2">
            <span class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded">{{ TYPE_LABELS[order.type] || order.type }}</span>
            <span :class="['px-2 py-0.5 rounded border', priorityBadge(order.priority)]">优先级: {{ priorityLabel(order.priority) }}</span>
          </div>
          <span>{{ formatDate(order.created_at) }}</span>
        </div>
        <div class="flex items-center justify-between text-xs text-gray-500 mb-3 border-t pt-2">
          <span>👤 {{ order.user_name }} (ID: {{ order.user_id }})</span>
          <span v-if="order.location">📍 {{ order.location }}</span>
        </div>
        <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
          <span v-if="order.assigned_to">已分配给: <b class="text-blue-700">{{ order.assigned_to }}</b></span>
          <span v-if="order.sla_deadline" class="text-red-600">SLA: {{ formatDate(order.sla_deadline) }}</span>
        </div>
        <!-- 操作按钮 -->
        <div class="flex gap-2 border-t pt-3">
          <button v-if="order.status !== 'completed' && order.status !== 'cancelled'"
            @click="openAssign(order)" class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm">分配</button>
          <button v-if="order.status !== 'completed' && order.status !== 'cancelled'"
            @click="openComplete(order)" class="flex-1 bg-green-600 text-white py-2 rounded-lg text-sm">完成</button>
          <button @click="deleteOrder(order)" class="px-4 bg-red-50 text-red-600 border border-red-200 py-2 rounded-lg text-sm">删除</button>
        </div>
      </div>
    </div>

    <!-- 分配弹窗 -->
    <div v-if="showAssignModal" class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center" @click.self="showAssignModal = false">
      <div class="bg-white rounded-t-2xl sm:rounded-xl w-full sm:max-w-md">
        <div class="border-b px-4 py-3 flex justify-between items-center">
          <h3 class="font-bold">分配工单</h3>
          <button @click="showAssignModal = false" class="text-gray-400">✕</button>
        </div>
        <div class="p-4 space-y-3">
          <div class="text-sm text-gray-600">工单: {{ selectedOrder?.title }}</div>
          <div>
            <label class="block text-sm font-medium mb-1">分配给 (管家/同事)</label>
            <input v-model="assignTo" type="text" placeholder="如: 张管家"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          </div>
          <button @click="submitAssign" class="w-full bg-blue-600 text-white py-2.5 rounded-lg text-sm font-medium">确认分配</button>
        </div>
      </div>
    </div>

    <!-- 完成弹窗 -->
    <div v-if="showCompleteModal" class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center" @click.self="showCompleteModal = false">
      <div class="bg-white rounded-t-2xl sm:rounded-xl w-full sm:max-w-md">
        <div class="border-b px-4 py-3 flex justify-between items-center">
          <h3 class="font-bold">完成工单</h3>
          <button @click="showCompleteModal = false" class="text-gray-400">✕</button>
        </div>
        <div class="p-4 space-y-3">
          <div class="text-sm text-gray-600">工单: {{ selectedOrder?.title }}</div>
          <div>
            <label class="block text-sm font-medium mb-1">回复内容 (可选)</label>
            <textarea v-model="replyText" rows="3" placeholder="处理说明…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
          </div>
          <button @click="submitComplete" class="w-full bg-green-600 text-white py-2.5 rounded-lg text-sm font-medium">标记完成</button>
        </div>
      </div>
    </div>
  </div>
</template>
