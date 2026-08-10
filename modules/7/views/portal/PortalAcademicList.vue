<template>
  <PortalListPage
    :title="$t('portal.nav.academic')"
    api-path="/association/academic"
    :extra-filter="{ status: 'published' }"
    :category-map="{ general: '综合', research: '研究', conference: '会议', award: '获奖' }"
    @item-click="go"
  />
</template>

<script setup>
import PortalListPage from './PortalListPage.vue'
import { useRouter } from 'vue-router'
const router = useRouter()
function go(item) { router.push(`/portal/academic/${item.id}`) }
</script>