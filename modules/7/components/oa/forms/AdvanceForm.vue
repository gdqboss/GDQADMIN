<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.advanceAmount') }} <span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <span class="absolute left-3 top-2 text-gray-500">¥</span>
        <input
          type="number"
          v-model.number="localData.amount"
          min="0"
          step="0.01"
          placeholder="0.00"
          class="w-full border border-gray-300 rounded-lg pl-8 pr-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
          required
        />
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.expectedDate') }} <span class="text-red-500">*</span>
      </label>
      <input
        type="date"
        v-model="localData.expected_date"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      />
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

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.repaymentPlan') }} <span class="text-red-500">*</span>
      </label>
      <textarea
        v-model="localData.repayment_plan"
        rows="3"
        :placeholder="$t('oa.repaymentPlan')"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      ></textarea>
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
  amount: props.modelValue.amount || '',
  expected_date: props.modelValue.expected_date || '',
  reason: props.modelValue.reason || '',
  repayment_plan: props.modelValue.repayment_plan || ''
})

watch(localData, (newVal) => {
  emit('update:modelValue', { ...newVal })
}, { deep: true })
</script>
