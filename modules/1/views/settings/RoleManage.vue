<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElTabs, ElTabPane, ElButton, ElInput, ElMessage, ElMessageBox, ElTag, ElTooltip } from 'element-plus'
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

// ─── 用户视角预设模板（按真实业务场景组织，一键加载）────────────────────────────
// 原则：每个模板 = 一个角色的日常工作需要的权限全集
// 设计逻辑：登录后看到的菜单/按钮/快捷操作 = 这些权限自动覆盖
const PRESET_TEMPLATES = [
  {
    key: 'admin',
    label: '超级管理员',
    icon: '👑',
    description: '所有功能全部可用（93 个权限）',
    color: 'red',
    permNames: ['admin'],  // 后端 admin short-cut：返回所有
  },
  {
    key: 'warehouse_keeper',
    label: '仓库管理员',
    icon: '📦',
    description: '仓库/库存/盘点/二维码/扫描 — 日常仓库工作',
    color: 'blue',
    permNames: [
      'warehouse:read', 'warehouse:write',
      'inventory:read', 'inventory:write', 'inventory:delete',
      'transfer:read', 'transfer:write', 'inventory:return',
      'stocktake:run', 'stocktake:report',
      'qrcode:read', 'qrcode:write', 'qrcode:delete', 'qrcode:scan',
      'products:read', 'products:write',  // ⚠ DB 是 products:write 不是 product:write
      // 快捷操作（工作台首页）
      'quick-action-attendance', 'quick-action-worklog', 'quick-action-task',
      'quick-action-scan', 'quick-action-responsibility', 'quick-action-profile',
      'quick-action-qrcode',
    ],
  },
  {
    key: 'salesperson',
    label: '销售员',
    icon: '💼',
    description: '订单/客户/商品查看/扫码 — 门店销售工作',
    color: 'green',
    permNames: [
      'order:read', 'order:write',
      'products:read',
      'qrcode:read', 'qrcode:scan',
      'aftersale:read', 'aftersale:write', 'scan:aftersale', 'scan:repair',
      'store:read', 'dealer:read',
      'task:read',
      // 快捷操作（工作台首页）
      'quick-action-attendance', 'quick-action-worklog', 'quick-action-task',
      'quick-action-scan', 'quick-action-responsibility', 'quick-action-profile',
    ],
  },
  {
    key: 'finance',
    label: '财务人员',
    icon: '💰',
    description: '财务/收款/付款/发票/报销审批',
    color: 'orange',
    permNames: [
      'finance:read', 'finance:write',
      'finance:receipt', 'finance:payment', 'finance:purchase',
      'finance:sales', 'finance:expense', 'finance:invoice', 'finance:reminder',
      'report:read',
      'approval:read', 'approval:write',
      'supplier:read', 'dealer:read',
    ],
  },
  {
    key: 'store_manager',
    label: '店长',
    icon: '🏪',
    description: '销售/库存/报表/任务分配 — 门店管理',
    color: 'purple',
    permNames: [
      'order:read', 'order:write',
      'products:read', 'products:write',
      'inventory:read', 'inventory:write',
      'transfer:read', 'transfer:write',
      'store:read', 'dealer:read',
      'report:read',
      'task:read',
      'aftersale:read', 'aftersale:write',
      'approval:read',
      'qrcode:read',
      // 快捷操作（工作台首页）
      'quick-action-attendance', 'quick-action-worklog', 'quick-action-task',
      'quick-action-scan', 'quick-action-responsibility', 'quick-action-expense',
      'quick-action-profile', 'quick-action-qrcode',
    ],
  },
  {
    key: 'operator',
    label: '操作员',
    icon: '⚙️',
    description: '商品管理/库存查询 — 基础数据维护',
    color: 'cyan',
    permNames: [
      'products:read', 'products:write', 'products:delete',
      'inventory:read',
      'qrcode:read', 'qrcode:write', 'qrcode:delete', 'qrcode:scan',
      'order:read',
    ],
  },
  {
    key: 'oa_user',
    label: '普通员工',
    icon: '👤',
    description: 'OA考勤/工作日志/任务 — 日常办公',
    color: 'gray',
    permNames: [
      'oa:read', 'oa:write',
      'attendance:view',
      'work_log:read', 'work_log:write',
      'task:read',
      'leave:read', 'leave:write',
      // 快捷操作（工作台首页）
      'quick-action-attendance', 'quick-action-worklog', 'quick-action-task',
      'quick-action-responsibility', 'quick-action-expense', 'quick-action-profile',
    ],
  },
]

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

// category 排序 + i18n label
const categoryOrder = [
  { key: 'system', label: '系统设置' },
  { key: 'product', label: '商品管理' },
  { key: 'warehouse', label: '仓库' },
  { key: 'inventory', label: '库存' },
  { key: 'stock', label: '库存预警' },
  { key: 'stock_movements', label: '库存流水' },
  { key: 'qrcode', label: '二维码' },
  { key: 'order', label: '订单' },
  { key: 'retail', label: '零售' },
  { key: 'aftersale', label: '售后' },
  { key: 'finance', label: '财务' },
  { key: 'report', label: '报表' },
  { key: 'bi', label: '数据分析' },
  { key: 'approval', label: '审批' },
  { key: 'task', label: '任务' },
  { key: 'attendance', label: '考勤' },
  { key: 'leave', label: '请假' },
  { key: 'shift', label: '排班' },
  { key: 'schedule', label: '日程' },
  { key: 'oa', label: 'OA 办公' },
  { key: 'work_log', label: '工作日志' },
  { key: 'supply', label: '供应链' },
  { key: 'workflow', label: '工作流' },
  { key: 'wecom', label: '企业微信' },
  { key: 'referral', label: '推荐返佣' },
  { key: 'ai', label: 'AI 课堂' },
  { key: 'ai-automation', label: 'AI 自动化' },
  { key: 'articles', label: '文章' },
  { key: 'restaurant', label: '餐饮' },
  { key: 'yuyue', label: '预约' },
  { key: 'coupon', label: '优惠券' },
  { key: 'score_shop', label: '积分商城' },
  { key: 'kefu', label: '客服' },
  { key: 'workbench', label: '工作台' },
  { key: 'navigation', label: '🧭 导航组件' },
  { key: 'quick-action', label: '⚡ 快捷操作' },
  { key: 'action', label: '🔘 普通操作' },
  { key: 'other', label: '其他' },
]

const sortedCategories = computed(() => {
  return categoryOrder
    .filter(c => permissionsByCategory.value[c.key]?.length)
    .map(c => c.key)
})

function getCategoryLabel(key) {
  return categoryOrder.find(c => c.key === key)?.label || key
}

// perm_name -> perm_id 反向索引（用于预设模板）
const permNameToId = computed(() => {
  const map = {}
  for (const p of (allPermissions.value || [])) {
    if (p.name) map[p.name] = p.id
  }
  return map
})

// Build a set of permission_ids for each role from the pending map (or initial permission_ids if not expanded)
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

// ─── 应用预设模板 ──────────────────────────────────────────────────────────────
// 点击"使用此预设" → 自动勾选该模板的所有权限（保留当前已选的合并）
function applyPreset(role, preset) {
  ensurePendingPerms(role)
  const idSet = rolePendingPerms.value[role.id]
  // 预设里的所有 permName → id 全部加入
  let appliedCount = 0
  let missingCount = 0
  for (const name of preset.permNames) {
    // admin 是 short-cut，不映射到具体 perm id，靠后端 ROLE_SHORT_CUT 处理
    if (name === 'admin') {
      ElMessage.warning('超级管理员是后端 short-cut（自动拥有所有权限），无需勾选具体权限')
      return
    }
    const pid = permNameToId.value[name]
    if (pid) {
      idSet.add(pid)
      appliedCount++
    } else {
      missingCount++
    }
  }
  role.permission_count = [...idSet].size
  rolePendingPerms.value = { ...rolePendingPerms.value }
  if (appliedCount > 0) {
    ElMessage.success(`已应用「${preset.label}」预设（${appliedCount} 个权限${missingCount > 0 ? `，${missingCount} 个不存在已跳过` : ''}）。记得点保存。`)
  }
}

// 一键清空所有权限
function clearAllPerms(role) {
  ensurePendingPerms(role)
  rolePendingPerms.value[role.id] = new Set()
  role.permission_count = 0
  rolePendingPerms.value = { ...rolePendingPerms.value }
  ElMessage.info('已清空。记得点保存。')
}

// 一键全选当前分类
function toggleCategoryAll(role, cat) {
  ensurePendingPerms(role)
  const idSet = rolePendingPerms.value[role.id]
  const perms = permissionsByCategory.value[cat] || []
  const allChecked = perms.every(p => idSet.has(p.id))
  if (allChecked) {
    // 全部已选 → 取消全选
    perms.forEach(p => idSet.delete(p.id))
  } else {
    // 部分或全未选 → 全选
    perms.forEach(p => idSet.add(p.id))
  }
  role.permission_count = [...idSet].size
  rolePendingPerms.value = { ...rolePendingPerms.value }
}

function isCategoryAllChecked(role, cat) {
  const idSet = getRolePermSet(role)
  const perms = permissionsByCategory.value[cat] || []
  return perms.length > 0 && perms.every(p => idSet.has(p.id))
}

function isCategoryAnyChecked(role, cat) {
  const idSet = getRolePermSet(role)
  const perms = permissionsByCategory.value[cat] || []
  return perms.some(p => idSet.has(p.id))
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
    // 拉全部权限（不分页）— 否则预设模板匹配会丢权限
    const res = await api.get('/rbac/permissions?pageSize=500')
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
  role.permission_count = [...idSet].size
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

                    <!-- Expanded: 用户视角预设模板 + 权限分类标签 -->
                    <div v-if="expandedRoleId === role.id" class="px-3 pb-3 border-t border-blue-100 pt-3">
                      <!-- 用户视角预设模板（解决"选择不清晰"的核心） -->
                      <div class="mb-4 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg p-3">
                        <div class="flex items-center justify-between mb-2">
                          <div class="text-xs font-semibold text-gray-700">
                            🎯 用户视角预设模板 <span class="text-gray-400 font-normal">— 一键加载该角色需要的权限</span>
                          </div>
                          <el-button size="small" text type="danger" @click="clearAllPerms(role)">
                            🗑 清空全部
                          </el-button>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                          <div
                            v-for="preset in PRESET_TEMPLATES"
                            :key="preset.key"
                            class="bg-white rounded-md border border-gray-200 px-2 py-2 cursor-pointer hover:border-blue-400 hover:shadow-sm transition-all"
                            @click="applyPreset(role, preset)"
                          >
                            <div class="flex items-center gap-1.5">
                              <span class="text-base">{{ preset.icon }}</span>
                              <span class="text-xs font-medium text-gray-800">{{ preset.label }}</span>
                              <el-tag v-if="preset.color" :type="preset.color" size="small" effect="plain" class="ml-auto">
                                {{ preset.permNames.length }}
                              </el-tag>
                            </div>
                            <div class="text-[10px] text-gray-500 mt-0.5 leading-tight">{{ preset.description }}</div>
                          </div>
                        </div>
                      </div>

                      <!-- 按分类浏览权限（带中文标签） -->
                      <div class="text-xs font-semibold text-gray-700 mb-2">
                        📋 按功能分类 <span class="text-gray-400 font-normal">— 微调具体权限</span>
                      </div>
                      <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                        <div v-for="cat in sortedCategories" :key="cat" class="bg-gray-50 rounded-md p-2">
                          <div class="flex items-center justify-between mb-1.5">
                            <div class="text-xs font-medium text-gray-600">
                              {{ getCategoryLabel(cat) }}
                              <span class="text-gray-400 text-[10px]">({{ permissionsByCategory[cat].length }})</span>
                              <span v-if="isCategoryAnyChecked(role, cat)" class="text-blue-500 text-[10px] ml-1">
                                · 已选 {{ permissionsByCategory[cat].filter(p => isPermChecked(role, p.id)).length }}/{{ permissionsByCategory[cat].length }}
                              </span>
                            </div>
                            <el-button
                              size="small"
                              text
                              :type="isCategoryAllChecked(role, cat) ? 'danger' : 'primary'"
                              @click="toggleCategoryAll(role, cat)"
                            >
                              {{ isCategoryAllChecked(role, cat) ? '取消全选' : '全选' }}
                            </el-button>
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
                              <el-tooltip :content="perm.name" placement="top" effect="light">
                                <span>{{ perm.label || perm.name }}</span>
                              </el-tooltip>
                            </el-tag>
                          </div>
                        </div>
                      </div>
                      <div class="mt-3 flex justify-end gap-2">
                        <el-button size="small" @click="expandedRoleId = null">取消</el-button>
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

            <!-- Right: 原则说明面板 -->
            <div class="flex-1 min-w-0">
              <div class="bg-white rounded-lg shadow p-5 sticky top-4">
                <h3 class="font-semibold text-gray-800 mb-3">💡 权限设计原则</h3>
                <div class="space-y-3 text-sm text-gray-600">
                  <div class="flex gap-2">
                    <span class="text-blue-500 font-bold">①</span>
                    <div>
                      <div class="font-medium text-gray-800">登录即可用原则</div>
                      <div class="text-xs text-gray-500 mt-0.5">用户登录后只能看到自己有权限的菜单、按钮和快捷操作。无权限的 UI 元素完全不显示。</div>
                    </div>
                  </div>
                  <div class="flex gap-2">
                    <span class="text-blue-500 font-bold">②</span>
                    <div>
                      <div class="font-medium text-gray-800">删除/编辑按钮按权限显隐</div>
                      <div class="text-xs text-gray-500 mt-0.5">例如商品列表：有 <code class="px-1 bg-gray-100 rounded">products:delete</code> 才显示「删除」按钮，无权限直接不渲染该按钮（不是 disabled）。</div>
                    </div>
                  </div>
                  <div class="flex gap-2">
                    <span class="text-blue-500 font-bold">③</span>
                    <div>
                      <div class="font-medium text-gray-800">快捷操作按权限显隐</div>
                      <div class="text-xs text-gray-500 mt-0.5">首页 Dashboard 的快捷操作按钮（如「扫码入库」「新建订单」），每个按钮绑定一个 permission key，无权限不显示。</div>
                    </div>
                  </div>
                  <div class="flex gap-2">
                    <span class="text-blue-500 font-bold">④</span>
                    <div>
                      <div class="font-medium text-gray-800">admin 角色 short-cut</div>
                      <div class="text-xs text-gray-500 mt-0.5">超级管理员不需勾选任何具体权限，后端识别 <code class="px-1 bg-gray-100 rounded">admin</code> 标识自动拥有 93 个权限。</div>
                    </div>
                  </div>
                  <div class="flex gap-2">
                    <span class="text-blue-500 font-bold">⑤</span>
                    <div>
                      <div class="font-medium text-gray-800">选完立即生效</div>
                      <div class="text-xs text-gray-500 mt-0.5">保存后无需重启、无需重新登录，前端自动重新拉取权限并重新渲染。</div>
                    </div>
                  </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100">
                  <h4 class="font-medium text-gray-800 mb-2 text-sm">🎯 推荐工作流</h4>
                  <ol class="space-y-1 text-xs text-gray-600 list-decimal list-inside">
                    <li>左侧选要编辑的角色 → 点展开</li>
                    <li>顶部选一个最接近的预设模板（如「仓库管理员」）→ 一键勾选</li>
                    <li>下方按分类微调（增删个别权限）</li>
                    <li>右上角点「保存」→ 完成</li>
                  </ol>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100">
                  <h4 class="font-medium text-gray-800 mb-2 text-sm">⚙️ 已实现 canAccess 的页面</h4>
                  <div class="text-xs text-gray-600 space-y-1">
                    <div>· 商品管理 / 库存管理 / 任务 / 审批 / 报表</div>
                    <div>· 首页 Dashboard 快捷操作</div>
                    <div>· 角色管理 / 用户管理</div>
                  </div>
                </div>
              </div>
            </div>
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
          <div class="text-xs text-gray-400 mt-1">英文标识，如 admin / warehouse_keeper</div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">角色显示名 *</label>
          <el-input v-model="roleForm.label" placeholder="如 仓库管理员" @keyup.enter="saveRole" />
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
