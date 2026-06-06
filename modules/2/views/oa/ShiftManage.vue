<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">{{ $t('oa.shiftManage') }}</h1>
      <button @click="showCreateDialog = true" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
        {{ $t('common.create') }}
      </button>
    </div>

    <!-- Shift List -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="shift in shifts" :key="shift.id"
           class="border rounded-lg p-4 hover:shadow-lg transition-shadow"
           :style="{ borderLeftWidth: '4px', borderLeftColor: shift.color }">
        <div class="flex justify-between items-start mb-2">
          <h3 class="text-lg font-semibold">{{ shift.name }}</h3>
          <span class="px-2 py-1 text-xs rounded"
                :class="shift.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
            {{ shift.status === 'active' ? $t('common.active') : $t('common.inactive') }}
          </span>
        </div>
        <div class="text-sm text-gray-600 space-y-1">
          <p>{{ $t('oa.shiftCode') }}: {{ shift.code }}</p>
          <p>{{ $t('oa.shiftTime') }}: {{ shift.start_time }} - {{ shift.end_time }}</p>
          <p>{{ $t('oa.duration') }}: {{ shift.duration }}h ({{ $t('oa.break') }}: {{ shift.break_duration }}h)</p>
          <p v-if="shift.description" class="text-gray-500">{{ shift.description }}</p>
        </div>
        <div class="mt-4 flex gap-2">
          <button @click="editShift(shift)" class="text-blue-600 hover:underline text-sm">
            {{ $t('common.edit') }}
          </button>
          <button @click="toggleShiftStatus(shift)" class="text-orange-600 hover:underline text-sm">
            {{ shift.status === 'active' ? $t('common.disable') : $t('common.enable') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <div v-if="showCreateDialog || editingShift" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">{{ editingShift ? $t('common.edit') : $t('common.create') }}{{ $t('oa.shift') }}</h2>
        <form @submit.prevent="saveShift" class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.shiftName') }}</label>
            <input v-model="form.name" required class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.shiftCode') }}</label>
            <input v-model="form.code" required :disabled="!!editingShift" class="w-full border rounded px-3 py-2" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">{{ $t('oa.startTime') }}</label>
              <input v-model="form.start_time" type="time" required class="w-full border rounded px-3 py-2" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">{{ $t('oa.endTime') }}</label>
              <input v-model="form.end_time" type="time" required class="w-full border rounded px-3 py-2" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">{{ $t('oa.duration') }} (h)</label>
              <input v-model.number="form.duration" type="number" step="0.5" required class="w-full border rounded px-3 py-2" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">{{ $t('oa.break') }} (h)</label>
              <input v-model.number="form.break_duration" type="number" step="0.5" class="w-full border rounded px-3 py-2" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.color') }}</label>
            <input v-model="form.color" type="color" class="w-full border rounded px-3 py-2 h-10" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('common.description') }}</label>
            <textarea v-model="form.description" rows="2" class="w-full border rounded px-3 py-2"></textarea>
          </div>
          <div class="flex gap-2 justify-end">
            <button type="button" @click="closeDialog" class="px-4 py-2 border rounded hover:bg-gray-50">
              {{ $t('common.cancel') }}
            </button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
              {{ $t('common.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'

const { t } = useI18n()
const shifts = ref([])
const showCreateDialog = ref(false)
const editingShift = ref(null)
const form = ref({
  name: '',
  code: '',
  start_time: '09:00',
  end_time: '18:00',
  duration: 8,
  break_duration: 1,
  color: '#3B82F6',
  description: ''
})

onMounted(() => {
  fetchShifts()
})

async function fetchShifts() {
  try {
    const res = await axios.get('/api/oa/shifts')
    if (res.data.code === 0) {
      shifts.value = res.data.data
    }
  } catch (err) {
    console.error('Failed to fetch shifts:', err)
  }
}

function editShift(shift) {
  editingShift.value = shift
  form.value = { ...shift }
}

async function saveShift() {
  try {
    if (editingShift.value) {
      await axios.put(`/api/oa/shifts/${editingShift.value.id}`, form.value)
    } else {
      await axios.post('/api/oa/shifts', form.value)
    }
    closeDialog()
    fetchShifts()
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save shift')
  }
}

async function toggleShiftStatus(shift) {
  try {
    const newStatus = shift.status === 'active' ? 'inactive' : 'active'
    await axios.put(`/api/oa/shifts/${shift.id}`, { status: newStatus })
    fetchShifts()
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to update status')
  }
}

function closeDialog() {
  showCreateDialog.value = false
  editingShift.value = null
  form.value = {
    name: '',
    code: '',
    start_time: '09:00',
    end_time: '18:00',
    duration: 8,
    break_duration: 1,
    color: '#3B82F6',
    description: ''
  }
}
</script>

<style scoped>
@media (max-width: 768px) {
  .p-6 {
    padding: 1rem;
  }

  .flex.justify-between.items-center.mb-6 {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .text-2xl {
    font-size: 1.25rem;
  }

  .grid {
    grid-template-columns: 1fr;
  }

  .fixed.inset-0.bg-black\/50 {
    padding: 0.5rem;
  }

  .bg-white.rounded-lg.p-6.max-w-md {
    width: 100%;
    max-width: 100%;
    margin: 0.5rem;
    padding: 1rem;
  }

  .grid.grid-cols-2.gap-4 {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }

  input,
  textarea {
    font-size: 16px;
  }

  button {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
  }
}
</style>
