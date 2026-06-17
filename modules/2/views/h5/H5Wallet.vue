<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center">
      <button @click="$router.back()" class="mr-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
      <h2 class="text-lg font-semibold">我的钱包</h2>
    </div>
    <div class="bg-gradient-to-r from-primary to-blue-500 px-4 pt-8 pb-10 text-white text-center">
      <div class="text-sm opacity-80">账户余额（元）</div>
      <div class="text-4xl font-bold mt-2">{{ balance }}</div>
    </div>
    <div class="flex gap-4 px-4 -mt-6">
      <button @click="type='recharge';showModal=true" class="flex-1 py-3 bg-white rounded-xl shadow text-center text-sm font-medium">充值</button>
      <button @click="type='withdraw';showModal=true" class="flex-1 py-3 bg-white rounded-xl shadow text-center text-sm font-medium">提现</button>
    </div>
    <div class="px-4 mt-4">
      <div class="text-sm font-medium mb-2">最近交易</div>
      <div class="space-y-2">
        <div v-for="l in logs" :key="l.id" class="bg-white rounded-xl p-4 flex justify-between items-center">
          <div><div class="text-sm">{{ l.type_label }}</div><div class="text-xs text-slate-500 mt-1">{{ l.created_at }}</div></div>
          <div :class="['font-bold',l.change>0?'text-green-500':'text-red-500']">{{ l.change>0?'+':'' }}{{ l.change }}</div>
        </div>
        <div v-if="!logs.length" class="text-center py-8 text-slate-400 text-sm">暂无记录</div>
      </div>
    </div>
    <div v-if="showModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showModal=false">
      <div class="bg-white rounded-2xl w-full max-w-sm p-6">
        <h3 class="font-semibold mb-4">{{ type==='recharge'?'充值':'提现' }}</h3>
        <input v-model="amount" type="number" :placeholder="type==='recharge'?'输入充值金额':'输入提现金额'" class="w-full bg-slate-50 rounded-xl px-4 py-3 text-lg mb-4"/>
        <div v-if="type==='recharge'" class="grid grid-cols-3 gap-2 mb-4">
          <button v-for="a in [100,200,500,1000,2000,5000]" :key="a" @click="amount=a" :class="['py-2 rounded-lg text-sm border',amount==a?'border-primary bg-primary/5 text-primary':'border-slate-200']">¥{{a}}</button>
        </div>
        <button @click="confirm" :disabled="!amount" class="w-full py-3 bg-primary text-white rounded-full disabled:opacity-50">{{ type==='recharge'?'立即充值':'申请提现' }}</button>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
const balance=ref('0.00'), logs=ref([]), amount=ref(''), showModal=ref(false), type=ref('recharge')
onMounted(()=>{ fetch('/api/wallet/my',{headers:{Authorization:'Bearer '+localStorage.getItem('mall_token')}}).then(r=>r.json()).then(r=>{ if(r.code===0){ balance.value=r.data.balance||'0.00'; logs.value=r.data.logs||[] } }).catch(()=>{}) })
function confirm(){ if(!amount.value||parseFloat(amount.value)<=0)return; const action=type.value==='recharge'?'recharge':'withdraw'; fetch('/api/wallet/'+action,{method:'POST',headers:{'Content-Type':'application/json',Authorization:'Bearer '+localStorage.getItem('mall_token')},body:JSON.stringify({amount:amount.value})}).then(r=>r.json()).then(r=>{ if(r.code===0){ alert(type.value==='recharge'?'充值成功':'申请已提交');showModal.value=false;location.reload() }else{alert(r.message||'操作失败')} }) }
</script>