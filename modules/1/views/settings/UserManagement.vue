<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800">{{ $t('nav.userManagement') }}</h1>
      <p class="text-gray-600 mt-1">{{ $t('settings.subtitle') }}</p>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow">
      <div class="border-b">
        <nav class="flex">
          <button
            @click="activeTab = 'internal'"
            :class="[
              'px-6 py-3 font-medium transition',
              activeTab === 'internal' ? 'border-b-2 border-primary text-primary' : 'text-gray-600 hover:text-gray-800'
            ]"
          >
            {{ t('settings.userList') }}
          </button>
          <button
            @click="activeTab = 'external'"
            :class="[
              'px-6 py-3 font-medium transition',
              activeTab === 'external' ? 'border-b-2 border-primary text-primary' : 'text-gray-600 hover:text-gray-800'
            ]"
          >
            {{ t('nav.h5Users') }}
          </button>
        </nav>
      </div>

      <!-- Internal Users (System Users) -->
      <div v-if="activeTab === 'internal'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <input
            v-model="searchInternal"
            type="text"
            :placeholder="t('common.search')"
            class="px-4 py-2 border rounded-lg w-64"
          />
          <button @click="loadInternalUsers" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">
            {{ t('common.search') }}
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('common.name') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('common.email') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('settings.role') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('oa.employeeCode') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('identityQR.identityCode') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('common.status') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('common.action') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="user in internalUsers" :key="user.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm">{{ user.name }}</td>
                <td class="px-4 py-3 text-sm">{{ user.email }}</td>
                <td class="px-4 py-3 text-sm">{{ roleLabel(user.role) }}</td>
                <td class="px-4 py-3 text-sm">{{ user.employee_code || '-' }}</td>
                <td class="px-4 py-3 text-sm">
                  <span v-if="user.identity_code" class="text-xs font-mono bg-gray-100 px-2 py-1 rounded">
                    {{ user.identity_code.substring(0, 12) }}...
                  </span>
                  <span v-else class="text-gray-400">{{ t('common.notSet') }}</span>
                </td>
                <td class="px-4 py-3 text-sm">
                  <span :class="user.status === 'active' ? 'text-green-600' : 'text-red-600'">
                    {{ user.status === 'active' ? t('common.active') : t('common.inactive') }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm">
                  <button
                    v-if="!user.identity_code"
                    @click="generateIdentityCode(user.id, 'system')"
                    class="text-primary hover:underline mr-2"
                  >
                    {{ t('identityQR.generateCode') }}
                  </button>
                  <button
                    v-if="user.identity_code"
                    @click="viewIdentityQR(user)"
                    class="text-blue-600 hover:underline mr-2"
                  >
                    {{ t('common.view') }}
                  </button>
                  <button
                    v-if="user.identity_code"
                    @click="deleteIdentityCode(user.id, 'system')"
                    class="text-red-600 hover:underline mr-2"
                  >
                    {{ t('common.delete') }}
                  </button>
                  <!-- 2026-08-29 WorkBuddy APP 连接链接 — 用户用自己密码生成的 token 自动对齐权限 -->
                  <button
                    @click="openWorkBuddyModal(user)"
                    class="text-purple-600 hover:underline"
                    :title="t('workbuddy.copyLinkHelp')"
                  >
                    🔗 {{ t('workbuddy.copyLink') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- External Users (H5 Users) -->
      <div v-if="activeTab === 'external'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <input
            v-model="searchExternal"
            type="text"
            :placeholder="t('common.search')"
            class="px-4 py-2 border rounded-lg w-64"
          />
          <button @click="loadExternalUsers" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">
            {{ t('common.search') }}
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('common.phone') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('settings.roleCol') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('settings.levelCol') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('settings.storeCol') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('settings.isInternal') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">{{ t('common.action') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="user in externalUsers" :key="user.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm">{{ user.phone }}</td>
                <td class="px-4 py-3 text-sm">{{ roleLabel(user.role) || 'customer' }}</td>
                <td class="px-4 py-3 text-sm">{{ user.level || 1 }}</td>
                <td class="px-4 py-3 text-sm">{{ user.parent_phone || '-' }}</td>
                <td class="px-4 py-3 text-sm">
                  <span :class="user.is_internal ? 'text-green-600' : 'text-gray-400'">
                    {{ user.is_internal ? t('common.yes') : t('common.no') }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm">
                  <button
                    v-if="user.is_internal && !user.identity_code"
                    @click="generateIdentityCode(user.id, 'h5')"
                    class="text-primary hover:underline mr-2"
                  >
                    {{ t('identityQR.generateCode') }}
                  </button>
                  <button
                    @click="editH5User(user)"
                    class="text-blue-600 hover:underline"
                  >
                    {{ t('common.edit') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Identity QR Modal -->
    <div v-if="showQRModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showQRModal = false">
      <div class="bg-white rounded-lg p-6 max-w-md" @click.stop>
        <h3 class="text-lg font-semibold mb-4">{{ t('oa.identityQR') }}</h3>
        <div class="text-center">
          <img v-if="currentQRPath" :src="currentQRPath" alt="Identity QR Code" class="mx-auto mb-4" />
          <p class="text-sm text-gray-600 mb-4">{{ currentIdentityCode }}</p>
          <div class="flex gap-2 justify-center">
            <a :href="currentQRPath" :download="`identity-${currentIdentityCode}.png`" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">
              {{ t('oa.downloadQR') }}
            </a>
            <button @click="showQRModal = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
              {{ t('common.close') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 2026-08-29 WorkBuddy APP 连接链接 Modal — 用户密码 → token → 对齐权限 -->
    <div v-if="showWBModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showWBModal = false">
      <div class="bg-white rounded-lg p-6 max-w-lg w-full" @click.stop>
        <h3 class="text-lg font-semibold mb-2">🔗 WorkBuddy 连接</h3>
        <p class="text-sm text-gray-600 mb-4">
          用户 <span class="font-bold">{{ wbTargetUser?.name }}</span> ({{ wbTargetUser?.phone || wbTargetUser?.email }})
          在 WorkBuddy APP 里粘贴下方链接, 自动连接我们的系统并对齐他自己的权限。
        </p>

        <!-- Step 1: 让用户输入自己的密码 (不知道密码的人拿不到 token) -->
        <div v-if="!wbToken" class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">登录账号 (手机/邮箱)</label>
            <input
              v-model="wbLoginKey"
              type="text"
              :placeholder="wbTargetUser?.phone || wbTargetUser?.email"
              class="w-full px-3 py-2 border rounded-lg"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">登录密码</label>
            <input
              v-model="wbPassword"
              type="password"
              placeholder="••••••••"
              class="w-full px-3 py-2 border rounded-lg"
              @keyup.enter="generateWorkBuddyLink"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">有效期 (天, 1-30)</label>
            <input
              v-model.number="wbExpiresDays"
              type="number"
              min="1"
              max="30"
              class="w-full px-3 py-2 border rounded-lg"
            />
            <p class="text-xs text-gray-500 mt-1">默认 7 天, 过期需重新生成</p>
          </div>
          <div v-if="wbError" class="text-sm text-red-600 bg-red-50 p-2 rounded">{{ wbError }}</div>
          <div class="flex gap-2 justify-end">
            <button @click="showWBModal = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
              {{ t('common.cancel') }}
            </button>
            <button
              @click="generateWorkBuddyLink"
              :disabled="wbLoading || !wbPassword"
              class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50"
            >
              {{ wbLoading ? '生成中...' : '🔐 生成链接' }}
            </button>
          </div>
        </div>

        <!-- Step 2: 显示生成的连接链接 + 复制按钮 -->
        <div v-else class="space-y-3">
          <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-sm">
            <p class="text-green-800 font-medium mb-1">✅ 已生成</p>
            <p class="text-green-700">
              有效期 {{ wbTokenInfo.expires_in_days }} 天, 至 {{ new Date(wbTokenInfo.expires_at).toLocaleString() }}
            </p>
            <p class="text-green-700">用户权限: {{ wbTokenInfo.role }} ({{ wbTokenInfo.permissions.length }} 项)</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">🔗 WorkBuddy 连接链接 (一键复制给 APP)</label>
            <div class="flex gap-2">
              <input
                ref="wbLinkInputRef"
                :value="wbTokenInfo.connect_link"
                readonly
                class="flex-1 px-3 py-2 border rounded-lg bg-gray-50 text-xs font-mono"
                @focus="$event.target.select()"
              />
              <button
                @click="copyWorkBuddyLink"
                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 whitespace-nowrap"
              >
                {{ wbCopied ? '✓ 已复制' : '📋 复制' }}
              </button>
            </div>
            <p class="text-xs text-gray-500 mt-1">
              链接格式: <code class="bg-gray-100 px-1 rounded">workbuddy://connect?server=...&amp;token=...</code><br>
              WorkBuddy APP 打开会自动接管, 用户只需在 APP 里粘贴或扫码即可连接。
            </p>
          </div>

          <details class="text-sm">
            <summary class="cursor-pointer text-gray-600 hover:text-gray-800">📋 纯 token (备选)</summary>
            <div class="mt-2 p-2 bg-gray-50 rounded text-xs font-mono break-all">{{ wbTokenInfo.token }}</div>
            <button @click="copyWorkBuddyToken" class="mt-1 text-purple-600 hover:underline text-xs">
              {{ wbCopiedToken ? '✓ 已复制' : '复制 token' }}
            </button>
          </details>

          <details class="text-sm">
            <summary class="cursor-pointer text-gray-600 hover:text-gray-800">🔑 该用户的权限 ({{ wbTokenInfo.permissions.length }} 项)</summary>
            <div class="mt-2 p-2 bg-gray-50 rounded text-xs max-h-40 overflow-y-auto">
              <span v-for="p in wbTokenInfo.permissions" :key="p" class="inline-block bg-white border rounded px-2 py-0.5 m-0.5">{{ p }}</span>
            </div>
          </details>

          <div class="flex gap-2 justify-end pt-2">
            <button @click="closeWorkBuddyModal" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">
              关闭
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api'

const { t } = useI18n()

const ROLE_LABELS = {
  admin: 'settings.roleAdmin',
  manager: 'settings.roleManager',
  operator: 'settings.roleOperator',
  member: 'settings.roleMember',
  warehouse: 'settings.roleWarehouse',
  customer: 'settings.roleCustomer',
}
function roleLabel(role) {
  return ROLE_LABELS[role] ? t(ROLE_LABELS[role]) : role
}

const activeTab = ref('internal')
const searchInternal = ref('')
const searchExternal = ref('')
const internalUsers = ref([])
const externalUsers = ref([])
const showQRModal = ref(false)
const currentQRPath = ref('')
const currentIdentityCode = ref('')

onMounted(() => {
  loadInternalUsers()
})

async function loadInternalUsers() {
  try {
    const res = await api.get('/users')
    if (res.code === 0) {
      internalUsers.value = res.data
    }
  } catch (err) {
    console.error('Failed to load internal users:', err)
  }
}

async function loadExternalUsers() {
  try {
    const res = await api.get('/h5-admin/users')
    if (res.code === 0) {
      externalUsers.value = res.data
    }
  } catch (err) {
    console.error('Failed to load external users:', err)
  }
}

async function generateIdentityCode(userId, userType) {
  try {
    const endpoint = userType === 'system' ? '/identity/system/generate' : '/identity/h5/generate'
    const res = await api.post(endpoint, { userId })
    if (res.code === 0) {
      alert(t('identityQR.generateCode') + ' ' + t('common.success'))
      if (userType === 'system') {
        loadInternalUsers()
      } else {
        loadExternalUsers()
      }
    }
  } catch (err) {
    alert(t('common.error') + ': ' + (err.response?.data?.message || err.message))
  }
}

async function deleteIdentityCode(userId, userType) {
  if (!confirm(t('common.confirmDelete'))) return
  try {
    const endpoint = `/identity/system/${userId}`
    await api.delete(endpoint)
    alert(t('common.success'))
    loadInternalUsers()
  } catch (err) {
    alert(t('common.error') + ': ' + (err.response?.data?.message || err.message))
  }
}

function viewIdentityQR(user) {
  currentQRPath.value = user.identity_qr_path
  currentIdentityCode.value = user.identity_code
  showQRModal.value = true
}

function editH5User(user) {
  // TODO: Implement H5 user edit modal
  alert('Edit H5 user: ' + user.phone)
}

// ==================== 2026-08-29 WorkBuddy 连接链接 ====================
const showWBModal = ref(false)
const wbTargetUser = ref(null)
const wbLoginKey = ref('')
const wbPassword = ref('')
const wbExpiresDays = ref(7)
const wbLoading = ref(false)
const wbError = ref('')
const wbToken = ref('')
const wbTokenInfo = ref(null)
const wbCopied = ref(false)
const wbCopiedToken = ref(false)
const wbLinkInputRef = ref(null)

function openWorkBuddyModal(user) {
  wbTargetUser.value = user
  // 默认填入该用户的手机或邮箱 (placeholder)
  wbLoginKey.value = user.phone || user.email || ''
  wbPassword.value = ''
  wbExpiresDays.value = 7
  wbLoading.value = false
  wbError.value = ''
  wbToken.value = ''
  wbTokenInfo.value = null
  wbCopied.value = false
  wbCopiedToken.value = false
  showWBModal.value = true
}

function closeWorkBuddyModal() {
  showWBModal.value = false
  wbPassword.value = ''
  wbError.value = ''
}

async function generateWorkBuddyLink() {
  if (!wbPassword.value) {
    wbError.value = '请输入密码'
    return
  }
  if (!wbLoginKey.value) {
    wbLoginKey.value = wbTargetUser.value?.phone || wbTargetUser.value?.email || ''
  }
  wbLoading.value = true
  wbError.value = ''
  try {
    const res = await api.post('/auth/workbuddy-token', {
      login_key: wbLoginKey.value,
      password: wbPassword.value,
      expires_days: wbExpiresDays.value,
    })
    if (res.code === 0 && res.data) {
      wbToken.value = res.data.token
      wbTokenInfo.value = res.data
      wbPassword.value = ''  // 用完即清, 不留在内存
    } else {
      wbError.value = res.message || '生成失败'
    }
  } catch (err) {
    wbError.value = err.response?.data?.message || err.message || '生成失败'
  } finally {
    wbLoading.value = false
  }
}

async function copyWorkBuddyLink() {
  if (!wbTokenInfo.value?.connect_link) return
  try {
    await navigator.clipboard.writeText(wbTokenInfo.value.connect_link)
    wbCopied.value = true
    setTimeout(() => { wbCopied.value = false }, 2000)
  } catch {
    // fallback: 选中 input 让用户手动 Ctrl+C
    wbLinkInputRef.value?.select?.()
  }
}

async function copyWorkBuddyToken() {
  if (!wbTokenInfo.value?.token) return
  try {
    await navigator.clipboard.writeText(wbTokenInfo.value.token)
    wbCopiedToken.value = true
    setTimeout(() => { wbCopiedToken.value = false }, 2000)
  } catch {}
}
</script>

<style scoped>
@media (max-width: 768px) {
  /* 容器内边距缩小 */
  .p-6 {
    padding: 1rem;
  }

  /* 标题区域 */
  .mb-6 {
    margin-bottom: 1rem;
  }

  .text-2xl {
    font-size: 1.25rem;
  }

  /* Tab 导航 - 缩小间距 */
  .border-b button {
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
  }

  /* 搜索表单 - 垂直堆叠 */
  .p-6 > .flex {
    flex-direction: column;
    align-items: stretch;
    gap: 0.5rem;
  }

  .p-6 input[type="text"] {
    width: 100%;
  }

  .p-6 button {
    width: 100%;
  }

  /* 表格横向滚动 */
  .overflow-x-auto {
    margin: 0 -1rem;
    padding: 0 1rem;
  }

  /* 表格单元格 - 缩小内边距 */
  table th,
  table td {
    padding: 0.5rem;
    font-size: 0.75rem;
  }

  /* 身份码 - 缩小显示 */
  .text-xs {
    font-size: 0.625rem;
    padding: 0.125rem 0.25rem;
  }

  /* 按钮 - 缩小尺寸 */
  .px-4 {
    padding: 0.375rem 0.75rem;
  }

  /* 操作按钮 */
  td button {
    font-size: 0.75rem;
    margin-right: 0.25rem;
  }

  /* Modal 弹窗 - 全屏适配 */
  .fixed.inset-0 {
    padding: 1rem;
  }

  .bg-white.rounded-lg.p-6 {
    width: 100%;
    max-width: 100%;
    padding: 1rem;
  }

  .max-w-md {
    max-width: 100%;
  }
}
</style>
