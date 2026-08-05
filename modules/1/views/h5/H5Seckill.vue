<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center">
      <button @click="$router.back()" class="flex-shrink-0"><span class="material-symbols-outlined text-2xl text-slate-600">arrow_back</span></button>
      <h2 class="text-lg font-semibold">限时秒杀</h2>
    </div>
    <div v-if="activity" class="px-4 pt-3">
      <div class="bg-gradient-to-r from-red-500 to-orange-400 rounded-xl p-4 text-white mb-4">
        <div class="font-bold text-lg">{{ activity.name }}</div>
        <div class="text-xs opacity-80 mt-1">{{ activity.start_time }} ~ {{ activity.end_time }}</div>
      </div>
      <div class="space-y-3">
        <div v-for="p in products" :key="p.id" class="bg-white rounded-xl p-3 flex gap-3">
          <img :src="p.image_url||'/images/placeholder.png'" class="w-24 h-24 rounded-lg object-cover bg-slate-100"/>
          <div class="flex-1">
            <div class="text-sm font-medium line-clamp-2">{{ p.product_name }}</div>
            <div class="flex items-baseline gap-1 mt-1">
              <span class="text-red-500 font-bold text-lg">¥{{ p.seckill_price }}</span>
              <span class="text-slate-400 text-xs line-through">¥{{ p.original_price }}</span>
            </div>
            <div class="text-xs text-slate-500 mt-1">剩余 {{ p.available_stock }} 件</div>
            <button @click="buySeckill(p)" :disabled="p.available_stock<=0"
              class="mt-2 px-4 py-1.5 bg-red-500 text-white rounded-full text-xs disabled:opacity-50">
              {{ p.available_stock<=0?'已售罄':'立即抢购' }}
            </button>
          </div>
        </div>
      </div>
    </div>
    <div v-else class="text-center py-20 text-slate-400 text-sm">暂无进行中的秒杀活动</div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
const router=useRouter(), activity=ref(null), products=ref([])
onMounted(()=>{ fetch('/api/seckill/active').then(r=>r.json()).then(r=>{ if(r.code===0&&r.data){ activity.value=r.data.activity; products.value=r.data.products||[] } }) })
function buySeckill(p){ fetch('/api/seckill/orders',{method:'POST',headers:{'Content-Type':'application/json',Authorization:'Bearer '+localStorage.getItem('mall_token')},body:JSON.stringify({activity_id:p.activity_id||p.id,product_id:p.product_id,quantity:1})}).then(r=>r.json()).then(r=>{ if(r.code===0){ router.push('/h5/order-pay?order_no='+r.data.order_no+'&amount='+r.data.total_amount) } else { alert(r.message) } }) }
</script>