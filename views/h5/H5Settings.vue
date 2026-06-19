<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <div class="sticky top-0 bg-white/95 backdrop-blur-sm z-20 px-4 py-3 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <button @click="$router.back()" class="flex-shrink-0">
          <span class="material-symbols-outlined text-2xl text-slate-600">arrow_back</span>
        </button>
        <h2 class="text-lg font-bold text-slate-800">账户设置</h2>
      </div>
    </div>
    <div class="px-4 pt-4 space-y-3">
      <div class="bg-white rounded-2xl overflow-hidden shadow-sm divide-y divide-slate-50">
        <button @click="$router.push('/h5/profile/edit')"
          class="w-full flex items-center gap-3 px-4 py-4 text-sm hover:bg-slate-50 transition-all">
          <div class="w-8 h-8 rounded-xl bg-blue-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-lg text-blue-500" style="font-variation-settings: 'FILL' 1">person</span>
          </div>
          <span class="flex-1 text-left text-slate-700 font-medium">个人资料</span>
          <span class="material-symbols-outlined text-base text-slate-300">chevron_right</span>
        </button>
        <button @click="$router.push('/h5/password')"
          class="w-full flex items-center gap-3 px-4 py-4 text-sm hover:bg-slate-50 transition-all">
          <div class="w-8 h-8 rounded-xl bg-slate-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-lg text-slate-500">lock</span>
          </div>
          <span class="flex-1 text-left text-slate-700 font-medium">修改密码</span>
          <span class="material-symbols-outlined text-base text-slate-300">chevron_right</span>
        </button>
        <button @click="$router.push('/h5/bind-phone')"
          class="w-full flex items-center gap-3 px-4 py-4 text-sm hover:bg-slate-50 transition-all">
          <div class="w-8 h-8 rounded-xl bg-green-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-lg text-green-500" style="font-variation-settings: 'FILL' 1">smartphone</span>
          </div>
          <span class="flex-1 text-left text-slate-700 font-medium">绑定手机</span>
          <span class="material-symbols-outlined text-base text-slate-300">chevron_right</span>
        </button>
      </div>
      <button @click="logout"
        class="w-full py-3.5 bg-white rounded-2xl text-sm text-red-400 font-medium shadow-sm hover:shadow-md transition-all border border-slate-100">
        退出登录
      </button>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
const router = useRouter()
function logout() {
  localStorage.removeItem('mall_token')
  localStorage.removeItem('mall_user_id')
  localStorage.removeItem('mall_user_name')
  router.push('/login')
}
</script>
