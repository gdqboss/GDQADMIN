<template>
  <div class="pb-16">
    <!-- 轮播Banner -->
    <div class="mb-4">
      <div class="w-full h-48 bg-gray-100 rounded-xl overflow-hidden relative">
        <img v-if="banners.length" :src="banners[0]" class="w-full h-full object-cover" @error="e => e.target.style.display='none'" />
        <div v-else class="flex items-center justify-center h-full text-gray-400">
          <span class="material-symbols-outlined text-5xl">storefront</span>
        </div>
      </div>
      <!-- 分类快捷入口 -->
      <div class="grid grid-cols-4 gap-3 mt-4">
        <div v-for="cat in topCategories" :key="cat.id"
          class="flex flex-col items-center gap-1.5 p-3 bg-white rounded-xl shadow-sm"
          @click="$router.push(`/mall/category/${cat.id}`)">
          <span class="material-symbols-outlined text-2xl text-blue-600">{{ getCatIcon(cat.name) }}</span>
          <span class="text-xs text-gray-700 text-center leading-tight">{{ cat.name }}</span>
        </div>
      </div>
    </div>

    <!-- 商品列表 -->
    <div class="mt-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-bold text-base">热门商品</h2>
        <span class="text-xs text-gray-400">共 {{ total }} 件</span>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div v-for="p in products" :key="p.id"
          class="bg-white rounded-xl overflow-hidden shadow-sm"
          @click="$router.push(`/mall/product/${p.id}`)">
          <div class="aspect-square bg-gray-100 relative">
            <img v-if="p.image_main" :src="'/' + p.image_main" class="w-full h-full object-cover" @error="e => e.target.parentElement.innerHTML='<div class=\'flex items-center justify-center h-full text-gray-300\'><span class=\'material-symbols-outlined text-4xl\'>image</span></div>'" />
            <div v-else class="flex items-center justify-center h-full text-gray-300">
              <span class="material-symbols-outlined text-4xl">image</span>
            </div>
            <span v-if="p.stock == 0" class="absolute inset-0 bg-black/40 flex items-center justify-center">
              <span class="bg-white/90 text-xs px-2 py-0.5 rounded-full text-gray-600">已售罄</span>
            </span>
          </div>
          <div class="p-2.5">
            <p class="text-sm text-gray-800 line-clamp-2 leading-tight">{{ p.name }}</p>
            <p class="mt-1.5 text-sm font-bold text-red-500">¥{{ p.sale_price || '--' }}</p>
            <p v-if="p.category_name" class="text-xs text-gray-400 mt-0.5">{{ p.category_name }}</p>
          </div>
        </div>
      </div>

      <!-- 加载更多 -->
      <div v-if="hasMore" class="mt-4 text-center">
        <button @click="loadMore" :disabled="loading"
          class="px-6 py-2 bg-white border border-gray-200 rounded-full text-sm text-gray-600 disabled:opacity-50">
          {{ loading ? '加载中...' : '加载更多' }}
        </button>
      </div>
      <div v-else-if="products.length > 0" class="mt-4 text-center text-sm text-gray-400">
        — 没有更多了 —
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const products = ref([])
const topCategories = ref([])
const banners = ref([])
const page = ref(1)
const total = ref(0)
const hasMore = ref(false)
const loading = ref(false)

const iconMap = {
  '电子': 'devices', '日用': 'home', '办公': 'business_center', '旅行': 'luggage',
  '箱包': 'backpack', '酒': 'wine_bar', '服装': 'checkroom', '运动': 'sports_soccer',
  '食品': 'restaurant', '美妆': 'spa', '母婴': 'child_care', '图书': 'menu_book'
}

function getCatIcon(name) {
  for (const [k, v] of Object.entries(iconMap)) {
    if (name.includes(k)) return v
  }
  return 'category'
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
  const res = await fetch('/api/mall/categories')
  const cats = await res.json()
  topCategories.value = Array.isArray(cats) ? cats.slice(0, 8) : []
})
</script>