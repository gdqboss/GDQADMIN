<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.leaveType') }} <span class="text-red-500">*</span>
      </label>
      <select
        v-model="localData.leave_type"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      >
        <option value="">{{ $t('common.pleaseSelect') }}</option>
        <option value="personal">{{ $t('oa.personalLeave') }}</option>
        <option value="sick">{{ $t('oa.sickLeave') }}</option>
        <option value="annual">{{ $t('oa.annualLeave') }}</option>
        <option value="compensatory">{{ $t('oa.compensatoryLeave') }}</option>
        <option value="marriage">{{ $t('oa.marriageLeave') }}</option>
        <option value="maternity">{{ $t('oa.maternityLeave') }}</option>
        <option value="paternity">{{ $t('oa.paternityLeave') }}</option>
      </select>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          {{ $t('oa.startDate') }} <span class="text-red-500">*</span>
        </label>
        <input
          type="datetime-local"
          v-model="localData.start_date"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
          required
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          {{ $t('oa.endDate') }} <span class="text-red-500">*</span>
        </label>
        <input
          type="datetime-local"
          v-model="localData.end_date"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
          required
        />
      </div>
    </div>

    <div v-if="localData.start_date && localData.end_date" class="bg-blue-50 border border-blue-200 rounded-lg p-3">
      <p class="text-sm text-blue-800">
        {{ $t('oa.leaveDays') }}: <span class="font-semibold">{{ calculateDays() }}</span> {{ $t('common.days') }}
      </p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.reason') }} <span class="text-red-500">*</span>
      </label>
      <textarea
        v-model="localData.reason"
        rows="4"
        :placeholder="$t('oa.reason')"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      ></textarea>
    </div>

    <div v-if="localData.leave_type === 'sick'" class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
      <p class="text-sm text-yellow-800">
        <span class="material-symbols-outlined text-sm align-middle">info</span>
        {{ $t('oa.medicalProofRequired') }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:modelValue'])

const localData = reactive({
  leave_type: props.modelValue.leave_type || '',
  start_date: props.modelValue.start_date || '',
  end_date: props.modelValue.end_date || '',
  reason: props.modelValue.reason || ''
})

watch(localData, (newVal) => {
  emit('update:modelValue', { ...newVal })
}, { deep: true })

function calculateDays() {
  if (!localData.start_date || !localData.end_date) return 0
  const start = new Date(localData.start_date)
  const end = new Date(localData.end_date)
  const diff = end - start
  return Math.ceil(diff / (1000 * 60 * 60 * 24))
}
</script>
