<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

const { t } = useI18n()

const loading = ref(false)
const roomTypes = ref([])
const currentYear = ref(new Date().getFullYear())
const currentMonth = ref(new Date().getMonth() + 1)
const priceData = ref({})
const editDialogVisible = ref(false)
const editForm = ref({ date: '', room_type_id: '', price: 0, rooms_available: 0 })
const editLoading = ref(false)

const calendarDays = computed(() => {
  const year = currentYear.value
  const month = currentMonth.value
  const firstDay = new Date(year, month - 1, 1)
  const lastDay = new Date(year, month, 0)
  const days = []
  //填充空白
  const startWeekday = firstDay.getDay()
  for (let i = 0; i < startWeekday; i++) {
    days.push({ day: null, date: null })
  }
  for (let d = 1; d <= lastDay.getDate(); d++) {
    const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    days.push({ day: d, date: dateStr })
  }
  return days
})

const monthLabel = computed(() => {
  return `${currentYear.value}年${currentMonth.value}月`
})

function prevMonth() {
  if (currentMonth.value === 1) {
    currentMonth.value = 12
    currentYear.value--
  } else {
    currentMonth.value--
  }
  fetchPriceData()
}

function nextMonth() {
  if (currentMonth.value === 12) {
    currentMonth.value = 1
    currentYear.value++
  } else {
    currentMonth.value++
  }
  fetchPriceData()
}

async function fetchRoomTypes() {
  try {
    const res = await api.get('/hotel/room-types')
    if (res.code === 0) {
      roomTypes.value = res.data || []
    }
  } catch (e) {
    // ignore
  }
}

async function fetchPriceData() {
  loading.value = true
  try {
    const monthStr = `${currentYear.value}-${String(currentMonth.value).padStart(2, '0')}`
    const res = await api.get('/hotel/price-calendar', {
      params: { month: monthStr }
    })
    if (res.code === 0) {
      priceData.value = res.data || {}
    }
  } catch (e) {
    ElMessage.error(e.message || '获取价格日历失败')
  } finally {
    loading.value = false
  }
}

function getCellData(dateStr, roomTypeId) {
  if (!dateStr || !priceData.value[dateStr]) return null
  return priceData.value[dateStr][roomTypeId]
}

function openEdit(dateStr, roomTypeId) {
  const existing = getCellData(dateStr, roomTypeId)
  editForm.value = {
    date: dateStr,
    room_type_id: roomTypeId,
    price: existing?.price || 0,
    rooms_available: existing?.rooms_available || 0,
  }
  editDialogVisible.value = true
}

async function submitEdit() {
  editLoading.value = true
  try {
    const res = await api.post('/hotel/price-calendar', {
      date: editForm.value.date,
      room_type_id: editForm.value.room_type_id,
      price: editForm.value.price,
      rooms_available: editForm.value.rooms_available,
    })
    if (res.code === 0) {
      ElMessage.success('保存成功')
      editDialogVisible.value = false
      fetchPriceData()
    } else {
      ElMessage.error(res.message || '保存失败')
    }
  } catch (e) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    editLoading.value = false
  }
}

onMounted(() => {
  fetchRoomTypes()
  fetchPriceData()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="价格日历" subtitle="批量设置每日房型价格与可订房间数" />

    <!-- Month navigator -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4 flex items-center gap-4">
      <el-button @click="prevMonth">&lt; 上月</el-button>
      <span class="text-lg font-semibold min-w-[120px] text-center">{{ monthLabel }}</span>
      <el-button @click="nextMonth">下月 &gt;</el-button>
    </div>

    <!-- Calendar -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <!-- Room type header -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b">
              <th class="p-3 text-left text-gray-500 font-medium min-w-[100px]">日期</th>
              <th
                v-for="rt in roomTypes"
                :key="rt.id"
                class="p-3 text-center text-gray-600 font-medium min-w-[120px]"
              >
                {{ rt.name }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="cell in calendarDays"
              :key="cell.date"
              class="border-b last:border-0 hover:bg-gray-50"
              :class="{ 'bg-gray-100': !cell.day }"
            >
              <!-- Date cell -->
              <td class="p-3 text-gray-600 min-w-[100px]">
                <span v-if="cell.day" class="font-medium">{{ cell.day }}日</span>
                <span v-else class="text-gray-300">-</span>
              </td>
              <!-- Price cells -->
              <td
                v-for="rt in roomTypes"
                :key="rt.id"
                class="p-2 text-center min-w-[120px]"
              >
                <div v-if="cell.day" class="flex flex-col gap-1">
                  <div class="text-blue-600 font-medium">
                    ¥ {{ getCellData(cell.date, rt.id)?.price || '-' }}
                  </div>
                  <div class="text-xs text-gray-400">
                    {{ getCellData(cell.date, rt.id)?.rooms_available ?? '-' }}间
                  </div>
                  <button
                    class="text-xs text-blue-500 hover:underline"
                    @click="openEdit(cell.date, rt.id)"
                  >
                    {{ getCellData(cell.date, rt.id) ? '修改' : '设置' }}
                  </button>
                </div>
               <div v-else class="text-gray-300">-</div>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="loading" class="absolute inset-0 bg-white/50 flex items-center justify-center">
          <el-icon class="is-loading text-2xl text-blue-600"><Loading /></el-icon>
        </div>
      </div>
    </div>

    <!-- Edit dialog -->
    <el-dialog v-model="editDialogVisible" title="设置价格" width="400px">
      <el-form :model="editForm" label-width="100px">
        <el-form-item label="日期">
          <el-input :model-value="editForm.date" disabled />
        </el-form-item>
        <el-form-item label="价格">
          <el-input-number v-model="editForm.price" :min="0" :precision="2" class="!w-full" />
        </el-form-item>
        <el-form-item label="可订房间数">
          <el-input-number v-model="editForm.rooms_available" :min="0" :max="99" class="!w-full" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="editLoading" @click="submitEdit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>