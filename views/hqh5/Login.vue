<template>
  <div class="min-h-screen bg-gradient-to-br from-purple-600 via-purple-700 to-indigo-800 flex items-center justify-center p-6">
    <div class="max-w-md w-full">
      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="text-6xl mb-3">🌉</div>
        <h1 class="text-2xl font-bold text-white">横琴湾区创新中心</h1>
        <p class="text-sm text-purple-200 mt-1">企业服务一站式平台</p>
      </div>

      <!-- 登录卡片 -->
      <div class="bg-white rounded-2xl shadow-2xl p-6">
        <h2 class="text-lg font-bold text-gray-800 text-center mb-6">登录 / 注册</h2>

        <!-- 手机号登录 -->
        <div class="space-y-3">
          <div>
            <label class="text-sm text-gray-600 block mb-1">手机号</label>
            <input v-model="phone" type="tel" maxlength="11" placeholder="请输入手机号" class="w-full p-3 border border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none" />
          </div>
          <div>
            <label class="text-sm text-gray-600 block mb-1">验证码</label>
            <div class="flex gap-2">
              <input v-model="code" maxlength="6" placeholder="6 位验证码" class="flex-1 p-3 border border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none" />
              <button
                @click="sendCode"
                :disabled="sending || countdown > 0"
                class="px-4 py-3 bg-purple-100 text-purple-600 rounded-lg text-sm font-medium whitespace-nowrap disabled:opacity-50"
              >{{ countdown > 0 ? `${countdown}s` : '发送验证码' }}</button>
            </div>
          </div>
          <button
            @click="login"
            :disabled="loading || !phone || !code"
            class="w-full bg-gradient-to-r from-purple-600 to-purple-700 text-white py-3 rounded-lg font-medium shadow-md disabled:opacity-50"
          >{{ loading ? '登录中...' : '登录' }}</button>
        </div>

        <!-- 演示账号 -->
        <div class="mt-6 pt-4 border-t border-gray-100">
          <p class="text-xs text-gray-500 text-center mb-2">🎯 演示模式 - 一键登录</p>
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="demo in demoUsers"
              :key="demo.role"
              @click="quickLogin(demo)"
              class="bg-gray-50 hover:bg-gray-100 rounded-lg p-3 text-left transition"
            >
              <div class="text-sm font-medium text-gray-800">{{ demo.label }}</div>
              <div class="text-xs text-gray-500 mt-0.5">{{ demo.desc }}</div>
            </button>
          </div>
        </div>

        <!-- OAuth -->
        <div class="mt-6 pt-4 border-t border-gray-100">
          <p class="text-xs text-gray-500 text-center mb-3">其他登录方式</p>
          <div class="flex justify-center gap-4">
            <button class="flex flex-col items-center gap-1" @click="oauthLogin('wechat')">
              <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                <span class="text-2xl">💬</span>
              </div>
              <span class="text-xs text-gray-600">微信</span>
            </button>
            <button class="flex flex-col items-center gap-1" @click="oauthLogin('wecom')">
              <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                <span class="text-2xl">🏢</span>
              </div>
              <span class="text-xs text-gray-600">企业微信</span>
            </button>
            <button class="flex flex-col items-center gap-1" @click="oauthLogin('sso')">
              <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                <span class="text-2xl">🔐</span>
              </div>
              <span class="text-xs text-gray-600">SSO</span>
            </button>
          </div>
        </div>
      </div>

      <!-- 底部 -->
      <div class="text-center mt-6">
        <p class="text-xs text-purple-200">登录即同意 <a class="underline">用户协议</a> 和 <a class="underline">隐私政策</a></p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'

const router = useRouter()
const phone = ref('')
const code = ref('')
const loading = ref(false)
const sending = ref(false)
const countdown = ref(0)
let timer = null

const demoUsers = [
  { role: 'admin', label: '👑 超级管理员', desc: '江清波 · 全权限', user_id: 99, user_name: '江清波' },
  { role: 'hr', label: '👔 园区管理员', desc: '李明 · 审批 + 后台', user_id: 2, user_name: '李明' },
  { role: 'employee', label: '👨‍💼 企业员工', desc: '张总 · 基础功能', user_id: 3, user_name: '张总' },
  { role: 'guest', label: '🚶 访客', desc: '游客模式 · 只读', user_id: 4, user_name: '王经理' }
]

const sendCode = () => {
  if (!/^1[3-9]\d{9}$/.test(phone.value)) {
    ElMessage.warning('请输入正确的手机号')
    return
  }
  sending.value = true
  setTimeout(() => {
    sending.value = false
    countdown.value = 60
    code.value = '888888' // 演示用固定码
    ElMessage.success('验证码已发送（演示码：888888）')
    timer = setInterval(() => {
      countdown.value--
      if (countdown.value <= 0) clearInterval(timer)
    }, 1000)
  }, 500)
}

const login = async () => {
  loading.value = true
  try {
    // 演示：直接通过
    setTimeout(() => {
      ElMessage.success('登录成功')
      localStorage.setItem('hqh5_user', JSON.stringify({ phone: phone.value, name: '演示用户' }))
      router.push('/hqh5/enterprise-home')
      loading.value = false
    }, 800)
  } catch (e) {
    ElMessage.error('登录失败')
    loading.value = false
  }
}

const quickLogin = (demo) => {
  localStorage.setItem('hqh5_user', JSON.stringify({ user_id: demo.user_id, name: demo.user_name, role: demo.role }))
  ElMessage.success(`已切换到 ${demo.label}`)
  setTimeout(() => {
    router.push(demo.role === 'guest' ? '/hqh5/guest-home' : '/hqh5/enterprise-home')
  }, 300)
}

const oauthLogin = (type) => {
  ElMessage.info(`${type === 'wechat' ? '微信' : type === 'wecom' ? '企业微信' : 'SSO'} 登录功能开发中`)
}

onBeforeUnmount(() => { if (timer) clearInterval(timer) })
</script>