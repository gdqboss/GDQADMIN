<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.resignType') }} <span class="text-red-500">*</span>
      </label>
      <select
        v-model="localData.resign_type"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      >
        <option value="">{{ $t('common.pleaseSelect') }}</option>
        <option value="voluntary">{{ $t('oa.voluntaryResign') }}</option>
        <option value="involuntary">{{ $t('oa.involuntaryResign') }}</option>
        <option value="mutual">{{ $t('oa.mutualResign') }}</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.resignDate') }} <span class="text-red-500">*</span>
      </label>
      <input
        type="date"
        v-model="localData.resign_date"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.resignReason') }} <span class="text-red-500">*</span>
      </label>
      <textarea
        v-model="localData.reason"
        rows="4"
        :placeholder="$t('oa.resignReason')"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      ></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.handoverTo') }} <span class="text-red-500">*</span>
      </label>
      <UserSelector v-model="localData.handover_to" required />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.handoverNotes') }}
      </label>
      <textarea
        v-model="localData.handover_notes"
        rows="3"
        :placeholder="$t('oa.handoverNotes')"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
      ></textarea>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch } from 'vue'
import UserSelector from '../UserSelector.vue'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:modelValue'])

const localData = reactive({
  resign_type: props.modelValue.resign_type || '',
  resign_date: props.modelValue.resign_date || '',
  reason: props.modelValue.reason || '',
  handover_to: props.modelValue.handover_to || '',
  handover_notes: props.modelValue.handover_notes || ''
})

watch(localData, (newVal) => {
  emit('update:modelValue', { ...newVal })
}, { deep: true })
</script>
