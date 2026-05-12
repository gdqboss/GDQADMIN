<template>
  <div class="schedule-container">
    <!-- Header -->
    <div class="page-header">
      <h2>排班管理</h2>
      <div class="header-actions">
        <el-button type="primary" @click="handleAdd">新建排班</el-button>
        <el-button type="success" @click="handleSwap">申请调班</el-button>
      </div>
    </div>

    <!-- Calendar View -->
    <el-card class="calendar-card">
      <div class="calendar-header">
        <el-button @click="prevMonth">&lt;</el-button>
        <span class="current-month">{{ currentYear }}年{{ currentMonth + 1 }}月</span>
        <el-button @click="nextMonth">&gt;</el-button>
        <el-button type="primary" size="small" @click="goToday">今天</el-button>
      </div>
      
      <el-calendar v-model="calendarDate">
        <template #date-cell="{ data }">
          <div class="calendar-cell" :class="data.isSelected ? 'is-selected' : ''">
            <div class="date-number">{{ data.day.split('-')[2] }}</div>
            <div class="schedule-list" v-if="getScheduleForDate(data.day)">
              <div 
                v-for="item in getScheduleForDate(data.day)" 
                :key="item.id"
                class="schedule-item"
                :style="{ backgroundColor: item.shift_color || '#409eff' }"
              >
                {{ item.user_name }}:{{ item.shift_name }}
              </div>
            </div>
          </div>
        </template>
      </el-calendar>
    </el-card>

    <!-- Search -->
    <el-card class="search-card">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="员工">
          <el-input v-model="searchForm.user_name" placeholder="员工姓名" clearable @change="loadData" />
        </el-form-item>
        <el-form-item label="班次">
          <el-select v-model="searchForm.shift_id" placeholder="全部" clearable @change="loadData">
            <el-option v-for="s in shifts" :key="s.id" :label="s.name" :value="s.id" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadData">查询</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- Schedule List -->
    <el-card class="data-card">
      <el-table v-loading="loading" :data="list" stripe border>
        <el-table-column prop="schedule_date" label="日期" width="120" />
        <el-table-column prop="user_name" label="员工" width="100" />
        <el-table-column prop="department" label="部门" width="120" />
        <el-table-column prop="shift_name" label="班次" width="100">
          <template #default="{ row }">
            <el-tag :style="{ backgroundColor: row.shift_color }" size="small">
              {{ row.shift_name }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="shift_start" label="上班时间" width="120" />
        <el-table-column prop="shift_end" label="下班时间" width="120" />
        <el-table-column prop="remark" label="备注" min-width="150" show-overflow-tooltip />
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" size="small" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.size"
          :page-sizes="[10, 20, 50]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next"
          @size-change="loadData"
          @current-change="loadData"
        />
      </div>
    </el-card>

    <!-- Add/Edit Dialog -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑排班' : '新建排班'" width="500px">
      <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="员工" prop="user_id">
          <el-select v-model="form.user_id" placeholder="请选择员工" filterable>
            <el-option v-for="u in employees" :key="u.id" :label="u.name" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="日期" prop="schedule_date">
          <el-date-picker v-model="form.schedule_date" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" />
        </el-form-item>
        <el-form-item label="班次" prop="shift_id">
          <el-select v-model="form.shift_id" placeholder="请选择班次" @change="onShiftChange">
            <el-option v-for="s in shifts" :key="s.id" :label="s.name" :value="s.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="form.remark" type="textarea" rows="2" placeholder="备注信息" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitForm" :loading="submitting">确定</el-button>
      </template>
    </el-dialog>

    <!-- Swap Dialog -->
    <el-dialog v-model="swapDialogVisible" title="申请调班" width="500px">
      <el-form :model="swapForm" :rules="swapRules" ref="swapFormRef" label-width="100px">
        <el-form-item label="调班员工" prop="target_user_id">
          <el-select v-model="swapForm.target_user_id" placeholder="选择调班对象">
            <el-option v-for="u in employees" :key="u.id" :label="u.name" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="我的日期" prop="my_date">
          <el-date-picker v-model="swapForm.my_date" type="date" value-format="YYYY-MM-DD" placeholder="选择我的排班日期" />
        </el-form-item>
        <el-form-item label="对方日期" prop="target_date">
          <el-date-picker v-model="swapForm.target_date" type="date" value-format="YYYY-MM-DD" placeholder="选择对方的排班日期" />
        </el-form-item>
        <el-form-item label="原因" prop="reason">
          <el-input v-model="swapForm.reason" type="textarea" rows="3" placeholder="调班原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="swapDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitSwap" :loading="swapSubmitting">提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getSchedules, createSchedule, updateSchedule, deleteSchedule, swapSchedule, getShifts, getEmployees } from '@/api/oa'

const loading = ref(false)
const list = ref([])
const shifts = ref([])
const employees = ref([])
const calendarDate = ref(new Date())
const currentDate = ref(new Date())

const searchForm = reactive({
  user_name: '',
  shift_id: ''
})

const pagination = reactive({
  page: 1,
  size: 10,
  total: 0
})

const dialogVisible = ref(false)
const swapDialogVisible = ref(false)
const submitting = ref(false)
const swapSubmitting = ref(false)
const isEdit = ref(false)
const formRef = ref(null)
const swapFormRef = ref(null)

const form = reactive({
  id: null,
  user_id: null,
  schedule_date: '',
  shift_id: null,
  remark: ''
})

const swapForm = reactive({
  target_user_id: null,
  my_date: '',
  target_date: '',
  reason: ''
})

const rules = {
  user_id: [{ required: true, message: '请选择员工', trigger: 'change' }],
  schedule_date: [{ required: true, message: '请选择日期', trigger: 'change' }],
  shift_id: [{ required: true, message: '请选择班次', trigger: 'change' }]
}

const swapRules = {
  target_user_id: [{ required: true, message: '请选择调班对象', trigger: 'change' }],
  my_date: [{ required: true, message: '请选择我的排班日期', trigger: 'change' }],
  target_date: [{ required: true, message: '请选择对方排班日期', trigger: 'change' }]
}

const currentYear = computed(() => currentDate.value.getFullYear())
const currentMonth = computed(() => currentDate.value.getMonth())

const prevMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1)
  loadData()
}

const nextMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1)
  loadData()
}

const goToday = () => {
  currentDate.value = new Date()
  calendarDate.value = new Date()
  loadData()
}

const getScheduleForDate = (date) => {
  return list.value.filter(s => s.schedule_date === date)
}

const onShiftChange = (shiftId) => {
  const shift = shifts.value.find(s => s.id === shiftId)
  if (shift) {
    form.start_time = shift.start_time
    form.end_time = shift.end_time
  }
}

const loadShifts = async () => {
  try {
    const data = await getShifts()
    shifts.value = data || []
  } catch (e) {
    console.error(e)
  }
}

const loadEmployees = async () => {
  try {
    const data = await getEmployees({ size: 100 })
    employees.value = data.list || []
  } catch (e) {
    console.error(e)
  }
}

const loadData = async () => {
  loading.value = true
  try {
    const year = currentYear.value
    const month = currentMonth.value + 1
    const start_date = `${year}-${String(month).padStart(2, '0')}-01`
    const lastDay = new Date(year, month, 0).getDate()
    const end_date = `${year}-${String(month).padStart(2, '0')}-${lastDay}`
    
    const params = {
      ...searchForm,
      start_date,
      end_date,
      page: pagination.page,
      size: pagination.size
    }
    const data = await getSchedules(params)
    list.value = data.list || []
    pagination.total = data.total || 0
  } catch (e) {
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

const resetSearch = () => {
  searchForm.user_name = ''
  searchForm.shift_id = ''
  pagination.page = 1
  loadData()
}

const handleAdd = () => {
  isEdit.value = false
  Object.assign(form, {
    id: null,
    user_id: null,
    schedule_date: '',
    shift_id: null,
    remark: ''
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
    await ElMessageBox.confirm('确认删除该排班？', '提示', { type: 'warning' })
    await deleteSchedule(row.id)
    ElMessage.success('删除成功')
    loadData()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '删除失败')
  }
}

const handleSwap = () => {
  Object.assign(swapForm, {
    target_user_id: null,
    my_date: '',
    target_date: '',
    reason: ''
  })
  swapDialogVisible.value = true
}

const submitForm = async () => {
  if (!formRef.value) return
  await formRef.value.validate()
  
  submitting.value = true
  try {
    if (isEdit.value) {
      await updateSchedule(form.id, form)
      ElMessage.success('更新成功')
    } else {
      await createSchedule(form)
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

const submitSwap = async () => {
  if (!swapFormRef.value) return
  await swapFormRef.value.validate()
  
  swapSubmitting.value = true
  try {
    await swapSchedule(swapForm)
    ElMessage.success('调班申请已提交')
    swapDialogVisible.value = false
    loadData()
  } catch (e) {
    ElMessage.error(e.message || '提交失败')
  } finally {
    swapSubmitting.value = false
  }
}

onMounted(() => {
  loadShifts()
  loadEmployees()
  loadData()
})
</script>

<style scoped>
.schedule-container { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }

.calendar-card { margin-bottom: 20px; }
.calendar-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
.current-month { font-size: 16px; font-weight: 500; min-width: 120px; text-align: center; }

.calendar-cell { height: 80px; overflow: hidden; }
.date-number { font-size: 14px; margin-bottom: 4px; }
.schedule-item { font-size: 11px; color: white; padding: 2px 4px; border-radius: 2px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.search-card { margin-bottom: 20px; }
.data-card { margin-bottom: 20px; }

.pagination-container { margin-top: 20px; display: flex; justify-content: flex-end; }
</style>
