<!--
  UiInput.vue — UI 抽象层 (0.6 铁律)
  严禁 view 直接写 <el-input>, 必须走 <UiInput v-model="x" />

  Props 标准化:
  - modelValue: v-model 绑定值
  - type: text | password | number | textarea | email | tel | url
  - placeholder / maxlength / minlength / showPassword
  - clearable / disabled / readonly
  - prefixIcon / suffixIcon (字符串图标名)
  - rows: textarea 专用
-->
<template>
  <el-input
    v-if="type !== 'textarea'"
    :model-value="modelValue"
    :type="type"
    :placeholder="placeholder"
    :maxlength="maxlength"
    :minlength="minlength"
    :show-password="showPassword"
    :clearable="clearable"
    :disabled="disabled"
    :readonly="readonly"
    :prefix-icon="prefixIcon"
    :suffix-icon="suffixIcon"
    :size="size"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template v-if="$slots.prepend" #prepend><slot name="prepend" /></template>
    <template v-if="$slots.append" #append><slot name="append" /></template>
    <template v-if="$slots.prefix" #prefix><slot name="prefix" /></template>
    <template v-if="$slots.suffix" #suffix><slot name="suffix" /></template>
  </el-input>

  <el-input
    v-else
    type="textarea"
    :model-value="modelValue"
    :placeholder="placeholder"
    :rows="rows || 4"
    :maxlength="maxlength"
    :disabled="disabled"
    :readonly="readonly"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>

<script setup>
import { ElInput } from 'element-plus'

defineProps({
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  maxlength: { type: [String, Number], default: null },
  minlength: { type: [String, Number], default: null },
  showPassword: { type: Boolean, default: false },
  clearable: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  prefixIcon: { type: [String, Object, Function], default: null },
  suffixIcon: { type: [String, Object, Function], default: null },
  size: { type: String, default: 'default' },
  rows: { type: Number, default: null },
})

const emit = defineEmits(['update:modelValue'])
</script>
