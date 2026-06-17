<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center justify-between">
      <button @click="$router.back()" class="mr-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
      <h2 class="text-lg font-semibold">我的订单</h2>
      <div class="w-8"></div>
    </div>
    <div class="flex gap-1 bg-white px-4 py-2 border-b">
      <button v-for="s in statuses" :key="s.value" @click="status=s.value;load()"
        :class="['px-3 py-1 rounded-full text-xs transition-all',
          status===s.value?'bg-primary text-white':'text-slate-500']">{{ s.label }}</button>
    </div>
    <div class="px-4 pt-3 space-y-3">
      <div v-for="o in orders" :key="o.id" @click="$router.push('/h5/order/'+o.id)"
        class="bg-white rounded-xl p-4 cursor-pointer">
        <div class="flex justify-between text-xs text-slate-500 mb-2">
          <span>{{ o.created_at }}</span>
          <span :class="{'text-red-500':o.status==='pending_pay','text-green-500':o.status==='paid'}">{{ statusLabel(o.status) }}</span>
        </div>
        <div class="text-sm font-medium">{{ o.product_name || '商品' }} x{{ o.quantity || 1 }}</div>
        <div class="text-right mt-2 text-red-500 font-bold">¥{{ o.total_amount }}</div>
      </div>
      <div v-if="!orders.length" class="text-center py-12 text-slate-400 text-sm">暂无订单</div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
const orders=ref([]), status=ref('')
const statuses=[{value:'',label:'全部'},{value:'pending_pay',label:'待付款'},{value:'paid',label:'已付款'},{value:'shipped',label:'待收货'},{value:'completed',label:'已完成'}]
onMounted(load)
function load(){ fetch('/api/mall/orders?'+new URLSearchParams({status:status.value}),{headers:{Authorization:'Bearer '+localStorage.getItem('mall_token')}}).then(r=>r.json()).then(r=>{ if(r.code===0) orders.value=r.data.list||[] }) }
function statusLabel(s){ return{pending_pay:'待付款',paid:'已付款',shipped:'待收货',completed:'已完成',cancelled:'已取消'}[s]||s }
</script>