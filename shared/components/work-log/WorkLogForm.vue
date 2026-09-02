<!--
  WorkLogForm - 工作日志共享表单组件 (2026-09-02 立)

  三端共用:
   - gdqadmin 端: <WorkLogForm adapter="gdqadmin" :template :form-data @submit="onSubmit" />
   - minip 端:    <WorkLogForm adapter="minip" :template :form-data @submit="onSubmit" />
   - labor 端:    <WorkLogForm adapter="labor" :template :form-data @submit="onSubmit" />

  字段类型支持: text/number/date/time/textarea/select/radio/checkbox/location/image/recipients/participants
  必填校验: 走 validateForm (useWorkLog.js)
  payload 标准化: 走 formatPayload (useWorkLog.js)

  Props:
   - template: 当前选中模板 (含 fields[])
   - formData: 表单数据对象 (含 content, recipients, participants 等)
   - adapter: 'gdqadmin' | 'minip' | 'labor'
   - loading: '外部 saving 状态
   - mode: 'create' | 'update'

  Emits:
   - submit(payload)
   - cancel()
   - change(formData)  - 字段变化同步到父组件
-->
<template>
  <div :class="style.cardClass">
    <!-- 模板字段渲染 -->
    <div v-for="field in parsedFields" :key="field.name" class="mb-4">
      <label :class="style.labelClass">
        {{ field.label || field.name }}
        <span v-if="field.required" class="text-red-500 ml-1">*</span>
      </label>

      <!-- text -->
      <input
        v-if="field.type === 'text'"
        type="text"
        :value="formData.content[field.name] || ''"
        @input="onFieldChange(field.name, $event.target.value)"
        :class="style.inputClass + ' mt-1'"
        :placeholder="field.placeholder || ''"
      />

      <!-- textarea -->
      <textarea
        v-else-if="field.type === 'textarea'"
        :value="formData.content[field.name] || ''"
        @input="onFieldChange(field.name, $event.target.value)"
        :class="style.inputClass + ' mt-1'"
        rows="3"
        :placeholder="field.placeholder || ''"
      />

      <!-- number -->
      <input
        v-else-if="field.type === 'number'"
        type="number"
        :value="formData.content[field.name] || ''"
        @input="onFieldChange(field.name, Number($event.target.value))"
        :class="style.inputClass + ' mt-1'"
      />

      <!-- date -->
      <input
        v-else-if="field.type === 'date'"
        type="date"
        :value="formData.content[field.name] || ''"
        @input="onFieldChange(field.name, $event.target.value)"
        :class="style.inputClass + ' mt-1'"
      />

      <!-- time -->
      <input
        v-else-if="field.type === 'time'"
        type="time"
        :value="formData.content[field.name] || ''"
        @input="onFieldChange(field.name, $event.target.value)"
        :class="style.inputClass + ' mt-1'"
      />

      <!-- select -->
      <select
        v-else-if="field.type === 'select'"
        :value="formData.content[field.name] || ''"
        @change="onFieldChange(field.name, $event.target.value)"
        :class="style.inputClass + ' mt-1'"
      >
        <option value="">{{ field.placeholder || '请选择' }}</option>
        <option v-for="opt in (field.options || [])" :key="opt" :value="opt">{{ opt }}</option>
      </select>

      <!-- radio -->
      <div v-else-if="field.type === 'radio'" class="mt-2 flex flex-wrap gap-3">
        <label v-for="opt in (field.options || [])" :key="opt" class="flex items-center gap-1 cursor-pointer">
          <input
            type="radio"
            :name="field.name"
            :value="opt"
            :checked="formData.content[field.name] === opt"
            @change="onFieldChange(field.name, opt)"
          />
          <span class="text-sm">{{ opt }}</span>
        </label>
      </div>

      <!-- checkbox -->
      <div v-else-if="field.type === 'checkbox'" class="mt-2 flex flex-wrap gap-3">
        <label v-for="opt in (field.options || [])" :key="opt" class="flex items-center gap-1 cursor-pointer">
          <input
            type="checkbox"
            :value="opt"
            :checked="(formData.content[field.name] || []).includes(opt)"
            @change="onCheckboxChange(field.name, opt, $event.target.checked)"
          />
          <span class="text-sm">{{ opt }}</span>
        </label>
      </div>

      <!-- location (gps) -->
      <div v-else-if="field.type === 'location'" class="mt-2">
        <button
          v-if="!formData.content[field.name]"
          type="button"
          @click="captureLocation(field.name)"
          :class="style.buttonGhost + ' w-full'"
        >
          📍 获取位置
        </button>
        <div v-else class="flex items-center gap-2">
          <span class="text-sm text-slate-600 flex-1">📍 {{ formData.content[field.name] }}</span>
          <button
            type="button"
            @click="clearLocation(field.name)"
            class="text-red-500 text-sm"
          >
            ✕
          </button>
        </div>
      </div>

      <!-- image -->
      <div v-else-if="field.type === 'image'" class="mt-2">
        <input
          type="file"
          accept="image/*"
          multiple
          @change="onImageChange(field.name, $event)"
          class="text-sm"
        />
        <div v-if="(formData.content[field.name] || []).length" class="flex flex-wrap gap-2 mt-2">
          <img
            v-for="(img, idx) in formData.content[field.name]"
            :key="idx"
            :src="img"
            class="w-16 h-16 object-cover rounded-lg"
          />
        </div>
      </div>

      <!-- recipients (人员选择) -->
      <div v-else-if="field.type === 'recipients'" class="mt-2">
        <div v-if="formData[field.name] && formData[field.name].length" class="flex flex-wrap gap-2 mb-2">
          <span
            v-for="(id, idx) in formData[field.name]"
            :key="idx"
            class="px-3 py-1 bg-primary/10 text-primary rounded-full text-sm"
          >
            {{ getUserName(id) }} ✕
          </span>
        </div>
        <p v-else class="text-xs text-slate-400">点击下方选择收件人（可选）</p>
      </div>

      <!-- participants -->
      <div v-else-if="field.type === 'participants'" class="mt-2">
        <p class="text-xs text-slate-400">参与人员: {{ (formData.participants || []).length }} 人</p>
      </div>

      <!-- recipients/participants/complainants string fallback -->
      <input
        v-else-if="['recipients', 'participants', 'complainants'].includes(field.type)"
        type="text"
        :value="formData.content[field.name + '_str'] || formData.content[field.name] || ''"
        @input="onFieldChange(field.name + '_str', $event.target.value)"
        :class="style.inputClass + ' mt-1'"
        placeholder="用逗号分隔的用户ID或姓名"
      />

      <p v-if="field.help" class="text-xs text-slate-400 mt-1">{{ field.help }}</p>
    </div>

    <!-- 操作按钮 -->
    <div class="flex gap-3 mt-6">
      <button
        type="button"
        @click="$emit('cancel')"
        :class="style.buttonGhost"
      >
        取消
      </button>
      <button
        type="button"
        @click="handleSubmit"
        :disabled="loading"
        :class="style.buttonPrimary + (loading ? ' opacity-60 cursor-not-allowed' : '')"
      >
        {{ loading ? '提交中...' : '提交' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useWorkLog, getWorkLogAdapter } from '../../composables/useWorkLog.js'

const props = defineProps({
  template: { type: Object, default: null },
  formData: { type: Object, required: true },
  api: { type: Object, required: true }, // 必传: 三端各自的 axios instance
  adapter: { type: String, default: 'gdqadmin' },
  loading: { type: Boolean, default: false },
  mode: { type: String, default: 'create' },
  users: { type: Array, default: () => [] }
})

const emit = defineEmits(['submit', 'cancel', 'change'])

const style = computed(() => getWorkLogAdapter(props.adapter))

const { parseFields, formatPayload, validateForm } = useWorkLog(props.api)

const parsedFields = computed(() => parseFields(props.template?.fields))

function onFieldChange(name, value) {
  props.formData.content[name] = value
  emit('change', props.formData)
}

function onCheckboxChange(name, opt, checked) {
  const arr = props.formData.content[name] || []
  if (checked && !arr.includes(opt)) arr.push(opt)
  else if (!checked) {
    const i = arr.indexOf(opt)
    if (i > -1) arr.splice(i, 1)
  }
  props.formData.content[name] = [...arr]
  emit('change', props.formData)
}

function onImageChange(name, e) {
  const files = Array.from(e.target.files || [])
  // 三端通用: 转 base64 或直接 URL (后端接收 URL 数组)
  const urls = files.map(f => URL.createObjectURL(f))
  props.formData.content[name] = [...(props.formData.content[name] || []), ...urls]
  emit('change', props.formData)
}

function captureLocation(fieldName) {
  if (!navigator.geolocation) {
    alert('当前环境不支持定位')
    return
  }
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      props.formData.content[fieldName] = `${pos.coords.latitude.toFixed(6)}, ${pos.coords.longitude.toFixed(6)}`
      props.formData.gps_lat = pos.coords.latitude
      props.formData.gps_lng = pos.coords.longitude
      emit('change', props.formData)
    },
    (err) => alert('获取位置失败: ' + err.message)
  )
}

function clearLocation(fieldName) {
  props.formData.content[fieldName] = ''
  delete props.formData.gps_lat
  delete props.formData.gps_lng
  emit('change', props.formData)
}

function getUserName(id) {
  const u = props.users.find(u => u.id === id)
  return u?.name || u?.username || id
}

function handleSubmit() {
  const missing = validateForm(props.formData, props.template)
  if (missing) {
    alert(`${missing} 不能为空`)
    return
  }
  const payload = formatPayload(props.formData, props.template, { mode: props.mode })
  emit('submit', payload)
}
</script>