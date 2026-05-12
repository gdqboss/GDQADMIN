<template>
  <div class="bi-dashboard">
    <el-row :gutter="20" class="stat-cards">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon sales">
            <el-icon><TrendCharts /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">总销售额</div>
            <div class="stat-value">{{ formatMoney(stats.totalSales) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon orders">
            <el-icon><ShoppingCart /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">订单数量</div>
            <div class="stat-value">{{ stats.orderCount }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon customers">
            <el-icon><User /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">新增客户</div>
            <div class="stat-value">{{ stats.newCustomers }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon profit">
            <el-icon><Coin /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">净利润</div>
            <div class="stat-value">{{ formatMoney(stats.netProfit) }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px;">
      <el-col :span="16">
        <el-card shadow="hover">
          <template #header>
            <span>销售趋势</span>
            <el-radio-group v-model="trendPeriod" size="small" style="float: right;">
              <el-radio-button label="week">本周</el-radio-button>
              <el-radio-button label="month">本月</el-radio-button>
              <el-radio-button label="year">本年</el-radio-button>
            </el-radio-group>
          </template>
          <div class="chart-container">
            <div class="bar-chart">
              <div v-for="(item, index) in chartData" :key="index" class="bar-item">
                <div class="bar-value">{{ formatMoney(item.sales) }}</div>
                <div class="bar" :style="{ height: (item.sales / maxSales * 200) + 'px' }"></div>
                <div class="bar-label">{{ item.label }}</div>
              </div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>
            <span>销售分类占比</span>
          </template>
          <div class="pie-container">
            <div v-for="(item, index) in pieData" :key="index" class="pie-item">
              <div class="pie-color" :style="{ backgroundColor: item.color }"></div>
              <div class="pie-label">{{ item.name }}</div>
              <div class="pie-value">{{ item.value }}%</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px;">
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header>
            <span>业绩排行榜</span>
          </template>
          <el-table :data="rankList" style="width: 100%">
            <el-table-column prop="rank" label="排名" width="80" align="center">
              <template #default="{ $index }">
                <el-tag :type="$index < 3 ? 'warning' : 'info'">{{ $index + 1 }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="name" label="名称" />
            <el-table-column prop="sales" label="销售额" align="right">
              <template #default="{ row }">
                {{ formatMoney(row.sales) }}
              </template>
            </el-table-column>
            <el-table-column prop="proportion" label="占比" width="100" align="center">
              <template #default="{ row }">
                {{ ((row.sales / totalSales) * 100).toFixed(1) }}%
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header>
            <span>关键指标</span>
          </template>
          <div class="kpi-grid">
            <div class="kpi-item">
              <div class="kpi-label">毛利率</div>
              <div class="kpi-value success">{{ stats.grossProfitRate }}%</div>
            </div>
            <div class="kpi-item">
              <div class="kpi-label">客单价</div>
              <div class="kpi-value">{{ formatMoney(stats.avgOrderValue) }}</div>
            </div>
            <div class="kpi-item">
              <div class="kpi-label">复购率</div>
              <div class="kpi-value">{{ stats.repurchaseRate }}%</div>
            </div>
            <div class="kpi-item">
              <div class="kpi-label">库存周转天数</div>
              <div class="kpi-value">{{ stats.inventoryTurnover }}天</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { TrendCharts, ShoppingCart, User, Coin } from '@element-plus/icons-vue'

const trendPeriod = ref('month')

const stats = reactive({
  totalSales: 0,
  orderCount: 0,
  newCustomers: 0,
  netProfit: 0,
  grossProfitRate: 0,
  avgOrderValue: 0,
  repurchaseRate: 0,
  inventoryTurnover: 0
})

const chartData = ref([
  { label: '1月', sales: 120000 },
  { label: '2月', sales: 135000 },
  { label: '3月', sales: 156000 },
  { label: '4月', sales: 148000 },
  { label: '5月', sales: 168000 },
  { label: '6月', sales: 178000 }
])

const pieData = ref([
  { name: '电子产品', value: 36, color: '#667eea' },
  { name: '服装鞋帽', value: 26, color: '#f093fb' },
  { name: '食品饮料', value: 20, color: '#4facfe' },
  { name: '家居用品', value: 12, color: '#43e97b' },
  { name: '其他', value: 6, color: '#909399' }
])

const rankList = ref([])

const maxSales = computed(() => {
  return Math.max(...chartData.value.map(item => item.sales))
})

const totalSales = computed(() => {
  return rankList.value.reduce((sum, item) => sum + item.sales, 0)
})

const formatMoney = (amount) => {
  return new Intl.NumberFormat('zh-CN', { style: 'currency', currency: 'CNY' }).format(amount || 0)
}

const loadStats = async () => {
  try {
    stats.totalSales = 1256800
    stats.orderCount = 3428
    stats.newCustomers = 156
    stats.netProfit = 186520
    stats.grossProfitRate = 24.5
    stats.avgOrderValue = 366.62
    stats.repurchaseRate = 32.8
    stats.inventoryTurnover = 45

    rankList.value = [
      { name: '北京分公司', sales: 456800 },
      { name: '上海分公司', sales: 389200 },
      { name: '广州分公司', sales: 298600 },
      { name: '深圳分公司', sales: 256400 },
      { name: '成都分公司', sales: 198800 }
    ]
  } catch (error) {
    ElMessage.error('加载数据失败')
  }
}

onMounted(async () => {
  await loadStats()
})
</script>

<style scoped>
.bi-dashboard {
  padding: 20px;
}
.stat-cards {
  margin-bottom: 0;
}
.stat-card {
  display: flex;
  align-items: center;
  padding: 15px;
}
.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  color: #fff;
  margin-right: 15px;
}
.stat-icon.sales { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-icon.orders { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.stat-icon.customers { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.stat-icon.profit { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
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
.chart-container {
  height: 250px;
  padding: 20px 0;
}
.bar-chart {
  display: flex;
  align-items: flex-end;
  justify-content: space-around;
  height: 200px;
  padding-top: 30px;
}
.bar-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 60px;
}
.bar-value {
  font-size: 12px;
  color: #606266;
  margin-bottom: 8px;
}
.bar {
  width: 40px;
  background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
  border-radius: 4px 4px 0 0;
  transition: height 0.3s ease;
}
.bar-label {
  margin-top: 8px;
  font-size: 12px;
  color: #909399;
}
.pie-container {
  padding: 20px 0;
}
.pie-item {
  display: flex;
  align-items: center;
  padding: 8px 0;
}
.pie-color {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  margin-right: 12px;
}
.pie-label {
  flex: 1;
  font-size: 14px;
  color: #606266;
}
.pie-value {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
}
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  padding: 10px 0;
}
.kpi-item {
  text-align: center;
  padding: 15px;
  background: #f5f7fa;
  border-radius: 8px;
}
.kpi-label {
  font-size: 14px;
  color: #909399;
  margin-bottom: 8px;
}
.kpi-value {
  font-size: 24px;
  font-weight: 600;
  color: #303133;
}
.kpi-value.success {
  color: #67c23a;
}
</style>
