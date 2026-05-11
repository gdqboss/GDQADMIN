<template>
  <div class="product-skus">
    <el-card>
      <template #header>
        <span>SKU 管理 - {{ productId }}</span>
      </template>
      <SkuManager v-model="dialogVisible"         :product-id="productId"
        :initial-specs="specs"
        @success="handleSuccess"
      />
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SkuManager from './SkuManager.vue'
import { getProductSpecs } from '../../../api/products.js'

const route = useRoute()
const router = useRouter()
const productId = route.params.id
const dialogVisible = ref(true)
const specs = ref([])

onMounted(async () => {
  try {
    const data = await getProductSpecs(productId)
    specs.value = data || []
  } catch (e) {
    console.error('Failed to load specs', e)
  }
})

const handleSuccess = () => {
  router.push('/products')
}
</script>
