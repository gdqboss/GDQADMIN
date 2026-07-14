<template>
  <div class="banner-manage">
    <h2>🎠 轮播图管理</h2>
    <p class="muted">不同位置展示不同轮播,后台可上传图 + 配置跳转目标。改完刷新页面立即生效。</p>

    <el-card>
      <div class="toolbar">
        <el-select v-model="filterPosition" placeholder="按位置筛选" clearable style="width:200px">
          <el-option v-for="p in positions" :key="p" :label="p" :value="p" />
        </el-select>
        <el-button type="primary" @click="openEdit(null)">+ 新增轮播</el-button>
        <el-button @click="loadList">刷新</el-button>
      </div>

      <el-table :data="filteredList" stripe>
        <el-table-column label="ID" prop="id" width="60" />
        <el-table-column label="图" width="120">
          <template #default="{ row }">
            <img :src="row.image_url" style="height:50px; max-width:100px; object-fit:cover; border-radius:4px" />
          </template>
        </el-table-column>
        <el-table-column label="位置" prop="position" width="120" />
        <el-table-column label="标题" prop="title" />
        <el-table-column label="跳转" width="180">
          <template #default="{ row }">
            <el-tag size="small">{{ row.link_type }}</el-tag>
            <span style="margin-left:8px; font-size:12px; color:#666">{{ row.link_target }}</span>
          </template>
        </el-table-column>
        <el-table-column label="排序" prop="sort_order" width="80" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="点击" prop="click_count" width="80" />
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
    <el-dialog v-model="editVisible" :title="form.id ? '编辑轮播' : '新增轮播'" width="640">
      <el-form :model="form" label-width="100">
        <el-form-item label="位置">
          <el-select v-model="form.position">
            <el-option v-for="p in positions" :key="p" :label="p" :value="p" />
          </el-select>
        </el-form-item>
        <el-form-item label="主图URL"><el-input v-model="form.image_url" /></el-form-item>
        <el-form-item label="移动图URL"><el-input v-model="form.image_mobile_url" placeholder="可选,适配 H5" /></el-form-item>
        <el-form-item label="标题"><el-input v-model="form.title" /></el-form-item>
        <el-form-item label="副标题"><el-input v-model="form.subtitle" /></el-form-item>
        <el-form-item label="跳转类型">
          <el-select v-model="form.link_type">
            <el-option v-for="t in linkTypes" :key="t" :label="t" :value="t" />
          </el-select>
        </el-form-item>
        <el-form-item label="跳转目标"><el-input v-model="form.link_target" placeholder="路径/URL/ID" /></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="form.sort_order" :min="0" /></el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio value="active">启用</el-radio>
            <el-radio value="inactive">禁用</el-radio>
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

const positions = ['home_top', 'home_mid', 'home_bottom', 'popup', 'category_top', 'order_success', 'login_bg']
const linkTypes = ['none', 'internal', 'external', 'product', 'category', 'article', 'activity', 'minip', 'wechat', 'phone', 'custom']

const list = ref([])
const filterPosition = ref('')
const editVisible = ref(false)
const saving = ref(false)
const form = ref({})

const filteredList = computed(() =>
  filterPosition.value ? list.value.filter(b => b.position === filterPosition.value) : list.value
)

async function loadList() {
  const token = localStorage.getItem('caimeite_token')
  const res = await fetch('/api/banners/admin/list', { headers: { Authorization: 'Bearer ' + token } })
  const d = await res.json()
  list.value = d.data || []
}

function openEdit(row) {
  if (row) {
    form.value = { ...row, link_params: row.link_params ? JSON.parse(row.link_params) : null }
  } else {
    form.value = {
      position: 'home_top',
      title: '',
      subtitle: '',
      image_url: '',
      image_mobile_url: '',
      link_type: 'none',
      link_target: '',
      sort_order: 0,
      status: 'active'
    }
  }
  editVisible.value = true
}

async function save() {
  saving.value = true
  const token = localStorage.getItem('caimeite_token')
  const method = form.value.id ? 'PATCH' : 'POST'
  const url = form.value.id ? `/api/banners/admin/${form.value.id}` : '/api/banners/admin'
  const res = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + token },
    body: JSON.stringify(form.value)
  })
  const d = await res.json()
  saving.value = false
  if (d.code === 0) {
    ElMessage.success('保存成功')
    editVisible.value = false
    loadList()
  } else {
    ElMessage.error(d.message)
  }
}

async function del(row) {
  const token = localStorage.getItem('caimeite_token')
  const res = await fetch(`/api/banners/admin/${row.id}`, {
    method: 'DELETE',
    headers: { Authorization: 'Bearer ' + token }
  })
  const d = await res.json()
  if (d.code === 0) { ElMessage.success('已删除'); loadList() }
  else ElMessage.error(d.message)
}

onMounted(loadList)
</script>

<style scoped>
.banner-manage { padding: 16px; }
.muted { color: #666; font-size: 13px; margin: 8px 0 16px; }
.toolbar { display: flex; gap: 12px; margin-bottom: 16px; }
</style>