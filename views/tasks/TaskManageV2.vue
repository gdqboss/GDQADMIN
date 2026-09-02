<!--
  TaskManageV2 - gdqadmin 任务管理 V2 (2026-09-02 立)
  使用 shared/components/task/{TaskList, TaskForm}
-->
<template>
  <div class="space-y-6">
    <PageHeader :title="$t('task.title')" :subtitle="$t('task.subtitle')">
      <button @click="openCreate" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90">
        新建任务
      </button>
    </PageHeader>

    <!-- 统计卡 -->
    <div class="grid grid-cols-4 gap-4">
      <div class="bg-white rounded-2xl p-4 shadow-sm">
        <div class="text-sm text-slate-500">待处理</div>
        <div class="text-2xl font-bold text-slate-600 mt-1">{{ stats.pending || 0 }}</div>
      </div>
      <div class="bg-white rounded-2xl p-4 shadow-sm">
        <div class="text-sm text-slate-500">进行中</div>
        <div class="text-2xl font-bold text-blue-600 mt-1">{{ stats.in_progress || 0 }}</div>
      </div>
      <div class="bg-white rounded-2xl p-4 shadow-sm">
        <div class="text-sm text-slate-500">已完成</div>
        <div class="text-2xl font-bold text-green-600 mt-1">{{ stats.completed || 0 }}</div>
      </div>
      <div class="bg-white rounded-2xl p-4 shadow-sm">
        <div class="text-sm text-slate-500">已驳回</div>
        <div class="text-2xl font-bold text-red-600 mt-1">{{ stats.rejected || 0 }}</div>
      </div>
    </div>

    <!-- 任务列表 -->
    <TaskList
      :tasks="tasks"
      :api="api"
      :users="users"
      adapter="gdqadmin"
      :loading="loading"
      @item-click="openDetail"
      @refresh="fetchAll"
    />

    <!-- 分页 -->
    <Pagination
      v-if="total > pageSize"
      :total="total"
      :page="currentPage"
      :page-size="pageSize"
      @change="onPageChange"
    />

    <!-- 创建/编辑对话框 -->
    <div v-if="showDialog" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4" @click.self="showDialog = false">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-slate-200 flex items-center justify-between sticky top-0 bg-white">
          <h3 class="text-lg font-bold">{{ dialogMode === 'create' ? '新建任务' : '编辑任务' }}</h3>
          <button @click="showDialog = false" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100">✕</button>
        </div>
        <div class="p-6">
          <TaskForm
            :form-data="formData"
            :users="users"
            :api="api"
            adapter="gdqadmin"
            :loading="saving"
            :mode="dialogMode"
            @submit="handleSubmit"
            @cancel="showDialog = false"
          />
        </div>
      </div>
    </div>

    <!-- 详情对话框 -->
    <div v-if="showDetailModal && selectedTask" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4" @click.self="showDetailModal = false">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6">
        <h3 class="text-lg font-bold mb-4">{{ selectedTask.title }}</h3>
        <p class="text-sm text-slate-700 whitespace-pre-wrap mb-4">{{ selectedTask.description }}</p>
        <div class="text-sm space-y-1">
          <div>👤 {{ getUserName(selectedTask.assignee_id) }}</div>
          <div>📅 {{ selectedTask.due_date || '无截止' }}</div>
          <div>📊 {{ statusLabel(selectedTask.status) }}</div>
        </div>
        <div class="flex gap-3 mt-6">
          <button @click="showDetailModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg">关闭</button>
          <button v-if="canReview(selectedTask)" @click="approveTask" class="px-4 py-2 bg-green-500 text-white rounded-lg">批准</button>
          <button v-if="canReview(selectedTask)" @click="rejectTask" class="px-4 py-2 bg-red-500 text-white rounded-lg">驳回</button>
          <button @click="editFromDetail" class="px-4 py-2 bg-primary text-white rounded-lg">编辑</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user.js'
import PageHeader from '../../components/PageHeader.vue'
import Pagination from '../../components/Pagination.vue'
import TaskList from '../../shared/components/task/TaskList.vue'
import TaskForm from '../../shared/components/task/TaskForm.vue'
import { useTask } from '../../shared/composables/useTask.js'
import api from '../../services/api.js'

const { t } = useI18n()
const userStore = useUserStore()

const {
  tasks, stats, loading, saving,
  fetchTasks, fetchStats, submitTask, updateStatus,
  statusLabel, statusClass
} = useTask(api)

const users = ref([])
const currentPage = ref(1)
const pageSize = 20
const total = ref(0)
const filter = ref({})

const showDialog = ref(false)
const dialogMode = ref('create')
const showDetailModal = ref(false)
const selectedTask = ref(null)
const formData = ref({})

onMounted(async () => {
  await fetchAll()
  const u = await api.get('/users', { params: { pageSize: 1000 } })
  if (u.code === 0) users.value = u.data?.list || []
})

async function fetchAll() {
  loading.value = true
  try {
    const params = { page: currentPage.value, pageSize, ...filter.value }
    const res = await api.get('/tasks', { params })
    if (res.code === 0) {
      tasks.value = res.data?.list || []
      total.value = res.data?.total || tasks.value.length
    }
  } finally {
    loading.value = false
  }
  await fetchStats()
}

function onPageChange(p) {
  currentPage.value = p
  fetchAll()
}

function openCreate() {
  dialogMode.value = 'create'
  formData.value = { title: '', description: '', priority: 'medium', due_date: '', assignee_id: null }
  showDialog.value = true
}

function openDetail(task) {
  selectedTask.value = task
  showDetailModal.value = true
}

function editFromDetail() {
  formData.value = { ...selectedTask.value }
  dialogMode.value = 'edit'
  showDetailModal.value = false
  showDialog.value = true
}

async function handleSubmit(payload) {
  const result = await submitTask(payload, dialogMode.value)
  if (result.ok) {
    showDialog.value = false
    await fetchAll()
  } else {
    alert(result.message || '保存失败')
  }
}

function canReview(task) {
  return userStore.user?.role === 'admin' || userStore.user?.role === 'superuser'
}

async function approveTask() {
  const r = await updateStatus(selectedTask.value.id, 'approved')
  if (r.ok) { showDetailModal.value = false; await fetchAll() }
  else alert(r.message)
}

async function rejectTask() {
  const r = await updateStatus(selectedTask.value.id, 'rejected')
  if (r.ok) { showDetailModal.value = false; await fetchAll() }
  else alert(r.message)
}

function getUserName(id) {
  return users.value.find(u => u.id === id)?.name || `用户${id}`
}
</script>