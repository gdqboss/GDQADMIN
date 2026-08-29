<script setup>
defineProps({
  type: { type: String, default: 'primary' }, // primary, success, warning, danger, info
  text: String,
})

const colorMap = {
  primary: 'bg-blue-100 text-primary',
  success: 'bg-green-100 text-success',
  warning: 'bg-orange-100 text-warning',
  danger: 'bg-red-100 text-danger',
  info: 'bg-gray-100 text-info',
}
</script>

<template>
  <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap', colorMap[type] || colorMap.info]">
    {{ text }}<slot></slot>
  </span>
</template>
