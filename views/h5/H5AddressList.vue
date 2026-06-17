<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center justify-between">
      <div class="flex items-center"><button @click="$router.back()" class="mr-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button><h2 class="text-lg font-semibold">收货地址</h2></div>
      <button @click="$router.push('/h5/address/edit')" class="text-primary text-sm">+ 新增</button>
    </div>
    <div class="px-4 pt-3 space-y-3">
      <div v-for="a in addresses" :key="a.id" class="bg-white rounded-xl p-4">
        <div class="flex justify-between"><div class="font-medium">{{ a.consignee }} {{ a.phone }}</div><button v-if="a.is_default" class="text-xs text-primary border border-primary px-2 py-0.5 rounded">默认</button></div>
        <div class="text-sm text-slate-500 mt-2">{{ a.province }} {{ a.city }} {{ a.district }} {{ a.detail }}</div>
        <div class="flex justify-end gap-3 mt-2 text-sm text-slate-500"><button @click="$router.push('/h5/address/edit?id='+a.id)">编辑</button><button @click="del(a.id)" class="text-red-500">删除</button></div>
      </div>
      <div v-if="!addresses.length" class="text-center py-12 text-slate-400 text-sm">暂无地址</div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
const addresses = ref([])
onMounted(() => { fetch('/api/mall/addresses',{headers:{Authorization:'Bearer '+localStorage.getItem('mall_token')}}).then(r=>r.json()).then(r=>{ if(r.code===0) addresses.value=r.data||[] }) })
function del(id){ if(!confirm('删除？'))return; fetch('/api/mall/addresses/'+id,{method:'DELETE',headers:{Authorization:'Bearer '+localStorage.getItem('mall_token')}}).then(r=>r.json()).then(r=>{ if(r.code===0) addresses.value=addresses.value.filter(a=>a.id!==id) }) }
</script>