<template>
  <PortalListPage
    :title="$t('portal.nav.journals')"
    api-path="/association/journals"
    :extra-filter="{ status: 'published' }"
    :category-map="{ general: '综合', special: '专刊', issue: '特辑' }"
    @item-click="go"
  />
</template>

<script setup>
import PortalListPage from './PortalListPage.vue'
import { useRouter } from 'vue-router'
const router = useRouter()
function go(item) { router.push(`/portal/journals/${item.id}`) }
</script>