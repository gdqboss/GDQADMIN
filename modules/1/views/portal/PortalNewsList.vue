<template>
  <PortalListPage
    :title="$t('portal.nav.news')"
    api-path="/association/announcements"
    :extra-filter="{ status: 'published' }"
    :category-map="{ general: '綜合', notice: '通知', event: '活動', recruit: '招募' }"
    @item-click="go"
  />
</template>

<script setup>
import PortalListPage from './PortalListPage.vue'
import { useRouter } from 'vue-router'
const router = useRouter()
function go(item) { router.push(`/portal/news/${item.id}`) }
</script>