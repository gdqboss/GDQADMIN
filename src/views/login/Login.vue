<template>
  <div class="min-h-screen flex flex-col bg-gradient-to-br from-slate-50 to-blue-50">
    <main class="flex-grow flex items-center justify-center p-4">
      <div class="w-full max-w-md bg-white rounded-xl shadow-card overflow-hidden">
        <!-- Header -->
        <div class="pt-6 sm:pt-8 md:pt-10 pb-3 sm:pb-4 md:pb-6 px-4 sm:px-6 md:px-8 text-center">
          <span class="material-symbols-outlined text-4xl text-primary mb-2">inventory_2 </span>
          <h1 class="text-lg sm:text-xl md:text-2xl font-bold text-slate-900">彩美特管理系统</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-1">企业品牌管理平台</p>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleLogin" class="px-4 sm:px-6 md:px-8 pb-6 sm:pb-8 md:pb-10 space-y-4">
          <!-- Account -->
          <div>
            <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">邮箱或手机号</label>
            <div class="relative">
              <input
                v-model="loginForm.account"
                type="text"
                placeholder="请输入邮箱或手机号"
                class="w-full px-3 py-2.5 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none text-sm"
              />
            </div>
          </div>

          <!-- Password -->
          <div>
            <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">密码</label>
            <div class="relative">
              <input
                v-model="loginForm.password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="请输入密码"
                class="w-full px-3 py-2.5 pr-10 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none text-sm"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
              >
                <span class="material-symbols-outlined text-lg">{{ showPassword ? 'visibility' : 'visibility_off' }}</span>
              </button>
            </div>
          </div>

          <!-- Options -->
          <div class="flex items-center justify-between">
            <label class="flex items-center cursor-pointer">
              <input type="checkbox" v-model="loginForm.remember" class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary" />
              <span class="ml-2 text-xs sm:text-sm text-slate-600">记住账号密码</span>
            </label>
            <button type="button" @click="showForgot = true" class="text-xs sm:text-sm text-blue-600 hover:text-blue-700">忘记密码？</button>
          </div>

          <!-- Error -->
          <div v-if="errorMsg" class="text-xs text-red-500 bg-red-50 py-2 px-3 rounded-lg text-center">
            {{ errorMsg }}
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="loading"
            class="w-full py-2.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-hover disabled:opacity-50 transition-colors"
          >
            {{ loading ? '登录中...' : '登录' }}
          </button>

          <!-- Register -->
          <div class="text-center pt-2">
            <button type="button" class="text-xs sm:text-sm text-slate-500 hover:text-slate-700">
              还没有账号？联系管理员
            </button>
          </div>
        </form>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'

const router = useRouter()
const loginForm = ref({
  account: '',
  password: '',
  remember: false
})
const showPassword = ref(false)
const loading = ref(false)
const errorMsg = ref('')
const showForgot = ref(false)

const handleLogin = async () => {
  if (!loginForm.value.account || !loginForm.value.password) {
    errorMsg.value = '请输入账号和密码'
    return
  }
  loading.value = true
  errorMsg.value = ''
  try {
    // 调用登录接口
    const res = await fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: loginForm.value.account,
        password: loginForm.value.password
      })
    })
    const data = await res.json()
    if (data.code === 0) {
      localStorage.setItem('caimeite_token', data.data.token)
      localStorage.setItem('caimeite_user', JSON.stringify(data.data.user))
      if (loginForm.value.remember) {
        localStorage.setItem('caimeite_account', loginForm.value.account)
        localStorage.setItem('caimeite_password', loginForm.value.password)
        localStorage.setItem('caimeite_remember', 'true')
      }
      ElMessage.success('登录成功')
      router.push('/')
    } else {
      errorMsg.value = data.message || '登录失败'
    }
  } catch (e) {
    errorMsg.value = '网络错误，请稍后重试'
  } finally {
    loading.value = false
  }
}
</script>
