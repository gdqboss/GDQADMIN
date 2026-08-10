<template>
  <div class="min-h-screen bg-gray-50">
    <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white py-16">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl md:text-4xl font-bold mb-3">{{ info.name_zh_tw || info.name_zh || '學會' }}</h1>
        <p v-if="info.slogan_zh_tw || info.slogan" class="text-amber-100">{{ info.slogan_zh_tw || info.slogan }}</p>
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <article v-if="loaded" class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 space-y-8">
        <!-- 歷史 -->
        <section v-if="info.history">
          <h2 class="text-xl font-bold text-gray-800 mb-3 border-l-4 border-amber-500 pl-3">📜 學會歷史</h2>
          <p class="text-gray-700 leading-relaxed whitespace-pre-wrap">{{ info.history }}</p>
        </section>

        <!-- 介紹 -->
        <section v-if="info.intro">
          <h2 class="text-xl font-bold text-gray-800 mb-3 border-l-4 border-amber-500 pl-3">📖 學會介紹</h2>
          <p class="text-gray-700 leading-relaxed whitespace-pre-wrap">{{ info.intro }}</p>
        </section>

        <!-- 願景 -->
        <section v-if="info.vision">
          <h2 class="text-xl font-bold text-gray-800 mb-3 border-l-4 border-amber-500 pl-3">🎯 願景使命</h2>
          <p class="text-gray-700 leading-relaxed whitespace-pre-wrap">{{ info.vision }}</p>
        </section>

        <!-- 基本資訊 -->
        <section>
          <h2 class="text-xl font-bold text-gray-800 mb-3 border-l-4 border-amber-500 pl-3">📞 聯絡方式</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-if="info.address" class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
              <span class="text-amber-600">📍</span>
              <div>
                <div class="text-sm text-gray-500">地址</div>
                <div class="text-gray-800">{{ info.address }}</div>
              </div>
            </div>
            <div v-if="info.phone" class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
              <span class="text-amber-600">📞</span>
              <div>
                <div class="text-sm text-gray-500">電話</div>
                <div class="text-gray-800">{{ info.phone }}</div>
              </div>
            </div>
            <div v-if="info.email" class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
              <span class="text-amber-600">✉</span>
              <div>
                <div class="text-sm text-gray-500">電郵</div>
                <div class="text-gray-800">{{ info.email }}</div>
              </div>
            </div>
            <div v-if="info.website" class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
              <span class="text-amber-600">🌐</span>
              <div>
                <div class="text-sm text-gray-500">網址</div>
                <a :href="info.website" target="_blank" class="text-amber-600 hover:underline">{{ info.website }}</a>
              </div>
            </div>
            <div v-if="info.founded_year" class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
              <span class="text-amber-600">📅</span>
              <div>
                <div class="text-sm text-gray-500">成立年份</div>
                <div class="text-gray-800">{{ info.founded_year }}</div>
              </div>
            </div>
            <div v-if="info.member_count" class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
              <span class="text-amber-600">👥</span>
              <div>
                <div class="text-sm text-gray-500">會員數</div>
                <div class="text-gray-800">{{ info.member_count }} 名</div>
              </div>
            </div>
          </div>
        </section>
      </article>

      <div v-else-if="loaded" class="text-center py-20 text-gray-400 bg-white rounded-xl">
        暫無學會介紹
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
const info = ref({})
const loaded = ref(false)

onMounted(async () => {
  try {
    const { api } = await import('@/services/api.js')
    const r = await api.get('/association/info')
    if (r.code === 0) info.value = r.data || {}
  } catch (e) {} finally { loaded.value = true }
})
</script>