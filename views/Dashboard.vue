<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useUserStore } from '../stores/user'
import StatCard from '../components/StatCard.vue'
import StatusTag from '../components/StatusTag.vue'
import api from '../services/api.js'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent } from 'echarts/components'
import VChart from 'vue-echarts'

use([CanvasRenderer, LineChart, GridComponent, TooltipComponent, LegendComponent])

const { t } = useI18n()
const router = useRouter()

const USE_MOCK_DATA = false // Switch to real API

const loading = ref(true)
const error = ref(null)
const stats = ref({})
const todayData = ref({})
const pendingAlerts = ref([])
const pendingApprovals = ref([])
const warehouses = ref([])
const trendOption = ref({})
const isMobile = ref(false)
const myResponsibilities = ref([]) // 我的职位权责
const myCurrentTasks = ref([]) // 我当前的任务

// 管理层数据
const teamAttendance = ref(null)
const teamWorkLogs = ref(null)

// 执行层数据
const myTodayAttendance = ref(null)
const myTodayWorkLog = ref(null)

// 扫码出售相关
const showScanModal = ref(false)
const showOvertimeModal = ref(false)
const overtimeHours = ref('')
const overtimeReason = ref('')
const scanning = ref(false)
const scanMode = ref('manual') // 'manual', 'camera', 或 'image'
const imageScanning = ref(false)
const manualQrCode = ref('')
const scannedQrcode = ref(null)
const scannedProduct = ref(null)
const saleType = ref('sale') // 'sale' 或 'gift'
const salePrice = ref(0)
const buyerName = ref('')
const buyerPhone = ref('')
const saleNote = ref('')
const saleLoading = ref(false)
const saleError = ref('')
const saleSuccess = ref('')
let html5QrScanner = null

// 赠送审批相关
const approverList = ref([])
const selectedApprover = ref('')
const canGift = ref(true)
const rejectedCount = ref(0)
const giftCheckLoading = ref(false)

// 获取当前用户信息
const userStore = useUserStore()

const quickActions = computed(() => {
  // 所有用户统一的6个快捷操作
  return [
    { name: t('dashboard.quickAttendance'), icon: 'schedule', color: 'success', route: '/oa/attendance' },
    { name: t('dashboard.quickWorkLog'), icon: 'description', color: 'primary', route: '/logs/work-logs' },
    { name: t('dashboard.quickMyTasks'), icon: 'task_alt', color: 'info', route: '/tasks' },
    { name: t('dashboard.quickScanSale'), icon: 'qr_code_scanner', color: 'warning', action: 'scan' },
    { name: t('dashboard.quickMyDuties'), icon: 'assignment', color: 'blue', route: '/oa/my-responsibility' },
    { name: t('dashboard.quickExpense'), icon: 'receipt_long', color: 'danger', route: '/oa/approvals/create?type=expense' },
  ]
})

const aiTasks = computed(() => [
  { name: t('dashboard.ai1688'), status: 'running', icon: 'play_circle', color: 'text-success' },
  { name: t('dashboard.aiTaobao'), status: 'idle', icon: 'pause_circle', color: 'text-info' },
  { name: t('dashboard.aiInternational'), status: 'error', icon: 'error', color: 'text-danger' },
  { name: t('dashboard.aiStockAlert'), status: 'idle', icon: 'pause_circle', color: 'text-info' },
])

const handleQuickAction = (action) => {
  if (action.action === 'scan') {
    openScanModal()
  } else if (action.route) {
    router.push(action.route)
  }
}

// 打开扫码弹窗
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

// 关闭扫码弹窗
const closeScanModal = () => {
  showScanModal.value = false
  scanning.value = false
  stopCameraScanner()
}

// 切换到从相册选择图片扫码
const startImageScanner = async () => {
  scanMode.value = 'image'
  saleError.value = ''
  // 触发文件选择
  const fileInput = document.getElementById('qr-image-input')
  if (fileInput) fileInput.click()
}

// 处理选择的图片
const handleImageSelect = async (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  
  imageScanning.value = true
  saleError.value = ''
  
  try {
    const { Html5Qrcode } = await import('html5-qrcode')
    const scanner = new Html5Qrcode('qr-reader')
    
    // 使用html5-qrcode扫描图片文件
    const result = await scanner.scanFile(file, false)
    // result是识别出的文本内容
    stopCameraScanner()
    imageScanning.value = false
    scanMode.value = 'manual'
    fetchQrcodeInfo(result)
  } catch (err) {
    imageScanning.value = false
    saleError.value = t('dashboard.imageScanFailed')
    console.error('Image scan error:', err)
  }
  
  // 清空input，允许重新选择同一张图片
  event.target.value = ''
}
const startCameraScanner = async () => {
  scanMode.value = 'camera'
  saleError.value = ''
  scanning.value = true
  await nextTick()

  try {
    const { Html5Qrcode } = await import('html5-qrcode')
    html5QrScanner = new Html5Qrcode('qr-reader')
    await html5QrScanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 220, height: 220 } },
      (decodedText) => {
        // 扫到码后停止扫描并查询
        stopCameraScanner()
        scanning.value = false
        scanMode.value = 'manual'
        fetchQrcodeInfo(decodedText.trim())
      },
      () => {} // 忽略扫描失败
    )
  } catch (err) {
    scanning.value = false
    scanMode.value = 'manual'
    saleError.value = t('dashboard.cameraError')
    console.error('Camera error:', err)
  }
}

// 停止摄像头
const stopCameraScanner = async () => {
  if (html5QrScanner) {
    try {
      const state = html5QrScanner.getState()
      if (state === 2) { // SCANNING
        await html5QrScanner.stop()
      }
    } catch (e) { /* ignore */ }
    html5QrScanner = null
  }
  scanning.value = false
}

// 手动输入二维码
const handleManualInput = async (qrCode) => {
  if (!qrCode || !qrCode.trim()) {
    saleError.value = t('dashboard.enterQrCodePlaceholder')
    return
  }
  await fetchQrcodeInfo(qrCode.trim())
}

// 切换到赠送时加载审批人和检查赠送资格
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

// 获取二维码信息
const fetchQrcodeInfo = async (qrCode) => {
  try {
    saleError.value = ''
    // 扫到的可能是URL（如 https://xxx/h5/scan?code=GDQ...），需提取编号
    let code = qrCode
    try {
      if (code.startsWith('http')) {
        const url = new URL(code)
        code = url.searchParams.get('code') || url.pathname.split('/').pop() || code
      }
    } catch { /* not a URL, use as-is */ }
    code = code.trim()
    if (!code) {
      saleError.value = t('dashboard.unrecognizedQr')
      return
    }

    const res = await api.get(`/qrcodes/by-code/${encodeURIComponent(code)}`)
    if (res.code === 0 && res.data) {
      scannedQrcode.value = res.data
      scannedProduct.value = res.data.product
      salePrice.value = res.data.product?.sale_price || 0

      // 检查二维码状态
      if (res.data.status === 'sold') {
        saleError.value = t('dashboard.alreadySold')
      } else if (res.data.status === 'unused') {
        saleError.value = t('dashboard.notBound')
      } else if (res.data.status === 'disabled') {
        saleError.value = t('dashboard.qrDisabled')
      } else if (res.data.status === 'afterSale') {
        saleError.value = t('dashboard.inAfterSale')
      }
    } else {
      saleError.value = res.message || t('dashboard.qrNotFound')
    }
  } catch (e) {
    saleError.value = e.message || t('dashboard.queryFailed')
  }
}

// 确认出售/赠送
const confirmSale = async () => {
  if (!scannedQrcode.value) {
    saleError.value = t('dashboard.scanFirst')
    return
  }

  // 销售验证
  if (saleType.value === 'sale') {
    if (!salePrice.value || salePrice.value <= 0) {
      saleError.value = t('dashboard.invalidPrice')
      return
    }
    const costPrice = scannedProduct.value?.purchase_price || 0
    if (parseFloat(salePrice.value) < parseFloat(costPrice)) {
      saleError.value = t('dashboard.priceBelowCost', { cost: costPrice })
      return
    }
  }

  // 赠送验证
  if (saleType.value === 'gift') {
    if (!canGift.value) {
      saleError.value = t('dashboard.giftBlockedShort')
      return
    }
    if (!buyerName.value.trim()) {
      saleError.value = t('dashboard.giftNameRequired')
      return
    }
    if (!buyerPhone.value.trim()) {
      saleError.value = t('dashboard.giftPhoneRequired')
      return
    }
    if (!selectedApprover.value) {
      saleError.value = t('dashboard.giftApproverRequired')
      return
    }
  }

  saleLoading.value = true
  saleError.value = ''

  try {
    const res = await api.post('/retail-records', {
      qrcode_id: scannedQrcode.value.id,
      product_id: scannedProduct.value.id,
      type: saleType.value,
      sale_price: saleType.value === 'sale' ? salePrice.value : 0,
      buyer_name: buyerName.value,
      buyer_phone: buyerPhone.value,
      note: saleNote.value,
      channel: 'scan',
      approver_id: saleType.value === 'gift' ? selectedApprover.value : undefined
    })

    if (res.code === 0) {
      saleSuccess.value = saleType.value === 'sale' ? t('dashboard.saleSuccess') : t('dashboard.giftSubmitted')
      setTimeout(() => {
        closeScanModal()
        loadDashboardData()
      }, 1500)
    } else {
      saleError.value = res.message || t('dashboard.operationFailed')
    }
  } catch (e) {
    saleError.value = e.message || t('dashboard.operationFailed')
  } finally {
    saleLoading.value = false
  }
}


// 提交加班申请
const submitOvertime = async () => {
  if (!overtimeHours.value || !overtimeHours.value.trim()) {
    alert(t('dashboard.overtimePeriodRequired'))
    return
  }
  
  // 验证格式 HH:MM-HH:MM
  const timePattern = /^\d{1,2}:\d{2}-\d{1,2}:\d{2}$/
  if (!timePattern.test(overtimeHours.value.trim())) {
    alert(t('dashboard.overtimeFormatError'))
    return
  }

  try {
    const res = await api.post('/oa/overtime', {
      hours: overtimeHours.value.trim(),
      reason: overtimeReason.value.trim()
    })
    
    if (res.code === 0) {
      alert(t('dashboard.overtimeSubmitted'))
      showOvertimeModal.value = false
      overtimeHours.value = ''
      overtimeReason.value = ''
    } else {
      alert(res.message || t('common.submitFailed'))
    }
  } catch (e) {
    alert(e.message || t('common.submitFailed'))
  }
}

const handleReplenish = (alert) => {
  router.push(`/in-out?tab=inbound&product=${alert.product_id}`)
}

const handleApprovalClick = (approval) => {
  router.push(`/approvals/${approval.id}`)
}

const refreshData = async () => {
  loading.value = true
  await loadDashboardData()
  loading.value = false
}

const loadDashboardData = async () => {
  try {
    error.value = null
    const role = userStore.userRole

    // 根据角色加载不同的数据
    const promises = []

    // 所有角色都加载：职位权责、任务
    promises.push(api.get('/job-responsibilities/my'))
    promises.push(api.get('/tasks/my', { params: { limit: 5 } }))

    // 管理员和经理加载：统计数据、库存预警、审批、仓库、趋势、团队动态
    if (role === 'admin' || role === 'manager') {
      promises.push(api.get('/dashboard/stats'))
      promises.push(api.get('/stock-alerts', { params: { handled: 'false', limit: 5 } }))
      promises.push(api.get('/approvals', { params: { status: 'pending', size: 5 } }))
      promises.push(api.get('/warehouses'))
      promises.push(api.get('/reports/monthly-inout'))
      promises.push(api.get('/oa/attendance/today-summary'))
      promises.push(api.get('/oa/work-logs/today-summary'))
    }

    // 执行层加载：我的今日考勤和日志
    if (role === 'operator' || role === 'member') {
      promises.push(api.get('/oa/attendance/my-today'))
      promises.push(api.get('/oa/work-logs/my-today'))
    }

    const results = await Promise.allSettled(promises)

    let respRes, tasksRes, dashRes, alertRes, approvalRes, whRes, trendRes, teamAttRes, teamLogRes, myAttRes, myLogRes

    // 解析结果
    if (role === 'admin' || role === 'manager') {
      [respRes, tasksRes, dashRes, alertRes, approvalRes, whRes, trendRes, teamAttRes, teamLogRes] = results

      if (teamAttRes && teamAttRes.status === 'fulfilled' && teamAttRes.value.code === 0) {
        teamAttendance.value = teamAttRes.value.data
      } else {
        teamAttendance.value = { total_employees: 0, checked_in: 0, late_count: 0, early_leave_count: 0, absent_count: 0 }
      }
      if (teamLogRes && teamLogRes.status === 'fulfilled' && teamLogRes.value.code === 0) {
        teamWorkLogs.value = teamLogRes.value.data
      } else {
        teamWorkLogs.value = { total_employees: 0, submitted_count: 0 }
      }
    } else if (role === 'operator' || role === 'member') {
      [respRes, tasksRes, myAttRes, myLogRes] = results

      if (myAttRes && myAttRes.status === 'fulfilled' && myAttRes.value.code === 0) {
        myTodayAttendance.value = myAttRes.value.data
      } else {
        myTodayAttendance.value = { clock_in: null, clock_out: null }
      }
      if (myLogRes && myLogRes.status === 'fulfilled' && myLogRes.value.code === 0) {
        myTodayWorkLog.value = myLogRes.value.data
      } else {
        myTodayWorkLog.value = null
      }
    } else {
      // 其他角色只有前两个
      [respRes, tasksRes] = results
    }

    if (dashRes && dashRes.status === 'fulfilled' && dashRes.value.code === 0) {
      stats.value = dashRes.value.data
      todayData.value = {
        todaySales: dashRes.value.data.todaySales || 0,
        todayInbound: dashRes.value.data.todayInbound || 0,
        todayOutbound: dashRes.value.data.todayOutbound || 0,
        pendingApprovals: dashRes.value.data.pendingApprovals || 0,
      }
    } else if (dashRes) {
      console.error('Dashboard stats failed:', dashRes)
    }
    if (alertRes && alertRes.status === 'fulfilled' && alertRes.value.code === 0) {
      pendingAlerts.value = alertRes.value.data.list || alertRes.value.data || []
    }
    if (approvalRes && approvalRes.status === 'fulfilled' && approvalRes.value.code === 0) {
      pendingApprovals.value = approvalRes.value.data.list || approvalRes.value.data || []
    }
    if (whRes && whRes.status === 'fulfilled' && whRes.value.code === 0) {
      warehouses.value = whRes.value.data || []
    }
    if (trendRes && trendRes.status === 'fulfilled' && trendRes.value.code === 0) {
      const months = trendRes.value.data.map(d => d.month + t('common.month'))
      trendOption.value = {
        tooltip: { trigger: 'axis' },
        legend: { data: [t('dashboard.inbound'), t('dashboard.outbound')], top: 0, right: 0, textStyle: { fontSize: 12, color: '#909399' } },
        grid: { left: 50, right: 20, top: 40, bottom: 30 },
        xAxis: { type: 'category', data: months, axisLine: { lineStyle: { color: '#dcdfe6' } }, axisLabel: { color: '#909399', fontSize: 11 } },
        yAxis: { type: 'value', axisLine: { show: false }, splitLine: { lineStyle: { color: '#f0f0f0' } }, axisLabel: { color: '#909399', fontSize: 11 } },
        series: [
          { name: t('dashboard.inbound'), type: 'line', data: trendRes.value.data.map(d => d.inbound), smooth: true, itemStyle: { color: '#409eff' }, areaStyle: { color: { type: 'linear', x: 0, y: 0, x2: 0, y2: 1, colorStops: [{ offset: 0, color: 'rgba(64,158,255,0.25)' }, { offset: 1, color: 'rgba(64,158,255,0.02)' }] } } },
          { name: t('dashboard.outbound'), type: 'line', data: trendRes.value.data.map(d => d.outbound), smooth: true, itemStyle: { color: '#e6a23c' }, areaStyle: { color: { type: 'linear', x: 0, y: 0, x2: 0, y2: 1, colorStops: [{ offset: 0, color: 'rgba(230,162,60,0.25)' }, { offset: 1, color: 'rgba(230,162,60,0.02)' }] } } },
        ],
      }
    }
    if (respRes && respRes.status === 'fulfilled' && respRes.value.code === 0) {
      myResponsibilities.value = respRes.value.data || []
    }
    if (tasksRes && tasksRes.status === 'fulfilled' && tasksRes.value.code === 0) {
      const taskData = tasksRes.value.data
      const taskList = taskData.tasks || taskData || []
      myCurrentTasks.value = taskList.filter(t => t.status === 'pending' || t.status === 'in_progress').slice(0, 5)
    }
  } catch (err) {
    console.error('Failed to load dashboard data:', err)
    error.value = err.message || 'Failed to load data'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  // Check if mobile
  if (typeof window !== 'undefined') {
    isMobile.value = window.innerWidth < 640
    window.addEventListener('resize', () => {
      isMobile.value = window.innerWidth < 640
    })
  }
  await loadDashboardData()
})

onUnmounted(() => {
  stopCameraScanner()
})
</script>

<template>
  <div class="space-y-4 sm:space-y-6">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="flex flex-col items-center gap-3">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
        <p class="text-text-secondary text-sm">{{ $t('common.loading') }}</p>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="flex items-center justify-center py-12">
      <div class="flex flex-col items-center gap-3 text-center">
        <span class="material-symbols-outlined text-5xl text-danger">error</span>
        <p class="text-text-primary font-medium">{{ $t('dashboard.loadFailed') }}</p>
        <p class="text-text-secondary text-sm">{{ error }}</p>
        <button @click="refreshData" class="mt-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-hover">
          {{ $t('dashboard.reload') }}
        </button>
      </div>
    </div>

    <template v-else>
      <!-- Header with Refresh Button -->
      <div class="flex justify-between items-center">
        <h2 class="text-xl sm:text-2xl font-bold text-text-primary">{{ $t('dashboard.title') }}</h2>
        <button @click="refreshData" class="flex items-center gap-2 px-3 py-2 text-sm text-primary hover:bg-primary/10 rounded-lg transition-colors">
          <span class="material-symbols-outlined text-lg">refresh</span>
          <span class="hidden sm:inline">{{ $t('dashboard.refresh') }}</span>
        </button>
      </div>

      <!-- 待审批事项卡片 - 管理层顶部 -->
      <div v-if="userStore.userRole === 'admin' || userStore.userRole === 'manager'"
           class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg border border-orange-200 shadow-card p-4 sm:p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-base sm:text-lg font-bold text-orange-900 flex items-center gap-2">
            <span class="material-symbols-outlined text-xl sm:text-2xl">pending_actions</span>
            {{ $t('dashboard.pendingItems') }}
          </h3>
          <router-link to="/approvals" class="text-orange-700 hover:text-orange-900 text-xs sm:text-sm font-medium">
            {{ $t('dashboard.viewAll') }}
          </router-link>
        </div>

        <div class="flex items-center gap-4 mb-4">
          <div class="text-center">
            <p class="text-3xl sm:text-4xl font-bold text-orange-900">{{ pendingApprovals.length }}</p>
            <p class="text-xs sm:text-sm text-orange-700 mt-1">{{ $t('dashboard.pending') }}</p>
          </div>
          <div class="flex-1 border-l border-orange-300 pl-4">
            <div v-if="pendingApprovals.length === 0" class="text-xs sm:text-sm text-orange-700">
              {{ $t('dashboard.noPendingApprovals') }}
            </div>
            <div v-else class="space-y-2">
              <div v-for="approval in pendingApprovals.slice(0, 3)" :key="approval.id"
                   @click="router.push('/approvals')"
                   class="flex items-center justify-between p-2 bg-white/60 rounded cursor-pointer hover:bg-white/80 transition-colors">
                <div class="flex-1 min-w-0">
                  <p class="text-xs sm:text-sm font-medium text-gray-900 truncate">{{ approval.title }}</p>
                  <p class="text-xs text-gray-600">{{ approval.applicant_name }} · {{ approval.created_at?.slice(0, 16) }}</p>
                </div>
                <span class="material-symbols-outlined text-orange-600 text-base sm:text-lg ml-2">chevron_right</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 团队动态概览 - 管理层 -->
      <div v-if="userStore.userRole === 'admin' || userStore.userRole === 'manager'"
           class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
        <!-- 今日考勤 -->
        <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 sm:p-5">
          <h4 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-primary">schedule</span>
            {{ $t('dashboard.todayAttendance') }}
          </h4>
          <div v-if="teamAttendance" class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs sm:text-sm text-text-secondary">{{ $t('dashboard.expectedCount') }}</span>
              <span class="text-base sm:text-lg font-bold text-text-primary">{{ teamAttendance.total_employees }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-xs sm:text-sm text-text-secondary">{{ $t('dashboard.actualCount') }}</span>
              <span class="text-base sm:text-lg font-bold text-success">{{ teamAttendance.checked_in }}</span>
            </div>
            <div class="grid grid-cols-3 gap-2 pt-2 border-t">
              <div class="text-center">
                <p class="text-xs text-text-secondary">{{ $t('dashboard.late') }}</p>
                <p class="text-sm sm:text-base font-bold text-warning">{{ teamAttendance.late_count }}</p>
              </div>
              <div class="text-center">
                <p class="text-xs text-text-secondary">{{ $t('dashboard.earlyLeave') }}</p>
                <p class="text-sm sm:text-base font-bold text-warning">{{ teamAttendance.early_leave_count }}</p>
              </div>
              <div class="text-center">
                <p class="text-xs text-text-secondary">{{ $t('dashboard.absent') }}</p>
                <p class="text-sm sm:text-base font-bold text-danger">{{ teamAttendance.absent_count }}</p>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-4 text-text-secondary text-xs sm:text-sm">{{ $t('common.loading') }}</div>
        </div>

        <!-- 今日日志 -->
        <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 sm:p-5">
          <h4 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-primary">description</span>
            {{ $t('dashboard.todayWorkLogs') }}
          </h4>
          <div v-if="teamWorkLogs" class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs sm:text-sm text-text-secondary">{{ $t('dashboard.shouldSubmitCount') }}</span>
              <span class="text-base sm:text-lg font-bold text-text-primary">{{ teamWorkLogs.total_employees }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-xs sm:text-sm text-text-secondary">{{ $t('dashboard.submittedCount') }}</span>
              <span class="text-base sm:text-lg font-bold text-success">{{ teamWorkLogs.submitted_count }}</span>
            </div>
            <div class="pt-2 border-t">
              <div class="flex items-center justify-between">
                <span class="text-xs sm:text-sm text-text-secondary">{{ $t('dashboard.notSubmittedCount') }}</span>
                <span class="text-base sm:text-lg font-bold text-danger">
                  {{ teamWorkLogs.total_employees - teamWorkLogs.submitted_count }}
                </span>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-4 text-text-secondary text-xs sm:text-sm">{{ $t('common.loading') }}</div>
        </div>
      </div>

      <!-- 我的工作面板 - 执行层顶部 -->
      <div v-if="userStore.userRole === 'operator' || userStore.userRole === 'member'"
           class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg border border-blue-200 shadow-card p-4 sm:p-5">
        <h3 class="text-base sm:text-lg font-bold text-blue-900 flex items-center gap-2 mb-4">
          <span class="material-symbols-outlined text-xl sm:text-2xl">work</span>
          {{ $t('dashboard.myWorkPanel') }}
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-4">
          <!-- 今日任务 -->
          <div class="bg-white/60 rounded-lg p-3 sm:p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs sm:text-sm text-gray-700 font-medium">{{ $t('dashboard.todayTasks') }}</span>
              <span class="material-symbols-outlined text-blue-600 text-lg sm:text-xl">task_alt</span>
            </div>
            <p class="text-2xl sm:text-3xl font-bold text-blue-900">
              {{ myCurrentTasks.filter(t => t.status !== 'completed').length }}
            </p>
            <p class="text-xs text-gray-600 mt-1">
              {{ $t('dashboard.todoOf') }} {{ myCurrentTasks.length }}
            </p>
          </div>

          <!-- 考勤状态 -->
          <div class="bg-white/60 rounded-lg p-3 sm:p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs sm:text-sm text-gray-700 font-medium">{{ $t('dashboard.attendanceCheck') }}</span>
              <span class="material-symbols-outlined text-green-600 text-lg sm:text-xl">schedule</span>
            </div>
            <div v-if="myTodayAttendance">
              <p class="text-xl sm:text-2xl font-bold text-green-600">{{ $t('dashboard.clockedIn') }}</p>
              <p class="text-xs text-gray-600 mt-1">
                {{ myTodayAttendance.clock_in?.slice(0, 5) }}
              </p>
            </div>
            <div v-else>
              <p class="text-xl sm:text-2xl font-bold text-orange-600">{{ $t('dashboard.notClockedIn') }}</p>
              <button @click="router.push('/oa/attendance')"
                      class="text-xs text-blue-600 hover:underline mt-1">
                {{ $t('dashboard.clockInNow') }}
              </button>
            </div>
          </div>

          <!-- 请假和加班 -->
          <div class="flex gap-2 mt-3 pt-3 border-t border-blue-200">
            <button @click="router.push('/oa/leave')"
                    class="flex-1 py-2 px-3 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg text-xs sm:text-sm font-medium transition-colors">
              {{ $t('dashboard.leaveRequest') }}
            </button>
            <button @click="showOvertimeModal = true"
                    class="flex-1 py-2 px-3 bg-purple-100 hover:bg-purple-200 text-purple-700 rounded-lg text-xs sm:text-sm font-medium transition-colors">
              {{ $t('dashboard.overtime') }}
            </button>
          </div>

          <!-- 日志状态 -->
          <div class="bg-white/60 rounded-lg p-3 sm:p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs sm:text-sm text-gray-700 font-medium">{{ $t('dashboard.workLog') }}</span>
              <span class="material-symbols-outlined text-purple-600 text-lg sm:text-xl">description</span>
            </div>
            <div v-if="myTodayWorkLog">
              <p class="text-xl sm:text-2xl font-bold text-green-600">{{ $t('dashboard.logSubmitted') }}</p>
              <p class="text-xs text-gray-600 mt-1">
                {{ myTodayWorkLog.created_at?.slice(11, 16) }}
              </p>
            </div>
            <div v-else>
              <p class="text-xl sm:text-2xl font-bold text-orange-600">{{ $t('dashboard.logNotSubmitted') }}</p>
              <button @click="router.push('/oa/work-logs')"
                      class="text-xs text-blue-600 hover:underline mt-1">
                {{ $t('dashboard.writeLogNow') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Stat Cards (仅管理员和经理可见) -->
      <div v-if="userStore.userRole === 'admin' || userStore.userRole === 'manager'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <StatCard :title="$t('dashboard.totalStockValue')" :value="'¥' + (stats.totalStock || 0).toLocaleString()" icon="attach_money" colorClass="blue" />
        <StatCard :title="$t('dashboard.todayInbound')" icon="input" colorClass="green">
          {{ stats.todayInbound || 0 }} <span class="text-xs sm:text-sm font-normal text-text-secondary">{{ $t('common.pieces') }}</span>
        </StatCard>
        <StatCard :title="$t('dashboard.todayOutbound')" icon="output" colorClass="orange">
          {{ stats.todayOutbound || 0 }} <span class="text-xs sm:text-sm font-normal text-text-secondary">{{ $t('common.pieces') }}</span>
        </StatCard>
        <StatCard :title="$t('dashboard.stockAlerts')" icon="warning" colorClass="red" :alert="true">
          <template #default>{{ stats.alertCount || 0 }} <span class="text-xs sm:text-sm font-normal text-text-secondary">{{ $t('common.items') }}</span>

<!-- 加班弹窗 -->
<div v-if="showOvertimeModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showOvertimeModal = false">
  <div class="w-full max-w-sm bg-white rounded-xl shadow-2xl overflow-hidden">
    <div class="p-5 border-b border-gray-100">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold text-text-primary flex items-center gap-2">
          <span class="material-symbols-outlined text-purple-600">schedule</span>
          {{ $t('dashboard.applyOvertime') }}
        </h2>
        <button @click="showOvertimeModal = false" class="text-text-secondary hover:text-text-primary">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
    </div>

    <div class="p-5 space-y-4">
      <div>
        <label class="block text-sm font-medium text-text-primary mb-2">
          {{ $t('dashboard.overtimePeriod') }}
          <span class="text-danger">*</span>
        </label>
        <input
          type="text"
          v-model="overtimeHours"
          placeholder="18:30-21:00"
          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none"
        />
        <p class="text-xs text-text-secondary mt-1">{{ $t('dashboard.overtimeFormatHint') }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-text-primary mb-2">
          {{ $t('dashboard.overtimeReason') }}
        </label>
        <textarea
          v-model="overtimeReason"
          :placeholder="$t('dashboard.overtimeReasonPlaceholder')"
          rows="2"
          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none"
        ></textarea>
      </div>
    </div>

    <div class="p-5 border-t border-gray-100 flex gap-3">
      <button
        @click="showOvertimeModal = false"
        class="flex-1 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-text-primary hover:bg-gray-50"
      >
        {{ $t('common.cancel') }}
      </button>
      <button
        @click="submitOvertime"
        class="flex-1 py-2.5 bg-purple-500 text-white rounded-lg text-sm font-bold hover:bg-purple-600"
      >
        {{ $t('common.submit') }}
      </button>
    </div>
  </div>
</div>
</template>
          <template #footer>
            <span class="text-danger font-medium">{{ $t('dashboard.needsImmediate') }}</span>
            <router-link to="/alerts" class="ml-auto text-primary hover:underline">{{ $t('dashboard.viewDetail') }}</router-link>
          

<!-- 加班弹窗 -->
<div v-if="showOvertimeModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showOvertimeModal = false">
  <div class="w-full max-w-sm bg-white rounded-xl shadow-2xl overflow-hidden">
    <div class="p-5 border-b border-gray-100">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold text-text-primary flex items-center gap-2">
          <span class="material-symbols-outlined text-purple-600">schedule</span>
          {{ $t('dashboard.applyOvertime') }}
        </h2>
        <button @click="showOvertimeModal = false" class="text-text-secondary hover:text-text-primary">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
    </div>

    <div class="p-5 space-y-4">
      <div>
        <label class="block text-sm font-medium text-text-primary mb-2">
          {{ $t('dashboard.overtimePeriod') }}
          <span class="text-danger">*</span>
        </label>
        <input
          type="text"
          v-model="overtimeHours"
          placeholder="18:30-21:00"
          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none"
        />
        <p class="text-xs text-text-secondary mt-1">{{ $t('dashboard.overtimeFormatHint') }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-text-primary mb-2">
          {{ $t('dashboard.overtimeReason') }}
        </label>
        <textarea
          v-model="overtimeReason"
          :placeholder="$t('dashboard.overtimeReasonPlaceholder')"
          rows="2"
          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none"
        ></textarea>
      </div>
    </div>

    <div class="p-5 border-t border-gray-100 flex gap-3">
      <button
        @click="showOvertimeModal = false"
        class="flex-1 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-text-primary hover:bg-gray-50"
      >
        {{ $t('common.cancel') }}
      </button>
      <button
        @click="submitOvertime"
        class="flex-1 py-2.5 bg-purple-500 text-white rounded-lg text-sm font-bold hover:bg-purple-600"
      >
        {{ $t('common.submit') }}
      </button>
    </div>
  </div>
</div>
</template>
        </StatCard>
      </div>

      <!-- Quick Actions - 执行层 -->
      <div v-if="userStore.userRole === 'operator' || userStore.userRole === 'member'" class="bg-white rounded-lg border border-gray-100 shadow-card p-4 sm:p-5">
        <div class="flex justify-between items-center mb-3 sm:mb-4">
          <h4 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-base sm:text-lg">bolt</span>
            {{ $t('dashboard.quickActions') }}
          </h4>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <button
            v-for="action in quickActions"
            :key="action.name"
            @click="handleQuickAction(action)"
            class="flex flex-col items-center gap-2 p-3 sm:p-4 rounded-lg border border-gray-200 hover:border-primary hover:bg-primary/5 transition-all group"
          >
            <span :class="['material-symbols-outlined text-2xl sm:text-3xl group-hover:scale-110 transition-transform', `text-${action.color}`]">{{ action.icon }}</span>
            <span class="text-xs sm:text-sm font-medium text-text-primary text-center">{{ action.name }}</span>
          </button>
        </div>
      </div>

      <!-- 我的职位权责 - 执行层 -->
      <div v-if="userStore.userRole === 'operator' || userStore.userRole === 'member' && myResponsibilities.length > 0" class="bg-white rounded-lg border border-gray-100 shadow-card p-4 sm:p-5">
        <div class="flex justify-between items-center mb-3 sm:mb-4">
          <h4 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-base sm:text-lg">assignment</span>
            {{ $t('dashboard.myResponsibilities') }}
          </h4>
        </div>
        <div class="space-y-3">
          <div v-for="item in myResponsibilities.slice(0, 6)" :key="item.id" class="flex items-start gap-3 p-3 rounded-lg border border-gray-100 hover:border-primary/30 hover:bg-primary/5 transition-all">
            <span :class="[
              'material-symbols-outlined text-xl shrink-0',
              item.category === 'duty' ? 'text-primary' : item.category === 'authority' ? 'text-success' : 'text-warning'
            ]">
              {{ item.category === 'duty' ? 'task' : item.category === 'authority' ? 'verified_user' : 'analytics' }}
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-sm font-medium text-text-primary">{{ item.title }}</span>
                <span :class="[
                  'px-2 py-0.5 rounded text-xs',
                  item.category === 'duty' ? 'bg-primary/10 text-primary' :
                  item.category === 'authority' ? 'bg-success/10 text-success' :
                  'bg-warning/10 text-warning'
                ]">
                  {{ item.category === 'duty' ? $t('dashboard.duty') : item.category === 'authority' ? $t('dashboard.authority') : 'KPI' }}
                </span>
              </div>
              <p v-if="item.description" class="text-xs text-text-secondary line-clamp-2">{{ item.description }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Today's Data Overview (仅管理员和经理可见) -->
      <div v-if="userStore.userRole === 'admin' || userStore.userRole === 'manager'" class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg border border-blue-200 p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs sm:text-sm text-blue-700 font-medium">{{ $t('dashboard.todaySales') }}</span>
            <span class="material-symbols-outlined text-blue-600 text-xl">payments</span>
          </div>
          <p class="text-xl sm:text-2xl font-bold text-blue-900">¥{{ (todayData.todaySales || 0).toLocaleString() }}</p>
        </div>
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg border border-green-200 p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs sm:text-sm text-green-700 font-medium">{{ $t('dashboard.todayInbound') }}</span>
            <span class="material-symbols-outlined text-green-600 text-xl">input</span>
          </div>
          <p class="text-xl sm:text-2xl font-bold text-green-900">{{ (todayData.todayInbound || 0).toLocaleString() }}</p>
        </div>
        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg border border-orange-200 p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs sm:text-sm text-orange-700 font-medium">{{ $t('dashboard.todayOutbound') }}</span>
            <span class="material-symbols-outlined text-orange-600 text-xl">output</span>
          </div>
          <p class="text-xl sm:text-2xl font-bold text-orange-900">{{ (todayData.todayOutbound || 0).toLocaleString() }}</p>
        </div>
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg border border-purple-200 p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs sm:text-sm text-purple-700 font-medium">{{ $t('dashboard.pendingApprovals') }}</span>
            <span class="material-symbols-outlined text-purple-600 text-xl">pending_actions</span>
          </div>
          <p class="text-xl sm:text-2xl font-bold text-purple-900">{{ (todayData.pendingApprovals || 0).toLocaleString() }}</p>
        </div>
      </div>

      <!-- Quick Actions - 管理层 -->
      <div v-if="userStore.userRole === 'admin' || userStore.userRole === 'manager'" class="bg-white rounded-lg border border-gray-100 shadow-card p-4 sm:p-5">
        <div class="flex justify-between items-center mb-3 sm:mb-4">
          <h4 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-base sm:text-lg">bolt</span>
            {{ $t('dashboard.quickActions') }}
          </h4>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          <button
            v-for="action in quickActions"
            :key="action.name"
            @click="handleQuickAction(action)"
            class="flex flex-col items-center gap-2 p-3 sm:p-4 rounded-lg border border-gray-200 hover:border-primary hover:bg-primary/5 transition-all group"
          >
            <span :class="['material-symbols-outlined text-2xl sm:text-3xl group-hover:scale-110 transition-transform', `text-${action.color}`]">{{ action.icon }}</span>
            <span class="text-xs sm:text-sm font-medium text-text-primary text-center">{{ action.name }}</span>
          </button>
        </div>
      </div>

      <!-- Charts Row (仅管理员和经理可见) -->
      <div v-if="userStore.userRole === 'admin' || userStore.userRole === 'manager'" class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">
        <!-- Warehouse Distribution -->
        <div class="lg:col-span-7 bg-white rounded-lg border border-gray-100 shadow-card p-4 sm:p-5 flex flex-col">
          <div class="flex justify-between items-center mb-3 sm:mb-4">
            <h4 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-base sm:text-lg">warehouse</span>
              {{ $t('dashboard.warehouseDistribution') }}
            </h4>
            <div class="text-xs text-text-secondary border rounded px-2 py-1 cursor-pointer hover:bg-gray-50">{{ $t('dashboard.thisWeek') }}</div>
          </div>
          <div v-if="warehouses.length === 0" class="flex-1 flex items-center justify-center text-text-secondary text-sm">
            {{ $t('common.noData') }}
          </div>
          <div v-else class="flex-1 flex flex-col justify-center gap-4 sm:gap-6">
            <div v-for="(wh, index) in warehouses.slice(0, 3)" :key="wh.id" class="flex flex-col gap-1">
              <div class="flex justify-between text-xs sm:text-sm mb-1">
                <span class="font-medium text-text-regular truncate mr-2">{{ wh.name }}</span>
                <span class="font-bold text-text-primary shrink-0">{{ (wh.totalQty || 0).toLocaleString() }} {{ $t('common.pieces') }}</span>
              </div>
              <div class="w-full bg-gray-100 rounded-full h-2 sm:h-2.5 overflow-hidden">
                <div
                  :class="['h-2 sm:h-2.5 rounded-full', index === 0 ? 'bg-primary' : index === 1 ? 'bg-success' : 'bg-warning']"
                  :style="{ width: Math.min(100, (wh.totalQty || 0) / Math.max(...warehouses.map(w => w.totalQty || 0)) * 100) + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </div>
        <!-- Alert Table -->
        <div class="lg:col-span-5 bg-white rounded-lg border border-gray-100 shadow-card flex flex-col overflow-hidden">
          <div class="p-3 sm:p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h4 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2">
              <span class="material-symbols-outlined text-danger text-base sm:text-lg">priority_high</span>
              {{ $t('dashboard.stockAlertItems') }}
            </h4>
            <router-link to="/alerts" class="text-primary text-xs hover:underline">{{ $t('common.all') }}</router-link>
          </div>
          <div class="flex-1 overflow-auto custom-scrollbar">
            <div v-if="pendingAlerts.length === 0" class="flex items-center justify-center py-8 text-text-secondary text-sm">
              {{ $t('common.noData') }}
            </div>
            <table v-else class="w-full text-left text-xs sm:text-sm">
              <thead class="bg-gray-50 text-text-secondary text-xs uppercase sticky top-0">
                <tr>
                  <th class="px-2 sm:px-4 py-2 sm:py-3 font-medium">SKU</th>
                  <th class="px-2 sm:px-4 py-2 sm:py-3 font-medium">{{ $t('common.name') }}</th>
                  <th class="px-2 sm:px-4 py-2 sm:py-3 font-medium text-center">{{ $t('product.stock') }}</th>
                  <th class="px-2 sm:px-4 py-2 sm:py-3 font-medium text-right">{{ $t('common.action') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="alert in pendingAlerts.slice(0, 5)" :key="alert.id" class="hover:bg-gray-50">
                  <td class="px-2 sm:px-4 py-2 sm:py-3 font-mono text-xs text-text-secondary">{{ alert.sku || '-' }}</td>
                  <td class="px-2 sm:px-4 py-2 sm:py-3 text-text-primary font-medium truncate max-w-[80px] sm:max-w-[100px]">{{ alert.product_name || '-' }}</td>
                  <td class="px-2 sm:px-4 py-2 sm:py-3 text-center">
                    <StatusTag :type="alert.level === 'critical' ? 'danger' : 'warning'" :text="String(alert.current_stock)" />
                  </td>
                  <td class="px-2 sm:px-4 py-2 sm:py-3 text-right">
                    <button @click="handleReplenish(alert)" class="text-primary hover:text-blue-700 text-xs font-medium">{{ $t('dashboard.replenish') }}</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Trend Chart + Pending Approvals (仅管理员和经理可见) -->
      <div v-if="userStore.userRole === 'admin' || userStore.userRole === 'manager'" class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">
        <!-- Trend Chart -->
        <div class="lg:col-span-8 bg-white rounded-lg border border-gray-100 shadow-card p-4 sm:p-5">
          <div class="flex justify-between items-center mb-3 sm:mb-4">
            <h4 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-base sm:text-lg">trending_up</span>
              {{ $t('dashboard.inoutTrend') }}
            </h4>
          </div>
          <VChart v-if="trendOption.series" :option="trendOption" :style="{ height: isMobile ? '220px' : '280px' }" autoresize />
          <div v-else class="flex items-center justify-center h-64 text-text-secondary text-sm">
            {{ $t('common.noData') }}
          </div>
        </div>
        <!-- Pending Approvals -->
        <div class="lg:col-span-4 bg-white rounded-lg border border-gray-100 shadow-card flex flex-col overflow-hidden">
          <div class="p-3 sm:p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h4 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2">
              <span class="material-symbols-outlined text-warning text-base sm:text-lg">pending_actions</span>
              {{ $t('dashboard.pendingApprovals') }}
            </h4>
            <router-link to="/approvals" class="text-primary text-xs hover:underline">{{ $t('common.all') }}</router-link>
          </div>
          <div class="flex-1 overflow-auto custom-scrollbar p-3 sm:p-4 space-y-2 sm:space-y-3">
            <div v-if="pendingApprovals.length === 0" class="text-center text-text-secondary text-xs sm:text-sm py-6 sm:py-8">{{ $t('dashboard.noApprovals') }}</div>
            <div
              v-for="ap in pendingApprovals"
              :key="ap.id"
              @click="handleApprovalClick(ap)"
              class="border border-gray-100 rounded-lg p-2.5 sm:p-3 hover:bg-gray-50 cursor-pointer transition-colors"
            >
              <div class="flex items-start justify-between mb-1.5 sm:mb-2">
                <div class="flex items-center gap-2">
                  <span class="material-symbols-outlined text-warning text-base">description</span>
                  <span class="text-xs sm:text-sm font-medium text-text-primary line-clamp-1">{{ ap.title }}</span>
                </div>
              </div>
              <div class="flex items-center justify-between text-xs text-text-secondary">
                <span class="truncate mr-2">{{ ap.applicant }} · {{ ap.department }}</span>
                <StatusTag :type="ap.urgency === 'high' ? 'danger' : ap.urgency === 'medium' ? 'warning' : 'info'" :text="ap.urgency === 'high' ? t('dashboard.urgent') : ap.urgency === 'medium' ? t('dashboard.medium') : t('dashboard.low')" />
              </div>
              <div class="text-xs text-text-secondary mt-1">{{ ap.created_at }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- AI Status (仅管理员可见) -->
      <div v-if="userStore.userRole === 'admin'" class="bg-white rounded-lg border border-gray-100 shadow-card p-4 sm:p-5">
        <div class="flex justify-between items-center mb-3 sm:mb-4">
          <h4 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-base sm:text-lg">smart_toy</span>
            {{ $t('dashboard.aiStatus') }}
          </h4>
          <router-link to="/ai-automation" class="text-primary text-xs hover:underline">{{ $t('dashboard.viewDetail') }}</router-link>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
          <div v-for="task in aiTasks" :key="task.name" class="flex items-center gap-2 sm:gap-3 p-2.5 sm:p-3 bg-gray-50 rounded-lg">
            <span :class="['material-symbols-outlined text-[20px] sm:text-[24px]', task.color]">{{ task.icon }}</span>
            <div class="min-w-0 flex-1">
              <p class="text-xs sm:text-sm font-medium text-text-primary truncate">{{ task.name }}</p>
              <p class="text-xs text-text-secondary">{{ task.status === 'running' ? $t('dashboard.running') : task.status === 'error' ? $t('dashboard.error') : $t('dashboard.idle') }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 我当前的任务 - 所有角色 -->
      <div v-if="myCurrentTasks.length > 0" class="bg-white rounded-lg border border-gray-100 shadow-card p-4 sm:p-5">
        <div class="flex justify-between items-center mb-3 sm:mb-4">
          <h4 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-base sm:text-lg">task_alt</span>
            {{ $t('dashboard.myCurrentTasks') }}
          </h4>
          <router-link to="/tasks" class="text-primary text-xs hover:underline">{{ $t('dashboard.viewAllTasks') }}</router-link>
        </div>
        <div class="space-y-3">
          <div v-for="task in myCurrentTasks" :key="task.id" class="flex items-start gap-3 p-3 rounded-lg border border-gray-100 hover:border-primary/30 hover:bg-primary/5 transition-all cursor-pointer" @click="router.push('/tasks')">
            <span :class="[
              'material-symbols-outlined text-xl shrink-0',
              task.status === 'pending' ? 'text-info' : 'text-warning'
            ]">
              {{ task.status === 'pending' ? 'pending' : 'autorenew' }}
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-sm font-medium text-text-primary line-clamp-1">{{ task.title }}</span>
                <span :class="[
                  'px-2 py-0.5 rounded text-xs shrink-0',
                  task.priority === 'urgent' ? 'bg-danger/10 text-danger' :
                  task.priority === 'high' ? 'bg-danger/10 text-danger' :
                  task.priority === 'medium' ? 'bg-warning/10 text-warning' :
                  'bg-info/10 text-info'
                ]">
                  {{ task.priority === 'urgent' ? $t('dashboard.urgent') : task.priority === 'high' ? $t('dashboard.high') : task.priority === 'medium' ? $t('dashboard.medium') : $t('dashboard.low') }}
                </span>
              </div>
              <p v-if="task.description" class="text-xs text-text-secondary line-clamp-1 mb-1">{{ task.description }}</p>
              <p class="text-xs text-text-secondary">{{ $t('dashboard.dueDate') }}: {{ task.due_date ? new Date(task.due_date).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }) : '-' }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 我的职位权责 - 所有角色 -->
      <div v-if="myResponsibilities.length > 0" class="bg-white rounded-lg border border-gray-100 shadow-card p-4 sm:p-5">
        <div class="flex justify-between items-center mb-3 sm:mb-4">
          <h4 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-base sm:text-lg">assignment</span>
            {{ $t('dashboard.myResponsibilities') }}
          </h4>
        </div>
        <div class="space-y-3">
          <div v-for="item in myResponsibilities.slice(0, 6)" :key="item.id" class="flex items-start gap-3 p-3 rounded-lg border border-gray-100 hover:border-primary/30 hover:bg-primary/5 transition-all">
            <span :class="[
              'material-symbols-outlined text-xl shrink-0',
              item.category === 'duty' ? 'text-primary' : item.category === 'authority' ? 'text-success' : 'text-warning'
            ]">
              {{ item.category === 'duty' ? 'task' : item.category === 'authority' ? 'verified_user' : 'analytics' }}
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-sm font-medium text-text-primary">{{ item.title }}</span>
                <span :class="[
                  'px-2 py-0.5 rounded text-xs',
                  item.category === 'duty' ? 'bg-primary/10 text-primary' :
                  item.category === 'authority' ? 'bg-success/10 text-success' :
                  'bg-warning/10 text-warning'
                ]">
                  {{ item.category === 'duty' ? $t('dashboard.duty') : item.category === 'authority' ? $t('dashboard.authority') : 'KPI' }}
                </span>
              </div>
              <p v-if="item.description" class="text-xs text-text-secondary line-clamp-2">{{ item.description }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- AI 任务建议 - 所有角色底部 -->
      <div v-if="false" class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg border border-purple-200 shadow-card p-4 sm:p-5">
        <div class="flex items-center justify-between mb-3 sm:mb-4">
          <h4 class="text-sm sm:text-base font-bold text-purple-900 flex items-center gap-2">
            <span class="material-symbols-outlined text-purple-700 text-base sm:text-lg">lightbulb</span>
            {{ $t('dashboard.aiSuggestion') }}
          </h4>
        </div>
        <div class="space-y-2">
          <div class="bg-white/60 rounded-lg p-3 flex items-start gap-3">
            <span class="material-symbols-outlined text-purple-600 text-lg">auto_awesome</span>
            <div class="flex-1">
              <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $t('dashboard.suggestRestock') }}</p>
              <p class="text-xs text-gray-600 mt-1">{{ $t('dashboard.suggestRestockDesc') }}</p>
            </div>
          </div>
        </div>
      </div>
    

<!-- 加班弹窗 -->
<div v-if="showOvertimeModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showOvertimeModal = false">
  <div class="w-full max-w-sm bg-white rounded-xl shadow-2xl overflow-hidden">
    <div class="p-5 border-b border-gray-100">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold text-text-primary flex items-center gap-2">
          <span class="material-symbols-outlined text-purple-600">schedule</span>
          {{ $t('dashboard.applyOvertime') }}
        </h2>
        <button @click="showOvertimeModal = false" class="text-text-secondary hover:text-text-primary">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
    </div>

    <div class="p-5 space-y-4">
      <div>
        <label class="block text-sm font-medium text-text-primary mb-2">
          {{ $t('dashboard.overtimePeriod') }}
          <span class="text-danger">*</span>
        </label>
        <input
          type="text"
          v-model="overtimeHours"
          placeholder="18:30-21:00"
          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none"
        />
        <p class="text-xs text-text-secondary mt-1">{{ $t('dashboard.overtimeFormatHint') }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-text-primary mb-2">
          {{ $t('dashboard.overtimeReason') }}
        </label>
        <textarea
          v-model="overtimeReason"
          :placeholder="$t('dashboard.overtimeReasonPlaceholder')"
          rows="2"
          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none"
        ></textarea>
      </div>
    </div>

    <div class="p-5 border-t border-gray-100 flex gap-3">
      <button
        @click="showOvertimeModal = false"
        class="flex-1 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-text-primary hover:bg-gray-50"
      >
        {{ $t('common.cancel') }}
      </button>
      <button
        @click="submitOvertime"
        class="flex-1 py-2.5 bg-purple-500 text-white rounded-lg text-sm font-bold hover:bg-purple-600"
      >
        {{ $t('common.submit') }}
      </button>
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
            {{ $t('dashboard.scanOperation') }}
          </h2>
          <button @click="closeScanModal" class="text-text-secondary hover:text-text-primary">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
      </div>

      <div class="p-5 space-y-4 overflow-y-auto">
        <!-- 输入方式（未扫到商品时显示） -->
        <div v-if="!scannedQrcode">
            <!-- 切换按钮：手动输入 / 摄像头扫码 / 从相册选择 -->
          <div class="flex gap-2 mb-3">
            <button
              @click="scanMode = 'manual'; stopCameraScanner()"
              :class="[
                'flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg border text-sm font-medium transition-all',
                scanMode === 'manual' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-text-secondary hover:border-gray-300'
              ]"
            >
              <span class="material-symbols-outlined text-lg">keyboard</span>
              {{ $t('dashboard.manualInput') }}
            </button>
            <button
              @click="startCameraScanner()"
              :class="[
                'flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg border text-sm font-medium transition-all',
                scanMode === 'camera' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-text-secondary hover:border-gray-300'
              ]"
            >
              <span class="material-symbols-outlined text-lg">photo_camera</span>
              {{ $t('dashboard.cameraScan') }}
            </button>
            <button
              @click="startImageScanner()"
              :class="[
                'flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg border text-sm font-medium transition-all',
                scanMode === 'image' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-text-secondary hover:border-gray-300'
              ]"
            >
              <span class="material-symbols-outlined text-lg">photo_library</span>
              {{ $t('dashboard.albumScan') }}
            </button>
          </div>

          <!-- 隐藏的图片文件输入 -->
          <input
            id="qr-image-input"
            type="file"
            accept="image/*"
            class="hidden"
            @change="handleImageSelect"
          />

          <!-- 手动输入模式 -->
          <div v-if="scanMode === 'manual'">
            <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('dashboard.enterQrCode') }}</label>
            <div class="flex gap-2">
              <input
                type="text"
                v-model="manualQrCode"
                :placeholder="$t('dashboard.enterQrCodePlaceholder')"
                class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                @keyup.enter="handleManualInput(manualQrCode)"
              />
              <button
                @click="handleManualInput(manualQrCode)"
                class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-hover transition-colors"
              >
                {{ $t('dashboard.query') }}
              </button>
            </div>
          </div>

          <!-- 摄像头扫码模式 -->
          <div v-if="scanMode === 'camera'">
            <div id="qr-reader" class="rounded-lg overflow-hidden border border-gray-200"></div>
            <p v-if="scanning" class="text-xs text-text-secondary text-center mt-2 flex items-center justify-center gap-1">
              <span class="animate-pulse inline-block w-2 h-2 bg-success rounded-full"></span>
              {{ $t('dashboard.cameraActive') }}
            </p>
          </div>

          <!-- 从相册选择图片模式 -->
          <div v-if="scanMode === 'image'" class="text-center py-8">
            <div v-if="imageScanning" class="flex flex-col items-center gap-3">
              <div class="w-10 h-10 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
              <p class="text-sm text-text-secondary">{{ $t('dashboard.scanning') }}</p>
            </div>
            <div v-else class="flex flex-col items-center gap-3">
              <span class="material-symbols-outlined text-5xl text-text-secondary">photo_library</span>
              <p class="text-sm text-text-secondary">{{ $t('dashboard.albumScanTip') }}</p>
              <button
                @click="startImageScanner()"
                class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-hover transition-colors"
              >
                {{ $t('dashboard.selectImage') }}
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
                <p class="text-xs text-text-secondary">{{ $t('dashboard.qrCode') }}: {{ scannedQrcode.qr_code }}</p>
              </div>
            </div>
          </div>

          <!-- 操作类型切换：销售 / 赠送 -->
          <div>
            <label class="block text-sm font-medium text-text-primary mb-2">{{ $t('dashboard.operationType') }}</label>
            <div class="grid grid-cols-2 gap-2">
              <button
                @click="saleType = 'sale'; saleError = ''"
                :class="[
                  'flex items-center justify-center gap-2 py-2.5 rounded-lg border-2 text-sm font-medium transition-all',
                  saleType === 'sale'
                    ? 'border-primary bg-primary/5 text-primary'
                    : 'border-gray-200 text-text-secondary hover:border-gray-300'
                ]"
              >
                <span class="material-symbols-outlined text-lg">sell</span>
                {{ $t('dashboard.sale') }}
              </button>
              <button
                @click="loadGiftData()"
                :class="[
                  'flex items-center justify-center gap-2 py-2.5 rounded-lg border-2 text-sm font-medium transition-all',
                  saleType === 'gift'
                    ? 'border-orange-500 bg-orange-50 text-orange-600'
                    : 'border-gray-200 text-text-secondary hover:border-gray-300'
                ]"
              >
                <span class="material-symbols-outlined text-lg">card_giftcard</span>
                {{ $t('dashboard.gift') }}
              </button>
            </div>
          </div>

          <!-- 售价（仅销售时显示） -->
          <div v-if="saleType === 'sale'">
            <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('dashboard.salePrice') }} <span class="text-danger">*</span></label>
            <input
              type="number"
              v-model="salePrice"
              placeholder="0"
              :min="scannedProduct.purchase_price || 0"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            />
            <p v-if="scannedProduct.purchase_price" class="text-xs text-text-secondary mt-1">
              {{ $t('dashboard.costPrice') }}: ¥{{ scannedProduct.purchase_price }}，{{ $t('dashboard.suggestedPrice') }}: ¥{{ scannedProduct.sale_price }}
            </p>
          </div>

          <!-- 赠送提示 -->
          <div v-if="saleType === 'gift' && !canGift" class="bg-red-50 border border-red-200 rounded-lg p-3">
            <p class="text-xs text-red-700 flex items-center gap-1">
              <span class="material-symbols-outlined text-sm">block</span>
              {{ $t('dashboard.giftBlocked', { count: rejectedCount }) }}
            </p>
          </div>
          <div v-if="saleType === 'gift' && canGift" class="bg-orange-50 border border-orange-200 rounded-lg p-3">
            <p class="text-xs text-orange-700 flex items-center gap-1">
              <span class="material-symbols-outlined text-sm">info</span>
              {{ $t('dashboard.giftNote') }}
            </p>
          </div>

          <!-- 审批人选择（仅赠送时） -->
          <div v-if="saleType === 'gift' && canGift">
            <label class="block text-sm font-medium text-text-primary mb-1">
              {{ $t('dashboard.approver') }} <span class="text-danger">*</span>
            </label>
            <select
              v-model="selectedApprover"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            >
              <option value="" disabled>{{ giftCheckLoading ? $t('dashboard.loadingApprovers') : $t('dashboard.selectApprover') }}</option>
              <option v-for="a in approverList" :key="a.id" :value="a.id">
                {{ a.name }} ({{ a.role }}{{ a.department ? ' - ' + a.department : '' }})
              </option>
            </select>
          </div>

          <!-- 客户姓名 -->
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">
              {{ $t('dashboard.customerName') }}
              <span v-if="saleType === 'gift'" class="text-danger">*</span>
              <span v-else class="text-text-secondary text-xs font-normal">{{ $t('dashboard.optional') }}</span>
            </label>
            <input
              type="text"
              v-model="buyerName"
              :placeholder="$t('dashboard.customerName')"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            />
          </div>

          <!-- 客户电话 -->
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">
              {{ $t('dashboard.customerPhone') }}
              <span v-if="saleType === 'gift'" class="text-danger">*</span>
              <span v-else class="text-text-secondary text-xs font-normal">{{ $t('dashboard.optional') }}</span>
            </label>
            <input
              type="tel"
              v-model="buyerPhone"
              :placeholder="$t('dashboard.customerPhone')"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            />
          </div>

          <!-- 备注 -->
          <div>
            <label class="block text-sm font-medium text-text-primary mb-1">
              {{ $t('dashboard.saleNote') }}
              <span class="text-text-secondary text-xs font-normal">{{ $t('dashboard.optional') }}</span>
            </label>
            <textarea
              v-model="saleNote"
              :placeholder="$t('dashboard.saleNotePlaceholder')"
              rows="2"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            ></textarea>
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
          <button
            v-if="scannedQrcode"
            @click="openScanModal"
            class="flex-1 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-text-primary hover:bg-gray-50"
          >
            {{ $t('common.rescan') }}
          </button>
          <button
            @click="closeScanModal"
            class="flex-1 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-text-primary hover:bg-gray-50"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            v-if="scannedProduct && saleType === 'sale'"
            @click="confirmSale"
            :disabled="saleLoading || !salePrice || salePrice <= 0"
            class="flex-1 py-2.5 bg-primary text-white rounded-lg text-sm font-bold hover:bg-primary-hover disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ saleLoading ? $t('dashboard.processing') : $t('dashboard.confirmSale') }}
          </button>
          <button
            v-if="scannedProduct && saleType === 'gift'"
            @click="confirmSale"
            :disabled="saleLoading || !canGift || !selectedApprover"
            class="flex-1 py-2.5 bg-orange-500 text-white rounded-lg text-sm font-bold hover:bg-orange-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ saleLoading ? $t('dashboard.processing') : $t('dashboard.confirmGift') }}
          </button>
        </div>
      </div>
    </div>
  </div>



<!-- 加班弹窗 -->
<div v-if="showOvertimeModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showOvertimeModal = false">
  <div class="w-full max-w-sm bg-white rounded-xl shadow-2xl overflow-hidden">
    <div class="p-5 border-b border-gray-100">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold text-text-primary flex items-center gap-2">
          <span class="material-symbols-outlined text-purple-600">schedule</span>
          {{ $t('dashboard.applyOvertime') }}
        </h2>
        <button @click="showOvertimeModal = false" class="text-text-secondary hover:text-text-primary">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
    </div>

    <div class="p-5 space-y-4">
      <div>
        <label class="block text-sm font-medium text-text-primary mb-2">
          {{ $t('dashboard.overtimePeriod') }}
          <span class="text-danger">*</span>
        </label>
        <input
          type="text"
          v-model="overtimeHours"
          placeholder="18:30-21:00"
          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none"
        />
        <p class="text-xs text-text-secondary mt-1">{{ $t('dashboard.overtimeFormatHint') }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-text-primary mb-2">
          {{ $t('dashboard.overtimeReason') }}
        </label>
        <textarea
          v-model="overtimeReason"
          :placeholder="$t('dashboard.overtimeReasonPlaceholder')"
          rows="2"
          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none"
        ></textarea>
      </div>
    </div>

    <div class="p-5 border-t border-gray-100 flex gap-3">
      <button
        @click="showOvertimeModal = false"
        class="flex-1 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-text-primary hover:bg-gray-50"
      >
        {{ $t('common.cancel') }}
      </button>
      <button
        @click="submitOvertime"
        class="flex-1 py-2.5 bg-purple-500 text-white rounded-lg text-sm font-bold hover:bg-purple-600"
      >
        {{ $t('common.submit') }}
      </button>
    </div>
  </div>
</div>
</template>