<template>
  <div class="import-records">
    <div class="header">
      <h1>📥 {{ $t('importRecords.pageTitle') }}</h1>
      <div class="header-actions">
        <button class="btn" @click="$router.push('/excel-analyzer')">+ {{ $t('importRecords.newImport') }}</button>
      </div>
    </div>

    <!-- Stats Overview -->
    <div class="stats-grid" v-if="stats">
      <div class="stat-card">
        <div class="stat-value">{{ stats.totalRecords }}</div>
        <div class="stat-label">{{ $t('importRecords.totalImports') }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ stats.totalItems }}</div>
        <div class="stat-label">{{ $t('importRecords.totalItems') }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">¥{{ stats.totalAmount?.toLocaleString() }}</div>
        <div class="stat-label">{{ $t('importRecords.totalAmount') }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ stats.uniqueStores }}</div>
        <div class="stat-label">{{ $t('importRecords.uniqueStores') }}</div>
      </div>
    </div>

    <!-- Multi-select Analysis Bar -->
    <div class="multi-bar" v-if="records.length > 0">
      <label class="checkbox-label">
        <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" />
        {{ $t('importRecords.selectAll') || '全选' }}
      </label>
      <span class="selected-count">{{ selected.length }} {{ $t('importRecords.selected') || '已选' }}</span>
      <button class="btn btn-analysis" :disabled="selected.length < 2" @click="multiAnalysis">
        📊 {{ $t('importRecords.multiAnalysis') || '多选分析' }}
      </button>
    </div>

    <!-- Records Table -->
    <div class="report-section">
      <table class="data-table">
        <thead>
          <tr>
            <th class="col-check"></th>
            <th>{{ $t('importRecords.fileName') }}</th>
            <th>{{ $t('importRecords.type') }}</th>
            <th>{{ $t('importRecords.uploadTime') }}</th>
            <th>{{ $t('importRecords.itemCount') }}</th>
            <th>{{ $t('importRecords.totalAmount') }}</th>
            <th>{{ $t('importRecords.status') }}</th>
            <th>{{ $t('importRecords.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="record in records" :key="record.id" :class="{ 'row-selected': selected.includes(record.id) }">
            <td class="col-check">
              <input type="checkbox" :value="record.id" v-model="selected" />
            </td>
            <td>{{ record.file_name }}</td>
            <td><span :class="['badge', record.file_type.toLowerCase()]">{{ record.file_type }}</span></td>
            <td>{{ formatDate(record.uploaded_at) }}</td>
            <td>{{ record.total_records?.toLocaleString() }}</td>
            <td>¥{{ record.total_amount?.toLocaleString() }}</td>
            <td><span :class="['status', record.status]">{{ record.status }}</span></td>
            <td>
              <div class="action-buttons">
                <button class="btn-small" @click="viewDetail(record)">{{ $t('importRecords.viewDetail') }}</button>
                <button class="btn-small btn-danger" @click="deleteRecord(record)">{{ $t('importRecords.delete') }}</button>
              </div>
            </td>
          </tr>
          <tr v-if="records.length === 0">
            <td colspan="8" class="empty-cell">{{ $t('importRecords.noRecords') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="total > pageSize">
      <button class="btn-small" :disabled="page <= 1" @click="loadRecords(page - 1)">{{ $t('common.prev') }}</button>
      <span>{{ page }} / {{ Math.ceil(total / pageSize) }}</span>
      <button class="btn-small" :disabled="page >= Math.ceil(total / pageSize)" @click="loadRecords(page + 1)">{{ $t('common.next') }}</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import i18n from '@/i18n'
import api from '@/services/api.js'

const { t, locale } = i18n.global

const records = ref([])
const stats = ref(null)
const page = ref(1)
const pageSize = 20
const total = ref(0)
const selected = ref([])
const selectAll = ref(false)

async function loadRecords(p = 1) {
  try {
    const res = await api.get('/import/records', { params: { page: p, page_size: pageSize } })
    if (res.success) {
      records.value = res.records || []
      total.value = res.total
      page.value = res.page
    }
  } catch (e) {
    console.error('[ImportRecords] load error:', e)
  }
}

async function loadStats() {
  try {
    if (records.value.length > 0) {
      stats.value = {
        totalRecords: records.value.length,
        totalItems: records.value.reduce((sum, r) => sum + (r.total_records || 0), 0),
        totalAmount: records.value.reduce((sum, r) => sum + (r.total_amount || 0), 0),
        uniqueStores: '-'
      }
    }
  } catch (e) {
    console.error('[ImportRecords] stats error:', e)
  }
}

function toggleSelectAll() {
  if (selectAll.value) {
    selected.value = records.value.map(r => r.id)
  } else {
    selected.value = []
  }
}

function multiAnalysis() {
  if (selected.value.length < 2) return
  const ids = selected.value.join(',')
  window.location.hash = `/import-detail-multi/${ids}`
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function viewDetail(record) {
  window.location.hash = `/import-detail/${record.id}`
}

async function deleteRecord(record) {
  const confirmMsg = locale.value === 'zh' ? `确定删除 "${record.file_name}" 吗？` : `Delete "${record.file_name}"?`
  if (!confirm(confirmMsg)) return
  try {
    const res = await api.delete(`/import/records/${record.id}`)
    if (res.success) {
      records.value = records.value.filter(r => r.id !== record.id)
      selected.value = selected.value.filter(id => id !== record.id)
    } else {
      alert(res.message || 'Delete failed')
    }
  } catch (e) {
    console.error('[ImportRecords] delete error:', e)
    alert('Delete failed')
  }
}

onMounted(() => {
  loadRecords()
  loadStats()
})
</script>

<style scoped>
.import-records { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.header h1 { margin: 0; font-size: 24px; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center; }
.stat-value { font-size: 28px; font-weight: bold; color: #409eff; }
.stat-label { font-size: 14px; color: #909399; margin-top: 4px; }

.multi-bar { display: flex; align-items: center; gap: 16px; background: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 14px; color: #606266; }
.selected-count { font-size: 13px; color: #909399; }
.btn-analysis { background: #67c23a; }
.btn-analysis:hover { background: #85ce61; }
.btn-analysis:disabled { background: #c0c4cc; cursor: not-allowed; }

.report-section { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 12px 8px; text-align: left; border-bottom: 1px solid #ebeef5; font-size: 14px; }
.data-table th { background: #fafafa; font-weight: 600; color: #606266; }
.data-table tr:hover { background: #f5f7fa; }
.row-selected { background: #ecf5ff; }
.col-check { width: 40px; text-align: center; }
.badge { padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.badge.sm { background: #e6f7ff; color: #1890ff; }
.badge.appollos { background: #f6ffed; color: #52c41a; }
.status { padding: 2px 8px; border-radius: 4px; font-size: 12px; }
.status.completed { background: #f6ffed; color: #52c41a; }
.status.pending { background: #fffbe6; color: #faad14; }
.status.failed { background: #fff2f0; color: #ff4d4f; }
.action-buttons { display: flex; gap: 8px; }
.btn { padding: 10px 20px; background: #409eff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
.btn:hover { background: #66b1ff; }
.btn-small { padding: 6px 12px; background: #409eff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn-small:hover { background: #66b1ff; }
.btn-small.btn-danger { background: #ff4d4f; }
.btn-small.btn-danger:hover { background: #ff7875; }
.empty-cell { text-align: center; color: #909399; padding: 40px; }
.pagination { display: flex; justify-content: center; align-items: center; gap: 16px; margin-top: 20px; }
.pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
</style>