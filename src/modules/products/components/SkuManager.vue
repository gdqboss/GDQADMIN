<template>
  <el-dialog
    v-model="visible"
    title="SKU 管理"
    width="900px"
    @close="handleClose"
  >
    <div class="spec-section">
      <div class="section-title">规格选项</div>
      <div class="spec-chips">
        <el-tag
          v-for="(values, key) in specMap"
          :key="key"
          closable
          @close="removeSpec(key)"
          class="spec-tag"
        >
          {{ key }}: [{{ values.join(', ') }}]
        </el-tag>
      </div>

      <div class="add-spec-form">
        <el-input v-model="newSpecName" placeholder="规格名称，如：颜色" style="width: 140px" />
        <el-input
          v-model="newSpecValues"
          placeholder="规格值，逗号分隔，如：红,黑"
          style="width: 240px"
          @keyup.enter="addSpec"
        />
        <el-button type="primary" @click="addSpec">添加规格</el-button>
        <el-button type="success" @click="generateCombinations" :disabled="!canGenerate">
          生成组合
        </el-button>
      </div>
    </div>

    <el-divider />

    <div class="sku-grid" v-if="skuList.length">
      <el-card
        v-for="(sku, index) in skuList"
        :key="index"
        class="sku-card"
        shadow="hover"
      >
        <div class="sku-header">
          <el-input
            v-model="sku.sku_code"
            placeholder="SKU编码"
            size="small"
            style="width: 180px"
          />
          <el-button
            type="primary"
            link
            size="small"
            @click="autoFillSku(sku)"
          >
            自动生成
          </el-button>
        </div>

        <div class="sku-specs">
          <el-tag
            v-for="(val, specKey) in sku.specs"
            :key="specKey"
            size="small"
            class="sku-spec-tag"
          >
            {{ specKey }}: {{ val }}
          </el-tag>
        </div>

        <div class="sku-prices">
          <el-input-number
            v-model="sku.purchase_price"
            :min="0"
            :precision="2"
            size="small"
            placeholder="采购价"
            style="width: 110px"
          />
          <span class="price-sep">/</span>
          <el-input-number
            v-model="sku.sale_price"
            :min="0"
            :precision="2"
            size="small"
            placeholder="售价"
            style="width: 110px"
          />
        </div>

        <div class="sku-stock">
          <span>库存:</span>
          <el-input-number v-model="sku.stock" :min="0" size="small" style="width: 100px" />
        </div>

        <div class="sku-status">
          <el-radio-group v-model="sku.status" size="small">
            <el-radio-button label="active">启用</el-radio-button>
            <el-radio-button label="discontinued">停售</el-radio-button>
          </el-radio-group>
        </div>
      </el-card>
    </div>

    <el-empty v-else description="暂无SKU，请添加规格后生成" />

    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button type="primary" :loading="loading" @click="handleSave">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { saveProductSpecs } from '../../../api/products.js'

const props = defineProps({
  modelValue: Boolean,
  productId: [String, Number],
  initialSpecs: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue', 'success'])

const visible = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

const loading = ref(false)
const specMap = reactive({})
const skuList = ref([])
const newSpecName = ref('')
const newSpecValues = ref('')

const canGenerate = computed(() => Object.keys(specMap).length > 0)

const addSpec = () => {
  if (!newSpecName.value || !newSpecValues.value) {
    ElMessage.warning('请输入规格名称和规格值')
    return
  }
  const values = newSpecValues.value.split(',').map(v => v.trim()).filter(v => v)
  if (values.length === 0) {
    ElMessage.warning('规格值不能为空')
    return
  }
  specMap[newSpecName.value] = values
  newSpecName.value = ''
  newSpecValues.value = ''
}

const removeSpec = (key) => {
  delete specMap[key]
}

const generateCombinations = () => {
  const keys = Object.keys(specMap)
  if (keys.length === 0) return

  const combinations = keys.reduce(
    (acc, key) => {
      const result = []
      for (const prev of acc) {
        for (const val of specMap[key]) {
          result.push({ ...prev, [key]: val })
        }
      }
      return result
    },
    [{}]
  )

  skuList.value = combinations.map(combo => ({
    specs: combo,
    sku_code: '',
    purchase_price: null,
    sale_price: null,
    stock: 0,
    status: 'active'
  }))

  ElMessage.success(`生成了 ${skuList.value.length} 个SKU组合`)
}

const generateSkuCode = () => {
  const date = new Date()
  const yyyy = date.getFullYear()
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const dd = String(date.getDate()).padStart(2, '0')
  const rand = String(Math.floor(Math.random() * 10000)).padStart(4, '0')
  return `SKU-${yyyy}${mm}${dd}-${rand}`
}

const autoFillSku = (sku) => {
  sku.sku_code = generateSkuCode()
}

const handleClose = () => {
  visible.value = false
}

const handleSave = async () => {
  if (!skuList.value.length) {
    ElMessage.warning('没有SKU可保存')
    return
  }

  try {
    loading.value = true
    await saveProductSpecs(props.productId, skuList.value)
    ElMessage.success('保存成功')
    emit('success')
    handleClose()
  } catch (e) {
    console.error('Save specs failed', e)
    ElMessage.error('保存失败')
  } finally {
    loading.value = false
  }
}

watch(visible, (val) => {
  if (val && props.initialSpecs?.length) {
    const merged = {}
    props.initialSpecs.forEach(sku => {
      Object.entries(sku.specs || {}).forEach(([key, val]) => {
        if (!merged[key]) merged[key] = []
        if (!merged[key].includes(val)) merged[key].push(val)
      })
    })
    Object.assign(specMap, merged)
    skuList.value = [...props.initialSpecs]
  }
})
</script>

<style scoped>
.spec-section {
  margin-bottom: 16px;
}

.section-title {
  font-weight: 500;
  margin-bottom: 12px;
  color: #303133;
}

.spec-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}

.spec-tag {
  font-size: 14px;
}

.add-spec-form {
  display: flex;
  gap: 8px;
  align-items: center;
}

.sku-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
  max-height: 400px;
  overflow-y: auto;
  padding: 4px;
}

.sku-card {
  border: 1px solid #ebeef5;
}

.sku-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.sku-specs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}

.sku-spec-tag {
  background-color: #f0f9eb;
  border-color: #e1f3d8;
  color: #67c23a;
}

.sku-prices {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.price-sep {
  color: #909399;
}

.sku-stock {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  font-size: 14px;
  color: #606266;
}

.sku-status {
  display: flex;
}
</style>
