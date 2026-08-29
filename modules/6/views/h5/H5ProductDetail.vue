<template>
  <div class="min-h-screen bg-white pb-24">
    <!-- 顶部栏 -->
    <div class="sticky top-0 bg-white/95 backdrop-blur-sm z-20 px-4 py-3 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <button @click="$router.back()" class="flex-shrink-0">
          <span class="material-symbols-outlined text-2xl text-slate-600">arrow_back</span>
        </button>
        <h2 class="text-lg font-bold text-slate-800">商品详情</h2>
      </div>
    </div>

    <div v-if="product">
      <!-- 商品主图 -->
      <div class="bg-slate-50 relative">
        <img v-if="product.image_url" :src="product.image_url" class="w-full aspect-square object-cover" />
        <div v-else class="w-full aspect-square flex items-center justify-center">
          <span class="material-symbols-outlined text-7xl text-slate-300" style="font-variation-settings: 'FILL' 1">image</span>
        </div>
        <!-- 标签 -->
        <span v-if="product.seckill_price" class="absolute top-4 left-4 bg-gradient-to-r from-red-500 to-orange-400 text-white text-xs font-bold px-3 py-1 rounded-full shadow-sm">限时秒杀</span>
      </div>

      <!-- 商品信息 -->
      <div class="px-4 pt-4">
        <div class="text-lg font-bold text-slate-800 leading-tight">{{ product.name }}</div>
        <div class="flex items-baseline gap-2 mt-3">
          <span class="text-3xl font-bold text-red-500">¥{{ product.sale_price || product.price }}</span>
          <span v-if="product.original_price" class="text-sm text-slate-400 line-through">¥{{ product.original_price }}</span>
        </div>
        <div v-if="product.description" class="mt-3 text-sm text-slate-500 leading-relaxed">{{ product.description }}</div>
        <div v-else class="mt-3 text-sm text-slate-300">暂无描述</div>

        <!-- 规格选择（占位） -->
        <div class="mt-6 bg-slate-50 rounded-2xl p-4">
          <div class="text-sm font-medium text-slate-700 mb-2">规格</div>
          <div class="flex gap-2">
            <span class="px-4 py-1.5 bg-white rounded-full text-xs text-slate-600 border border-slate-200">默认</span>
          </div>
        </div>

        <!-- 商品详情说明 -->
        <div class="mt-4 border-t border-slate-100 pt-4 pb-8">
          <div class="text-sm font-medium text-slate-700 mb-3 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-lg text-primary">description</span>
            商品详情
          </div>
          <div class="text-sm text-slate-400 leading-relaxed">
            优质商品，品质保障。如有疑问请联系客服。
          </div>
        </div>
      </div>
    </div>

    <!-- 底部操作栏 -->
    <div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-sm border-t border-slate-100 z-30 px-4 py-3 shadow-[0_-2px_12px_rgba(0,0,0,0.06)] safe-area-bottom">
      <div class="flex gap-3">
        <button @click="addToCart"
          class="flex-1 py-3 border-2 border-primary text-primary rounded-2xl font-semibold text-sm hover:bg-primary/5 transition-all active:scale-[0.98] flex items-center justify-center gap-1.5">
          <span class="material-symbols-outlined text-lg">add_shopping_cart</span>
          加入购物车
        </button>
        <button @click="buyNow"
          class="flex-1 py-3 bg-gradient-to-r from-primary to-blue-500 text-white rounded-2xl font-semibold text-sm shadow-sm shadow-primary/20 hover:shadow-md transition-all active:scale-[0.98] flex items-center justify-center gap-1.5">
          <span class="material-symbols-outlined text-lg">flash_on</span>
          立即购买
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const product = ref({})
const id = router.currentRoute.value.params.id

onMounted(() => {
  fetch('/api/mall/products/' + id)
    .then(r => r.json())
    .then(res => { if (res.code === 0) product.value = res.data })
    .catch(() => {})
})

function addToCart() {
  fetch('/api/mall/cart', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ product_id: id, quantity: 1 })
  })
    .then(r => r.json())
    .then(res => {
      if (res.code === 0) {
        // 触发购物车数量更新
        window.dispatchEvent(new CustomEvent('mall_cart_updated'))
        alert('已加入购物车')
      }
    })
    .catch(() => alert('加入失败'))
}

function buyNow() {
  router.push('/h5/checkout?items=' + encodeURIComponent(
    JSON.stringify([{ product_id: id, quantity: 1, price: product.value.sale_price || product.value.price }])
  ))
}
</script>

<style scoped>
.safe-area-bottom {
  padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>
