<template>
  <PortalDetailPage
    api-path="/association/academic"
    back-path="/portal/academic"
    :back-label="$t('portal.nav.academic')"
  />
</template>

<script setup>
import PortalDetailPage from './PortalDetailPage.vue'
</script>