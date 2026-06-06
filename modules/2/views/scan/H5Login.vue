<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

// Tab 切换: 'sms' | 'password'
const activeTab = ref('sms')

// 验证码登录
const smsPhone = ref('')
const smsCode = ref('')
const smsError = ref('')
const smsLoading = ref(false)
const smsSending = ref(false)
const smsCountdown = ref(0)

// 密码登录
const pwdPhone = ref('')
const pwdPassword = ref('')
const pwdError = ref('')
const pwdLoading = ref(false)

// 隐私协议
const agreePrivacy = ref(false)

// 发送验证码（验证码登录）
async function sendSmsCode() {
  smsError.value = ''
  if (!smsPhone.value) { smsError.value = t('scan.enterPhone'); return }
  if (!/^1[3-9]\d{9}$/.test(smsPhone.value)) { smsError.value = t('scan.phoneInvalid'); return }

  smsSending.value = true
  try {
    const res = await fetch('/api/h5/send-sms', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ phone: smsPhone.value }),
    })
    const json = await res.json()
    if (json.code === 0) {
      smsCountdown.value = 60
      const timer = setInterval(() => {
        smsCountdown.value--
        if (smsCountdown.value <= 0) clearInterval(timer)
      }, 1000)
    } else {
      smsError.value = json.message || t('scan.sendFailed')
    }
  } catch { smsError.value = t('scan.networkError') }
  finally { smsSending.value = false }
}

// 验证码登录
async function loginWithSms() {
  smsError.value = ''
  if (!smsPhone.value || !smsCode.value) { smsError.value = t('scan.enterPhoneAndCode'); return }
  if (!agreePrivacy.value) { smsError.value = t('scan.agreePrivacyFirst'); return }

  smsLoading.value = true
  try {
    const res = await fetch('/api/h5/login-sms', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ phone: smsPhone.value, code: smsCode.value }),
    })
    const json = await res.json()
    if (json.code === 0) {
      localStorage.setItem('h5_token', json.data.token)
      localStorage.setItem('h5_user', JSON.stringify({ id: json.data.id, name: json.data.name, phone: json.data.phone }))
      const redirect = route.query.redirect || '/'
      router.replace(redirect)
    } else {
      smsError.value = json.message || t('scan.loginFailed')
    }
  } catch { smsError.value = t('scan.networkError') }
  finally { smsLoading.value = false }
}

// 密码登录
async function loginWithPassword() {
  pwdError.value = ''
  if (!pwdPhone.value || !pwdPassword.value) { pwdError.value = t('scan.enterPhoneAndPassword'); return }
  if (!/^1[3-9]\d{9}$/.test(pwdPhone.value)) { pwdError.value = t('scan.phoneInvalid'); return }
  if (!agreePrivacy.value) { pwdError.value = t('scan.agreePrivacyFirst'); return }

  pwdLoading.value = true
  try {
    const res = await fetch('/api/h5/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ phone: pwdPhone.value, password: pwdPassword.value }),
    })
    const json = await res.json()
    if (json.code === 0) {
      localStorage.setItem('h5_token', json.data.token)
      localStorage.setItem('h5_user', JSON.stringify({ id: json.data.id, name: json.data.name, phone: json.data.phone }))
      const redirect = route.query.redirect || '/'
      router.replace(redirect)
    } else {
      pwdError.value = json.message || t('scan.loginFailed')
    }
  } catch { pwdError.value = t('scan.networkError') }
  finally { pwdLoading.value = false }
}

// 导航到注册页面
function goToRegister() {
  router.push('/h5/register')
}

// 导航到忘记密码页面
function goToForgotPassword() {
  router.push('/h5/forgot-password')
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4 py-10">
    <!-- Logo + Register link -->
    <div class="w-full max-w-sm mb-6 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-[#1890ff] shadow-md">
          <span class="text-white font-bold text-lg">GDQ</span>
        </div>
        <span class="text-gray-700 font-medium">{{ $t('scan.tracePlatform') }}</span>
      </div>
      <button @click="goToRegister" class="text-sm text-gray-500 hover:text-[#1890ff]">
        {{ $t('scan.registerLink') }}
      </button>
    </div>

    <!-- Card -->
    <div class="w-full max-w-sm bg-white rounded-lg shadow-sm overflow-hidden">
      <!-- Tab switcher -->
      <div class="flex border-b border-gray-200">
        <button
          @click="activeTab = 'sms'"
          :class="[
            'flex-1 py-4 text-sm font-medium transition-all relative',
            activeTab === 'sms'
              ? 'text-[#1890ff]'
              : 'text-gray-500'
          ]"
        >
          {{ $t('scan.smsLoginTab') }}
          <div v-if="activeTab === 'sms'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#1890ff]"></div>
        </button>
        <button
          @click="activeTab = 'password'"
          :class="[
            'flex-1 py-4 text-sm font-medium transition-all relative',
            activeTab === 'password'
              ? 'text-[#1890ff]'
              : 'text-gray-500'
          ]"
        >
          {{ $t('scan.passwordLoginTab') }}
          <div v-if="activeTab === 'password'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#1890ff]"></div>
        </button>
      </div>

      <!-- 验证码登录 Tab -->
      <div v-if="activeTab === 'sms'" class="p-6 space-y-5">
        <div>
          <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:border-[#1890ff] focus-within:ring-1 focus-within:ring-[#1890ff]">
            <span class="px-3 text-sm text-gray-500 border-r border-gray-300">+86</span>
            <input
              v-model="smsPhone"
              type="tel"
              inputmode="numeric"
              maxlength="11"
              :placeholder="$t('scan.enterPhonePlaceholder')"
              class="flex-1 px-3 py-3 text-sm outline-none"
            />
          </div>
        </div>
        <div>
          <div class="flex gap-2">
            <input
              v-model="smsCode"
              type="text"
              inputmode="numeric"
              maxlength="6"
              :placeholder="$t('scan.enterCodePlaceholder')"
              @keyup.enter="loginWithSms"
              class="flex-1 border border-gray-300 rounded-lg px-3 py-3 text-sm outline-none focus:border-[#1890ff] focus:ring-1 focus:ring-[#1890ff]"
            />
            <button
              @click="sendSmsCode"
              :disabled="smsSending || smsCountdown > 0"
              class="px-4 py-3 border border-[#1890ff] text-[#1890ff] rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap hover:bg-[#1890ff] hover:text-white transition-colors"
            >
              <span v-if="smsCountdown > 0">{{ smsCountdown }}s</span>
              <span v-else-if="smsSending">{{ $t('scan.sending') }}</span>
              <span v-else>{{ $t('scan.getCode') }}</span>
            </button>
          </div>
        </div>

        <p v-if="smsError" class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ smsError }}</p>

        <button
          @click="loginWithSms"
          :disabled="smsLoading"
          class="w-full bg-[#1890ff] hover:bg-[#40a9ff] disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-3 rounded-lg text-sm transition-colors"
        >
          <span v-if="smsLoading" class="inline-flex items-center gap-2">
            <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            {{ $t('scan.loggingIn') }}
          </span>
          <span v-else>{{ $t('scan.loginBtn') }}</span>
        </button>

        <!-- Privacy agreement -->
        <div class="flex items-start gap-2 pt-2">
          <input
            v-model="agreePrivacy"
            type="checkbox"
            id="privacy-sms"
            class="mt-0.5 w-4 h-4 text-[#1890ff] border-gray-300 rounded focus:ring-[#1890ff]"
          />
          <label for="privacy-sms" class="text-xs text-gray-500 leading-relaxed">
            {{ $t('scan.agreePrefix') }}<a href="#" class="text-[#1890ff]">{{ $t('scan.userAgreement') }}</a>{{ $t('scan.and') }}<a href="#" class="text-[#1890ff]">{{ $t('scan.privacyPolicy') }}</a>
          </label>
        </div>
      </div>

      <!-- 密码登录 Tab -->
      <div v-if="activeTab === 'password'" class="p-6 space-y-5">
        <div>
          <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:border-[#1890ff] focus-within:ring-1 focus-within:ring-[#1890ff]">
            <span class="px-3 text-sm text-gray-500 border-r border-gray-300">+86</span>
            <input
              v-model="pwdPhone"
              type="tel"
              inputmode="numeric"
              maxlength="11"
              :placeholder="$t('scan.enterPhonePlaceholder')"
              @keyup.enter="loginWithPassword"
              class="flex-1 px-3 py-3 text-sm outline-none"
            />
          </div>
        </div>
        <div>
          <input
            v-model="pwdPassword"
            type="password"
            :placeholder="$t('scan.enterPasswordPlaceholder')"
            @keyup.enter="loginWithPassword"
            class="w-full border border-gray-300 rounded-lg px-3 py-3 text-sm outline-none focus:border-[#1890ff] focus:ring-1 focus:ring-[#1890ff]"
          />
        </div>

        <div class="flex justify-end">
          <button @click="goToForgotPassword" class="text-xs text-gray-500 hover:text-[#1890ff]">
            {{ $t('scan.forgotPassword') }}
          </button>
        </div>

        <p v-if="pwdError" class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ pwdError }}</p>

        <button
          @click="loginWithPassword"
          :disabled="pwdLoading"
          class="w-full bg-[#1890ff] hover:bg-[#40a9ff] disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-3 rounded-lg text-sm transition-colors"
        >
          <span v-if="pwdLoading" class="inline-flex items-center gap-2">
            <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            {{ $t('scan.loggingIn') }}
          </span>
          <span v-else>{{ $t('scan.loginBtn') }}</span>
        </button>

        <!-- Privacy agreement -->
        <div class="flex items-start gap-2 pt-2">
          <input
            v-model="agreePrivacy"
            type="checkbox"
            id="privacy-pwd"
            class="mt-0.5 w-4 h-4 text-[#1890ff] border-gray-300 rounded focus:ring-[#1890ff]"
          />
          <label for="privacy-pwd" class="text-xs text-gray-500 leading-relaxed">
            {{ $t('scan.agreePrefix') }}<a href="#" class="text-[#1890ff]">{{ $t('scan.userAgreement') }}</a>{{ $t('scan.and') }}<a href="#" class="text-[#1890ff]">{{ $t('scan.privacyPolicy') }}</a>
          </label>
        </div>
      </div>
    </div>
  </div>
</template>
