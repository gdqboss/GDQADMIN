<template>
  <div class="min-h-screen bg-slate-50 pb-24">
    <!-- 顶部栏 -->
    <div class="sticky top-0 bg-white/95 backdrop-blur-sm z-20 px-4 py-3 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <button @click="$router.back()" class="flex-shrink-0">
          <span class="material-symbols-outlined text-2xl text-slate-600">arrow_back</span>
        </button>
        <h2 class="text-lg font-bold text-slate-800">我的订单</h2>
      </div>
    </div>

    <!-- 状态标签 -->
    <div class="flex gap-1.5 bg-white px-4 py-3 border-b border-slate-100 overflow-x-auto" style="-webkit-overflow-scrolling:touch">
      <button v-for="s in statuses" :key="s.value" @click="status = s.value; load()"
        :class="['flex-shrink-0 px-4 py-1.5 rounded-full text-xs font-medium transition-all',
          status === s.value ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'bg-slate-100 text-slate-500 hover:bg-slate-200']">
        {{ s.label }}
      </button>
    </div>

    <!-- 订单列表 -->
    <div class="px-4 pt-3 space-y-3">
      <div v-for="o in orders" :key="o.id"
        @click="$router.push('/h5/order/' + o.id)"
        class="bg-white rounded-2xl p-4 shadow-sm hover:shadow-md transition-all cursor-pointer active:scale-[0.99]">
        <!-- 订单头部 -->
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center gap-1.5 text-xs text-slate-400">
            <span class="material-symbols-outlined text-sm">calendar_today</span>
            {{ o.created_at }}
          </div>
          <span class="text-xs font-medium px-2 py-0.5 rounded-full"
            :class="statusBadge(o.status).class">
            {{ statusBadge(o.status).label }}
          </span>
        </div>
        <!-- 商品信息 -->
        <div class="flex items-center gap-3">
          <div class="w-16 h-16 rounded-xl overflow-hidden bg-slate-100 flex-shrink-0">
            <img v-if="o.image_url" :src="o.image_url" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center">
              <span class="material-symbols-outlined text-2xl text-slate-300">image</span>
            </div>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-slate-700 line-clamp-1">{{ o.product_name || '商品' }}</div>
            <div class="text-xs text-slate-400 mt-0.5">{{ o.sku_name || '' }}</div>
          </div>
          <div class="text-right">
            <div class="text-sm font-bold text-red-500">¥{{ o.total_amount }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">x{{ o.quantity || 1 }}</div>
          </div>
        </div>
      </div>

      <!-- 空状态 -->
      <div v-if="!orders.length" class="flex flex-col items-center justify-center py-16 text-slate-300">
        <span class="material-symbols-outlined text-5xl mb-3" style="font-variation-settings: 'FILL' 1">receipt_long</span>
        <div class="text-sm">暂无订单</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const orders = ref([])
const status = ref('')

const statuses = [
  { value: '', label: '全部' },
  { value: 'pending_pay', label: '待付款' },
  { value: 'paid', label: '已付款' },
  { value: 'shipped', label: '待收货' },
  { value: 'completed', label: '已完成' },
]

const badgeMap = {
  pending_pay: { label: '待付款', class: 'bg-orange-50 text-orange-500 border border-orange-200' },
  paid: { label: '已付款', class: 'bg-blue-50 text-blue-500 border border-blue-200' },
  shipped: { label: '待收货', class: 'bg-purple-50 text-purple-500 border border-purple-200' },
  completed: { label: '已完成', class: 'bg-green-50 text-green-500 border border-green-200' },
  cancelled: { label: '已取消', class: 'bg-slate-50 text-slate-400 border border-slate-200' },
}

function statusBadge(s) {
  return badgeMap[s] || { label: s, class: 'bg-slate-50 text-slate-500 border border-slate-200' }
}

onMounted(load)

function load() {
  fetch('/api/mall/orders?' + new URLSearchParams({ status: status.value }), {
    headers: { Authorization: 'Bearer ' + localStorage.getItem('mall_token') }
  })
    .then(r => r.json())
    .then(res => {
      if (res.code === 0) orders.value = res.data?.list || []
    })
    .catch(() => { orders.value = [] })
}
</script>
