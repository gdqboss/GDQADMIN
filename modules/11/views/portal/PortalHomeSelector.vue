<!--
  Variant Selector: 让波哥 / 客户在前端 4 套对比
  用法: /portal?v=A (古典药柜) / ?v=B (现代) / ?v=C (端庄红) / ?v=original (原版) / 默认 A
  浮动工具栏, 只在?v=... 时显示
-->
<template>
  <component :is="currentComponent" v-if="currentComponent" />

  <div v-if="showSelector" class="fixed top-20 left-1/2 -translate-x-1/2 z-50 bg-mo/90 backdrop-blur rounded-full px-3 py-2 flex items-center gap-2 shadow-2xl border border-gold/30">
    <span class="text-gold text-xs font-mono px-2">VIEW</span>
    <button
      v-for="opt in options"
      :key="opt.value"
      @click="switchTo(opt.value)"
      :class="[
        'px-4 py-1.5 rounded-full text-xs font-mono tracking-wider transition',
        currentVariant === opt.value
          ? 'bg-gold text-mo font-bold'
          : 'text-cream/70 hover:text-cream hover:bg-cream/10'
      ]"
    >
      {{ opt.label }}
    </button>
    <span class="text-cream/40 text-xs px-2 hidden md:inline">/portal?v={{ currentVariant }}</span>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const currentVariant = ref('A')
const showSelector = ref(false)

const options = [
  { value: 'A', label: 'A · 古典药柜' },
  { value: 'B', label: 'B · 现代专业' },
  { value: 'C', label: 'C · 端庄红' },
  { value: 'original', label: '原版对照' },
]

const componentA = defineAsyncComponent(() => import('./PortalHome-A.vue'))
const componentB = defineAsyncComponent(() => import('./PortalHome-B.vue'))
const componentC = defineAsyncComponent(() => import('./PortalHome-C.vue'))
const componentOriginal = defineAsyncComponent(() => import('./PortalHome.original.vue'))

const componentMap = {
  A: componentA,
  B: componentB,
  C: componentC,
  original: componentOriginal,
}

const currentComponent = computed(() => componentMap[currentVariant.value] || componentMap.A)

function switchTo(v) {
  currentVariant.value = v
  router.replace({ path: '/portal', query: { ...route.query, v } })
}

onMounted(() => {
  const v = route.query.v
  if (v && componentMap[v]) {
    currentVariant.value = v
    showSelector.value = true
  }
})

watch(() => route.query.v, (newV) => {
  if (newV && componentMap[newV]) {
    currentVariant.value = newV
    showSelector.value = true
  }
})
</script>

<style scoped>
.bg-mo { background-color: #1a1a1a; }
.text-cream { color: #f5ecd9; }
.text-gold { color: #b8860b; }
.bg-gold { background-color: #b8860b; }
.border-gold\/30 { border-color: rgb(184 134 11 / 0.3); }
</style>