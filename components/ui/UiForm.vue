<!--
  UiForm.vue — UI 抽象层 (0.6 铁律)
  严禁 view 直接写 <el-form>, 必须走 <UiForm :model="form" :rules="rules">

  Props 标准化:
  - model: 表单数据对象
  - rules: 校验规则 (el-form 兼容格式)
  - labelPosition: left | right | top
  - labelWidth
  - size
  - inline (行内表单)
  - disabled (整个表单禁用)

  用法 (与 UiFormItem 配合):
  <UiForm ref="formRef" :model="form" :rules="rules">
    <UiFormItem label="名称" prop="name">
      <UiInput v-model="form.name" />
    </UiFormItem>
  </UiForm>
-->
<template>
  <el-form
    ref="formRef"
    :model="model"
    :rules="rules"
    :label-position="labelPosition"
    :label-width="labelWidth"
    :size="size"
    :inline="inline"
    :disabled="disabled"
    @submit.prevent
  >
    <slot />
  </el-form>
</template>

<script setup>
import { ref } from 'vue'
import { ElForm } from 'element-plus'

const props = defineProps({
  model: { type: Object, required: true },
  rules: { type: Object, default: () => ({}) },
  labelPosition: { type: String, default: 'right' },
  labelWidth: { type: [String, Number], default: '80px' },
  size: { type: String, default: 'default' },
  inline: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const formRef = ref(null)

defineExpose({
  validate: (...args) => formRef.value?.validate(...args),
  validateField: (...args) => formRef.value?.validateField(...args),
  resetFields: (...args) => formRef.value?.resetFields(...args),
  scrollToField: (...args) => formRef.value?.scrollToField(...args),
  clearValidate: (...args) => formRef.value?.clearValidate(...args),
})
</script>
