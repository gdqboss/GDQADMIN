<template>
  <div class="module-page module-finance-profit">
    <el-row :gutter="20" class="stat-cards">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon revenue">
            <el-icon><TrendCharts /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">销售收入</div>
            <div class="stat-value text-primary">{{ formatMoney(stats.revenue) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon cost">
            <el-icon><Coin /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">销售成本</div>
            <div class="stat-value text-danger">{{ formatMoney(stats.cost) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon gross">
            <el-icon><Histogram /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">毛利润</div>
            <div class="stat-value text-success">{{ formatMoney(stats.grossProfit) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon net">
            <el-icon><Money /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">净利润</div>
            <div class="stat-value" :class="stats.netProfit >= 0 ? 'text-success' : 'text-danger'">
              {{ formatMoney(stats.netProfit) }}
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px;">
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header>
            <span>利润率趋势</span>
          </template>
          <div class="profit-rate">
            <div class="rate-item">
              <span class="label">毛利率：</span>
              <span class="value text-success">{{ stats.grossRate }}%</span>
            </div>
            <div class="rate-item">
              <span class="label">净利率：</span>
              <span class="value" :class="stats.netRate >= 0 ? 'text-success' : 'text-danger'">{{ stats.netRate }}%</span>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header>
            <span>成本构成</span>
          </template>
          <div class="cost-breakdown">
            <div class="cost-item">
              <span class="label">采购成本：</span>
              <span class="value">{{ formatMoney(stats.purchaseCost) }}</span>
            </div>
            <div class="cost-item">
              <span class="label">运营成本：</span>
              <span class="value">{{ formatMoney(stats.operatingCost) }}</span>
            </div>
            <div class="cost-item">
              <span class="label">其他成本：</span>
              <span class="value">{{ formatMoney(stats.otherCost) }}</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card class="search-card" shadow="never" style="margin-top: 20px;">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="日期范围">
          <el-date-picker v-model="searchForm.dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD" />
        </el-form-item>
        <el-form-item label="产品">
          <el-input v-model="searchForm.productName" placeholder="请输入产品名称" clearable />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card" shadow="never">
      <template #header>
        <span>利润明细</span>
        <el-button type="primary" size="small" @click="exportData" style="float: right;">导出</el-button>
      </template>
      <el-table :data="list" v-loading="loading" style="width: 100%" border>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="productName" label="产品名称" min-width="150" />
        <el-table-column prop="salesAmount" label="销售收入" width="130" align="right">
          <template #default="{ row }">
            {{ formatMoney(row.salesAmount) }}
          </template>
        </el-table-column>
        <el-table-column prop="costAmount" label="销售成本" width="130" align="right">
          <template #default="{ row }">
            {{ formatMoney(row.costAmount) }}
          </template>
        </el-table-column>
        <el-table-column prop="grossProfit" label="毛利润" width="130" align="right">
          <template #default="{ row }">
            <span class="text-success">{{ formatMoney(row.grossProfit) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="grossRate" label="毛利率" width="100" align="right">
          <template #default="{ row }">
            {{ row.grossRate }}%
          </template>
        </el-table-column>
        <el-table-column prop="period" label="期间" width="120" />
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { TrendCharts, Coin, Histogram, Money } from '@element-plus/icons-vue'
import { getSalesRevenues, getPurchaseCosts } from '@/api/finance'

const list = ref([])
const total = ref(0)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)

const stats = reactive({
  revenue: 0,
  cost: 0,
  grossProfit: 0,
  netProfit: 0,
  grossRate: '0.00',
  netRate: '0.00',
  purchaseCost: 0,
  operatingCost: 0,
  otherCost: 0
})

const searchForm = reactive({
  dateRange: null,
  productName: ''
})

const formatMoney = (amount) => {
  return new Intl.NumberFormat('zh-CN', { style: 'currency', currency: 'CNY' }).format(amount || 0)
}

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value
    }
    if (searchForm.dateRange && searchForm.dateRange.length === 2) {
      params.startDate = searchForm.dateRange[0]
      params.endDate = searchForm.dateRange[1]
    }
    const res = await getSalesRevenues(params)
    list.value = Array.isArray(res) ? res : []
    total.value = res.length || 0

    // Calculate stats
    const salesRes = await getSalesRevenues({ page: 1, pageSize: 1000 })
    const costRes = await getPurchaseCosts({ page: 1, pageSize: 1000 })
    const salesTotal = Array.isArray(salesRes) ? salesRes.reduce((sum, i) => sum + (i.amount || 0), 0) : 0
    const costTotal = Array.isArray(costRes) ? costRes.reduce((sum, i) => sum + (i.amount || 0), 0) : 0

    stats.revenue = salesTotal
    stats.cost = costTotal
    stats.grossProfit = salesTotal - costTotal
    stats.purchaseCost = costTotal
    stats.operatingCost = salesTotal * 0.1
    stats.otherCost = salesTotal * 0.05
    stats.netProfit = stats.grossProfit - stats.operatingCost - stats.otherCost
    stats.grossRate = salesTotal > 0 ? ((stats.grossProfit / salesTotal) * 100).toFixed(2) : '0.00'
    stats.netRate = salesTotal > 0 ? ((stats.netProfit / salesTotal) * 100).toFixed(2) : '0.00'
  } catch (error) {
    console.error('加载数据失败:', error)
    ElMessage.error('加载利润数据失败')
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
  searchForm.productName = ''
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
.stat-icon.revenue { background: linear-gradient(135deg, #409eff 0%, #79bbff 100%); }
.stat-icon.cost { background: linear-gradient(135deg, #f56c6c 0%, #fab6b6 100%); }
.stat-icon.gross { background: linear-gradient(135deg, #67c23a 0%, #95d475 100%); }
.stat-icon.net { background: linear-gradient(135deg, #e6a23c 0%, #f3d19e 100%); }
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
.text-primary { color: #409eff; }
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
.profit-rate, .cost-breakdown {
  display: flex;
  flex-direction: column;
  gap: 15px;
}
.rate-item, .cost-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.rate-item .label, .cost-item .label {
  color: #909399;
}
.rate-item .value, .cost-item .value {
  font-size: 18px;
  font-weight: 600;
}
.search-card {
  margin-bottom: 20px;
}
</style>
