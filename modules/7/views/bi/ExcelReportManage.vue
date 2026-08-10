<template>
  <div class="import-records">
    <div class="header">
      <h1>📥 {{ $t('importRecords.pageTitle') }}</h1>
      <div class="header-actions">
        <button class="btn" @click="$router.push('/excel-analyzer')">+ {{ $t('importRecords.newImport') }}</button>
      </div>
    </div>

    <!-- Multi-select Analysis Bar -->
    <div class="multi-bar" v-if="list.length > 0">
      <label class="checkbox-label">
        <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" />
        {{ $t('importRecords.selectAll') || '全选' }}
      </label>
      <span class="selected-count">{{ selected.length }} {{ $t('importRecords.selected') || '已选' }}</span>
      <button class="btn btn-analysis" :disabled="selected.length < 2" @click="multiAnalysis">
        📊 {{ $t('importRecords.multiAnalysis') || '多选分析' }}
      </button>
    </div>

    <!-- Stats Overview -->
    <div class="stats-grid" v-if="stats">
      <div class="stat-card purple">
        <div class="stat-value">{{ stats.totalRecords }}</div>
        <div class="stat-label">{{ $t('importRecords.totalImports') }}</div>
      </div>
      <div class="stat-card blue">
        <div class="stat-value">{{ stats.totalItems?.toLocaleString() }}</div>
        <div class="stat-label">{{ $t('importRecords.totalItems') }}</div>
      </div>
      <div class="stat-card green">
        <div class="stat-value">¥{{ stats.totalAmount?.toLocaleString() }}</div>
        <div class="stat-label">{{ $t('importRecords.totalAmount') }}</div>
      </div>
      <div class="stat-card orange">
        <div class="stat-value">{{ stats.uniqueStores }}</div>
        <div class="stat-label">{{ $t('importRecords.uniqueStores') }}</div>
      </div>
    </div>

    <!-- Records Table -->
    <div class="report-section">
      <!-- Search -->
      <div class="search-bar">
        <input v-model="searchKeyword" type="text" :placeholder="locale === 'zh' ? '搜索文件名...' : 'Search file name...'" @input="debounceSearch" />
      </div>

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
          <tr v-for="(record, i) in list" :key="record.id" :class="{ 'row-selected': selected.includes(record.id) }">
            <td class="col-check">
              <input type="checkbox" :value="record.id" v-model="selected" />
            </td>
            <td class="file-name">{{ record.file_name }}</td>
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
          <tr v-if="list.length === 0">
            <td colspan="8" class="empty-cell">
              {{ locale === 'zh' ? '暂无导入记录，请先在分析器中上传Excel' : 'No records. Please upload an Excel in the analyzer first.' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="total > pageSize">
      <button class="btn-small" :disabled="page <= 1" @click="loadList(page - 1)">{{ $t('common.prev') }}</button>
      <span>{{ page }} / {{ Math.ceil(total / pageSize) }}</span>
      <button class="btn-small" :disabled="page >= Math.ceil(total / pageSize)" @click="loadList(page + 1)">{{ $t('common.next') }}</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import i18n from '@/i18n'

const router = useRouter()
const { t, locale } = i18n.global

const list = ref([])
const stats = ref(null)
const page = ref(1)
const pageSize = 20
const total = ref(0)
const searchKeyword = ref('')
const selected = ref([])
const selectAll = ref(false)

let searchTimer = null

function toggleSelectAll() {
  if (selectAll.value) {
    selected.value = list.value.map(r => r.id)
  } else {
    selected.value = []
  }
}

function multiAnalysis() {
  if (selected.value.length < 2) return
  const ids = selected.value.join(',')
  window.location.hash = `/import-detail-multi/${ids}`
}

function debounceSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadList(1), 400)
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

async function loadList(p = 1) {
  page.value = p
  const token = localStorage.getItem('caimeite_token')
  const params = new URLSearchParams({ page: p, page_size: pageSize })
  if (searchKeyword.value) params.set('keyword', searchKeyword.value)

  try {
    const res = await fetch(`/api/import/records?${params}`, {
      headers: { Authorization: 'Bearer ' + token }
    }).then(r => r.json())

    if (res.success) {
      list.value = res.records || []
      total.value = res.total || 0
      page.value = res.page || 1
      computeStats()
    }
  } catch (e) {
    console.error('[ImportRecords] load error:', e)
  }
}

function computeStats() {
  const records = list.value
  if (!records.length) {
    stats.value = null
    return
  }

  // Compute aggregated stats from current list
  const totalAmount = records.reduce((sum, r) => sum + parseFloat(r.total_amount || 0), 0)
  const totalItems = records.reduce((sum, r) => sum + (r.total_records || 0), 0)

  stats.value = {
    totalRecords: records.length,
    totalItems,
    totalAmount,
    uniqueStores: '-'
  }
}

function viewDetail(record) {
  window.location.href = `/#/import-detail/${record.id}`
}

async function deleteRecord(record) {
  const confirmMsg = locale.value === 'zh'
    ? `确定删除 "${record.file_name}" 吗？`
    : `Delete "${record.file_name}"?`
  if (!confirm(confirmMsg)) return

  const token = localStorage.getItem('caimeite_token')
  try {
    const res = await fetch(`/api/import/records/${record.id}`, {
      method: 'DELETE',
      headers: { Authorization: 'Bearer ' + token }
    }).then(r => r.json())

    if (res.success) {
      loadList(page.value)
    } else {
      alert(res.message || (locale.value === 'zh' ? '删除失败' : 'Delete failed'))
    }
  } catch (e) {
    console.error('[ImportRecords] delete error:', e)
    alert(locale.value === 'zh' ? '删除失败' : 'Delete failed')
  }
}

onMounted(() => loadList(1))
</script>

<style scoped>
.import-records {
  padding: 24px;
  background: #f5f7fa;
  min-height: 100vh;
}
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}
.header h1 { margin: 0; font-size: 24px; }
.header-actions { display: flex; gap: 8px; }

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 20px;
}
.stat-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.stat-value {
  font-size: 28px;
  font-weight: bold;
  color: #303133;
}
.stat-label {
  font-size: 13px;
  color: #909399;
  margin-top: 6px;
}
.stat-card.purple .stat-value { color: #7c3aed; }
.stat-card.blue .stat-value { color: #2563eb; }
.stat-card.green .stat-value { color: #16a34a; }
.stat-card.orange .stat-value { color: #ea580c; }

/* Multi-select Bar */
.multi-bar { display: flex; align-items: center; gap: 16px; background: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 14px; color: #606266; }
.selected-count { font-size: 13px; color: #909399; }
.btn-analysis { background: #67c23a; border-color: #67c23a; color: white; }
.btn-analysis:hover { background: #85ce61; border-color: #85ce61; }
.btn-analysis:disabled { background: #c0c4cc; border-color: #c0c4cc; cursor: not-allowed; }

/* Table */
.report-section {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}
.search-bar { margin-bottom: 16px; }
.search-bar input {
  width: 100%;
  max-width: 340px;
  padding: 9px 14px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
}
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #eee; }
.data-table th { background: #fafafa; font-weight: 600; color: #606266; font-size: 12px; text-transform: uppercase; }
.data-table .file-name { font-weight: 600; color: #303133; }
.data-table .empty-cell { text-align: center; color: #999; padding: 40px; }
.data-table tr:hover td { background: #f9fafb; }
.data-table .row-selected td { background: #ecf5ff; }
.col-check { width: 40px; text-align: center; }

/* Badges */
.badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
}
.badge.sm { background: #dbeafe; color: #1d4ed8; }
.badge.appollos { background: #fce7f3; color: #be185d; }

/* Status */
.status {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
}
.status.completed { background: #dcfce7; color: #16a34a; }
.status.pending { background: #fef9c3; color: #a16207; }
.status.processing { background: #dbeafe; color: #1d4ed8; }
.status.failed { background: #fee2e2; color: #dc2626; }

/* Action buttons */
.action-buttons { display: flex; gap: 6px; }
.btn-small {
  padding: 5px 10px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  transition: all 0.2s;
}
.btn-small:hover { border-color: #409eff; color: #409eff; }
.btn-danger { color: #f56c6c; border-color: #f56c6c; }
.btn-danger:hover { background: #fef0f0; }
.btn {
  padding: 9px 18px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}
.btn:hover { border-color: #409eff; color: #409eff; }

/* Pagination */
.pagination { display: flex; align-items: center; gap: 12px; justify-content: center; margin-top: 20px; }
.pagination span { color: #606266; font-size: 13px; }
</style>