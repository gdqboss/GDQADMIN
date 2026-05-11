<template>
  <div class="p-4">
    <!-- 工具栏 -->
    <div class="flex gap-2 mb-4">
      <el-button type="primary" @click="handleAdd(null)">新增顶级菜单</el-button>
    </div>

    <!-- 树形表格 -->
    <el-table :data="menuTree" stripe border row-key="id" :tree-props="{ children: 'children', hasChildren: 'hasChildren' }">
      <el-table-column prop="label" label="菜单名称" min-width="180" />
      <el-table-column prop="name" label="标识" width="150" />
      <el-table-column prop="path" label="路由" min-width="200" show-overflow-tooltip />
      <el-table-column prop="icon" label="图标" width="120" />
      <el-table-column prop="sort_order" label="排序" width="80" />
      <el-table-column prop="type" label="类型" width="100">
        <template #default="{ row }">
          <el-tag :type="row.type==='menu'?'primary':row.type==='button'?'success':'warning'">{{ row.type }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="visible" label="显示" width="80">
        <template #default="{ row }">
          <el-tag :type="row.visible==='show'?'success':'info'">{{ row.visible }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="220" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="handleAdd(row)">新增子菜单</el-button>
          <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
          <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 弹窗 -->
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="550px">
      <el-form ref="formRef" :model="form" label-width="90px">
        <el-form-item label="菜单标识" prop="name" :rules="[{ required: true, message: '必填' }]">
          <el-input v-model="form.name" placeholder="如: products" />
        </el-form-item>
        <el-form-item label="显示名称" prop="label" :rules="[{ required: true, message: '必填' }]">
          <el-input v-model="form.label" placeholder="如: 商品管理" />
        </el-form-item>
        <el-form-item label="路由路径" prop="path" :rules="[{ required: true, message: '必填' }]">
          <el-input v-model="form.path" placeholder="如: /products" />
        </el-form-item>
        <el-form-item label="图标">
          <el-input v-model="form.icon" placeholder="Material Symbols 图标名，如: inventory_2" />
        </el-form-item>
        <el-form-item label="父菜单">
          <el-select v-model="form.parent_id" clearable placeholder="顶级菜单" style="width:100%">
            <el-option v-for="m in flatMenus" :key="m.id" :label="m.label" :value="m.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" />
        </el-form-item>
        <el-form-item label="类型">
          <el-radio-group v-model="form.type">
            <el-radio value="menu">菜单</el-radio>
            <el-radio value="button">按钮</el-radio>
            <el-radio value="divider">分隔线</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="显示">
          <el-radio-group v-model="form.visible">
            <el-radio value="show">显示</el-radio>
            <el-radio value="hide">隐藏</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio value="enabled">启用</el-radio>
            <el-radio value="disabled">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getMenuTree, getMenuFlat, createMenu, updateMenu, deleteMenu } from '@/api/rbac/menus'

const menuTree = ref([])
const flatMenus = ref([])
const dialogVisible = ref(false)
const dialogTitle = ref('')
const formRef = ref()
const form = reactive({
  id: null, name: '', label: '', path: '', icon: '',
  parent_id: null, sort_order: 0, type: 'menu', visible: 'show', status: 'enabled'
})

async function loadTree() {
  const res = await getMenuTree()
  menuTree.value = res.data
}

async function loadFlat() {
  const res = await getMenuFlat()
  flatMenus.value = res.data
}

function handleAdd(parent) {
  Object.assign(form, { id: null, name: '', label: '', path: '', icon: '', parent_id: parent?.id ?? null, sort_order: 0, type: 'menu', visible: 'show', status: 'enabled' })
  dialogTitle.value = parent ? `新增子菜单「${parent.label}」` : '新增顶级菜单'
  dialogVisible.value = true
}

function handleEdit(row) {
  Object.assign(form, { id: row.id, name: row.name, label: row.label, path: row.path, icon: row.icon || '', parent_id: row.parent_id, sort_order: row.sort_order, type: row.type, visible: row.visible, status: row.status })
  dialogTitle.value = `编辑菜单「${row.label}」`
  dialogVisible.value = true
}

async function handleSubmit() {
  try {
    await formRef.value.validate()
    const payload = { ...form }
    if (form.id) {
      await updateMenu(form.id, payload)
    } else {
      await createMenu(payload)
    }
    dialogVisible.value = false
    loadTree()
    loadFlat()
  } catch (e) { /* 验证失败 */ }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(`确定删除菜单「${row.label}」及其所有子菜单？`, '确认')
    await deleteMenu(row.id)
    loadTree()
    loadFlat()
  } catch (e) { /* 取消 */ }
}

onMounted(() => { loadTree(); loadFlat() })
</script>
