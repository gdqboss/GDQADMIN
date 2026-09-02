<!--
  WorkLogList - 工作日志共享列表组件 (2026-09-02 立)

  三端共用: 列表 + 筛选 + 分页 + 互动 (点赞 / 评论)
  Props:
   - logs: 日志数组
   - users: 用户列表 (用于解析 creator_name / recipients)
   - api: axios instance
   - adapter: 'gdqadmin' | 'minip' | 'labor'
   - loading: 加载状态
  Emits:
   - item-click(log) - 点击日志项
   - refresh()        - 刷新
-->
<template>
  <div :class="style.cardClass">
    <!-- 筛选 -->
    <div v-if="showFilters" class="mb-4 flex flex-wrap gap-2">
      <select v-model="filter.status" @change="$emit('refresh')" :class="style.inputClass + ' w-auto'">
        <option value="">全部状态</option>
        <option value="submitted">已提交</option>
        <option value="draft">草稿</option>
        <option value="reviewed">已审核</option>
      </select>
      <select v-model="filter.type" @change="$emit('refresh')" :class="style.inputClass + ' w-auto'">
        <option value="">全部类型</option>
        <option value="work">工作日志</option>
        <option value="visit">">拜访记录</option>
        <option value="share">分享</option>
        <option value="feedback">反馈</option>
      </select>
      <input
        v-model="filter.date_from"
        type="date"
        @change="$emit('refresh')"
        :class="style.inputClass + ' w-auto'"
        placeholder="开始"
      />
      <input
        v-model="filter.date_to"
        type="date"
        @change="$emit('refresh')"
        :class="style.inputClass + ' w-auto'"
        placeholder="结束"
      />
    </div>

    <!-- 加载中 -->
    <div v-if="loading" class="py-8 text-center text-slate-400 text-sm">加载中...</div>

    <!-- 空 -->
    <div v-else-if="!logs || logs.length === 0" class="py-8 text-center text-slate-400 text-sm">
      暂无工作日志
    </div>

    <!-- 列表 -->
    <div v-else>
      <div
        v-for="log in logs"
        :key="log.id"
        :class="style.listItemClass + ' cursor-pointer'"
        @click="$emit('item-click', log)"
      >
        <!-- 头部: 创建者 + 时间 + 状态 -->
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary text-sm font-bold">
              {{ getUserInitial(log.creator_name || getUserName(log.user_id)) }}
            </div>
            <div>
              <div class="text-sm font-medium text-slate-900">{{ log.creator_name || getUserName(log.user_id) }}</div>
              <div class="text-xs text-slate-500">{{ formatDate(log.created_at || log.submit_date) }}</div>
            </div>
          </div>
          <span :class="['px-2 py-1 rounded-full text-xs', statusClass(log.status)]">
            {{ statusLabel(log.status) }}
          </span>
        </div>

        <!-- 摘要: 模板字段 + 内容 -->
        <div v-if="log.summary" class="text-sm text-slate-700 line-clamp-2 mb-2">{{ log.summary }}</div>
        <div v-else-if="log.content && typeof log.content === 'string'" class="text-sm text-slate-700 line-clamp-2 mb-2">
          {{ truncate(log.content, 120) }}
        </div>

        <!-- 互动数 -->
        <div class="flex items-center gap-4 text-xs text-slate-500">
          <span v-if="log.like_count > 0">👍 {{ log.like_count }}</span>
          <span v-if="log.comment_count > 0">💬 {{ log.comment_count }}</span>
          <span v-if="log.recipients && log.recipients.length > 0">📨 {{ log.recipients.length }} 收件</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { getWorkLogAdapter } from '../../composables/useWorkLog.js'

const props = defineProps({
  logs: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  adapter: { type: String, default: 'gdqadmin' },
  loading: { type: Boolean, default: false },
  showFilters: { type: Boolean, default: true }
})

const emit = defineEmits(['item-click', 'refresh'])

const style = computed(() => getWorkLogAdapter(props.adapter))

const filter = reactive({ status: '', type: '', date_from: '', date_to: '' })

defineExpose({ filter })

function getUserName(id) {
  const u = props.users.find(u => u.id === id || u.id === Number(id))
  return u?.name || u?.username || `用户${id}`
}

function getUserInitial(name) {
  if (!name) return '?'
  return name[0]?.toUpperCase() || '?'
}

function truncate(text, n) {
  if (!text) return ''
  return text.length > n ? text.slice(0, n) + '...' : text
}

function formatDate(d) {
  if (!d) return ''
  const date = new Date(d)
  if (isNaN(date)) return d
  return date.toLocaleString('zh-CN', { hour12: false }).slice(0, 16)
}

function statusLabel(s) {
  return { submitted: '已提交', draft: '草稿', reviewed: '已审核', approved: '已批准', rejected: '已驳回' }[s] || s
}

function statusClass(s) {
  return {
    submitted: 'bg-blue-100 text-blue-700',
    draft: 'bg-slate-100 text-slate-600',
    reviewed: 'bg-green-100 text-green-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700'
  }[s] || 'bg-slate-100 text-slate-600'
}
</script>