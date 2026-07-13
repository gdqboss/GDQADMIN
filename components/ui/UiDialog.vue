<!--
  UiDialog.vue — UI 抽象层 (0.6 铁律)
  严禁 view 直接写 <el-dialog>, 必须走 <UiDialog v-model="show" title="...">
  替代 antd Modal / naive NDialog 都无须改 view

  Props 标准化:
  - modelValue: v-model 控制显示 (与 el-dialog 一致)
  - title / width / fullscreen / top
  - modal / modalClass / appendToBody
  - closeOnClickModal / closeOnPressEscape
  - showClose / center
  - confirmLoading: 确认按钮 loading 状态
  - onConfirm: 确认回调 (不暴露 native before-close)
  - onCancel: 取消回调

  Slot: default (内容), footer (可替换底部按钮; 不传走默认 [取消][确认])
-->
<template>
  <el-dialog
    :model-value="modelValue"
    :title="title"
    :width="width"
    :fullscreen="fullscreen"
    :top="top"
    :modal="modal"
    :modal-class="modalClass"
    :append-to-body="appendToBody"
    :close-on-click-modal="closeOnClickModal"
    :close-on-press-escape="closeOnPressEscape"
    :show-close="showClose"
    :center="center"
    :destroy-on-close="destroyOnClose"
    @update:model-value="emit('update:modelValue', $event)"
    @close="onClose"
  >
    <slot />
    <template #footer>
      <slot name="footer">
        <UiButton @click="handleCancel">{{ cancelText }}</UiButton>
        <UiButton
          :variant="confirmVariant"
          :loading="confirmLoading"
          @click="handleConfirm"
        >{{ confirmText }}</UiButton>
      </slot>
    </template>
  </el-dialog>
</template>

<script setup>
import { ElDialog } from 'element-plus'
import UiButton from './UiButton.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  title: { type: String, default: '' },
  width: { type: [String, Number], default: '500px' },
  fullscreen: { type: Boolean, default: false },
  top: { type: String, default: '15vh' },
  modal: { type: Boolean, default: true },
  modalClass: { type: String, default: '' },
  appendToBody: { type: Boolean, default: false },
  closeOnClickModal: { type: Boolean, default: true },
  closeOnPressEscape: { type: Boolean, default: true },
  showClose: { type: Boolean, default: true },
  center: { type: Boolean, default: false },
  destroyOnClose: { type: Boolean, default: false },
  confirmText: { type: String, default: '确定' },
  cancelText: { type: String, default: '取消' },
  confirmVariant: { type: String, default: 'primary' },
  confirmLoading: { type: Boolean, default: false },
  onConfirm: { type: Function, default: null },
  onCancel: { type: Function, default: null },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel', 'close'])

async function handleConfirm() {
  emit('confirm')
  if (props.onConfirm) {
    const r = props.onConfirm()
    if (r && r.then) await r
  }
}

function handleCancel() {
  emit('cancel')
  if (props.onCancel) props.onCancel()
  emit('update:modelValue', false)
}

function onClose() {
  emit('close')
  emit('update:modelValue', false)
}
</script>
