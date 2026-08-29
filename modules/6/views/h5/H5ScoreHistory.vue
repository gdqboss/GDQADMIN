<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center">
      <button @click="$router.back()" class="flex-shrink-0"><span class="material-symbols-outlined text-2xl text-slate-600">arrow_back</span></button>
      <h2 class="text-lg font-semibold">积分记录</h2>
    </div>
    <div class="px-4 pt-3 space-y-2">
      <div v-for="l in logs" :key="l.id" class="bg-white rounded-xl p-4 flex justify-between items-center">
        <div><div class="text-sm font-medium">{{ l.type_label }}</div><div class="text-xs text-slate-500 mt-1">{{ l.created_at }}</div></div>
        <div :class="['font-bold',l.change>0?'text-green-500':'text-red-500']">{{ l.change>0?'+':'' }}{{ l.change }}</div>
      </div>
      <div v-if="!logs.length" class="text-center py-12 text-slate-400 text-sm">暂无记录</div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
const logs=ref([])
onMounted(()=>{ fetch('/api/member-level/score-log',{headers:{Authorization:'Bearer '+localStorage.getItem('mall_token')}}).then(r=>r.json()).then(r=>{ if(r.code===0) logs.value=r.data||[] }).catch(()=>{}) })
</script>