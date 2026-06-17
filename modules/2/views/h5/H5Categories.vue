<template>
  <div class="min-h-screen bg-slate-50">
    <div class="sticky top-0 bg-white z-20 px-4 py-3 border-b flex items-center">
      <button @click="$router.back()" class="mr-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
      <h2 class="text-lg font-semibold">分类</h2>
    </div>
    <div class="flex">
      <div class="w-24 bg-slate-100 overflow-y-auto" style="height:calc(100vh - 56px)">
        <button v-for="c in categories" :key="c.id"
          @click="selectedId=c.id; loadProducts()"
          :class="['w-full text-xs py-3 px-2 text-center border-b transition-all',
            selectedId===c.id?'bg-white text-primary font-medium':'text-slate-600']">
          {{ c.name }}
        </button>
      </div>
      <div class="flex-1 p-3 overflow-y-auto" style="height:calc(100vh - 56px)">
        <div class="grid grid-cols-3 gap-2">
          <div v-for="p in products" :key="p.id" @click="$router.push('/h5/product/'+p.id)" class="text-center">
            <img :src="p.image_url||'/images/placeholder.png'" class="w-full aspect-square rounded-lg object-cover bg-slate-100" />
            <div class="text-xs mt-1 line-clamp-2">{{ p.name }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
const categories = ref([{id:null,name:'全部'}]), products = ref([]), selectedId = ref(null)
onMounted(()=>{ fetch('/api/categories').then(r=>r.json()).then(r=>{ if(r.code===0) categories.value.push(...r.data) }) })
function loadProducts(){ fetch('/api/mall/products?'+new URLSearchParams({category_id:selectedId.value})).then(r=>r.json()).then(r=>{ if(r.code===0) products.value=r.data.list }) }
onMounted(loadProducts)
</script>