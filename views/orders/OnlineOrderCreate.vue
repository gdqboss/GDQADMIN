<script setup>
/**
 * 门店批量订货选择表
 * 路径：/orders/create
 * 功能：店长对仓库有库存的商品按 SKU 维度填写订购数量
 *
 * 业务流：
 *   1. 顶部选门店（STR/BRANCH）→ 自动带区域（ARC）
 *   2. 表格横向是商品，纵向是 SKU（颜色 × 尺寸）
 *   3. 输入数量 → 实时算 T.QTY + 总金额
 *   4. 提交 → POST /api/online-orders → 状态进入 pending（待审核）
 */
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { formatSkuLabel } from '../../utils/sku.js'

const { t } = useI18n()
const router = useRouter()

// ─── 数据 ─────────────────────────────────────────────────────────────
const stores = ref([])               // 门店列表
const suppliers = ref([])             // 供货商列表
const selectedStoreId = ref(null)    // 选中的门店
const selectedSupplierId = ref(null) // 选中的供货商
const deliveryAddress = ref('')
const deliveryContact = ref('')
const deliveryPhone = ref('')
const remark = ref('')

// 商品 + 库存数据
const products = ref([])              // warehouse_stock 中有库存的商品
const loading = ref(false)
const submitting = ref(false)

// 数量输入：{ product_id-sku_id: quantity }
const quantities = ref({})

// ─── 选中的门店信息 ─────────────────────────────────────────────────
const selectedStore = computed(() => stores.value.find(s => s.id === selectedStoreId.value))

// ─── 计算总览 ───────────────────────────────────────────────────────
const totalQty = computed(() => {
  return Object.values(quantities.value).reduce((sum, q) => sum + (Number(q) || 0), 0)
})

const totalAmount = computed(() => {
  let amount = 0
  for (const row of orderItems.value) {
    amount += Number(row.unit_price) * Number(row.quantity)
  }
  return amount
})

// ─── 计算实际下单的 items ───────────────────────────────────────────
const orderItems = computed(() => {
  const items = []
  for (const product of products.value) {
    const skus = product.skus && product.skus.length > 0
      ? product.skus
      : [{ id: null, sku_code: product.sku, color: null, size: null, unit_price: product.sale_price }]
    for (const sku of skus) {
      const key = product.id + '-' + (sku.id || 'none')
      const qty = Number(quantities.value[key] || 0)
      if (qty > 0) {
        items.push({
          product_id: product.id,
          product_sku_id: sku.id,
          product_name: product.name,
          product_image: product.image_main,
          product_model: product.sku,
          product_color: sku.color || null,
          product_size: sku.size || null,
          sku_code: sku.sku_code,
          unit_price: Number(sku.unit_price || product.sale_price || 0),
          quantity: qty
        })
      }
    }
  }
  return items
})

// ─── 加载数据 ───────────────────────────────────────────────────────
async function loadStores() {
  try {
    const r = await api.get('/stores')
    if (r.code === 0) stores.value = r.data || []
  } catch (e) {
    console.error('loadStores', e)
  }
}

async function loadSuppliers() {
  try {
    const r = await api.get('/suppliers')
    if (r.code === 0) suppliers.value = r.data || []
  } catch (e) {
    console.error('loadSuppliers', e)
  }
}

async function loadProducts() {
  loading.value = true
  try {
    // 拿所有上架商品（LEFT JOIN，无库存也返回） + 它们的 SKU
    const r = await api.get('/warehouses/available-products')
    if (r.code === 0) {
      products.value = r.data || []
    } else {
      // 兜底（理论上不会走）
      const [pRes, wRes] = await Promise.all([
        api.get('/products?page=1&size=100'),
        api.get('/warehouse-stock?page=1&size=100')
      ])
      const stockMap = {}
      for (const w of (wRes.data?.list || [])) {
        stockMap[w.product_id] = (stockMap[w.product_id] || 0) + Number(w.quantity)
      }
      const list = (pRes.data?.list || []).map(p => {
        p.total_stock = stockMap[p.id] || 0
        return p
      })
      products.value = list
    }
  } catch (e) {
    ElMessage.error(t('order.loadProductsFailed') + ': ' + (e?.message || ''))
  } finally {
    loading.value = false
  }
}

// ─── 搜索/筛选（按确认键才过滤，避免输入时频繁刷新） ──────────────────
const searchKeyword = ref('')
const pendingKeyword = ref('')  // 输入框的临时值
const isSearchClicked = ref(false)  // 用户是否点过"确认"按钮（视觉反馈）
const selectedCategory = ref('')
function confirmSearch() {
  // 按确认键/Enter → 把输入值提交为实际搜索关键字
  searchKeyword.value = pendingKeyword.value.trim()
  isSearchClicked.value = true
  setTimeout(() => isSearchClicked.value = false, 800)
}
function clearSearch() {
  pendingKeyword.value = ''
  searchKeyword.value = ''
  selectedCategory.value = ''
}
// 多规格商品展开状态：{ productId: true }（默认全部折叠）
const expandedSkus = ref({})
function toggleSkuExpand(productId) {
  expandedSkus.value[productId] = !expandedSkus.value[productId]
  expandedSkus.value = { ...expandedSkus.value }  // 触发响应式
}
// 计算某商品所有 SKU 的小计（已选 quantity × unit_price 的总和）
function productSubtotal(productId, defaultPrice) {
  let sum = 0
  const skuList = products.value.find(p => p.id === productId)?.skus || []
  for (const sku of skuList) {
    const q = Number(getQty(productId, sku.id)) || 0
    const p = Number(sku.unit_price || defaultPrice || 0)
    sum += q * p
  }
  return sum
}
const categories = computed(() => {
  const set = new Set()
  for (const p of products.value) {
    if (p.category) set.add(p.category)
  }
  return Array.from(set).sort()
})

// ─── 动态规格筛选（颜色/尺寸/公斤/毫升/...自动从 specs 提取）───
const selectedSpecs = ref({})  // { '颜色': Set(['黑', '白']), '尺寸': Set(['20']) }
// 解析 SKU.specs（可能是 JSON 字符串或对象），提取所有 spec key → values
function parseSpecs(specs) {
  if (!specs) return {}
  if (typeof specs === 'object') return specs
  try { return JSON.parse(specs) } catch { return {} }
}
const specKeyValues = computed(() => {
  const map = {}  // { '颜色': Set([...]), '尺寸': Set([...]) }
  for (const p of products.value) {
    for (const sku of (p.skus || [])) {
      const specs = parseSpecs(sku.specs)
      for (const [k, v] of Object.entries(specs)) {
        if (v == null || v === '') continue
        if (!map[k]) map[k] = new Set()
        map[k].add(String(v))
      }
    }
  }
  // 转成有序数组（key 按中文/英文顺序）
  return Object.keys(map).sort().reduce((acc, k) => {
    acc[k] = Array.from(map[k]).sort()
    return acc
  }, {})
})
function toggleSpec(key, value) {
  if (!selectedSpecs.value[key]) selectedSpecs.value[key] = new Set()
  // 改用 Set 的包装对象（reactive 不追踪 Set 内部变化，用数组代替）
  const arr = Array.from(selectedSpecs.value[key])
  const idx = arr.indexOf(value)
  if (idx >= 0) arr.splice(idx, 1)
  else arr.push(value)
  selectedSpecs.value[key] = arr
  // 触发响应式
  selectedSpecs.value = { ...selectedSpecs.value }
}
function isSpecSelected(key, value) {
  return (selectedSpecs.value[key] || []).includes(value)
}
function clearAllSpecs() {
  selectedSpecs.value = {}
}
// 过滤 SKU（按选中 specs）— 商品行内只保留匹配的 SKU
function filterSkus(skus) {
  if (!Object.keys(selectedSpecs.value).length) return skus || []
  return (skus || []).filter(sku => {
    const specs = parseSpecs(sku.specs)
    for (const [k, values] of Object.entries(selectedSpecs.value)) {
      if (!values.length) continue
      if (!values.includes(String(specs[k]))) return false  // AND 关系
    }
    return true
  })
}
const filteredProducts = computed(() => {
  const kw = searchKeyword.value.trim().toLowerCase()
  return products.value.filter(p => {
    if (selectedCategory.value && p.category !== selectedCategory.value) return false
    if (kw) {
      const name = (p.name || '').toLowerCase()
      const sku = (p.sku || '').toLowerCase()
      if (!name.includes(kw) && !sku.includes(kw)) return false
    }
    return true
  })
})
// 统计：多少商品被选中（任意 SKU quantity > 0）
const selectedProductCount = computed(() => {
  let n = 0
  for (const p of filteredProducts.value) {
    const has = (p.skus || []).some(s => (quantities.value[p.id + '-' + s.id] || 0) > 0)
      || (!p.skus || p.skus.length === 0) && (quantities.value[p.id + '-none'] || 0) > 0
    if (has) n++
  }
  return n
})
// 统计：当前规格筛选命中的 SKU 总数
const matchedSkuCount = computed(() => {
  let n = 0
  for (const p of filteredProducts.value) {
    n += filterSkus(p.skus).length
  }
  return n
})
// 是否有任何规格筛选生效
const hasSpecFilter = computed(() => {
  return Object.values(selectedSpecs.value).some(arr => arr.length > 0)
})

onMounted(async () => {
  await Promise.all([loadStores(), loadSuppliers(), loadProducts()])
})

// ─── 数量操作 ───────────────────────────────────────────────────────
function setQty(productId, skuId, value) {
  const key = productId + '-' + (skuId || 'none')
  const num = Number(value) || 0
  if (num < 0) {
    ElMessage.warning(t('order.qtyNonNegative'))
    return
  }
  if (num === 0) {
    delete quantities.value[key]
    quantities.value = { ...quantities.value }
  } else {
    quantities.value = { ...quantities.value, [key]: num }
  }
}

function getQty(productId, skuId) {
  const key = productId + '-' + (skuId || 'none')
  return quantities.value[key] || ''
}

function clearAll() {
  ElMessageBox.confirm(t('order.confirmClearAll'), t('common.confirm'), {
    confirmButtonText: t('common.confirm'),
    cancelButtonText: t('common.cancel'),
    type: 'warning'
  }).then(() => {
    quantities.value = {}
  }).catch(() => {})
}

// ─── 提交 ───────────────────────────────────────────────────────────
async function submit() {
  if (!selectedStoreId.value) {
    ElMessage.warning(t('order.selectStoreFirst'))
    return
  }
  if (orderItems.value.length === 0) {
    ElMessage.warning(t('order.emptyOrder'))
    return
  }
  submitting.value = true
  try {
    const payload = {
      store_id: selectedStoreId.value,
      supplier_id: selectedSupplierId.value || null,
      delivery_address: deliveryAddress.value || null,
      delivery_contact: deliveryContact.value || null,
      delivery_phone: deliveryPhone.value || null,
      remark: remark.value || null,
      items: orderItems.value.map(it => ({
        ...it,
        unit_price: it.unit_price.toFixed(2)
      }))
    }
    const r = await api.post('/online-orders', payload)
    if (r.code === 0) {
      ElMessage.success(t('order.submitSuccess') + ': ' + r.data.order_no)
      router.push('/orders/' + r.data.id)
    } else {
      ElMessage.error(r.message || t('order.submitFailed'))
    }
  } catch (e) {
    ElMessage.error(t('order.submitFailed') + ': ' + (e?.message || ''))
  } finally {
    submitting.value = false
  }
}

// ─── SKU 标签 ───────────────────────────────────────────────────────
function skuLabel(sku) {
  if (!sku) return ''
  return formatSkuLabel(sku)
}
</script>

<template>
  <div>
    <PageHeader
      :title="t('order.createTitle')"
      :subtitle="t('order.createSubtitle')"
    >
      <template #actions>
        <button @click="router.back()" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm hover:bg-gray-50">
          <span class="material-symbols-outlined text-[18px] align-middle mr-1">arrow_back</span>
          {{ t('common.back') }}
        </button>
      </template>
    </PageHeader>

    <!-- 顶部：门店 + 供货商 + 收货信息 -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-5 mb-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-xs text-text-secondary mb-1">{{ t('order.store') }} <span class="text-red-500">*</span></label>
          <select v-model="selectedStoreId" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-primary focus:outline-none">
            <option :value="null">{{ t('order.selectStore') }}</option>
            <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-text-secondary mb-1">{{ t('order.region') }}</label>
          <input :value="selectedStore?.region || selectedStore?.area || ''" readonly
                 class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 text-text-secondary" />
        </div>
        <div>
          <label class="block text-xs text-text-secondary mb-1">{{ t('order.supplier') }}</label>
          <select v-model="selectedSupplierId" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-primary focus:outline-none">
            <option :value="null">{{ t('order.selectSupplier') }}</option>
            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-text-secondary mb-1">{{ t('order.deliveryContact') }}</label>
          <input v-model="deliveryContact" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-primary focus:outline-none" :placeholder="t('order.contactPlaceholder')" />
        </div>
        <div>
          <label class="block text-xs text-text-secondary mb-1">{{ t('order.deliveryPhone') }}</label>
          <input v-model="deliveryPhone" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-primary focus:outline-none" :placeholder="t('order.phonePlaceholder')" />
        </div>
        <div class="md:col-span-2">
          <label class="block text-xs text-text-secondary mb-1">{{ t('order.deliveryAddress') }}</label>
          <input v-model="deliveryAddress" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-primary focus:outline-none" :placeholder="t('order.addressPlaceholder')" />
        </div>
        <div>
          <label class="block text-xs text-text-secondary mb-1">{{ t('order.remark') }}</label>
          <input v-model="remark" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-primary focus:outline-none" :placeholder="t('order.remarkPlaceholder')" />
        </div>
      </div>
    </div>

    <!-- 订货表格 -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-primary">inventory_2</span>
            <h3 class="font-medium text-text-primary">{{ t('order.orderTable') }}</h3>
            <span class="text-xs text-text-secondary">
              {{ t('order.totalProducts') }}:
              <strong class="text-primary">{{ filteredProducts.length }}</strong> / {{ products.length }}
            </span>
            <span v-if="selectedProductCount > 0" class="text-xs px-2 py-0.5 bg-primary/10 text-primary rounded-full">
              {{ t('order.selectedCount', { n: selectedProductCount }) }}
            </span>
          </div>
          <button @click="clearAll" class="text-xs text-text-secondary hover:text-red-600 flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px]">delete_sweep</span>
            {{ t('order.clearAll') }}
          </button>
        </div>
        <!-- 搜索 + 分类筛选 -->
        <div class="flex items-center gap-3">
          <div class="flex-1 relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
            <input
              v-model="pendingKeyword"
              type="text"
              :placeholder="t('order.searchPlaceholder')"
              @keyup.enter="confirmSearch"
              class="w-full pl-10 pr-10 py-2 text-sm border border-gray-200 rounded-lg focus:border-primary focus:outline-none bg-white"
            />
            <button
              type="button"
              @click="confirmSearch"
              class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary text-[20px] cursor-pointer"
              :title="t('order.searchBtn') || '搜索'"
            >search</button>
          </div>
          <select
            v-model="selectedCategory"
            class="px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-primary focus:outline-none bg-white min-w-[140px]"
          >
            <option value="">{{ t('order.allCategories') }}</option>
            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
          </select>
          <button
            v-if="searchKeyword || pendingKeyword || selectedCategory"
            @click="clearSearch"
            class="text-xs text-text-secondary hover:text-primary"
          >
            {{ t('common.clear') }}
          </button>
        </div>
        <!-- 动态规格筛选（颜色/尺寸/公斤/毫升...）— 自动从 SKU.specs 提取 -->
        <div v-if="Object.keys(specKeyValues).length > 0" class="mt-3 space-y-1.5">
          <div v-for="(values, key) in specKeyValues" :key="key" class="flex items-start gap-2">
            <span class="text-xs text-text-secondary w-14 pt-1 shrink-0">{{ key }}:</span>
            <div class="flex flex-wrap gap-1.5 flex-1">
              <button
                v-for="val in (values.length > 12 ? values.slice(0, 12) : values)"
                :key="val"
                type="button"
                @click="toggleSpec(key, val)"
                :class="[
                  'px-2.5 py-0.5 text-xs rounded-full border transition-colors',
                  isSpecSelected(key, val)
                    ? 'bg-primary text-white border-primary'
                    : 'bg-white text-text-secondary border-gray-200 hover:border-primary hover:text-primary'
                ]"
              >{{ val }}</button>
              <span v-if="values.length > 12" class="text-xs text-text-secondary pt-1">+{{ values.length - 12 }} more</span>
            </div>
          </div>
          <div v-if="hasSpecFilter" class="flex items-center gap-2 pt-1">
            <span class="text-xs text-text-secondary">已选 <strong class="text-primary">{{ matchedSkuCount }}</strong> 个 SKU</span>
            <button @click="clearAllSpecs" class="text-xs text-primary hover:underline">{{ t('common.clear') }}</button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="p-12 text-center text-text-secondary">
        <span class="material-symbols-outlined animate-spin text-4xl">progress_activity</span>
        <p class="mt-2 text-sm">{{ t('common.loading') }}</p>
      </div>

      <div v-else-if="products.length === 0" class="p-12 text-center text-text-secondary">
        <span class="material-symbols-outlined text-6xl text-gray-300">inbox</span>
        <p class="mt-3">{{ t('order.noAvailableProducts') }}</p>
        <p class="text-xs mt-1">{{ t('order.noAvailableProductsHint') }}</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-3 py-3 font-medium text-left w-24">{{ t('order.image') }}</th>
              <th class="px-3 py-3 font-medium text-left min-w-[120px]">{{ t('order.sku') }}</th>
              <th class="px-3 py-3 font-medium text-left">{{ t('order.productName') }}</th>
              <th class="px-3 py-3 font-medium text-left min-w-[160px]">{{ t('order.spec') }}</th>
              <th class="px-3 py-3 font-medium text-right w-24">{{ t('order.unitPrice') }}</th>
              <th class="px-3 py-3 font-medium text-right w-20">{{ t('order.stock') }}</th>
              <th class="px-3 py-3 font-medium text-center w-28">{{ t('order.tQty') }} <span class="text-red-500">*</span></th>
              <th class="px-3 py-3 font-medium text-right w-28">{{ t('order.subtotal') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <template v-for="product in filteredProducts" :key="product.id">
              <!-- 没有 SKU 的商品：一行 -->
              <tr v-if="!product.skus || filterSkus(product.skus).length === 0 && (!product.skus || product.skus.length === 0)" class="hover:bg-gray-50">
                <td class="px-3 py-2">
                  <img v-if="product.image_main" :src="product.image_main" class="w-16 h-16 object-cover rounded border border-gray-100" />
                  <div v-else class="w-16 h-16 bg-gray-100 rounded flex items-center justify-center">
                    <span class="material-symbols-outlined text-gray-300">image</span>
                  </div>
                </td>
                <td class="px-3 py-2 font-mono text-xs text-text-secondary">{{ product.sku }}</td>
                <td class="px-3 py-2 font-medium text-text-primary">{{ product.name }}</td>
                <td class="px-3 py-2 text-xs text-text-secondary">—</td>
                <td class="px-3 py-2 text-right">¥{{ Number(product.sale_price || 0).toFixed(2) }}</td>
                <td class="px-3 py-2 text-right font-medium text-success">{{ product.total_stock || 0 }}</td>
                <td class="px-3 py-2">
                  <input type="number" min="0" :value="getQty(product.id, null)"
                         @input="setQty(product.id, null, $event.target.value)"
                         class="w-full px-2 py-1 border border-gray-200 rounded text-center text-sm focus:border-primary focus:outline-none" />
                </td>
                <td class="px-3 py-2 text-right text-primary font-medium">
                  ¥{{ ((Number(getQty(product.id, null)) || 0) * Number(product.sale_price || 0)).toFixed(2) }}
                </td>
              </tr>
              <!-- 有 SKU 的商品：默认折叠，点 + 展开全部 SKU -->
              <template v-else>
                <tr class="hover:bg-gray-50 cursor-pointer" @click="toggleSkuExpand(product.id)">
                  <td class="px-3 py-2 align-top">
                    <button type="button" class="material-symbols-outlined text-primary text-[20px] hover:bg-primary/10 rounded p-0.5" @click.stop="toggleSkuExpand(product.id)">
                      {{ expandedSkus[product.id] ? 'remove' : 'add' }}
                    </button>
                  </td>
                  <td class="px-3 py-2 align-top">
                    <img v-if="product.image_main" :src="product.image_main" class="w-16 h-16 object-cover rounded border border-gray-100" />
                    <div v-else class="w-16 h-16 bg-gray-100 rounded flex items-center justify-center">
                      <span class="material-symbols-outlined text-gray-300">image</span>
                    </div>
                  </td>
                  <td class="px-3 py-2 align-top font-mono text-xs text-text-secondary">{{ product.sku }}</td>
                  <td class="px-3 py-2 align-top font-medium text-text-primary">{{ product.name }}</td>
                  <td class="px-3 py-2 text-xs text-text-secondary">
                    <span class="px-2 py-0.5 bg-gray-100 rounded text-[11px]">{{ filterSkus(product.skus).length }} {{ t('order.specCount') || '个规格' }}</span>
                  </td>
                  <td class="px-3 py-2 text-right">¥{{ Number(product.sale_price || 0).toFixed(2) }}</td>
                  <td class="px-3 py-2 text-right font-medium text-success">{{ product.total_stock || 0 }}</td>
                  <td class="px-3 py-2 text-center text-text-secondary text-xs">
                    <span v-if="!expandedSkus[product.id]">{{ t('order.clickToExpand') || '点击展开' }}</span>
                  </td>
                  <td class="px-3 py-2 text-right text-primary font-medium">
                    ¥{{ productSubtotal(product.id, product.sale_price).toFixed(2) }}
                  </td>
                </tr>
                <!-- 展开后的 SKU 详情行（每规格一行） -->
                <template v-if="expandedSkus[product.id]">
                  <tr v-for="sku in filterSkus(product.skus)" :key="product.id + '-' + sku.id" class="bg-gray-50/50 hover:bg-gray-100">
                    <td class="px-3 py-2"></td>
                    <td colspan="2" class="px-3 py-2 text-xs text-text-secondary">
                      <span v-for="(v, k) in parseSpecs(sku.specs)" :key="k" class="inline-block mr-2">
                        <span class="text-text-secondary">{{ k }}:</span><span class="text-text-primary ml-0.5 font-medium">{{ v }}</span>
                      </span>
                    </td>
                    <td class="px-3 py-2 text-right text-xs">¥{{ Number(sku.unit_price || product.sale_price || 0).toFixed(2) }}</td>
                    <td class="px-3 py-2 text-right text-success font-medium text-xs">{{ sku.stock || 0 }}</td>
                    <td class="px-3 py-2">
                      <input type="number" min="0" :value="getQty(product.id, sku.id)"
                             @input="setQty(product.id, sku.id, $event.target.value)"
                             class="w-full px-2 py-1 border border-gray-200 rounded text-center text-sm focus:border-primary focus:outline-none" />
                    </td>
                    <td class="px-3 py-2 text-right text-primary font-medium">
                      ¥{{ ((Number(getQty(product.id, sku.id)) || 0) * Number(sku.unit_price || product.sale_price || 0)).toFixed(2) }}
                    </td>
                  </tr>
                </template>
              </template>
            </template>
          </tbody>
          <tfoot v-if="orderItems.length > 0" class="bg-primary/5 font-medium">
            <tr>
              <td colspan="6" class="px-3 py-3 text-right text-text-primary">{{ t('order.total') }}：</td>
              <td class="px-3 py-3 text-center text-primary text-lg font-bold">{{ totalQty }}</td>
              <td class="px-3 py-3 text-right text-primary text-lg font-bold">¥{{ totalAmount.toFixed(2) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- 底部操作栏 -->
    <div class="mt-4 bg-white rounded-lg border border-gray-100 shadow-card p-4 flex items-center justify-between">
      <div class="text-sm text-text-secondary">
        <span>{{ t('order.selectedItems') }}：<strong class="text-primary">{{ orderItems.length }}</strong> {{ t('order.itemsUnit') }}</span>
        <span class="mx-3">|</span>
        <span>{{ t('order.totalQty') }}：<strong class="text-primary">{{ totalQty }}</strong></span>
        <span class="mx-3">|</span>
        <span>{{ t('order.totalAmount') }}：<strong class="text-primary text-lg">¥{{ totalAmount.toFixed(2) }}</strong></span>
      </div>
      <div class="flex items-center gap-2">
        <button @click="router.back()" class="px-5 py-2 border border-gray-200 rounded-lg text-sm hover:bg-gray-50">
          {{ t('common.cancel') }}
        </button>
        <button @click="submit" :disabled="submitting || orderItems.length === 0"
                class="px-6 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]" v-if="!submitting">send</span>
          <span class="material-symbols-outlined text-[18px] animate-spin" v-else>progress_activity</span>
          {{ submitting ? t('order.submitting') : t('order.submitOrder') }}
        </button>
      </div>
    </div>
  </div>
</template>