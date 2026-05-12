<template>
  <div class="module-page module-sales-retail">
    <!-- Header -->
    <el-card class="header-card" shadow="never">
      <template #header>
        <div class="card-header">
          <span class="card-header-title">零售记录管理</span>
          <div class="card-header-actions">
            <el-button type="primary" @click="handleRefresh">刷新</el-button>
            <el-button @click="exportData">导出</el-button>
          </div>
        </div>
      </template>
    </el-card>

    <!-- Search Form -->
    <el-card class="search-card" shadow="never">
      <el-form :inline="true" :model="searchForm" ref="searchFormRef">
        <el-form-item label="零售单号">
          <el-input v-model="searchForm.retailNo" placeholder="请输入零售单号" clearable />
        </el-form-item>
        <el-form-item label="门店">
          <el-select v-model="searchForm.storeId" placeholder="请选择门店" clearable style="width: 180px;">
            <el-option label="全部门店" value="" />
            <el-option v-for="store in storeList" :key="store.id" :label="store.name" :value="store.id" />
          </el-select>
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
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="请选择状态" clearable>
            <el-option label="全部" value="" />
            <el-option label="已完成" value="completed" />
            <el-option label="已撤销" value="revoked" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Statistics Cards -->
    <el-row :gutter="20" class="stat-cards">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-label">今日零售笔数</div>
            <div class="stat-value">{{ stats.todayCount }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-label">今日零售金额</div>
            <div class="stat-value text-success">{{ formatMoney(stats.todayAmount) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-label">本月零售笔数</div>
            <div class="stat-value">{{ stats.monthCount }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-label">本月零售金额</div>
            <div class="stat-value text-success">{{ formatMoney(stats.monthAmount) }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Table -->
    <el-card class="table-card" shadow="never" style="margin-top: 20px;">
      <el-table :data="list" v-loading="loading" stripe border @selection-change="handleSelectionChange">
        <el-table-column type="selection" width="55" />
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="retailNo" label="零售单号" width="160" />
        <el-table-column prop="storeName" label="门店" width="120" />
        <el-table-column prop="productName" label="商品名称" min-width="150" />
        <el-table-column prop="quantity" label="数量" width="80" align="center" />
        <el-table-column prop="unitPrice" label="单价" width="100" align="right">
          <template #default="{ row }">
            {{ formatMoney(row.unitPrice) }}
          </template>
        </el-table-column>
        <el-table-column prop="totalAmount" label="总金额" width="120" align="right">
          <template #default="{ row }">
            <span class="text-success">{{ formatMoney(row.totalAmount) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="paymentMethod" label="支付方式" width="100">
          <template #default="{ row }">
            {{ getPaymentMethodText(row.paymentMethod) }}
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'completed' ? 'success' : 'info'">
              {{ row.status === 'completed' ? '已完成' : '已撤销' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="零售时间" width="160" />
        <el-table-column label="操作" width="120" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="danger" @click="handleRevoke(row)" :disabled="row.status === 'revoked'">撤销</el-button>
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getRetailRecords, revokeRetailRecord, deleteRetailRecord, exportRetailRecords, getStores } from '@/api/finance'

const list = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const storeList = ref([])

const stats = reactive({
  todayCount: 0,
  todayAmount: 0,
  monthCount: 0,
  monthAmount: 0
})

const searchForm = reactive({
  retailNo: '',
  storeId: '',
  dateRange: null,
  status: ''
})

const formatMoney = (amount) => {
  return new Intl.NumberFormat('zh-CN', { style: 'currency', currency: 'CNY' }).format(amount || 0)
}

const getPaymentMethodText = (method) => {
  const texts = {
    cash: '现金',
    bank_transfer: '银行转账',
    alipay: '支付宝',
    wechat: '微信支付',
    card: '银行卡',
    other: '其他'
  }
  return texts[method] || method
}

const loadStoreList = async () => {
  try {
    const res = await getStores()
    storeList.value = Array.isArray(res) ? res : []
  } catch (error) {
    console.error('加载门店列表失败:', error)
  }
}

const loadStats = async () => {
  // 模拟统计数据，实际应从API获取
  const today = new Date().toISOString().split('T')[0]
  stats.todayCount = list.value.filter(item => item.createdAt && item.createdAt.startsWith(today)).length
  stats.todayAmount = list.value.filter(item => item.createdAt && item.createdAt.startsWith(today))
    .reduce((sum, item) => sum + (item.totalAmount || 0), 0)
  
  const currentMonth = today.substring(0, 7)
  stats.monthCount = list.value.filter(item => item.createdAt && item.createdAt.startsWith(currentMonth)).length
  stats.monthAmount = list.value.filter(item => item.createdAt && item.createdAt.startsWith(currentMonth))
    .reduce((sum, item) => sum + (item.totalAmount || 0), 0)
}

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
      retailNo: searchForm.retailNo,
      storeId: searchForm.storeId,
      status: searchForm.status
    }
    if (searchForm.dateRange && searchForm.dateRange.length === 2) {
      params.startDate = searchForm.dateRange[0]
      params.endDate = searchForm.dateRange[1]
    }
    const res = await getRetailRecords(params)
    list.value = Array.isArray(res) ? res : []
    total.value = res.length || 0
    loadStats()
  } catch (error) {
    console.error('加载数据失败:', error)
    ElMessage.error('加载零售记录数据失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  loadData()
}

const resetSearch = () => {
  searchForm.retailNo = ''
  searchForm.storeId = ''
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

const handleRefresh = () => {
  loadData()
  ElMessage.success('刷新成功')
}

const handleRevoke = async (row) => {
  try {
    await ElMessageBox.confirm(`确定要撤销零售记录「${row.retailNo}」吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    await revokeRetailRecord(row.id)
    ElMessage.success('撤销成功')
    loadData()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('撤销失败')
    }
  }
}

const exportData = async () => {
  try {
    const params = { ...searchForm }
    if (searchForm.dateRange && searchForm.dateRange.length === 2) {
      params.startDate = searchForm.dateRange[0]
      params.endDate = searchForm.dateRange[1]
    }
    await exportRetailRecords(params)
    ElMessage.success('导出成功')
  } catch (error) {
    ElMessage.error('导出失败')
  }
}

onMounted(() => {
  loadStoreList()
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
.stat-cards {
  margin-bottom: 0;
}
.stat-card {
  display: flex;
  align-items: center;
  padding: 15px;
}
.stat-content {
  flex: 1;
}
.stat-label {
  font-size: 14px;
  color: #909399;
  margin-bottom: 5px;
}
.stat-value {
  font-size: 24px;
  font-weight: 600;
  color: #303133;
}
.text-success {
  color: #67c23a;
}
</style>
