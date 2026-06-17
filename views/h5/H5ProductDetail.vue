<template>
  <div class="min-h-screen bg-white pb-24">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center">
      <button @click="$router.back()" class="mr-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
      <h2 class="text-lg font-semibold">商品详情</h2>
    </div>
    <div v-if="product">
      <img :src="product.image_url||'/images/placeholder.png'" class="w-full aspect-square bg-slate-100" />
      <div class="p-4">
        <div class="text-xl font-bold">{{ product.name }}</div>
        <div class="text-red-500 text-2xl font-bold mt-2">¥{{ product.sale_price || product.price }}</div>
        <div class="text-sm text-slate-500 mt-1">{{ product.description || '暂无描述' }}</div>
      </div>
    </div>
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t z-30 px-4 py-3 flex gap-3">
      <button @click="addToCart" class="flex-1 py-3 border border-primary text-primary rounded-full font-medium text-sm">加入购物车</button>
      <button @click="buyNow" class="flex-1 py-3 bg-primary text-white rounded-full font-medium text-sm">立即购买</button>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter(), product = ref({}), id = router.currentRoute.value.params.id
onMounted(()=>{ fetch('/api/mall/products/'+id).then(r=>r.json()).then(r=>{ if(r.code===0) product.value=r.data }) })
function addToCart(){ fetch('/api/mall/cart',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({product_id:id,quantity:1})}).then(r=>r.json()).then(r=>{ if(r.code===0) alert('已加入购物车') }) }
function buyNow(){ router.push('/h5/checkout?items='+encodeURIComponent(JSON.stringify([{product_id:id,quantity:1,price:product.value.sale_price||product.value.price}]))) }
</script>