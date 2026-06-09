<template>
  <div class="pb-16">
    <!-- Tabs -->
    <div class="flex border-b border-gray-100 mb-4 bg-white sticky top-0 z-10">
      <button v-for="tab in tabs" :key="tab.key"
        class="flex-1 py-3 text-sm font-medium border-b-2 transition-colors"
        :class="activeTab === tab.key
          ? 'border-amber-500 text-amber-600'
          : 'border-transparent text-gray-400'"
        @click="activeTab = tab.key; fetchCoupons()">
        {{ tab.label }}
      </button>
    </div>

    <!-- 可领取优惠券 -->
    <div v-if="activeTab === 'available'" class="px-4">
      <div v-for="c in coupons" :key="c.id"
        class="bg-white rounded-xl p-4 mb-3 flex gap-3 border border-gray-100">
        <div class="w-20 h-20 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 flex flex-col items-center justify-center flex-shrink-0 text-white">
          <span v-if="c.type === 'cash'" class="text-2xl font-bold">¥</span>
          <span v-else-if="c.type === 'discount'" class="text-2xl font-bold">{{ (c.discount_rate * 10).toFixed(0) }}折</span>
          <span v-else class="text-lg font-bold">免运</span>
          <span v-if="c.type === 'cash' && c.money" class="text-xs opacity-80">满{{ c.min_amount }}用</span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-bold text-gray-800">{{ c.name }}</p>
          <p class="text-xs text-gray-400 mt-0.5">
            {{ c.start_time ? formatDate(c.start_time) : '立即可用' }}
            <span v-if="c.end_time"> ~ {{ formatDate(c.end_time) }}</span>
          </p>
          <p class="text-xs text-gray-400 mt-0.5" v-if="c.valid_days">领取后{{ c.valid_days }}天内有效</p>
          <div class="flex items-center justify-between mt-2">
            <span class="text-xs text-gray-400">剩余 {{ c.remain_count }}</span>
            <button v-if="c.can_get"
              @click="receiveCoupon(c.id)"
              :disabled="receiving === c.id"
              class="px-3 py-1 rounded-full bg-amber-500 text-white text-xs">
              {{ receiving === c.id ? '领取中...' : '立即领取' }}
            </button>
            <span v-else class="text-xs text-gray-400">{{ c.remain_count <= 0 ? '已领完' : '暂不可领' }}</span>
          </div>
        </div>
      </div>
      <div v-if="coupons.length === 0 && !loading" class="mt-8 text-center text-sm text-gray-400">
        — 暂无优惠券 —
      </div>
    </div>

    <!-- 我的优惠券 -->
    <div v-else-if="activeTab === 'my'" class="px-4">
      <div v-for="c in myCoupons" :key="c.user_coupon_id"
        class="rounded-xl mb-3 overflow-hidden"
        :class="couponCardClass(c.status)">
        <div class="p-4 flex gap-3">
          <div class="w-20 h-20 rounded-lg flex flex-col items-center justify-center flex-shrink-0 text-white">
            <span v-if="c.type === 'cash'" class="text-2xl font-bold">¥{{ c.money }}</span>
            <span v-else-if="c.type === 'discount'" class="text-2xl font-bold">{{ (c.discount_rate * 10).toFixed(0) }}折</span>
            <span v-else class="text-lg font-bold">免运</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-bold text-white">{{ c.name }}</p>
            <p class="text-xs text-white/70 mt-0.5" v-if="c.min_amount > 0">满{{ c.min_amount }}元可用</p>
            <p class="text-xs text-white/70 mt-0.5">
              {{ c.valid_start ? formatDate(c.valid_start) : '' }}
              <span v-if="c.valid_end"> ~ {{ formatDate(c.valid_end) }}</span>
            </p>
          </div>
          <div class="flex flex-col items-center justify-center">
            <span v-if="c.status === 'unused'" class="text-sm font-bold text-white">{{ c.valid_end && new Date(c.valid_end) < new Date() ? '已过期' : '未使用' }}</span>
            <span v-else-if="c.status === 'used'" class="text-sm text-white/60">已使用</span>
            <span v-else class="text-sm text-white/60">已过期</span>
          </div>
        </div>
      </div>
      <div v-if="myCoupons.length === 0 && !loading" class="mt-8 text-center text-sm text-gray-400">
        — 暂无优惠券 —
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const activeTab = ref('available')
const coupons = ref([])
const myCoupons = ref([])
const loading = ref(false)
const receiving = ref(null)

const tabs = [
  { key: 'available', label: '领券中心' },
  { key: 'my', label: '我的优惠券' },
]

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('zh-CN')
}

function couponCardClass(status) {
  if (status === 'unused') return 'bg-gradient-to-br from-amber-400 to-orange-500'
  if (status === 'used') return 'bg-gray-400'
  return 'bg-gray-300'
}

async function fetchCoupons() {
  loading.value = true
  try {
    if (activeTab.value === 'available') {
      const res = await api.get('/coupon/available', { params: { size: 50 } })
      if (res.code === 0) coupons.value = res.data.list || []
    } else {
      const res = await api.get('/coupon/my', { params: { size: 50 } })
      if (res.code === 0) myCoupons.value = res.data.list || []
    }
  } finally {
    loading.value = false
  }
}

async function receiveCoupon(id) {
  receiving.value = id
  try {
    const res = await api.post(`/coupon/${id}/receive`)
    if (res.code === 0) {
      ElMessage.success('领取成功')
      fetchCoupons()
    } else {
      ElMessage.error(res.message || '领取失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '领取失败')
  } finally {
    receiving.value = null
  }
}

onMounted(() => {
  fetchCoupons()
})
</script>
