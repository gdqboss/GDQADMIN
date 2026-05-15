<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800">{{ $t('oa.directoryTitle') }}</h1>
      <p class="text-gray-600 mt-1">{{ $t('oa.directorySubtitle') }}</p>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
      <input
        v-model="search"
        type="text"
        :placeholder="$t('oa.searchEmployeePlaceholder')"
        class="w-full px-4 py-2 border rounded-lg mb-6"
        @input="filterEmployees"
      />

      <!-- Department Tree -->
      <div v-if="departments.length" class="mb-6">
        <h3 class="font-semibold mb-3">{{ $t('oa.browseDepartment') }}</h3>
        <div class="space-y-2">
          <div v-for="dept in departments" :key="dept.id" class="border rounded-lg">
            <button
              @click="toggleDept(dept.id)"
              class="w-full flex items-center justify-between p-3 hover:bg-gray-50"
            >
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">corporate_fare</span>
                <span class="font-medium">{{ dept.name }}</span>
                <span class="text-sm text-gray-500">({{ dept.member_count }}{{ $t('common.people') }})</span>
              </div>
              <span class="material-symbols-outlined">{{ expandedDepts.includes(dept.id) ? 'expand_less' : 'expand_more' }}</span>
            </button>
            <div v-if="expandedDepts.includes(dept.id)" class="p-3 bg-gray-50">
              <div v-if="deptMembers[dept.id]" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <div v-for="emp in deptMembers[dept.id]" :key="emp.id" class="bg-white border rounded-lg p-3">
                  <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold">
                      {{ emp.name?.charAt(0) || '?' }}
                    </div>
                    <div>
                      <h4 class="font-semibold">{{ emp.name }}</h4>
                      <p class="text-xs text-gray-600">{{ emp.job_level_name || emp.role }}</p>
                    </div>
                  </div>
                  <div class="text-xs space-y-1">
                    <p><span class="text-gray-600">{{ $t('oa.phoneLabel') }}</span> {{ emp.phone || '-' }}</p>
                    <p><span class="text-gray-600">{{ $t('oa.emailLabel') }}</span> {{ emp.email || '-' }}</p>
                  </div>
                </div>
              </div>
              <p v-else class="text-sm text-gray-500">{{ $t('oa.noMembers') }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- All Employees Grid -->
      <div v-if="filteredEmployees.length">
        <h3 class="font-semibold mb-3">{{ $t('oa.allEmployeesLabel') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="employee in filteredEmployees" :key="employee.id" class="border rounded-lg p-4 hover:shadow-md transition">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center text-lg font-bold">
                {{ employee.name?.charAt(0) || '?' }}
              </div>
              <div>
                <h3 class="font-semibold">{{ employee.name }}</h3>
                <p class="text-sm text-gray-600">{{ employee.department || '-' }}</p>
              </div>
            </div>
            <div class="text-sm space-y-1">
              <p><span class="text-gray-600">{{ $t('oa.phoneLabel') }}</span> {{ employee.phone || '-' }}</p>
              <p><span class="text-gray-600">{{ $t('oa.emailLabel') }}</span> {{ employee.email || '-' }}</p>
              <p><span class="text-gray-600">{{ $t('oa.roleLabel') }}</span> {{ employee.role || '-' }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api'

const { t } = useI18n()

const search = ref('')
const employees = ref([])
const departments = ref([])
const expandedDepts = ref([])
const deptMembers = ref({})

const filteredEmployees = computed(() => {
  if (!search.value) return employees.value
  const term = search.value.toLowerCase()
  return employees.value.filter(emp =>
    emp.name?.toLowerCase().includes(term) ||
    emp.department?.toLowerCase().includes(term) ||
    emp.phone?.includes(term) ||
    emp.email?.toLowerCase().includes(term)
  )
})

onMounted(() => {
  loadEmployees()
  loadDepartments()
})

async function loadEmployees() {
  try {
    const res = await api.get('/users')
    if (res.code === 0) {
      employees.value = res.data.filter(u => u.status === 'active')
    }
  } catch (err) {
    console.error('Failed to load employees:', err)
  }
}

async function loadDepartments() {
  try {
    const res = await api.get('/oa/departments')
    if (res.code === 0) {
      departments.value = res.data
    }
  } catch (err) {
    console.error('Failed to load departments:', err)
  }
}

async function toggleDept(deptId) {
  const idx = expandedDepts.value.indexOf(deptId)
  if (idx > -1) {
    expandedDepts.value.splice(idx, 1)
  } else {
    expandedDepts.value.push(deptId)
    if (!deptMembers.value[deptId]) {
      await loadDeptMembers(deptId)
    }
  }
}

async function loadDeptMembers(deptId) {
  try {
    const res = await api.get(`/oa/departments/${deptId}/members`)
    if (res.code === 0) {
      deptMembers.value[deptId] = res.data
    }
  } catch (err) {
    console.error('Failed to load department members:', err)
  }
}

function filterEmployees() {
  // Reactive computed will handle this
}
</script>
