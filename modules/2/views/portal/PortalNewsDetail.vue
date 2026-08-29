<template>
  <PortalDetailPage
    api-path="/association/announcements"
    back-path="/portal/news"
    :back-label="$t('portal.nav.news')"
  />
</template>

<script setup>
import PortalDetailPage from './PortalDetailPage.vue'
</script>