<!--
  TaskList - 任务共享列表组件 (2026-09-02 立)

  三端共用: 任务列表 + 状态筛选 + 优先级徽章
-->
<template>
  <div :class="style.cardClass">
    <!-- 筛选 -->
    <div v-if="showFilters" class="flex flex-wrap gap-2 mb-4">
      <select v-model="filter.status" @change="$emit('refresh')" :class="style.inputClass + ' w-auto'">
        <option value="">全部状态</option>
        <option value="pending">待处理</option>
        <option value="in_progress">进行中</option>
        <option value="completed">已完成</option>
        <option value="approved">已批准</option>
        <option value="rejected">已驳回</option>
      </select>
      <select v-model="filter.priority" @change="$emit('refresh')" :class="style.inputClass + ' w-auto'">
        <option value="">全部优先级</option>
        <option value="high">高</option>
        <option value="medium">中</option>
        <option value="low">低</option>
      </select>
    </div>

    <!-- 加载 -->
    <div v-if="loading" class="py-8 text-center text-slate-400 text-sm">加载中...</div>

    <!-- 空 -->
    <div v-else-if="!tasks || tasks.length === 0" class="py-8 text-center text-slate-400 text-sm">
      暂无任务
    </div>

    <!-- 列表 -->
    <div v-else>
      <div
        v-for="task in tasks"
        :key="task.id"
        :class="style.listItemClass + ' cursor-pointer'"
        @click="$emit('item-click', task)"
      >
        <div class="flex items-start justify-between gap-2 mb-2">
          <h4 class="font-medium text-slate-900 flex-1 line-clamp-2">{{ task.title || task.name }}</h4>
          <div class="flex gap-1 flex-shrink-0">
            <span :class="['px-2 py-0.5 rounded text-xs', priorityClass(task.priority)]">
              {{ task.priority || 'medium' }}
            </span>
            <span :class="['px-2 py-0.5 rounded text-xs', statusClass(task.status)]">
              {{ statusLabel(task.status) }}
            </span>
          </div>
        </div>
        <p v-if="task.description" class="text-sm text-slate-600 line-clamp-2 mb-2">{{ task.description }}</p>
        <div class="flex items-center gap-3 text-xs text-slate-500">
          <span v-if="task.assignee_name">👤 {{ task.assignee_name }}</span>
          <span v-if="task.due_date">📅 {{ formatDate(task.due_date) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useTask } from '../../composables/useTask.js'

const props = defineProps({
  tasks: { type: Array, default: () => [] },
  api: { type: Object, required: true },
  adapter: { type: String, default: 'gdqadmin' },
  loading: { type: Boolean, default: false },
  showFilters: { type: Boolean, default: true }
})

const emit = defineEmits(['item-click', 'refresh'])

const { getAdapter, statusClass, statusLabel, priorityClass } = useTask(props.api)
const style = computed(() => getAdapter(props.adapter))

const filter = reactive({ status: '', priority: '' })

defineExpose({ filter })

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('zh-CN').slice(0, 10)
}
</script>