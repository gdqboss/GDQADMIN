<template>
  <div class="space-y-2">
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          {{ startLabel || $t('oa.startDate') }} <span v-if="required" class="text-red-500">*</span>
        </label>
        <input
          :type="type"
          :value="modelValue.start"
          @input="updateStart"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
          :required="required"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          {{ endLabel || $t('oa.endDate') }} <span v-if="required" class="text-red-500">*</span>
        </label>
        <input
          :type="type"
          :value="modelValue.end"
          @input="updateEnd"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
          :required="required"
        />
      </div>
    </div>
    <div v-if="showDuration && modelValue.start && modelValue.end" class="text-sm text-gray-600">
      {{ $t('oa.days') }}: <span class="font-semibold">{{ calculateDuration() }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({ start: '', end: '' })
  },
  type: {
    type: String,
    default: 'datetime-local'
  },
  startLabel: {
    type: String,
    default: ''
  },
  endLabel: {
    type: String,
    default: ''
  },
  required: Boolean,
  showDuration: Boolean
})

const emit = defineEmits(['update:modelValue'])

function updateStart(e) {
  emit('update:modelValue', { ...props.modelValue, start: e.target.value })
}

function updateEnd(e) {
  emit('update:modelValue', { ...props.modelValue, end: e.target.value })
}

function calculateDuration() {
  if (!props.modelValue.start || !props.modelValue.end) return 0
  const start = new Date(props.modelValue.start)
  const end = new Date(props.modelValue.end)
  const diff = end - start
  return Math.ceil(diff / (1000 * 60 * 60 * 24))
}
</script>
