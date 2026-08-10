<template>
  <el-dialog v-model="visible" :title="activity.title" width="600px" :close-on-click-modal="false">
    <el-form :model="form" label-width="80px" ref="formRef">
      <el-form-item :label="$t('portal.form.name')" required>
        <el-input v-model="form.member_name" :placeholder="$t('portal.form.name_required')" />
      </el-form-item>
      <el-form-item :label="$t('portal.form.phone')" required>
        <el-input v-model="form.member_phone" :placeholder="$t('portal.form.phone_required')" />
      </el-form-item>
      <el-form-item :label="$t('portal.form.email')">
        <el-input v-model="form.member_email" type="email" />
      </el-form-item>
      <el-form-item :label="$t('portal.form.company')">
        <el-input v-model="form.member_company" />
      </el-form-item>
      <el-form-item :label="$t('portal.form.title')">
        <el-input v-model="form.member_title" />
      </el-form-item>
      <el-form-item :label="$t('portal.form.remarks')">
        <el-input v-model="form.remarks" type="textarea" :rows="2" />
      </el-form-item>
    </el-form>
    <template #footer>
      <button @click="visible = false" class="px-4 py-2 border rounded-lg">{{ $t('portal.form.cancel') }}</button>
      <button @click="submit" :disabled="submitting" class="px-4 py-2 bg-amber-500 text-white rounded-lg ml-2 disabled:opacity-50">
        {{ submitting ? $t('portal.form.submitting') : $t('portal.form.submit') }}
      </button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'

const props = defineProps({
  modelValue: Boolean,
  activity: Object,
})

const emit = defineEmits(['update:modelValue', 'success'])

const visible = ref(props.modelValue)
const submitting = ref(false)
const formRef = ref()
const form = reactive({
  member_name: '',
  member_phone: '',
  member_email: '',
  member_company: '',
  member_title: '',
  remarks: '',
})

async function submit() {
  if (!form.member_name) { ElMessage.error('请输入姓名'); return }
  if (!/^1[3-9]\d{9}$/.test(form.member_phone)) { ElMessage.error('请输入正确的手机号'); return }

  submitting.value = true
  try {
    const { api } = await import('@/services/api.js')
    const res = await api.post(`/association/activities/${props.activity.id}/register`, form)
    if (res.code === 0) {
      ElMessage.success('报名成功')
      emit('success')
      visible.value = false
    } else {
      ElMessage.error(res.message || '报名失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '网络错误')
  } finally {
    submitting.value = false
  }
}
</script>