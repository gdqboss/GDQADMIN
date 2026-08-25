<script setup>
import { ref } from 'vue'
import Sidebar from '../components/Sidebar.vue'
import AppHeader from '../components/AppHeader.vue'
// import AiChat from '../components/AiChat.vue' // 暂时隐藏智能助手

const sidebarOpen = ref(false)
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-bg-light text-text-primary">
    <!-- Mobile overlay -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 bg-black/50 z-30 lg:hidden"
      @click="sidebarOpen = false"
    ></div>

    <!-- Sidebar: fixed on mobile, static on desktop -->
    <div :class="[
      'fixed lg:static inset-y-0 left-0 z-40 transform transition-transform duration-300 lg:transform-none shrink-0',
      sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
    ]">
      <Sidebar @close="sidebarOpen = false" />
    </div>

    <!-- Main content -->
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
      <AppHeader @toggle-sidebar="sidebarOpen = !sidebarOpen" />
      <main class="flex-1 overflow-y-auto p-3 sm:p-4 md:p-6 custom-scrollbar bg-bg-light">
        <div class="max-w-[1600px] mx-auto">
          <router-view />
        </div>
      </main>
    </div>
    <!-- <AiChat /> 暂时隐藏智能助手 -->
  </div>
</template>
