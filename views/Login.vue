<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { setLocale } from '../i18n/index.js'
import { useUserStore } from '../stores/user'
import { systemSettings } from '../stores/system'
import api from '../services/api'

// Migrate old localStorage keys on login page load
const oldToken = localStorage.getItem('gdq_token')
const oldUser = localStorage.getItem('gdq_user')
if (oldToken) {
  localStorage.setItem('caimeite_token', oldToken)
  localStorage.removeItem('gdq_token')
}
if (oldUser) {
  localStorage.setItem('caimeite_user', oldUser)
  localStorage.removeItem('gdq_user')
}

const router = useRouter()
const userStore = useUserStore()
const { t, locale: i18nLocale, messages: i18nMessages } = useI18n()

// ─── 页面加载时：检测微信授权回调code ───
const urlParams = new URLSearchParams(window.location.search)
const wxCode = urlParams.get('code')
if (wxCode) {
  // 微信回调来了，自动完成登录
  loading.value = true
  api.post('/auth/wx-h5-login', { code: wxCode }).then(res => {
    if (res.code === 0) {
      localStorage.setItem('caimeite_token', res.data.token)
      localStorage.setItem('caimeite_user', JSON.stringify(res.data.user))
      localStorage.setItem('caimeite_permissions', JSON.stringify(res.data.permissions || []))
      userStore.token = res.data.token
      userStore.user = res.data.user
      // 清理URL参数后跳转
      window.history.replaceState({}, '', window.location.pathname)
      router.push('/')
    } else {
      error.value = res.message || t('login.wxLoginFailed')
      loginMode.value = 'customer'
    }
  }).catch(() => {
    error.value = t('login.wxLoginFailed')
    loginMode.value = 'customer'
  }).finally(() => {
    loading.value = false
  })
}

// ─── 登录模式切换 ───
const loginMode = ref('employee') // 'employee' | 'customer'

const showRegister = ref(false)
const phone = ref('')
const password = ref(localStorage.getItem('caimeite_password') || '')
const rememberMe = ref(localStorage.getItem('caimeite_remember') === 'true')
const showPassword = ref(false)
const error = ref('')
const loading = ref(false)

// 顾客登录 - 手机验证码
const smsCode = ref('')
const smsCountdown = ref(0)
const smsLoading = ref(false)
let smsTimer = null

function openSendSms() {
  if (!phone.value || !/^\+?\d{6,20}$/.test(phone.value)) {
    error.value = t('login.phoneFormatError')
    return
  }
  smsLoading.value = true
  error.value = ''
  api.post('/auth/sms-code', { phone: phone.value }).then(res => {
    if (res.code === 0) {
      smsCountdown.value = 60
      smsTimer = setInterval(() => {
        smsCountdown.value--
        if (smsCountdown.value <= 0) clearInterval(smsTimer)
      }, 1000)
    } else {
      error.value = res.message || t('login.sendCodeFailed')
    }
  }).catch(e => {
    error.value = e.message || t('login.sendCodeFailed')
  }).finally(() => {
    smsLoading.value = false
  })
}

// 员工密码登录
async function handleLogin() {
  error.value = ''
  if (!phone.value || !password.value) {
    error.value = t('login.requiredFields')
    return
  }
  loading.value = true
  try {
    const res = await userStore.login(phone.value, password.value)
    if (res.code === 0) {
      if (rememberMe.value) {
        localStorage.setItem('caimeite_remember', 'true')
        localStorage.setItem('caimeite_phone', phone.value)
        localStorage.setItem('caimeite_password', password.value)
      } else {
        localStorage.setItem('caimeite_remember', 'false')
        localStorage.removeItem('caimeite_phone')
        localStorage.removeItem('caimeite_password')
      }
      router.push('/')
    } else {
      error.value = res.message || t('login.error')
    }
  } catch (e) {
    error.value = e.message || t('login.networkError')
  } finally {
    loading.value = false
  }
}

// 顾客手机验证码登录
async function handleCustomerLogin() {
  error.value = ''
  if (!phone.value || !smsCode.value) {
    error.value = t('login.enterCode')
    return
  }
  loading.value = true
  try {
    const res = await api.post('/auth/phone-login', { phone: phone.value, code: smsCode.value })
    if (res.code === 0) {
      localStorage.setItem('caimeite_token', res.data.token)
      localStorage.setItem('caimeite_user', JSON.stringify(res.data.user))
      localStorage.setItem('caimeite_permissions', JSON.stringify(res.data.permissions || []))
      userStore.token = res.data.token
      userStore.user = res.data.user
      router.push('/')
    } else {
      error.value = res.message || t('login.loginFailed')
    }
  } catch (e) {
    error.value = e.message || t('login.networkError')
  } finally {
    loading.value = false
  }
}

// 微信小程序登录（网页端唤起小程序）
function handleWxMpLogin() {
  // 环境检测：小程序环境直接调wx.login，非小程序环境提示
  if (typeof wx !== 'undefined' && wx.login) {
    wx.login({
      success: (res) => {
        if (res.code) {
          api.post('/auth/wx-mp-login', { code: res.code }).then(ret => {
            if (ret.code === 0) {
              localStorage.setItem('caimeite_token', ret.data.token)
              localStorage.setItem('caimeite_user', JSON.stringify(ret.data.user))
              localStorage.setItem('caimeite_permissions', JSON.stringify(ret.data.permissions || []))
              userStore.token = ret.data.token
              userStore.user = ret.data.user
              router.push('/')
            } else {
              error.value = ret.message || t('login.loginFailed')
            }
          })
        }
      },
      fail: () => {
        error.value = t('login.wxNotSupported')
      }
    })
  } else {
    // 提示用微信扫一扫
    error.value = t('login.wxScanTip')
  }
}

// 微信公众号H5静默授权登录（snsapi_base，无需用户点击确认）
function handleWxH5Login() {
  const appid = 'wx2947d27b4da69b1e'
  // 回调到自己这个登录页面，code会挂在URL参数上
  const redirectUri = encodeURIComponent(window.location.origin + window.location.pathname)
  const state = Math.random().toString(36).slice(2)
  sessionStorage.setItem('wx_oauth_state', state)
  window.location.href =
    `https://open.weixin.qq.com/connect/oauth2/authorize?appid=${appid}&redirect_uri=${redirectUri}&response_type=code&scope=snsapi_base&state=${state}#wechat_redirect`
}

// Apple登录
function handleAppleLogin() {
  if (typeof window.AppleID !== 'undefined') {
    window.AppleID.auth.signIn().then(res => {
      const idToken = res.authorization.id_token
      api.post('/auth/apple-login', { id_token: idToken }).then(ret => {
        if (ret.code === 0) {
          localStorage.setItem('caimeite_token', ret.data.token)
          localStorage.setItem('caimeite_user', JSON.stringify(ret.data.user))
          localStorage.setItem('caimeite_permissions', JSON.stringify(ret.data.permissions || []))
          userStore.token = ret.data.token
          userStore.user = ret.data.user
          router.push('/')
        } else {
          error.value = ret.message || t('login.loginFailed')
        }
      })
    }).catch(() => {
      error.value = t('login.appleNotSupported')
    })
  } else {
    error.value = t('login.appleNotSupported')
  }
}

// Google登录
function handleGoogleLogin() {
  // 触发Google登录按钮点击（由Google SDK渲染）
  const btn = document.querySelector('[data-g-interactive="signin"]')
  if (btn) btn.click()
  else error.value = t('login.googleNotConfigured')
}

// 注册表单
const registerForm = ref({
  name: '',
  phone: '',
  id_card: '',
  password: '',
  confirmPassword: ''
})
const registerError = ref('')
const registerLoading = ref(false)
const showRegPassword = ref(false)
const showRegConfirmPassword = ref(false)

async function handleRegister() {
  registerError.value = ''
  if (!registerForm.value.name || !registerForm.value.phone || !registerForm.value.password) {
    registerError.value = t('login.fillRequiredFields')
    return
  }
  if (!/^\+?\d{6,20}$/.test(registerForm.value.phone)) {
    registerError.value = t('login.phoneFormatError')
    return
  }
  if (registerForm.value.password.length < 6) {
    registerError.value = t('login.passwordTooShort')
    return
  }
  if (registerForm.value.password !== registerForm.value.confirmPassword) {
    registerError.value = t('login.passwordMismatch')
    return
  }
  registerLoading.value = true
  try {
    const res = await api.post('/auth/register-employee', {
      name: registerForm.value.name,
      phone: registerForm.value.phone,
      id_card: registerForm.value.id_card,
      password: registerForm.value.password
    })
    if (res.code === 0) {
      alert(t('login.registerSuccess').replace('{phone}', registerForm.value.phone))
      showRegister.value = false
      registerForm.value = { name: '', phone: '', id_card: '', password: '', confirmPassword: '' }
    } else {
      registerError.value = res.message || t('login.registerFailed')
    }
  } catch (e) {
    registerError.value = e.response?.data?.message || e.message || t('login.registerFailedRetry')
  } finally {
    registerLoading.value = false
  }
}

// 忘记密码
const showForgotPassword = ref(false)
const forgotPhone = ref('')
const forgotCode = ref('')
const forgotNewPassword = ref('')
const forgotConfirmPassword = ref('')
const forgotError = ref('')
const forgotLoading = ref(false)
const forgotCountdown = ref(0)
const showForgotNewPwd = ref(false)
const showForgotConfirmPwd = ref(false)
let countdownTimer = null

function openForgotPassword() {
  showForgotPassword.value = true
  forgotPhone.value = ''
  forgotCode.value = ''
  forgotNewPassword.value = ''
  forgotConfirmPassword.value = ''
  forgotError.value = ''
  forgotCountdown.value = 0
  if (countdownTimer) clearInterval(countdownTimer)
}

async function sendForgotCode() {
  forgotError.value = ''
  if (!forgotPhone.value) {
    forgotError.value = t('login.enterPhone')
    return
  }
  forgotLoading.value = true
  try {
    const res = await api.post('/auth/send-code', { phone: forgotPhone.value })
    if (res.code === 0) {
      forgotCountdown.value = 60
      countdownTimer = setInterval(() => {
        forgotCountdown.value--
        if (forgotCountdown.value <= 0) clearInterval(countdownTimer)
      }, 1000)
    } else {
      forgotError.value = res.message || t('login.sendCodeFailed')
    }
  } catch (e) {
    forgotError.value = e.message || t('login.sendCodeFailed')
  } finally {
    forgotLoading.value = false
  }
}

async function handleResetPassword() {
  forgotError.value = ''
  if (!forgotCode.value) { forgotError.value = t('login.enterCode'); return }
  if (!forgotNewPassword.value || forgotNewPassword.value.length < 6) { forgotError.value = t('login.passwordTooShort'); return }
  if (forgotNewPassword.value !== forgotConfirmPassword.value) { forgotError.value = t('login.passwordMismatch'); return }
  forgotLoading.value = true
  try {
    const res = await api.post('/auth/reset-password', {
      phone: forgotPhone.value,
      code: forgotCode.value,
      new_password: forgotNewPassword.value
    })
    if (res.code === 0) {
      alert(t('login.resetSuccess'))
      showForgotPassword.value = false
    } else {
      forgotError.value = res.message || t('login.resetFailed')
    }
  } catch (e) {
    forgotError.value = e.message || t('login.resetFailed')
  } finally {
    forgotLoading.value = false
  }
}

// 语言切换
const localeCycle = computed(() => systemSettings.languages)
const localeLabels = computed(() => {
  const labels = {}
  for (const l of systemSettings.languages) {
    labels[l] = l === 'zh' ? '中文' : l === 'en' ? 'English' : l
  }
  return labels
})
const showLangDropdown = ref(false)

async function switchLocale(lang) {
  showLangDropdown.value = false
  if (lang === i18nLocale.value) return
  // 用 i18n 的 setLocale（动态 import 包 + 切 locale + 存 localStorage）
  // 无需 reload — Vue 响应式会自动重渲染
  await setLocale(lang)
}

function langLabel(l) {
  return localeLabels[l] || l.toUpperCase()
}
</script>

<template>
  <div class="min-h-screen flex flex-col bg-gradient-to-br from-slate-50 to-blue-50 relative overflow-x-hidden">
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-[10%] -right-[10%] w-[50%] h-[50%] rounded-full bg-primary/5 blur-[100px]"></div>
      <div class="absolute -bottom-[10%] -left-[10%] w-[40%] h-[40%] rounded-full bg-primary/10 blur-[80px]"></div>
    </div>

    <main class="relative z-10 flex-grow flex items-center justify-center p-3 sm:p-4 md:p-6 lg:p-8">
      <div class="w-full max-w-[460px] bg-white rounded-xl shadow-card overflow-hidden">

        <!-- Header -->
        <div class="pt-6 sm:pt-8 md:pt-10 pb-3 sm:pb-4 md:pb-6 px-4 sm:px-6 md:px-8 text-center relative">
          <!-- Language Toggle -->
          <div class="absolute top-4 right-4 relative">
            <button @click="showLangDropdown = !showLangDropdown" class="flex items-center gap-1 sm:gap-1.5 px-2 sm:px-3 py-1.5 sm:py-2 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-medium text-slate-700 hover:border-primary transition-all">
              <span class="material-symbols-outlined text-[16px] sm:text-[18px]">language</span>
              <span class="hidden sm:inline">{{ langLabel(i18nLocale) }}</span>
              <span class="sm:hidden">{{ langLabel(i18nLocale) }}</span>
              <span v-if="localeCycle.length > 2" class="material-symbols-outlined text-[14px]">expand_more</span>
            </button>
            <div v-if="showLangDropdown" class="absolute right-0 mt-1 w-28 bg-white rounded-lg shadow-lg border z-50 py-1" @click.stop>
              <button
                v-for="lang in localeCycle"
                :key="lang"
                @click="switchLocale(lang)"
                class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50 transition-colors"
                :class="lang === i18nLocale ? 'text-primary font-semibold' : 'text-text-primary'"
              >
                {{ langLabel(lang) }}
              </button>
            </div>
          </div>

          <div class="flex justify-center mb-2 sm:mb-3 md:mb-4">
            <img src="/logo.jpg" alt="logo" class="h-36 w-36 sm:h-42 sm:w-42 md:h-48 md:w-48 object-contain rounded-lg" />
          </div>
          <h1 class="text-lg sm:text-xl md:text-2xl font-bold tracking-tight text-slate-900 mb-1">{{ $t('system.fullName') }}</h1>
          <p class="text-xs sm:text-sm text-slate-500">{{ $t('system.motto') }}</p>
        </div>

        <!-- 模式切换 Tabs -->
        <div class="px-4 sm:px-6 md:px-8 pb-2 text-center">
          <div class="inline-flex bg-slate-100 rounded-xl p-1 gap-1">
            <button
              @click="loginMode = 'employee'; error = ''"
              class="px-5 py-2 rounded-lg text-sm font-medium transition-all"
              :class="loginMode === 'employee' ? 'bg-white text-primary shadow-sm' : 'text-slate-500 hover:text-slate-700'"
            >
              {{ $t('login.employeeLogin') || '员工登录' }}
            </button>
            <button
              @click="loginMode = 'customer'; error = ''"
              class="px-5 py-2 rounded-lg text-sm font-medium transition-all"
              :class="loginMode === 'customer' ? 'bg-white text-primary shadow-sm' : 'text-slate-500 hover:text-slate-700'"
            >
              {{ $t('login.customerLogin') || '顾客登录' }}
            </button>
          </div>
        </div>

        <div class="px-4 sm:px-6 md:px-8 pb-6 sm:pb-8 md:pb-10">

          <!-- ─── 员工登录（手机号+密码）─── -->
          <div v-if="loginMode === 'employee'">
            <form @submit.prevent="handleLogin" class="space-y-3 sm:space-y-4 md:space-y-5 mt-2">
              <div class="space-y-1 sm:space-y-1.5">
                <label class="block text-xs sm:text-sm font-medium text-slate-700" for="account">{{ $t('login.account') }}</label>
                <div class="relative flex items-center w-full rounded-lg border border-slate-300 overflow-hidden focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-colors">
                  <div class="pl-2 sm:pl-3 text-slate-400 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[16px] sm:text-[18px] md:text-[20px]">person</span>
                  </div>
                  <input v-model="phone" type="text" id="account" :placeholder="$t('login.accountPlaceholder')" class="w-full border-none bg-transparent py-2 sm:py-2.5 pl-1 sm:pl-2 pr-3 sm:pr-4 text-[16px] text-slate-900 placeholder:text-slate-400 focus:ring-0 focus:outline-none" />
                </div>
              </div>
              <div class="space-y-1 sm:space-y-1.5">
                <div class="flex items-center justify-between">
                  <label class="text-xs sm:text-sm font-medium text-slate-700" for="password">{{ $t('login.password') }}</label>
                </div>
                <div class="relative flex items-center w-full rounded-lg border border-slate-300 overflow-hidden focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-colors">
                  <div class="pl-2 sm:pl-3 text-slate-400 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[16px] sm:text-[18px] md:text-[20px]">lock</span>
                  </div>
                  <input v-model="password" :type="showPassword ? 'text' : 'password'" id="password" :placeholder="$t('login.passwordPlaceholder')" class="w-full border-none bg-transparent py-2 sm:py-2.5 pl-1 sm:pl-2 pr-8 sm:pr-10 text-[16px] text-slate-900 placeholder:text-slate-400 focus:ring-0 focus:outline-none" />
                  <button type="button" @click="showPassword = !showPassword" class="absolute right-2 sm:right-3 text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined text-[16px] sm:text-[18px] md:text-[20px]">{{ showPassword ? 'visibility' : 'visibility_off' }}</span>
                  </button>
                </div>
                <div class="text-right">
                  <span role="button" tabindex="0" @click="openForgotPassword" @keydown.enter="openForgotPassword" class="text-xs font-medium text-primary hover:text-primary-hover hover:underline cursor-pointer inline-block">{{ $t('login.forgotPassword') }}</span>
                </div>
              </div>
              <div class="flex items-center">
                <input type="checkbox" v-model="rememberMe" id="rememberMe" class="w-4 h-4 accent-primary" />
                <label for="rememberMe" class="ml-2 text-xs sm:text-sm text-slate-600 cursor-pointer select-none">{{ $t('login.rememberMe') }}</label>
              </div>
              <div v-if="error" class="text-xs sm:text-sm text-red-500 text-center bg-red-50 py-2 px-2 sm:px-3 rounded-lg">{{ error }}</div>
              <button type="submit" :disabled="loading" class="w-full flex justify-center py-2 sm:py-2.5 md:py-3 px-3 sm:px-4 border border-transparent rounded-lg shadow-sm text-xs sm:text-sm font-bold text-white bg-primary hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-all duration-200 active:scale-[0.98] disabled:opacity-50">
                {{ loading ? $t('login.loggingIn') : $t('login.loginBtn') }}
              </button>
            </form>
            <div class="mt-4 sm:mt-5 md:mt-6 text-center">
              <button @click="showRegister = true" class="text-sm text-primary hover:text-primary-hover font-medium">
                {{ $t('login.employeeRegister') }}
              </button>
            </div>
          </div>

          <!-- ─── 顾客登录（手机验证码 + 第三方）─── -->
          <div v-if="loginMode === 'customer'">
            <!-- 第三方快捷登录 -->
            <div class="space-y-2 mb-5">
              <p class="text-xs text-slate-400 text-center mb-3">{{ $t('login.thirdPartyLogin') }}</p>

              <!-- 微信公众号H5扫码登录 -->
              <button @click="handleWxH5Login" class="w-full flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg border border-slate-200 hover:bg-green-50 hover:border-green-300 transition-all text-sm font-medium text-slate-700">
                <svg class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="currentColor"><path d="M8.5 11.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm7 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM8 16c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm8 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm-4 4c-2 0-3.75-1.1-4.75-2.75L.59 19.5l1.5-1 1.5 1.5-1 1.5 1 1.5 2.41-2.25C6.5 21.5 7.15 22 8 22c.55 0 1.05-.15 1.5-.4L11 24h2l1.5-2.4c.45.25.95.4 1.5.4.85 0 1.5-.5 1.59-1.19L17 24h2l-1.16-2.25C19.75 23.9 18 25 16 25c-2.76 0-5-2.24-5-5z"/></svg>
                {{ $t('login.wxH5Login') }}
              </button>

              <!-- 微信小程序登录 -->
              <button @click="handleWxMpLogin" class="w-full flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg border border-slate-200 hover:bg-slate-50 transition-all text-sm font-medium text-slate-600">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM8 18c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm4-3c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm4 3c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z"/></svg>
                {{ $t('login.wxMpLogin') }}
              </button>

              <div class="flex gap-2">
                <!-- Apple登录 -->
                <button @click="handleAppleLogin" class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg border border-slate-200 hover:bg-black hover:border-black transition-all text-sm font-medium text-slate-700 hover:text-white">
                  <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
                  {{ $t('login.appleLogin') }}
                </button>
                <!-- Google登录 -->
                <button @click="handleGoogleLogin" class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg border border-slate-200 hover:bg-red-50 hover:border-red-300 transition-all text-sm font-medium text-slate-700">
                  <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                  {{ $t('login.googleLogin') }}
                </button>
              </div>
            </div>

            <!-- 分隔线 -->
            <div class="flex items-center gap-3 mb-5">
              <div class="flex-1 h-px bg-slate-200"></div>
              <span class="text-xs text-slate-400">{{ $t('login.or') }}</span>
              <div class="flex-1 h-px bg-slate-200"></div>
            </div>

            <!-- 手机号+验证码登录 -->
            <form @submit.prevent="handleCustomerLogin" class="space-y-3">
              <div>
                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">{{ $t('login.phone') }}</label>
                <div class="relative flex items-center w-full rounded-lg border border-slate-300 overflow-hidden focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-colors">
                  <div class="pl-2 sm:pl-3 text-slate-400 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[16px] sm:text-[18px]">phone</span>
                  </div>
                  <input v-model="phone" type="tel" :placeholder="$t('login.phonePlaceholder')" class="w-full border-none bg-transparent py-2.5 pl-1 sm:pl-2 pr-3 text-[16px] text-slate-900 placeholder:text-slate-400 focus:ring-0 focus:outline-none" />
                </div>
              </div>

              <div>
                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1">{{ $t('login.smsCode') }}</label>
                <div class="flex gap-2">
                  <div class="relative flex-1 flex items-center w-full rounded-lg border border-slate-300 overflow-hidden focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-colors">
                    <div class="pl-2 sm:pl-3 text-slate-400 flex items-center justify-center shrink-0">
                      <span class="material-symbols-outlined text-[16px] sm:text-[18px]">pin</span>
                    </div>
                    <input v-model="smsCode" type="text" maxlength="6" :placeholder="$t('login.smsCodePlaceholder')" class="w-full border-none bg-transparent py-2.5 pl-1 sm:pl-2 pr-3 text-[16px] text-slate-900 placeholder:text-slate-400 focus:ring-0 focus:outline-none" />
                  </div>
                  <button type="button" @click="openSendSms" :disabled="smsLoading || smsCountdown > 0" class="shrink-0 px-3 py-2.5 text-sm rounded-lg border border-slate-300 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed font-medium text-slate-700">
                    {{ smsCountdown > 0 ? `${smsCountdown}s` : (smsLoading ? $t('login.sending') : $t('login.sendCode')) }}
                  </button>
                </div>
              </div>

              <div v-if="error" class="text-xs text-red-500 text-center bg-red-50 py-2 px-3 rounded-lg">{{ error }}</div>

              <button type="submit" :disabled="loading" class="w-full flex justify-center py-2.5 md:py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-primary hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-all duration-200 active:scale-[0.98] disabled:opacity-50">
                {{ loading ? $t('login.loggingIn') : $t('login.loginBtn') }}
              </button>

              <p class="text-xs text-slate-400 text-center">{{ $t('login.customerLoginHint') || '未注册用户将自动创建账号' }}</p>
            </form>
          </div>

        </div>
      </div>
    </main>

    <!-- 员工注册弹窗 -->
    <Teleport to="body">
      <div v-if="showRegister" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[9999] p-4" @click.self="showRegister = false">
        <div class="w-full max-w-md bg-white rounded-xl shadow-2xl overflow-hidden">
          <div class="p-6 border-b border-gray-100">
            <div class="flex items-center justify-between">
              <h2 class="text-xl font-bold text-slate-900">{{ $t('login.registerTitle') }}</h2>
              <button type="button" @click="showRegister = false" class="text-slate-400 hover:text-slate-600">
                <span class="material-symbols-outlined">arrow_back</span>
              </button>
            </div>
            <p class="text-sm text-slate-500 mt-2">{{ $t('login.registerSubtitle') }}</p>
          </div>
          <form @submit.prevent="handleRegister" class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('login.name') }} *</label>
              <input v-model="registerForm.name" type="text" :placeholder="$t('login.namePlaceholder')" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('login.phone') }} *</label>
              <input v-model="registerForm.phone" type="tel" :placeholder="$t('login.phonePlaceholder')" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('login.idCardOptional') }}</label>
              <input v-model="registerForm.id_card" type="text" :placeholder="$t('login.idCardPlaceholder')" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('login.setPassword') }} *</label>
              <div class="relative">
                <input v-model="registerForm.password" :type="showRegPassword ? 'text' : 'password'" :placeholder="$t('login.passwordMinLength')" class="w-full px-3 py-2 pr-10 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                <button type="button" @click="showRegPassword = !showRegPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                  <span class="material-symbols-outlined text-[20px]">{{ showRegPassword ? 'visibility' : 'visibility_off' }}</span>
                </button>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('login.confirmPassword') }} *</label>
              <div class="relative">
                <input v-model="registerForm.confirmPassword" :type="showRegConfirmPassword ? 'text' : 'password'" :placeholder="$t('login.confirmPasswordPlaceholder')" class="w-full px-3 py-2 pr-10 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                <button type="button" @click="showRegConfirmPassword = !showRegConfirmPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                  <span class="material-symbols-outlined text-[20px]">{{ showRegConfirmPassword ? 'visibility' : 'visibility_off' }}</span>
                </button>
              </div>
            </div>
            <div v-if="registerError" class="text-sm text-red-500 bg-red-50 py-2 px-3 rounded-lg">{{ registerError }}</div>
            <div class="flex gap-3 pt-2">
              <button type="button" @click="showRegister = false" class="flex-1 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">
                {{ $t('common.cancel') }}
              </button>
              <button type="submit" :disabled="registerLoading" class="flex-1 py-2.5 bg-primary text-white rounded-lg text-sm font-bold hover:bg-primary-hover disabled:opacity-50">
                {{ registerLoading ? $t('login.submitting') : $t('login.submitApplication') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- 忘记密码弹窗 -->
    <Teleport to="body">
      <div v-if="showForgotPassword" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[9999] p-4" @click.self="showForgotPassword = false">
        <div class="w-full max-w-md bg-white rounded-xl shadow-2xl overflow-hidden">
          <div class="p-6 border-b border-gray-100">
            <div class="flex items-center justify-between">
              <h2 class="text-xl font-bold text-slate-900">{{ $t('login.forgotPassword') }}</h2>
              <button type="button" @click="showForgotPassword = false" class="text-slate-400 hover:text-slate-600">
                <span class="material-symbols-outlined">arrow_back</span>
              </button>
            </div>
            <p class="text-sm text-slate-500 mt-2">{{ $t('login.forgotPasswordHint') }}</p>
          </div>
          <div class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('login.phone') }}</label>
              <input v-model="forgotPhone" type="tel" :placeholder="$t('login.phonePlaceholder')" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('login.smsCode') }}</label>
              <div class="flex gap-2">
                <input v-model="forgotCode" type="text" maxlength="6" :placeholder="$t('login.smsCodePlaceholder')" class="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                <button type="button" @click="sendForgotCode" :disabled="forgotLoading || forgotCountdown > 0" class="shrink-0 px-3 py-2 text-sm rounded-lg border border-slate-300 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                  {{ forgotCountdown > 0 ? `${forgotCountdown}s` : $t('login.sendCode') }}
                </button>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('login.newPassword') }}</label>
              <div class="relative">
                <input v-model="forgotNewPassword" :type="showForgotNewPwd ? 'text' : 'password'" :placeholder="$t('login.passwordMinLength')" class="w-full px-3 py-2 pr-10 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                <button type="button" @click="showForgotNewPwd = !showForgotNewPwd" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
<span class="material-symbols-outlined text-[20px]">{{ showForgotNewPwd ? 'visibility' : 'visibility_off' }}</span>
                </button>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('login.confirmPassword') }}</label>
              <div class="relative">
                <input v-model="forgotConfirmPassword" :type="showForgotConfirmPwd ? 'text' : 'password'" :placeholder="$t('login.confirmPasswordPlaceholder')" class="w-full px-3 py-2 pr-10 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                <button type="button" @click="showForgotConfirmPwd = !showForgotConfirmPwd" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
<span class="material-symbols-outlined text-[20px]">{{ showForgotConfirmPwd ? 'visibility' : 'visibility_off' }}</span>
                </button>
              </div>
            </div>
            <div v-if="forgotError" class="text-sm text-red-500 bg-red-50 py-2 px-3 rounded-lg">{{ forgotError }}</div>
            <div class="flex gap-3 pt-2">
              <button type="button" @click="showForgotPassword = false" class="flex-1 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">
                {{ $t('common.cancel') }}
              </button>
              <button type="button" @click="handleResetPassword" :disabled="forgotLoading" class="flex-1 py-2.5 bg-primary text-white rounded-lg text-sm font-bold hover:bg-primary-hover disabled:opacity-50">
                {{ forgotLoading ? $t('login.submitting') : $t('login.resetPassword') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <footer class="relative z-10 py-3 sm:py-4 md:py-6 text-center">
      <p class="text-xs text-slate-400">&copy; 2024 {{ $t('system.companyName') }}. All rights reserved.</p>
    </footer>
  </div>
</template>
