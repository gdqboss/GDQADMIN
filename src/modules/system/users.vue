<template>
  <div class="p-4">
    <!-- 搜索栏 -->
    <div class="flex gap-2 mb-4">
      <el-input v-model="searchForm.keyword" placeholder="搜索姓名/邮箱/手机" clearable style="width:220px" @keyup.enter="loadUsers" />
      <el-button type="primary" @click="loadUsers">搜索</el-button>
    </div>

    <!-- 用户列表 -->
    <el-table :data="users" stripe border v-loading="loading">
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="name" label="姓名" min-width="120" />
      <el-table-column prop="email" label="邮箱" min-width="200" />
      <el-table-column prop="phone" label="手机" width="130" />
      <el-table-column prop="role" label="原角色" width="100">
        <template #default="{ row }">
          <el-tag type="info" size="small">{{ row.role }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="关联角色" min-width="250">
        <template #default="{ row }">
          <div class="flex flex-wrap gap-1">
            <el-tag
              v-for="r in row._roles"
              :key="r.id"
              type="success"
              size="small"
              closable
              @close="removeUserRole(row, r.id)"
              class="mr-1"
            >{{ r.label }}</el-tag>
            <el-button link type="primary" size="small" @click="openRoleDialog(row)">+ 分配角色</el-button>
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
            {{ row.status === 'active' ? '正常' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="100" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" size="small" @click="openRoleDialog(row)">角色分配</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 角色分配弹窗 -->
    <el-dialog v-model="dialogVisible" :title="`为「${currentUser?.name}」分配角色`" width="550px">
      <div v-if="currentUser">
        <div class="mb-2 text-sm text-gray-500">当前用户：{{ currentUser.name }} ({{ currentUser.email }})</div>
        <el-checkbox-group v-model="selectedRoleIds">
          <div v-for="r in allRoles" :key="r.id" class="py-1">
            <el-checkbox :value="r.id">
              <span class="font-medium">{{ r.label }}</span>
              <span class="text-xs text-gray-400 ml-2">{{ r.name }}</span>
              <el-tag v-if="r.is_system" type="warning" size="small" class="ml-2">系统</el-tag>
            </el-checkbox>
          </div>
        </el-checkbox-group>
      </div>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleRoleSubmit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import request from '@/api/request'
import { getRoleList } from '@/api/rbac/roles'
import { getUserRoles, updateUserRoles } from '@/api/rbac/userRoles'

const users = ref([])
const allRoles = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const currentUser = ref(null)
const selectedRoleIds = ref([])
const searchForm = reactive({ keyword: '' })

async function loadUsers() {
  loading.value = true
  try {
    const params = { page: 1, pageSize: 200 }
    if (searchForm.keyword) params.keyword = searchForm.keyword
    const res = await request.get('/users', { params })
    users.value = res.data.list || res.data || []
    for (const u of users.value) {
      try {
        const roleRes = await getUserRoles(u.id)
        u._roles = roleRes.data || []
      } catch { u._roles = [] }
    }
  } catch (e) {
    ElMessage.error('加载用户失败')
  } finally {
    loading.value = false
  }
}

async function loadRoles() {
  const res = await getRoleList()
  allRoles.value = res.data
}

function openRoleDialog(user) {
  currentUser.value = user
  selectedRoleIds.value = user._roles.map(r => r.id)
  dialogVisible.value = true
}

async function handleRoleSubmit() {
  try {
    await updateUserRoles(currentUser.value.id, selectedRoleIds.value)
    ElMessage.success('角色分配成功')
    dialogVisible.value = false
    loadUsers()
  } catch (e) { ElMessage.error('分配失败') }
}

async function removeUserRole(user, roleId) {
  const newRoleIds = user._roles.filter(r => r.id !== roleId).map(r => r.id)
  try {
    await updateUserRoles(user.id, newRoleIds)
    user._roles = user._roles.filter(r => r.id !== roleId)
    ElMessage.success('已移除角色')
  } catch (e) { ElMessage.error('移除失败') }
}

onMounted(() => { loadUsers(); loadRoles() })
</script>
