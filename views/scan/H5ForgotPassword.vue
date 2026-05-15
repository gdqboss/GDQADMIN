<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const router = useRouter()

const phone = ref('')
const code = ref('')
const newPassword = ref('')
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

async function resetPassword() {
  error.value = ''
  if (!phone.value) { error.value = t('scan.enterPhone'); return }
  if (!/^1[3-9]\d{9}$/.test(phone.value)) { error.value = t('scan.phoneInvalid'); return }
  if (!code.value) { error.value = t('scan.enterCode'); return }
  if (!newPassword.value) { error.value = t('scan.enterNewPassword'); return }
  if (newPassword.value.length < 6) { error.value = t('scan.passwordMin6'); return }

  loading.value = true
  try {
    const res = await fetch('/api/h5/reset-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        phone: phone.value,
        code: code.value,
        new_password: newPassword.value
      }),
    })
    const json = await res.json()
    if (json.code === 0) {
      router.push({
        path: '/h5/login',
        query: { message: t('scan.resetSuccessMessage') }
      })
    } else {
      error.value = json.message || t('scan.resetFailed')
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
        <h2 class="text-xl font-bold text-gray-800">{{ t('scan.resetTitle') }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ t('scan.resetSubtitle') }}</p>
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
              :placeholder="t('scan.enterPhonePlaceholder')"
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
              :placeholder="t('scan.enterCodePlaceholder')"
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

        <!-- New Password -->
        <div>
          <label class="block text-xs text-gray-600 mb-2">{{ t('scan.newPasswordLabel') }}</label>
          <input
            v-model="newPassword"
            type="password"
            :placeholder="t('scan.enterNewPasswordPlaceholder')"
            @keyup.enter="resetPassword"
            class="w-full border border-gray-300 rounded-lg px-3 py-3 text-sm outline-none focus:border-[#1890ff] focus:ring-1 focus:ring-[#1890ff]"
          />
        </div>

        <p v-if="error" class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ error }}</p>

        <button
          @click="resetPassword"
          :disabled="loading"
          class="w-full bg-[#1890ff] hover:bg-[#40a9ff] disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-3 rounded-lg text-sm transition-colors"
        >
          <span v-if="loading" class="inline-flex items-center gap-2">
            <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            {{ t('scan.resettingPassword') }}
          </span>
          <span v-else>{{ t('scan.resetPasswordBtn') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
