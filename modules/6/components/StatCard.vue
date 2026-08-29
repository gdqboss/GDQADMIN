<script setup>
defineProps({
  title: String,
  value: String,
  icon: String,
  trend: String,
  trendValue: String,
  trendLabel: { type: String, default: '' },
  colorClass: { type: String, default: 'blue' },
  alert: Boolean,
})
</script>

<template>
  <div
    :class="[
      'bg-white p-4 sm:p-5 rounded-lg border shadow-card hover:shadow-card-hover transition-shadow group',
      alert ? 'border-danger/20 relative overflow-hidden' : 'border-gray-100'
    ]"
  >
    <div v-if="alert" class="absolute right-0 top-0 p-1">
      <div class="size-2 rounded-full bg-danger animate-pulse-red"></div>
    </div>
    <div class="flex justify-between items-start">
      <div class="flex-1 min-w-0">
        <p class="text-xs sm:text-sm text-text-secondary font-medium mb-1 truncate">{{ title }}</p>
        <h3 class="text-xl sm:text-2xl font-bold tracking-tight truncate" :class="alert ? 'text-danger' : 'text-text-primary'">
          <slot>{{ value }}</slot>
        </h3>
      </div>
      <div
        :class="[
          'p-1.5 sm:p-2 rounded-lg transition-colors shrink-0 ml-2',
          colorClass === 'blue' ? 'bg-blue-50 text-primary group-hover:bg-primary group-hover:text-white' :
          colorClass === 'green' ? 'bg-green-50 text-success group-hover:bg-success group-hover:text-white' :
          colorClass === 'orange' ? 'bg-orange-50 text-warning group-hover:bg-warning group-hover:text-white' :
          colorClass === 'red' ? 'bg-red-50 text-danger group-hover:bg-danger group-hover:text-white' : ''
        ]"
      >
        <span class="material-symbols-outlined text-[20px] sm:text-[24px]">{{ icon }}</span>
      </div>
    </div>
    <div class="mt-3 sm:mt-4 flex items-center text-xs">
      <span v-if="trend" :class="trend === 'up' ? 'text-success' : 'text-danger'" class="flex items-center font-medium">
        <span class="material-symbols-outlined text-[14px] sm:text-[16px] mr-0.5">{{ trend === 'up' ? 'trending_up' : 'trending_down' }}</span>
        {{ trendValue }}
      </span>
      <span v-if="trendLabel && trend" class="text-text-secondary ml-2 truncate">{{ trendLabel }}</span>
      <slot name="footer"></slot>
    </div>
  </div>
</template>
