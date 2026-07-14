<template>
  <MinipLayout title="财务中心" :canBack="true" tabbar="enterprise">
    <!-- 余额卡片 -->
    <div class="balance-card">
      <div class="balance-label">账户余额</div>
      <div class="balance-num">¥{{ formatMoney(balance) }}</div>
      <div class="balance-meta">
        <div><span>本月收入</span><strong>¥{{ formatMoney(monthly.income) }}</strong></div>
        <div><span>本月支出</span><strong>¥{{ formatMoney(monthly.expense) }}</strong></div>
        <div><span>待收账款</span><strong>¥{{ formatMoney(monthly.receivable) }}</strong></div>
      </div>
    </div>

    <!-- 功能区 -->
    <div class="section-title">快捷功能</div>
    <div class="action-grid">
      <navigator url="/minip/finance/expense" class="action-cell">
        <div class="action-icon">📤</div>
        <span>报销</span>
      </navigator>
      <navigator url="/minip/finance/receipt" class="action-cell">
        <div class="action-icon">📥</div>
        <span>收款</span>
      </navigator>
      <navigator url="/minip/finance/wallet" class="action-cell">
        <div class="action-icon">💳</div>
        <span>钱包</span>
      </navigator>
      <navigator url="/minip/finance/invoice" class="action-cell">
        <div class="action-icon">🧾</div>
        <span>发票</span>
      </navigator>
    </div>

    <!-- 报表 -->
    <div class="section-title">财务报表</div>
    <div class="report-list">
      <div v-for="r in reports" :key="r.path" class="report-row" @click="uni.navigateTo({ url: r.path })">
        <span class="report-icon">{{ r.icon }}</span>
        <span class="report-name">{{ r.name }}</span>
        <span class="report-arrow">›</span>
      </div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const loading = ref(false)
import api from '@/utils/api'
import MinipLayout from './MinipLayout.vue'

const balance = ref(0)
const monthly = ref({ income: 0, expense: 0, receivable: 0 })
const reports = ref([
  { icon: '📊', name: '财务总览', path: '/minip/finance' },
  { icon: '💸', name: '支出管理', path: '/minip/finance/expense' },
  { icon: '📋', name: '应收账款', path: '/minip/finance/receivable' },
  { icon: '📑', name: '应付账款', path: '/minip/finance/payable' },
  { icon: '💹', name: '利润分析', path: '/minip/finance/profit' }
])

function formatMoney(v) {
  return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

onMounted(async () => {
  try {
    loading.value = true
    const r = await api.get('/finance/wallet')
    if (r.code === 0) balance.value = r.data?.balance || 0
  } catch {}
  try {
    const r = await api.get('/finance/overview?range=month')
    if (r.code === 0 && r.data) {
      monthly.value = {
        income: r.data.income || 0,
        expense: r.data.expense || 0,
        receivable: r.data.receivable || 0
      }
    }
  } catch {}
  finally {
    loading.value = false
  }
})
</script>

<style scoped>
.balance-card {
  background: linear-gradient(135deg, #f59e0b, #ef4444);
  color: #fff;
  border-radius: 12px;
  padding: 20px 16px;
  margin-bottom: 12px;
  box-shadow: 0 4px 12px rgba(245,158,11,0.25);
}
.balance-label {
  font-size: 12px;
  opacity: 0.9;
}
.balance-num {
  font-size: 32px;
  font-weight: 700;
  margin: 4px 0 12px;
}
.balance-meta {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 4px;
  border-top: 1px solid rgba(255,255,255,0.2);
  padding-top: 12px;
}
.balance-meta div {
  display: flex;
  flex-direction: column;
  font-size: 10px;
  opacity: 0.85;
}
.balance-meta strong {
  font-size: 13px;
  font-weight: 600;
  margin-top: 2px;
  opacity: 1;
}
.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #4b5563;
  margin: 12px 4px 8px;
}
.action-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  background: #fff;
  border-radius: 12px;
  padding: 12px 8px;
}
.action-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 4px;
  font-size: 12px;
  color: #1f2329;
  text-decoration: none;
}
.action-icon {
  font-size: 26px;
  margin-bottom: 4px;
}
.report-list {
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
}
.report-row {
  display: flex;
  align-items: center;
  padding: 12px;
  border-bottom: 1px solid #f5f6f8;
  gap: 10px;
}
.report-row:last-child { border-bottom: 0; }
.report-icon { font-size: 18px; }
.report-name { flex: 1; font-size: 14px; color: #1f2329; }
.report-arrow { font-size: 18px; color: #9ca3af; }
</style>