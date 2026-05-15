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
    // 直接调用后端API获取用户信息
    const userRes = await api.get('/auth/me')
    console.log('用户信息响应:', userRes)
    
    if (!userRes || userRes.code !== 0) {
      console.error('获取用户信息失败', userRes)
      loading.value = false
      return
    }
    
    const user = userRes.data
    console.log('用户:', user)
    
    // 获取权责列表
    const respRes = await api.get('/job-responsibilities')
    console.log('权责列表:', respRes)
    
    if (respRes && respRes.code === 0) {
      const list = respRes.data || []
      // 只用responsibility_id，默认显示公司职员(7)
      const userRespId = user.responsibility_id ? parseInt(user.responsibility_id) : 7
      responsibility.value = list.find(r => r.id === parseInt(userRespId)) || list.find(r => r.id === 7)
      console.log('匹配的权责:', responsibility.value)
    }
  } catch (e) {
    console.error('加载权责失败:', e)
  } finally {
    loading.value = false
  }
})
</script>
