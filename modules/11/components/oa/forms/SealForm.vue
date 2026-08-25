<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.sealType') }} <span class="text-red-500">*</span>
      </label>
      <select
        v-model="localData.seal_type"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      >
        <option value="">{{ $t('common.pleaseSelect') }}</option>
        <option value="contract">{{ $t('oa.contractSeal') }}</option>
        <option value="official">{{ $t('oa.officialSeal') }}</option>
        <option value="financial">{{ $t('oa.financialSeal') }}</option>
        <option value="legal">{{ $t('oa.legalSeal') }}</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.copies') }} <span class="text-red-500">*</span>
      </label>
      <input
        type="number"
        v-model.number="localData.copies"
        min="1"
        :placeholder="$t('oa.copies')"
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
        {{ $t('common.description') }}
      </label>
      <textarea
        v-model="localData.document_description"
        rows="3"
        :placeholder="$t('common.description')"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
      ></textarea>
      <p class="text-xs text-gray-500 mt-1">{{ $t('oa.supportedFormats') }}</p>
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
  seal_type: props.modelValue.seal_type || '',
  copies: props.modelValue.copies || 1,
  reason: props.modelValue.reason || '',
  document_description: props.modelValue.document_description || ''
})

watch(localData, (newVal) => {
  emit('update:modelValue', { ...newVal })
}, { deep: true })
</script>
