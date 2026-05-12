<template>
  <div class="inventory-container">
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header">
          <span class="card-title">库存管理</span>
          <el-space>
            <el-button type="success" @click="handleStockIn">
              <el-icon><Plus /></el-icon>
              入库
            </el-button>
            <el-button type="warning" @click="handleStockOut">
              <el-icon><Minus /></el-icon>
              出库
            </el-button>
            <el-button type="primary" @click="handleAdd">
              <el-icon><Plus /></el-icon>
              新增商品
            </el-button>
          </el-space>
        </div>
      </template>
    </el-card>

    <el-card class="search-card" shadow="never">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="商品名称">
          <el-input v-model="searchForm.name" placeholder="请输入商品名称" clearable />
        </el-form-item>
        <el-form-item label="SKU编号">
          <el-input v-model="searchForm.sku" placeholder="请输入SKU编号" clearable />
        </el-form-item>
        <el-form-item label="库存状态">
          <el-select v-model="searchForm.stockStatus" placeholder="请选择" clearable>
            <el-option label="正常" value="normal" />
            <el-option label="不足" value="low" />
            <el-option label="超储" value="high" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="never">
      <el-table :data="list" v-loading="loading" stripe border @selection-change="handleSelectionChange">
        <el-table-column type="selection" width="55" />
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="商品名称" min-width="180">
          <template #default="{ row }">
            <div class="product-info">
              <span class="product-name">{{ row.name }}</span>
              <span class="product-sku">{{ row.sku }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="category" label="分类" width="120" />
        <el-table-column prop="stock" label="库存数量" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStockType(row.stock, row.minStock)" effect="plain">
              {{ row.stock }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="minStock" label="最低库存" width="100" align="center" />
        <el-table-column prop="unit" label="单位" width="80" align="center" />
        <el-table-column prop="costPrice" label="成本价" width="100" align="right">
          <template #default="{ row }">
            {{ formatMoney(row.costPrice) }}
          </template>
        </el-table-column>
        <el-table-column prop="supplier" label="供应商" min-width="150" show-overflow-tooltip />
        <el-table-column label="库存状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStockStatusType(row.stock, row.minStock)">
              {{ getStockStatusText(row.stock, row.minStock) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
            <el-button type="primary" link @click="handleStockRecord(row)">记录</el-button>
            <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :total="total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadData"
          @current-change="loadData"
        />
      </div>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑商品' : '新增商品'" width="600px" @close="handleDialogClose">
      <el-form ref="formRef" :model="formData" :rules="rules" label-width="100px">
        <el-form-item label="商品名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入商品名称" />
        </el-form-item>
        <el-form-item label="SKU编号" prop="sku">
          <el-input v-model="formData.sku" placeholder="请输入SKU编号" />
        </el-form-item>
        <el-form-item label="商品分类" prop="category">
          <el-select v-model="formData.category" placeholder="请选择分类">
            <el-option label="电子产品" value="电子产品" />
            <el-option label="服装鞋帽" value="服装鞋帽" />
            <el-option label="食品饮料" value="食品饮料" />
            <el-option label="家居用品" value="家居用品" />
            <el-option label="其他" value="其他" />
          </el-select>
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="库存数量" prop="stock">
              <el-input-number v-model="formData.stock" :min="0" style="width: 100%;" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="最低库存" prop="minStock">
              <el-input-number v-model="formData.minStock" :min="0" style="width: 100%;" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="成本价" prop="costPrice">
              <el-input-number v-model="formData.costPrice" :min="0" :precision="2" style="width: 100%;" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="单位" prop="unit">
              <el-input v-model="formData.unit" placeholder="如: 件/箱/个" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="供应商" prop="supplier">
          <el-input v-model="formData.supplier" placeholder="请输入供应商" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitLoading">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="stockDialogVisible" :title="stockDialogType === 'in' ? '入库' : '出库'" width="500px">
      <el-form :model="stockForm" label-width="100px">
        <el-form-item label="商品名称">
          <el-input v-model="stockForm.productName" disabled />
        </el-form-item>
        <el-form-item label="当前库存">
          <el-input v-model="stockForm.currentStock" disabled />
        </el-form-item>
        <el-form-item label="变更数量">
          <el-input-number v-model="stockForm.quantity" :min="1" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="stockForm.remark" type="textarea" :rows="3" placeholder="请输入备注" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="stockDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleStockSubmit">{{ stockDialogType === 'in' ? '确认入库' : '确认出库' }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="recordDialogVisible" title="库存记录" width="80%">
      <el-table :data="recordList" border stripe max-height="400">
        <el-table-column prop="type" label="类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.type === 'in' ? 'success' : 'warning'">{{ row.type === 'in' ? '入库' : '出库' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="quantity" label="数量" width="100" align="center" />
        <el-table-column prop="stockBefore" label="变更前库存" width="120" align="center" />
        <el-table-column prop="stockAfter" label="变更后库存" width="120" align="center" />
        <el-table-column prop="operator" label="操作人" width="120" />
        <el-table-column prop="remark" label="备注" min-width="150" />
        <el-table-column prop="createdAt" label="操作时间" width="180" />
      </el-table>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Minus } from '@element-plus/icons-vue'
import { getInventoryList, createInventory, updateInventory, deleteInventory } from '@/api/inventory'

const loading = ref(false)
const submitLoading = ref(false)
const list = ref([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(10)
const dialogVisible = ref(false)
const stockDialogVisible = ref(false)
const recordDialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)
const stockDialogType = ref('in')
const recordList = ref([])

const searchForm = reactive({
  name: '',
  sku: '',
  stockStatus: ''
})

const formData = reactive({
  id: null,
  name: '',
  sku: '',
  category: '',
  stock: 0,
  minStock: 10,
  unit: '件',
  costPrice: 0,
  supplier: ''
})

const stockForm = reactive({
  productName: '',
  currentStock: 0,
  quantity: 1,
  remark: ''
})

const rules = {
  name: [{ required: true, message: '请输入商品名称', trigger: 'blur' }],
  sku: [{ required: true, message: '请输入SKU编号', trigger: 'blur' }],
  category: [{ required: true, message: '请选择分类', trigger: 'change' }],
  stock: [{ required: true, message: '请输入库存数量', trigger: 'blur' }]
}

const formatMoney = (amount) => {
  return new Intl.NumberFormat('zh-CN', { style: 'currency', currency: 'CNY' }).format(amount || 0)
}

const getStockType = (stock, minStock) => {
  if (stock <= 0) return 'danger'
  if (stock < minStock) return 'warning'
  return 'success'
}

const getStockStatusType = (stock, minStock) => {
  if (stock <= 0) return 'danger'
  if (stock < minStock) return 'warning'
  if (stock > minStock * 3) return 'info'
  return 'success'
}

const getStockStatusText = (stock, minStock) => {
  if (stock <= 0) return '缺货'
  if (stock < minStock) return '不足'
  if (stock > minStock * 3) return '超储'
  return '正常'
}

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value
    }
    if (searchForm.name) params.name = searchForm.name
    if (searchForm.sku) params.sku = searchForm.sku
    if (searchForm.stockStatus) params.stockStatus = searchForm.stockStatus

    const res = await getInventoryList(params)
    list.value = res.data?.list || res.data || []
    total.value = res.data?.total || 0
  } catch (e) {
    ElMessage.error('获取库存列表失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  loadData()
}

const handleReset = () => {
  searchForm.name = ''
  searchForm.sku = ''
  searchForm.stockStatus = ''
  handleSearch()
}

const handleAdd = () => {
  isEdit.value = false
  formData.id = null
  formData.name = ''
  formData.sku = ''
  formData.category = ''
  formData.stock = 0
  formData.minStock = 10
  formData.unit = '件'
  formData.costPrice = 0
  formData.supplier = ''
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  formData.id = row.id
  formData.name = row.name
  formData.sku = row.sku
  formData.category = row.category
  formData.stock = row.stock
  formData.minStock = row.minStock
  formData.unit = row.unit
  formData.costPrice = row.costPrice
  formData.supplier = row.supplier
  dialogVisible.value = true
}

const handleDelete = (row) => {
  ElMessageBox.confirm('确定删除该商品吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    try {
      await deleteInventory(row.id)
      ElMessage.success('删除成功')
      loadData()
    } catch (e) {
      ElMessage.error('删除失败')
    }
  }).catch(() => {})
}

const handleStockIn = (row) => {
  // Handle batch stock in via selection
}

const handleStockOut = (row) => {
  // Handle batch stock out via selection
}

const handleStockRecord = (row) => {
  recordDialogVisible.value = true
  recordList.value = [
    { type: 'in', quantity: 100, stockBefore: 0, stockAfter: 100, operator: '张三', remark: '首批入库', createdAt: '2026-05-01 10:00:00' },
    { type: 'out', quantity: 20, stockBefore: 100, stockAfter: 80, operator: '李四', remark: '销售出库', createdAt: '2026-05-05 14:30:00' },
    { type: 'in', quantity: 50, stockBefore: 80, stockAfter: 130, operator: '王五', remark: '补货入库', createdAt: '2026-05-10 09:15:00' }
  ]
}

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate()

  submitLoading.value = true
  try {
    if (isEdit.value) {
      await updateInventory(formData.id, formData)
      ElMessage.success('更新成功')
    } else {
      await createInventory(formData)
      ElMessage.success('新增成功')
    }
    dialogVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(isEdit.value ? '更新失败' : '新增失败')
  } finally {
    submitLoading.value = false
  }
}

const handleStockSubmit = async () => {
  ElMessage.success(stockDialogType.value === 'in' ? '入库成功' : '出库成功')
  stockDialogVisible.value = false
  loadData()
}

const handleDialogClose = () => {
  formRef.value?.resetFields()
}

const handleSelectionChange = (val) => {
  console.log('Selected:', val)
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.inventory-container {
  padding: 20px;
}
.header-card {
  margin-bottom: 20px;
}
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.card-title {
  font-size: 18px;
  font-weight: 600;
}
.search-card {
  margin-bottom: 20px;
}
.table-card {
  margin-bottom: 20px;
}
.pagination-wrapper {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
.product-info {
  display: flex;
  flex-direction: column;
}
.product-name {
  font-weight: 500;
}
.product-sku {
  font-size: 12px;
  color: #909399;
}
</style>
