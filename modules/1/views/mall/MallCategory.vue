<template>
  <div class="pb-16">
    <div class="flex items-center gap-2 mb-4">
      <button @click="$router.back()" class="p-1">
        <span class="material-symbols-outlined">arrow_back</span>
      </button>
      <h1 class="font-bold text-base">{{ categoryName || '全部分类' }}</h1>
    </div>

    <!-- 分类子菜单 -->
    <div v-if="children.length" class="flex gap-2 overflow-x-auto pb-3 mb-3 scrollbar-hide">
      <button v-for="c in children" :key="c.id"
        class="flex-shrink-0 px-3 py-1.5 rounded-full text-sm border"
        :class="selectedId === c.id ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200'"
        @click="selectCategory(c.id, c.name)">
        {{ c.name }}
      </button>
      <button v-if="parentId"
        class="flex-shrink-0 px-3 py-1.5 rounded-full text-sm border border-gray-200 bg-white text-gray-500"
        @click="selectCategory(parentId, parentName)">
        返回上级
      </button>
    </div>

    <!-- 商品列表 -->
    <div v-if="loading" class="text-center py-10 text-gray-400">
      <span class="material-symbols-outlined text-4xl animate-spin">progress_activity</span>
    </div>
    <div v-else-if="!products.length" class="text-center py-10 text-gray-400">
      <span class="material-symbols-outlined text-5xl">inventory_2</span>
      <p class="mt-2 text-sm">该分类暂无商品</p>
    </div>
    <div v-else class="grid grid-cols-2 gap-3">
      <div v-for="p in products" :key="p.id"
        class="bg-white rounded-xl overflow-hidden shadow-sm"
        @click="$router.push(`/mall/product/${p.id}`)">
        <div class="aspect-square bg-gray-100">
          <img v-if="p.image_main" :src="'/' + p.image_main" class="w-full h-full object-cover" @error="e => e.target.parentElement.innerHTML='<div class=\'flex items-center justify-center h-full text-gray-300\'><span class=\'material-symbols-outlined text-4xl\'>image</span></div>'" />
          <div v-else class="flex items-center justify-center h-full text-gray-300">
            <span class="material-symbols-outlined text-4xl">image</span>
          </div>
        </div>
        <div class="p-2.5">
          <p class="text-sm text-gray-800 line-clamp-2 leading-tight">{{ p.name }}</p>
          <p class="mt-1.5 text-sm font-bold text-red-500">¥{{ p.sale_price || '--' }}</p>
        </div>
      </div>
    </div>

    <!-- 加载更多 -->
    <div v-if="hasMore" class="mt-4 text-center">
      <button @click="loadMore" :disabled="loading" class="px-6 py-2 bg-white border border-gray-200 rounded-full text-sm disabled:opacity-50">
        {{ loading ? '加载中...' : '加载更多' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
const route = useRoute()

const products = ref([])
const children = ref([])
const categoryName = ref('')
const selectedId = ref(null)
const parentId = ref(null)
const parentName = ref('')
const page = ref(1)
const hasMore = ref(false)
const loading = ref(false)

async function loadCategory(id) {
  const res = await fetch('/api/store-mall/categories')
  const cats = await res.json()
  const flat = flattenCats(cats)
  const cat = flat.find(c => c.id === Number(id))
  if (cat) {
    categoryName.value = cat.name
    children.value = cat.children || []
    parentId.value = cat.parent_id || null
    const parent = cat.parent_id ? flat.find(c => c.id === cat.parent_id) : null
    parentName.value = parent ? parent.name : ''
    selectedId.value = cat.id
  }
}

function flattenCats(cats, result = []) {
  for (const c of cats) {
    result.push(c)
    if (c.children?.length) flattenCats(c.children, result)
  }
  return result
}

async function loadProducts(append = false) {
  loading.value = true
  try {
    const url = `/api/store-mall/products?category_id=${selectedId.value || 0}&page=${page.value}&size=10`
    const res = await fetch(url)
    const data = await res.json()
    if (append) products.value.push(...data.list)
    else products.value = data.list
    hasMore.value = data.list.length === 10
  } finally {
    loading.value = false
  }
}

function selectCategory(id, name) {
  selectedId.value = id
  categoryName.value = name
  page.value = 1
  loadProducts()
  window.history.pushState({}, '', `/mall/category/${id}`)
}

function loadMore() {
  page.value++
  loadProducts(true)
}

onMounted(async () => {
  const cid = route.params.id || 0
  await loadCategory(cid)
  await loadProducts()
})
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
</style>