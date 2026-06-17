<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center">
      <button @click="$router.back()" class="mr-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
      <h2 class="text-lg font-semibold">订单详情</h2>
    </div>
    <div v-if="order" class="px-4 pt-4 space-y-3">
      <div class="bg-white rounded-xl p-4">
        <div class="text-xs text-slate-500 mb-1">订单状态</div>
        <div :class="['text-lg font-bold',order.status==='paid'?'text-green-500':'text-red-500']">{{ statusLabel(order.status) }}</div>
      </div>
      <div class="bg-white rounded-xl p-4">
        <div class="text-xs text-slate-500 mb-2">商品信息</div>
        <div class="flex gap-3">
          <img :src="order.image_url||'/images/placeholder.png'" class="w-16 h-16 rounded-lg object-cover bg-slate-100"/>
          <div class="flex-1">
            <div class="text-sm font-medium">{{ order.product_name }}</div>
            <div class="text-xs text-slate-400 mt-1">x{{ order.quantity }}</div>
            <div class="text-right text-red-500 font-bold mt-1">¥{{ order.total_amount }}</div>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-xl p-4">
        <div class="text-xs text-slate-500 mb-2">订单信息</div>
        <div class="text-xs space-y-1 text-slate-600">
          <div>订单号：{{ order.order_no }}</div>
          <div>下单时间：{{ order.created_at }}</div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
const router=useRouter(), order=ref({})
onMounted(()=>{ const id=router.currentRoute.value.params.id; fetch('/api/mall/orders/'+id,{headers:{Authorization:'Bearer '+localStorage.getItem('mall_token')}}).then(r=>r.json()).then(r=>{ if(r.code===0) order.value=r.data }) })
function statusLabel(s){ return{pending_pay:'待付款',paid:'已付款',shipped:'待收货',completed:'已完成',cancelled:'已取消'}[s]||s }
</script>