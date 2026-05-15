<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import PageHeader from '../../components/PageHeader.vue'
import api from '../../services/api.js'
import { useUserStore } from '../../stores/user.js'

const { t } = useI18n()
const router = useRouter()
const userStore = useUserStore()

const warehouses = ref([])
const showModal = ref(false)
const isEdit = ref(false)
const form = ref({
  id: null,
  name: '',
  address: '',
  type: t('warehouse.domestic'),
  manager: '',
  status: 'active'
})

const typeColors = computed(() => ({
  [t('warehouse.domestic')]: 'bg-blue-100 text-primary',
  [t('warehouse.overseas')]: 'bg-green-100 text-success',
  [t('warehouse.bonded')]: 'bg-orange-100 text-warning',
}))

const isAdmin = computed(() => userStore.userRole === 'admin')

async function loadWarehouses() {
  const res = await api.get('/warehouses')
  warehouses.value = res.data
}

onMounted(loadWarehouses)

function openAddModal() {
  isEdit.value = false
  form.value = {
    id: null,
    name: '',
    address: '',
    type: t('warehouse.domestic'),
    manager: '',
    status: 'active'
  }
  showModal.value = true
}

function openEditModal(wh, event) {
  event.preventDefault()
  event.stopPropagation()
  isEdit.value = true
  form.value = {
    id: wh.id,
    name: wh.name,
    address: wh.address || '',
    type: wh.type || t('warehouse.domestic'),
    manager: wh.manager || '',
    status: wh.status || 'active'
  }
  showModal.value = true
}

async function handleSubmit() {
  try {
    if (!form.value.name) {
      alert(t('warehouse.nameRequired'))
      return
    }

    if (isEdit.value) {
      await api.put(`/warehouses/${form.value.id}`, form.value)
      alert(t('warehouse.updateSuccess'))
    } else {
      await api.post('/warehouses', form.value)
      alert(t('warehouse.createSuccess'))
    }

    showModal.value = false
    await loadWarehouses()
  } catch (err) {
    alert(err.response?.data?.message || err.message || t('warehouse.operationFailed'))
  }
}

async function handleDelete(wh, event) {
  event.preventDefault()
  event.stopPropagation()

  if (!confirm(t('warehouse.confirmDelete', { name: wh.name }))) {
    return
  }

  try {
    await api.delete(`/warehouses/${wh.id}`)
    alert(t('warehouse.deleteSuccess'))
    await loadWarehouses()
  } catch (err) {
    alert(err.response?.data?.message || err.message || t('warehouse.deleteFailed'))
  }
}

function viewDetail(wh) {
  router.push(`/warehouses/${wh.id}`)
}
</script>

<template>
  <div>
    <PageHeader :title="$t('warehouse.title')" :subtitle="$t('warehouse.subtitle')">
      <template #actions>
        <button
          @click="openAddModal"
          class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
        >
          <span class="material-symbols-outlined text-[18px]">add</span>
          {{ $t('warehouse.add') }}
        </button>
      </template>
    </PageHeader>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div
        v-for="wh in warehouses"
        :key="wh.id"
        @click="viewDetail(wh)"
        class="bg-white rounded-lg border border-gray-100 shadow-card hover:shadow-card-hover transition-all p-5 group cursor-pointer"
      >
        <div class="flex items-start justify-between mb-4">
          <div class="flex-1">
            <h3 class="text-lg font-bold text-text-primary group-hover:text-primary transition-colors">{{ wh.name }}</h3>
            <p class="text-xs text-text-secondary mt-1">{{ wh.address }}</p>
          </div>
          <div class="flex items-center gap-2">
            <span :class="['text-xs font-medium px-2 py-0.5 rounded', typeColors[wh.type] || 'bg-gray-100 text-info']">{{ wh.type }}</span>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-4 mb-4">
          <div>
            <p class="text-xs text-text-secondary">{{ $t('warehouse.stockCount') }}</p>
            <p class="text-lg font-bold text-text-primary">{{ (wh.totalQty || 0).toLocaleString() }}</p>
          </div>
          <div>
            <p class="text-xs text-text-secondary">{{ $t('warehouse.productTypes') }}</p>
            <p class="text-lg font-bold text-text-primary">{{ wh.productCount || 0 }}</p>
          </div>
          <div>
            <p class="text-xs text-text-secondary">{{ $t('warehouse.manager') }}</p>
            <p class="text-sm font-medium text-text-primary">{{ wh.manager }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2 pt-3 border-t border-gray-100">
          <button
            @click="openEditModal(wh, $event)"
            class="flex items-center gap-1 text-primary hover:text-primary-hover text-xs font-medium transition-colors"
          >
            <span class="material-symbols-outlined text-[16px]">edit</span>
            {{ $t('common.edit') }}
          </button>
          <button
            v-if="isAdmin"
            @click="handleDelete(wh, $event)"
            class="flex items-center gap-1 text-danger hover:text-red-700 text-xs font-medium transition-colors ml-auto"
          >
            <span class="material-symbols-outlined text-[16px]">delete</span>
            {{ $t('common.delete') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30" @click="showModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="text-lg font-bold text-text-primary">{{ isEdit ? $t('warehouse.editWarehouse') : $t('warehouse.add') }}</h3>
            <button @click="showModal = false" class="text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <div class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">
                {{ $t('settings.warehouseName') }} <span class="text-danger">*</span>
              </label>
              <input
                v-model="form.name"
                type="text"
                :placeholder="$t('warehouse.namePlaceholder')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('warehouse.address') }}</label>
              <input
                v-model="form.address"
                type="text"
                :placeholder="$t('warehouse.addressPlaceholder')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('warehouse.type') }}</label>
              <select
                v-model="form.type"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              >
                <option :value="$t('warehouse.domestic')">{{ $t('warehouse.domestic') }}</option>
                <option :value="$t('warehouse.overseas')">{{ $t('warehouse.overseas') }}</option>
                <option :value="$t('warehouse.bonded')">{{ $t('warehouse.bonded') }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('warehouse.manager') }}</label>
              <input
                v-model="form.manager"
                type="text"
                :placeholder="$t('warehouse.managerPlaceholder')"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              />
            </div>

            <div v-if="isEdit">
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.status') }}</label>
              <select
                v-model="form.status"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              >
                <option value="active">{{ $t('common.active') }}</option>
                <option value="inactive">{{ $t('common.inactive') }}</option>
              </select>
            </div>
          </div>

          <div class="px-6 py-4 border-t flex gap-3 justify-end">
            <button
              @click="showModal = false"
              class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-text-primary hover:bg-gray-50 transition-colors"
            >
              {{ $t('common.cancel') }}
            </button>
            <button
              @click="handleSubmit"
              class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors"
            >
              {{ isEdit ? $t('common.save') : $t('common.create') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
