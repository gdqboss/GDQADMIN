<template>
  <div class="min-h-screen bg-slate-50 pb-24">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b text-center">
      <h2 class="text-lg font-semibold">订单支付</h2>
    </div>
    <div class="mx-4 mt-6 bg-white rounded-xl p-6 text-center">
      <div class="text-sm text-slate-500 mb-2">订单号</div>
      <div class="font-mono text-sm mb-4">{{ orderNo }}</div>
      <div class="text-sm text-slate-500 mb-2">应付金额</div>
      <div class="text-3xl text-red-500 font-bold">¥{{ amount }}</div>
    </div>
    <div class="mx-4 mt-4 bg-white rounded-xl p-4">
      <div class="text-sm font-medium mb-3">选择支付方式</div>
      <div v-for="m in payMethods" :key="m.value" @click="selectedPay=m.value"
        :class="['flex items-center gap-3 p-3 rounded-xl mb-2 cursor-pointer border transition-all',
          selectedPay===m.value?'border-primary bg-primary/5':'border-slate-100']">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center" :style="{background:m.color}">
          <span class="text-white text-xs font-bold">{{ m.label[0] }}</span>
        </div>
        <div class="flex-1 text-sm">{{ m.label }}</div>
        <div v-if="selectedPay===m.value" class="w-5 h-5 rounded-full bg-primary flex items-center justify-center">
          <span class="material-symbols-outlined text-xs text-white">check</span>
        </div>
      </div>
    </div>
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t z-30 px-4 py-3">
      <button @click="doPay" :disabled="paying"
        class="w-full py-3 bg-primary text-white rounded-full font-medium text-sm disabled:opacity-50">
        {{ paying ? '支付中...' : '确认支付 ¥' + amount }}
      </button>
    </div>
  </div>
</template>
<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter(), orderNo = ref(router.currentRoute.value.query.order_no||''), amount = ref(router.currentRoute.value.query.amount||'0'), selectedPay = ref('wxpay'), paying = ref(false)
const payMethods = [
  {value:'wxpay',label:'微信支付',color:'#07C160'},
  {value:'alipay',label:'支付宝',color:'#1677FF'},
]
function doPay(){ paying.value=true; fetch('/api/pay/create',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({orderNo:orderNo.value,amount:amount.value,description:'订单'+orderNo.value})}).then(r=>r.json()).then(r=>{ if(r.code===0){ if(r.data.code_url){ location.href=r.data.code_url } else { router.push('/h5/orders') } } else { alert(r.message||'发起支付失败') } paying.value=false }) }
</script>