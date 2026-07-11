<template>
  <MinipLayout title="我的钱包" :canBack="true">
    <div class="wallet-hero">
      <div class="label">余额</div>
      <div class="amount">¥{{ formatMoney(balance) }}</div>
      <div class="meta">积分 {{ points }} 分</div>
    </div>
    <div class="actions">
      <button>充值</button>
      <button>提现</button>
      <button>明细</button>
    </div>
    <div class="section-title">账单</div>
    <div class="bill-list">
      <div v-for="b in bills" :key="b.id" class="bill-row">
        <div class="bill-main">
          <div class="bill-title">{{ b.title }}</div>
          <div class="bill-date">{{ b.date }}</div>
        </div>
        <div class="bill-amount" :class="b.type">{{ b.type === 'in' ? '+' : '-' }}¥{{ formatMoney(b.amount) }}</div>
      </div>
      <div v-if="!bills.length" class="empty">暂无账单</div>
    </div>
  </MinipLayout>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/request'
import MinipLayout from './MinipLayout.vue'
const balance = ref(0)
const points = ref(0)
const bills = ref([])
function formatMoney(v) {
  return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
onMounted(async () => {
  try {
    const r = await api.get('/h5/wallet')
    if (r.code === 0) {
      balance.value = r.data?.balance || 0
      points.value = r.data?.points || 0
    }
  } catch {
    balance.value = 1280
    points.value = 2380
  }
  try {
    const r = await api.get('/h5/wallet/bills?limit=20')
    if (r.code === 0) bills.value = r.data || []
  } catch {
    bills.value = [
      { id: 1, title: '订单支付', date: '2026-07-10', amount: 268, type: 'out' },
      { id: 2, title: '退款', date: '2026-07-08', amount: 195, type: 'in' },
      { id: 3, title: '充值', date: '2026-07-05', amount: 500, type: 'in' }
    ]
  }
})
</script>
<style scoped>
.wallet-hero { background: linear-gradient(135deg, #14b8a6, #10b981); color: #fff; border-radius: 12px; padding: 20px 16px; text-align: center; margin-bottom: 12px; box-shadow: 0 4px 12px rgba(20,184,166,0.25); }
.wallet-hero .label { font-size: 12px; opacity: 0.9; }
.wallet-hero .amount { font-size: 36px; font-weight: 700; margin: 4px 0; }
.wallet-hero .meta { font-size: 11px; opacity: 0.85; }
.actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px; }
.actions button { background: #fff; border: 0; border-radius: 10px; padding: 12px; font-size: 13px; color: #1f2329; cursor: pointer; }
.section-title { font-size: 13px; font-weight: 600; color: #4b5563; margin: 12px 4px 8px; }
.bill-list { background: #fff; border-radius: 12px; overflow: hidden; }
.bill-row { display: flex; align-items: center; padding: 12px; gap: 10px; border-bottom: 1px solid #f5f6f8; }
.bill-row:last-child { border-bottom: 0; }
.bill-main { flex: 1; }
.bill-title { font-size: 13px; color: #1f2329; }
.bill-date { font-size: 11px; color: #9ca3af; margin-top: 2px; }
.bill-amount { font-size: 14px; font-weight: 600; }
.bill-amount.in { color: #10b981; }
.bill-amount.out { color: #ef4444; }
.empty { text-align: center; padding: 40px 0; color: #9ca3af; font-size: 13px; }
</style>