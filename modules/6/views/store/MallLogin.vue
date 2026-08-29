<template>
  <div class="min-h-screen bg-gray-50 flex flex-col justify-center px-6">
    <div class="text-center mb-8">
      <span class="material-symbols-outlined text-5xl text-blue-600">person</span>
      <h1 class="font-bold text-xl mt-3">{{ shopName }}</h1>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm">
      <div class="mb-4">
        <p class="text-sm text-gray-500 mb-1.5">手机号</p>
        <input v-model="form.phone" type="tel" placeholder="请输入手机号"
          class="w-full border rounded-xl px-4 py-3 text-base" />
      </div>
      <div class="mb-6">
        <p class="text-sm text-gray-500 mb-1.5">密码</p>
        <input v-model="form.password" type="password" placeholder="请输入密码"
          class="w-full border rounded-xl px-4 py-3 text-base" @keyup.enter="login" />
      </div>

      <div v-if="error" class="mb-4 p-3 bg-red-50 text-red-600 text-sm rounded-xl">{{ error }}</div>

      <button @click="login" :disabled="loading"
        class="w-full py-3 bg-blue-600 text-white rounded-xl font-medium text-base disabled:opacity-50">
        {{ loading ? '登录中...' : '登录' }}
      </button>

      <p class="text-center text-sm text-gray-400 mt-4">
        还没有账号？<router-link to="/mall/register" class="text-blue-600">立即注册</router-link>
      </p>
    </div>

    <button @click="$router.push('/mall')" class="mt-6 text-sm text-gray-400 self-center">
      返回商城
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter()

const shopName = ref('TRAVELMATE')
const form = ref({ phone: '', password: '' })
const loading = ref(false)
const error = ref('')

async function login() {
  error.value = ''
  if (!form.value.phone || !form.value.password) { error.value = '请填写手机号和密码'; return }
  loading.value = true
  try {
    const res = await fetch('/api/store-mall/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(form.value)
    })
    const data = await res.json()
    if (data.user_id) {
      localStorage.setItem('mall_user_id', data.user_id)
      localStorage.setItem('mall_user_name', data.name || '')
      window.dispatchEvent(new Event('mall_user_login'))
      router.push('/mall')
    } else {
      error.value = data.message || '登录失败'
    }
  } catch (e) {
    error.value = '登录失败：' + e.message
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (localStorage.getItem('mall_user_id')) router.push('/mall')
  fetch('/api/store-mall/config').then(r => r.json()).then(d => { if (d.shop_name) shopName.value = d.shop_name }).catch(() => {})
})
</script>