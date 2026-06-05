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
                    class="text-red-600 hover:underline"
                  >
                    {{ t('common.delete') }}
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
