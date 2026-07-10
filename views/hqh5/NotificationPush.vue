<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- 顶部 -->
    <div class="bg-white p-4 sticky top-0 z-10 shadow-sm flex items-center justify-between">
      <button @click="router.back()" class="text-gray-600">‹ 返回</button>
      <h1 class="text-lg font-bold text-gray-800">通知推送</h1>
      <button @click="showPush = true" class="text-purple-600 text-sm font-medium">+ 新建</button>
    </div>

    <!-- 统计 -->
    <div class="p-4 grid grid-cols-3 gap-3">
      <div class="bg-white rounded-xl p-3 text-center shadow-sm">
        <div class="text-2xl font-bold text-purple-600">{{ stats.total }}</div>
        <div class="text-xs text-gray-500 mt-1">总通知</div>
      </div>
      <div class="bg-white rounded-xl p-3 text-center shadow-sm">
        <div class="text-2xl font-bold text-green-600">{{ stats.sent }}</div>
        <div class="text-xs text-gray-500 mt-1">已发送</div>
      </div>
      <div class="bg-white rounded-xl p-3 text-center shadow-sm">
        <div class="text-2xl font-bold text-red-600">{{ stats.urgent }}</div>
        <div class="text-xs text-gray-500 mt-1">紧急</div>
      </div>
    </div>

    <!-- 列表 -->
    <div class="px-4 space-y-3">
      <div v-if="loading" class="text-center py-8 text-gray-400">加载中...</div>
      <div v-else-if="list.length === 0" class="text-center py-12 text-gray-400 bg-white rounded-xl">
        暂无通知
      </div>
      <div
        v-for="n in list"
        :key="n.id"
        class="bg-white rounded-xl p-4 shadow-sm"
      >
        <div class="flex items-start justify-between mb-2">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                 :class="{
                   'bg-red-100 text-red-600': n.type === 'urgent',
                   'bg-blue-100 text-blue-600': n.type === 'system',
                   'bg-green-100 text-green-600': n.type === 'activity',
                   'bg-purple-100 text-purple-600': n.type === 'announcement'
                 }">
              {{ n.type === 'urgent' ? '🚨' : n.type === 'system' ? '⚙️' : n.type === 'activity' ? '🎯' : '📢' }}
            </div>
            <span class="text-sm font-medium text-gray-800">{{ n.title }}</span>
          </div>
          <span
            class="text-xs px-2 py-0.5 rounded-full"
            :class="{
              'bg-yellow-100 text-yellow-700': n.status === 'draft',
              'bg-green-100 text-green-700': n.status === 'sent',
              'bg-blue-100 text-blue-700': n.status === 'scheduled'
            }"
          >{{ statusText(n.status) }}</span>
        </div>
        <p class="text-xs text-gray-500 mb-2">{{ n.content }}</p>
        <div class="flex items-center justify-between text-xs text-gray-400">
          <span>📍 {{ targetText(n.target) }}</span>
          <span>{{ n.sent_at ? formatTime(n.sent_at) : '未发送' }}</span>
        </div>
      </div>
    </div>

    <!-- 推送通知 -->
    <el-dialog v-model="showPush" title="发送通知" width="95%" top="5vh">
      <el-form :model="newNotif" label-width="80px">
        <el-form-item label="标题">
          <el-input v-model="newNotif.title" placeholder="通知标题" />
        </el-form-item>
        <el-form-item label="内容">
          <el-input v-model="newNotif.content" type="textarea" :rows="4" />
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="newNotif.type">
            <el-option label="系统通知" value="system" />
            <el-option label="活动通知" value="activity" />
            <el-option label="公告" value="announcement" />
            <el-option label="紧急" value="urgent" />
          </el-select>
        </el-form-item>
        <el-form-item label="接收对象">
          <el-select v-model="newNotif.target">
            <el-option label="所有人" value="all" />
            <el-option label="企业员工" value="employee" />
            <el-option label="管理员" value="admin" />
          </el-select>
        </el-form-item>
        <el-form-item label="优先级">
          <el-radio-group v-model="newNotif.priority">
            <el-radio-button label="low">低</el-radio-button>
            <el-radio-button label="medium">中</el-radio-button>
            <el-radio-button label="high">高</el-radio-button>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPush = false">取消</el-button>
        <el-button type="primary" @click="submitPush" :loading="submitting">立即推送</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'

const router = useRouter()
const list = ref([])
const stats = ref({ total: 0, sent: 0, urgent: 0 })
const loading = ref(false)
const showPush = ref(false)
const submitting = ref(false)
const newNotif = ref({ title: '', content: '', type: 'system', target: 'all', priority: 'medium' })

const statusText = (s) => ({ draft: '草稿', sent: '已发送', scheduled: '定时' }[s] || s)
const targetText = (t) => ({ all: '所有人', employee: '企业员工', admin: '管理员', specific: '指定用户' }[t] || t)
const formatTime = (d) => d ? new Date(d).toLocaleString('zh-CN') : ''

const loadList = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/hqh5/notifications/list?status=sent')
    list.value = res.data?.data || []
    stats.value.total = list.value.length
    stats.value.sent = list.value.filter(n => n.status === 'sent').length
    stats.value.urgent = list.value.filter(n => n.type === 'urgent').length
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const submitPush = async () => {
  if (!newNotif.value.title || !newNotif.value.content) {
    ElMessage.warning('标题和内容必填')
    return
  }
  submitting.value = true
  try {
    const res = await axios.post('/api/hqh5/notifications/push', {
      ...newNotif.value,
      sender_id: 99,
      sender_name: '管理员'
    })
    if (res.data?.code === 0) {
      ElMessage.success(res.data.message || '推送成功')
      showPush.value = false
      newNotif.value = { title: '', content: '', type: 'system', target: 'all', priority: 'medium' }
      loadList()
    }
  } catch (e) {
    ElMessage.error('推送失败')
  } finally {
    submitting.value = false
  }
}

onMounted(loadList)
</script>