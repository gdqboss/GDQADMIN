<template>
  <div class="identity-qrcode">
    <div v-if="loading" class="text-center py-8">
      <p class="text-gray-600">{{ $t('identityQR.generating') }}</p>
    </div>
    <div v-else-if="identityCode" class="text-center">
      <div class="mb-4">
        <canvas ref="qrcodeCanvas" class="mx-auto border rounded-lg"></canvas>
      </div>
      <div class="space-y-2">
        <p class="text-sm text-gray-600">{{ $t('identityQR.identityCode') }}: <span class="font-mono font-semibold">{{ identityCode }}</span></p>
        <p class="text-xs text-gray-500">{{ $t('identityQR.scanToVerify') }}</p>
        <div class="flex gap-2 justify-center">
          <button @click="downloadQR" class="px-3 py-1 text-sm bg-primary text-white rounded hover:bg-primary-dark">{{ $t('identityQR.download') }}</button>
          <button @click="printQR" class="px-3 py-1 text-sm bg-gray-600 text-white rounded hover:bg-gray-700">{{ $t('identityQR.print') }}</button>
        </div>
      </div>
    </div>
    <div v-else class="text-center py-8">
      <p class="text-gray-600 mb-4">{{ $t('identityQR.notGenerated') }}</p>
      <button @click="generateCode" class="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark">{{ $t('identityQR.generateCode') }}</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'

const { t } = useI18n()

const props = defineProps({
  userId: { type: Number, required: true },
  userType: { type: String, default: 'system' }, // 'system' or 'h5'
  code: { type: String, default: '' }
})

const emit = defineEmits(['generated'])

const loading = ref(false)
const identityCode = ref(props.code)
const qrcodeCanvas = ref(null)

watch(() => props.code, (newCode) => {
  identityCode.value = newCode
  if (newCode) generateQRCode()
})

onMounted(() => {
  if (identityCode.value) {
    generatRCode()
  }
})

async function generateCode() {
  loading.value = true
  try {
    // Call API to generate identity code
    const endpoint = props.userType === 'system' ? '/identity/system/generate' : '/identity/h5/generate'
    const res = await fetch(`/api${endpoint}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('caimeite_token')}`
      },
      body: JSON.stringify({ userId: props.userId })
    })
    const data = await res.json()
    if (data.code === 0) {
      identityCode.value = data.data.identityCode
      emit('generated', data.data.identityCode)
      await generateQRCode()
    } else {
      alert(data.message || t('identityQR.generateFailed'))
    }
  } catch (err) {
    console.error('Failed to generate identity code:', err)
    alert(t('identityQR.generateFailed'))
  } finally {
    loading.value = false
  }
}

async function generateQRCode() {
  if (!qrcodeCanvas.value || !identityCode.value) return
  try {
    await QRCode.toCanvas(qrcodeCanvas.value, identityCode.value, {
      width: 200,
      margin: 2,
      color: {
        dark: '#000000',
        light: '#FFFFFF'
      }
    })
  } catch (err) {
    console.error('Failed to generate QR code:', err)
  }
}

function downloadQR() {
  if (!qrcodeCanvas.value) return
  const url = qrcodeCanvas.value.toDataURL('image/png')
  const link = document.createElement('a')
  link.download = `identity-${identityCode.value}.png`
  link.href = url
  link.click()
}

function printQR() {
  if (!qrcodeCanvas.value) return
  const printWindow = window.open('', '_blank')
  const url = qrcodeCanvas.value.toDataURL('image/png')
  printWindow.document.write(`
    <html>
      <head><title>${t('identityQR.printTitle')}</title></head>
      <body style="text-align: center; padding: 20px;">
        <h2>${t('identityQR.employeeIdentity')}</h2>
        <img src="${url}" style="width: 300px; height: 300px;" />
        <p>${t('identityQR.identityCode')}: ${identityCode.value}</p>
        <script>window.print(); window.close();</script>
      </body>
    </html>
  `)
  printWindow.document.close()
}
</script>

<style scoped>
.identity-qrcode {
  padding: 1rem;
}
</style>
