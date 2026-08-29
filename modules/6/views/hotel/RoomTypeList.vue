<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const { t } = useI18n()

const loading = ref(false)
const roomTypes = ref([])
const dialogVisible = ref(false)
const formLoading = ref(false)
const editingId = ref(null)

const form = ref({
  name: '',
  description: '',
  base_price: 0,
  capacity: 1,
  bed_type: '',
  area: '',
  facilities: [],
})

const facilitiesOptions = [
  'wifi', '空调', '独立卫浴', '电视', '冰箱', '吹风机',
  '保险柜', '阳台', '海景', '电吹风', '拖鞋', '牙刷',
]

function resetForm() {
  editingId.value = null
  form.value = {
    name: '',
    description: '',
    base_price: 0,
    capacity: 1,
    bed_type: '',
    area: '',
    facilities: [],
  }
}

function openAdd() {
  resetForm()
  dialogVisible.value = true
}

function openEdit(row) {
  editingId.value = row.id
  form.value = {
    name: row.name || '',
    description: row.description || '',
    base_price: row.base_price || 0,
    capacity: row.capacity || 1,
    bed_type: row.bed_type || '',
    area: row.area || '',
    facilities: Array.isArray(row.facilities) ? row.facilities : [],
  }
  dialogVisible.value = true
}

async function fetchRoomTypes() {
  loading.value = true
  try {
    const res = await api.get('/hotel/room-types')
    if (res.code === 0) {
      roomTypes.value = res.data || []
    }
  } catch (e) {
    ElMessage.error(e.message || '获取房型列表失败')
  } finally {
    loading.value = false
  }
}

async function submitForm() {
  if (!form.value.name) {
    ElMessage.warning('请输入房型名称')
    return
  }
  formLoading.value = true
  try {
    const payload = { ...form.value }
    const res = editingId.value
      ? await api.put(`/hotel/room-types/${editingId.value}`, payload)
      : await api.post('/hotel/room-types', payload)
    if (res.code === 0) {
      ElMessage.success(editingId.value ? '修改成功' : '添加成功')
      dialogVisible.value = false
      fetchRoomTypes()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  } finally {
    formLoading.value = false
  }
}

async function deleteRoomType(row) {
  if (!confirm(`确定删除房型 "${row.name}"？`)) return
  try {
    const res = await api.delete(`/hotel/room-types/${row.id}`)
    if (res.code === 0) {
      ElMessage.success('删除成功')
      fetchRoomTypes()
    } else {
      ElMessage.error(res.message || '删除失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '删除失败')
  }
}

onMounted(fetchRoomTypes)
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="房型管理" subtitle="酒店房型与基础价格设置" />

    <!-- Actions -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4 flex justify-end">
      <el-button type="primary" @click="openAdd">新增房型</el-button>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table :data="roomTypes" stripe v-loading="loading">
        <el-table-column label="房型名称" prop="name" min-width="120" />
        <el-table-column label="床型" prop="bed_type" width="100" />
        <el-table-column label="面积" width="80">
          <template #default="{ row }">
            {{ row.area || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="可住人数" prop="capacity" width="90" align="center" />
        <el-table-column label="基础价格" width="110" align="right">
          <template #default="{ row }">
            <span class="text-blue-600 font-medium">¥ {{ row.base_price }}</span>
          </template>
        </el-table-column>
        <el-table-column label="设施" min-width="200">
          <template #default="{ row }">
            <div class="flex flex-wrap gap-1">
              <el-tag
                v-for="f in (row.facilities || []).slice(0, 4)"
                :key="f"
                size="small"
                type="info"
              >{{ f }}</el-tag>
              <el-tag v-if="(row.facilities || []).length > 4" size="small" type="info">
                +{{ row.facilities.length - 4 }}
              </el-tag>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" fixed="right">
          <template #default="{ row }">
            <div class="flex flex-wrap gap-1">
              <el-button size="small" link type="primary" @click="openEdit(row)">编辑</el-button>
              <el-button size="small" link type="danger" @click="deleteRoomType(row)">删除</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- Add/Edit Dialog -->
    <el-dialog v-model="dialogVisible" :title="editingId ? '编辑房型' : '新增房型'" width="600px">
      <el-form :model="form" label-width="90px">
        <el-form-item label="房型名称">
          <el-input v-model="form.name" placeholder="如：豪华海景大床房" />
        </el-form-item>
        <el-form-item label="床型">
          <el-input v-model="form.bed_type" placeholder="如：大床1.8m / 双床1.2m" />
        </el-form-item>
        <div class="grid grid-cols-2 gap-4">
          <el-form-item label="可住人数">
            <el-input-number v-model="form.capacity" :min="1" :max="10" />
          </el-form-item>
          <el-form-item label="基础价格">
            <el-input-number v-model="form.base_price" :min="0" :precision="2" />
          </el-form-item>
        </div>
        <el-form-item label="面积">
          <el-input v-model="form.area" placeholder="如：35㎡" />
        </el-form-item>
        <el-form-item label="设施配置">
          <el-checkbox-group v-model="form.facilities">
            <el-checkbox v-for="f in facilitiesOptions" :key="f" :label="f">{{ f }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="formLoading" @click="submitForm">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>