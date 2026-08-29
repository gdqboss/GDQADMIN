<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center">
      <button @click="$router.back()" class="flex-shrink-0"><span class="material-symbols-outlined text-2xl text-slate-600">arrow_back</span></button>
      <h2 class="text-lg font-semibold">修改密码</h2>
    </div>
    <div class="px-4 pt-4 space-y-3">
      <div class="bg-white rounded-xl p-4 space-y-3">
        <div class="text-sm"><span class="w-24 inline-block">原密码</span><input v-model="form.old" type="password" class="bg-slate-50 rounded-lg px-3 py-2 text-sm flex-1" placeholder="请输入原密码"/></div>
        <div class="text-sm"><span class="w-24 inline-block">新密码</span><input v-model="form.new" type="password" class="bg-slate-50 rounded-lg px-3 py-2 text-sm flex-1" placeholder="请输入新密码"/></div>
        <div class="text-sm"><span class="w-24 inline-block">确认密码</span><input v-model="form.confirm" type="password" class="bg-slate-50 rounded-lg px-3 py-2 text-sm flex-1" placeholder="请再次输入新密码"/></div>
      </div>
      <button @click="save" class="w-full py-3 bg-primary text-white rounded-full font-medium text-sm mt-4">确认修改</button>
    </div>
  </div>
</template>
<script setup>
import { ref } from 'vue'
const form=ref({old:'',new:'',confirm:''})
function save(){ if(form.value.new!==form.value.confirm){alert('两次密码不一致');return} fetch('/api/h5/change-password',{method:'PUT',headers:{'Content-Type':'application/json',Authorization:'Bearer '+localStorage.getItem('mall_token')},body:JSON.stringify({oldPassword:form.value.old,newPassword:form.value.new})}).then(r=>r.json()).then(r=>{ if(r.code===0){alert('修改成功');location.reload()}else{alert(r.message||'修改失败')} }) }
</script>