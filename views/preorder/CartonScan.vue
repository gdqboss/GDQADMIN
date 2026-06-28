<template>
  <div class="p-6 max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-4">📷 扫码收货（仓管）</h1>
    <p class="text-sm text-gray-500 mb-6">
      扫箱唛 BDSP 条码 → 自动识别 DRF → 累加件数 → 满 actual_qty 自动推 warehouse_confirmed
    </p>

    <el-card class="mb-4">
      <el-form @submit.prevent="doScan">
        <el-form-item label="BDSP 条码">
          <el-input
            v-model="barcode"
            ref="inputRef"
            placeholder="扫码枪扫描或手动输入 BDSP 编码"
            size="large"
            autofocus
            @keyup.enter="doScan"
          >
            <template #append>
              <el-button type="primary" @click="doScan" :loading="scanning">扫描</el-button>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item>
          <el-checkbox v-model="damaged">本箱有破损（不算入 actual_qty）</el-checkbox>
          <el-button class="ml-4" text @click="clearHistory">清空记录</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 最近扫描 -->
    <el-card v-if="history.length">
      <h3 class="text-lg font-semibold mb-3">本会话扫描记录（{{ history.length }} 箱 / 累加 {{ totalScanned }} 件）</h3>
      <el-table :data="history" border stripe max-height="500">
        <el-table-column prop="scanned_at" label="时间" width="100" />
        <el-table-column prop="carton_barcode" label="箱唛条码" min-width="280">
          <template #default="{ row }">
            <span class="font-mono text-xs">{{ row.carton_barcode }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="drf_id" label="DRF" width="80" align="center" />
        <el-table-column prop="box_seq" label="箱号" width="80" align="center">
          <template #default="{ row }">{{ row.box_seq }} / {{ row.total_boxes }}</template>
        </el-table-column>
        <el-table-column prop="qty_in_box" label="件数" width="80" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.new_carton_status === 'received' ? 'success' : 'danger'" size="small">
              {{ row.new_carton_status === 'received' ? '已收' : '破损' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="DRF 推进" width="120" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.drf_status_advanced" type="success" size="small">✓ 已推</el-tag>
            <span v-else class="text-gray-400">—</span>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-empty v-else description="尚无扫描记录" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../services/api.js'

const barcode = ref('')
const damaged = ref(false)
const scanning = ref(false)
const history = ref([])
const inputRef = ref(null)

const totalScanned = computed(() =>
  history.value.filter(r => r.new_carton_status === 'received').reduce((s, r) => s + (r.qty_in_box || 0), 0)
)

async function doScan() {
  if (!barcode.value.trim()) return
  scanning.value = true
  const scannedAt = new Date().toLocaleTimeString('zh-CN')
  try {
    const r = await api.post('/preorder/cartons/scan', {
      carton_barcode: barcode.value.trim(),
      damaged: damaged.value
    })
    const row = { ...r.data, scanned_at: scannedAt }
    history.value.unshift(row)
    ElMessage.success(`扫描成功：${row.carton_barcode} · ${row.qty_in_box} 件${row.drf_status_advanced ? ' · DRF 已推进' : ''}`)
    barcode.value = ''
    damaged.value = false
    // 重新聚焦 input（扫码枪连发需要保持焦点）
    setTimeout(() => inputRef.value?.focus(), 100)
  } catch (e) {
    const msg = e.response?.data?.message || e.message
    ElMessage.error('扫描失败: ' + msg)
    history.value.unshift({
      scanned_at: scannedAt,
      carton_barcode: barcode.value,
      drf_id: '-', box_seq: '-', total_boxes: '-',
      qty_in_box: 0,
      new_carton_status: 'error',
      drf_status_advanced: false,
      error: msg
    })
    barcode.value = ''
    setTimeout(() => inputRef.value?.focus(), 100)
  } finally {
    scanning.value = false
  }
}

function clearHistory() {
  ElMessageBox.confirm('清空本会话记录（不影响数据库）？', '确认', { type: 'warning' })
    .then(() => { history.value = [] })
    .catch(() => {})
}

onMounted(() => {
  inputRef.value?.focus()
})
</script>
