<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElCollapse, ElCollapseItem, ElCheckbox, ElCheckboxGroup, ElButton, ElInput, ElMessage, ElMessageBox } from 'element-plus'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'

const { t } = useI18n()

// ─── State ─────────────────────────────────────────────────────────────────────
const roles = ref([])
const rolesLoading = ref(false)
const allPermissions = ref([]) // all permission definitions from API
const selectedRoleId = ref(null)
const rolePermissions = ref([]) // permission_ids for selected role
const pendingPermissions = ref([]) // local changes before save
const isDirty = ref(false)
const savingPermissions = ref(false)

// ─── Add/Edit Role Modal ────────────────────────────────────────────────────────
const showRoleModal = ref(false)
const editingRole = ref(null)
const roleForm = ref({ name: '', label: '' })
const roleLoading = ref(false)
const roleError = ref('')

// ─── Computed ──────────────────────────────────────────────────────────────────
const selectedRole = computed(() => roles.value.find(r => r.id === selectedRoleId.value))

const permissionsByCategory = computed(() => {
  const map = {}
  const perms = allPermissions.value || []
  for (const p of perms) {
    const cat = p.category || 'other'
    if (!map[cat]) map[cat] = []
    map[cat].push(p)
  }
  return map
})

const categoryOrder = ['system', 'product', 'warehouse', 'stock', 'finance', 'retail', 'aftersale', 'oa', 'supply', 'work_log', 'bi', 'report']

const sortedCategories = computed(() => {
  return categoryOrder.filter(cat => permissionsByCategory.value[cat]?.length)
})

// ─── Load data ─────────────────────────────────────────────────────────────────
async function loadRoles() {
  rolesLoading.value = true
  try {
    const res = await api.get('/rbac/roles')
    if (res.code === 0) roles.value = res.data || []
  } catch (e) {
    ElMessage.error(t('settings.loadRolesFailed'))
  } finally {
    rolesLoading.value = false
  }
}

async function loadAllPermissions() {
  try {
    const res = await api.get('/rbac/permissions')
    if (res.code === 0) allPermissions.value = res.data?.list || res.data || []
  } catch (e) {
    ElMessage.error(t('settings.loadPermissionsFailed'))
  }
}

async function loadRolePermissions(roleId) {
  try {
    const res = await api.get(`/rbac/roles/${roleId}/permissions`)
    if (res.code === 0) {
      rolePermissions.value = (res.data || []).map(p => p.id)
      pendingPermissions.value = [...rolePermissions.value]
      isDirty.value = false
    }
  } catch (e) {
    ElMessage.error(t('settings.loadRolePermissionsFailed'))
  }
}

// ─── Role selection ────────────────────────────────────────────────────────────
function selectRole(role) {
  if (isDirty.value) {
    ElMessageBox.confirm(t('settings.unsavedChangesTip'), t('common.tip'), {
      confirmButtonText: t('common.confirm'),
      cancelButtonText: t('common.cancel'),
      type: 'warning',
    }).then(() => {
      selectedRoleId.value = role.id
    }).catch(() => {})
  } else {
    selectedRoleId.value = role.id
  }
}

watch(selectedRoleId, (newId) => {
  if (newId) loadRolePermissions(newId)
})

// ─── Permission changes ────────────────────────────────────────────────────────
function onPermissionChange(permId, checked) {
  if (checked) {
    if (!pendingPermissions.value.includes(permId)) {
      pendingPermissions.value.push(permId)
    }
  } else {
    const idx = pendingPermissions.value.indexOf(permId)
    if (idx !== -1) pendingPermissions.value.splice(idx, 1)
  }
  isDirty.value = true
}

function isPermChecked(permId) {
  return pendingPermissions.value.includes(permId)
}

async function savePermissions() {
  if (!selectedRoleId.value) return
  savingPermissions.value = true
  try {
    const res = await api.put(`/rbac/roles/${selectedRoleId.value}/permissions`, {
      permission_ids: pendingPermissions.value,
    })
    if (res.code === 0) {
      rolePermissions.value = [...pendingPermissions.value]
      isDirty.value = false
      ElMessage.success(t('settings.savePermissionsSuccess'))
    } else {
      ElMessage.error(res.message || t('settings.savePermissionsFailed'))
    }
  } catch (e) {
    ElMessage.error(e.message || t('settings.savePermissionsFailed'))
  } finally {
    savingPermissions.value = false
  }
}

function cancelPermissionChanges() {
  pendingPermissions.value = [...rolePermissions.value]
  isDirty.value = false
}

// ─── Add/Edit Role ─────────────────────────────────────────────────────────────
function openAddRole() {
  editingRole.value = null
  roleForm.value = { name: '', label: '' }
  roleError.value = ''
  showRoleModal.value = true
}

function openEditRole(role, event) {
  event.stopPropagation()
  editingRole.value = role
  roleForm.value = { name: role.name || '', label: role.label || role.name || '' }
  roleError.value = ''
  showRoleModal.value = true
}

async function saveRole() {
  if (!roleForm.value.name.trim()) {
    roleError.value = t('settings.roleNameRequired')
    return
  }
  roleLoading.value = true
  roleError.value = ''
  try {
    let res
    if (editingRole.value) {
      res = await api.put(`/rbac/roles/${editingRole.value.id}`, {
        name: roleForm.value.name.trim(),
        label: roleForm.value.label.trim(),
      })
    } else {
      res = await api.post('/rbac/roles', {
        name: roleForm.value.name.trim(),
        label: roleForm.value.label.trim(),
      })
    }
    if (res.code === 0) {
      showRoleModal.value = false
      await loadRoles()
      ElMessage.success(editingRole.value ? t('settings.updateRoleSuccess') : t('settings.addRoleSuccess'))
    } else {
      roleError.value = res.message || t('settings.operationFailed')
    }
  } catch (e) {
    roleError.value = e.message || t('settings.requestFailed')
  } finally {
    roleLoading.value = false
  }
}

async function deleteRole(role, event) {
  event.stopPropagation()
  try {
    await ElMessageBox.confirm(
      t('settings.confirmDeleteRole', { name: role.label || role.name }),
      t('common.tip'),
      { confirmButtonText: t('common.confirm'), cancelButtonText: t('common.cancel'), type: 'warning' }
    )
    const res = await api.delete(`/rbac/roles/${role.id}`)
    if (res.code === 0) {
      if (selectedRoleId.value === role.id) {
        selectedRoleId.value = null
        rolePermissions.value = []
        pendingPermissions.value = []
        isDirty.value = false
      }
      await loadRoles()
      ElMessage.success(t('settings.deleteRoleSuccess'))
    } else {
      ElMessage.error(res.message || t('settings.deleteFailed'))
    }
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || t('settings.deleteFailed'))
    }
  }
}

// ─── Init ──────────────────────────────────────────────────────────────────────
onMounted(async () => {
  await Promise.all([loadRoles(), loadAllPermissions()])
})
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <PageHeader :title="$t('settings.roleManage')" :subtitle="$t('settings.roleManageSubtitle')" />

    <div class="max-w-screen-xl mx-auto px-6 py-6">
      <div class="flex gap-6">
        <!-- Left: Role List -->
        <div class="w-80 flex-shrink-0">
          <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between mb-4">
              <h3 class="font-medium text-gray-700">{{ $t('settings.roleList') }}</h3>
              <el-button type="primary" size="small" @click="openAddRole">
                {{ $t('settings.addRole') }}
              </el-button>
            </div>

            <div v-if="rolesLoading" class="text-center py-8 text-gray-400">
              {{ $t('common.loading') }}...
            </div>

            <div v-else-if="roles.length === 0" class="text-center py-8 text-gray-400">
              {{ $t('settings.noRoles') }}
            </div>

            <div v-else class="space-y-2">
              <div
                v-for="role in roles"
                :key="role.id"
                @click="selectRole(role)"
                :class="[
                  'p-3 rounded-lg cursor-pointer border transition-all',
                  selectedRoleId === role.id
                    ? 'border-blue-500 bg-blue-50'
                    : 'border-gray-200 hover:border-gray-300 bg-white'
                ]"
              >
                <div class="flex items-start justify-between">
                  <div class="flex-1 min-w-0">
                    <div class="font-medium text-gray-800 truncate">
                      {{ role.label || role.name }}
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">
                      {{ (role.user_count || 0) + $t('settings.people') }}
                    </div>
                  </div>
                  <div class="flex items-center gap-1 ml-2">
                    <el-button
                      size="small"
                      text
                      @click="openEditRole(role, $event)"
                      :title="$t('common.edit')"
                    >
                      <span class="text-gray-400 hover:text-blue-500">✏️</span>
                    </el-button>
                    <el-button
                      size="small"
                      text
                      @click="deleteRole(role, $event)"
                      :title="$t('common.delete')"
                    >
                      <span class="text-gray-400 hover:text-red-500">🗑️</span>
                    </el-button>
                  </div>
                </div>
                <div v-if="role.permissions?.length" class="mt-1 text-xs text-gray-400">
                  {{ $t('settings.permissionCount') }}{{ role.permissions.length }}{{ $t('settings.permissionUnit') }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Permission Panel -->
        <div class="flex-1 min-w-0">
          <div class="bg-white rounded-lg shadow p-6">
            <template v-if="!selectedRoleId">
              <div class="text-center py-16 text-gray-400">
                <div class="text-4xl mb-2">👆</div>
                <div>{{ $t('settings.selectRoleToManagePermissions') }}</div>
              </div>
            </template>

            <template v-else>
              <div class="flex items-center justify-between mb-6">
                <h3 class="font-medium text-gray-700">
                  {{ $t('settings.editPermissions') }}: {{ selectedRole?.label || selectedRole?.name }}
                </h3>
                <div class="flex gap-2">
                  <el-button
                    v-if="isDirty"
                    @click="cancelPermissionChanges"
                    size="small"
                  >
                    {{ $t('common.cancel') }}
                  </el-button>
                  <el-button
                    type="primary"
                    size="small"
                    :loading="savingPermissions"
                    :disabled="!isDirty"
                    @click="savePermissions"
                  >
                    {{ $t('common.save') }}
                  </el-button>
                </div>
              </div>

              <div v-if="sortedCategories.length === 0" class="text-center py-8 text-gray-400">
                {{ $t('settings.noPermissions') }}
              </div>

              <el-collapse v-else class="role-permission-collapse" :model-value="sortedCategories">
                <el-collapse-item
                  v-for="cat in sortedCategories"
                  :key="cat"
                  :title="$t('settings.category_' + cat)"
                  :name="cat"
                >
                  <div class="grid grid-cols-2 gap-2">
                    <div
                      v-for="perm in permissionsByCategory[cat]"
                      :key="perm.id"
                      class="flex items-center gap-2 p-2 rounded hover:bg-gray-50"
                    >
                      <el-checkbox
                        :model-value="isPermChecked(perm.id)"
                        @change="(val) => onPermissionChange(perm.id, val)"
                      >
                        <span class="text-sm">{{ perm.label || perm.name }}</span>
                      </el-checkbox>
                      <span class="text-xs text-gray-400 font-mono">{{ perm.name }}</span>
                    </div>
                  </div>
                </el-collapse-item>
              </el-collapse>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Role Modal -->
    <el-dialog
      v-model="showRoleModal"
      :title="editingRole ? $t('settings.editRole') : $t('settings.addRole')"
      width="400px"
      :close-on-click-modal="false"
    >
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            {{ $t('settings.roleName') }} *
          </label>
          <el-input
            v-model="roleForm.name"
            :placeholder="$t('settings.roleNamePlaceholder')"
            @keyup.enter="saveRole"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            {{ $t('settings.roleLabel') }}
          </label>
          <el-input
            v-model="roleForm.label"
            :placeholder="$t('settings.roleLabelPlaceholder')"
            @keyup.enter="saveRole"
          />
        </div>
        <div v-if="roleError" class="text-sm text-red-500">{{ roleError }}</div>
      </div>
      <template #footer>
        <el-button @click="showRoleModal = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" :loading="roleLoading" @click="saveRole">
          {{ $t('common.save') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.role-permission-collapse :deep(.el-collapse-item__header) {
  font-weight: 500;
  font-size: 15px;
}
.role-permission-collapse :deep(.el-collapse-item__content) {
  padding-bottom: 12px;
}
</style>