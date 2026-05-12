<template>
  <div class="module-page module-finance-payable">
    <el-card class="search-card" shadow="never">
      <el-form :inline="true" :model="searchForm" ref="searchFormRef">
        <el-form-item label="供应商名称">
          <el-input v-model="searchForm.supplierName" placeholder="请输入供应商名称" clearable />
        </el-form-item>
        <el-form-item label="日期范围">
          <el-date-picker v-model="searchForm.dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="请选择状态" clearable>
            <el-option label="未付款" value="unpaid" />
            <el-option label="部分付款" value="partial" />
            <el-option label="已付款" value="paid" />
            <el-option label="逾期" value="overdue" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="never">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <div>
          <el-button type="primary" @click="handleAdd">新增应付</el-button>
          <el-button @click="exportData">导出</el-button>
        </div>
      </div>

      <el-table :data="list" v-loading="loading" style="width: 100%" border @selection-change="handleSelectionChange">
        <el-table-column type="selection" width="55" />
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="supplierName" label="供应商名称" min-width="150" />
        <el-table-column prop="orderNo" label="采购单号" width="150" />
        <el-table-column prop="amount" label="应付金额" width="130" align="right">
          <template #default="{ row }">
            {{ formatMoney(row.amount) }}
          </template>
        </el-table-column>
        <el-table-column prop="paidAmount" label="已付金额" width="130" align="right">
          <template #default="{ row }">
            {{ formatMoney(row.paidAmount) }}
          </template>
        </el-table-column>
        <el-table-column prop="balance" label="待付余额" width="130" align="right">
          <template #default="{ row }">
            <span class="text-warning">{{ formatMoney(row.balance) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="dueDate" label="到期日期" width="120" />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleRecord(row)">付款记录</el-button>
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
        :current-page="currentPage"
        :page-sizes="[10, 20, 50]"
        :page-size="pageSize"
        layout="total, sizes, prev, pager, next, jumper"
        :total="total"
        style="margin-top: 15px;"
      />
    </el-card>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑应付' : '新增应付'" width="500px">
      <el-form ref="formRef" :model="formData" :rules="rules" label-width="100px">
        <el-form-item label="供应商名称" prop="supplierName">
          <el-input v-model="formData.supplierName" placeholder="请输入供应商名称" />
        </el-form-item>
        <el-form-item label="采购单号" prop="orderNo">
          <el-input v-model="formData.orderNo" placeholder="请输入采购单号" />
        </el-form-item>
        <el-form-item label="应付金额" prop="amount">
          <el-input-number v-model="formData.amount" :min="0" :precision="2" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="已付金额" prop="paidAmount">
          <el-input-number v-model="formData.paidAmount" :min="0" :precision="2" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="到期日期" prop="dueDate">
          <el-date-picker v-model="formData.dueDate" type="date" value-format="YYYY-MM-DD" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="formData.remark" type="textarea" rows="3" />
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
import { getPurchaseCosts } from '@/api/finance'

const list = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const dialogVisible = ref(false)
const isEdit = ref(false)

const searchForm = reactive({
  supplierName: '',
  dateRange: null,
  status: ''
})

const formData = reactive({
  id: null,
  supplierName: '',
  orderNo: '',
  amount: 0,
  paidAmount: 0,
  dueDate: '',
  remark: ''
})

const rules = {
  supplierName: [{ required: true, message: '请输入供应商名称', trigger: 'blur' }],
  amount: [{ required: true, message: '请输入应付金额', trigger: 'blur' }]
}

const formatMoney = (amount) => {
  return new Intl.NumberFormat('zh-CN', { style: 'currency', currency: 'CNY' }).format(amount || 0)
}

const getStatusType = (status) => {
  const types = { unpaid: 'warning', partial: 'info', paid: 'success', overdue: 'danger' }
  return types[status] || 'info'
}

const getStatusText = (status) => {
  const texts = { unpaid: '未付款', partial: '部分付款', paid: '已付款', overdue: '逾期' }
  return texts[status] || status
}

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
      supplierName: searchForm.supplierName,
      status: searchForm.status
    }
    if (searchForm.dateRange && searchForm.dateRange.length === 2) {
      params.startDate = searchForm.dateRange[0]
      params.endDate = searchForm.dateRange[1]
    }
    const res = await getPurchaseCosts(params)
    list.value = Array.isArray(res) ? res : []
    total.value = res.length || 0
  } catch (error) {
    console.error('加载数据失败:', error)
    ElMessage.error('加载应付数据失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  loadData()
}

const resetSearch = () => {
  searchForm.supplierName = ''
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
  Object.assign(formData, { id: null, supplierName: '', orderNo: '', amount: 0, paidAmount: 0, dueDate: '', remark: '' })
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  Object.assign(formData, row)
  dialogVisible.value = true
}

const handleRecord = (row) => {
  ElMessage.info('付款记录功能开发中')
}

const dialogSubmit = async () => {
  ElMessage.success(isEdit.value ? '编辑成功' : '新增成功')
  dialogVisible.value = false
  loadData()
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
.search-card {
  margin-bottom: 20px;
}
.table-card {
  margin-top: 10px;
}
.text-warning {
  color: #e6a23c;
}
</style>
