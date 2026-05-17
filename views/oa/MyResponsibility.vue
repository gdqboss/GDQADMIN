<template>
  <div class="p-6 max-w-2xl mx-auto">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800">{{ $t('oa.myResponsibilities') }}</h1>
    </div>

    <div v-if="loading" class="text-center py-8">
      <span class="text-gray-500">{{ $t('common.loading') }}</span>
    </div>

    <div v-else-if="responsibility" class="space-y-6">
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-primary mb-4">{{ responsibility.title }}</h2>
        <p class="text-gray-600">{{ responsibility.description }}</p>
      </div>
    </div>

    <div v-else class="text-center py-8 text-gray-500">
      {{ $t('oa.noResponsibilityInfo') }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api.js'

const { t } = useI18n()
const loading = ref(true)
const responsibility = ref(null)

onMounted(async () => {
  try {
    const userRes = await api.get('/auth/me')
    if (!userRes || userRes.code !== 0) {
      loading.value = false
      return
    }
    const user = userRes.data
    // 从职级的 responsibility_desc 获取权责说明（job_level_id 实际对应 job_levels.level）
    if (user.job_level_id) {
      const levelsRes = await api.get('/users/job-levels/list')
      if (levelsRes && levelsRes.code === 0) {
        const level = (levelsRes.data || []).find(l => String(l.level) === String(user.job_level_id))
        if (level?.responsibility_desc) {
          responsibility.value = { title: level.name, description: level.responsibility_desc }
        }
      }
    }
  } catch (e) {
    console.error('加载权责失败:', e)
  } finally {
    loading.value = false
  }
})
</script>
