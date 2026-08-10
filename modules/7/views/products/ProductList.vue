<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import Pagination from '../../components/Pagination.vue'
import api from '../../services/api.js'
import { useUserStore } from '../../stores/user.js'

const { t, locale } = useI18n()
const userStore = useUserStore()
// 权限 key 必须用冒号（与 rbac_permissions.name 一致）
const canWrite = computed(() => userStore.canAccess('products:write'))
const canDelete = computed(() => userStore.canAccess('products:delete'))

const searchQuery = ref('')
const selectedCategoryId = ref('')
const products = ref([])
const categories = ref([])
const suppliers = ref([])

const isEnglish = computed(() => locale.value === 'en')

// Category translation map (zh → en), aligned with Singapore DB
const categoryMap = {
  "电子产品": "Electronic products",
  "日用品": "Daily necessities",
  "办公用品": "Office supplies",
  "旅行箱包": "Travel luggage",
  "品牌酒类": "Brand liquor",
  "茶叶咖啡": "Tea and coffee",
  "软件AI": "Software AI",
  "数码配件": "Digital accessories",
  "手机配件": "Mobile phone accessories",
  "家居日用": "Home daily necessities",
  "高端电子": "High-end electronics",
  "品牌周边": "Brand merchandise",
  "白酒": "Baijiu",
  "红酒": "Red wine",
  "啤酒": "Beer",
  "红茶": "Black tea",
  "绿茶": "Green tea",
  "白茶": "White tea",
  "黑茶": "Dark tea",
  "乌龙茶": "Oolong tea",
  "普洱茶": "Pu'er tea",
  "智能助手": "Smart assistant",
  "微信小程序": "WeChat Mini Program",
  "微信公众号": "WeChat Official Account",
  "商业管理系统": "Business management system",
  "鼠标/键盘": "Mouse/Keyboard",
  "扩展坞/HUB": "Docking station/HUB",
  "耳机/音频": "Headphones/Audio",
  "手机壳": "Phone case",
  "贴膜": "Screen protector",
  "数据线/充电": "Data cable/charging",
  "收纳整理": "Storage and organization",
  "浓香型": "Strong aroma type",
  "酱香型": "Sauce-aroma type",
  "清香型": "Light fragrance type",
  "凤香型": "Fengxiang type",
  "大红袍": "Da Hong Pao",
  "铁观音": "Tieguanyin",
  "单枞茶": "Dancong tea",
}

// Products with category translated when in English mode
const translatedProducts = computed(() => {
  if (!isEnglish.value) return products.value
  return products.value.map(p => ({
    ...p,
    category: categoryMap[p.category] || p.category
  }))
})

// ─── Batch Delete ───────────────────────────────────────────────────────────────
const selectedProducts = ref([])
const selectAll = ref(false)

function toggleSelectAll() {
  if (selectAll.value) {
    selectedProducts.value = products.value.map(p => p.id)
  } else {
    selectedProducts.value = []
  }
}

function toggleSelect(id) {
  const idx = selectedProducts.value.indexOf(id)
  if (idx > -1) {
    selectedProducts.value.splice(idx, 1)
  } else {
    selectedProducts.value.push(id)
  }
  selectAll.value = selectedProducts.value.length === products.value.length
}

async function batchDelete() {
  if (selectedProducts.value.length === 0) {
    alert(t('product.selectDeleteFirst'))
    return
  }
  if (!confirm(t('product.batchDeleteConfirm', { count: selectedProducts.value.length }))) return

  try {
    const res = await api.post('/products/batch-delete', { ids: selectedProducts.value })
    if (res.code === 0) {
      alert(t('product.batchDeleteSuccess'))
      selectedProducts.value = []
      selectAll.value = false
      fetchProducts()
    } else {
      alert(res.message || t('product.batchDeleteFailed'))
    }
  } catch (e) {
    alert(e.message || t('product.batchDeleteFailed'))
  }
}

// ─── Pagination ─────────────────────────────────────────────────────────────────
const currentPage = ref(1)
const pageSize = 50
const total = ref(0)

// ─── Category helpers ───────────────────────────────────────────────────────────
function buildCategoryTree(flat) {
  const map = {}
  const roots = []
  for (const c of flat) map[c.id] = { ...c, children: [] }
  for (const c of flat) {
    if (c.parent_id && map[c.parent_id]) map[c.parent_id].children.push(map[c.id])
    else roots.push(map[c.id])
  }
  return roots
}

function getCategoryPath(catId, flat) {
  const path = []
  let cur = flat.find(c => c.id === catId)
  while (cur) {
    path.unshift(cur.name)
    cur = cur.parent_id ? flat.find(c => c.id === cur.parent_id) : null
  }
  return path.join(' / ')
}

// DFS-flattened list with depth for toolbar select
const categoriesOrdered = computed(() => {
  function dfs(nodes, depth = 0) {
    const out = []
    for (const n of nodes) {
      out.push({ ...n, _depth: depth })
      if (n.children?.length) out.push(...dfs(n.children, depth + 1))
    }
    return out
  }
  return dfs(buildCategoryTree(categories.value))
})

// ─── Fetch ─────────────────────────────────────────────────────────────────────
async function fetchProducts() {
  const params = { page: currentPage.value, size: pageSize }
  if (searchQuery.value) params.keyword = searchQuery.value
  if (selectedCategoryId.value) params.category_id = selectedCategoryId.value
  const res = await api.get('/products', { params })
  if (res.code === 0) {
    products.value = res.data.list || res.data
    total.value = res.data.total ?? products.value.length
  }
}

watch([searchQuery, selectedCategoryId], () => { currentPage.value = 1; fetchProducts() })
watch(currentPage, fetchProducts)

async function fetchCategories() {
  const res = await api.get('/categories')
  if (res.code === 0) categories.value = res.data
}

async function fetchSuppliers() {
  const res = await api.get('/suppliers')
  if (res.code === 0) suppliers.value = res.data.list || res.data
}

onMounted(async () => {
  await Promise.all([fetchProducts(), fetchCategories(), fetchSuppliers()])
})

const filteredProducts = computed(() => translatedProducts.value)
// ─── Supplier options ──────────────────────────────────────────────────────────
const supplierOptions = computed(() => [
  { value: '', label: t('product.noSupplier') },
  { value: t('product.selfProduced'), label: t('product.selfProduced') },
  ...suppliers.value.map(s => ({ value: s.name, label: s.name }))
])

// ─── Unified Drawer state ──────────────────────────────────────────────────────
const showDrawer = ref(false)
const editingId = ref(null)
const activeDrawerTab = ref('basic')
const saveError = ref('')

const isEdit = computed(() => editingId.value !== null)
const drawerTitle = computed(() => isEdit.value ? t('product.editProductTitle') : t('product.addProductTitle'))

const emptyForm = () => ({
  name: '',
  sku: '',
  category: '',
  category_id: null,
  spec: '',
  unit: '个',
  supplier: '',
  purchase_price: '',
  sale_price: '',
  status: 'active',
  image_main: '',
  images: [],
  external_links: [],
  group_qr_url: '',
  group_qr_type: ''
})

const form = ref(emptyForm())

// ─── Cascader state ─────────────────────────────────────────────────────────────
const cascaderPanels = ref([])
const cascaderSelected = ref([])

function initCascader() {
  const tree = buildCategoryTree(categories.value)
  cascaderPanels.value = tree.length ? [tree] : []
  cascaderSelected.value = []
}

function initCascaderWithSelection(catId) {
  const tree = buildCategoryTree(categories.value)
  if (!catId) {
    cascaderPanels.value = tree.length ? [tree] : []
    cascaderSelected.value = []
    return
  }
  const pathIds = []
  let cur = categories.value.find(c => c.id === catId)
  while (cur) {
    pathIds.unshift(cur.id)
    cur = cur.parent_id ? categories.value.find(c => c.id === cur.parent_id) : null
  }
  const panels = [tree]
  const selected = []
  for (const pid of pathIds) {
    selected.push(pid)
    const lastPanel = panels[panels.length - 1]
    const node = lastPanel.find(n => n.id === pid)
    if (node?.children?.length) panels.push(node.children)
  }
  cascaderPanels.value = panels
  cascaderSelected.value = selected
}

function onCascaderSelect(panelIdx, node) {
  cascaderSelected.value = [...cascaderSelected.value.slice(0, panelIdx), node.id]
  cascaderPanels.value = [...cascaderPanels.value.slice(0, panelIdx + 1)]
  if (node.children?.length) cascaderPanels.value.push(node.children)
  form.value.category_id = node.id
  form.value.category = getCategoryPath(node.id, categories.value)
}

function clearCascader() {
  cascaderSelected.value = []
  const tree = buildCategoryTree(categories.value)
  cascaderPanels.value = tree.length ? [tree] : []
  form.value.category_id = null
  form.value.category = ''
}

// ─── Multi-spec state ──────────────────────────────────────────────────────────
const multiSpec = ref(false)
const specGroups = ref([])
const newSpecValues = ref([])

function addSpecGroup() {
  specGroups.value.push({ name: '', values: [] })
  newSpecValues.value.push('')
}

function removeSpecGroup(idx) {
  specGroups.value.splice(idx, 1)
  newSpecValues.value.splice(idx, 1)
}

function addSpecValue(groupIdx) {
  const val = (newSpecValues.value[groupIdx] || '').trim()
  if (!val) return
  if (!specGroups.value[groupIdx].values.includes(val)) {
    specGroups.value[groupIdx].values.push(val)
  }
  newSpecValues.value[groupIdx] = ''
}

function removeSpecValue(groupIdx, valIdx) {
  specGroups.value[groupIdx].values.splice(valIdx, 1)
}

// ─── SKU Matrix (computed + editable override) ─────────────────────────────────
const skuMatrixOverrides = ref({})

function cartesian(arrays) {
  if (!arrays.length) return [[]]
  const [first, ...rest] = arrays
  const restProduct = cartesian(rest)
  return first.flatMap(val => restProduct.map(combo => [val, ...combo]))
}

const skuMatrix = computed(() => {
  if (!multiSpec.value) return []
  const validGroups = specGroups.value.filter(g => g.values.length > 0)
  if (!validGroups.length) return []
  const combos = cartesian(validGroups.map(g => g.values))
  return combos.map((combo, idx) => {
    const specsObj = {}
    validGroups.forEach((g, i) => { specsObj[g.name || t('product.attributeLabel', { n: i + 1 })] = combo[i] })
    const key = combo.join('-')
    const override = skuMatrixOverrides.value[key] || {}
    return {
      key,
      specs: specsObj,
      specsLabel: combo.join(' / '),
      sku: override.sku !== undefined ? override.sku : '',
      purchase_price: override.purchase_price !== undefined ? override.purchase_price : '',
      sale_price: override.sale_price !== undefined ? override.sale_price : '',
      image: override.image !== undefined ? override.image : ''
    }
  })
})

function updateMatrixRow(key, field, value) {
  if (!skuMatrixOverrides.value[key]) skuMatrixOverrides.value[key] = {}
  skuMatrixOverrides.value[key][field] = value
}

// SKU图片上传
const skuImageUploading = ref({})

async function onSkuImageChange(e, rowKey) {
  const file = e.target.files[0]
  if (!file) return
  skuImageUploading.value[rowKey] = true
  try {
    const url = await uploadImage(file)
    if (url) updateMatrixRow(rowKey, 'image', url)
  } finally {
    skuImageUploading.value[rowKey] = false
    e.target.value = ''
  }
}

function removeSkuImage(rowKey) {
  updateMatrixRow(rowKey, 'image', '')
}

// ─── Image upload ──────────────────────────────────────────────────────────────
const imageMainUploading = ref(false)
const galleryUploading = ref(false)

async function uploadImage(file) {
  const fd = new FormData()
  fd.append('file', file)
  const res = await fetch('/api/upload/product-image', {
    method: 'POST',
    headers: { Authorization: `Bearer ${localStorage.getItem('caimeite_token')}` },
    body: fd
  })
  const json = await res.json()
  return json.data?.url
}

async function uploadGroupQr(e) {
  const file = e.target.files[0]
  if (!file) return
  const url = await uploadImage(file)
  if (url) form.value.group_qr_url = url
}

async function onMainImageChange(e) {
  const file = e.target.files[0]
  if (!file) return
  imageMainUploading.value = true
  try {
    const url = await uploadImage(file)
    if (url) form.value.image_main = url
  } finally {
    imageMainUploading.value = false
    e.target.value = ''
  }
}

async function onGalleryChange(e) {
  const files = Array.from(e.target.files)
  if (!files.length) return
  galleryUploading.value = true
  try {
    for (const file of files) {
      const url = await uploadImage(file)
      if (url) form.value.images.push(url)
    }
  } finally {
    galleryUploading.value = false
    e.target.value = ''
  }
}

function removeGalleryImage(idx) {
  form.value.images.splice(idx, 1)
}

// ─── External links ────────────────────────────────────────────────────────────
const platformOptions = computed(() => [
  { value: 'jd', label: t('product.platformJd') },
  { value: 'taobao', label: t('product.platformTaobao') },
  { value: 'tmall', label: t('product.platformTmall') },
  { value: 'pinduoduo', label: t('product.platformPinduoduo') },
  { value: 'other', label: t('product.platformOther') }
])

function addExternalLink() {
  form.value.external_links.push({ platform: 'jd', url: '' })
}

function removeExternalLink(idx) {
  form.value.external_links.splice(idx, 1)
}

// ─── Open / close drawer ───────────────────────────────────────────────────────
function openAdd() {
  editingId.value = null
  form.value = emptyForm()
  multiSpec.value = false
  specGroups.value = []
  newSpecValues.value = []
  skuMatrixOverrides.value = {}
  saveError.value = ''
  activeDrawerTab.value = 'basic'
  initCascader()
  showDrawer.value = true
}

async function openEdit(p) {
  editingId.value = p.id
  form.value = {
    name: p.name || '',
    sku: p.sku || '',
    category: p.category || '',
    category_id: p.category_id || null,
    spec: p.spec || '',
    unit: p.unit || '个',
    supplier: p.supplier || '',
    purchase_price: p.purchase_price || '',
    sale_price: p.sale_price || '',
    status: p.status || 'active',
    image_main: p.image_main || '',
    images: p.images ? (Array.isArray(p.images) ? [...p.images] : JSON.parse(p.images || '[]')) : [],
    external_links: p.external_links ? (Array.isArray(p.external_links) ? [...p.external_links] : JSON.parse(p.external_links || '[]')) : []
  }
  multiSpec.value = false
  specGroups.value = []
  newSpecValues.value = []
  skuMatrixOverrides.value = {}
  saveError.value = ''
  activeDrawerTab.value = 'basic'
  initCascaderWithSelection(p.category_id || null)

  // Try to load specs
  try {
    const res = await api.get(`/products/${p.id}/specs`)
    if (res.code === 0 && res.data) {
      const { specs, skus } = res.data
      if (specs && specs.length) {
        multiSpec.value = true
        specGroups.value = specs.map(g => ({ name: g.name, values: g.values ? g.values.map(v => v.value || v) : [] }))
        newSpecValues.value = specs.map(() => '')
        if (skus && skus.length) {
          skus.forEach(row => {
            const key = row.key || Object.values(row.specs || {}).join('-')
            skuMatrixOverrides.value[key] = {
              sku: row.sku || '',
              purchase_price: row.purchase_price || '',
              sale_price: row.sale_price || '',
              image: row.image || ''
            }
          })
        }
      }
    }
  } catch (e) {
    // specs endpoint may not exist, ignore
  }

  showDrawer.value = true
}

function closeDrawer() {
  showDrawer.value = false
  editingId.value = null
}

// ─── 网站图库选择器 ─────────────────────────────────────────────────────────────
const showLibrary = ref(false)
const libraryTarget = ref('main') // 'main' | 'gallery'
const libraryImages = ref([])
const libraryPage = ref(1)
const libraryLoading = ref(false)
const libraryTotal = ref(0)

async function loadLibraryImages() {
  libraryLoading.value = true
  try {
    const res = await api.get('/upload/images', {
      params: { page: libraryPage.value, limit: 30 }
    })
    if (res.code === 0) {
      libraryImages.value = res.data.list || []
      libraryTotal.value = res.data.total || 0
    }
  } catch (e) {
    console.error('加载图库失败', e)
  } finally {
    libraryLoading.value = false
  }
}

function showLibraryPicker(target) {
  libraryTarget.value = target
  libraryPage.value = 1
  libraryImages.value = []
  showLibrary.value = true
  loadLibraryImages()
}

function selectLibraryImage(img) {
  if (libraryTarget.value === 'main') {
    form.value.image_main = img.url
  } else if (libraryTarget.value === 'gallery') {
    form.value.images.push(img.url)
  }
  showLibrary.value = false
}

// ─── Save ──────────────────────────────────────────────────────────────────────
async function handleSave() {
  saveError.value = ''
  if (!form.value.name.trim()) {
    saveError.value = t('product.nameRequired')
    activeDrawerTab.value = 'basic'
    return
  }

  const payload = { ...form.value }

  try {
    let productId = editingId.value

    if (isEdit.value) {
      const res = await api.put(`/products/${productId}`, payload)
      if (res.code !== 0) {
        saveError.value = res.message || t('product.saveFailed')
        return
      }
    } else {
      const res = await api.post('/products', payload)
      if (res.code !== 0) {
        saveError.value = res.message || t('product.saveFailed')
        return
      }
      productId = res.data?.id || res.data
    }

    // Save specs if multiSpec is enabled
    if (multiSpec.value && productId) {
      const specsPayload = {
        specs: specGroups.value,
        skus: skuMatrix.value.map(row => ({
          key: row.key,
          specs: row.specs,
          sku: row.sku,
          purchase_price: row.purchase_price,
          sale_price: row.sale_price,
          image: row.image
        }))
      }
      const specsRes = await api.post(`/products/${productId}/specs`, specsPayload)
      if (specsRes.code !== 0) {
        saveError.value = specsRes.message || t('product.specSaveFailed')
        return
      }
    }

    closeDrawer()
    await fetchProducts()
  } catch (e) {
    saveError.value = e.message || t('product.requestFailed')
  }
}

// ─── Spec Modal ─────────────────────────────────────────────────────────────────
const showSpecModal = ref(false)
const specModalProduct = ref(null)
const specModalSkus = ref([])

async function openSpecModal(p) {
  specModalProduct.value = p
  specModalSkus.value = []
  showSpecModal.value = true
  // Single spec - no need to load
  if (!p.sku_count || p.sku_count <= 1) {
    return
  }
  // Load specs from API
  try {
    const res = await api.get(`/products/${p.id}/specs`)
    if (res.code === 0 && res.data?.skus) {
      specModalSkus.value = res.data.skus
    }
  } catch (e) {
    // ignore
  }
}

// ─── Delete ────────────────────────────────────────────────────────────────────
async function handleDelete(id) {
  if (!confirm(t('product.confirmDelete'))) return
  try {
    await api.delete(`/products/${id}`)
    await fetchProducts()
  } catch (err) {
    const errorMsg = err.response?.data?.message || err.message || t('product.deleteFailed')
    alert(errorMsg)
  }
}

// ─── 上架 / 下架 ───────────────────────────────────────────────────────────
async function handlePublish(product) {
  try {
    const res = await api.put(`/products/${product.id}/publish`)
    alert(res.message || t('product.publishSuccess'))
    await fetchProducts()
  } catch (err) {
    const msg = err.response?.data?.message || err.message || t('product.publishFailed')
    alert(msg)
  }
}

async function handleUnpublish(product) {
  try {
    const res = await api.put(`/products/${product.id}/unpublish`)
    alert(res.message || t('product.unpublishSuccess'))
    await fetchProducts()
  } catch (err) {
    const msg = err.response?.data?.message || err.message || t('product.publishFailed')
    alert(msg)
  }
}

// ─── Material Calculator ─────────────────────────────────────────────────────────
const showCalcModal = ref(false)
const calcProduct = ref(null)
const calcQuantity = ref(1)
const calcResult = ref([])           // [{material_name, unit, quantity, total}]
const calcPerSku = ref(false)       // 按SKU分别显示
const productSkusMap = ref({})      // {productId: [{id, sku, specs, sku_key}]}

async function openMaterialCalculator() {
  calcProduct.value = null
  calcQuantity.value = 1
  calcResult.value = []
  calcPerSku.value = false
  showCalcModal.value = true
}

async function fetchProductSkus(productId) {
  if (productSkusMap.value[productId]) return
  try {
    const res = await api.get(`/materials/product/${productId}/skus`)
    if (res.code === 0 || res.success) {
      productSkusMap.value = { ...productSkusMap.value, [productId]: res.data || [] }
    }
  } catch (e) { /* ignore */ }
}

async function doCalculate() {
  if (!calcProduct.value) { calcResult.value = []; return }
  try {
    const pid = calcProduct.value.id
    // 如果有SKU，先获取SKU列表
    await fetchProductSkus(pid)
    const skus = productSkusMap.value[pid] || []
    const hasSkus = skus.length > 0

    if (!hasSkus) {
      // 无SKU商品：直接查共用配方
      const res = await api.get(`/materials/product/${pid}`)
      if ((res.code === 0 || res.success) && res.data?.length) {
        calcResult.value = res.data.map(m => ({
          sku_id: null,
          sku_label: t('product.allSkusShared'),
          material_name: m.material_name,
          unit: m.unit,
          quantity: m.quantity,
          total: (m.quantity || 0) * calcQuantity.value
        }))
      } else {
        calcResult.value = []
      }
      return
    }

    // 多SKU商品
    if (calcPerSku.value) {
      // 按SKU分别显示：每个SKU单独请求
      const results = []
      for (const sku of skus) {
        const res = await api.get(`/materials/product/${pid}?sku_id=${sku.id}`)
        const mats = (res.code === 0 || res.success) ? res.data || [] : []
        const skuQty = (calcQuantity.value / skus.length) // 平均分配
        for (const m of mats) {
          results.push({
            sku_id: sku.id,
            sku_label: sku.sku_key || sku.sku || `#${sku.id}`,
            material_name: m.material_name,
            unit: m.unit,
            quantity: m.quantity,
            total: (m.quantity || 0) * skuQty
          })
        }
        if (mats.length === 0) {
          results.push({
            sku_id: sku.id,
            sku_label: sku.sku_key || sku.sku || `#${sku.id}`,
            material_name: t('product.noMaterialsForProduct'),
            unit: '-', quantity: '-', total: '-'
          })
        }
      }
      calcResult.value = results
    } else {
      // 汇总：优先查共用配方，如果没有再合并各SKU
      const res = await api.get(`/materials/product/${pid}`)
      const mats = (res.code === 0 || res.success) ? res.data || [] : []
      if (mats.length) {
        calcResult.value = mats.map(m => ({
          sku_id: m.sku_id,
          sku_label: m.sku_id ? (m.sku_key || m.sku_code || `#${m.sku_id}`) : t('product.allSkusShared'),
          material_name: m.material_name,
          unit: m.unit,
          quantity: m.quantity,
          total: (m.quantity || 0) * calcQuantity.value
        }))
      } else {
        calcResult.value = []
      }
    }
  } catch (e) {
    calcResult.value = []
  }
}

function groupBySku(results) {
  const groups = {}
  for (const r of results) {
    const label = r.sku_label || '其他'
    if (!groups[label]) groups[label] = []
    groups[label].push(r)
  }
  return groups
}

watch([calcProduct, calcQuantity, calcPerSku], () => {
  if (calcProduct.value && calcQuantity.value > 0) {
    doCalculate()
  } else {
    calcResult.value = []
  }
})

// ─── Material management ───────────────────────────────────────────────────────
const showMaterialModal = ref(false)
const materialMode = ref('manage')   // 'manage' | 'edit'
const materialProduct = ref(null)
const editingMat = ref(null)        // 当前编辑的材料类目（manage模式）
const newMatName = ref('')
const newMatUnit = ref('')
const newMatRemark = ref('')
const newMatSupplierId = ref('')
const materialCategories = ref([])
const matSuppliers = ref([])
const productMaterials = ref([])
const matLoading = ref(false)

async function fetchMaterials() {
  try {
    const res = await api.get('/materials/categories')
    if (res.code === 0 || res.success) {
      materialCategories.value = res.data || []
    }
  } catch (e) {
    console.error('fetchMaterials error', e)
  }
}

async function fetchMatSuppliers() {
  try {
    const res = await api.get('/suppliers', { params: { type: 'raw_material' } })
    if (res.code === 0) {
      matSuppliers.value = res.data || []
    } else {
      matSuppliers.value = []
    }
  } catch (e) {
    matSuppliers.value = []
  }
}

// 打开材料管理（基础数据管理，不关联商品）
async function openMaterialManage() {
  materialMode.value = 'manage'
  materialProduct.value = null
  editingMat.value = null
  newMatName.value = ''
  newMatUnit.value = ''
  newMatRemark.value = ''
  newMatSupplierId.value = ''
  await fetchMaterials()
  await fetchMatSuppliers()
  showMaterialModal.value = true
}
// 打开商品配方编辑
async function openMaterialEdit(p) {
  materialMode.value = 'edit'
  materialProduct.value = p
  productMaterials.value = []
  activeSkuTab.value = 'shared'
  skuMaterialsMap.value = {}
  await fetchMaterials()
  await fetchEditProductSkus(p.id)
  showMaterialModal.value = true
  await fetchProductMaterials(p.id)
}

const activeSkuTab = ref('shared')   // 'shared' | 'per-sku'
const skuMaterialsMap = ref({})      // {sku_id: [{material_id, material_name, unit, quantity}]}

async function fetchEditProductSkus(productId) {
  try {
    const res = await api.get(`/materials/product/${productId}/skus`)
    if (res.code === 0 || res.success) {
      skuMaterialsMap.value = { ...skuMaterialsMap.value, [productId]: res.data || [] }
    }
  } catch (e) { /* ignore */ }
}

async function fetchProductMaterials(productId) {
  try {
    const res = await api.get(`/materials/product/${productId}`)
    if (res.code === 0 || res.success) {
      productMaterials.value = res.data || []
    } else {
      productMaterials.value = []
    }
  } catch (e) {
    productMaterials.value = []
  }
}

async function addMaterialCategory() {
  if (!newMatName.value.trim()) return
  try {
    const res = await api.post('/materials/categories', {
      name: newMatName.value.trim(),
      unit: newMatUnit.value,
      remark: newMatRemark.value
    })
    if (res.code === 0 || res.success) {
      newMatName.value = ''
      newMatUnit.value = ''
      newMatRemark.value = ''
      await fetchMaterials()
    }
  } catch (e) {
    alert(e.message || '添加失败')
  }
}

async function deleteMaterialCategory(id) {
  if (!confirm('确定删除该材料类目？')) return
  try {
    const res = await api.delete(`/materials/categories/${id}`)
    if (res.code === 0 || res.success) {
      await fetchMaterials()
    }
  } catch (e) {
    alert(e.message || '删除失败')
  }
}

function startEditMat(cat) {
  editingMat.value = cat
  newMatName.value = cat.name
  newMatUnit.value = cat.unit || ''
  newMatRemark.value = cat.remark || ''
  newMatSupplierId.value = cat.supplier_id || ''
}

function cancelEditMat() {
  editingMat.value = null
  newMatName.value = ''
  newMatUnit.value = ''
  newMatRemark.value = ''
  newMatSupplierId.value = ''
}

async function saveMatCategory() {
  if (!newMatName.value.trim()) {
    alert('请输入材料名称')
    return
  }
  matLoading.value = true
  try {
    const payload = {
      name: newMatName.value.trim(),
      unit: newMatUnit.value || '',
      remark: newMatRemark.value || '',
      supplier_id: newMatSupplierId.value || null
    }
    if (editingMat.value) {
      await api.put(`/materials/categories/${editingMat.value.id}`, payload)
    } else {
      await api.post('/materials/categories', payload)
    }
    cancelEditMat()
    await fetchMaterials()
  } catch (e) {
    alert(e.message || '保存失败')
  } finally {
    matLoading.value = false
  }
}

async function saveProductMaterials() {
  if (!materialProduct.value) return
  matLoading.value = true
  try {
    const pid = materialProduct.value.id
    const skus = skuMaterialsMap.value[pid] || []
    
    // 共用配方（sku_id=null）
    const sharedMaterials = productMaterials.value
      .filter(pm => !pm.sku_id)
      .map(pm => ({
        material_id: pm.material_id,
        quantity: pm.quantity
      }))
    
    // 按SKU配方
    const skuMaterials = []
    for (const [skuId, mats] of Object.entries(skuMaterialsMap.value)) {
      if (skuId === String(pid)) continue  // SKU列表不是配方
      const validMats = (mats || [])
        .filter(pm => pm.material_id && pm.quantity > 0)
        .map(pm => ({
          material_id: pm.material_id,
          quantity: pm.quantity
        }))
      if (validMats.length > 0) {
        skuMaterials.push({ sku_id: Number(skuId), materials: validMats })
      }
    }
    
    await api.post(`/materials/product/${pid}/batch`, {
      shared_materials: sharedMaterials,
      sku_materials: skuMaterials
    })
    showMaterialModal.value = false
  } catch (e) {
    alert(e.message || '保存失败')
  } finally {
    matLoading.value = false
  }
}

// 旧函数保留兼容（只加到共用配方）
function addMaterialToProduct(cat) { addMaterialToShared(cat) }

function addMaterialToShared(cat) {
  const exists = productMaterials.value.find(pm => pm.material_id === cat.id && !pm.sku_id)
  if (exists) return
  productMaterials.value.push({
    material_id: cat.id,
    material_name: cat.name,
    unit: cat.unit || '',
    quantity: 1,
    sku_id: null
  })
}

function addMaterialToSku(skuId, cat) {
  const key = `${materialProduct.value.id}_${skuId}`
  if (!skuMaterialsMap.value[key]) skuMaterialsMap.value[key] = []
  const exists = skuMaterialsMap.value[key].find(pm => pm.material_id === cat.id)
  if (exists) return
  skuMaterialsMap.value[key].push({
    material_id: cat.id,
    material_name: cat.name,
    unit: cat.unit || '',
    quantity: 1,
    sku_id: skuId
  })
  // 触发响应式更新
  skuMaterialsMap.value = { ...skuMaterialsMap.value }
}

function removeMaterialFromProduct(idx, mode) {
  if (mode === 'shared') {
    // 从 productMaterials 里删（sku_id=null）
    const toRemove = productMaterials.value.filter(pm => !pm.sku_id)
    if (toRemove[idx]) {
      const realIdx = productMaterials.value.indexOf(toRemove[idx])
      productMaterials.value.splice(realIdx, 1)
    }
  } else if (mode && mode.startsWith('per-sku-')) {
    const skuId = mode.replace('per-sku-', '')
    const key = `${materialProduct.value.id}_${skuId}`
    skuMaterialsMap.value[key]?.splice(idx, 1)
    skuMaterialsMap.value = { ...skuMaterialsMap.value }
  } else {
    productMaterials.value.splice(idx, 1)
  }
}

// ─── Category management modal ─────────────────────────────────────────────────
const showCatModal = ref(false)
const newCatName = ref('')
const editingCat = ref(null)
const editCatName = ref('')
const catError = ref('')
const catExpandedIds = ref([])
const catAddingChildOf = ref(null)
const catChildName = ref('')

function flattenTree(nodes, expanded) {
  const result = []
  for (const node of nodes) {
    result.push(node)
    if (expanded.includes(node.id) && node.children?.length)
      result.push(...flattenTree(node.children, expanded))
  }
  return result
}

const catModalTree = computed(() => buildCategoryTree(categories.value))
const catModalFlat = computed(() => flattenTree(catModalTree.value, catExpandedIds.value))

function catToggleExpand(id) {
  const idx = catExpandedIds.value.indexOf(id)
  if (idx >= 0) catExpandedIds.value.splice(idx, 1)
  else catExpandedIds.value.push(id)
}

function catStartAddChild(cat) {
  catAddingChildOf.value = cat.id
  catChildName.value = ''
  catError.value = ''
  editingCat.value = null
  if (!catExpandedIds.value.includes(cat.id)) catExpandedIds.value.push(cat.id)
}

async function catSaveChild(parentId) {
  catError.value = ''
  if (!catChildName.value.trim()) { catError.value = t('product.catNameRequired'); return }
  const res = await api.post('/categories', { name: catChildName.value.trim(), parent_id: parentId })
  if (res.code === 0) {
    catAddingChildOf.value = null
    catChildName.value = ''
    await fetchCategories()
  } else {
    catError.value = res.message || t('product.catAddFailed')
  }
}

async function addCategory() {
  catError.value = ''
  if (!newCatName.value.trim()) return
  const res = await api.post('/categories', { name: newCatName.value.trim() })
  if (res.code === 0) {
    newCatName.value = ''
    await fetchCategories()
  } else {
    catError.value = res.message
  }
}

function startEditCat(cat) {
  editingCat.value = cat.id
  editCatName.value = cat.name
  catAddingChildOf.value = null
}

async function saveEditCat(cat) {
  catError.value = ''
  if (!editCatName.value.trim()) return
  const res = await api.put(`/categories/${cat.id}`, { name: editCatName.value.trim() })
  if (res.code === 0) {
    editingCat.value = null
    await fetchCategories()
    await fetchProducts()
  } else {
    catError.value = res.message
  }
}

async function deleteCategory(cat) {
  catError.value = ''
  const hasChildren = cat.children && cat.children.length > 0
  const msg = hasChildren
    ? t('product.deleteCatWithChildren', { name: cat.name })
    : t('product.deleteCatConfirm', { name: cat.name })
  if (!confirm(msg)) return
  const res = await api.delete(`/categories/${cat.id}`)
  if (res.code === 0) {
    await fetchCategories()
  } else {
    catError.value = res.message
  }
}
</script>

<template>
  <div>
    <PageHeader :title="$t('product.title')" :subtitle="$t('product.subtitle')" />

    <!-- Toolbar -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-6">
      <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center bg-gray-50 rounded-lg px-3 py-2 border border-transparent focus-within:border-primary focus-within:bg-white transition-all flex-1 min-w-[160px] max-w-[360px]">
          <span class="material-symbols-outlined text-text-secondary text-[20px]">search</span>
          <input v-model="searchQuery" type="text" :placeholder="$t('product.searchPlaceholder')" class="bg-transparent border-none focus:ring-0 focus:outline-none text-sm w-full text-text-primary placeholder-text-secondary ml-2" />
        </div>
        <select v-model="selectedCategoryId" @change="() => {}" class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
          <option value="">{{ $t('product.allCategories') }}</option>
          <option v-for="cat in categoriesOrdered" :key="cat.id" :value="cat.id">{{ '　'.repeat(cat._depth) }}{{ isEnglish ? (categoryMap[cat.name] || cat.name) : cat.name }}</option>
        </select>
        <button v-if="canWrite" @click="showCatModal = true; fetchCategories()" class="flex items-center gap-1.5 border border-gray-200 text-text-secondary hover:text-text-primary hover:border-gray-300 px-3 py-2 rounded-lg text-sm transition-colors">
          <span class="material-symbols-outlined text-[18px]">category</span>
          {{ $t('product.manageCategories') }}
        </button>
        <button v-if="canWrite" @click="openMaterialManage()" class="flex items-center gap-1.5 border border-gray-200 text-text-secondary hover:text-text-primary hover:border-gray-300 px-3 py-2 rounded-lg text-sm transition-colors">
          <span class="material-symbols-outlined text-[18px]">inventory_2</span>
          {{ $t('product.materialManagement') }}
        </button>
        <button @click="openMaterialCalculator" class="flex items-center gap-1.5 border border-gray-200 text-text-secondary hover:text-text-primary hover:border-gray-300 px-3 py-2 rounded-lg text-sm transition-colors">
          <span class="material-symbols-outlined text-[18px]">calculate</span>
          {{ $t('product.materialCalculator') }}
        </button>
        <div class="ml-auto">
          <button v-if="canWrite" @click="openAdd" class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">add</span>
            {{ $t('product.addProduct') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <!-- Batch Actions Bar -->
      <div v-if="selectedProducts.length > 0" class="px-4 py-3 bg-blue-50 border-b border-blue-100 flex items-center justify-between">
        <span class="text-sm text-blue-700">{{ $t('product.selectedCount', { count: selectedProducts.length }) }}</span>
        <button @click="batchDelete" class="flex items-center gap-1 px-3 py-1.5 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600 transition-colors">
          <span class="material-symbols-outlined text-[16px]">delete</span>
          {{ $t('product.batchDelete') }}
        </button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium w-12">
                <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="rounded border-gray-300 text-primary focus:ring-primary" />
              </th>
              <th class="px-4 py-3 font-medium w-14">{{ $t('product.image') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('product.productName') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('product.category') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('product.supplier') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('product.specType') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('product.salePrice') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('common.status') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="p in filteredProducts" :key="p.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3">
                <input type="checkbox" :checked="selectedProducts.includes(p.id)" @change="toggleSelect(p.id)" class="rounded border-gray-300 text-primary focus:ring-primary" />
              </td>
              <td class="px-4 py-3">
                <img v-if="p.image_main" :src="p.image_main" alt="" class="w-10 h-10 object-cover rounded border border-gray-100" />
                <div v-else class="w-10 h-10 rounded border border-gray-100 bg-gray-50 flex items-center justify-center">
                  <span class="material-symbols-outlined text-gray-300 text-[20px]">image</span>
                </div>
              </td>
              <td class="px-4 py-3 font-medium text-text-primary">{{ p.name || '-' }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ p.category || '-' }}</td>
              <td class="px-4 py-3 text-text-secondary text-xs">{{ p.supplier || '-' }}</td>
              <td class="px-4 py-3 text-center">
                <span v-if="!p.sku_count || p.sku_count <= 1" class="text-text-secondary text-xs">{{ $t('product.singleSpec') }}</span>
                <button v-else @click="openSpecModal(p)" class="text-primary hover:text-primary-hover text-xs font-medium">{{ $t('product.multiSpec') }}({{ p.sku_count }})</button>
              </td>
              <td class="px-4 py-3 text-right text-text-primary">¥{{ Number(p.sale_price || 0).toFixed(2) }}</td>
              <td class="px-4 py-3 text-center">
                <template v-if="p.publish_status === 'published'">
                  <StatusTag type="success" :text="$t('product.published')" />
                </template>
                <template v-else-if="p.publish_status === 'unpublished'">
                  <StatusTag type="warning" :text="$t('product.unpublished')" />
                </template>
                <template v-else>
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">{{ $t('product.draft') }}</span>
                </template>
              </td>
              <td class="px-4 py-3 text-right">
                <button v-if="canWrite" @click="openEdit(p)" class="text-primary hover:text-primary-hover text-xs font-medium mr-3">{{ $t('common.edit') }}</button>
                <button v-if="canWrite" @click="openMaterialEdit(p)" class="text-primary hover:text-primary-hover text-xs font-medium mr-3">{{ $t('product.materialEdit') }}</button>
                <template v-if="p.publish_status === 'draft' || p.publish_status === 'unpublished'">
                  <button v-if="canWrite && (p.total_stock > 0)" @click="handlePublish(p)" class="text-success hover:text-green-600 text-xs font-medium mr-3">{{ $t('product.publish') }}</button>
                  <span v-else-if="canWrite" class="text-gray-400 text-xs mr-3 cursor-not-allowed" :title="$t('product.noStockForPublish')">{{ $t('product.publish') }}</span>
                </template>
                <button v-else-if="canWrite && p.publish_status === 'published'" @click="handleUnpublish(p)" class="text-warning hover:text-orange-600 text-xs font-medium mr-3">{{ $t('product.unpublish') }}</button>
                <button v-if="canDelete" @click="handleDelete(p.id)" class="text-danger hover:text-red-700 text-xs font-medium">{{ $t('common.delete') }}</button>
              </td>
            </tr>
            <tr v-if="filteredProducts.length === 0">
              <td colspan="8" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('product.noProductData') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100">
        <Pagination :total="total" :page="currentPage" :pageSize="pageSize" @update:page="currentPage = $event" />
      </div>
    </div>

    <!-- ── Unified Drawer (Add + Edit) ── -->
    <Teleport to="body">
      <div v-if="showDrawer" class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/30" @click="closeDrawer"></div>
        <div class="relative w-full max-w-2xl bg-white shadow-xl flex flex-col">
          <!-- Drawer header -->
          <div class="flex items-center justify-between px-6 py-4 border-b shrink-0">
            <h3 class="text-lg font-bold text-text-primary">{{ drawerTitle }}</h3>
            <button @click="closeDrawer" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Tabs -->
          <div class="flex border-b bg-gray-50 shrink-0">
            <button
              v-for="tab in [{ key: 'basic', label: $t('product.basicInfo') }, { key: 'specs', label: $t('product.specsAndSku') }, { key: 'images', label: $t('product.imagesAndLinks') }]"
              :key="tab.key"
              @click="activeDrawerTab = tab.key"
              :class="[
                'px-5 py-3 text-sm font-medium border-b-2 transition-colors',
                activeDrawerTab === tab.key
                  ? 'border-primary text-primary bg-white'
                  : 'border-transparent text-text-secondary hover:text-text-primary'
              ]"
            >{{ tab.label }}</button>
          </div>

          <!-- Tab content -->
          <div class="flex-1 overflow-y-auto">

            <!-- Tab 1: 基本信息 -->
            <div v-show="activeDrawerTab === 'basic'" class="p-6 space-y-4">
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('product.productNameLabel') }} <span class="text-danger">*</span></label>
                <input v-model="form.name" :placeholder="$t('product.enterProductName')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('product.skuCode') }}</label>
                  <input v-model="form.sku" :placeholder="$t('product.skuAutoGen')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                  <p class="text-xs text-text-secondary mt-1">{{ $t('product.skuAutoGenHint') }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('product.unitLabel') }}</label>
                  <input v-model="form.unit" :placeholder="$t('product.unitPlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('product.categoryLabel') }}</label>
                <!-- Selected path -->
                <div v-if="form.category" class="flex items-center gap-2 mb-2 bg-primary/5 border border-primary/20 rounded-lg px-3 py-2">
                  <span class="material-symbols-outlined text-primary text-[15px]">category</span>
                  <span class="text-sm text-primary flex-1">{{ isEnglish ? (categoryMap[form.category] || form.category) : form.category }}</span>
                  <button @click="clearCascader" class="text-text-secondary hover:text-danger transition-colors">
                    <span class="material-symbols-outlined text-[15px]">close</span>
                  </button>
                </div>
                <!-- Cascader panels -->
                <div v-if="cascaderPanels.length" class="flex border border-gray-200 rounded-lg overflow-hidden">
                  <div
                    v-for="(panel, pIdx) in cascaderPanels" :key="pIdx"
                    class="flex-1 min-w-[130px] max-h-44 overflow-y-auto border-r border-gray-100 last:border-r-0"
                  >
                    <div
                      v-for="node in panel" :key="node.id"
                      @click="onCascaderSelect(pIdx, node)"
                      :class="[
                        'flex items-center justify-between px-3 py-2 text-sm cursor-pointer hover:bg-gray-50 transition-colors',
                        cascaderSelected[pIdx] === node.id ? 'bg-primary/10 text-primary font-medium' : 'text-text-primary'
                      ]"
                    >
                      <span>{{ isEnglish ? (categoryMap[node.name] || node.name) : node.name }}</span>
                      <span v-if="node.children?.length" class="material-symbols-outlined text-[14px] text-text-secondary shrink-0">chevron_right</span>
                    </div>
                  </div>
                </div>
                <p v-else class="text-xs text-text-secondary py-1">{{ $t('product.noCategoryHint') }}</p>
                <p v-if="!form.category" class="text-xs text-text-secondary mt-1">{{ $t('product.selectCategoryHint') }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('product.specDescription') }}</label>
                <input v-model="form.spec" :placeholder="$t('product.specPlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
              </div>

              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('product.supplierLabel') }}</label>
                <select v-model="form.supplier" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                  <option v-for="opt in supplierOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('product.purchasePriceLabel') }}</label>
                  <input v-model="form.purchase_price" type="number" min="0" step="0.01" placeholder="0.00" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('product.salePriceLabel') }}</label>
                  <input v-model="form.sale_price" type="number" min="0" step="0.01" placeholder="0.00" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                </div>
              </div>

              <!-- Group QR URL for after-sale service group -->
              <div>
                <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('product.groupQrUrl') }}</label>
                <div class="flex gap-2">
                  <label class="w-1/2 border border-gray-200 rounded-lg px-3 py-2 text-sm text-text-secondary cursor-pointer hover:border-primary transition-colors">
                    {{ form.group_qr_url ? $t('product.groupQrUrlUploaded') : $t('product.groupQrUrlPlaceholder') }}
                    <input type="file" accept="image/*" class="hidden" @change="uploadGroupQr" />
                  </label>
                  <select v-model="form.group_qr_type" :class="['w-1/3 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none', form.group_qr_type ? 'text-text-primary' : 'text-text-secondary']">
                    <option value="" disabled hidden>{{ $t('product.groupQrTypePlaceholder') }}</option>
                    <option value="wechat_work">{{ $t('product.groupQrTypeWechatWork') }}</option>
                    <option value="dingtalk">{{ $t('product.groupQrTypeDingtalk') }}</option>
                    <option value="telegram">{{ $t('product.groupQrTypeTelegram') }}</option>
                    <option value="whatsapp">{{ $t('product.groupQrTypeWhatsapp') }}</option>
                  </select>
                </div>
                <p class="text-xs text-text-secondary mt-1">{{ $t('product.groupQrUrlHint') }}</p>
                <img v-if="form.group_qr_url" :src="form.group_qr_url" class="mt-2 w-24 h-24 rounded border border-gray-100 object-contain" />
              </div>

              <div v-if="isEdit">
                <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('product.statusLabel') }}</label>
                <select v-model="form.status" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                  <option value="active">{{ $t('product.active') }}</option>
                  <option value="discontinued">{{ $t('product.discontinued') }}</option>
                </select>
              </div>
            </div>

            <!-- Tab 2: 规格与SKU -->
            <div v-show="activeDrawerTab === 'specs'" class="p-6 space-y-5">
              <!-- Enable toggle -->
              <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-100">
                <div>
                  <p class="text-sm font-medium text-text-primary">{{ $t('product.enableMultiSpec') }}</p>
                  <p class="text-xs text-text-secondary mt-0.5">{{ $t('product.multiSpecHint') }}</p>
                </div>
                <label class="flex items-center cursor-pointer select-none">
                  <div class="relative">
                    <input type="checkbox" v-model="multiSpec" class="sr-only" />
                    <div :class="['w-11 h-6 rounded-full transition-colors', multiSpec ? 'bg-primary' : 'bg-gray-300']"></div>
                    <div :class="['absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform', multiSpec ? 'translate-x-5' : 'translate-x-0']"></div>
                  </div>
                </label>
              </div>

              <template v-if="multiSpec">
                <!-- Spec groups -->
                <div class="space-y-4">
                  <div v-for="(group, gIdx) in specGroups" :key="gIdx" class="border border-gray-200 rounded-lg p-4 space-y-3">
                    <div class="flex items-center gap-2">
                      <input v-model="group.name" :placeholder="$t('product.specGroupName')" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                      <button @click="removeSpecGroup(gIdx)" class="text-text-secondary hover:text-danger transition-colors p-1">
                        <span class="material-symbols-outlined text-[20px]">delete</span>
                      </button>
                    </div>
                    <!-- Values chips -->
                    <div class="flex flex-wrap gap-2">
                      <span v-for="(val, vIdx) in group.values" :key="vIdx" class="flex items-center gap-1 bg-primary/10 text-primary text-xs px-2.5 py-1 rounded-full">
                        {{ val }}
                        <button @click="removeSpecValue(gIdx, vIdx)" class="hover:text-danger ml-0.5 leading-none">&times;</button>
                      </span>
                    </div>
                    <!-- Add value input -->
                    <div class="flex gap-2">
                      <input
                        v-model="newSpecValues[gIdx]"
                        @keyup.enter="addSpecValue(gIdx)"
                        :placeholder="$t('product.enterSpecValue')"
                        class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                      />
                      <button @click="addSpecValue(gIdx)" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-text-primary rounded-lg text-sm transition-colors">{{ $t('product.addBtn') }}</button>
                    </div>
                  </div>

                  <button @click="addSpecGroup" class="w-full py-2.5 border border-dashed border-gray-300 hover:border-primary hover:text-primary text-text-secondary rounded-lg text-sm transition-colors flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    {{ $t('product.addSpecGroup') }}
                  </button>
                </div>

                <!-- SKU Matrix -->
                <div v-if="skuMatrix.length > 0">
                  <h4 class="text-sm font-medium text-text-primary mb-3">{{ $t('product.skuCombinationList', { count: skuMatrix.length }) }}</h4>
                  <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="w-full text-sm">
                      <thead class="bg-gray-50">
                        <tr>
                          <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.specCombination') }}</th>
                          <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.skuCodeCol') }}</th>
                          <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.imageCol') }}</th>
                          <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.purchasePriceCol') }}</th>
                          <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.salePriceCol') }}</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in skuMatrix" :key="row.key">
                          <td class="px-3 py-2 text-text-secondary text-xs whitespace-nowrap">{{ row.specsLabel }}</td>
                          <td class="px-3 py-2">
                            <input
                              :value="row.sku"
                              @input="updateMatrixRow(row.key, 'sku', $event.target.value)"
                              :placeholder="$t('product.autoGenerate')"
                              class="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none min-w-[100px]"
                            />
                          </td>
                          <td class="px-3 py-2">
                            <div class="flex items-center gap-2">
                              <div v-if="row.image" class="relative w-12 h-12 border border-gray-200 rounded overflow-hidden shrink-0 group">
                                <img :src="row.image" class="w-full h-full object-cover" />
                                <button
                                  @click="removeSkuImage(row.key)"
                                  class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center"
                                >
                                  <span class="material-symbols-outlined text-white text-[16px]">close</span>
                                </button>
                              </div>
                              <label class="cursor-pointer flex items-center gap-1 text-xs text-primary hover:text-primary-hover transition-colors">
                                <span class="material-symbols-outlined text-[16px]">{{ row.image ? 'edit' : 'add_photo_alternate' }}</span>
                                <span>{{ row.image ? $t('product.changeImage') : $t('product.uploadImage') }}</span>
                                <input
                                  type="file"
                                  accept="image/*"
                                  class="hidden"
                                  @change="onSkuImageChange($event, row.key)"
                                  :disabled="skuImageUploading[row.key]"
                                />
                              </label>
                              <span v-if="skuImageUploading[row.key]" class="text-xs text-text-secondary">{{ $t('product.uploading') }}</span>
                            </div>
                          </td>
                          <td class="px-3 py-2">
                            <input
                              :value="row.purchase_price"
                              @input="updateMatrixRow(row.key, 'purchase_price', $event.target.value)"
                              type="number" min="0" step="0.01" placeholder="0.00"
                              class="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none min-w-[80px]"
                            />
                          </td>
                          <td class="px-3 py-2">
                            <input
                              :value="row.sale_price"
                              @input="updateMatrixRow(row.key, 'sale_price', $event.target.value)"
                              type="number" min="0" step="0.01" placeholder="0.00"
                              class="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none min-w-[80px]"
                            />
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div v-else-if="specGroups.length > 0" class="text-sm text-text-secondary text-center py-4 bg-gray-50 rounded-lg">
                  {{ $t('product.addSpecValuesHint') }}
                </div>
              </template>

              <div v-if="!multiSpec" class="text-sm text-text-secondary text-center py-8">
                {{ $t('product.enableMultiSpecHint') }}
              </div>
            </div>

            <!-- Tab 3: 图片&外链 -->
            <div v-show="activeDrawerTab === 'images'" class="p-6 space-y-6">
              <!-- Main image -->
              <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('product.mainImage') }}</label>
                <div class="flex items-start gap-4">
                  <div class="w-24 h-24 border-2 border-dashed border-gray-300 rounded-lg overflow-hidden flex items-center justify-center bg-gray-50 shrink-0">
                    <img v-if="form.image_main" :src="form.image_main" class="w-full h-full object-cover" />
                    <span v-else class="material-symbols-outlined text-gray-400 text-[32px]">image</span>
                  </div>
                  <div class="flex-1">
                    <div class="flex gap-2 flex-wrap">
                      <label class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-primary hover:bg-gray-50 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">upload</span>
                        {{ imageMainUploading ? $t('product.uploading') : $t('product.selectImage') }}
                        <input type="file" accept="image/*" class="hidden" @change="onMainImageChange" :disabled="imageMainUploading" />
                      </label>
                      <button @click="showLibraryPicker('main')" type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-primary hover:bg-gray-50 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">photo_library</span>
                        从网站图库选择
                      </button>
                    </div>
                    <p class="text-xs text-text-secondary mt-2">{{ $t('product.imageFormatHint') }}</p>
                    <button v-if="form.image_main" @click="form.image_main = ''" class="mt-2 text-xs text-danger hover:text-red-700">{{ $t('product.removeMainImage') }}</button>
                  </div>
                </div>
              </div>

              <!-- Gallery images -->
              <div>
                <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('product.gallery') }}</label>
                <div class="flex flex-wrap gap-3 mb-3">
                  <div v-for="(img, idx) in form.images" :key="idx" class="relative w-20 h-20 rounded-lg overflow-hidden border border-gray-200 group">
                    <img :src="img" class="w-full h-full object-cover" />
                    <button @click="removeGalleryImage(idx)" class="absolute inset-0 bg-black/50 hidden group-hover:flex items-center justify-center text-white">
                      <span class="material-symbols-outlined text-[20px]">delete</span>
                    </button>
                  </div>
                  <label class="cursor-pointer w-20 h-20 border-2 border-dashed border-gray-300 hover:border-primary rounded-lg flex flex-col items-center justify-center text-text-secondary hover:text-primary transition-colors">
                    <span class="material-symbols-outlined text-[20px]">{{ galleryUploading ? 'hourglass_empty' : 'add' }}</span>
                    <span class="text-xs mt-1">{{ galleryUploading ? $t('product.galleryUploadingText') : $t('product.galleryAddText') }}</span>
                    <input type="file" accept="image/*" multiple class="hidden" @change="onGalleryChange" :disabled="galleryUploading" />
                  </label>
                  <button @click="showLibraryPicker('gallery')" type="button"
                    class="w-20 h-20 border-2 border-dashed border-gray-300 hover:border-primary rounded-lg flex flex-col items-center justify-center text-text-secondary hover:text-primary transition-colors">
                    <span class="material-symbols-outlined text-[20px]">photo_library</span>
                    <span class="text-xs mt-1">网站图库</span>
                  </button>
                </div>
                <p class="text-xs text-text-secondary">{{ $t('product.galleryHint') }}</p>
              </div>

              <!-- External links -->
              <div>
                <div class="flex items-center justify-between mb-2">
                  <label class="text-sm font-medium text-text-primary">{{ $t('product.platformLinks') }}</label>
                  <button @click="addExternalLink" class="flex items-center gap-1 text-sm text-primary hover:text-primary-hover transition-colors">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    {{ $t('product.addLink') }}
                  </button>
                </div>
                <div class="space-y-2">
                  <div v-for="(link, idx) in form.external_links" :key="idx" class="flex items-center gap-2">
                    <select v-model="link.platform" class="border border-gray-200 rounded-lg px-2 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none w-28 shrink-0">
                      <option v-for="opt in platformOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <input v-model="link.url" placeholder="https://..." class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                    <button @click="removeExternalLink(idx)" class="text-text-secondary hover:text-danger transition-colors shrink-0">
                      <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                  </div>
                  <div v-if="form.external_links.length === 0" class="text-sm text-text-secondary text-center py-4 bg-gray-50 rounded-lg">
                    {{ $t('product.noLinksYet') }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Error + Footer -->
          <div class="shrink-0 border-t">
            <div v-if="saveError" class="px-6 pt-3 text-sm text-danger bg-red-50 border-b border-red-100 py-2">
              {{ saveError }}
            </div>
            <div class="px-6 py-4 flex gap-3 justify-end">
              <button @click="closeDrawer" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-primary hover:bg-gray-50 transition-colors">{{ $t('common.cancel') }}</button>
              <button @click="handleSave" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors">
                {{ isEdit ? $t('common.saveChanges') : $t('common.add') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Category Management Modal ── -->
    <Teleport to="body">
      <div v-if="showCatModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/40" @click.self="showCatModal = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
          <div class="flex items-center justify-between px-5 py-4 border-b">
            <h3 class="font-bold text-text-primary">{{ $t('product.manageCategoriesTitle') }}</h3>
            <button @click="showCatModal = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
          </div>

          <!-- Tree list -->
          <div class="px-5 py-3 max-h-96 overflow-y-auto space-y-0.5">
            <template v-for="node in catModalFlat" :key="node.id">
              <!-- Category row -->
              <div
                v-if="editingCat !== node.id"
                class="flex items-center gap-1.5 py-1.5 rounded-lg hover:bg-gray-50 group"
                :style="{ paddingLeft: ((node.level - 1) * 20 + 8) + 'px' }"
              >
                <!-- Expand arrow -->
                <button v-if="node.children?.length" @click="catToggleExpand(node.id)"
                  class="text-text-secondary hover:text-primary transition-colors w-5 shrink-0">
                  <span class="material-symbols-outlined text-[16px]">
                    {{ catExpandedIds.includes(node.id) ? 'expand_more' : 'chevron_right' }}
                  </span>
                </button>
                <span v-else class="w-5 shrink-0"></span>

                <!-- Level badge -->
                <span :class="[
                  'text-[10px] font-bold px-1.5 py-0.5 rounded shrink-0',
                  node.level === 1 ? 'bg-blue-100 text-blue-600' :
                  node.level === 2 ? 'bg-green-100 text-green-600' :
                  node.level === 3 ? 'bg-yellow-100 text-yellow-600' :
                                     'bg-purple-100 text-purple-600'
                ]">L{{ node.level }}</span>

                <span class="flex-1 text-sm text-text-primary truncate">{{ node.name }}</span>

                <!-- Actions (show on hover) -->
                <div class="hidden group-hover:flex items-center gap-1 shrink-0">
                  <button v-if="node.level < 4" @click="catStartAddChild(node)"
                    class="text-xs text-primary hover:text-primary-hover px-1.5 py-0.5 rounded transition-colors">{{ $t('product.addChild') }}</button>
                  <button @click="startEditCat(node)" class="text-text-secondary hover:text-primary transition-colors p-0.5">
                    <span class="material-symbols-outlined text-[15px]">edit</span>
                  </button>
                  <button @click="deleteCategory(node)" class="text-text-secondary hover:text-danger transition-colors p-0.5">
                    <span class="material-symbols-outlined text-[15px]">delete</span>
                  </button>
                </div>
              </div>

              <!-- Inline edit row -->
              <div v-else class="flex items-center gap-2 py-1.5 px-2 bg-primary/5 rounded-lg"
                :style="{ marginLeft: ((node.level - 1) * 20) + 'px' }">
                <input v-model="editCatName" @keyup.enter="saveEditCat(node)" @keyup.esc="editingCat = null"
                  class="flex-1 border border-primary rounded px-2 py-1 text-sm focus:ring-1 focus:ring-primary focus:outline-none" autofocus />
                <button @click="saveEditCat(node)" class="text-primary text-xs font-medium hover:text-primary-hover">{{ $t('product.saveBtn') }}</button>
                <button @click="editingCat = null" class="text-text-secondary text-xs hover:text-text-primary">{{ $t('product.cancelBtn') }}</button>
              </div>

              <!-- Inline add-child row -->
              <div v-if="catAddingChildOf === node.id"
                class="flex items-center gap-2 py-1.5 px-2 bg-green-50 border border-green-200 rounded-lg"
                :style="{ marginLeft: (node.level * 20 + 8) + 'px' }">
                <span class="material-symbols-outlined text-green-500 text-[16px] shrink-0">subdirectory_arrow_right</span>
                <input v-model="catChildName" @keyup.enter="catSaveChild(node.id)" @keyup.esc="catAddingChildOf = null"
                  :placeholder="$t('product.addChildPlaceholder', { name: node.name })"
                  class="flex-1 border border-green-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-green-400 focus:outline-none" autofocus />
                <button @click="catSaveChild(node.id)" class="text-green-600 text-xs font-medium hover:text-green-700">{{ $t('product.addBtn') }}</button>
                <button @click="catAddingChildOf = null" class="text-text-secondary text-xs hover:text-text-primary">{{ $t('product.cancelBtn') }}</button>
              </div>
            </template>

            <div v-if="!categories.length" class="text-sm text-text-secondary text-center py-4">{{ $t('product.noCategoriesYet') }}</div>
          </div>

          <!-- Add top-level category -->
          <div class="px-5 py-4 border-t">
            <p class="text-xs text-text-secondary mb-2">{{ $t('product.addTopCategory') }}</p>
            <div class="flex gap-2">
              <input v-model="newCatName" @keyup.enter="addCategory" :placeholder="$t('product.enterTopCatName')" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
              <button @click="addCategory" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors">{{ $t('product.addBtn') }}</button>
            </div>
            <p v-if="catError" class="text-xs text-danger mt-2">{{ catError }}</p>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Material Management / Edit Modal ── -->
    <Teleport to="body">
      <div v-if="showMaterialModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/40" @click.self="showMaterialModal = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col">
          <div class="flex items-center justify-between px-5 py-4 border-b shrink-0">
            <h3 class="font-bold text-text-primary">
              {{ materialMode === 'manage' ? $t('product.materialManagement') : $t('product.editMaterial') }}
              <span v-if="materialProduct" class="font-normal text-text-secondary"> - {{ materialProduct.name }}</span>
            </h3>
            <button @click="showMaterialModal = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
          </div>

          <div class="flex-1 overflow-y-auto p-5">
            <!-- 基础数据管理模式：材料类目增删改 -->
            <div v-if="materialMode === 'manage'">
              <p class="text-sm text-text-secondary mb-3">{{ $t('product.materialCategoriesDesc') }}</p>

              <!-- 已有材料列表 -->
              <div class="space-y-2 mb-4">
                <div v-for="cat in materialCategories" :key="cat.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                  <div>
                    <div class="font-medium text-sm text-text-primary">{{ cat.name }}</div>
                    <div class="text-xs text-text-secondary mt-0.5">
                      {{ $t('product.unit') }}: {{ cat.unit || $t('product.none') }} |
                      供应商: {{ cat.supplier_name || '未指定' }}
                    </div>
                    <div class="text-xs text-text-secondary mt-0.5">
                      {{ $t('product.remarkOptional') }}: {{ cat.remark || $t('product.none') }}
                    </div>
                  </div>
                  <div class="flex gap-2">
                    <button @click="startEditMat(cat)" class="text-blue-600 hover:text-blue-800 text-sm">{{ $t('common.edit') }}</button>
                    <button @click="deleteMaterialCategory(cat.id)" class="text-red-500 hover:text-red-700 text-sm">{{ $t('common.delete') }}</button>
                  </div>
                </div>
                <div v-if="!materialCategories.length" class="text-sm text-text-secondary text-center py-6">
                  {{ $t('product.noMaterialsYet') }}
                </div>
              </div>

              <!-- 新增/编辑材料表单 -->
              <div class="border border-gray-200 rounded-lg p-4 bg-blue-50/50">
                <h4 class="text-sm font-medium text-text-primary mb-3">
                  {{ editingMat ? $t('product.editMaterial') : $t('product.addMaterial') }}
                </h4>
                <div class="space-y-2">
                  <input v-model="newMatName" :placeholder="$t('product.materialName')" class="w-full border border-gray-200 rounded px-3 py-1.5 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                  <div class="flex gap-2">
                    <input v-model="newMatUnit" :placeholder="$t('product.unit')" class="flex-1 border border-gray-200 rounded px-3 py-1.5 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                    <select v-model="newMatSupplierId" class="flex-1 border border-gray-200 rounded px-3 py-1.5 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none bg-white">
                      <option value="">选择原材料供应商</option>
                      <option v-for="s in matSuppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                  </div>
                  <input v-model="newMatRemark" :placeholder="$t('product.remarkOptional')" class="w-full border border-gray-200 rounded px-3 py-1.5 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                  <div class="flex gap-2">
                    <button @click="saveMatCategory" class="flex-1 py-1.5 bg-primary hover:bg-primary-hover text-white rounded text-sm font-medium">
                      {{ $t('common.save') }}
                    </button>
                    <button v-if="editingMat" @click="cancelEditMat" class="px-3 py-1.5 border border-gray-200 rounded text-sm hover:bg-gray-50">
                      {{ $t('common.cancel') }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- 商品配方编辑模式：给商品配置材料 -->
            <div v-else>
              <!-- SKU tabs -->
              <div v-if="(skuMaterialsMap[materialProduct?.id] || []).length > 0" class="flex border-b bg-gray-50 rounded-t-lg -mt-5 mb-4">
                <button @click="activeSkuTab = 'shared'" :class="['px-5 py-3 text-sm font-medium border-b-2 transition-colors', activeSkuTab === 'shared' ? 'border-primary text-primary' : 'border-transparent text-text-secondary hover:text-text-primary']">
                  {{ $t('product.sharedMaterials') }}
                </button>
                <button @click="activeSkuTab = 'per-sku'" :class="['px-5 py-3 text-sm font-medium border-b-2 transition-colors', activeSkuTab === 'per-sku' ? 'border-primary text-primary' : 'border-transparent text-text-secondary hover:text-text-primary']">
                  {{ $t('product.perSkuMaterials') }}
                </button>
              </div>

              <!-- 共用配方 -->
              <div v-show="activeSkuTab === 'shared'">
                <div class="mb-6">
                  <h4 class="text-sm font-medium text-text-primary mb-3">{{ $t('product.selectMaterialToAdd') }}</h4>
                  <div class="space-y-2 max-h-48 overflow-y-auto border border-gray-100 rounded-lg p-3 bg-gray-50">
                    <div v-for="cat in materialCategories" :key="cat.id" class="flex items-center justify-between py-1.5 px-2 hover:bg-white rounded">
                      <div class="flex-1">
                        <span class="text-sm text-text-primary">{{ cat.name }}</span>
                        <span v-if="cat.unit" class="text-xs text-text-secondary ml-2">({{ cat.unit }})</span>
                      </div>
                      <button @click="addMaterialToShared(cat)" class="text-xs text-primary hover:text-primary-hover font-medium">{{ $t('product.addToProduct') }}</button>
                    </div>
                    <div v-if="!materialCategories.length" class="text-sm text-text-secondary text-center py-4">{{ $t('product.noMaterialsYet') }}</div>
                  </div>
                </div>
                <div>
                  <h4 class="text-sm font-medium text-text-primary mb-3">{{ $t('product.sharedMaterials') }} ({{ materialProduct?.name }})</h4>
                  <div class="border border-gray-100 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                      <thead class="bg-gray-50">
                        <tr>
                          <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.materialName') }}</th>
                          <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.unit') }}</th>
                          <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.quantity') }}</th>
                          <th class="px-3 py-2 text-right text-xs font-medium text-text-secondary">{{ $t('common.action') }}</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">
                        <tr v-for="(pm, idx) in productMaterials.filter(pm => !pm.sku_id)" :key="pm.material_id">
                          <td class="px-3 py-2 text-text-primary">{{ pm.material_name }}</td>
                          <td class="px-3 py-2 text-text-secondary">{{ pm.unit || '-' }}</td>
                          <td class="px-3 py-2">
                            <input type="number" v-model.number="pm.quantity" min="0" class="w-20 border border-gray-200 rounded px-2 py-1 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                          </td>
                          <td class="px-3 py-2 text-right">
                            <button @click="removeMaterialFromProduct(idx, 'shared')" class="text-danger hover:text-red-700 text-xs">{{ $t('common.delete') }}</button>
                          </td>
                        </tr>
                        <tr v-if="!productMaterials.filter(pm => !pm.sku_id).length">
                          <td colspan="4" class="px-3 py-6 text-center text-text-secondary text-sm">{{ $t('product.noProductMaterial') }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <!-- 按SKU配方 -->
              <div v-show="activeSkuTab === 'per-sku'">
                <div v-for="sku in (skuMaterialsMap[materialProduct?.id] || [])" :key="sku.id" class="mb-6">
                  <h4 class="text-sm font-medium text-text-primary mb-3 flex items-center gap-2">
                    <span class="bg-blue-100 text-blue-600 text-xs px-2 py-0.5 rounded">{{ sku.sku_key || sku.sku || '#'+sku.id }}</span>
                  </h4>
                  <div class="border border-gray-100 rounded-lg overflow-hidden mb-3">
                    <table class="w-full text-sm">
                      <thead class="bg-gray-50">
                        <tr>
                          <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.materialName') }}</th>
                          <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.unit') }}</th>
                          <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.quantity') }}</th>
                          <th class="px-3 py-2 text-right text-xs font-medium text-text-secondary">{{ $t('common.action') }}</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">
                        <tr v-for="(pm, idx) in (skuMaterialsMap[`${materialProduct?.id}_${sku.id}`] || [])" :key="pm.material_id">
                          <td class="px-3 py-2 text-text-primary">{{ pm.material_name }}</td>
                          <td class="px-3 py-2 text-text-secondary">{{ pm.unit || '-' }}</td>
                          <td class="px-3 py-2">
                            <input type="number" v-model.number="pm.quantity" min="0" class="w-20 border border-gray-200 rounded px-2 py-1 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                          </td>
                          <td class="px-3 py-2 text-right">
                            <button @click="removeMaterialFromProduct(idx, `per-sku-${sku.id}`)" class="text-danger hover:text-red-700 text-xs">{{ $t('common.delete') }}</button>
                          </td>
                        </tr>
                        <tr v-if="!(skuMaterialsMap[`${materialProduct?.id}_${sku.id}`] || []).length">
                          <td colspan="4" class="px-3 py-4 text-center text-text-secondary text-sm">{{ $t('product.noMaterialsForSku') || $t('product.noMaterialsForProduct') }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div class="space-y-2 max-h-40 overflow-y-auto border border-gray-100 rounded-lg p-3 bg-gray-50">
                    <div v-for="cat in materialCategories" :key="cat.id" class="flex items-center justify-between py-1 px-2 hover:bg-white rounded">
                      <div class="flex-1">
                        <span class="text-sm text-text-primary">{{ cat.name }}</span>
                        <span v-if="cat.unit" class="text-xs text-text-secondary ml-2">({{ cat.unit }})</span>
                      </div>
                      <button @click="addMaterialToSku(sku.id, cat)" class="text-xs text-primary hover:text-primary-hover font-medium">{{ $t('product.addToProduct') }}</button>
                    </div>
                  </div>
                </div>
                <div v-if="!(skuMaterialsMap[materialProduct?.id] || []).length" class="text-center py-8 text-text-secondary text-sm">
                  {{ $t('product.noSkusForProduct') }}
                </div>
              </div>
            </div>
          </div>

          <div class="px-5 py-4 border-t shrink-0 flex justify-end gap-3">
            <button v-if="materialMode === 'edit'" @click="showMaterialModal = false" class="px-4 py-2 border border-gray-200 text-text-secondary hover:text-text-primary rounded-lg text-sm transition-colors">{{ $t('common.cancel') }}</button>
            <button v-if="materialMode === 'edit'" @click="saveProductMaterials" :disabled="matLoading" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50">{{ $t('common.saveChanges') }}</button>
            <button v-else @click="showMaterialModal = false" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg text-sm transition-colors">{{ $t('common.close') }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Material Calculator Modal ── -->
    <Teleport to="body">
      <div v-if="showCalcModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/40" @click.self="showCalcModal = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col">
          <div class="flex items-center justify-between px-5 py-4 border-b shrink-0">
            <h3 class="font-bold text-text-primary">{{ $t('product.materialCalculator') }}</h3>
            <button @click="showCalcModal = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
          </div>

          <div class="flex-1 overflow-y-auto p-5">
            <!-- Product & Quantity selector -->
            <div class="mb-6">
              <h4 class="text-sm font-medium text-text-primary mb-3">{{ $t('product.selectProductForCalc') }}</h4>
              <div class="flex gap-3 items-end">
                <div class="flex-1">
                  <label class="block text-xs text-text-secondary mb-1.5">{{ $t('product.product') }}</label>
                  <select v-model="calcProduct" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                    <option :value="null" disabled>{{ $t('product.selectProductPlaceholder') }}</option>
                    <option v-for="p in products" :key="p.id" :value="p">{{ p.name }} ({{ p.sku || $t('product.noSku') }})</option>
                  </select>
                </div>
                <div class="w-32">
                  <label class="block text-xs text-text-secondary mb-1.5">{{ $t('product.productionQuantity') }}</label>
                  <input type="number" v-model.number="calcQuantity" min="1" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
                </div>
              </div>
              <!-- 按SKU显示切换（仅当商品有SKU时显示） -->
              <div v-if="calcProduct && (skuMaterialsMap[calcProduct.id] || []).length > 0" class="mt-3 flex items-center gap-2">
                <input type="checkbox" id="calcPerSkuChk" v-model="calcPerSku" class="rounded border-gray-300 text-primary focus:ring-primary" />
                <label for="calcPerSkuChk" class="text-sm text-text-secondary cursor-pointer select-none">{{ $t('product.showPerSku') }}</label>
              </div>
            </div>

            <!-- Calculation result -->
            <div v-if="calcResult.length > 0">
              <h4 class="text-sm font-medium text-text-primary mb-3">
                {{ $t('product.calcResult') }} ({{ $t('product.quantityPrefix') }}{{ calcQuantity }}{{ $t('product.quantitySuffix') }})
                <span v-if="calcPerSku && calcProduct" class="ml-2 text-xs text-text-secondary">({{ $t('product.perSkuView') }})</span>
              </h4>
              <!-- 有SKU分组 -->
              <template v-if="calcResult.some(r => r.sku_id) && calcPerSku">
                <div v-for="(group, gIdx) in Object.entries(groupBySku(calcResult))" :key="gIdx" class="mb-4">
                  <div class="bg-blue-50 border border-blue-100 rounded-lg px-3 py-2 mb-2">
                    <span class="text-xs font-medium text-blue-600">{{ group[0] }}</span>
                    <span class="text-xs text-blue-400 ml-2">×{{ calcQuantity }}</span>
                  </div>
                  <div class="border border-gray-100 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                      <thead class="bg-gray-50">
                        <tr>
                          <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.materialName') }}</th>
                          <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.unit') }}</th>
                          <th class="px-3 py-2 text-right text-xs font-medium text-text-secondary">{{ $t('product.perUnit') }}</th>
                          <th class="px-3 py-2 text-right text-xs font-medium text-text-secondary">{{ $t('product.totalRequired') }}</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">
                        <tr v-for="r in group[1]" :key="r.material_name">
                          <td class="px-3 py-2 text-text-primary">{{ r.material_name }}</td>
                          <td class="px-3 py-2 text-text-secondary">{{ r.unit || '-' }}</td>
                          <td class="px-3 py-2 text-right text-text-secondary">{{ r.quantity }}</td>
                          <td class="px-3 py-2 text-right font-medium text-primary">{{ typeof r.total === 'number' ? r.total.toFixed(2) : r.total }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </template>
              <!-- 无分组（共用配方列表） -->
              <div v-else class="border border-gray-100 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.materialName') }}</th>
                      <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.unit') }}</th>
                      <th class="px-3 py-2 text-right text-xs font-medium text-text-secondary">{{ $t('product.perUnit') }}</th>
                      <th class="px-3 py-2 text-right text-xs font-medium text-text-secondary">{{ $t('product.totalRequired') }}</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr v-for="r in calcResult" :key="r.material_name + (r.sku_id || '')">
                      <td class="px-3 py-2 text-text-primary">{{ r.material_name }}</td>
                      <td class="px-3 py-2 text-text-secondary">{{ r.unit || '-' }}</td>
                      <td class="px-3 py-2 text-right text-text-secondary">{{ r.quantity }}</td>
                      <td class="px-3 py-2 text-right font-medium text-primary">{{ typeof r.total === 'number' ? r.total.toFixed(2) : r.total }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div v-else-if="calcProduct && calcQuantity > 0" class="text-center py-8 text-text-secondary text-sm">
              {{ $t('product.noMaterialsForProduct') }}
            </div>
          </div>

          <div class="px-5 py-4 border-t shrink-0 flex justify-end">
            <button @click="showCalcModal = false" class="px-4 py-2 border border-gray-200 text-text-secondary hover:text-text-primary rounded-lg text-sm transition-colors">{{ $t('common.close') }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Spec Modal ── -->
    <Teleport to="body">
      <div v-if="showSpecModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="showSpecModal = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[80vh] flex flex-col">
          <div class="flex items-center justify-between px-5 py-4 border-b shrink-0">
            <h3 class="font-bold text-text-primary">{{ $t('product.specListTitle') }}</h3>
            <button @click="showSpecModal = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
          </div>
          <div class="flex-1 overflow-y-auto p-5">
            <div v-if="specModalSkus.length === 0" class="text-center py-8 text-text-secondary">
              {{ $t('product.noSpecsYet') }}
            </div>
            <table v-else class="w-full text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.imageCol') }}</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.productNameLabel') }}</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('product.skuCode') }}</th>
                  <th class="px-3 py-2 text-right text-xs font-medium text-text-secondary">{{ $t('product.salePriceCol') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="(sku, idx) in specModalSkus" :key="idx">
                  <td class="px-3 py-2">
                    <img v-if="sku.image" :src="sku.image" class="w-10 h-10 object-cover rounded border border-gray-100" />
                    <div v-else class="w-10 h-10 rounded border border-gray-100 bg-gray-50 flex items-center justify-center">
                      <span class="material-symbols-outlined text-gray-300 text-[16px]">image</span>
                    </div>
                  </td>
                  <td class="px-3 py-2 text-text-primary">{{ sku.name || specModalProduct?.name }}</td>
                  <td class="px-3 py-2 text-text-secondary text-xs">{{ sku.sku || '-' }}</td>
                  <td class="px-3 py-2 text-right text-text-primary">¥{{ Number(sku.sale_price || 0).toFixed(2) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <!-- 网站图库选择器弹窗 -->
      <div v-if="showLibrary" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/40" @click.self="showLibrary = false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] flex flex-col">
          <div class="flex items-center justify-between px-6 py-4 border-b shrink-0">
            <h3 class="text-lg font-bold">从网站图库选择</h3>
            <button @click="showLibrary = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
          <div class="flex-1 overflow-y-auto p-6">
            <div v-if="libraryLoading" class="text-center py-8 text-text-secondary">加载中...</div>
            <div v-else-if="libraryImages.length === 0" class="text-center py-8 text-text-secondary">图库暂无图片，请先上传</div>
            <div v-else class="grid grid-cols-4 sm:grid-cols-5 gap-3">
              <div v-for="img in libraryImages" :key="img.id" @click="selectLibraryImage(img)"
                class="aspect-square rounded-lg overflow-hidden border border-gray-200 cursor-pointer hover:border-primary hover:shadow-md transition-all group relative">
                <img :src="img.url" class="w-full h-full object-cover" />
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors"></div>
              </div>
            </div>
          </div>
          <div class="flex items-center justify-between px-6 py-3 border-t shrink-0">
            <span class="text-xs text-text-secondary">共 {{ libraryTotal }} 张图片</span>
            <button @click="showLibrary = false" class="px-4 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50">取消</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  /* Toolbar: search input full width, buttons stack */
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-4.mb-6 {
    padding: 12px;
  }
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-4.mb-6 .flex.flex-wrap.items-center.gap-3 {
    flex-direction: column;
    align-items: stretch;
  }
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-4.mb-6 .flex.flex-wrap.items-center.gap-3 > div:first-child,
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-4.mb-6 .flex.flex-wrap.items-center.gap-3 select {
    min-width: unset !important;
    max-width: unset !important;
    width: 100%;
  }
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-4.mb-6 .flex.flex-wrap.items-center.gap-3 button {
    width: 100%;
    justify-content: center;
    padding: 10px 12px;
  }
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-4.mb-6 .ml-auto {
    margin-left: 0;
    width: 100%;
  }
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-4.mb-6 .ml-auto button {
    width: 100%;
  }

  /* Table: enable horizontal scroll */
  .overflow-x-auto {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  /* Table cells: reduce padding */
  table.text-left.text-sm th,
  table.text-left.text-sm td {
    padding: 8px 10px;
    font-size: 12px;
  }

  /* Product image smaller */
  table.text-left.text-sm td img,
  table.text-left.text-sm td > div {
    width: 36px !important;
    height: 36px !important;
  }

  /* Action buttons: stack vertically */
  table.text-left.text-sm td button:last-child {
    margin-right: 0;
  }
  table.text-left.text-sm td button {
    display: block;
    margin-bottom: 4px;
  }
}
</style>
