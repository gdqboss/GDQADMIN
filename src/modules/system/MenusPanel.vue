<template>
  <div>
    <div class="flex gap-2 mb-4">
      <el-button type="primary" @click="openAdd(null)">新增菜单</el-button>
    </div>

    <el-table :data="menus" stripe border v-loading="loading" row-key="id" :tree-props="{children: 'children', hasChildren: 'hasChildren'}">
      <el-table-column prop="label" label="菜单名称" min-width="180" />
      <el-table-column prop="path" label="路由路径" min-width="200" />
      <el-table-column prop="icon" label="图标" width="120" />
      <el-table-column prop="sort_order" label="排序" width="80" />
      <el-table-column prop="visible" label="显示" width="80">
        <template #default="{ row }">
          <el-tag :type="row.visible === 'show' ? 'success' : 'info'" size="small">{{ row.visible === 'show' ? '是' : '否' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openAdd(row)">添加子菜单</el-button>
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 弹窗 -->
    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑菜单' : '新增菜单'" width="500px">
      <el-form ref="formRef" :model="form" label-width="90px">
        <el-form-item label="菜单名称" prop="label" :rules="[{ required: true, message: '必填' }]">
          <el-input v-model="form.label" placeholder="如: 商品管理" />
        </el-form-item>
        <el-form-item label="路由路径" prop="path" :rules="[{ required: true, message: '必填' }]">
          <el-input v-model="form.path" placeholder="如: /products" />
        </el-form-item>
        <el-form-item label="图标">
          <el-input v-model="form.icon" placeholder="Material Symbols 图标名，如: inventory_2" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" />
        </el-form-item>
        <el-form-item label="显示">
          <el-radio-group v-model="form.visible">
            <el-radio value="show">显示</el-radio>
            <el-radio value="hide">隐藏</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="组件路径" prop="component_path">
          <el-input v-model="form.component_path" placeholder="如: modules/products/index.vue" />
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
import { getMenuTree, createMenu, updateMenu, deleteMenu } from '@/api/rbac/menus'

const menus = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const formRef = ref()
const form = reactive({ id: null, parent_id: null, label: '', path: '', icon: '', sort_order: 0, visible: 'show', component_path: '' })

async function load() {
  loading.value = true
  try {
    const res = await getMenuTree()
    menus.value = res || []
  } finally {
    loading.value = false
  }
}

function openAdd(parent) {
  Object.assign(form, { id: null, parent_id: parent?.id || null, label: '', path: '', icon: '', sort_order: 0, visible: 'show', component_path: '' })
  dialogVisible.value = true
}

function openEdit(row) {
  Object.assign(form, { id: row.id, parent_id: row.parent_id, label: row.label, path: row.path, icon: row.icon || '', sort_order: row.sort_order || 0, visible: row.visible || 'show', component_path: row.component_path || '' })
  dialogVisible.value = true
}

async function handleSubmit() {
  try {
    await formRef.value.validate()
    const data = { ...form }
    if (form.id) {
      await updateMenu(form.id, data)
      ElMessage.success('更新成功')
    } else {
      await createMenu(data)
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    load()
  } catch (e) { /* fail */ }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(`确定删除「${row.label}」？`, '确认')
    await deleteMenu(row.id)
    ElMessage.success('删除成功')
    load()
  } catch (e) { /* cancel */ }
}

onMounted(() => load())
</script>
