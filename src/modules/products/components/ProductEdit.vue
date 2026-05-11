<template>
  <el-dialog v-model="visible"     :title="isEdit ? '编辑产品' : '新增产品'"
    width="640px"
    @close="handleClose"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
      <el-form-item label="SKU" prop="sku">
        <el-input v-model="form.sku" placeholder="商品SKU">
          <template #append>
            <el-button @click="generateSku">生成</el-button>
          </template>
        </el-input>
      </el-form-item>

      <el-form-item label="产品名称" prop="name">
        <el-input v-model="form.name" placeholder="请输入产品名称" />
      </el-form-item>

      <el-form-item label="产品分类" prop="category_id">
        <el-cascader v-model="form.category_id"           :options="categoryOptions"
          :props="{ checkStrictly: true, label: 'name', value: 'id' }"
          placeholder="请选择分类"
          clearable
          style="width: 100%"
        />
      </el-form-item>

      <el-form-item label="单位" prop="unit">
        <el-input v-model="form.unit" placeholder="单位" style="width: 120px" />
      </el-form-item>

      <el-form-item label="供应商" prop="supplier_id">
        <el-select v-model="form.supplier_id" placeholder="请选择供应商" clearable style="width: 100%">
          <el-option
            v-for="supplier in supplierOptions"
            :key="supplier.id"
            :label="supplier.name"
            :value="supplier.id"
          />
        </el-select>
      </el-form-item>

      <el-form-item label="采购价格">
        <el-input-number v-model="form.purchase_price" :min="0" :precision="2" style="width: 150px" />
      </el-form-item>

      <el-form-item label="销售价格">
        <div style="display:flex; gap:8px;">
          <div style="display:flex;align-items:center;">
            <span style="margin-right:4px">¥</span>
            <el-input-number v-model="form.sale_price" :min="0" :precision="2" style="width:120px" placeholder="CNY" />
          </div>
          <div style="display:flex;align-items:center;">
            <span style="margin-right:4px">$</span>
            <el-input-number v-model="form.sale_price_usd" :min="0" :precision="2" style="width:120px" placeholder="USD" />
          </div>
          <div style="display:flex;align-items:center;">
            <span style="margin-right:4px">₱</span>
            <el-input-number v-model="form.sale_price_php" :min="0" :precision="2" style="width:120px" placeholder="PHP" />
          </div>
        </div>
      </el-form-item>

      <el-form-item label="库存" prop="stock">
        <el-input-number v-model="form.stock" :min="0" style="width: 150px" />
      </el-form-item>

      <el-form-item label="安全库存" prop="safe_stock">
        <el-input-number v-model="form.safe_stock" :min="0" style="width: 150px" />
      </el-form-item>

      <el-form-item label="状态" prop="status">
        <el-radio-group v-model="form.status">
          <el-radio label="active">启用</el-radio>
          <el-radio label="discontinued">停售</el-radio>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="产品图片" prop="image_main">
        <el-upload
          :action="uploadUrl"
          :headers="{ Authorization: `Bearer ${token}` }"
          :show-file-list="false"
          :on-success="handleImageSuccess"
          :before-upload="beforeImageUpload"
          accept="image/*"
        >
          <img v-if="form.image_main" :src="form.image_main" class="product-image" />
          <el-icon v-else><Plus /></el-icon>
        </el-upload>
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button type="primary" :loading="loading" @click="handleSave">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getCategories } from '../../../api/categories.js'
import { getSupplierList } from "../../../api/suppliers.js";
import { createProduct, updateProduct } from '../../../api/products.js'

const props = defineProps({
  modelValue: Boolean,
  product: Object
})

const emit = defineEmits(['update:modelValue', 'success'])

const visible = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

const isEdit = computed(() => !!props.product?.id)

const formRef = ref(null)
const loading = ref(false)
const categoryOptions = ref([])
const supplierOptions = ref([])

const form = reactive({
  sku: '',
  name: '',
  category_id: null,
  unit: '个',
  supplier_id: null,
  purchase_price: null,
  sale_price: null,
  sale_price_usd: null,
  sale_price_php: null,
  stock: 0,
  safe_stock: 0,
  status: 'active',
  image_main: ''
})

const rules = {
  name: [{ required: true, message: '请输入产品名称', trigger: 'blur' }]
}

const uploadUrl = import.meta.env.VITE_API_BASE_URL + '/upload'
const token = localStorage.getItem('token') || ''

const loadCategories = async () => {
  try {
    const { data } = await getCategories()
    categoryOptions.value = data
  } catch (e) {
    console.error('Failed to load categories', e)
  }
}

const loadSuppliers = async () => {
  try {
    const { data } = await getSupplierList()
    supplierOptions.value = data
  } catch (e) {
    console.error('Failed to load suppliers', e)
  }
}

const generateSku = () => {
  const date = new Date()
  const yyyy = date.getFullYear()
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const dd = String(date.getDate()).padStart(2, '0')
  const rand = String(Math.floor(Math.random() * 10000)).padStart(4, '0')
  form.sku = `SKU-${yyyy}${mm}${dd}-${rand}`
}

const handleImageSuccess = (res) => {
  form.image_main = res.url || res.data?.url || ''
}

const beforeImageUpload = (file) => {
  const isImage = file.type.startsWith('image/')
  if (!isImage) {
    ElMessage.error('只能上传图片文件')
  }
  return isImage
}

const handleClose = () => {
  formRef.value?.resetFields()
  Object.assign(form, {
    sku: '',
    name: '',
    category_id: null,
    unit: '个',
    supplier_id: null,
    purchase_price: null,
    sale_price: null,
    sale_price_usd: null,
    sale_price_php: null,
    stock: 0,
    safe_stock: 0,
    status: 'active',
    image_main: ''
  })
  visible.value = false
}

const handleSave = async () => {
  try {
    await formRef.value?.validate()
    loading.value = true
    const payload = { ...form }
    if (isEdit.value) {
      await updateProduct(props.product.id, payload)
    } else {
      await createProduct(payload)
    }
    ElMessage.success('保存成功')
    emit('success')
    handleClose()
  } catch (e) {
    if (e !== false) {
      console.error('Save failed', e)
    }
  } finally {
    loading.value = false
  }
}

watch(visible, (val) => {
  if (val) {
    loadCategories()
    loadSuppliers()
    if (props.product) {
      Object.assign(form, props.product)
    }
  }
})
</script>

<style scoped>
.product-image {
  width: 120px;
  height: 120px;
  object-fit: cover;
  border-radius: 4px;
}
</style>
