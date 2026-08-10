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
const filterExpress = ref('')

const expressCompanies = ref([])
const showFormDrawer = ref(false)
const editingId = ref(null)
const form = ref(emptyForm())

function emptyForm() {
  return {
    name: '', express_company_id: null, channel_code: '', channel_name: '',
    website: '', tracking_url: '', status: 'enabled',
    min_days: null, max_days: null,
    first_weight_fee: null, continue_weight_fee: null,
    estimate_days: null, remark: ''
  }
}

async function fetchExpressCompanies() {
  try {
    const res = await api.get('/logistics/express-companies', { params: { size: 100, status: 'enabled' } })
    if (res.code === 0) expressCompanies.value = res.data.list || []
  } catch (e) { console.error(e) }
}

async function fetchList() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value }
    if (searchKeyword.value) params.name = searchKeyword.value
    if (filterStatus.value) params.status = filterStatus.value
    if (filterExpress.value) params.express_company_id = filterExpress.value
    const res = await api.get('/logistics/channel-logistics', { params })
    if (res.code === 0) {
      list.value = res.data.list || []
      total.value = res.data.total ?? 0
    }
  } catch (e) {
    ElMessage.error(e.message || '获取渠道物流列表失败')
  } finally {
    loading.value = false
  }
}

watch([searchKeyword, filterStatus, filterExpress], () => { currentPage.value = 1; fetchList() })
watch(currentPage, fetchList)
onMounted(() => { fetchList(); fetchExpressCompanies() })

function openAdd() {
  editingId.value = null
  form.value = emptyForm()
  showFormDrawer.value = true
}

async function openEdit(row) {
  editingId.value = row.id
  try {
    const res = await api.get(`/logistics/channel-logistics/${row.id}`)
    if (res.code === 0) {
      const d = res.data
      form.value = {
        name: d.name, express_company_id: d.express_company_id || null,
        channel_code: d.channel_code || '', channel_name: d.channel_name || '',
        website: d.website || '', tracking_url: d.tracking_url || '',
        status: d.status,
        min_days: d.min_days || null, max_days: d.max_days || null,
        first_weight_fee: d.first_weight_fee || null, continue_weight_fee: d.continue_weight_fee || null,
        estimate_days: d.estimate_days || null, remark: d.remark || ''
      }
      showFormDrawer.value = true
    }
  } catch (e) {
    ElMessage.error(e.message || '获取详情失败')
  }
}

async function handleSubmit() {
  if (!form.value.name) { ElMessage.warning('渠道名称必填'); return }
  try {
    const payload = { ...form.value }
    if (editingId.value) {
      await api.put(`/logistics/channel-logistics/${editingId.value}`, payload)
      ElMessage.success('更新成功')
    } else {
      await api.post('/logistics/channel-logistics', payload)
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
    await ElMessageBox.confirm(`确定删除渠道「${row.name}」？`, '提示', { type: 'warning' })
    await api.delete(`/logistics/channel-logistics/${row.id}`)
    ElMessage.success('删除成功')
    fetchList()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '删除失败')
  }
}

function getExpressName(id) {
  const ec = expressCompanies.value.find(e => String(e.id) === String(id))
  return ec ? ec.name : ''
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="渠道物流管理" subtitle="管理物流发货渠道与时效配置" />

    <!-- Search bar -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
      <div class="flex flex-wrap gap-4 items-end">
        <div>
          <label class="block text-xs text-gray-500 mb-1">渠道名称</label>
          <el-input v-model="searchKeyword" placeholder="渠道名称" clearable class="!w-52" @keyup.enter="fetchList" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">快递公司</label>
          <el-select v-model="filterExpress" clearable class="!w-44">
            <el-option label="全部" value="" />
            <el-option v-for="ec in expressCompanies" :key="ec.id" :label="ec.name" :value="ec.id" />
          </el-select>
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
          <el-button type="primary" @click="openAdd">新增渠道</el-button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table v-loading="loading" :data="list" stripe empty-text="暂无数据">
        <el-table-column label="渠道名称" prop="name" min-width="140" />
        <el-table-column label="快递公司" min-width="120">
          <template #default="{ row }">
            <span v-if="row.express_company_name">{{ row.express_company_name }}</span>
            <span v-else-if="row.express_company_id">{{ getExpressName(row.express_company_id) }}</span>
            <span v-else class="text-gray-400">-</span>
          </template>
        </el-table-column>
        <el-table-column label="渠道代码" prop="channel_code" min-width="100">
          <template #default="{ row }"><span class="text-gray-500 text-sm">{{ row.channel_code || '-' }}</span></template>
        </el-table-column>
        <el-table-column label="参考时效" width="110">
          <template #default="{ row }">
            <span v-if="row.min_days || row.max_days">{{ row.min_days ||0 }}-{{ row.max_days || 0 }}天</span>
            <span v-else-if="row.estimate_days">{{ row.estimate_days }}天</span>
            <span v-else class="text-gray-400">-</span>
          </template>
        </el-table-column>
        <el-table-column label="首重费用" width="100" align="right">
          <template #default="{ row }">
            <span v-if="row.first_weight_fee != null" class="text-blue-600">S$ {{ parseFloat(row.first_weight_fee).toFixed(2) }}</span>
            <span v-else class="text-gray-400">-</span>
          </template>
        </el-table-column>
        <el-table-column label="续重费用" width="100" align="right">
          <template #default="{ row }">
            <span v-if="row.continue_weight_fee != null" class="text-blue-600">S$ {{ parseFloat(row.continue_weight_fee).toFixed(2) }}</span>
            <span v-else class="text-gray-400">-</span>
          </template>
        </el-table-column>
        <el-table-column label="追踪网址" min-width="160">
          <template #default="{ row }">
            <a v-if="row.tracking_url" :href="row.tracking_url" target="_blank" class="text-blue-500 hover:underline truncate block text-sm">{{ row.tracking_url }}</a>
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
        <el-table-column label="操作" width="140" fixed="right">
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
    <el-drawer v-model="showFormDrawer" :title="editingId ? '编辑渠道物流' : '新增渠道物流'" size="480px">
      <div class="pr-6 space-y-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">渠道名称 <span class="text-red-500">*</span></label>
          <el-input v-model="form.name" placeholder="如：DHL Express Singapore" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">所属快递公司</label>
          <el-select v-model="form.express_company_id" clearable class="!w-full">
            <el-option v-for="ec in expressCompanies" :key="ec.id" :label="ec.name" :value="ec.id" />
          </el-select>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">渠道代码</label>
            <el-input v-model="form.channel_code" placeholder="如：DHLEXPRESS" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">渠道名称（官方）</label>
            <el-input v-model="form.channel_name" placeholder="官方渠道名" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">最小天数</label>
            <el-input-number v-model="form.min_days" :min="0" class="!w-full" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">最大天数</label>
            <el-input-number v-model="form.max_days" :min="0" class="!w-full" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">首重费用（S$）</label>
            <el-input-number v-model="form.first_weight_fee" :min="0" :precision="2" class="!w-full" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">续重费用（S$/kg）</label>
            <el-input-number v-model="form.continue_weight_fee" :min="0" :precision="2" class="!w-full" />
          </div>
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">官网</label>
          <el-input v-model="form.website" placeholder="https://" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">运单追踪URL</label>
          <el-input v-model="form.tracking_url" placeholder="https://track.example.com/{tracking_number}" />
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