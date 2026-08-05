<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user.js'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'

const { t, locale } = useI18n()
const userStore = useUserStore()

// ─── State ──────────────────────────────────────────────────────────────────────
const isAdmin = computed(() => userStore.canAccess('work_log:write'))
const records = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = 20
const stats = ref({
  total: 0,
  pending: 0,
  processing: 0,
  resolved: 0,
  avgResolutionTime: 0,
  byType: { complaint: 0, suggestion: 0, bug: 0, other: 0 }
})
const systemUsers = ref([])

// Filters
const filterType = ref('')
const filterStatus = ref('')
const filterAssignedTo = ref('')

// Detail Modal
const showDetailModal = ref(false)
const selectedFeedback = ref(null)
const replyText = ref('')
const assignToUser = ref('')
const saving = ref(false)

// Submit Dialog (User)
const showSubmitDialog = ref(false)
const submitForm = ref({
  type: 'suggestion',
  title: '',
  content: '',
  contact_phone: '',
  images: []
})
const uploadingImages = ref(false)
const submitting = ref(false)

// ─── Fetch ────────────────────────────────────────────────────────────────────
async function fetchRecords() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize }
    if (filterType.value) params.type = filterType.value
    if (filterStatus.value) params.status = filterStatus.value
    if (filterAssignedTo.value) params.assigned_to = filterAssignedTo.value
    const res = await api.get('/feedback', { params })
    if (res.code === 0) {
      records.value = res.data.list
      total.value = res.data.total
    }
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  if (!isAdmin.value) return
  const res = await api.get('/feedback/stats')
  if (res.code === 0) stats.value = res.data
}

async function fetchSystemUsers() {
  if (!isAdmin.value) return
  const res = await api.get('/users')
  if (res.code === 0) systemUsers.value = res.data.list || res.data
}

watch([filterType, filterStatus, filterAssignedTo], () => {
  currentPage.value = 1
  fetchRecords()
})
watch(currentPage, fetchRecords)

onMounted(() => {
  fetchRecords()
  fetchStats()
  fetchSystemUsers()
})

// ─── Detail Modal ───────────────────────────────────────────────────────────────
function openDetail(row) {
  selectedFeedback.value = row
  replyText.value = row.reply || ''
  assignToUser.value = row.assigned_to || ''
  showDetailModal.value = true
}

async function assignFeedback() {
  if (!assignToUser.value) return
  saving.value = true
  try {
    const res = await api.put(`/feedback/${selectedFeedback.value.id}`, {
      assigned_to: assignToUser.value
    })
    if (res.code === 0) {
      await fetchRecords()
      await fetchStats()
      selectedFeedback.value.assigned_to = assignToUser.value
      const user = systemUsers.value.find(u => u.id === parseInt(assignToUser.value))
      if (user) selectedFeedback.value.assigned_to_name = user.name
    }
  } finally {
    saving.value = false
  }
}

async function replyFeedback() {
  if (!replyText.value.trim()) return
  saving.value = true
  try {
    const res = await api.put(`/feedback/${selectedFeedback.value.id}`, {
      status: 'resolved',
      reply: replyText.value
    })
    if (res.code === 0) {
      showDetailModal.value = false
      await fetchRecords()
      await fetchStats()
    }
  } finally {
    saving.value = false
  }
}

async function closeFeedback() {
  saving.value = true
  try {
    const res = await api.put(`/feedback/${selectedFeedback.value.id}`, {
      status: 'resolved'
    })
    if (res.code === 0) {
      showDetailModal.value = false
      await fetchRecords()
      await fetchStats()
    }
  } finally {
    saving.value = false
  }
}

// ─── Submit Dialog (User) ───────────────────────────────────────────────────────
function openSubmitDialog() {
  submitForm.value = {
    type: 'suggestion',
    title: '',
    content: '',
    contact_phone: '',
    images: []
  }
  showSubmitDialog.value = true
}

async function handleImageUpload(event) {
  const files = Array.from(event.target.files)
  if (submitForm.value.images.length + files.length > 5) {
    alert(t('logs.maxImagesAlert'))
    return
  }

  uploadingImages.value = true
  try {
    for (const file of files) {
      const formData = new FormData()
      formData.append('image', file)
      const res = await api.post('/feedback/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      if (res.code === 0) {
        submitForm.value.images.push(res.data.url)
      }
    }
  } finally {
    uploadingImages.value = false
  }
}

function removeImage(index) {
  submitForm.value.images.splice(index, 1)
}

async function submitFeedback() {
  if (!submitForm.value.title.trim() || !submitForm.value.content.trim()) {
    alert(t('logs.fillTitleAndContent'))
    return
  }

  submitting.value = true
  try {
    const res = await api.post('/feedback', submitForm.value)
    if (res.code === 0) {
      showSubmitDialog.value = false
      await fetchRecords()
      alert(t('logs.submitSuccessMsg'))
    }
  } finally {
    submitting.value = false
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatTime(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleString(locale.value === 'zh' ? 'zh-CN' : 'en-US', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatDate(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleDateString(locale.value === 'zh' ? 'zh-CN' : 'en-US', {
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const typeConfig = computed(() => ({
  complaint: { label: t('logs.complaintType'), icon: 'report', color: 'danger' },
  suggestion: { label: t('logs.suggestionType'), icon: 'lightbulb', color: 'primary' },
  bug: { label: t('logs.bugType'), icon: 'bug_report', color: 'warning' },
  other: { label: t('logs.otherType'), icon: 'help', color: 'info' }
}))

const statusConfig = computed(() => ({
  pending: { type: 'info', label: t('logs.pendingStatus') },
  processing: { type: 'warning', label: t('logs.processingStatus') },
  resolved: { type: 'success', label: t('logs.resolvedStatus') }
}))
</script>

<template>
  <div>
    <PageHeader
      :title="isAdmin ? $t('logs.feedbackManage') : $t('logs.myFeedbackTitle')"
      :subtitle="isAdmin ? $t('logs.feedbackManageSubtitle') : $t('logs.myFeedbackSubtitle')"
    />

    <!-- Admin Stats -->
    <div v-if="isAdmin" class="grid grid-cols-4 gap-4 mb-6">
      <div v-for="item in [
        { label: $t('logs.pendingStatus'), value: stats.pending, icon: 'inbox', color: 'info' },
        { label: $t('logs.processingStatus'), value: stats.processing, icon: 'pending', color: 'warning' },
        { label: $t('logs.resolvedStatus'), value: stats.resolved, icon: 'check_circle', color: 'success' },
        { label: $t('logs.avgResolutionTime'), value: stats.avgResolutionTime ? `${stats.avgResolutionTime}h` : '-', icon: 'schedule', color: 'primary' },
      ]" :key="item.label" class="bg-white rounded-lg border border-gray-100 shadow-card p-4 flex items-center gap-3">
        <div :class="`size-10 rounded-lg bg-${item.color}/10 flex items-center justify-center`">
          <span :class="`material-symbols-outlined text-${item.color} text-[20px]`">{{ item.icon }}</span>
        </div>
        <div>
          <p class="text-xs text-text-secondary">{{ item.label }}</p>
          <p class="text-xl font-bold text-text-primary">{{ item.value }}</p>
        </div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <select v-model="filterType" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
            <option value="">{{ $t('logs.allTypesFilter') }}</option>
            <option value="complaint">{{ $t('logs.complaintType') }}</option>
            <option value="suggestion">{{ $t('logs.suggestionType') }}</option>
            <option value="bug">{{ $t('logs.bugType') }}</option>
            <option value="other">{{ $t('logs.otherType') }}</option>
          </select>
          <select v-model="filterStatus" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
            <option value="">{{ $t('logs.allStatusFilter') }}</option>
            <option value="pending">{{ $t('logs.pendingStatus') }}</option>
            <option value="processing">{{ $t('logs.processingStatus') }}</option>
            <option value="resolved">{{ $t('logs.resolvedStatus') }}</option>
          </select>
          <select v-if="isAdmin" v-model="filterAssignedTo" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
            <option value="">{{ $t('logs.allAssignees') }}</option>
            <option v-for="u in systemUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <button v-if="!isAdmin" @click="openSubmitDialog" class="flex items-center gap-2 bg-primary text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-primary-hover">
          <span class="material-symbols-outlined text-[18px]">add</span>
          {{ $t('logs.submitFeedbackBtn') }}
        </button>
      </div>
    </div>

    <!-- Admin Table View -->
    <div v-if="isAdmin" class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('logs.submitTimeCol') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('logs.typeCol') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('logs.titleCol') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('logs.submitterCol') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('logs.statusCol') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('logs.assigneeCol') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('logs.actionCol') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.loading') }}</td>
            </tr>
            <tr v-else-if="records.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('logs.noFeedbackRecords') }}</td>
            </tr>
            <tr v-for="r in records" :key="r.id" class="hover:bg-gray-50 transition-colors cursor-pointer" @click="openDetail(r)">
              <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">{{ formatTime(r.created_at) }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <span :class="`material-symbols-outlined text-${typeConfig[r.type]?.color || 'info'} text-[18px]`">
                    {{ typeConfig[r.type]?.icon || 'help' }}
                  </span>
                  <span class="text-text-primary text-xs">{{ typeConfig[r.type]?.label || r.type }}</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <p class="font-medium text-text-primary">{{ r.title }}</p>
                <p class="text-xs text-text-secondary line-clamp-1">{{ r.content }}</p>
              </td>
              <td class="px-4 py-3">
                <p class="text-text-primary">{{ r.submitter_name || $t('logs.anonymousUser') }}</p>
                <p class="text-xs text-text-secondary">{{ r.contact_phone || '' }}</p>
              </td>
              <td class="px-4 py-3 text-center">
                <StatusTag
                  :type="statusConfig[r.status]?.type || 'info'"
                  :text="statusConfig[r.status]?.label || r.status"
                />
              </td>
              <td class="px-4 py-3 text-text-secondary text-sm">{{ r.assigned_to_name || $t('logs.notAssigned') }}</td>
              <td class="px-4 py-3 text-right">
                <button @click.stop="openDetail(r)" class="text-primary hover:text-primary-hover text-xs font-medium">{{ $t('logs.viewBtn') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100">
        <Pagination :total="total" :page="currentPage" :pageSize="pageSize" @update:page="currentPage = $event" />
      </div>
    </div>

    <!-- User Card View -->
    <div v-else class="space-y-4">
      <div v-if="loading" class="text-center py-8 text-text-secondary">{{ $t('common.loading') }}</div>
      <div v-else-if="records.length === 0" class="bg-white rounded-lg border border-gray-100 shadow-card p-8 text-center text-text-secondary">
        {{ $t('logs.noFeedbackRecords') }}
      </div>
      <div v-else v-for="r in records" :key="r.id" @click="openDetail(r)" class="bg-white rounded-lg border border-gray-100 shadow-card p-4 hover:shadow-md transition-shadow cursor-pointer">
        <div class="flex items-start justify-between mb-2">
          <div class="flex items-center gap-2">
            <span :class="`material-symbols-outlined text-${typeConfig[r.type]?.color || 'info'} text-[20px]`">
              {{ typeConfig[r.type]?.icon || 'help' }}
            </span>
            <h3 class="font-medium text-text-primary">{{ r.title }}</h3>
          </div>
          <StatusTag
            :type="statusConfig[r.status]?.type || 'info'"
            :text="statusConfig[r.status]?.label || r.status"
          />
        </div>
        <p class="text-sm text-text-secondary mb-3 line-clamp-2">{{ r.content }}</p>
        <div class="flex items-center justify-between text-xs text-text-secondary">
          <span>{{ formatDate(r.created_at) }}</span>
          <span v-if="r.reply" class="text-success">{{ $t('logs.replied') }}</span>
        </div>
      </div>
      <div v-if="total > pageSize" class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <Pagination :total="total" :page="currentPage" :pageSize="pageSize" @update:page="currentPage = $event" />
      </div>
    </div>

    <!-- Detail Modal -->
    <Teleport to="body">
      <div v-if="showDetailModal && selectedFeedback" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/30" @click="showDetailModal = false"></div>
        <div class="relative w-full max-w-2xl bg-white rounded-lg shadow-xl flex flex-col max-h-[90vh]">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b shrink-0">
            <h3 class="text-lg font-bold text-text-primary">{{ $t('logs.feedbackDetailTitle') }}</h3>
            <button @click="showDetailModal = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Content -->
          <div class="flex-1 overflow-y-auto p-6 space-y-5">
            <!-- Feedback Info -->
            <div class="bg-gray-50 rounded-lg p-4 space-y-3">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span :class="`material-symbols-outlined text-${typeConfig[selectedFeedback.type]?.color || 'info'} text-[24px]`">
                    {{ typeConfig[selectedFeedback.type]?.icon || 'help' }}
                  </span>
                  <div>
                    <p class="text-xs text-text-secondary">{{ typeConfig[selectedFeedback.type]?.label || selectedFeedback.type }}</p>
                    <p class="font-medium text-text-primary">{{ selectedFeedback.title }}</p>
                  </div>
                </div>
                <StatusTag
                  :type="statusConfig[selectedFeedback.status]?.type || 'info'"
                  :text="statusConfig[selectedFeedback.status]?.label || selectedFeedback.status"
                />
              </div>
              <div class="text-xs text-text-secondary">
                {{ $t('logs.submitTimeLabel') }}{{ formatTime(selectedFeedback.created_at) }}
              </div>
            </div>

            <!-- Submitter Info -->
            <div class="bg-gray-50 rounded-lg p-4 space-y-2">
              <p class="text-xs font-medium text-text-secondary uppercase tracking-wider">{{ $t('logs.submitterInfo') }}</p>
              <p class="font-medium text-text-primary">{{ selectedFeedback.submitter_name || $t('logs.anonymousUserLabel') }}</p>
              <p v-if="selectedFeedback.contact_phone" class="text-sm text-text-secondary">{{ $t('logs.contactPhoneLabel') }}{{ selectedFeedback.contact_phone }}</p>
            </div>

            <!-- Content -->
            <div>
              <p class="text-sm font-medium text-text-primary mb-2">{{ $t('logs.feedbackContentLabel') }}</p>
              <p class="text-sm text-text-secondary bg-gray-50 rounded-lg p-3 whitespace-pre-wrap">{{ selectedFeedback.content }}</p>
            </div>

            <!-- Images -->
            <div v-if="selectedFeedback.images && selectedFeedback.images.length > 0">
              <p class="text-sm font-medium text-text-primary mb-2">{{ $t('logs.relatedImages') }}</p>
              <div class="grid grid-cols-3 gap-2">
                <img v-for="(img, idx) in selectedFeedback.images" :key="idx" :src="img" class="w-full h-24 object-cover rounded-lg border border-gray-100 cursor-pointer hover:opacity-80" @click="window.open(img, '_blank')" />
              </div>
            </div>

            <!-- Reply Section -->
            <div v-if="selectedFeedback.reply" class="bg-success/5 border border-success/20 rounded-lg p-4">
              <p class="text-sm font-medium text-success mb-2 flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                {{ $t('logs.processReply') }}
              </p>
              <p class="text-sm text-text-secondary whitespace-pre-wrap">{{ selectedFeedback.reply }}</p>
              <p v-if="selectedFeedback.resolved_at" class="text-xs text-text-secondary mt-2">
                {{ $t('logs.replyTimeLabel') }}{{ formatTime(selectedFeedback.resolved_at) }}
              </p>
            </div>

            <!-- Admin Actions -->
            <div v-if="isAdmin && selectedFeedback.status !== 'resolved'" class="space-y-4 pt-4 border-t">
              <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('logs.assignAssignee') }}</label>
                <div class="flex gap-2">
                  <select v-model="assignToUser" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                    <option value="">{{ $t('logs.selectAssignee') }}</option>
                    <option v-for="u in systemUsers" :key="u.id" :value="u.id">{{ u.name }} ({{ u.role }})</option>
                  </select>
                  <button @click="assignFeedback" :disabled="!assignToUser || saving" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-hover disabled:opacity-50">
                    {{ $t('logs.assignBtn') }}
                  </button>
                </div>
                <p v-if="selectedFeedback.assigned_to_name" class="text-xs text-text-secondary mt-1">
                  {{ $t('logs.currentAssignee') }}{{ selectedFeedback.assigned_to_name }}
                </p>
              </div>

              <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('logs.replyAndResolve') }}</label>
                <textarea v-model="replyText" rows="4" :placeholder="$t('logs.replyPlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none"></textarea>
                <div class="flex gap-2 mt-2">
                  <button @click="replyFeedback" :disabled="!replyText.trim() || saving" class="px-4 py-2 bg-success text-white rounded-lg text-sm font-medium hover:bg-success/90 disabled:opacity-50">
                    {{ saving ? $t('common.submitting') : $t('logs.replyAndResolveBtn') }}
                  </button>
                  <button @click="closeFeedback" :disabled="saving" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-secondary hover:border-gray-300">
                    {{ $t('logs.closeDirectly') }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Submit Dialog (User) -->
    <Teleport to="body">
      <div v-if="showSubmitDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/30" @click="showSubmitDialog = false"></div>
        <div class="relative w-full max-w-lg bg-white rounded-lg shadow-xl flex flex-col max-h-[90vh]">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b shrink-0">
            <h3 class="text-lg font-bold text-text-primary">{{ $t('logs.submitFeedbackTitle') }}</h3>
            <button @click="showSubmitDialog = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Content -->
          <div class="flex-1 overflow-y-auto p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('logs.feedbackTypeLabel') }} <span class="text-danger">*</span></label>
              <select v-model="submitForm.type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                <option value="complaint">{{ $t('logs.complaintType') }}</option>
                <option value="suggestion">{{ $t('logs.suggestionType') }}</option>
                <option value="bug">{{ $t('logs.bugType') }}</option>
                <option value="other">{{ $t('logs.otherType') }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('logs.titleCol') }} <span class="text-danger">*</span></label>
              <input v-model="submitForm.title" type="text" :placeholder="$t('logs.titlePlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('logs.detailedContent') }} <span class="text-danger">*</span></label>
              <textarea v-model="submitForm.content" rows="5" :placeholder="$t('logs.contentFeedbackPlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('logs.contactPhoneLabel') }}</label>
              <input v-model="submitForm.contact_phone" type="tel" :placeholder="$t('logs.contactPhonePlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('logs.relatedImagesMax') }}</label>
              <div class="grid grid-cols-3 gap-2">
                <div v-for="(img, idx) in submitForm.images" :key="idx" class="relative group">
                  <img :src="img" class="w-full h-24 object-cover rounded-lg border border-gray-100" />
                  <button @click="removeImage(idx)" class="absolute top-1 right-1 bg-danger text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <span class="material-symbols-outlined text-[16px]">close</span>
                  </button>
                </div>
                <label v-if="submitForm.images.length < 5" class="w-full h-24 border-2 border-dashed border-gray-200 rounded-lg flex items-center justify-center cursor-pointer hover:border-primary transition-colors">
                  <input type="file" accept="image/*" multiple @change="handleImageUpload" class="hidden" />
                  <span v-if="uploadingImages" class="text-text-secondary text-xs">{{ $t('logs.uploading') }}</span>
                  <span v-else class="material-symbols-outlined text-text-secondary text-[32px]">add_photo_alternate</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t shrink-0 flex justify-end gap-3">
            <button @click="showSubmitDialog = false" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-secondary hover:border-gray-300">{{ $t('common.cancel') }}</button>
            <button @click="submitFeedback" :disabled="submitting || !submitForm.title.trim() || !submitForm.content.trim()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-hover disabled:opacity-50">
              {{ submitting ? $t('common.submitting') : $t('logs.submitFeedbackBtn') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
