<template>
  <div class="min-h-screen bg-slate-50">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center">
      <button @click="$router.back()" class="mr-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
      <h2 class="text-lg font-semibold">积分明细</h2>
    </div>
    <div class="text-center py-16"><div class="text-4xl font-bold text-yellow-500">{{ score }}</div><div class="text-sm text-slate-500 mt-2">当前积分</div></div>
    <div class="px-4 space-y-2"><div v-for="l in logs" :key="l.id" class="bg-white rounded-xl p-4 flex justify-between"><div class="text-sm">{{ l.label }}</div><div :class="['font-bold',l.change>0?'text-green-500':'text-red-500']">{{ l.change>0?'+':'' }}{{ l.change }}</div></div><div v-if="!logs.length" class="text-center py-8 text-slate-400 text-sm">暂无记录</div></div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
const score=ref(0), logs=ref([])
onMounted(()=>{ fetch('/api/member-level/my',{headers:{Authorization:'Bearer '+localStorage.getItem('mall_token')}}).then(r=>r.json()).then(r=>{ if(r.code===0){ score.value=r.data.score||0; logs.value=r.data.logs||[] } }).catch(()=>{}) })
</script>