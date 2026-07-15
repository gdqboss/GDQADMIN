<template><MinipLayout title="收货地址" :canBack="true"><button class="add-btn" @click="alert('新增地址功能开发中')">+ 新增收货地址</button><div class="list"><div v-for="a in list" :key="a.id" class="card"><div class="head"><span class="name">{{ a.name }}</span><span class="phone">{{ a.phone }}</span><span v-if="a.is_default" class="default">默认</span></div><div class="addr">{{ a.region }} {{ a.detail }}</div><div class="actions"><button @click="alert('编辑')">编辑</button><button @click="alert('删除')" class="del">删除</button></div></div><div v-if="!list.length" class="empty">暂无地址</div></div></MinipLayout></template>
<script setup>import { ref, onMounted } from 'vue'; import api from '@/api/request'

const loading = ref(false)
import { ElMessage } from 'element-plus'; import MinipLayout from './MinipLayout.vue'; const list = ref([]); onMounted(async () => { try {
    loading.value = true
  const r = await api.get('/mall/addresses'); if (r.code === 0) list.value = r.data || [] } catch (e) {
    ElMessage.error('加载失败,请稍后重试')
    list.value = []
  }
  finally {
    loading.value = false
  } }); </script>
<style scoped>.add-btn { width: 100%; background: #6366f1; color: #fff; border: 0; border-radius: 10px; padding: 12px; font-size: 14px; cursor: pointer; margin-bottom: 12px; } .list { display: flex; flex-direction: column; gap: 8px; } .card { background: #fff; border-radius: 10px; padding: 12px; } .head { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; } .name { font-size: 14px; font-weight: 600; color: #1f2329; } .phone { font-size: 12px; color: #6b7280; } .default { background: #ec4899; color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 4px; } .addr { font-size: 13px; color: #4b5563; line-height: 1.5; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #f5f6f8; } .actions { display: flex; gap: 8px; justify-content: flex-end; } .actions button { background: #f3f4f6; border: 0; border-radius: 6px; padding: 4px 12px; font-size: 11px; color: #4b5563; cursor: pointer; } .actions button.del { background: #fee2e2; color: #b91c1c; } .empty { text-align: center; padding: 40px 0; color: #9ca3af; font-size: 13px; }</style>