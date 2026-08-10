<template>
  <div class="min-h-screen bg-gray-50 flex flex-col justify-center px-6">
    <div class="text-center mb-8">
      <span class="material-symbols-outlined text-5xl text-blue-600">person_add</span>
      <h1 class="font-bold text-xl mt-3">注册账号</h1>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm">
      <div class="mb-4">
        <p class="text-sm text-gray-500 mb-1.5">姓名</p>
        <input v-model="form.name" type="text" placeholder="请输入姓名" class="w-full border rounded-xl px-4 py-3 text-base" />
      </div>
      <div class="mb-4">
        <p class="text-sm text-gray-500 mb-1.5">手机号</p>
        <input v-model="form.phone" type="tel" placeholder="请输入手机号" class="w-full border rounded-xl px-4 py-3 text-base" />
      </div>
      <div class="mb-4">
        <p class="text-sm text-gray-500 mb-1.5">密码</p>
        <input v-model="form.password" type="password" placeholder="设置密码（至少6位）" class="w-full border rounded-xl px-4 py-3 text-base" />
      </div>
      <div class="mb-6">
        <p class="text-sm text-gray-500 mb-1.5">确认密码</p>
        <input v-model="form.confirm" type="password" placeholder="再次输入密码" class="w-full border rounded-xl px-4 py-3 text-base" />
      </div>

      <div v-if="error" class="mb-4 p-3 bg-red-50 text-red-600 text-sm rounded-xl">{{ error }}</div>

      <button @click="register" :disabled="loading"
        class="w-full py-3 bg-blue-600 text-white rounded-xl font-medium text-base disabled:opacity-50">
        {{ loading ? '注册中...' : '注册' }}
      </button>

      <p class="text-center text-sm text-gray-400 mt-4">
        已有账号？<router-link to="/mall/login" class="text-blue-600">立即登录</router-link>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter()
const form = ref({ name: '', phone: '', password: '', confirm: '' })
const loading = ref(false)
const error = ref('')

async function register() {
  error.value = ''
  if (!form.value.name || !form.value.phone || !form.value.password) { error.value = '请填写完整信息'; return }
  if (form.value.password !== form.value.confirm) { error.value = '两次密码不一致'; return }
  if (form.value.password.length < 6) { error.value = '密码至少6位'; return }
  loading.value = true
  try {
    const res = await fetch('/api/store-mall/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: form.value.name, phone: form.value.phone, password: form.value.password })
    })
    const data = await res.json()
    if (data.user_id) {
      alert('注册成功！')
      router.push('/mall/login')
    } else {
      error.value = data.message || '注册失败'
    }
  } catch (e) {
    error.value = '注册失败：' + e.message
  } finally {
    loading.value = false
  }
}
</script>