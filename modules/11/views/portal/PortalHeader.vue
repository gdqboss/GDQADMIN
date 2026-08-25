<template>
  <header class="bg-white border-b border-gray-200 sticky top-0 z-40 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">
        <!-- Logo -->
        <router-link :to="'/portal'" class="flex items-center gap-3">
          <img src="/logo-square.png" alt="澳門中醫藥學會" class="h-14 w-14 object-contain bg-white rounded-full p-0.5 shadow-sm" />
          <div class="hidden sm:block">
            <div class="font-bold text-gray-800">{{ siteName }}</div>
            <div class="text-xs text-gray-500">{{ siteSlogan }}</div>
          </div>
        </router-link>

        <!-- PC nav -->
        <nav class="hidden md:flex items-center gap-1">
          <router-link
            v-for="item in navItems"
            :key="item.path"
            :to="`/portal${item.path}`"
            class="px-3 py-2 text-sm text-gray-700 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition"
            :class="{ 'text-amber-600 bg-amber-50': isActive(item.path) }"
          >
            {{ $t(`portal.nav.${item.key}`) }}
          </router-link>
        </nav>

        <!-- Language + Mobile menu -->
        <div class="flex items-center gap-2">
          <select
            :value="currentLocale"
            @change="switchLang"
            class="text-sm border border-gray-200 rounded-lg px-2 py-1 bg-white"
          >
            <option value="zh">简体中文</option>
            <option value="zh-TW">繁體中文</option>
            <option value="en">English</option>
          </select>

          <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 rounded-lg hover:bg-gray-100">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Mobile nav -->
      <transition name="slide">
        <nav v-if="mobileOpen" class="md:hidden border-t border-gray-200 py-2">
          <router-link
            v-for="item in navItems"
            :key="item.path"
            :to="`/portal${item.path}`"
            @click="mobileOpen = false"
            class="block px-4 py-2 text-sm text-gray-700 hover:bg-amber-50 hover:text-amber-600"
            :class="{ 'text-amber-600 bg-amber-50': isActive(item.path) }"
          >
            {{ $t(`portal.nav.${item.key}`) }}
          </router-link>
        </nav>
      </transition>
    </div>
  </header>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { setLocale } from '@/i18n'

const route = useRoute()
const { locale } = useI18n()
const mobileOpen = ref(false)
const currentLocale = computed(() => locale.value)

const logoText = '协'
const siteName = ref('')
const siteSlogan = ref('')

// 默认值走 i18n, DB 异步加载后覆盖
// 2026-08-03+ 多语言: DB 现在有 name_zh_tw / name_en / name_en / slogan_en / slogan_tw
//   按 locale 选 DB 字段, 缺语言 fallback 到 zh (简体)
const { t } = useI18n()
siteName.value = t('portal.header.siteName')
siteSlogan.value = t('portal.header.siteSlogan')

// 缓存 DB 返回的最新一行, locale 变了直接从缓存挑 (不重发请求)
let cachedInfo = null
async function refreshFromCache() {
  if (!cachedInfo) return
  const lc = locale.value
  const nameMap = {
    'zh': cachedInfo.name_zh,
    'zh-TW': cachedInfo.name_zh_tw || cachedInfo.name_zh,
    'en': cachedInfo.name_en || cachedInfo.name_zh,
  }
  const sloganMap = {
    'zh': cachedInfo.slogan,
    'zh-TW': cachedInfo.slogan_zh_tw || cachedInfo.slogan,
    'en': cachedInfo.slogan_en || cachedInfo.slogan,
  }
  if (nameMap[lc]) siteName.value = nameMap[lc]
  if (sloganMap[lc]) siteSlogan.value = sloganMap[lc]
}
watch(locale, refreshFromCache)

// 一次性 fetch (以后切语言走 cache)
import('@/services/api.js').then(async ({ api }) => {
  try {
    const r = await api.get('/association/info')
    if (r.code === 0 && r.data) {
      cachedInfo = r.data
      await refreshFromCache()
    }
  } catch (e) {}
})

const navItems = [
  { path: '', key: 'home' },
  { path: '/about', key: 'about' },
  { path: '/news', key: 'news' },
  { path: '/academic', key: 'academic' },
  { path: '/activities', key: 'activities' },
  { path: '/journals', key: 'journals' },
  { path: '/members', key: 'members' },
  { path: '/downloads', key: 'downloads' },
  { path: '/org', key: 'org' },
  { path: '/contact', key: 'contact' },
]

function isActive(path) {
  if (path === '') return route.path === '/portal' || route.path === '/portal/'
  return route.path.startsWith(`/portal${path}`)
}

function switchLang(e) {
  setLocale(e.target.value)
  // 切语言后刷新页面以让所有 chunk 重新渲染
  setTimeout(() => window.location.reload(), 100)
}
</script>

<style scoped>
.slide-enter-active, .slide-leave-active {
  transition: all 0.2s ease;
}
.slide-enter-from, .slide-leave-to {
  transform: translateY(-10px);
  opacity: 0;
}
</style>