<template>
  <MinipLayout title="薪酬管理" :canBack="true">
    <div class="period-bar">
      <button @click="period='2026-06'" :class="{on:period==='2026-06'}">2026-06</button>
      <button @click="period='2026-07'" :class="{on:period==='2026-07'}">2026-07</button>
    </div>

    <div class="summary">
      <div class="sum-row"><span>应发总额</span><strong>¥{{ formatMoney(summary.gross) }}</strong></div>
      <div class="sum-row"><span>扣款合计</span><strong class="down">-¥{{ formatMoney(summary.deduct) }}</strong></div>
      <div class="sum-row total"><span>实发总额</span><strong class="net">¥{{ formatMoney(summary.net) }}</strong></div>
    </div>

    <div class="section-title">薪资明细</div>
    <div class="salary-list">
      <div v-for="s in list" :key="s.id" class="salary-card" @click="showDetail(s)">
        <div class="sal-name">{{ s.name }}</div>
        <div class="sal-amount">¥{{ formatMoney(s.net) }}</div>
        <div class="sal-meta">{{ s.position }} · 已{{ s.status === 'paid' ? '发放' : '待审' }}</div>
      </div>
      <div v-if="!list.length" class="empty">暂无薪资数据</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/request'
import { ElMessage } from 'element-plus'
import MinipLayout from './MinipLayout.vue'

const period = ref('2026-07')
const list = ref([])
const summary = ref({ gross: 0, deduct: 0, net: 0 })

function formatMoney(v) {
  return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function showDetail(s) {
  alert(`${s.name}\n应发: ¥${formatMoney(s.gross)}\n扣款: ¥${formatMoney(s.deduct)}\n实发: ¥${formatMoney(s.net)}`)
}

onMounted(async () => {
  try {
    const r = await api.get(`/oa/salary?period=${period.value}&limit=50`)
    if (r.code === 0) {
      list.value = r.data?.list || []
      summary.value = r.data?.summary || summary.value
    }
  } catch (e) {
    ElMessage.error('加载失败,请稍后重试')
    list.value = []
  }
})
</script>

<style scoped>
.period-bar {
  display: flex;
  gap: 6px;
  margin-bottom: 12px;
}
.period-bar button {
  flex: 1;
  padding: 10px;
  background: #fff;
  border: 0;
  border-radius: 10px;
  font-size: 13px;
  color: #6b7280;
  cursor: pointer;
}
.period-bar button.on {
  background: #6366f1;
  color: #fff;
}
.summary {
  background: #fff;
  border-radius: 12px;
  padding: 12px;
  margin-bottom: 12px;
}
.sum-row {
  display: flex;
  justify-content: space-between;
  padding: 6px 0;
  font-size: 13px;
}
.sum-row + .sum-row { border-top: 1px solid #f3f4f6; }
.sum-row.total {
  border-top: 2px solid #6366f1;
  margin-top: 4px;
  padding-top: 8px;
  font-weight: 600;
}
.sum-row strong.down { color: #ef4444; }
.sum-row strong.net { color: #6366f1; font-size: 16px; }
.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #4b5563;
  margin: 12px 4px 8px;
}
.salary-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.salary-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
}
.sal-name { font-size: 14px; font-weight: 600; color: #1f2329; }
.sal-amount { font-size: 18px; font-weight: 700; color: #6366f1; margin: 4px 0; }
.sal-meta { font-size: 11px; color: #6b7280; }
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>