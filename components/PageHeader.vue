<script setup>
defineProps({
  title: String,
  subtitle: String,
})
</script>

<template>
  <div class="mb-4 sm:mb-6 flex items-start justify-between">
    <div>
      <h2 class="text-lg sm:text-xl font-bold text-text-primary">{{ title }}</h2>
      <p v-if="subtitle" class="text-xs sm:text-sm text-text-secondary mt-1">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.actions" class="ml-4">
      <slot name="actions"></slot>
    </div>
  </div>
</template>
