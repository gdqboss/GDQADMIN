<template>
  <MinipLayout title="财务收款" :canBack="true">
    <div class="summary-card">
      <div class="row"><span>本月已收</span><strong>¥{{ formatMoney(summary.received) }}</strong></div>
      <div class="row"><span>待收账款</span><strong class="warn">¥{{ formatMoney(summary.pending) }}</strong></div>
    </div>

    <div class="section-title">收款记录</div>
    <div class="receipt-list">
      <div v-for="item in list" :key="item.id" class="receipt-card">
        <div class="receipt-main">
          <div class="r-name">{{ item.customer }}</div>
          <div class="r-invoice">单号 {{ item.invoice_no }}</div>
          <div class="r-date">{{ item.date }}</div>
        </div>
        <div class="r-amount">¥{{ formatMoney(item.amount) }}</div>
      </div>
      <div v-if="!list.length" class="empty">暂无收款记录</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const loading = ref(false)
import api from '@/api/request'
import { ElMessage } from 'element-plus'
import MinipLayout from './MinipLayout.vue'

const list = ref([])
const summary = ref({ received: 0, pending: 0 })

function formatMoney(v) {
  return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

onMounted(async () => {
  try {
    loading.value = true
    const r = await api.get('/finance/receipts?limit=50')
    if (r.code === 0) list.value = r.data || []
  } catch (e) {
    ElMessage.error('加载失败,请稍后重试')
    list.value = []
  }
  try {
    const r = await api.get('/finance/accounts-receivable?range=month')
    if (r.code === 0) {
      summary.value = {
        received: r.data?.received || 0,
        pending: r.data?.pending || 0
      }
    }
  } catch {}
  finally {
    loading.value = false
  }
})
</script>

<style scoped>
.summary-card {
  background: #fff;
  border-radius: 12px;
  padding: 12px;
  margin-bottom: 12px;
}
.summary-card .row {
  display: flex;
  justify-content: space-between;
  padding: 6px 0;
  font-size: 13px;
}
.summary-card .row + .row { border-top: 1px solid #f3f4f6; }
.summary-card .row strong { font-weight: 700; }
.summary-card .row strong.warn { color: #f59e0b; }
.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #4b5563;
  margin: 12px 4px 8px;
}
.receipt-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.receipt-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.receipt-main { flex: 1; }
.r-name { font-size: 14px; font-weight: 600; color: #1f2329; }
.r-invoice { font-size: 11px; color: #6b7280; margin-top: 2px; }
.r-date { font-size: 10px; color: #9ca3af; margin-top: 2px; }
.r-amount { font-size: 16px; font-weight: 700; color: #10b981; }
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>