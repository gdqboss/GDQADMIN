<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-semibold">{{ $t('nav.seckill') }}</h2>
      <button @click="showActivityModal = true" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">
        + {{ $t('seckill.createActivity') }}
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-4 bg-slate-100 p-1 rounded-lg w-fit">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
        :class="['px-4 py-1.5 rounded-md text-sm font-medium transition-all', activeTab === tab.key ? 'bg-white shadow text-primary' : 'text-slate-600 hover:text-slate-900']">
        {{ tab.label }}
      </button>
    </div>

    <!-- 活动列表 -->
    <div v-if="activeTab === 'activities'">
      <div class="flex gap-3 mb-4">
        <select v-model="filterStatus" @change="loadActivities" class="border rounded-lg px-3 py-2 text-sm">
          <option value="">{{ $t('common.allStatus') }}</option>
          <option value="pending">{{ $t('seckill.pending') }}</option>
          <option value="active">{{ $t('seckill.active') }}</option>
          <option value="paused">{{ $t('seckill.paused') }}</option>
          <option value="ended">{{ $t('seckill.ended') }}</option>
        </select>
      </div>
      <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left">{{ $t('seckill.activityName') }}</th>
              <th class="px-4 py-3">{{ $t('seckill.timeRange') }}</th>
              <th class="px-4 py-3">{{ $t('seckill.status') }}</th>
              <th class="px-4 py-3">{{ $t('seckill.productCount') }}</th>
              <th class="px-4 py-3">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="a in activities" :key="a.id" class="hover:bg-slate-50">
              <td class="px-4 py-3">
                <div class="font-medium">{{ a.name }}</div>
                <div class="text-xs text-slate-400">{{ a.description }}</div>
              </td>
              <td class="px-4 py-3 text-center text-xs">
                <div>{{ a.start_time }}</div>
                <div class="text-slate-400">~</div>
                <div>{{ a.end_time }}</div>
              </td>
              <td class="px-4 py-3 text-center">
                <span :class="['px-2 py-0.5 rounded-full text-xs font-medium',
                  a.status === 'active' ? 'bg-green-100 text-green-700' :
                  a.status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                  a.status === 'paused' ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-500']">
                  {{ $t(`seckill.${a.status}`) }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">{{ a.product_count }}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex gap-2 justify-center">
                  <button @click="editActivity(a)" class="text-primary text-xs hover:underline">{{ $t('common.edit') }}</button>
                  <button @click="manageProducts(a)" class="text-blue-600 text-xs hover:underline">{{ $t('seckill.manageProducts') }}</button>
                  <button @click="deleteActivity(a.id)" class="text-red-500 text-xs hover:underline">{{ $t('common.delete') }}</button>
                </div>
              </td>
            </tr>
            <tr v-if="!activities.length">
              <td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ $t('common.noData') }}</td>
            </tr>
          </tbody>
        </table>
        <div class="p-3 border-t flex justify-end" v-if="activityTotal > pageSize">
          <div class="flex gap-2">
            <button @click="activityPage--" :disabled="activityPage<=1" class="px-3 py-1 border rounded text-sm disabled:opacity-50">{{ $t('common.prev') }}</button>
            <span class="px-3 py-1 text-sm">{{ activityPage }}/{{ Math.ceil(activityTotal/pageSize) }}</span>
            <button @click="activityPage++" :disabled="activityPage>=Math.ceil(activityTotal/pageSize)" class="px-3 py-1 border rounded text-sm disabled:opacity-50">{{ $t('common.next') }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 秒杀商品管理 -->
    <div v-if="activeTab === 'products'">
      <div class="mb-3 flex items-center gap-3">
        <button @click="activeTab='activities'" class="text-sm text-slate-500 hover:text-primary">← {{ $t('common.back') }}</button>
        <span class="font-medium">{{ currentActivity?.name || $t('seckill.selectActivity') }}</span>
        <select v-model="selectedActivityId" @change="loadProducts" class="border rounded-lg px-3 py-2 text-sm">
          <option value="">{{ $t('seckill.selectActivity') }}</option>
          <option v-for="a in activities" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>
        <button v-if="selectedActivityId" @click="showProductModal = true" class="px-4 py-2 bg-primary text-white rounded-lg text-sm">
          + {{ $t('seckill.addProduct') }}
        </button>
      </div>
      <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left">{{ $t('product.name') }}</th>
              <th class="px-4 py-3">{{ $t('seckill.seckillPrice') }}</th>
              <th class="px-4 py-3">{{ $t('seckill.originalPrice') }}</th>
              <th class="px-4 py-3">{{ $t('seckill.stock') }}</th>
              <th class="px-4 py-3">{{ $t('seckill.sold') }}</th>
              <th class="px-4 py-3">{{ $t('seckill.limitPerUser') }}</th>
              <th class="px-4 py-3">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="p in products" :key="p.id" class="hover:bg-slate-50">
              <td class="px-4 py-3">
                <div class="font-medium">{{ p.product_name }}</div>
              </td>
              <td class="px-4 py-3 text-center text-red-600 font-medium">¥{{ p.seckill_price }}</td>
              <td class="px-4 py-3 text-center text-slate-400 line-through">¥{{ p.original_price }}</td>
              <td class="px-4 py-3 text-center">{{ p.stock - p.sold }} / {{ p.stock }}</td>
              <td class="px-4 py-3 text-center">{{ p.sold }}</td>
              <td class="px-4 py-3 text-center">{{ p.max_per_user }}</td>
              <td class="px-4 py-3 text-center">
                <button @click="editProduct(p)" class="text-primary text-xs hover:underline">{{ $t('common.edit') }}</button>
                <button @click="deleteProduct(p.id)" class="text-red-500 text-xs hover:underline ml-2">{{ $t('common.delete') }}</button>
              </td>
            </tr>
            <tr v-if="!products.length">
              <td colspan="7" class="px-4 py-8 text-center text-slate-400">{{ $t('common.noData') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 秒杀订单 -->
    <div v-if="activeTab === 'orders'">
      <div class="bg-white rounded-xl shadow">
        <div class="p-4 border-b flex gap-3">
          <select v-model="orderStatus" @change="loadOrders" class="border rounded-lg px-3 py-2 text-sm">
            <option value="">{{ $t('common.allStatus') }}</option>
            <option value="pending">{{ $t('order.pendingPay') }}</option>
            <option value="paid">{{ $t('order.paid') }}</option>
            <option value="cancelled">{{ $t('order.cancelled') }}</option>
            <option value="refunded">{{ $t('order.refunded') }}</option>
          </select>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3">{{ $t('order.orderNo') }}</th>
              <th class="px-4 py-3">{{ $t('order.member') }}</th>
              <th class="px-4 py-3">{{ $t('product.name') }}</th>
              <th class="px-4 py-3">{{ $t('order.amount') }}</th>
              <th class="px-4 py-3">{{ $t('order.status') }}</th>
              <th class="px-4 py-3">{{ $t('order.createdAt') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="o in orders" :key="o.id" class="hover:bg-slate-50">
              <td class="px-4 py-3 font-mono text-xs">{{ o.order_no }}</td>
              <td class="px-4 py-3">{{ o.user_id }}</td>
              <td class="px-4 py-3">{{ o.product_name }}</td>
              <td class="px-4 py-3 text-red-600 font-medium">¥{{ o.total_amount }}</td>
              <td class="px-4 py-3">
                <span :class="['px-2 py-0.5 rounded-full text-xs',
                  o.status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500']">
                  {{ o.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-xs text-slate-500">{{ o.created_at }}</td>
            </tr>
            <tr v-if="!orders.length">
              <td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ $t('common.noData') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 创建/编辑活动弹窗 -->
    <div v-if="showActivityModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showActivityModal=false">
      <div class="bg-white rounded-xl p-6 w-full max-w-lg">
        <h3 class="text-lg font-semibold mb-4">{{ editingActivity ? $t('seckill.editActivity') : $t('seckill.createActivity') }}</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm mb-1">{{ $t('seckill.activityName') }} *</label>
            <input v-model="activityForm.name" type="text" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm mb-1">{{ $t('seckill.description') }}</label>
            <textarea v-model="activityForm.description" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm mb-1">{{ $t('seckill.startTime') }} *</label>
              <input v-model="activityForm.start_time" type="datetime-local" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm mb-1">{{ $t('seckill.endTime') }} *</label>
              <input v-model="activityForm.end_time" type="datetime-local" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
          <div>
            <label class="block text-sm mb-1">{{ $t('seckill.status') }}</label>
            <select v-model="activityForm.status" class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="pending">{{ $t('seckill.pending') }}</option>
              <option value="active">{{ $t('seckill.active') }}</option>
              <option value="paused">{{ $t('seckill.paused') }}</option>
              <option value="ended">{{ $t('seckill.ended') }}</option>
            </select>
          </div>
          <div class="flex justify-end gap-2 mt-4">
            <button @click="showActivityModal=false" class="px-4 py-2 border rounded-lg text-sm">{{ $t('common.cancel') }}</button>
            <button @click="saveActivity" class="px-4 py-2 bg-primary text-white rounded-lg text-sm">{{ $t('common.save') }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 添加/编辑商品弹窗 -->
    <div v-if="showProductModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showProductModal=false">
      <div class="bg-white rounded-xl p-6 w-full max-w-lg">
        <h3 class="text-lg font-semibold mb-4">{{ editingProduct ? $t('seckill.editProduct') : $t('seckill.addProduct') }}</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm mb-1">{{ $t('seckill.seckillPrice') }} *</label>
            <input v-model.number="productForm.seckill_price" type="number" step="0.01" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm mb-1">{{ $t('seckill.originalPrice') }}</label>
            <input v-model.number="productForm.original_price" type="number" step="0.01" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm mb-1">{{ $t('seckill.stock') }} *</label>
            <input v-model.number="productForm.stock" type="number" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm mb-1">{{ $t('seckill.limitPerUser') }}</label>
            <input v-model.number="productForm.max_per_user" type="number" class="w-full border rounded-lg px-3 py-2 text-sm" min="1" />
          </div>
          <div class="flex justify-end gap-2 mt-4">
            <button @click="showProductModal=false" class="px-4 py-2 border rounded-lg text-sm">{{ $t('common.cancel') }}</button>
            <button @click="saveProduct" class="px-4 py-2 bg-primary text-white rounded-lg text-sm">{{ $t('common.save') }}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import api from '@/api/seckill'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const tabs = [
  { key: 'activities', label: t('seckill.activities') },
  { key: 'products', label: t('seckill.products') },
  { key: 'orders', label: t('seckill.orders') }
]

const activeTab = ref('activities')
const activities = ref([])
const products = ref([])
const orders = ref([])
const filterStatus = ref('')
const orderStatus = ref('')
const activityPage = ref(1)
const activityTotal = ref(0)
const pageSize = 20

const showActivityModal = ref(false)
const showProductModal = ref(false)
const editingActivity = ref(null)
const editingProduct = ref(null)
const selectedActivityId = ref('')
const currentActivity = ref(null)

const activityForm = ref({ name: '', description: '', start_time: '', end_time: '', status: 'pending' })
const productForm = ref({ seckill_price: '', original_price: '', stock: '', max_per_user: 1 })

watch(activeTab, () => {
  if (activeTab.value === 'activities') loadActivities()
  else if (activeTab.value === 'orders') loadOrders()
})

function loadActivities() {
  api.getActivities({ status: filterStatus.value, page: activityPage.value, pageSize }).then(res => {
    if (res.code === 0) {
      activities.value = res.data.list
      activityTotal.value = res.data.total
    }
  })
}

function loadProducts() {
  if (!selectedActivityId.value) return
  currentActivity.value = activities.value.find(a => a.id == selectedActivityId.value)
  api.getProducts({ activity_id: selectedActivityId.value }).then(res => {
    if (res.code === 0) products.value = res.data.list
  })
}

function loadOrders() {
  api.getOrders({ status: orderStatus.value }).then(res => {
    if (res.code === 0) orders.value = res.data.list
  })
}

function editActivity(a) {
  editingActivity.value = a
  activityForm.value = { name: a.name, description: a.description, start_time: a.start_time.slice(0,16), end_time: a.end_time.slice(0,16), status: a.status }
  showActivityModal.value = true
}

function manageProducts(a) {
  activeTab.value = 'products'
  selectedActivityId.value = a.id
  currentActivity.value = a
  loadProducts()
}

function saveActivity() {
  if (editingActivity.value) {
    api.updateActivity(editingActivity.value.id, activityForm.value).then(() => {
      showActivityModal.value = false
      editingActivity.value = null
      loadActivities()
    })
  } else {
    api.createActivity(activityForm.value).then(() => {
      showActivityModal.value = false
      loadActivities()
    })
  }
}

function deleteActivity(id) {
  if (!confirm(t('common.confirmDelete'))) return
  api.deleteActivity(id).then(() => loadActivities())
}

function editProduct(p) {
  editingProduct.value = p
  productForm.value = { seckill_price: p.seckill_price, original_price: p.original_price, stock: p.stock, max_per_user: p.max_per_user }
  showProductModal.value = true
}

function saveProduct() {
  if (editingProduct.value) {
    api.updateProduct(editingProduct.value.id, productForm.value).then(() => {
      showProductModal.value = false
      editingProduct.value = null
      loadProducts()
    })
  } else {
    api.addProduct({ activity_id: selectedActivityId.value, product_id: null, ...productForm.value }).then(() => {
      showProductModal.value = false
      loadProducts()
    })
  }
}

function deleteProduct(id) {
  if (!confirm(t('common.confirmDelete'))) return
  api.deleteProduct(id).then(() => loadProducts())
}

loadActivities()
</script>
