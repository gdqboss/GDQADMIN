<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../stores/user'
import api from '../services/api.js'

const router = useRouter()
const userStore = useUserStore()
const { t } = useI18n()

// ─── State ─────────────────────────────────────────────────────────────────────
const loading = ref(true)
const error = ref(null)
const myResponsibilities = ref([])

// ─── Scan Modal ─────────────────────────────────────────────────────────────────
const showScanModal = ref(false)
const scanMode = ref('manual')
const imageScanning = ref(false)
const manualQrCode = ref('')
const scannedQrcode = ref(null)
const scannedProduct = ref(null)
const saleType = ref('sale')
const salePrice = ref(0)
const buyerName = ref('')
const buyerPhone = ref('')
const saleNote = ref('')
const saleLoading = ref(false)
const saleError = ref('')
const saleSuccess = ref('')
let html5QrScanner = null

// ─── Gift ──────────────────────────────────────────────────────────────────────
const approverList = ref([])
const selectedApprover = ref('')
const canGift = ref(true)
const rejectedCount = ref(0)
const giftCheckLoading = ref(false)

// ─── Quick Actions ───────────────────────────────────────────────────────────────────
const quickActions = [
  { name: () => t('dashboard.quickAttendance'), icon: 'schedule', color: 'success', route: '/oa/attendance',   permission: 'quick-action-attendance' },
  { name: () => t('dashboard.quickWorkLog'), icon: 'description', color: 'primary', route: '/logs/work-logs', permission: 'quick-action-worklog' },
  { name: () => t('dashboard.quickMyTasks'), icon: 'task_alt', color: 'info', route: '/tasks',              permission: 'quick-action-task' },
  { name: () => t('dashboard.quickScanSale'), icon: 'qr_code_scanner', color: 'warning', action: 'scan',     permission: 'quick-action-scan' },
  { name: () => t('dashboard.quickMyDuties'), icon: 'assignment', color: 'blue', route: '/oa/my-responsibility', permission: 'quick-action-responsibility' },
  { name: () => t('dashboard.quickExpense'), icon: 'receipt_long', color: 'danger', route: '/oa/approvals/create?type=expense', permission: 'quick-action-expense' },
  { name: () => t('dashboard.quickProfile'), icon: 'person', color: 'purple', route: '/profile',            permission: 'quick-action-profile' },
  { name: () => t('dashboard.quickQrcode'), icon: 'qr_code', color: 'teal', route: '/qrcode',           permission: 'quick-action-qrcode' },
]

// 按权限过滤显示的快捷操作
const visibleQuickActions = computed(() =>
  quickActions.filter(a => userStore.canAccess(a.permission))
)

// ─── Actions ────────────────────────────────────────────────────────────────────
const handleQuickAction = (action) => {
  if (action.action === 'scan') { openScanModal() }
  else if (action.route) { router.push(action.route) }
}

// ─── Scan ───────────────────────────────────────────────────────────────────────
const openScanModal = () => {
  showScanModal.value = true
  scanMode.value = 'manual'
  manualQrCode.value = ''
  scannedQrcode.value = null
  scannedProduct.value = null
  saleType.value = 'sale'
  salePrice.value = 0
  buyerName.value = ''
  buyerPhone.value = ''
  saleNote.value = ''
  saleError.value = ''
  saleSuccess.value = ''
  selectedApprover.value = ''
  stopCameraScanner()
}

const closeScanModal = () => {
  showScanModal.value = false
  stopCameraScanner()
}

const scanning = ref(false)
const stopCameraScanner = async () => {
  if (html5QrScanner) {
    try {
      const state = html5QrScanner.getState()
      if (state === 2) await html5QrScanner.stop()
    } catch (e) { /* ignore */ }
    html5QrScanner = null
  }
  scanning.value = false
}

const startCameraScanner = async () => {
  scanMode.value = 'camera'
  saleError.value = ''
  scanning.value = true
  await import('vue').then(v => v.nextTick)
  try {
    const { Html5Qrcode } = await import('html5-qrcode')
    html5QrScanner = new Html5Qrcode('qr-reader')
    await html5QrScanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 220, height: 220 } },
      (decodedText) => {
        stopCameraScanner()
        scanning.value = false
        scanMode.value = 'manual'
        fetchQrcodeInfo(decodedText.trim())
      },
      () => {}
    )
  } catch (err) {
    scanning.value = false
    scanMode.value = 'manual'
    saleError.value = '相机启动失败'
  }
}

const startImageScanner = async () => {
  scanMode.value = 'image'
  saleError.value = ''
  const fileInput = document.getElementById('qr-image-input')
  if (fileInput) fileInput.click()
}

const handleImageSelect = async (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  imageScanning.value = true
  saleError.value = ''
  try {
    const { Html5Qrcode } = await import('html5-qrcode')
    const scanner = new Html5Qrcode('qr-reader')
    const result = await scanner.scanFile(file, false)
    stopCameraScanner()
    imageScanning.value = false
    scanMode.value = 'manual'
    fetchQrcodeInfo(result)
  } catch (err) {
    imageScanning.value = false
    saleError.value = '图片扫描失败'
  }
  event.target.value = ''
}

const handleManualInput = async (qrCode) => {
  if (!qrCode?.trim()) { saleError.value = '请输入二维码编号'; return }
  await fetchQrcodeInfo(qrCode.trim())
}

const loadGiftData = async () => {
  saleType.value = 'gift'
  saleError.value = ''
  giftCheckLoading.value = true
  try {
    const [approversRes, canGiftRes] = await Promise.all([
      api.get('/gift-approvals/approvers'),
      api.get('/gift-approvals/can-gift'),
    ])
    if (approversRes.code === 0) approverList.value = approversRes.data
    if (canGiftRes.code === 0) {
      canGift.value = canGiftRes.data.canGift
      rejectedCount.value = canGiftRes.data.rejectedCount
    }
  } catch (e) {
    console.error('加载赠送数据失败:', e)
  } finally {
    giftCheckLoading.value = false
  }
}

const fetchQrcodeInfo = async (qrCode) => {
  try {
    saleError.value = ''
    let code = qrCode
    try {
      if (code.startsWith('http')) {
        const url = new URL(code)
        code = url.searchParams.get('code') || url.pathname.split('/').pop() || code
      }
    } catch { /* not a URL */ }
    code = code.trim()
    if (!code) { saleError.value = '无法识别的二维码'; return }

    const res = await api.get(`/qrcodes/by-code/${encodeURIComponent(code)}`)
    if (res.code === 0 && res.data) {
      scannedQrcode.value = res.data
      scannedProduct.value = res.data.product
      salePrice.value = res.data.product?.sale_price || 0
      if (res.data.status === 'sold') saleError.value = '该商品已售出'
      else if (res.data.status === 'disabled') saleError.value = '该二维码已禁用'
    } else {
      saleError.value = res.message || '二维码不存在'
    }
  } catch (e) {
    saleError.value = e.message || '查询失败'
  }
}

const confirmSale = async () => {
  if (!scannedQrcode.value) { saleError.value = '请先扫描二维码'; return }

  if (saleType.value === 'sale') {
    if (!salePrice.value || salePrice.value <= 0) { saleError.value = '请输入有效售价'; return }
  }

  if (saleType.value === 'gift') {
    if (!canGift.value) { saleError.value = '您本月赠送次数已用完'; return }
    if (!buyerName.value?.trim()) { saleError.value = '请输入客户姓名'; return }
    if (!buyerPhone.value?.trim()) { saleError.value = '请输入客户电话'; return }
    if (!selectedApprover.value) { saleError.value = '请选择审批人'; return }
  }

  saleLoading.value = true
  saleError.value = ''
  try {
    const res = await api.post('/retail-records', {
      qrcode_id: scannedQrcode.value.id,
      product_id: scannedProduct.value.id,
      type: saleType.value,
      price: saleType.value === 'sale' ? salePrice.value : 0,
      buyer_name: buyerName.value,
      buyer_phone: buyerPhone.value,
      note: saleNote.value,
      approver_id: saleType.value === 'gift' ? selectedApprover.value : undefined,
    })
    if (res.code === 0) {
      saleSuccess.value = saleType.value === 'sale' ? '销售记录已保存' : '赠送申请已提交'
      setTimeout(closeScanModal, 1500)
    } else {
      saleError.value = res.message || '操作失败'
    }
  } catch (e) {
    saleError.value = e.message || '操作失败'
  } finally {
    saleLoading.value = false
  }
}

// ─── Data Loading ───────────────────────────────────────────────────────────────
// ─── 仓库情况组件状态 ───────────────────────────────────────────────────────────────
const warehouseSummary = ref([])
const wsLoading = ref(false)

const loadDashboardData = async () => {
  try {
    error.value = null
    const respRes = await Promise.allSettled([
      api.get('/job-responsibilities/my'),
      api.get('/reports/dashboard-top-warehouses?limit=5'),  // 仓库情况 Top5
    ])

    if (respRes?.[0]?.status === 'fulfilled' && respRes[0].value.code === 0) {
      myResponsibilities.value = respRes[0].value.data || []
    }
    if (respRes?.[1]?.status === 'fulfilled' && respRes[1].value.code === 0) {
      warehouseSummary.value = respRes[1].value.data || []
    } else {
      wsLoading.value = true
    }
  } catch (err) {
    console.error('Failed to load dashboard data:', err)
    error.value = err.message || 'Failed to load data'
  } finally {
    loading.value = false
  }
}

const refreshData = async () => { loading.value = true; await loadDashboardData() }

onMounted(async () => {
  await loadDashboardData()
})

onUnmounted(() => { stopCameraScanner() })
</script>

<template>
  <div class="space-y-4 sm:space-y-6">

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="flex flex-col items-center gap-3">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
        <p class="text-text-secondary text-sm">加载中...</p>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="flex items-center justify-center py-12">
      <div class="flex flex-col items-center gap-3 text-center">
        <span class="material-symbols-outlined text-5xl text-danger">error</span>
        <p class="text-text-primary font-medium">加载失败</p>
        <p class="text-text-secondary text-sm">{{ error }}</p>
        <button @click="refreshData" class="mt-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-hover">
          重新加载
        </button>
      </div>
    </div>

    <!-- Main Content -->
    <template v-else>
      <div class="flex justify-between items-center">
        <h2 class="text-xl sm:text-2xl font-bold text-text-primary">{{ $t('dashboard.title') }}</h2>
        <button @click="refreshData" class="flex items-center gap-2 px-3 py-2 text-sm text-primary hover:bg-primary/10 rounded-lg transition-colors">
          <span class="material-symbols-outlined text-lg">refresh</span>
          <span class="hidden sm:inline">{{ $t('dashboard.refresh') }}</span>
        </button>
      </div>

      <!-- 快捷操作 - 所有用户 -->
      <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 sm:p-5">
        <div class="flex justify-between items-center mb-3 sm:mb-4">
          <h4 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-base sm:text-lg">bolt</span>
            {{ $t('dashboard.quickActions') }}
          </h4>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
          <button
            v-for="action in visibleQuickActions"
            :key="action.permission"
            @click="handleQuickAction(action)"
            class="flex flex-col items-center gap-2 p-3 sm:p-4 rounded-lg border border-gray-200 hover:border-primary hover:bg-primary/5 transition-all group"
          >
            <span :class="['material-symbols-outlined text-2xl sm:text-3xl group-hover:scale-110 transition-transform', `text-${action.color}`]">{{ action.icon }}</span>
            <span class="text-xs sm:text-sm font-medium text-text-primary text-center">{{ action.name() }}</span>
          </button>
        </div>
      </div>

      <!-- 仓库情况 Top 5 -->
      <div v-if="warehouseSummary.length > 0 && userStore.canAccess('dashboard:warehouse_summary')" class="bg-white rounded-lg border border-gray-100 shadow-card p-4 sm:p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h4 class="font-bold text-text-primary flex items-center gap-2">
              <span class="material-symbols-outlined text-primary">warehouse</span>
              {{ t('dashboard.warehouseSummary') || '仓库情况' }}
            </h4>
            <p class="text-xs text-text-secondary mt-1">{{ t('dashboard.warehouseSummaryDesc') || '按库存数量排序，展示前 5 个仓库' }}</p>
          </div>
          <router-link
            to="/reports?tab=stock"
            class="text-xs text-primary hover:underline flex items-center gap-1"
          >
            {{ t('common.viewAll') || '查看全部' }}
            <span class="material-symbols-outlined text-sm">arrow_forward</span>
          </router-link>
        </div>
        <div class="space-y-2">
          <div
            v-for="(wh, idx) in warehouseSummary"
            :key="wh.id"
            @click="router.push(`/reports?tab=stock&warehouse=${wh.id}`)"
            class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:border-primary hover:bg-primary/5 cursor-pointer transition-all"
          >
            <!-- 排名 -->
            <div :class="['flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold',
              idx === 0 ? 'bg-yellow-100 text-yellow-700' :
              idx === 1 ? 'bg-gray-100 text-gray-700' :
              idx === 2 ? 'bg-orange-100 text-orange-700' :
              'bg-gray-50 text-gray-500']">
              {{ idx + 1 }}
            </div>
            <!-- 仓库名 + 类型 -->
            <div class="flex-shrink-0 w-40">
              <p class="font-medium text-sm text-text-primary truncate">{{ wh.name }}</p>
              <p class="text-xs text-text-secondary">{{ wh.type }} · {{ wh.manager || '未指定' }}</p>
            </div>
            <!-- 5 个指标 -->
            <div class="flex-1 grid grid-cols-5 gap-2 text-center">
              <div>
                <p class="text-xs text-text-secondary">库存数</p>
                <p class="text-sm font-bold text-text-primary">{{ Number(wh.total_qty).toLocaleString() }}</p>
              </div>
              <div>
                <p class="text-xs text-text-secondary">价值(元)</p>
                <p class="text-sm font-bold text-primary">{{ Number(wh.total_value).toLocaleString() }}</p>
              </div>
              <div>
                <p class="text-xs text-text-secondary">商品种类</p>
                <p class="text-sm font-bold text-text-primary">{{ wh.sku_count }}</p>
              </div>
              <div>
                <p class="text-xs text-text-secondary">30天出库</p>
                <p class="text-sm font-bold text-success">{{ wh.outbound_30d }}</p>
              </div>
              <div>
                <p class="text-xs text-text-secondary">低库存</p>
                <p :class="['text-sm font-bold', wh.low_stock_count > 0 ? 'text-danger' : 'text-text-primary']">
                  {{ wh.low_stock_count }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 我的权责 -->
      <div v-if="myResponsibilities.length > 0" class="bg-white rounded-lg border border-gray-100 shadow-card p-4 sm:p-5">
        <div class="flex justify-between items-center mb-3 sm:mb-4">
          <h4 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-base sm:text-lg">assignment</span>
            {{ $t('dashboard.myResponsibilities') }}
          </h4>
        </div>
        <div class="space-y-3">
          <div v-for="item in myResponsibilities.slice(0, 6)" :key="item.id"
               class="flex items-start gap-3 p-3 rounded-lg border border-gray-100 hover:border-primary/30 hover:bg-primary/5 transition-all">
            <span :class="['material-symbols-outlined text-xl shrink-0',
              item.category === 'duty' ? 'text-primary' : item.category === 'authority' ? 'text-success' : 'text-warning'
            ]">
              {{ item.category === 'duty' ? 'task' : item.category === 'authority' ? 'verified_user' : 'analytics' }}
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-sm font-medium text-text-primary">{{ item.title }}</span>
                <span :class="['px-2 py-0.5 rounded text-xs',
                  item.category === 'duty' ? 'bg-primary/10 text-primary' :
                  item.category === 'authority' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'
                ]">
                  {{ item.category === 'duty' ? '职责' : item.category === 'authority' ? '权限' : 'KPI' }}
                </span>
              </div>
              <p v-if="item.description" class="text-xs text-text-secondary line-clamp-2">{{ item.description }}</p>
            </div>
          </div>
        </div>
      </div>

    </template>
  </div>

  <!-- 扫码出售/赠送弹窗 -->
  <div v-if="showScanModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="closeScanModal">
    <div class="w-full max-w-md bg-white rounded-xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
      <div class="p-5 border-b border-gray-100 shrink-0">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-bold text-text-primary flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">qr_code_scanner</span>
            扫码操作
          </h2>
          <button @click="closeScanModal" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
      </div>

      <div class="p-5 space-y-4 overflow-y-auto">
        <!-- 输入方式 -->
        <div v-if="!scannedQrcode">
          <div class="flex gap-2 mb-3">
            <button @click="scanMode = 'manual'; stopCameraScanner()"
              :class="['flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg border text-sm font-medium transition-all',
                scanMode === 'manual' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-text-secondary hover:border-gray-300']">
              <span class="material-symbols-outlined text-lg">keyboard</span>
              手动输入
            </button>
            <button @click="startCameraScanner()"
              :class="['flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg border text-sm font-medium transition-all',
                scanMode === 'camera' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-text-secondary hover:border-gray-300']">
              <span class="material-symbols-outlined text-lg">photo_camera</span>
              相机扫描
            </button>
            <button @click="startImageScanner()"
              :class="['flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg border text-sm font-medium transition-all',
                scanMode === 'image' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-text-secondary hover:border-gray-300']">
              <span class="material-symbols-outlined text-lg">photo_library</span>
              相册
            </button>
          </div>

          <input id="qr-image-input" type="file" accept="image/*" class="hidden" @change="handleImageSelect" />

          <!-- 手动输入 -->
          <div v-if="scanMode === 'manual'">
            <label class="block text-sm font-medium text-text-primary mb-2">输入二维码编号</label>
            <div class="flex gap-2">
              <input type="text" v-model="manualQrCode" placeholder="请输入二维码编号"
                class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                @keyup.enter="handleManualInput(manualQrCode)" />
              <button @click="handleManualInput(manualQrCode)"
                class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-hover transition-colors">
                查询
              </button>
            </div>
          </div>

          <!-- 摄像头扫码 -->
          <div v-if="scanMode === 'camera'">
            <div id="qr-reader" class="rounded-lg overflow-hidden border border-gray-200"></div>
            <p v-if="scanning" class="text-xs text-text-secondary text-center mt-2 flex items-center justify-center gap-1">
              <span class="animate-pulse inline-block w-2 h-2 bg-success rounded-full"></span>
              相机运行中
            </p>
          </div>

          <!-- 从相册选择 -->
          <div v-if="scanMode === 'image'" class="text-center py-8">
            <div v-if="imageScanning" class="flex flex-col items-center gap-3">
              <div class="w-10 h-10 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
              <p class="text-sm text-text-secondary">扫描中...</p>
            </div>
            <div v-else class="flex flex-col items-center gap-3">
              <span class="material-symbols-outlined text-5xl text-text-secondary">photo_library</span>
              <p class="text-sm text-text-secondary">从相册选择图片扫描二维码</p>
              <button @click="startImageScanner()"
                class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-hover transition-colors">
                选择图片
              </button>
            </div>
          </div>
        </div>

        <!-- 商品信息 -->
        <div v-if="scannedProduct" class="space-y-4">
          <div class="border border-gray-200 rounded-lg p-3">
            <div class="flex items-center gap-3">
              <img v-if="scannedProduct.image_main" :src="scannedProduct.image_main" class="w-14 h-14 object-cover rounded-lg" />
              <div class="flex-1 min-w-0">
                <p class="font-medium text-text-primary text-sm truncate">{{ scannedProduct.name }}</p>
                <p class="text-xs text-text-secondary">SKU: {{ scannedProduct.sku }}</p>
                <p class="text-xs text-text-secondary">二维码: {{ scannedQrcode.qr_code }}</p>
              </div>
            </div>
          </div>

          <!-- 操作类型 -->
          <div>
            <label class="block text-sm font-medium text-text-primary mb-2">操作类型</label>
            <div class="grid grid-cols-2 gap-2">
              <button @click="saleType = 'sale'; saleError = ''"
                :class="['flex items-center justify-center gap-2 py-2.5 rounded-lg border-2 text-sm font-medium transition-all',
                  saleType === 'sale' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-text-secondary hover:border-gray-300']">
                <span class="material-symbols-outlined text-lg">sell</span>
                销售
              </button>
              <button @click="loadGiftData()"
                :class="['flex items-center justify-center gap-2 py-2.5 rounded-lg border-2 text-sm font-medium transition-all',
                  saleType === 'gift' ? 'border-orange-500 bg-orange-50 text-orange-600' : 'border-gray-200 text-text-secondary hover:border-gray-300']">
                <span class="material-symbols-outlined text-lg">card_giftcard</span>
                赠送
              </button>
            </div>
          </div>

          <!-- 售价 -->
          <div v-if="saleType === 'sale'">
            <label class="block text-sm font-medium text-text-primary mb-1">售价 <span class="text-danger">*</span></label>
            <input type="number" v-model="salePrice" placeholder="0" :min="scannedProduct.purchase_price || 0"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            <p v-if="scannedProduct.purchase_price" class="text-xs text-text-secondary mt-1">
              成本价: ¥{{ scannedProduct.purchase_price }}，建议售价: ¥{{ scannedProduct.sale_price }}
            </p>
          </div>

          <!-- 赠送提示 -->
          <div v-if="saleType === 'gift' && !canGift" class="bg-red-50 border border-red-200 rounded-lg p-3">
            <p class="text-xs text-red-700 flex items-center gap-1">
              <span class="material-symbols-outlined text-sm">block</span>
              您本月赠送次数已用完（共 {{ rejectedCount }} 次被拒绝）
            </p>
          </div>
          <div v-if="saleType === 'gift' && canGift" class="bg-orange-50 border border-orange-200 rounded-lg p-3">
            <p class="text-xs text-orange-700 flex items-center gap-1">
              <span class="material-symbols-outlined text-sm">info</span>
              赠送需要主管审批，请填写客户信息
            </p>
          </div>

          <!-- 审批人 -->
          <div v-if="saleType === 'gift' && canGift">
            <label class="block text-sm font-medium text-text-primary mb-1">审批人 <span class="text-danger">*</span></label>
            <select v-model="selectedApprover"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
              <option value="" disabled>{{ giftCheckLoading ? '加载中...' : '请选择审批人' }}</option>
              <option v-for="a in approverList" :key="a.id" :value="a.id">{{ a.name }} ({{ a.role }})</option>
            </select>
          </div>

          <!-- 客户姓名 -->
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">客户姓名
              <span v-if="saleType === 'gift'" class="text-danger">*</span>
              <span v-else class="text-text-secondary text-xs font-normal">选填</span>
            </label>
            <input type="text" v-model="buyerName" placeholder="客户姓名"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>

          <!-- 客户电话 -->
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">客户电话
              <span v-if="saleType === 'gift'" class="text-danger">*</span>
              <span v-else class="text-text-secondary text-xs font-normal">选填</span>
            </label>
            <input type="tel" v-model="buyerPhone" placeholder="客户电话"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
          </div>

          <!-- 备注 -->
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">备注
              <span class="text-text-secondary text-xs font-normal">选填</span>
            </label>
            <textarea v-model="saleNote" placeholder="备注信息" rows="2"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none resize-none"></textarea>
          </div>
        </div>

        <!-- 成功提示 -->
        <div v-if="saleSuccess" class="text-sm text-success bg-green-50 py-2 px-3 rounded-lg flex items-center gap-2">
          <span class="material-symbols-outlined text-lg">check_circle</span>
          {{ saleSuccess }}
        </div>

        <!-- 错误提示 -->
        <div v-if="saleError" class="text-sm text-danger bg-red-50 py-2 px-3 rounded-lg flex items-center gap-2">
          <span class="material-symbols-outlined text-lg">error</span>
          {{ saleError }}
        </div>

        <!-- 操作按钮 -->
        <div v-if="!saleSuccess" class="flex gap-3 pt-1">
          <button v-if="scannedQrcode" @click="openScanModal"
            class="flex-1 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-text-primary hover:bg-gray-50">
            重新扫描
          </button>
          <button @click="closeScanModal"
            class="flex-1 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-text-primary hover:bg-gray-50">
            取消
          </button>
          <button v-if="scannedProduct && saleType === 'sale'" @click="confirmSale"
            :disabled="saleLoading || !salePrice || salePrice <= 0"
            class="flex-1 py-2.5 bg-primary text-white rounded-lg text-sm font-bold hover:bg-primary-hover disabled:opacity-50 disabled:cursor-not-allowed">
            {{ saleLoading ? '处理中...' : '确认销售' }}
          </button>
          <button v-if="scannedProduct && saleType === 'gift'" @click="confirmSale"
            :disabled="saleLoading || !canGift || !selectedApprover"
            class="flex-1 py-2.5 bg-orange-500 text-white rounded-lg text-sm font-bold hover:bg-orange-600 disabled:opacity-50 disabled:cursor-not-allowed">
            {{ saleLoading ? '处理中...' : '确认赠送' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media (max-width: 768px) {
  /* 卡片网格 - 快捷操作 */
  .grid-cols-2 {
    grid-template-columns: repeat(2, 1fr);
  }
  
  /* 快捷操作图标和文字 */
  .grid span.material-symbols-outlined {
    font-size: 1.5rem;
  }
  .grid span.text-sm {
    font-size: 0.7rem;
  }
  
  /* 卡片内边距 */
  .bg-white.rounded-lg {
    padding: 0.75rem;
  }
  
  /* 标题文字 */
  .text-xl {
    font-size: 1.125rem;
  }
  .text-2xl {
    font-size: 1.25rem;
  }
  
  /* 权责列表项 */
  .space-y-3 > div {
    padding: 0.625rem;
  }
  
  /* 弹窗适配 */
  .fixed.inset-0 {
    padding: 1rem;
  }
  .max-w-md {
    max-width: 100%;
  }
}
</style>