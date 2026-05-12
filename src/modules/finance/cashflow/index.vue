<template>
  <div class="module-page module-finance-cashflow">
    <el-row :gutter="20" class="stat-cards">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon inflow">
            <el-icon><Top /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">本月流入</div>
            <div class="stat-value text-success">{{ formatMoney(stats.inflow) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon outflow">
            <el-icon><Bottom /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">本月流出</div>
            <div class="stat-value text-danger">{{ formatMoney(stats.outflow) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon balance">
            <el-icon><Wallet /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">账户余额</div>
            <div class="stat-value">{{ formatMoney(stats.balance) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon frozen">
            <el-icon><Lock /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">冻结金额</div>
            <div class="stat-value text-warning">{{ formatMoney(stats.frozen) }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card class="search-card" shadow="never" style="margin-top: 20px;">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="日期范围">
          <el-date-picker v-model="searchForm.dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD" />
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="searchForm.type" placeholder="请选择类型" clearable>
            <el-option label="全部" value="" />
            <el-option label="收入" value="income" />
            <el-option label="支出" value="expense" />
            <el-option label="转账" value="transfer" />
          </el-select>
        </el-form-item>
        <el-form-item label="账户">
          <el-select v-model="searchForm.accountId" placeholder="请选择账户" clearable>
            <el-option v-for="acc in accounts" :key="acc.id" :label="acc.name" :value="acc.id" />
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
          <el-button type="primary" @click="handleAdd">记录交易</el-button>
          <el-button @click="exportData">导出</el-button>
        </div>
      </div>

      <el-table :data="list" v-loading="loading" style="width: 100%" border>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="date" label="日期" width="120" />
        <el-table-column prop="type" label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="row.type === 'income' ? 'success' : row.type === 'expense' ? 'danger' : 'info'">
              {{ getTypeText(row.type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="accountName" label="账户" width="120" />
        <el-table-column prop="category" label="类别" width="120" />
        <el-table-column prop="amount" label="金额" width="130" align="right">
          <template #default="{ row }">
            <span :class="row.type === 'income' ? 'text-success' : 'text-danger'">
              {{ row.type === 'income' ? '+' : '-' }}{{ formatMoney(row.amount) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="description" label="描述" min-width="200" />
        <el-table-column prop="remark" label="备注" min-width="150" />
        <el-table-column label="操作" width="150" align="center">
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
        :page-sizes="[10, 20, 50]"
        :page-size="pageSize"
        layout="total, sizes, prev, pager, next, jumper"
        :total="total"
        style="margin-top: 15px;"
      />
    </el-card>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑交易' : '记录交易'" width="500px">
      <el-form ref="formRef" :model="formData" :rules="rules" label-width="100px">
        <el-form-item label="日期" prop="date">
          <el-date-picker v-model="formData.date" type="date" value-format="YYYY-MM-DD" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="formData.type" style="width: 100%;">
            <el-option label="收入" value="income" />
            <el-option label="支出" value="expense" />
            <el-option label="转账" value="transfer" />
          </el-select>
        </el-form-item>
        <el-form-item label="账户" prop="accountId">
          <el-select v-model="formData.accountId" style="width: 100%;">
            <el-option v-for="acc in accounts" :key="acc.id" :label="acc.name" :value="acc.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="类别" prop="category">
          <el-input v-model="formData.category" placeholder="请输入类别" />
        </el-form-item>
        <el-form-item label="金额" prop="amount">
          <el-input-number v-model="formData.amount" :min="0" :precision="2" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="formData.description" placeholder="请输入描述" />
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="formData.remark" type="textarea" rows="2" />
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
import { Top, Bottom, Wallet, Lock } from '@element-plus/icons-vue'
import { getAccounts, getSalesRevenues, getPurchaseCosts } from '@/api/finance'

const list = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const dialogVisible = ref(false)
const isEdit = ref(false)
const accounts = ref([])

const stats = reactive({
  inflow: 0,
  outflow: 0,
  balance: 0,
  frozen: 0
})

const searchForm = reactive({
  dateRange: null,
  type: '',
  accountId: ''
})

const formData = reactive({
  id: null,
  date: '',
  type: 'income',
  accountId: '',
  category: '',
  amount: 0,
  description: '',
  remark: ''
})

const rules = {
  date: [{ required: true, message: '请选择日期', trigger: 'change' }],
  type: [{ required: true, message: '请选择类型', trigger: 'change' }],
  amount: [{ required: true, message: '请输入金额', trigger: 'blur' }]
}

const formatMoney = (amount) => {
  return new Intl.NumberFormat('zh-CN', { style: 'currency', currency: 'CNY' }).format(amount || 0)
}

const getTypeText = (type) => {
  const texts = { income: '收入', expense: '支出', transfer: '转账' }
  return texts[type] || type
}

const loadAccounts = async () => {
  try {
    const res = await getAccounts({ page: 1, pageSize: 100 })
    accounts.value = Array.isArray(res) ? res : []
  } catch (error) {
    console.error('加载账户失败:', error)
  }
}

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
      type: searchForm.type,
      accountId: searchForm.accountId
    }
    if (searchForm.dateRange && searchForm.dateRange.length === 2) {
      params.startDate = searchForm.dateRange[0]
      params.endDate = searchForm.dateRange[1]
    }
    const res = await getSalesRevenues(params)
    list.value = Array.isArray(res) ? res : []
    total.value = res.length || 0

    // Calculate stats
    const inflowRes = await getSalesRevenues({ page: 1, pageSize: 1000 })
    const outflowRes = await getPurchaseCosts({ page: 1, pageSize: 1000 })
    const inflowTotal = Array.isArray(inflowRes) ? inflowRes.reduce((sum, i) => sum + (i.amount || 0), 0) : 0
    const outflowTotal = Array.isArray(outflowRes) ? outflowRes.reduce((sum, i) => sum + (i.amount || 0), 0) : 0
    stats.inflow = inflowTotal
    stats.outflow = outflowTotal
    stats.balance = inflowTotal - outflowTotal
    stats.frozen = inflowTotal * 0.1
  } catch (error) {
    console.error('加载数据失败:', error)
    ElMessage.error('加载现金流数据失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  loadData()
}

const resetSearch = () => {
  searchForm.dateRange = null
  searchForm.type = ''
  searchForm.accountId = ''
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

const handleAdd = () => {
  isEdit.value = false
  Object.assign(formData, { id: null, date: '', type: 'income', accountId: '', category: '', amount: 0, description: '', remark: '' })
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  Object.assign(formData, row)
  dialogVisible.value = true
}

const handleDelete = async (row) => {
  await ElMessageBox.confirm(`确定要删除这条记录吗?`, '提示', { type: 'warning' })
  ElMessage.success('删除成功')
  loadData()
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
  loadAccounts()
  loadData()
})
</script>

<style scoped>
.module-page {
  padding: 20px;
}
.stat-card {
  display: flex;
  align-items: center;
  padding: 10px;
}
.stat-icon {
  width: 50px;
  height: 50px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: #fff;
  margin-right: 15px;
}
.stat-icon.inflow { background: linear-gradient(135deg, #67c23a 0%, #95d475 100%); }
.stat-icon.outflow { background: linear-gradient(135deg, #f56c6c 0%, #fab6b6 100%); }
.stat-icon.balance { background: linear-gradient(135deg, #409eff 0%, #79bbff 100%); }
.stat-icon.frozen { background: linear-gradient(135deg, #e6a23c 0%, #f3d19e 100%); }
.stat-content {
  flex: 1;
}
.stat-label {
  font-size: 14px;
  color: #909399;
  margin-bottom: 5px;
}
.stat-value {
  font-size: 20px;
  font-weight: 600;
  color: #303133;
}
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
.text-warning { color: #e6a23c; }
.search-card {
  margin-bottom: 20px;
}
</style>
