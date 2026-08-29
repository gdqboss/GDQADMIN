<template>
  <div class="p-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold">{{ $t('finance.reminders.title') }}</h1>
      <div class="flex gap-2">
        <select v-model="filters.reminder_type" class="px-3 py-2 border rounded">
          <option value="">{{ $t('finance.reminders.allTypes') }}</option>
          <option value="payable_due">{{ $t('finance.reminders.types.payable_due') }}</option>
          <option value="receivable_overdue">{{ $t('finance.reminders.types.receivable_overdue') }}</option>
          <option value="expense_abnormal">{{ $t('finance.reminders.types.expense_abnormal') }}</option>
          <option value="monthly_report">{{ $t('finance.reminders.types.monthly_report') }}</option>
        </select>
        <select v-model="filters.status" class="px-3 py-2 border rounded">
          <option value="">{{ $t('finance.reminders.allStatus') }}</option>
          <option value="unread">{{ $t('finance.reminders.status.unread') }}</option>
          <option value="read">{{ $t('finance.reminders.status.read') }}</option>
        </select>
        <select v-model="filters.priority" class="px-3 py-2 border rounded">
          <option value="">{{ $t('finance.reminders.allPriority') }}</option>
          <option value="high">{{ $t('finance.reminders.priority.high') }}</option>
          <option value="medium">{{ $t('finance.reminders.priority.medium') }}</option>
          <option value="low">{{ $t('finance.reminders.priority.low') }}</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
    </div>

    <div v-else-if="reminders.length === 0" class="text-center py-12 text-gray-500">
      {{ $t('finance.reminders.noData') }}
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="reminder in reminders"
        :key="reminder.id"
        class="bg-white border rounded-lg p-4 hover:shadow-md transition-shadow"
        :class="{
          'border-l-4 border-l-red-500': reminder.priority === 'high',
          'border-l-4 border-l-yellow-500': reminder.priority === 'medium',
          'border-l-4 border-l-blue-500': reminder.priority === 'low',
          'bg-gray-50': reminder.status !== 'unread'
        }"
      >
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <span
                class="px-2 py-1 text-xs rounded"
                :class="{
                  'bg-red-100 text-red-700': reminder.reminder_type === 'payable_due',
                  'bg-orange-100 text-orange-700': reminder.reminder_type === 'receivable_overdue',
                  'bg-purple-100 text-purple-700': reminder.reminder_type === 'expense_abnormal',
                  'bg-blue-100 text-blue-700': reminder.reminder_type === 'monthly_report'
                }"
              >
                {{ $t(`finance.reminders.types.${reminder.reminder_type}`) }}
              </span>
              <span
                class="px-2 py-1 text-xs rounded"
                :class="{
                  'bg-red-100 text-red-700': reminder.priority === 'high',
                  'bg-yellow-100 text-yellow-700': reminder.priority === 'medium',
                  'bg-blue-100 text-blue-700': reminder.priority === 'low'
                }"
              >
                {{ $t(`finance.reminders.priority.${reminder.priority}`) }}
              </span>
              <span v-if="reminder.status === 'unread'" class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded">
                {{ $t('finance.reminders.status.unread') }}
              </span>
            </div>
            <h3 class="font-semibold text-lg mb-2">{{ reminder.title }}</h3>
            <p class="text-gray-600 whitespace-pre-line mb-2">{{ reminder.content }}</p>
            <p class="text-sm text-gray-400">{{ formatDate(reminder.created_at) }}</p>
          </div>
          <div class="flex gap-2 ml-4">
            <button
              v-if="reminder.status === 'unread'"
              @click="markAsRead(reminder.id)"
              class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600"
            >
              {{ $t('finance.reminders.markRead') }}
            </button>
            <button
              v-if="reminder.status === 'unread'"
              @click="dismissReminder(reminder.id)"
              class="px-3 py-1 text-sm bg-gray-500 text-white rounded hover:bg-gray-600"
            >
              {{ $t('finance.reminders.dismiss') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="total > pageSize" class="mt-6 flex justify-center">
      <div class="flex gap-2">
        <button
          @click="currentPage--"
          :disabled="currentPage === 1"
          class="px-4 py-2 border rounded disabled:opacity-50"
        >
          {{ $t('common.previous') }}
        </button>
        <span class="px-4 py-2">{{ currentPage }} / {{ Math.ceil(total / pageSize) }}</span>
        <button
          @click="currentPage++"
          :disabled="currentPage >= Math.ceil(total / pageSize)"
          class="px-4 py-2 border rounded disabled:opacity-50"
        >
          {{ $t('common.next') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api.js'

const { t } = useI18n()

const loading = ref(false)
const reminders = ref([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)

const filters = reactive({
  reminder_type: '',
  status: '',
  priority: ''
})

const fetchReminders = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      size: pageSize.value,
      ...filters
    }
    Object.keys(params).forEach(key => !params[key] && delete params[key])

    const data = await api.get('/finance-simple/reminders', { params })
    if (data.code === 0) {
      reminders.value = data.data.list
      total.value = data.data.total
    }
  } catch (error) {
    console.error('获取提醒失败:', error)
  } finally {
    loading.value = false
  }
}

const markAsRead = async (id) => {
  try {
    const data = await api.put(`/finance-simple/reminders/${id}/read`)
    if (data.code === 0) {
      await fetchReminders()
    }
  } catch (error) {
    console.error('标记已读失败:', error)
  }
}

const dismissReminder = async (id) => {
  try {
    const data = await api.put(`/finance-simple/reminders/${id}/dismiss`)
    if (data.code === 0) {
      await fetchReminders()
    }
  } catch (error) {
    console.error('忽略提醒失败:', error)
  }
}

const formatDate = (dateStr) => {
  return new Date(dateStr).toLocaleString('zh-CN')
}

watch([filters, currentPage], () => {
  fetchReminders()
})

onMounted(() => {
  fetchReminders()
})
</script>
