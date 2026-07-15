<script setup>
/**
 * ReaderAvatars — 钉钉式已阅头像堆叠组件
 *
 * Props:
 *   logType: String  - 'work_log' | 'visit_log' | 'share_log' | 'feedback'
 *   logId:   Number  - 日志 ID
 *   max:     Number  - 最多显示头像数（默认 3）
 *   size:    String  - 'sm' | 'md' (默认 md)
 *
 * Events:
 *   open-detail  - 点击头像堆叠触发，弹窗显示完整名单
 */
import { ref, onMounted, computed } from 'vue'
import { ElMessage } from 'element-plus'
import axios from 'axios'

const props = defineProps({
  logType: { type: String, required: true },
  logId: { type: [Number, String], required: true },
  max: { type: Number, default: 3 },
  size: { type: String, default: 'md' },
})

const emit = defineEmits(['open-detail'])

const readers = ref([])
const loading = ref(false)

const visibleReaders = computed(() => readers.value.slice(0, props.max))
const hiddenCount = computed(() => Math.max(0, readers.value.length - props.max))

const sizeClass = computed(() => props.size === 'sm' ? 'w-6 h-6 text-xs' : 'w-8 h-8 text-sm')

// 处理头像 URL（如果是相对路径，加后端 baseURL）
function avatarUrl(avatar) {
  if (!avatar) return ''
  if (avatar.startsWith('http') || avatar.startsWith('//')) return avatar
  const base = axios.defaults.baseURL || ''
  return base + avatar
}

// 头像 fallback 显示：取 name 第一个字符
function nameInitial(name) {
  if (!name) return '?'
  return name.charAt(0)
}

// 颜色生成（基于 user_id hash 一致）
function avatarColor(userId) {
  const colors = [
    'bg-pink-500', 'bg-purple-500', 'bg-indigo-500', 'bg-blue-500',
    'bg-cyan-500', 'bg-teal-500', 'bg-green-500', 'bg-yellow-500',
    'bg-orange-500', 'bg-red-500',
  ]
  return colors[Math.abs(userId || 0) % colors.length]
}

async function fetchReaders() {
  if (!props.logType || !props.logId) return
  loading.value = true
  try {
    const res = await axios.get(
      `/api/log-interactions/readers/${props.logType}/${props.logId}`
    )
    if (res.data?.code === 0) {
      readers.value = res.data.data || []
    } else if (res.data?.code === 403) {
      // 无权限查看 → 不显示
      readers.value = []
    }
  } catch (err) {
    console.warn('[ReaderAvatars] fetch failed', err)
    readers.value = []
  } finally {
    loading.value = false
  }
}

async function markAsRead() {
  if (!props.logType || !props.logId) return
  try {
    await axios.post('/api/log-interactions/read', {
      log_type: props.logType,
      log_id: Number(props.logId),
    })
    // 标记完刷新列表
    await fetchReaders()
  } catch (err) {
    // 403 等错误静默（不是所有人都能 mark read）
    if (err.response?.status !== 403) {
      console.warn('[ReaderAvatars] markAsRead failed', err)
    }
  }
}

function handleClick() {
  emit('open-detail', { logType: props.logType, logId: props.logId, readers: readers.value })
}

// 暴露方法给父组件
defineExpose({
  fetchReaders,
  markAsRead,
  readers,
})

onMounted(() => {
  fetchReaders()
  // 进入即标记已读（钉钉模式）
  markAsRead()
})
</script>

<template>
  <div class="inline-flex items-center" @click.stop>
    <div
      v-if="readers.length > 0"
      class="inline-flex items-center cursor-pointer hover:opacity-80 transition-opacity"
      @click="handleClick"
    >
      <!-- 头像堆叠 -->
      <div class="flex -space-x-2">
        <div
          v-for="(reader, idx) in visibleReaders"
          :key="reader.user_id"
          :class="['relative rounded-full border-2 border-white overflow-hidden flex items-center justify-center font-medium text-white shadow-sm', sizeClass, avatarColor(reader.user_id)]"
          :style="{ zIndex: visibleReaders.length - idx }"
          :title="reader.name + ' · ' + new Date(reader.read_at).toLocaleString('zh-CN')"
        >
          <img
            v-if="reader.avatar"
            :src="avatarUrl(reader.avatar)"
            :alt="reader.name"
            class="w-full h-full object-cover"
            @error="(e) => { e.target.style.display = 'none' }"
          />
          <span v-else>{{ nameInitial(reader.name) }}</span>
        </div>
        <!-- +N 指示器 -->
        <div
          v-if="hiddenCount > 0"
          :class="['rounded-full border-2 border-white bg-gray-200 text-gray-700 font-medium flex items-center justify-center shadow-sm', sizeClass]"
        >
          +{{ hiddenCount }}
        </div>
      </div>
      <!-- 阅读总数 -->
      <span class="ml-2 text-xs text-text-secondary whitespace-nowrap">
        {{ readers.length }} 人已阅
      </span>
    </div>
    <span v-else-if="loading" class="text-xs text-text-tertiary">加载中...</span>
  </div>
</template>