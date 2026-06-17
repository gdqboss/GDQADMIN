<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center">
      <button @click="$router.back()" class="mr-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
      <h2 class="text-lg font-semibold">{{ isEdit?'编辑地址':'新增地址' }}</h2>
    </div>
    <div class="px-4 pt-4 space-y-3">
      <div class="bg-white rounded-xl p-4 space-y-3">
        <div class="flex items-center justify-between"><span class="text-sm w-16">收货人</span><input v-model="form.consignee" class="flex-1 bg-slate-50 rounded-lg px-3 py-2 text-sm" placeholder="姓名"/></div>
        <div class="flex items-center justify-between"><span class="text-sm w-16">手机号</span><input v-model="form.phone" type="tel" class="flex-1 bg-slate-50 rounded-lg px-3 py-2 text-sm" placeholder="手机号码"/></div>
        <div class="flex items-center justify-between"><span class="text-sm w-16">地区</span><input v-model="form.district" class="flex-1 bg-slate-50 rounded-lg px-3 py-2 text-sm" placeholder="省市区"/></div>
        <div class="flex items-center justify-between"><span class="text-sm w-16">详细地址</span><textarea v-model="form.detail" rows="2" class="flex-1 bg-slate-50 rounded-lg px-3 py-2 text-sm resize-none" placeholder="街道门牌号"/></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.is_default" class="accent-primary"/>设为默认地址</label>
      </div>
      <button @click="save" class="w-full py-3 bg-primary text-white rounded-full font-medium text-sm mt-4">{{ isEdit?'保存修改':'添加地址' }}</button>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
const router=useRouter(), isEdit=ref(false), id=ref(null)
const form=ref({consignee:'',phone:'',province:'广东省',city:'深圳市',district:'',detail:'',is_default:false})
onMounted(()=>{ const q=router.currentRoute.value.query; if(q.id){id.value=q.id;isEdit.value=true;fetch('/api/mall/addresses/'+id.value,{headers:{Authorization:'Bearer '+localStorage.getItem('mall_token')}}).then(r=>r.json()).then(r=>{ if(r.code===0) form.value=r.data }) } })
function save(){ if(!form.value.consignee||!form.value.phone){alert('请填写完整');return} const method=isEdit.value?'PUT':'POST',url=isEdit.value?'/api/mall/addresses/'+id.value:'/api/mall/addresses',body=JSON.stringify(form.value); fetch(url,{method,headers:{'Content-Type':'application/json',Authorization:'Bearer '+localStorage.getItem('mall_token')},body}).then(r=>r.json()).then(r=>{ if(r.code===0) router.back();else alert(r.message||'保存失败') }) }
</script>