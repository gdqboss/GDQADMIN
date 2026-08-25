<template>
  <div class="p-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
      <div class="flex items-center gap-2 text-sm text-gray-600 mb-2">
        <router-link to="/oa/approvals" class="hover:text-primary">{{ $t('oa.approvalManageBreadcrumb') }}</router-link>
        <span>/</span>
        <span>{{ $t('oa.initiateApprovalBreadcrumb') }}</span>
      </div>
      <h1 class="text-2xl font-bold text-gray-800">{{ $t('oa.initiateApprovalHeading') }}</h1>
    </div>

    <!-- Step 1: Select Type -->
    <div v-if="step === 1" class="bg-white rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold mb-4">{{ $t('oa.selectApprovalTypeStep') }}</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <ApprovalTypeCard
          v-for="type in approvalTypes"
          :key="type.code"
          :icon="type.icon"
          :name="type.name"
          :description="getTypeDescription(type.code)"
          :selected="form.type_code === type.code"
          @select="selectType(type)"
        />
      </div>
      <div class="mt-6 flex justify-end">
        <button
          @click="nextStep"
          :disabled="!form.type_code"
          class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark disabled:bg-gray-300 disabled:cursor-not-allowed"
        >
          {{ $t('oa.nextStep') }}
        </button>
      </div>
    </div>

    <!-- Step 2: Fill Form -->
    <div v-if="step === 2" class="bg-white rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold mb-4">{{ $t('oa.fillApplicationInfoStep') }}</h2>

      <!-- Dynamic Form Fields -->
      <div class="space-y-4">
        <!-- Vehicle Application -->
        <template v-if="form.type_code === 'vehicle'">
          <VehicleForm v-model="form.form_data" />
        </template>

        <!-- Seal Application -->
        <template v-else-if="form.type_code === 'seal'">
          <SealForm v-model="form.form_data" />
        </template>

        <!-- Advance Payment -->
        <template v-else-if="form.type_code === 'advance'">
          <AdvanceForm v-model="form.form_data" />
        </template>

        <!-- Expense Reimbursement -->
        <template v-else-if="form.type_code === 'expense'">
          <ExpenseForm v-model="form.form_data" />
        </template>

        <!-- Leave Application -->
        <template v-else-if="form.type_code === 'leave'">
          <LeaveForm v-model="form.form_data" />
        </template>

        <!-- Hire Application -->
        <template v-else-if="form.type_code === 'hire'">
          <HireForm v-model="form.form_data" />
        </template>

        <!-- Resign Application -->
        <template v-else-if="form.type_code === 'resign'">
          <ResignForm v-model="form.form_data" />
        </template>

        <!-- Transfer Application -->
        <template v-else-if="form.type_code === 'transfer'">
          <TransferForm v-model="form.form_data" />
        </template>

        <!-- Attachments -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('oa.attachmentUpload') }}</label>
          <input
            type="file"
            multiple
            @change="handleFileUpload"
            accept="image/*,.pdf"
            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary-dark"
          />
          <p class="text-xs text-gray-500 mt-1">{{ $t('oa.supportedFormats') }}</p>
          <div v-if="form.attachments.length" class="mt-2 space-y-1">
            <div v-for="(file, idx) in form.attachments" :key="idx" class="flex items-center gap-2 text-sm">
              <span class="material-symbols-outlined text-gray-400">attach_file</span>
              <span>{{ file.name }}</span>
              <button @click="removeFile(idx)" class="text-red-500 hover:text-red-700">
                <span class="material-symbols-outlined text-sm">close</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-6 flex gap-3 justify-end">
        <button @click="step = 1" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
          {{ $t('oa.previousStep') }}
        </button>
        <button
          @click="submitApproval"
          :disabled="submitting"
          class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark disabled:bg-gray-300"
        >
          {{ submitting ? $t('oa.submittingText') : $t('oa.submitApprovalBtn') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '../../stores/user'
import api from '../../services/api'
import ApprovalTypeCard from '../../components/oa/ApprovalTypeCard.vue'
import VehicleForm from '../../components/oa/forms/VehicleForm.vue'
import SealForm from '../../components/oa/forms/SealForm.vue'
import AdvanceForm from '../../components/oa/forms/AdvanceForm.vue'
import ExpenseForm from '../../components/oa/forms/ExpenseForm.vue'
import LeaveForm from '../../components/oa/forms/LeaveForm.vue'
import HireForm from '../../components/oa/forms/HireForm.vue'
import ResignForm from '../../components/oa/forms/ResignForm.vue'
import TransferForm from '../../components/oa/forms/TransferForm.vue'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

const step = ref(1)
const approvalTypes = ref([])
const submitting = ref(false)

const form = ref({
  type_code: '',
  form_data: {},
  attachments: []
})

onMounted(async () => {
  await loadApprovalTypes()
  // 支持 URL 参数预选审批类型，如 ?type=expense
  const preType = route.query.type
  if (preType && approvalTypes.value.some(t => t.code === preType)) {
    form.value.type_code = preType
    form.value.form_data = {}
    step.value = 2
  }
})

async function loadApprovalTypes() {
  try {
    const res = await api.get('/oa/approval-types')
    if (res.code === 0) {
      approvalTypes.value = res.data.filter(t => t.status === 'active')
    }
  } catch (err) {
    console.error('Failed to load approval types:', err)
  }
}

function selectType(type) {
  form.value.type_code = type.code
  form.value.form_data = {}
}

function nextStep() {
  if (!form.value.type_code) {
    alert(t('oa.pleaseSelectType'))
    return
  }
  step.value = 2
}

function getTypeDescription(code) {
  const descriptions = {
    vehicle: t('oa.vehicleDesc'),
    seal: t('oa.sealDesc'),
    advance: t('oa.advanceDesc'),
    expense: t('oa.expenseDesc'),
    leave: t('oa.leaveDesc'),
    hire: t('oa.hireDesc'),
    resign: t('oa.resignDesc'),
    transfer: t('oa.transferDesc')
  }
  return descriptions[code] || ''
}

function handleFileUpload(e) {
  const files = Array.from(e.target.files)
  form.value.attachments.push(...files)
}

function removeFile(idx) {
  form.value.attachments.splice(idx, 1)
}

async function submitApproval() {
  if (!validateForm()) return

  submitting.value = true
  try {
    const formData = new FormData()
    formData.append('type_code', form.value.type_code)
    formData.append('applicant_id', userStore.userId)
    formData.append('form_data', JSON.stringify(form.value.form_data))

    form.value.attachments.forEach((file, idx) => {
      formData.append(`attachments`, file)
    })

    const res = await api.post('/oa/approvals', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    if (res.code === 0) {
      alert(t('oa.approvalSubmitted'))
      router.push('/oa/approvals')
    } else {
      alert(res.message || t('oa.submitFailed'))
    }
  } catch (err) {
    alert(err.response?.data?.message || t('oa.submitFailedRetry'))
  } finally {
    submitting.value = false
  }
}

function validateForm() {
  const data = form.value.form_data

  switch (form.value.type_code) {
    case 'vehicle':
      if (!data.use_date || !data.start_time || !data.end_time || !data.reason || !data.destination) {
        alert(t('oa.pleaseFillRequired'))
        return false
      }
      break
    case 'seal':
      if (!data.seal_type || !data.copies || !data.reason) {
        alert(t('oa.pleaseFillRequired'))
        return false
      }
      break
    case 'advance':
      if (!data.amount || !data.reason || !data.expected_date) {
        alert(t('oa.pleaseFillRequired'))
        return false
      }
      if (data.amount <= 0) {
        alert(t('oa.advanceAmountPositive'))
        return false
      }
      break
    case 'expense':
      if (!data.expense_type || !data.amount || !data.expense_date || !data.reason) {
        alert(t('oa.pleaseFillRequired'))
        return false
      }
      if (data.amount <= 0) {
        alert(t('oa.reimbursementAmountPositive'))
        return false
      }
      break
    case 'leave':
      if (!data.leave_type || !data.start_date || !data.end_date || !data.reason) {
        alert(t('oa.pleaseFillRequired'))
        return false
      }
      if (new Date(data.end_date) <= new Date(data.start_date)) {
        alert(t('oa.endDateAfterStart'))
        return false
      }
      break
    case 'hire':
      if (!data.name || !data.position || !data.department || !data.hire_date || !data.salary) {
        alert(t('oa.pleaseFillRequired'))
        return false
      }
      break
    case 'resign':
      if (!data.resign_type || !data.resign_date || !data.reason) {
        alert(t('oa.pleaseFillRequired'))
        return false
      }
      break
    case 'transfer':
      if (!data.employee_id || !data.new_department || !data.new_position || !data.transfer_date || !data.reason) {
        alert(t('oa.pleaseFillRequired'))
        return false
      }
      break
  }

  return true
}
</script>
