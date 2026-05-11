<template>
  <header class="h-14 bg-white border-b border-[#dcdfe6] flex items-center justify-between px-4 shadow-sm shrink-0">
    <!-- Left: menu toggle + breadcrumb -->
    <div class="flex items-center gap-3">
      <button @click="$emit('toggle-sidebar')" class="p-2 rounded hover:bg-gray-100 text-gray-500">
        <span class="material-symbols-outlined text-xl">menu</span>
      </button>
      <nav class="flex items-center gap-1 text-sm text-gray-500">
        <router-link to="/" class="hover:text-primary">首页</router-link>
        <span v-if="route.meta.title"> / {{ route.meta.title }}</span>
      </nav>
    </div>

    <!-- Right: search + actions -->
    <div class="flex items-center gap-2">
      <!-- Search -->
      <div class="hidden md:flex items-center bg-gray-50 rounded-full px-3 py-1.5 border border-transparent focus-within:border-primary focus-within:bg-white transition-all w-56">
        <span class="material-symbols-outlined text-lg text-gray-400">search</span>
        <input type="text" placeholder="搜索..." class="bg-transparent border-none focus:ring-0 text-sm w-full ml-1" />
      </div>

      <!-- Lang toggle -->
      <button @click="toggleLang" class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">
        <span class="material-symbols-outlined text-base">language</span>
        <span>{{ locale === 'zh' ? 'EN' : '中' }}</span>
      </button>

      <!-- Notifications -->
      <button class="relative p-2 text-gray-500 hover:bg-gray-100 rounded-full">
        <span class="material-symbols-outlined text-xl">notifications</span>
      </button>

      <!-- User dropdown -->
      <el-dropdown @command="handleCommand">
        <button class="flex items-center gap-2 p-1.5 rounded hover:bg-gray-100">
          <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center text-white text-sm font-medium">
            {{ userInitial }}
          </div>
          <span class="hidden md:block text-sm text-gray-600">{{ userName }}</span>
          <span class="material-symbols-outlined text-base text-gray-400">expand_more</span>
        </button>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item command="profile">个人设置</el-dropdown-item>
            <el-dropdown-item command="logout" divided>退出登录</el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

defineEmits(['toggle-sidebar'])

const route = useRoute()
const router = useRouter()
const { locale } = useI18n()

const userInfo = JSON.parse(localStorage.getItem('caimeite_user') || '{}')
const userName = computed(() => userInfo.name || '管理员')
const userInitial = computed(() => (userInfo.name || 'A').charAt(0).toUpperCase())

const toggleLang = () => {
  locale.value = locale.value === 'zh' ? 'en' : 'zh'
  localStorage.setItem('caimeite_locale', locale.value)
}

const handleCommand = (cmd) => {
  if (cmd === 'logout') {
    localStorage.removeItem('caimeite_token')
    localStorage.removeItem('caimeite_user')
    router.push('/login')
  } else if (cmd === 'profile') {
    router.push('/profile')
  }
}
</script>
