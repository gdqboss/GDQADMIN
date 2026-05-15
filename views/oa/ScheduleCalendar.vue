<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">{{ $t('oa.scheduleCalendar') }}</h1>
      <div class="flex gap-2">
        <button @click="prevMonth" class="px-3 py-2 border rounded hover:bg-gray-50">
          ← {{ $t('common.prev') }}
        </button>
        <button @click="today" class="px-3 py-2 border rounded hover:bg-gray-50">
          {{ $t('common.today') }}
        </button>
        <button @click="nextMonth" class="px-3 py-2 border rounded hover:bg-gray-50">
          {{ $t('common.next') }} →
        </button>
        <button @click="showBatchDialog = true" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
          {{ $t('oa.batchSchedule') }}
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="mb-4 flex gap-4">
      <select v-model="filters.department" @change="fetchSchedules" class="border rounded px-3 py-2">
        <option value="">{{ $t('common.allDepartments') }}</option>
        <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
      </select>
      <select v-model="filters.user_id" @change="fetchSchedules" class="border rounded px-3 py-2">
        <option value="">{{ $t('common.allEmployees') }}</option>
        <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }}</option>
      </select>
    </div>

    <!-- Calendar Header -->
    <div class="text-center text-xl font-bold mb-4">
      {{ $t('oa.yearMonth', { year: currentYear, month: currentMonth }) }}
    </div>

    <!-- Calendar Grid -->
    <div class="grid grid-cols-7 gap-2">
      <div v-for="day in weekDays" :key="day" class="text-center font-semibold py-2 bg-gray-100">
        {{ day }}
      </div>
      <div v-for="(date, idx) in calendarDates" :key="idx"
           class="border rounded p-2 min-h-32 hover:bg-gray-50 transition-colors"
           :class="{ 'bg-gray-50': !date.isCurrentMonth, 'bg-blue-50': date.isToday }">
        <div class="text-sm font-semibold mb-1" :class="{ 'text-gray-400': !date.isCurrentMonth }">
          {{ date.day }}
        </div>
        <div class="space-y-1">
          <div v-for="schedule in date.schedules" :key="schedule.id"
               class="text-xs p-1 rounded cursor-pointer hover:opacity-80"
               :style="{ backgroundColor: schedule.color + '20', borderLeft: `3px solid ${schedule.color}` }"
               @click="editSchedule(schedule)">
            <div class="font-medium truncate">{{ schedule.user_name }}</div>
            <div class="text-gray-600">{{ schedule.shift_name }}</div>
          </div>
        </div>
        <button v-if="date.isCurrentMonth" @click="addSchedule(date.fullDate)"
                class="mt-1 text-xs text-blue-600 hover:underline">
          + {{ $t('common.add') }}
        </button>
      </div>
    </div>

    <!-- Batch Schedule Dialog -->
    <div v-if="showBatchDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <h2 class="text-xl font-bold mb-4">{{ $t('oa.batchSchedule') }}</h2>
        <form @submit.prevent="saveBatchSchedule" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">{{ $t('common.startDate') }}</label>
              <input v-model="batchForm.start_date" type="date" required class="w-full border rounded px-3 py-2" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">{{ $t('common.endDate') }}</label>
              <input v-model="batchForm.end_date" type="date" required class="w-full border rounded px-3 py-2" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('common.employees') }}</label>
            <select v-model="batchForm.user_ids" multiple size="5" class="w-full border rounded px-3 py-2">
              <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }} - {{ emp.department }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.shift') }}</label>
            <select v-model="batchForm.shift_id" required class="w-full border rounded px-3 py-2">
              <option v-for="shift in shifts" :key="shift.id" :value="shift.id">{{ shift.name }}</option>
            </select>
          </div>
          <!-- Weekday checkboxes -->
          <div>
            <label class="block text-sm font-medium mb-2">{{ $t('oa.repeatWeekdays') }}</label>
            <div class="flex flex-wrap gap-2">
              <label v-for="(day, idx) in weekDayOptions" :key="idx"
                     class="inline-flex items-center gap-1 px-3 py-1.5 border rounded-lg cursor-pointer transition-colors"
                     :class="batchForm.weekdays.includes(idx) ? 'bg-blue-100 border-blue-400 text-blue-700' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'">
                <input type="checkbox" :value="idx" v-model="batchForm.weekdays" class="hidden" />
                {{ day }}
              </label>
            </div>
            <p class="text-xs text-gray-400 mt-1">{{ $t('oa.weekdayHint') }}</p>
          </div>
          <div class="flex gap-2 justify-end">
            <button type="button" @click="showBatchDialog = false" class="px-4 py-2 border rounded hover:bg-gray-50">
              {{ $t('common.cancel') }}
            </button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
              {{ $t('common.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'

const { t } = useI18n()
const currentDate = ref(new Date())
const schedules = ref([])
const shifts = ref([])
const employees = ref([])
const departments = ref([])
const showBatchDialog = ref(false)
const filters = ref({ department: '', user_id: '' })
const batchForm = ref({
  start_date: '',
  end_date: '',
  user_ids: [],
  shift_id: null,
  weekdays: [1, 2, 3, 4, 5] // Default Mon-Fri
})

const weekDays = computed(() => [t('oa.weekSun'), t('oa.weekMon'), t('oa.weekTue'), t('oa.weekWed'), t('oa.weekThu'), t('oa.weekFri'), t('oa.weekSat')])
const weekDayOptions = computed(() => [t('oa.weekSun'), t('oa.weekMon'), t('oa.weekTue'), t('oa.weekWed'), t('oa.weekThu'), t('oa.weekFri'), t('oa.weekSat')])

const currentYear = computed(() => currentDate.value.getFullYear())
const currentMonth = computed(() => currentDate.value.getMonth() + 1)

const calendarDates = computed(() => {
  const year = currentYear.value
  const month = currentMonth.value
  const firstDay = new Date(year, month - 1, 1)
  const lastDay = new Date(year, month, 0)
  const prevLastDay = new Date(year, month - 1, 0)

  const dates = []
  const startWeekDay = firstDay.getDay()

  // Previous month days
  for (let i = startWeekDay - 1; i >= 0; i--) {
    const day = prevLastDay.getDate() - i
    dates.push({
      day,
      fullDate: `${year}-${String(month - 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`,
      isCurrentMonth: false,
      isToday: false,
      schedules: []
    })
  }

  // Current month days
  for (let day = 1; day <= lastDay.getDate(); day++) {
    const fullDate = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    const today = new Date().toISOString().slice(0, 10)
    dates.push({
      day,
      fullDate,
      isCurrentMonth: true,
      isToday: fullDate === today,
      schedules: schedules.value.filter(s => s.schedule_date === fullDate)
    })
  }

  // Next month days
  const remaining = 42 - dates.length
  for (let day = 1; day <= remaining; day++) {
    dates.push({
      day,
      fullDate: `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`,
      isCurrentMonth: false,
      isToday: false,
      schedules: []
    })
  }

  return dates
})

onMounted(() => {
  fetchShifts()
  fetchEmployees()
  fetchSchedules()
})

async function fetchShifts() {
  try {
    const res = await axios.get('/api/oa/shifts', { params: { status: 'active' } })
    if (res.data.code === 0) shifts.value = res.data.data
  } catch (err) {
    console.error('Failed to fetch shifts:', err)
  }
}

async function fetchEmployees() {
  try {
    const res = await axios.get('/api/oa/employees', { params: { status: 'active', size: 1000 } })
    if (res.data.code === 0) {
      employees.value = res.data.data.list
      departments.value = [...new Set(employees.value.map(e => e.department).filter(Boolean))]
    }
  } catch (err) {
    console.error('Failed to fetch employees:', err)
  }
}

async function fetchSchedules() {
  try {
    const year = currentYear.value
    const month = currentMonth.value
    const start_date = `${year}-${String(month).padStart(2, '0')}-01`
    const end_date = new Date(year, month, 0).toISOString().slice(0, 10)

    const res = await axios.get('/api/oa/schedules', {
      params: { start_date, end_date, ...filters.value, size: 1000 }
    })
    if (res.data.code === 0) schedules.value = res.data.data.list
  } catch (err) {
    console.error('Failed to fetch schedules:', err)
  }
}

function prevMonth() {
  currentDate.value = new Date(currentYear.value, currentMonth.value - 2, 1)
  fetchSchedules()
}

function nextMonth() {
  currentDate.value = new Date(currentYear.value, currentMonth.value, 1)
  fetchSchedules()
}

function today() {
  currentDate.value = new Date()
  fetchSchedules()
}

function addSchedule(date) {
  // TODO: Implement add schedule dialog
  console.log('Add schedule for', date)
}

function editSchedule(schedule) {
  // TODO: Implement edit schedule dialog
  console.log('Edit schedule', schedule)
}

async function saveBatchSchedule() {
  try {
    const { start_date, end_date, user_ids, shift_id, weekdays } = batchForm.value

    if (user_ids.length === 0) {
      alert(t('oa.pleaseSelectEmployee'))
      return
    }

    // Generate schedules for each day and each user, filtered by weekdays
    const scheduleList = []
    const start = new Date(start_date)
    const end = new Date(end_date)

    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
      // Filter by selected weekdays
      if (weekdays.length > 0 && !weekdays.includes(d.getDay())) continue

      const dateStr = d.toISOString().slice(0, 10)
      for (const user_id of user_ids) {
        const emp = employees.value.find(e => e.id === user_id)
        scheduleList.push({
          user_id,
          shift_id,
          schedule_date: dateStr,
          department: emp?.department
        })
      }
    }

    if (scheduleList.length === 0) {
      alert(t('oa.noScheduleGenerated'))
      return
    }

    const res = await axios.post('/api/oa/schedules', { schedules: scheduleList, weekdays })
    if (res.data.code === 0) {
      alert(t('oa.batchScheduleSuccess', { count: res.data.data.count }))
    }
    showBatchDialog.value = false
    batchForm.value = { start_date: '', end_date: '', user_ids: [], shift_id: null, weekdays: [1, 2, 3, 4, 5] }
    fetchSchedules()
  } catch (err) {
    alert(err.response?.data?.message || t('oa.createScheduleFailed'))
  }
}
</script>
