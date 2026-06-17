<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center">
      <button @click="$router.back()" class="mr-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
      <h2 class="text-lg font-semibold">我的优惠券</h2>
    </div>
    <div class="flex gap-1 bg-white px-4 py-2 border-b">
      <button v-for="s in tabs" :key="s.v" @click="tab=s.v;load()"
        :class="['px-3 py-1 rounded-full text-xs transition-all',tab===s.v?'bg-primary text-white':'text-slate-500']">{{ s.l }}</button>
    </div>
    <div class="px-4 pt-3 space-y-3">
      <div v-for="c in coupons" :key="c.id" :class="['bg-white rounded-xl p-4 flex gap-3 border-l-4',c.status==='available'?'border-red-500':'border-slate-300']">
        <div class="flex-1">
          <div class="font-bold text-red-500 text-xl">¥{{ c.discount }}</div>
          <div class="text-xs text-slate-500 mt-1">{{ c.rule_name || ('满' + c.min_amount + '元可用') }}</div>
        </div>
        <div class="flex flex-col justify-between items-end">
          <div :class="['text-xs px-2 py-0.5 rounded',c.status==='available'?'bg-red-50 text-red-500':'bg-slate-100 text-slate-400']">{{ c.status==='available'?'可用':'已用' }}</div>
          <button v-if="c.status==='available'" @click="$router.push('/h5/home')" class="text-xs text-primary">去使用 →</button>
        </div>
      </div>
      <div v-if="!coupons.length" class="text-center py-12 text-slate-400 text-sm">暂无优惠券</div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
const coupons=ref([]), tab=ref('available')
const tabs=[{v:'available',l:'可用'},{v:'used',l:'已使用'},{v:'expired',l:'已过期'}]
onMounted(load)
function load(){ fetch('/api/mall/user-coupons?status='+tab.value,{headers:{Authorization:'Bearer '+localStorage.getItem('mall_token')}}).then(r=>r.json()).then(r=>{ if(r.code===0) coupons.value=r.data||[] }) }
</script>