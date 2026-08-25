<template>
  <div class="min-h-screen bg-slate-50">
    <!-- 顶部栏 -->
    <div class="sticky top-0 bg-white/95 backdrop-blur-sm z-20 px-4 py-3 border-b border-slate-100">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
          <span class="material-symbols-outlined text-2xl text-primary" style="font-variation-settings: 'FILL' 1">shopping_cart</span>
          购物车
        </h2>
        <button v-if="items.length" @click="showClearDialog = true"
          class="text-xs text-slate-400 hover:text-red-500 transition-colors flex items-center gap-1">
          <span class="material-symbols-outlined text-base">delete_sweep</span>
          清空
        </button>
      </div>
    </div>

    <!-- 空状态 -->
    <div v-if="!items.length" class="flex flex-col items-center justify-center py-24 text-slate-300">
      <span class="material-symbols-outlined text-7xl mb-4" style="font-variation-settings: 'FILL' 1">shopping_cart</span>
      <p class="text-sm text-slate-400 mb-6">购物车还是空的</p>
      <button @click="$router.push('/h5')"
        class="px-8 py-2.5 bg-primary text-white rounded-full text-sm font-medium shadow-sm shadow-primary/20 hover:shadow-md transition-all active:scale-95">
        去逛逛
      </button>
    </div>

    <!-- 购物车列表 -->
    <div v-else class="px-4 pt-3 pb-32 space-y-3">
      <div v-for="item in items" :key="item.id"
        class="bg-white rounded-2xl p-3 flex gap-3 shadow-sm">
        <!-- 勾选框 -->
        <div class="flex items-center">
          <div @click="item.selected = !item.selected"
            class="w-5 h-5 rounded-full border-2 flex items-center justify-center cursor-pointer transition-all"
            :class="item.selected ? 'bg-primary border-primary text-white' : 'border-slate-300'">
            <span v-if="item.selected" class="material-symbols-outlined text-sm">check</span>
          </div>
        </div>
        <!-- 商品图片 -->
        <div class="w-20 h-20 rounded-xl overflow-hidden flex-shrink-0 bg-slate-100"
          @click="$router.push('/h5/product/' + item.product_id)">
          <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover" />
          <div v-else class="w-full h-full flex items-center justify-center">
            <span class="material-symbols-outlined text-3xl text-slate-300" style="font-variation-settings: 'FILL' 1">image</span>
          </div>
        </div>
        <!-- 商品信息 -->
        <div class="flex-1 min-w-0">
          <div class="text-sm font-medium text-slate-800 line-clamp-2 leading-tight cursor-pointer"
            @click="$router.push('/h5/product/' + item.product_id)">
            {{ item.product_name }}
          </div>
          <div class="text-[11px] text-slate-400 mt-1">{{ item.sku_name || '默认规格' }}</div>
          <div class="flex items-center justify-between mt-2">
            <span class="text-red-500 font-bold text-sm">¥{{ item.price }}</span>
            <div class="flex items-center gap-2 bg-slate-100 rounded-full px-1">
              <button @click="changeQty(item, -1)"
                class="w-7 h-7 rounded-full flex items-center justify-center text-slate-500 hover:bg-white hover:shadow-sm transition-all active:scale-90">
                <span class="material-symbols-outlined text-base">remove</span>
              </button>
              <span class="w-7 text-center text-sm font-medium text-slate-700">{{ item.quantity }}</span>
              <button @click="changeQty(item, 1)"
                class="w-7 h-7 rounded-full flex items-center justify-center text-slate-500 hover:bg-white hover:shadow-sm transition-all active:scale-90">
                <span class="material-symbols-outlined text-base">add</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 结算栏 -->
    <div v-if="items.length"
      class="fixed bottom-14 left-0 right-0 bg-white/95 backdrop-blur-sm border-t border-slate-100 z-30 px-4 py-3 shadow-[0_-2px_12px_rgba(0,0,0,0.06)] safe-area-bottom">
      <div class="flex items-center justify-between mb-2">
        <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer" @click="toggleAll">
          <div
            class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
            :class="selectAll ? 'bg-primary border-primary text-white' : 'border-slate-300'">
            <span v-if="selectAll" class="material-symbols-outlined text-sm">check</span>
          </div>
          全选
        </label>
        <div class="text-right">
          <div class="text-[11px] text-slate-400">合计</div>
          <div class="text-xl text-red-500 font-bold">¥{{ totalAmount }}</div>
        </div>
      </div>
      <button @click="checkout" :disabled="!selectedItems.length"
        class="w-full py-3 bg-gradient-to-r from-primary to-blue-500 text-white rounded-2xl font-medium text-sm disabled:opacity-40 disabled:cursor-not-allowed shadow-sm shadow-primary/20 hover:shadow-md transition-all active:scale-[0.98]">
        去结算
        <span v-if="selectedItems.length" class="ml-1">({{ selectedItems.length }})</span>
      </button>
    </div>

    <!-- 清空确认对话框 -->
    <div v-if="showClearDialog" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-6"
      @click.self="showClearDialog = false">
      <div class="bg-white rounded-2xl p-6 max-w-xs w-full shadow-xl">
        <div class="text-center">
          <span class="material-symbols-outlined text-4xl text-red-400" style="font-variation-settings: 'FILL' 1">delete_sweep</span>
          <div class="text-base font-bold text-slate-800 mt-2">清空购物车？</div>
          <div class="text-xs text-slate-500 mt-1">已选商品将被移除</div>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="showClearDialog = false"
            class="flex-1 py-2.5 bg-slate-100 rounded-xl text-sm text-slate-600 font-medium">取消</button>
          <button @click="confirmClear"
            class="flex-1 py-2.5 bg-red-500 text-white rounded-xl text-sm font-medium shadow-sm">确定清空</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const items = ref([])
const showClearDialog = ref(false)

onMounted(() => loadCart())

function loadCart() {
  fetch('/api/mall/cart', { headers: { Authorization: 'Bearer ' + localStorage.getItem('mall_token') } })
    .then(r => r.json())
    .then(res => {
      if (res.code === 0) {
        items.value = (res.data || []).map(i => ({ ...i, selected: false }))
      }
    })
    .catch(() => { items.value = [] })
}

const selectAll = computed(() => items.value.length > 0 && items.value.every(i => i.selected))

function toggleAll() {
  const val = !selectAll.value
  items.value.forEach(i => i.selected = val)
}

const selectedItems = computed(() => items.value.filter(i => i.selected))
const totalAmount = computed(() =>
  selectedItems.value.reduce((sum, i) => sum + parseFloat(i.price) * i.quantity, 0).toFixed(2)
)

function changeQty(item, delta) {
  const newQty = item.quantity + delta
  if (newQty < 1) return
  item.quantity = newQty
  fetch(`/api/mall/cart/${item.id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + localStorage.getItem('mall_token') },
    body: JSON.stringify({ quantity: newQty })
  }).catch(() => {})
}

function confirmClear() {
  const selected = items.value.filter(i => i.selected).map(i => i.id)
  if (!selected.length) return
  fetch('/api/mall/cart', {
    method: 'DELETE',
    headers: { Authorization: 'Bearer ' + localStorage.getItem('mall_token') },
    body: JSON.stringify({ ids: selected })
  }).then(() => {
    showClearDialog.value = false
    loadCart()
  })
}

function checkout() {
  if (!selectedItems.value.length) return
  router.push({
    path: '/h5/checkout',
    query: { items: JSON.stringify(selectedItems.value.map(i => ({ cart_id: i.id, product_id: i.product_id, sku_id: i.sku_id, quantity: i.quantity }))) }
  })
}
</script>

<style scoped>
.safe-area-bottom {
  padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>
