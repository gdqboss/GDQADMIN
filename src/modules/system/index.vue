<template>
  <div class="p-4">
    <el-tabs v-model="activeTab" class="system-tabs">
      <el-tab-pane label="用户管理" name="users">
        <router-view />
      </el-tab-pane>
      <el-tab-pane label="角色管理" name="roles">
        <router-view v-if="activeTab === 'roles'" />
      </el-tab-pane>
      <el-tab-pane label="权限管理" name="permissions">
        <router-view v-if="activeTab === 'permissions'" />
      </el-tab-pane>
      <el-tab-pane label="菜单管理" name="menus">
        <router-view v-if="activeTab === 'menus'" />
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const activeTab = ref(route.name === 'SystemSettings' ? 'users' : route.name?.replace('System', '').toLowerCase())

watch(activeTab, (tab) => {
  const tabMap = { users: 'users', roles: 'roles', permissions: 'permissions', menus: 'menus' }
  router.push({ name: `System${tab.charAt(0).toUpperCase() + tab.slice(1)}` })
})
</script>

<style scoped>
.system-tabs :deep(.el-tabs__header) {
  background: white;
  border-radius: 8px 8px 0 0;
  padding: 0 16px;
}
</style>
