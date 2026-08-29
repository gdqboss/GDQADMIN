<script setup>
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { ElMessage, ElMessageBox } from 'element-plus'

const { t } = useI18n()

const list = ref([])
const loading = ref(false)
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)
const searchKeyword = ref('')
const filterStatus = ref('')

const showFormDrawer = ref(false)
const editingId = ref(null)
const form = ref(emptyForm())

function emptyForm() {
  return { name: '', code: '', website: '', phone: '', status: 'enabled', sort: 0, remark: '' }
}

async function fetchList() {
  loading.value = true
  try {
    const params = { page: currentPage.value, size: pageSize.value }
    if (searchKeyword.value) params.keyword = searchKeyword.value
    if (filterStatus.value) params.status = filterStatus.value
    const res = await api.get('/logistics/express-companies', { params })
    if (res.code === 0) {
      list.value = res.data.list || []
      total.value = res.data.total ?? 0
    }
  } catch (e) {
    ElMessage.error(e.message || '获取快递公司列表失败')
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
  showFormDrawer.value = true
}

function openEdit(row) {
  editingId.value = row.id
  form.value = {
    name: row.name, code: row.code, website: row.website || '',
    phone: row.phone || '', status: row.status, sort: row.sort || 0, remark: row.remark || ''
  }
  showFormDrawer.value = true
}

async function handleSubmit() {
  if (!form.value.name || !form.value.code) {
    ElMessage.warning('名称和代码必填')
    return
  }
  try {
    if (editingId.value) {
      await api.put(`/logistics/express-companies/${editingId.value}`, { ...form.value })
      ElMessage.success('更新成功')
    } else {
      await api.post('/logistics/express-companies', { ...form.value })
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
    await ElMessageBox.confirm(`确定删除快递公司「${row.name}」？`, '提示', { type: 'warning' })
    await api.delete(`/logistics/express-companies/${row.id}`)
    ElMessage.success('删除成功')
    fetchList()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '删除失败')
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="快递公司管理" subtitle="维护快递公司基础信息" />

    <!-- Search bar -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
      <div class="flex flex-wrap gap-4 items-end">
        <div>
          <label class="block text-xs text-gray-500 mb-1">关键词</label>
          <el-input v-model="searchKeyword" placeholder="名称 / 代码" clearable class="!w-52" @keyup.enter="fetchList" />
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
          <el-button type="primary" @click="openAdd">新增快递公司</el-button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table v-loading="loading" :data="list" stripe empty-text="暂无数据">
        <el-table-column label="排序" prop="sort" width="70" align="center" />
        <el-table-column label="名称" prop="name" min-width="140" />
        <el-table-column label="代码" prop="code" min-width="100">
          <template #default="{ row }">
            <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">{{ row.code }}</span>
          </template>
        </el-table-column>
        <el-table-column label="官网" prop="website" min-width="160">
          <template #default="{ row }">
            <a v-if="row.website" :href="row.website" target="_blank" class="text-blue-500 hover:underline truncate block">{{ row.website }}</a>
            <span v-else class="text-gray-400">-</span>
          </template>
        </el-table-column>
        <el-table-column label="电话" prop="phone" min-width="120" />
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="row.status === 'enabled' ? 'success' : 'info'" size="small">
              {{ row.status === 'enabled' ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="备注" prop="remark" min-width="140">
          <template #default="{ row }"><span class="text-gray-500 text-xs">{{ row.remark || '-' }}</span></template>
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
    <el-drawer v-model="showFormDrawer" :title="editingId ? '编辑快递公司' : '新增快递公司'" size="400px">
      <div class="pr-6 space-y-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">名称 <span class="text-red-500">*</span></label>
          <el-input v-model="form.name" placeholder="如：顺丰速运" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">代码 <span class="text-red-500">*</span></label>
          <el-input v-model="form.code" placeholder="如：SF" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">官网</label>
          <el-input v-model="form.website" placeholder="https://" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">电话</label>
          <el-input v-model="form.phone" placeholder="客服电话" />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">排序</label>
          <el-input-number v-model="form.sort" :min="0" class="!w-full" />
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
          <el-input v-model="form.remark" type="textarea" :rows="2" placeholder="备注信息" />
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