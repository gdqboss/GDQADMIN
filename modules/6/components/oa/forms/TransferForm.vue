<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.employeeName') }} <span class="text-red-500">*</span>
      </label>
      <UserSelector v-model="localData.employee_id" @update:modelValue="loadEmployeeInfo" required />
    </div>

    <div v-if="employeeInfo" class="bg-gray-50 border border-gray-200 rounded-lg p-3">
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div><span class="text-gray-600">{{ $t('oa.originalDepartment') }}:</span> {{ employeeInfo.department || '-' }}</div>
        <div><span class="text-gray-600">{{ $t('oa.originalPosition') }}:</span> {{ employeeInfo.position || '-' }}</div>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.newDepartment') }} <span class="text-red-500">*</span>
      </label>
      <select
        v-model="localData.new_department"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      >
        <option value="">{{ $t('common.pleaseSelect') }}</option>
        <option v-for="dept in departments" :key="dept.id" :value="dept.name">{{ dept.name }}</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.newPosition') }} <span class="text-red-500">*</span>
      </label>
      <input
        type="text"
        v-model="localData.new_position"
        :placeholder="$t('oa.newPosition')"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.transferDate') }} <span class="text-red-500">*</span>
      </label>
      <input
        type="date"
        v-model="localData.transfer_date"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.transferReason') }} <span class="text-red-500">*</span>
      </label>
      <textarea
        v-model="localData.reason"
        rows="4"
        :placeholder="$t('oa.transferReason')"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      ></textarea>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch, ref, onMounted } from 'vue'
import api from '../../../services/api'
import UserSelector from '../UserSelector.vue'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:modelValue'])

const departments = ref([])
const employeeInfo = ref(null)

const localData = reactive({
  employee_id: props.modelValue.employee_id || '',
  new_department: props.modelValue.new_department || '',
  new_position: props.modelValue.new_position || '',
  transfer_date: props.modelValue.transfer_date || '',
  reason: props.modelValue.reason || ''
})

onMounted(async () => {
  try {
    const res = await api.get('/oa/departments')
    if (res.code === 0) {
      departments.value = res.data
    }
  } catch (err) {
    console.error('Failed to load departments:', err)
  }
})

async function loadEmployeeInfo(userId) {
  if (!userId) {
    employeeInfo.value = null
    return
  }
  try {
    const res = await api.get(`/oa/employees/${userId}`)
    if (res.code === 0) {
      employeeInfo.value = res.data
    }
  } catch (err) {
    console.error('Failed to load employee info:', err)
  }
}

watch(localData, (newVal) => {
  emit('update:modelValue', { ...newVal })
}, { deep: true })
</script>
