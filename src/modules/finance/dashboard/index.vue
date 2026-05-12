<template>
  <div class="module-page module-finance-dashboard">
    <el-row :gutter="20" class="stat-cards">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon sales">
            <el-icon><TrendCharts /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">销售收入</div>
            <div class="stat-value">{{ formatMoney(stats.salesRevenue) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon cost">
            <el-icon><Coin /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">采购成本</div>
            <div class="stat-value">{{ formatMoney(stats.purchaseCost) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon receivable">
            <el-icon><Wallet /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">应收款项</div>
            <div class="stat-value">{{ formatMoney(stats.receivable) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon payable">
            <el-icon><CreditCard /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">应付款项</div>
            <div class="stat-value">{{ formatMoney(stats.payable) }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="stat-cards" style="margin-top: 20px;">
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header>
            <span>利润概览</span>
          </template>
          <div class="profit-overview">
            <div class="profit-item">
              <span class="label">毛利润：</span>
              <span class="value success">{{ formatMoney(stats.grossProfit) }}</span>
            </div>
            <div class="profit-item">
              <span class="label">净利润：</span>
              <span class="value" :class="stats.netProfit >= 0 ? 'success' : 'danger'">
                {{ formatMoney(stats.netProfit) }}
              </span>
            </div>
            <div class="profit-item">
              <span class="label">利润率：</span>
              <span class="value">{{ stats.profitRate }}%</span>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header>
            <span>发票统计</span>
          </template>
          <div class="invoice-stats">
            <div class="invoice-item">
              <span class="label">待开票：</span>
              <span class="value">{{ stats.pendingInvoices }}</span>
            </div>
            <div class="invoice-item">
              <span class="label">已开票：</span>
              <span class="value">{{ stats.issuedInvoices }}</span>
            </div>
            <div class="invoice-item">
              <span class="label">已作废：</span>
              <span class="value">{{ stats.voidedInvoices }}</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card class="table-card" style="margin-top: 20px;">
      <template #header>
        <span>近期交易记录</span>
        <el-button type="primary" size="small" @click="loadRecentRecords" style="float: right;">
          刷新
        </el-button>
      </template>
      <el-table :data="recentRecords" v-loading="loading" style="width: 100%" border>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="type" label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="row.type === 'income' ? 'success' : 'warning'">
              {{ row.type === 'income' ? '收入' : '支出' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="description" label="描述" />
        <el-table-column prop="amount" label="金额" width="150" align="right">
          <template #default="{ row }">
            <span :class="row.type === 'income' ? 'text-success' : 'text-danger'">
              {{ row.type === 'income' ? '+' : '-' }}{{ formatMoney(row.amount) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="时间" width="180" />
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { TrendCharts, Coin, Wallet, CreditCard } from '@element-plus/icons-vue'
import { getSalesRevenues, getPurchaseCosts, getAccountsReceivable, getInvoices } from '@/api/finance'

const loading = ref(false)
const stats = reactive({
  salesRevenue: 0,
  purchaseCost: 0,
  receivable: 0,
  payable: 0,
  grossProfit: 0,
  netProfit: 0,
  profitRate: '0.00',
  pendingInvoices: 0,
  issuedInvoices: 0,
  voidedInvoices: 0
})
const recentRecords = ref([])

const formatMoney = (amount) => {
  return new Intl.NumberFormat('zh-CN', { style: 'currency', currency: 'CNY' }).format(amount || 0)
}

const loadStats = async () => {
  loading.value = true
  try {
    const [salesRes, costRes, receivableRes, invoiceRes] = await Promise.all([
      getSalesRevenues({ page: 1, pageSize: 100 }),
      getPurchaseCosts({ page: 1, pageSize: 100 }),
      getAccountsReceivable({ page: 1, pageSize: 100 }),
      getInvoices({ page: 1, pageSize: 100 })
    ])

    // Calculate totals
    const salesTotal = Array.isArray(salesRes) ? salesRes.reduce((sum, item) => sum + (item.amount || 0), 0) : 0
    const costTotal = Array.isArray(costRes) ? costRes.reduce((sum, item) => sum + (item.amount || 0), 0) : 0
    const receivableTotal = Array.isArray(receivableRes) ? receivableRes.reduce((sum, item) => sum + (item.amount || 0), 0) : 0
    const invoiceList = Array.isArray(invoiceRes) ? invoiceRes : []

    stats.salesRevenue = salesTotal
    stats.purchaseCost = costTotal
    stats.receivable = receivableTotal
    stats.payable = costTotal * 0.8 // Estimate
    stats.grossProfit = salesTotal - costTotal
    stats.netProfit = stats.grossProfit * 0.7 // Estimate after expenses
    stats.profitRate = salesTotal > 0 ? ((stats.netProfit / salesTotal) * 100).toFixed(2) : '0.00'
    
    stats.pendingInvoices = invoiceList.filter(i => i.status === 'pending').length
    stats.issuedInvoices = invoiceList.filter(i => i.status === 'issued').length
    stats.voidedInvoices = invoiceList.filter(i => i.status === 'voided').length

    // Generate recent records from sales data
    recentRecords.value = (salesRes || []).slice(0, 5).map(item => ({
      id: item.id,
      type: 'income',
      description: item.description || '销售收入',
      amount: item.amount || 0,
      createdAt: item.createdAt || new Date().toLocaleString()
    }))
  } catch (error) {
    console.error('加载统计数据失败:', error)
    ElMessage.error('加载统计数据失败')
  } finally {
    loading.value = false
  }
}

const loadRecentRecords = () => {
  loadStats()
}

onMounted(() => {
  loadStats()
})
</script>

<style scoped>
.module-page {
  padding: 20px;
}
.stat-cards {
  margin-bottom: 0;
}
.stat-card {
  display: flex;
  align-items: center;
  padding: 10px;
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
.stat-icon.cost { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.stat-icon.receivable { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.stat-icon.payable { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
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
.profit-overview, .invoice-stats {
  display: flex;
  flex-direction: column;
  gap: 15px;
}
.profit-item, .invoice-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.profit-item .label, .invoice-item .label {
  color: #909399;
}
.profit-item .value, .invoice-item .value {
  font-size: 18px;
  font-weight: 600;
}
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
</style>
