<template>
  <div class="product-new">
    <el-card>
      <template #header>
        <span>新增商品</span>
      </template>
      <ProductEdit v-model="editVisible" :product="null" @success="handleSuccess" />
      <div class="tips">
        <el-button type="primary" @click="editVisible = true">打开新增表单</el-button>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import ProductEdit from './ProductEdit.vue'

const router = useRouter()
const editVisible = ref(true)

const handleSuccess = () => {
  router.push('/products')
}
</script>
