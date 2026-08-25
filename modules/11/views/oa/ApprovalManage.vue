<template>
  <div class="p-6">
    <div class="mb-6 flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $t('oa.approvalManageTitle') }}</h1>
        <p class="text-gray-600 mt-1">{{ $t('oa.approvalManageSubtitle') }}</p>
      </div>
      <router-link to="/oa/approvals/create" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark flex items-center gap-2">
        <span class="material-symbols-outlined">add</span>
        <span>{{ $t('oa.initiateApprovalBtn') }}</span>
      </router-link>
    </div>

    <!-- Quick Launch Cards -->
    <div class="mb-6 bg-white rounded-lg shadow p-4">
      <h3 class="text-sm font-medium text-gray-600 mb-3">{{ $t('oa.quickLaunch') }}</h3>
      <div class="grid grid-cols-4 md:grid-cols-8 gap-2">
        <router-link
          v-for="type in approvalTypes"
          :key="type.code"
          :to="`/oa/approvals/create?type=${type.code}`"
          class="flex flex-col items-center gap-1 p-3 rounded-lg border border-gray-100 hover:border-primary hover:bg-primary/5 transition-all text-center"
        >
          <span class="material-symbols-outlined text-2xl text-primary">{{ type.icon || getTypeIcon(type.code) }}</span>
          <span class="text-xs text-gray-700">{{ type.name }}</span>
        </router-link>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
      <!-- Tabs -->
      <div class="flex gap-4 mb-4">
        <button
          @click="activeTab = 'pending'"
          :class="['px-4 py-2 rounded-lg', activeTab === 'pending' ? 'bg-primary text-white' : 'bg-gray-100']"
        >
          {{ $t('oa.pendingMyApprovalTab') }}
        </button>
        <button
          @click="activeTab = 'mine'"
          :class="['px-4 py-2 rounded-lg', activeTab === 'mine' ? 'bg-primary text-white' : 'bg-gray-100']"
        >
          {{ $t('oa.myInitiated') }}
        </button>
        <button
          @click="activeTab = 'all'"
          :class="['px-4 py-2 rounded-lg', activeTab === 'all' ? 'bg-primary text-white' : 'bg-gray-100']"
        >
          {{ $t('oa.allApprovals') }}
        </button>
      </div>

      <!-- Filters -->
      <div class="flex gap-3 mb-4">
        <select v-model="filterType" @change="loadApprovals" class="border rounded-lg px-3 py-2 text-sm">
          <option value="">{{ $t('oa.allTypes') }}</option>
          <option v-for="type in approvalTypes" :key="type.code" :value="type.code">{{ type.name }}</option>
        </select>
        <select v-model="filterStatus" @change="loadApprovals" class="border rounded-lg px-3 py-2 text-sm">
          <option value="">{{ $t('oa.allStatuses') }}</option>
          <option value="pending">{{ $t('oa.approvalStatusPending') }}</option>
          <option value="approved">{{ $t('oa.approvalStatusApproved') }}</option>
          <option value="rejected">{{ $t('oa.approvalStatusRejected') }}</option>
        </select>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ $t('oa.typeColumn') }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ $t('oa.titleColumn') }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ $t('oa.applicantColumn') }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ $t('oa.statusColumn') }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ $t('oa.createdTimeColumn') }}</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ $t('oa.actionColumn') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-if="approvals.list.length === 0">
              <td colspan="6" class="px-4 py-8 text-center text-gray-400">{{ $t('common.noData') }}</td>
            </tr>
            <tr v-for="approval in approvals.list" :key="approval.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm">
                <span class="inline-flex items-center gap-1">
                  <span class="material-symbols-outlined text-lg">{{ approval.type_icon || getTypeIcon(approval.type_code) }}</span>
                  {{ approval.type_name }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm font-medium">{{ approval.title || getDefaultTitle(approval) }}</td>
              <td class="px-4 py-3 text-sm">{{ approval.applicant_name }}</td>
              <td class="px-4 py-3 text-sm">
                <span :class="getStatusClass(approval.status)">{{ getStatusText(approval.status) }}</span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(approval.created_at) }}</td>
              <td class="px-4 py-3 text-sm">
                <button @click="viewApproval(approval)" class="text-primary hover:underline">{{ $t('oa.viewBtn') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- View Approval Modal -->
    <div v-if="viewingApproval" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <h2 class="text-xl font-bold mb-4">{{ $t('oa.approvalDetailDialog') }}</h2>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div><span class="text-gray-600">{{ $t('oa.typeFieldLabel') }}</span>{{ viewingApproval.type_name }}</div>
            <div><span class="text-gray-600">{{ $t('oa.applicantFieldLabel') }}</span>{{ viewingApproval.applicant_name }}</div>
            <div><span class="text-gray-600">{{ $t('oa.statusFieldLabel') }}</span><span :class="getStatusClass(viewingApproval.status)">{{ getStatusText(viewingApproval.status) }}</span></div>
            <div><span class="text-gray-600">{{ $t('oa.createdTimeFieldLabel') }}</span>{{ formatDate(viewingApproval.created_at) }}</div>
          </div>
          <div>
            <h3 class="font-semibold mb-2">{{ $t('oa.formContent') }}</h3>
            <div class="bg-gray-50 p-4 rounded space-y-2">
              <div v-if="viewingApproval.type_code === 'expense'">
                <div class="grid grid-cols-2 gap-3">
                  <div><span class="text-gray-600">{{ $t('oa.expenseIdLabel') }}</span>{{ parseFormData(viewingApproval.form_data).expense_id }}</div>
                  <div><span class="text-gray-600">{{ $t('oa.amountLabel') }}</span><span class="font-semibold text-red-600">¥{{ parseFormData(viewingApproval.form_data).amount }}</span></div>
                  <div><span class="text-gray-600">{{ $t('oa.categoryLabel') }}</span>{{ parseFormData(viewingApproval.form_data).category }}</div>
                  <div><span class="text-gray-600">{{ $t('oa.payeeLabel') }}</span>{{ parseFormData(viewingApproval.form_data).payee || '-' }}</div>
                </div>
                <div class="mt-2"><span class="text-gray-600">{{ $t('oa.descriptionLabel') }}</span>{{ parseFormData(viewingApproval.form_data).description }}</div>
              </div>
              <div v-else>
                <div v-for="(value, key) in parseFormData(viewingApproval.form_data)" :key="key">
                  <span class="text-gray-600">{{ key }}:</span> {{ value }}
                </div>
              </div>
            </div>
          </div>
          <div v-if="viewingApproval.steps && viewingApproval.steps.length">
            <h3 class="font-semibold mb-2">{{ $t('oa.approvalFlowTitle') }}</h3>
            <div class="space-y-2">
              <div v-for="step in viewingApproval.steps" :key="step.id" class="flex items-center gap-3 p-3 bg-gray-50 rounded">
                <span class="material-symbols-outlined" :class="step.status === 'approved' ? 'text-green-600' : step.status === 'rejected' ? 'text-red-600' : 'text-gray-400'">
                  {{ step.status === 'approved' ? 'check_circle' : step.status === 'rejected' ? 'cancel' : 'radio_button_unchecked' }}
                </span>
                <div class="flex-1">
                  <p class="font-medium">{{ step.approver_name }}</p>
                  <p class="text-sm text-gray-600">{{ step.comment || '-' }}</p>
                </div>
                <span class="text-xs text-gray-500">{{ step.approved_at ? formatDate(step.approved_at) : $t('oa.approvalStatusPending') }}</span>
              </div>
            </div>
          </div>
          <div v-if="canApprove(viewingApproval)" class="flex gap-2">
            <button @click="handleApprove('approve')" class="flex-1 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">{{ $t('oa.passBtn') }}</button>
            <button @click="handleApprove('reject')" class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">{{ $t('oa.rejectBtn') }}</button>
          </div>
        </div>
        <div class="mt-6 flex justify-end">
          <button @click="viewingApproval = null" class="px-4 py-2 border rounded hover:bg-gray-50">{{ $t('oa.closeBtn') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user'
import api from '../../services/api'

const { t } = useI18n()
const userStore = useUserStore()
const activeTab = ref('pending')
const approvals = ref({ list: [], total: 0 })
const approvalTypes = ref([])
const viewingApproval = ref(null)
const filterType = ref('')
const filterStatus = ref('')

const typeIconMap = {
  vehicle: 'directions_car',
  seal: 'verified',
  advance: 'payments',
  expense: 'receipt_long',
  leave: 'event_busy',
  hire: 'person_add',
  resign: 'person_remove',
  transfer: 'swap_horiz'
}

function getTypeIcon(code) {
  return typeIconMap[code] || 'description'
}

function getDefaultTitle(approval) {
  const data = parseFormData(approval.form_data)
  return data.reason || data.description || approval.type_name || '-'
}

onMounted(() => {
  loadApprovalTypes()
  loadApprovals()
})

watch(activeTab, () => {
  loadApprovals()
})

async function loadApprovalTypes() {
  try {
    const res = await api.get('/oa/approval-types')
    if (res.code === 0) {
      approvalTypes.value = res.data.map(t => ({
        ...t,
        form_fields: typeof t.form_fields === 'string' ? JSON.parse(t.form_fields) : t.form_fields
      }))
    }
  } catch (err) {
    console.error('Failed to load approval types:', err)
  }
}

async function loadApprovals() {
  try {
    const params = {}
    if (activeTab.value === 'pending') {
      params.approver_id = userStore.userId
      params.status = 'pending'
    } else if (activeTab.value === 'mine') {
      params.applicant_id = userStore.userId
    }
    // Apply filters
    if (filterType.value) params.type_code = filterType.value
    if (filterStatus.value && activeTab.value !== 'pending') params.status = filterStatus.value

    const res = await api.get('/oa/approvals', { params })
    if (res.code === 0) {
      approvals.value = res.data
    }
  } catch (err) {
    console.error('Failed to load approvals:', err)
  }
}

function viewApproval(approval) {
  viewingApproval.value = approval
}

function canApprove(approval) {
  if (activeTab.value !== 'pending') return false
  const pendingStep = approval.steps?.find(s => s.status === 'pending' && s.approver_id === userStore.userId)
  return !!pendingStep
}

async function handleApprove(action) {
  const comment = prompt(action === 'approve' ? t('oa.approvalCommentPrompt') : t('oa.rejectReasonPrompt'))
  if (comment === null) return

  try {
    const res = await api.post(`/oa/approvals/${viewingApproval.value.id}/action`, {
      approver_id: userStore.userId,
      action,
      comment
    })
    if (res.code === 0) {
      alert(t('oa.operationSuccess'))
      viewingApproval.value = null
      loadApprovals()
    }
  } catch (err) {
    alert(err.response?.data?.message || t('oa.submitFailed'))
  }
}

function getStatusClass(status) {
  const classes = {
    pending: 'text-orange-600 font-semibold',
    approved: 'text-green-600 font-semibold',
    rejected: 'text-red-600 font-semibold'
  }
  return classes[status] || 'text-gray-600'
}

function getStatusText(status) {
  const texts = {
    pending: t('oa.approvalStatusPending'),
    approved: t('oa.approvalStatusApproved'),
    rejected: t('oa.approvalStatusRejected')
  }
  return texts[status] || status
}

function formatDate(date) {
  return new Date(date).toLocaleString('zh-CN')
}

function parseFormData(data) {
  try {
    return typeof data === 'string' ? JSON.parse(data) : (data || {})
  } catch {
    return {}
  }
}
</script>

<style scoped>
@media (max-width: 768px) {
  /* 页面整体适配 */
  .p-6 {
    padding: 1rem;
  }

  /* 头部标题区域 */
  .mb-6.flex.justify-between.items-center {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .mb-6 .text-2xl {
    font-size: 1.25rem;
  }

  /* 发起审批按钮 */
  .mb-6.flex.justify-between.items-center a {
    width: 100%;
    justify-content: center;
  }

  /* 快捷卡片 */
  .grid.grid-cols-4 {
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
  }

  /* 标签页 */
  .flex.gap-4.mb-4 {
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .flex.gap-4.mb-4 button {
    flex: 1;
    min-width: 30%;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
  }

  /* 筛选器 */
  .flex.gap-3.mb-4 {
    flex-direction: column;
    gap: 0.5rem;
  }

  .flex.gap-3.mb-4 select {
    width: 100%;
    font-size: 0.875rem;
  }

  /* 表格横向滚动 */
  .overflow-x-auto {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .overflow-x-auto table {
    min-width: 600px;
  }

  .overflow-x-auto th,
  .overflow-x-auto td {
    padding: 0.5rem;
    font-size: 0.75rem;
    white-space: nowrap;
  }

  .overflow-x-auto td .inline-flex {
    gap: 0.25rem;
  }

  /* 弹窗 */
  .fixed.inset-0.bg-black.bg-opacity-50 .bg-white {
    margin: 1rem;
    padding: 1rem;
    max-width: calc(100% - 2rem);
  }

  .bg-white.rounded-lg.p-6 {
    padding: 1rem;
  }

  .bg-white.rounded-lg.p-6 .grid.grid-cols-2 {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }

  /* 弹窗内按钮 */
  .flex.gap-2 button {
    padding: 0.5rem;
    font-size: 0.875rem;
  }

  .mt-6.flex.justify-end button {
    width: 100%;
  }
}
</style>
