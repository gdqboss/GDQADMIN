<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center">
      <button @click="$router.back()" class="flex-shrink-0"><span class="material-symbols-outlined text-2xl text-slate-600">arrow_back</span></button>
      <h2 class="text-lg font-semibold">拼团详情</h2>
    </div>
    <div v-if="collage" class="px-4 pt-4">
      <div class="bg-white rounded-xl overflow-hidden mb-4">
        <img :src="collage.image_url||'/images/placeholder.png'" class="w-full aspect-square bg-slate-100"/>
        <div class="p-4">
          <div class="font-bold">{{ collage.name }}</div>
          <div class="flex items-baseline gap-2 mt-2">
            <span class="text-red-500 text-2xl font-bold">¥{{ collage.collage_price }}</span>
            <span class="text-slate-400 text-sm line-through">¥{{ collage.original_price }}</span>
          </div>
          <div class="text-sm text-slate-500 mt-2">{{ collage.description }}</div>
        </div>
      </div>
      <div class="bg-white rounded-xl p-4">
        <div class="text-sm font-medium mb-3">正在进行中的团</div>
        <div v-for="g in groups" :key="g.id" class="flex items-center justify-between py-2 border-b last:border-0">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-xs">{{ g.user_name?.[0]||'?' }}</div>
            <span class="text-sm">{{ g.user_name||'匿名用户' }}</span>
          </div>
          <div class="flex items-center gap-3">
            <div class="text-xs text-slate-500">还差 {{ g.need_count }} 人</div>
            <button @click="joinGroup(g.id)" class="px-4 py-1 bg-primary text-white rounded-full text-xs">去拼团</button>
          </div>
        </div>
        <div v-if="!groups.length" class="text-center py-4 text-slate-400 text-sm">暂无进行中的团</div>
      </div>
      <div class="mt-4">
        <button @click="createCollage" class="w-full py-3 bg-primary text-white rounded-full font-medium text-sm">发起拼团</button>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
const router=useRouter(), collage=ref(null), groups=ref([])
onMounted(()=>{ const id=router.currentRoute.value.params.id; fetch('/api/collage/detail/'+id).then(r=>r.json()).then(r=>{ if(r.code===0){ collage.value=r.data; groups.value=r.data.groups||[] } }) })
function joinGroup(gid){ router.push('/h5/checkout?collage_id='+gid) }
function createCollage(){ router.push('/h5/checkout?collage_id='+collage.value.id) }
</script>