<template>
  <div class="pb-20">
    <div v-if="loading" class="text-center py-20">
      <span class="material-symbols-outlined text-4xl animate-spin text-gray-400">progress_activity</span>
    </div>
    <div v-else-if="!product.id" class="text-center py-20">
      <span class="material-symbols-outlined text-5xl text-gray-300">error</span>
      <p class="mt-2 text-gray-500">商品不存在</p>
    </div>
    <div v-else>
      <!-- 商品图片 -->
      <div class="aspect-square bg-gray-100">
        <img v-if="mainImage" :src="'/' + mainImage" class="w-full h-full object-cover" @error="e => e.target.style.display='none'" />
        <div v-else class="flex items-center justify-center h-full text-gray-300">
          <span class="material-symbols-outlined text-6xl">image</span>
        </div>
      </div>

      <!-- 图片画廊 -->
      <div v-if="images.length > 1" class="flex gap-2 p-3 overflow-x-auto">
        <div v-for="(img, i) in images" :key="i"
          class="w-16 h-16 flex-shrink-0 rounded-lg overflow-hidden border-2"
          :class="mainImage === img ? 'border-blue-500' : 'border-transparent'"
          @click="mainImage = img">
          <img :src="'/' + img" class="w-full h-full object-cover" @error="e => e.target.parentElement.style.display='none'" />
        </div>
      </div>

      <!-- 价格和名称 -->
      <div class="bg-white p-4">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-2xl font-bold text-red-500">¥{{ product.sale_price || '--' }}</p>
            <p v-if="product.stock !== null" class="text-xs text-gray-400 mt-1">库存 {{ product.stock }} 件</p>
          </div>
          <span v-if="product.category_name" class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ product.category_name }}</span>
        </div>
        <h1 class="mt-3 font-bold text-base leading-tight">{{ product.name }}</h1>
        <p v-if="product.spec" class="mt-2 text-sm text-gray-500">{{ product.spec }}</p>
      </div>

      <!-- 规格 -->
      <div v-if="product.unit" class="bg-white mt-2 p-4">
        <p class="text-sm text-gray-500">单位：{{ product.unit }}</p>
      </div>
    </div>

    <!-- 底部购买按钮 -->
    <div v-if="product.id" class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-3 flex gap-3">
      <button @click="goBack"
        class="flex-1 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium flex items-center justify-center gap-1">
        <span class="material-symbols-outlined text-lg">arrow_back</span> 返回
      </button>
      <button @click="addToCart"
        :disabled="product.stock === 0"
        class="flex-1 py-3 rounded-xl bg-blue-600 text-white text-sm font-medium flex items-center justify-center gap-1 disabled:bg-gray-300">
        <span class="material-symbols-outlined text-lg">shopping_cart</span>
        加入购物车
      </button>
      <button @click="buyNow"
        :disabled="product.stock === 0"
        class="flex-1 py-3 rounded-xl bg-red-500 text-white text-sm font-medium disabled:bg-gray-300">
        立即购买
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
const route = useRoute()
const router = useRouter()

const product = ref({})
const mainImage = ref('')
const images = ref([])
const loading = ref(true)

function goBack() { window.history.length > 1 ? router.back() : router.push('/mall') }
function addToCart() {
  const cart = JSON.parse(localStorage.getItem('mall_cart') || '[]')
  const exist = cart.find(i => i.id === product.value.id)
  if (exist) exist.qty++
  else cart.push({ id: product.value.id, name: product.value.name, price: product.value.sale_price, qty: 1, sku: product.value.sku || '', image: product.value.image_main })
  localStorage.setItem('mall_cart', JSON.stringify(cart))
  window.dispatchEvent(new Event('mall_cart_updated'))
  alert('已加入购物车')
}

function buyNow() {
  addToCart()
  router.push('/mall/cart')
}

onMounted(async () => {
  loading.value = true
  try {
    const res = await fetch(`/api/store-mall/products/${route.params.id}`)
    const data = await res.json()
    product.value = data
    mainImage.value = data.image_main || ''
    if (data.images?.length) images.value = data.images
    else if (data.image_main) images.value = [data.image_main]
  } finally {
    loading.value = false
  }
})
</script>