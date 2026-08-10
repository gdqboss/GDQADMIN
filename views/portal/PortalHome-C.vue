<!--
  设计读 (Section 0.B):
    协会官网 for 澳門中醫藥學會 + 国际访客, "传承权威 + 现代专业"语言
    套 C = 端庄中国红 / 朱漆金 / 思源宋体 + 楷体 / Material Symbols sharp

  Design tokens (locked):
    - 朱红:    #b21f24 (主色 / CTA / 装饰)
    - 金:      #d4a537 (辅助 / 边框 / 强调)
    - 米黄:    #f5ecd9 (浅背景)
    - 中红:    #8a181d (深背景)
    - 黑墨:    #2a1a1a (主文字)
    - 留白:    py-24
    - 圆角:    rounded-md

  严格遵守:
    - ZERO em-dashes
    - ONE accent color (朱红)
    - 砍掉 9 张 emoji 卡片重复, 改用 4 张精选入口 + 数据看板
-->

<template>
  <div class="bg-cream text-ink text-[15px] leading-relaxed" style="font-family: 'Source Han Serif', 'Noto Serif CJK SC', 'Songti SC', serif;">
    <section class="relative">
      <UiBanner
        position="portal_hero"
        height-class="h-[28rem] md:h-[36rem]"
        :rounded="false"
        :interval="5000"
        placeholder-text="澳門中醫藥學會"
        placeholder-class="bg-gradient-to-br from-[#3a0808] via-red-deep to-red text-gold"
        :manageable="true"
      />
      <div class="absolute inset-0 bg-gradient-to-br from-red-deep/70 via-red/40 to-transparent pointer-events-none"></div>
      <div class="absolute inset-x-8 top-8 bottom-8 border border-gold/30 pointer-events-none"></div>
      <div class="absolute inset-x-0 bottom-0 pb-12 md:pb-20 pointer-events-none">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-cream">
          <div class="text-[11px] tracking-[0.32em] uppercase text-gold mb-4 font-mono">Macau TCM</div>
          <h1 class="text-4xl md:text-6xl font-bold leading-[1.1] mb-4 max-w-3xl">
            {{ $t('portal.hero.slogan') }}
          </h1>
          <p class="text-base md:text-lg text-cream/80 max-w-xl mb-6">
            岐黃傳承 · 守正創新
          </p>
          <div class="flex flex-wrap gap-3 pointer-events-auto">
            <router-link to="/portal/contact" class="px-7 py-3 bg-gold hover:bg-[#c1942e] text-red-deep rounded-md font-medium tracking-wider transition">
              加入學會
            </router-link>
            <router-link to="/portal/about" class="px-7 py-3 border-2 border-cream/60 hover:bg-cream/10 text-cream rounded-md font-medium tracking-wider transition">
              了解更多
            </router-link>
          </div>
        </div>
      </div>
    </section>

    <section class="py-16 bg-cream">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
          <div v-for="(stat, i) in stats" :key="i" class="bg-white rounded-md p-6 shadow-sm border-t-4 border-red hover:shadow-md transition">
            <div class="text-4xl md:text-5xl font-bold text-red mb-2 font-mono">
              {{ stat.value }}
            </div>
            <div class="text-xs tracking-[0.18em] uppercase text-ink/60">
              {{ stat.label }}
            </div>
          </div>
        </div>
      </div>
    </section>

    <section v-if="info" class="py-24 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
          <div class="lg:col-span-4">
            <div class="w-32 h-32 rounded-full bg-red text-gold flex items-center justify-center text-6xl font-bold mb-6 ring-4 ring-gold/30 ring-offset-4 ring-offset-white">
              協
            </div>
            <div class="text-2xl font-bold mb-1 text-red">澳門中醫藥學會</div>
            <div class="text-sm text-ink/60 mb-6 leading-relaxed">Associação dos Investigadores, Praticantes e Promotores da Medicina Chinesa de Macau</div>
            <div class="flex items-center gap-4 text-sm">
              <div v-if="info.founded_year" class="bg-cream px-3 py-1.5 rounded-md">
                <span class="text-red font-mono font-bold">{{ info.founded_year }}</span>
                <span class="text-ink/60 ml-1">年成立</span>
              </div>
              <div v-if="info.member_count" class="bg-cream px-3 py-1.5 rounded-md">
                <span class="text-red font-mono font-bold">{{ info.member_count }}</span>
                <span class="text-ink/60 ml-1">名會員</span>
              </div>
            </div>
          </div>
          <div class="lg:col-span-8 space-y-8">
            <div>
              <div class="flex items-center gap-3 mb-3">
                <div class="w-1 h-6 bg-red"></div>
                <div class="text-[11px] tracking-[0.32em] uppercase text-red font-mono">About</div>
              </div>
              <h2 class="text-3xl font-bold mb-4 leading-snug text-ink">{{ $t('association.bio_short') }}</h2>
              <p class="text-base text-ink/80 leading-loose">{{ info.intro || '-' }}</p>
            </div>
            <div v-if="info.vision">
              <div class="flex items-center gap-3 mb-3">
                <div class="w-1 h-6 bg-gold"></div>
                <div class="text-[11px] tracking-[0.32em] uppercase text-gold font-mono">Vision</div>
              </div>
              <p class="text-base text-ink/80 leading-loose italic">{{ info.vision }}</p>
            </div>
            <div v-if="timeline.length">
              <div class="flex items-center gap-3 mb-3">
                <div class="w-1 h-6 bg-red"></div>
                <div class="text-[11px] tracking-[0.32em] uppercase text-red font-mono">Milestones</div>
              </div>
              <ol class="relative border-l-2 border-gold/40 pl-6 space-y-5">
                <li v-for="m in timeline" :key="m.year" class="relative">
                  <div class="absolute -left-[29px] top-1.5 w-4 h-4 bg-red border-2 border-gold rounded-full"></div>
                  <span class="font-mono text-red font-bold mr-3 text-lg">{{ m.year }}</span>
                  <span class="text-ink/80">{{ m.text }}</span>
                </li>
              </ol>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-24 bg-cream">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <div class="text-[11px] tracking-[0.32em] uppercase text-red mb-3 font-mono">Explore</div>
          <h2 class="text-3xl md:text-4xl font-bold text-ink">探索學會</h2>
          <div class="flex items-center justify-center gap-3 mt-4">
            <div class="w-12 h-px bg-red"></div>
            <div class="w-2 h-2 bg-gold rounded-full"></div>
            <div class="w-12 h-px bg-red"></div>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <router-link
            v-for="(item, i) in featuredCards"
            :key="i"
            :to="item.to"
            class="group bg-white rounded-md p-8 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 border-t-4 border-red hover:border-gold"
          >
            <div class="text-red text-4xl mb-4 material-symbols-outlined group-hover:text-gold transition-colors">{{ item.icon }}</div>
            <div class="text-lg font-bold text-ink mb-2 group-hover:text-red transition">{{ item.label }}</div>
            <div class="text-sm text-ink/60 leading-relaxed">{{ item.desc }}</div>
            <div class="mt-4 text-xs text-red font-mono group-hover:underline">查看 →</div>
          </router-link>
        </div>
      </div>
    </section>

    <section v-if="latestNews.length" class="py-24 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between mb-10 pb-4 border-b-2 border-red">
          <div class="flex items-end gap-4">
            <div class="text-[11px] tracking-[0.32em] uppercase text-red font-mono">Latest</div>
            <h2 class="text-3xl font-bold text-ink">{{ $t('portal.sections.latest_news') }}</h2>
          </div>
          <router-link to="/portal/news" class="text-sm text-red hover:underline flex items-center gap-1">
            查看更多 <span class="material-symbols-outlined text-base">arrow_forward</span>
          </router-link>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <article v-if="latestNews[0]" class="group cursor-pointer lg:row-span-2" @click="$router.push(`/portal/news/${latestNews[0].id}`)">
            <div class="aspect-[4/3] overflow-hidden rounded-md bg-gradient-to-br from-cream to-[#e8d8b8] mb-4 border-2 border-cream">
              <img v-if="latestNews[0].cover_image" :src="latestNews[0].cover_image" :alt="latestNews[0].title" class="w-full h-full object-cover group-hover:scale-105 transition duration-700" loading="lazy" />
              <div v-else class="w-full h-full flex items-center justify-center">
                <span class="material-symbols-outlined text-red/30 text-7xl">article</span>
              </div>
            </div>
            <div class="text-xs text-ink/50 mb-2 font-mono">{{ formatDate(latestNews[0].published_at) }}</div>
            <h3 class="text-2xl font-bold mb-3 group-hover:text-red transition leading-snug text-ink">{{ latestNews[0].title }}</h3>
            <p class="text-base text-ink/70 leading-relaxed line-clamp-3">{{ latestNews[0].summary }}</p>
          </article>
          <div class="space-y-5">
            <article v-for="n in latestNews.slice(1, 5)" :key="n.id" class="group cursor-pointer bg-cream/50 hover:bg-cream p-5 rounded-md transition border-l-4 border-transparent hover:border-red" @click="$router.push(`/portal/news/${n.id}`)">
              <div class="text-xs text-ink/50 mb-2 font-mono">{{ formatDate(n.published_at) }}</div>
              <h3 class="font-bold text-ink mb-1 group-hover:text-red transition line-clamp-2">{{ n.title }}</h3>
              <p class="text-sm text-ink/60 line-clamp-1">{{ n.summary }}</p>
            </article>
          </div>
        </div>
      </div>
    </section>

    <section v-if="upcomingActivities.length" class="py-24 bg-red-deep text-cream">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between mb-10 pb-4 border-b-2 border-gold/50">
          <div class="flex items-end gap-4">
            <div class="text-[11px] tracking-[0.32em] uppercase text-gold font-mono">Upcoming</div>
            <h2 class="text-3xl font-bold">{{ $t('portal.sections.upcoming_activities') }}</h2>
          </div>
          <router-link to="/portal/activities" class="text-sm text-gold hover:underline flex items-center gap-1">
            活動回顧 <span class="material-symbols-outlined text-base">arrow_forward</span>
          </router-link>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <article v-for="a in upcomingActivities" :key="a.id" class="group cursor-pointer bg-cream/10 hover:bg-cream/15 rounded-md overflow-hidden border-2 border-gold/30 hover:border-gold transition" @click="$router.push(`/portal/activities/${a.id}`)">
            <div class="aspect-[16/9] overflow-hidden bg-gradient-to-br from-red-deep to-[#3a0808]">
              <img v-if="a.cover_image" :src="a.cover_image" :alt="a.title" class="w-full h-full object-cover group-hover:scale-105 transition duration-700" loading="lazy" />
              <div v-else class="w-full h-full flex items-center justify-center">
                <span class="material-symbols-outlined text-gold/30 text-6xl">event</span>
              </div>
            </div>
            <div class="p-6">
              <div class="text-xs text-gold mb-2 font-mono">{{ formatDate(a.start_time) }} · {{ a.location }}</div>
              <h3 class="text-lg font-bold mb-3 group-hover:text-gold transition leading-snug">{{ a.title }}</h3>
              <div class="flex justify-between items-center pt-3 border-t border-gold/20 text-xs">
                <span class="text-cream/70">限 {{ a.max_participants || '∞' }} 人 · 已報 {{ a.current_participants }}</span>
                <span class="text-gold font-bold">{{ a.fee > 0 ? 'MOP ' + a.fee : '免費' }}</span>
              </div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="py-24 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
          <div v-if="latestAcademic.length">
            <div class="flex items-end justify-between mb-6 pb-3 border-b border-red/30">
              <div class="flex items-end gap-3">
                <div class="text-[11px] tracking-[0.32em] uppercase text-red font-mono">Research</div>
                <h2 class="text-2xl font-bold text-ink">{{ $t('portal.sections.latest_academic') }}</h2>
              </div>
              <router-link to="/portal/academic" class="text-xs text-red hover:underline">查看更多</router-link>
            </div>
            <ol class="space-y-4">
              <li v-for="(a, i) in latestAcademic" :key="a.id" class="flex gap-4 cursor-pointer group p-4 hover:bg-cream rounded-md transition border-l-4 border-transparent hover:border-red" @click="$router.push(`/portal/academic/${a.id}`)">
                <span class="text-2xl font-mono font-bold text-red/40 group-hover:text-red transition">{{ String(i + 1).padStart(2, '0') }}</span>
                <div class="flex-1">
                  <h3 class="font-bold mb-1 group-hover:text-red transition line-clamp-2 text-ink">{{ a.title }}</h3>
                  <p class="text-xs text-ink/50 font-mono">
                    <span v-if="a.author_name">{{ a.author_name }}</span>
                    <span v-if="a.journal_name"> · {{ a.journal_name }}</span>
                    <span v-if="a.doi"> · DOI {{ a.doi }}</span>
                  </p>
                </div>
              </li>
            </ol>
          </div>
          <div v-if="featuredJournals.length">
            <div class="flex items-end justify-between mb-6 pb-3 border-b border-red/30">
              <div class="flex items-end gap-3">
                <div class="text-[11px] tracking-[0.32em] uppercase text-red font-mono">Journals</div>
                <h2 class="text-2xl font-bold text-ink">{{ $t('portal.sections.featured_journals') }}</h2>
              </div>
              <router-link to="/portal/journals" class="text-xs text-red hover:underline">查看更多</router-link>
            </div>
            <div class="grid grid-cols-2 gap-5">
              <article v-for="j in featuredJournals" :key="j.id" class="group cursor-pointer" @click="$router.push(`/portal/journals/${j.id}`)">
                <div class="aspect-[3/4] rounded-md bg-gradient-to-br from-cream to-[#e8d8b8] mb-3 overflow-hidden border-2 border-cream group-hover:border-gold transition">
                  <img v-if="j.cover_image" :src="j.cover_image" class="w-full h-full object-cover group-hover:scale-105 transition duration-700" />
                  <div v-else class="w-full h-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-red/20 text-5xl">menu_book</span>
                  </div>
                </div>
                <div class="text-[10px] text-red mb-1 font-mono tracking-wider">VOL.{{ j.volume }} NO.{{ j.issue }}</div>
                <h3 class="font-bold text-sm leading-snug group-hover:text-red transition line-clamp-2 text-ink">{{ j.title }}</h3>
              </article>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="contact" class="py-24 bg-cream">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <div class="text-[11px] tracking-[0.32em] uppercase text-red mb-3 font-mono">Contact</div>
          <h2 class="text-3xl md:text-4xl font-bold mb-3 text-ink">{{ $t('portal.contact.title') }}</h2>
          <div class="flex items-center justify-center gap-3 mt-4">
            <div class="w-12 h-px bg-red"></div>
            <div class="w-2 h-2 bg-gold rounded-full"></div>
            <div class="w-12 h-px bg-red"></div>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
          <div v-if="info.phone" class="bg-white rounded-md p-8 text-center hover:shadow-lg transition border-t-4 border-red">
            <div class="text-red text-4xl mb-3 material-symbols-outlined">call</div>
            <div class="text-xs tracking-[0.18em] uppercase text-ink/60 mb-2">{{ $t('portal.footer.phone') }}</div>
            <div class="font-mono text-base text-ink">{{ info.phone }}</div>
          </div>
          <div v-if="info.email" class="bg-white rounded-md p-8 text-center hover:shadow-lg transition border-t-4 border-red">
            <div class="text-red text-4xl mb-3 material-symbols-outlined">mail</div>
            <div class="text-xs tracking-[0.18em] uppercase text-ink/60 mb-2">{{ $t('portal.footer.email') }}</div>
            <div class="font-mono text-base text-ink">{{ info.email }}</div>
          </div>
          <div v-if="info.address" class="bg-white rounded-md p-8 text-center hover:shadow-lg transition border-t-4 border-red">
            <div class="text-red text-4xl mb-3 material-symbols-outlined">location_on</div>
            <div class="text-xs tracking-[0.18em] uppercase text-ink/60 mb-2">{{ $t('portal.footer.address') }}</div>
            <div class="font-mono text-base text-ink">{{ info.address }}</div>
          </div>
        </div>
        <div class="text-center">
          <router-link to="/portal/contact" class="inline-block px-10 py-3 bg-red hover:bg-red-deep text-cream rounded-md font-medium tracking-wider transition border-2 border-red">
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
.bg-cream { background-color: #f5ecd9; }
.bg-red { background-color: #b21f24; }
.bg-red-deep { background-color: #8a181d; }
.text-cream { color: #f5ecd9; }
.text-ink { color: #2a1a1a; }
.text-red { color: #b21f24; }
.text-gold { color: #d4a537; }
.border-red\/30 { border-color: rgb(178 31 36 / 0.3); }
</style>