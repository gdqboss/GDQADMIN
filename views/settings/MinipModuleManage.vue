<template>
  <div class="minip-module-manage">
    <PageHeader title="📦 小程序业务模块管理" subtitle="管理 minip 首页/企业页/游客页展示的业务模块入口" />

    <el-card>
      <div class="toolbar">
        <el-select v-model="filterTarget" placeholder="按目标筛选" clearable style="width:160px">
          <el-option label="游客可见 (visitor)" value="visitor" />
          <el-option label="员工可见 (employee)" value="employee" />
          <el-option label="全部可见 (both)" value="both" />
        </el-select>
        <el-button @click="loadList">刷新</el-button>
        <el-button type="primary" @click="openEdit(null)">+ 新增模块</el-button>
      </div>

      <el-table :data="filteredList" stripe v-loading="loading">
        <el-table-column label="ID" prop="id" width="60" />
        <el-table-column label="图标" prop="icon" width="100">
          <template #default="{ row }">
            <span style="font-family:monospace; font-size:12px">{{ row.icon }}</span>
          </template>
        </el-table-column>
        <el-table-column label="模块 Key" prop="module_key" width="180" />
        <el-table-column label="标题" prop="title" width="140" />
        <el-table-column label="副标题" prop="subtitle" />
        <el-table-column label="跳转路径" prop="path" width="180">
          <template #default="{ row }">
            <code style="font-size:12px">{{ row.path }}</code>
          </template>
        </el-table-column>
        <el-table-column label="目标" prop="target" width="100">
          <template #default="{ row }">
            <el-tag :type="row.target === 'visitor' ? 'success' : row.target === 'employee' ? 'warning' : 'info'" size="small">
              {{ row.target }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="排序" prop="sort_order" width="80" />
        <el-table-column label="启用" width="80">
          <template #default="{ row }">
            <el-tag :type="row.enabled ? 'success' : 'info'" size="small">
              {{ row.enabled ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180">
          <template #default="{ row }">
            <el-button size="small" @click="openEdit(row)">编辑</el-button>
            <el-popconfirm title="确定删除?" @confirm="del(row)">
              <template #reference><el-button size="small" type="danger">删除</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 编辑弹窗 -->
    <el-dialog v-model="editVisible" :title="form.id ? '编辑模块' : '新增模块'" width="640">
      <el-form :model="form" label-width="100">
        <el-form-item label="模块 Key">
          <el-input v-model="form.module_key" placeholder="例如 enterprise_service" :disabled="!!form.id" />
        </el-form-item>
        <el-form-item label="标题">
          <el-input v-model="form.title" placeholder="例如 企业服务" />
        </el-form-item>
        <el-form-item label="副标题">
          <el-input v-model="form.subtitle" placeholder="例如 政策对接·资源整合" />
        </el-form-item>
        <el-form-item label="图标">
          <el-input v-model="form.icon" placeholder="Material Icons 名称, 例如 handshake" />
        </el-form-item>
        <el-form-item label="跳转路径">
          <el-input v-model="form.path" placeholder="例如 /enterprise/service" />
        </el-form-item>
        <el-form-item label="目标人群">
          <el-select v-model="form.target">
            <el-option label="游客可见 (visitor)" value="visitor" />
            <el-option label="员工可见 (employee)" value="employee" />
            <el-option label="全部可见 (both)" value="both" />
          </el-select>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" />
        </el-form-item>
        <el-form-item label="启用">
          <el-radio-group v-model="form.enabled">
            <el-radio :value="1">启用</el-radio>
            <el-radio :value="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'

const list = ref([])
const loading = ref(false)
const filterTarget = ref('')
const editVisible = ref(false)
const saving = ref(false)
const form = ref({})

const filteredList = computed(() =>
  filterTarget.value ? list.value.filter(m => m.target === filterTarget.value) : list.value
)

async function loadList() {
  loading.value = true
  try {
    const r = await api.get('/minip/admin/modules')
    list.value = r.data?.data || []
  } catch (e) {
    ElMessage.error('加载失败: ' + (e.response?.data?.message || e.message))
  }
  loading.value = false
}

function openEdit(row) {
  if (row) {
    form.value = { ...row }
  } else {
    form.value = {
      module_key: '', title: '', subtitle: '', icon: '',
      path: '', target: 'both', sort_order: 0, enabled: 1
    }
  }
  editVisible.value = true
}

async function save() {
  if (!form.value.module_key || !form.value.title) {
    ElMessage.error('模块 Key 和标题必填')
    return
  }
  saving.value = true
  try {
    const method = form.value.id ? 'put' : 'post'
    const url = form.value.id ? `/minip/admin/modules/${form.value.id}` : '/minip/admin/modules'
    const r = await api[method](url, form.value)
    if (r.data?.code === 0) {
      ElMessage.success('保存成功')
      editVisible.value = false
      loadList()
    } else {
      ElMessage.error(r.data?.message || '保存失败')
    }
  } catch (e) {
    ElMessage.error('保存失败: ' + (e.response?.data?.message || e.message))
  }
  saving.value = false
}

async function del(row) {
  try {
    const r = await api.delete(`/minip/admin/modules/${row.id}`)
    if (r.data?.code === 0) {
      ElMessage.success('已删除')
      loadList()
    } else {
      ElMessage.error(r.data?.message || '删除失败')
    }
  } catch (e) {
    ElMessage.error('删除失败: ' + (e.response?.data?.message || e.message))
  }
}

onMounted(loadList)
</script>

<style scoped>
.minip-module-manage { padding: 16px; }
.toolbar { display: flex; gap: 12px; margin-bottom: 16px; }
</style>