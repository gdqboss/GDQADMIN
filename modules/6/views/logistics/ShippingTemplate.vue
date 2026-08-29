<script setup>
import { ref, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'

const list = ref([])
const loading = ref(false)
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)
const searchKeyword = ref('')
const filterStatus = ref('')

const showFormDrawer = ref(false)
const showRuleDrawer = ref(false)
const editingId = ref(null)
const activeTemplate = ref(null)

const calcTypes = [
  { label: '按重量计费', value: 'by_weight' },
  { label: '按件计费', value: 'by_item' },
]
const dispatchTypes = [
  { label: '预计发货时间', value: 'estimated' },
  { label: '固定发货日', value: 'fixed' },
]

const form = ref(emptyForm())

function emptyForm() {
  return {
    name: '', calc_type: 'by_weight', dispatch_type: 'estimated',
    dispatch_time: 3, min_free_amount: null, min_free_number: null,
    status: 'enabled', remark: '', rules: []
  }
}

function emptyRule() {
  return {
    region_names: [], first_weight: 1, first_fee: 0,
    continue_weight: 1, continue_fee: 0,
    item_first: 1, item_first_fee: 0,
    item_continue: 1, item_continue_fee: 0
  }
}

async function fetchList() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value }
    if (searchKeyword.value) params.name = searchKeyword.value
    if (filterStatus.value) params.status = filterStatus.value
    const res = await api.get('/logistics/freight-templates', { params })
    if (res.code === 0) {
      list.value = res.data.list || []
      total.value = res.data.total ?? 0
    }
  } catch (e) {
    ElMessage.error(e.message || '获取运费模板列表失败')
  } finally {
    loading.value = false
  }
}

watch([searchKeyword, filterStatus], () => { currentPage.value = 1; fetchList() })
watch(currentPage, fetchList)
onMounted(fetchList)

function openAdd() {
  editingId.value = null
  form.value = emptyForm()
  form.value.rules = [emptyRule()]
  showFormDrawer.value = true
}

async function openEdit(row) {
  editingId.value = row.id
  try {
    const res = await api.get(`/logistics/freight-templates/${row.id}`)
    if (res.code === 0) {
      const tpl = res.data
      form.value = {
        name: tpl.name,
        calc_type: tpl.calc_type || 'by_weight',
        dispatch_type: tpl.dispatch_type || 'estimated',
        dispatch_time: tpl.dispatch_time || 3,
        min_free_amount: tpl.min_free_amount || null,
        min_free_number: tpl.min_free_number || null,
        status: tpl.status,
        remark: tpl.remark || '',
        rules: (tpl.rules || []).map(r => ({
          region_names: Array.isArray(r.region_names) ? r.region_names : (typeof r.region_names === 'string' ? (() => { try { return JSON.parse(r.region_names) } catch { return [] } })() : []),
          first_weight: r.first_weight || 1,
          first_fee: r.first_fee || 0,
          continue_weight: r.continue_weight || 1,
          continue_fee: r.continue_fee || 0,
          item_first: r.item_first || 0,
          item_first_fee: r.item_first_fee || 0,
          item_continue: r.item_continue || 0,
          item_continue_fee: r.item_continue_fee || 0,
        }))
      }
      showFormDrawer.value = true
    }
  } catch (e) {
    ElMessage.error(e.message || '获取详情失败')
  }
}

function addRule() {
  form.value.rules.push(emptyRule())
}

function removeRule(idx) {
  if (form.value.rules.length <= 1) { ElMessage.warning('至少保留一条计费规则'); return }
  form.value.rules.splice(idx, 1)
}

async function handleSubmit() {
  if (!form.value.name) { ElMessage.warning('模板名称必填'); return }
  if (!form.value.rules || form.value.rules.length === 0) { ElMessage.warning('至少需要一条计费规则'); return }
  try {
    const payload = { ...form.value }
    if (editingId.value) {
      await api.put(`/logistics/freight-templates/${editingId.value}`, payload)
      ElMessage.success('更新成功')
    } else {
      await api.post('/logistics/freight-templates', payload)
      ElMessage.success('新增成功')
    }
    showFormDrawer.value = false
    fetchList()
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(`确定删除运费模板「${row.name}」？`, '提示', { type: 'warning' })
    await api.delete(`/logistics/freight-templates/${row.id}`)
    ElMessage.success('删除成功')
    fetchList()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '删除失败')
  }
}

function formatCalcType(v) { return calcTypes.find(c => c.value === v)?.label || v }
function formatDispatchType(v) { return dispatchTypes.find(d => d.value === v)?.label || v }
function formatRegions(rules) {
  const all = rules.flatMap(r => Array.isArray(r.region_names) ? r.region_names : [])
  return all.length > 0 ? all.join('、') : '全国'
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="运费模板管理" subtitle="配置按重量或按件计费规则" />

    <!-- Search bar -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
      <div class="flex flex-wrap gap-4 items-end">
        <div>
          <label class="block text-xs text-gray-500 mb-1">模板名称</label>
          <el-input v-model="searchKeyword" placeholder="模板名称" clearable class="!w-52" @keyup.enter="fetchList" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">状态</label>
          <el-select v-model="filterStatus" clearable class="!w-36">
            <el-option label="全部" value="" />
            <el-option label="启用" value="enabled" />
            <el-option label="禁用" value="disabled" />
          </el-select>
        </div>
        <div class="ml-auto">
          <el-button type="primary" @click="openAdd">新增模板</el-button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table v-loading="loading" :data="list" stripe empty-text="暂无数据">
        <el-table-column label="模板名称" prop="name" min-width="140" />
        <el-table-column label="计费方式" min-width="110">
          <template #default="{ row }">
            <span class="text-sm">{{ formatCalcType(row.calc_type) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="配送类型" min-width="110">
          <template #default="{ row }">
            <span class="text-sm">{{ formatDispatchType(row.dispatch_type) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="发货时效" width="100">
          <template #default="{ row }">
            <span v-if="row.dispatch_time">{{ row.dispatch_time }}天</span>
            <span v-else class="text-gray-400">-</span>
          </template>
        </el-table-column>
        <el-table-column label="适用地区" min-width="160">
          <template #default="{ row }">
            <span class="text-sm text-gray-600 truncate block">{{ formatRegions(row.rules || []) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="满免金额" width="100" align="right">
          <template #default="{ row }">
            <span v-if="row.min_free_amount" class="text-green-600">S$ {{ parseFloat(row.min_free_amount).toFixed(2) }}</span>
            <span v-else class="text-gray-400">-</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="row.status === 'enabled' ? 'success' : 'info'" size="small">
              {{ row.status === 'enabled' ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" fixed="right">
          <template #default="{ row }">
            <el-button size="small" link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button size="small" link type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="flex justify-end p-4 border-t">
        <el-pagination
          v-model:current-page="currentPage"
          :page-size="pageSize"
          :total="total"
          layout="total, prev, pager, next"
          background
        />
      </div>
    </div>

    <!-- Form Drawer -->
    <el-drawer v-model="showFormDrawer" :title="editingId ? '编辑运费模板' : '新增运费模板'" size="520px">
      <div class="pr-6 space-y-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">模板名称 <span class="text-red-500">*</span></label>
          <el-input v-model="form.name" placeholder="如：SG-西马计费" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">计费方式</label>
            <el-select v-model="form.calc_type" class="!w-full">
              <el-option v-for="c in calcTypes" :key="c.value" :label="c.label" :value="c.value" />
            </el-select>
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">配送类型</label>
            <el-select v-model="form.dispatch_type" class="!w-full">
              <el-option v-for="d in dispatchTypes" :key="d.value" :label="d.label" :value="d.value" />
            </el-select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">发货时效（天）</label>
            <el-input-number v-model="form.dispatch_time" :min="1" class="!w-full" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">满免金额（S$）</label>
            <el-input-number v-model="form.min_free_amount" :min="0" :precision="2" class="!w-full" />
          </div>
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">满免件数</label>
          <el-input-number v-model="form.min_free_number" :min="0" class="!w-full" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">状态</label>
          <el-radio-group v-model="form.status">
            <el-radio value="enabled">启用</el-radio>
            <el-radio value="disabled">禁用</el-radio>
          </el-radio-group>
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">备注</label>
          <el-input v-model="form.remark" type="textarea" :rows="2" />
        </div>

        <!-- Rules -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="text-sm text-gray-600 font-medium">计费规则</label>
            <el-button size="small" link type="primary" @click="addRule">+ 添加规则</el-button>
          </div>
          <div v-for="(rule, idx) in form.rules" :key="idx" class="border border-gray-200 rounded-lg p-4 mb-3 bg-gray-50">
            <div class="flex justify-between items-center mb-3">
              <span class="text-xs font-medium text-gray-500">规则 {{ idx + 1 }}</span>
              <el-button size="small" link type="danger" @click="removeRule(idx)">删除</el-button>
            </div>
            <div class="space-y-3">
              <div>
                <label class="block text-xs text-gray-500 mb-1">地区名称（逗号分隔）</label>
                <el-input v-model="rule.region_names" placeholder="如：西马,东马,新加坡" />
              </div>
              <div v-if="form.calc_type === 'by_weight'" class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs text-gray-500 mb-1">首重（kg）</label>
                  <el-input-number v-model="rule.first_weight" :min="0" :precision="2" class="!w-full" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 mb-1">首重费用（S$）</label>
                  <el-input-number v-model="rule.first_fee" :min="0" :precision="2" class="!w-full" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 mb-1">续重（kg）</label>
                  <el-input-number v-model="rule.continue_weight" :min="0" :precision="2" class="!w-full" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 mb-1">续费（S$/kg）</label>
                  <el-input-number v-model="rule.continue_fee" :min="0" :precision="2" class="!w-full" />
                </div>
              </div>
              <div v-else class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs text-gray-500 mb-1">首件</label>
                  <el-input-number v-model="rule.item_first" :min="0" class="!w-full" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 mb-1">首件费用（S$）</label>
                  <el-input-number v-model="rule.item_first_fee" :min="0" :precision="2" class="!w-full" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 mb-1">续件</label>
                  <el-input-number v-model="rule.item_continue" :min="0" class="!w-full" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 mb-1">续费（S$/件）</label>
                  <el-input-number v-model="rule.item_continue_fee" :min="0" :precision="2" class="!w-full" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-3 justify-end">
          <el-button @click="showFormDrawer = false">取消</el-button>
          <el-button type="primary" @click="handleSubmit">保存</el-button>
        </div>
      </template>
    </el-drawer>
  </div>
</template>