<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Pagination from '../../components/Pagination.vue'

const { t } = useI18n()
const loading = ref(false)
const users = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(50)

const search = ref('')
const roleFilter = ref('')
const statusFilter = ref('')

const roles = ref([])
const stores = ref([])

const editingUser = ref(null)
const showEditDialog = ref(false)

onMounted(async () => {
  await Promise.all([fetchUsers(), fetchRoles(), fetchStores()])
})

async function fetchUsers() {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: page.value,
      limit: limit.value,
      search: search.value,
      role: roleFilter.value,
      status: statusFilter.value
    })
    const res = await fetch(`/api/h5-admin/users?${params}`, {
      headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
    })
    const json = await res.json()
    if (json.code === 0) {
      users.value = json.data.list
      total.value = json.data.total
    }
  } finally {
    loading.value = false
  }
}

async function fetchRoles() {
  const res = await fetch('/api/h5/roles')
  const json = await res.json()
  if (json.code === 0) roles.value = json.data
}

async function fetchStores() {
  const res = await fetch('/api/stores?page=1&limit=1000', {
    headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
  })
  const json = await res.json()
  if (json.code === 0) stores.value = json.data.list
}

function editUser(user) {
  editingUser.value = { ...user }
  showEditDialog.value = true
}

async function saveUser() {
  try {
    const res = await fetch(`/api/h5-admin/users/${editingUser.value.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token')}`
      },
      body: JSON.stringify({
        role: editingUser.value.role,
        store_id: editingUser.value.store_id || null,
        level: editingUser.value.level,
        status: editingUser.value.status
      })
    })
    const json = await res.json()
    if (json.code === 0) {
      showEditDialog.value = false
      await fetchUsers()
    } else {
      alert(json.message)
    }
  } catch (err) {
    alert(t('settings.saveFailed'))
  }
}

function handlePageChange(newPage) {
  page.value = newPage
  fetchUsers()
}

function handleSearch() {
  page.value = 1
  fetchUsers()
}

function statusLabel(status) {
  return status === 'active' ? t('settings.activeStatus') : t('settings.disabledStatus')
}

function statusColor(status) {
  return status === 'active' ? 'text-success' : 'text-danger'
}
</script>

<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-text-primary">{{ t('nav.h5Users') }}</h1>
      <p class="text-sm text-text-secondary mt-1">{{ $t('settings.h5UserSubtitle') }}</p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <input
          v-model="search"
          type="text"
          :placeholder="$t('settings.searchNameOrPhone')"
          class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none"
          @keyup.enter="handleSearch"
        />
        <select
          v-model="roleFilter"
          class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none"
          @change="handleSearch"
        >
          <option value="">{{ $t('settings.allRoles') }}</option>
          <option v-for="role in roles" :key="role.name" :value="role.name">{{ role.label }}</option>
        </select>
        <select
          v-model="statusFilter"
          class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none"
          @change="handleSearch"
        >
          <option value="">{{ $t('settings.allStatusFilter') }}</option>
          <option value="active">{{ $t('settings.activeStatus') }}</option>
          <option value="disabled">{{ $t('settings.disabledStatus') }}</option>
        </select>
        <button
          @click="handleSearch"
          class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
        >
          {{ t('common.search') }}
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">ID</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('settings.nameCol') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('settings.phoneCol') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('settings.roleCol') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('settings.storeCol') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('settings.levelCol') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('settings.statusCol') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('settings.registerTimeCol') }}</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">{{ $t('settings.actionCol') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-if="loading">
              <td colspan="9" class="px-4 py-8 text-center text-text-secondary">{{ t('common.loading') }}</td>
            </tr>
            <tr v-else-if="users.length === 0">
              <td colspan="9" class="px-4 py-8 text-center text-text-secondary">{{ t('common.noData') }}</td>
            </tr>
            <tr v-else v-for="user in users" :key="user.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm text-text-primary">{{ user.id }}</td>
              <td class="px-4 py-3 text-sm text-text-primary">{{ user.name }}</td>
              <td class="px-4 py-3 text-sm text-text-primary font-mono">{{ user.phone }}</td>
              <td class="px-4 py-3 text-sm">
                <span class="px-2 py-1 bg-primary/10 text-primary rounded text-xs">{{ user.role_label }}</span>
              </td>
              <td class="px-4 py-3 text-sm text-text-secondary">{{ user.store_name || '-' }}</td>
              <td class="px-4 py-3 text-sm text-text-primary">{{ user.level }}</td>
              <td class="px-4 py-3 text-sm">
                <span :class="['font-medium', statusColor(user.status)]">{{ statusLabel(user.status) }}</span>
              </td>
              <td class="px-4 py-3 text-sm text-text-secondary">{{ user.created_at?.slice(0, 10) }}</td>
              <td class="px-4 py-3 text-sm">
                <button
                  @click="editUser(user)"
                  class="text-primary hover:text-primary-hover font-medium"
                >
                  {{ t('common.edit') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination
        :current-page="page"
        :total="total"
        :page-size="limit"
        @page-change="handlePageChange"
      />
    </div>

    <!-- Edit Dialog -->
    <div v-if="showEditDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showEditDialog = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold text-text-primary mb-4">{{ $t('settings.editH5User') }}</h2>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">{{ $t('settings.nameCol') }}</label>
            <input
              :value="editingUser.name"
              disabled
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">{{ $t('settings.phoneCol') }}</label>
            <input
              :value="editingUser.phone"
              disabled
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 font-mono"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">{{ $t('settings.roleCol') }}</label>
            <select
              v-model="editingUser.role"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none"
            >
              <option v-for="role in roles" :key="role.name" :value="role.name">{{ role.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">{{ $t('settings.associatedStore') }}</label>
            <select
              v-model="editingUser.store_id"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none"
            >
              <option :value="null">{{ $t('settings.noneOption') }}</option>
              <option v-for="store in stores" :key="store.id" :value="store.id">{{ store.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">{{ $t('settings.userLevel') }}</label>
            <input
              v-model.number="editingUser.level"
              type="number"
              min="1"
              max="10"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">{{ $t('settings.statusCol') }}</label>
            <select
              v-model="editingUser.status"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none"
            >
              <option value="active">{{ $t('settings.activeStatus') }}</option>
              <option value="disabled">{{ $t('settings.disabledStatus') }}</option>
            </select>
          </div>
        </div>
        <div class="flex gap-3 mt-6">
          <button
            @click="showEditDialog = false"
            class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-text-secondary hover:bg-gray-50 transition-colors"
          >
            {{ t('common.cancel') }}
          </button>
          <button
            @click="saveUser"
            class="flex-1 px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors"
          >
            {{ t('common.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
