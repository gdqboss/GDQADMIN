<template>
  <div>
    <div class="flex gap-2 mb-4">
      <el-button type="primary" @click="openAdd">新增权限</el-button>
    </div>

    <el-table :data="permissions" stripe border v-loading="loading">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="name" label="标识" width="180" />
      <el-table-column prop="label" label="名称" min-width="150" />
      <el-table-column prop="category" label="分类" width="120" />
      <el-table-column prop="description" label="描述" min-width="200" show-overflow-tooltip />
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 弹窗 -->
    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑权限' : '新增权限'" width="450px">
      <el-form ref="formRef" :model="form" label-width="80px">
        <el-form-item label="标识" prop="name" :rules="[{ required: true, message: '必填' }]">
          <el-input v-model="form.name" placeholder="如: product:read" :disabled="!!form.id" />
        </el-form-item>
        <el-form-item label="名称" prop="label" :rules="[{ required: true, message: '必填' }]">
          <el-input v-model="form.label" placeholder="如: 商品查看" />
        </el-form-item>
        <el-form-item label="分类" prop="category" :rules="[{ required: true, message: '必填' }]">
          <el-select v-model="form.category" style="width:100%">
            <el-option v-for="c in categories" :key="c" :label="c" :value="c" />
          </el-select>
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="form.description" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible=false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getPermissionList, createPermission, updatePermission, deletePermission } from '@/api/rbac/permissions'

const permissions = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const formRef = ref()
const form = reactive({ id: null, name: '', label: '', category: '', description: '' })
const categories = ['商品管理', '库存管理', '销售管理', '财务', '客户管理', '售后管理', '报表管理', '系统设置', 'OA办公', '其他']

async function load() {
  loading.value = true
  try {
    const res = await getPermissionList({ pageSize: 500 })
    permissions.value = Array.isArray(res) ? res : (res?.list || [])
  } finally {
    loading.value = false
  }
}

function openAdd() {
  Object.assign(form, { id: null, name: '', label: '', category: '', description: '' })
  dialogVisible.value = true
}

function openEdit(row) {
  Object.assign(form, { id: row.id, name: row.name, label: row.label, category: row.category, description: row.description })
  dialogVisible.value = true
}

async function handleSubmit() {
  try {
    await formRef.value.validate()
    if (form.id) {
      await updatePermission(form.id, { label: form.label, category: form.category, description: form.description })
      ElMessage.success('更新成功')
    } else {
      await createPermission(form)
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    load()
  } catch (e) { /* fail */ }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(`确定删除「${row.label}」？`, '确认')
    await deletePermission(row.id)
    ElMessage.success('删除成功')
    load()
  } catch (e) { /* cancel */ }
}

onMounted(() => load())
</script>
