<template>
  <div class="pb-24">
    <div class="flex items-center justify-between p-4 border-b bg-white sticky top-0 z-10">
      <button @click="$router.back()" class="text-gray-600">
        <span class="material-symbols-outlined">arrow_back</span>
      </button>
      <h1 class="font-bold text-base">确认订单</h1>
      <div class="w-8"></div>
    </div>

    <div v-if="loading" class="text-center py-20 text-gray-400">
      <span class="material-symbols-outlined text-5xl animate-spin">progress_activity</span>
      <p class="mt-2">加载中...</p>
    </div>

    <div v-else>
      <!-- 收货地址 -->
      <div @click="showAddressPicker = true" class="flex items-center gap-3 p-4 bg-white border-b cursor-pointer">
        <span class="material-symbols-outlined text-gray-400">location_on</span>
        <div v-if="selectedAddress" class="flex-1">
          <p class="text-sm">{{ selectedAddress.receiver_name }} {{ selectedAddress.receiver_phone }}</p>
          <p class="text-xs text-gray-500 mt-1">{{ selectedAddress.province }}{{ selectedAddress.city }}{{ selectedAddress.district }}{{ selectedAddress.detail }}</p>
        </div>
        <div v-else class="flex-1 text-sm text-gray-500">
          <p>请选择收货地址</p>
        </div>
        <span class="material-symbols-outlined text-gray-300">chevron_right</span>
      </div>

      <!-- 商品列表 -->
      <div class="bg-white mt-2 p-4">
        <p class="text-sm font-bold mb-3">商品信息</p>
        <div v-for="item in items" :key="item.id" class="flex gap-3 mb-3">
          <img :src="'/' + (item.image_main || item.pic)" class="w-16 h-16 rounded bg-gray-100 object-cover" @error="e => e.target.style.display='none'" />
          <div class="flex-1">
            <p class="text-sm line-clamp-2">{{ item.name }}</p>
            <p class="text-xs text-gray-500 mt-1">x{{ item.quantity || item.qty }}</p>
          </div>
          <p class="text-sm text-red-500 font-bold">¥{{ ((item.sale_price || item.price) * (item.quantity || item.qty)).toFixed(2) }}</p>
        </div>
      </div>

      <!-- 订单备注 -->
      <div class="bg-white mt-2 p-4">
        <p class="text-sm font-bold mb-2">备注</p>
        <textarea v-model="remark" placeholder="选填，可备注特殊需求" class="w-full border rounded-lg p-3 text-sm resize-none" rows="2"></textarea>
      </div>

      <!-- 费用明细 -->
      <div class="bg-white mt-2 p-4">
        <div class="flex justify-between text-sm mb-2">
          <span class="text-gray-500">商品金额</span>
          <span>¥{{ goodsAmount }}</span>
        </div>
        <div class="flex justify-between text-sm mb-2">
          <span class="text-gray-500">运费</span>
          <span>¥{{ freightAmount }}</span>
        </div>
        <div class="flex justify-between text-sm font-bold mt-3 pt-3 border-t">
          <span>合计</span>
          <span class="text-red-500">¥{{ totalAmount }}</span>
        </div>
      </div>

      <!-- 提交按钮 -->
      <div class="fixed bottom-0 left-0 right-0 bg-white border-t p-4 flex items-center justify-between">
        <div>
          <p class="text-xs text-gray-500">实付款</p>
          <p class="text-red-500 font-bold text-xl">¥{{ totalAmount }}</p>
        </div>
        <button @click="submitOrder" :disabled="submitting || !selectedAddress" class="px-8 py-3 bg-blue-600 text-white rounded-full text-sm font-bold disabled:opacity-50">
          {{ submitting ? '提交中...' : '提交订单' }}
        </button>
      </div>
    </div>

    <!-- 地址选择弹窗 -->
    <div v-if="showAddressPicker" class="fixed inset-0 bg-black/50 z-50 flex items-end" @click.self="showAddressPicker = false">
      <div class="bg-white w-full max-h-[70vh] rounded-t-2xl overflow-auto">
        <div class="sticky top-0 bg-white flex items-center justify-between p-4 border-b">
          <h2 class="font-bold">选择收货地址</h2>
          <button @click="showAddressPicker = false"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div v-if="addresses.length === 0" class="text-center py-10 text-gray-400">
          <p>暂无地址</p>
        </div>
        <div v-else>
          <div v-for="addr in addresses" :key="addr.id" @click="selectAddress(addr)" class="flex gap-3 p-4 border-b cursor-pointer" :class="selectedAddress?.id === addr.id ? 'bg-blue-50' : ''">
            <span class="material-symbols-outlined text-gray-400 mt-1">location_on</span>
            <div class="flex-1">
              <p class="text-sm">{{ addr.receiver_name }} {{ addr.receiver_phone }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ addr.province }}{{ addr.city }}{{ addr.district }}{{ addr.detail }}</p>
            </div>
            <span v-if="selectedAddress?.id === addr.id" class="material-symbols-outlined text-blue-600">check</span>
          </div>
        </div>
        <div class="sticky bottom-0 bg-white p-4">
          <button @click="goAddAddress" class="w-full py-3 border border-blue-600 text-blue-600 rounded-full text-sm">新增地址</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const userId = ref(localStorage.getItem('mall_user_id') || '')
const token = localStorage.getItem('mall_token') || ''
const loading = ref(true)
const submitting = ref(false)
const items = ref([])
const addresses = ref([])
const selectedAddress = ref(null)
const showAddressPicker = ref(false)
const remark = ref('')

const authHeaders = computed(() => token ? { Authorization: `Bearer ${token}` } : {})

const freightAmount = ref('0.00')

const goodsAmount = computed(() =>
  items.value.reduce((s, i) => s + ((i.sale_price || i.price || 0) * (i.quantity || i.qty || 0)), 0).toFixed(2)
)

const totalAmount = computed(() =>
  (parseFloat(goodsAmount.value) + parseFloat(freightAmount.value)).toFixed(2)
)

async function loadCart() {
  try {
    const res = await fetch(`/api/mall/cart?user_id=${userId.value}`, { headers: authHeaders.value })
    const data = await res.json()
    items.value = Array.isArray(data) ? data : (data.list || [])
  } catch (e) {
    items.value = []
  }
}

async function loadAddresses() {
  try {
    const res = await fetch(`/api/mall/addresses?user_id=${userId.value}`, { headers: authHeaders.value })
    const data = await res.json()
    addresses.value = Array.isArray(data) ? data : (data.list || [])
    selectedAddress.value = addresses.value.find(a => a.is_default === 1) || addresses.value[0]
  } catch (e) {
    addresses.value = []
  }
}

function selectAddress(addr) {
  selectedAddress.value = addr
  showAddressPicker.value = false
}

function goAddAddress() {
  showAddressPicker.value = false
  router.push('/mall/address/edit')
}

async function submitOrder() {
  if (!selectedAddress.value) { alert('请选择收货地址'); return }
  if (!userId.value) { router.push('/mall/login'); return }
  submitting.value = true
  try {
    const res = await fetch('/api/mall/orders', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...authHeaders.value },
      body: JSON.stringify({
        user_id: userId.value,
        receiver_name: selectedAddress.value.receiver_name,
        receiver_phone: selectedAddress.value.receiver_phone,
        receiver_address: `${selectedAddress.value.province}${selectedAddress.value.city}${selectedAddress.value.district}${selectedAddress.value.detail}`,
        remark: remark.value,
        items: items.value.map(i => ({
          product_id: i.product_id || i.id,
          product_name: i.name,
          quantity: i.quantity || i.qty,
          price: i.sale_price || i.price
        }))
      })
    })
    const data = await res.json()
    if (data.id || data.order_id) {
      // 清空购物车
      await fetch('/api/mall/cart/clear', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...authHeaders.value },
        body: JSON.stringify({ user_id: userId.value })
      })
      window.dispatchEvent(new Event('mall_cart_updated'))
      alert(`订单提交成功！订单号：${data.order_no || data.id}`)
      router.push('/mall/orders')
    } else {
      alert(data.message || data.error || '提交失败')
    }
  } catch (e) {
    alert('提交失败：' + e.message)
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  if (!userId.value) { router.push('/mall/login'); return }
  await Promise.all([loadCart(), loadAddresses()])
  loading.value = false
})
</script>
