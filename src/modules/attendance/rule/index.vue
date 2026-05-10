<template>
  <div class="module-page module-attendance-rule">
    <el-card>
      <template #header>
        <div class="card-header">
          <span class="module-title">考勤规则</span>
          <el-button type="primary" @click="handleAdd">新增规则</el-button>
        </div>
      </template>

      <!-- 规则列表 -->
      <el-table :data="ruleList" stripe style="width: 100%">
        <el-table-column prop="ruleName" label="规则名称" width="180" />
        <el-table-column prop="ruleType" label="规则类型" width="120">
          <template #default="{ row }">
            <el-tag>{{ getRuleTypeText(row.ruleType) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="workStartTime" label="上班时间" width="100" />
        <el-table-column prop="workEndTime" label="下班时间" width="100" />
        <el-table-column prop="lateThreshold" label="迟到阈值" width="100">
          <template #default="{ row }">{{ row.lateThreshold }}分钟</template>
        </el-table-column>
        <el-table-column prop="earlyThreshold" label="早退阈值" width="100">
          <template #default="{ row }">{{ row.earlyThreshold }}分钟</template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="80">
          <template #default="{ row }">
            <el-switch v-model="row.status" active-text="启用" inactive-text="停用" @change="handleStatusChange(row)" />
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="150" />
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" size="small" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 新增/编辑对话框 -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑规则' : '新增规则'" width="500px">
      <el-form :model="ruleForm" label-width="100px">
        <el-form-item label="规则名称">
          <el-input v-model="ruleForm.ruleName" placeholder="请输入规则名称" />
        </el-form-item>
        <el-form-item label="规则类型">
          <el-select v-model="ruleForm.ruleType" placeholder="请选择规则类型">
            <el-option label="固定班次" value="fixed" />
            <el-option label="弹性班次" value="flexible" />
            <el-option label="综合工时" value="comprehensive" />
          </el-select>
        </el-form-item>
        <el-form-item label="上班时间">
          <el-time-picker v-model="ruleForm.workStartTime" format="HH:mm" value-format="HH:mm" placeholder="选择上班时间" style="width: 100%" />
        </el-form-item>
        <el-form-item label="下班时间">
          <el-time-picker v-model="ruleForm.workEndTime" format="HH:mm" value-format="HH:mm" placeholder="选择下班时间" style="width: 100%" />
        </el-form-item>
        <el-form-item label="迟到阈值">
          <el-input-number v-model="ruleForm.lateThreshold" :min="0" :max="120" /> 分钟
        </el-form-item>
        <el-form-item label="早退阈值">
          <el-input-number v-model="ruleForm.earlyThreshold" :min="0" :max="120" /> 分钟
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="ruleForm.remark" type="textarea" :rows="3" placeholder="请输入备注" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'

const dialogVisible = ref(false)
const isEdit = ref(false)

const ruleList = ref([
  { ruleName: '标准工时制', ruleType: 'fixed', workStartTime: '09:00', workEndTime: '18:00', lateThreshold: 15, earlyThreshold: 15, status: true, remark: '默认考勤规则' },
  { ruleName: '弹性工作制', ruleType: 'flexible', workStartTime: '10:00', workEndTime: '19:00', lateThreshold: 30, earlyThreshold: 30, status: true, remark: '适用于技术部' },
  { ruleName: '销售外勤制', ruleType: 'comprehensive', workStartTime: '08:00', workEndTime: '20:00', lateThreshold: 0, earlyThreshold: 0, status: false, remark: '外勤人员使用' },
  { ruleName: '行政班次', ruleType: 'fixed', workStartTime: '08:30', workEndTime: '17:30', lateThreshold: 10, earlyThreshold: 10, status: true, remark: '行政人员专用' }
])

const ruleForm = reactive({
  ruleName: '',
  ruleType: 'fixed',
  workStartTime: '',
  workEndTime: '',
  lateThreshold: 15,
  earlyThreshold: 15,
  remark: ''
})

const getRuleTypeText = (type) => {
  const map = { fixed: '固定班次', flexible: '弹性班次', comprehensive: '综合工时' }
  return map[type] || type
}

const handleAdd = () => {
  isEdit.value = false
  Object.assign(ruleForm, { ruleName: '', ruleType: 'fixed', workStartTime: '', workEndTime: '', lateThreshold: 15, earlyThreshold: 15, remark: '' })
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  Object.assign(ruleForm, row)
  dialogVisible.value = true
}

const handleSave = () => {
  if (!ruleForm.ruleName) { ElMessage.warning('请输入规则名称'); return }
  ElMessage.success(isEdit.value ? '修改成功' : '添加成功')
  dialogVisible.value = false
}

const handleDelete = async (row) => {
  await ElMessageBox.confirm(`确定删除规则 "${row.ruleName}" 吗？`, '提示', { type: 'warning' })
  ElMessage.success('删除成功')
}

const handleStatusChange = (row) => {
  ElMessage.success(`${row.ruleName} 已${row.status ? '启用' : '停用'}`)
}
</script>

<style scoped>
.module-page { padding: 20px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.module-title { font-size: 18px; font-weight: 600; }
</style>
