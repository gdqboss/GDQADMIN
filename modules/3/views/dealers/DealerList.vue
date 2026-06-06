<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import api from '../../services/api.js'
import { mockDealers } from '../../services/mockData.js'

const { t } = useI18n()

const USE_MOCK_DATA = false // Toggle to switch between mock and real API

const dealers = ref([])
const searchQuery = ref('')
const showDrawer = ref(false)
const showEditDrawer = ref(false)
const editingId = ref(null)

const emptyForm = () => ({ name: '', contact: '', phone: '', email: '', address: '', region: '', remark: '' })
const newForm = ref(emptyForm())
const editForm = ref(emptyForm())

async function fetchDealers() {
  if (USE_MOCK_DATA) {
    dealers.value = mockDealers
    return
  }

  const params = {}
  if (searchQuery.value) params.keyword = searchQuery.value
  const res = await api.get('/dealers', { params })
  if (res.code === 0) dealers.value = res.data
}

onMounted(fetchDealers)

async function handleAdd() {
  if (!newForm.value.name) return alert(t('dealer.nameRequired'))
  await api.post('/dealers', { ...newForm.value })
  showDrawer.value = false
  newForm.value = emptyForm()
  await fetchDealers()
}

function openEdit(d) {
  editingId.value = d.id
  editForm.value = { name: d.name, contact: d.contact || '', phone: d.phone || '', email: d.email || '', address: d.address || '', region: d.region || '', remark: d.remark || '', status: d.status }
  showEditDrawer.value = true
}

async function handleEdit() {
  await api.put(`/dealers/${editingId.value}`, { ...editForm.value })
  showEditDrawer.value = false
  await fetchDealers()
}

async function handleDelete(id) {
  if (!confirm(t('dealer.confirmDelete'))) return
  await api.delete(`/dealers/${id}`)
  await fetchDealers()
}

const addFormFields = computed(() => [
  { label: t('dealer.nameLabel'), key: 'name', required: true, placeholder: t('common.pleaseInput', { field: t('dealer.nameLabel') }) },
  { label: t('dealer.region'), key: 'region', placeholder: t('dealer.regionPlaceholder') },
  { label: t('common.contact'), key: 'contact', placeholder: t('common.pleaseInput', { field: t('common.contact') }) },
  { label: t('common.phone'), key: 'phone', placeholder: t('common.pleaseInput', { field: t('common.phone') }) },
  { label: t('common.email'), key: 'email', placeholder: t('common.pleaseInput', { field: t('common.email') }) },
  { label: t('common.address'), key: 'address', placeholder: t('common.pleaseInput', { field: t('common.address') }) },
  { label: t('common.remark'), key: 'remark', placeholder: t('common.pleaseInput', { field: t('common.remark') }) },
])

const editFormFields = computed(() => [
  { label: t('dealer.nameLabel'), key: 'name', placeholder: t('common.pleaseInput', { field: t('dealer.nameLabel') }) },
  { label: t('dealer.region'), key: 'region', placeholder: t('common.pleaseInput', { field: t('dealer.region') }) },
  { label: t('common.contact'), key: 'contact', placeholder: t('common.pleaseInput', { field: t('common.contact') }) },
  { label: t('common.phone'), key: 'phone', placeholder: t('common.pleaseInput', { field: t('common.phone') }) },
  { label: t('common.email'), key: 'email', placeholder: t('common.pleaseInput', { field: t('common.email') }) },
  { label: t('common.address'), key: 'address', placeholder: t('common.pleaseInput', { field: t('common.address') }) },
  { label: t('common.remark'), key: 'remark', placeholder: t('common.pleaseInput', { field: t('common.remark') }) },
])
</script>

<template>
  <div>
    <PageHeader :title="$t('dealer.title')" :subtitle="$t('dealer.subtitle')" />

    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-6">
      <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center bg-gray-50 rounded-lg px-3 py-2 border border-transparent focus-within:border-primary focus-within:bg-white transition-all flex-1 min-w-[160px] max-w-[360px]">
          <span class="material-symbols-outlined text-text-secondary text-[20px]">search</span>
          <input v-model="searchQuery" @input="fetchDealers" type="text" :placeholder="$t('dealer.searchPlaceholder')" class="bg-transparent border-none focus:ring-0 focus:outline-none text-sm w-full ml-2" />
        </div>
        <div class="ml-auto">
          <button @click="showDrawer = true" class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">add</span>
            {{ $t('dealer.add') }}
          </button>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('dealer.nameLabel') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('dealer.region') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.contact') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.phone') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.email') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('common.status') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="d in dealers" :key="d.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-medium text-text-primary">{{ d.name }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ d.region || '-' }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ d.contact || '-' }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ d.phone || '-' }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ d.email || '-' }}</td>
              <td class="px-4 py-3 text-center">
                <StatusTag :type="d.status === 'active' ? 'success' : 'danger'" :text="d.status === 'active' ? $t('dealer.statusActive') : $t('dealer.statusInactive')" />
              </td>
              <td class="px-4 py-3 text-right">
                <button @click="openEdit(d)" class="text-primary hover:text-primary-hover text-xs font-medium mr-3">{{ $t('common.edit') }}</button>
                <button @click="handleDelete(d.id)" class="text-danger hover:text-red-700 text-xs font-medium">{{ $t('common.delete') }}</button>
              </td>
            </tr>
            <tr v-if="dealers.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.noData') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100 text-sm text-text-secondary">{{ $t('dealer.totalRecords', { count: dealers.length }) }}</div>
    </div>

    <!-- Add Drawer -->
    <Teleport to="body">
      <div v-if="showDrawer" class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/30" @click="showDrawer = false"></div>
        <div class="relative w-full max-w-lg bg-white shadow-xl flex flex-col">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="text-lg font-bold text-text-primary">{{ $t('dealer.add') }}</h3>
            <button @click="showDrawer = false"><span class="material-symbols-outlined">close</span></button>
          </div>
          <div class="flex-1 overflow-y-auto p-6 space-y-4">
            <div v-for="f in addFormFields" :key="f.key">
              <label class="block text-sm font-medium text-text-primary mb-1">{{ f.label }}<span v-if="f.required" class="text-danger ml-1">*</span></label>
              <input v-model="newForm[f.key]" :placeholder="f.placeholder" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
          </div>
          <div class="px-6 py-4 border-t flex gap-3 justify-end">
            <button @click="showDrawer = false" class="px-4 py-2 border border-gray-200 rounded-lg text-sm hover:bg-gray-50">{{ $t('common.cancel') }}</button>
            <button @click="handleAdd" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium">{{ $t('common.add') }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit Drawer -->
    <Teleport to="body">
      <div v-if="showEditDrawer" class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/30" @click="showEditDrawer = false"></div>
        <div class="relative w-full max-w-lg bg-white shadow-xl flex flex-col">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="text-lg font-bold text-text-primary">{{ $t('dealer.edit') }}</h3>
            <button @click="showEditDrawer = false"><span class="material-symbols-outlined">close</span></button>
          </div>
          <div class="flex-1 overflow-y-auto p-6 space-y-4">
            <div v-for="f in editFormFields" :key="f.key">
              <label class="block text-sm font-medium text-text-primary mb-1">{{ f.label }}</label>
              <input v-model="editForm[f.key]" :placeholder="f.placeholder" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.status') }}</label>
              <select v-model="editForm.status" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                <option value="active">{{ $t('dealer.statusActive') }}</option>
                <option value="inactive">{{ $t('dealer.statusInactive') }}</option>
              </select>
            </div>
          </div>
          <div class="px-6 py-4 border-t flex gap-3 justify-end">
            <button @click="showEditDrawer = false" class="px-4 py-2 border border-gray-200 rounded-lg text-sm hover:bg-gray-50">{{ $t('common.cancel') }}</button>
            <button @click="handleEdit" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium">{{ $t('common.save') }}</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
