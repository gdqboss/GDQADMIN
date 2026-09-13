<template>
  <div class="scraper-page">
    <el-card class="filter-bar" shadow="never">
      <div class="filter-row">
        <el-input
          v-model="filterKeyword"
          placeholder="按任务名/URL 搜索"
          clearable
          style="width: 280px"
          @keyup.enter="reload"
        >
          <template #prefix><el-icon><Search /></el-icon></template>
        </el-input>
        <el-select v-model="filterStatus" placeholder="状态" clearable style="width: 140px" @change="reload">
          <el-option label="全部" value="" />
          <el-option label="启用中" value="active" />
          <el-option label="已暂停" value="paused" />
          <el-option label="已禁用" value="disabled" />
        </el-select>
        <el-button type="primary" :icon="Plus" @click="openCreate">新建抓取任务</el-button>
        <div class="filter-spacer" />
        <el-tag type="info" effect="plain">{{ total }} 个任务</el-tag>
      </div>
    </el-card>

    <el-table
      :data="rows"
      v-loading="loading"
      stripe
      style="width: 100%; margin-top: 12px"
      :header-cell-style="{ background: '#fafafa', fontWeight: 600 }"
    >
      <el-table-column prop="id" label="ID" width="64" />
      <el-table-column prop="name" label="任务名" min-width="160">
        <template #default="{ row }">
          <div class="name-cell">
            <strong>{{ row.name }}</strong>
            <el-tag v-if="row.last_status === 'success'" type="success" size="small">上次成功</el-tag>
            <el-tag v-else-if="row.last_status === 'failed'" type="danger" size="small">上次失败</el-tag>
            <el-tag v-else size="small" type="info">未跑过</el-tag>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="目标 URL" min-width="280">
        <template #default="{ row }">
          <a :href="row.url" target="_blank" class="url-link" :title="row.url">
            {{ row.url }}
          </a>
        </template>
      </el-table-column>
      <el-table-column prop="selector" label="选择器" min-width="200">
        <template #default="{ row }">
          <code class="selector-code">{{ row.selector_type }}: {{ row.selector }}</code>
        </template>
      </el-table-column>
      <el-table-column prop="fetch_mode" label="抓取模式" width="110">
        <template #default="{ row }">
          <el-tag :type="modeTagType(row.fetch_mode)" size="small">
            {{ modeLabel(row.fetch_mode) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="上次跑于" width="170">
        <template #default="{ row }">
          <span v-if="row.last_run_at">{{ fmtDate(row.last_run_at) }}</span>
          <span v-else class="muted">—</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="240" fixed="right">
        <template #default="{ row }">
          <el-button size="small" type="primary" :icon="VideoPlay" :loading="row._running" @click="runJob(row)">
            跑
          </el-button>
          <el-button size="small" :icon="Edit" @click="openEdit(row)">编辑</el-button>
          <el-button size="small" type="danger" :icon="Delete" @click="remove(row)">删</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      v-model:current-page="page"
      v-model:page-size="size"
      :total="total"
      :page-sizes="[10, 20, 50]"
      layout="total, sizes, prev, pager, next"
      style="margin-top: 16px; justify-content: flex-end"
      @current-change="reload"
      @size-change="reload"
    />

    <!-- 创建/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="editingId ? '编辑抓取任务' : '新建抓取任务'" width="640px">
      <el-form :model="form" label-width="110px">
        <el-form-item label="任务名" required>
          <el-input v-model="form.name" placeholder="人类可读, 如 'quotes 首页 抓取'" maxlength="200" />
        </el-form-item>
        <el-form-item label="目标 URL" required>
          <el-input v-model="form.url" placeholder="https://example.com/page" />
        </el-form-item>
        <el-form-item label="选择器">
          <div class="selector-row">
            <el-select v-model="form.selector_type" style="width: 110px">
              <el-option label="CSS" value="css" />
              <el-option label="XPath" value="xpath" />
              <el-option label="Text" value="text" />
            </el-select>
            <el-input v-model="form.selector" placeholder=".title / //h1 / '整页文本'" />
          </div>
        </el-form-item>
        <el-form-item label="抓取模式">
          <el-radio-group v-model="form.fetch_mode">
            <el-radio-button value="fetcher">HTTP 快</el-radio-button>
            <el-radio-button value="dynamic">JS 渲染</el-radio-button>
            <el-radio-button value="stealthy">CF 反爬</el-radio-button>
          </el-radio-group>
          <div class="mode-hint">
            <span v-if="form.fetch_mode === 'fetcher'">⚡ 速度最快 (≈700ms), 普通静态页</span>
            <span v-else-if="form.fetch_mode === 'dynamic'">🕷 跑 Chromium (3-8s), JS 渲染后内容</span>
            <span v-else>🛡 隐藏指纹 + 真实浏览器, 过 Cloudflare (10-30s)</span>
          </div>
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio-button value="active">启用</el-radio-button>
            <el-radio-button value="paused">暂停</el-radio-button>
            <el-radio-button value="disabled">禁用</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="Cron (可选)">
          <el-input v-model="form.cron" placeholder="如 '0 */2 * * *' (预留, 当前靠手动跑)" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">{{ editingId ? '保存' : '创建' }}</el-button>
      </template>
    </el-dialog>

    <!-- 结果预览弹窗 -->
    <el-dialog v-model="resultVisible" :title="`抓取结果预览 · 任务 #${runResult.job_id}`" width="780px">
      <div v-if="runResult.ok" class="result-success">
        <div class="result-meta">
          <el-tag type="success" size="large">✓ 成功</el-tag>
          <span>耗时 <strong>{{ runResult.elapsed_ms }}ms</strong></span>
          <span>抓取 <strong>{{ runResult.count }}</strong> 条</span>
          <span>HTTP <strong>{{ runResult.status }}</strong></span>
        </div>
        <el-divider />
        <el-table :data="previewItems" max-height="400" size="small">
          <el-table-column type="index" width="50" />
          <el-table-column label="内容预览">
            <template #default="{ row }">
              <code class="item-code">{{ row }}</code>
            </template>
          </el-table-column>
        </el-table>
        <p v-if="runResult.count > previewItems.length" class="muted center">
          只显示前 {{ previewItems.length }} / {{ runResult.count }} 条;完整数据已存数据库
        </p>
      </div>
      <div v-else class="result-error">
        <el-alert type="error" :closable="false" :title="runResult.error || '抓取失败'">
          <p v-if="runResult.trace" class="trace">{{ runResult.trace }}</p>
        </el-alert>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Edit, Delete, VideoPlay, Search } from '@element-plus/icons-vue'
import api from '../../services/api.js'

const rows = ref([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const size = ref(20)
const filterKeyword = ref('')
const filterStatus = ref('')

const dialogVisible = ref(false)
const editingId = ref(null)
const saving = ref(false)
const form = ref(emptyForm())

const resultVisible = ref(false)
const runResult = ref({ ok: false, items: [], elapsed_ms: 0, count: 0, status: 0, error: null, job_id: 0 })
const previewItems = computed(() => (runResult.value.items || []).slice(0, 20))

function emptyForm() {
  return { name: '', url: '', selector: '', selector_type: 'css', fetch_mode: 'fetcher', status: 'active', cron: '' }
}

async function reload() {
  loading.value = true
  try {
    const params = { page: page.value, size: size.value }
    if (filterKeyword.value) params.keyword = filterKeyword.value
    if (filterStatus.value) params.status = filterStatus.value
    const res = await api.get('/scraper/jobs', { params })
    if (res.code === 0) {
      rows.value = (res.data.list || []).map(r => ({ ...r, _running: false }))
      total.value = res.data.total || 0
    } else {
      ElMessage.error(res.message || '加载失败')
    }
  } catch (e) {
    ElMessage.error('网络错误: ' + e.message)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  dialogVisible.value = true
}

function openEdit(row) {
  editingId.value = row.id
  form.value = {
    name: row.name,
    url: row.url,
    selector: row.selector,
    selector_type: row.selector_type,
    fetch_mode: row.fetch_mode,
    status: row.status,
    cron: row.cron || '',
  }
  dialogVisible.value = true
}

async function save() {
  if (!form.value.name || !form.value.url || !form.value.selector) {
    ElMessage.warning('任务名/URL/选择器 必填')
    return
  }
  saving.value = true
  try {
    let res
    if (editingId.value) {
      res = await api.put(`/scraper/jobs/${editingId.value}`, form.value)
    } else {
      res = await api.post('/scraper/jobs', form.value)
    }
    if (res.code === 0) {
      ElMessage.success(editingId.value ? '已保存' : '已创建')
      dialogVisible.value = false
      reload()
    } else {
      ElMessage.error(res.message || '保存失败')
    }
  } catch (e) {
    ElMessage.error('保存错误: ' + e.message)
  } finally {
    saving.value = false
  }
}

async function remove(row) {
  try {
    await ElMessageBox.confirm(`确认删除任务「${row.name}」? 这会同时删除所有历史结果`, '确认', { type: 'warning' })
  } catch { return }
  try {
    const res = await api.delete(`/scraper/jobs/${row.id}`)
    if (res.code === 0) {
      ElMessage.success('已删除')
      reload()
    } else {
      ElMessage.error(res.message || '删除失败')
    }
  } catch (e) {
    ElMessage.error('删除错误: ' + e.message)
  }
}

async function runJob(row) {
  row._running = true
  try {
    const res = await api.post(`/scraper/jobs/${row.id}/run`)
    if (res.code === 0) {
      runResult.value = { ok: true, ...res.data, job_id: row.id }
      resultVisible.value = true
      ElMessage.success(`✓ 抓到 ${res.data.count} 条 · ${res.data.elapsed_ms}ms`)
      setTimeout(reload, 500)
    } else {
      runResult.value = { ok: false, error: res.message, job_id: row.id }
      resultVisible.value = true
      ElMessage.error('抓取失败: ' + (res.message || '未知'))
    }
  } catch (e) {
    runResult.value = { ok: false, error: e.message, job_id: row.id }
    resultVisible.value = true
    ElMessage.error('网络错误: ' + e.message)
  } finally {
    row._running = false
  }
}

function fmtDate(s) {
  if (!s) return '—'
  const d = new Date(s)
  if (isNaN(d)) return s
  const pad = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function modeLabel(m) { return { fetcher: 'HTTP', dynamic: 'JS', stealthy: 'CF' }[m] || m }
function modeTagType(m) { return { fetcher: '', dynamic: 'warning', stealthy: 'danger' }[m] || '' }
function statusLabel(s) { return { active: '启用', paused: '暂停', disabled: '禁用' }[s] || s }
function statusTagType(s) { return { active: 'success', paused: 'warning', disabled: 'info' }[s] || '' }

onMounted(reload)
</script>

<style scoped>
.scraper-page { padding: 16px; }
.filter-row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.filter-spacer { flex: 1; }
.name-cell { display: flex; align-items: center; gap: 8px; }
.url-link { color: #409eff; text-decoration: none; font-family: 'JetBrains Mono', monospace; font-size: 12px; word-break: break-all; }
.url-link:hover { text-decoration: underline; }
.selector-code { background: #f5f7fa; padding: 2px 6px; border-radius: 3px; font-size: 12px; color: #606266; }
.selector-row { display: flex; gap: 8px; align-items: center; width: 100%; }
.mode-hint { font-size: 12px; color: #909399; margin-top: 6px; }
.result-meta { display: flex; gap: 16px; align-items: center; }
.item-code { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #303133; word-break: break-all; }
.trace { font-family: monospace; font-size: 11px; white-space: pre-wrap; margin-top: 8px; max-height: 200px; overflow: auto; background: #fafafa; padding: 8px; border-radius: 4px; }
.muted { color: #909399; font-size: 12px; }
.center { text-align: center; margin-top: 12px; }
</style>
