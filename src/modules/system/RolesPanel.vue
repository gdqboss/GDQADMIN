<template>
  <div>
    <!-- 工具栏 -->
    <div class="flex gap-2 mb-4">
      <el-button type="primary" @click="handleAdd">新增角色</el-button>
    </div>

    <!-- 角色列表 -->
    <el-table :data="roles" stripe border v-loading="loading">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="name" label="标识" width="150" />
      <el-table-column prop="label" label="名称" min-width="150" />
      <el-table-column prop="description" label="描述" min-width="200" show-overflow-tooltip />
      <el-table-column prop="user_count" label="用户数" width="90" />
      <el-table-column prop="is_system" label="系统" width="80">
        <template #default="{ row }">
          <el-tag :type="row.is_system ? 'warning' : 'info'" size="small">{{ row.is_system ? '是' : '否' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="280" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="handleAssignPerm(row)">分配权限</el-button>
          <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
          <el-button link type="danger" :disabled="row.is_system" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 编辑角色弹窗 -->
    <el-dialog v-model="editDialogVisible" title="编辑角色" width="450px">
      <el-form ref="editFormRef" :model="editForm" label-width="80px">
        <el-form-item label="标识" prop="name" :rules="[{ required: true, message: '必填' }]">
          <el-input v-model="editForm.name" placeholder="英文小写" :disabled="!!editForm.id" />
        </el-form-item>
        <el-form-item label="名称" prop="label" :rules="[{ required: true, message: '必填' }]">
          <el-input v-model="editForm.label" placeholder="如: 仓库管理员" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="editForm.description" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleEditSubmit">确定</el-button>
      </template>
    </el-dialog>

    <!-- 权限分配弹窗 -->
    <el-dialog v-model="permDialogVisible" :title="`分配权限 - ${currentRole?.label}`" width="650px">
      <div v-if="currentRole" class="max-h-[60vh] overflow-y-auto">
        <el-checkbox-group v-model="selectedPermIds">
          <div v-for="(perms, cat) in groupedPermissions" :key="cat" class="mb-4">
            <div class="font-bold text-sm text-gray-600 mb-2">{{ cat }}</div>
            <el-checkbox v-for="p in perms" :key="p.id" :value="p.id" :label="p.id" class="mr-4 mb-1">
              {{ p.label }} <span class="text-xs text-gray-400 ml-1">{{ p.name }}</span>
            </el-checkbox>
          </div>
        </el-checkbox-group>
      </div>
      <template #footer>
        <el-button @click="permDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handlePermSubmit">保存权限</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getRoleList, getRolePermissions, createRole, updateRole, deleteRole, updateRolePermissions } from '@/api/rbac/roles'
import { getPermissionList } from '@/api/rbac/permissions'

const roles = ref([])
const loading = ref(false)
const editDialogVisible = ref(false)
const permDialogVisible = ref(false)
const currentRole = ref(null)
const editFormRef = ref()
const allPermissions = ref([])
const selectedPermIds = ref([])
const editForm = reactive({ id: null, name: '', label: '', description: '' })

const groupedPermissions = computed(() => {
  const map = {}
  for (const p of allPermissions.value) {
    if (!map[p.category]) map[p.category] = []
    map[p.category].push(p)
  }
  return map
})

async function loadRoles() {
  loading.value = true
  try {
    const data = await getRoleList()
    roles.value = data || []
  } finally {
    loading.value = false
  }
}

async function loadAllPermissions() {
  const data = await getPermissionList({ pageSize: 500 })
  allPermissions.value = Array.isArray(data) ? data : (data?.list || [])
}

async function loadRolePermissions(roleId) {
  const data = await getRolePermissions(roleId)
  selectedPermIds.value = (data || []).map(p => p.id)
}

function handleAdd() {
  Object.assign(editForm, { id: null, name: '', label: '', description: '' })
  editDialogVisible.value = true
}

function handleEdit(row) {
  Object.assign(editForm, { id: row.id, name: row.name, label: row.label, description: row.description })
  editDialogVisible.value = true
}

async function handleEditSubmit() {
  try {
    await editFormRef.value.validate()
    if (editForm.id) {
      await updateRole(editForm.id, { label: editForm.label, description: editForm.description })
      ElMessage.success('更新成功')
    } else {
      await createRole(editForm)
      ElMessage.success('创建成功')
    }
    editDialogVisible.value = false
    loadRoles()
  } catch (e) { /* validate fail */ }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(`确定删除角色「${row.label}」？`, '确认')
    await deleteRole(row.id)
    ElMessage.success('删除成功')
    loadRoles()
  } catch (e) { /* cancel */ }
}

async function handleAssignPerm(row) {
  currentRole.value = row
  await loadAllPermissions()
  await loadRolePermissions(row.id)
  permDialogVisible.value = true
}

async function handlePermSubmit() {
  try {
    await updateRolePermissions(currentRole.value.id, selectedPermIds.value)
    permDialogVisible.value = false
    ElMessage.success('权限分配成功')
  } catch (e) { ElMessage.error('保存失败') }
}

onMounted(() => loadRoles())
</script>
