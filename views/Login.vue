<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import i18n from '../i18n/index.js'
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
const { t } = useI18n()

const showRegister = ref(false)
const account = ref('')
const password = ref(localStorage.getItem('caimeite_password') || '')
const rememberMe = ref(localStorage.getItem('caimeite_remember') === 'true')
const showPassword = ref(false)
const error = ref('')
const loading = ref(false)

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
  if (lang === i18n.global.locale.value) return
  localStorage.setItem('caimeite_locale', lang)
  window.location.reload()
}

function langLabel(l) {
  return localeLabels[l] || l.toUpperCase()
}

async function handleLogin() {
  error.value = ''

  // Validate inputs
  if (!account.value || !password.value) {
    error.value = t('login.requiredFields')
    return
  }

  loading.value = true
  try {
    const res = await userStore.login(account.value, password.value)
    if (res.code === 0) {
      if (rememberMe.value) { localStorage.setItem('caimeite_remember', 'true'); localStorage.setItem('caimeite_account', account.value); localStorage.setItem('caimeite_password', password.value) } else { localStorage.setItem('caimeite_remember', 'false'); localStorage.removeItem('caimeite_account'); localStorage.removeItem('caimeite_password') }
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

async function handleDingTalkLogin() {
  error.value = ''
  loading.value = true
  try {
    // TODO: Implement actual DingTalk OAuth flow
    // For now, redirect to DingTalk authorization
    error.value = t('login.dingtalkNotConfigured')
  } catch (e) {
    error.value = e.message || t('login.networkError')
  } finally {
    loading.value = false
  }
}

async function handleRegister() {
  registerError.value = ''

  // 验证必填项（身份证改为可选）
  if (!registerForm.value.name || !registerForm.value.phone || !registerForm.value.password) {
    registerError.value = t('login.fillRequiredFields')
    return
  }

  // 验证手机号（支持国内外手机号，至少6位）
  if (!/^\+?\d{6,20}$/.test(registerForm.value.phone)) {
    registerError.value = t('login.phoneFormatError')
    return
  }

  // 验证身份证（可选，6位以上即可，支持国际）
  if (registerForm.value.id_card && registerForm.value.id_card.length < 6) {
    registerError.value = t('login.idCardMinLength')
    return
  }

  // 验证密码
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
          <!-- Language Toggle - Dropdown for 3 languages -->
          <div class="absolute top-4 right-4 relative">
            <button @click="showLangDropdown = !showLangDropdown" class="flex items-center gap-1 sm:gap-1.5 px-2 sm:px-3 py-1.5 sm:py-2 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-medium text-slate-700 hover:border-primary transition-all">
              <span class="material-symbols-outlined text-[16px] sm:text-[18px]">language</span>
              <span class="hidden sm:inline">{{ langLabel(i18n.global.locale.value) }}</span>
              <span class="sm:hidden">{{ langLabel(i18n.global.locale.value) }}</span>
              <span v-if="localeCycle.length > 2" class="material-symbols-outlined text-[14px]">expand_more</span>
            </button>
            <div v-if="showLangDropdown" class="absolute right-0 mt-1 w-28 bg-white rounded-lg shadow-lg border z-50 py-1" @click.stop>
              <button
                v-for="lang in localeCycle"
                :key="lang"
                @click="switchLocale(lang)"
                class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50 transition-colors"
                :class="lang === i18n.global.locale.value ? 'text-primary font-semibold' : 'text-text-primary'"
              >
                {{ langLabel(lang) }}
              </button>
            </div>
          </div>

          <div class="flex justify-center mb-2 sm:mb-3 md:mb-4">
            <img src="/mywh3-logo.jpg" alt="logo" class="h-36 w-36 sm:h-42 sm:w-42 md:h-48 md:w-48 object-contain rounded-lg" />
          </div>
          <h1 class="text-lg sm:text-xl md:text-2xl font-bold tracking-tight text-slate-900 mb-1">{{ $t('system.fullName') }}</h1>
          <p class="text-xs sm:text-sm text-slate-500">{{ $t('system.motto') }}</p>
        </div>
        <div class="px-4 sm:px-6 md:px-8 pb-6 sm:pb-8 md:pb-10">
          <!-- Form -->
          <form @submit.prevent="handleLogin" class="space-y-3 sm:space-y-4 md:space-y-5 mt-2">
            <div class="space-y-1 sm:space-y-1.5">
              <label class="block text-xs sm:text-sm font-medium text-slate-700" for="email">{{ $t('login.email') }}</label>
              <div class="relative flex items-center w-full rounded-lg border border-slate-300 overflow-hidden focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-colors">
                <div class="pl-2 sm:pl-3 text-slate-400 flex items-center justify-center">
                  <span class="material-symbols-outlined text-[16px] sm:text-[18px] md:text-[20px]">person</span>
                </div>
                <input v-model="account" type="text" id="account" :placeholder="$t('login.accountPlaceholder')" class="w-full border-none bg-transparent py-2 sm:py-2.5 pl-1 sm:pl-2 pr-3 sm:pr-4 text-[16px] text-slate-900 placeholder:text-slate-400 focus:ring-0 focus:outline-none" />
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
            <!-- 手机号 -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('login.phone') }}</label>
              <input v-model="forgotPhone" type="tel" :placeholder="$t('login.phonePlaceholder')" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <!-- 验证码 -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('login.smsCode') }}</label>
              <div class="flex gap-2">
                <input v-model="forgotCode" type="text" maxlength="6" :placeholder="$t('login.smsCodePlaceholder')" class="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                <button type="button" @click="sendForgotCode" :disabled="forgotLoading || forgotCountdown > 0" class="shrink-0 px-3 py-2 text-sm rounded-lg border border-slate-300 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                  {{ forgotCountdown > 0 ? `${forgotCountdown}s` : $t('login.sendCode') }}
                </button>
              </div>
            </div>
            <!-- 新密码 -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('login.newPassword') }}</label>
              <div class="relative">
                <input v-model="forgotNewPassword" :type="showForgotNewPwd ? 'text' : 'password'" :placeholder="$t('login.passwordMinLength')" class="w-full px-3 py-2 pr-10 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                <button type="button" @click="showForgotNewPwd = !showForgotNewPwd" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
<span class="material-symbols-outlined text-[20px]">{{ showForgotNewPwd ? 'visibility' : 'visibility_off' }}</span>
                </button>
              </div>
            </div>
            <!-- 确认密码 -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">{{ $t('login.confirmPassword') }}</label>
              <div class="relative">
                <input v-model="forgotConfirmPassword" :type="showForgotConfirmPwd ? 'text' : 'password'" :placeholder="$t('login.confirmPasswordPlaceholder')" class="w-full px-3 py-2 pr-10 border border-slate-300 rounded-lg focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                <button type="button" @click="showForgotConfirmPwd = !showForgotConfirmPwd" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
<span class="material-symbols-outlined text-[20px]">{{ showForgotConfirmPassword ? 'visibility' : 'visibility_off' }}</span>
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
