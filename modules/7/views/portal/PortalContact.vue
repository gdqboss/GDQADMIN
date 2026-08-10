<template>
  <div class="min-h-screen bg-gray-50">
    <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white py-16">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl md:text-4xl font-bold mb-3">{{ $t('portal.contact.title') }}</h1>
        <p class="text-amber-100">{{ info.slogan || '' }}</p>
      </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div v-if="info.phone" class="bg-white rounded-xl p-5 border border-gray-100 text-center">
          <div class="text-amber-600 text-3xl mb-2">📞</div>
          <div class="text-sm text-gray-500 mb-1">{{ $t('portal.footer.phone') }}</div>
          <div class="font-medium text-gray-800">{{ info.phone }}</div>
        </div>
        <div v-if="info.email" class="bg-white rounded-xl p-5 border border-gray-100 text-center">
          <div class="text-amber-600 text-3xl mb-2">✉</div>
          <div class="text-sm text-gray-500 mb-1">{{ $t('portal.footer.email') }}</div>
          <div class="font-medium text-gray-800">{{ info.email }}</div>
        </div>
        <div v-if="info.address" class="bg-white rounded-xl p-5 border border-gray-100 text-center">
          <div class="text-amber-600 text-3xl mb-2">📍</div>
          <div class="text-sm text-gray-500 mb-1">{{ $t('portal.footer.address') }}</div>
          <div class="font-medium text-gray-800">{{ info.address }}</div>
        </div>
      </div>

      <form @submit.prevent="submit" class="bg-white rounded-xl p-6 border border-gray-100">
        <h3 class="text-lg font-bold text-gray-800 mb-4">📝 在线留言</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('portal.contact.name') }}</label>
            <input v-model="form.name" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-amber-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('portal.form.phone') }}</label>
            <input v-model="form.phone" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-amber-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('portal.form.email') }}</label>
            <input v-model="form.email" type="email" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-amber-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('portal.contact.message') }}</label>
            <textarea v-model="form.message" rows="4" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-amber-500"></textarea>
          </div>
          <button type="submit" :disabled="submitting" class="w-full px-6 py-3 bg-amber-500 text-white rounded-lg hover:bg-amber-600 disabled:opacity-50">
            {{ submitting ? $t('portal.form.submitting') : $t('portal.contact.submit') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'

const info = ref({})
const submitting = ref(false)
const form = reactive({ name: '', phone: '', email: '', message: '' })

onMounted(async () => {
  try {
    const { api } = await import('@/services/api.js')
    const r = await api.get('/association/info')
    if (r.code === 0) info.value = r.data || {}
  } catch (e) {}
})

async function submit() {
  if (!form.name || !form.message) return ElMessage.error('请填写姓名和留言')
  submitting.value = true
  try {
    // 简易留言,写入 settings 表或单独 log（此处 console 占位）
    console.log('Contact form submitted:', form)
    ElMessage.success('留言已提交,我们会尽快回复')
    form.name = form.phone = form.email = form.message = ''
  } finally {
    submitting.value = false
  }
}
</script>