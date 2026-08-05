<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center">
      <button @click="$router.back()" class="flex-shrink-0"><span class="material-symbols-outlined text-2xl text-slate-600">arrow_back</span></button>
      <h2 class="text-lg font-semibold">个人资料</h2>
    </div>
    <div class="px-4 pt-4 space-y-3">
      <div class="bg-white rounded-xl p-4"><div class="text-sm font-medium mb-2">头像</div><div class="flex items-center gap-3"><div class="w-16 h-16 rounded-full bg-slate-200 flex items-center justify-center text-2xl">{{ user.name?.[0] }}</div><button class="text-sm text-primary">更换头像</button></div></div>
      <div class="bg-white rounded-xl p-4 space-y-3">
        <div class="flex items-center justify-between"><span class="text-sm w-16">昵称</span><input v-model="form.name" class="flex-1 bg-slate-50 rounded-lg px-3 py-2 text-sm"/></div>
        <div class="flex items-center justify-between"><span class="text-sm w-16">手机</span><input v-model="form.phone" type="tel" class="flex-1 bg-slate-50 rounded-lg px-3 py-2 text-sm"/></div>
      </div>
      <button @click="save" class="w-full py-3 bg-primary text-white rounded-full font-medium text-sm mt-4">保存</button>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
const user=ref({}), form=ref({name:'',phone:''})
onMounted(()=>{ fetch('/api/h5/me',{headers:{Authorization:'Bearer '+localStorage.getItem('mall_token')}}).then(r=>r.json()).then(r=>{ if(r.code===0){ user.value=r.data; form.value={name:r.data.name||'',phone:r.data.phone||''} } }) })
function save(){ fetch('/api/h5/profile',{method:'PUT',headers:{'Content-Type':'application/json',Authorization:'Bearer '+localStorage.getItem('mall_token')},body:JSON.stringify(form.value)}).then(r=>r.json()).then(r=>{ if(r.code===0){alert('保存成功');location.reload()}else{alert(r.message||'保存失败')} }) }
</script>