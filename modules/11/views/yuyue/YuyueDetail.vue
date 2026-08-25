<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const loading = ref(false)
const yuyue = ref(null)

const statusTypeMap = {
  pending: 'warning',
  confirmed: 'primary',
  completed: 'success',
  cancelled: 'info',
  no_show: 'danger',
}

const statusLabelMap = {
  pending: '待确认',
  confirmed: '已确认',
  completed: '已完成',
  cancelled: '已取消',
  no_show: '未到店',
}

function getStatusType(status) {
  return statusTypeMap[status] || 'info'
}

function getStatusLabel(status) {
  return statusLabelMap[status] || status
}

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { hour12: false })
}

function formatTime(t) {
  if (!t) return '-'
  return t.substring(0, 5)
}

async function fetchDetail() {
  loading.value = true
  try {
    const res = await api.get(`/yuyue/${route.params.id}`)
    if (res.code === 0) {
      yuyue.value = res.data
    } else {
      ElMessage.error(res.message || '获取预约详情失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '获取预约详情失败')
  } finally {
    loading.value = false
  }
}

async function confirmYuyue() {
  if (!confirm(`确定确认预约 ${yuyue.value.yuyue_no}？`)) return
  try {
    const res = await api.put(`/yuyue/${yuyue.value.id}/status`, { action: 'confirm' })
    if (res.code === 0) {
      ElMessage.success('预约已确认')
      fetchDetail()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function completeYuyue() {
  if (!confirm(`确定完成预约 ${yuyue.value.yuyue_no}？`)) return
  try {
    const res = await api.put(`/yuyue/${yuyue.value.id}/status`, { action: 'complete' })
    if (res.code === 0) {
      ElMessage.success('预约已完成')
      fetchDetail()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function cancelYuyue() {
  if (!confirm(`确定取消预约 ${yuyue.value.yuyue_no}？`)) return
  try {
    const res = await api.put(`/yuyue/${yuyue.value.id}/status`, { action: 'cancel' })
    if (res.code === 0) {
      ElMessage.success('预约已取消')
      fetchDetail()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function noShowYuyue() {
  if (!confirm(`确定标记 ${yuyue.value.yuyue_no} 为未到店？`)) return
  try {
    const res = await api.put(`/yuyue/${yuyue.value.id}/status`, { action: 'no_show' })
    if (res.code === 0) {
      ElMessage.success('已标记为未到店')
      fetchDetail()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '操作失败')
  }
}

function canShowConfirm(status) { return status === 'pending' }
function canShowComplete(status) { return status === 'confirmed' }
function canShowCancel(status) { return status === 'pending' || status === 'confirmed' }
function canShowNoShow(status) { return status === 'confirmed' }

onMounted(() => {
  fetchDetail()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader 
      title="预约详情" 
      :subtitle="yuyue ? yuyue.yuyue_no : ''" 
      :back="() => router.push('/yuyue')"
    />

    <div v-loading="loading" class="max-w-4xl">
      <!-- 基本信息 -->
      <div class="bg-white rounded-xl shadow-sm p-6 mb-4">
        <h3 class="text-lg font-medium mb-4">基本信息</h3>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm text-gray-500">预约单号</label>
            <div class="mt-1">{{ yuyue?.yuyue_no || '-' }}</div>
          </div>
          <div>
            <label class="text-sm text-gray-500">状态</label>
            <div class="mt-1">
              <el-tag :type="getStatusType(yuyue?.status)" size="small">
                {{ getStatusLabel(yuyue?.status) }}
              </el-tag>
            </div>
          </div>
          <div>
            <label class="text-sm text-gray-500">预约人姓名</label>
            <div class="mt-1">{{ yuyue?.member_name || '-' }}</div>
          </div>
          <div>
            <label class="text-sm text-gray-500">预约人电话</label>
            <div class="mt-1">{{ yuyue?.member_phone || '-' }}</div>
          </div>
          <div>
            <label class="text-sm text-gray-500">服务类型</label>
            <div class="mt-1">{{ yuyue?.service_type || '-' }}</div>
          </div>
          <div>
            <label class="text-sm text-gray-500">预约项目</label>
            <div class="mt-1">{{ yuyue?.service_item || '-' }}</div>
          </div>
          <div>
            <label class="text-sm text-gray-500">门店名称</label>
            <div class="mt-1">{{ yuyue?.store_name || '-' }}</div>
          </div>
          <div>
            <label class="text-sm text-gray-500">服务员工</label>
            <div class="mt-1">{{ yuyue?.staff_name || '-' }}</div>
          </div>
          <div>
            <label class="text-sm text-gray-500">预约日期</label>
            <div class="mt-1">{{ yuyue?.yuyue_date || '-' }}</div>
          </div>
          <div>
            <label class="text-sm text-gray-500">预约时间</label>
            <div class="mt-1">{{ formatTime(yuyue?.yuyue_time) }} ({{ yuyue?.duration }}分钟)</div>
          </div>
          <div>
            <label class="text-sm text-gray-500">备注</label>
            <div class="mt-1">{{ yuyue?.remark || '-' }}</div>
          </div>
          <div>
            <label class="text-sm text-gray-500">管理员备注</label>
            <div class="mt-1">{{ yuyue?.admin_remark || '-' }}</div>
          </div>
        </div>
      </div>

      <!-- 时间线 -->
      <div class="bg-white rounded-xl shadow-sm p-6 mb-4">
        <h3 class="text-lg font-medium mb-4">时间记录</h3>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm text-gray-500">创建时间</label>
            <div class="mt-1">{{ formatDate(yuyue?.created_at) }}</div>
          </div>
          <div>
            <label class="text-sm text-gray-500">确认时间</label>
            <div class="mt-1">{{ formatDate(yuyue?.confirmed_at) }}</div>
          </div>
          <div>
            <label class="text-sm text-gray-500">完成时间</label>
            <div class="mt-1">{{ formatDate(yuyue?.completed_at) }}</div>
          </div>
          <div>
            <label class="text-sm text-gray-500">取消时间</label>
            <div class="mt-1">{{ formatDate(yuyue?.cancelled_at) }}</div>
          </div>
        </div>
      </div>

      <!-- 操作 -->
      <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-medium mb-4">操作</h3>
        <div class="flex flex-wrap gap-2">
          <el-button v-if="canShowConfirm(yuyue?.status)" type="success" @click="confirmYuyue">确认预约</el-button>
          <el-button v-if="canShowComplete(yuyue?.status)" type="primary" @click="completeYuyue">完成预约</el-button>
          <el-button v-if="canShowNoShow(yuyue?.status)" type="warning" @click="noShowYuyue">标记未到店</el-button>
          <el-button v-if="canShowCancel(yuyue?.status)" type="warning" @click="cancelYuyue">取消预约</el-button>
        </div>
      </div>
    </div>
  </div>
</template>