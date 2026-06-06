<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold mb-2">{{ $t('finance.reminders.settings.title') }}</h1>
      <p class="text-gray-600">{{ $t('finance.reminders.settings.description') }}</p>
    </div>

    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
    </div>

    <div v-else class="space-y-6">
      <div
        v-for="setting in settings"
        :key="setting.id"
        class="bg-white border rounded-lg p-6"
      >
        <div class="flex items-start justify-between mb-4">
          <div>
            <h3 class="text-lg font-semibold mb-1">
              {{ $t(`finance.reminders.types.${setting.reminder_type}`) }}
            </h3>
            <p class="text-sm text-gray-600">
              {{ $t(`finance.reminders.settings.descriptions.${setting.reminder_type}`) }}
            </p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input
              type="checkbox"
              v-model="setting.enabled"
              class="sr-only peer"
            >
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
          </label>
        </div>

        <div v-if="setting.enabled" class="grid grid-cols-2 gap-4">
          <div v-if="setting.reminder_type === 'payable_due'">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              {{ $t('finance.reminders.settings.advanceDays') }}
            </label>
            <input
              type="number"
              v-model.number="setting.advance_days"
              min="1"
              max="30"
              class="w-full px-3 py-2 border rounded"
            >
            <p class="text-xs text-gray-500 mt-1">
              {{ $t('finance.reminders.settings.advanceDaysHint') }}
            </p>
          </div>

          <div v-if="setting.reminder_type === 'payable_due'">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              {{ $t('finance.reminders.settings.thresholdAmount') }}
            </label>
            <input
              type="number"
              v-model.number="setting.threshold_amount"
              min="0"
              step="1000"
              class="w-full px-3 py-2 border rounded"
            >
            <p class="text-xs text-gray-500 mt-1">
              {{ $t('finance.reminders.settings.thresholdAmountHint') }}
            </p>
          </div>

          <div v-if="setting.reminder_type === 'receivable_overdue'">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              {{ $t('finance.reminders.settings.overdueDays') }}
            </label>
            <input
              type="number"
              v-model.number="setting.advance_days"
              min="1"
              max="90"
              class="w-full px-3 py-2 border rounded"
            >
            <p class="text-xs text-gray-500 mt-1">
              {{ $t('finance.reminders.settings.overdueDaysHint') }}
            </p>
          </div>

          <div v-if="setting.reminder_type === 'receivable_overdue'">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              {{ $t('finance.reminders.settings.thresholdAmount') }}
            </label>
            <input
              type="number"
              v-model.number="setting.threshold_amount"
              min="0"
              step="1000"
              class="w-full px-3 py-2 border rounded"
            >
            <p class="text-xs text-gray-500 mt-1">
              {{ $t('finance.reminders.settings.thresholdAmountHint') }}
            </p>
          </div>

          <div v-if="setting.reminder_type === 'expense_abnormal'" class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              {{ $t('finance.reminders.settings.abnormalThreshold') }}
            </label>
            <input
              type="number"
              v-model.number="setting.threshold_amount"
              min="0"
              step="5000"
              class="w-full px-3 py-2 border rounded"
            >
            <p class="text-xs text-gray-500 mt-1">
              {{ $t('finance.reminders.settings.abnormalThresholdHint') }}
            </p>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-3">
        <button
          @click="resetSettings"
          class="px-6 py-2 border rounded hover:bg-gray-50"
        >
          {{ $t('common.reset') }}
        </button>
        <button
          @click="saveSettings"
          :disabled="saving"
          class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
        >
          {{ saving ? $t('common.saving') : $t('common.save') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api.js'

const { t } = useI18n()

const loading = ref(false)
const saving = ref(false)
const settings = ref([])
const originalSettings = ref([])

const fetchSettings = async () => {
  loading.value = true
  try {
    const data = await api.get('/finance-simple/reminder-settings')
    if (data.code === 0) {
      settings.value = data.data
      originalSettings.value = JSON.parse(JSON.stringify(data.data))
    }
  } catch (error) {
    console.error('获取设置失败:', error)
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  saving.value = true
  try {
    const data = await api.put('/finance-simple/reminder-settings', {
      settings: settings.value
    })
    if (data.code === 0) {
      alert(t('finance.reminders.settings.saveSuccess'))
      originalSettings.value = JSON.parse(JSON.stringify(settings.value))
    }
  } catch (error) {
    console.error('保存设置失败:', error)
    alert(t('finance.reminders.settings.saveFailed'))
  } finally {
    saving.value = false
  }
}

const resetSettings = () => {
  settings.value = JSON.parse(JSON.stringify(originalSettings.value))
}

onMounted(() => {
  fetchSettings()
})
</script>
