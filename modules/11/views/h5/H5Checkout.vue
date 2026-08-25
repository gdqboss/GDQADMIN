<template>
  <div class="min-h-screen bg-slate-50 pb-24">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center">
      <button @click="$router.back()" class="flex-shrink-0"><span class="material-symbols-outlined text-2xl text-slate-600">arrow_back</span></button>
      <h2 class="text-lg font-semibold">{{ $t('h5.confirmOrder') }}</h2>
    </div>

    <!-- 收货地址 -->
    <div @click="showAddressPicker = true" class="mx-4 mt-3 bg-white rounded-xl p-4 flex items-center gap-3">
      <span class="material-symbols-outlined text-xl text-primary flex-shrink-0" style="font-variation-settings: 'FILL' 1">location_on</span>
      <div v-if="address" class="flex-1">
        <div class="text-sm font-medium">{{ address.consignee }} {{ address.phone }}</div>
        <div class="text-xs text-slate-500 mt-1">{{ address.province }} {{ address.city }} {{ address.district }} {{ address.detail }}</div>
      </div>
      <div v-else class="flex-1 text-sm text-slate-400">请选择收货地址</div>
      <span class="material-symbols-outlined text-base text-slate-400">chevron_right</span>
    </div>

    <!-- 商品列表 -->
    <div class="mx-4 mt-3 bg-white rounded-xl overflow-hidden">
      <div v-for="item in orderItems" :key="item.product_id" class="p-4 flex gap-3 border-b last:border-0">
        <img :src="item.image_url || '/images/placeholder.png'" class="w-16 h-16 rounded-lg object-cover bg-slate-100 flex-shrink-0" />
        <div class="flex-1 min-w-0">
          <div class="text-sm font-medium line-clamp-2">{{ item.product_name }}</div>
          <div class="text-xs text-slate-400 mt-1">{{ item.sku_name || '默认规格' }}</div>
          <div class="flex justify-between items-center mt-1">
            <span class="text-red-500 font-bold">¥{{ item.price }}</span>
            <span class="text-xs text-slate-400">x{{ item.quantity }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 配送方式 -->
    <div class="mx-4 mt-3 bg-white rounded-xl p-4">
      <div class="text-sm font-medium mb-2">配送方式</div>
      <div class="flex gap-2">
        <button v-for="m in deliveryMethods" :key="m.value"
          @click="deliveryMethod = m.value"
          :class="['px-4 py-2 rounded-full text-xs transition-all border',
            deliveryMethod === m.value ? 'border-primary bg-primary/5 text-primary' : 'border-slate-200 text-slate-500']">
          {{ m.label }}
        </button>
      </div>
    </div>

    <!-- 优惠券 -->
    <div @click="showCoupons = true" class="mx-4 mt-3 bg-white rounded-xl p-4 flex items-center justify-between">
      <span class="text-sm">优惠券</span>
      <div class="flex items-center gap-2">
        <span v-if="selectedCoupon" class="text-xs text-red-500">-¥{{ selectedCoupon.discount }}</span>
        <span v-else class="text-xs text-slate-400">暂无可用</span>
        <span class="material-symbols-outlined text-base text-slate-400">chevron_right</span>
      </div>
    </div>

    <!-- 订单备注 -->
    <div class="mx-4 mt-3 bg-white rounded-xl p-4">
      <div class="text-sm font-medium mb-2">备注</div>
      <textarea v-model="remark" rows="2" placeholder="选填，可备注特殊需求" class="w-full bg-slate-50 rounded-lg px-3 py-2 text-sm resize-none"></textarea>
    </div>

    <!-- 结算 -->
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t z-30 px-4 py-3 flex items-center gap-3">
      <div class="flex-1">
        <div class="text-xs text-slate-500">实付款</div>
        <div class="text-xl text-red-500 font-bold">¥{{ finalAmount }}</div>
      </div>
      <button @click="submitOrder" :disabled="submitting || !address"
        class="px-8 py-3 bg-primary text-white rounded-full font-medium text-sm disabled:opacity-50">
        {{ submitting ? '提交中...' : '提交订单' }}
      </button>
    </div>

    <!-- 地址选择弹窗 -->
    <div v-if="showAddressPicker" class="fixed inset-0 bg-black/50 z-50 flex items-end" @click.self="showAddressPicker=false">
      <div class="bg-white w-full rounded-t-2xl max-h-[70vh] overflow-y-auto p-4">
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-semibold">选择收货地址</h3>
          <button @click="showAddressPicker=false" class="text-slate-400">✕</button>
        </div>
        <div v-for="addr in addresses" :key="addr.id"
          @click="selectAddress(addr)"
          :class="['p-4 rounded-xl mb-2 border cursor-pointer transition-all',
            address?.id === addr.id ? 'border-primary bg-primary/5' : 'border-slate-100']">
          <div class="text-sm font-medium">{{ addr.consignee }} {{ addr.phone }}</div>
          <div class="text-xs text-slate-500 mt-1">{{ addr.province }} {{ addr.city }} {{ addr.district }} {{ addr.detail }}</div>
        </div>
        <button @click="$router.push('/h5/address/edit')" class="w-full py-3 border border-dashed border-slate-300 rounded-xl text-sm text-slate-500 mt-2">
          + 新增收货地址
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const orderItems = ref([])
const address = ref(null)
const addresses = ref([])
const showAddressPicker = ref(false)
const showCoupons = ref(false)
const selectedCoupon = ref(null)
const remark = ref('')
const deliveryMethod = ref('express')
const submitting = ref(false)

const deliveryMethods = [
  { value: 'express', label: '快递配送' },
  { value: 'selfpickup', label: '上门自提' },
]

onMounted(() => {
  const items = router.currentRoute.value.query.items
  if (items) orderItems.value = JSON.parse(items)
  loadAddresses()
  loadCoupons()
})

function loadAddresses() {
  fetch('/api/mall/addresses', { headers: { Authorization: 'Bearer ' + localStorage.getItem('mall_token') } })
    .then(r => r.json())
    .then(res => {
      if (res.code === 0) {
        addresses.value = res.data || []
        address.value = addresses.value.find(a => a.is_default) || addresses.value[0] || null
      }
    })
}

function loadCoupons() {
  fetch('/api/mall/user-coupons?status=available', { headers: { Authorization: 'Bearer ' + localStorage.getItem('mall_token') } })
    .then(r => r.json())
    .then(res => {
      if (res.code === 0 && res.data?.length) {
        selectedCoupon.value = res.data[0]
      }
    })
}

function selectAddress(addr) {
  address.value = addr
  showAddressPicker.value = false
}

const finalAmount = computed(() => {
  let total = orderItems.value.reduce((s, i) => s + parseFloat(i.price) * i.quantity, 0)
  if (selectedCoupon.value) total -= parseFloat(selectedCoupon.value.discount)
  return total.toFixed(2)
})

function submitOrder() {
  if (!address.value) return
  submitting.value = true
  const body = {
    address_id: address.value.id,
    items: orderItems.value.map(i => ({ product_id: i.product_id, sku_id: i.sku_id, quantity: i.quantity })),
    remark: remark.value,
    delivery_method: deliveryMethod.value,
    coupon_id: selectedCoupon.value?.id
  }
  fetch('/api/mall/orders', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + localStorage.getItem('mall_token') },
    body: JSON.stringify(body)
  }).then(r => r.json()).then(res => {
    if (res.code === 0) {
      router.push({ path: '/h5/order-pay', query: { order_no: res.data.order_no, amount: finalAmount.value } })
    } else {
      alert(res.message || '下单失败')
    }
    submitting.value = false
  }).catch(() => { submitting.value = false })
}
</script>
