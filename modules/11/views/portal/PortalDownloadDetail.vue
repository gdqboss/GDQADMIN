<template>
  <PortalDetailPage
    api-path="/association/downloads"
    back-path="/portal/downloads"
    :back-label="$t('portal.nav.downloads')"
    download-endpoint="/association/downloads/{id}/download"
  />
</template>

<script setup>
import PortalDetailPage from './PortalDetailPage.vue'
</script>