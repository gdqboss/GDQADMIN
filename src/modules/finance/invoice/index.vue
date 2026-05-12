<template>
  <div class="module-page module-finance-invoice">
    <el-card class="search-card" shadow="never">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="发票号码">
          <el-input v-model="searchForm.invoiceNo" placeholder="请输入发票号码" clearable />
        </el-form-item>
        <el-form-item label="客户名称">
          <el-input v-model="searchForm.customerName" placeholder="请输入客户名称" clearable />
        </el-form-item>
        <el-form-item label="日期范围">
          <el-date-picker v-model="searchForm.dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="请选择状态" clearable>
            <el-option label="待开票" value="pending" />
            <el-option label="已开票" value="issued" />
            <el-option label="已作废" value="voided" />
            <el-option label="已红冲" value="red" />
          </el-select>
        </el-form-item>
        <el-form-item label="发票类型">
          <el-select v-model="searchForm.type" placeholder="请选择类型" clearable>
            <el-option label="增值税专用发票" value="special" />
            <el-option label="增值税普通发票" value="normal" />
            <el-option label="电子发票" value="electronic" />
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
          <el-button type="primary" @click="handleAdd">新增发票</el-button>
          <el-button @click="batchExport">导出</el-button>
        </div>
      </div>

      <el-table :data="list" v-loading="loading" style="width: 100%" border @selection-change="handleSelectionChange">
        <el-table-column type="selection" width="55" />
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="invoiceNo" label="发票号码" width="180" />
        <el-table-column prop="customerName" label="客户名称" min-width="150" />
        <el-table-column prop="type" label="发票类型" width="130">
          <template #default="{ row }">
            <el-tag size="small">{{ getTypeText(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="amount" label="发票金额" width="130" align="right">
          <template #default="{ row }">
            {{ formatMoney(row.amount) }}
          </template>
        </el-table-column>
        <el-table-column prop="taxAmount" label="税额" width="100" align="right">
          <template #default="{ row }">
            {{ formatMoney(row.taxAmount) }}
          </template>
        </el-table-column>
        <el-table-column prop="invoiceDate" label="开票日期" width="120" />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="250" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleView(row)">查看</el-button>
            <el-button link type="success" @click="handleVerify(row)" v-if="row.status === 'pending'">核准</el-button>
            <el-button link type="warning" @click="handleVoid(row)" v-if="row.status === 'issued'">作废</el-button>
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
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

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑发票' : '新增发票'" width="600px">
      <el-form ref="formRef" :model="formData" :rules="rules" label-width="110px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="客户名称" prop="customerName">
              <el-input v-model="formData.customerName" placeholder="请输入客户名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="发票号码" prop="invoiceNo">
              <el-input v-model="formData.invoiceNo" placeholder="请输入发票号码" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="发票类型" prop="type">
              <el-select v-model="formData.type" style="width: 100%;">
                <el-option label="增值税专用发票" value="special" />
                <el-option label="增值税普通发票" value="normal" />
                <el-option label="电子发票" value="electronic" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开票日期" prop="invoiceDate">
              <el-date-picker v-model="formData.invoiceDate" type="date" value-format="YYYY-MM-DD" style="width: 100%;" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="金额" prop="amount">
              <el-input-number v-model="formData.amount" :min="0" :precision="2" style="width: 100%;" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="税率" prop="taxRate">
              <el-input-number v-model="formData.taxRate" :min="0" :max="100" :precision="2" style="width: 100%;" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="formData.remark" type="textarea" rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="dialogSubmit">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="viewDialogVisible" title="发票详情" width="600px">
      <el-descriptions :column="2" border>
        <el-descriptions-item label="发票号码">{{ viewData.invoiceNo }}</el-descriptions-item>
        <el-descriptions-item label="客户名称">{{ viewData.customerName }}</el-descriptions-item>
        <el-descriptions-item label="发票类型">{{ getTypeText(viewData.type) }}</el-descriptions-item>
        <el-descriptions-item label="开票日期">{{ viewData.invoiceDate }}</el-descriptions-item>
        <el-descriptions-item label="金额">{{ formatMoney(viewData.amount) }}</el-descriptions-item>
        <el-descriptions-item label="税额">{{ formatMoney(viewData.taxAmount) }}</el-descriptions-item>
        <el-descriptions-item label="价税合计">{{ formatMoney(viewData.totalAmount) }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="getStatusType(viewData.status)">{{ getStatusText(viewData.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="备注" :span="2">{{ viewData.remark }}</el-descriptions-item>
      </el-descriptions>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getInvoices, createInvoice, updateInvoice, verifyInvoice, voidInvoice, deleteInvoice } from '@/api/finance'

const list = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const dialogVisible = ref(false)
const viewDialogVisible = ref(false)
const isEdit = ref(false)

const searchForm = reactive({
  invoiceNo: '',
  customerName: '',
  dateRange: null,
  status: '',
  type: ''
})

const formData = reactive({
  id: null,
  customerName: '',
  invoiceNo: '',
  type: 'normal',
  amount: 0,
  taxRate: 13,
  taxAmount: 0,
  totalAmount: 0,
  invoiceDate: '',
  remark: ''
})

const viewData = reactive({
  invoiceNo: '',
  customerName: '',
  type: '',
  invoiceDate: '',
  amount: 0,
  taxAmount: 0,
  totalAmount: 0,
  status: '',
  remark: ''
})

const rules = {
  customerName: [{ required: true, message: '请输入客户名称', trigger: 'blur' }],
  invoiceNo: [{ required: true, message: '请输入发票号码', trigger: 'blur' }],
  amount: [{ required: true, message: '请输入金额', trigger: 'blur' }]
}

const formatMoney = (amount) => {
  return new Intl.NumberFormat('zh-CN', { style: 'currency', currency: 'CNY' }).format(amount || 0)
}

const getTypeText = (type) => {
  const texts = { special: '增值税专用发票', normal: '增值税普通发票', electronic: '电子发票' }
  return texts[type] || type
}

const getStatusType = (status) => {
  const types = { pending: 'warning', issued: 'success', voided: 'info', red: 'danger' }
  return types[status] || 'info'
}

const getStatusText = (status) => {
  const texts = { pending: '待开票', issued: '已开票', voided: '已作废', red: '已红冲' }
  return texts[status] || status
}

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
      invoiceNo: searchForm.invoiceNo,
      customerName: searchForm.customerName,
      status: searchForm.status,
      type: searchForm.type
    }
    if (searchForm.dateRange && searchForm.dateRange.length === 2) {
      params.startDate = searchForm.dateRange[0]
      params.endDate = searchForm.dateRange[1]
    }
    const res = await getInvoices(params)
    list.value = Array.isArray(res) ? res : []
    total.value = res.length || 0
  } catch (error) {
    console.error('加载数据失败:', error)
    ElMessage.error('加载发票数据失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  loadData()
}

const resetSearch = () => {
  searchForm.invoiceNo = ''
  searchForm.customerName = ''
  searchForm.dateRange = null
  searchForm.status = ''
  searchForm.type = ''
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
  Object.assign(formData, {
    id: null, customerName: '', invoiceNo: '', type: 'normal', amount: 0, taxRate: 13, taxAmount: 0, totalAmount: 0, invoiceDate: '', remark: ''
  })
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  Object.assign(formData, row)
  dialogVisible.value = true
}

const handleView = (row) => {
  Object.assign(viewData, row)
  viewDialogVisible.value = true
}

const handleVerify = async (row) => {
  await ElMessageBox.confirm('确定要核准这张发票吗?', '提示', { type: 'success' })
  await verifyInvoice(row.id)
  ElMessage.success('核准成功')
  loadData()
}

const handleVoid = async (row) => {
  await ElMessageBox.confirm('确定要作废这张发票吗?', '提示', { type: 'warning' })
  await voidInvoice(row.id)
  ElMessage.success('作废成功')
  loadData()
}

const handleDelete = async (row) => {
  await ElMessageBox.confirm('确定要删除这张发票吗?', '提示', { type: 'warning' })
  await deleteInvoice(row.id)
  ElMessage.success('删除成功')
  loadData()
}

const dialogSubmit = async () => {
  // Calculate tax
  formData.taxAmount = formData.amount * (formData.taxRate / 100)
  formData.totalAmount = formData.amount + formData.taxAmount

  if (isEdit.value) {
    await updateInvoice(formData.id, formData)
    ElMessage.success('编辑成功')
  } else {
    await createInvoice(formData)
    ElMessage.success('新增成功')
  }
  dialogVisible.value = false
  loadData()
}

const batchExport = () => {
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
</style>
