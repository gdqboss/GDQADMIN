<template>
  <div class="pb-4">
    <!-- 轮播 Banner -->
    <div v-if="banners.length" class="relative rounded-2xl overflow-hidden shadow-sm mb-4">
      <div class="relative overflow-hidden" @touchstart="touchStart" @touchend="touchEnd">
        <div class="flex transition-transform duration-300 ease-out" :style="{ transform: `translateX(-${bannerIndex * 100}%)` }">
          <div v-for="(b, i) in banners" :key="i" class="w-full flex-shrink-0">
            <img v-if="b.image_url" :src="b.image_url" class="w-full aspect-[2/1] object-cover" @error="e => e.target.style.display='none'" />
            <div v-else class="w-full aspect-[2/1] bg-gradient-to-br from-primary/80 to-blue-500 flex items-center justify-center">
              <div class="text-center text-white p-6">
                <span class="material-symbols-outlined text-5xl mb-2" style="font-variation-settings: 'FILL' 1">{{ b.icon || 'campaign' }}</span>
                <div class="text-lg font-bold">{{ b.title || '欢迎光临' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
        <span v-for="(b, i) in banners" :key="i"
          class="w-1.5 h-1.5 rounded-full transition-all duration-300"
          :class="i === bannerIndex ? 'bg-white w-4' : 'bg-white/40'"></span>
      </div>
    </div>

    <!-- 分类快捷入口 -->
    <div class="mb-4">
      <div class="grid grid-cols-5 gap-2">
        <div v-for="cat in topCategories" :key="cat.id"
          class="flex flex-col items-center gap-1.5 p-2 bg-white rounded-2xl shadow-sm hover:shadow-md transition-all cursor-pointer active:scale-95"
          @click="$router.push(`/mall/category/${cat.id}`)">
          <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white text-xl"
            :style="{ background: cat.color || 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' }">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1">{{ cat.icon || 'category' }}</span>
          </div>
          <span class="text-[11px] text-gray-600 text-center leading-tight line-clamp-1">{{ cat.name }}</span>
        </div>
      </div>
    </div>

    <!-- 商品列表 -->
    <div>
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-bold text-base text-gray-800">热门商品</h2>
        <span class="text-xs text-gray-400">共 {{ total }} 件</span>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div v-for="p in products" :key="p.id"
          class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all active:scale-[0.98] cursor-pointer"
          @click="$router.push(`/mall/product/${p.id}`)">
          <div class="aspect-square bg-gray-100 relative overflow-hidden">
            <img v-if="p.image_main || p.image_url"
              :src="p.image_main ? '/' + p.image_main : p.image_url"
              class="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
              @error="handleImgError" />
            <div v-else class="w-full h-full flex items-center justify-center">
              <span class="material-symbols-outlined text-5xl text-gray-300" style="font-variation-settings: 'FILL' 1">image</span>
            </div>
            <span v-if="p.stock == 0" class="absolute inset-0 bg-black/40 flex items-center justify-center">
              <span class="bg-white/90 text-xs px-2 py-0.5 rounded-full text-gray-600">已售罄</span>
            </span>
          </div>
          <div class="p-2.5">
            <p class="text-sm text-gray-800 font-medium line-clamp-2 leading-tight min-h-[2.5em]">{{ p.name }}</p>
            <div class="mt-1.5 flex items-baseline gap-1">
              <span class="text-red-500 font-bold text-base">¥{{ p.sale_price || p.price || '--' }}</span>
            </div>
            <p v-if="p.category_name" class="text-[10px] text-gray-400 mt-1">{{ p.category_name }}</p>
          </div>
        </div>
      </div>

      <!-- 加载状态 -->
      <div v-if="loading" class="pt-6 pb-4">
        <div class="flex justify-center gap-1">
          <span class="w-2 h-2 bg-primary rounded-full animate-bounce" style="animation-delay:0ms"></span>
          <span class="w-2 h-2 bg-primary rounded-full animate-bounce" style="animation-delay:150ms"></span>
          <span class="w-2 h-2 bg-primary rounded-full animate-bounce" style="animation-delay:300ms"></span>
        </div>
      </div>
      <div v-if="hasMore" class="mt-4 text-center">
        <button @click="loadMore" :disabled="loading"
          class="px-6 py-2 bg-white border border-gray-200 rounded-full text-sm text-gray-500 hover:border-primary/30 hover:text-primary transition-all disabled:opacity-50 active:scale-95">
          {{ loading ? '加载中...' : '加载更多' }}
        </button>
      </div>
      <div v-else-if="products.length > 0" class="mt-4 text-center text-xs text-gray-300">
        — 已经到底了 —
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const products = ref([])
const topCategories = ref([])
const banners = ref([])
const page = ref(1)
const total = ref(0)
const hasMore = ref(false)
const loading = ref(false)

// 轮播
const bannerIndex = ref(0)
let bannerTimer = null
let touchStartX = 0
function touchStart(e) { touchStartX = e.touches[0].clientX }
function touchEnd(e) {
  const diff = touchStartX - e.changedTouches[0].clientX
  if (Math.abs(diff) > 50) {
    if (diff > 0 && bannerIndex.value < banners.value.length - 1) bannerIndex.value++
    else if (diff < 0 && bannerIndex.value > 0) bannerIndex.value--
  }
}

const iconColorMap = {
  '全部': { icon: 'apps', color: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' },
  '电子': { icon: 'devices', color: 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)' },
  '日用': { icon: 'home', color: 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' },
  '办公': { icon: 'business_center', color: 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)' },
  '旅行': { icon: 'luggage', color: 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)' },
  '箱包': { icon: 'backpack', color: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' },
  '酒': { icon: 'wine_bar', color: 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)' },
  '服装': { icon: 'checkroom', color: 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)' },
  '运动': { icon: 'sports_soccer', color: 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' },
  '食品': { icon: 'restaurant', color: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' },
  '美妆': { icon: 'spa', color: 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)' },
  '母婴': { icon: 'child_care', color: 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)' },
  '图书': { icon: 'menu_book', color: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' },
}

function getCatIcon(name) {
  for (const [k, v] of Object.entries(iconColorMap)) {
    if (name.includes(k)) return v
  }
  return { icon: 'category', color: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' }
}

function handleImgError(e) {
  e.target.style.display = 'none'
}

async function load(append = false) {
  loading.value = true
  try {
    const res = await fetch(`/api/store-mall/products?page=${page.value}&size=10`)
    const data = await res.json()
    if (append) products.value.push(...data.list)
    else products.value = data.list
    total.value = data.total
    hasMore.value = data.list.length === 10
  } finally {
    loading.value = false
  }
}

function loadMore() {
  page.value++
  load(true)
}

onMounted(async () => {
  await load()
  // 轮播占位
  banners.value = [
    { title: '新品上市', icon: 'new_releases' },
    { title: '优质好物', icon: 'local_mall' },
  ]
  bannerTimer = setInterval(() => {
    bannerIndex.value = (bannerIndex.value + 1) % banners.value.length
  }, 4000)

  // 加载分类
  try {
    const res = await fetch('/api/mall/categories')
    const cats = await res.json()
    const list = Array.isArray(cats) ? cats.slice(0, 10) : []
    topCategories.value = list.map(c => {
      const style = getCatIcon(c.name || '')
      return { ...c, icon: style.icon, color: style.color }
    })
  } catch (e) {}
})

onUnmounted(() => {
  if (bannerTimer) clearInterval(bannerTimer)
})
</script>
