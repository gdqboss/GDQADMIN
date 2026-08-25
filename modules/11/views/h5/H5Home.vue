<template>
  <div class="min-h-screen bg-slate-50">
    <!-- 搜索栏 -->
    <div class="sticky top-0 bg-white/95 backdrop-blur-sm z-20 px-4 py-3 border-b border-slate-100">
      <div class="flex gap-2">
        <div class="flex-1 relative">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
          <input v-model="keyword" @keyup.enter="search" type="text" :placeholder="$t('h5.searchProducts')"
            class="w-full bg-slate-100 rounded-full px-4 py-2.5 pl-10 text-sm outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
        </div>
        <button @click="search" class="px-5 py-2.5 bg-primary text-white rounded-full text-sm font-medium shadow-sm shadow-primary/20 hover:shadow-md hover:shadow-primary/30 transition-all active:scale-95">
          {{ $t('common.search') }}
        </button>
      </div>
    </div>

    <!-- 轮播 Banner -->
    <div v-if="banners.length" class="mx-4 mt-3 relative rounded-2xl overflow-hidden shadow-sm">
      <div class="relative overflow-hidden" @touchstart="touchStart" @touchend="touchEnd">
        <div class="flex transition-transform duration-300 ease-out" :style="{ transform: `translateX(-${bannerIndex * 100}%)` }">
          <div v-for="(b, i) in banners" :key="i" class="w-full flex-shrink-0">
            <div class="aspect-[2/1] bg-gradient-to-br from-primary/80 to-blue-500 flex items-center justify-center"
              @click="b.link && $router.push(b.link)">
              <div class="text-center text-white p-6">
                <span class="material-symbols-outlined text-5xl mb-2" style="font-variation-settings: 'FILL' 1">{{ b.icon || 'campaign' }}</span>
                <div class="text-lg font-bold">{{ b.title }}</div>
                <div class="text-xs opacity-80 mt-1">{{ b.subtitle }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- 轮播指示器 -->
      <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
        <span v-for="(b, i) in banners" :key="i"
          class="w-1.5 h-1.5 rounded-full transition-all duration-300"
          :class="i === bannerIndex ? 'bg-white w-4' : 'bg-white/40'"></span>
      </div>
    </div>

    <!-- 分类快捷入口 -->
    <div class="mx-4 mt-4">
      <div class="grid grid-cols-5 gap-2">
        <div v-for="cat in topCategories" :key="cat.id"
          class="flex flex-col items-center gap-1.5 p-2 bg-white rounded-2xl shadow-sm hover:shadow-md transition-all cursor-pointer active:scale-95"
          @click="selectCategory(cat.id)">
          <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white text-xl"
            :style="{ background: cat.color || 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' }">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1">{{ cat.icon || 'category' }}</span>
          </div>
          <span class="text-[11px] text-slate-600 text-center leading-tight line-clamp-1">{{ cat.name }}</span>
        </div>
      </div>
    </div>

    <!-- 秒杀横幅 -->
    <div v-if="seckillActivity" class="mx-4 mt-4 bg-gradient-to-r from-rose-500 via-red-500 to-orange-400 rounded-2xl p-4 text-white relative overflow-hidden shadow-md"
      @click="goSeckill(seckillActivity)">
      <div class="absolute top-0 right-0 w-28 h-28 bg-white/10 rounded-full -mr-10 -mt-10"></div>
      <div class="absolute bottom-0 left-0 w-20 h-20 bg-white/5 rounded-full -ml-8 -mb-8"></div>
      <div class="relative flex items-center gap-4">
        <div class="flex-1">
          <div class="flex items-center gap-2 mb-1">
            <span class="bg-white/20 backdrop-blur-sm text-[10px] font-bold px-2 py-0.5 rounded-full">⏰ 限时秒杀</span>
          </div>
          <div class="font-bold text-base">{{ seckillActivity.name }}</div>
          <div class="flex items-baseline gap-1.5 mt-1">
            <span class="text-2xl font-bold">¥{{ seckillActivity.seckill_price }}</span>
            <span class="text-xs line-through opacity-60">¥{{ seckillActivity.original_price }}</span>
          </div>
        </div>
        <div class="text-center">
          <div class="bg-white/20 backdrop-blur-sm rounded-xl px-3 py-2">
            <div class="text-[10px] opacity-80">库存</div>
            <div class="font-bold text-sm">{{ seckillActivity.stock - seckillActivity.sold }}件</div>
          </div>
          <div class="mt-1.5 bg-white text-red-500 px-3 py-1 rounded-full text-[10px] font-bold shadow-sm">
            立即抢购 →
          </div>
        </div>
      </div>
    </div>

    <!-- 分类标签横向滚动 -->
    <div class="mx-4 mt-4 overflow-x-auto" style="-webkit-overflow-scrolling:touch">
      <div class="flex gap-2 pb-1">
        <button v-for="c in categories" :key="c.id"
          @click="selectedCategory = c.id; loadProducts()"
          :class="['flex-shrink-0 px-4 py-1.5 rounded-full text-xs font-medium transition-all',
            selectedCategory === c.id ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'bg-white text-slate-500 border border-slate-200 hover:border-primary/30']">
          {{ c.name }}
        </button>
      </div>
    </div>

    <!-- 商品瀑布流 -->
    <div class="px-4 mt-3 pb-4">
      <div class="grid grid-cols-2 gap-3">
        <div v-for="p in products" :key="p.id"
          @click="goDetail(p)"
          class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all active:scale-[0.98] cursor-pointer">
          <div class="aspect-square bg-slate-100 relative overflow-hidden">
            <img v-if="p.image_url" :src="p.image_url"
              class="w-full h-full object-cover hover:scale-105 transition-transform duration-300" />
            <div v-else class="w-full h-full flex items-center justify-center">
              <span class="material-symbols-outlined text-5xl text-slate-300" style="font-variation-settings: 'FILL' 1">image</span>
            </div>
            <!-- 标签 -->
            <span v-if="p.seckill_price" class="absolute top-2 left-2 bg-gradient-to-r from-red-500 to-orange-400 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
              秒杀
            </span>
            <span v-if="p.is_new" class="absolute top-2 right-2 bg-emerald-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
              新品
            </span>
          </div>
          <div class="p-3">
            <div class="text-sm font-medium text-slate-800 line-clamp-2 leading-tight min-h-[2.5em]">{{ p.name }}</div>
            <div class="mt-2 flex items-baseline gap-1.5">
              <span v-if="p.seckill_price" class="text-red-500 font-bold text-base">¥{{ p.seckill_price }}</span>
              <span v-if="p.seckill_price" class="text-slate-400 text-[11px] line-through">¥{{ p.original_price }}</span>
              <span v-else class="text-slate-800 font-bold text-base">¥{{ p.sale_price || p.price }}</span>
            </div>
            <div class="flex items-center justify-between mt-1.5">
              <span class="text-[10px] text-slate-400">{{ p.sales_count || 0 }}人付款</span>
              <span v-if="p.rating" class="text-[10px] text-amber-400">★ {{ p.rating }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 加载状态 -->
      <div v-if="loading" class="py-8">
        <div class="flex justify-center gap-1">
          <span class="w-2 h-2 bg-primary rounded-full animate-bounce" style="animation-delay:0ms"></span>
          <span class="w-2 h-2 bg-primary rounded-full animate-bounce" style="animation-delay:150ms"></span>
          <span class="w-2 h-2 bg-primary rounded-full animate-bounce" style="animation-delay:300ms"></span>
        </div>
        <div class="text-center text-slate-400 text-xs mt-2">加载中...</div>
      </div>
      <div v-else-if="products.length >= total" class="text-center py-6 text-slate-300 text-sm">— 已经到底了 —</div>
      <div v-else class="text-center py-4">
        <button @click="page++; loadProducts()"
          class="px-6 py-2 bg-white border border-slate-200 rounded-full text-sm text-slate-500 hover:border-primary/30 hover:text-primary transition-all active:scale-95">
          加载更多
        </button>
      </div>
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

// 轮播
const banners = ref([])
const bannerIndex = ref(0)
let bannerTimer = null

// 轮播触摸
let touchStartX = 0
function touchStart(e) { touchStartX = e.touches[0].clientX }
function touchEnd(e) {
  const diff = touchStartX - e.changedTouches[0].clientX
  if (Math.abs(diff) > 50) {
    if (diff > 0 && bannerIndex.value < banners.value.length - 1) bannerIndex.value++
    else if (diff < 0 && bannerIndex.value > 0) bannerIndex.value--
  }
}

// 前8个分类作为快捷入口（带颜色和图标映射）
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

const topCategories = ref([])

onMounted(() => {
  loadBanners()
  loadCategories()
  loadSeckill()
  loadProducts()
  startBannerAutoPlay()
})

function loadBanners() {
  banners.value = [
    { title: '新品上市', subtitle: '全场低至5折', icon: 'new_releases', link: null },
    { title: '优质好物', subtitle: '品质生活从这里开始', icon: 'local_mall', link: null },
    { title: '会员专享', subtitle: '注册即送积分', icon: 'card_membership', link: '/h5/profile' },
  ]
}

function startBannerAutoPlay() {
  bannerTimer = setInterval(() => {
    bannerIndex.value = (bannerIndex.value + 1) % banners.value.length
  }, 4000)
}

function loadCategories() {
  fetch('/api/categories').then(r => r.json()).then(res => {
    if (res.code === 0) {
      const all = [{ id: null, name: '全部' }, ...res.data]
      categories.value = all
      topCategories.value = all.slice(0, 10).map(c => {
        const style = iconColorMap[c.name] || { icon: 'category', color: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' }
        return { ...c, icon: style.icon, color: style.color }
      })
    }
  })
}

function selectCategory(id) {
  selectedCategory.value = id
  page.value = 1
  loadProducts()
  // 滚动到分类标签区
  window.scrollTo({ top: 280, behavior: 'smooth' })
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
  const params = new URLSearchParams({ page: page.value, pageSize })
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
</script>
