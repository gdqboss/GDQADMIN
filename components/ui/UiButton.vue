<!--
  UiButton.vue — UI 抽象层 (0.6 铁律)
  严禁 view 直接写 <el-button>, 必须走 <UiButton variant="primary">
  当前实现 = element-plus (ui-kits/element-plus/)
  后续可换 naive-ui / ant-design 无须改 view

  Props 标准化 (不暴露 EP 内部 prop type/nativeType/plain/round):
  - variant: primary | success | warning | danger | info | default
  - size: small | default | large
  - loading / disabled / icon
  - type: button | submit | reset (HTML 原生)
-usage: <UiButton variant="primary" :loading="saving" @click="save">保存</UiButton>
-->
<template>
  <el-button
    v-bind="elBindings"
    :loading="loading"
    :disabled="disabled"
    @click="$emit('click', $event)"
  >
    <slot name="icon" v-if="$slots.icon" />
    <el-icon v-else-if="icon"><component :is="icon" /></el-icon>
    <slot />
  </el-button>
</template>

<script setup>
import { computed } from 'vue'
import { ElButton, ElIcon } from 'element-plus'

const props = defineProps({
  variant: { type: String, default: 'default' }, // primary | success | warning | danger | info | default
  size: { type: String, default: 'default' }, // small | default | large
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  icon: { type: [String, Object, Function], default: null }, // 接受 component 名或 import
  plain: { type: Boolean, default: false },
  round: { type: Boolean, default: false },
  circle: { type: Boolean, default: false },
  text: { type: Boolean, default: false }, // 文字按钮 (vs 主按钮)
  bg: { type: Boolean, default: false },
  link: { type: Boolean, default: false }, // 链接样式按钮
  block: { type: Boolean, default: false },
  type: { type: String, default: 'button' }, // HTML 原生 button/submit/reset (form 用)
  autofocus: { type: Boolean, default: false },
})

defineEmits(['click'])

// variant → el-button type 映射
const elBindings = computed(() => ({
  type: props.variant === 'default' ? 'default' : props.variant,
  size: props.size,
  plain: props.plain,
  round: props.round,
  circle: props.circle,
  text: props.text,
  bg: props.bg,
  link: props.link,
  block: props.block,
  nativeType: props.type,
  autofocus: props.autofocus,
}))
</script>
