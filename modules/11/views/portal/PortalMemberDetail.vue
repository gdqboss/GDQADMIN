<template>
  <div class="min-h-screen bg-gray-50">
    <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white py-12">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-sm text-amber-100 mb-2">
          <router-link to="/portal" class="hover:text-white">{{ $t('portal.nav.home') }}</router-link>
          <span class="mx-2">/</span>
          <router-link to="/portal/members" class="hover:text-white">{{ $t('portal.nav.members') }}</router-link>
        </div>
        <h1 class="text-3xl font-bold">{{ member.name }}</h1>
        <p v-if="member.title" class="text-amber-100 mt-2">{{ member.title }}</p>
      </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <div class="flex flex-col sm:flex-row gap-6 mb-6">
          <div class="w-32 h-32 rounded-xl overflow-hidden flex-shrink-0 bg-gray-100">
            <img v-if="member.avatar" :src="member.avatar" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center text-5xl text-gray-300">👤</div>
          </div>
          <div class="flex-1">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">{{ member.name }}</h2>
            <div class="space-y-1 text-sm text-gray-600">
              <div v-if="member.title">💼 {{ member.title }}</div>
              <div v-if="member.company">🏢 {{ member.company }}</div>
              <div v-if="member.industry">🏷 {{ member.industry }}</div>
              <div v-if="member.card_level">⭐ {{ member.card_level }}</div>
            </div>
          </div>
        </div>

        <div v-if="member.bio" class="mt-6 pt-6 border-t border-gray-100">
          <h3 class="font-bold text-gray-800 mb-3">個人簡介</h3>
          <p class="text-gray-700 leading-relaxed whitespace-pre-wrap">{{ member.bio }}</p>
        </div>

        <div v-if="member.interests" class="mt-6">
          <h3 class="font-bold text-gray-800 mb-3">兴趣爱好</h3>
          <p class="text-gray-700">{{ member.interests }}</p>
        </div>

        <div v-if="member.phone || member.email || member.wechat" class="mt-6 pt-6 border-t border-gray-100">
          <h3 class="font-bold text-gray-800 mb-3">{{ $t('portal.member.contact') }}</h3>
          <div class="space-y-2 text-sm">
            <div v-if="member.phone">📞 {{ member.phone }}</div>
            <div v-if="member.email">✉ {{ member.email }}</div>
            <div v-if="member.wechat">💬 WeChat: {{ member.wechat }}</div>
          </div>
        </div>
      </div>

      <div class="mt-8 text-center">
        <button @click="$router.push('/portal/members')" class="px-6 py-2 border border-gray-200 rounded-lg hover:bg-gray-50">
          ← {{ $t('portal.detail.back') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const member = ref({})

onMounted(async () => {
  try {
    const { api } = await import('@/services/api.js')
    const r = await api.get(`/association/cards/${route.params.id}`)
    if (r.code === 0) member.value = r.data || {}
  } catch (e) {}
})
</script>