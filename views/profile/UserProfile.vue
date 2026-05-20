<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user'
import api from '../../services/api.js'
import ImageSelector from '../../components/ImageSelector.vue'

const { t } = useI18n()
const userStore = useUserStore()

const user = computed(() => userStore.user || {})
const initials = computed(() => (user.value.name || '?').slice(0, 1).toUpperCase())

// 编辑模式
const editing = ref(false)
const saving = ref(false)
const saveMsg = ref('')
const saveErr = ref('')

// 头像和生活照
const avatar = ref('')
const lifePhotosSelected = ref([])

// 上传相关
const uploadingAvatar = ref(false)

function startEdit() {
  avatar.value = user.value.avatar || ''
  lifePhotosSelected.value = user.value.life_photos
    ? [...user.value.life_photos].filter(Boolean).slice(0, 9)
    : []
  saveMsg.value = ''
  saveErr.value = ''
  editing.value = true
}
function cancelEdit() { editing.value = false }

async function uploadAvatar(file) {
  uploadingAvatar.value = true
  try {
    const form = new FormData()
    form.append('file', file)
    form.append('category', 'avatar')
    const res = await api.post('/upload/image', form)
    if (res.code === 0) {
      avatar.value = res.data.url
    }
  } finally {
    uploadingAvatar.value = false
  }
}

function onAvatarFileChange(e) {
  const file = e.target.files[0]
  if (file) uploadAvatar(file)
}

async function saveProfile() {
  saving.value = true
  saveMsg.value = ''
  saveErr.value = ''
  try {
    const res = await api.put('/auth/profile', {
      avatar: avatar.value,
      life_photos: lifePhotosSelected.value
    })
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

    <!-- ========== 名片区域（渐变背景卡片） ========== -->
    <div class="bg-gradient-to-br from-primary/90 to-primary rounded-2xl p-6 text-white shadow-lg">
      <!-- 封面图 -->
      <div class="relative mb-16">
        <div
          :style="(editing ? avatar : user.avatar) ? `background-image:url('${editing ? avatar : user.avatar}')` : ''"
          class="w-full h-40 rounded-xl bg-cover bg-center bg-white/10 flex items-center justify-center"
        >
          <span v-if="!(editing ? avatar : user.avatar)" class="material-symbols-outlined text-5xl text-white/40">image</span>
        </div>
        <!-- 头像（底部居中叠在封面图上） -->
        <div class="absolute -bottom-10 left-1/2 -translate-x-1/2">
          <div v-if="editing" class="relative group cursor-pointer">
            <div class="w-20 h-20 rounded-full bg-indigo-400 border-4 border-white flex items-center justify-center text-2xl font-bold text-white overflow-hidden">
              <img v-if="avatar" :src="avatar" class="w-full h-full object-cover" />
              <span v-else>{{ initials }}</span>
            </div>
            <div class="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              <span class="material-symbols-outlined text-white">photo_camera</span>
            </div>
            <input type="file" accept="image/*" @change="onAvatarFileChange" class="absolute inset-0 opacity-0 cursor-pointer" />
          </div>
          <div v-else class="w-20 h-20 rounded-full bg-indigo-400 border-4 border-white flex items-center justify-center text-2xl font-bold text-white overflow-hidden">
            <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover" />
            <span v-else>{{ initials }}</span>
          </div>
        </div>
      </div>

      <!-- 姓名+职位信息 -->
      <div class="text-center mt-12 space-y-1">
        <h2 class="text-xl font-bold">{{ user.name || '未设置姓名' }}</h2>
        <p class="text-white/80 text-sm">{{ roleLabel }}<span v-if="user.department"> · {{ user.department }}</span></p>
        <p class="text-white/60 text-xs mt-1">{{ user.email || '' }}</p>
      </div>
    </div>

    <!-- ========== 编辑按钮（只在非编辑模式显示） ========== -->
    <div v-if="!editing" class="flex justify-end">
      <button @click="startEdit" class="flex items-center gap-1 px-4 py-2 bg-primary text-white text-sm rounded-lg hover:bg-primary/90 shadow">
        <span class="material-symbols-outlined text-base">edit</span>{{ t('profile.editInfo') }}
      </button>
    </div>

    <!-- ========== 编辑表单 ========== -->
    <div v-if="editing" class="bg-white rounded-xl shadow p-6 space-y-6">
      <div class="flex items-center justify-between">
        <h3 class="font-semibold">编辑资料</h3>
      </div>

      <div v-if="saveMsg" class="p-2 bg-green-50 text-green-700 rounded text-sm">{{ saveMsg }}</div>
      <div v-if="saveErr" class="p-2 bg-red-50 text-red-700 rounded text-sm">{{ saveErr }}</div>

      <!-- 封面图+头像 -->
      <div class="space-y-4">
        <div>
          <label class="block text-sm text-gray-600 mb-2">封面图</label>
          <div class="w-full h-24 rounded-lg bg-gray-100 overflow-hidden flex items-center justify-center relative group cursor-pointer">
            <img v-if="avatar" :src="avatar" class="w-full h-full object-cover" />
            <div v-else class="text-gray-400 flex flex-col items-center">
              <span class="material-symbols-outlined text-3xl">image</span>
              <span class="text-xs mt-1">上传封面图</span>
            </div>
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white">
              <span class="material-symbols-outlined text-2xl">upload</span>
            </div>
            <input type="file" accept="image/*" @change="onAvatarFileChange" class="absolute inset-0 opacity-0 cursor-pointer" />
          </div>
          <div class="flex gap-2 mt-2">
            <label class="flex items-center gap-1.5 px-3 py-1.5 border border-dashed border-gray-300 rounded-lg text-sm text-gray-500 hover:border-primary hover:text-primary cursor-pointer transition-colors">
              <span class="material-symbols-outlined text-base">upload</span>本地上传
              <input type="file" accept="image/*" class="hidden" @change="onAvatarFileChange" />
            </label>
            <ImageSelector :model-value="avatar" :multiple="false" @update:model-value="v => avatar = v" />
          </div>
        </div>

        <div>
          <label class="block text-sm text-gray-600 mb-2">头像</label>
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-full bg-indigo-100 overflow-hidden flex items-center justify-center text-indigo-500">
              <img v-if="avatar" :src="avatar" class="w-full h-full object-cover" />
              <span v-else class="text-xl font-bold">{{ initials }}</span>
            </div>
            <label class="flex items-center gap-1.5 px-3 py-1.5 border border-dashed border-gray-300 rounded-lg text-sm text-gray-500 hover:border-primary hover:text-primary cursor-pointer transition-colors">
              <span class="material-symbols-outlined text-base">upload</span>本地上传
              <input type="file" accept="image/*" class="hidden" @change="onAvatarFileChange" />
            </label>
            <ImageSelector :model-value="avatar" :multiple="false" @update:model-value="v => avatar = v" />
          </div>
        </div>
      </div>

      <!-- 九宫格生活照 -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-sm text-gray-600 font-medium">生活照 <span class="text-gray-400 text-xs">（最多9张）</span></label>
          <span class="text-xs text-gray-400">{{ lifePhotosSelected.length }}/9</span>
        </div>

        <!-- 当前已选生活照预览（可删除） -->
        <div v-if="lifePhotosSelected.length" class="grid grid-cols-3 gap-2 mb-2">
          <div v-for="(photo, i) in lifePhotosSelected" :key="i" class="aspect-square rounded-lg bg-gray-100 overflow-hidden relative group">
            <img :src="photo" class="w-full h-full object-cover" />
            <button @click="lifePhotosSelected.splice(i, 1)" class="absolute top-1 right-1 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              <span class="material-symbols-outlined text-white text-xs">close</span>
            </button>
          </div>
        </div>

        <!-- 本地上传 + 从图片库选择 -->
        <div class="flex gap-2 flex-wrap">
          <!-- 本地上传入口（本地上传会走ImageSelector的@change，但ImageSelector是独立按钮，所以这里再加一个） -->
          <label v-if="lifePhotosSelected.length < 9" class="flex items-center gap-1.5 px-3 py-1.5 border border-dashed border-gray-300 rounded-lg text-sm text-gray-500 hover:border-primary hover:text-primary cursor-pointer transition-colors">
            <span class="material-symbols-outlined text-base">upload</span>本地上传
            <input
              type="file"
              accept="image/*"
              class="hidden"
              @change="async (e) => {
                const file = e.target.files[0]
                if (!file) return
                const form = new FormData()
                form.append('file', file)
                form.append('category', 'life_photo')
                const res = await api.post('/upload/image', form)
                if (res.code === 0 && lifePhotosSelected.length < 9) lifePhotosSelected.push(res.data.url)
              }"
            />
          </label>

          <!-- 图片库选择 -->
          <ImageSelector
            :selected="lifePhotosSelected"
            :multiple="true"
            :max="9"
            @update:selected="v => lifePhotosSelected = v"
          />
        </div>
      </div>

      <!-- 保存/取消 -->
      <div class="flex gap-3 justify-end pt-2">
        <button @click="cancelEdit" class="px-4 py-2 text-sm rounded-lg border hover:bg-gray-50">{{ t('profile.cancel') }}</button>
        <button @click="saveProfile" :disabled="saving" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-primary/90 disabled:opacity-50">
          {{ saving ? '...' : t('profile.save') }}
        </button>
      </div>
    </div>

    <!-- ========== 基本信息卡片（非编辑模式） ========== -->
    <div v-if="!editing" class="bg-white rounded-xl shadow p-6 space-y-4">
      <div class="grid grid-cols-2 gap-4 text-sm">
        <div><span class="text-gray-500">{{ t('profile.phone') }}</span><p class="font-medium mt-0.5">{{ user.phone || '-' }}</p></div>
        <div><span class="text-gray-500">{{ t('profile.email') }}</span><p class="font-medium mt-0.5">{{ user.email || '-' }}</p></div>
        <div><span class="text-gray-500">{{ t('profile.department') }}</span><p class="font-medium mt-0.5">{{ user.department || '-' }}</p></div>
        <div><span class="text-gray-500">{{ t('profile.hireDate') }}</span><p class="font-medium mt-0.5">{{ user.hire_date || '-' }}</p></div>
        <div class="col-span-2"><span class="text-gray-500">{{ t('profile.lastLogin') }}</span><p class="font-medium mt-0.5">{{ user.last_login || '-' }}</p></div>
      </div>
    </div>

    <!-- 九宫格生活照展示（非编辑模式） -->
    <div v-if="!editing && user.life_photos && user.life_photos.length" class="bg-white rounded-xl shadow p-6">
      <h3 class="font-semibold mb-4">生活照</h3>
      <div class="grid grid-cols-3 gap-2">
        <div v-for="(photo, i) in user.life_photos" :key="i" class="aspect-square rounded-lg overflow-hidden bg-gray-100">
          <img :src="photo" class="w-full h-full object-cover" />
        </div>
      </div>
    </div>

    <!-- ========== 修改密码 ========== -->
    <div class="bg-white rounded-xl shadow p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold">{{ t('profile.changePassword') }}</h3>
        <button v-if="!showPwd" @click="showPwd = true; pwdMsg = ''; pwdErr = ''" class="text-primary text-sm flex items-center gap-1 hover:text-primary/80">
          <span class="material-symbols-outlined text-base">lock</span>{{ t('profile.changePassword') }}
        </button>
      </div>

      <div v-if="pwdMsg" class="mb-3 p-2 bg-green-50 text-green-700 rounded text-sm">{{ pwdMsg }}</div>
      <div v-if="pwdErr" class="mb-3 p-2 bg-red-50 text-red-700 rounded text-sm">{{ pwdErr }}</div>

      <form v-if="showPwd" @submit.prevent="changePassword" class="space-y-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">{{ t('profile.oldPassword') }}</label>
          <input v-model="pwd.oldPassword" type="password" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/40 outline-none" required />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">{{ t('profile.newPassword') }}</label>
          <input v-model="pwd.newPassword" type="password" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/40 outline-none" required />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">{{ t('profile.confirmPassword') }}</label>
          <input v-model="pwd.confirm" type="password" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/40 outline-none" required />
        </div>
        <div class="flex gap-3 justify-end">
          <button type="button" @click="showPwd = false" class="px-4 py-2 text-sm rounded-lg border hover:bg-gray-50">{{ t('profile.cancel') }}</button>
          <button type="submit" :disabled="pwdSaving" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-primary/90 disabled:opacity-50">
            {{ pwdSaving ? '...' : t('profile.submitPassword') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>