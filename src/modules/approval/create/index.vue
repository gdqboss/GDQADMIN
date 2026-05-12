<template>
  <div class="approval-create-container">
    <!-- Header -->
    <div class="page-header">
      <h2>新建审批</h2>
    </div>

    <!-- Approval Type Selection -->
    <el-card class="type-card">
      <div class="type-grid">
        <div 
          v-for="t in approvalTypes" 
          :key="t.value"
          class="type-item"
          :class="{ active: selectedType === t.value }"
          @click="selectedType = t.value"
        >
          <span class="type-icon">{{ t.icon }}</span>
          <span class="type-label">{{ t.label }}</span>
        </div>
      </div>
    </el-card>

    <!-- Approval Form -->
    <el-card class="form-card" v-if="selectedType">
      <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="标题" prop="title">
          <el-input v-model="form.title" placeholder="请输入审批标题" />
        </el-form-item>

        <!-- Leave Form -->
        <template v-if="selectedType === 'leave'">
          <el-form-item label="请假类型" prop="leave_type">
            <el-select v-model="form.leave_type" placeholder="请选择">
              <el-option label="年假" value="annual" />
              <el-option label="病假" value="sick" />
              <el-option label="事假" value="personal" />
              <el-option label="婚假" value="marriage" />
              <el-option label="产假" value="maternity" />
              <el-option label="丧假" value="bereavement" />
            </el-select>
          </el-form-item>
          <el-form-item label="开始日期" prop="start_date">
            <el-date-picker v-model="form.start_date" type="date" value-format="YYYY-MM-DD" />
          </el-form-item>
          <el-form-item label="结束日期" prop="end_date">
            <el-date-picker v-model="form.end_date" type="date" value-format="YYYY-MM-DD" />
          </el-form-item>
          <el-form-item label="天数" prop="days">
            <el-input-number v-model="form.days" :min="0.5" :max="30" :step="0.5" />
          </el-form-item>
        </template>

        <!-- Overtime Form -->
        <template v-if="selectedType === 'overtime'">
          <el-form-item label="加班日期" prop="overtime_date">
            <el-date-picker v-model="form.overtime_date" type="date" value-format="YYYY-MM-DD" />
          </el-form-item>
          <el-form-item label="加班类型" prop="overtime_type">
            <el-select v-model="form.overtime_type" placeholder="请选择">
              <el-option label="工作日加班" value="weekday" />
              <el-option label="周末加班" value="weekend" />
              <el-option label="节假日加班" value="holiday" />
            </el-select>
          </el-form-item>
          <el-form-item label="加班小时" prop="hours">
            <el-input-number v-model="form.hours" :min="0.5" :max="24" :step="0.5" />
          </el-form-item>
        </template>

        <!-- Expense Form -->
        <template v-if="selectedType === 'expense'">
          <el-form-item label="报销类型" prop="expense_type">
            <el-select v-model="form.expense_type" placeholder="请选择">
              <el-option label="差旅费" value="travel" />
              <el-option label="交通费" value="transport" />
              <el-option label="餐饮费" value="meal" />
              <el-option label="办公费" value="office" />
              <el-option label="其他" value="other" />
            </el-select>
          </el-form-item>
          <el-form-item label="报销金额" prop="amount">
            <el-input-number v-model="form.amount" :min="0" :precision="2" />
          </el-form-item>
        </template>

        <!-- Business Trip Form -->
        <template v-if="selectedType === 'business'">
          <el-form-item label="出差地点" prop="destination">
            <el-input v-model="form.destination" placeholder="请输入出差地点" />
          </el-form-item>
          <el-form-item label="开始日期" prop="start_date">
            <el-date-picker v-model="form.start_date" type="date" value-format="YYYY-MM-DD" />
          </el-form-item>
          <el-form-item label="结束日期" prop="end_date">
            <el-date-picker v-model="form.end_date" type="date" value-format="YYYY-MM-DD" />
          </el-form-item>
        </template>

        <!-- Common Fields -->
        <el-form-item label="详细说明" prop="content">
          <el-input v-model="form.content" type="textarea" rows="4" placeholder="请输入审批详细说明" />
        </el-form-item>

        <!-- Attachments -->
        <el-form-item label="附件">
          <el-upload
            action="/api/upload"
            :headers="{ Authorization: `Bearer ${token}` }"
            :on-success="handleUploadSuccess"
            :on-error="handleUploadError"
            :file-list="fileList"
            :before-remove="beforeRemove"
            multiple
          >
            <el-button type="primary">上传附件</el-button>
          </el-upload>
        </el-form-item>
      </el-form>

      <div class="form-footer">
        <el-button @click="handleCancel">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">提交审批</el-button>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { getApprovalTypes, createApproval } from '@/api/oa'

const router = useRouter()
const loading = ref(false)
const submitting = ref(false)
const selectedType = ref('')
const approvalTypes = ref([])
const formRef = ref(null)
const token = localStorage.getItem('caimeite_token')
const fileList = ref([])

const form = reactive({
  title: '',
  type: '',
  content: '',
  // Leave
  leave_type: '',
  start_date: '',
  end_date: '',
  days: 1,
  // Overtime
  overtime_date: '',
  overtime_type: '',
  hours: 2,
  // Expense
  expense_type: '',
  amount: 0,
  // Business
  destination: ''
})

const rules = {
  title: [{ required: true, message: '请输入审批标题', trigger: 'blur' }],
  content: [{ required: true, message: '请输入详细说明', trigger: 'blur' }]
}

const loadApprovalTypes = async () => {
  try {
    const data = await getApprovalTypes()
    approvalTypes.value = data || [
      { value: 'leave', label: '请假', icon: '📅' },
      { value: 'overtime', label: '加班', icon: '⏰' },
      { value: 'expense', label: '报销', icon: '💰' },
      { value: 'business', label: '出差', icon: '✈️' },
      { value: 'general', label: '一般审批', icon: '📝' }
    ]
  } catch (e) {
    approvalTypes.value = [
      { value: 'leave', label: '请假', icon: '📅' },
      { value: 'overtime', label: '加班', icon: '⏰' },
      { value: 'expense', label: '报销', icon: '💰' },
      { value: 'business', label: '出差', icon: '✈️' },
      { value: 'general', label: '一般审批', icon: '📝' }
    ]
  }
}

const handleUploadSuccess = (res) => {
  if (res.code === 0) {
    fileList.value.push({ name: res.data.filename, url: res.data.url })
  }
}

const handleUploadError = () => {
  ElMessage.error('上传失败')
}

const beforeRemove = (file) => {
  fileList.value = fileList.value.filter(f => f.name !== file.name)
  return true
}

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate()
  
  submitting.value = true
  try {
    const data = {
      type: selectedType.value,
      title: form.title,
      content: form.content,
      attachments: fileList.value.map(f => f.url)
    }
    
    // Add type-specific fields
    if (selectedType.value === 'leave') {
      data.leave_type = form.leave_type
      data.start_date = form.start_date
      data.end_date = form.end_date
      data.days = form.days
    } else if (selectedType.value === 'overtime') {
      data.overtime_date = form.overtime_date
      data.overtime_type = form.overtime_type
      data.hours = form.hours
    } else if (selectedType.value === 'expense') {
      data.expense_type = form.expense_type
      data.amount = form.amount
    } else if (selectedType.value === 'business') {
      data.destination = form.destination
      data.start_date = form.start_date
      data.end_date = form.end_date
    }
    
    await createApproval(data)
    ElMessage.success('提交成功')
    router.push('/approval/list')
  } catch (e) {
    ElMessage.error(e.message || '提交失败')
  } finally {
    submitting.value = false
  }
}

const handleCancel = () => {
  router.back()
}

onMounted(() => {
  loadApprovalTypes()
})
</script>

<style scoped>
.approval-create-container { padding: 20px; }
.page-header { margin-bottom: 20px; }
.page-header h2 { margin: 0; }

.type-card { margin-bottom: 20px; }
.type-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; }
.type-item { 
  display: flex; 
  flex-direction: column; 
  align-items: center; 
  padding: 20px; 
  border: 2px solid #e4e7ed; 
  border-radius: 8px; 
  cursor: pointer; 
  transition: all 0.3s;
}
.type-item:hover { border-color: #409eff; }
.type-item.active { border-color: #409eff; background-color: #ecf5ff; }
.type-icon { font-size: 32px; margin-bottom: 10px; }
.type-label { font-size: 14px; }

.form-card { margin-bottom: 20px; }
.form-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 30px; }
</style>
