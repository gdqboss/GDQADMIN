<template>
  <div class="pb-20">
    <div class="flex items-center justify-between p-4 border-b">
      <h1 class="font-bold text-base">购物车</h1>
      <button v-if="items.length" @click="clearCart" class="text-sm text-gray-500">清空</button>
    </div>

    <div v-if="!items.length" class="text-center py-20">
      <span class="material-symbols-outlined text-5xl text-gray-300">shopping_cart</span>
      <p class="mt-3 text-gray-400">购物车是空的</p>
      <button @click="$router.push('/mall')" class="mt-4 px-6 py-2 bg-blue-600 text-white rounded-full text-sm">
        去逛逛
      </button>
    </div>

    <div v-else>
      <div v-for="item in items" :key="item.id"
        class="flex gap-3 p-4 bg-white border-b border-gray-100">
        <div class="w-20 h-20 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
          <img v-if="item.image" :src="'/' + item.image" class="w-full h-full object-cover" @error="e => e.target.style.display='none'" />
          <div v-else class="flex items-center justify-center h-full text-gray-300">
            <span class="material-symbols-outlined text-2xl">image</span>
          </div>
        </div>
        <div class="flex-1 flex flex-col justify-between">
          <p class="text-sm line-clamp-2 leading-tight">{{ item.name }}</p>
          <div class="flex items-end justify-between mt-1">
            <p class="text-red-500 font-bold text-sm">¥{{ item.price || '--' }}</p>
            <div class="flex items-center gap-2">
              <button @click="changeQty(item, -1)" class="w-7 h-7 rounded border border-gray-200 flex items-center justify-center text-gray-500">−</button>
              <span class="text-sm w-6 text-center">{{ item.qty }}</span>
              <button @click="changeQty(item, 1)" class="w-7 h-7 rounded border border-gray-200 flex items-center justify-center text-gray-500">+</button>
            </div>
          </div>
        </div>
        <button @click="removeItem(item.id)" class="text-gray-300 self-start">
          <span class="material-symbols-outlined text-lg">close</span>
        </button>
      </div>

      <!-- 金额汇总 -->
      <div class="bg-white p-4 sticky bottom-16 border-t">
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-500">共 {{ totalCount }} 件</span>
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500">合计：</span>
            <span class="text-xl font-bold text-red-500">¥{{ totalPrice }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 下单按钮 -->
    <div v-if="items.length" class="fixed bottom-0 left-0 right-0 bg-white border-t p-3 flex gap-3">
      <button @click="$router.push('/mall')"
        class="flex-1 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium">
        继续购物
      </button>
      <button @click="goCheckout"
        :disabled="!userId"
        class="flex-1 py-3 rounded-xl bg-red-500 text-white text-sm font-medium disabled:bg-gray-300">
        结算 ({{ totalCount }})
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter()

const items = ref([])
const userId = ref(localStorage.getItem('mall_user_id') || '')

const totalCount = computed(() => items.value.reduce((s, i) => s + i.qty, 0))
const totalPrice = computed(() => items.value.reduce((s, i) => s + (i.price || 0) * i.qty, 0).toFixed(2))

function loadCart() {
  items.value = JSON.parse(localStorage.getItem('mall_cart') || '[]')
}

function changeQty(item, delta) {
  if (item.qty + delta <= 0) { removeItem(item.id); return }
  item.qty += delta
  localStorage.setItem('mall_cart', JSON.stringify(items.value))
  window.dispatchEvent(new Event('mall_cart_updated'))
}

function removeItem(id) {
  items.value = items.value.filter(i => i.id !== id)
  localStorage.setItem('mall_cart', JSON.stringify(items.value))
  window.dispatchEvent(new Event('mall_cart_updated'))
}

function clearCart() {
  localStorage.setItem('mall_cart', '[]')
  items.value = []
  window.dispatchEvent(new Event('mall_cart_updated'))
}

function goCheckout() {
  if (!userId.value) { router.push('/mall/login'); return }
  router.push('/mall/checkout')
}

onMounted(() => {
  loadCart()
  window.addEventListener('mall_cart_updated', loadCart)
})
</script>