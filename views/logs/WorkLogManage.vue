<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user.js'
import PageHeader from '../../components/PageHeader.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'

const { t, locale } = useI18n()
const userStore = useUserStore()

// ─── State ──────────────────────────────────────────────────────────────────────
const activeTab = ref('my') // 'my' | 'received' | 'templates'
const logs = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = 20

// Log Type
const currentLogType = ref('work')

// Filters
const filterDateStart = ref('')
const filterDateEnd = ref('')

// Templates
const templates = ref([])

// Detail Modal
const showDetailModal = ref(false)
const selectedLog = ref(null)

// Create/Edit Dialog
const showDialog = ref(false)
const dialogMode = ref('create')
const selectedTemplateId = ref(null)
const formData = ref({
  log_type: 'work',
  title: '',
  submit_date: new Date().toISOString().split('T')[0],
  single_line: '',
  multi_line: '',
  location: '',
  location_lat: null,
  location_lng: null,
  images: [],
  participants: [],
  recipients: []
})
const isDraft = ref(false)
const saving = ref(false)

// Participants
const showParticipantModal = ref(false)
const showRecipientModal = ref(false)
const selectedRecipients = ref([])
const availableUsers = ref([])
const selectedParticipants = ref([])

// Template Edit
const showTemplateDialog = ref(false)
const editingTemplate = ref(null)
const templateForm = ref({
  name: '',
  description: '',
  log_type: 'work',
  fields: []
})
const savingTemplate = ref(false)

// ─── Computed ───────────────────────────────────────────────────────────────────
const isAdmin = computed(() => userStore.userRole === 'admin' || userStore.isAdmin)

const filteredTemplates = computed(() => {
  return templates.value.filter(t => t.log_type === currentLogType.value)
})

// ─── Fetch ──────────────────────────────────────────────────────────────────────
async function fetchLogs() {
  loading.value = true
  try {
    const params = { 
      page: currentPage.value, 
      size: pageSize,
      type: activeTab.value,
      log_type: currentLogType.value
    }
    if (filterDateStart.value) params.date_start = filterDateStart.value
    if (filterDateEnd.value) params.date_end = filterDateEnd.value
    
    const res = await api.get('/work-logs', { params })
    if (res.code === 0) {
      logs.value = res.data.list || res.data || []
      total.value = res.data.total || logs.value.length
    }
  } finally {
    loading.value = false
  }
}

async function fetchTemplates() {
  const res = await api.get('/work-logs/templates')
  if (res.code === 0) {
    templates.value = res.data || []
  }
}

async function fetchUsers() {
  try {
    // 参与人选择：获取自己及下级
    const res = await api.get('/users/subordinates')
    if (res.code === 0) {
      availableUsers.value = res.data || []
    }
  } catch {
    // fallback: 获取全部用户列表
    const res = await api.get('/users/list')
    if (res.code === 0) {
      availableUsers.value = res.data || []
    }
  }
}

// ─── Watch ─────────────────────────────────────────────────────────────────────
watch([filterDateStart, filterDateEnd], () => {
  currentPage.value = 1
  fetchLogs()
})
watch(currentPage, fetchLogs)
watch(activeTab, () => {
  if (activeTab.value === 'my' || activeTab.value === 'received') {
    fetchLogs()
  } else if (activeTab.value === 'templates') {
    fetchTemplates()
  }
})

onMounted(() => {
  fetchLogs()
  fetchUsers()
})

// ─── Helpers ───────────────────────────────────────────────────────────────────
function getLogTypeName(type) {
  const map = { work: t('logs.workLogType'), complaint: t('logs.complaintLogType'), share: t('logs.shareLogType') }
  return map[type] || t('logs.logType')
}

function getLogTypeTagClass(type) {
  const map = { work: 'bg-blue-100 text-blue-700', complaint: 'bg-red-100 text-red-700', share: 'bg-green-100 text-green-700' }
  return map[type] || 'bg-gray-100 text-gray-700'
}

function formatDate(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleDateString(locale.value === 'zh' ? 'zh-CN' : 'en-US', { year: 'numeric', month: '2-digit', day: '2-digit' })
}

function getFieldTypeName(type) {
  const map = {
    'text': t('logs.typeText'),
    'number': t('logs.typeNumber'),
    'date': t('logs.typeDate'),
    'time': t('logs.typeTime'),
    'time_range': t('logs.typeTimeRange'),
    'textarea': t('logs.typeTextarea'),
    'select': t('logs.typeSelect'),
    'checkbox': t('logs.typeCheckbox'),
    'radio': t('logs.typeRadio'),
    'location': t('logs.typeLocation'),
    'image': t('logs.typeImage'),
    'participants': t('logs.typeParticipants'),
    'rating': t('logs.typeRating'),
    // legacy label-based mapping
    'title': t('logs.typeText'),
    'submit_date': t('logs.typeDate'),
    'single_line': t('logs.typeText'),
    'multi_line': t('logs.typeTextarea'),
  }
  return map[type] || t('logs.typeText')
}

function hasField(fieldName) {
  if (editingTemplate.value?.fields) {
    const fields = typeof editingTemplate.value.fields === 'string' 
      ? JSON.parse(editingTemplate.value.fields) 
      : editingTemplate.value.fields
    return fields.some(f => f.name === fieldName)
  }
  // Default fields for log types
  if (currentLogType.value === 'work') {
    return ['title', 'submit_date', 'single_line', 'multi_line', 'location', 'images', 'participants'].includes(fieldName)
  }
  return true
}

function parseFields(fields) {
  if (!fields) return []
  try {
    return typeof fields === 'string' ? JSON.parse(fields) : fields
  } catch {
    return []
  }
}

// ─── Actions ───────────────────────────────────────────────────────────────────
function onLogTypeChange() {
  currentPage.value = 1
  fetchLogs()
}

function openDetail(log) {
  selectedLog.value = log
  showDetailModal.value = true
}

function openCreateDialog() {
  dialogMode.value = 'create'
  selectedTemplateId.value = null
  formData.value = {
    log_type: currentLogType.value,
    title: '',
    submit_date: new Date().toISOString().split('T')[0],
    single_line: '',
    multi_line: '',
    location: '',
    location_lat: null,
    location_lng: null,
    images: [],
    participants: [],
    recipients: [],
  recipients: []
  }
  isDraft.value = false
  showDialog.value = true
}

function openEditDialog(log) {
  dialogMode.value = 'edit'
  formData.value = {
    id: log.id,
    log_type: log.log_type || 'work',
    title: log.title || '',
    submit_date: log.submit_date || log.date,
    single_line: log.single_line || '',
    multi_line: log.multi_line || log.content || '',
    location: log.location || '',
    location_lat: log.location_lat,
    location_lng: log.location_lng,
    images: log.images || [],
    participants: log.participants || []
  }
  isDraft.value = log.status === 'draft'
  showDialog.value = true
}

async function deleteLog(id) {
  if (!confirm(t('logs.confirmDeleteLog'))) return
  const res = await api.delete(`/work-logs/${id}`)
  if (res.code === 0) {
    showDetailModal.value = false
    await fetchLogs()
  }
}

async function handleImageUpload(event) {
  const files = Array.from(event.target.files)
  if (files.length === 0) return
  
  for (const file of files) {
    const fd = new FormData()
    fd.append('file', file)
    const res = await api.post('/upload', fd)
    if (res.code === 0) {
      formData.value.images.push(res.data.url)
    }
  }
  event.target.value = ''
}

function removeImage(index) {
  formData.value.images.splice(index, 1)
}

function getLocation() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        formData.value.location_lat = pos.coords.latitude
        formData.value.location_lng = pos.coords.longitude
        formData.value.location = `${t('logs.latitudePrefix')}${pos.coords.latitude.toFixed(6)}, ${t('logs.longitudePrefix')}${pos.coords.longitude.toFixed(6)}`
        alert(t('logs.locationSuccess'))
      },
      (err) => {
        alert(t('logs.locationFail') + err.message)
      }
    )
  } else {
    alert(t('logs.locationNotSupported'))
  }
}

function openRecipientPicker() {
  selectedRecipients.value = [...(formData.value.recipients || [])]
  showRecipientModal.value = true
}

function saveRecipients() {
  formData.value.recipients = [...selectedRecipients.value]
  showRecipientModal.value = false
}

function openParticipantPicker() {
  const existing = formData.value.participants || []
  // 默认勾选当前用户
  const currentUserId = userStore.userId
  if (existing.length === 0 && currentUserId) {
    selectedParticipants.value = [currentUserId]
  } else {
    selectedParticipants.value = [...existing]
  }
  showParticipantModal.value = true
}

function saveParticipants() {
  formData.value.participants = [...selectedParticipants.value]
  showParticipantModal.value = false
}

async function saveLog() {
  if (!formData.value.title) {
    alert(t('logs.pleaseEnterTitle'))
    return
  }

  saving.value = true
  try {
    const payload = {
      ...formData.value,
      status: isDraft.value ? 'draft' : 'submitted',
      template_id: selectedTemplateId.value
    }

    let res
    if (dialogMode.value === 'create') {
      res = await api.post('/work-logs', payload)
    } else {
      res = await api.put(`/work-logs/${formData.value.id}`, payload)
    }

    if (res.code === 0) {
      showDialog.value = false
      await fetchLogs()
    }
  } finally {
    saving.value = false
  }
}

// ─── Template Management ───────────────────────────────────────────────────────
function openCreateTemplate() {
  editingTemplate.value = null
  templateForm.value = {
    name: '',
    description: '',
    log_type: currentLogType.value,
    fields: [
      { name: 'title', label: t('logs.titleOption'), type: 'text', required: true },
      { name: 'submit_date', label: t('logs.dateOption'), type: 'date', required: true }
    ]
  }
  showTemplateDialog.value = true
}

function openEditTemplate(template) {
  editingTemplate.value = template
  templateForm.value = {
    name: template.name,
    description: template.description || '',
    log_type: template.log_type || 'work',
    fields: parseFields(template.fields)
  }
  showTemplateDialog.value = true
}

function addTemplateField() {
  templateForm.value.fields.push({
    name: `field_${Date.now()}`,
    label: '',
    type: 'text',
    required: false
  })
}

function removeTemplateField(index) {
  templateForm.value.fields.splice(index, 1)
}

async function saveTemplate() {
  if (!templateForm.value.name) {
    alert(t('logs.pleaseEnterTemplateName'))
    return
  }

  savingTemplate.value = true
  try {
    let res
    if (editingTemplate.value) {
      res = await api.put(`/work-logs/templates/${editingTemplate.value.id}`, templateForm.value)
    } else {
      res = await api.post('/work-logs/templates', templateForm.value)
    }

    if (res.code === 0) {
      showTemplateDialog.value = false
      fetchTemplates()
    }
  } finally {
    savingTemplate.value = false
  }
}

async function deleteTemplate(id) {
  if (!confirm(t('logs.confirmDeleteTemplate'))) return
  const res = await api.delete(`/work-logs/templates/${id}`)
  if (res.code === 0) {
    fetchTemplates()
  }
}

async function initDefaultTemplates() {
  try {
    const res = await api.post('/work-logs/templates/init-defaults')
    if (res.code === 0) {
      fetchTemplates()
    }
  } catch (e) {
    console.error('Init default templates failed:', e)
  }
}
</script>

<template>
  <div>
    <PageHeader
      :title="activeTab === 'templates' ? $t('logs.logTemplates') : $t('logs.workLogType')"
      :subtitle="activeTab === 'templates' ? $t('logs.manageTemplates') : $t('logs.logsAndFeedback')"
    />

    <!-- Tab Buttons -->
    <div class="flex gap-2 mb-4">
      <button 
        @click="activeTab = 'my'"
        :class="[
          'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
          activeTab === 'my' ? 'bg-primary text-white' : 'bg-white text-gray-600 border border-gray-200'
        ]"
      >
        {{ $t('logs.myLogs') }}
      </button>
      <button
        @click="activeTab = 'received'"
        :class="[
          'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
          activeTab === 'received' ? 'bg-primary text-white' : 'bg-white text-gray-600 border border-gray-200'
        ]"
      >
        {{ $t('logs.received') }}
      </button>
      <button
        v-if="isAdmin"
        @click="activeTab = 'templates'"
        :class="[
          'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
          activeTab === 'templates' ? 'bg-primary text-white' : 'bg-white text-gray-600 border border-gray-200'
        ]"
      >
        {{ $t('logs.templateManage') }}
      </button>
    </div>

    <!-- Log Type Selector (My Tab) -->
    <div v-if="activeTab === 'my'" class="flex gap-3 mb-4">
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="radio" v-model="currentLogType" value="work" @change="onLogTypeChange" class="w-4 h-4 text-primary" />
        <span class="text-sm">{{ $t('logs.workLogType') }}</span>
      </label>
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="radio" v-model="currentLogType" value="complaint" @change="onLogTypeChange" class="w-4 h-4 text-primary" />
        <span class="text-sm">{{ $t('logs.complaintLogType') }}</span>
      </label>
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="radio" v-model="currentLogType" value="share" @change="onLogTypeChange" class="w-4 h-4 text-primary" />
        <span class="text-sm">{{ $t('logs.shareLogType') }}</span>
      </label>
    </div>

    <!-- Filters -->
    <div v-if="activeTab !== 'templates'" class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-4">
      <div class="flex gap-4 flex-wrap">
        <div class="flex-1 min-w-[150px]">
          <label class="text-xs text-text-secondary block mb-1">{{ $t('logs.startDateLabel') }}</label>
          <input 
            type="date" 
            v-model="filterDateStart"
            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"
          />
        </div>
        <div class="flex-1 min-w-[150px]">
          <label class="text-xs text-text-secondary block mb-1">{{ $t('logs.endDateLabel') }}</label>
          <input 
            type="date" 
            v-model="filterDateEnd"
            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"
          />
        </div>
        <div class="flex items-end">
          <button 
            @click="filterDateStart = ''; filterDateEnd = ''; fetchLogs()"
            class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50"
          >
            {{ $t('common.reset') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Log List -->
    <div v-if="activeTab !== 'templates'">
      <div v-if="loading" class="text-center py-8 text-gray-400">
        {{ $t('common.loading') }}
      </div>
      <div v-else-if="logs.length === 0" class="text-center py-8 text-gray-400">
        {{ $t('logs.noLogs') }}
      </div>
      <div v-else class="space-y-3">
        <div 
          v-for="log in logs" 
          :key="log.id"
          @click="openDetail(log)"
          class="bg-white rounded-lg border border-gray-100 shadow-card p-4 cursor-pointer hover:shadow-md transition-shadow"
        >
          <div class="flex justify-between items-start mb-2">
            <div class="flex-1">
              <h3 class="font-medium text-text-primary">{{ log.title || $t('logs.logPrefix') + log.id }}</h3>
              <p class="text-xs text-text-secondary mt-1">{{ formatDate(log.submit_date || log.date) }}</p>
            </div>
            <div class="flex gap-2">
              <span :class="['px-2 py-1 rounded text-xs font-medium', getLogTypeTagClass(log.log_type)]">
                {{ getLogTypeName(log.log_type) }}
              </span>
              <span v-if="log.status === 'draft'" class="px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-700">
                {{ $t('logs.draft') }}
              </span>
            </div>
          </div>
          <p v-if="log.multi_line" class="text-sm text-text-secondary line-clamp-2">{{ log.multi_line }}</p>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="total > pageSize" class="mt-4 flex justify-center">
        <Pagination 
          :total="total" 
          :page="currentPage" 
          :size="pageSize"
          @change="p => { currentPage = p; fetchLogs() }"
        />
      </div>

      <!-- Create Button -->
      <button 
        v-if="activeTab === 'my'"
        @click="openCreateDialog"
        class="fixed bottom-6 right-6 w-14 h-14 bg-primary text-white rounded-full shadow-lg flex items-center justify-center hover:bg-primary/90 transition-colors"
      >
        <span class="material-symbols-outlined text-3xl">add</span>
      </button>
    </div>

    <!-- Template List -->
    <div v-if="activeTab === 'templates'">
      <div v-if="loading" class="text-center py-8 text-gray-400">
        {{ $t('common.loading') }}
      </div>
      <div v-else class="space-y-3">
        <div 
          v-for="template in templates" 
          :key="template.id"
          class="bg-white rounded-lg border border-gray-100 shadow-card p-4"
        >
          <div class="flex justify-between items-start">
            <div class="flex-1" @click="openEditTemplate(template)">
              <h3 class="font-medium text-text-primary">{{ template.name }}</h3>
              <p class="text-xs text-text-secondary mt-1">{{ template.description || $t('logs.noDescription') }}</p>
              <span :class="['inline-block mt-2 px-2 py-1 rounded text-xs font-medium', getLogTypeTagClass(template.log_type)]">
                {{ getLogTypeName(template.log_type) }}
              </span>
            </div>
            <button
              @click.stop="deleteTemplate(template.id)"
              class="text-red-500 text-sm p-2"
            >
              {{ $t('common.delete') }}
            </button>
          </div>
        </div>
      </div>

      <button
        v-if="isAdmin"
        @click="openCreateTemplate"
        class="mt-4 w-full py-3 border-2 border-dashed border-gray-300 rounded-lg text-gray-500 hover:border-primary hover:text-primary transition-colors"
      >
        {{ $t('logs.newTemplate') }}
      </button>
      <button
        v-if="isAdmin && filteredTemplates.length === 0"
        @click="initDefaultTemplates"
        class="mt-2 w-full py-3 bg-primary/10 text-primary rounded-lg text-sm hover:bg-primary/20 transition-colors"
      >
        {{ $t('logs.initDefaults') }}
      </button>
    </div>

    <!-- Detail Modal -->
    <div v-if="showDetailModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showDetailModal = false">
      <div class="bg-white rounded-xl max-w-lg w-full max-h-[80vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-100 px-4 py-3 flex justify-between items-center">
          <h3 class="font-medium">{{ $t('logs.logDetail') }}</h3>
          <button @click="showDetailModal = false" class="text-gray-400">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-4 space-y-3">
          <div class="flex justify-between">
            <span class="text-text-secondary text-sm">{{ $t('logs.typeLabel') }}</span>
            <span :class="['px-2 py-1 rounded text-xs font-medium', getLogTypeTagClass(selectedLog.log_type)]">
              {{ getLogTypeName(selectedLog.log_type) }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-text-secondary text-sm">{{ $t('logs.titleLabel') }}</span>
            <span class="text-text-primary">{{ selectedLog.title }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-text-secondary text-sm">{{ $t('logs.dateLabel') }}</span>
            <span class="text-text-primary">{{ formatDate(selectedLog.submit_date || selectedLog.date) }}</span>
          </div>
          <div v-if="selectedLog.single_line">
            <span class="text-text-secondary text-sm block">{{ $t('logs.singleLineText') }}</span>
            <span class="text-text-primary">{{ selectedLog.single_line }}</span>
          </div>
          <div v-if="selectedLog.multi_line">
            <span class="text-text-secondary text-sm block">{{ $t('logs.multiLineText') }}</span>
            <p class="text-text-primary whitespace-pre-wrap">{{ selectedLog.multi_line }}</p>
          </div>
          <div v-if="selectedLog.location">
            <span class="text-text-secondary text-sm block">{{ $t('logs.locationLabel') }}</span>
            <span class="text-text-primary">{{ selectedLog.location }}</span>
          </div>
          <div v-if="selectedLog.images && selectedLog.images.length">
            <span class="text-text-secondary text-sm block mb-2">{{ $t('logs.imagesLabel') }}</span>
            <div class="grid grid-cols-3 gap-2">
              <img 
                v-for="(img, idx) in selectedLog.images" 
                :key="idx"
                :src="img.url || img" 
                class="w-full h-20 object-cover rounded"
              />
            </div>
          </div>
        </div>
        <div class="sticky bottom-0 bg-white border-t border-gray-100 px-4 py-3 flex gap-2">
          <button
            v-if="activeTab === 'my'"
            @click="openEditDialog(selectedLog); showDetailModal = false"
            class="flex-1 py-2 bg-primary text-white rounded-lg text-sm"
          >
            {{ $t('common.edit') }}
          </button>
          <button
            v-if="activeTab === 'my'"
            @click="deleteLog(selectedLog.id)"
            class="flex-1 py-2 bg-red-500 text-white rounded-lg text-sm"
          >
            {{ $t('common.delete') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <div v-if="showDialog" class="fixed inset-0 bg-black/50 flex items-end z-50" @click.self="showDialog = false">
      <div class="bg-white rounded-t-xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-100 px-4 py-3 flex justify-between items-center">
          <h3 class="font-medium">{{ dialogMode === 'create' ? $t('logs.writeLogTitle') : $t('logs.editLogTitle') }}</h3>
          <button @click="showDialog = false" class="text-gray-400">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="p-4 space-y-4">
          <!-- Log Type -->
          <div>
            <label class="text-sm text-text-secondary block mb-2">{{ $t('logs.logTypeLabel') }}</label>
            <div class="flex gap-3">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" v-model="formData.log_type" value="work" class="w-4 h-4 text-primary" />
                <span class="text-sm">{{ $t('logs.workLogType') }}</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" v-model="formData.log_type" value="complaint" class="w-4 h-4 text-primary" />
                <span class="text-sm">{{ $t('logs.complaintLogType') }}</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" v-model="formData.log_type" value="share" class="w-4 h-4 text-primary" />
                <span class="text-sm">{{ $t('logs.shareLogType') }}</span>
              </label>
            </div>
          </div>

          <!-- Title -->
          <div v-if="hasField('title')">
            <label class="text-sm text-text-secondary block mb-1">{{ $t('logs.titleRequired') }} <span class="text-red-500">*</span></label>
            <input
              v-model="formData.title"
              type="select"
              :placeholder="$t('logs.enterTitle')"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"
            />
          </div>

          <!-- Date -->
          <div v-if="hasField('submit_date')">
            <label class="text-sm text-text-secondary block mb-1">{{ $t('logs.dateLabel') }}</label>
            <input 
              v-model="formData.submit_date"
              type="date"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"
            />
          </div>

          <!-- Single Line -->
          <div v-if="hasField('single_line')">
            <label class="text-sm text-text-secondary block mb-1">{{ $t('logs.singleLineText') }}</label>
            <input
              v-model="formData.single_line"
              type="select"
              :placeholder="$t('logs.enterText')"
              maxlength="255"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"
            />
          </div>

          <!-- Multi Line -->
          <div v-if="hasField('multi_line')">
            <label class="text-sm text-text-secondary block mb-1">{{ $t('logs.multiLineText') }}</label>
            <textarea
              v-model="formData.multi_line"
              :placeholder="$t('logs.enterContent')"
              maxlength="2550"
              rows="4"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm resize-none"
            ></textarea>
          </div>

          <!-- Location -->
          <div v-if="hasField('location')">
            <label class="text-sm text-text-secondary block mb-1">{{ $t('logs.locationOption') }}</label>
            <button
              @click="getLocation"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-left flex justify-between items-center"
            >
              <span>{{ formData.location || $t('logs.getLocation') }}</span>
              <span class="material-symbols-outlined text-gray-400">my_location</span>
            </button>
          </div>

          <!-- Images -->
          <div v-if="hasField('images')">
            <label class="text-sm text-text-secondary block mb-1">{{ $t('logs.imagesLabel') }}</label>
            <div class="grid grid-cols-4 gap-2">
              <div 
                v-for="(img, idx) in formData.images" 
                :key="idx"
                class="relative aspect-square"
              >
                <img :src="img" class="w-full h-full object-cover rounded" />
                <button 
                  @click="removeImage(idx)"
                  class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs"
                >
                  ×
                </button>
              </div>
              <label class="aspect-square border-2 border-dashed border-gray-200 rounded-lg flex items-center justify-center cursor-pointer hover:border-primary">
                <span class="material-symbols-outlined text-gray-400">add_photo_alternate</span>
                <input type="file" accept="image/*" multiple @change="handleImageUpload" class="hidden" />
              </label>
            </div>
          </div>

          <!-- Participants -->
          <div v-if="hasField('participants')">
            <label class="text-sm text-text-secondary block mb-1">{{ $t('logs.participantsLabel') }}</label>
            <button
              @click="openParticipantPicker"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-left flex justify-between items-center"
            >
              <span>{{ formData.participants?.length ? $t('logs.selectedCount', { count: formData.participants.length }) : $t('logs.selectParticipants') }}</span>
              <span class="material-symbols-outlined text-gray-400">chevron_right</span>
            </button>
          </div>

          <!-- Draft -->
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="isDraft" class="w-4 h-4 text-primary rounded" />
            <span class="text-sm">{{ $t('logs.saveAsDraft') }}</span>
          </label>
        </div>

        <div class="sticky bottom-0 bg-white border-t border-gray-100 px-4 py-3">
          <button 
            @click="saveLog"
            :disabled="saving"
            class="w-full py-3 bg-primary text-white rounded-lg font-medium disabled:opacity-50"
          >
            {{ saving ? $t('logs.saving') : $t('logs.submitBtn') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Recipient Picker (收件人) -->
    <div v-if="showRecipientModal" class="fixed inset-0 bg-black/50 flex items-end z-50" @click.self="showRecipientModal = false">
      <div class="bg-white rounded-t-xl w-full max-h-[70vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-100 px-4 py-3 flex justify-between items-center">
          <h3 class="font-medium">{{ $t('logs.selectRecipientTitle') }}</h3>
          <button @click="showRecipientModal = false" class="text-gray-400">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-4">
          <div class="text-xs text-gray-500 mb-2">{{ $t('logs.autoIncludeAdmins') }}</div>
          <div 
            v-for="user in availableUsers" 
            :key="user.id"
            class="flex items-center gap-3 py-3 border-b border-gray-50"
          >
            <input 
              type="checkbox" 
              :value="user.id" 
              v-model="selectedRecipients"
              class="w-4 h-4 text-primary rounded"
            />
            <span>{{ user.name }}</span>
            <span v-if="user.role === 'admin'" class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded">{{ $t('logs.adminTag') }}</span>
          </div>
        </div>
        <div class="sticky bottom-0 bg-white border-t border-gray-100 px-4 py-3">
          <button 
            @click="saveRecipients"
            class="w-full py-3 bg-primary text-white rounded-lg font-medium"
          >
            {{ $t('common.confirm') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Participant Picker -->
    <div v-if="showParticipantModal" class="fixed inset-0 bg-black/50 flex items-end z-50" @click.self="showParticipantModal = false">
      <div class="bg-white rounded-t-xl w-full max-h-[70vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-100 px-4 py-3 flex justify-between items-center">
          <h3 class="font-medium">{{ $t('logs.selectParticipants') }}</h3>
          <button @click="showParticipantModal = false" class="text-gray-400">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-4">
          <div
            v-for="user in availableUsers"
            :key="user.id"
            class="flex items-center gap-3 py-3 border-b border-gray-50"
          >
            <input
              type="checkbox"
              :value="user.id"
              v-model="selectedParticipants"
              class="w-4 h-4 text-primary rounded"
            />
            <div class="flex-1 min-w-0">
              <span class="text-sm">{{ user.name }}</span>
              <span v-if="user.department" class="text-xs text-text-secondary ml-2">({{ user.department }})</span>
            </div>
            <span v-if="user.is_self" class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded shrink-0">
              {{ $t('logs.myself') }}
            </span>
          </div>
        </div>
        <div class="sticky bottom-0 bg-white border-t border-gray-100 px-4 py-3">
          <button
            @click="saveParticipants"
            class="w-full py-3 bg-primary text-white rounded-lg font-medium"
          >
            {{ $t('common.confirm') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Template Dialog -->
    <div v-if="showTemplateDialog" class="fixed inset-0 bg-black/50 flex items-end z-50" @click.self="showTemplateDialog = false">
      <div class="bg-white rounded-t-xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-100 px-4 py-3 flex justify-between items-center">
          <h3 class="font-medium">{{ editingTemplate ? $t('logs.editTemplateTitle') : $t('logs.newTemplateTitle') }}</h3>
          <button @click="showTemplateDialog = false" class="text-gray-400">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="p-4 space-y-4">
          <div>
            <label class="text-sm text-text-secondary block mb-1">{{ $t('logs.templateNameLabel') }} <span class="text-red-500">*</span></label>
            <input
              v-model="templateForm.name"
              type="select"
              :placeholder="$t('logs.enterTemplateName')"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"
            />
          </div>

          <div>
            <label class="text-sm text-text-secondary block mb-1">{{ $t('logs.templateDescription') }}</label>
            <textarea
              v-model="templateForm.description"
              :placeholder="$t('logs.enterDescription')"
              rows="2"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm resize-none"
            ></textarea>
          </div>

          <div>
            <label class="text-sm text-text-secondary block mb-2">{{ $t('logs.applicableType') }}</label>
            <div class="flex gap-3">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" v-model="templateForm.log_type" value="work" class="w-4 h-4 text-primary" />
                <span class="text-sm">{{ $t('logs.workLogType') }}</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" v-model="templateForm.log_type" value="complaint" class="w-4 h-4 text-primary" />
                <span class="text-sm">{{ $t('logs.complaintLogType') }}</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" v-model="templateForm.log_type" value="share" class="w-4 h-4 text-primary" />
                <span class="text-sm">{{ $t('logs.shareLogType') }}</span>
              </label>
            </div>
          </div>

          <div>
            <div class="flex justify-between items-center mb-2">
              <label class="text-sm text-text-secondary">{{ $t('logs.fieldConfig') }}</label>
              <button @click="addTemplateField" class="text-primary text-sm">{{ $t('logs.addFieldBtn') }}</button>
            </div>
            <div v-for="(field, index) in templateForm.fields" :key="index" class="bg-gray-50 rounded-lg p-3 mb-2">
              <div class="flex items-center gap-2 mb-2">
                <input
                  v-model="field.label"
                  type="text"
                  :placeholder="$t('logs.fieldLabelPlaceholder')"
                  class="flex-1 px-2 py-1 border border-gray-200 rounded text-sm"
                />
                <select
                  v-model="field.type"
                  class="px-2 py-1 border border-gray-200 rounded text-sm"
                >
                  <option value="text">{{ $t('logs.typeText') }}</option>
                  <option value="number">{{ $t('logs.typeNumber') }}</option>
                  <option value="date">{{ $t('logs.typeDate') }}</option>
                  <option value="time">{{ $t('logs.typeTime') }}</option>
                  <option value="time_range">{{ $t('logs.typeTimeRange') }}</option>
                  <option value="textarea">{{ $t('logs.typeTextarea') }}</option>
                  <option value="select">{{ $t('logs.typeSelect') }}</option>
                  <option value="checkbox">{{ $t('logs.typeCheckbox') }}</option>
                  <option value="radio">{{ $t('logs.typeRadio') }}</option>
                  <option value="location">{{ $t('logs.typeLocation') }}</option>
                  <option value="image">{{ $t('logs.typeImage') }}</option>
                  <option value="participants">{{ $t('logs.typeParticipants') }}</option>
                  <option value="rating">{{ $t('logs.typeRating') }}</option>
                </select>
                <button @click="removeTemplateField(index)" class="text-red-500 text-sm">{{ $t('common.delete') }}</button>
              </div>
              <!-- Type-specific config -->
              <div class="space-y-2 text-xs">
                <!-- text/textarea: maxLength -->
                <div v-if="['text', 'textarea'].includes(field.type)" class="flex items-center gap-2">
                  <label>{{ $t('logs.maxLength') }}:</label>
                  <input v-model.number="field.maxLength" type="number" class="w-20 px-2 py-1 border border-gray-200 rounded" />
                </div>
                <!-- number: min/max -->
                <div v-if="field.type === 'number'" class="flex items-center gap-2">
                  <label>{{ $t('logs.minValue') }}:</label>
                  <input v-model.number="field.min" type="number" class="w-20 px-2 py-1 border border-gray-200 rounded" />
                  <label>{{ $t('logs.maxValue') }}:</label>
                  <input v-model.number="field.max" type="number" class="w-20 px-2 py-1 border border-gray-200 rounded" />
                </div>
                <!-- select/radio/checkbox: options -->
                <div v-if="['select', 'radio', 'checkbox'].includes(field.type)">
                  <label class="block mb-1">{{ $t('logs.options') }}:</label>
                  <div class="space-y-1">
                    <div v-for="(opt, i) in (field.options || [])" :key="i" class="flex items-center gap-1">
                      <input v-model="field.options[i]" type="text" class="flex-1 px-2 py-1 border border-gray-200 rounded text-xs" />
                      <button @click="field.options.splice(i, 1)" class="text-red-500 text-xs">×</button>
                    </div>
                  </div>
                  <button @click="(field.options = field.options || []).push('')" class="text-primary text-xs mt-1">+ {{ $t('logs.addOption') }}</button>
                </div>
                <!-- image: maxCount -->
                <div v-if="field.type === 'image'" class="flex items-center gap-2">
                  <label>{{ $t('logs.maxImages') }}:</label>
                  <input v-model.number="field.maxCount" type="number" class="w-20 px-2 py-1 border border-gray-200 rounded" />
                </div>
                <!-- rating: maxRating -->
                <div v-if="field.type === 'rating'" class="flex items-center gap-2">
                  <label>{{ $t('logs.maxRating') }}:</label>
                  <input v-model.number="field.maxRating" type="number" min="1" max="10" class="w-20 px-2 py-1 border border-gray-200 rounded" />
                </div>
                <!-- required checkbox -->
                <label class="flex items-center gap-1">
                  <input type="checkbox" v-model="field.required" class="w-3 h-3" />
                  <span>{{ $t('logs.requiredField') }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="sticky bottom-0 bg-white border-t border-gray-100 px-4 py-3">
          <button 
            @click="saveTemplate"
            :disabled="savingTemplate"
            class="w-full py-3 bg-primary text-white rounded-lg font-medium disabled:opacity-50"
          >
            {{ savingTemplate ? $t('logs.savingTemplate') : $t('logs.saveTemplate') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
