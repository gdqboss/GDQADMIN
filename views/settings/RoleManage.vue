<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElTabs, ElTabPane, ElButton, ElInput, ElMessage, ElMessageBox, ElTag } from 'element-plus'
import PageHeader from '../../components/PageHeader.vue'
import JobResponsibilities from './JobResponsibilities.vue'
import api from '../../services/api.js'

const { t } = useI18n()

// ─── State ─────────────────────────────────────────────────────────────────────
const activeTab = ref('permissions')
const roles = ref([])
const rolesLoading = ref(false)
const allPermissions = ref([])
const expandedRoleId = ref(null)
const savingRoleId = ref(null)
const rolePendingPerms = ref({}) // roleId -> Set of pending permIds

// ─── Computed ──────────────────────────────────────────────────────────────────
const permissionsByCategory = computed(() => {
  const map = {}
  for (const p of (allPermissions.value || [])) {
    const cat = p.category || 'other'
    if (!map[cat]) map[cat] = []
    map[cat].push(p)
  }
  return map
})

const categoryOrder = [
  'system', 'product', 'warehouse', 'stock',
  'finance', 'retail', 'aftersale', 'oa',
  'supply', 'work_log', 'bi', 'report',
  'inventory', 'order', 'approval', 'attendance',
  'leave', 'shift', 'schedule', 'task',
  'qrcode', 'referral', 'wecom', 'ai-automation',
  'workflow', 'workbench',
  'navigation', 'quick-action', 'action',
]

const sortedCategories = computed(() => {
  return categoryOrder.filter(cat => permissionsByCategory.value[cat]?.length)
})

// Build a set of permission_ids for each role from the pending map (or initial permission_ids if not expanded)
// Only mutate role.permission_ids when explicitly saved
function getRolePermSet(role) {
  if (rolePendingPerms.value[role.id] !== undefined) {
    return rolePendingPerms.value[role.id]
  }
  const ids = role.permission_ids || ''
  return new Set(ids.split(',').filter(Boolean).map(Number))
}

function ensurePendingPerms(role) {
  if (rolePendingPerms.value[role.id] === undefined) {
    const ids = role.permission_ids || ''
    rolePendingPerms.value[role.id] = new Set(ids.split(',').filter(Boolean).map(Number))
  }
}

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

// ─── Role card expand/collapse ─────────────────────────────────────────────────
function toggleRole(roleId) {
  expandedRoleId.value = expandedRoleId.value === roleId ? null : roleId
}

// ─── Permission tag toggle ────────────────────────────────────────────────────
function togglePermission(role, permId) {
  ensurePendingPerms(role)
  const idSet = rolePendingPerms.value[role.id]
  if (idSet.has(permId)) {
    idSet.delete(permId)
  } else {
    idSet.add(permId)
  }
  // Update the pending count on the role card so the tag shows live count
  role.permission_count = [...idSet].size
  // Trigger reactivity
  rolePendingPerms.value = { ...rolePendingPerms.value }
}

function isPermChecked(role, permId) {
  return getRolePermSet(role).has(permId)
}

// ─── Save permissions for a role ──────────────────────────────────────────────
async function saveRolePermissions(role) {
  const idSet = getRolePermSet(role)
  savingRoleId.value = role.id
  try {
    const res = await api.put(`/rbac/roles/${role.id}/permissions`, {
      permission_ids: [...idSet],
    })
    if (res.code === 0) {
      if (res.data?.length) {
        role.permission_names = res.data.map(p => p.name).join(',')
      }
      role.permission_count = [...idSet].size
      role.permission_ids = [...idSet].join(',')
      // Clear pending
      const pending = { ...rolePendingPerms.value }
      delete pending[role.id]
      rolePendingPerms.value = pending
      ElMessage.success(t('settings.savePermissionsSuccess'))
      expandedRoleId.value = null
    } else {
      ElMessage.error(res.message || t('settings.savePermissionsFailed'))
    }
  } catch (e) {
    ElMessage.error(e.message || t('settings.savePermissionsFailed'))
  } finally {
    savingRoleId.value = null
  }
}

// ─── Add/Edit Role ─────────────────────────────────────────────────────────────
const showRoleModal = ref(false)
const editingRole = ref(null)
const roleForm = ref({ name: '', label: '' })
const roleLoading = ref(false)
const roleError = ref('')

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
  if (!roleForm.value.name.trim()) { roleError.value = t('settings.roleNameRequired'); return }
  if (!roleForm.value.label.trim()) { roleError.value = '角色显示名必填'; return }
  roleLoading.value = true
  roleError.value = ''
  try {
    let res
    if (editingRole.value) {
      res = await api.put(`/rbac/roles/${editingRole.value.id}`, { name: roleForm.value.name.trim(), label: roleForm.value.label.trim() })
    } else {
      res = await api.post('/rbac/roles', { name: roleForm.value.name.trim(), label: roleForm.value.label.trim() })
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
    await ElMessageBox.confirm(t('settings.confirmDeleteRole', { name: role.label || role.name }), t('common.tip'), { confirmButtonText: t('common.confirm'), cancelButtonText: t('common.cancel'), type: 'warning' })
    const res = await api.delete(`/rbac/roles/${role.id}`)
    if (res.code === 0) {
      if (expandedRoleId.value === role.id) expandedRoleId.value = null
      await loadRoles()
      ElMessage.success(t('settings.deleteRoleSuccess'))
    } else {
      ElMessage.error(res.message || t('settings.deleteFailed'))
    }
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || t('settings.deleteFailed'))
  }
}

// ─── Init ─────────────────────────────────────────────────────────────────────
onMounted(async () => { await Promise.all([loadRoles(), loadAllPermissions()]) })
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <PageHeader :title="$t('settings.roleManage')" :subtitle="$t('settings.roleManageSubtitle')" />

    <div class="max-w-screen-xl mx-auto px-6 py-6">
      <el-tabs v-model="activeTab" class="settings-tabs">
        <el-tab-pane :label="$t('settings.rolePermissionsTab')" name="permissions">
          <div class="flex gap-6">
            <!-- Left: Role List Cards -->
            <div class="w-96 flex-shrink-0">
              <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="font-medium text-gray-700">{{ $t('settings.roleList') }}</h3>
                  <el-button type="primary" size="small" @click="openAddRole">{{ $t('settings.addRole') }}</el-button>
                </div>

                <div v-if="rolesLoading" class="text-center py-8 text-gray-400">{{ $t('common.loading') }}...</div>
                <div v-else-if="roles.length === 0" class="text-center py-8 text-gray-400">{{ $t('settings.noRoles') }}</div>

                <div v-else class="space-y-3">
                  <div
                    v-for="role in roles"
                    :key="role.id"
                    :class="['rounded-lg border transition-all', expandedRoleId === role.id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white hover:border-gray-300']"
                  >
                    <!-- Card header (always visible) -->
                    <div
                      class="p-3 cursor-pointer flex items-start justify-between"
                      @click="toggleRole(role.id)"
                    >
                      <div class="flex-1 min-w-0">
                        <div class="font-medium text-gray-800">{{ role.label || role.name }}</div>
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                          <span class="text-xs text-gray-400">{{ (role.user_count || 0) }}{{ $t('settings.people') }}</span>
                          <el-tag type="info" size="small">{{ role.permission_count || 0 }} 权限</el-tag>
                        </div>
                        <!-- Permission names preview (collapsed) -->
                        <div v-if="expandedRoleId !== role.id && role.permission_names" class="mt-1.5 text-xs text-gray-400 truncate">
                          {{ role.permission_names }}
                        </div>
                      </div>
                      <div class="flex items-center gap-1 ml-2" @click.stop>
                        <el-button size="small" text @click="openEditRole(role, $event)" :title="$t('common.edit')">
                          <span class="text-gray-400 hover:text-blue-500">✏️</span>
                        </el-button>
                        <el-button size="small" text @click="deleteRole(role, $event)" :title="$t('common.delete')">
                          <span class="text-gray-400 hover:text-red-500">🗑️</span>
                        </el-button>
                        <span class="text-gray-300 text-xs ml-1">{{ expandedRoleId === role.id ? '▲' : '▼' }}</span>
                      </div>
                    </div>

                    <!-- Expanded: permission tags + save -->
                    <div v-if="expandedRoleId === role.id" class="px-3 pb-3 border-t border-blue-100 pt-3">
                      <div class="space-y-3 max-h-80 overflow-y-auto">
                        <div v-for="cat in sortedCategories" :key="cat">
                          <div class="text-xs font-medium text-gray-500 uppercase mb-1.5 tracking-wide">
                            {{ $t('settings.category_' + cat) }}
                          </div>
                          <div class="flex flex-wrap gap-1.5">
                            <el-tag
                              v-for="perm in permissionsByCategory[cat]"
                              :key="perm.id"
                              :type="isPermChecked(role, perm.id) ? 'primary' : 'info'"
                              class="cursor-pointer text-xs select-none"
                              :class="isPermChecked(role, perm.id) ? '' : 'opacity-60'"
                              @click="togglePermission(role, perm.id)"
                            >
                              {{ perm.label || perm.name }}
                            </el-tag>
                          </div>
                        </div>
                      </div>
                      <div class="mt-3 flex justify-end">
                        <el-button
                          type="primary"
                          size="small"
                          :loading="savingRoleId === role.id"
                          @click="saveRolePermissions(role)"
                        >
                          {{ $t('common.save') }}
                        </el-button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right: placeholder to keep layout stable -->
            <div class="flex-1 min-w-0" />
          </div>
        </el-tab-pane>

        <el-tab-pane :label="$t('settings.jobResponsibilitiesTab')" name="responsibilities">
          <JobResponsibilities />
        </el-tab-pane>
      </el-tabs>
    </div>

    <!-- Add/Edit Role Modal -->
    <el-dialog v-model="showRoleModal" :title="editingRole ? $t('settings.editRole') : $t('settings.addRole')" width="400px" :close-on-click-modal="false">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('settings.roleName') }} *</label>
          <el-input v-model="roleForm.name" :placeholder="$t('settings.roleNamePlaceholder')" @keyup.enter="saveRole" />
          <div class="text-xs text-gray-400 mt-1">英文标识，如 admin / manager</div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">角色显示名 *</label>
          <el-input v-model="roleForm.label" placeholder="如 管理员" @keyup.enter="saveRole" />
        </div>
        <div v-if="roleError" class="text-sm text-red-500">{{ roleError }}</div>
      </div>
      <template #footer>
        <el-button @click="showRoleModal = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" :loading="roleLoading" @click="saveRole">{{ $t('common.save') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  .max-w-screen-xl.mx-auto.px-6.py-6 { padding: 12px; }
  .flex.gap-6 { flex-direction: column; gap: 12px; }
  .w-96.flex-shrink-0 { width: 100%; }
}
</style>