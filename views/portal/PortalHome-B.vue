<!--
  设计读 (Section 0.B):
    协会官网 for 澳門中醫藥學會 + 国际访客, "传承权威 + 现代专业"语言
    套 B = 现代专业 / 深蓝白 / Geist sans / Material Symbols rounded

  Design tokens (locked):
    - 深蓝:    #1e3a5f (主色 / CTA / 链接)
    - 暖白:    #f8f5f0 (浅背景)
    - 薄荷绿:  #4a9d7c (辅助 / 数据)
    - 中灰:    #6b7280 (副文字)
    - 留白:    py-24 大段距
    - 圆角:    rounded-2xl (柔和现代)

  严格遵守:
    - ZERO em-dashes (用 · 或 -)
    - ONE accent color (深蓝)
    - 砍掉 9 张 emoji 卡片重复, 改用 4 张精选入口 + 数据看板
-->

<template>
  <div class="bg-warm text-deep text-[15px] leading-relaxed" style="font-family: 'Geist', 'Inter', system-ui, sans-serif;">
    <!-- 1. HERO 全宽 banner + 深蓝蒙版 + 浮层 -->
    <section class="relative">
      <UiBanner
        position="portal_hero"
        height-class="h-[28rem] md:h-[36rem]"
        :rounded="false"
        :interval="5000"
        placeholder-text="Professional TCM Association"
        placeholder-class="bg-gradient-to-br from-[#0e2238] via-deep to-[#2a4870] text-mint/40"
        :manageable="true"
      />
      <div class="absolute inset-0 bg-gradient-to-r from-deep/85 via-deep/50 to-transparent pointer-events-none"></div>
      <div class="absolute inset-x-0 bottom-0 pb-12 md:pb-20 pointer-events-none">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-warm">
          <div class="text-[11px] tracking-[0.32em] uppercase text-mint mb-4 font-mono">Macau TCM Association</div>
          <h1 class="text-4xl md:text-6xl font-bold leading-[1.1] mb-4 max-w-3xl">
            {{ $t('portal.hero.slogan') }}
          </h1>
          <p class="text-base md:text-lg text-warm/75 max-w-xl mb-6">
            汇聚中医专家 · 共建专业平台
          </p>
          <div class="flex flex-wrap gap-3 pointer-events-auto">
            <router-link to="/portal/contact" class="px-7 py-3 bg-mint hover:bg-[#3d8c6b] text-warm rounded-2xl font-medium tracking-wide transition">
              加入學會
            </router-link>
            <router-link to="/portal/about" class="px-7 py-3 bg-warm/10 hover:bg-warm/20 backdrop-blur text-warm rounded-2xl font-medium tracking-wide transition border border-warm/20">
              了解更多
            </router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- 2. 数据看板 -->
    <section class="py-16 bg-warm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-10">
          <div v-for="(stat, i) in stats" :key="i" class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition border border-gray-100">
            <div class="text-4xl md:text-5xl font-bold text-deep mb-2 font-mono">
              {{ stat.value }}
            </div>
            <div class="text-xs tracking-[0.12em] uppercase text-gray-500">
              {{ stat.label }}
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 3. 关于学会 -->
    <section v-if="info" class="py-24 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
          <div class="lg:col-span-4">
            <div class="w-32 h-32 rounded-2xl bg-deep text-mint flex items-center justify-center text-6xl font-bold mb-6">
              協
            </div>
            <div class="text-2xl font-bold mb-1 text-deep">澳門中醫藥學會</div>
            <div class="text-sm text-gray-500 mb-6 leading-relaxed">Associação dos Investigadores, Praticantes e Promotores da Medicina Chinesa de Macau</div>
            <div class="flex flex-col gap-3 text-sm">
              <div v-if="info.founded_year" class="flex items-center gap-2 text-gray-600">
                <span class="material-symbols-outlined text-mint text-base">calendar_today</span>
                <span><span class="font-mono font-bold text-deep">{{ info.founded_year }}</span> 年成立</span>
              </div>
              <div v-if="info.member_count" class="flex items-center gap-2 text-gray-600">
                <span class="material-symbols-outlined text-mint text-base">group</span>
                <span><span class="font-mono font-bold text-deep">{{ info.member_count }}</span> 名會員</span>
              </div>
            </div>
          </div>
          <div class="lg:col-span-8 space-y-10">
            <div>
              <div class="text-[11px] tracking-[0.32em] uppercase text-mint mb-3 font-mono">About</div>
              <h2 class="text-3xl font-bold mb-4 leading-snug text-deep">{{ $t('association.bio_short') }}</h2>
              <p class="text-base text-gray-700 leading-relaxed">{{ info.intro || '-' }}</p>
            </div>
            <div v-if="info.vision">
              <div class="text-[11px] tracking-[0.32em] uppercase text-mint mb-3 font-mono">Vision</div>
              <p class="text-base text-gray-700 leading-relaxed italic border-l-4 border-mint pl-4">{{ info.vision }}</p>
            </div>
            <div v-if="timeline.length">
              <div class="text-[11px] tracking-[0.32em] uppercase text-mint mb-3 font-mono">Milestones</div>
              <ol class="space-y-4">
                <li v-for="m in timeline" :key="m.year" class="flex gap-4 items-start">
                  <div class="flex-shrink-0 w-16 h-16 rounded-2xl bg-mint/10 text-mint flex items-center justify-center font-mono font-bold">
                    {{ m.year }}
                  </div>
                  <div class="flex-1 pt-2 border-l border-gray-200 pl-4 -ml-px">
                    <span class="text-base text-gray-700">{{ m.text }}</span>
                  </div>
                </li>
              </ol>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 4. 核心板块 (4 张精选入口) -->
    <section class="py-24 bg-warm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <div class="text-[11px] tracking-[0.32em] uppercase text-mint mb-3 font-mono">Explore</div>
          <h2 class="text-3xl md:text-4xl font-bold text-deep">探索學會</h2>
          <p class="text-gray-500 mt-3">4 個核心板塊, 全方位服務會員</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <router-link
            v-for="(item, i) in featuredCards"
            :key="i"
            :to="item.to"
            class="group bg-white rounded-2xl p-8 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 border border-gray-100"
          >
            <div class="w-14 h-14 rounded-2xl bg-deep/5 group-hover:bg-deep text-deep group-hover:text-mint flex items-center justify-center text-3xl mb-4 transition-colors material-symbols-outlined">
              {{ item.icon }}
            </div>
            <div class="text-lg font-bold text-deep mb-2 group-hover:text-mint transition">{{ item.label }}</div>
            <div class="text-sm text-gray-500 leading-relaxed">{{ item.desc }}</div>
          </router-link>
        </div>
      </div>
    </section>

    <!-- 5. 最新公告 (1 大 + 4 小) -->
    <section v-if="latestNews.length" class="py-24 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between mb-10">
          <div>
            <div class="text-[11px] tracking-[0.32em] uppercase text-mint mb-3 font-mono">Latest</div>
            <h2 class="text-3xl font-bold text-deep">{{ $t('portal.sections.latest_news') }}</h2>
          </div>
          <router-link to="/portal/news" class="text-sm text-mint hover:underline flex items-center gap-1">
            查看更多 <span class="material-symbols-outlined text-base">arrow_forward</span>
          </router-link>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <article v-if="latestNews[0]" class="group cursor-pointer lg:row-span-2" @click="$router.push(`/portal/news/${latestNews[0].id}`)">
            <div class="aspect-[4/3] overflow-hidden rounded-2xl bg-gradient-to-br from-[#e6ecf2] to-[#d4dde6] mb-4">
              <img v-if="latestNews[0].cover_image" :src="latestNews[0].cover_image" :alt="latestNews[0].title" class="w-full h-full object-cover group-hover:scale-105 transition duration-700" loading="lazy" />
              <div v-else class="w-full h-full flex items-center justify-center">
                <span class="material-symbols-outlined text-deep/30 text-7xl">article</span>
              </div>
            </div>
            <div class="text-xs text-gray-500 mb-2 font-mono">{{ formatDate(latestNews[0].published_at) }}</div>
            <h3 class="text-2xl font-bold mb-3 group-hover:text-mint transition leading-snug text-deep">{{ latestNews[0].title }}</h3>
            <p class="text-base text-gray-600 leading-relaxed line-clamp-3">{{ latestNews[0].summary }}</p>
          </article>
          <div class="space-y-5">
            <article v-for="n in latestNews.slice(1, 5)" :key="n.id" class="group cursor-pointer bg-warm rounded-2xl p-5 hover:shadow-md transition" @click="$router.push(`/portal/news/${n.id}`)">
              <div class="text-xs text-gray-500 mb-2 font-mono">{{ formatDate(n.published_at) }}</div>
              <h3 class="font-bold text-deep mb-1 group-hover:text-mint transition line-clamp-2">{{ n.title }}</h3>
              <p class="text-sm text-gray-600 line-clamp-1">{{ n.summary }}</p>
            </article>
          </div>
        </div>
      </div>
    </section>

    <!-- 6. 近期活动 -->
    <section v-if="upcomingActivities.length" class="py-24 bg-deep text-warm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between mb-10">
          <div>
            <div class="text-[11px] tracking-[0.32em] uppercase text-mint mb-3 font-mono">Upcoming</div>
            <h2 class="text-3xl font-bold">{{ $t('portal.sections.upcoming_activities') }}</h2>
          </div>
          <router-link to="/portal/activities" class="text-sm text-mint hover:underline flex items-center gap-1">
            活動回顧 <span class="material-symbols-outlined text-base">arrow_forward</span>
          </router-link>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <article v-for="a in upcomingActivities" :key="a.id" class="group cursor-pointer bg-warm/5 hover:bg-warm/10 backdrop-blur rounded-2xl overflow-hidden border border-warm/10 hover:border-mint/40 transition" @click="$router.push(`/portal/activities/${a.id}`)">
            <div class="aspect-[16/9] overflow-hidden bg-gradient-to-br from-[#0e2238] to-deep">
              <img v-if="a.cover_image" :src="a.cover_image" :alt="a.title" class="w-full h-full object-cover group-hover:scale-105 transition duration-700" loading="lazy" />
              <div v-else class="w-full h-full flex items-center justify-center">
                <span class="material-symbols-outlined text-mint/30 text-6xl">event</span>
              </div>
            </div>
            <div class="p-6">
              <div class="text-xs text-mint mb-2 font-mono">{{ formatDate(a.start_time) }} · {{ a.location }}</div>
              <h3 class="text-lg font-bold mb-3 group-hover:text-mint transition leading-snug">{{ a.title }}</h3>
              <div class="flex justify-between items-center pt-3 border-t border-warm/10 text-xs">
                <span class="text-warm/60">限 {{ a.max_participants || '∞' }} 人 · 已報 {{ a.current_participants }}</span>
                <span class="text-mint font-bold">{{ a.fee > 0 ? 'MOP ' + a.fee : '免費' }}</span>
              </div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- 7. 学术前沿 + 期刊精选 -->
    <section class="py-24 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
          <div v-if="latestAcademic.length">
            <div class="flex items-end justify-between mb-6">
              <div>
                <div class="text-[11px] tracking-[0.32em] uppercase text-mint mb-2 font-mono">Research</div>
                <h2 class="text-2xl font-bold text-deep">{{ $t('portal.sections.latest_academic') }}</h2>
              </div>
              <router-link to="/portal/academic" class="text-xs text-mint hover:underline">查看更多</router-link>
            </div>
            <ol class="space-y-3">
              <li v-for="(a, i) in latestAcademic" :key="a.id" class="flex gap-4 cursor-pointer group p-4 hover:bg-warm rounded-xl transition" @click="$router.push(`/portal/academic/${a.id}`)">
                <span class="text-2xl font-mono font-bold text-mint group-hover:text-deep transition">{{ String(i + 1).padStart(2, '0') }}</span>
                <div class="flex-1">
                  <h3 class="font-bold mb-1 group-hover:text-mint transition line-clamp-2 text-deep">{{ a.title }}</h3>
                  <p class="text-xs text-gray-500 font-mono">
                    <span v-if="a.author_name">{{ a.author_name }}</span>
                    <span v-if="a.journal_name"> · {{ a.journal_name }}</span>
                    <span v-if="a.doi"> · DOI {{ a.doi }}</span>
                  </p>
                </div>
              </li>
            </ol>
          </div>
          <div v-if="featuredJournals.length">
            <div class="flex items-end justify-between mb-6">
              <div>
                <div class="text-[11px] tracking-[0.32em] uppercase text-mint mb-2 font-mono">Journals</div>
                <h2 class="text-2xl font-bold text-deep">{{ $t('portal.sections.featured_journals') }}</h2>
              </div>
              <router-link to="/portal/journals" class="text-xs text-mint hover:underline">查看更多</router-link>
            </div>
            <div class="grid grid-cols-2 gap-5">
              <article v-for="j in featuredJournals" :key="j.id" class="group cursor-pointer" @click="$router.push(`/portal/journals/${j.id}`)">
                <div class="aspect-[3/4] rounded-xl bg-gradient-to-br from-warm to-[#e8e3da] mb-3 overflow-hidden">
                  <img v-if="j.cover_image" :src="j.cover_image" class="w-full h-full object-cover group-hover:scale-105 transition duration-700" />
                  <div v-else class="w-full h-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-deep/20 text-5xl">menu_book</span>
                  </div>
                </div>
                <div class="text-[10px] text-mint mb-1 font-mono tracking-wider">VOL.{{ j.volume }} NO.{{ j.issue }}</div>
                <h3 class="font-bold text-sm leading-snug group-hover:text-mint transition line-clamp-2 text-deep">{{ j.title }}</h3>
              </article>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 8. 联系我们 -->
    <section id="contact" class="py-24 bg-warm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <div class="text-[11px] tracking-[0.32em] uppercase text-mint mb-3 font-mono">Contact</div>
          <h2 class="text-3xl md:text-4xl font-bold mb-3 text-deep">{{ $t('portal.contact.title') }}</h2>
          <p class="text-gray-500">期待與您交流 · 歡迎來信來電</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
          <div v-if="info.phone" class="bg-white rounded-2xl p-8 text-center hover:shadow-md transition border border-gray-100">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-mint/10 text-mint flex items-center justify-center text-3xl mb-3 material-symbols-outlined">call</div>
            <div class="text-xs tracking-[0.12em] uppercase text-gray-500 mb-2">{{ $t('portal.footer.phone') }}</div>
            <div class="font-mono text-base text-deep">{{ info.phone }}</div>
          </div>
          <div v-if="info.email" class="bg-white rounded-2xl p-8 text-center hover:shadow-md transition border border-gray-100">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-mint/10 text-mint flex items-center justify-center text-3xl mb-3 material-symbols-outlined">mail</div>
            <div class="text-xs tracking-[0.12em] uppercase text-gray-500 mb-2">{{ $t('portal.footer.email') }}</div>
            <div class="font-mono text-base text-deep">{{ info.email }}</div>
          </div>
          <div v-if="info.address" class="bg-white rounded-2xl p-8 text-center hover:shadow-md transition border border-gray-100">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-mint/10 text-mint flex items-center justify-center text-3xl mb-3 material-symbols-outlined">location_on</div>
            <div class="text-xs tracking-[0.12em] uppercase text-gray-500 mb-2">{{ $t('portal.footer.address') }}</div>
            <div class="font-mono text-base text-deep">{{ info.address }}</div>
          </div>
        </div>
        <div class="text-center">
          <router-link to="/portal/contact" class="inline-block px-10 py-3 bg-deep hover:bg-[#16294a] text-warm rounded-2xl font-medium tracking-wide transition">
            提交留言
          </router-link>
        </div>
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

const featuredCards = [
  { to: '/portal/news', icon: 'campaign', label: '公告資訊', desc: '學會最新動態與重要通知' },
  { to: '/portal/activities', icon: 'event', label: '活動報名', desc: '學術講座 · 沙龍 · 培訓課程' },
  { to: '/portal/academic', icon: 'science', label: '學術前沿', desc: '中醫藥研究論文與期刊文獻' },
  { to: '/portal/members', icon: 'diversity_3', label: '會員風采', desc: '本會會員名錄與專業名片' },
]

const stats = ref([
  { value: '32+', label: 'Years Founded' },
  { value: '800+', label: 'Members' },
  { value: '120+', label: 'Activities' },
  { value: '28', label: 'Journals / Yr' },
])

const timeline = [
  { year: 1991, text: '學會正式成立, 創會會員 47 人' },
  { year: 2003, text: '舉辦首屆澳門中醫藥國際學術研討會' },
  { year: 2014, text: '獲特區政府頒授社會服務傑出獎' },
  { year: 2021, text: '中醫藥文化列入澳門非物質文化遺產名錄' },
]

function formatDate(t) {
  if (!t) return ''
  return new Date(t).toLocaleDateString('zh-TW')
}

onMounted(async () => {
  const { api } = await import('@/services/api.js')

  try {
    const r = await api.get('/association/info', { params: { server_profile_id: 7 } })
    if (r.code === 0) info.value = r.data || {}
  } catch (e) {}

  try {
    const r = await api.get('/association/announcements', { params: { server_profile_id: 7, size: 5, status: 'published' } })
    if (r.code === 0) latestNews.value = (Array.isArray(r.data) ? r.data : (r.data.list || []))
  } catch (e) {}

  try {
    const r = await api.get('/association/activities', { params: { server_profile_id: 7, size: 3 } })
    if (r.code === 0) upcomingActivities.value = (Array.isArray(r.data) ? r.data : (r.data.list || []))
  } catch (e) {}

  try {
    const r = await api.get('/association/academic', { params: { size: 4 } })
    if (r.code === 0) latestAcademic.value = (Array.isArray(r.data) ? r.data : (r.data.list || []))
  } catch (e) {}

  try {
    const r = await api.get('/association/journals', { params: { size: 4 } })
    if (r.code === 0) featuredJournals.value = (Array.isArray(r.data) ? r.data : (r.data.list || []))
  } catch (e) {}
})
</script>

<style scoped>
.bg-warm { background-color: #f8f5f0; }
.bg-deep { background-color: #1e3a5f; }
.text-warm { color: #f8f5f0; }
.text-deep { color: #1e3a5f; }
.text-mint { color: #4a9d7c; }
.bg-mint\/10 { background-color: rgb(74 157 124 / 0.1); }
</style>