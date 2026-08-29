<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.tripDestination') }} <span class="text-red-500">*</span>
      </label>
      <input
        type="text"
        v-model="localData.destination"
        :placeholder="$t('oa.tripDestinationPlaceholder')"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      />
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

    <div v-if="localData.start_date && localData.end_date" class="bg-purple-50 border border-purple-200 rounded-lg p-3">
      <p class="text-sm text-purple-800">
        {{ $t('oa.tripDaysCount') }}: <span class="font-semibold">{{ calculateDays() }}</span> {{ $t('common.days') }}
      </p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.reason') }} <span class="text-red-500">*</span>
      </label>
      <textarea
        v-model="localData.reason"
        rows="3"
        :placeholder="$t('oa.reason')"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      ></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.tripBudget') }}
      </label>
      <input
        type="number"
        min="0"
        step="0.01"
        v-model="localData.budget"
        :placeholder="$t('oa.tripBudgetPlaceholder')"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
      />
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
  destination: props.modelValue.destination || '',
  start_date: props.modelValue.start_date || '',
  end_date: props.modelValue.end_date || '',
  reason: props.modelValue.reason || '',
  budget: props.modelValue.budget || ''
})

watch(localData, (newVal) => {
  emit('update:modelValue', { ...newVal })
}, { deep: true })

function calculateDays() {
  if (!localData.start_date || !localData.end_date) return 0
  const start = new Date(localData.start_date)
  const end = new Date(localData.end_date)
  const diff = end - start
  return Math.max(1, Math.ceil(diff / (1000 * 60 * 60 * 24)))
}
</script>
