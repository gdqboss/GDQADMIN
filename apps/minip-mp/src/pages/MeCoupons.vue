<template>
  <MinipLayout title="我的优惠券" :canBack="true">
    <div class="tabs">
      <div v-for="t in tabs" :key="t.v" :class="{on: filter===t.v}" @click="filter=t.v">{{ t.l }}</div>
    </div>

    <div class="cp-list">
      <div v-if="loading" class="empty">加载中…</div>
      <div v-for="c in filteredList" :key="c.id" class="cp-card">
        <div class="cp-left">
          <span class="cp-cur">¥</span>
          <span class="cp-num">{{ c.amount }}</span>
        </div>
        <div class="cp-right">
          <div class="cp-name">{{ c.name }}</div>
          <div class="cp-cond">满 ¥{{ c.threshold }} 可用</div>
          <div class="cp-date">{{ (c.expire_at || '').slice(0, 10) }} 到期</div>
        </div>
        <button class="cp-use" @click="useCoupon(c)">立即使用</button>
      </div>
      <div v-if="!loading && !filteredList.length" class="empty">暂无优惠券</div>
    </div>
  </MinipLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/utils/api'
import MinipLayout from './MinipLayout.vue'

const filter = ref('unused')
const tabs = [
  { v: 'unused', l: '未使用' },
  { v: 'used', l: '已使用' },
  { v: 'expired', l: '已过期' }
]

const loading = ref(false)
const list = ref([])

const filteredList = computed(() => {
  const now = Date.now()
  return list.value.filter(c => {
    const end = c.expire_at ? new Date(c.expire_at).getTime() : 0
    if (filter.value === 'unused') return c.status === 'unused' && end > now
    if (filter.value === 'used') return c.status === 'used'
    if (filter.value === 'expired') return c.status === 'used' || end <= now
    return true
  })
})

onMounted(async () => {
  loading.value = true
  try {
    const r = await api.get('/minip/enterprise/coupons')
    if (r.code === 0 && r.data) {
      list.value = r.data.list || []
    } else {
      uni.showToast({ title: r.message || '加载失败', icon: 'none' })
    }
  } catch (e) {
    uni.showToast({ title: '优惠券加载失败,请稍后重试', icon: 'none' })
    list.value = []
  } finally {
    loading.value = false
  }
})

function useCoupon(c) {
  uni.showToast({ title: `使用优惠券：${c.name} ¥${c.amount}`, icon: 'none' })
}
</script>

<style scoped>
.tabs {
  display: flex;
  background: #fff;
  border-radius: 10px;
  padding: 4px;
  margin-bottom: 12px;
}
.tabs > div {
  flex: 1;
  text-align: center;
  padding: 8px;
  font-size: 12px;
  color: #6b7280;
  border-radius: 8px;
  cursor: pointer;
}
.tabs > div.on {
  background: #ec4899;
  color: #fff;
}
.cp-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.cp-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.cp-left {
  background: linear-gradient(135deg, #ec4899, #f43f5e);
  color: #fff;
  padding: 12px;
  border-radius: 8px;
  text-align: center;
  min-width: 70px;
}
.cp-cur { font-size: 11px; }
.cp-num { font-size: 22px; font-weight: 700; margin-left: 2px; }
.cp-right { flex: 1; }
.cp-name { font-size: 13px; font-weight: 600; color: #1f2329; }
.cp-cond { font-size: 11px; color: #6b7280; margin-top: 2px; }
.cp-date { font-size: 10px; color: #9ca3af; margin-top: 2px; }
.cp-use {
  background: #ec4899;
  color: #fff;
  border: 0;
  border-radius: 14px;
  padding: 4px 10px;
  font-size: 11px;
  cursor: pointer;
}
.empty {
  text-align: center;
  padding: 40px 0;
  color: #9ca3af;
  font-size: 13px;
}
</style>
