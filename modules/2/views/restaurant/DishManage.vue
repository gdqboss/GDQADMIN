<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const categories = ref([])
const dishes = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const editDish = ref(null)
const form = ref({ name: '', category_id: null, unit: '份', price: 0, image: '', description: '', is_available: 'yes' })
const catDialogVisible = ref(false)
const catForm = ref({ name: '', sort_order: 0 })

async function fetchCategories() {
  try {
    const res = await api.get('/restaurant/categories')
    categories.value = res.data || []
  } catch (e) { ElMessage.error(e.message || '获取分类失败') }
}

async function fetchDishes() {
  loading.value = true
  try {
    const res = await api.get('/restaurant/dishes')
    dishes.value = res.data || []
  } catch (e) { ElMessage.error(e.message || '获取菜品失败') }
  finally { loading.value = false }
}

async function saveCat() {
  try {
    await api.post('/restaurant/categories', catForm.value)
    ElMessage.success('添加成功')
    catDialogVisible.value = false
    catForm.value = { name: '', sort_order: 0 }
    fetchCategories()
  } catch (e) { ElMessage.error(e.message || '操作失败') }
}

function openAdd() {
  editDish.value = null
  form.value = { name: '', category_id: null, unit: '份', price: 0, image: '', description: '', is_available: 'yes' }
  dialogVisible.value = true
}

function openEdit(row) {
  editDish.value = row
  form.value = { name: row.name, category_id: row.category_id, unit: row.unit, price: row.price, image: row.image, description: row.description, is_available: row.is_available }
  dialogVisible.value = true
}

async function saveDish() {
  try {
    if (editDish.value) {
      await api.put(`/restaurant/dishes/${editDish.value.id}`, form.value)
    } else {
      await api.post('/restaurant/dishes', form.value)
    }
    ElMessage.success('保存成功')
    dialogVisible.value = false
    fetchDishes()
  } catch (e) { ElMessage.error(e.message || '操作失败') }
}

async function deleteDish(row) {
  if (!confirm(`确定删除菜品 ${row.name}？`)) return
  try {
    await api.delete(`/restaurant/dishes/${row.id}`)
    ElMessage.success('删除成功')
    fetchDishes()
  } catch (e) { ElMessage.error(e.message || '删除失败') }
}

onMounted(() => { fetchCategories(); fetchDishes() })
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">菜品管理</h1>
      <div class="flex gap-2">
        <el-button @click="catDialogVisible = true">新增分类</el-button>
        <el-button type="primary" @click="openAdd">新增菜品</el-button>
      </div>
    </div>

    <!-- Categories -->
    <div class="flex gap-2 mb-4 flex-wrap">
      <el-tag v-for="c in categories" :key="c.id" type="info" class="cursor-pointer">{{ c.name }}</el-tag>
    </div>

    <!-- Dishes table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table v-loading="loading" :data="dishes" stripe>
        <el-table-column label="菜品名称" prop="name" min-width="120" />
        <el-table-column label="分类" prop="category_name" width="100" />
        <el-table-column label="单位" prop="unit" width="60" />
        <el-table-column label="价格" width="100" align="right">
          <template #default="{ row }"><span class="text-orange-500 font-medium">S$ {{ row.price }}</span></template>
        </el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.is_available === 'yes' ? 'success' : 'info'" size="small">
              {{ row.is_available === 'yes' ? '可售' : '停售' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button size="small" link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button size="small" link type="danger" @click="deleteDish(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- Dish dialog -->
    <el-dialog v-model="dialogVisible" :title="editDish ? '编辑菜品' : '新增菜品'" width="500">
      <el-form :model="form" label-width="80">
        <el-form-item label="菜品名称"><el-input v-model="form.name" /></el-form-item>
        <el-form-item label="分类">
          <el-select v-model="form.category_id" clearable>
            <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="单位"><el-input v-model="form.unit" /></el-form-item>
        <el-form-item label="价格"><el-input-number v-model="form.price" :min="0" :precision="2" /></el-form-item>
        <el-form-item label="是否可售">
          <el-radio-group v-model="form.is_available">
            <el-radio value="yes">可售</el-radio>
            <el-radio value="no">停售</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="备注"><el-input v-model="form.description" type="textarea" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="saveDish">保存</el-button>
      </template>
    </el-dialog>

    <!-- Category dialog -->
    <el-dialog v-model="catDialogVisible" title="新增分类" width="350">
      <el-form :model="catForm" label-width="70">
        <el-form-item label="分类名称"><el-input v-model="catForm.name" /></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="catForm.sort_order" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="catDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="saveCat">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>