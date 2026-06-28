<template>
  <div class="p-4 max-w-7xl mx-auto">
    <!-- 操作栏（打印时隐藏） -->
    <div class="no-print mb-4 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold">📦 箱唛打印（A4 × 2）</h1>
        <p class="text-sm text-gray-500 mt-1">
          DRF #{{ drfId }} · {{ drf?.product_name }} · {{ cartons.length }} 箱
        </p>
      </div>
      <div class="flex gap-2">
        <el-button @click="$router.back()">返回</el-button>
        <el-button type="primary" @click="window.print()">🖨️ 打印</el-button>
      </div>
    </div>

    <!-- 加载中 -->
    <div v-if="loading" class="no-print text-center py-12">
      <el-icon class="is-loading"><Loading /></el-icon>
      <span class="ml-2">加载箱唛数据…</span>
    </div>

    <!-- A4 排版容器：横版 A4，2 列 -->
    <div v-else-if="cartons.length" class="print-area">
      <!-- 每对箱唛占 1 张 A4，2 列并排 -->
      <div
        v-for="(pair, idx) in cartonPairs"
        :key="idx"
        class="a4-page"
      >
        <div
          v-for="(carton, i) in pair"
          :key="carton.id"
          class="carton-label"
        >
          <div class="label-header">
            <div class="text-[10px] text-gray-500">STORE SUPPLIER - Dept Store</div>
          </div>
          <div class="vendor-line">
            <span class="font-bold">{{ drf.vendor_code || drf.vendor_name }}</span>
            <span class="ml-1">{{ drf.vendor_name || drf.supplier_name }}</span>
          </div>
          <div class="store-line">
            <span class="font-bold">{{ drf.store_code }}</span>
            <span class="ml-1">- {{ drf.store_name }}</span>
          </div>

          <div class="barcode-wrap">
            <canvas :ref="el => qrCanvases[carton.id] = el" class="qr-canvas"></canvas>
          </div>

          <div class="info-block">
            <div><span class="label">DR NO.</span> <span class="value">{{ carton.box_seq === 1 && cartons.length === 1 ? drf.id : (drf.dr_no || drf.id.toString().padStart(6, '0')) }}</span></div>
            <div><span class="label">DEPT/SUB DEPT/CLASS CODE:</span> <span class="value">{{ drf.dept_code || '047' }}-{{ drf.sub_dept_code || '074' }}-{{ drf.class_code || '103' }}</span></div>
            <div><span class="label">CLASS NAME:</span> <span class="value">{{ drf.class_name || 'VOYAGER' }}</span></div>
            <div><span class="label">BOX/BUNDLE NO.:</span> <span class="value">{{ carton.box_seq }} OF {{ carton.total_boxes }}</span></div>
            <div><span class="label">RDD:</span> <span class="value">{{ formatRdd(drf.expected_date) }}</span></div>
            <div><span class="label">ASN No.:</span> <span class="value">{{ drf.asn_no || '—' }}</span></div>
          </div>

          <div class="barcode-text">{{ carton.carton_barcode }}</div>
        </div>
      </div>
    </div>

    <div v-else class="no-print text-center py-12 text-gray-500">
      <p>该 DRF 还没有箱唛</p>
      <el-button class="mt-4" type="primary" @click="$router.back()">返回生成箱唛</el-button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Loading } from '@element-plus/icons-vue'
import QRCode from 'qrcode'
import api from '../../services/api.js'

const route = useRoute()
const drfId = computed(() => route.params.drf_id)
const drf = ref({})
const cartons = ref([])
const loading = ref(true)
const qrCanvases = ref({})

const cartonPairs = computed(() => {
  const pairs = []
  for (let i = 0; i < cartons.value.length; i += 2) {
    pairs.push([cartons.value[i], cartons.value[i + 1]].filter(Boolean))
  }
  return pairs
})

function formatRdd(d) {
  if (!d) return 'Jun 17, 2026 09:00 am'
  const dt = new Date(d)
  if (isNaN(dt.getTime())) return d
  return dt.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) +
    ' ' + dt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })
}

async function loadDrf() {
  loading.value = true
  try {
    // 1. 拿 DRF 详情（通过 asn/:id 反查 DRF）
    const listRes = await api.get('/preorder/drf/list')
    const drfRow = (listRes.data?.data || []).find(r => String(r.id) === String(drfId.value))
    if (!drfRow) throw new Error('DRF 不存在')
    drf.value = drfRow

    // 2. 拿箱唛
    const cartonRes = await api.get(`/preorder/cartons/list?drf_id=${drfId.value}`)
    cartons.value = cartonRes.data?.data || []

    // 3. 渲染 QR
    await nextTick()
    await renderQRCodes()
  } catch (e) {
    ElMessage.error('加载失败: ' + (e.response?.data?.message || e.message))
  } finally {
    loading.value = false
  }
}

async function renderQRCodes() {
  for (const carton of cartons.value) {
    const canvas = qrCanvases.value[carton.id]
    if (canvas) {
      try {
        await QRCode.toCanvas(canvas, carton.carton_barcode, {
          width: 120,
          margin: 1,
          errorCorrectionLevel: 'M'
        })
      } catch (e) {
        console.error('QR render fail:', e)
      }
    }
  }
}

onMounted(loadDrf)
watch(drfId, loadDrf)
</script>

<style scoped>
.print-area {
  background: #f0f0f0;
  padding: 12px;
}

/* A4 横版：297mm × 210mm */
.a4-page {
  width: 297mm;
  height: 210mm;
  background: white;
  margin: 0 auto 12px auto;
  padding: 6mm;
  display: flex;
  gap: 4mm;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  page-break-after: always;
}

.carton-label {
  flex: 1;
  border: 2px solid #000;
  padding: 6mm;
  display: flex;
  flex-direction: column;
  font-size: 11px;
  line-height: 1.4;
}

.label-header {
  border-bottom: 1px solid #ccc;
  padding-bottom: 2mm;
  margin-bottom: 3mm;
}

.vendor-line,
.store-line {
  margin-bottom: 2mm;
  font-size: 13px;
}

.barcode-wrap {
  display: flex;
  justify-content: center;
  margin: 3mm 0;
}

.qr-canvas {
  border: 1px solid #ddd;
}

.info-block {
  flex: 1;
  font-size: 11px;
  line-height: 1.7;
}

.info-block .label {
  display: inline-block;
  min-width: 90px;
  color: #555;
}

.info-block .value {
  font-weight: bold;
  font-family: 'Courier New', monospace;
}

.barcode-text {
  font-family: 'Courier New', monospace;
  font-size: 10px;
  text-align: center;
  border-top: 1px dashed #999;
  padding-top: 2mm;
  margin-top: 2mm;
  letter-spacing: 1px;
}

@media print {
  @page {
    size: A4 landscape;
    margin: 0;
  }
  body {
    margin: 0;
    background: white !important;
  }
  .no-print {
    display: none !important;
  }
  .print-area {
    background: white !important;
    padding: 0 !important;
  }
  .a4-page {
    margin: 0 !important;
    box-shadow: none !important;
    page-break-after: always;
  }
}
</style>
