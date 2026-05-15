<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.candidateName') }} <span class="text-red-500">*</span>
      </label>
      <input
        type="text"
        v-model="localData.name"
        :placeholder="$t('oa.candidateName')"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.appliedPosition') }} <span class="text-red-500">*</span>
      </label>
      <input
        type="text"
        v-model="localData.position"
        :placeholder="$t('oa.appliedPosition')"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('common.department') }} <span class="text-red-500">*</span>
      </label>
      <select
        v-model="localData.department"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      >
        <option value="">{{ $t('common.pleaseSelect') }}</option>
        <option v-for="dept in departments" :key="dept.id" :value="dept.name">{{ dept.name }}</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.jobLevel') }} <span class="text-red-500">*</span>
      </label>
      <select
        v-model="localData.job_level"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      >
        <option value="">{{ $t('common.pleaseSelect') }}</option>
        <option value="junior">{{ $t('oa.juniorLevel') }}</option>
        <option value="intermediate">{{ $t('oa.intermediateLevel') }}</option>
        <option value="senior">{{ $t('oa.seniorLevel') }}</option>
        <option value="expert">{{ $t('oa.expertLevel') }}</option>
        <option value="manager">{{ $t('oa.managerLevel') }}</option>
        <option value="director">{{ $t('oa.directorLevel') }}</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.hireDate') }} <span class="text-red-500">*</span>
      </label>
      <input
        type="date"
        v-model="localData.hire_date"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
        required
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ $t('oa.salary') }} <span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <span class="absolute left-3 top-2 text-gray-500">¥</span>
        <input
          type="number"
          v-model.number="localData.salary"
          min="0"
          step="100"
          placeholder="0"
          class="w-full border border-gray-300 rounded-lg pl-8 pr-3 py-2 focus:ring-2 focus:ring-primary focus:border-transparent"
          required
        />
      </div>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
      <p class="text-sm text-blue-800">
        <span class="material-symbols-outlined text-sm align-middle">info</span>
        {{ $t('oa.resumeRequired') }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch, ref, onMounted } from 'vue'
import api from '../../../services/api'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:modelValue'])

const departments = ref([])

const localData = reactive({
  name: props.modelValue.name || '',
  position: props.modelValue.position || '',
  department: props.modelValue.department || '',
  job_level: props.modelValue.job_level || '',
  hire_date: props.modelValue.hire_date || '',
  salary: props.modelValue.salary || ''
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

watch(localData, (newVal) => {
  emit('update:modelValue', { ...newVal })
}, { deep: true })
</script>
