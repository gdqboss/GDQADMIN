<template>
  <div class="min-h-screen bg-white flex flex-col">
    <PortalHeader />
    <main class="flex-1 pb-16">
      <router-view />
    </main>
    <PortalTabBar />
  </div>
</template>

<script setup>
import PortalHeader from './PortalHeader.vue'
import PortalTabBar from './PortalTabBar.vue'
</script>

<style scoped>
@media (max-width: 768px) {
  main { padding-bottom: 0; }
}
</style>