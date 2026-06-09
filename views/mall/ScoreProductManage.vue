<template>
  <div class="p-6">
    <PageHeader title="积分商品管理" subtitle="积分商城商品管理" />

    <!-- 操作栏 -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4 flex flex-wrap gap-4 items-end">
      <div>
        <label class="block text-xs text-gray-500 mb-1">状态筛选</label>
        <el-select v-model="filterStatus" placeholder="全部状态" clearable class="!w-36" @change="resetAndFetch">
          <el-option label="全部" value="" />
          <el-option label="上架" value="active" />
          <el-option label="下架" value="inactive" />
          <el-option label="售罄" value="sold_out" />
        </el-select>
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">关键词</label>
        <el-input v-model="keyword" placeholder="商品名称" clearable class="!w-48" @clear="resetAndFetch" @keyup.enter="resetAndFetch" />
      </div>
      <button @click="resetAndFetch" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">搜索</button>
      <button @click="showCreateDialog = true" class="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm hover:bg-amber-600 ml-auto">
        + 新增积分商品
      </button>
    </div>

    <!-- 表格 -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table :data="products" v-loading="loading" stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column label="商品" min-width="200">
          <template #default="{ row }">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-lg bg-gray-100 overflow-hidden flex-shrink-0">
                <img v-if="row.image_main" :src="'/' + row.image_main" class="w-full h-full object-cover" />
                <div v-else class="flex items-center justify-center h-full text-gray-300"><span class="material-symbols-outlined text-xl">card_giftcard</span></div>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-800">{{ row.name }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ row.category_name || '未分类' }}</p>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="积分价格" width="100">
          <template #default="{ row }">
            <span class="text-amber-600 font-bold">{{ row.score_price }}积分</span>
          </template>
        </el-table-column>
        <el-table-column prop="stock" label="库存" width="80" />
        <el-table-column prop="exchange_count" label="已兑换" width="80" />
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="is_recommend" label="推荐" width="80">
          <template #default="{ row }">
            <el-switch v-model="row.is_recommend" :active-value="1" :inactive-value="0" @change="toggleRecommend(row)" />
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="160">
          <template #default="{ row }">
            <span class="text-xs text-gray-500">{{ formatDate(row.created_at) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <button @click="editProduct(row)" class="text-blue-600 text-sm hover:underline mr-3">编辑</button>
            <button @click="deleteProduct(row)" class="text-red-500 text-sm hover:underline">删除</button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="p-4 flex justify-end">
        <el-pagination
          v-model:current-page="currentPage"
          :page-size="pageSize"
          :total="total"
          layout="total, prev, pager, next"
          @current-change="fetchProducts"
        />
      </div>
    </div>

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="showCreateDialog" :title="editingId ? '编辑积分商品' : '新增积分商品'" width="600px" :close-on-click-modal="false">
      <el-form :model="form" label-width="90px" class="grid grid-cols-2 gap-x-4">
        <el-form-item label="商品名称" class="col-span-2">
          <el-input v-model="form.name" placeholder="请输入商品名称" />
        </el-form-item>
        <el-form-item label="积分价格" required>
          <el-input-number v-model="form.score_price" :min="1" />
        </el-form-item>
        <el-form-item label="库存">
          <el-input-number v-model="form.stock" :min="0" />
        </el-form-item>
        <el-form-item label="安全库存">
          <el-input-number v-model="form.safe_stock" :min="0" />
        </el-form-item>
        <el-form-item label="分类">
          <el-select v-model="form.category_id" placeholder="选择分类" clearable class="!w-full">
            <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="主图">
          <el-input v-model="form.image_main" placeholder="图片路径，如 uploads/xxx.jpg" />
        </el-form-item>
        <el-form-item label="推荐" class="col-span-2">
          <el-switch v-model="form.is_recommend" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="form.status" class="!w-full">
            <el-option label="上架" value="active" />
            <el-option label="下架" value="inactive" />
          </el-select>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" />
        </el-form-item>
        <el-form-item label="商品描述" class="col-span-2">
          <el-input v-model="form.description" type="textarea" :rows="3" placeholder="商品描述" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="submitForm" :loading="submitting">{{ editingId ? '保存' : '创建' }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { ElMessage, ElMessageBox } from 'element-plus'

const products = ref([])
const categories = ref([])
const loading = ref(false)
const showCreateDialog = ref(false)
const submitting = ref(false)
const editingId = ref(null)
const filterStatus = ref('')
const keyword = ref('')
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)

const defaultForm = {
  name: '', description: '', image_main: '', score_price: 100, stock: 10,
  safe_stock: 2, category_id: null, is_recommend: 0, sort_order: 0, status: 'active'
}
const form = ref({ ...defaultForm })

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { hour12: false })
}
function statusType(s) {
  return { active: 'success', inactive: 'info', sold_out: 'danger' }[s] || 'info'
}
function statusLabel(s) {
  return { active: '上架', inactive: '下架', sold_out: '售罄' }[s] || s
}

async function fetchProducts() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value }
    if (filterStatus.value) params.status = filterStatus.value
    if (keyword.value) params.keyword = keyword.value
    const res = await api.get('/score-shop/admin/products', { params })
    if (res.code === 0) {
      products.value = res.data.list || []
      total.value = res.data.total
    }
  } finally { loading.value = false }
}

async function fetchCategories() {
  try {
    const res = await api.get('/score-shop/admin/categories')
    if (res.code === 0) categories.value = res.data || []
  } catch {}
}

async function toggleRecommend(row) {
  try {
    await api.put(`/score-shop/admin/products/${row.id}`, { is_recommend: row.is_recommend })
    ElMessage.success('更新成功')
  } catch (e) {
    ElMessage.error('更新失败')
    fetchProducts()
  }
}

function editProduct(row) {
  editingId.value = row.id
  form.value = { name: row.name, description: row.description, image_main: row.image_main,
    score_price: row.score_price, stock: row.stock, safe_stock: row.safe_stock,
    category_id: row.category_id, is_recommend: row.is_recommend,
    sort_order: row.sort_order, status: row.status }
  showCreateDialog.value = true
}

function resetForm() {
  editingId.value = null
  form.value = { ...defaultForm }
}

async function submitForm() {
  if (!form.value.name) return ElMessage.warning('商品名称必填')
  if (!form.value.score_price) return ElMessage.warning('积分价格必填')
  submitting.value = true
  try {
    const payload = { ...form.value }
    let res
    if (editingId.value) {
      res = await api.put(`/score-shop/admin/products/${editingId.value}`, payload)
    } else {
      res = await api.post('/score-shop/admin/products', payload)
    }
    if (res.code === 0) {
      ElMessage.success(editingId.value ? '更新成功' : '创建成功')
      showCreateDialog.value = false
      resetForm()
      fetchProducts()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  } finally { submitting.value = false }
}

async function deleteProduct(row) {
  try {
    await ElMessageBox.confirm(`确定删除积分商品「${row.name}」？`, '确认删除')
    const res = await api.delete(`/score-shop/admin/products/${row.id}`)
    if (res.code === 0) {
      ElMessage.success('删除成功')
      fetchProducts()
    }
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '删除失败')
  }
}

function resetAndFetch() {
  currentPage.value = 1
  fetchProducts()
}

onMounted(() => {
  fetchProducts()
  fetchCategories()
})
</script>
