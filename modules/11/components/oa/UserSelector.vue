<template>
  <div>
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
      {{ label }} <span v-if="required" class="text-red-500">*</span>
    </label>
    <select
      :value="modelValue"
      @change="$emit('update:modelValue', $event.target.value)"
      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
      :required="required"
    >
      <option value="">{{ placeholder || $t('userSelector.selectEmployee') }}</option>
      <option v-for="user in users" :key="user.id" :value="user.id">
        {{ user.name }} - {{ user.department || $t('userSelector.noDepartment') }}
      </option>
    </select>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api'

const { t } = useI18n()

defineProps({
  modelValue: [String, Number],
  label: String,
  placeholder: {
    type: String,
    default: ''
  },
  required: Boolean
})

defineEmits(['update:modelValue'])

const users = ref([])

onMounted(async () => {
  try {
    const res = await api.get('/oa/employees')
    if (res.code === 0) {
      users.value = res.data
    }
  } catch (err) {
    console.error('Failed to load employees:', err)
  }
})
</script>
