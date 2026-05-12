<template>
  <div class="module-page module-sales-returns">
    <!-- Header -->
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header">
          <span class="card-header-title">退货记录管理</span>
          <div class="card-header-actions">
            <el-button type="primary" @click="handleAdd">新增退货</el-button>
            <el-button @click="exportData">导出</el-button>
          </div>
        </div>
      </template>
    </el-card>

    <!-- Search Form -->
    <el-card class="search-card" shadow="never">
      <el-form :inline="true" :model="searchForm" ref="searchFormRef">
        <el-form-item label="退货单号">
          <el-input v-model="searchForm.returnNo" placeholder="请输入退货单号" clearable />
        </el-form-item>
        <el-form-item label="原订单号">
          <el-input v-model="searchForm.orderNo" placeholder="请输入原订单号" clearable />
        </el-form-item>
        <el-form-item label="客户名称">
          <el-input v-model="searchForm.customerName" placeholder="请输入客户名称" clearable />
        </el-form-item>
        <el-form-item label="日期范围">
          <el-date-picker
            v-model="searchForm.dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
          />
        </el-form-item>
        <el-form-item label="处理状态">
          <el-select v-model="searchForm.status" placeholder="请选择状态" clearable>
            <el-option label="全部" value="" />
            <el-option label="待处理" value="pending" />
            <el-option label="处理中" value="processing" />
            <el-option label="已完成" value="completed" />
            <el-option label="已拒绝" value="rejected" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Table -->
    <el-card class="table-card" shadow="never">
      <el-table :data="list" v-loading="loading" stripe border @selection-change="handleSelectionChange">
        <el-table-column type="selection" width="55" />
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="returnNo" label="退货单号" width="160" />
        <el-table-column prop="orderNo" label="原订单号" width="160" />
        <el-table-column prop="customerName" label="客户名称" min-width="120" />
        <el-table-column prop="productName" label="商品名称" min-width="150" />
        <el-table-column prop="quantity" label="退货数量" width="100" align="center" />
        <el-table-column prop="returnAmount" label="退货金额" width="120" align="right">
          <template #default="{ row }">
            <span class="text-danger">{{ formatMoney(row.returnAmount) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="reason" label="退货原因" min-width="150" show-overflow-tooltip />
        <el-table-column prop="status" label="处理状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="handler" label="处理人" width="100" />
        <el-table-column prop="createdAt" label="申请时间" width="160" />
        <el-table-column prop="processedAt" label="处理时间" width="160" />
        <el-table-column label="操作" width="150" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
        :current-page="currentPage"
        :page-sizes="[10, 20, 50, 100]"
        :page-size="pageSize"
        layout="total, sizes, prev, pager, next, jumper"
        :total="total"
        style="margin-top: 15px;"
      />
    </el-card>

    <!-- Add/Edit Dialog -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑退货' : '新增退货'" width="600px">
      <el-form ref="formRef" :model="formData" :rules="rules" label-width="100px">
        <el-form-item label="退货单号" prop="returnNo">
          <el-input v-model="formData.returnNo" placeholder="请输入退货单号" :disabled="isEdit" />
        </el-form-item>
        <el-form-item label="原订单号" prop="orderNo">
          <el-input v-model="formData.orderNo" placeholder="请输入原订单号" />
        </el-form-item>
        <el-form-item label="客户名称" prop="customerName">
          <el-input v-model="formData.customerName" placeholder="请输入客户名称" />
        </el-form-item>
        <el-form-item label="商品名称" prop="productName">
          <el-input v-model="formData.productName" placeholder="请输入商品名称" />
        </el-form-item>
        <el-form-item label="退货数量" prop="quantity">
          <el-input-number v-model="formData.quantity" :min="1" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="退货金额" prop="returnAmount">
          <el-input-number v-model="formData.returnAmount" :min="0" :precision="2" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="退货原因" prop="reason">
          <el-select v-model="formData.reason" placeholder="请选择退货原因" style="width: 100%;">
            <el-option label="商品质量问题" value="quality_issue" />
            <el-option label="商品与描述不符" value="mismatch" />
            <el-option label="错拍/多拍" value="wrong_order" />
            <el-option label="七天无理由" value="七天无理由" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item label="处理状态" prop="status">
          <el-select v-model="formData.status" placeholder="请选择处理状态" style="width: 100%;">
            <el-option label="待处理" value="pending" />
            <el-option label="处理中" value="processing" />
            <el-option label="已完成" value="completed" />
            <el-option label="已拒绝" value="rejected" />
          </el-select>
        </el-form-item>
        <el-form-item label="处理人" prop="handler">
          <el-input v-model="formData.handler" placeholder="请输入处理人" />
        </el-form-item>
        <el-form-item label="详细说明" prop="remark">
          <el-input v-model="formData.remark" type="textarea" rows="3" placeholder="请输入详细说明" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="dialogSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getReturns, createReturn } from '@/api/finance'

const list = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const dialogVisible = ref(false)
const isEdit = ref(false)

const searchForm = reactive({
  returnNo: '',
  orderNo: '',
  customerName: '',
  dateRange: null,
  status: ''
})

const formData = reactive({
  id: null,
  returnNo: '',
  orderNo: '',
  customerName: '',
  productName: '',
  quantity: 1,
  returnAmount: 0,
  reason: '',
  status: 'pending',
  handler: '',
  remark: ''
})

const rules = {
  returnNo: [{ required: true, message: '请输入退货单号', trigger: 'blur' }],
  orderNo: [{ required: true, message: '请输入原订单号', trigger: 'blur' }],
  customerName: [{ required: true, message: '请输入客户名称', trigger: 'blur' }],
  returnAmount: [{ required: true, message: '请输入退货金额', trigger: 'blur' }]
}

const formatMoney = (amount) => {
  return new Intl.NumberFormat('zh-CN', { style: 'currency', currency: 'CNY' }).format(amount || 0)
}

const getStatusType = (status) => {
  const types = {
    pending: 'warning',
    processing: 'info',
    completed: 'success',
    rejected: 'danger'
  }
  return types[status] || 'info'
}

const getStatusText = (status) => {
  const texts = {
    pending: '待处理',
    processing: '处理中',
    completed: '已完成',
    rejected: '已拒绝'
  }
  return texts[status] || status
}

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
      returnNo: searchForm.returnNo,
      orderNo: searchForm.orderNo,
      customerName: searchForm.customerName,
      status: searchForm.status
    }
    if (searchForm.dateRange && searchForm.dateRange.length === 2) {
      params.startDate = searchForm.dateRange[0]
      params.endDate = searchForm.dateRange[1]
    }
    const res = await getReturns(params)
    list.value = Array.isArray(res) ? res : []
    total.value = res.length || 0
  } catch (error) {
    console.error('加载数据失败:', error)
    ElMessage.error('加载退货记录数据失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  loadData()
}

const resetSearch = () => {
  searchForm.returnNo = ''
  searchForm.orderNo = ''
  searchForm.customerName = ''
  searchForm.dateRange = null
  searchForm.status = ''
  handleSearch()
}

const handleSizeChange = (val) => {
  pageSize.value = val
  loadData()
}

const handleCurrentChange = (val) => {
  currentPage.value = val
  loadData()
}

const handleSelectionChange = (selection) => {
  console.log('选中的行:', selection)
}

const handleAdd = () => {
  isEdit.value = false
  const today = new Date().toISOString().split('T')[0]
  Object.assign(formData, {
    id: null,
    returnNo: 'R' + Date.now(),
    orderNo: '',
    customerName: '',
    productName: '',
    quantity: 1,
    returnAmount: 0,
    reason: '',
    status: 'pending',
    handler: '',
    remark: ''
  })
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  Object.assign(formData, row)
  dialogVisible.value = true
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定要删除退货记录「${row.returnNo}」吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    // deleteReturn API would be called here
    ElMessage.success('删除成功')
    loadData()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('删除失败')
    }
  }
}

const dialogSubmit = async () => {
  try {
    if (isEdit.value) {
      // updateReturn API would be called here
      ElMessage.success('编辑成功')
    } else {
      await createReturn(formData)
      ElMessage.success('新增成功')
    }
    dialogVisible.value = false
    loadData()
  } catch (error) {
    ElMessage.error(isEdit.value ? '编辑失败' : '新增失败')
  }
}

const exportData = () => {
  ElMessage.info('导出功能开发中')
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.module-page {
  padding: 20px;
}
.header-card {
  margin-bottom: 20px;
}
.search-card {
  margin-bottom: 20px;
}
.table-card {
  margin-top: 10px;
}
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.card-header-title {
  font-size: 16px;
  font-weight: 600;
}
.card-header-actions {
  display: flex;
  gap: 10px;
}
.text-danger {
  color: #f56c6c;
}
</style>
