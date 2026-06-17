<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <!-- 搜索栏 -->
    <div class="sticky top-0 bg-white z-20 px-4 py-3 shadow-sm">
      <div class="flex gap-2">
        <div class="flex-1 relative">
          <input v-model="keyword" @keyup.enter="search" type="text" :placeholder="$t('h5.searchProducts')"
            class="w-full bg-slate-100 rounded-full px-4 py-2 pl-9 text-sm" />
          <svg class="absolute left-3 top-2.5 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
        </div>
        <button @click="search" class="px-4 py-2 bg-primary text-white rounded-full text-sm">{{ $t('common.search') }}</button>
      </div>
      <!-- 分类横向滚动 -->
      <div class="flex gap-2 mt-3 overflow-x-auto pb-1" style="-webkit-overflow-scrolling:touch">
        <button v-for="c in categories" :key="c.id"
          @click="selectedCategory = c.id; loadProducts()"
          :class="['flex-shrink-0 px-4 py-1.5 rounded-full text-xs transition-all',
            selectedCategory === c.id ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600']">
          {{ c.name }}
        </button>
      </div>
    </div>

    <!-- 秒杀横幅 -->
    <div v-if="seckillActivity" class="mx-4 mt-3 bg-gradient-to-r from-red-500 to-orange-400 rounded-xl p-4 text-white relative overflow-hidden">
      <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-8 -mt-8"></div>
      <div class="relative">
        <div class="text-xs opacity-80 mb-1">🔥 限时秒杀</div>
        <div class="font-bold text-lg">{{ seckillActivity.name }}</div>
        <div class="flex items-end gap-2 mt-2">
          <span class="text-2xl font-bold">¥{{ seckillActivity.seckill_price }}</span>
          <span class="text-xs line-through opacity-70">¥{{ seckillActivity.original_price }}</span>
        </div>
        <div class="text-xs mt-1 opacity-80">库存 {{ seckillActivity.stock - seckillActivity.sold }} 件</div>
        <button @click="goSeckill(seckillActivity)" class="mt-2 bg-white text-red-500 px-4 py-1 rounded-full text-xs font-bold">
          立即抢购 →
        </button>
      </div>
    </div>

    <!-- 商品列表 -->
    <div class="px-4 mt-3">
      <div class="grid grid-cols-2 gap-3">
        <div v-for="p in products" :key="p.id"
          @click="goDetail(p)"
          class="bg-white rounded-xl overflow-hidden shadow-sm">
          <div class="aspect-square bg-slate-100 relative">
            <img v-if="p.image_url" :src="p.image_url" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center text-slate-300">
              <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
            </div>
            <!-- 秒杀标签 -->
            <span v-if="p.seckill_price" class="absolute top-2 left-2 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded">
              秒杀
            </span>
          </div>
          <div class="p-3">
            <div class="text-sm font-medium line-clamp-2 leading-tight">{{ p.name }}</div>
            <div class="mt-2 flex items-baseline gap-1">
              <span v-if="p.seckill_price" class="text-red-500 font-bold text-lg">¥{{ p.seckill_price }}</span>
              <span v-if="p.seckill_price" class="text-slate-400 text-xs line-through">¥{{ p.original_price }}</span>
              <span v-else class="text-slate-800 font-bold text-lg">¥{{ p.sale_price || p.price }}</span>
            </div>
            <div class="text-xs text-slate-400 mt-1">{{ p.sales_count || 0 }}人付款</div>
          </div>
        </div>
      </div>

      <!-- 加载更多 -->
      <div v-if="loading" class="text-center py-6 text-slate-400 text-sm">加载中...</div>
      <div v-else-if="products.length >= total" class="text-center py-6 text-slate-400 text-sm">— 没有更多了 —</div>
      <div v-else class="text-center py-6">
        <button @click="page++; loadProducts()" class="text-primary text-sm">加载更多</button>
      </div>
    </div>

    <!-- 底部导航 -->
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t flex z-30">
      <button v-for="tab in tabs" :key="tab.path" @click="goTab(tab.path)"
        :class="['flex-1 py-2 flex flex-col items-center gap-0.5 text-xs',
          activeTab === tab.path ? 'text-primary' : 'text-slate-400']">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon"/>
        </svg>
        {{ tab.label }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const keyword = ref('')
const categories = ref([])
const selectedCategory = ref(null)
const products = ref([])
const loading = ref(false)
const page = ref(1)
const pageSize = 20
const total = ref(0)
const seckillActivity = ref(null)
const activeTab = ref('/h5/home')

const tabs = [
  { path: '/h5/home', label: '首页', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { path: '/h5/categories', label: '分类', icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
  { path: '/h5/cart', label: '购物车', icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z' },
  { path: '/h5/profile', label: '我的', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
]

onMounted(() => {
  loadCategories()
  loadSeckill()
  loadProducts()
})

function loadCategories() {
  fetch('/api/categories').then(r => r.json()).then(res => {
    if (res.code === 0) categories.value = [{ id: null, name: '全部' }, ...res.data]
  })
}

function loadSeckill() {
  fetch('/api/seckill/active').then(r => r.json()).then(res => {
    if (res.code === 0 && res.data) {
      seckillActivity.value = res.data.products?.[0] || null
    }
  })
}

function search() {
  page.value = 1
  loadProducts()
}

function loadProducts() {
  loading.value = true
  const params = new URLSearchParams({ page: page.value, pageSize: pageSize })
  if (selectedCategory.value) params.set('category_id', selectedCategory.value)
  if (keyword.value) params.set('keyword', keyword.value)
  fetch('/api/mall/products?' + params).then(r => r.json()).then(res => {
    if (res.code === 0) {
      if (page.value === 1) products.value = res.data.list
      else products.value.push(...res.data.list)
      total.value = res.data.total
    }
    loading.value = false
  }).catch(() => { loading.value = false })
}

function goDetail(p) {
  router.push('/h5/product/' + p.id)
}

function goSeckill(p) {
  router.push('/h5/seckill/' + (p.activity_id || ''))
}

function goTab(path) {
  if (path === '/h5/home') return
  router.push(path)
}
</script>
