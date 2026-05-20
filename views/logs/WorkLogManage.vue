<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user.js'
import PageHeader from '../../components/PageHeader.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'

// Prevent Vite from tree-shaking the watch import (used at top-level call sites)
void watch

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
const selectedTemplateForCreate = ref(null)
const activeTemplateId = ref(null) // user's manual selection in dropdown
// selectedTemplateId reflects activeTemplateId if set, otherwise first template
const selectedTemplateId = computed(() => activeTemplateId.value ?? filteredTemplates.value[0]?.id ?? null)
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

// Participants / Recipients / Complainants
const showParticipantModal = ref(false)
const showRecipientModal = ref(false)
const showComplainantModal = ref(false)
const selectedRecipients = ref([])
const selectedComplainants = ref([])
const availableUsers = ref([])
const selectedParticipants = ref([])
const recipientSearch = ref('')
const participantSearch = ref('')
const complainantSearch = ref('')

// Interactions (reactions)
const interactionsMap = ref({}) // logId -> interactions[]
const commentInputs = ref({})   // logId -> comment text

function canEditDelete(logId) {
  const list = interactionsMap.value[logId] || []
  const hasInteractions = list.some(i => ['comment','like','dislike','forward'].includes(i.type))
  // 有互动 → 仅管理员可编辑/删除
  if (hasInteractions) return isAdmin.value
  // 无互动 → 创建者可编辑/删除（成员也行）
  const log = logs.value.find(l => l.id === logId)
  return isAdmin.value || (log && log.user_id === userStore.userInfo?.id)
}

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
const isAdmin = computed(() => userStore.canAccess('worklogs_admin'))

// 全部模板可选，不限制类型
const filteredTemplates = computed(() => {
  return templates.value
})

const filteredRecipients = computed(() => {
  const q = recipientSearch.value.toLowerCase()
  if (!q) return availableUsers.value
  return availableUsers.value.filter(u =>
    u.name.toLowerCase().includes(q) ||
    (u.department || '').toLowerCase().includes(q)
  )
})

const filteredParticipants = computed(() => {
  const q = participantSearch.value.toLowerCase()
  if (!q) return availableUsers.value
  return availableUsers.value.filter(u =>
    u.name.toLowerCase().includes(q) ||
    (u.department || '').toLowerCase().includes(q)
  )
})

const filteredComplainants = computed(() => {
  const q = complainantSearch.value.toLowerCase()
  if (!q) return availableUsers.value
  return availableUsers.value.filter(u =>
    u.name.toLowerCase().includes(q) ||
    (u.department || '').toLowerCase().includes(q)
  )
})

function getAvatarColor(name) {
  const colors = ['bg-primary', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-pink-500', 'bg-indigo-500']
  let hash = 0
  for (let i = 0; i < name.length; i++) hash += name.charCodeAt(i)
  return colors[hash % colors.length]
}
function getAvatarInitial(name) {
  return name ? name[0].toUpperCase() : '?'
}

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
    // "all" tab shows all logs regardless of date filter
    if (activeTab.value !== 'all') {
      if (filterDateStart.value) params.date_start = filterDateStart.value
      if (filterDateEnd.value) params.date_end = filterDateEnd.value
    }
    
    const res = await api.get('/work-logs', { params })
    if (res.code === 0) {
      logs.value = res.data.logs || res.data.list || res.data || []
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
    // 收件人/参与人：获取全部用户列表
    const res = await api.get('/users/list')
    if (res.code === 0) {
      availableUsers.value = res.data || []
    }
  } catch {
    availableUsers.value = []
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
  fetchTemplates()
})

// ─── Helpers ───────────────────────────────────────────────────────────────────
function getLogTypeName(type) {
  const map = { work: t('logs.workLogType'), complaint: t('logs.complaintLogType'), share: t('logs.shareLogType'), task: '任务' }
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
    'recipients': t('logs.typeRecipients'),
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
    return ['title', 'submit_date', 'single_line', 'multi_line', 'location', 'images', 'participants', 'recipients'].includes(fieldName)
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

function openCreateDialog() {
  dialogMode.value = 'create'
  const firstTpl = filteredTemplates.value[0]
  if (firstTpl) {
    activeTemplateId.value = firstTpl.id
    // Set selectedTemplateForCreate synchronously so template v-if renders on first pass
    selectedTemplateForCreate.value = firstTpl
    applyTemplateToForm(firstTpl)
  } else {
    activeTemplateId.value = null
    selectedTemplateForCreate.value = null
    formData.value = {
      log_type: currentLogType.value,
      submit_date: new Date().toISOString().split('T')[0],
      content: {},
      participants: [],
      recipients: [],
    }
  }
  isDraft.value = false
  showDialog.value = true
}

function onTemplateChangeForCreate() {
  const tpl = templates.value.find(t => t.id === activeTemplateId.value)
  if (tpl) {
    currentLogType.value = tpl.log_type
    applyTemplateToForm(tpl)
  }
}

function applyTemplateToForm(template) {
  selectedTemplateForCreate.value = template
  const fields = parseFields(template.fields)
  const content = {}
  fields.forEach(f => {
    if (f.type === 'checkbox' || f.type === 'image') {
      content[f.name] = []
    } else if (f.type === 'rating') {
      content[f.name] = 0
    } else {
      content[f.name] = ''
    }
  })
  formData.value = {
    log_type: template.log_type || 'work',
    submit_date: new Date().toISOString().split('T')[0],
    content,
    participants: [],
    recipients: [],
  }
}

function openEditDialog(log) {
  dialogMode.value = 'edit'
  // Load log content as the content object
  let content = {}
  if (log.content && typeof log.content === 'object') {
    content = { ...log.content }
  }
  // Also support legacy flat fields
  if (!content.title && log.title) content.title = log.title
  if (!content.single_line && log.single_line) content.single_line = log.single_line
  if (!content.multi_line && log.multi_line) content.multi_line = log.multi_line
  if (!content.location && log.location) content.location = log.location

  // Find the template for this log
  const tpl = templates.value.find(t => t.id === log.template_id)
  selectedTemplateForCreate.value = tpl || null

  formData.value = {
    id: log.id,
    log_type: log.log_type || 'work',
    submit_date: log.submit_date || log.date,
    content,
    images: log.images || [],
    participants: log.participants || [],
    recipients: log.recipients || [],
    template_id: log.template_id,
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

// ─── Interactions ────────────────────────────────────────────────────────────────

async function fetchInteractions(logId) {
  try {
    const res = await api.get(`/work-logs/${logId}/interactions`)
    if (res.code === 0) {
      interactionsMap.value[logId] = res.data || []
    }
  } catch (e) {
    console.error('fetchInteractions error', e)
  }
}

function getLogInteraction(logId) {
  const list = interactionsMap.value[logId] || []
  const uid = userStore.userInfo?.id
  return {
    liked: list.some(i => i.type === 'like' && i.user_id === uid),
    disliked: list.some(i => i.type === 'dislike' && i.user_id === uid),
    forwarded: list.some(i => i.type === 'forward' && i.user_id === uid),
    comments: list.filter(i => i.type === 'comment'),
    likeCount: list.filter(i => i.type === 'like').length,
    dislikeCount: list.filter(i => i.type === 'dislike').length,
    forwardCount: list.filter(i => i.type === 'forward').length,
  }
}

async function toggleLike(logId) {
  const res = await api.post(`/work-logs/${logId}/like`)
  if (res.code === 0) {
    await fetchInteractions(logId)
  }
}

async function toggleDislike(logId) {
  const res = await api.post(`/work-logs/${logId}/dislike`)
  if (res.code === 0) {
    await fetchInteractions(logId)
  }
}

async function forwardLog(logId) {
  const res = await api.post(`/work-logs/${logId}/forward`)
  if (res.code === 0) {
    await fetchInteractions(logId)
  }
}

async function submitComment(logId) {
  const text = (commentInputs.value[logId] || '').trim()
  if (!text) return
  const res = await api.post(`/work-logs/${logId}/comment`, { content: text })
  if (res.code === 0) {
    commentInputs.value[logId] = ''
    await fetchInteractions(logId)
  }
}

async function openDetail(log) {
  selectedLog.value = log
  showDetailModal.value = true
  await fetchInteractions(log.id)
  commentInputs.value[log.id] = ''
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
        formData.value.content['location'] = `${t('logs.latitudePrefix')}${pos.coords.latitude.toFixed(6)}, ${t('logs.longitudePrefix')}${pos.coords.longitude.toFixed(6)}`
        formData.value.content['location_lat'] = pos.coords.latitude
        formData.value.content['location_lng'] = pos.coords.longitude
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

function getLocationForField(fieldName) {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        formData.value.content[fieldName] = `${t('logs.latitudePrefix')}${pos.coords.latitude.toFixed(6)}, ${t('logs.longitudePrefix')}${pos.coords.longitude.toFixed(6)}`
        formData.value.content[fieldName + '_lat'] = pos.coords.latitude
        formData.value.content[fieldName + '_lng'] = pos.coords.longitude
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

async function handleImageUploadForField(event, fieldName) {
  const files = Array.from(event.target.files)
  if (files.length === 0) return
  if (!formData.value.content[fieldName]) {
    formData.value.content[fieldName] = []
  }
  for (const file of files) {
    const fd = new FormData()
    fd.append('file', file)
    const res = await api.post('/upload', fd)
    if (res.code === 0) {
      formData.value.content[fieldName].push(res.data.url)
    }
  }
  event.target.value = ''
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

function openComplainantPicker() {
  selectedComplainants.value = [...(formData.value.complainants || [])]
  showComplainantModal.value = true
}

function saveComplainants() {
  formData.value.complainants = [...selectedComplainants.value]
  showComplainantModal.value = false
}

async function saveLog() {
  // Validate required fields from template
  if (selectedTemplateForCreate.value) {
    const fields = parseFields(selectedTemplateForCreate.value.fields)
    for (const field of fields) {
      if (field.required) {
        const val = formData.value.content[field.name]
        if (!val || (typeof val === 'string' && !val.trim()) || (Array.isArray(val) && val.length === 0)) {
          alert(`${field.label} 不能为空`)
          return
        }
      }
    }
  }

  saving.value = true
  try {
    const payload = {
      log_type: formData.value.log_type,
      submit_date: formData.value.submit_date,
      content: formData.value.content,
      participants: formData.value.participants,
      recipients: formData.value.recipients,
      status: isDraft.value ? 'draft' : 'submitted',
      template_id: selectedTemplateForCreate.value ? selectedTemplateForCreate.value.id : formData.value.template_id,
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
    } else if (res.code === 400) {
      alert(res.message || '保存失败')
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

function moveFieldUp(index) {
  if (index === 0) return
  const fields = templateForm.value.fields
  ;[fields[index - 1], fields[index]] = [fields[index], fields[index - 1]]
}

function moveFieldDown(index) {
  const fields = templateForm.value.fields
  if (index === fields.length - 1) return
  ;[fields[index], fields[index + 1]] = [fields[index + 1], fields[index]]
}

async function saveTemplate() {
  if (!templateForm.value.name) {
    alert(t('logs.pleaseEnterTemplateName'))
    return
  }

  // Auto-fill empty label with type name
  templateForm.value.fields.forEach(f => {
    if (!f.label) f.label = f.type
  })

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
    } else if (res.code === 400) {
      alert(res.message || t('logs.saveTemplateFailed'))
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
        @click="activeTab = 'all'"
        :class="[
          'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
          activeTab === 'all' ? 'bg-primary text-white' : 'bg-white text-gray-600 border border-gray-200'
        ]"
      >
        {{ $t('logs.viewAll') }}
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

    <!-- Template Selector (My Tab) -->
    <div v-if="activeTab === 'my'" class="flex gap-3 mb-4">
      <select
        v-model="activeTemplateId"
        @change="onTemplateChangeForCreate"
        class="px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white"
      >
        <option v-for="tpl in filteredTemplates" :key="tpl.id" :value="tpl.id">
          {{ tpl.name }}
        </option>
      </select>
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
              <h3 class="font-medium text-text-primary">{{ log.content?.title || log.title || $t('logs.logPrefix') + log.id }}</h3>
              <p class="text-xs text-text-secondary mt-1">{{ formatDate(log.submit_date || log.date) }}</p>
            </div>
            <div class="flex gap-2">
              <span :class="['px-2 py-1 rounded text-xs font-medium', getLogTypeTagClass(log.log_type)]">
                {{ getLogTypeName(log.log_type) }}
              </span>
              <span v-if="log.status === 'draft'" class="px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-700">
                {{ $t('logs.draft') }}
              </span>
              <!-- Admin: Edit & Delete (all tab) -->
              <span v-if="isAdmin && activeTab === 'all'" class="flex gap-1 ml-auto">
                <button
                  @click.stop="openEdit(log)"
                  class="px-2 py-1 text-xs text-primary hover:bg-primary/10 rounded transition-colors"
                >{{ $t('common.edit') }}</button>
                <button
                  @click.stop="deleteLog(log.id)"
                  class="px-2 py-1 text-xs text-red-500 hover:bg-red-50 rounded transition-colors"
                >{{ $t('common.delete') }}</button>
              </span>
            </div>
          </div>
          <p v-if="log.multi_line" class="text-sm text-text-secondary line-clamp-2 mb-2">{{ log.multi_line }}</p>
          <!-- Interaction buttons row -->
          <div class="flex items-center gap-4 pt-2 border-t border-gray-50" @click.stop>
            <!-- Like -->
            <button
              @click.stop="toggleLike(log.id)"
              class="flex items-center gap-1 text-xs transition-colors"
              :class="getLogInteraction(log.id).liked ? 'text-red-500' : 'text-gray-400 hover:text-red-400'"
            >
              <span class="material-symbols-outlined text-base">{{ getLogInteraction(log.id).liked ? 'favorite' : 'favorite_border' }}</span>
              <span>{{ getLogInteraction(log.id).likeCount }}</span>
            </button>
            <!-- Dislike -->
            <button
              @click.stop="toggleDislike(log.id)"
              class="flex items-center gap-1 text-xs transition-colors"
              :class="getLogInteraction(log.id).disliked ? 'text-orange-500' : 'text-gray-400 hover:text-orange-400'"
            >
              <span class="material-symbols-outlined text-base">{{ getLogInteraction(log.id).disliked ? 'thumb_down' : 'thumb_down_off' }}</span>
              <span>{{ getLogInteraction(log.id).dislikeCount }}</span>
            </button>
            <!-- Forward -->
            <button
              @click.stop="forwardLog(log.id)"
              class="flex items-center gap-1 text-xs transition-colors"
              :class="getLogInteraction(log.id).forwarded ? 'text-blue-500' : 'text-gray-400 hover:text-blue-400'"
            >
              <span class="material-symbols-outlined text-base">{{ getLogInteraction(log.id).forwarded ? 'redo' : 'share' }}</span>
              <span>{{ getLogInteraction(log.id).forwardCount }}</span>
            </button>
            <!-- Comment count -->
            <button
              @click.stop="openDetail(log)"
              class="flex items-center gap-1 text-xs text-gray-400 hover:text-primary transition-colors"
            >
              <span class="material-symbols-outlined text-base">chat_bubble_outline</span>
              <span>{{ getLogInteraction(log.id).comments.length }}</span>
            </button>
          </div>
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
          <!-- Interaction buttons in modal header -->
          <div class="flex items-center gap-2">
            <button
              @click="selectedLog && toggleLike(selectedLog.id)"
              class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs transition-colors"
              :class="selectedLog && getLogInteraction(selectedLog.id).liked ? 'bg-red-50 text-red-500' : 'bg-gray-50 text-gray-500 hover:bg-red-50 hover:text-red-400'"
            >
              <span class="material-symbols-outlined text-sm">{{ selectedLog && getLogInteraction(selectedLog.id).liked ? 'favorite' : 'favorite_border' }}</span>
              <span>{{ selectedLog ? getLogInteraction(selectedLog.id).likeCount : 0 }}</span>
            </button>
            <button
              @click="selectedLog && toggleDislike(selectedLog.id)"
              class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs transition-colors"
              :class="selectedLog && getLogInteraction(selectedLog.id).disliked ? 'bg-orange-50 text-orange-500' : 'bg-gray-50 text-gray-500 hover:bg-orange-50 hover:text-orange-400'"
            >
              <span class="material-symbols-outlined text-sm">{{ selectedLog && getLogInteraction(selectedLog.id).disliked ? 'thumb_down' : 'thumb_down_off' }}</span>
              <span>{{ selectedLog ? getLogInteraction(selectedLog.id).dislikeCount : 0 }}</span>
            </button>
            <button
              @click="selectedLog && forwardLog(selectedLog.id)"
              class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs transition-colors"
              :class="selectedLog && getLogInteraction(selectedLog.id).forwarded ? 'bg-blue-50 text-blue-500' : 'bg-gray-50 text-gray-500 hover:bg-blue-50 hover:text-blue-400'"
            >
              <span class="material-symbols-outlined text-sm">{{ selectedLog && getLogInteraction(selectedLog.id).forwarded ? 'redo' : 'share' }}</span>
              <span>{{ selectedLog ? getLogInteraction(selectedLog.id).forwardCount : 0 }}</span>
            </button>
            <button @click="showDetailModal = false" class="text-gray-400 ml-1">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
        </div>
        <div class="p-4 space-y-3">
          <!-- Log Type -->
          <div class="flex justify-between">
            <span class="text-text-secondary text-sm">{{ $t('logs.typeLabel') }}</span>
            <span :class="['px-2 py-1 rounded text-xs font-medium', getLogTypeTagClass(selectedLog.log_type)]">
              {{ getLogTypeName(selectedLog.log_type) }}
            </span>
          </div>

          <!-- Dynamic fields from template or legacy fallback -->
          <template v-if="(() => { const tpl = templates.find(t => t.id === selectedLog.template_id); return tpl ? parseFields(tpl.fields) : null; })()">
            <template v-for="field in (() => { const tpl = templates.find(t => t.id === selectedLog.template_id); return tpl ? parseFields(tpl.fields) : []; })()" :key="field.name">
              <template v-if="field.type !== 'participants' && field.type !== 'recipients'">
                <!-- text / number / date / time -->
                <div v-if="['text','number','date','time','textarea'].includes(field.type)" class="flex justify-between">
                  <span class="text-text-secondary text-sm">{{ field.label }}</span>
                  <span class="text-text-primary text-sm max-w-[60%] text-right">{{ selectedLog.content?.[field.name] || '-' }}</span>
                </div>
                <!-- time_range -->
                <div v-else-if="field.type === 'time_range'" class="flex justify-between">
                  <span class="text-text-secondary text-sm">{{ field.label }}</span>
                  <span class="text-text-primary text-sm">
                    {{ selectedLog.content?.[field.name + '_start'] || '-' }} ~ {{ selectedLog.content?.[field.name + '_end'] || '-' }}
                  </span>
                </div>
                <!-- location -->
                <div v-else-if="field.type === 'location'" class="flex justify-between">
                  <span class="text-text-secondary text-sm">{{ field.label }}</span>
                  <span class="text-text-primary text-sm max-w-[60%] text-right">{{ selectedLog.content?.[field.name] || '-' }}</span>
                </div>
                <!-- select / radio -->
                <div v-else-if="['select','radio'].includes(field.type)" class="flex justify-between">
                  <span class="text-text-secondary text-sm">{{ field.label }}</span>
                  <span class="text-text-primary text-sm">{{ selectedLog.content?.[field.name] || '-' }}</span>
                </div>
                <!-- checkbox -->
                <div v-else-if="field.type === 'checkbox'" class="flex justify-between">
                  <span class="text-text-secondary text-sm">{{ field.label }}</span>
                  <span class="text-text-primary text-sm">{{ (selectedLog.content?.[field.name] || []).join('、') || '-' }}</span>
                </div>
                <!-- rating -->
                <div v-else-if="field.type === 'rating'" class="flex justify-between">
                  <span class="text-text-secondary text-sm">{{ field.label }}</span>
                  <span class="text-text-primary text-sm">{{ selectedLog.content?.[field.name] || 0 }} / {{ field.maxRating || 5 }}</span>
                </div>
                <!-- image -->
                <div v-else-if="field.type === 'image'" class="space-y-1">
                  <span class="text-text-secondary text-sm block">{{ field.label }}</span>
                  <div class="grid grid-cols-4 gap-2" v-if="selectedLog.content?.[field.name]?.length">
                    <img v-for="(img, idx) in selectedLog.content[field.name]" :key="idx" :src="img" class="w-full h-16 object-cover rounded" />
                  </div>
                  <span v-else class="text-text-secondary text-sm">-</span>
                </div>
              </template>
              <!-- participants -->
              <div v-else-if="field.type === 'participants'" class="flex justify-between">
                <span class="text-text-secondary text-sm">{{ field.label }}</span>
                <span class="text-text-primary text-sm">{{ selectedLog.participants?.length || 0 }} 人</span>
              </div>
              <!-- recipients -->
              <div v-else-if="field.type === 'recipients'" class="flex justify-between">
                <span class="text-text-secondary text-sm">{{ field.label }}</span>
                <span class="text-text-primary text-sm">{{ selectedLog.recipients?.length || 0 }} 人</span>
              </div>
            </template>
          </template>
          <!-- Legacy fallback: no template -->
          <template v-else>
            <div v-if="selectedLog.title" class="flex justify-between">
              <span class="text-text-secondary text-sm">{{ $t('logs.titleLabel') }}</span>
              <span class="text-text-primary text-sm">{{ selectedLog.title }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-text-secondary text-sm">{{ $t('logs.dateLabel') }}</span>
              <span class="text-text-primary text-sm">{{ formatDate(selectedLog.submit_date || selectedLog.date) }}</span>
            </div>
            <div v-if="selectedLog.single_line" class="flex justify-between">
              <span class="text-text-secondary text-sm">{{ $t('logs.singleLineText') }}</span>
              <span class="text-text-primary text-sm">{{ selectedLog.single_line }}</span>
            </div>
            <div v-if="selectedLog.multi_line">
              <span class="text-text-secondary text-sm block">{{ $t('logs.multiLineText') }}</span>
              <p class="text-text-primary whitespace-pre-wrap">{{ selectedLog.multi_line }}</p>
            </div>
            <div v-if="selectedLog.location" class="flex justify-between">
              <span class="text-text-secondary text-sm">{{ $t('logs.locationLabel') }}</span>
              <span class="text-text-primary text-sm">{{ selectedLog.location }}</span>
            </div>
            <div v-if="selectedLog.images?.length" class="space-y-1">
              <span class="text-text-secondary text-sm block">{{ $t('logs.imagesLabel') }}</span>
              <div class="grid grid-cols-3 gap-2">
                <img v-for="(img, idx) in selectedLog.images" :key="idx" :src="img.url || img" class="w-full h-20 object-cover rounded" />
              </div>
            </div>
          </template>

          <!-- Date footer -->
          <div class="flex justify-between pt-2 border-t">
            <span class="text-text-secondary text-xs">{{ $t('logs.createdAt') || '创建时间' }}</span>
            <span class="text-text-secondary text-xs">{{ formatDate(selectedLog.created_at) }}</span>
          </div>

          <!-- Comments section -->
          <div class="pt-3 border-t space-y-3" v-if="selectedLog">
            <h4 class="text-sm font-medium text-text-primary flex items-center gap-1">
              <span class="material-symbols-outlined text-base">chat_bubble_outline</span>
              {{ $t('logs.comments') || '评论' }} ({{ getLogInteraction(selectedLog.id).comments.length }})
            </h4>
            <!-- Comment list -->
            <div class="space-y-2 max-h-40 overflow-y-auto">
              <div
                v-for="comment in getLogInteraction(selectedLog.id).comments"
                :key="comment.id"
                class="bg-gray-50 rounded-lg p-2 text-sm"
              >
                <div class="flex justify-between items-start">
                  <span class="font-medium text-xs text-primary">{{ comment.name || '未知' }}</span>
                  <span class="text-xs text-gray-400">{{ formatDate(comment.created_at) }}</span>
                </div>
                <p class="text-text-primary mt-1 whitespace-pre-wrap">{{ comment.content }}</p>
              </div>
              <div v-if="getLogInteraction(selectedLog.id).comments.length === 0" class="text-center text-xs text-gray-400 py-2">
                {{ $t('logs.noComments') || '暂无评论' }}
              </div>
            </div>
            <!-- Comment input -->
            <div class="flex gap-2">
              <input
                type="text"
                v-model="commentInputs[selectedLog.id]"
                @keyup.enter="submitComment(selectedLog.id)"
                :placeholder="$t('logs.enterComment') || '输入评论...'"
                class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
              />
              <button
                @click="submitComment(selectedLog.id)"
                class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary/90 transition-colors"
              >
                {{ $t('logs.send') || '发送' }}
              </button>
            </div>
          </div>
        </div>
        <div class="sticky bottom-0 bg-white border-t border-gray-100 px-4 py-3 flex gap-2">
          <button
            v-if="canEditDelete(selectedLog.id)"
            @click="openEditDialog(selectedLog); showDetailModal = false"
            class="flex-1 py-2 bg-primary text-white rounded-lg text-sm"
          >
            {{ $t('common.edit') }}
          </button>
          <button
            v-if="canEditDelete(selectedLog.id)"
            @click="deleteLog(selectedLog.id)"
            class="flex-1 py-2 bg-red-500 text-white rounded-lg text-sm"
          >
            {{ $t('common.delete') }}
          </button>
          <button
            @click="showDetailModal = false"
            class="flex-1 py-2 bg-gray-100 text-text-primary rounded-lg text-sm"
          >
            {{ $t('common.close') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <div v-if="showDialog" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4" @click.self="showDialog = false">
      <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl max-h-[85vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gradient-to-r from-primary/5 to-transparent">
          <h3 class="font-semibold text-lg text-gray-800">
            {{ dialogMode === 'create' ? $t('logs.writeLogTitle') : $t('logs.editLogTitle') }}
          </h3>
          <button @click="showDialog = false" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors">
            <span class="material-symbols-outlined text-xl">close</span>
          </button>
        </div>

        <!-- Template Selector for Create -->
        <div v-if="dialogMode === 'create'" class="px-6 pt-4">
          <label class="text-xs text-text-secondary block mb-1.5">{{ $t('logs.templateLabel') || '选择模板' }}</label>
          <select
            v-model="activeTemplateId"
            @change="onTemplateChangeForCreate"
            class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
          >
            <option v-for="tpl in filteredTemplates" :key="tpl.id" :value="tpl.id">
              {{ tpl.name }}
            </option>
          </select>
        </div>

        <div class="p-6 space-y-5 overflow-y-auto flex-1">
          <!-- Submit Date (always show) -->
          <div>
            <label class="text-sm text-text-secondary block mb-1">{{ $t('logs.dateLabel') }}</label>
            <input
              v-model="formData.submit_date"
              type="date"
              class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
            />
          </div>

          <!-- Dynamic Fields from Template -->
          <template v-if="selectedTemplateForCreate || dialogMode === 'edit'">
            <template v-for="field in (selectedTemplateForCreate ? parseFields(selectedTemplateForCreate.fields) : [])" :key="field.name">
              <!-- text -->
              <div v-if="field.type === 'text'">
                <label class="text-sm text-text-secondary block mb-1">
                  {{ field.label }}<span v-if="field.required" class="text-red-500"> *</span>
                </label>
                <input
                  v-model="formData.content[field.name]"
                  type="text"
                  :placeholder="$t('logs.enterText')"
                  class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                />
              </div>
              <!-- number -->
              <div v-else-if="field.type === 'number'">
                <label class="text-sm text-text-secondary block mb-1">
                  {{ field.label }}<span v-if="field.required" class="text-red-500"> *</span>
                </label>
                <input
                  v-model.number="formData.content[field.name]"
                  type="number"
                  class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                />
              </div>
              <!-- date -->
              <div v-else-if="field.type === 'date'">
                <label class="text-sm text-text-secondary block mb-1">{{ field.label }}</label>
                <input
                  v-model="formData.content[field.name]"
                  type="date"
                  class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                />
              </div>
              <!-- time -->
              <div v-else-if="field.type === 'time'">
                <label class="text-sm text-text-secondary block mb-1">{{ field.label }}</label>
                <input
                  v-model="formData.content[field.name]"
                  type="time"
                  class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                />
              </div>
              <!-- time_range -->
              <div v-else-if="field.type === 'time_range'">
                <label class="text-sm text-text-secondary block mb-1">{{ field.label }}</label>
                <div class="flex gap-2 items-center">
                  <input v-model="formData.content[field.name + '_start']" type="time" class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm" />
                  <span class="text-gray-400">~</span>
                  <input v-model="formData.content[field.name + '_end']" type="time" class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm" />
                </div>
              </div>
              <!-- textarea -->
              <div v-else-if="field.type === 'textarea'">
                <label class="text-sm text-text-secondary block mb-1">
                  {{ field.label }}<span v-if="field.required" class="text-red-500"> *</span>
                </label>
                <textarea
                  v-model="formData.content[field.name]"
                  :placeholder="$t('logs.enterContent')"
                  rows="4"
                  class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                ></textarea>
              </div>
              <!-- select -->
              <div v-else-if="field.type === 'select'">
                <label class="text-sm text-text-secondary block mb-1">{{ field.label }}</label>
                <select
                  v-model="formData.content[field.name]"
                  class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white"
                >
                  <option value="">-- {{ $t('logs.selectPlaceholder') }} --</option>
                  <option v-for="opt in (field.options || [])" :key="opt" :value="opt">{{ opt }}</option>
                </select>
              </div>
              <!-- radio -->
              <div v-else-if="field.type === 'radio'">
                <label class="text-sm text-text-secondary block mb-1">{{ field.label }}</label>
                <div class="flex gap-4 flex-wrap">
                  <label v-for="opt in (field.options || [])" :key="opt" class="flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" v-model="formData.content[field.name]" :value="opt" class="w-4 h-4 text-primary" />
                    <span class="text-sm">{{ opt }}</span>
                  </label>
                </div>
              </div>
              <!-- checkbox -->
              <div v-else-if="field.type === 'checkbox'">
                <label class="text-sm text-text-secondary block mb-1">{{ field.label }}</label>
                <div class="flex gap-4 flex-wrap">
                  <label v-for="opt in (field.options || [])" :key="opt" class="flex items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" :value="opt" v-model="formData.content[field.name]" class="w-4 h-4 text-primary rounded" />
                    <span class="text-sm">{{ opt }}</span>
                  </label>
                </div>
              </div>
              <!-- rating -->
              <div v-else-if="field.type === 'rating'">
                <label class="text-sm text-text-secondary block mb-1">{{ field.label }}</label>
                <div class="flex gap-1">
                  <button
                    v-for="n in (field.maxRating || 5)"
                    :key="n"
                    @click="formData.content[field.name] = n"
                    class="text-2xl text-gray-300 hover:text-yellow-400 transition-colors"
                    :class="formData.content[field.name] >= n ? '!text-yellow-400' : ''"
                  >★</button>
                  <span class="text-sm text-text-secondary ml-2">{{ formData.content[field.name] || 0 }}/{{ field.maxRating || 5 }}</span>
                </div>
              </div>
              <!-- location -->
              <div v-else-if="field.type === 'location'">
                <label class="text-sm text-text-secondary block mb-1">{{ field.label }}</label>
                <button
                  @click="getLocationForField(field.name)"
                  class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-left flex justify-between items-center"
                >
                  <span>{{ formData.content[field.name] || $t('logs.getLocation') }}</span>
                  <span class="material-symbols-outlined text-gray-400">my_location</span>
                </button>
              </div>
              <!-- image -->
              <div v-else-if="field.type === 'image'">
                <label class="text-sm text-text-secondary block mb-1">{{ field.label }}</label>
                <div class="grid grid-cols-4 gap-2">
                  <div
                    v-for="(img, idx) in (formData.content[field.name] || [])"
                    :key="idx"
                    class="relative aspect-square"
                  >
                    <img :src="img" class="w-full h-full object-cover rounded" />
                    <button
                      @click="formData.content[field.name].splice(idx, 1)"
                      class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs"
                    >×</button>
                  </div>
                  <label class="aspect-square border-2 border-dashed border-gray-200 rounded-lg flex items-center justify-center cursor-pointer hover:border-primary">
                    <span class="material-symbols-outlined text-gray-400">add_photo_alternate</span>
                    <input type="file" accept="image/*" multiple @change="e => handleImageUploadForField(e, field.name)" class="hidden" />
                  </label>
                </div>
              </div>
              <!-- participants -->
              <div v-else-if="field.type === 'participants'">
                <label class="text-sm text-text-secondary block mb-1">{{ field.label }}</label>
                <button
                  @click="openParticipantPicker"
                  class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-left flex justify-between items-center"
                >
                  <span>{{ formData.participants?.length ? $t('logs.selectedCount', { count: formData.participants.length }) : $t('logs.selectParticipants') }}</span>
                  <span class="material-symbols-outlined text-gray-400">chevron_right</span>
                </button>
              </div>
              <!-- recipients -->
              <div v-else-if="field.type === 'recipients'">
                <label class="text-sm text-text-secondary block mb-1">{{ field.label }}</label>
                <button
                  @click="openRecipientPicker"
                  class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-left flex justify-between items-center"
                >
                  <span>{{ formData.recipients?.length ? $t('logs.selectedCount', { count: formData.recipients.length }) : $t('logs.selectRecipients') }}</span>
                  <span class="material-symbols-outlined text-gray-400">chevron_right</span>
                </button>
              </div>
              <!-- complainants -->
              <div v-else-if="field.type === 'complainants'">
                <label class="text-sm text-text-secondary block mb-1">{{ field.label }}</label>
                <button
                  @click="openComplainantPicker"
                  class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-left flex justify-between items-center"
                >
                  <span>{{ formData.complainants?.length ? $t('logs.selectedCount', { count: formData.complainants.length }) : $t('logs.selectComplainants') }}</span>
                  <span class="material-symbols-outlined text-gray-400">chevron_right</span>
                </button>
              </div>
            </template>
          </template>

          <!-- Draft toggle -->
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="isDraft" class="w-4 h-4 text-primary rounded" />
            <span class="text-sm text-gray-600">{{ $t('logs.saveAsDraft') }}</span>
          </label>
        </div>

        <div class="px-6 py-4 bg-gray-50/80 border-t border-gray-100">
          <button
            @click="saveLog"
            :disabled="saving"
            class="w-full py-3 bg-primary text-white rounded-xl font-medium disabled:opacity-50 hover:bg-primary/90 active:scale-[0.98] transition-all shadow-lg shadow-primary/25"
          >
            {{ saving ? $t('logs.saving') : $t('logs.submitBtn') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Recipient Picker (收件人) -->
    <div v-if="showRecipientModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4" @click.self="showRecipientModal = false">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl max-h-[80vh] flex flex-col overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center shrink-0">
          <h3 class="font-semibold text-gray-800">{{ $t('logs.selectRecipientTitle') }}</h3>
          <button @click="showRecipientModal = false" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors">
            <span class="material-symbols-outlined text-xl">close</span>
          </button>
        </div>
        <!-- Search -->
        <div class="px-5 py-3 border-b border-gray-50 shrink-0">
          <input
            v-model="recipientSearch"
            type="text"
            :placeholder="$t('common.search') + ' 姓名/部门…'"
            class="w-full px-3 py-2 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
          />
        </div>
        <div class="flex-1 overflow-y-auto">
          <div class="p-3 space-y-1">
            <div
              v-for="user in filteredRecipients"
              :key="user.id"
              @click="() => { const idx = selectedRecipients.indexOf(user.id); if(idx > -1) selectedRecipients.splice(idx, 1); else selectedRecipients.push(user.id) }"
              class="flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-colors"
              :class="selectedRecipients.includes(user.id) ? 'bg-primary/5' : 'hover:bg-gray-50'"
            >
              <div :class="[getAvatarColor(user.name), 'w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-medium shrink-0']">
                {{ getAvatarInitial(user.name) }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-gray-800 truncate">{{ user.name }}</div>
                <div class="text-xs text-gray-400 truncate">{{ user.department || '未分配部门' }}</div>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <span v-if="user.role === 'admin'" class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full">管理员</span>
                <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
                  :class="selectedRecipients.includes(user.id) ? 'border-primary bg-primary' : 'border-gray-300'"
                >
                  <span v-if="selectedRecipients.includes(user.id)" class="material-symbols-outlined text-white text-xs">check</span>
                </div>
              </div>
            </div>
            <div v-if="filteredRecipients.length === 0" class="text-center py-8 text-gray-400 text-sm">
              未找到匹配的用户
            </div>
          </div>
        </div>
        <div class="px-5 py-4 border-t border-gray-100 shrink-0 bg-gray-50/50">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm text-gray-500">已选择 {{ selectedRecipients.length }} 人</span>
          </div>
          <button
            @click="saveRecipients"
            class="w-full py-3 bg-primary text-white rounded-xl font-medium hover:bg-primary/90 active:scale-[0.98] transition-all shadow-lg shadow-primary/25"
          >
            {{ $t('common.confirm') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Complainant Picker (被投诉人) -->
    <div v-if="showComplainantModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4" @click.self="showComplainantModal = false">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl max-h-[80vh] flex flex-col overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center shrink-0">
          <h3 class="font-semibold text-gray-800">{{ $t('logs.selectComplainants') }}</h3>
          <button @click="showComplainantModal = false" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors">
            <span class="material-symbols-outlined text-xl">close</span>
          </button>
        </div>
        <!-- Search -->
        <div class="px-5 py-3 border-b border-gray-50 shrink-0">
          <input
            v-model="complainantSearch"
            type="text"
            :placeholder="$t('common.search') + ' 姓名/部门…'"
            class="w-full px-3 py-2 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
          />
        </div>
        <div class="flex-1 overflow-y-auto">
          <div class="p-3 space-y-1">
            <div
              v-for="user in filteredComplainants"
              :key="user.id"
              @click="() => { const idx = selectedComplainants.indexOf(user.id); if(idx > -1) selectedComplainants.splice(idx, 1); else selectedComplainants.push(user.id) }"
              class="flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-colors"
              :class="selectedComplainants.includes(user.id) ? 'bg-red-50' : 'hover:bg-gray-50'"
            >
              <div :class="[getAvatarColor(user.name), 'w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-medium shrink-0']">
                {{ getAvatarInitial(user.name) }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-gray-800 truncate">{{ user.name }}</div>
                <div class="text-xs text-gray-400 truncate">{{ user.department || '未分配部门' }}</div>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <span v-if="user.role === 'admin'" class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full">管理员</span>
                <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
                  :class="selectedComplainants.includes(user.id) ? 'border-red-500 bg-red-500' : 'border-gray-300'"
                >
                  <span v-if="selectedComplainants.includes(user.id)" class="material-symbols-outlined text-white text-xs">check</span>
                </div>
              </div>
            </div>
            <div v-if="filteredComplainants.length === 0" class="text-center py-8 text-gray-400 text-sm">
              未找到匹配的用户
            </div>
          </div>
        </div>
        <div class="px-5 py-4 border-t border-gray-100 shrink-0 bg-gray-50/50">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm text-gray-500">已选择 {{ selectedComplainants.length }} 人</span>
          </div>
          <button
            @click="saveComplainants"
            class="w-full py-3 bg-red-500 text-white rounded-xl font-medium hover:bg-red-500/90 active:scale-[0.98] transition-all shadow-lg shadow-red-500/25"
          >
            {{ $t('common.confirm') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Participant Picker -->
    <div v-if="showParticipantModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4" @click.self="showParticipantModal = false">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl max-h-[80vh] flex flex-col overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center shrink-0">
          <h3 class="font-semibold text-gray-800">{{ $t('logs.selectParticipants') }}</h3>
          <button @click="showParticipantModal = false" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors">
            <span class="material-symbols-outlined text-xl">close</span>
          </button>
        </div>
        <!-- Search -->
        <div class="px-5 py-3 border-b border-gray-50 shrink-0">
          <input
            v-model="participantSearch"
            type="text"
            :placeholder="$t('common.search') + ' 姓名/部门…'"
            class="w-full px-3 py-2 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
          />
        </div>
        <div class="flex-1 overflow-y-auto">
          <div class="p-3 space-y-1">
            <div
              v-for="user in filteredParticipants"
              :key="user.id"
              @click="() => { const idx = selectedParticipants.indexOf(user.id); if(idx > -1) selectedParticipants.splice(idx, 1); else selectedParticipants.push(user.id) }"
              class="flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-colors"
              :class="selectedParticipants.includes(user.id) ? 'bg-primary/5' : 'hover:bg-gray-50'"
            >
              <div :class="[getAvatarColor(user.name), 'w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-medium shrink-0']">
                {{ getAvatarInitial(user.name) }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-gray-800 truncate">{{ user.name }}</div>
                <div class="text-xs text-gray-400 truncate">{{ user.department || '未分配部门' }}</div>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <span v-if="user.is_self" class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full">本人</span>
                <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
                  :class="selectedParticipants.includes(user.id) ? 'border-primary bg-primary' : 'border-gray-300'"
                >
                  <span v-if="selectedParticipants.includes(user.id)" class="material-symbols-outlined text-white text-xs">check</span>
                </div>
              </div>
            </div>
            <div v-if="filteredParticipants.length === 0" class="text-center py-8 text-gray-400 text-sm">
              未找到匹配的用户
            </div>
          </div>
        </div>
        <div class="px-5 py-4 border-t border-gray-100 shrink-0 bg-gray-50/50">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm text-gray-500">已选择 {{ selectedParticipants.length }} 人</span>
          </div>
          <button
            @click="saveParticipants"
            class="w-full py-3 bg-primary text-white rounded-xl font-medium hover:bg-primary/90 active:scale-[0.98] transition-all shadow-lg shadow-primary/25"
          >
            {{ $t('common.confirm') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Template Dialog -->
    <div v-if="showTemplateDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showTemplateDialog = false">
      <div class="bg-white rounded-xl w-full max-w-2xl max-h-[85vh] overflow-y-auto shadow-xl">
        <div class="sticky top-0 bg-white border-b border-gray-100 px-4 py-3 flex justify-between items-center">
          <h3 class="font-medium">{{ editingTemplate ? $t('logs.editTemplateTitle') : $t('logs.newTemplateTitle') }}</h3>
          <button @click="showTemplateDialog = false" class="text-gray-400 hover:text-gray-600">
            <span class="material-symbols-outlined text-lg">close</span>
          </button>
        </div>

        <div class="p-4 space-y-4">
          <div>
            <label class="text-sm text-text-secondary block mb-1">{{ $t('logs.templateNameLabel') }} <span class="text-red-500">*</span></label>
            <input
              v-model="templateForm.name"
              type="text"
              :placeholder="$t('logs.enterTemplateName')"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary"
            />
          </div>

          <div>
            <label class="text-sm text-text-secondary block mb-1">{{ $t('logs.templateDescription') }}</label>
            <textarea
              v-model="templateForm.description"
              :placeholder="$t('logs.enterDescription')"
              rows="2"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm resize-none focus:outline-none focus:border-primary"
            ></textarea>
          </div>

          <div>
            <label class="text-sm text-text-secondary block mb-2">{{ $t('logs.applicableType') }}</label>
            <div class="flex gap-4">
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
              <button @click="addTemplateField" class="text-primary text-sm hover:underline">{{ $t('logs.addFieldBtn') }}</button>
            </div>
            <div v-for="(field, index) in templateForm.fields" :key="index" class="bg-gray-50 rounded-lg p-3 mb-2 border border-gray-100">
              <div class="flex items-center gap-2 mb-2">
                <input
                  v-model="field.label"
                  type="text"
                  :placeholder="$t('logs.fieldLabelPlaceholder')"
                  class="flex-1 px-2 py-1.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-primary"
                />
                <select
                  v-model="field.type"
                  class="px-2 py-1.5 border border-gray-200 rounded text-sm focus:outline-none focus:border-primary bg-white"
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
                  <option value="recipients">{{ $t('logs.typeRecipients') }}</option>
                  <option value="complainants">{{ $t('logs.typeComplainants') }}</option>
                </select>
                <button @click="moveFieldUp(index)" :disabled="index === 0" class="text-gray-400 hover:text-primary disabled:opacity-30 text-sm" title="上移">↑</button>
                <button @click="moveFieldDown(index)" :disabled="index === templateForm.fields.length - 1" class="text-gray-400 hover:text-primary disabled:opacity-30 text-sm" title="下移">↓</button>
                <button @click="removeTemplateField(index)" class="text-red-500 text-sm hover:text-red-700 whitespace-nowrap">{{ $t('common.delete') }}</button>
              </div>
              <!-- Type-specific config -->
              <div class="space-y-2 text-xs">
                <!-- text/textarea: maxLength -->
                <div v-if="['text', 'textarea'].includes(field.type)" class="flex items-center gap-2">
                  <label class="text-text-secondary">{{ $t('logs.maxLength') }}:</label>
                  <input v-model.number="field.maxLength" type="number" class="w-16 px-2 py-1 border border-gray-200 rounded focus:outline-none focus:border-primary" />
                </div>
                <!-- number: min/max -->
                <div v-if="field.type === 'number'" class="flex items-center gap-2">
                  <label class="text-text-secondary">{{ $t('logs.minValue') }}:</label>
                  <input v-model.number="field.min" type="number" class="w-16 px-2 py-1 border border-gray-200 rounded focus:outline-none focus:border-primary" />
                  <label class="text-text-secondary">{{ $t('logs.maxValue') }}:</label>
                  <input v-model.number="field.max" type="number" class="w-16 px-2 py-1 border border-gray-200 rounded focus:outline-none focus:border-primary" />
                </div>
                <!-- select/radio/checkbox: options -->
                <div v-if="['select', 'radio', 'checkbox'].includes(field.type)">
                  <label class="block mb-1 text-text-secondary">{{ $t('logs.options') }}:</label>
                  <div class="space-y-1">
                    <div v-for="(opt, i) in (field.options || [])" :key="i" class="flex items-center gap-1">
                      <input v-model="field.options[i]" type="text" class="flex-1 px-2 py-1 border border-gray-200 rounded text-xs focus:outline-none focus:border-primary" />
                      <button @click="field.options.splice(i, 1)" class="text-red-500 text-xs hover:text-red-700">×</button>
                    </div>
                  </div>
                  <button @click="(field.options = field.options || []).push('')" class="text-primary text-xs mt-1 hover:underline">+ {{ $t('logs.addOption') }}</button>
                </div>
                <!-- image: maxCount -->
                <div v-if="field.type === 'image'" class="flex items-center gap-2">
                  <label class="text-text-secondary">{{ $t('logs.maxImages') }}:</label>
                  <input v-model.number="field.maxCount" type="number" class="w-16 px-2 py-1 border border-gray-200 rounded focus:outline-none focus:border-primary" />
                </div>
                <!-- rating: maxRating -->
                <div v-if="field.type === 'rating'" class="flex items-center gap-2">
                  <label class="text-text-secondary">{{ $t('logs.maxRating') }}:</label>
                  <input v-model.number="field.maxRating" type="number" min="1" max="10" class="w-16 px-2 py-1 border border-gray-200 rounded focus:outline-none focus:border-primary" />
                </div>
                <!-- required checkbox -->
                <label class="flex items-center gap-1.5">
                  <input type="checkbox" v-model="field.required" class="w-3 h-3 rounded text-primary" />
                  <span class="text-text-secondary">{{ $t('logs.requiredField') }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="sticky bottom-0 bg-white border-t border-gray-100 px-4 py-3">
          <button
            @click="saveTemplate"
            :disabled="savingTemplate"
            class="w-full py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-primary/90 disabled:opacity-50 transition-colors"
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
