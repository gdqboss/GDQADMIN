<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

const phone = ref('')
const code = ref('')
const name = ref('')
const password = ref('')
const agreePrivacy = ref(false)
const error = ref('')
const loading = ref(false)
const sending = ref(false)
const countdown = ref(0)

async function sendCode() {
  error.value = ''
  if (!phone.value) { error.value = t('scan.enterPhone'); return }
  if (!/^1[3-9]\d{9}$/.test(phone.value)) { error.value = t('scan.phoneInvalid'); return }

  sending.value = true
  try {
    const res = await fetch('/api/h5/send-sms', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ phone: phone.value }),
    })
    const json = await res.json()
    if (json.code === 0) {
      countdown.value = 60
      const timer = setInterval(() => {
        countdown.value--
        if (countdown.value <= 0) clearInterval(timer)
      }, 1000)
    } else {
      error.value = json.message || t('scan.sendFailed')
    }
  } catch { error.value = t('scan.networkError') }
  finally { sending.value = false }
}

async function register() {
  error.value = ''
  if (!phone.value) { error.value = t('scan.enterPhone'); return }
  if (!/^1[3-9]\d{9}$/.test(phone.value)) { error.value = t('scan.phoneInvalid'); return }
  if (!code.value) { error.value = t('scan.enterCode'); return }
  if (!name.value) { error.value = t('scan.enterName'); return }
  if (!password.value) { error.value = t('scan.enterPassword'); return }
  if (password.value.length < 6) { error.value = t('scan.passwordMin6'); return }
  if (!agreePrivacy.value) { error.value = t('scan.agreePrivacyFirst'); return }

  loading.value = true
  try {
    const res = await fetch('/api/h5/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        phone: phone.value,
        code: code.value,
        password: password.value,
        name: name.value || undefined
      }),
    })
    const json = await res.json()
    if (json.code === 0) {
      localStorage.setItem('h5_token', json.data.token)
      localStorage.setItem('h5_user', JSON.stringify({ id: json.data.id, name: json.data.name, phone: json.data.phone }))
      const redirect = route.query.redirect || '/'
      router.replace(redirect)
    } else {
      error.value = json.message || t('scan.registerFailed')
    }
  } catch { error.value = t('scan.networkError') }
  finally { loading.value = false }
}

function goToLogin() {
  router.push('/h5/login')
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4 py-10">
    <!-- Header with back button -->
    <div class="w-full max-w-sm mb-6 flex items-center justify-between">
      <button @click="goToLogin" class="flex items-center gap-1 text-sm text-gray-500 hover:text-[#1890ff]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        {{ t('scan.backToLogin') }}
      </button>
      <div class="flex items-center gap-2">
        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-[#1890ff] shadow-md">
          <span class="text-white font-bold text-sm">GDQ</span>
        </div>
      </div>
    </div>

    <!-- Card -->
    <div class="w-full max-w-sm bg-white rounded-lg shadow-sm overflow-hidden">
      <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-800">{{ t('scan.registerAccount') }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ t('scan.fillInfoToRegister') }}</p>
      </div>

      <div class="p-6 space-y-4">
        <!-- Phone -->
        <div>
          <label class="block text-xs text-gray-600 mb-2">{{ t('scan.phoneLabel') }}</label>
          <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:border-[#1890ff] focus-within:ring-1 focus-within:ring-[#1890ff]">
            <span class="px-3 text-sm text-gray-500 border-r border-gray-300">+86</span>
            <input
              v-model="phone"
              type="tel"
              inputmode="numeric"
              maxlength="11"
              :placeholder="t('scan.enterPhone')"
              class="flex-1 px-3 py-3 text-sm outline-none"
            />
          </div>
        </div>

        <!-- SMS Code -->
        <div>
          <label class="block text-xs text-gray-600 mb-2">{{ t('scan.verifyCode') }}</label>
          <div class="flex gap-2">
            <input
              v-model="code"
              type="text"
              inputmode="numeric"
              maxlength="6"
              :placeholder="t('scan.enterCode')"
              class="flex-1 border border-gray-300 rounded-lg px-3 py-3 text-sm outline-none focus:border-[#1890ff] focus:ring-1 focus:ring-[#1890ff]"
            />
            <button
              @click="sendCode"
              :disabled="sending || countdown > 0"
              class="px-4 py-3 border border-[#1890ff] text-[#1890ff] rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap hover:bg-[#1890ff] hover:text-white transition-colors"
            >
              <span v-if="countdown > 0">{{ countdown }}s</span>
              <span v-else-if="sending">{{ t('scan.sending') }}</span>
              <span v-else>{{ t('scan.getCode') }}</span>
            </button>
          </div>
        </div>

        <!-- Name -->
        <div>
          <label class="block text-xs text-gray-600 mb-2">{{ t('scan.nameLabel') }}</label>
          <input
            v-model="name"
            type="text"
            :placeholder="t('scan.enterName')"
            class="w-full border border-gray-300 rounded-lg px-3 py-3 text-sm outline-none focus:border-[#1890ff] focus:ring-1 focus:ring-[#1890ff]"
          />
        </div>

        <!-- Password -->
        <div>
          <label class="block text-xs text-gray-600 mb-2">{{ t('scan.setPassword') }}</label>
          <input
            v-model="password"
            type="password"
            :placeholder="t('scan.setPasswordPlaceholder')"
            class="w-full border border-gray-300 rounded-lg px-3 py-3 text-sm outline-none focus:border-[#1890ff] focus:ring-1 focus:ring-[#1890ff]"
          />
        </div>

        <p v-if="error" class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ error }}</p>

        <button
          @click="register"
          :disabled="loading"
          class="w-full bg-[#1890ff] hover:bg-[#40a9ff] disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-3 rounded-lg text-sm transition-colors"
        >
          <span v-if="loading" class="inline-flex items-center gap-2">
            <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            {{ t('scan.registering') }}
          </span>
          <span v-else>{{ t('scan.register') }}</span>
        </button>

        <!-- Privacy agreement -->
        <div class="flex items-start gap-2 pt-2">
          <input
            v-model="agreePrivacy"
            type="checkbox"
            id="privacy-register"
            class="mt-0.5 w-4 h-4 text-[#1890ff] border-gray-300 rounded focus:ring-[#1890ff]"
          />
          <label for="privacy-register" class="text-xs text-gray-500 leading-relaxed">
            {{ t('scan.agreePrefix') }}<a href="#" class="text-[#1890ff]">{{ t('scan.userAgreement') }}</a>{{ t('scan.and') }}<a href="#" class="text-[#1890ff]">{{ t('scan.privacyPolicy') }}</a>
          </label>
        </div>
      </div>
    </div>
  </div>
</template>
