<template>
  <div>
    <!-- 搜索栏 -->
    <div class="flex gap-2 mb-4">
      <el-input v-model="keyword" placeholder="搜索姓名/邮箱/手机" clearable style="width:220px" @keyup.enter="load" />
      <el-button type="primary" @click="load">搜索</el-button>
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
      <el-table-column label="关联角色" min-width="220">
        <template #default="{ row }">
          <div class="flex flex-wrap gap-1">
            <el-tag v-for="r in row._roles" :key="r.id" type="success" size="small" closable @close="removeRole(row, r.id)" class="mr-1">{{ r.label }}</el-tag>
            <el-button link type="primary" size="small" @click="openDialog(row)">+ 分配</el-button>
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">{{ row.status === 'active' ? '正常' : '禁用' }}</el-tag>
        </template>
      </el-table-column>
    </el-table>

    <!-- 角色分配弹窗 -->
    <el-dialog v-model="dialogVisible" :title="`为「${currentUser?.name}」分配角色`" width="500px">
      <el-checkbox-group v-model="selected">
        <div v-for="r in allRoles" :key="r.id" class="py-1">
          <el-checkbox :value="r.id"><span class="font-medium">{{ r.label }}</span> <span class="text-xs text-gray-400">{{ r.name }}</span></el-checkbox>
        </div>
      </el-checkbox-group>
      <template #footer>
        <el-button @click="dialogVisible=false">取消</el-button>
        <el-button type="primary" @click="submitRoles">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import request from '@/api/request'
import { getRoleList } from '@/api/rbac/roles'
import { getUserRoles, updateUserRoles } from '@/api/rbac/userRoles'

const users = ref([])
const allRoles = ref([])
const loading = ref(false)
const keyword = ref('')
const dialogVisible = ref(false)
const currentUser = ref(null)
const selected = ref([])

async function load() {
  loading.value = true
  try {
    const params = { page: 1, pageSize: 200 }
    if (keyword.value) params.keyword = keyword.value
    const res = await request.get('/users', { params })
    users.value = res.list || res || []
    for (const u of users.value) {
      try {
        const r = await getUserRoles(u.id)
        u._roles = r || []
      } catch { u._roles = [] }
    }
  } finally {
    loading.value = false
  }
}

async function loadRoles() {
  const r = await getRoleList()
  allRoles.value = r.data
}

function openDialog(user) {
  currentUser.value = user
  selected.value = user._roles.map(r => r.id)
  dialogVisible.value = true
}

async function submitRoles() {
  try {
    await updateUserRoles(currentUser.value.id, selected.value)
    ElMessage.success('保存成功')
    dialogVisible.value = false
    load()
  } catch { ElMessage.error('保存失败') }
}

async function removeRole(user, roleId) {
  const ids = user._roles.filter(r => r.id !== roleId).map(r => r.id)
  try {
    await updateUserRoles(user.id, ids)
    user._roles = user._roles.filter(r => r.id !== roleId)
    ElMessage.success('已移除')
  } catch { ElMessage.error('移除失败') }
}

onMounted(() => { load(); loadRoles() })
</script>
