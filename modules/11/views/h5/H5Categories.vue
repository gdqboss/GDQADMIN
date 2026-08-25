<template>
  <div class="min-h-screen bg-slate-50">
    <!-- 顶部搜索栏 -->
    <div class="sticky top-0 bg-white/95 backdrop-blur-sm z-20 px-4 py-3 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <button @click="$router.back()" class="flex-shrink-0">
          <span class="material-symbols-outlined text-2xl text-slate-600">arrow_back</span>
        </button>
        <div class="flex-1 relative">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
          <input v-model="keyword" @keyup.enter="search" type="text" placeholder="搜索商品..."
            class="w-full bg-slate-100 rounded-full px-4 py-2 pl-10 text-sm outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
        </div>
      </div>
    </div>

    <div class="flex" style="height:calc(100vh - 112px)">
      <!-- 左侧分类列表 -->
      <div class="w-24 bg-white overflow-y-auto border-r border-slate-100">
        <button v-for="c in categories" :key="c.id"
          @click="selectedId = c.id; loadProducts(); scrollTop()"
          class="w-full flex flex-col items-center gap-1 py-4 px-2 transition-all relative"
          :class="selectedId === c.id ? 'text-primary font-medium' : 'text-slate-500 hover:text-slate-700'">
          <span class="material-symbols-outlined text-xl" :class="selectedId === c.id ? '' : ''"
            :style="selectedId === c.id ? 'font-variation-settings: \'FILL\' 1' : ''">
            {{ getCatIcon(c.name) }}
          </span>
          <span class="text-[11px] leading-tight text-center">{{ c.name }}</span>
          <!-- 选中指示条 -->
          <span v-if="selectedId === c.id" class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-primary rounded-r-full"></span>
        </button>
      </div>

      <!-- 右侧商品展示 -->
      <div class="flex-1 bg-slate-50 overflow-y-auto" ref="productListRef">
        <!-- 分类标题 -->
        <div class="px-3 pt-3 pb-1 flex items-center justify-between">
          <span class="text-xs text-slate-400">{{ selectedCategoryName }}</span>
          <span class="text-[10px] text-slate-300">{{ products.length }}件商品</span>
        </div>

        <!-- 商品网格 -->
        <div class="px-3 pb-4">
          <div class="grid grid-cols-2 gap-2.5">
            <div v-for="p in products" :key="p.id"
              @click="$router.push('/h5/product/' + p.id)"
              class="bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all active:scale-[0.98] cursor-pointer">
              <div class="aspect-square bg-slate-100 relative">
                <img v-if="p.image_url" :src="p.image_url" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full flex items-center justify-center">
                  <span class="material-symbols-outlined text-4xl text-slate-300">image</span>
                </div>
              </div>
              <div class="p-2">
                <div class="text-[11px] text-slate-700 line-clamp-2 leading-tight">{{ p.name }}</div>
                <div class="mt-1 text-xs font-bold text-red-500">¥{{ p.sale_price || p.price }}</div>
              </div>
            </div>
          </div>

          <!-- 空状态 -->
          <div v-if="!loading && products.length === 0" class="flex flex-col items-center justify-center py-16 text-slate-300">
            <span class="material-symbols-outlined text-5xl mb-3" style="font-variation-settings: 'FILL' 1">inventory_2</span>
            <div class="text-sm">该分类暂无商品</div>
          </div>

          <!-- 加载状态 -->
          <div v-if="loading" class="flex justify-center py-8">
            <div class="flex gap-1">
              <span class="w-1.5 h-1.5 bg-primary rounded-full animate-bounce" style="animation-delay:0ms"></span>
              <span class="w-1.5 h-1.5 bg-primary rounded-full animate-bounce" style="animation-delay:150ms"></span>
              <span class="w-1.5 h-1.5 bg-primary rounded-full animate-bounce" style="animation-delay:300ms"></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const keyword = ref('')
const categories = ref([{ id: null, name: '全部' }])
const selectedId = ref(null)
const products = ref([])
const loading = ref(false)
const productListRef = ref(null)

const iconMap = {
  '全部': 'apps', '电子': 'devices', '日用': 'home', '办公': 'business_center',
  '旅行': 'luggage', '箱包': 'backpack', '酒': 'wine_bar', '服装': 'checkroom',
  '运动': 'sports_soccer', '食品': 'restaurant', '美妆': 'spa', '母婴': 'child_care',
  '图书': 'menu_book', '家居': 'chair', '数码': 'tablet', '配件': 'cable',
}

function getCatIcon(name) {
  return iconMap[name] || 'category'
}

const selectedCategoryName = computed(() => {
  const c = categories.value.find(c => c.id === selectedId.value)
  return c ? c.name : '全部'
})

function scrollTop() {
  if (productListRef.value) productListRef.value.scrollTop = 0
}

function search() {
  loadProducts()
}

function loadProducts() {
  loading.value = true
  const params = new URLSearchParams({ page: 1, pageSize: 50 })
  if (selectedId.value) params.set('category_id', selectedId.value)
  if (keyword.value) params.set('keyword', keyword.value)
  fetch('/api/mall/products?' + params).then(r => r.json()).then(res => {
    if (res.code === 0) products.value = res.data.list || []
    loading.value = false
  }).catch(() => { loading.value = false })
}

onMounted(() => {
  fetch('/api/categories').then(r => r.json()).then(res => {
    if (res.code === 0) categories.value.push(...res.data)
  })
  loadProducts()
})
</script>
