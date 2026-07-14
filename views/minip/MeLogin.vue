<template>
  <div class="login-bg">
    <div class="logo">📱</div>
    <h1>彩美特小程序</h1>
    <p class="sub">企业服务一站式平台</p>

    <div class="card">
      <h2>登录 / 注册</h2>
      <div class="form">
        <label>手机号</label>
        <input
          v-model="phone"
          type="tel"
          maxlength="11"
          placeholder="请输入手机号"
          @input="onPhoneInput"
        />

        <label>验证码</label>
        <div class="code-row">
          <input
            v-model="code"
            maxlength="6"
            inputmode="numeric"
            placeholder="6 位验证码"
          />
          <button
            :disabled="sending || countdown > 0 || !isValidPhone"
            @click="sendCode"
          >
            {{ countdown > 0 ? `${countdown}s 后重试` : '发送验证码' }}
          </button>
        </div>

        <button
          class="login-btn"
          :disabled="loading || !isValidPhone || !code"
          @click="login"
        >
          {{ loading ? '登录中...' : '登录' }}
        </button>

        <p v-if="errMsg" class="err">{{ errMsg }}</p>
      </div>
    </div>

    <p class="terms">登录即同意 <span>用户协议</span> 和 <span>隐私政策</span></p>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import api from '@/services/api.js'

const router = useRouter()
const phone = ref('')
const code = ref('')
const loading = ref(false)
const sending = ref(false)
const countdown = ref(0)
const errMsg = ref('')

const isValidPhone = computed(() => /^1[3-9]\d{9}$/.test(phone.value))

let timer = null

function onPhoneInput() {
  // 只允许数字
  phone.value = phone.value.replace(/\D/g, '').slice(0, 11)
}

async function sendCode() {
  if (!isValidPhone.value) {
    ElMessage.warning('请输入正确的手机号')
    return
  }
  if (countdown.value > 0) return

  sending.value = true
  errMsg.value = ''
  try {
    // 真接 API: POST /api/auth/sms-code
    const res = await api.post('/auth/sms-code', { phone: phone.value })
    if (res?.code === 0) {
      ElMessage.success(res.message || '验证码已发送')
      // 启动 60s 倒计时
      countdown.value = 60
      timer = setInterval(() => {
        countdown.value--
        if (countdown.value <= 0) {
          clearInterval(timer)
          timer = null
          sending.value = false
        }
      }, 1000)
    } else {
      ElMessage.error(res?.message || '发送失败，请稍后重试')
    }
  } catch (err) {
    ElMessage.error(err?.response?.data?.message || err?.message || '网络异常')
  } finally {
    if (countdown.value <= 0) sending.value = false
  }
}

async function login() {
  if (!isValidPhone.value) {
    ElMessage.warning('请输入正确的手机号')
    return
  }
  if (!code.value) {
    ElMessage.warning('请输入验证码')
    return
  }
  loading.value = true
  errMsg.value = ''
  try {
    // 真接 API: POST /api/auth/phone-login
    const res = await api.post('/auth/phone-login', {
      phone: phone.value,
      code: code.value
    })
    if (res?.code === 0) {
      const { token, user } = res.data || {}
      if (!token) throw new Error('服务端未返回 token')

      // 持久化
      localStorage.setItem('caimeite_token', token)
      localStorage.setItem('caimeite_user', JSON.stringify(user))

      ElMessage.success('登录成功')
      // 跳到 /minip/me（路由守卫会拦未登录）
      setTimeout(() => router.replace('/minip/me'), 300)
    } else {
      const msg = res?.message || '登录失败'
      errMsg.value = msg
      ElMessage.error(msg)
    }
  } catch (err) {
    const msg = err?.response?.data?.message || err?.message || '网络异常'
    errMsg.value = msg
    ElMessage.error(msg)
  } finally {
    loading.value = false
  }
}

import { onUnmounted } from 'vue'
onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<style scoped>
.login-bg {
  min-height: 100vh;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  padding: 40px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', sans-serif;
  color: #fff;
  max-width: 480px;
  margin: 0 auto;
}
.logo {
  font-size: 56px;
  margin-bottom: 8px;
}
h1 {
  font-size: 22px;
  font-weight: 700;
  margin: 0;
}
.sub {
  font-size: 13px;
  opacity: 0.85;
  margin: 4px 0 24px;
}
.card {
  background: #fff;
  color: #1f2329;
  border-radius: 16px;
  padding: 20px;
  width: 100%;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}
.card h2 {
  font-size: 16px;
  text-align: center;
  margin: 0 0 16px;
}
.form label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin: 8px 0 4px;
}
.form input {
  width: 100%;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  box-sizing: border-box;
}
.form input:focus {
  outline: none;
  border-color: #6366f1;
}
.code-row {
  display: flex;
  gap: 8px;
}
.code-row input {
  flex: 1;
}
.code-row button {
  padding: 12px 14px;
  background: #eef2ff;
  color: #6366f1;
  border: 0;
  border-radius: 8px;
  font-size: 12px;
  cursor: pointer;
  white-space: nowrap;
}
.code-row button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.login-btn {
  width: 100%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  border: 0;
  border-radius: 8px;
  padding: 12px;
  font-size: 14px;
  font-weight: 600;
  margin-top: 16px;
  cursor: pointer;
}
.login-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.err {
  color: #ef4444;
  font-size: 12px;
  margin: 12px 0 0;
  text-align: center;
}
.terms {
  font-size: 11px;
  margin-top: 16px;
  opacity: 0.85;
  text-align: center;
}
.terms span {
  text-decoration: underline;
}
</style>
