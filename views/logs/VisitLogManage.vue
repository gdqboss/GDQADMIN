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
const logs = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = 20

// Filters
const filterDateStart = ref('')
const filterDateEnd = ref('')
const filterCustomerType = ref('')
const filterCustomerId = ref('')
const filterKeyword = ref('')

// Stats
const stats = ref({
  total: 0,
  thisMonth: 0,
  byType: { supplier: 0, dealer: 0, store: 0 },
  byUser: []
})

// Customers
const suppliers = ref([])
const dealers = ref([])
const stores = ref([])

// Detail Modal
const showDetailModal = ref(false)
const selectedLog = ref(null)

// Create/Edit Dialog
const showDialog = ref(false)
const dialogMode = ref('create') // 'create' | 'edit'
const formData = ref({
  visit_date: new Date().toISOString().split('T')[0],
  customer_type: '',
  customer_id: '',
  purpose: '',
  content: '',
  result: '',
  next_plan: '',
  location: '',
  gps_lat: null,
  gps_lng: null,
  photos: []
})
const uploadingPhotos = ref(false)
const saving = ref(false)

// ─── Computed ───────────────────────────────────────────────────────────────────
const filteredCustomers = computed(() => {
  if (filterCustomerType.value === 'supplier') return suppliers.value
  if (filterCustomerType.value === 'dealer') return dealers.value
  if (filterCustomerType.value === 'store') return stores.value
  return []
})

const dialogCustomers = computed(() => {
  if (formData.value.customer_type === 'supplier') return suppliers.value
  if (formData.value.customer_type === 'dealer') return dealers.value
  if (formData.value.customer_type === 'store') return stores.value
  return []
})

// ─── Fetch ──────────────────────────────────────────────────────────────────────
async function fetchLogs() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize }
    if (filterDateStart.value) params.date_start = filterDateStart.value
    if (filterDateEnd.value) params.date_end = filterDateEnd.value
    if (filterCustomerType.value) params.customer_type = filterCustomerType.value
    if (filterCustomerId.value) params.customer_id = filterCustomerId.value
    if (filterKeyword.value) params.keyword = filterKeyword.value
    const res = await api.get('/visit-logs', { params })
    if (res.code === 0) {
      logs.value = res.data.list
      total.value = res.data.total
    }
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  const res = await api.get('/visit-logs/stats')
  if (res.code === 0) stats.value = res.data
}

async function fetchCustomers() {
  const [s, d, st] = await Promise.all([
    api.get('/suppliers'),
    api.get('/dealers'),
    api.get('/stores')
  ])
  if (s.code === 0) suppliers.value = s.data.list || s.data
  if (d.code === 0) dealers.value = d.data.list || d.data
  if (st.code === 0) stores.value = st.data.list || st.data
}

watch([filterDateStart, filterDateEnd, filterCustomerType, filterCustomerId, filterKeyword], () => {
  currentPage.value = 1
  fetchLogs()
})
watch(currentPage, fetchLogs)

// Reset customer filter when type changes
watch(filterCustomerType, () => {
  filterCustomerId.value = ''
})

onMounted(() => {
  fetchLogs()
  fetchStats()
  fetchCustomers()
})

// ─── Detail Modal ───────────────────────────────────────────────────────────────
function openDetail(log) {
  selectedLog.value = log
  showDetailModal.value = true
}

function canEdit(log) {
  return log.created_by === userStore.user?.id
}

async function deleteLog(id) {
  if (!confirm(t('logs.confirmDeleteVisit'))) return
  const res = await api.delete(`/visit-logs/${id}`)
  if (res.code === 0) {
    showDetailModal.value = false
    await fetchLogs()
    await fetchStats()
  }
}

// ─── Create/Edit Dialog ─────────────────────────────────────────────────────────
function openCreateDialog() {
  dialogMode.value = 'create'
  formData.value = {
    visit_date: new Date().toISOString().split('T')[0],
    customer_type: '',
    customer_id: '',
    purpose: '',
    content: '',
    result: '',
    next_plan: '',
    location: '',
    gps_lat: null,
    gps_lng: null,
    photos: []
  }
  // Try to get GPS location
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        formData.value.gps_lat = pos.coords.latitude
        formData.value.gps_lng = pos.coords.longitude
      },
      () => {} // Silently fail if denied
    )
  }
  showDialog.value = true
}

function openEditDialog(log) {
  dialogMode.value = 'edit'
  formData.value = {
    id: log.id,
    visit_date: log.visit_date,
    customer_type: log.customer_type,
    customer_id: log.customer_id,
    purpose: log.purpose,
    content: log.content,
    result: log.result || '',
    next_plan: log.next_plan || '',
    location: log.location || '',
    gps_lat: log.gps_lat,
    gps_lng: log.gps_lng,
    photos: log.photos ? JSON.parse(log.photos) : []
  }
  showDialog.value = true
}

async function handlePhotoUpload(event) {
  const files = Array.from(event.target.files)
  if (files.length === 0) return
  if (formData.value.photos.length + files.length > 5) {
    alert(t('logs.maxPhotosAlert'))
    return
  }

  uploadingPhotos.value = true
  try {
    for (const file of files) {
      const fd = new FormData()
      fd.append('file', file)
      const res = await api.post('/upload', fd)
      if (res.code === 0) {
        formData.value.photos.push(res.data.url)
      }
    }
  } finally {
    uploadingPhotos.value = false
    event.target.value = '' // Reset input
  }
}

function removePhoto(index) {
  formData.value.photos.splice(index, 1)
}

async function saveLog() {
  // Validation
  if (!formData.value.visit_date || !formData.value.customer_type || !formData.value.customer_id || !formData.value.purpose) {
    alert(t('logs.fillRequired'))
    return
  }

  saving.value = true
  try {
    const payload = {
      ...formData.value,
      photos: formData.value.photos.length > 0 ? JSON.stringify(formData.value.photos) : null
    }

    let res
    if (dialogMode.value === 'create') {
      res = await api.post('/visit-logs', payload)
    } else {
      res = await api.put(`/visit-logs/${formData.value.id}`, payload)
    }

    if (res.code === 0) {
      showDialog.value = false
      await fetchLogs()
      await fetchStats()
    }
  } finally {
    saving.value = false
  }
}

// ─── Helpers ────────────────────────────────────────────────────────────────────
function formatDate(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleDateString(locale.value === 'zh' ? 'zh-CN' : 'en-US', { year: 'numeric', month: '2-digit', day: '2-digit' })
}

function formatTime(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleString(locale.value === 'zh' ? 'zh-CN' : 'en-US', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function getCustomerName(log) {
  return log.customer_name || '-'
}

function getCustomerTypeLabel(type) {
  const map = { supplier: t('logs.supplierType'), dealer: t('logs.dealerType'), store: t('logs.storeType') }
  return map[type] || type
}
</script>

<template>
  <div>
    <PageHeader :title="$t('logs.visitLogTitle')" :subtitle="$t('logs.visitLogSubtitle')" />

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 flex items-center gap-3">
        <div class="size-10 rounded-lg bg-primary/10 flex items-center justify-center">
          <span class="material-symbols-outlined text-primary text-[20px]">event_note</span>
        </div>
        <div>
          <p class="text-xs text-text-secondary">{{ $t('logs.allVisits') }}</p>
          <p class="text-xl font-bold text-text-primary">{{ stats.total }}</p>
        </div>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 flex items-center gap-3">
        <div class="size-10 rounded-lg bg-green-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-green-600 text-[20px]">calendar_month</span>
        </div>
        <div>
          <p class="text-xs text-text-secondary">{{ $t('logs.thisMonthVisits') }}</p>
          <p class="tebold text-text-primary">{{ stats.thisMonth }}</p>
        </div>
      </div>
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <p class="text-xs text-text-secondary mb-2">{{ $t('logs.customerTypeDist') }}</p>
        <div class="space-y-1 text-xs">
          <div class="flex justify-between">
            <span class="text-text-secondary">{{ $t('logs.supplierType') }}</span>
            <span class="font-medium text-text-primary">{{ stats.byType.supplier }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-text-secondary">{{ $t('logs.dealerType') }}</span>
            <span class="font-medium text-text-primary">{{ stats.byType.dealer }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-text-secondary">{{ $t('logs.storeType') }}</span>
            <span class="font-medium text-text-primary">{{ stats.byType.store }}</span>
          </div>
        </div>
      </div>
      <div v-if="userStore.canAccess('visitlogs_admin') && stats.byUser.length > 0" class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
        <p class="text-xs text-text-secondary mb-2">{{ $t('logs.employeeVisitStats') }}</p>
        <div class="space-y-1 text-xs max-h-[60px] overflow-y-auto">
          <div v-for="u in stats.byUser.slice(0, 3)" :key="u.user_id" class="flex justify-between">
            <span class="text-text-secondary truncate">{{ u.user_name }}</span>
            <span class="font-medium text-text-primary">{{ u.count }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-6">
      <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center bg-gray-50 rounded-lg px-3 py-2 border border-transparent focus-within:border-primary focus-within:bg-white transition-all min-w-[180px]">
          <span class="material-symbols-outlined text-text-secondary text-[18px]">search</span>
          <input v-model="filterKeyword" type="text" :placeholder="$t('logs.searchVisit')" class="bg-transparent border-none focus:ring-0 focus:outline-none text-sm w-full ml-2" />
        </div>
        <input v-model="filterDateStart" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <span class="text-text-secondary text-sm">—</span>
        <input v-model="filterDateEnd" type="date" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
        <select v-model="filterCustomerType" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('logs.allCustomerTypes') }}</option>
          <option value="supplier">{{ $t('logs.supplierType') }}</option>
          <option value="dealer">{{ $t('logs.dealerType') }}</option>
          <option value="store">{{ $t('logs.storeType') }}</option>
        </select>
        <select v-if="filterCustomerType" v-model="filterCustomerId" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none min-w-[150px]">
          <option value="">{{ $t('logs.allCustomers') }}</option>
          <option v-for="c in filteredCustomers" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <button @click="filterDateStart='';filterDateEnd='';filterCustomerType='';filterCustomerId='';filterKeyword=''" class="text-sm text-text-secondary hover:text-text-primary border border-gray-200 rounded-lg px-3 py-2">
          {{ $t('common.reset') }}
        </button>
        <div class="ml-auto">
          <button @click="openCreateDialog" class="flex items-center gap-1.5 bg-primary text-white hover:bg-primary-hover px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">add</span>
            {{ $t('logs.newVisit') }}
          </button>
        </div>
      </div>
    </div>

    <!-- List View -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
      <div v-if="loading" class="col-span-full text-center py-12 text-text-secondary text-sm">{{ $t('common.loading') }}</div>
      <div v-else-if="logs.length === 0" class="col-span-full text-center py-12 text-text-secondary text-sm">{{ $t('logs.noVisitRecords') }}</div>
      <div
        v-for="log in logs"
        :key="log.id"
        @click="openDetail(log)"
        class="bg-white rounded-lg border border-gray-100 shadow-card p-4 hover:shadow-lg hover:border-primary/30 transition-all cursor-pointer"
      >
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[20px]">calendar_today</span>
            <span class="text-sm font-medium text-text-primary">{{ formatDate(log.visit_date) }}</span>
          </div>
          <span class="text-xs px-2 py-0.5 rounded-full bg-primary/10 text-primary">{{ getCustomerTypeLabel(log.customer_type) }}</span>
        </div>
        <h4 class="font-bold text-text-primary mb-1">{{ getCustomerName(log) }}</h4>
        <p class="text-sm text-text-secondary mb-2">{{ log.purpose }}</p>
        <p class="text-xs text-text-secondary line-clamp-2 mb-3">{{ log.content || $t('logs.noContent') }}</p>
        <div class="flex items-center justify-between text-xs text-text-secondary">
          <div class="flex items-center gap-1">
            <span class="material-symbols-outlined text-[14px]">photo_library</span>
            <span>{{ log.photos ? JSON.parse(log.photos).length : 0 }} {{ $t('logs.photosCount') }}</span>
          </div>
          <span>{{ log.created_by_name }}</span>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="total > 0" class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
      <Pagination :total="total" :page="currentPage" :pageSize="pageSize" @update:page="currentPage = $event" />
    </div>

    <!-- Detail Modal -->
    <Teleport to="body">
      <div v-if="showDetailModal && selectedLog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showDetailModal = false"></div>
        <div class="relative w-full max-w-2xl bg-white rounded-lg shadow-xl max-h-[90vh] overflow-y-auto">
          <!-- Header -->
          <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-text-primary">{{ $t('logs.visitDetail') }}</h3>
            <button @click="showDetailModal = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Content -->
          <div class="p-6 space-y-5">
            <!-- Basic Info -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="text-xs text-text-secondary mb-1">{{ $t('logs.visitDateLabel') }}</p>
                <p class="text-sm font-medium text-text-primary">{{ formatDate(selectedLog.visit_date) }}</p>
              </div>
              <div>
                <p class="text-xs text-text-secondary mb-1">{{ $t('logs.customerTypeLabel') }}</p>
                <p class="text-sm font-medium text-text-primary">{{ getCustomerTypeLabel(selectedLog.customer_type) }}</p>
              </div>
              <div>
                <p class="text-xs text-text-secondary mb-1">{{ $t('logs.customerNameLabel') }}</p>
                <p class="text-sm font-medium text-text-primary">{{ getCustomerName(selectedLog) }}</p>
              </div>
              <div>
                <p class="text-xs text-text-secondary mb-1">{{ $t('logs.visitorLabel') }}</p>
                <p class="text-sm font-medium text-text-primary">{{ selectedLog.created_by_name }}</p>
              </div>
            </div>

            <hr class="border-gray-100" />

            <!-- Purpose -->
            <div>
              <p class="text-xs text-text-secondary mb-1">{{ $t('logs.visitPurposeLabel') }}</p>
              <p class="text-sm text-text-primary">{{ selectedLog.purpose }}</p>
            </div>

            <!-- Content -->
            <div>
              <p class="text-xs text-text-secondary mb-1">{{ $t('logs.visitContentLabel') }}</p>
              <p class="text-sm text-text-primary whitespace-pre-wrap">{{ selectedLog.content || '-' }}</p>
            </div>

            <!-- Result -->
            <div v-if="selectedLog.result">
              <p class="text-xs text-text-secondary mb-1">{{ $t('logs.visitResultLabel') }}</p>
              <p class="text-sm text-text-primary whitespace-pre-wrap">{{ selectedLog.result }}</p>
            </div>

            <!-- Next Plan -->
            <div v-if="selectedLog.next_plan">
              <p class="text-xs text-text-secondary mb-1">{{ $t('logs.nextPlanLabel') }}</p>
              <p class="text-sm text-text-primary whitespace-pre-wrap">{{ selectedLog.next_plan }}</p>
            </div>

            <!-- Location -->
            <div v-if="selectedLog.location || (selectedLog.gps_lat && selectedLog.gps_lng)">
              <p class="text-xs text-text-secondary mb-1">{{ $t('logs.locationInfo') }}</p>
              <div class="flex items-start gap-2">
                <span class="material-symbols-outlined text-text-secondary text-[18px]">location_on</span>
                <div>
                  <p v-if="selectedLog.location" class="text-sm text-text-primary">{{ selectedLog.location }}</p>
                  <p v-if="selectedLog.gps_lat && selectedLog.gps_lng" class="text-xs text-text-secondary font-mono">
                    {{ selectedLog.gps_lat.toFixed(6) }}, {{ selectedLog.gps_lng.toFixed(6) }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Photos -->
            <div v-if="selectedLog.photos">
              <p class="text-xs text-text-secondary mb-2">{{ $t('logs.photosLabel') }}</p>
              <div class="grid grid-cols-3 gap-2">
                <img
                  v-for="(photo, idx) in JSON.parse(selectedLog.photos)"
                  :key="idx"
                  :src="photo"
                  class="w-full h-32 object-cover rounded-lg border border-gray-100 cursor-pointer hover:opacity-80"
                  @click="window.open(photo, '_blank')"
                />
              </div>
            </div>

            <hr class="border-gray-100" />

            <p class="text-xs text-text-secondary">{{ $t('logs.createdAtLabel') }}{{ formatTime(selectedLog.created_at) }}</p>
          </div>

          <!-- Footer -->
          <div v-if="canEdit(selectedLog)" class="sticky bottom-0 bg-white border-t px-6 py-4 flex justify-end gap-3">
            <button @click="deleteLog(selectedLog.id)" class="px-4 py-2 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50">
              {{ $t('common.delete') }}
            </button>
            <button @click="showDetailModal = false; openEditDialog(selectedLog)" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-hover">
              {{ $t('common.edit') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Create/Edit Dialog -->
    <Teleport to="body">
      <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showDialog = false"></div>
        <div class="relative w-full max-w-2xl bg-white rounded-lg shadow-xl max-h-[90vh] overflow-y-auto">
          <!-- Header -->
          <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-text-primary">{{ dialogMode === 'create' ? $t('logs.newVisitTitle') : $t('logs.editVisitTitle') }}</h3>
            <button @click="showDialog = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Form -->
          <div class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('logs.visitDateLabel') }} <span class="text-red-500">*</span></label>
                <input v-model="formData.visit_date" type="date" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('logs.customerTypeLabel') }} <span class="text-red-500">*</span></label>
                <select v-model="formData.customer_type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                  <option value="">{{ $t('logs.pleaseSelect') }}</option>
                  <option value="supplier">{{ $t('logs.supplierType') }}</option>
                  <option value="dealer">{{ $t('logs.dealerType') }}</option>
                  <option value="store">{{ $t('logs.storeType') }}</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('logs.customerNameLabel') }} <span class="text-red-500">*</span></label>
              <select v-model="formData.customer_id" :disabled="!formData.customer_type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none disabled:bg-gray-50">
                <option value="">{{ $t('logs.selectCustomer') }}</option>
                <option v-for="c in dialogCustomers" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('logs.visitPurposeLabel') }} <span class="text-red-500">*</span></label>
              <input v-model="formData.purpose" type="text" :placeholder="$t('logs.purposePlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('logs.visitContentLabel') }}</label>
              <textarea v-model="formData.content" rows="4" :placeholder="$t('logs.contentPlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('logs.visitResultLabel') }}</label>
              <textarea v-model="formData.result" rows="3" :placeholder="$t('logs.resultPlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('logs.nextPlanLabel') }}</label>
              <textarea v-model="formData.next_plan" rows="2" :placeholder="$t('logs.nextPlanPlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('logs.locationLabel') }}</label>
              <input v-model="formData.location" type="text" :placeholder="$t('logs.visitLocation')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
              <p v-if="formData.gps_lat && formData.gps_lng" class="text-xs text-text-secondary mt-1 font-mono">
                GPS: {{ formData.gps_lat.toFixed(6) }}, {{ formData.gps_lng.toFixed(6) }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('logs.maxPhotos') }}</label>
              <div class="space-y-2">
                <div v-if="formData.photos.length > 0" class="grid grid-cols-5 gap-2">
                  <div v-for="(photo, idx) in formData.photos" :key="idx" class="relative group">
                    <img :src="photo" class="w-full h-20 object-cover rounded-lg border border-gray-100" />
                    <button @click="removePhoto(idx)" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                      <span class="material-symbols-outlined text-[14px]">close</span>
                    </button>
                  </div>
                </div>
                <label v-if="formData.photos.length < 5" class="flex items-center justify-center gap-2 border-2 border-dashed border-gray-200 rounded-lg px-4 py-3 text-sm text-text-secondary hover:border-primary hover:text-primary cursor-pointer transition-colors">
                  <span class="material-symbols-outlined text-[18px]">add_photo_alternate</span>
                  <span>{{ uploadingPhotos ? $t('logs.uploading') : $t('logs.addPhoto') }}</span>
                  <input type="file" accept="image/*" multiple @change="handlePhotoUpload" class="hidden" :disabled="uploadingPhotos" />
                </label>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="sticky bottom-0 bg-white border-t px-6 py-4 flex justify-end gap-3">
            <button @click="showDialog = false" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-secondary hover:border-gray-300">
              {{ $t('common.cancel') }}
            </button>
            <button @click="saveLog" :disabled="saving" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-hover disabled:opacity-50">
              {{ saving ? $t('logs.saving') : $t('common.save') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  /* Stats cards - stack vertically */
  .grid.grid-cols-4 {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }

  /* Toolbar - full width, stack items */
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-4.mb-6 {
    padding: 12px;
  }

  .flex.flex-wrap.items-center.gap-3 {
    flex-direction: column;
    align-items: stretch;
  }

  .flex.flex-wrap.items-center.gap-3 > * {
    width: 100%;
  }

  .flex.items-center.bg-gray-50.rounded-lg.px-3.py-2.border.border-transparent.focus-within\:border-primary.focus-within\:bg-white.transition-all.min-w-\[180px\] {
    min-width: unset;
  }

  .ml-auto {
    margin-left: 0;
    margin-top: 8px;
  }

  /* List cards - full width */
  .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-3.gap-4.mb-6 {
    gap: 12px;
  }

  /* Modal adjustments */
  .fixed.inset-0.z-50.flex.items-center.justify-center.p-4 {
    padding: 8px;
  }

  .relative.w-full.max-w-2xl.bg-white.rounded-lg.shadow-xl.max-h-\[90vh\].overflow-y-auto {
    max-height: 85vh;
  }

  /* Modal grid - single column */
  .grid.grid-cols-2.gap-4 {
    grid-template-columns: 1fr;
  }

  /* Modal photos grid */
  .grid.grid-cols-3.gap-2 {
    grid-template-columns: repeat(2, 1fr);
  }

  /* Form inputs */
  .p-6.space-y-4 input,
  .p-6.space-y-4 select,
  .p-6.space-y-4 textarea {
    font-size: 14px;
  }

  /* Photo upload grid */
  .grid.grid-cols-5.gap-2 {
    grid-template-columns: repeat(3, 1fr);
  }

  /* Modal footer */
  .sticky.bottom-0.bg-white.border-t.px-6.py-4.flex.justify-end.gap-3 {
    padding: 12px;
    gap: 8px;
  }

  .sticky.bottom-0.bg-white.border-t.px-6.py-4.flex.justify-end.gap-3 button {
    padding: 8px 12px;
    font-size: 13px;
  }
}
</style scoped>
