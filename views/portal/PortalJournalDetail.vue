<template>
  <PortalDetailPage
    api-path="/association/journals"
    back-path="/portal/journals"
    :back-label="$t('portal.nav.journals')"
    download-endpoint="/association/journals/{id}/download"
  />
</template>

<script setup>
import PortalDetailPage from './PortalDetailPage.vue'
</script>