<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- 顶部 -->
    <div class="bg-white border-b px-4 py-3">
      <div class="flex justify-between items-center">
        <h1 class="text-lg font-bold text-gray-800">{{ $t('oa.workLogs') }}</h1>
        <button @click="openWriteLog" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-1">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          {{ $t('oa.writeLog') }}
        </button>
      </div>
    </div>

    <!-- 标签页 -->
    <div class="bg-white border-b sticky top-0 z-10">
      <div class="flex overflow-x-auto">
        <button v-for="tab in tabs" :key="tab.value" @click="currentTab = tab.value; logs.page = 1; loadLogs()" :class="['px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2', currentTab === tab.value ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500']">
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- 日志列表 -->
    <div class="p-4 space-y-3">
      <div v-if="logs.list.length === 0" class="text-center py-12 text-gray-400">
        {{ $t('oa.noLogRecords') }}
      </div>

      <div v-for="log in logs.list" :key="log.id" @click="viewLogDetail(log.id)" class="bg-white rounded-xl shadow-sm p-4 active:scale-98 transition-transform">
        <div class="flex items-start justify-between mb-2">
          <div class="font-medium text-gray-800">{{ getLogTitle(log) }}</div>
          <span :class="['px-2 py-0.5 text-xs rounded-full', log.status === 'submitted' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700']">
            {{ log.status === 'submitted' ? $t('oa.statusSubmitted') || '已提交' : $t('oa.statusDraft') || '草稿' }}
          </span>
        </div>
        <div class="text-sm text-gray-500 line-clamp-2 mb-2">{{ getLogSummary(log) }}</div>
        <div class="flex items-center justify-between text-xs text-gray-400 mb-2">
          <span>{{ formatDate(log.date) || log.created_at?.slice(0, 16) }}</span>
          <span v-if="log.template_name" class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded">{{ log.template_name }}</span>
        </div>
        <!-- 已阅头像（钉钉式） -->
        <ReaderAvatars
          log-type="work_log"
          :log-id="log.id"
          size="sm"
          @open-detail="handleReaderDetail"
        />
      </div>

      <!-- 加载更多 -->
      <div v-if="logs.list.length < logs.total" @click="logs.page++; loadLogs()" class="text-center py-4 text-blue-600 text-sm">
        {{ $t('oa.clickLoadMore') }}
      </div>
    </div>

    <!-- 写日志弹窗 -->
    <div v-if="showWriteLog" class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center" @click.self="showWriteLog = false">
      <div class="bg-white rounded-t-2xl sm:rounded-xl w-full sm:max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-4 py-3 flex justify-between items-center z-10">
          <h3 class="font-bold">{{ $t('oa.writeLog') }}</h3>
          <button type="button" @click="showWriteLog = false" class="text-gray-400">✕</button>
        </div>
        <div class="p-4 space-y-4">
          <!-- 模板选择 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('oa.selectTemplate') || '选择模板' }}</label>
            <select v-model="selectedTemplateId" @change="onTemplateChange" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
              <option :value="null">{{ $t('oa.noTemplate') || '不使用模板' }}</option>
              <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">{{ tpl.name }}</option>
            </select>
          </div>

          <!-- 日期 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('oa.dateLabel') || '日期' }} <span class="text-red-500">*</span></label>
            <div class="relative">
              <div @click="$refs.dateInput.showPicker?.()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm cursor-pointer flex justify-between items-center">
                <span>{{ formatDate(formData.date) || $t('oa.dateLabel') }}</span>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
              </div>
              <input ref="dateInput" v-model="formData.date" type="date" class="absolute inset-0 opacity-0 cursor-pointer" />
            </div>
          </div>

          <!-- 动态模板字段 -->
          <template v-if="currentFields.length > 0">
            <div v-for="field in currentFields" :key="field.name">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ field.label }}
                <span v-if="field.required" class="text-red-500">*</span>
              </label>

              <!-- text -->
              <input v-if="field.type === 'text'" v-model="formContent[field.name]" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />

              <!-- number -->
              <input v-else-if="field.type === 'number'" v-model.number="formContent[field.name]" type="number" :min="field.min" :max="field.max" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />

              <!-- textarea -->
              <textarea v-else-if="field.type === 'textarea'" v-model="formContent[field.name]" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none"></textarea>

              <!-- date -->
              <input v-else-if="field.type === 'date'" v-model="formContent[field.name]" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />

              <!-- time -->
              <input v-else-if="field.type === 'time'" v-model="formContent[field.name]" type="time" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />

              <!-- time_range -->
              <div v-else-if="field.type === 'time_range'" class="flex gap-2 items-center">
                <input v-model="formContent[field.name + '_start']" type="time" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                <span class="text-gray-400">~</span>
                <input v-model="formContent[field.name + '_end']" type="time" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" />
              </div>

              <!-- select -->
              <select v-else-if="field.type === 'select'" v-model="formContent[field.name]" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">请选择</option>
                <option v-for="opt in (field.options || [])" :key="opt" :value="opt">{{ opt }}</option>
              </select>

              <!-- radio -->
              <div v-else-if="field.type === 'radio'" class="flex flex-wrap gap-3">
                <label v-for="opt in (field.options || [])" :key="opt" class="flex items-center gap-1 text-sm">
                  <input type="radio" v-model="formContent[field.name]" :value="opt" class="w-4 h-4" />
                  {{ opt }}
                </label>
              </div>

              <!-- checkbox -->
              <div v-else-if="field.type === 'checkbox'" class="flex flex-wrap gap-3">
                <label v-for="opt in (field.options || [])" :key="opt" class="flex items-center gap-1 text-sm">
                  <input type="checkbox" :value="opt" v-model="formContent[field.name]" class="w-4 h-4" />
                  {{ opt }}
                </label>
              </div>

              <!-- location -->
              <button v-else-if="field.type === 'location'" type="button" @click="getLocation(field.name)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-left flex justify-between items-center">
                <span>{{ formContent[field.name] || '点击获取位置' }}</span>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
              </button>

              <!-- image -->
              <div v-else-if="field.type === 'image'">
                <div class="grid grid-cols-4 gap-2">
                  <div v-for="(img, idx) in (formContent[field.name] || [])" :key="idx" class="relative aspect-square">
                    <img :src="img" class="w-full h-full object-cover rounded" />
                    <button type="button" @click="removeImage(field.name, idx)" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center">×</button>
                  </div>
                  <label v-if="!formContent[field.name] || formContent[field.name].length < (field.maxCount || 9)" class="aspect-square border-2 border-dashed border-gray-200 rounded-lg flex items-center justify-center cursor-pointer hover:border-blue-400">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <input type="file" accept="image/*" multiple @change="handleImageUpload($event, field.name)" class="hidden" />
                  </label>
                </div>
              </div>

              <!-- participants (跳过，下面单独处理) -->
              <button v-else-if="field.type === 'participants'" type="button" @click="openParticipantPicker" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-left flex justify-between items-center">
                <span>{{ formData.participants.length ? `已选 ${formData.participants.length} 人` : '选择参与人' }}</span>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>

              <!-- rating -->
              <div v-else-if="field.type === 'rating'" class="flex gap-1">
                <button v-for="star in (field.maxRating || 5)" :key="star" type="button" @click="formContent[field.name] = star" :class="['text-2xl', star <= (formContent[field.name] || 0) ? 'text-yellow-400' : 'text-gray-300']">★</button>
              </div>

              <!-- fallback text -->
              <input v-else v-model="formContent[field.name]" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
          </template>

          <!-- 无模板时的简单表单 -->
          <template v-if="currentFields.length === 0">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('oa.logTitleLabel') || '标题' }} <span class="text-red-500">*</span></label>
              <input v-model="formContent.title" type="text" :placeholder="$t('oa.titlePlaceholder') || '请输入标题'" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('oa.logContentLabel') || '内容' }} <span class="text-red-500">*</span></label>
              <textarea v-model="formContent.content" rows="4" :placeholder="$t('oa.contentPlaceholder') || '请输入内容'" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none"></textarea>
            </div>
            <!-- 参与人 -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">参与人</label>
              <button type="button" @click="openParticipantPicker" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-left flex justify-between items-center">
                <span>{{ formData.participants.length ? `已选 ${formData.participants.length} 人` : '选择参与人' }}</span>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
            </div>
          </template>

          <!-- 提交按钮 -->
          <button type="button" @click="submitLog" :disabled="submitting" class="w-full bg-blue-600 text-white py-3 rounded-lg font-medium disabled:opacity-50">
            {{ submitting ? $t('common.submitting') || '提交中...' : $t('common.submit') || '提交' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 参与人选择弹窗 -->
    <div v-if="showParticipantModal" class="fixed inset-0 bg-black/50 z-[60] flex items-end sm:items-center justify-center" @click.self="showParticipantModal = false">
      <div class="bg-white rounded-t-2xl sm:rounded-xl w-full sm:max-w-md max-h-[70vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-4 py-3 flex justify-between items-center z-10">
          <h3 class="font-bold text-sm">选择参与人（自己及下级）</h3>
          <button type="button" @click="showParticipantModal = false" class="text-gray-400">✕</button>
        </div>
        <div class="p-4">
          <div v-if="availableUsers.length === 0" class="text-center py-8 text-gray-400 text-sm">暂无可选人员</div>
          <div
            v-for="user in availableUsers"
            :key="user.id"
            class="flex items-center gap-3 py-3 border-b border-gray-50"
            :style="{ paddingLeft: (user.level || 0) * 16 + 'px' }"
          >
            <input
              type="checkbox"
              :value="user.id"
              v-model="tempParticipants"
              class="w-4 h-4 text-blue-600 rounded"
            />
            <div class="flex-1 min-w-0">
              <span class="text-sm">{{ user.name }}</span>
              <span v-if="user.department" class="text-xs text-gray-400 ml-1">({{ user.department }})</span>
            </div>
            <span v-if="user.is_self" class="text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded shrink-0">自己</span>
            <span v-else-if="user.level === 1" class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded shrink-0">直接下级</span>
            <span v-else-if="user.level > 1" class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded shrink-0">{{ user.level }}级下级</span>
          </div>
        </div>
        <div class="sticky bottom-0 bg-white border-t px-4 py-3">
          <button type="button" @click="confirmParticipants" class="w-full py-3 bg-blue-600 text-white rounded-lg font-medium text-sm">
            确认（已选 {{ tempParticipants.length }} 人）
          </button>
        </div>
      </div>
    </div>
  <!-- 已阅名单弹窗 -->
    <div v-if="showReadersModal" class="fixed inset-0 bg-black/50 z-[70] flex items-end sm:items-center justify-center" @click.self="showReadersModal = false">
      <div class="bg-white rounded-t-2xl sm:rounded-xl w-full sm:max-w-md max-h-[70vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-4 py-3 flex justify-between items-center z-10">
          <h3 class="font-bold text-sm">已阅名单（{{ readersList.length }} 人）</h3>
          <button type="button" @click="showReadersModal = false" class="text-gray-400">✕</button>
        </div>
        <div class="p-4 space-y-2">
          <div v-if="readersList.length === 0" class="text-center py-8 text-gray-400 text-sm">暂无已阅</div>
          <div v-for="r in readersList" :key="r.user_id" class="flex items-center gap-3 py-2 border-b border-gray-50">
            <div :class="['w-9 h-9 rounded-full flex items-center justify-center text-white font-medium overflow-hidden', avatarColor(r.user_id)]">
              <img v-if="r.avatar" :src="avatarUrl(r.avatar)" class="w-full h-full object-cover" />
              <span v-else>{{ (r.name || '?').charAt(0) }}</span>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm text-gray-800">{{ r.name }}</div>
              <div class="text-xs text-gray-400">{{ new Date(r.read_at).toLocaleString('zh-CN') }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user'
import api from '../../services/api'
import ReaderAvatars from '../../components/ReaderAvatars.vue'
import axios from 'axios'

const { t, locale } = useI18n()
const userStore = useUserStore()

// ─── 日志列表 ───
const logs = ref({ list: [], total: 0, page: 1, size: 20 })
const currentTab = ref('my')

const tabs = computed(() => [
  { label: t('oa.myLogsTab'), value: 'my' },
  { label: t('oa.subordinateLogs') || '下级的', value: 'subordinate' },
  { label: t('oa.receivedLogs') || '收到的', value: 'received' }
])

// ─── 写日志 ───
const showWriteLog = ref(false)
const submitting = ref(false)
const templates = ref([])
const selectedTemplateId = ref(null)
const formData = ref({
  date: new Date().toISOString().split('T')[0],
  participants: [],
  recipients: []
})
const formContent = ref({})

// ─── 参与人 ───
const showParticipantModal = ref(false)
const availableUsers = ref([])
const tempParticipants = ref([])

// ─── 已阅名单 ───
const showReadersModal = ref(false)
const readersList = ref([])

function handleReaderDetail({ readers }) {
  readersList.value = readers || []
  showReadersModal.value = true
}

function avatarUrl(avatar) {
  if (!avatar) return ''
  if (avatar.startsWith('http') || avatar.startsWith('//')) return avatar
  const base = axios.defaults.baseURL || ''
  return base + avatar
}

function avatarColor(userId) {
  const colors = [
    'bg-pink-500', 'bg-purple-500', 'bg-indigo-500', 'bg-blue-500',
    'bg-cyan-500', 'bg-teal-500', 'bg-green-500', 'bg-yellow-500',
    'bg-orange-500', 'bg-red-500',
  ]
  return colors[Math.abs(userId || 0) % colors.length]
}

// ─── 当前模板字段 ───
const currentFields = computed(() => {
  if (!selectedTemplateId.value) return []
  const tpl = templates.value.find(t => t.id === selectedTemplateId.value)
  if (!tpl || !tpl.fields) return []
  try {
    const fields = typeof tpl.fields === 'string' ? JSON.parse(tpl.fields) : tpl.fields
    return Array.isArray(fields) ? fields : []
  } catch {
    return []
  }
})

// ─── 加载数据 ───
async function loadTemplates() {
  try {
    const res = await api.get('/oa/work-log-templates')
    if (res.code === 0) {
      templates.value = res.data || []
    }
  } catch (err) {
    console.error('Failed to load templates:', err)
  }
}

async function loadUsers() {
  try {
    const res = await api.get('/users/subordinates')
    if (res.code === 0) {
      availableUsers.value = res.data || []
    }
  } catch {
    try {
      const res = await api.get('/users/list')
      if (res.code === 0) {
        availableUsers.value = res.data || []
      }
    } catch {}
  }
}

async function loadLogs() {
  try {
    const params = {
      page: logs.value.page,
      size: logs.value.size,
      type: currentTab.value
    }
    const res = await api.get('/oa/work-logs', { params })
    if (res.code === 0) {
      if (logs.value.page === 1) {
        logs.value = { ...res.data, page: 1, size: 20 }
      } else {
        logs.value.list.push(...(res.data.list || []))
        logs.value.total = res.data.total
      }
    }
  } catch (err) {
    console.error('Failed to load logs:', err)
  }
}

onMounted(() => {
  loadTemplates()
  loadUsers()
  loadLogs()
})

// ─── 写日志操作 ───
function openWriteLog() {
  selectedTemplateId.value = null
  formContent.value = {}
  formData.value = {
    date: new Date().toISOString().split('T')[0],
    participants: [userStore.userId],
    recipients: []
  }
  showWriteLog.value = true
}

function onTemplateChange() {
  formContent.value = {}
  // 初始化 checkbox 类型字段为空数组，image 类型为空数组
  for (const field of currentFields.value) {
    if (field.type === 'checkbox') {
      formContent.value[field.name] = []
    } else if (field.type === 'image') {
      formContent.value[field.name] = []
    }
  }
}

async function submitLog() {
  if (!formData.value.date) {
    alert('请选择日期')
    return
  }

  // 验证必填字段
  if (currentFields.value.length > 0) {
    for (const field of currentFields.value) {
      if (field.required && !formContent.value[field.name]) {
        alert(`请填写「${field.label}」`)
        return
      }
    }
  } else {
    if (!formContent.value.title) {
      alert('请输入标题')
      return
    }
  }

  submitting.value = true
  try {
    const payload = {
      template_id: selectedTemplateId.value,
      date: formData.value.date,
      content: formContent.value,
      participants: formData.value.participants,
      recipients: formData.value.recipients
    }

    const res = await api.post('/oa/work-logs', payload)
    if (res.code === 0) {
      showWriteLog.value = false
      logs.value.page = 1
      loadLogs()
    } else {
      alert(res.message || '提交失败')
    }
  } catch (err) {
    alert(err.message || '提交失败')
  } finally {
    submitting.value = false
  }
}

// ─── 参与人选择 ───
function openParticipantPicker() {
  const existing = formData.value.participants || []
  if (existing.length === 0) {
    tempParticipants.value = userStore.userId ? [userStore.userId] : []
  } else {
    tempParticipants.value = [...existing]
  }
  showParticipantModal.value = true
}

function confirmParticipants() {
  formData.value.participants = [...tempParticipants.value]
  showParticipantModal.value = false
}

// ─── 图片上传 ───
async function handleImageUpload(event, fieldName) {
  const files = Array.from(event.target.files)
  if (!files.length) return
  if (!formContent.value[fieldName]) formContent.value[fieldName] = []

  for (const file of files) {
    const fd = new FormData()
    fd.append('file', file)
    try {
      const res = await api.post('/upload', fd)
      if (res.code === 0) {
        formContent.value[fieldName].push(res.data.url)
      }
    } catch {}
  }
  event.target.value = ''
}

function removeImage(fieldName, index) {
  formContent.value[fieldName].splice(index, 1)
}

// ─── 定位 ───
function getLocation(fieldName) {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        formContent.value[fieldName] = `${pos.coords.latitude.toFixed(6)}, ${pos.coords.longitude.toFixed(6)}`
        formData.value.gps_lat = pos.coords.latitude
        formData.value.gps_lng = pos.coords.longitude
      },
      (err) => alert('定位失败: ' + err.message)
    )
  } else {
    alert('浏览器不支持定位')
  }
}

// ─── 日志列表辅助 ───
function getLogTitle(log) {
  if (log.title) return log.title
  try {
    const content = typeof log.content === 'string' ? JSON.parse(log.content) : log.content
    return content?.title || `日志 #${log.id}`
  } catch {
    return `日志 #${log.id}`
  }
}

function getLogSummary(log) {
  try {
    const content = typeof log.content === 'string' ? JSON.parse(log.content) : log.content
    if (typeof content === 'string') return content
    // 取第一个 textarea 类型的值作为摘要
    const values = Object.values(content || {}).filter(v => typeof v === 'string' && v.length > 10)
    return values[0] || Object.values(content || {}).join(' / ')
  } catch {
    return typeof log.content === 'string' ? log.content : ''
  }
}

function viewLogDetail(id) {
  console.log('View log:', id)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  const lang = locale.value || 'zh'
  if (lang.startsWith('zh')) {
    return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日`
  }
  const months = ['January','February','March','April','May','June','July','August','September','October','November','December']
  return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`
}
</script>
