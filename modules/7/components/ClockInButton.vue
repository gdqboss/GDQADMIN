<template>
  <div class="clock-in-button">
    <button
      @click="handleClock"
      :disabled="loading || disabled"
      :class="[
        'px-6 py-3 rounded-lg font-semibold flex items-center gap-2 transition-all',
        type === 'in' ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-blue-600 hover:bg-blue-700 text-white',
        'disabled:opacity-50 disabled:cursor-not-allowed'
      ]"
    >
      <span class="material-symbols-outlined">{{ type === 'in' ? 'login' : 'logout' }}</span>
      <span>{{ type === 'in' ? $t('clockIn.clockIn') : $t('clockIn.clockOut') }}</span>
      <span v-if="loading" class="material-symbols-outlined animate-spin">refresh</span>
    </button>
    <p v-if="error" class="text-red-600 text-sm mt-2">{{ error }}</p>
    <p v-if="gpsInfo" class="text-gray-600 text-xs mt-1">{{ gpsInfo }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  type: { type: String, required: true }, // 'in' or 'out'
  disabled: { type: Boolean, default: false }
})

const emit = defineEmits(['success', 'error'])

const loading = ref(false)
const error = ref('')
const gpsInfo = ref('')

async function handleClock() {
  loading.value = true
  error.value = ''
  gpsInfo.value = ''

  try {
    // Get GPS location
    const position = await new Promise((resolve, reject) => {
      if (!navigator.geolocation) {
        reject(new Error(t('clockIn.gpsNotSupported')))
        return
      }
      navigator.geolocation.getCurrentPosition(resolve, reject, {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
      })
    })

    const { latitude, longitude, accuracy } = position.coords
    gpsInfo.value = `GPS: ${latitude.toFixed(6)}, ${longitude.toFixed(6)} (±${Math.round(accuracy)}m)`

    // Submit clock in/out
    const res = await fetch('/api/oa/attendance/clock', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('caimeite_token')}`
      },
      body: JSON.stringify({
        type: props.type,
        lat: latitude,
        lng: longitude,
        accuracy: accuracy,
        device_info: navigator.userAgent,
        ip: ''
      })
    })

    const data = await res.json()
    if (data.code === 0) {
      emit('success', data.data)
    } else {
      error.value = data.message || t('clockIn.clockFailed')
      emit('error', error.value)
    }
  } catch (err) {
    if (err.code === 1) {
      error.value = t('clockIn.gpsFailed')
    } else {
      error.value = err.message || t('clockIn.clockFailed')
    }
    emit('error', error.value)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.animate-spin {
  animation: spin 1s linear infinite;
}
</style>
