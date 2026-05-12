<template>
  <div class="main-layout">
    <Sidebar
      :title="sidebarTitle"
      :menu-items="menuItems"
    >
      <template #default>
        <slot name="sidebar" />
      </template>
    </Sidebar>
    <div class="main-content" :class="{ collapsed }">
      <AppHeader
        :collapsed="collapsed"
        :username="username"
        @toggle-sidebar="toggleSidebar"
        @logout="handleLogout"
      >
        <template #actions>
          <slot name="header-actions" />
        </template>
      </AppHeader>
      <main class="content-area">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import Sidebar from './Sidebar.vue'
import AppHeader from './AppHeader.vue'

const props = defineProps({
  sidebarTitle: {
    type: String,
    default: '导航菜单'
  },
  menuItems: {
    type: Array,
    default: () => []
  },
  username: {
    type: String,
    default: '用户'
  }
})

const emit = defineEmits(['logout'])

const collapsed = ref(false)

const toggleSidebar = () => {
  collapsed.value = !collapsed.value
}

const handleLogout = () => {
  emit('logout')
}
</script>

<style scoped>
.main-layout {
  display: flex;
  height: 100vh;
  overflow: hidden;
}

.main-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transition: margin-left 0.3s;
}

.content-area {
  flex: 1;
  padding: 20px;
  overflow-y: auto;
  background: #f5f7fa;
}
</style>
