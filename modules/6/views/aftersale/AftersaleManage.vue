<script setup>
import { ref, onMounted, watch, onUnmounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'

const { t } = useI18n()

// ─── State ──────────────────────────────────────────────────────────────────────
const records = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = 50
const stats = ref({ total: 0, processing: 0, resolved: 0, rejected: 0 })
const systemUsers = ref([])

// Filters
const filterStatus = ref('')
const filterAssignedTo = ref('')
const filterDateStart = ref('')
const filterDateEnd = ref('')
const filterKeyword = ref('')
const filterType = ref('')
const filterPriority = ref('')

// Drawer
const showDrawer = ref(false)
const selected = ref(null)
const editStatus = ref('')
const editAssignedTo = ref('')
const editNote = ref('')
const saving = ref(false)
const editPriority = ref('')
const channelQrcodes = ref({ telegram: '', whatsapp: '', wecom: '' })
const channelQrPreview = ref('')

// ─── Chat ────────────────────────────────────────────────────────────────────
const chatMessages = ref([])
const chatInput = ref('')
const chatSending = ref(false)
const chatLoading = ref(false)
const chatContainer = ref(null)
let chatPollTimer = null

function scrollChatToBottom() {
  nextTick(() => {
    const el = chatContainer.value
    if (el) el.scrollTop = el.scrollHeight
  })
}

async function fetchChatMessages(aftersaleId) {
  chatLoading.value = true
  try {
    const res = await api.get(`/aftersales/${aftersaleId}/messages`)
    if (res.code === 0) chatMessages.value = res.data
  } catch { /* silent */ }
  finally { chatLoading.value = false }
  scrollChatToBottom()
}

async function pollChatMessages() {
  if (!selected.value) return
  try {
    const res = await api.get(`/aftersales/${selected.value.id}/messages`)
    if (res.code === 0 && res.data.length !== chatMessages.value.length) {
      chatMessages.value = res.data
      scrollChatToBottom()
    }
  } catch { /* silent */ }
}

function startChatPolling() {
  stopChatPolling()
  chatPollTimer = setInterval(pollChatMessages, 5000)
}

function stopChatPolling() {
  if (chatPollTimer) { clearInterval(chatPollTimer); chatPollTimer = null }
}

async function sendChatMessage() {
  const text = chatInput.value.trim()
  if (!text || chatSending.value || !selected.value) return
  chatInput.value = ''
  chatSending.value = true
  try {
    const res = await api.post(`/aftersales/${selected.value.id}/messages`, { content: text })
    if (res.code === 0) {
      await fetchChatMessages(selected.value.id)
    }
  } catch { /* silent */ }
  finally { chatSending.value = false }
}

onUnmounted(stopChatPolling)

// ─── Fetch ────────────────────────────────────────────────────────────────────
async function fetchRecords() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize }
    if (filterStatus.value) params.status = filterStatus.value
    if (filterAssignedTo.value) params.assigned_to = filterAssignedTo.value
    if (filterDateStart.value) params.date_start = filterDateStart.value
    if (filterDateEnd.value) params.date_end = filterDateEnd.value
    if (filterKeyword.value) params.keyword = filterKeyword.value
    if (filterType.value) params.type = filterType.value
    if (filterPriority.value) params.priority = filterPriority.value
    const res = await api.get('/aftersales', { params })
    if (res.code === 0) { records.value = res.data.list; total.value = res.data.total }
  } finally { loading.value = false }
}

async function fetchStats() {
  const res = await api.get('/aftersales/stats')
  if (res.code === 0) stats.value = res.data
}

async function fetchSystemUsers() {
  const res = await api.get('/users')
  if (res.code === 0) systemUsers.value = res.data.list || res.data
}

watch([filterStatus, filterAssignedTo, filterDateStart, filterDateEnd, filterKeyword, filterType, filterPriority], () => {
  currentPage.value = 1; fetchRecords()
})
watch(currentPage, fetchRecords)

onMounted(() => { fetchRecords(); fetchStats(); fetchSystemUsers() })

// ─── Drawer ───────────────────────────────────────────────────────────────────
function openDetail(row) {
  selected.value = row
  editStatus.value = row.status
  editAssignedTo.value = row.assigned_to || ''
  editNote.value = row.handler_note || ''
  editPriority.value = row.priority || 'normal'
  const existing = row.channel_qrcodes || {}
  channelQrcodes.value = {
    telegram: existing.telegram || '',
    whatsapp: existing.whatsapp || '',
    wecom: existing.wecom || '',
  }
  showDrawer.value = true
  chatMessages.value = []
  chatInput.value = ''
  fetchChatMessages(row.id)
  startChatPolling()
}

async function saveChanges() {
  saving.value = true
  try {
    const res = await api.put(`/aftersales/${selected.value.id}`, {
      status: editStatus.value,
      assigned_to: editAssignedTo.value || null,
      handler_note: editNote.value,
      priority: editPriority.value,
      channel_qrcodes: channelQrcodes.value
    })
    if (res.code === 0) {
      showDrawer.value = false
      stopChatPolling()
      await fetchRecords()
      await fetchStats()
    }
  } finally { saving.value = false }
}

async function handleChannelQrUpload(channel, event) {
  const file = event.target.files?.[0]
  if (!file) return
  const fd = new FormData()
  fd.append('file', file)
  try {
    const res = await api.post('/upload', fd)
    if (res.code === 0) channelQrcodes.value[channel] = res.data.url
  } catch (e) {
    console.error('Upload failed:', e)
  }
  event.target.value = ''
}

function removeChannelQr(channel) {
  channelQrcodes.value[channel] = ''
}

// ─── Delete ──────────────────────────────────────────────────────────────────
async function handleDelete(record) {
  if (!confirm(`确认删除工单 ${record.ticket_no || '#' + record.id}？\n此操作不可恢复，关联的聊天记录也会被清空。`)) return
  try {
    const res = await api.delete(`/aftersales/${record.id}`)
    if (res.code === 0) {
      await fetchRecords()
      await fetchStats()
    } else {
      alert('删除失败：' + (res.message || '未知错误'))
    }
  } catch (e) {
    console.error('删除售后记录失败:', e)
    alert('删除失败，请重试')
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatTime(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

const statusConfig = {
  processing: { type: 'warning', label: t('aftersale.processing') },
  resolved:   { type: 'success', label: t('aftersale.resolved') },
  rejected:   { type: 'danger',  label: t('aftersale.rejected') },
}

const typeConfig = {
  repair:   { label: t('aftersale.typeRepair'), color: 'text-blue-600 bg-blue-50' },
  return:   { label: t('aftersale.typeReturn'), color: 'text-red-600 bg-red-50' },
  exchange: { label: t('aftersale.typeExchange'), color: 'text-orange-600 bg-orange-50' },
  consult:  { label: t('aftersale.typeConsult'), color: 'text-gray-600 bg-gray-100' },
}

const priorityConfig = {
  low:    { label: t('aftersale.priorityLow'), color: 'text-gray-500 bg-gray-50' },
  normal: { label: t('aftersale.priorityNormal'), color: 'text-blue-600 bg-blue-50' },
  high:   { label: t('aftersale.priorityHigh'), color: 'text-orange-600 bg-orange-50' },
  urgent: { label: t('aftersale.priorityUrgent'), color: 'text-red-600 bg-red-50' },
}
</script>

<template>
  <div>
    <PageHeader :title="$t('aftersale.title')" :subtitle="$t('aftersale.subtitle')" />

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div v-for="item in [
        { label: $t('aftersale.allRequests'), value: stats.total, icon: 'support_agent', color: 'primary' },
        { label: $t('aftersale.processing'),   value: stats.processing, icon: 'pending', color: 'warning' },
        { label: $t('aftersale.resolved'),   value: stats.resolved,   icon: 'check_circle', color: 'success' },
        { label: $t('aftersale.rejected'),   value: stats.rejected,   icon: 'cancel', color: 'danger' },
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

    <!-- Filters -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-6">
      <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center bg-gray-50 rounded-lg px-3 py-2 border border-transparent focus-within:border-primary focus-within:bg-white transition-all min-w-[180px]">
          <span class="material-symbols-outlined text-text-secondary text-[18px]">search</span>
          <input v-model="filterKeyword" type="text" :placeholder="$t('aftersale.searchPlaceholder')" class="bg-transparent border-none focus:ring-0 focus:outline-none text-sm w-full ml-2" />
        </div>
        <select v-model="filterStatus" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('aftersale.allStatus') }}</option>
          <option value="processing">{{ $t('aftersale.processing') }}</option>
          <option value="resolved">{{ $t('aftersale.resolved') }}</option>
          <option value="rejected">{{ $t('aftersale.rejected') }}</option>
        </select>
        <select v-model="filterType" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('aftersale.allTypes') }}</option>
          <option value="repair">{{ $t('aftersale.typeRepair') }}</option>
          <option value="return">{{ $t('aftersale.typeReturn') }}</option>
          <option value="exchange">{{ $t('aftersale.typeExchange') }}</option>
          <option value="consult">{{ $t('aftersale.typeConsult') }}</option>
        </select>
        <select v-model="filterPriority" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('aftersale.allPriorities') }}</option>
          <option value="low">{{ $t('aftersale.priorityLow') }}</option>
          <option value="normal">{{ $t('aftersale.priorityNormal') }}</option>
          <option value="high">{{ $t('aftersale.priorityHigh') }}</option>
          <option value="urgent">{{ $t('aftersale.priorityUrgent') }}</option>
        </select>
        <select v-model="filterAssignedTo" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('aftersale.allAssignees') }}</option>
          <option v-for="u in systemUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <input v-model="filterDateStart" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <span class="text-text-secondary text-sm">—</span>
        <input v-model="filterDateEnd" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <button @click="filterStatus='';filterAssignedTo='';filterDateStart='';filterDateEnd='';filterKeyword='';filterType='';filterPriority=''" class="text-sm text-text-secondary hover:text-text-primary border border-gray-200 rounded-lg px-3 py-2">
          {{ $t('common.reset') }}
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('aftersale.colTicketNo') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('aftersale.colTime') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('aftersale.colProduct') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('aftersale.colH5User') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('aftersale.colQrCode') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('aftersale.colType') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('aftersale.colPriority') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('aftersale.colIssue') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('aftersale.colStatus') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('aftersale.colAssignee') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('aftersale.colRespondedAt') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('aftersale.colAction') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loading">
              <td colspan="12" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('aftersale.loading') }}</td>
            </tr>
            <tr v-else-if="records.length === 0">
              <td colspan="12" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('aftersale.noRecords') }}</td>
            </tr>
            <tr v-for="r in records" :key="r.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-mono text-xs text-primary whitespace-nowrap">{{ r.ticket_no || '-' }}</td>
              <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">{{ formatTime(r.created_at) }}</td>
              <td class="px-4 py-3">
                <p class="font-medium text-text-primary">{{ r.product_name || r.buyer || '-' }}</p>
                <p v-if="r.product_spec" class="text-xs text-text-secondary">{{ r.product_spec }}</p>
              </td>
              <td class="px-4 py-3">
                <p class="text-text-primary">{{ r.h5_name || '-' }}</p>
                <p class="text-xs text-text-secondary">{{ r.h5_phone || r.contact_phone || '' }}</p>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-primary">{{ r.qr_code || '-' }}</td>
              <td class="px-4 py-3">
                <span v-if="r.type" :class="['px-2 py-0.5 text-xs rounded-full', typeConfig[r.type]?.color || 'text-gray-600 bg-gray-100']">{{ typeConfig[r.type]?.label || r.type }}</span>
                <span v-else class="text-text-secondary text-xs">-</span>
              </td>
              <td class="px-4 py-3">
                <span v-if="r.priority" :class="['px-2 py-0.5 text-xs rounded-full', priorityConfig[r.priority]?.color || 'text-gray-500 bg-gray-50']">{{ priorityConfig[r.priority]?.label || r.priority }}</span>
                <span v-else class="text-text-secondary text-xs">-</span>
              </td>
              <td class="px-4 py-3 text-text-secondary text-xs max-w-[200px]">
                <p class="line-clamp-2">{{ r.issue || '-' }}</p>
              </td>
              <td class="px-4 py-3 text-center">
                <StatusTag
                  :type="statusConfig[r.status]?.type || 'info'"
                  :text="statusConfig[r.status]?.label || r.status"
                />
              </td>
              <td class="px-4 py-3 text-text-secondary text-sm">{{ r.assigned_to_name || $t('aftersale.unassigned') }}</td>
              <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">{{ formatTime(r.responded_at) }}</td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button @click="openDetail(r)" class="text-primary hover:text-primary-hover text-xs font-medium">{{ $t('aftersale.handle') }}</button>
                  <button @click="handleDelete(r)" class="text-danger hover:text-danger/80 text-xs font-medium">{{ $t('common.delete') }}</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100">
        <Pagination :total="total" :page="currentPage" :pageSize="pageSize" @update:page="currentPage = $event" />
      </div>
    </div>

    <!-- Detail Drawer -->
    <Teleport to="body">
      <div v-if="showDrawer && selected" class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/30" @click="showDrawer = false; stopChatPolling()"></div>
        <div class="relative w-full max-w-lg bg-white shadow-xl flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b shrink-0">
            <h3 class="text-lg font-bold text-text-primary">{{ $t('aftersale.detailTitle') }}</h3>
            <button @click="showDrawer = false; stopChatPolling()" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Content -->
          <div class="flex-1 overflow-y-auto p-6 space-y-5">
            <!-- 商品信息 -->
            <div class="bg-gray-50 rounded-lg p-4 space-y-2">
              <p class="text-xs font-medium text-text-secondary uppercase tracking-wider">{{ $t('aftersale.productInfo') }}</p>
              <!-- 工单编号 + 类型 + 优先级 -->
              <div v-if="selected.ticket_no" class="flex items-center gap-2 flex-wrap">
                <span class="font-mono text-xs text-primary">{{ selected.ticket_no }}</span>
                <span v-if="selected.type" :class="['px-2 py-0.5 text-xs rounded-full', typeConfig[selected.type]?.color || '']">{{ typeConfig[selected.type]?.label || selected.type }}</span>
                <span v-if="selected.priority" :class="['px-2 py-0.5 text-xs rounded-full', priorityConfig[selected.priority]?.color || '']">{{ priorityConfig[selected.priority]?.label || selected.priority }}</span>
              </div>
              <div class="flex items-center gap-3">
                <img v-if="selected.image_main" :src="selected.image_main" class="w-12 h-12 rounded object-cover border border-gray-100" />
                <div>
                  <p class="font-medium text-text-primary">{{ selected.product_name || $t('aftersale.unboundProduct') }}</p>
                  <p v-if="selected.product_spec" class="text-xs text-text-secondary">{{ selected.product_spec }}</p>
                  <p v-if="selected.qr_code" class="text-xs font-mono text-primary">{{ selected.qr_code }}</p>
                </div>
              </div>
            </div>

            <!-- H5用户 -->
            <div class="bg-gray-50 rounded-lg p-4 space-y-2">
              <p class="text-xs font-medium text-text-secondary uppercase tracking-wider">{{ $t('aftersale.applicant') }}</p>
              <p class="font-medium text-text-primary">{{ selected.h5_name || selected.buyer || $t('aftersale.anonymous') }}</p>
              <p class="text-sm text-text-secondary">{{ selected.h5_phone || selected.contact_phone || $t('aftersale.noPhone') }}</p>
            </div>

            <!-- 问题描述 -->
            <div>
              <p class="text-sm font-medium text-text-primary mb-1">{{ $t('aftersale.issueDesc') }}</p>
              <p class="text-sm text-text-secondary bg-gray-50 rounded-lg p-3">{{ selected.issue || $t('aftersale.noDescription') }}</p>
            </div>

            <!-- 时间线 -->
            <div class="bg-gray-50 rounded-lg p-4 space-y-2">
              <p class="text-xs font-medium text-text-secondary uppercase tracking-wider">{{ $t('aftersale.colTime') }}</p>
              <div class="flex items-center gap-4 text-xs">
                <div class="flex flex-col items-center">
                  <span class="text-text-secondary">{{ $t('aftersale.colTime') }}</span>
                  <span class="font-medium text-text-primary">{{ formatTime(selected.created_at) }}</span>
                </div>
                <span class="text-gray-300">→</span>
                <div class="flex flex-col items-center">
                  <span class="text-text-secondary">{{ $t('aftersale.respondedAt') }}</span>
                  <span class="font-medium" :class="selected.responded_at ? 'text-blue-600' : 'text-gray-400'">{{ selected.responded_at ? formatTime(selected.responded_at) : '-' }}</span>
                </div>
                <span class="text-gray-300">→</span>
                <div class="flex flex-col items-center">
                  <span class="text-text-secondary">{{ $t('aftersale.resolvedAt') }}</span>
                  <span class="font-medium" :class="selected.resolved_at ? 'text-green-600' : 'text-gray-400'">{{ selected.resolved_at ? formatTime(selected.resolved_at) : '-' }}</span>
                </div>
              </div>
            </div>

            <hr class="border-gray-100" />

            <!-- 聊天记录 -->
            <div>
              <p class="text-sm font-medium text-text-primary mb-2 flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px] text-primary">forum</span>
                {{ $t('serviceChat.title') }}
              </p>
              <div ref="chatContainer" class="bg-gray-50 rounded-lg p-3 h-[200px] overflow-y-auto space-y-2 custom-scrollbar">
                <div v-if="chatLoading" class="flex items-center justify-center h-full">
                  <div class="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                </div>
                <div v-else-if="chatMessages.length === 0" class="flex items-center justify-center h-full text-xs text-text-secondary">
                  {{ $t('serviceChat.noMessages') }}
                </div>
                <div v-for="msg in chatMessages" :key="msg.id" :class="['flex', msg.sender_type === 'staff' ? 'justify-end' : 'justify-start']">
                  <div class="max-w-[80%]">
                    <p class="text-[10px] text-text-secondary mb-0.5" :class="msg.sender_type === 'staff' ? 'text-right' : ''">
                      {{ msg.sender_name }} · {{ msg.created_at?.slice(11, 16) }}
                    </p>
                    <div :class="[
                      'px-3 py-1.5 text-xs leading-relaxed whitespace-pre-wrap break-words rounded-xl',
                      msg.sender_type === 'staff'
                        ? 'bg-primary text-white rounded-br-sm'
                        : 'bg-white text-text-primary border border-gray-200 rounded-bl-sm'
                    ]">
                      {{ msg.content }}
                    </div>
                  </div>
                </div>
              </div>
              <div class="flex items-center gap-2 mt-2">
                <input
                  v-model="chatInput"
                  @keydown.enter="sendChatMessage"
                  type="text"
                  :placeholder="$t('serviceChat.inputPlaceholder')"
                  class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                  :disabled="chatSending"
                />
                <button
                  @click="sendChatMessage"
                  :disabled="!chatInput.trim() || chatSending"
                  :class="[
                    'px-3 py-1.5 rounded-lg text-sm transition-colors shrink-0',
                    chatInput.trim() && !chatSending
                      ? 'bg-primary hover:bg-primary-hover text-white'
                      : 'bg-gray-100 text-text-secondary cursor-not-allowed'
                  ]"
                >
                  {{ $t('serviceChat.send') }}
                </button>
              </div>
            </div>

            <hr class="border-gray-100" />

            <!-- 处理操作 -->
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('aftersale.handleSection') }}</label>
                <select v-model="editStatus" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                  <option value="processing">{{ $t('aftersale.processing') }}</option>
                  <option value="resolved">{{ $t('aftersale.resolved') }}</option>
                  <option value="rejected">{{ $t('aftersale.rejected') }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('aftersale.assignLabel') }}</label>
                <select v-model="editAssignedTo" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                  <option value="">{{ $t('aftersale.noAssign') }}</option>
                  <option v-for="u in systemUsers" :key="u.id" :value="u.id">{{ u.name }} ({{ u.role }})</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('aftersale.priorityLabel') }}</label>
                <select v-model="editPriority" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                  <option value="low">{{ $t('aftersale.priorityLow') }}</option>
                  <option value="normal">{{ $t('aftersale.priorityNormal') }}</option>
                  <option value="high">{{ $t('aftersale.priorityHigh') }}</option>
                  <option value="urgent">{{ $t('aftersale.priorityUrgent') }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('aftersale.handlerNote') }}</label>
                <textarea v-model="editNote" rows="3" :placeholder="$t('aftersale.handlerNotePlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none"></textarea>
              </div>

              <!-- 渠道二维码 -->
              <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('qrcode.channelQrcodes') || '渠道二维码' }} <span class="text-text-secondary font-normal text-xs">({{ $t('common.optional') }})</span></label>
                <div class="grid grid-cols-3 gap-3">
                  <div v-for="ch in ['telegram', 'whatsapp', 'wecom']" :key="ch" class="border border-gray-200 rounded-lg p-3 text-center">
                    <p class="text-xs font-medium mb-2 capitalize">{{ ch === 'wecom' ? '企业微信' : ch.charAt(0).toUpperCase() + ch.slice(1) }}</p>
                    <div v-if="channelQrcodes[ch]" class="relative inline-block">
                      <img :src="channelQrcodes[ch]" class="w-20 h-20 object-contain rounded border cursor-pointer" @click="channelQrPreview = channelQrcodes[ch]" />
                      <button type="button" @click="removeChannelQr(ch)" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center leading-none">×</button>
                    </div>
                    <label v-else class="inline-flex flex-col items-center gap-1 cursor-pointer text-primary hover:text-primary-hover py-2">
                      <span class="material-symbols-outlined text-[24px]">add_photo_alternate</span>
                      <span class="text-xs">{{ $t('qrcode.uploadQrcode') || '上传' }}</span>
                      <input type="file" accept="image/*" @change="handleChannelQrUpload(ch, $event)" class="hidden" />
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t shrink-0 flex justify-end gap-3">
            <button @click="showDrawer = false; stopChatPolling()" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-secondary hover:border-gray-300">{{ $t('common.cancel') }}</button>
            <button @click="saveChanges" :disabled="saving" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-hover disabled:opacity-50">
              {{ saving ? $t('aftersale.saving') : $t('common.saveChanges') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- 渠道二维码放大预览 -->
    <Teleport to="body">
      <div v-if="channelQrPreview" class="fixed inset-0 z-[60] bg-black/70 flex items-center justify-center" @click="channelQrPreview = ''">
        <img :src="channelQrPreview" class="max-w-[80vw] max-h-[80vh] rounded-lg shadow-2xl" />
      </div>
    </Teleport>
  </div>
</template>
