<template>
  <div class="p-6 max-w-7xl mx-auto">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800">{{ $t('oa.leaveManageTitle') }}</h1>
      <p class="text-gray-600 mt-1">{{ $t('oa.leaveManageSubtitle') }}</p>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow mb-6">
      <div class="border-b border-gray-200">
        <nav class="flex -mb-px">
          <button
            @click="activeTab = 'my'"
            :class="['px-6 py-3 border-b-2 font-medium text-sm transition-colors', activeTab === 'my' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700']"
          >
            {{ $t('oa.myLeave') }}
          </button>
          <button
            @click="activeTab = 'approval'"
            :class="['px-6 py-3 border-b-2 font-medium text-sm transition-colors', activeTab === 'approval' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700']"
          >
            {{ $t('oa.pendingApprovalTab') }}
            <span v-if="pendingCount > 0" class="ml-1 px-2 py-0.5 bg-red-500 text-white text-xs rounded-full">{{ pendingCount }}</span>
          </button>
        </nav>
      </div>
    </div>

    <!-- 我的请假 Tab -->
    <div v-if="activeTab === 'my'">
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-lg font-semibold">{{ $t('oa.myLeaveRecords') }}</h2>
          <button @click="showCreateDialog = true" class="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark flex items-center gap-2">
            <span class="material-symbols-outlined text-lg">add</span>
            {{ $t('oa.applyLeave') }}
          </button>
        </div>

        <div class="space-y-3">
          <div v-if="loading" class="text-center py-8 text-gray-500">{{ $t('common.loading') }}</div>
          <div v-else-if="myLeaves.length === 0" class="text-center py-8 text-gray-500">{{ $t('oa.noLeaveRecords') }}</div>
          <div v-else v-for="leave in myLeaves" :key="leave.id" class="border rounded-lg p-4 hover:bg-gray-50">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                  <span class="font-medium">{{ getLeaveTypeText(leave.type) }}</span>
                  <span :class="getStatusClass(leave.status)" class="px-2 py-0.5 text-xs rounded">
                    {{ getStatusText(leave.status) }}
                  </span>
                </div>
                <div class="text-sm text-gray-600 space-y-1">
                  <div>{{ $t('oa.timeRange', { start: leave.start_date, end: leave.end_date, days: leave.days }) }}</div>
                  <div>{{ $t('oa.reasonText', { reason: leave.reason }) }}</div>
                  <div v-if="leave.reject_reason" class="text-red-600">{{ $t('oa.rejectReasonText', { reason: leave.reject_reason }) }}</div>
                  <div class="text-xs text-gray-500">{{ $t('oa.applyTimeText', { time: formatDateTime(leave.created_at) }) }}</div>
                </div>
              </div>
              <button v-if="leave.status === 'pending'" @click="deleteLeave(leave.id)" class="text-red-500 hover:text-red-700 text-sm">
                {{ $t('oa.withdraw') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 待审批 Tab -->
    <div v-if="activeTab === 'approval'">
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">{{ $t('oa.pendingLeaveApplications') }}</h2>
        <div class="space-y-3">
          <div v-if="loading" class="text-center py-8 text-gray-500">{{ $t('common.loading') }}</div>
          <div v-else-if="pendingLeaves.length === 0" class="text-center py-8 text-gray-500">{{ $t('oa.noPendingLeave') }}</div>
          <div v-else v-for="leave in pendingLeaves" :key="leave.id" class="border rounded-lg p-4">
            <div class="flex items-start justify-between mb-3">
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                  <span class="font-medium">{{ leave.user_name }}</span>
                  <span class="text-sm text-gray-500">{{ leave.department }}</span>
                  <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded">{{ getLeaveTypeText(leave.type) }}</span>
                </div>
                <div class="text-sm text-gray-600 space-y-1">
                  <div>{{ $t('oa.timeRange', { start: leave.start_date, end: leave.end_date, days: leave.days }) }}</div>
                  <div>{{ $t('oa.reasonText', { reason: leave.reason }) }}</div>
                  <div class="text-xs text-gray-500">{{ $t('oa.applyTimeText', { time: formatDateTime(leave.created_at) }) }}</div>
                </div>
              </div>
            </div>
            <div class="flex gap-2 justify-end">
              <button @click="handleApprove(leave, 'reject')" class="px-4 py-2 border border-red-500 text-red-500 rounded hover:bg-red-50">
                {{ $t('oa.reject') }}
              </button>
              <button @click="handleApprove(leave, 'approve')" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                {{ $t('oa.approve') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 创建请假对话框 -->
    <div v-if="showCreateDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg max-w-lg w-full">
        <div class="border-b px-6 py-4 flex justify-between items-center">
          <h3 class="text-lg font-bold">{{ $t('oa.applyLeaveTitle') }}</h3>
          <button @click="showCreateDialog = false" class="text-gray-500 hover:text-gray-700">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.leaveTypeLabel') }}</label>
            <select v-model="leaveForm.type" class="w-full px-3 py-2 border rounded">
              <option value="sick">{{ $t('oa.sickLeaveOption') }}</option>
              <option value="personal">{{ $t('oa.personalLeaveOption') }}</option>
              <option value="annual">{{ $t('oa.annualLeaveOption') }}</option>
              <option value="other">{{ $t('oa.otherLeaveOption') }}</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">{{ $t('oa.startDateLabel') }}</label>
              <input v-model="leaveForm.start_date" type="date" class="w-full px-3 py-2 border rounded">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">{{ $t('oa.endDateLabel') }}</label>
              <input v-model="leaveForm.end_date" type="date" class="w-full px-3 py-2 border rounded">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.leaveDaysLabel') }}</label>
            <input v-model.number="leaveForm.days" type="number" step="0.5" min="0.5" class="w-full px-3 py-2 border rounded">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.leaveReasonLabel') }}</label>
            <textarea v-model="leaveForm.reason" rows="3" class="w-full px-3 py-2 border rounded" :placeholder="$t('oa.leaveReasonPlaceholder')"></textarea>
          </div>
          <div class="flex gap-3 pt-2">
            <button @click="showCreateDialog = false" class="flex-1 px-4 py-2 border rounded hover:bg-gray-50">
              {{ $t('common.cancel') }}
            </button>
            <button @click="submitLeave" :disabled="loading" class="flex-1 px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark disabled:opacity-50">
              {{ $t('oa.submitApplication') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 审批对话框 -->
    <div v-if="showApprovalDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg max-w-lg w-full">
        <div class="border-b px-6 py-4 flex justify-between items-center">
          <h3 class="text-lg font-bold">{{ approvalAction === 'approve' ? $t('oa.approveLeaveTitle') : $t('oa.rejectLeaveTitle') }}</h3>
          <button @click="showApprovalDialog = false" class="text-gray-500 hover:text-gray-700">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div v-if="approvalAction === 'reject'">
            <label class="block text-sm font-medium mb-1">{{ $t('oa.rejectReasonInputLabel') }}</label>
            <textarea v-model="rejectReason" rows="3" class="w-full px-3 py-2 border rounded" :placeholder="$t('oa.rejectReasonPlaceholder')"></textarea>
          </div>
          <div v-else class="text-sm text-gray-600">
            {{ $t('oa.confirmApproveMsg', { name: selectedLeave?.user_name }) }}
          </div>
          <div class="flex gap-3 pt-2">
            <button @click="showApprovalDialog = false" class="flex-1 px-4 py-2 border rounded hover:bg-gray-50">
              {{ $t('common.cancel') }}
            </button>
            <button @click="confirmApproval" :disabled="loading" class="flex-1 px-4 py-2 text-white rounded disabled:opacity-50" :class="approvalAction === 'approve' ? 'bg-green-500 hover:bg-green-600' : 'bg-red-500 hover:bg-red-600'">
              {{ $t('common.confirm') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user'
import api from '../../services/api'

const { t } = useI18n()
const userStore = useUserStore()
const activeTab = ref('my')
const loading = ref(false)
const myLeaves = ref([])
const pendingLeaves = ref([])
const showCreateDialog = ref(false)
const showApprovalDialog = ref(false)
const approvalAction = ref('approve')
const selectedLeave = ref(null)
const rejectReason = ref('')

const leaveForm = ref({
  type: 'sick',
  start_date: '',
  end_date: '',
  days: 1,
  reason: ''
})

const pendingCount = computed(() => pendingLeaves.value.length)

onMounted(() => {
  loadMyLeaves()
  loadPendingLeaves()
})

async function loadMyLeaves() {
  loading.value = true
  try {
    const res = await api.get('/oa/leave', {
      params: { user_id: userStore.user.id }
    })
    if (res.code === 0) {
      myLeaves.value = res.data.list || []
    }
  } catch (err) {
    console.error('Failed to load leaves:', err)
  } finally {
    loading.value = false
  }
}

async function loadPendingLeaves() {
  try {
    const res = await api.get('/oa/leave/pending')
    if (res.code === 0) {
      pendingLeaves.value = res.data || []
    }
  } catch (err) {
    console.error('Failed to load pending leaves:', err)
  }
}

async function submitLeave() {
  if (!leaveForm.value.start_date || !leaveForm.value.end_date || !leaveForm.value.reason) {
    alert(t('oa.pleaseFillComplete'))
    return
  }

  loading.value = true
  try {
    const res = await api.post('/oa/leave', leaveForm.value)
    if (res.code === 0) {
      alert(t('oa.leaveSubmitted'))
      showCreateDialog.value = false
      leaveForm.value = {
        type: 'sick',
        start_date: '',
        end_date: '',
        days: 1,
        reason: ''
      }
      await loadMyLeaves()
    }
  } catch (err) {
    alert(err.response?.data?.message || t('oa.submitFailed'))
  } finally {
    loading.value = false
  }
}

async function deleteLeave(id) {
  if (!confirm(t('oa.confirmWithdraw'))) return

  try {
    const res = await api.delete(`/oa/leave/${id}`)
    if (res.code === 0) {
      alert(t('oa.withdrawn'))
      await loadMyLeaves()
    }
  } catch (err) {
    alert(err.response?.data?.message || t('oa.withdrawFailed'))
  }
}

function handleApprove(leave, action) {
  selectedLeave.value = leave
  approvalAction.value = action
  rejectReason.value = ''
  showApprovalDialog.value = true
}

async function confirmApproval() {
  if (approvalAction.value === 'reject' && !rejectReason.value) {
    alert(t('oa.pleaseEnterRejectReason'))
    return
  }

  loading.value = true
  try {
    const res = await api.put(`/oa/leave/${selectedLeave.value.id}/approve`, {
      action: approvalAction.value,
      reject_reason: rejectReason.value
    })
    if (res.code === 0) {
      alert(approvalAction.value === 'approve' ? t('oa.approved') : t('oa.rejected'))
      showApprovalDialog.value = false
      await loadPendingLeaves()
    }
  } catch (err) {
    alert(err.response?.data?.message || t('oa.submitFailed'))
  } finally {
    loading.value = false
  }
}

function getLeaveTypeText(type) {
  const types = {
    sick: t('oa.leaveTypeSick'),
    personal: t('oa.leaveTypePersonal'),
    annual: t('oa.leaveTypeAnnual'),
    other: t('oa.leaveTypeOther')
  }
  return types[type] || type
}

function getStatusClass(status) {
  const classes = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

function getStatusText(status) {
  const texts = {
    pending: t('oa.statusPending'),
    approved: t('oa.approved'),
    rejected: t('oa.rejected')
  }
  return texts[status] || status
}

function formatDateTime(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>
