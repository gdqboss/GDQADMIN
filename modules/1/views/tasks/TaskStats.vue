<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api.js'
import StatusTag from '../../components/StatusTag.vue'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { PieChart, BarChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent } from 'echarts/components'
import VChart from 'vue-echarts'

use([CanvasRenderer, PieChart, BarChart, GridComponent, TooltipComponent, LegendComponent])

const { t } = useI18n()

const loading = ref(false)
const stats = ref({
  total: 0,
  pending: 0,
  in_progress: 0,
  submitted: 0,
  completed: 0,
  completion_rate: 0
})
const allTasks = ref([])
const showDetailModal = ref(false)
const selectedTask = ref(null)

const statusChartOption = computed(() => ({
  tooltip: {
    trigger: 'item',
    formatter: '{b}: {c} ({d}%)'
  },
  legend: {
    orient: 'vertical',
    right: 10,
    top: 'center'
  },
  series: [
    {
      type: 'pie',
      radius: ['40%', '70%'],
      avoidLabelOverlap: false,
      itemStyle: {
        borderRadius: 10,
        borderColor: '#fff',
        borderWidth: 2
      },
      label: {
        show: false,
        position: 'center'
      },
      emphasis: {
        label: {
          show: true,
          fontSize: 20,
          fontWeight: 'bold'
        }
      },
      labelLine: {
        show: false
      },
      data: [
        { value: stats.value.pending, name: t('tasks.pending'), itemStyle: { color: '#409eff' } },
        { value: stats.value.in_progress, name: t('tasks.inProgress'), itemStyle: { color: '#e6a23c' } },
        { value: stats.value.submitted, name: t('tasks.submitted'), itemStyle: { color: '#1890ff' } },
        { value: stats.value.completed, name: t('tasks.completed'), itemStyle: { color: '#67c23a' } }
      ]
    }
  ]
}))

const getStatusColor = (status) => {
  const colors = {
    pending: 'info',
    in_progress: 'warning',
    submitted: 'primary',
    completed: 'success',
    rejected: 'danger'
  }
  return colors[status] || 'info'
}

const getStatusText = (status) => {
  const texts = {
    pending: t('tasks.pending'),
    in_progress: t('tasks.inProgress'),
    submitted: t('tasks.submitted'),
    completed: t('tasks.completed'),
    rejected: t('tasks.rejected')
  }
  return texts[status] || status
}

const getPriorityColor = (priority) => {
  const colors = {
    low: 'info',
    medium: 'warning',
    high: 'danger',
    urgent: 'danger'
  }
  return colors[priority] || 'info'
}

const getPriorityText = (priority) => {
  const texts = {
    low: t('tasks.low'),
    medium: t('tasks.medium'),
    high: t('tasks.high'),
    urgent: t('tasks.urgent')
  }
  return texts[priority] || priority
}

const loadStats = async () => {
  try {
    loading.value = true
    const res = await api.get('/tasks/stats')
    if (res.code === 0) {
      stats.value = res.data || {
        total: 0,
        pending: 0,
        in_progress: 0,
        submitted: 0,
        completed: 0,
        completion_rate: 0
      }
    }
  } catch (err) {
    console.error('Failed to load stats:', err)
  } finally {
    loading.value = false
  }
}

const loadAllTasks = async () => {
  try {
    loading.value = true
    const res = await api.get('/tasks/all')
    if (res.code === 0) {
      allTasks.value = res.data || []
    }
  } catch (err) {
    console.error('Failed to load all tasks:', err)
  } finally {
    loading.value = false
  }
}

const handleViewDetail = (task) => {
  selectedTask.value = task
  showDetailModal.value = true
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

onMounted(async () => {
  await loadStats()
  await loadAllTasks()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex justify-between items-center">
      <h2 class="text-2xl font-bold text-text-primary">{{ $t('tasks.taskStatsTitle') }}</h2>
    </div>

    <!-- 统计卡片 -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm text-text-secondary">{{ $t('tasks.totalTaskCount') }}</span>
          <span class="material-symbols-outlined text-primary text-xl">task</span>
        </div>
        <p class="text-2xl font-bold text-text-primary">{{ stats.total }}</p>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm text-text-secondary">{{ $t('tasks.pending') }}</span>
          <span class="material-symbols-outlined text-info text-xl">pending</span>
        </div>
        <p class="text-2xl font-bold text-info">{{ stats.pending }}</p>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm text-text-secondary">{{ $t('tasks.inProgress') }}</span>
          <span class="material-symbols-outlined text-warning text-xl">autorenew</span>
        </div>
        <p class="text-2xl font-bold text-warning">{{ stats.in_progress }}</p>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm text-text-secondary">{{ $t('tasks.completed') }}</span>
          <span class="material-symbols-outlined text-success text-xl">check_circle</span>
        </div>
        <p class="text-2xl font-bold text-success">{{ stats.completed }}</p>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm text-text-secondary">{{ $t('tasks.completionRate') }}</span>
          <span class="material-symbols-outlined text-primary text-xl">analytics</span>
        </div>
        <p class="text-2xl font-bold text-primary">{{ stats.completion_rate }}%</p>
      </div>
    </div>

    <!-- 图表 -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-6">
      <h3 class="text-lg font-bold text-text-primary mb-4">{{ $t('tasks.statusDistribution') }}</h3>
      <VChart :option="statusChartOption" style="height: 300px" autoresize />
    </div>

    <!-- 任务列表 -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card">
      <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-bold text-text-primary">{{ $t('tasks.allTaskList') }}</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('tasks.taskTitleCol') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('tasks.createdByCol') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('tasks.assignedToCol') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('tasks.priorityCol') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('tasks.statusCol') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('tasks.dueDateCol') }}</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-text-secondary uppercase">{{ $t('tasks.actionCol') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-if="loading">
              <td colspan="7" class="px-6 py-8 text-center text-text-secondary">{{ $t('tasks.loadingText') }}</td>
            </tr>
            <tr v-else-if="allTasks.length === 0">
              <td colspan="7" class="px-6 py-8 text-center text-text-secondary">{{ $t('tasks.noTaskData') }}</td>
            </tr>
            <tr v-else v-for="task in allTasks" :key="task.id" class="hover:bg-gray-50">
              <td class="px-6 py-4">
                <div class="text-sm font-medium text-text-primary">{{ task.title }}</div>
                <div class="text-xs text-text-secondary line-clamp-1">{{ task.description }}</div>
              </td>
              <td class="px-6 py-4 text-sm text-text-primary">{{ task.created_by_name }}</td>
              <td class="px-6 py-4 text-sm text-text-primary">{{ task.assigned_to_name }}</td>
              <td class="px-6 py-4">
                <StatusTag :type="getPriorityColor(task.priority)" :text="getPriorityText(task.priority)" />
              </td>
              <td class="px-6 py-4">
                <StatusTag :type="getStatusColor(task.status)" :text="getStatusText(task.status)" />
              </td>
              <td class="px-6 py-4 text-sm text-text-secondary">{{ formatDate(task.due_date) }}</td>
              <td class="px-6 py-4 text-right">
                <button @click="handleViewDetail(task)" class="text-primary hover:underline text-sm">{{ $t('tasks.viewBtn') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 任务详情弹窗 -->
    <div v-if="showDetailModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
          <h3 class="text-lg font-bold text-text-primary">{{ $t('tasks.taskDetail') }}</h3>
          <button @click="showDetailModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.taskTitle') }}</label>
            <p class="text-text-primary mt-1">{{ selectedTask.title }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.taskDescription') }}</label>
            <p class="text-text-primary mt-1">{{ selectedTask.description || '-' }}</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.status') }}</label>
              <div class="mt-1">
                <StatusTag :type="getStatusColor(selectedTask.status)" :text="getStatusText(selectedTask.status)" />
              </div>
            </div>
            <div>
              <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.priority') }}</label>
              <div class="mt-1">
                <StatusTag :type="getPriorityColor(selectedTask.priority)" :text="getPriorityText(selectedTask.priority)" />
              </div>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.createdBy') }}</label>
              <p class="text-text-primary mt-1">{{ selectedTask.created_by_name }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.assignedTo') }}</label>
              <p class="text-text-primary mt-1">{{ selectedTask.assigned_to_name }}</p>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.createdAt') }}</label>
              <p class="text-text-primary mt-1">{{ formatDate(selectedTask.created_at) }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.dueDate') }}</label>
              <p class="text-text-primary mt-1">{{ formatDate(selectedTask.due_date) }}</p>
            </div>
          </div>
          <div v-if="selectedTask.completion_notes">
            <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.completionNote') }}</label>
            <p class="text-text-primary mt-1">{{ selectedTask.completion_notes }}</p>
          </div>
          <div v-if="selectedTask.review_notes">
            <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.reviewNote') }}</label>
            <p class="text-text-primary mt-1">{{ selectedTask.review_notes }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  /* 标题 */
  .text-2xl {
    font-size: 1.25rem;
  }

  /* 统计卡片网格改为单列 */
  .grid-cols-1.sm\:grid-cols-2.lg\:grid-cols-5 {
    grid-template-columns: repeat(2, 1fr);
  }

  /* 统计卡片内边距 */
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-4 {
    padding: 0.75rem;
  }

  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-4 .text-2xl {
    font-size: 1.125rem;
  }

  /* 图表区域 */
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-6 {
    padding: 1rem;
  }

  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-6 h3 {
    font-size: 1rem;
    margin-bottom: 0.5rem;
  }

  /* 图表高度 */
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-6 :deep(canvas) {
    height: 220px !important;
  }

  /* 任务列表 - 表格适配 */
  .overflow-x-auto table {
    font-size: 0.75rem;
  }

  .overflow-x-auto .px-6.py-3 {
    padding: 0.5rem 0.75rem;
  }

  .overflow-x-auto .px-6.py-4 {
    padding: 0.625rem 0.5rem;
  }

  /* 任务列表表头文字 */
  .overflow-x-auto .uppercase {
    font-size: 0.625rem;
  }

  /* 弹窗适配 */
  .fixed.inset-0.bg-black\/50 {
    padding: 0.5rem;
  }

  .bg-white.rounded-lg.max-w-2xl.w-full.max-h-\[90vh\] {
    max-height: 85vh;
  }

  .bg-white.rounded-lg.max-w-2xl.w-full.max-h-\[90vh\] .p-6 {
    padding: 1rem;
  }

  /* 弹窗内网格改为单列 */
  .grid.grid-cols-2.gap-4 {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }
}
</style>
