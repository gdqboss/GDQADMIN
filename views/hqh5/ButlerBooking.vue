<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- 顶部 -->
    <div class="bg-white p-4 sticky top-0 z-10 shadow-sm">
      <h1 class="text-lg font-bold text-gray-800">管家服务</h1>
    </div>

    <!-- 服务类型 -->
    <div class="p-4">
      <h2 class="text-base font-bold text-gray-800 mb-3">服务类型</h2>
      <div class="grid grid-cols-5 gap-3">
        <button
          v-for="svc in serviceTypes"
          :key="svc.value"
          @click="selectType(svc)"
          :class="['flex flex-col items-center p-3 rounded-xl transition',
                   selectedType === svc.value ? 'bg-purple-600 text-white shadow-md' : 'bg-white text-gray-700 shadow-sm']"
        >
          <span class="text-2xl mb-1">{{ svc.icon }}</span>
          <span class="text-xs">{{ svc.label }}</span>
        </button>
      </div>
    </div>

    <!-- 新建工单 -->
    <div v-if="selectedType" class="px-4">
      <h2 class="text-base font-bold text-gray-800 mb-3">提交工单</h2>
      <div class="bg-white rounded-xl p-4 shadow-sm space-y-3">
        <div>
          <label class="text-sm text-gray-600 block mb-1">事项描述</label>
          <input v-model="newOrder.title" placeholder="如：电脑无法连接打印机" class="w-full p-2 border rounded-lg" />
        </div>
        <div>
          <label class="text-sm text-gray-600 block mb-1">详细说明</label>
          <textarea v-model="newOrder.description" rows="3" class="w-full p-2 border rounded-lg"></textarea>
        </div>
        <div>
          <label class="text-sm text-gray-600 block mb-1">位置</label>
          <input v-model="newOrder.location" placeholder="如：A栋 305" class="w-full p-2 border rounded-lg" />
        </div>
        <div>
          <label class="text-sm text-gray-600 block mb-1">优先级</label>
          <div class="flex gap-2">
            <button
              v-for="p in priorities"
              :key="p.value"
              @click="newOrder.priority = p.value"
              :class="['flex-1 py-2 rounded-lg text-sm transition',
                       newOrder.priority === p.value ? p.activeClass : 'bg-gray-100 text-gray-600']"
            >{{ p.label }}</button>
          </div>
        </div>
        <div class="text-xs text-gray-400 bg-blue-50 p-2 rounded">
          ⏱ {{ slaHint }}
        </div>
        <button
          @click="submitOrder"
          :disabled="!newOrder.title || submitting"
          class="w-full bg-gradient-to-r from-orange-500 to-orange-600 text-white py-3 rounded-xl font-medium shadow-md disabled:opacity-50"
        >{{ submitting ? '提交中...' : '提交工单' }}</button>
      </div>
    </div>

    <!-- 我的工单 -->
    <div class="p-4">
      <h2 class="text-base font-bold text-gray-800 mb-3">我的工单</h2>
      <div v-if="loading" class="text-center py-8 text-gray-400">加载中...</div>
      <div v-else-if="orders.length === 0" class="text-center py-8 text-gray-400 bg-white rounded-xl">暂无工单</div>
      <div v-else class="space-y-2">
        <div
          v-for="o in orders"
          :key="o.id"
          class="bg-white rounded-xl p-4 shadow-sm"
        >
          <div class="flex items-start justify-between mb-2">
            <div class="flex items-center gap-2">
              <span class="text-lg">{{ typeIcon(o.type) }}</span>
              <span class="text-sm font-medium text-gray-800">{{ o.title }}</span>
            </div>
            <span
              class="text-xs px-2 py-0.5 rounded-full"
              :class="{
                'bg-yellow-100 text-yellow-700': o.status === 'open',
                'bg-blue-100 text-blue-700': o.status === 'assigned' || o.status === 'processing',
                'bg-green-100 text-green-700': o.status === 'completed',
                'bg-gray-100 text-gray-700': o.status === 'cancelled'
              }"
            >{{ statusText(o.status) }}</span>
          </div>
          <p class="text-xs text-gray-500 mb-2">{{ o.description || '无' }}</p>
          <div class="flex items-center justify-between text-xs">
            <span class="text-gray-400">📍 {{ o.location || '未指定' }}</span>
            <span :class="priorityClass(o.priority)">{{ priorityText(o.priority) }}</span>
          </div>
          <div v-if="o.sla_deadline" class="text-xs text-gray-400 mt-2">
            ⏱ SLA 截止：{{ formatTime(o.sla_deadline) }}
          </div>
          <div v-if="o.assigned_to" class="text-xs text-blue-600 mt-1">
            👤 处理人：{{ o.assigned_to }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { ElMessage } from 'element-plus'

const serviceTypes = [
  { value: 'it', label: 'IT 支持', icon: '💻' },
  { value: 'express', label: '快递代收', icon: '📦' },
  { value: 'moving', label: '搬运协助', icon: '🚚' },
  { value: 'cleaning', label: '保洁', icon: '🧹' },
  { value: 'parking', label: '停车', icon: '🅿️' }
]
const priorities = [
  { value: 'low', label: '低', activeClass: 'bg-gray-500 text-white' },
  { value: 'medium', label: '中', activeClass: 'bg-blue-500 text-white' },
  { value: 'high', label: '高', activeClass: 'bg-orange-500 text-white' },
  { value: 'urgent', label: '紧急', activeClass: 'bg-red-500 text-white' }
]

const selectedType = ref(null)
const orders = ref([])
const loading = ref(false)
const submitting = ref(false)
const newOrder = ref({ title: '', description: '', location: '', priority: 'medium' })

const slaHint = computed(() => {
  const map = { urgent: '2 小时', high: '8 小时', medium: '24 小时', low: '72 小时' }
  return `${priorities.find(p => p.value === newOrder.value.priority)?.label}优先级 SLA = ${map[newOrder.value.priority]}`
})

const typeIcon = (t) => serviceTypes.find(s => s.value === t)?.icon || '📌'
const statusText = (s) => ({ open: '待处理', assigned: '已分配', processing: '处理中', completed: '已完成', cancelled: '已取消' }[s] || s)
const priorityText = (p) => priorities.find(x => x.value === p)?.label || p
const priorityClass = (p) => ({
  low: 'text-gray-500',
  medium: 'text-blue-600',
  high: 'text-orange-600',
  urgent: 'text-red-600 font-medium'
}[p] || 'text-gray-500')
const formatTime = (d) => d ? new Date(d).toLocaleString('zh-CN') : ''

const selectType = (svc) => {
  selectedType.value = svc.value
}

const loadOrders = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/hqh5/butler/services?user_id=1')
    orders.value = res.data?.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const submitOrder = async () => {
  submitting.value = true
  try {
    const res = await axios.post('/api/hqh5/butler/create', {
      user_id: 1,
      user_name: '江清波',
      type: selectedType.value,
      title: newOrder.value.title,
      description: newOrder.value.description,
      location: newOrder.value.location,
      priority: newOrder.value.priority
    })
    if (res.data?.code === 0) {
      ElMessage.success(res.data.message || '工单创建成功')
      newOrder.value = { title: '', description: '', location: '', priority: 'medium' }
      selectedType.value = null
      loadOrders()
    }
  } catch (e) {
    ElMessage.error('提交失败')
  } finally {
    submitting.value = false
  }
}

onMounted(loadOrders)
</script>