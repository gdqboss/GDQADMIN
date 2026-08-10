<template>
  <div class="pb-16">
    <!-- 头部积分展示 -->
    <div class="bg-gradient-to-r from-amber-500 to-orange-500 rounded-xl p-4 mb-4 text-white">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs opacity-80">我的积分</p>
          <p class="text-3xl font-bold mt-1">{{ balance }}</p>
        </div>
        <div class="flex flex-col items-end gap-2">
          <button
            @click="doSign"
            :disabled="signStatus.signed"
            class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors"
            :class="signStatus.signed
              ? 'bg-white/30 text-white/60 cursor-default'
              : 'bg-white text-amber-600 hover:bg-amber-50'">
            {{ signStatus.signed ? '✓ 已签到' : '每日签到' }}
          </button>
          <span class="text-xs opacity-70">连续{{ signStatus.streak_days }}天</span>
        </div>
      </div>
    </div>

    <!-- 分类筛选 -->
    <div class="flex gap-2 overflow-x-auto pb-2 mb-4">
      <button
        v-for="cat in categories" :key="cat.id"
        class="px-4 py-1.5 rounded-full text-sm whitespace-nowrap transition-colors border"
        :class="activeCategory === cat.id
          ? 'bg-amber-500 text-white border-amber-500'
          : 'bg-white text-gray-600 border-gray-200 hover:border-amber-300'"
        @click="activeCategory = cat.id; activeTab = 'products'; fetchProducts()">
        {{ cat.name }}
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex border-b border-gray-100 mb-4">
      <button v-for="tab in tabs" :key="tab.key"
        class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
        :class="activeTab === tab.key
          ? 'border-amber-500 text-amber-600'
          : 'border-transparent text-gray-400 hover:text-gray-600'"
        @click="activeTab = tab.key; switchTab(tab.key)">
        {{ tab.label }}
      </button>
    </div>

    <!-- 积分商品列表 -->
    <div v-if="activeTab === 'products'">
      <div class="grid grid-cols-2 gap-3">
        <div v-for="p in products" :key="p.id"
          class="bg-white rounded-xl overflow-hidden shadow-sm"
          @click="goDetail(p.id)">
          <div class="aspect-square bg-gray-100 relative">
            <img v-if="p.image_main" :src="'/' + p.image_main" class="w-full h-full object-cover"
              @error="e => e.target.parentElement.innerHTML='<div class=flex items-center justify-center h-full text-gray-300><span class=material-symbols-outlined text-4xl>card_giftcard</span></div>'" />
            <div v-else class="flex items-center justify-center h-full text-gray-300">
              <span class="material-symbols-outlined text-4xl">card_giftcard</span>
            </div>
            <span v-if="p.stock == 0" class="absolute inset-0 bg-black/40 flex items-center justify-center">
              <span class="bg-white/90 text-xs px-2 py-0.5 rounded-full text-gray-600">已兑完</span>
            </span>
          </div>
          <div class="p-2.5">
            <p class="text-sm text-gray-800 line-clamp-2 leading-tight">{{ p.name }}</p>
            <p class="mt-1.5 flex items-center gap-1 text-sm font-bold text-amber-500">
              <span class="material-symbols-outlined text-sm">stars</span>
              {{ p.score_price }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">已兑 {{ p.exchange_count }}</p>
          </div>
        </div>
      </div>

      <div v-if="hasMore" class="mt-4 text-center">
        <button @click="loadMore" :disabled="loading"
          class="px-6 py-2 bg-white border border-gray-200 rounded-full text-sm text-gray-600 disabled:opacity-50">
          {{ loading ? '加载中...' : '加载更多' }}
        </button>
      </div>
      <div v-else-if="products.length === 0 && !loading" class="mt-8 text-center text-sm text-gray-400">
        — 暂无积分商品 —
      </div>
    </div>

    <!-- 积分记录 -->
    <div v-else-if="activeTab === 'records'">
      <div class="space-y-3">
        <div v-for="r in records" :key="r.id"
          class="bg-white rounded-xl p-3 flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-800">{{ r.remark }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ formatDate(r.created_at) }}</p>
          </div>
          <div class="text-right">
            <p class="text-sm font-bold" :class="r.type === 'earn' ? 'text-green-500' : 'text-red-400'">
              {{ r.type === 'earn' ? '+' : '-' }}{{ r.score }}
            </p>
            <p class="text-xs text-gray-400">余额 {{ r.balance }}</p>
          </div>
        </div>
      </div>
      <div v-if="records.length === 0 && !loading" class="mt-8 text-center text-sm text-gray-400">
        — 暂无积分记录 —
      </div>
    </div>

    <!-- 兑换记录 -->
    <div v-else-if="activeTab === 'orders'">
      <div class="space-y-3">
        <div v-for="o in orders" :key="o.id"
          class="bg-white rounded-xl p-3"
          @click="goOrderDetail(o.id)">
          <div class="flex items-start gap-3">
            <div class="w-16 h-16 rounded-lg bg-gray-100 flex-shrink-0 overflow-hidden">
              <img v-if="o.product_image" :src="'/' + o.product_image" class="w-full h-full object-cover" />
              <div v-else class="flex items-center justify-center h-full text-gray-300">
                <span class="material-symbols-outlined text-2xl">card_giftcard</span>
              </div>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-800 line-clamp-1">{{ o.product_name }}</p>
              <p class="text-xs text-gray-400 mt-0.5">兑换单号：{{ o.order_no }}</p>
              <p class="text-xs text-gray-400">{{ formatDate(o.created_at) }}</p>
            </div>
            <div class="text-right flex-shrink-0">
              <p class="text-sm font-bold text-amber-500 flex items-center gap-0.5">
                <span class="material-symbols-outlined text-xs">stars</span>{{ o.total_score }}
              </p>
              <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full"
                :class="statusClass(o.status)">{{ statusLabel(o.status) }}</span>
            </div>
          </div>
        </div>
      </div>
      <div v-if="orders.length === 0 && !loading" class="mt-8 text-center text-sm text-gray-400">
        — 暂无兑换记录 —
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const router = useRouter()
const products = ref([])
const records = ref([])
const orders = ref([])
const categories = ref([{ id: 0, name: '全部' }])
const activeCategory = ref(0)
const activeTab = ref('products')
const balance = ref(0)
const signStatus = ref({ signed: false, sign_score: 10, streak_days: 0 })
const page = ref(1)
const total = ref(0)
const hasMore = ref(false)
const loading = ref(false)

const tabs = [
  { key: 'products', label: '积分商城' },
  { key: 'records', label: '积分明细' },
  { key: 'orders', label: '兑换记录' },
]

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { hour12: false })
}

function statusClass(s) {
  const map = { pending: 'bg-yellow-100 text-yellow-600', processing: 'bg-blue-100 text-blue-600', shipped: 'bg-green-100 text-green-600', completed: 'bg-gray-100 text-gray-500', cancelled: 'bg-gray-100 text-gray-400' }
  return map[s] || 'bg-gray-100 text-gray-500'
}

function statusLabel(s) {
  const map = { pending: '待发货', processing: '处理中', shipped: '已发货', completed: '已完成', cancelled: '已取消' }
  return map[s] || s
}

async function fetchProducts(append = false) {
  loading.value = true
  try {
    const params = { page: page.value, size: 10, status: 'active' }
    if (activeCategory.value > 0) params.category_id = activeCategory.value
    const res = await api.get('/score-shop/products', { params })
    if (res.code === 0) {
      if (append) products.value.push(...res.data.list)
      else products.value = res.data.list || []
      total.value = res.data.total
      hasMore.value = res.data.list?.length === 10
    }
  } finally {
    loading.value = false
  }
}

async function fetchRecords() {
  loading.value = true
  try {
    const res = await api.get('/score-shop/records', { params: { page: 1, size: 50 } })
    if (res.code === 0) records.value = res.data.list || []
  } finally {
    loading.value = false
  }
}

async function fetchOrders() {
  loading.value = true
  try {
    const res = await api.get('/score-shop/orders', { params: { page: 1, size: 50 } })
    if (res.code === 0) orders.value = res.data.list || []
  } finally {
    loading.value = false
  }
}

async function fetchBalance() {
  try {
    const res = await api.get('/score-shop/balance')
    if (res.code === 0) balance.value = res.data.balance || 0
  } catch {}
}

async function fetchSignStatus() {
  try {
    const res = await api.get('/score-shop/sign')
    if (res.code === 0) signStatus.value = res.data
  } catch {}
}

async function fetchCategories() {
  try {
    const res = await api.get('/score-shop/admin/categories')
    if (res.code === 0) {
      categories.value = [{ id: 0, name: '全部' }, ...(res.data || [])]
    }
  } catch {}
}

async function doSign() {
  try {
    const res = await api.post('/score-shop/sign')
    if (res.code === 0) {
      signStatus.value.signed = true
      balance.value = res.data.balance
      ElMessage.success(`签到成功，获得 ${res.data.score} 积分`)
    } else {
      ElMessage.error(res.message || '签到失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '签到失败')
  }
}

function switchTab(key) {
  if (key === 'products' && products.value.length === 0) fetchProducts()
  if (key === 'records' && records.value.length === 0) fetchRecords()
  if (key === 'orders' && orders.value.length === 0) fetchOrders()
}

function loadMore() {
  page.value++
  fetchProducts(true)
}

function goDetail(id) {
  router.push(`/mall/score-product/${id}`)
}

function goOrderDetail(id) {
  router.push(`/mall/score-order/${id}`)
}

onMounted(() => {
  fetchBalance()
  fetchSignStatus()
  fetchCategories()
  fetchProducts()
})
</script>
