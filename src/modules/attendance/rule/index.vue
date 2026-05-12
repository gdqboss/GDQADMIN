<template>
  <div class="attendance-rule-container">
    <!-- Header -->
    <div class="page-header">
      <h2>考勤规则</h2>
      <div class="header-actions">
        <el-button type="primary" @click="handleAdd">新增规则</el-button>
      </div>
    </div>

    <!-- Rules List -->
    <el-card class="data-card">
      <el-table v-loading="loading" :data="list" stripe border>
        <el-table-column prop="name" label="规则名称" min-width="150" />
        <el-table-column prop="clock_in_start" label="上班开始" width="100">
          <template #default="{ row }">
            {{ row.clock_in_start || '09:00' }}
          </template>
        </el-table-column>
        <el-table-column prop="clock_in_end" label="上班截止" width="100">
          <template #default="{ row }">
            {{ row.clock_in_end || '09:30' }}
          </template>
        </el-table-column>
        <el-table-column prop="clock_out_start" label="下班开始" width="100">
          <template #default="{ row }">
            {{ row.clock_out_start || '18:00' }}
          </template>
        </el-table-column>
        <el-table-column prop="late_rule" label="迟到规则" min-width="150">
          <template #default="{ row }">
            超过{{ row.clock_in_end || '09:30' }}记迟到
          </template>
        </el-table-column>
        <el-table-column prop="early_rule" label="早退规则" min-width="150">
          <template #default="{ row }">
            早于{{ row.clock_out_start || '18:00' }}记早退
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
              {{ row.status === 'active' ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" size="small" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- Add/Edit Dialog -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑规则' : '新增规则'" width="500px">
      <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="规则名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入规则名称" />
        </el-form-item>
        <el-form-item label="上班开始时间" prop="clock_in_start">
          <el-time-picker v-model="form.clock_in_start" placeholder="选择时间" format="HH:mm" value-format="HH:mm" />
        </el-form-item>
        <el-form-item label="上班截止时间" prop="clock_in_end">
          <el-time-picker v-model="form.clock_in_end" placeholder="选择时间" format="HH:mm" value-format="HH:mm" />
        </el-form-item>
        <el-form-item label="下班开始时间" prop="clock_out_start">
          <el-time-picker v-model="form.clock_out_start" placeholder="选择时间" format="HH:mm" value-format="HH:mm" />
        </el-form-item>
        <el-form-item label="下班截止时间" prop="clock_out_end">
          <el-time-picker v-model="form.clock_out_end" placeholder="选择时间" format="HH:mm" value-format="HH:mm" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="form.status">
            <el-radio label="active">启用</el-radio>
            <el-radio label="inactive">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitForm" :loading="submitting">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getAttendanceRules, createAttendanceRule, updateAttendanceRule, deleteAttendanceRule } from '@/api/oa'

const loading = ref(false)
const list = ref([])
const dialogVisible = ref(false)
const submitting = ref(false)
const isEdit = ref(false)
const formRef = ref(null)

const form = reactive({
  id: null,
  name: '',
  clock_in_start: '09:00',
  clock_in_end: '09:30',
  clock_out_start: '18:00',
  clock_out_end: '23:59',
  status: 'active'
})

const rules = {
  name: [{ required: true, message: '请输入规则名称', trigger: 'blur' }]
}

const loadData = async () => {
  loading.value = true
  try {
    const data = await getAttendanceRules()
    list.value = data || []
  } catch (e) {
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

const handleAdd = () => {
  isEdit.value = false
  Object.assign(form, {
    id: null,
    name: '',
    clock_in_start: '09:00',
    clock_in_end: '09:30',
    clock_out_start: '18:00',
    clock_out_end: '23:59',
    status: 'active'
  })
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  Object.assign(form, row)
  dialogVisible.value = true
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确认删除该规则？', '提示', { type: 'warning' })
    await deleteAttendanceRule(row.id)
    ElMessage.success('删除成功')
    loadData()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '删除失败')
  }
}

const submitForm = async () => {
  if (!formRef.value) return
  await formRef.value.validate()
  
  submitting.value = true
  try {
    if (isEdit.value) {
      await updateAttendanceRule(form.id, form)
      ElMessage.success('更新成功')
    } else {
      await createAttendanceRule(form)
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.attendance-rule-container { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }
.data-card { margin-bottom: 20px; }
</style>
