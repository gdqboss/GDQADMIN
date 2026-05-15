<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user'
import api from '../../services/api.js'
import StatusTag from '../../components/StatusTag.vue'

const { t } = useI18n()
const userStore = useUserStore()

const activeTab = ref('my-tasks')
const loading = ref(false)
const myTasks = ref([])
const assignedTasks = ref([])
const allTasks = ref([])
const allTasksTotal = ref(0)
const allTasksPage = ref(1)
const allTasksPageSize = ref(20)
const users = ref([])
const allUsers = ref([])
const showDetailModal = ref(false)
const showSubmitModal = ref(false)
const showReviewModal = ref(false)
const selectedTask = ref(null)
const statusFilter = ref('all')
const priorityFilter = ref('all')

// 全局筛选条件
const filterAssignedTo = ref('')
const filterAssignedBy = ref('')
const filterDateStart = ref('')
const filterDateEnd = ref('')
const filterKeyword = ref('')
const quickDateRange = ref('')

// 创建任务表单
const taskForm = ref({
  title: '',
  description: '',
  assigned_to: null,
  priority: 'medium',
  due_date: ''
})

// 提交任务表单
const submitForm = ref({
  completion_notes: '',
  attachments: [],
  images: []
})

// 审核表单
const reviewForm = ref({
  action: 'approve',
  review_notes: ''
})

const tabs = computed(() => {
  const baseTabs = [
    { key: 'my-tasks', label: t('tasks.myTasks'), icon: 'task_alt' },
    { key: 'assigned-tasks', label: t('tasks.assignedTasks'), icon: 'assignment_ind' }
  ]
  if (userStore.user.role === 'admin') {
    baseTabs.push({ key: 'all-tasks', label: t('tasks.allTasks'), icon: 'list_alt' })
  }
  baseTabs.push({ key: 'create-task', label: t('tasks.createTask'), icon: 'add_task' })
  return baseTabs
})

const statusOptions = computed(() => [
  { value: 'all', label: t('common.allStatus') },
  { value: 'pending', label: t('tasks.pending') },
  { value: 'in_progress', label: t('tasks.inProgress') },
  { value: 'submitted', label: t('tasks.submitted') },
  { value: 'completed', label: t('tasks.completed') }
])

const priorityOptions = computed(() => [
  { value: 'all', label: t('tasks.allPriority') },
  { value: 'low', label: t('tasks.low') },
  { value: 'medium', label: t('tasks.medium') },
  { value: 'high', label: t('tasks.high') },
  { value: 'urgent', label: t('tasks.urgent') }
])

const filteredMyTasks = computed(() => {
  let tasks = myTasks.value
  if (statusFilter.value !== 'all') {
    tasks = tasks.filter(t => t.status === statusFilter.value)
  }
  if (priorityFilter.value !== 'all') {
    tasks = tasks.filter(t => t.priority === priorityFilter.value)
  }
  return tasks
})

const filteredAssignedTasks = computed(() => {
  let tasks = assignedTasks.value
  if (statusFilter.value !== 'all') {
    tasks = tasks.filter(t => t.status === statusFilter.value)
  }
  if (priorityFilter.value !== 'all') {
    tasks = tasks.filter(t => t.priority === priorityFilter.value)
  }
  return tasks
})

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

const loadMyTasks = async () => {
  try {
    loading.value = true
    const res = await api.get('/tasks/my')
    if (res.code === 0) {
      myTasks.value = res.data.tasks || res.data || []
    }
  } catch (err) {
    console.error('Failed to load my tasks:', err)
  } finally {
    loading.value = false
  }
}

const loadAssignedTasks = async () => {
  try {
    loading.value = true
    const res = await api.get('/tasks/assigned')
    if (res.code === 0) {
      assignedTasks.value = res.data.tasks || res.data || []
    }
  } catch (err) {
    console.error('Failed to load assigned tasks:', err)
  } finally {
    loading.value = false
  }
}

const loadUsers = async () => {
  try {
    const res = await api.get('/users')
    if (res.code === 0) {
      const userData = res.data || []
      allUsers.value = userData // 保存所有用户用于筛选

      // 如果是超级管理员，显示所有用户
      if (userStore.user.role === 'admin') {
        users.value = userData
      } else {
        // 否则只显示当前用户的下级（通过递归查找supervisor_id链条）
        const currentUserId = userStore.user.id
        const subordinates = []

        // 递归查找所有下级
        const findSubordinates = (supervisorId) => {
          const directSubordinates = userData.filter(u => u.supervisor_id === supervisorId)
          directSubordinates.forEach(sub => {
            subordinates.push(sub)
            findSubordinates(sub.id) // 递归查找下下级
          })
        }

        findSubordinates(currentUserId)
        users.value = subordinates
      }
    }
  } catch (err) {
    console.error('Failed to load users:', err)
  }
}

const handleViewDetail = (task) => {
  selectedTask.value = task
  showDetailModal.value = true
  // 如果任务有ID，重新加载完整详情（包括附件）
  if (task.id) {
    loadTaskDetail(task.id)
  }
}

const loadTaskDetail = async (taskId) => {
  try {
    const res = await api.get(`/tasks/${taskId}`)
    if (res.code === 0) {
      selectedTask.value = res.data
    }
  } catch (err) {
    console.error('Failed to load task detail:', err)
  }
}

const handleSubmitTask = (task) => {
  selectedTask.value = task
  submitForm.value = {
    completion_notes: '',
    attachments: [],
    images: []
  }
  showSubmitModal.value = true
}

// Compress image using canvas, returns a Blob
const compressImage = (file, maxWidth = 1920, quality = 0.8) => {
  return new Promise((resolve) => {
    const img = new Image()
    img.onload = () => {
      let { width, height } = img
      if (width > maxWidth) {
        height = Math.round(height * maxWidth / width)
        width = maxWidth
      }
      const canvas = document.createElement('canvas')
      canvas.width = width
      canvas.height = height
      canvas.getContext('2d').drawImage(img, 0, 0, width, height)
      canvas.toBlob((blob) => resolve(blob), 'image/jpeg', quality)
    }
    img.onerror = () => resolve(null)
    img.src = URL.createObjectURL(file)
  })
}

const handleImageUpload = async (event) => {
  const files = event.target.files
  if (!files || files.length === 0) return

  for (let file of files) {
    const ext = file.name.split('.').pop()?.toLowerCase()
    const allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif']
    if (!file.type.startsWith('image/') && !allowedExts.includes(ext)) {
      alert(t('tasks.selectImageFile'))
      continue
    }

    let uploadFile = file
    // Compress if larger than 1MB
    if (file.size > 1 * 1024 * 1024) {
      const compressed = await compressImage(file)
      if (compressed && compressed.size < file.size) {
        uploadFile = new File([compressed], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' })
      }
    }

    if (uploadFile.size > 5 * 1024 * 1024) {
      alert(t('tasks.fileTooLarge', { name: file.name, size: 5 }))
      continue
    }

    const formData = new FormData()
    formData.append('file', uploadFile)

    try {
      const res = await api.post('/upload/task-attachment', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      if (res.code === 0 && res.data?.url) {
        submitForm.value.images.push(res.data.url)
      } else {
        alert(t('tasks.uploadFailed'))
      }
    } catch (err) {
      console.error('Upload failed:', err)
      alert(t('tasks.uploadFailed'))
    }
  }
  event.target.value = ''
}

const removeImage = (index) => {
  submitForm.value.images.splice(index, 1)
}

const handleReviewTask = (task) => {
  selectedTask.value = task
  reviewForm.value = {
    action: 'approve',
    review_notes: ''
  }
  showReviewModal.value = true
}

const submitTask = async () => {
  try {
    loading.value = true
    const payload = {
      completion_notes: submitForm.value.completion_notes,
      attachments: submitForm.value.images.length > 0 ? JSON.stringify(submitForm.value.images) : null
    }
    const res = await api.put(`/tasks/${selectedTask.value.id}/submit`, payload)
    if (res.code === 0) {
      showSubmitModal.value = false
      alert(t('tasks.taskSubmitted'))
      await loadMyTasks()
    } else {
      alert(res.message || t('tasks.submitFailed'))
    }
  } catch (err) {
    console.error('Failed to submit task:', err)
    alert(err.response?.data?.message || t('tasks.submitFailed'))
  } finally {
    loading.value = false
  }
}

const reviewTask = async () => {
  try {
    loading.value = true
    const res = await api.put(`/tasks/${selectedTask.value.id}/review`, reviewForm.value)
    if (res.code === 0) {
      showReviewModal.value = false
      alert(reviewForm.value.action === 'approve' ? t('tasks.taskApproved') : t('tasks.taskDenied'))
      await loadAssignedTasks()
      if (activeTab.value === 'all-tasks') {
        await loadAllTasks()
      }
    }
  } catch (err) {
    console.error('Failed to review task:', err)
    alert(err.response?.data?.message || t('tasks.reviewFailed'))
  } finally {
    loading.value = false
  }
}

// 加载全部任务（仅管理员）
const loadAllTasks = async () => {
  try {
    loading.value = true
    const params = {
      page: allTasksPage.value,
      limit: allTasksPageSize.value
    }

    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (priorityFilter.value !== 'all') params.priority = priorityFilter.value
    if (filterAssignedTo.value) params.assigned_to = filterAssignedTo.value
    if (filterAssignedBy.value) params.assigned_by = filterAssignedBy.value
    if (filterDateStart.value) params.date_start = filterDateStart.value
    if (filterDateEnd.value) params.date_end = filterDateEnd.value
    if (filterKeyword.value) params.keyword = filterKeyword.value

    const res = await api.get('/tasks/all', { params })

    if (res.code === 0) {
      allTasks.value = res.data.tasks || []
      allTasksTotal.value = res.data.total || 0
    }
  } catch (err) {
    console.error('Failed to load all tasks:', err)
    alert(err.response?.data?.message || t('tasks.loadFailed'))
  } finally {
    loading.value = false
  }
}

// 快捷日期范围选择
const setQuickDateRange = (range) => {
  const today = new Date()
  const formatDate = (date) => date.toISOString().slice(0, 10)

  if (range === 'week') {
    const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000)
    filterDateStart.value = formatDate(weekAgo)
    filterDateEnd.value = formatDate(today)
  } else if (range === 'month') {
    const monthAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000)
    filterDateStart.value = formatDate(monthAgo)
    filterDateEnd.value = formatDate(today)
  } else if (range === 'all') {
    filterDateStart.value = ''
    filterDateEnd.value = ''
  }

  quickDateRange.value = range
  allTasksPage.value = 1
  loadAllTasks()
}

// 重置筛选条件
const resetFilters = () => {
  statusFilter.value = 'all'
  priorityFilter.value = 'all'
  filterAssignedTo.value = ''
  filterAssignedBy.value = ''
  filterDateStart.value = ''
  filterDateEnd.value = ''
  filterKeyword.value = ''
  quickDateRange.value = ''
  allTasksPage.value = 1
  loadAllTasks()
}

// 应用筛选
const applyFilters = () => {
  allTasksPage.value = 1
  loadAllTasks()
}

const createTask = async () => {
  if (!taskForm.value.title || !taskForm.value.assigned_to) {
    alert(t('tasks.fillTitleAndAssignee'))
    return
  }
  try {
    loading.value = true
    const res = await api.post('/tasks', taskForm.value)
    if (res.code === 0) {
      taskForm.value = {
        title: '',
        description: '',
        assigned_to: null,
        priority: 'medium',
        due_date: ''
      }
      alert(t('tasks.taskCreated'))
      activeTab.value = 'assigned-tasks'
      await loadAssignedTasks()
    }
  } catch (err) {
    console.error('Failed to create task:', err)
    alert(t('tasks.createFailed'))
  } finally {
    loading.value = false
  }
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
  await loadUsers()
  if (userStore.user.role === 'admin') {
    activeTab.value = 'all-tasks'
    await Promise.all([loadMyTasks(), loadAllTasks()])
  } else {
    await loadMyTasks()
  }
  await loadAssignedTasks()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex justify-between items-center">
      <h2 class="text-2xl font-bold text-text-primary">{{ $t('tasks.title') }}</h2>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card">
      <div class="border-b border-gray-200">
        <nav class="flex -mb-px overflow-x-auto scrollbar-hide">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="activeTab = tab.key"
            :class="[
              'flex items-center gap-1 sm:gap-2 px-3 sm:px-6 py-3 border-b-2 font-medium text-xs sm:text-sm transition-colors whitespace-nowrap flex-shrink-0',
              activeTab === tab.key
                ? 'border-primary text-primary'
                : 'border-transparent text-text-secondary hover:text-text-primary hover:border-gray-300'
            ]"
          >
            <span class="material-symbols-outlined text-base sm:text-lg">{{ tab.icon }}</span>
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <!-- 我的任务 -->
      <div v-if="activeTab === 'my-tasks'" class="p-3 sm:p-6">
        <div class="flex gap-2 sm:gap-4 mb-4">
          <select v-model="statusFilter" class="flex-1 sm:flex-none px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
          <select v-model="priorityFilter" class="flex-1 sm:flex-none px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option v-for="opt in priorityOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>

        <div v-if="loading" class="text-center py-8 text-text-secondary">{{ $t('common.loading') }}</div>
        <div v-else-if="filteredMyTasks.length === 0" class="text-center py-8 text-text-secondary">{{ $t('tasks.noTasks') }}</div>
        <div v-else class="space-y-3">
          <div
            v-for="task in filteredMyTasks"
            :key="task.id"
            class="border border-gray-200 rounded-lg p-3 sm:p-4 hover:border-primary/50 hover:bg-primary/5 transition-all"
          >
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-2 gap-2">
              <div class="flex-1 min-w-0">
                <h4 class="font-medium text-text-primary mb-1 text-sm sm:text-base">{{ task.title }}</h4>
                <p class="text-xs sm:text-sm text-text-secondary line-clamp-2">{{ task.description }}</p>
              </div>
              <div class="flex gap-2 flex-shrink-0">
                <StatusTag :type="getStatusColor(task.status)" :text="getStatusText(task.status)" />
                <StatusTag :type="getPriorityColor(task.priority)" :text="getPriorityText(task.priority)" />
              </div>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between text-xs text-text-secondary mt-3 gap-2">
              <span>{{ $t('tasks.dueDate') }}: {{ formatDate(task.due_date) }}</span>
              <div class="flex gap-2">
                <button @click="handleViewDetail(task)" class="text-primary hover:underline">{{ $t('tasks.viewDetail') }}</button>
                <button
                  v-if="task.status === 'pending' || task.status === 'in_progress'"
                  @click="handleSubmitTask(task)"
                  class="px-3 py-1 bg-success text-white rounded hover:bg-success/90 font-medium"
                >
                  {{ $t('tasks.completeTaskBtn') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 我指派的任务 -->
      <div v-if="activeTab === 'assigned-tasks'" class="p-3 sm:p-6">
        <div class="flex gap-2 sm:gap-4 mb-4">
          <select v-model="statusFilter" class="flex-1 sm:flex-none px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
          <select v-model="priorityFilter" class="flex-1 sm:flex-none px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option v-for="opt in priorityOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>

        <div v-if="loading" class="text-center py-8 text-text-secondary">{{ $t('common.loading') }}</div>
        <div v-else-if="filteredAssignedTasks.length === 0" class="text-center py-8 text-text-secondary">{{ $t('tasks.noTasks') }}</div>
        <div v-else class="space-y-3">
          <div
            v-for="task in filteredAssignedTasks"
            :key="task.id"
            class="border border-gray-200 rounded-lg p-3 sm:p-4 hover:border-primary/50 hover:bg-primary/5 transition-all"
          >
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-2 gap-2">
              <div class="flex-1 min-w-0">
                <h4 class="font-medium text-text-primary mb-1 text-sm sm:text-base">{{ task.title }}</h4>
                <p class="text-xs sm:text-sm text-text-secondary line-clamp-2">{{ task.description }}</p>
              </div>
              <div class="flex gap-2 flex-shrink-0">
                <StatusTag :type="getStatusColor(task.status)" :text="getStatusText(task.status)" />
                <StatusTag :type="getPriorityColor(task.priority)" :text="getPriorityText(task.priority)" />
              </div>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between text-xs text-text-secondary mt-3 gap-2">
              <span>{{ $t('tasks.assignedToLabel') }}: {{ task.assigned_to_name }} | {{ $t('tasks.dueLabel') }}: {{ formatDate(task.due_date) }}</span>
              <div class="flex gap-2">
                <button @click="handleViewDetail(task)" class="text-primary hover:underline">{{ $t('tasks.viewDetail') }}</button>
                <button
                  v-if="task.status === 'submitted'"
                  @click="handleReviewTask(task)"
                  class="text-warning hover:underline"
                >
                  {{ $t('tasks.review') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 全部任务（仅管理员） -->
      <div v-if="activeTab === 'all-tasks'" class="p-3 sm:p-6">
        <!-- 筛选条件 -->
        <div class="bg-gray-50 rounded-lg p-3 sm:p-4 mb-4 space-y-3">
          <div class="grid grid-cols-2 sm:flex sm:flex-wrap items-center gap-2 sm:gap-3">
            <!-- 关键词搜索 -->
            <div class="col-span-2 flex items-center bg-white rounded-lg px-3 py-2 border">
              <span class="material-symbols-outlined text-gray-400 mr-2 text-sm">search</span>
              <input
                v-model="filterKeyword"
                type="text"
                :placeholder="$t('tasks.searchPlaceholder')"
                class="outline-none text-sm w-full sm:w-48"
              />
            </div>

            <!-- 状态筛选 -->
            <select v-model="statusFilter" class="px-3 py-2 border rounded-lg text-sm bg-white">
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>

            <!-- 优先级筛选 -->
            <select v-model="priorityFilter" class="px-3 py-2 border rounded-lg text-sm bg-white">
              <option v-for="opt in priorityOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>

            <!-- 接受人筛选 -->
            <select v-model="filterAssignedTo" class="px-3 py-2 border rounded-lg text-sm bg-white">
              <option value="">{{ $t('tasks.allAssignees') }}</option>
              <option v-for="user in allUsers" :key="user.id" :value="user.id">
                {{ user.name }}
              </option>
            </select>

            <!-- 指派人筛选 -->
            <select v-model="filterAssignedBy" class="px-3 py-2 border rounded-lg text-sm bg-white">
              <option value="">{{ $t('tasks.allAssigners') }}</option>
              <option v-for="user in allUsers" :key="user.id" :value="user.id">
                {{ user.name }}
              </option>
            </select>
          </div>

          <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <!-- 快捷日期选项 -->
            <div class="flex gap-2">
              <button
                @click="setQuickDateRange('week')"
                :class="['px-3 py-1 rounded text-sm', quickDateRange === 'week' ? 'bg-primary text-white' : 'bg-white border hover:bg-gray-50']">
                {{ $t('tasks.lastWeek') }}
              </button>
              <button
                @click="setQuickDateRange('month')"
                :class="['px-3 py-1 rounded text-sm', quickDateRange === 'month' ? 'bg-primary text-white' : 'bg-white border hover:bg-gray-50']">
                {{ $t('tasks.lastMonth') }}
              </button>
              <button
                @click="setQuickDateRange('all')"
                :class="['px-3 py-1 rounded text-sm', quickDateRange === 'all' ? 'bg-primary text-white' : 'bg-white border hover:bg-gray-50']">
                {{ $t('common.all') }}
              </button>
            </div>

            <!-- 自定义日期范围 -->
            <div class="flex items-center gap-2 w-full sm:w-auto">
              <input
                v-model="filterDateStart"
                type="date"
                class="px-2 sm:px-3 py-1 border rounded text-sm bg-white flex-1 sm:flex-none"
              />
              <span class="text-gray-400">—</span>
              <input
                v-model="filterDateEnd"
                type="date"
                class="px-2 sm:px-3 py-1 border rounded text-sm bg-white flex-1 sm:flex-none"
              />
            </div>

            <!-- 应用和重置按钮 -->
            <div class="flex gap-2">
              <button
                @click="applyFilters"
                class="px-3 py-1 bg-primary text-white rounded text-sm hover:bg-primary-hover flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">filter_alt</span>
                {{ $t('tasks.applyFilter') }}
              </button>
              <button
                @click="resetFilters"
                class="px-3 py-1 border rounded text-sm hover:bg-gray-100 flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">refresh</span>
                {{ $t('common.reset') }}
              </button>
            </div>
          </div>
        </div>

        <!-- 任务列表 -->
        <div v-if="loading" class="text-center py-8 text-text-secondary">{{ $t('common.loading') }}</div>
        <div v-else-if="allTasks.length === 0" class="text-center py-8 text-text-secondary">{{ $t('tasks.noTasks') }}</div>
        <div v-else class="space-y-3">
          <div
            v-for="task in allTasks"
            :key="task.id"
            class="border border-gray-200 rounded-lg p-3 sm:p-4 hover:border-primary/50 hover:bg-primary/5 transition-all cursor-pointer"
            @click="handleViewDetail(task)"
          >
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-2 gap-2">
              <div class="flex-1 min-w-0">
                <h4 class="font-medium text-text-primary mb-1 text-sm sm:text-base">{{ task.title }}</h4>
                <p class="text-xs sm:text-sm text-text-secondary line-clamp-2">{{ task.content || task.description }}</p>
              </div>
              <div class="flex gap-2 flex-shrink-0">
                <StatusTag :type="getStatusColor(task.status)" :text="getStatusText(task.status)" />
                <StatusTag :type="getPriorityColor(task.priority)" :text="getPriorityText(task.priority)" />
              </div>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center text-xs text-text-secondary mt-3 gap-1 sm:gap-4">
              <span>{{ $t('tasks.assignedByLabel') }}: {{ task.assigned_by_name }}</span>
              <span>{{ $t('tasks.assignedToLabel') }}: {{ task.assigned_to_name }}</span>
              <span>{{ $t('tasks.dueLabel') }}: {{ formatDate(task.due_date) }}</span>
            </div>
          </div>
        </div>

        <!-- 分页 -->
        <div v-if="allTasksTotal > allTasksPageSize" class="flex justify-center items-center gap-2 mt-4">
          <button
            @click="allTasksPage = Math.max(1, allTasksPage - 1); loadAllTasks()"
            :disabled="allTasksPage === 1"
            class="px-3 py-1 border rounded text-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ $t('tasks.prevPage') }}
          </button>
          <span class="text-sm text-text-secondary">
            {{ allTasksPage }} / {{ Math.ceil(allTasksTotal / allTasksPageSize) }} {{ $t('tasks.page') }}，{{ allTasksTotal }} {{ $t('tasks.totalRecords') }}
          </span>
          <button
            @click="allTasksPage = Math.min(Math.ceil(allTasksTotal / allTasksPageSize), allTasksPage + 1); loadAllTasks()"
            :disabled="allTasksPage >= Math.ceil(allTasksTotal / allTasksPageSize)"
            class="px-3 py-1 border rounded text-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ $t('tasks.nextPage') }}
          </button>
        </div>
      </div>

      <!-- 创建任务 -->
      <div v-if="activeTab === 'create-task'" class="p-3 sm:p-6">
        <div class="max-w-2xl space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('tasks.taskTitle') }} *</label>
            <input
              v-model="taskForm.title"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
              :placeholder="$t('tasks.enterTaskTitle')"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('tasks.taskDescription') }}</label>
            <textarea
              v-model="taskForm.description"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
              :placeholder="$t('tasks.enterTaskDesc')"
            ></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('tasks.assignedTo') }} *</label>
            <select
              v-model="taskForm.assigned_to"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
            >
              <option :value="null">{{ $t('tasks.pleaseSelect') }}</option>
              <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
            </select>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('tasks.priority') }}</label>
              <select
                v-model="taskForm.priority"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
              >
                <option value="low">{{ $t('tasks.low') }}</option>
                <option value="medium">{{ $t('tasks.medium') }}</option>
                <option value="high">{{ $t('tasks.high') }}</option>
                <option value="urgent">{{ $t('tasks.urgent') }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('tasks.dueDate') }}</label>
              <input
                v-model="taskForm.due_date"
                type="datetime-local"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
              />
            </div>
          </div>
          <div class="flex justify-end">
            <button
              @click="createTask"
              :disabled="loading"
              class="w-full sm:w-auto px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-hover disabled:opacity-50"
            >
              {{ $t('tasks.createTask') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 任务详情弹窗 -->
    <div v-if="showDetailModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-2 sm:p-4">
      <div class="bg-white rounded-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-4 sm:px-6 py-3 sm:py-4 flex justify-between items-center">
          <h3 class="text-base sm:text-lg font-bold text-text-primary">{{ $t('tasks.taskDetail') }}</h3>
          <button @click="showDetailModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-4 sm:p-6 space-y-4">
          <div>
            <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.taskTitle') }}</label>
            <p class="text-text-primary mt-1">{{ selectedTask.title }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.taskDescription') }}</label>
            <p class="text-text-primary mt-1">{{ selectedTask.description || '-' }}</p>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.createdBy') }}</label>
              <p class="text-text-primary mt-1">{{ selectedTask.created_by_name || selectedTask.assigned_by_name }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.assignedTo') }}</label>
              <p class="text-text-primary mt-1">{{ selectedTask.assigned_to_name }}</p>
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.createdAt') }}</label>
              <p class="text-text-primary mt-1">{{ formatDate(selectedTask.created_at) }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.dueDate') }}</label>
              <p class="text-text-primary mt-1">{{ formatDate(selectedTask.due_date) }}</p>
            </div>
          </div>
          <div v-if="selectedTask.completion_note || selectedTask.completion_notes">
            <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.completionNote') }}</label>
            <p class="text-text-primary mt-1">{{ selectedTask.completion_note || selectedTask.completion_notes }}</p>
          </div>
          <div v-if="selectedTask.attachments && selectedTask.attachments.length > 0">
            <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.attachmentImages') }}</label>
            <div class="mt-2 grid grid-cols-2 sm:grid-cols-3 gap-2">
              <div v-for="(att, idx) in selectedTask.attachments" :key="idx" class="relative group">
                <img
                  :src="att.file_path || att"
                  :alt="att.file_name || $t('tasks.attachmentImages')"
                  class="w-full h-24 object-cover rounded border border-gray-200 cursor-pointer hover:opacity-80"
                  @click="window.open(att.file_path || att, '_blank')"
                  @error="(e) => { e.target.style.display = 'none'; e.target.nextElementSibling.style.display = 'flex' }"
                >
                <div class="hidden w-full h-24 items-center justify-center bg-gray-100 rounded border border-gray-200 text-xs text-gray-500">
                  <div class="text-center">
                    <span class="material-symbols-outlined text-2xl block mb-1">broken_image</span>
                    <span>{{ $t('tasks.imageFailed') }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div v-if="selectedTask.review_note || selectedTask.review_notes">
            <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.reviewNote') }}</label>
            <p class="text-text-primary mt-1">{{ selectedTask.review_note || selectedTask.review_notes }}</p>
          </div>
          <div v-if="selectedTask.reviewed_by_name">
            <label class="text-sm font-medium text-text-secondary">{{ $t('tasks.reviewer') }}</label>
            <p class="text-text-primary mt-1">{{ selectedTask.reviewed_by_name }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- 提交任务弹窗 -->
    <div v-if="showSubmitModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-2 sm:p-4">
      <div class="bg-white rounded-lg w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="border-b border-gray-200 px-4 sm:px-6 py-3 sm:py-4 flex justify-between items-center sticky top-0 bg-white">
          <h3 class="text-lg font-bold text-text-primary">{{ $t('tasks.completeTaskBtn') }}</h3>
          <button @click="showSubmitModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-4 sm:p-6 space-y-4">
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
            <p class="font-medium mb-1">{{ selectedTask?.title }}</p>
            <p class="text-xs text-blue-600">{{ $t('tasks.submitDirectHint') }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-text-primary mb-2">
              {{ $t('tasks.completionNoteOptional') }}
            </label>
            <textarea
              v-model="submitForm.completion_notes"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
              :placeholder="$t('tasks.describeCompletion')"
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-text-primary mb-2">
              {{ $t('tasks.uploadImagesOptional') }}
            </label>
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-primary transition-colors">
              <input
                type="file"
                accept="image/*"
                multiple
                @change="handleImageUpload"
                class="w-full text-sm"
              >
              <p class="text-xs text-gray-500 mt-2">{{ $t('tasks.supportMultipleImages') }}</p>
            </div>

            <div v-if="submitForm.images && submitForm.images.length > 0" class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-2">
              <div v-for="(img, idx) in submitForm.images" :key="idx" class="relative group">
                <img :src="img" class="w-full h-24 object-cover rounded border border-gray-200">
                <button
                  @click="removeImage(idx)"
                  class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600 opacity-0 group-hover:opacity-100 transition-opacity"
                >
                  <span class="material-symbols-outlined text-sm">close</span>
                </button>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              @click="showSubmitModal = false"
              class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              {{ $t('common.cancel') }}
            </button>
            <button
              @click="submitTask"
              :disabled="loading"
              class="px-4 py-2 bg-success text-white rounded-lg hover:bg-success/90 disabled:opacity-50 flex items-center gap-2"
            >
              <span class="material-symbols-outlined text-lg">check_circle</span>
              <span>{{ loading ? $t('common.submitting') : $t('tasks.submitComplete') }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 审核任务弹窗 -->
    <div v-if="showReviewModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-2 sm:p-4">
      <div class="bg-white rounded-lg w-full max-w-lg">
        <div class="border-b border-gray-200 px-4 sm:px-6 py-3 sm:py-4 flex justify-between items-center">
          <h3 class="text-lg font-bold text-text-primary">{{ $t('tasks.reviewTask') }}</h3>
          <button @click="showReviewModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-4 sm:p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('tasks.reviewResult') }}</label>
            <div class="flex gap-4">
              <label class="flex items-center gap-2">
                <input v-model="reviewForm.action" type="radio" value="approve" class="text-primary" />
                <span>{{ $t('tasks.confirmComplete') }}</span>
              </label>
              <label class="flex items-center gap-2">
                <input v-model="reviewForm.action" type="radio" value="reject" class="text-danger" />
                <span>{{ $t('tasks.reject') }}</span>
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('tasks.reviewNote') }}</label>
            <textarea
              v-model="reviewForm.review_notes"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
              :placeholder="$t('tasks.enterReviewNote')"
            ></textarea>
          </div>
          <div class="flex justify-end gap-2">
            <button
              @click="showReviewModal = false"
              class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              {{ $t('common.cancel') }}
            </button>
            <button
              @click="reviewTask"
              :disabled="loading"
              class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-hover disabled:opacity-50"
            >
              {{ $t('tasks.submitReview') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
