<template>
  <nav v-if="showTabBar"
    class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-sm border-t border-gray-100 z-50 shadow-[0_-2px_12px_rgba(0,0,0,0.06)] safe-area-bottom">
    <div class="flex justify-around py-1">
      <router-link v-for="tab in tabs" :key="tab.path" :to="tab.path"
        class="flex flex-col items-center py-1.5 px-3 min-w-0 transition-colors duration-150"
        :class="isActive(tab.path) ? 'text-amber-600' : 'text-gray-400 hover:text-gray-500'">
        <span class="text-2xl leading-none">{{ tab.icon }}</span>
        <span class="text-[10px] mt-1 font-medium">{{ tab.label }}</span>
      </router-link>
    </div>
  </nav>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'

const route = useRoute()
const { t } = useI18n()

// 學會 5 大 Tab (PC 端頂部 Header 仍然顯示所有模組連結,移動端用 TabBar 主力)
// 翻译缺失时回落到默认中文 (波哥 2026-08-04: 各客户 DB 翻译表可能未填, fallback 不算硬编码)
const tabs = computed(() => [
  { path: '/portal',          name: 'PortalHome',     icon: '🏛',  label: t('portal.tabBar.home', '首頁') },
  { path: '/portal/news',     name: 'PortalNews',     icon: '📰',  label: t('portal.tabBar.news', '資訊') },
  { path: '/portal/academic', name: 'PortalAcademic', icon: '🎓',  label: t('portal.tabBar.academic', '學術') },
  { path: '/portal/activities', name: 'PortalActivities', icon: '📅', label: t('portal.tabBar.activities', '活動') },
  { path: '/portal/profile',  name: 'PortalProfile',  icon: '👤',  label: t('portal.tabBar.profile', '我的') },
])

// 只在主 Tab 页面显示底部 TabBar (兼容 hash 模式 /portal/ 跟 /portal 都行)
const tabPaths = ['/portal', '/portal/news', '/portal/academic', '/portal/activities', '/portal/profile']
const showTabBar = computed(() => {
  const p = route.path.replace(/\/+$/, '') || '/'
  return tabPaths.some(tp => {
    const t = tp.replace(/\/+$/, '') || '/'
    return p === t || p.startsWith(t + '/')
  })
})

function isActive(path) {
  const p = path.replace(/\/+$/, '') || '/'
  const r = route.path.replace(/\/+$/, '') || '/'
  return r === p || r.startsWith(p + '/')
}
</script>

<style scoped>
.safe-area-bottom {
  padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>