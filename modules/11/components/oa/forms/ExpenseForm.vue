<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.expenseType') }} <span class="text-red-500">*</span>
      </label>
      <select
        v-model="localData.expense_type"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      >
        <option value="">{{ $t('common.pleaseSelect') }}</option>
        <option value="travel">{{ $t('oa.travelExpense') }}</option>
        <option value="meal">{{ $t('oa.mealExpense') }}</option>
        <option value="transport">{{ $t('oa.transportExpense') }}</option>
        <option value="office">{{ $t('oa.officeSupplies') }}</option>
        <option value="other">{{ $t('oa.otherExpense') }}</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.expenseAmount') }} <span class="text-red-500">*</span>
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
        {{ $t('oa.expenseDate') }} <span class="text-red-500">*</span>
      </label>
      <input
        type="date"
        v-model="localData.expense_date"
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

    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
      <p class="text-sm text-yellow-800">
        <span class="material-symbols-outlined text-sm align-middle">info</span>
        {{ $t('oa.invoiceRequired') }}
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
  expense_type: props.modelValue.expense_type || '',
  amount: props.modelValue.amount || '',
  expense_date: props.modelValue.expense_date || '',
  reason: props.modelValue.reason || ''
})

watch(localData, (newVal) => {
  emit('update:modelValue', { ...newVal })
}, { deep: true })
</script>
