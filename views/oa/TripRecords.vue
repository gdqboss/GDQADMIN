<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">{{ $t('oa.tripRecords') }}</h1>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">{{ $t('common.employee') }}</label>
        <select v-model="filters.user_id" class="w-full border rounded px-3 py-2">
          <option value="">{{ $t('oa.allEmployees') }}</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}（{{ e.department || '-' }}）</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">{{ $t('common.startDate') }}</label>
        <input v-model="filters.start_date" type="date" class="w-full border rounded px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">{{ $t('common.endDate') }}</label>
        <input v-model="filters.end_date" type="date" class="w-full border rounded px-3 py-2" />
      </div>
      <div class="flex items-end">
        <button @click="fetchTrips" class="w-full px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
          {{ $t('common.search') }}
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="text-sm text-blue-600 mb-1">{{ $t('oa.tripTodayCount') }}</div>
        <div class="text-2xl font-bold text-blue-700">{{ todayCount }}</div>
      </div>
      <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="text-sm text-green-600 mb-1">{{ $t('oa.tripTotalCount') }}</div>
        <div class="text-2xl font-bold text-green-700">{{ trips.length }}</div>
      </div>
      <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
        <div class="text-sm text-purple-600 mb-1">{{ $t('oa.tripPeopleCount') }}</div>
        <div class="text-2xl font-bold text-purple-700">{{ peopleCount }}</div>
      </div>
    </div>

    <!-- Trip Records Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('common.employee') }}</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('common.department') }}</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('oa.tripDate') }}</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('oa.tripTime') }}</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('oa.tripLocation') }}</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('oa.tripRemark') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="trips.length === 0">
            <td colspan="6" class="px-4 py-6 text-center text-gray-400">{{ $t('common.noData') }}</td>
          </tr>
          <tr v-for="item in trips" :key="item.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-sm">{{ item.user_name }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ item.department || '-' }}</td>
            <td class="px-4 py-3 text-sm">{{ item.trip_date }}</td>
            <td class="px-4 py-3 text-sm">{{ item.log_time }}</td>
            <td class="px-4 py-3 text-sm">{{ item.location || '-' }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ item.remark || '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../stores/user'
import api from '../../services/api.js'

const { t } = useI18n()
const userStore = useUserStore()
const trips = ref([])
const employees = ref([])
const filters = ref({
  user_id: '',
  start_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
  end_date: new Date().toISOString().slice(0, 10)
})

const todayStr = new Date().toISOString().slice(0, 10)
const todayCount = computed(() => trips.value.filter(x => String(x.trip_date) === todayStr).length)
const peopleCount = computed(() => new Set(trips.value.map(x => x.user_id)).size)

onMounted(async () => {
  await fetchEmployees()
  await fetchTrips()
})

async function fetchEmployees() {
  try {
    const res = await api.get('/oa/employees', { params: { size: 1000 } })
    if (res.code === 0) employees.value = res.data.list
  } catch (err) {
    console.error('Failed to fetch employees:', err)
  }
}

async function fetchTrips() {
  try {
    const params = { ...filters.value }
    // 非管理员只能看自己的
    if (!userStore.canAccess('attendance:manage')) {
      params.user_id = userStore.user?.id
    }
    const res = await api.get('/oa/attendance/trip-logs', { params })
    if (res.code === 0) {
      trips.value = (res.data.list || res.data).map(x => ({
        ...x,
        trip_date: String(x.trip_date).slice(0, 10),
        log_time: String(x.log_time).slice(0, 5)
      }))
    }
  } catch (err) {
    console.error('Failed to fetch trips:', err)
  }
}
</script>
