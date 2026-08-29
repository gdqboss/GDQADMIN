<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import api from '../../services/api.js'
import { mockStores, mockDealers } from '../../services/mockData.js'

const { t } = useI18n()

const USE_MOCK_DATA = false // Toggle to switch between mock and real API

const stores = ref([])
const dealers = ref([])
const searchQuery = ref('')
const showDrawer = ref(false)
const showEditDrawer = ref(false)
const editingId = ref(null)

const emptyForm = () => ({ name: '', store_code: '', dealer_id: '', contact: '', phone: '', address: '', city: '', remark: '' })
const newForm = ref(emptyForm())
const editForm = ref(emptyForm())

async function fetchStores() {
  if (USE_MOCK_DATA) {
    stores.value = mockStores
    return
  }

  const params = {}
  if (searchQuery.value) params.keyword = searchQuery.value
  const res = await api.get('/stores', { params })
  if (res.code === 0) stores.value = res.data
}

async function fetchDealers() {
  if (USE_MOCK_DATA) {
    dealers.value = mockDealers
    return
  }

  const res = await api.get('/dealers')
  if (res.code === 0) dealers.value = res.data
}

onMounted(async () => {
  await Promise.all([fetchStores(), fetchDealers()])
})

async function handleAdd() {
  if (!newForm.value.name) return alert(t('store.nameRequired'))
  await api.post('/stores', { ...newForm.value, dealer_id: newForm.value.dealer_id || null })
  showDrawer.value = false
  newForm.value = emptyForm()
  await fetchStores()
}

function openEdit(s) {
  editingId.value = s.id
  editForm.value = { name: s.name, store_code: s.store_code || '', dealer_id: s.dealer_id || '', contact: s.contact || '', phone: s.phone || '', address: s.address || '', city: s.city || '', remark: s.remark || '', status: s.status }
  showEditDrawer.value = true
}

async function handleEdit() {
  await api.put(`/stores/${editingId.value}`, { ...editForm.value, dealer_id: editForm.value.dealer_id || null })
  showEditDrawer.value = false
  await fetchStores()
}

async function handleDelete(id) {
  if (!confirm(t('store.confirmDelete'))) return
  await api.delete(`/stores/${id}`)
  await fetchStores()
}

const extraFormFields = computed(() => [
  { label: t('store.storeCode'), key: 'store_code', placeholder: t('common.pleaseInput', { field: t('store.storeCode') }) },
  { label: t('store.city'), key: 'city', placeholder: t('common.pleaseInput', { field: t('store.city') }) },
  { label: t('common.contact'), key: 'contact', placeholder: t('common.pleaseInput', { field: t('common.contact') }) },
  { label: t('common.phone'), key: 'phone', placeholder: t('common.pleaseInput', { field: t('common.phone') }) },
  { label: t('common.address'), key: 'address', placeholder: t('common.pleaseInput', { field: t('common.address') }) },
  { label: t('common.remark'), key: 'remark', placeholder: t('common.pleaseInput', { field: t('common.remark') }) },
])
</script>

<template>
  <div>
    <PageHeader :title="$t('store.title')" :subtitle="$t('store.subtitle')" />

    <div class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-6">
      <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center bg-gray-50 rounded-lg px-3 py-2 border border-transparent focus-within:border-primary focus-within:bg-white transition-all flex-1 min-w-[160px] max-w-[360px]">
          <span class="material-symbols-outlined text-text-secondary text-[20px]">search</span>
          <input v-model="searchQuery" @input="fetchStores" type="text" :placeholder="$t('store.searchPlaceholder')" class="bg-transparent border-none focus:ring-0 focus:outline-none text-sm w-full ml-2" />
        </div>
        <div class="ml-auto">
          <button @click="showDrawer = true" class="flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <span class="material-symbols-outlined text-[18px]">add</span>
            {{ $t('store.add') }}
          </button>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-100 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-text-secondary text-xs uppercase">
            <tr>
              <th class="px-4 py-3 font-medium">{{ $t('store.nameLabel') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('store.storeCode') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('store.dealer') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('store.city') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.contact') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.phone') }}</th>
              <th class="px-4 py-3 font-medium">{{ $t('common.address') }}</th>
              <th class="px-4 py-3 font-medium text-center">{{ $t('common.status') }}</th>
              <th class="px-4 py-3 font-medium text-right">{{ $t('common.action') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="s in stores" :key="s.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-medium text-text-primary">{{ s.name }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ s.store_code || '-' }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ s.dealer_name || '-' }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ s.city || '-' }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ s.contact || '-' }}</td>
              <td class="px-4 py-3 text-text-secondary">{{ s.phone || '-' }}</td>
              <td class="px-4 py-3 text-text-secondary text-xs max-w-[200px] truncate">{{ s.address || '-' }}</td>
              <td class="px-4 py-3 text-center">
                <StatusTag :type="s.status === 'active' ? 'success' : 'danger'" :text="s.status === 'active' ? $t('store.statusActive') : $t('store.statusInactive')" />
              </td>
              <td class="px-4 py-3 text-right">
                <button @click="openEdit(s)" class="text-primary hover:text-primary-hover text-xs font-medium mr-3">{{ $t('common.edit') }}</button>
                <button @click="handleDelete(s.id)" class="text-danger hover:text-red-700 text-xs font-medium">{{ $t('common.delete') }}</button>
              </td>
            </tr>
            <tr v-if="stores.length === 0">
              <td colspan="9" class="px-4 py-8 text-center text-text-secondary text-sm">{{ $t('common.noData') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100 text-sm text-text-secondary">{{ $t('store.totalRecords', { count: stores.length }) }}</div>
    </div>

    <!-- Add Drawer -->
    <Teleport to="body">
      <div v-if="showDrawer" class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/30" @click="showDrawer = false"></div>
        <div class="relative w-full max-w-lg bg-white shadow-xl flex flex-col">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="text-lg font-bold text-text-primary">{{ $t('store.add') }}</h3>
            <button @click="showDrawer = false"><span class="material-symbols-outlined">close</span></button>
          </div>
          <div class="flex-1 overflow-y-auto p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('store.nameLabel') }} <span class="text-danger">*</span></label>
              <input v-model="newForm.name" :placeholder="$t('store.namePlaceholder')" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('store.dealer') }}</label>
              <select v-model="newForm.dealer_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                <option value="">{{ $t('store.selectDealerOptional') }}</option>
                <option v-for="d in dealers" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>
            <div v-for="f in extraFormFields" :key="f.key">
              <label class="block text-sm font-medium text-text-primary mb-1">{{ f.label }}</label>
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
            <h3 class="text-lg font-bold text-text-primary">{{ $t('store.edit') }}</h3>
            <button @click="showEditDrawer = false"><span class="material-symbols-outlined">close</span></button>
          </div>
          <div class="flex-1 overflow-y-auto p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('store.nameLabel') }}</label>
              <input v-model="editForm.name" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('store.dealer') }}</label>
              <select v-model="editForm.dealer_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                <option value="">{{ $t('store.noneOption') }}</option>
                <option v-for="d in dealers" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>
            <div v-for="f in extraFormFields" :key="f.key">
              <label class="block text-sm font-medium text-text-primary mb-1">{{ f.label }}</label>
              <input v-model="editForm[f.key]" :placeholder="f.placeholder" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-primary mb-1">{{ $t('common.status') }}</label>
              <select v-model="editForm.status" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                <option value="active">{{ $t('store.statusActive') }}</option>
                <option value="inactive">{{ $t('store.statusInactive') }}</option>
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

<style scoped>
@media (max-width: 768px) {
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.p-4.mb-6 {
    padding: 12px;
  }
  .flex.flex-wrap.items-center.gap-3 {
    flex-direction: column;
    align-items: stretch;
  }
  .flex.items-center.bg-gray-50.rounded-lg.px-3.py-2.border.border-transparent.focus-within\:border-primary.focus-within\:bg-white.transition-all.flex-1.min-w-\[160px\].max-w-\[360px\] {
    max-width: 100%;
    min-width: unset;
  }
  .ml-auto {
    margin-left: 0;
    margin-top: 8px;
  }
  .bg-white.rounded-lg.border.border-gray-100.shadow-card.overflow-hidden {
    padding: 0;
  }
  table {
    font-size: 12px;
  }
  table th,
  table td {
    padding: 8px 6px;
    white-space: nowrap;
  }
  table td.text-xs.max-w-\[200px\].truncate {
    max-width: 120px;
  }
  button.text-xs {
    font-size: 11px;
    padding: 2px 4px;
  }
  .fixed.max-w-lg {
    max-width: 100%;
  }
  .p-6 {
    padding: 16px;
  }
  .space-y-4 > div {
    margin-bottom: 12px;
  }
  input.w-full,
  select.w-full {
    font-size: 14px;
  }
  .px-6.py-4.border-t.flex.gap-3.justify-end {
    padding: 12px 16px;
    flex-wrap: wrap;
  }
  .px-6.py-4.border-t.flex.gap-3.justify-end button {
    flex: 1;
    min-width: 80px;
    text-align: center;
  }
}
</style>
