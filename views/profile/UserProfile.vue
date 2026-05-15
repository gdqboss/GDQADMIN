<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user'
import api from '../../services/api.js'

const { t } = useI18n()
const userStore = useUserStore()

const user = computed(() => userStore.user || {})
const initials = computed(() => (user.value.name || '?').slice(0, 1).toUpperCase())

// 编辑模式
const editing = ref(false)
const form = ref({ name: '', phone: '', email: '' })
const saving = ref(false)
const saveMsg = ref('')
const saveErr = ref('')

function startEdit() {
  form.value = { name: user.value.name || '', phone: user.value.phone || '', email: user.value.email || '' }
  saveMsg.value = ''
  saveErr.value = ''
  editing.value = true
}
function cancelEdit() { editing.value = false }

async function saveProfile() {
  saving.value = true
  saveMsg.value = ''
  saveErr.value = ''
  try {
    const res = await api.put('/auth/profile', form.value)
    if (res.code === 0) {
      saveMsg.value = t('profile.saveSuccess')
      editing.value = false
      await userStore.fetchMe()
    } else {
      saveErr.value = res.message || 'Error'
    }
  } catch (e) {
    saveErr.value = e.response?.data?.message || e.message
  } finally {
    saving.value = false
  }
}

// 修改密码
const showPwd = ref(false)
const pwd = ref({ oldPassword: '', newPassword: '', confirm: '' })
const pwdSaving = ref(false)
const pwdMsg = ref('')
const pwdErr = ref('')

async function changePassword() {
  pwdMsg.value = ''
  pwdErr.value = ''
  if (pwd.value.newPassword.length < 6) { pwdErr.value = t('profile.passwordTooShort'); return }
  if (pwd.value.newPassword !== pwd.value.confirm) { pwdErr.value = t('profile.passwordMismatch'); return }
  pwdSaving.value = true
  try {
    const res = await api.put('/auth/change-password', { oldPassword: pwd.value.oldPassword, newPassword: pwd.value.newPassword })
    if (res.code === 0) {
      pwdMsg.value = t('profile.passwordSuccess')
      pwd.value = { oldPassword: '', newPassword: '', confirm: '' }
      showPwd.value = false
    } else {
      pwdErr.value = res.message || 'Error'
    }
  } catch (e) {
    pwdErr.value = e.response?.data?.message || e.message
  } finally {
    pwdSaving.value = false
  }
}

const roleLabel = computed(() => {
  const map = { admin: t('settings.roleAdmin'), manager: t('settings.roleManager'), operator: t('settings.roleOperator') }
  return map[user.value.role] || user.value.role
})

onMounted(() => { userStore.fetchMe() })
</script>

<template>
  <div class="max-w-2xl mx-auto py-6 px-4 space-y-6">
    <!-- 头像 + 姓名 -->
    <div class="bg-white rounded-xl shadow p-6 flex items-center gap-5">
      <div class="w-16 h-16 rounded-full bg-indigo-500 text-white flex items-center justify-center text-2xl font-bold shrink-0">{{ initials }}</div>
      <div class="min-w-0">
        <h2 class="text-xl font-semibold truncate">{{ user.name }}</h2>
        <p class="text-gray-500 text-sm">{{ roleLabel }} <span v-if="user.department">· {{ user.department }}</span></p>
      </div>
    </div>

    <!-- 基本信息卡片 -->
    <div class="bg-white rounded-xl shadow p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">{{ t('profile.basicInfo') }}</h3>
        <button v-if="!editing" @click="startEdit" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center gap-1">
          <span class="material-symbols-outlined text-base">edit</span>{{ t('profile.editInfo') }}
        </button>
      </div>

      <!-- 成功/错误消息 -->
      <div v-if="saveMsg" class="mb-3 p-2 bg-green-50 text-green-700 rounded text-sm">{{ saveMsg }}</div>
      <div v-if="saveErr" class="mb-3 p-2 bg-red-50 text-red-700 rounded text-sm">{{ saveErr }}</div>

      <div v-if="!editing" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div><span class="text-gray-500">{{ t('profile.name') }}</span><p class="font-medium">{{ user.name || '-' }}</p></div>
        <div><span class="text-gray-500">{{ t('profile.phone') }}</span><p class="font-medium">{{ user.phone || '-' }}</p></div>
        <div><span class="text-gray-500">{{ t('profile.email') }}</span><p class="font-medium">{{ user.email || '-' }}</p></div>
        <div><span class="text-gray-500">{{ t('profile.role') }}</span><p class="font-medium">{{ roleLabel }}</p></div>
        <div><span class="text-gray-500">{{ t('profile.department') }}</span><p class="font-medium">{{ user.department || '-' }}</p></div>
        <div><span class="text-gray-500">{{ t('profile.hireDate') }}</span><p class="font-medium">{{ user.hire_date || '-' }}</p></div>
        <div class="sm:col-span-2"><span class="text-gray-500">{{ t('profile.lastLogin') }}</span><p class="font-medium">{{ user.last_login || '-' }}</p></div>
      </div>

      <!-- 编辑表单 -->
      <form v-else @submit.prevent="saveProfile" class="space-y-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">{{ t('profile.name') }}</label>
          <input v-model="form.name" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none" required />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">{{ t('profile.phone') }}</label>
          <input v-model="form.phone" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">{{ t('profile.email') }}</label>
          <input v-model="form.email" type="email" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none" />
        </div>
        <div class="flex gap-3 justify-end">
          <button type="button" @click="cancelEdit" class="px-4 py-2 text-sm rounded-lg border hover:bg-gray-50">{{ t('profile.cancel') }}</button>
          <button type="submit" :disabled="saving" class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">
            {{ saving ? '...' : t('profile.save') }}
          </button>
        </div>
      </form>
    </div>

    <!-- 修改密码 -->
    <div class="bg-white rounded-xl shadow p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">{{ t('profile.changePassword') }}</h3>
        <button v-if="!showPwd" @click="showPwd = true; pwdMsg = ''; pwdErr = ''" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center gap-1">
          <span class="material-symbols-outlined text-base">lock</span>{{ t('profile.changePassword') }}
        </button>
      </div>

      <div v-if="pwdMsg" class="mb-3 p-2 bg-green-50 text-green-700 rounded text-sm">{{ pwdMsg }}</div>
      <div v-if="pwdErr" class="mb-3 p-2 bg-red-50 text-red-700 rounded text-sm">{{ pwdErr }}</div>

      <form v-if="showPwd" @submit.prevent="changePassword" class="space-y-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">{{ t('profile.oldPassword') }}</label>
          <input v-model="pwd.oldPassword" type="password" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none" required />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">{{ t('profile.newPassword') }}</label>
          <input v-model="pwd.newPassword" type="password" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none" required />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">{{ t('profile.confirmPassword') }}</label>
          <input v-model="pwd.confirm" type="password" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none" required />
        </div>
        <div class="flex gap-3 justify-end">
          <button type="button" @click="showPwd = false" class="px-4 py-2 text-sm rounded-lg border hover:bg-gray-50">{{ t('profile.cancel') }}</button>
          <button type="submit" :disabled="pwdSaving" class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">
            {{ pwdSaving ? '...' : t('profile.submitPassword') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
