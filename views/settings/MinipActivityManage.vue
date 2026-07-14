<template>
  <div class="minip-activity-manage">
    <PageHeader title="🎯 小程序活动管理" subtitle="管理 minip 首页活动 banner 列表（湾区创新论坛/科技人才训练营...）" />

    <el-card>
      <div class="toolbar">
        <el-select v-model="filterStatus" placeholder="按状态筛选" clearable style="width:160px">
          <el-option label="未发布 (draft)" value="draft" />
          <el-option label="进行中 (ongoing)" value="ongoing" />
          <el-option label="已结束 (finished)" value="finished" />
          <el-option label="已发布 (published)" value="published" />
        </el-select>
        <el-button @click="loadList">刷新</el-button>
        <el-button type="primary" @click="openEdit(null)">+ 新增活动</el-button>
      </div>

      <el-table :data="filteredList" stripe v-loading="loading">
        <el-table-column label="ID" prop="id" width="60" />
        <el-table-column label="封面" width="100">
          <template #default="{ row }">
            <img v-if="row.cover_image" :src="row.cover_image" style="height:50px; max-width:80px; object-fit:cover; border-radius:4px" />
            <span v-else style="color:#999; font-size:12px">无图</span>
          </template>
        </el-table-column>
        <el-table-column label="标题" prop="title" width="200" />
        <el-table-column label="描述" prop="description" show-overflow-tooltip />
        <el-table-column label="地点" prop="location" width="160" />
        <el-table-column label="时间" width="200">
          <template #default="{ row }">
            <div style="font-size:12px">
              <div>{{ row.start_date ? new Date(row.start_date).toLocaleString('zh-CN') : '-' }}</div>
              <div style="color:#999">→ {{ row.end_date ? new Date(row.end_date).toLocaleString('zh-CN') : '-' }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="参与" width="100">
          <template #default="{ row }">
            <span>{{ row.current_participants || 0 }} / {{ row.max_participants || '∞' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusType(row.status)" size="small">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="排序" prop="sort_order" width="70" />
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
    <el-dialog v-model="editVisible" :title="form.id ? '编辑活动' : '新增活动'" width="720">
      <el-form :model="form" label-width="100">
        <el-form-item label="标题">
          <el-input v-model="form.title" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="封面图 URL">
          <el-input v-model="form.cover_image" placeholder="可选" />
        </el-form-item>
        <el-form-item label="地点">
          <el-input v-model="form.location" />
        </el-form-item>
        <el-form-item label="开始时间">
          <el-date-picker v-model="form.start_date" type="datetime" value-format="YYYY-MM-DDTHH:mm:ss.SSSZ" placeholder="选择日期时间" style="width:100%" />
        </el-form-item>
        <el-form-item label="结束时间">
          <el-date-picker v-model="form.end_date" type="datetime" value-format="YYYY-MM-DDTHH:mm:ss.SSSZ" placeholder="选择日期时间" style="width:100%" />
        </el-form-item>
        <el-form-item label="最大参与人数">
          <el-input-number v-model="form.max_participants" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="form.status">
            <el-option label="草稿 (draft)" value="draft" />
            <el-option label="已发布 (published)" value="published" />
            <el-option label="进行中 (ongoing)" value="ongoing" />
            <el-option label="已结束 (finished)" value="finished" />
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
const filterStatus = ref('')
const editVisible = ref(false)
const saving = ref(false)
const form = ref({})

const filteredList = computed(() =>
  filterStatus.value ? list.value.filter(a => a.status === filterStatus.value) : list.value
)

function statusType(s) {
  return { draft: 'info', published: 'success', ongoing: 'warning', finished: '' }[s] || 'info'
}

async function loadList() {
  loading.value = true
  try {
    const r = await api.get('/minip/admin/activities')
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
      title: '', description: '', cover_image: '', location: '',
      start_date: null, end_date: null, max_participants: 0,
      status: 'draft', sort_order: 0, enabled: 1
    }
  }
  editVisible.value = true
}

async function save() {
  if (!form.value.title) {
    ElMessage.error('标题必填')
    return
  }
  saving.value = true
  try {
    const method = form.value.id ? 'put' : 'post'
    const url = form.value.id ? `/minip/admin/activities/${form.value.id}` : '/minip/admin/activities'
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
    const r = await api.delete(`/minip/admin/activities/${row.id}`)
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
.minip-activity-manage { padding: 16px; }
.toolbar { display: flex; gap: 12px; margin-bottom: 16px; }
</style>