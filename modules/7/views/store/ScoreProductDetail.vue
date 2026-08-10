<template>
  <div class="pb-20">
    <div v-if="product">
      <!-- 商品图片 -->
      <div class="aspect-square bg-gray-100 relative">
        <img v-if="product.image_main" :src="'/' + product.image_main" class="w-full h-full object-cover"
          @error="e => e.target.style.display='none'" />
        <div v-else class="flex items-center justify-center h-full text-gray-300">
          <span class="material-symbols-outlined text-6xl">card_giftcard</span>
        </div>
        <button @click="$router.back()" class="absolute top-4 left-4 w-9 h-9 rounded-full bg-black/40 text-white flex items-center justify-center">
          <span class="material-symbols-outlined text-xl">arrow_back</span>
        </button>
      </div>

      <!-- 商品信息 -->
      <div class="p-4">
        <div class="flex items-start gap-2 mb-3">
          <span class="flex items-center gap-0.5 text-xl font-bold text-amber-500">
            <span class="material-symbols-outlined">stars</span>{{ product.score_price }}
          </span>
          <span class="text-xs text-gray-400 mt-1">积分</span>
          <span class="ml-auto text-xs text-gray-400">库存 {{ product.stock }}</span>
        </div>
        <h1 class="text-lg font-bold text-gray-800 mb-2">{{ product.name }}</h1>
        <p v-if="product.description" class="text-sm text-gray-500 leading-relaxed">{{ product.description }}</p>
        <p v-if="product.category_name" class="text-xs text-gray-400 mt-2">分类：{{ product.category_name }}</p>
      </div>

      <!-- 我的积分 -->
      <div class="mx-4 p-3 bg-amber-50 rounded-xl flex items-center justify-between mb-4">
        <span class="text-sm text-amber-700">我的积分</span>
        <span class="text-lg font-bold text-amber-600 flex items-center gap-0.5">
          <span class="material-symbols-outlined text-sm">stars</span>{{ balance }}
        </span>
      </div>

      <!-- 兑换表单 -->
      <div class="mx-4 mb-4">
        <div class="flex items-center gap-3 mb-4">
          <span class="text-sm text-gray-600">兑换数量</span>
          <div class="flex items-center gap-2 ml-auto">
            <button @click="quantity > 1 && quantity--" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center text-gray-500">
              <span class="material-symbols-outlined text-sm">remove</span>
            </button>
            <input v-model.number="quantity" type="number" min="1" :max="product.stock"
              class="w-16 h-8 text-center border border-gray-200 rounded-lg text-sm" />
            <button @click="quantity < product.stock && quantity++" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center text-gray-500">
              <span class="material-symbols-outlined text-sm">add</span>
            </button>
          </div>
        </div>

        <!-- 收货地址 -->
        <div class="mb-4">
          <p class="text-sm text-gray-600 mb-2">收货地址</p>
          <div v-if="addresses.length > 0">
            <div v-for="addr in addresses" :key="addr.id"
              class="p-3 border rounded-xl mb-2 cursor-pointer"
              :class="addressId === addr.id ? 'border-amber-400 bg-amber-50' : 'border-gray-100'"
              @click="addressId = addr.id">
              <p class="text-sm font-medium text-gray-800">{{ addr.receiver_name }} {{ addr.receiver_phone }}</p>
              <p class="text-xs text-gray-400 mt-0.5">{{ addr.province }}{{ addr.city }}{{ addr.district }}{{ addr.address }}</p>
            </div>
          </div>
          <div v-else class="p-4 border border-dashed border-gray-200 rounded-xl text-center text-sm text-gray-400">
            暂无收货地址，请先添加
          </div>
        </div>

        <!-- 备注 -->
        <textarea v-model="remark" rows="2" placeholder="备注（选填）"
          class="w-full p-3 border border-gray-200 rounded-xl text-sm mb-4" />

        <!-- 合计 -->
        <div class="flex items-center justify-between mb-4">
          <span class="text-sm text-gray-500">消耗积分</span>
          <span class="text-xl font-bold text-amber-500 flex items-center gap-0.5">
            <span class="material-symbols-outlined text-sm">stars</span>{{ totalScore }}
          </span>
        </div>

        <button @click="doExchange" :disabled="submitting || product.stock === 0 || !addressId"
          class="w-full py-3 rounded-xl text-white font-medium text-sm transition-colors"
          :class="product.stock === 0 || !addressId
            ? 'bg-gray-300 cursor-default'
            : 'bg-amber-500 hover:bg-amber-600'">
          {{ submitting ? '兑换中...' : product.stock === 0 ? '已兑完' : !addressId ? '请选择地址' : '确认兑换' }}
        </button>
      </div>
    </div>
    <div v-else class="flex items-center justify-center h-64 text-gray-400">
      加载中...
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const route = useRoute()
const product = ref(null)
const balance = ref(0)
const addresses = ref([])
const addressId = ref(null)
const quantity = ref(1)
const remark = ref('')
const submitting = ref(false)

const totalScore = computed(() => product.value ? product.value.score_price * quantity.value : 0)

async function fetchProduct() {
  try {
    const res = await api.get(`/score-shop/products/${route.params.id}`)
    if (res.code === 0) product.value = res.data
  } catch (e) {
    ElMessage.error('获取商品失败')
  }
}

async function fetchBalance() {
  try {
    const res = await api.get('/score-shop/balance')
    if (res.code === 0) balance.value = res.data.balance || 0
  } catch {}
}

async function fetchAddresses() {
  try {
    const userId = localStorage.getItem('caimeite_user_id')
    if (!userId) return
    const res = await api.get('/mall/addresses', { params: { user_id: userId } })
    addresses.value = res || []
    if (addresses.value.length > 0) {
      const def = addresses.value.find(a => a.is_default) || addresses.value[0]
      addressId.value = def?.id
    }
  } catch {}
}

async function doExchange() {
  if (!addressId.value) return ElMessage.warning('请选择收货地址')
  if (quantity.value < 1) return ElMessage.warning('数量至少为1')
  if (balance.value < totalScore.value) return ElMessage.warning('积分不足')

  submitting.value = true
  try {
    const res = await api.post('/score-shop/exchange', {
      score_product_id: product.value.id,
      quantity: quantity.value,
      address_id: addressId.value,
      remark: remark.value
    })
    if (res.code === 0) {
      ElMessage.success('兑换成功')
      balance.value -= totalScore.value
      product.value.stock -= quantity.value
      setTimeout(() => history.back(), 1500)
    } else {
      ElMessage.error(res.message || '兑换失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '兑换失败')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchProduct()
  fetchBalance()
  fetchAddresses()
})
</script>
