<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'

const { t } = useI18n()

const loading = ref(false)
const config = ref({
  expense_approval_threshold: 5000,
  payment_approval_threshold: 10000,
  approval_workflow_level: 1
})

onMounted(() => {
  loadConfig()
})

async function loadConfig() {
  loading.value = true
  try {
    const res = await api.get('/finance-simple/config')
    if (res.code === 0) {
      config.value = {
        expense_approval_threshold: parseFloat(res.data.expense_approval_threshold?.value || 5000),
        payment_approval_threshold: parseFloat(res.data.payment_approval_threshold?.value || 10000),
        approval_workflow_level: parseInt(res.data.approval_workflow_level?.value || 1)
      }
    }
  } catch (err) {
    console.error('Failed to load config:', err)
  } finally {
    loading.value = false
  }
}

async function saveConfig() {
  loading.value = true
  try {
    const res = await api.put('/finance-simple/config', config.value)
    if (res.code === 0) {
      alert(t('common.saveSuccess'))
    }
  } catch (err) {
    alert(err.response?.data?.message || t('common.saveFailed'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader :title="$t('financeSimple.approvalSettings')" :subtitle="$t('financeSimple.approvalSettingsSubtitle')" />

    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-6">
      <div class="space-y-6">
        <div>
          <h3 class="text-lg font-semibold text-text-primary mb-4">{{ $t('financeSimple.approvalThresholds') }}</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-2">
                {{ $t('financeSimple.expenseApprovalThreshold') }}
              </label>
              <div class="flex items-center gap-3">
                <input
                  v-model.number="config.expense_approval_threshold"
                  type="number"
                  step="100"
                  min="0"
                  class="w-64 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                />
                <span class="text-sm text-text-secondary">{{ $t('financeSimple.yuan') }}</span>
              </div>
              <p class="mt-1 text-xs text-text-secondary">{{ $t('financeSimple.expenseThresholdHint') }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-2">
                {{ $t('financeSimple.paymentApprovalThreshold') }}
              </label>
              <div class="flex items-center gap-3">
                <input
                  v-model.number="config.payment_approval_threshold"
                  type="number"
                  step="100"
                  min="0"
                  class="w-64 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                />
                <span class="text-sm text-text-secondary">{{ $t('financeSimple.yuan') }}</span>
              </div>
              <p class="mt-1 text-xs text-text-secondary">{{ $t('financeSimple.paymentThresholdHint') }}</p>
            </div>
          </div>
        </div>

        <div class="border-t border-gray-100 pt-6">
          <h3 class="text-lg font-semibold text-text-primary mb-4">{{ $t('financeSimple.approvalWorkflow') }}</h3>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-2">
              {{ $t('financeSimple.workflowLevel') }}
            </label>
            <select
              v-model.number="config.approval_workflow_level"
              class="w-64 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            >
              <option :value="1">{{ $t('financeSimple.oneLevelApproval') }}</option>
              <option :value="2">{{ $t('financeSimple.twoLevelApproval') }}</option>
            </select>
            <p class="mt-1 text-xs text-text-secondary">{{ $t('financeSimple.workflowLevelHint') }}</p>
          </div>
        </div>

        <div class="border-t border-gray-100 pt-6">
          <button
            @click="saveConfig"
            :disabled="loading"
            class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ loading ? $t('common.saving') : $t('common.save') }}
          </button>
        </div>
      </div>
    </div>

    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
      <div class="flex items-start gap-3">
        <span class="material-symbols-outlined text-blue-600 text-[20px]">info</span>
        <div class="flex-1 text-sm text-blue-800">
          <p class="font-medium mb-1">{{ $t('financeSimple.approvalSettingsNote') }}</p>
          <ul class="list-disc list-inside space-y-1 text-xs">
            <li>{{ $t('financeSimple.approvalNote1') }}</li>
            <li>{{ $t('financeSimple.approvalNote2') }}</li>
            <li>{{ $t('financeSimple.approvalNote3') }}</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>
