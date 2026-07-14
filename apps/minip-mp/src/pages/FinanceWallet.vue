<template>
  <MinipLayout title="钱包" :canBack="true">
    <div class="wallet-hero">
      <div class="label">钱包余额</div>
      <div class="amount">¥{{ formatMoney(balance) }}</div>
      <div class="meta">{{ frozen ? '冻结: ¥' + formatMoney(frozen) : '正常可用' }}</div>
    </div>

    <div class="action-bar">
      <button class="btn primary">充值</button>
      <button class="btn">提现</button>
      <button class="btn">转账</button>
    </div>

    <div class="section-title">交易明细</div>
    <div class="tx-list">
      <div v-for="t in transactions" :key="t.id" class="tx-row">
        <div class="tx-icon" :style="{background: t.type === 'in' ? '#d1fae5' : '#fee2e2'}">
          {{ t.type === 'in' ? '↓' : '↑' }}
        </div>
        <div class="tx-main">
          <div class="tx-title">{{ t.title }}</div>
          <div class="tx-time">{{ t.time }}</div>
        </div>
        <div class="tx-amount" :class="t.type === 'in' ? 'in' : 'out'">
          {{ t.type === 'in' ? '+' : '-' }}¥{{ formatMoney(t.amount) }}
        </div>
      </div>
      <div v-if="!transactions.length" class="empty">暂无交易记录</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const loading = ref(false)
import api from '@/utils/api'
import MinipLayout from './MinipLayout.vue'

const balance = ref(0)
const frozen = ref(0)
const transactions = ref([])

function formatMoney(v) {
  return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

onMounted(async () => {
  try {
    loading.value = true
    const r = await api.get('/finance/wallet')
    if (r.code === 0) {
      balance.value = r.data?.balance || 0
      frozen.value = r.data?.frozen || 0
    }
  } catch {}
  try {
    const r = await api.get('/finance/wallet/transactions?limit=20')
    if (r.code === 0) transactions.value = r.data || []
  } catch (e) {
    uni.showToast({ title: '加载失败,请稍后重试', icon: 'none' })
    transactions.value = []
  }
  finally {
    loading.value = false
  }
})
</script>

<style scoped>
.wallet-hero {
  background: linear-gradient(135deg, #10b981, #14b8a6);
  color: #fff;
  border-radius: 12px;
  padding: 24px 16px;
  text-align: center;
  margin-bottom: 12px;
  box-shadow: 0 4px 12px rgba(16,185,129,0.25);
}
.wallet-hero .label { font-size: 12px; opacity: 0.9; }
.wallet-hero .amount {
  font-size: 36px;
  font-weight: 700;
  margin: 4px 0;
}
.wallet-hero .meta { font-size: 11px; opacity: 0.85; }
.action-bar {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-bottom: 12px;
}
.btn {
  background: #fff;
  border: 0;
  border-radius: 10px;
  padding: 12px;
  font-size: 14px;
  color: #1f2329;
  cursor: pointer;
}
.btn.primary {
  background: #6366f1;
  color: #fff;
}
.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #4b5563;
  margin: 12px 4px 8px;
}
.tx-list {
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
}
.tx-row {
  display: flex;
  align-items: center;
  padding: 12px;
  gap: 10px;
  border-bottom: 1px solid #f5f6f8;
}
.tx-row:last-child { border-bottom: 0; }
.tx-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 16px;
  color: #fff;
}
.tx-main { flex: 1; }
.tx-title { font-size: 13px; color: #1f2329; }
.tx-time { font-size: 10px; color: #9ca3af; margin-top: 2px; }
.tx-amount { font-size: 14px; font-weight: 600; }
.tx-amount.in { color: #10b981; }
.tx-amount.out { color: #ef4444; }
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>