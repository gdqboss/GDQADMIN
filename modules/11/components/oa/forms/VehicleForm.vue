<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.useDate') }} <span class="text-red-500">*</span>
      </label>
      <input
        type="date"
        v-model="localData.use_date"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      />
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          {{ $t('oa.startTime') }} <span class="text-red-500">*</span>
        </label>
        <input
          type="time"
          v-model="localData.start_time"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
          required
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          {{ $t('oa.endTime') }} <span class="text-red-500">*</span>
        </label>
        <input
          type="time"
          v-model="localData.end_time"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
          required
        />
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.destination') }} <span class="text-red-500">*</span>
      </label>
      <input
        type="text"
        v-model="localData.destination"
        :placeholder="$t('oa.destination')"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.passengerCount') }} <span class="text-red-500">*</span>
      </label>
      <input
        type="number"
        v-model.number="localData.passenger_count"
        min="1"
        :placeholder="$t('oa.passengerCount')"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.needDriver') }} <span class="text-red-500">*</span>
      </label>
      <div class="flex gap-4">
        <label class="flex items-center">
          <input type="radio" v-model="localData.need_driver" :value="true" class="mr-2" />
          {{ $t('common.yes') }}
        </label>
        <label class="flex items-center">
          <input type="radio" v-model="localData.need_driver" :value="false" class="mr-2" />
          {{ $t('common.no') }}
        </label>
      </div>
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
  use_date: props.modelValue.use_date || '',
  start_time: props.modelValue.start_time || '',
  end_time: props.modelValue.end_time || '',
  destination: props.modelValue.destination || '',
  passenger_count: props.modelValue.passenger_count || 1,
  need_driver: props.modelValue.need_driver ?? true,
  reason: props.modelValue.reason || ''
})

watch(localData, (newVal) => {
  emit('update:modelValue', { ...newVal })
}, { deep: true })
</script>
