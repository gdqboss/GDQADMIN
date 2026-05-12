<template>
  <div class="login-container">
    <!-- Language toggle -->
    <div class="lang-toggle">
      <button class="lang-btn" @click="toggleLocale">
        <span class="material-symbols-outlined">language</span>
        <span>{{ locale === 'zh' ? 'EN' : '中' }}</span>
      </button>
    </div>

    <main class="login-main">
      <div class="login-card">
        <!-- Header -->
        <div class="login-header">
          <span class="material-symbols-outlined login-icon">inventory_2</span>
          <h1 class="login-title">🛒 彩美特管理系统</h1>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleLogin" class="login-form">
          <!-- Account -->
          <div class="form-item">
            <label class="form-label">邮箱或手机号</label>
            <el-input
              v-model="loginForm.email"
              placeholder="请输入邮箱或手机号"
              prefix-icon="User"
              size="large"
            />
          </div>

          <!-- Password -->
          <div class="form-item">
            <label class="form-label">密码</label>
            <el-input
              v-model="loginForm.password"
              :type="showPassword ? 'text' : 'password'"
              placeholder="请输入密码"
              prefix-icon="Lock"
              size="large"
              show-password
            />
          </div>

          <!-- Options -->
          <div class="form-options">
            <label class="remember-label">
              <input type="checkbox" v-model="loginForm.remember" class="remember-checkbox" />
              <span>记住账号密码</span>
            </label>
            <button type="button" class="forgot-btn">忘记密码?</button>
          </div>

          <!-- Error -->
          <div v-if="errorMsg" class="error-msg">
            {{ errorMsg }}
          </div>

          <!-- Submit -->
          <el-button
            type="primary"
            :loading="loading"
            class="submit-btn"
            size="large"
            @click="handleLogin"
          >
            {{ loading ? '登录中...' : '登录' }}
          </el-button>

          <!-- Register -->
          <div class="register-link">
            <button type="button" class="text-btn">员工注册申请</button>
          </div>
        </form>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, getCurrentInstance } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useUserStore } from '@/stores/user'

const { proxy } = getCurrentInstance()
const locale = ref(proxy.$locale || 'zh')
const toggleLocale = () => {
  locale.value = locale.value === 'zh' ? 'en' : 'zh'
  proxy.$locale = locale.value
  localStorage.setItem('caimeite_locale', locale.value)
}

const router = useRouter()
const userStore = useUserStore()
const loginForm = ref({
  email: '',
  password: '',
  remember: false
})
const showPassword = ref(false)
const loading = ref(false)
const errorMsg = ref('')

const handleLogin = async () => {
  if (!loginForm.value.email || !loginForm.value.password) {
    errorMsg.value = '请输入账号和密码'
    return
  }
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: loginForm.value.email,
        password: loginForm.value.password
      })
    })
    const data = await res.json()
    if (data.code === 0) {
      await userStore.login(data.data.token, data.data.user)
      if (loginForm.value.remember) {
        localStorage.setItem('caimeite_account', loginForm.value.email)
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

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: linear-gradient(135deg, #667eea, #764ba2);
}

.lang-toggle {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 10;
}

.lang-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.lang-btn:hover {
  background: rgba(255, 255, 255, 0.2);
}

.lang-btn .material-symbols-outlined {
  font-size: 18px;
}

.login-main {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.login-card {
  width: 100%;
  max-width: 400px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  overflow: hidden;
}

.login-header {
  padding: 32px 24px 24px;
  text-align: center;
}

.login-icon {
  font-size: 40px;
  color: #667eea;
  margin-bottom: 8px;
}

.login-title {
  font-size: 20px;
  font-weight: bold;
  color: #1a1a2e;
  margin: 0;
}

.login-form {
  padding: 0 24px 32px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 14px;
  font-weight: 500;
  color: #475569;
}

.form-options {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.remember-label {
  display: flex;
  align-items: center;
  cursor: pointer;
  font-size: 14px;
  color: #64748b;
}

.remember-checkbox {
  width: 16px;
  height: 16px;
  margin-right: 8px;
  accent-color: #667eea;
}

.forgot-btn {
  font-size: 14px;
  color: #667eea;
  background: none;
  border: none;
  cursor: pointer;
}

.forgot-btn:hover {
  color: #764ba2;
}

.error-msg {
  font-size: 14px;
  color: #ef4444;
  background: #fef2f2;
  padding: 10px 12px;
  border-radius: 8px;
  text-align: center;
}

.submit-btn {
  width: 100%;
  height: 44px;
  background: linear-gradient(135deg, #667eea, #764ba2) !important;
  border: none !important;
  font-size: 16px;
  font-weight: 500;
}

.submit-btn:hover {
  opacity: 0.9;
}

.register-link {
  text-align: center;
  padding-top: 8px;
}

.text-btn {
  font-size: 14px;
  color: #94a3b8;
  background: none;
  border: none;
  cursor: pointer;
}

.text-btn:hover {
  color: #64748b;
}
</style>
