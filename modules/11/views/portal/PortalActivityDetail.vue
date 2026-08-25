<template>
  <div class="min-h-screen bg-gray-50">
    <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white py-12">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-sm text-amber-100 mb-2">
          <router-link to="/portal" class="hover:text-white">{{ $t('portal.nav.home') }}</router-link>
          <span class="mx-2">/</span>
          <router-link to="/portal/activities" class="hover:text-white">{{ $t('portal.nav.activities') }}</router-link>
        </div>
        <h1 class="text-3xl font-bold">{{ item.title }}</h1>
        <div class="flex flex-wrap items-center gap-4 mt-4 text-sm text-amber-100">
          <span>📅 {{ formatDate(item.start_time) }} - {{ formatDate(item.end_time) }}</span>
          <span>📍 {{ item.location }}</span>
        </div>
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- 左：详情 -->
        <div class="md:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-8">
          <div v-if="item.cover_image" class="mb-6">
            <img :src="item.cover_image" class="w-full rounded-lg" :alt="item.title" />
          </div>
          <p class="text-gray-700 leading-relaxed whitespace-pre-wrap">{{ item.description }}</p>

          <!-- 活动详情 -->
          <div class="mt-8 pt-6 border-t border-gray-100 space-y-3 text-sm">
            <div class="flex items-start gap-3">
              <span class="text-amber-600">⏰</span>
              <div>
                <div class="text-gray-500">活動時間</div>
                <div>{{ formatDate(item.start_time) }} {{ formatTime(item.start_time) }} - {{ formatTime(item.end_time) }}</div>
              </div>
            </div>
            <div class="flex items-start gap-3">
              <span class="text-amber-600">📍</span>
              <div>
                <div class="text-gray-500">活動地點</div>
                <div>{{ item.location }}</div>
              </div>
            </div>
            <div v-if="item.organizer" class="flex items-start gap-3">
              <span class="text-amber-600">🏛</span>
              <div>
                <div class="text-gray-500">主办方</div>
                <div>{{ item.organizer }}</div>
              </div>
            </div>
            <div v-if="item.contact_person" class="flex items-start gap-3">
              <span class="text-amber-600">📞</span>
              <div>
                <div class="text-gray-500">聯絡人</div>
                <div>{{ item.contact_person }} {{ item.contact_phone }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- 右：报名 -->
        <div>
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sticky top-20">
            <div class="text-center mb-4">
              <div class="text-3xl font-bold" :class="item.fee > 0 ? 'text-amber-600' : 'text-green-600'">
                {{ item.fee > 0 ? '¥ ' + item.fee : '免費' }}
              </div>
              <div class="text-sm text-gray-500 mt-1">{{ item.fee > 0 ? '元/人' : '免費活動' }}</div>
            </div>

            <div class="space-y-3 text-sm mb-6">
              <div class="flex justify-between">
                <span class="text-gray-500">名额</span>
                <span class="font-medium">{{ item.max_participants || '不限' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">已报名</span>
                <span class="font-medium text-amber-600">{{ item.current_participants || 0 }} 人</span>
              </div>
              <div v-if="item.registration_deadline" class="flex justify-between">
                <span class="text-gray-500">报名截止</span>
                <span class="font-medium">{{ formatDate(item.registration_deadline) }}</span>
              </div>
            </div>

            <button
              @click="registerOpen = true"
              :disabled="!canRegister"
              class="w-full py-3 rounded-lg font-medium transition"
              :class="canRegister ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-gray-200 text-gray-500 cursor-not-allowed'"
            >
              {{ registerBtnText }}
            </button>
            <p v-if="!canRegister" class="text-xs text-gray-400 mt-2 text-center">{{ reasonText }}</p>
          </div>
        </div>
      </div>

      <div class="mt-8 text-center">
        <button @click="$router.push('/portal/activities')" class="px-6 py-2 border border-gray-200 rounded-lg hover:bg-gray-50">
          ← {{ $t('portal.detail.back') }}
        </button>
      </div>
    </div>

    <PortalRegisterForm
      v-if="item.id"
      v-model="registerOpen"
      :activity="item"
      @success="onRegistered"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import PortalRegisterForm from './PortalRegisterForm.vue'

const route = useRoute()
const item = ref({})
const registerOpen = ref(false)

async function load() {
  const { api } = await import('@/services/api.js')
  const r = await api.get(`/association/activities/${route.params.id}`)
  if (r.code === 0) item.value = r.data || {}
}

function formatDate(t) { return t ? new Date(t).toLocaleDateString() : '-' }
function formatTime(t) { return t ? new Date(t).toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', hour12: false }) : '' }

const canRegister = computed(() => {
  if (item.value.status !== 'open') return false
  if (item.value.registration_deadline && new Date(item.value.registration_deadline) < new Date()) return false
  if (item.value.max_participants > 0 && item.value.current_participants >= item.value.max_participants) return false
  return true
})

const registerBtnText = computed(() => {
  if (item.value.status !== 'open') return '未开放'
  if (item.value.registration_deadline && new Date(item.value.registration_deadline) < new Date()) return '已截止'
  if (item.value.max_participants > 0 && item.value.current_participants >= item.value.max_participants) return '已满员'
  return '立即报名'
})

const reasonText = computed(() => {
  if (item.value.status !== 'open') return '該活動未開放報名'
  if (item.value.registration_deadline && new Date(item.value.registration_deadline) < new Date()) return '报名已截止'
  if (item.value.max_participants > 0 && item.value.current_participants >= item.value.max_participants) return '名额已满'
  return ''
})

async function onRegistered() {
  await load()
}

onMounted(load)
</script>