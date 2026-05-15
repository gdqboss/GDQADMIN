<script setup>
import { computed } from 'vue'

const props = defineProps({
  total:    { type: Number, required: true },
  page:     { type: Number, required: true },
  pageSize: { type: Number, default: 50 },
})
const emit = defineEmits(['update:page'])

const totalPages = computed(() => Math.max(1, Math.ceil(props.total / props.pageSize)))

const pages = computed(() => {
  const p = props.page, t = totalPages.value
  if (t <= 7) return Array.from({ length: t }, (_, i) => i + 1)
  const arr = []
  arr.push(1)
  if (p > 3) arr.push('...')
  for (let i = Math.max(2, p - 1); i <= Math.min(t - 1, p + 1); i++) arr.push(i)
  if (p < t - 2) arr.push('...')
  if (t > 1) arr.push(t)
  return arr
})

const start = computed(() => (props.page - 1) * props.pageSize + 1)
const end   = computed(() => Math.min(props.page * props.pageSize, props.total))
</script>

<template>
  <div v-if="total > 0" class="flex items-center justify-between text-sm text-text-secondary">
    <span>{{ $t('common.paginationInfo', { start, end, total }) }}</span>
    <div class="flex items-center gap-1">
      <button
        @click="emit('update:page', page - 1)"
        :disabled="page <= 1"
        class="px-2 py-1 rounded hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed"
      >‹</button>
      <template v-for="p in pages" :key="p">
        <span v-if="p === '...'" class="px-2 py-1 text-text-secondary">…</span>
        <button
          v-else
          @click="emit('update:page', p)"
          :class="[
            'px-2.5 py-1 rounded text-xs font-medium transition-colors',
            p === page ? 'bg-primary text-white' : 'hover:bg-gray-100'
          ]"
        >{{ p }}</button>
      </template>
      <button
        @click="emit('update:page', page + 1)"
        :disabled="page >= totalPages"
        class="px-2 py-1 rounded hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed"
      >›</button>
    </div>
  </div>
</template>
