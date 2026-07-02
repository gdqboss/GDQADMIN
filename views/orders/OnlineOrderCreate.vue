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
import { useUserStore } from '../../stores/user.js'

const { t } = useI18n()
const router = useRouter()
const userStore = useUserStore()

// ─── 当前用户角色（决定是否显示供货商字段）───────────────────────
// 业务规则：
//   shopkeeper（店长）→ 不需要供货商（公司直发，不经经销商）
//   admin/manager → 需要供货商（进货/采购场景）
const isShopkeeper = computed(() => userStore.user?.role === 'shopkeeper')
const showSupplier = computed(() => !isShopkeeper.value)

// ─── 数据 ─────────────────────────────────────────────────────────────
const stores = ref([])               // 门店列表
const suppliers = ref([])             // 供货商列表（admin 才用）
const selectedStoreId = ref(null)    // 选中的门店
const selectedSupplierId = ref(null) // 选中的供货商
const deliveryAddress = ref('')
const deliveryContact = ref('')
const deliveryPhone = ref('')
const remark = ref('')

// 商品 + 库存数据
const products = ref([])              // /preorder/products 返回的可订商品
const loading = ref(false)
const submitting = ref(false)

// 数量输入：{ product_id-sku_id: quantity }
const quantities = ref({})

// ─── 选中的门店信息 ─────────────────────────────────────────────────
const selectedStore = computed(() => stores.value.find(s => s.id === selectedStoreId.value))

// ─── 门店变化时自动填充收货信息（用户未手动修改的前提下） ──────────
// 行为：
//   1. 门店切换时，把 stores 表的 contact/phone/address 写入表单
//   2. 如果用户已经手动改过对应字段（值非空），不覆盖
//   3. 门店清空（id=null）时不修改字段
// ─── 门店变化时：自动填收货信息 + 重新加载商品列表 ──────────────
// 行为：
//   1. 门店切换时，把 stores 表的 contact/phone/address 写入表单（仅在字段为空时）
//   2. 重新调 /preorder/products 加载该门店可订商品
//   3. 门店清空（id=null）时不修改字段，清空商品列表
watch(selectedStoreId, (newId) => {
  if (newId == null) {
    products.value = []
    return
  }
  // 兼容 string/int：<select>.value 永远是 string，store.id 是 int
  const store = stores.value.find(s => String(s.id) === String(newId))
  if (store) {
    // 只在字段为空时填充（用户已填就不动）
    if (!deliveryContact.value && store.contact) deliveryContact.value = store.contact
    if (!deliveryPhone.value && store.phone) deliveryPhone.value = store.phone
    if (!deliveryAddress.value && store.address) deliveryAddress.value = store.address
  }
  // 重载商品列表（按门店过滤）
  loadProducts()
})

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
    // 用 /api/preorder/stores 而非 /api/stores：
    //   admin → 全部门店
    //   shopkeeper/member → 只返回自己关联的门店（user_stores JOIN 过滤）
    const r = await api.get('/preorder/stores')
    if (r.code === 0) {
      stores.value = r.data || []
      // UX：只有 1 个门店时，自动选中（用户不用再点一下）
      // 仅在用户当前未选任何门店时生效，避免覆盖用户主动选择
      if (stores.value.length === 1 && !selectedStoreId.value) {
        selectedStoreId.value = stores.value[0].id
      }
    }
  } catch (e) {
    console.error('loadStores', e)
  }
}

async function loadSuppliers() {
  if (!showSupplier.value) return  // shopkeeper 不需要供货商
  try {
    const r = await api.get('/suppliers')
    if (r.code === 0) suppliers.value = r.data || []
  } catch (e) {
    console.error('loadSuppliers', e)
  }
}

async function loadProducts() {
  if (!selectedStoreId.value) {
    products.value = []
    return
  }
  loading.value = true
  try {
    // 用 /preorder/products 而不是 /warehouses/available-products：
    //   - /preorder/products 返回 is_preorderable=1 的商品（店长预订单商品池）
    //   - shopkeeper 有 preorder:read 权限，inventory:read 缺失
    //   - 传 store_id：独立门店走 is_preorderable 过滤；经销商门店走 dealer_preorder_products
    const r = await api.get(`/preorder/products?store_id=${selectedStoreId.value}`)
    if (r.code === 0) {
      products.value = r.data || []
    } else {
      products.value = []
      ElMessage.error(r.message || t('order.loadProductsFailed'))
    }
  } catch (e) {
    products.value = []
    ElMessage.error(t('order.loadProductsFailed') + ': ' + (e?.message || ''))
  } finally {
    loading.value = false
  }
}

// 门店切换时自动重载商品列表（在上面的 watch 里统一处理）
// （保留这里作为占位说明，避免后续误加重复 watch）

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
  // 顺序：先 loadStores（拿到 selectedStoreId 后自动 watch 触发 loadProducts）
  await loadStores()
  // shopkeeper 不需要供货商，但 admin 需要
  if (showSupplier.value) await loadSuppliers()
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
  // shopkeeper 模式：supplier_id 不能传
  if (showSupplier.value && !selectedSupplierId.value) {
    ElMessage.warning(t('order.selectSupplier') || '请选择供货商')
    return
  }
  submitting.value = true
  try {
    const payload = {
      store_id: selectedStoreId.value,
      delivery_address: deliveryAddress.value || null,
      delivery_contact: deliveryContact.value || null,
      delivery_phone: deliveryPhone.value || null,
      remark: remark.value || null,
      items: orderItems.value.map(it => ({
        product_id: it.product_id,
        sku_id: it.product_sku_id || null,
        quantity: Number(it.quantity),
        box_qty: 0,
        unit_price: Number(it.unit_price || 0)
      }))
    }
    // admin/manager 模式：加 supplier_id
    if (showSupplier.value) {
      payload.supplier_id = selectedSupplierId.value
    }
    // 走预订单 API（而不是 online-orders 完整工作流）
    const r = await api.post('/preorder/create', payload)
    if (r.code === 0) {
      ElMessage.success(t('order.submitSuccess') + ': ' + r.data.order_no)
      router.push('/preorder/summary')
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

// ─── 规格摘要（折叠行直接显示前 3 个 SKU 的规格组合）─────────────
// 输入：sku = { specs: '{"尺寸":"20","颜色":"碳灰-A"}' }
// 输出：'20/碳灰-A'（值用 / 分隔）
function formatSpecSummary(sku) {
  if (!sku) return ''
  let specs = {}
  try {
    specs = typeof sku.specs === 'string' ? JSON.parse(sku.specs) : (sku.specs || {})
  } catch (e) { specs = {} }
  const values = Object.values(specs).filter(v => v != null && v !== '')
  return values.length ? values.join('/') : (sku.sku || '—')
}

// ─── 矩阵式下单核心逻辑 ────────────────────────────────────────────
// 自动识别 2 维规格：颜色 / 尺寸
// 行标题：尺寸（更长的那维），列标题：颜色
// 兼容：1 维规格（行=该维值，列=1 个 "数量" 列）
// 兼容：每行只 1 个 SKU（行=SKU 摘要，列=1 个 input 列）
function getMatrixDimensions(product) {
  const skus = filterSkus(product.skus || [])
  if (!skus.length) return { rows: [], cols: [], rowKey: null, colKey: null }
  // 收集所有 specs key
  const keySet = new Set()
  for (const sku of skus) {
    const specs = parseSpecs(sku.specs)
    for (const k of Object.keys(specs)) keySet.add(k)
  }
  const keys = Array.from(keySet)
  if (keys.length === 0) {
    // specs 全空：行=SKU
    return { rows: skus.map(s => formatSpecSummary(s)), cols: [], rowKey: null, colKey: null, mode: 'flat' }
  }
  if (keys.length === 1) {
    const k = keys[0]
    const vals = Array.from(new Set(skus.map(s => String(parseSpecs(s.specs)[k] ?? '—')))).sort()
    return { rows: vals, cols: [], rowKey: k, colKey: null, mode: '1d' }
  }
  // 2 维或以上：行=第一个 key（习惯：尺寸/规格值），列=第二个 key（颜色等）
  // 选择"行维" = 唯一值更少的那一维（视觉上更适合做行）
  // 选择"列维" = 唯一值更多的那一维
  const counts = keys.map(k => ({
    key: k,
    count: new Set(skus.map(s => String(parseSpecs(s.specs)[k] ?? '—'))).size
  }))
  counts.sort((a, b) => b.count - a.count)  // 多的在前
  const colKey = counts[0].key
  const rowKey = counts[1].key
  const colVals = Array.from(new Set(skus.map(s => String(parseSpecs(s.specs)[colKey] ?? '—')))).sort()
  const rowVals = Array.from(new Set(skus.map(s => String(parseSpecs(s.specs)[rowKey] ?? '—')))).sort()
  return { rows: rowVals, cols: colVals, rowKey, colKey, mode: '2d' }
}
function getMatrixColumns(product) {
  const dim = getMatrixDimensions(product)
  if (dim.mode === 'flat') {
    return { colValues: ['数量'] }
  }
  if (dim.mode === '1d') {
    return { colValues: ['数量'] }
  }
  return { colValues: dim.cols }
}
function getMatrixRows(product) {
  const dim = getMatrixDimensions(product)
  return { rowValues: dim.rows }
}
// 矩阵 cell 找 SKU：返回 (rowVal, colVal) 对应的 SKU 对象
function getSkuByCell(product, rowVal, colVal) {
  const skus = filterSkus(product.skus || [])
  const dim = getMatrixDimensions(product)
  if (dim.mode === 'flat') {
    // rowVal 是 formatSpecSummary
    return skus.find(s => formatSpecSummary(s) === rowVal) || null
  }
  if (dim.mode === '1d') {
    // rowVal 是 dim.rows 里的值，colKey=null
    return skus.find(s => String(parseSpecs(s.specs)[dim.rowKey] ?? '—') === String(rowVal)) || null
  }
  return skus.find(s => {
    const specs = parseSpecs(s.specs)
    return String(specs[dim.rowKey] ?? '—') === String(rowVal) && String(specs[dim.colKey] ?? '—') === String(colVal)
  }) || null
}
// 行小计 = 该行所有 SKU 数量之和
function rowSubtotal(product, rowVal) {
  const skus = filterSkus(product.skus || [])
  const dim = getMatrixDimensions(product)
  let total = 0
  for (const sku of skus) {
    let match = false
    if (dim.mode === 'flat') {
      match = formatSpecSummary(sku) === rowVal
    } else if (dim.mode === '1d') {
      match = String(parseSpecs(sku.specs)[dim.rowKey] ?? '—') === String(rowVal)
    } else {
      match = String(parseSpecs(sku.specs)[dim.rowKey] ?? '—') === String(rowVal)
    }
    if (match) total += Number(getQty(product.id, sku.id) || 0)
  }
  return total
}
// 列小计 = 该列所有 SKU 数量之和
function colSubtotal(product, colVal) {
  const skus = filterSkus(product.skus || [])
  const dim = getMatrixDimensions(product)
  if (dim.mode !== '2d') return ''  // 1d/flat 模式无列
  let total = 0
  for (const sku of skus) {
    if (String(parseSpecs(sku.specs)[dim.colKey] ?? '—') === String(colVal)) {
      total += Number(getQty(product.id, sku.id) || 0)
    }
  }
  return total
}
// 该商品已选规格数（任意 quantity > 0）
function productSelectedCount(productId) {
  const product = products.value.find(p => p.id === productId)
  if (!product) return 0
  const skus = filterSkus(product.skus || [])
  if (!skus.length) {
    return Number(getQty(productId, null)) > 0 ? 1 : 0
  }
  return skus.filter(s => Number(getQty(productId, s.id)) > 0).length
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
      <div :class="['grid grid-cols-1 gap-4', showSupplier ? 'md:grid-cols-4' : 'md:grid-cols-3']">
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
        <!-- 供货商：仅 admin/manager 显示（shopkeeper 不需要，公司直发） -->
        <div v-if="showSupplier">
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
        <!-- 矩阵式下单：表头=规格维度1（颜色/第一维），首列=规格维度2（尺寸/第二维），单元格=数量 -->
        <!-- 无 SKU 商品：单行单 input。1 维 SKU：行=该维值，列=1 个 input 列 -->
        <table class="w-full text-sm border-collapse">
          <tbody class="divide-y divide-gray-100">
            <template v-for="product in filteredProducts" :key="product.id">
              <!-- ================== 无 SKU 商品：单行 ================== -->
              <tr v-if="!product.skus || filterSkus(product.skus).length === 0" class="hover:bg-gray-50">
                <td class="px-3 py-2 w-16">
                  <img v-if="product.image_main" :src="product.image_main" class="w-12 h-12 object-cover rounded border border-gray-100" />
                  <div v-else class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center">
                    <span class="material-symbols-outlined text-gray-300 text-sm">image</span>
                  </div>
                </td>
                <td class="px-3 py-2">
                  <div class="font-mono text-xs text-text-secondary">{{ product.sku }}</div>
                  <div class="font-medium text-text-primary">{{ product.name }}</div>
                </td>
                <td class="px-3 py-2 w-32">
                  <input type="number" min="0" :value="getQty(product.id, null)"
                         @input="setQty(product.id, null, $event.target.value)"
                         class="w-full px-2 py-1 border border-gray-200 rounded text-center text-sm focus:border-primary focus:outline-none"
                         placeholder="数量" />
                </td>
              </tr>

              <!-- ================== 有 SKU 商品：颜色×尺寸 矩阵 ================== -->
              <template v-else>
                <!-- 取该商品所有 SKU 的 specs key（自动识别 2 维：颜色、尺寸等） -->
                <!-- 第一行：商品信息（跨列） + 矩阵表头 -->
                <tr class="bg-gray-50">
                  <td colspan="100" class="px-3 py-2">
                    <div class="flex items-center gap-3">
                      <img v-if="product.image_main" :src="product.image_main" class="w-10 h-10 object-cover rounded border border-gray-100" />
                      <div v-else class="w-10 h-10 bg-gray-100 rounded flex items-center justify-center">
                        <span class="material-symbols-outlined text-gray-300 text-sm">image</span>
                      </div>
                      <div class="flex-1">
                        <div class="font-medium text-text-primary">{{ product.name }}</div>
                        <div class="text-xs text-text-secondary font-mono">{{ product.sku }}</div>
                      </div>
                      <div class="text-xs text-text-secondary">
                        已选 <strong class="text-primary">{{ productSelectedCount(product.id) }}</strong> / 共 {{ filterSkus(product.skus).length }} 个规格
                      </div>
                    </div>
                  </td>
                </tr>
                <!-- 矩阵表头：第一列 = 维度2（行标题），其他列 = 维度1（颜色） -->
                <tr class="bg-gray-50/70 border-b-2 border-gray-200">
                  <th class="px-2 py-2 text-xs font-medium text-text-secondary text-left w-24">尺寸 / 颜色</th>
                  <th v-for="colVal in getMatrixColumns(product).colValues" :key="colVal"
                      class="px-2 py-2 text-xs font-medium text-text-secondary text-center w-20">
                    {{ colVal }}
                  </th>
                  <th class="px-2 py-2 text-xs font-medium text-text-secondary text-center w-16">小计</th>
                </tr>
                <!-- 矩阵行：行标题 = 维度2（尺寸），单元格 = 数量输入框 -->
                <template v-for="rowVal in getMatrixRows(product).rowValues" :key="rowVal">
                  <tr class="hover:bg-gray-50/50">
                    <td class="px-2 py-1.5 text-xs font-medium text-text-primary bg-gray-50/30 text-center border-r border-gray-100">
                      {{ rowVal }}
                    </td>
                    <td v-for="colVal in getMatrixColumns(product).colValues" :key="colVal"
                        class="px-1 py-1 text-center">
                      <input v-if="getSkuByCell(product, rowVal, colVal)"
                             type="number" min="0"
                             :value="getQty(product.id, getSkuByCell(product, rowVal, colVal).id)"
                             @input="setQty(product.id, getSkuByCell(product, rowVal, colVal).id, $event.target.value)"
                             class="w-full px-1 py-1 border border-gray-200 rounded text-center text-sm focus:border-primary focus:outline-none"
                             placeholder="—" />
                      <span v-else class="text-gray-300 text-xs">·</span>
                    </td>
                    <td class="px-2 py-1.5 text-center text-primary font-medium text-sm">
                      {{ rowSubtotal(product, rowVal) }}
                    </td>
                  </tr>
                </template>
                <!-- 矩阵底部：列小计行 -->
                <tr class="bg-primary/5 font-medium">
                  <td class="px-2 py-1.5 text-xs text-text-primary text-center border-r border-gray-100">列小计</td>
                  <td v-for="colVal in getMatrixColumns(product).colValues" :key="colVal"
                      class="px-2 py-1.5 text-center text-primary text-xs">
                    {{ colSubtotal(product, colVal) }}
                  </td>
                  <td class="px-2 py-1.5 text-center text-primary text-sm font-bold">
                    {{ productSelectedCount(product.id) }}
                  </td>
                </tr>
                <!-- 商品之间分隔 -->
                <tr><td colspan="100" class="h-2"></td></tr>
              </template>
            </template>
          </tbody>
          <tfoot v-if="orderItems.length > 0" class="bg-primary/10 font-bold">
            <tr>
              <td colspan="100" class="px-3 py-3 text-right text-text-primary">
                {{ t('order.total') }}：<span class="text-primary text-lg mx-2">{{ orderItems.length }} 项</span>
                <span class="text-text-secondary text-sm">|</span>
                <span class="text-primary text-lg ml-2">T.QTY {{ totalQty }}</span>
              </td>
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