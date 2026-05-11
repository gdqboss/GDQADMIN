<template>
  <div class="product-list">
    <div class="toolbar">
      <div class="search-area">
        <el-input v-model="filters.keyword"           placeholder="搜索SKU、名称"
          clearable
          style="width: 200px"
          @keyup.enter="handleSearch"
        />
        <el-select v-model="filters.status"           placeholder="状态"
          clearable
          style="width: 120px"
          @change="handleSearch"
        >
          <el-option label="启用" value="active" />
          <el-option label="停售" value="discontinued" />
        </el-select>
        <el-button type="primary" @click="handleSearch">搜索</el-button>
      </div>
      <div class="action-area">
        <el-button type="primary" @click="handleAdd">+ 新增商品</el-button>
        <el-button
          type="danger"
          :disabled="!selectedRows.length"
          @click="handleBatchDelete"
        >
          批量删除
        </el-button>
      </div>
    </div>

    <el-table
      v-loading="loading"
      :data="tableData"
      @selection-change="handleSelectionChange"
      stripe
      style="width: 100%"
    >
      <el-table-column type="selection" width="55" />
      <el-table-column prop="sku" label="SKU" width="160" />
      <el-table-column prop="name" label="名称" min-width="180" show-overflow-tooltip />
      <el-table-column prop="category_name" label="分类" width="120" />
      <el-table-column prop="unit" label="单位" width="80" />
      <el-table-column prop="supplier_name" label="供应商" width="120" />
      <el-table-column prop="stock" label="库存" width="80" align="right" />
      <el-table-column prop="sale_price" label="¥售价" width="90" align="right">
        <template #default="{ row }">{{ row.sale_price != null ? '¥' + Number(row.sale_price).toFixed(2) : '-' }}</template>
      </el-table-column>
      <el-table-column prop="sale_price_usd" label="$售价" width="90" align="right">
        <template #default="{ row }">{{ row.sale_price_usd != null ? '$' + Number(row.sale_price_usd).toFixed(2) : '-' }}</template>
      </el-table-column>
      <el-table-column prop="sale_price_php" label="₱售价" width="90" align="right">
        <template #default="{ row }">{{ row.sale_price_php != null ? '₱' + Number(row.sale_price_php).toFixed(2) : '-' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
            {{ row.status === 'active' ? '启用' : '停售' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button size="small" type="primary" link @click="handleEdit(row)">编辑</el-button>
          <el-button size="small" type="primary" link @click="handleSkus(row)">SKU</el-button>
          <el-button size="small" type="danger" link @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination">
      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.pageSize"
        :page-sizes="[10, 20, 50, 100]"
        :total="pagination.total"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadData"
        @current-change="loadData"
      />
    </div>

    <product-edit v-model="editVisible"       :product="currentProduct"
      @success="loadData"
    />

    <sku-manager v-model="skuManagerVisible"       :product-id="currentProductId"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getProductList, deleteProduct, batchDeleteProducts } from '../../../api/products.js'
import ProductEdit from './ProductEdit.vue'
import SkuManager from './SkuManager.vue'

const loading = ref(false)
const tableData = ref([])
const selectedRows = ref([])

const filters = reactive({
  keyword: '',
  status: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

const editVisible = ref(false)
const skuManagerVisible = ref(false)
const currentProduct = ref(null)
const currentProductId = ref(null)

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.page,
      page_size: pagination.pageSize,
      keyword: filters.keyword || undefined,
      status: filters.status || undefined
    }
    const { data } = await getProductList(params)
    tableData.value = data.items || data.list || data
    pagination.total = data.total || 0
  } catch (e) {
    console.error('Failed to load product list', e)
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  pagination.page = 1
  loadData()
}

const handleSelectionChange = (rows) => {
  selectedRows.value = rows
}

const handleAdd = () => {
  currentProduct.value = null
  editVisible.value = true
}

const handleEdit = (row) => {
  currentProduct.value = { ...row }
  editVisible.value = true
}

const handleSkus = (row) => {
  currentProductId.value = row.id
  skuManagerVisible.value = true
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定删除该商品？', '提示', {
      type: 'warning'
    })
    await deleteProduct(row.id)
    ElMessage.success('删除成功')
    loadData()
  } catch (e) {
    if (e !== 'cancel') {
      console.error('Delete failed', e)
    }
  }
}

const handleBatchDelete = async () => {
  const ids = selectedRows.value.map((r) => r.id)
  try {
    await ElMessageBox.confirm(`确定删除选中的 ${ids.length} 个商品？`, '提示', {
      type: 'warning'
    })
    await batchDeleteProducts(ids)
    ElMessage.success('批量删除成功')
    selectedRows.value = []
    loadData()
  } catch (e) {
    if (e !== 'cancel') {
      console.error('Batch delete failed', e)
    }
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.product-list {
  padding: 20px;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  margin-bottom: 16px;
}

.search-area {
  display: flex;
  gap: 10px;
  align-items: center;
}

.action-area {
  display: flex;
  gap: 10px;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>
