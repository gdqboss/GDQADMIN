<template>
  <div>
    <!-- Hero 轮播图 (UiBanner 通用组件, 从 /api/banners?position=portal_hero 读取) -->
    <section class="relative bg-slate-900">
      <UiBanner
        position="portal_hero"
        height-class="h-72 md:h-[28rem]"
        :rounded="false"
        :interval="5000"
        placeholder-text="学会官网轮播图位"
        placeholder-class="bg-gradient-to-br from-slate-800 via-slate-900 to-black text-amber-300"
        :manageable="true"
      />
      <!-- 漸層蒙版,讓標題浮在上面更清晰 -->
      <div class="absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-black/40 to-transparent pointer-events-none"></div>
    </section>

    <!-- Hero 标题浮在轮播下方 (替代原本的纯渐层 Hero) -->
    <section class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white relative overflow-hidden">
      <div class="absolute inset-0 opacity-10 pointer-events-none">
        <div class="absolute -top-1/2 -left-1/4 w-96 h-96 bg-amber-500 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-1/2 -right-1/4 w-96 h-96 bg-amber-600 rounded-full blur-3xl"></div>
      </div>
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16 relative">
        <div class="text-center max-w-3xl mx-auto">
          <h1 class="text-3xl md:text-5xl font-bold mb-4 leading-tight">
            {{ $t('portal.hero.slogan') }}
          </h1>
          <p class="text-base md:text-lg text-gray-300 mb-6">
            {{ $t('portal.hero.subtitle') }}
          </p>
          <div class="flex flex-wrap gap-3 justify-center">
            <a href="#contact" class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 rounded-lg font-medium transition">
              {{ $t('portal.hero.cta_join') }}
            </a>
            <router-link to="/portal/about" class="px-6 py-2.5 bg-white/10 hover:bg-white/20 backdrop-blur rounded-lg font-medium transition">
              {{ $t('portal.hero.cta_learn') }}
            </router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- 關於學會 -->
    <section v-if="info" class="py-16 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-center text-gray-800 mb-10">{{ $t('portal.sections.about') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="bg-amber-50 rounded-xl p-6">
            <div class="text-amber-600 text-3xl mb-3">📜</div>
            <h3 class="font-bold text-gray-800 mb-2">{{ $t('association.bio_short') }}</h3>
            <p class="text-sm text-gray-600 line-clamp-4">{{ info.intro || '-' }}</p>
          </div>
          <div class="bg-amber-50 rounded-xl p-6">
            <div class="text-amber-600 text-3xl mb-3">🎯</div>
            <h3 class="font-bold text-gray-800 mb-2">{{ $t('association.vision') }}</h3>
            <p class="text-sm text-gray-600 line-clamp-4">{{ info.vision || '-' }}</p>
          </div>
          <div class="bg-amber-50 rounded-xl p-6">
            <div class="text-amber-600 text-3xl mb-3">📅</div>
            <h3 class="font-bold text-gray-800 mb-2">{{ $t('association.founded_year') }}</h3>
            <p class="text-sm text-gray-600">
              <span v-if="info.founded_year">{{ info.founded_year }} 年成立</span>
              <span v-if="info.member_count"> · {{ info.member_count }} 名會員</span>
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- 9 個模組入口卡片 (漸層 icon + hover 抬升) -->
    <section class="py-16 bg-gray-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
          <h2 class="text-3xl font-bold text-gray-800">探索學會</h2>
          <p class="text-gray-500 mt-2">9 個核心板塊,全方位服務會員</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
          <router-link
            v-for="item in navCards"
            :key="item.path"
            :to="`/portal${item.path}`"
            class="bg-white rounded-xl p-6 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group relative overflow-hidden"
          >
            <!-- 背景渐层(hover 时显示) -->
            <div class="absolute inset-0 bg-gradient-to-br opacity-0 group-hover:opacity-10 transition-opacity"
                 :class="item.gradient"></div>
            <div class="relative">
              <!-- icon 容器,带渐层背景 -->
              <div class="w-14 h-14 rounded-xl flex items-center justify-center text-3xl mb-3 transition-transform group-hover:scale-110"
                   :class="item.bgGradient">
                {{ item.icon }}
              </div>
              <div class="font-bold text-gray-800 group-hover:text-amber-600 transition">{{ $t(`portal.nav.${item.key}`) }}</div>
              <div class="text-xs text-gray-400 mt-1 line-clamp-2">{{ item.desc }}</div>
            </div>
          </router-link>
        </div>
      </div>
    </section>

    <!-- 最新公告 (含封面图) -->
    <section v-if="latestNews.length" class="py-16 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
          <h2 class="text-2xl font-bold text-gray-800">{{ $t('portal.sections.latest_news') }}</h2>
          <router-link to="/portal/news" class="text-amber-600 hover:underline text-sm">查看更多 →</router-link>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <article v-for="n in latestNews" :key="n.id" class="bg-white border border-gray-100 rounded-xl overflow-hidden hover:shadow-lg transition cursor-pointer group" @click="$router.push(`/portal/news/${n.id}`)">
            <!-- 封面图(无图时显示渐层占位) -->
            <div class="aspect-[16/9] overflow-hidden bg-gradient-to-br" :class="n.cover_image ? '' : 'from-slate-100 to-slate-200'">
              <img v-if="n.cover_image" :src="n.cover_image" :alt="n.title" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" loading="lazy" />
              <div v-else class="w-full h-full flex items-center justify-center">
                <span class="text-5xl opacity-30">📰</span>
              </div>
            </div>
            <div class="p-5">
              <div class="text-xs text-gray-400 mb-2">📅 {{ formatDate(n.published_at) }}</div>
              <h3 class="font-bold text-gray-800 mb-2 line-clamp-2 group-hover:text-amber-600 transition">{{ n.title }}</h3>
              <p class="text-sm text-gray-500 line-clamp-2">{{ n.summary }}</p>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- 近期活動 (含封面圖) -->
    <section v-if="upcomingActivities.length" class="py-16 bg-gray-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
          <h2 class="text-2xl font-bold text-gray-800">{{ $t('portal.sections.upcoming_activities') }}</h2>
          <router-link to="/portal/activities" class="text-amber-600 hover:underline text-sm">查看更多 →</router-link>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <article v-for="a in upcomingActivities" :key="a.id" class="bg-white rounded-xl overflow-hidden hover:shadow-lg transition cursor-pointer border border-gray-100 group" @click="$router.push(`/portal/activities/${a.id}`)">
            <!-- 封面图(无图时显示渐层占位) -->
            <div class="aspect-[16/9] overflow-hidden bg-gradient-to-br" :class="a.cover_image ? '' : 'from-amber-50 to-amber-100'">
              <img v-if="a.cover_image" :src="a.cover_image" :alt="a.title" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" loading="lazy" />
              <div v-else class="w-full h-full flex items-center justify-center">
                <span class="text-5xl opacity-30">🎪</span>
              </div>
            </div>
            <div class="p-5">
              <div class="text-xs text-amber-600 font-medium mb-2">📅 {{ formatDate(a.start_time) }}</div>
              <h3 class="font-bold text-gray-800 mb-2 line-clamp-2 group-hover:text-amber-600 transition">{{ a.title }}</h3>
              <p class="text-sm text-gray-500 mb-3">📍 {{ a.location }}</p>
              <div class="flex justify-between items-center text-xs text-gray-500">
                <span>👥 {{ a.current_participants }} / {{ a.max_participants || '∞' }}</span>
                <span class="text-amber-600 font-medium">{{ a.fee > 0 ? a.fee + ' 元' : '免費' }}</span>
              </div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- 學術前沿 -->
    <section v-if="latestAcademic.length" class="py-16 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
          <h2 class="text-2xl font-bold text-gray-800">{{ $t('portal.sections.latest_academic') }}</h2>
          <router-link to="/portal/academic" class="text-amber-600 hover:underline text-sm">查看更多 →</router-link>
        </div>
        <div class="space-y-4">
          <article v-for="a in latestAcademic" :key="a.id" class="flex items-start gap-4 p-4 hover:bg-gray-50 rounded-lg cursor-pointer border border-gray-100" @click="$router.push(`/portal/academic/${a.id}`)">
            <div class="flex-1">
              <h3 class="font-bold text-gray-800 mb-1">{{ a.title }}</h3>
              <p class="text-sm text-gray-500">
                <span v-if="a.author_name">{{ a.author_name }}</span>
                <span v-if="a.journal_name"> · {{ a.journal_name }}</span>
                <span v-if="a.doi"> · DOI: {{ a.doi }}</span>
              </p>
            </div>
            <span class="text-xs text-gray-400 whitespace-nowrap">{{ formatDate(a.published_at) }}</span>
          </article>
        </div>
      </div>
    </section>

    <!-- 期刊精选 -->
    <section v-if="featuredJournals.length" class="py-16 bg-gray-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
          <h2 class="text-2xl font-bold text-gray-800">{{ $t('portal.sections.featured_journals') }}</h2>
          <router-link to="/portal/journals" class="text-amber-600 hover:underline text-sm">查看更多 →</router-link>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
          <article v-for="j in featuredJournals" :key="j.id" class="bg-white rounded-xl overflow-hidden hover:shadow-md transition cursor-pointer border border-gray-100" @click="$router.push(`/portal/journals/${j.id}`)">
            <div class="aspect-[3/4] bg-gray-100">
              <img v-if="j.cover_image" :src="j.cover_image" class="w-full h-full object-cover" />
              <div v-else class="w-full h-full flex items-center justify-center text-gray-400">📄</div>
            </div>
            <div class="p-4">
              <div class="text-xs text-amber-600 mb-1">Vol.{{ j.volume }} No.{{ j.issue }}</div>
              <h3 class="font-bold text-gray-800 text-sm line-clamp-2">{{ j.title }}</h3>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- 聯絡諮詢 -->
    <section id="contact" class="py-16 bg-gradient-to-br from-slate-900 to-slate-800 text-white">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold mb-6">{{ $t('portal.contact.title') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
          <div v-if="info.phone" class="bg-white/10 backdrop-blur rounded-xl p-5">
            <div class="text-amber-400 text-2xl mb-2">📞</div>
            <div class="text-sm text-gray-300 mb-1">{{ $t('portal.footer.phone') }}</div>
            <div class="font-medium">{{ info.phone }}</div>
          </div>
          <div v-if="info.email" class="bg-white/10 backdrop-blur rounded-xl p-5">
            <div class="text-amber-400 text-2xl mb-2">✉</div>
            <div class="text-sm text-gray-300 mb-1">{{ $t('portal.footer.email') }}</div>
            <div class="font-medium">{{ info.email }}</div>
          </div>
          <div v-if="info.address" class="bg-white/10 backdrop-blur rounded-xl p-5">
            <div class="text-amber-400 text-2xl mb-2">📍</div>
            <div class="text-sm text-gray-300 mb-1">{{ $t('portal.footer.address') }}</div>
            <div class="font-medium">{{ info.address }}</div>
          </div>
        </div>
        <router-link to="/portal/contact" class="inline-block px-8 py-3 bg-amber-500 hover:bg-amber-600 rounded-lg font-medium">
          {{ $t('portal.contact.submit') }} →
        </router-link>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import UiBanner from '@/components/UiBanner.vue'

const info = ref({})
const latestNews = ref([])
const upcomingActivities = ref([])
const latestAcademic = ref([])
const featuredJournals = ref([])

const navCards = [
  { path: '/about', key: 'about', icon: '📖', desc: '了解學會簡介與歷史', bgGradient: 'bg-gradient-to-br from-amber-100 to-amber-200', gradient: 'from-amber-400 to-amber-600' },
  { path: '/news', key: 'news', icon: '📢', desc: '最新公告與動態', bgGradient: 'bg-gradient-to-br from-blue-100 to-blue-200', gradient: 'from-blue-400 to-blue-600' },
  { path: '/academic', key: 'academic', icon: '🔬', desc: '學術前沿與論文', bgGradient: 'bg-gradient-to-br from-purple-100 to-purple-200', gradient: 'from-purple-400 to-purple-600' },
  { path: '/activities', key: 'activities', icon: '🎪', desc: '活動報名與回顧', bgGradient: 'bg-gradient-to-br from-pink-100 to-pink-200', gradient: 'from-pink-400 to-pink-600' },
  { path: '/journals', key: 'journals', icon: '📚', desc: '期刊雜誌訂閱', bgGradient: 'bg-gradient-to-br from-indigo-100 to-indigo-200', gradient: 'from-indigo-400 to-indigo-600' },
  { path: '/members', key: 'members', icon: '👥', desc: '會員名錄與名片', bgGradient: 'bg-gradient-to-br from-green-100 to-green-200', gradient: 'from-green-400 to-green-600' },
  { path: '/downloads', key: 'downloads', icon: '📥', desc: '資料下載中心', bgGradient: 'bg-gradient-to-br from-orange-100 to-orange-200', gradient: 'from-orange-400 to-orange-600' },
  { path: '/org', key: 'org', icon: '🏛', desc: '組織架構與成員', bgGradient: 'bg-gradient-to-br from-teal-100 to-teal-200', gradient: 'from-teal-400 to-teal-600' },
  { path: '/contact', key: 'contact', icon: '✉', desc: '聯絡我們', bgGradient: 'bg-gradient-to-br from-rose-100 to-rose-200', gradient: 'from-rose-400 to-rose-600' },
]

function formatDate(t) {
  if (!t) return ''
  return new Date(t).toLocaleDateString()
}

onMounted(async () => {
  const { api } = await import('@/services/api.js')

  try {
    const r = await api.get('/association/info', { params: { server_profile_id: 7 } })
    if (r.code === 0) info.value = r.data || {}
  } catch (e) {}

  try {
    const r = await api.get('/association/announcements', { params: { server_profile_id: 7, size: 3, status: 'published' } })
    if (r.code === 0) latestNews.value = (Array.isArray(r.data) ? r.data : (r.data.list || []))
  } catch (e) {}

  try {
    const r = await api.get('/association/activities', { params: { server_profile_id: 7, size: 3 } })
    if (r.code === 0) upcomingActivities.value = (Array.isArray(r.data) ? r.data : (r.data.list || []))
  } catch (e) {}

  try {
    const r = await api.get('/association/academic', { params: { size: 5 } })
    if (r.code === 0) latestAcademic.value = (Array.isArray(r.data) ? r.data : (r.data.list || []))
  } catch (e) {}

  try {
    const r = await api.get('/association/journals', { params: { size: 4 } })
    if (r.code === 0) featuredJournals.value = (Array.isArray(r.data) ? r.data : (r.data.list || []))
  } catch (e) {}
})
</script>