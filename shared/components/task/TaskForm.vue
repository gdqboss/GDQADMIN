<!--
  TaskForm - 任务共享表单组件 (2026-09-02 立)

  三端共用: 创建 / 编辑任务
  Fields: title / description / priority / due_date / assignee_id
-->
<template>
  <div :class="style.cardClass">
    <div class="space-y-4">
      <div>
        <label :class="style.labelClass">任务标题 <span class="text-red-500">*</span></label>
        <input
          type="text"
          v-model="form.title"
          :class="style.inputClass + ' mt-1'"
          placeholder="一句话说清楚任务"
        />
      </div>

      <div>
        <label :class="style.labelClass">任务描述</label>
        <textarea
          v-model="form.description"
          :class="style.inputClass + ' mt-1'"
          rows="3"
          placeholder="详细说明任务背景 / 目标 / 验收标准"
        />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label :class="style.labelClass">优先级</label>
          <select v-model="form.priority" :class="style.inputClass + ' mt-1'">
            <option value="low">低</option>
            <option value="medium">中</option>
            <option value="high">高</option>
          </select>
        </div>
        <div>
          <label :class="style.labelClass">截止日期</label>
          <input
            type="date"
            v-model="form.due_date"
            :class="style.inputClass + ' mt-1'"
          />
        </div>
      </div>

      <div>
        <label :class="style.labelClass">指派给</label>
        <select v-model="form.assignee_id" :class="style.inputClass + ' mt-1'">
          <option :value="null">请选择</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name || u.username }}</option>
        </select>
      </div>
    </div>

    <!-- 操作按钮 -->
    <div class="flex gap-3 mt-6">
      <button type="button" @click="$emit('cancel')" :class="style.buttonGhost">取消</button>
      <button
        type="button"
        @click="handleSubmit"
        :disabled="saving || !form.title?.trim()"
        :class="style.buttonPrimary + (saving || !form.title?.trim() ? ' opacity-50 cursor-not-allowed' : '')"
      >
        {{ saving ? '提交中...' : '提交' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch as vueWatch } from 'vue'
import { useTask } from '../../composables/useTask.js'

const props = defineProps({
  formData: { type: Object, default: () => ({}) },
  users: { type: Array, default: () => [] },
  api: { type: Object, required: true },
  adapter: { type: String, default: 'gdqadmin' },
  loading: { type: Boolean, default: false },
  mode: { type: String, default: 'create' }
})

const emit = defineEmits(['submit', 'cancel', 'change'])

const { getAdapter, submitTask, saving } = useTask(props.api)
const style = computed(() => getAdapter(props.adapter))

const form = ref({
  title: props.formData.title || '',
  description: props.formData.description || '',
  priority: props.formData.priority || 'medium',
  due_date: props.formData.due_date || '',
  assignee_id: props.formData.assignee_id || null,
  ...props.formData
})

vueWatch(() => props.formData, (v) => {
  if (v) Object.assign(form.value, v)
}, { deep: true })

vueWatch(form, () => emit('change', form.value), { deep: true })

function handleSubmit() {
  if (!form.value.title?.trim()) {
    alert('任务标题不能为空')
    return
  }
  emit('submit', {
    ...form.value,
    ...(props.mode === 'update' && { id: props.formData.id })
  })
}
</script>