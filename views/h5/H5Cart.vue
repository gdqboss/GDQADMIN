<template>
  <div class="min-h-screen bg-slate-50 pb-24">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center justify-between">
      <h2 class="text-lg font-semibold">{{ $t('h5.shoppingCart') }}</h2>
      <button v-if="items.length" @click="clearSelected" class="text-sm text-red-500">清空</button>
    </div>

    <div v-if="!items.length" class="flex flex-col items-center justify-center py-20 text-slate-400">
      <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
      </svg>
      <p class="text-sm mb-4">购物车是空的</p>
      <button @click="$router.push('/h5/home')" class="px-6 py-2 bg-primary text-white rounded-full text-sm">去逛逛</button>
    </div>

    <div v-else class="px-4 pt-3 space-y-3">
      <div v-for="item in items" :key="item.id"
        class="bg-white rounded-xl p-3 flex gap-3">
        <input type="checkbox" v-model="item.selected" class="mt-6 flex-shrink-0 accent-primary" />
        <img :src="item.image_url || '/images/placeholder.png'" class="w-20 h-20 rounded-lg object-cover flex-shrink-0 bg-slate-100" />
        <div class="flex-1 min-w-0">
          <div class="text-sm font-medium line-clamp-2 leading-tight">{{ item.product_name }}</div>
          <div class="text-xs text-slate-400 mt-1">{{ item.sku_name || '默认规格' }}</div>
          <div class="flex items-center justify-between mt-2">
            <span class="text-red-500 font-bold">¥{{ item.price }}</span>
            <div class="flex items-center gap-2">
              <button @click="changeQty(item, -1)" class="w-7 h-7 rounded bg-slate-100 flex items-center justify-center text-slate-500">-</button>
              <span class="w-8 text-center text-sm">{{ item.quantity }}</span>
              <button @click="changeQty(item, 1)" class="w-7 h-7 rounded bg-slate-100 flex items-center justify-center text-slate-500">+</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 结算栏 -->
    <div v-if="items.length" class="fixed bottom-14 left-0 right-0 bg-white border-t z-30 px-4 py-3">
      <div class="flex items-center justify-between mb-2">
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" v-model="selectAll" @change="toggleAll" class="accent-primary" />
          全选
        </label>
        <div class="text-right">
          <div class="text-xs text-slate-500">合计</div>
          <div class="text-xl text-red-500 font-bold">¥{{ totalAmount }}</div>
        </div>
      </div>
      <button @click="checkout" :disabled="!selectedItems.length"
        class="w-full py-3 bg-primary text-white rounded-full font-medium text-sm disabled:opacity-50">
        结算 ({{ selectedItems.length }})
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const items = ref([])

onMounted(() => loadCart())

function loadCart() {
  fetch('/api/mall/cart', { headers: { Authorization: 'Bearer ' + localStorage.getItem('mall_token') } })
    .then(r => r.json())
    .then(res => {
      if (res.code === 0) {
        items.value = (res.data || []).map(i => ({ ...i, selected: false }))
      }
    })
}

const selectAll = computed({
  get: () => items.value.length > 0 && items.value.every(i => i.selected),
  set: () => {}
})

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
  })
}

function clearSelected() {
  const selected = items.value.filter(i => i.selected).map(i => i.id)
  if (!selected.length) return
  fetch('/api/mall/cart', {
    method: 'DELETE',
    headers: { Authorization: 'Bearer ' + localStorage.getItem('mall_token') },
    body: JSON.stringify({ ids: selected })
  }).then(() => loadCart())
}

function checkout() {
  if (!selectedItems.value.length) return
  router.push({ path: '/h5/checkout', query: { items: JSON.stringify(selectedItems.value.map(i => ({ cart_id: i.id, product_id: i.product_id, sku_id: i.sku_id, quantity: i.quantity }))) } })
}
</script>
