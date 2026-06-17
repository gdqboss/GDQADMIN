<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center">
      <button @click="$router.back()" class="mr-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
      <h2 class="text-lg font-semibold">绑定手机</h2>
    </div>
    <div class="px-4 pt-4 space-y-3">
      <div class="bg-white rounded-xl p-4 space-y-3">
        <div class="text-sm">手机号</div>
        <input v-model="form.phone" type="tel" class="w-full bg-slate-50 rounded-lg px-4 py-3 text-sm" placeholder="请输入手机号"/>
        <div class="flex gap-2">
          <input v-model="form.code" class="flex-1 bg-slate-50 rounded-lg px-3 py-3 text-sm" placeholder="验证码"/>
          <button @click="sendCode" :disabled="countdown>0" class="px-4 py-3 bg-slate-100 rounded-lg text-sm disabled:opacity-50">{{ countdown>0?countdown+'s':'获取验证码' }}</button>
        </div>
      </div>
      <button @click="bind" class="w-full py-3 bg-primary text-white rounded-full font-medium text-sm mt-4">确认绑定</button>
    </div>
  </div>
</template>
<script setup>
import { ref } from 'vue'
const form=ref({phone:'',code:''}), countdown=ref(0)
function sendCode() {
  if (!form.value.phone) return
  fetch('/api/h5/send-sms', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone: form.value.phone, type: 'bind' })
  }).then(r => r.json()).then(r => {
    if (r.code === 0) {
      countdown.value = 60
      const timer = setInterval(() => { if (countdown.value > 0) countdown.value--; else clearInterval(timer) }, 1000)
    } else {
      alert(r.message || '发送失败')
    }
  })
}
function bind() {
  fetch('/api/h5/bind-phone', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + localStorage.getItem('mall_token') },
    body: JSON.stringify(form.value)
  }).then(r => r.json()).then(r => {
    if (r.code === 0) { alert('绑定成功'); location.reload() } else { alert(r.message || '绑定失败') }
  })
}
</script>