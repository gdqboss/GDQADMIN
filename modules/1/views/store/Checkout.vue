<template>
  <div class="pb-20">
    <div class="flex items-center gap-2 p-4 border-b bg-white sticky top-0 z-10">
      <button @click="$router.back()" class="p-1">
        <span class="material-symbols-outlined">arrow_back</span>
      </button>
      <h1 class="font-bold text-base">提交订单</h1>
    </div>

    <!-- 收货信息 -->
    <div class="bg-white p-4 m-3 rounded-xl">
      <div v-if="!address" class="text-center py-4 text-gray-400">
        <span class="material-symbols-outlined text-3xl">location_on</span>
        <p class="text-sm mt-1">请填写收货地址</p>
      </div>
      <div v-else class="space-y-1">
        <p class="font-medium">{{ address.name }} {{ address.phone }}</p>
        <p class="text-sm text-gray-500">{{ address.address }}</p>
      </div>
    </div>

    <!-- 商品清单 -->
    <div class="bg-white mt-2 mx-3 rounded-xl overflow-hidden">
      <div v-for="item in items" :key="item.id" class="flex gap-3 p-4 border-b last:border-0">
        <div class="w-16 h-16 bg-gray-100 rounded-lg flex-shrink-0 overflow-hidden">
          <img v-if="item.image" :src="'/' + item.image" class="w-full h-full object-cover" @error="e => e.target.style.display='none'" />
        </div>
        <div class="flex-1">
          <p class="text-sm line-clamp-1">{{ item.name }}</p>
          <div class="flex justify-between mt-1">
            <span class="text-xs text-gray-400">x{{ item.qty }}</span>
            <span class="text-sm text-red-500 font-bold">¥{{ ((item.price || 0) * item.qty).toFixed(2) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 地址编辑 -->
    <div class="bg-white mt-2 mx-3 p-4 rounded-xl">
      <p class="text-sm text-gray-500 mb-2">收货人</p>
      <input v-model="form.receiver_name" type="text" placeholder="姓名" class="w-full border rounded-lg px-3 py-2 text-sm mb-3" />
      <p class="text-sm text-gray-500 mb-2">联系电话</p>
      <input v-model="form.receiver_phone" type="tel" placeholder="手机号" class="w-full border rounded-lg px-3 py-2 text-sm mb-3" />
      <p class="text-sm text-gray-500 mb-2">收货地址</p>
      <input v-model="form.receiver_address" type="text" placeholder="详细地址" class="w-full border rounded-lg px-3 py-2 text-sm" />
    </div>

    <!-- 底部提交 -->
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t p-3 flex items-center gap-3">
      <div class="flex-1">
        <span class="text-sm text-gray-500">合计：</span>
        <span class="text-xl font-bold text-red-500">¥{{ totalPrice }}</span>
      </div>
      <button @click="submitOrder"
        :disabled="submitting || !form.receiver_name || !form.receiver_phone"
        class="px-6 py-3 bg-red-500 text-white rounded-xl font-medium text-sm disabled:bg-gray-300">
        {{ submitting ? '提交中...' : '提交订单' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter()

const items = ref([])
const submitting = ref(false)
const address = ref(null)
const form = ref({ receiver_name: '', receiver_phone: '', receiver_address: '' })

const totalPrice = computed(() => items.value.reduce((s, i) => s + (i.price || 0) * i.qty, 0).toFixed(2))

async function submitOrder() {
  if (!localStorage.getItem('mall_user_id')) {
    router.push('/mall/login'); return
  }
  submitting.value = true
  try {
    const res = await fetch('/api/store-mall/orders', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        user_id: localStorage.getItem('mall_user_id'),
        items: items.value,
        total_amount: totalPrice.value,
        receiver_name: form.value.receiver_name,
        receiver_phone: form.value.receiver_phone,
        receiver_address: form.value.receiver_address
      })
    })
    const data = await res.json()
    if (data.order_id) {
      localStorage.setItem('mall_cart', '[]')
      window.dispatchEvent(new Event('mall_cart_updated'))
      alert('订单提交成功！订单号：' + data.order_no)
      router.push('/mall/orders')
    } else {
      alert(data.message || '提交失败')
    }
  } catch (e) {
    alert('提交失败：' + e.message)
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  items.value = JSON.parse(localStorage.getItem('mall_cart') || '[]')
  if (!items.value.length) router.push('/mall/cart')
})
</script>