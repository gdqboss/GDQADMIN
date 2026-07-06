<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElCollapse, ElCollapseItem } from 'element-plus'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import api from '../../services/api.js'
import { ROLES } from '../../constants/roles.js'
import { NAV_PERMISSION_KEYS } from '../../constants/navPermission.js'
import { useWecomStore } from '../../stores/wecom.js'

const { t } = useI18n()
const route = useRoute()
const wecomStore = useWecomStore()

// ─── Data ─────────────────────────────────────────────────────────────────────
const users = ref([])
const pendingUsers = ref([])
const warehouses = ref([])
const suppliers = ref([])
const dealers = ref([])
const stores = ref([])
const departments = ref([])
const jobLevels = ref([])
const responsibilities = ref([])
// 获取部门名称
function getDepartmentName(deptId) { if (!deptId) return ""; const dept = departments.value.find(d => d.id === Number(deptId)); return dept ? dept.name : ""; }

// ─── Customer Management ───────────────────────────────────────────────────
const customerStats = ref({ total_customers: 0, by_level: [] })
const customerList = ref([])
const customerLoading = ref(false)
const selectedLevelFilter = ref('')
const customerSearch = ref('')

async function loadCustomerStats() {
  try {
    const res = await api.get('/customer-level/stats')
    if (res.code === 0) customerStats.value = res.data
  } catch (e) { /* ignore */ }
}

async function loadCustomerList() {
  customerLoading.value = true
  try {
    const params = {}
    if (selectedLevelFilter.value) params.level = selectedLevelFilter.value
    if (customerSearch.value) params.keyword = customerSearch.value
    const res = await api.get('/customer-level/list', { params })
    if (res.code === 0) customerList.value = res.data
  } catch (e) { /* ignore */ }
  finally { customerLoading.value = false }
}

async function recalculateCustomerLevels() {
  try {
    const res = await api.post('/customer-level/calculate')
    if (res.code === 0) {
      alert(t('settings.levelRecalculated'))
      await loadCustomerStats()
      await loadCustomerList()
    }
  } catch (e) { alert(e.message) }
}

function getLevelLabel(code) {
  const map = { VIP: t('settings.vipCustomer'), KEY: t('settings.keyCustomer'), NORMAL: t('settings.normalCustomer'), RISK: t('settings.riskCustomer') }
  return map[code] || code || '未分类'
}

function getLevelColor(code) {
  const map = { VIP: 'danger', KEY: 'warning', NORMAL: 'info', RISK: 'danger' }
  return map[code] || 'info'
}


onMounted(async () => {
  try {
    const [usersRes, whRes, supRes, dealerRes, storeRes] = await Promise.all([
      api.get('/users'),
      api.get('/warehouses'),
      api.get('/suppliers'),
      api.get('/dealers').catch(() => ({ data: { list: [] } })),
      api.get('/stores').catch(() => ({ data: { list: [] } })),
    ])
    if (usersRes.code === 0) users.value = (usersRes.data || []).filter(u => !u.is_service_customer)
    if (whRes.code === 0) warehouses.value = whRes.data.list || whRes.data
    if (supRes.code === 0) suppliers.value = supRes.data.list || supRes.data
    if (dealerRes.code === 0) dealers.value = dealerRes.data.list || dealerRes.data || []
    if (storeRes.code === 0) stores.value = storeRes.data.list || storeRes.data || [] // stores API 通常直接返回数组

    // 加载所有角色（包括自定义角色）
    await loadRoles()
    // 加载权限定义（用于角色编辑时的 name→id 映射）
    await loadAllPermissions()

    // 加载待审批员工
    await loadPendingUsers()

    // 加载部门和职级
    await loadDepartments()
    await loadJobLevels()
    await loadResponsibilities()
  } catch (e) { /* ignore */ }
})

// 权限分组：由 allPermissions 动态生成，不再硬编码
// allPermissions 在 onMounted 时从 /rbac/permissions 加载，包含数据库全部 86 项权限
const PERMISSION_GROUP_LABELS = {
  'page': 'nav.pagePermissions',
  'product': 'nav.products',
  'warehouse': 'nav.warehouses',
  'stock': 'nav.inout',
  'inventory': 'nav.inventory',
  'transfer': 'nav.transfer',
  'finance': 'nav.finance',
  'retail': 'nav.retail',
  'order': 'nav.orders',
  'aftersale': 'nav.aftersale',
  'supplier': 'nav.suppliers',
  'dealer': 'nav.dealers',
  'store': 'nav.stores',
  'user': 'nav.oa',
  'role': 'nav.roleManageIndex',
  'ai-classroom': 'nav.aiClassroom',
  'attendance': 'nav.attendance',
  'leave': 'nav.leave',
  'shift': 'nav.shifts',
  'schedule': 'nav.schedule',
  'task': 'nav.tasks',
  'workflow': 'nav.workflow',
  'quick-action': 'nav.quickActions',
  'report': 'nav.reports',
  'bi': 'nav.reports',
  'approval': 'nav.approvals',
  'gift': 'nav.giftApprovals',
  'referral': 'nav.referral',
  'qrcode': 'nav.qrcode',
  'oa': 'nav.oa',
  'menu': 'nav.settingsIndex',
  'permission': 'nav.permissionManage',
  'system': 'nav.settingsIndex',
  'wecom': 'nav.wechatLink',
  'products_delete': 'settings.permDeleteProduct',
  'products_read': 'settings.permReadProduct',
  'products_write': 'settings.permWriteProduct',
  'work_log': 'nav.workLogs',
  'work_log_template': 'settings.workLogTemplate',
}

const PERMISSION_GROUPS = computed(() => {
  const groups = {}
  for (const p of allPermissions.value) {
    const colonIdx = p.name.indexOf(':')
    const prefix = colonIdx > 0 ? p.name.slice(0, colonIdx) : p.name
    const labelKey = PERMISSION_GROUP_LABELS[p.name] || PERMISSION_GROUP_LABELS[prefix] || `perm.${p.name}`
    if (!groups[labelKey]) groups[labelKey] = []
    groups[labelKey].push({ key: p.name, labelKey: labelKey, id: p.id })
  }
  return Object.entries(groups).map(([label, children]) => ({ label, children }))
})

// ALL_PAGES: 扁平化的所有权限项（用于用户权限标签展示）
const ALL_PAGES = computed(() => PERMISSION_GROUPS.value.flatMap(g => g.children.map(c => ({ key: c.key, label: c.labelKey }))))

const ROLE_LABELS = computed(() => ({ admin: t('settings.roleAdmin'), manager: t('settings.roleManager'), operator: t('settings.roleOperator'), member: t('settings.roleMember'), warehouse: t('settings.roleWarehouse'), custom: t('settings.roleCustom') }))
const ROLE_COLORS = { admin: 'danger', manager: 'primary', operator: 'info', member: 'success', warehouse: 'warning', custom: 'warning' }

// ─── User modal ────────────────────────────────────────────────────────────────
const showUserModal = ref(false)
const editingUser = ref(null)
const userForm = ref({ name: '', phone: '', password: '', role: ROLES.OPERATOR, department_id: null, permissions: [], supplier_id: null, supplier_ids: [], dealer_ids: [], store_ids: [], supervisor_id: null, responsibility_id: null, require_attendance: false, require_worklog: false })
const userLoading = ref(false)
const userError = ref('')

function openAddUser() {
  editingUser.value = null
  userForm.value = { name: '', phone: '', password: '', role: ROLES.OPERATOR, department_id: null, permissions: [], supplier_id: null, supplier_ids: [], dealer_ids: [], store_ids: [], supervisor_id: null, responsibility_id: null, require_attendance: false, require_worklog: false }
  userError.value = ''
  showUserModal.value = true
}

function openEditUser(u) {
  editingUser.value = u
  userForm.value = {
    name: u.name,
    phone: u.phone || '',
    password: '',
    role: u.role,
    department_id: u.department_id || null,
    permissions: Array.isArray(u.permissions) ? [...u.permissions] : [],
    supplier_id: u.supplier_id || null,
    supplier_ids: Array.isArray(u.suppliers) ? u.suppliers.map(s => s.id) : [],
    dealer_ids: Array.isArray(u.dealers) ? u.dealers.map(d => d.id) : [],
    store_ids: Array.isArray(u.stores) ? u.stores.map(s => s.id) : [],
    supervisor_id: u.supervisor_id || null,
    responsibility_id: u.responsibility_id || null, require_attendance: u.require_attendance === 1, require_worklog: u.require_worklog === 1
  }
  userError.value = ''
  showUserModal.value = true

  // 加载角色的权限配置（所有角色都从这里读取，包括预设角色）
  if (u.role !== 'custom') {
    const role = roles.value.find(r => r.name === u.role)
    if (role && Array.isArray(role.permissions)) {
      userForm.value.permissions = [...role.permissions]
    }
  }
}

function togglePermission(key) {
  const idx = userForm.value.permissions.indexOf(key)
  if (idx === -1) userForm.value.permissions.push(key)
  else userForm.value.permissions.splice(idx, 1)
}

// 角色切换时自动加载权限
function onRoleChange() {
  const selectedRole = userForm.value.role

  // 如果选择的是自定义角色，加载该角色的权限；如果选择其他角色也加载对应权限
  if (selectedRole !== 'custom') {
    const role = roles.value.find(r => r.name === selectedRole)
    if (role && Array.isArray(role.permissions)) {
      userForm.value.permissions = [...role.permissions]
    }
  }
  // 如果选择"自定义权限（临时）"，保持当前权限或清空
  else {
    if (userForm.value.permissions.length === 0) {
      userForm.value.permissions = []
    }
  }
}

async function saveUser() {
  userError.value = ''
  if (!userForm.value.name || !userForm.value.phone) {
    userError.value = t('settings.namePhoneRequired')
    return
  }
  if (!editingUser.value && !userForm.value.password) {
    userError.value = t('settings.newUserPasswordRequired')
    return
  }
  userLoading.value = true
  try {
    const payload = {
      name: userForm.value.name,
      phone: userForm.value.phone,
      role: userForm.value.role,
      department_id: userForm.value.department_id,
      supplier_id: userForm.value.supplier_id || null,
      supplier_ids: userForm.value.supplier_ids || [],
      dealer_ids: userForm.value.dealer_ids || [],
      store_ids: userForm.value.store_ids || [],
      supervisor_id: userForm.value.supervisor_id, responsibility_id: userForm.value.responsibility_id || null, require_attendance: userForm.value.require_attendance ? 1 : 0, require_worklog: userForm.value.require_worklog ? 1 : 0,
      // 只有选择"自定义权限（临时）"时才传递permissions，其他角色由后端根据role自动处理
      permissions: userForm.value.role === 'custom' ? userForm.value.permissions : undefined,
    }
    if (userForm.value.password) payload.password = userForm.value.password

    let res
    if (editingUser.value) {
      res = await api.put(`/users/${editingUser.value.id}`, payload)
    } else {
      res = await api.post('/users', payload)
    }

    if (res.code === 0) {
      showUserModal.value = false
      const usersRes = await api.get('/users')
      if (usersRes.code === 0) users.value = (usersRes.data || []).filter(u => !u.is_service_customer)
    } else {
      userError.value = res.message || t('settings.operationFailed')
    }
  } catch (e) {
    userError.value = e.message || t('settings.requestFailed')
  } finally {
    userLoading.value = false
  }
}

// ─── Pending Users ─────────────────────────────────────────────────────────────
async function loadPendingUsers() {
  try {
    const res = await api.get('/users?status=pending')
    if (res.code === 0) {
      pendingUsers.value = Array.isArray(res.data) ? res.data.filter(u => u.status === 'pending') : []
    }
  } catch (e) {
    console.error('加载待审批员工失败:', e)
  }
}

async function approveUser(user) {
  if (!confirm(t('settings.confirmApproveUser', { name: user.name }))) return
  try {
    const res = await api.put(`/users/${user.id}`, { status: 'active' })
    if (res.code === 0) {
      alert(t('settings.approveSuccess'))
      await loadPendingUsers()
      const usersRes = await api.get('/users')
      if (usersRes.code === 0) users.value = (usersRes.data || []).filter(u => !u.is_service_customer)
    } else {
      alert(res.message || t('settings.approveFailed'))
    }
  } catch (e) {
    alert(e.message || t('settings.approveFailed'))
  }
}

async function rejectUser(user) {
  const reason = prompt(t('settings.confirmRejectUser', { name: user.name }))
  if (reason === null) return // 用户取消
  try {
    const res = await api.delete(`/users/${user.id}`)
    if (res.code === 0) {
      alert(t('settings.rejectSuccess'))
      await loadPendingUsers()
    } else {
      alert(res.message || t('settings.operationFailed'))
    }
  } catch (e) {
    alert(e.message || t('settings.operationFailed'))
  }
}

async function toggleUserStatus(u) {
  const newStatus = u.status === 'active' ? 'disabled' : 'active'
  const label = newStatus === 'disabled' ? t('settings.disable') : t('settings.enable')
  if (!confirm(t('settings.confirmToggleUser', { action: label, name: u.name }))) return
  try {
    const res = await api.put(`/users/${u.id}`, { status: newStatus })
    if (res.code === 0) {
      u.status = newStatus
    }
  } catch (e) { /* ignore */ }
}

// ─── Categories ────────────────────────────────────────────────────────────────
const categories = ref([])
const catLoading = ref(false)
const catEditId = ref(null)
const catEditName = ref('')
const catNewName = ref('')
const catError = ref('')
const expandedIds = ref([])
const addingChildOf = ref(null)
const childCatName = ref('')

function buildCategoryTree(flat) {
  const map = {}
  const roots = []
  for (const c of flat) map[c.id] = { ...c, children: [] }
  for (const c of flat) {
    if (c.parent_id && map[c.parent_id]) map[c.parent_id].children.push(map[c.id])
    else roots.push(map[c.id])
  }
  return roots
}

function flattenTree(nodes, expanded) {
  const result = []
  for (const node of nodes) {
    result.push(node)
    if (expanded.includes(node.id) && node.children?.length)
      result.push(...flattenTree(node.children, expanded))
  }
  return result
}

const catTree = computed(() => buildCategoryTree(categories.value))
const catFlat = computed(() => flattenTree(catTree.value, expandedIds.value))

function toggleExpand(id) {
  const idx = expandedIds.value.indexOf(id)
  if (idx >= 0) expandedIds.value.splice(idx, 1)
  else expandedIds.value.push(id)
}

async function loadCategories() {
  catLoading.value = true
  try {
    const res = await api.get('/categories')
    if (res.code === 0) categories.value = res.data || []
  } catch (e) { /* ignore */ } finally {
    catLoading.value = false
  }
}

function startEditCat(cat) {
  catEditId.value = cat.id
  catEditName.value = cat.name
  catError.value = ''
  addingChildOf.value = null
}

function cancelEditCat() {
  catEditId.value = null
  catEditName.value = ''
  catError.value = ''
}

async function saveEditCat(cat) {
  if (!catEditName.value.trim()) { catError.value = t('settings.categoryNameRequired'); return }
  try {
    const res = await api.put(`/categories/${cat.id}`, { name: catEditName.value.trim() })
    if (res.code === 0) {
      cancelEditCat()
      await loadCategories()
    } else {
      catError.value = res.message || t('settings.updateFailed')
    }
  } catch (e) { catError.value = e.message || t('settings.requestFailed') }
}

async function deleteCat(cat) {
  const hasChildren = cat.children && cat.children.length > 0
  const msg = hasChildren
    ? t('settings.confirmDeleteCategoryWithChildren', { name: cat.name })
    : t('settings.confirmDeleteCategory', { name: cat.name })
  if (!confirm(msg)) return
  try {
    const res = await api.delete(`/categories/${cat.id}`)
    if (res.code === 0) {
      await loadCategories()
    } else {
      alert(res.message || t('settings.deleteFailed'))
    }
  } catch (e) { alert(e.message || t('settings.requestFailed')) }
}

async function addCategory() {
  catError.value = ''
  if (!catNewName.value.trim()) { catError.value = t('settings.categoryNameRequired'); return }
  try {
    const res = await api.post('/categories', { name: catNewName.value.trim() })
    if (res.code === 0) {
      catNewName.value = ''
      await loadCategories()
    } else {
      catError.value = res.message || t('settings.addFailed')
    }
  } catch (e) { catError.value = e.message || t('settings.requestFailed') }
}

function startAddChild(cat) {
  addingChildOf.value = cat.id
  childCatName.value = ''
  catError.value = ''
  catEditId.value = null
  if (!expandedIds.value.includes(cat.id)) expandedIds.value.push(cat.id)
}

async function saveChildCat(parentId) {
  catError.value = ''
  if (!childCatName.value.trim()) { catError.value = t('settings.categoryNameRequired'); return }
  try {
    const res = await api.post('/categories', { name: childCatName.value.trim(), parent_id: parentId })
    if (res.code === 0) {
      addingChildOf.value = null
      childCatName.value = ''
      await loadCategories()
    } else {
      catError.value = res.message || t('settings.addFailed')
    }
  } catch (e) { catError.value = e.message || t('settings.requestFailed') }
}

// ─── Manage Roles ───────────────────────────────────────────────────────────────
const roles = ref([])
const rolesLoading = ref(false)
const showRoleModal = ref(false)
const editingRole = ref(null)
const roleForm = ref({ name: '', label: '', permissions: [] })
const roleLoading = ref(false)
const roleError = ref('')
// 所有权限定义的缓存（从 /rbac/permissions 加载），用于 name→id 转换
const allPermissions = ref([])
// 权限弹窗折叠状态：默认全部展开（等 allPermissions 加载完成后初始化）
const activePermissionGroups = ref([])

// 统一解析角色权限字段（可能是 JSON 字符串或数组）
function parsePermissions(perm) {
  if (Array.isArray(perm)) return perm
  if (typeof perm === 'string') {
    try { return JSON.parse(perm) } catch { return [] }
  }
  return []
}

// 加载所有权限定义（name→id 映射用）
async function loadAllPermissions() {
  try {
    const res = await api.get('/rbac/permissions')
    if (res.code === 0) {
      allPermissions.value = res.data?.list || res.data || []
      // allPermissions 加载完成后，初始化折叠状态（全部展开）
      nextTick(() => {
        activePermissionGroups.value = PERMISSION_GROUPS.value.map(g => g.label)
      })
    }
  } catch (e) { /* ignore */ }
}

async function loadRoles() {
  rolesLoading.value = true
  try {
    const res = await api.get('/rbac/roles')
    if (res.code === 0) roles.value = res.data || []
  } catch (e) { /* ignore */ } finally {
    rolesLoading.value = false
  }
}

function openAddRole() {
  editingRole.value = null
  roleForm.value = { name: '', label: '', permissions: [] }
  roleError.value = ''
  showRoleModal.value = true
}

async function openEditRole(r) {
  editingRole.value = r
  roleForm.value = { name: r.name, label: r.label, permissions: [] }
  roleError.value = ''
  // 从 API 加载该角色已有的权限
  try {
    const res = await api.get(`/rbac/roles/${r.id}/permissions`)
    if (res.code === 0) {
      // 数据库返回的权限含 name（如 'product:read'），前端 UI 用字符串 key 匹配，保存时再转 ID
      roleForm.value.permissions = (res.data || []).map(p => p.name)
    }
  } catch (e) { /* ignore */ }
  showRoleModal.value = true
}

function toggleRolePermission(key) {
  const idx = roleForm.value.permissions.indexOf(key)
  if (idx === -1) roleForm.value.permissions.push(key)
  else roleForm.value.permissions.splice(idx, 1)
}

async function saveRole() {
  roleError.value = ''
  if (!editingRole.value && !roleForm.value.name.trim()) {
    roleError.value = t('settings.roleIdRequired')
    return
  }
  if (!roleForm.value.label.trim()) {
    roleError.value = t('settings.roleNameRequired')
    return
  }
  roleLoading.value = true
  try {
    let roleId
    if (editingRole.value) {
      // 更新角色基本信息（label/description）
      const res = await api.put(`/rbac/roles/${editingRole.value.id}`, {
        label: roleForm.value.label.trim(),
      })
      if (res.code !== 0) { roleError.value = res.message || t('settings.operationFailed'); return }
      roleId = editingRole.value.id
    } else {
      // 新建角色
      const res = await api.post('/rbac/roles', {
        name: roleForm.value.name.trim(),
        label: roleForm.value.label.trim(),
      })
      if (res.code !== 0) { roleError.value = res.message || t('settings.addFailed'); return }
      roleId = res.data.id
    }
    // 保存权限关联（整体替换）
    // roleForm.value.permissions 存的是 name 字符串，需要转成数字 ID
    const nameToId = {}
    for (const p of allPermissions.value) nameToId[p.name] = p.id
    const permission_ids = roleForm.value.permissions
      .map(n => nameToId[n])
      .filter(id => id != null)
    const permRes = await api.put(`/rbac/roles/${roleId}/permissions`, { permission_ids })
    if (permRes.code !== 0) { roleError.value = permRes.message || t('settings.permSaveFailed'); return }
    showRoleModal.value = false
    await loadRoles()
  } catch (e) {
    roleError.value = e.message || t('settings.requestFailed')
  } finally {
    roleLoading.value = false
  }
}

async function deleteRole(r) {
  if (!confirm(t('settings.confirmDeleteRole', { name: r.label || r.name }))) return
  try {
    const res = await api.delete(`/rbac/roles/${r.id}`)
    if (res.code === 0) {
      roles.value = roles.value.filter(x => x.id !== r.id)
    } else {
      alert(res.message || t('settings.deleteFailed'))
    }
  } catch (e) { alert(e.message || t('settings.requestFailed')) }
}

// ─── AI 配置 ────────────────────────────────────────────────────────────────
const aiConfigs = ref([])
const botName = ref('')
const botNameLoading = ref(false)
const botNameSuccess = ref(false)

async function loadAiConfigs() {
  try {
    const res = await api.get('/ai-config')
    if (res.code === 0) aiConfigs.value = res.data || []
    // 加载机器人名称（从第一个配置取 provider 作为名称，或从 settings 表取）
    const botRes = await api.get('/system/settings')
    if (botRes.code === 0) {
      botName.value = botRes.data.bot_name || ''
    }
  } catch (e) { /* ignore */ }
}

async function saveBotName() {
  botNameLoading.value = true
  try {
    await api.put('/system/settings', { bot_name: botName.value })
    botNameSuccess.value = true
    setTimeout(() => botNameSuccess.value = false, 2000)
  } catch (e) { alert(e.message) }
  finally { botNameLoading.value = false }
}

// 模型配置弹窗
const showModelModal = ref(false)
const editingModel = ref(null)
const modelForm = ref({ provider: '', model: '', api_key: '', base_url: '', is_default: false })
const modelLoading = ref(false)
const modelError = ref('')

function openAddModel() {
  editingModel.value = null
  modelForm.value = { provider: '', model: '', api_key: '', base_url: '', is_default: false }
  modelError.value = ''
  showModelModal.value = true
}

function openEditModel(cfg) {
  editingModel.value = cfg
  modelForm.value = { provider: cfg.provider, model: cfg.model, api_key: '', base_url: cfg.base_url || '', is_default: cfg.is_default === 1 }
  modelError.value = ''
  showModelModal.value = true
}

async function saveModel() {
  if (!modelForm.value.provider || !modelForm.value.model) {
    modelError.value = t('settings.providerModelRequired')
    return
  }
  modelLoading.value = true
  try {
    const payload = { ...modelForm.value }
    let res
    if (editingModel.value) {
      res = await api.put(`/ai-config/${editingModel.value.id}`, payload)
    } else {
      res = await api.post('/ai-config', payload)
    }
    if (res.code === 0) {
      showModelModal.value = false
      await loadAiConfigs()
    } else {
      modelError.value = res.message || t('settings.saveFailed')
    }
  } catch (e) { modelError.value = e.message }
  finally { modelLoading.value = false }
}

async function deleteModel(id) {
  if (!confirm(t('settings.confirmDeleteModel'))) return
  try {
    const res = await api.delete(`/ai-config/${id}`)
    if (res.code === 0) await loadAiConfigs()
    else alert(res.message)
  } catch (e) { alert(e.message) }
}

// ─── WeCom ───────────────────────────────────────────────────────────────────
const wecomCorpId = ref(wecomStore.corpId)
const wecomAgentId = ref(wecomStore.agentId)
const wecomSecret = ref(wecomStore.secret)
const showWecomSecret = ref(false)
const wecomSaveSuccess = ref(false)
function saveWecomSettings() {
  wecomStore.saveConfig({ corpId: wecomCorpId.value, agentId: wecomAgentId.value, secret: wecomSecret.value })
  wecomSaveSuccess.value = true
  setTimeout(() => wecomSaveSuccess.value = false, 2000)
}

const activeTab = ref(route.query.tab || 'users')
const tabs = computed(() => [
  { key: 'users',                label: t('settings.userList'),            icon: 'people' },
  { key: 'departments',          label: t('settings.deptManage'),          icon: 'corporate_fare' },
  { key: 'job-levels',           label: t('settings.levelManage'),         icon: 'military_tech' },
  { key: 'customers',            label: t('settings.customerManage'),      icon: 'group' },
  { key: 'payment',              label: t('settings.paymentSettings'),     icon: 'payments' },
  { key: 'ai-config',            label: t('settings.aiConfig'),            icon: 'smart_toy' },
])

function switchTab(key) {
  activeTab.value = key
  if (key === 'departments' && departments.value.length === 0) loadDepartments()
  if (key === 'job-levels' && jobLevels.value.length === 0) loadJobLevels()
  if (key === 'ai-config' && aiConfigs.value.length === 0) loadAiConfigs()
  if (key === 'customers') { loadCustomerStats(); loadCustomerList() }
  if (key === 'payment') loadPaymentConfig()
}

watch([selectedLevelFilter, customerSearch], () => {
  loadCustomerList()
})

// ─── Payment Settings ─────────────────────────────────────────────────────────
const paymentConfig = ref({
  wechat: { enabled: false, appId: '', mchId: '', apiKey: '' },
  alipay: { enabled: false, appId: '', privateKey: '', alipayPublicKey: '' },
  paypal: { enabled: false, clientId: '', secret: '', environment: 'sandbox' },
})

async function loadPaymentConfig() {
  try {
    const res = await api.get('/system/wechat-config')
    if (res.code === 0 && res.data) {
      const d = res.data
      paymentConfig.value = {
        ...paymentConfig.value,
        wechat: {
          enabled: d.status === 'active',
          appId: d.appid || '',
          mchId: d.mchid || '',
          // 后端 GET 会掩码 api_key 为 ********，保留用户已输入值
          apiKey: d.api_key && d.api_key !== '********' ? d.api_key : paymentConfig.value.wechat.apiKey,
        },
      }
    }
  } catch (e) {
    console.warn('[loadPaymentConfig]', e.message)
  }
}

async function savePaymentConfig() {
  try {
    // 只保存微信（后端路由只有 wechat-config，alipay/paypal 无对应表）
    const wechatEnabled = paymentConfig.value.wechat.enabled
    const res = await api.post('/system/wechat-config', {
      appid: paymentConfig.value.wechat.appId,
      mchid: paymentConfig.value.wechat.mchId,
      api_key: paymentConfig.value.wechat.apiKey,
      status: wechatEnabled ? 'active' : 'inactive',
    })
    if (res.code === 0) {
      alert(t('common.saveSuccess'))
    } else {
      alert(res.message || t('settings.savePaymentFailed'))
    }
  } catch (e) {
    alert(e.message || t('settings.savePaymentFailed'))
  }
}

// ─── Job Responsibilities (from JobResponsibilities.vue logic) ─────────────────
const filterJobLevel = ref('')
const filterDepartment = ref('')

function flattenDepartments(depts, result = [], level = 0) {
  for (const dept of depts) {
    result.push({ ...dept, displayName: '　'.repeat(level) + dept.name, _level: level })
    if (dept.children && dept.children.length > 0) flattenDepartments(dept.children, result, level + 1)
  }
  return result
}

const flatDepartments = computed(() => flattenDepartments(departments.value))

const filteredResponsibilities = computed(() => {
  return responsibilities.value.filter(r => {
    if (filterJobLevel.value && String(r.jobLevelId) !== String(filterJobLevel.value)) return false
    if (filterDepartment.value && String(r.departmentId) !== String(filterDepartment.value)) return false
    return true
  })
})

async function openAddResponsibility() {
  const name = prompt(t('settings.enterResponsibilityName'))
  if (!name?.trim()) return
  try {
    const res = await api.post('/responsibilities', { name, departmentId: filterDepartment.value || undefined, jobLevelId: filterJobLevel.value || undefined })
    if (res.code === 0) { await loadResponsibilities(); alert(t('common.addSuccess')) }
    else alert(res.message || t('settings.addResponsibilityFailed'))
  } catch (e) { alert(e.message || t('settings.addResponsibilityFailed')) }
}

async function openEditResponsibility(resp) {
  const name = prompt(t('settings.enterResponsibilityName'), resp.name)
  if (!name?.trim()) return
  try {
    const res = await api.put(`/responsibilities/${resp.id}`, { name })
    if (res.code === 0) { await loadResponsibilities(); alert(t('common.updateSuccess')) }
    else alert(res.message || t('settings.updateResponsibilityFailed'))
  } catch (e) { alert(e.message || t('settings.updateResponsibilityFailed')) }
}

async function deleteResponsibility(resp) {
  if (!confirm(t('settings.confirmDeleteResponsibility'))) return
  try {
    const res = await api.delete(`/responsibilities/${resp.id}`)
    if (res.code === 0) { await loadResponsibilities(); alert(t('common.deleteSuccess')) }
    else alert(res.message || t('settings.deleteResponsibilityFailed'))
  } catch (e) { alert(e.message || t('settings.deleteResponsibilityFailed')) }
}

// ─── Warehouse Management ──────────────────────────────────────────────────────
const showWarehouseModal = ref(false)
const editingWarehouse = ref(null)
const warehouseForm = ref({ name: '', address: '', type: '', manager: '', contact: '' })
const warehouseLoading = ref(false)
const selectedWarehouses = ref([])
const selectAllWarehouses = ref(false)

function toggleSelectAllWarehouses() {
  if (selectAllWarehouses.value) {
    selectedWarehouses.value = warehouses.value.map(w => w.id)
  } else {
    selectedWarehouses.value = []
  }
}

function toggleSelectWarehouse(id) {
  const idx = selectedWarehouses.value.indexOf(id)
  if (idx > -1) {
    selectedWarehouses.value.splice(idx, 1)
  } else {
    selectedWarehouses.value.push(id)
  }
  selectAllWarehouses.value = selectedWarehouses.value.length === warehouses.value.length
}

async function batchDeleteWarehouses() {
  if (selectedWarehouses.value.length === 0) {
    alert(t('settings.selectWarehousesFirst'))
    return
  }
  if (!confirm(t('settings.confirmBatchDeleteWarehouses', { count: selectedWarehouses.value.length }))) return

  try {
    const res = await api.post('/warehouses/batch-delete', { ids: selectedWarehouses.value })
    if (res.code === 0) {
      alert(res.message || t('settings.batchDeleteSuccess'))
      selectedWarehouses.value = []
      selectAllWarehouses.value = false
      const whRes = await api.get('/warehouses')
      if (whRes.code === 0) warehouses.value = whRes.data.list || whRes.data
    } else {
      alert(res.message || t('settings.batchDeleteFailed'))
    }
  } catch (e) {
    alert(e.message || t('settings.batchDeleteFailed'))
  }
}

function openAddWarehouse() {
  editingWarehouse.value = null
  warehouseForm.value = { name: '', address: '', type: '', manager: '', contact: '' }
  showWarehouseModal.value = true
}

function openEditWarehouse(wh) {
  editingWarehouse.value = wh
  warehouseForm.value = { ...wh }
  showWarehouseModal.value = true
}

async function saveWarehouse() {
  if (!warehouseForm.value.name?.trim()) {
    alert(t('settings.warehouseNameRequired'))
    return
  }
  warehouseLoading.value = true
  try {
    if (editingWarehouse.value) {
      const res = await api.put(`/warehouses/${editingWarehouse.value.id}`, warehouseForm.value)
      if (res.code === 0) {
        const idx = warehouses.value.findIndex(w => w.id === editingWarehouse.value.id)
        if (idx !== -1) warehouses.value[idx] = { ...editingWarehouse.value, ...warehouseForm.value }
        showWarehouseModal.value = false
      } else {
        alert(res.message || t('settings.saveFailed'))
      }
    } else {
      const res = await api.post('/warehouses', warehouseForm.value)
      if (res.code === 0) {
        warehouses.value.push(res.data)
        showWarehouseModal.value = false
      } else {
        alert(res.message || t('settings.createFailed'))
      }
    }
  } catch (e) {
    alert(e.message || t('settings.operationFailed'))
  } finally {
    warehouseLoading.value = false
  }
}

async function deleteWarehouse(wh) {
  if (!confirm(t('settings.confirmDeleteWarehouse', { name: wh.name }))) return
  try {
    const res = await api.delete(`/warehouses/${wh.id}`)
    if (res.code === 0) {
      warehouses.value = warehouses.value.filter(w => w.id !== wh.id)
    } else {
      alert(res.message || t('settings.deleteFailed'))
    }
  } catch (e) {
    alert(e.message || t('settings.deleteFailed'))
  }
}

// ─── Departments Management ────────────────────────────────────────────────────
const deptLoading = ref(false)
const deptError = ref('')
const showDeptModal = ref(false)
const editingDept = ref(null)
const deptForm = ref({ name: '', parent_id: null, manager_id: null, sort_order: 0 })

async function loadDepartments() {
  try {
    deptLoading.value = true
    const res = await api.get('/users/departments/list')
    if (res.code === 0) {
      departments.value = res.data || []
    }
  } catch (err) {
    deptError.value = t('settings.loadDeptFailed')
  } finally {
    deptLoading.value = false
  }
}

function openAddDept() {
  editingDept.value = null
  deptForm.value = { name: '', parent_id: null, manager_id: null, sort_order: 0 }
  deptError.value = ''
  showDeptModal.value = true
}

function openEditDept(dept) {
  editingDept.value = dept
  deptForm.value = {
    name: dept.name,
    parent_id: dept.parent_id || null,
    manager_id: dept.manager_id || null,
    sort_order: dept.sort_order || 0
  }
  deptError.value = ''
  showDeptModal.value = true
}

async function saveDept() {
  if (!deptForm.value.name) {
    deptError.value = t('settings.deptNameRequired')
    return
  }
  try {
    deptLoading.value = true
    let res
    if (editingDept.value) {
      res = await api.put(`/users/departments/${editingDept.value.id}`, deptForm.value)
    } else {
      res = await api.post('/users/departments/create', deptForm.value)
    }
    if (res.code === 0) {
      showDeptModal.value = false
      await loadDepartments()
    } else {
      deptError.value = res.message || t('settings.operationFailed')
    }
  } catch (err) {
    deptError.value = t('settings.operationFailed')
  } finally {
    deptLoading.value = false
  }
}

async function deleteDept(dept) {
  if (!confirm(t('settings.confirmDeleteDept', { name: dept.name }))) return
  try {
    const res = await api.delete(`/users/departments/${dept.id}`)
    if (res.code === 0) {
      await loadDepartments()
    } else {
      alert(res.message || t('settings.deleteFailed'))
    }
  } catch (err) {
    alert(t('settings.deleteFailed'))
  }
}

// ─── Job Levels Management ─────────────────────────────────────────────────────
const levelLoading = ref(false)
const levelError = ref('')
const showLevelModal = ref(false)
const editingLevel = ref(null)
const levelForm = ref({ name: '', level: 1, description: '', responsibility_desc: '' })

async function loadResponsibilities() { try { const res = await api.get("/users/responsibilities/list"); if (res.code === 0) responsibilities.value = res.data || []; } catch(e) {} }
async function loadJobLevels() {
  try {
    levelLoading.value = true
    const res = await api.get('/users/job-levels/list')
    if (res.code === 0) {
      jobLevels.value = res.data || []
    }
  } catch (err) {
    levelError.value = t('settings.loadLevelFailed')
  } finally {
    levelLoading.value = false
  }
}

function openAddLevel() {
  editingLevel.value = null
  levelForm.value = { name: '', level: 1, description: '', responsibility_desc: '' }
  levelError.value = ''
  showLevelModal.value = true
}

function openEditLevel(level) {
  editingLevel.value = level
  levelForm.value = {
    name: level.name,
    level: level.level,
    description: level.description || '',
    responsibility_desc: level.responsibility_desc || ''
  }
  levelError.value = ''
  showLevelModal.value = true
}

async function saveLevel() {
  if (!levelForm.value.name || !levelForm.value.level) {
    levelError.value = t('settings.levelNameAndGradeRequired')
    return
  }
  try {
    levelLoading.value = true
    let res
    if (editingLevel.value) {
      res = await api.put(`/users/job-levels/${editingLevel.value.id}`, levelForm.value)
    } else {
      res = await api.post('/users/job-levels/create', levelForm.value)
    }
    if (res.code === 0) {
      showLevelModal.value = false
      await loadJobLevels()
    await loadResponsibilities()
    } else {
      levelError.value = res.message || t('settings.operationFailed')
    }
  } catch (err) {
    levelError.value = t('settings.operationFailed')
  } finally {
    levelLoading.value = false
  }
}

async function deleteLevel(level) {
  if (!confirm(t('settings.confirmDeleteLevel', { name: level.name }))) return
  try {
    const res = await api.delete(`/users/job-levels/${level.id}`)
    if (res.code === 0) {
      await loadJobLevels()
    await loadResponsibilities()
    } else {
      alert(res.message || t('settings.deleteFailed'))
    }
  } catch (err) {
    alert(t('settings.deleteFailed'))
  }
}

// ─── User Delete ───────────────────────────────────────────────────────────────
async function deleteUser(user) {
  if (user.status !== 'disabled') {
    alert(t('settings.onlyDeleteDisabledUser'))
    return
  }
  if (!confirm(t('settings.confirmDeleteUser', { name: user.name }))) return
  try {
    const res = await api.delete(`/users/${user.id}`)
    if (res.code === 0) {
      const usersRes = await api.get('/users')
      if (usersRes.code === 0) users.value = (usersRes.data || []).filter(u => !u.is_service_customer)
    } else {
      alert(res.message || t('settings.deleteFailed'))
    }
  } catch (err) {
    alert(t('settings.deleteFailed'))
  }
}
</script>

<template>
  <div>
    <PageHeader :title="$t('settings.title')" :subtitle="$t('settings.subtitle')" />

    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <!-- Tabs -->
      <div class="flex border-b border-gray-100 overflow-x-auto">
        <button
          v-for="tab in tabs" :key="tab.key"
          @click="switchTab(tab.key)"
          :class="['flex items-center gap-2 px-6 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap',
            activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-text-secondary hover:text-text-primary']"
        >
          <span class="material-symbols-outlined text-[18px]">{{ tab.icon }}</span>
          {{ tab.label }}
        </button>
      </div>

      <!-- ── Users ── -->
      <div v-if="activeTab === 'users'" class="p-6">
        <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
          <span class="material-symbols-outlined text-sm align-middle">info</span>
          {{ $t('settings.userManageHint') }}
        </div>
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-bold text-text-primary">{{ $t('settings.userList') }}</h3>
          <button @click="openAddUser" class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">person_add</span>
            {{ $t('settings.addUser') }}
          </button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
              <tr>
                <th class="px-4 py-3 font-medium">{{ $t('common.name') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('common.phone') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('settings.role') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('settings.department') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('settings.supervisor') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('settings.supplierCol') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('settings.lastLogin') }}</th>
                <th class="px-4 py-3 font-medium text-center">{{ $t('common.status') }}</th>
                <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 font-medium text-text-primary">{{ u.name }}</td>
                <td class="px-4 py-3 text-text-secondary">{{ u.phone || '-' }}</td>
                <td class="px-4 py-3 whitespace-nowrap">
                  <StatusTag :type="ROLE_COLORS[u.role] || 'info'" :text="ROLE_LABELS[u.role] || u.role" />
                </td>
                <td class="px-4 py-3 text-text-secondary">{{ getDepartmentName(u.department_id) || '-' }}</td>
                <td class="px-4 py-3 text-text-secondary">{{ u.supervisor_name || '-' }}</td>
                <td class="px-4 py-3 text-text-secondary text-xs">
                  <template v-if="u.suppliers && u.suppliers.length > 0">
                    <span v-for="(s, idx) in u.suppliers" :key="s.id" class="inline-block bg-primary/10 text-primary px-2 py-0.5 rounded text-xs mr-1 mb-1">
                      {{ s.name }}
                    </span>
                  </template>
                  <span v-else>-</span>
                </td>
                <td class="px-4 py-3 text-text-secondary text-xs">{{ u.last_login ? u.last_login.slice(0,16) : '-' }}</td>
                <td class="px-4 py-3 text-center">
                  <StatusTag :type="u.status === 'active' ? 'success' : 'danger'" :text="u.status === 'active' ? $t('settings.enable') : $t('settings.disable')" />
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                  <button @click="openEditUser(u)" class="text-primary hover:text-primary-hover text-xs font-medium">{{ $t('common.edit') }}</button>
                  <button @click="toggleUserStatus(u)" :class="['text-xs font-medium', u.status === 'active' ? 'text-danger hover:text-red-700' : 'text-success hover:text-green-700']">
                    {{ u.status === 'active' ? $t('settings.disable') : $t('settings.enable') }}
                  </button>
                  <button v-if="u.status === 'disabled'"
                          @click="deleteUser(u)"
                          class="text-danger hover:text-red-700 text-xs font-medium"
                          :title="$t('settings.permanentDeleteUser') + u.name">
                    {{ $t('common.delete') }}
                  </button>
                  <span v-else
                        class="text-xs text-gray-400 cursor-not-allowed"
                        :title="$t('settings.disableBeforeDelete')">
                    {{ $t('common.delete') }}
                  </span>
                </td>
              </tr>
              <tr v-if="!users.length">
                <td colspan="9" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('settings.noUsers') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Pending Users ── -->
      <div v-if="activeTab === 'pending-users'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-bold text-text-primary">{{ $t('settings.pendingUsers') }}
            <span class="text-xs font-normal text-text-secondary ml-2">{{ $t('settings.totalCount', { count: pendingUsers.length }) }}</span>
          </h3>
          <button @click="loadPendingUsers" class="flex items-center gap-2 text-primary hover:text-primary-hover text-sm font-medium">
            <span class="material-symbols-outlined text-[18px]">refresh</span>
            {{ $t('settings.refresh') }}
          </button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
              <tr>
                <th class="px-4 py-3 font-medium">{{ $t('common.name') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('common.phone') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('settings.idCard') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('settings.applyTime') }}</th>
                <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="u in pendingUsers" :key="u.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 font-medium text-text-primary">{{ u.name }}</td>
                <td class="px-4 py-3 text-text-secondary">{{ u.phone }}</td>
                <td class="px-4 py-3 text-text-secondary">{{ u.id_card || '-' }}</td>
                <td class="px-4 py-3 text-text-secondary text-xs">{{ u.applied_at ? u.applied_at.slice(0,16) : '-' }}</td>
                <td class="px-4 py-3 text-right space-x-3">
                  <button @click="approveUser(u)" class="text-success hover:text-green-700 text-xs font-medium">
                    {{ $t('settings.approve') }}
                  </button>
                  <button @click="rejectUser(u)" class="text-danger hover:text-red-700 text-xs font-medium">
                    {{ $t('settings.reject') }}
                  </button>
                </td>
              </tr>
              <tr v-if="!pendingUsers.length">
                <td colspan="5" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('settings.noPendingUsers') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Departments ── -->
      <div v-if="activeTab === 'departments'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-bold text-text-primary">{{ $t('settings.deptManage') }}
            <span class="text-xs font-normal text-text-secondary ml-2">{{ $t('settings.totalDepts', { count: departments.length }) }}</span>
          </h3>
          <button @click="openAddDept" class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">add</span>
            {{ $t('settings.addDept') }}
          </button>
        </div>
        <div v-if="deptError" class="mb-3 text-sm text-danger bg-red-50 px-3 py-2 rounded-lg">{{ deptError }}</div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
              <tr>
                <th class="px-4 py-3 font-medium">{{ $t('settings.deptName') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('settings.manager') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('settings.sortOrder') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('common.status') }}</th>
                <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="dept in departments" :key="dept.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 font-medium text-text-primary">{{ dept.name }}</td>
                <td class="px-4 py-3 text-text-secondary">{{ dept.manager_name || '-' }}</td>
                <td class="px-4 py-3 text-text-secondary">{{ dept.sort_order }}</td>
                <td class="px-4 py-3">
                  <StatusTag :type="dept.status === 'active' ? 'success' : 'danger'" :text="dept.status === 'active' ? $t('settings.enable') : $t('settings.disable')" />
                </td>
                <td class="px-4 py-3 text-right space-x-3">
                  <button @click="openEditDept(dept)" class="text-primary hover:text-primary-hover text-xs font-medium">{{ $t('common.edit') }}</button>
                  <button @click="deleteDept(dept)" class="text-danger hover:text-red-700 text-xs font-medium">{{ $t('common.delete') }}</button>
                </td>
              </tr>
              <tr v-if="!departments.length">
                <td colspan="5" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('settings.noDepts') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Job Levels ── -->
      <div v-if="activeTab === 'job-levels'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-bold text-text-primary">{{ $t('settings.levelManage') }}
            <span class="text-xs font-normal text-text-secondary ml-2">{{ $t('settings.totalLevels', { count: jobLevels.length }) }}</span>
          </h3>
          <button @click="openAddLevel" class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">add</span>
            {{ $t('settings.addLevel') }}
          </button>
        </div>
        <div v-if="levelError" class="mb-3 text-sm text-danger bg-red-50 px-3 py-2 rounded-lg">{{ levelError }}</div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
              <tr>
                <th class="px-4 py-3 font-medium">{{ $t('settings.levelName') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('settings.grade') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('common.description') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('settings.responsibilityDesc') }}</th>
                <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="level in jobLevels" :key="level.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 font-medium text-text-primary">{{ level.name }}</td>
                <td class="px-4 py-3 text-text-secondary">{{ level.level }}</td>
                <td class="px-4 py-3 text-text-secondary">{{ level.description || '-' }}</td>
                <td class="px-4 py-3 text-text-secondary text-sm">{{ level.responsibility_desc || '-' }}</td>
                <td class="px-4 py-3 text-right space-x-3">
                  <button @click="openEditLevel(level)" class="text-primary hover:text-primary-hover text-xs font-medium">{{ $t('common.edit') }}</button>
                  <button @click="deleteLevel(level)" class="text-danger hover:text-red-700 text-xs font-medium">{{ $t('common.delete') }}</button>
                </td>
              </tr>
              <tr v-if="!jobLevels.length">
                <td colspan="4" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('settings.noLevels') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Categories ── -->
      <div v-if="activeTab === 'categories'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-bold text-text-primary">{{ $t('settings.categoryManage') }}
            <span class="text-xs font-normal text-text-secondary ml-2">{{ $t('settings.maxFourLevels') }}</span>
          </h3>
          <div class="flex items-center gap-3">
            <span v-if="catLoading" class="text-sm text-text-secondary">{{ $t('common.loading') }}</span>
          </div>
        </div>

        <div v-if="catError" class="mb-3 text-sm text-danger bg-red-50 px-3 py-2 rounded-lg">{{ catError }}</div>

        <!-- Tree view -->
        <div class="border border-gray-100 rounded-lg divide-y divide-gray-100 mb-4">
          <div v-if="!catFlat.length && !catLoading" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('settings.noCategories') }}</div>

          <template v-for="node in catFlat" :key="node.id">
            <!-- Category row -->
            <div
              :style="{ paddingLeft: `${(node.level - 1) * 24 + 16}px` }"
              class="flex items-center gap-2 pr-4 py-2.5 hover:bg-gray-50 transition-colors min-h-[44px]"
            >
              <!-- Expand arrow -->
              <button
                v-if="node.children && node.children.length"
                @click="toggleExpand(node.id)"
                class="w-5 h-5 flex items-center justify-center text-text-secondary hover:text-text-primary shrink-0"
              >
                <span class="material-symbols-outlined text-[16px] transition-transform"
                  :class="expandedIds.includes(node.id) ? 'rotate-90' : ''">chevron_right</span>
              </button>
              <span v-else class="w-5 shrink-0"></span>

              <!-- Level badge -->
              <span :class="[
                'text-[10px] font-bold px-1.5 py-0.5 rounded shrink-0',
                node.level === 1 ? 'bg-primary/10 text-primary' :
                node.level === 2 ? 'bg-success/10 text-success' :
                node.level === 3 ? 'bg-warning/10 text-warning' :
                'bg-danger/10 text-danger'
              ]">L{{ node.level }}</span>

              <!-- Name / Edit input -->
              <template v-if="catEditId !== node.id">
                <span class="flex-1 text-sm text-text-primary font-medium">{{ node.name }}</span>
              </template>
              <template v-else>
                <input
                  v-model="catEditName"
                  type="text"
                  class="flex-1 border border-primary rounded-lg px-2 py-1 text-sm focus:ring-1 focus:ring-primary focus:outline-none"
                  @keyup.enter="saveEditCat(node)"
                  @keyup.esc="cancelEditCat"
                  autofocus
                />
                <button @click="saveEditCat(node)" class="text-white bg-primary hover:bg-primary-hover text-xs px-2.5 py-1 rounded-lg shrink-0">{{ $t('common.saveChanges') }}</button>
                <button @click="cancelEditCat" class="text-text-secondary hover:text-text-primary text-xs px-2 py-1 shrink-0">{{ $t('common.cancel') }}</button>
              </template>

              <!-- Actions (only when not editing) -->
              <template v-if="catEditId !== node.id">
                <button
                  v-if="node.level < 4"
                  @click="startAddChild(node)"
                  class="text-success hover:text-green-700 text-xs font-medium px-2 py-1 rounded hover:bg-green-50 transition-colors shrink-0"
                >{{ $t('settings.addChild') }}</button>
                <button
                  @click="startEditCat(node)"
                  class="text-primary hover:text-primary-hover text-xs font-medium px-2 py-1 rounded hover:bg-primary/5 transition-colors shrink-0"
                >{{ $t('common.edit') }}</button>
                <button
                  @click="deleteCat(node)"
                  class="text-danger hover:text-red-700 text-xs font-medium px-2 py-1 rounded hover:bg-red-50 transition-colors shrink-0"
                >{{ $t('common.delete') }}</button>
              </template>
            </div>

            <!-- Inline add-child input row -->
            <div
              v-if="addingChildOf === node.id"
              :style="{ paddingLeft: `${node.level * 24 + 16}px` }"
              class="flex items-center gap-2 pr-4 py-2 bg-green-50 border-l-2 border-success"
            >
              <span class="material-symbols-outlined text-success text-[16px] shrink-0">add_circle_outline</span>
              <input
                v-model="childCatName"
                type="text"
                :placeholder="$t('settings.enterSubCategoryName', { level: node.level + 1 })"
                class="flex-1 border border-success rounded-lg px-3 py-1.5 text-sm focus:ring-1 focus:ring-success focus:outline-none bg-white"
                @keyup.enter="saveChildCat(node.id)"
                @keyup.esc="addingChildOf = null"
                autofocus
              />
              <button @click="saveChildCat(node.id)" class="text-white bg-success hover:bg-green-700 text-xs px-2.5 py-1.5 rounded-lg shrink-0">{{ $t('common.add') }}</button>
              <button @click="addingChildOf = null" class="text-text-secondary hover:text-text-primary text-xs px-2 py-1.5 shrink-0">{{ $t('common.cancel') }}</button>
            </div>
          </template>
        </div>

        <!-- Add top-level category -->
        <div class="flex items-center gap-3 border border-dashed border-gray-200 rounded-lg px-4 py-3 bg-gray-50/50">
          <span class="material-symbols-outlined text-[18px] text-text-secondary shrink-0">add_circle_outline</span>
          <input
            v-model="catNewName"
            type="text"
            :placeholder="$t('settings.enterTopCategoryName')"
            class="flex-1 bg-transparent text-sm text-text-primary placeholder-text-secondary focus:outline-none"
            @keyup.enter="addCategory"
          />
          <button
            @click="addCategory"
            class="text-white bg-primary hover:bg-primary-hover text-xs font-medium px-3 py-1.5 rounded-lg transition-colors whitespace-nowrap shrink-0"
          >{{ $t('settings.addTopCategory') }}</button>
        </div>
      </div>

      <!-- ── Customer Management ── -->
      <div v-if="activeTab === 'customers'" class="p-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
          <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
            <div class="flex items-center gap-2">
              <div class="size-9 rounded-lg bg-primary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-[18px]">group</span>
              </div>
              <div>
                <p class="text-xl font-bold text-text-primary">{{ customerStats.total_customers }}</p>
                <p class="text-[10px] text-text-secondary">{{ $t('settings.totalCustomers') }}</p>
              </div>
            </div>
          </div>
          <div v-for="lv in customerStats.by_level" :key="lv.level"
            class="bg-white rounded-lg border border-gray-100 shadow-card p-4">
            <div class="flex items-center gap-2">
              <div :class="['size-9 rounded-lg flex items-center justify-center', `bg-${getLevelColor(lv.level)}/10`]">
                <span :class="['material-symbols-outlined text-[18px]', `text-${getLevelColor(lv.level)}`]">star</span>
              </div>
              <div>
                <p class="text-xl font-bold text-text-primary">{{ lv.customer_count }}</p>
                <p class="text-[10px] text-text-secondary">{{ getLevelLabel(lv.level) }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-3 mb-4">
          <div class="relative flex-1 min-w-[200px]">
            <span class="material-symbols-outlined text-[18px] text-text-secondary absolute left-3 top-1/2 -translate-y-1/2">search</span>
            <input v-model="customerSearch" type="text" :placeholder="$t('settings.customerPhone') + ' / ' + $t('settings.customerName')"
              class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <select v-model="selectedLevelFilter" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
            <option value="">{{ $t('settings.allLevels') }}</option>
            <option value="VIP">{{ $t('settings.vipCustomer') }}</option>
            <option value="KEY">{{ $t('settings.keyCustomer') }}</option>
            <option value="NORMAL">{{ $t('settings.normalCustomer') }}</option>
            <option value="RISK">{{ $t('settings.riskCustomer') }}</option>
          </select>
          <button @click="loadCustomerList"
            class="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-text-primary px-4 py-2 rounded-lg text-sm transition-colors">
            <span class="material-symbols-outlined text-[18px]">refresh</span>
          </button>
          <button @click="recalculateCustomerLevels"
            class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">refresh</span>
            {{ $t('settings.recalculateLevel') }}
          </button>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
                <tr>
                  <th class="px-4 py-3 font-medium">{{ $t('settings.customerPhone') }}</th>
                  <th class="px-4 py-3 font-medium">{{ $t('settings.customerName') }}</th>
                  <th class="px-4 py-3 font-medium text-center">{{ $t('settings.totalAmount') }}</th>
                  <th class="px-4 py-3 font-medium text-center">{{ $t('settings.monthlyAmount') }}</th>
                  <th class="px-4 py-3 font-medium text-center">{{ $t('settings.orderCount') }}</th>
                  <th class="px-4 py-3 font-medium text-center">{{ $t('settings.avgOrderAmount') }}</th>
                  <th class="px-4 py-3 font-medium text-center">{{ $t('settings.currentLevel') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-if="customerLoading">
                  <td colspan="7" class="px-4 py-8 text-center text-text-secondary">{{ $t('common.loading') }}</td>
                </tr>
                <tr v-else-if="!customerList.length">
                  <td colspan="7" class="px-4 py-8 text-center text-text-secondary">{{ $t('settings.noCustomerData') }}</td>
                </tr>
                <tr v-else v-for="c in customerList" :key="c.customer_phone"
                  class="hover:bg-gray-50 transition-colors">
                  <td class="px-4 py-3 font-mono text-sm text-primary">{{ c.customer_phone }}</td>
                  <td class="px-4 py-3 text-text-primary font-medium">{{ c.customer_name || '-' }}</td>
                  <td class="px-4 py-3 text-center text-text-primary">¥{{ Number(c.total_amount || 0).toFixed(2) }}</td>
                  <td class="px-4 py-3 text-center text-text-primary">¥{{ Number(c.monthly_amount || 0).toFixed(2) }}</td>
                  <td class="px-4 py-3 text-center text-text-secondary">{{ c.order_count }}</td>
                  <td class="px-4 py-3 text-center text-text-secondary">¥{{ Number(c.avg_order_amount || 0).toFixed(2) }}</td>
                  <td class="px-4 py-3 text-center">
                    <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                      c.current_level === 'VIP' ? 'bg-danger/10 text-danger' :
                      c.current_level === 'KEY' ? 'bg-warning/10 text-warning' :
                      c.current_level === 'RISK' ? 'bg-danger/10 text-danger' :
                      'bg-info/10 text-info']">
                      {{ getLevelLabel(c.current_level) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── Job Responsibilities ── -->
      <div v-if="activeTab === 'job-responsibilities'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-bold text-text-primary">{{ $t('nav.jobResponsibilities') }}</h3>
          <button @click="openAddResponsibility" class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">add</span>
            {{ $t('settings.addResponsibility') }}
          </button>
        </div>
        <div class="mb-4 flex gap-3 flex-wrap">
          <select v-model="filterJobLevel" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
            <option value="">{{ $t('settings.allJobLevels') }}</option>
            <option v-for="jl in jobLevels" :key="jl.id" :value="jl.id">{{ jl.name }}</option>
          </select>
          <select v-model="filterDepartment" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
            <option value="">{{ $t('settings.allDepartments') }}</option>
            <option v-for="dept in flatDepartments" :key="dept.id" :value="dept.id">{{ dept.displayName }}</option>
          </select>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
              <tr>
                <th class="px-4 py-3 font-medium">{{ $t('settings.responsibilityName') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('settings.deptManage') }}</th>
                <th class="px-4 py-3 font-medium">{{ $t('settings.levelManage') }}</th>
                <th class="px-4 py-3 font-medium text-center">{{ $t('common.status') }}</th>
                <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="resp in filteredResponsibilities" :key="resp.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 font-medium text-text-primary">{{ resp.name }}</td>
                <td class="px-4 py-3 text-text-secondary">{{ getDepartmentName(resp.departmentId) }}</td>
                <td class="px-4 py-3"><StatusTag type="info" :text="resp.jobLevelName || resp.jobLevelId" /></td>
                <td class="px-4 py-3 text-center"><StatusTag type="success" :text="$t('common.active')" /></td>
                <td class="px-4 py-3 text-right">
                  <button @click="openEditResponsibility(resp)" class="text-primary hover:text-primary-hover text-xs font-medium mr-3">{{ $t('common.edit') }}</button>
                  <button @click="deleteResponsibility(resp)" class="text-danger hover:text-red-700 text-xs font-medium">{{ $t('common.delete') }}</button>
                </td>
              </tr>
              <tr v-if="filteredResponsibilities.length === 0">
                <td colspan="5" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.noData') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Payment Settings ── -->
      <div v-if="activeTab === 'payment'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-bold text-text-primary">{{ $t('settings.paymentSettings') }}</h3>
        </div>
        <div class="max-w-2xl space-y-6">
          <!-- WeChat Pay -->
          <div class="bg-white border border-gray-200 rounded-xl p-5">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-white text-xl">qr_code</span>
              </div>
              <div>
                <h4 class="font-semibold text-text-primary">微信支付</h4>
                <p class="text-xs text-text-secondary">WeChat Pay</p>
              </div>
              <div class="ml-auto">
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="paymentConfig.wechat.enabled" class="sr-only peer">
                  <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-200 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                </label>
              </div>
            </div>
            <div class="grid grid-cols-1 gap-3">
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">AppID</label>
                <input v-model="paymentConfig.wechat.appId" type="text" :placeholder="$t('settings.enterAppId')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">MchID</label>
                <input v-model="paymentConfig.wechat.mchId" type="text" :placeholder="$t('settings.enterMchId')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">API Key</label>
                <input v-model="paymentConfig.wechat.apiKey" type="password" :placeholder="$t('settings.enterApiKey')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
              </div>
            </div>
          </div>
          <!-- Alipay -->
          <div class="bg-white border border-gray-200 rounded-xl p-5">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-white text-xl">account_balance_wallet</span>
              </div>
              <div>
                <h4 class="font-semibold text-text-primary">支付宝</h4>
                <p class="text-xs text-text-secondary">Alipay</p>
              </div>
              <div class="ml-auto">
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="paymentConfig.alipay.enabled" class="sr-only peer">
                  <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-200 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                </label>
              </div>
            </div>
            <div class="grid grid-cols-1 gap-3">
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">AppID</label>
                <input v-model="paymentConfig.alipay.appId" type="text" :placeholder="$t('settings.enterAppId')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">Private Key</label>
                <textarea v-model="paymentConfig.alipay.privateKey" rows="3" :placeholder="$t('settings.enterPrivateKey')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none"></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">Alipay Public Key</label>
                <textarea v-model="paymentConfig.alipay.alipayPublicKey" rows="3" :placeholder="$t('settings.enterAlipayPublicKey')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none"></textarea>
              </div>
            </div>
          </div>
          <!-- PayPal -->
          <div class="bg-white border border-gray-200 rounded-xl p-5">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-10 h-10 bg-blue-700 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-white text-xl">public</span>
              </div>
              <div>
                <h4 class="font-semibold text-text-primary">PayPal</h4>
                <p class="text-xs text-text-secondary">International Payments</p>
              </div>
              <div class="ml-auto">
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="paymentConfig.paypal.enabled" class="sr-only peer">
                  <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-200 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                </label>
              </div>
            </div>
            <div class="grid grid-cols-1 gap-3">
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">Client ID</label>
                <input v-model="paymentConfig.paypal.clientId" type="text" :placeholder="$t('settings.enterClientId')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">Secret</label>
                <input v-model="paymentConfig.paypal.secret" type="password" :placeholder="$t('settings.enterSecret')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">Environment</label>
                <select v-model="paymentConfig.paypal.environment" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                  <option value="sandbox">Sandbox</option>
                  <option value="live">Live</option>
                </select>
              </div>
            </div>
          </div>
          <!-- Save -->
          <div class="flex justify-end">
            <button @click="savePaymentConfig" class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-6 py-2.5 rounded-lg text-sm font-medium transition-colors">
              <span class="material-symbols-outlined text-[18px]">save</span>
              {{ $t('common.save') }}
            </button>
          </div>
        </div>
      </div>

      <!-- ── DingTalk ── -->
      <div v-if="activeTab === 'dingtalk'" class="p-6">
        <h3 class="font-bold text-text-primary mb-4">{{ $t('settings.dingtalConfig') }}</h3>
        <div class="max-w-2xl space-y-4">
          <div><label class="block text-sm font-medium text-text-primary mb-1">AppKey</label>
            <input type="text" value="ding_xxxxxxxx" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none bg-gray-50" readonly /></div>
          <div><label class="block text-sm font-medium text-text-primary mb-1">AppSecret</label>
            <input type="password" value="xxxxxxxxxxxxxx" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none bg-gray-50" readonly /></div>
          <div><label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.callbackUrl') }}</label>
            <input type="text" value="https://claw.gdqshop.cn/api/dingtalk/callback" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" /></div>
          <button class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">save</span>{{ $t('settings.saveConfig') }}
          </button>
        </div>
      </div>

      <!-- ── WeCom ── -->
      <div v-if="activeTab === 'wecom'" class="p-6">
        <h3 class="font-bold text-text-primary mb-4">{{ $t('settings.wecomConfig') }}</h3>
        <div class="max-w-2xl space-y-4">
          <div><label class="block text-sm font-medium text-text-primary mb-1">CorpID</label>
            <input v-model="wecomCorpId" type="text" placeholder="CorpID" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" /></div>
          <div><label class="block text-sm font-medium text-text-primary mb-1">AgentId</label>
            <input v-model="wecomAgentId" type="text" placeholder="AgentId" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" /></div>
          <div><label class="block text-sm font-medium text-text-primary mb-1">Secret</label>
            <div class="relative">
              <input v-model="wecomSecret" :type="showWecomSecret ? 'text' : 'password'" placeholder="Secret" class="w-full border border-gray-200 rounded-lg px-3 py-2 pr-10 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
              <button @click="showWecomSecret = !showWecomSecret" class="absolute right-2 top-1/2 -translate-y-1/2 text-text-secondary hover:text-text-primary transition-colors">
                <span class="material-symbols-outlined text-[18px]">{{ showWecomSecret ? 'visibility_off' : 'visibility' }}</span>
              </button>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <button @click="saveWecomSettings" class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
              <span class="material-symbols-outlined text-[18px]">save</span>{{ $t('settings.saveConfig') }}
            </button>
            <span v-if="wecomSaveSuccess" class="text-sm text-success flex items-center gap-1">
              <span class="material-symbols-outlined text-[16px]">check_circle</span>{{ $t('settings.saved') }}
            </span>
          </div>
        </div>
      </div>

      <!-- ── OpenClaw ── -->
      <div v-if="activeTab === 'openclaw'" class="p-6">
        <h3 class="font-bold text-text-primary mb-4">{{ $t('settings.openclawConfig') }}</h3>
        <div class="max-w-2xl space-y-4">
          <div><label class="block text-sm font-medium text-text-primary mb-1">Gateway URL</label>
            <input type="text" value="http://localhost:3578" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" /></div>
          <div><label class="block text-sm font-medium text-text-primary mb-1">Webhook Token</label>
            <input type="password" value="xxxxxxxxxxxxxx" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" /></div>
          <button class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">save</span>{{ $t('settings.saveConfig') }}
          </button>
        </div>
      </div>

      <!-- ── AI 配置 ── -->
      <div v-if="activeTab === 'ai-config'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-bold text-text-primary">{{ $t('settings.aiConfig') }}</h3>
        </div>
        <div class="max-w-3xl space-y-6">

          <!-- 机器人名称 -->
          <div class="bg-white border border-gray-200 rounded-xl p-5">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-xl">smart_toy</span>
              </div>
              <div>
                <h4 class="font-semibold text-text-primary">{{ $t('settings.botName') }}</h4>
                <p class="text-xs text-text-secondary">{{ $t('settings.botNameHint') }}</p>
              </div>
            </div>
            <input v-model="botName" type="text" :placeholder="$t('settings.botNamePlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>

          <!-- 模型配置列表 -->
          <div class="bg-white border border-gray-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-500/10 rounded-lg flex items-center justify-center">
                  <span class="material-symbols-outlined text-blue-500 text-xl">psychology</span>
                </div>
                <div>
                  <h4 class="font-semibold text-text-primary">{{ $t('settings.modelList') }}</h4>
                  <p class="text-xs text-text-secondary">{{ $t('settings.modelListHint') }}</p>
                </div>
              </div>
              <button @click="openAddModel" class="flex items-center gap-1.5 bg-primary hover:bg-primary-hover text-white text-xs px-3 py-1.5 rounded-lg transition-colors">
                <span class="material-symbols-outlined text-[14px]">add</span>{{ $t('common.add') }}
              </button>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
                  <tr>
                    <th class="px-3 py-2 font-medium">{{ $t('settings.provider') }}</th>
                    <th class="px-3 py-2 font-medium">{{ $t('settings.modelName') }}</th>
                    <th class="px-3 py-2 font-medium">{{ $t('settings.modelKey') }}</th>
                    <th class="px-3 py-2 font-medium text-center">{{ $t('common.status') }}</th>
                    <th class="px-3 py-2 font-medium text-center">{{ $t('common.default') }}</th>
                    <th class="px-3 py-2 font-medium text-right">{{ $t('common.action') }}</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="cfg in aiConfigs" :key="cfg.id" class="hover:bg-gray-50">
                    <td class="px-3 py-3 font-medium text-text-primary">{{ cfg.provider }}</td>
                    <td class="px-3 py-3 text-text-secondary">{{ cfg.model }}</td>
                    <td class="px-3 py-3 font-mono text-xs text-text-secondary">{{ cfg.api_key ? '••••' + cfg.api_key.slice(-4) : '—' }}</td>
                    <td class="px-3 py-3 text-center">
                      <span v-if="cfg.status === 1" class="inline-flex items-center gap-1 text-xs text-success">
                        <span class="w-1.5 h-1.5 bg-success rounded-full"></span>{{ $t('common.active') }}
                      </span>
                      <span v-else class="inline-flex items-center gap-1 text-xs text-text-secondary">
                        <span class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span>{{ $t('common.inactive') }}
                      </span>
                    </td>
                    <td class="px-3 py-3 text-center">
                      <span v-if="cfg.is_default === 1" class="text-xs text-primary font-medium">✓</span>
                      <span v-else class="text-xs text-gray-300">—</span>
                    </td>
                    <td class="px-3 py-3 text-right">
                      <button @click="openEditModel(cfg)" class="text-primary hover:text-primary-hover text-xs font-medium mr-3">{{ $t('common.edit') }}</button>
                      <button @click="deleteModel(cfg.id)" class="text-danger hover:text-red-700 text-xs font-medium">{{ $t('common.delete') }}</button>
                    </td>
                  </tr>
                  <tr v-if="aiConfigs.length === 0">
                    <td colspan="6" class="px-3 py-8 text-center text-text-secondary text-sm">{{ $t('common.noData') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- 全局保存（机器人名称） -->
          <div class="flex items-center gap-3">
            <button @click="saveBotName" :disabled="botNameLoading" class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
              <span class="material-symbols-outlined text-[18px]">save</span>{{ $t('settings.saveBotName') }}
            </button>
            <span v-if="botNameSuccess" class="text-sm text-success flex items-center gap-1">
              <span class="material-symbols-outlined text-[16px]">check_circle</span>{{ $t('settings.saved') }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Department Modal ── -->
    <div v-if="showDeptModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
          <h3 class="text-lg font-bold text-text-primary">{{ editingDept ? $t('settings.editDept') : $t('settings.addDept') }}</h3>
          <button @click="showDeptModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="px-6 py-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.deptName') }} <span class="text-danger">*</span></label>
            <input v-model="deptForm.name" type="text" :placeholder="$t('settings.enterDeptName')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.parentDept') }}</label>
            <select v-model="deptForm.parent_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option :value="null">{{ $t('settings.noParentDept') }}</option>
              <option v-for="dept in departments.filter(d => d.id !== editingDept?.id)" :key="dept.id" :value="dept.id">
                {{ dept.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.deptManager') }}</label>
            <select v-model="deptForm.manager_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option :value="null">{{ $t('settings.noManager') }}</option>
              <option v-for="u in users" :key="u.id" :value="u.id">
                {{ u.name }} ({{ u.phone }})
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.sortOrder') }}</label>
            <input v-model.number="deptForm.sort_order" type="number" placeholder="0" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div v-if="deptError" class="text-sm text-danger bg-red-50 px-3 py-2 rounded-lg">{{ deptError }}</div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
          <button @click="showDeptModal = false" class="px-4 py-2 text-sm text-text-secondary border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">{{ $t('common.cancel') }}</button>
          <button @click="saveDept" :disabled="deptLoading" class="px-4 py-2 text-sm text-white bg-primary hover:bg-primary-hover rounded-lg transition-colors disabled:opacity-50">
            {{ deptLoading ? $t('common.saving') : $t('common.saveChanges') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Job Level Modal ── -->
    <div v-if="showLevelModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
          <h3 class="text-lg font-bold text-text-primary">{{ editingLevel ? $t('settings.editLevel') : $t('settings.addLevel') }}</h3>
          <button @click="showLevelModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="px-6 py-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.levelName') }} <span class="text-danger">*</span></label>
            <input v-model="levelForm.name" type="text" :placeholder="$t('settings.levelNamePlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.grade') }} <span class="text-danger">*</span></label>
            <input v-model.number="levelForm.level" type="number" :placeholder="$t('settings.gradePlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            <p class="text-xs text-text-secondary mt-1">{{ $t('settings.gradeHint') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.description') }}</label>
            <textarea v-model="levelForm.description" :placeholder="$t('settings.levelDescPlaceholder')" rows="3" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.responsibilityDesc') }}</label>
            <textarea v-model="levelForm.responsibility_desc" :placeholder="$t('settings.responsibilityDescPlaceholder')" rows="3" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"></textarea>
          </div>
          <div v-if="levelError" class="text-sm text-danger bg-red-50 px-3 py-2 rounded-lg">{{ levelError }}</div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
          <button @click="showLevelModal = false" class="px-4 py-2 text-sm text-text-secondary border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">{{ $t('common.cancel') }}</button>
          <button @click="saveLevel" :disabled="levelLoading" class="px-4 py-2 text-sm text-white bg-primary hover:bg-primary-hover rounded-lg transition-colors disabled:opacity-50">
            {{ levelLoading ? $t('common.saving') : $t('common.saveChanges') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Add/Edit User Modal ── -->
    <div v-if="showUserModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="showUserModal = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <h3 class="font-bold text-text-primary">{{ editingUser ? $t('settings.editUser') : $t('settings.addUser') }}</h3>
          <button @click="showUserModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>
        <div class="px-6 py-4 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.name') }} <span class="text-danger">*</span></label>
              <input v-model="userForm.name" type="text" :placeholder="$t('settings.enterName')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.department') }}</label>
              <select v-model="userForm.department_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                <option value="">{{ $t('settings.selectDept') }}</option>
                <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.jobResponsibility') }}</label>
            <select v-model="userForm.responsibility_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="">{{ $t('settings.selectResponsibility') }}</option>
              <option v-for="resp in responsibilities" :key="resp.id" :value="resp.id">{{ resp.title }}</option>
            </select>
          </div>
          <div class="flex items-center gap-4 mt-3">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="userForm.require_attendance" class="w-4 h-4 text-primary rounded" />
              <span class="text-sm text-text-primary">{{ $t('settings.requireAttendance') }}</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="userForm.require_worklog" class="w-4 h-4 text-primary rounded" />
              <span class="text-sm text-text-primary">{{ $t('settings.requireWorklog') }}</span>
            </label>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.supplierAssociation') }} <span class="text-xs text-text-secondary font-normal">{{ $t('settings.supplierAssociationHint') }}</span></label>
            <div class="border border-gray-200 rounded-lg p-3 bg-white max-h-48 overflow-y-auto">
              <label v-for="s in suppliers" :key="s.id" class="flex items-center gap-2 py-1.5 hover:bg-gray-50 rounded px-2 cursor-pointer">
                <input
                  type="checkbox"
                  :value="s.id"
                  v-model="userForm.supplier_ids"
                  class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary focus:ring-2"
                />
                <span class="text-sm text-text-primary">{{ s.name }}</span>
              </label>
              <div v-if="suppliers.length === 0" class="text-sm text-text-secondary text-center py-2">
                {{ $t('settings.noSuppliers') }}
              </div>
            </div>
            <p v-if="userForm.supplier_ids.length > 0" class="text-xs text-text-secondary mt-1">
              {{ $t('settings.selectedSuppliers', { count: userForm.supplier_ids.length }) }}
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.dealerAssociation') }} <span class="text-xs text-text-secondary font-normal">{{ $t('settings.dealerAssociationHint') }}</span></label>
            <div class="border border-gray-200 rounded-lg p-3 bg-white max-h-48 overflow-y-auto">
              <label v-for="d in dealers" :key="d.id" class="flex items-center gap-2 py-1.5 hover:bg-gray-50 rounded px-2 cursor-pointer">
                <input type="checkbox" :value="d.id" v-model="userForm.dealer_ids" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary focus:ring-2" />
                <span class="text-sm text-text-primary">{{ d.name }}</span>
              </label>
              <div v-if="dealers.length === 0" class="text-sm text-text-secondary text-center py-2">{{ $t('settings.noDealers') }}</div>
            </div>
            <p v-if="userForm.dealer_ids.length > 0" class="text-xs text-text-secondary mt-1">{{ $t('settings.selectedDealers', { count: userForm.dealer_ids.length }) }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.storeAssociation') }} <span class="text-xs text-text-secondary font-normal">{{ $t('settings.storeAssociationHint') }}</span></label>
            <div class="border border-gray-200 rounded-lg p-3 bg-white max-h-48 overflow-y-auto">
              <label v-for="st in stores" :key="st.id" class="flex items-center gap-2 py-1.5 hover:bg-gray-50 rounded px-2 cursor-pointer">
                <input type="checkbox" :value="st.id" v-model="userForm.store_ids" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary focus:ring-2" />
                <span class="text-sm text-text-primary">{{ st.name }}</span>
              </label>
              <div v-if="stores.length === 0" class="text-sm text-text-secondary text-center py-2">{{ $t('settings.noStores') }}</div>
            </div>
            <p v-if="userForm.store_ids.length > 0" class="text-xs text-text-secondary mt-1">{{ $t('settings.selectedStores', { count: userForm.store_ids.length }) }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.phone') }} <span class="text-danger">*</span></label>
            <input v-model="userForm.phone" type="text" :placeholder="$t('settings.enterPhone')" :disabled="!!editingUser" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none disabled:bg-gray-50 disabled:text-text-secondary" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.supervisor') }}</label>
            <select v-model="userForm.supervisor_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option :value="null">{{ $t('settings.noSupervisor') }}</option>
              <option v-for="u in users.filter(user => user.id !== editingUser?.id)" :key="u.id" :value="u.id">
                {{ u.name }} ({{ u.phone }})
              </option>
            </select>
            <p class="text-xs text-text-secondary mt-1">
              {{ $t('settings.supervisorHint') }}
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.password') }} {{ editingUser ? $t('settings.passwordEditHint') : '*' }}</label>
            <input v-model="userForm.password" type="password" :placeholder="$t('settings.enterPassword')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.role') }} <span class="text-danger">*</span></label>
            <select v-model="userForm.role" @change="onRoleChange" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.label }}</option>
            </select>
            <p class="text-xs text-text-secondary mt-1">
              {{ $t('settings.roleInheritHint') }}
            </p>
          </div>

          <div v-if="userError" class="text-sm text-danger bg-red-50 px-3 py-2 rounded-lg">{{ userError }}</div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
          <button @click="showUserModal = false" class="px-4 py-2 text-sm text-text-secondary border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">{{ $t('common.cancel') }}</button>
          <button @click="saveUser" :disabled="userLoading" class="px-4 py-2 text-sm text-white bg-primary hover:bg-primary-hover rounded-lg transition-colors disabled:opacity-50">
            {{ userLoading ? $t('common.saving') : $t('common.saveChanges') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Add/Edit Role Modal ── -->
    <div v-if="showRoleModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="showRoleModal = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <h3 class="font-bold text-text-primary">{{ editingRole ? $t('settings.editRole') : $t('settings.addRole') }}</h3>
          <button @click="showRoleModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>
        <div class="px-6 py-4 space-y-4">
          <!-- Role name (identifier) - only editable when adding -->
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">
              {{ $t('settings.roleIdentifier') }} <span class="text-danger">*</span>
              <span class="text-xs font-normal text-text-secondary ml-1">{{ $t('settings.roleIdentifierHint') }}</span>
            </label>
            <input
              v-model="roleForm.name"
              type="text"
              placeholder="e.g. viewer"
              :disabled="!!editingRole"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none disabled:bg-gray-50 disabled:text-text-secondary font-mono"
            />
          </div>
          <!-- Role label (display name) -->
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.roleName') }} <span class="text-danger">*</span></label>
            <input
              v-model="roleForm.label"
              type="text"
              :placeholder="$t('settings.roleNamePlaceholder')"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            />
          </div>
          <!-- Permissions -->
          <div>
            <label class="block text-sm font-medium text-text-primary mb-3">
              {{ $t('settings.pagePermissions') }}
              <span class="text-xs font-normal text-text-secondary ml-1">{{ $t('settings.pagePermissionsHint') }}</span>
            </label>
            <ElCollapse v-model="activePermissionGroups" class="permission-collapse">
              <ElCollapseItem
                v-for="group in PERMISSION_GROUPS"
                :key="group.label"
                :name="group.label"
              >
                <template #title>
                  <span class="flex items-center gap-2">
                    <span>{{ $t(group.label) }}</span>
                    <span class="text-xs text-text-secondary font-normal">
                      ({{ group.children.filter(c => roleForm.permissions.includes(c.key)).length }}/{{ group.children.length }})
                    </span>
                  </span>
                </template>
                <div class="grid grid-cols-2 gap-2">
                  <label
                    v-for="item in group.children" :key="item.key"
                    :class="['flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer border transition-colors text-sm',
                      roleForm.permissions.includes(item.key) ? 'border-primary bg-primary/5 text-primary' : 'border-gray-100 text-text-secondary hover:border-gray-200']"
                    @click="toggleRolePermission(item.key)"
                  >
                    <span class="material-symbols-outlined text-[16px]">
                      {{ roleForm.permissions.includes(item.key) ? 'check_box' : 'check_box_outline_blank' }}
                    </span>
                    {{ $t(item.labelKey) }}
                  </label>
                </div>
              </ElCollapseItem>
            </ElCollapse>
          </div>

          <div v-if="roleError" class="text-sm text-danger bg-red-50 px-3 py-2 rounded-lg">{{ roleError }}</div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
          <button @click="showRoleModal = false" class="px-4 py-2 text-sm text-text-secondary border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">{{ $t('common.cancel') }}</button>
          <button @click="saveRole" :disabled="roleLoading" class="px-4 py-2 text-sm text-white bg-primary hover:bg-primary-hover rounded-lg transition-colors disabled:opacity-50">
            {{ roleLoading ? $t('common.saving') : $t('common.saveChanges') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Model Config Modal ── -->
    <div v-if="showModelModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="showModelModal = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <h3 class="font-bold text-text-primary">{{ editingModel ? $t('settings.editModel') : $t('settings.addModel') }}</h3>
          <button @click="showModelModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>
        <div class="px-6 py-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.provider') }} <span class="text-danger">*</span></label>
            <input v-model="modelForm.provider" type="text" :placeholder="$t('settings.providerPlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.modelName') }} <span class="text-danger">*</span></label>
            <input v-model="modelForm.model" type="text" :placeholder="$t('settings.modelNamePlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.modelKey') }} <span v-if="editingModel" class="text-xs text-text-secondary font-normal">{{ $t('settings.leaveBlankKeep') }}</span></label>
            <input v-model="modelForm.api_key" type="password" :placeholder="editingModel ? $t('settings.keyKeepExisting') : $t('settings.enterModelKey')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.baseUrl') }} <span class="text-xs text-text-secondary font-normal">{{ $t('settings.baseUrlOptional') }}</span></label>
            <input v-model="modelForm.base_url" type="text" :placeholder="$t('settings.baseUrlPlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="modelForm.is_default" class="w-4 h-4 text-primary rounded" />
            <span class="text-sm text-text-primary">{{ $t('settings.setAsDefault') }}</span>
          </label>
          <div v-if="modelError" class="text-sm text-danger bg-red-50 px-3 py-2 rounded-lg">{{ modelError }}</div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
          <button @click="showModelModal = false" class="px-4 py-2 text-sm text-text-secondary border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">{{ $t('common.cancel') }}</button>
          <button @click="saveModel" :disabled="modelLoading" class="px-4 py-2 text-sm text-white bg-primary hover:bg-primary-hover rounded-lg transition-colors disabled:opacity-50">
            {{ modelLoading ? $t('common.saving') : $t('common.saveChanges') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Add/Edit Warehouse Modal ── -->
    <div v-if="showWarehouseModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="showWarehouseModal = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <h3 class="font-bold text-text-primary">{{ editingWarehouse ? $t('settings.editWarehouse') : $t('settings.addWarehouse') }}</h3>
          <button @click="showWarehouseModal = false" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>
        <div class="px-6 py-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.warehouseName') }} <span class="text-danger">*</span></label>
            <input
              v-model="warehouseForm.name"
              type="text"
              :placeholder="$t('settings.enterWarehouseName')"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.address') }}</label>
            <input
              v-model="warehouseForm.address"
              type="text"
              :placeholder="$t('settings.enterWarehouseAddress')"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.type') }}</label>
            <input
              v-model="warehouseForm.type"
              type="text"
              :placeholder="$t('settings.warehouseTypePlaceholder')"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('settings.manager') }}</label>
            <input
              v-model="warehouseForm.manager"
              type="text"
              :placeholder="$t('settings.enterManagerName')"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.contact') }}</label>
            <input
              v-model="warehouseForm.contact"
              type="text"
              :placeholder="$t('settings.enterContactPhone')"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            />
          </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
          <button @click="showWarehouseModal = false" class="px-4 py-2 text-sm text-text-secondary border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">{{ $t('common.cancel') }}</button>
          <button @click="saveWarehouse" :disabled="warehouseLoading" class="px-4 py-2 text-sm text-white bg-primary hover:bg-primary-hover rounded-lg transition-colors disabled:opacity-50">
            {{ warehouseLoading ? $t('common.saving') : $t('common.saveChanges') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.permission-collapse :deep(.el-collapse-item__header) {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-primary);
  padding-left: 8px;
}
.permission-collapse :deep(.el-collapse-item__wrap) {
  border-top: none;
}
.permission-collapse :deep(.el-collapse-item__content) {
  padding-bottom: 8px;
}

@media (max-width: 768px) {
  /* Tab content area */
  .settings-content > div[class*="p-6"] {
    padding: 12px;
  }

  /* Tab header - stack on mobile */
  .settings-content .flex.justify-between.items-center {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  /* Customer stats - single column on mobile */
  .settings-content .grid.grid-cols-2 {
    grid-template-columns: 1fr;
  }

  /* Tables - reduce padding */
  .settings-content table td,
  .settings-content table th {
    padding-left: 8px;
    padding-right: 8px;
  }

  /* Modal adjustments */
  .settings-content .fixed .bg-white {
    max-width: 100%;
    margin: 0 8px;
  }

  /* Form grids - single column on mobile */
  .settings-content .grid.grid-cols-2 {
    grid-template-columns: 1fr;
  }

  /* Buttons - full width on small screens */
  .settings-content button[class*="px-4 py-2"] {
    width: 100%;
    justify-content: center;
  }

  /* Tab bar */
  .settings-tabs {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  /* Payment settings - full width inputs */
  .settings-content .max-w-2xl input,
  .settings-content .max-w-2xl textarea,
  .settings-content .max-w-2xl select {
    width: 100%;
  }

  /* Role cards - stack on mobile */
  .settings-content .grid.grid-cols-1.md\:grid-cols-2 {
    grid-template-columns: 1fr;
  }

  /* AI config table */
  .settings-content .overflow-x-auto {
    font-size: 12px;
  }

  /* Modal footer buttons */
  .settings-content .fixed .flex.justify-end.gap-3 {
    flex-direction: column-reverse;
    gap: 8px;
  }
  .settings-content .fixed .flex.justify-end.gap-3 button {
    width: 100%;
  }
}
</style>
