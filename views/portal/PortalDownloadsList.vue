<template>
  <PortalListPage
    :title="$t('portal.nav.downloads')"
    api-path="/association/downloads"
    :extra-filter="{ status: 'published' }"
    :category-map="{ general: '综合', form: '表格', template: '模板', guide: '指南', regulation: '规章' }"
    @item-click="go"
  />
</template>

<script setup>
import PortalListPage from './PortalListPage.vue'
import { useRouter } from 'vue-router'
const router = useRouter()
function go(item) { router.push(`/portal/downloads/${item.id}`) }
</script>