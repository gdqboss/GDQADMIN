<script setup>
import { ref, onMounted, computed } from "vue"
import PageHeader from "../../components/PageHeader.vue"
import api from "../../services/api.js"
import { ElMessage } from "element-plus"

const loading = ref(false)
const products = ref([])
const dialogVisible = ref(false)
const formLoading = ref(false)
const editingId = ref(null)
const categories = ref([])

const dialogTitle = computed(() => editingId.value ? '编辑商品' : '新增商品')

const form = ref({
  cid: 0, name: "", procode: "", pic: "", pics: [], detail: "",
  market_price: 0, sell_price: 0, leader_price: 0, cost_price: 0,
  weight: 0, stock: 0, sort: 0, status: 1, teamnum: 2, teamhour: 24,
})

function resetForm() {
  editingId.value = null
  form.value = { cid: 0, name: "", procode: "", pic: "", pics: [], detail: "",
    market_price: 0, sell_price: 0, leader_price: 0, cost_price: 0,
    weight: 0, stock: 0, sort: 0, status: 1, teamnum: 2, teamhour: 24 }
}

async function fetchProducts() {
  loading.value = true
  try {
    const res = await api.get("/collage/products")
    if (res.code === 0) products.value = res.data.list || []
  } catch (e) { ElMessage.error(e.message) }
  finally { loading.value = false }
}

async function fetchCategories() {
  try {
    const res = await api.get("/collage/categories")
    if (res.code === 0) categories.value = res.data || []
  } catch (e) {}
}

function openAdd() { resetForm(); dialogVisible.value = true }
function openEdit(row) {
  editingId.value = row.id
  form.value = { cid: row.cid, name: row.name, procode: row.procode || "",
    pic: row.pic || "", detail: row.detail || "",
    market_price: row.market_price, sell_price: row.sell_price,
    leader_price: row.leader_price, cost_price: row.cost_price,
    weight: row.weight, stock: row.stock, sort: row.sort, status: row.status,
    teamnum: row.teamnum || 2, teamhour: row.teamhour || 24, pics: row.pics || [] }
  dialogVisible.value = true
}

async function submitForm() {
  if (!form.value.name) { ElMessage.warning("请输入商品名称"); return }
  formLoading.value = true
  try {
    const payload = { ...form.value }
    const res = editingId.value
      ? await api.put(`/collage/products/${editingId.value}`, payload)
      : await api.post("/collage/products", payload)
    if (res.code === 0) {
      ElMessage.success(editingId.value ? "修改成功" : "添加成功")
      dialogVisible.value = false
      fetchProducts()
    } else { ElMessage.error(res.message || "操作失败") }
  } catch (e) { ElMessage.error(e.message) }
  finally { formLoading.value = false }
}

async function deleteProduct(row) {
  if (!confirm(`确定删除商品 "${row.name}"？`)) return
  try {
    const res = await api.delete(`/collage/products/${row.id}`)
    if (res.code === 0) { ElMessage.success("删除成功"); fetchProducts() }
    else ElMessage.error(res.message)
  } catch (e) { ElMessage.error(e.message) }
}

onMounted(() => { fetchProducts(); fetchCategories() })
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="拼团商品" subtitle="管理拼团商品" />
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4 flex justify-end">
      <el-button type="primary" @click="openAdd">新增商品</el-button>
    </div>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table :data="products" stripe v-loading="loading">
        <el-table-column label="商品名称" prop="name" min-width="160" />
        <el-table-column label="分类" prop="catname" width="100" />
        <el-table-column label="市场价" width="90" align="right">
          <template #default="{ row }"><span class="text-gray-400">¥ {{ row.market_price }}</span></template>
        </el-table-column>
        <el-table-column label="拼团价" width="90" align="right">
          <template #default="{ row }"><span class="text-blue-600 font-medium">¥ {{ row.sell_price }}</span></template>
        </el-table-column>
        <el-table-column label="团长价" width="90" align="right">
          <template #default="{ row }"><span class="text-green-600">¥ {{ row.leader_price }}</span></template>
        </el-table-column>
        <el-table-column label="库存" prop="stock" width="70" align="center" />
        <el-table-column label="状态" width="70">
          <template #default="{ row }">
            <el-tag :type="row.status == 1 ? 'success' : 'info'" size="small">{{ row.status == 1 ? "上架" : "下架" }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button size="small" link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button size="small" link type="danger" @click="deleteProduct(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="700px">
      <el-form :model="form" label-width="80px">
        <el-form-item label="商品名称"><el-input v-model="form.name" /></el-form-item>
        <div class="grid grid-cols-2 gap-4">
          <el-form-item label="拼团价"><el-input-number v-model="form.sell_price" :min="0" :precision="2" /></el-form-item>
          <el-form-item label="团长价"><el-input-number v-model="form.leader_price" :min="0" :precision="2" /></el-form-item>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <el-form-item label="市场价"><el-input-number v-model="form.market_price" :min="0" :precision="2" /></el-form-item>
          <el-form-item label="库存"><el-input-number v-model="form.stock" :min="0" /></el-form-item>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <el-form-item label="拼团人数"><el-input-number v-model="form.teamnum" :min="2" :max="20" /></el-form-item>
          <el-form-item label="成团时限"><el-input-number v-model="form.teamhour" :min="1" :max="72" /> 小时</el-form-item>
        </div>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio :label="1">上架</el-radio><el-radio :label="0">下架</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="formLoading" @click="submitForm">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>
