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
        <p v-if="responsibility.description" class="text-gray-600 mb-4">{{ responsibility.description }}</p>
        <ul v-if="responsibility.details && responsibility.details.length" class="space-y-3">
          <li
            v-for="(item, idx) in responsibility.details"
            :key="idx"
            class="flex items-start gap-3 text-gray-700"
          >
            <span class="material-symbols-outlined text-primary text-base mt-0.5">check_circle</span>
            <span>{{ item }}</span>
          </li>
        </ul>
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
    // 按用户角色（role）从 role_responsibilities 表获取权责
    const role = user.role || 'member'
    const respRes = await api.get(`/responsibilities/${role}`)
    if (respRes && respRes.code === 0 && respRes.data) {
      const data = respRes.data
      // 解析 responsibilities JSON 字段（数组），显示为列表
      let details = []
      if (typeof data.responsibilities === 'string') {
        try { details = JSON.parse(data.responsibilities) } catch (e) { details = [data.responsibilities] }
      } else if (Array.isArray(data.responsibilities)) {
        details = data.responsibilities
      }
      responsibility.value = {
        title: data.title,
        description: data.description || '',
        details
      }
    }
  } catch (e) {
    console.error('加载权责失败:', e)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
@media (max-width: 768px) {
  .p-6 {
    padding: 1rem;
  }
  .max-w-2xl {
    max-width: 100%;
  }
  .mb-6 {
    margin-bottom: 1rem;
  }
  .space-y-6 > :not(:last-child) {
    margin-bottom: 1rem;
  }
  .bg-white {
    padding: 1rem;
  }
  .text-2xl {
    font-size: 1.25rem;
  }
  .text-xl {
    font-size: 1.1rem;
  }
}
</style>
