<template><MinipLayout title="我的收藏" :canBack="true"><div class="grid"><div v-for="p in list" :key="p.id" class="card" @click="alert(p.name)"><div class="thumb">{{ p.emoji }}</div><div class="info"><div class="name">{{ p.name }}</div><div class="price">¥{{ p.price }}</div></div></div><div v-if="!list.length" class="empty">暂无收藏</div></div></MinipLayout></template>
<script setup>import { ref, onMounted } from 'vue'; import api from '@/api/request'

const loading = ref(false)
import { ElMessage } from 'element-plus'; import MinipLayout from './MinipLayout.vue'; const list = ref([]); onMounted(async () => { try {
    loading.value = true
  const r = await api.get('/mall/favorites?limit=50'); if (r.code === 0) list.value = r.data || [] } catch (e) {
    ElMessage.error('加载失败,请稍后重试')
    list.value = []
  }
  finally {
    loading.value = false
  } }); </script>
<style scoped>.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; } .card { background: #fff; border-radius: 10px; overflow: hidden; } .thumb { font-size: 56px; text-align: center; padding: 16px 0; background: linear-gradient(135deg, #fef3c7, #fde68a); } .info { padding: 8px; } .name { font-size: 13px; color: #1f2329; font-weight: 500; } .price { font-size: 14px; color: #ec4899; font-weight: 700; margin-top: 4px; } .empty { grid-column: 1/-1; text-align: center; padding: 40px 0; color: #9ca3af; font-size: 13px; }</style>