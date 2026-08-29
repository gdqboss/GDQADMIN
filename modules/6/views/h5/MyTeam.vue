<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const router = useRouter()
const loading = ref(true)
const team = ref([])
const tree = ref([])
const viewMode = ref('list') // 'list' or 'tree'

const h5Token = localStorage.getItem('h5_token')
const h5User = JSON.parse(localStorage.getItem('h5_user') || 'null')

onMounted(async () => {
  if (!h5Token) {
    router.push('/h5/login')
    return
  }

  await fetchTeam()
})

async function fetchTeam() {
  loading.value = true
  try {
    const res = await fetch('/api/h5/my-team', {
      headers: { 'Authorization': `Bearer ${h5Token}` }
    })
    const json = await res.json()
    if (json.code === 0) {
      team.value = json.data
    } else if (json.code === 403) {
      alert(t('h5.vipRequired'))
      router.push('/scan')
    }
  } finally {
    loading.value = false
  }
}

async function fetchTree() {
  loading.value = true
  try {
    const res = await fetch('/api/h5/my-tree', {
      headers: { 'Authorization': `Bearer ${h5Token}` }
    })
    const json = await res.json()
    if (json.code === 0) {
      tree.value = json.data
    }
  } finally {
    loading.value = false
  }
}

function switchMode(mode) {
  viewMode.value = mode
  if (mode === 'tree' && tree.value.length === 0) {
    fetchTree()
  }
}

function roleLabel(role) {
  const map = {
    customer: t('h5.roleCustomer'),
    salesperson: t('h5.roleSalesperson'),
    cashier: t('h5.roleCashier'),
    service: t('h5.roleService'),
    vip: t('h5.roleVip')
  }
  return map[role] || role
}

function getDepthIndent(depth) {
  return `${(depth - 1) * 20}px`
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white border-b border-gray-100 px-4 py-3 flex items-center gap-2 shadow-sm">
      <button @click="router.back()" class="text-text-secondary hover:text-primary">
        <span class="material-symbols-outlined text-[20px]">arrow_back</span>
      </button>
      <span class="text-primary font-bold text-lg">{{ t('h5.myTeam') }}</span>
    </div>

    <div class="max-w-4xl mx-auto p-4">
      <!-- View Mode Toggle -->
      <div class="bg-white rounded-lg shadow-sm p-4 mb-4 flex gap-2">
        <button
          @click="switchMode('list')"
          :class="['flex-1 py-2 rounded-lg text-sm font-medium transition-colors', viewMode === 'list' ? 'bg-primary text-white' : 'bg-gray-100 text-text-secondary']"
        >
          {{ t('h5.directList') }}
        </button>
        <button
          @click="switchMode('tree')"
          :class="['flex-1 py-2 rounded-lg text-sm font-medium transition-colors', viewMode === 'tree' ? 'bg-primary text-white' : 'bg-gray-100 text-text-secondary']"
        >
          {{ t('h5.teamTree') }}
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-20 text-text-secondary gap-3">
        <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm">{{ t('h5.loading') }}</p>
      </div>

      <!-- List View -->
      <div v-else-if="viewMode === 'list'" class="space-y-3">
        <div v-if="team.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
          <p class="text-text-secondary">{{ t('h5.noDirectMembers') }}</p>
        </div>
        <div v-else v-for="member in team" :key="member.id" class="bg-white rounded-lg shadow-sm p-4">
          <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                <span class="text-primary font-bold">{{ member.name?.charAt(0) || 'U' }}</span>
              </div>
              <div>
                <p class="font-medium text-text-primary">{{ member.name }}</p>
                <p class="text-xs text-text-secondary font-mono">{{ member.phone }}</p>
              </div>
            </div>
            <div class="text-right">
              <span class="px-2 py-1 bg-primary/10 text-primary rounded text-xs">{{ roleLabel(member.role) }}</span>
              <p class="text-xs text-text-secondary mt-1">{{ t('h5.level') }} {{ member.level }}</p>
            </div>
          </div>
          <div class="text-xs text-text-secondary">
            {{ t('h5.joinedAt') }} {{ member.created_at?.slice(0, 10) }}
          </div>
        </div>
      </div>

      <!-- Tree View -->
      <div v-else-if="viewMode === 'tree'" class="bg-white rounded-lg shadow-sm p-4">
        <div v-if="tree.length === 0" class="py-8 text-center text-text-secondary">
          {{ t('h5.noTeamData') }}
        </div>
        <div v-else class="space-y-2">
          <div v-for="member in tree" :key="member.id" class="py-2 border-b border-gray-100 last:border-0">
            <div class="flex items-center gap-2" :style="{ paddingLeft: getDepthIndent(member.depth) }">
              <span v-if="member.depth > 1" class="text-text-secondary">└─</span>
              <div class="flex-1 flex items-center justify-between">
                <div>
                  <span class="font-medium text-text-primary">{{ member.name }}</span>
                  <span class="text-xs text-text-secondary ml-2 font-mono">{{ member.phone }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="px-2 py-0.5 bg-primary/10 text-primary rounded text-xs">{{ roleLabel(member.role) }}</span>
                  <span class="text-xs text-text-secondary">L{{ member.level }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
