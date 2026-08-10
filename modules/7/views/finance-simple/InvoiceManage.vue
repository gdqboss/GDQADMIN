<template>
  <div class="invoice-manage p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold">{{ $t('invoice.title') }}</h1>
    </div>

    <!-- Tabs -->
    <div class="mb-6 border-b">
      <div class="flex space-x-4">
        <button
          @click="activeTab = 'input'"
          :class="['px-4 py-2 font-medium', activeTab === 'input' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-600']"
        >
          {{ $t('invoice.inputInvoices') }}
        </button>
        <button
          @click="activeTab = 'output'"
          :class="['px-4 py-2 font-medium', activeTab === 'output' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-600']"
        >
          {{ $t('invoice.outputInvoices') }}
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="mb-4 flex flex-wrap gap-4">
      <input
        v-model="filters.search"
        type="text"
        :placeholder="$t('invoice.searchPlaceholder')"
        class="px-3 py-2 border rounded w-64"
      />
      <select v-model="filters.invoice_type" class="px-3 py-2 border rounded">
        <option value="">{{ $t('invoice.allTypes') }}</option>
        <option value="vat_special">{{ $t('invoice.vatSpecial') }}</option>
        <option value="vat_normal">{{ $t('invoice.vatNormal') }}</option>
        <option value="receipt">{{ $t('invoice.receipt') }}</option>
      </select>
      <select v-model="filters.status" class="px-3 py-2 border rounded">
        <option value="">{{ $t('invoice.allStatus') }}</option>
        <option value="pending">{{ $t('invoice.pending') }}</option>
        <option value="verified">{{ $t('invoice.verified') }}</option>
        <option value="void">{{ $t('invoice.void') }}</option>
      </select>
      <input
        v-model="filters.start_date"
        type="date"
        class="px-3 py-2 border rounded"
      />
      <input
        v-model="filters.end_date"
        type="date"
        class="px-3 py-2 border rounded"
      />
      <button
        @click="loadInvoices"
        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
      >
        {{ $t('common.search') }}
      </button>
      <button
        @click="resetFilters"
        class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
      >
        {{ $t('common.reset') }}
      </button>
      <button
        @click="openAddModal"
        class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 ml-auto"
      >
        {{ $t('invoice.addInvoice') }}
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <table class="min-w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('invoice.invoiceNo') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('invoice.invoiceCode') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('invoice.type') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('invoice.invoiceDate') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ activeTab === 'input' ? $t('invoice.seller') : $t('invoice.buyer') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('invoice.totalAmount') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('invoice.taxAmount') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('invoice.status') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="invoice in invoices" :key="invoice.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-sm">{{ invoice.invoice_no }}</td>
            <td class="px-4 py-3 text-sm">{{ invoice.invoice_code }}</td>
            <td class="px-4 py-3 text-sm">{{ $t(`invoice.${invoice.invoice_type}`) }}</td>
            <td class="px-4 py-3 text-sm">{{ invoice.invoice_date }}</td>
            <td class="px-4 py-3 text-sm">{{ activeTab === 'input' ? invoice.seller_name : invoice.buyer_name }}</td>
            <td class="px-4 py-3 text-sm font-medium">¥{{ parseFloat(invoice.total_amount).toFixed(2) }}</td>
            <td class="px-4 py-3 text-sm">¥{{ parseFloat(invoice.tax_amount).toFixed(2) }}</td>
            <td class="px-4 py-3 text-sm">
              <span :class="getStatusClass(invoice.status)">
                {{ $t(`invoice.${invoice.status}`) }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm">
              <button @click="viewInvoice(invoice)" class="text-blue-600 hover:underline mr-2">{{ $t('common.view') }}</button>
              <button @click="editInvoice(invoice)" class="text-green-600 hover:underline mr-2">{{ $t('common.edit') }}</button>
              <button v-if="invoice.status === 'pending'" @click="verifyInvoice(invoice.id)" class="text-purple-600 hover:underline mr-2">{{ $t('invoice.verify') }}</button>
              <button v-if="invoice.status !== 'void'" @click="voidInvoice(invoice.id)" class="text-orange-600 hover:underline mr-2">{{ $t('invoice.voidAction') }}</button>
              <button @click="deleteInvoice(invoice.id)" class="text-red-600 hover:underline">{{ $t('common.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <Pagination
      v-if="pagination.total > 0"
      :current="pagination.page"
      :total="pagination.total"
      :page-size="pagination.limit"
      @change="handlePageChange"
      class="mt-4"
    />

    <!-- Invoice Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">{{ modalMode === 'add' ? $t('invoice.addInvoice') : modalMode === 'edit' ? $t('invoice.editInvoice') : $t('invoice.viewInvoice') }}</h2>
            <button @click="closeModal" class="text-gray-500 hover:text-gray-700">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="grid grid-cols-2 gap-6">
            <!-- Left: Form -->
            <div>
              <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ $t('invoice.invoiceNo') }} *</label>
                <input
                  v-model="formData.invoice_no"
                  type="text"
                  :disabled="modalMode === 'view'"
                  class="w-full px-3 py-2 border rounded"
                  required
                />
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ $t('invoice.invoiceCode') }}</label>
                <input
                  v-model="formData.invoice_code"
                  type="text"
                  :disabled="modalMode === 'view'"
                  class="w-full px-3 py-2 border rounded"
                />
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ $t('invoice.type') }} *</label>
                <select
                  v-model="formData.invoice_type"
                  :disabled="modalMode === 'view'"
                  class="w-full px-3 py-2 border rounded"
                  required
                >
                  <option value="vat_special">{{ $t('invoice.vatSpecial') }}</option>
                  <option value="vat_normal">{{ $t('invoice.vatNormal') }}</option>
                  <option value="receipt">{{ $t('invoice.receipt') }}</option>
                </select>
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ $t('invoice.invoiceDate') }} *</label>
                <input
                  v-model="formData.invoice_date"
                  type="date"
                  :disabled="modalMode === 'view'"
                  class="w-full px-3 py-2 border rounded"
                  required
                />
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ $t('invoice.seller') }} *</label>
                <input
                  v-model="formData.seller_name"
                  type="text"
                  :disabled="modalMode === 'view'"
                  class="w-full px-3 py-2 border rounded"
                  required
                />
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ $t('invoice.sellerTaxNo') }}</label>
                <input
                  v-model="formData.seller_tax_no"
                  type="text"
                  :disabled="modalMode === 'view'"
                  class="w-full px-3 py-2 border rounded"
                />
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ $t('invoice.buyer') }} *</label>
                <input
                  v-model="formData.buyer_name"
                  type="text"
                  :disabled="modalMode === 'view'"
                  class="w-full px-3 py-2 border rounded"
                  required
                />
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ $t('invoice.buyerTaxNo') }}</label>
                <input
                  v-model="formData.buyer_tax_no"
                  type="text"
                  :disabled="modalMode === 'view'"
                  class="w-full px-3 py-2 border rounded"
                />
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ $t('invoice.amountWithoutTax') }} *</label>
                <input
                  v-model.number="formData.amount_without_tax"
                  type="number"
                  step="0.01"
                  :disabled="modalMode === 'view'"
                  @input="calculateTotal"
                  class="w-full px-3 py-2 border rounded"
                  required
                />
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ $t('invoice.taxRate') }} (%)</label>
                <input
                  v-model.number="formData.tax_rate"
                  type="number"
                  step="0.01"
                  :disabled="modalMode === 'view'"
                  @input="calculateTotal"
                  class="w-full px-3 py-2 border rounded"
                />
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ $t('invoice.taxAmount') }}</label>
                <input
                  v-model.number="formData.tax_amount"
                  type="number"
                  step="0.01"
                  :disabled="modalMode === 'view'"
                  @input="calculateTotal"
                  class="w-full px-3 py-2 border rounded"
                />
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ $t('invoice.totalAmount') }} *</label>
                <input
                  v-model.number="formData.total_amount"
                  type="number"
                  step="0.01"
                  :disabled="modalMode === 'view'"
                  class="w-full px-3 py-2 border rounded bg-gray-50"
                  required
                />
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ $t('invoice.note') }}</label>
                <textarea
                  v-model="formData.note"
                  :disabled="modalMode === 'view'"
                  class="w-full px-3 py-2 border rounded"
                  rows="3"
                ></textarea>
              </div>
            </div>

            <!-- Right: Image Upload -->
            <div>
              <label class="block text-sm font-medium mb-2">{{ $t('invoice.invoiceImage') }}</label>
              <InvoiceUploader
                v-if="modalMode !== 'view'"
                v-model="formData.image"
              />
              <div v-else-if="formData.image_path" class="border rounded p-4">
                <img
                  v-if="!formData.image_path.endsWith('.pdf')"
                  :src="formData.image_path"
                  alt="Invoice"
                  class="max-w-full cursor-pointer"
                  @click="openImagePreview(formData.image_path)"
                />
                <div v-else class="text-center">
                  <a :href="formData.image_path" target="_blank" class="text-blue-600 hover:underline">
                    {{ $t('invoice.viewPdf') }}
                  </a>
                </div>
              </div>
            </div>
          </div>

          <div v-if="modalMode !== 'view'" class="mt-6 flex justify-end space-x-3">
            <button
              @click="closeModal"
              class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
            >
              {{ $t('common.cancel') }}
            </button>
            <button
              @click="saveInvoice"
              class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
            >
              {{ $t('common.save') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Image Preview Modal -->
    <div v-if="previewImage" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50" @click="previewImage = null">
      <img :src="previewImage" alt="Preview" class="max-w-[90%] max-h-[90%]" />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../services/api.js'
import InvoiceUploader from '@/components/InvoiceUploader.vue'
import Pagination from '@/components/Pagination.vue'

const { t } = useI18n()

const activeTab = ref('input')
const invoices = ref([])
const showModal = ref(false)
const modalMode = ref('add')
const previewImage = ref(null)

const filters = reactive({
  search: '',
  invoice_type: '',
  status: '',
  start_date: '',
  end_date: ''
})

const pagination = reactive({
  page: 1,
  limit: 50,
  total: 0
})

const formData = reactive({
  invoice_no: '',
  invoice_code: '',
  invoice_type: 'vat_special',
  direction: 'input',
  invoice_date: '',
  seller_name: '',
  seller_tax_no: '',
  buyer_name: '',
  buyer_tax_no: '',
  total_amount: 0,
  tax_amount: 0,
  amount_without_tax: 0,
  tax_rate: 0,
  image: null,
  image_path: '',
  note: ''
})

watch(activeTab, () => {
  pagination.page = 1
  loadInvoices()
})

onMounted(() => {
  loadInvoices()
})

const loadInvoices = async () => {
  try {
    const params = {
      page: pagination.page,
      limit: pagination.limit,
      direction: activeTab.value,
      ...filters
    }

    const response = await api.get('/invoices', { params })
    invoices.value = response.data.data
    pagination.total = response.data.pagination.total
  } catch (error) {
    console.error('Failed to load invoices:', error)
  }
}

const resetFilters = () => {
  filters.search = ''
  filters.invoice_type = ''
  filters.status = ''
  filters.start_date = ''
  filters.end_date = ''
  pagination.page = 1
  loadInvoices()
}

const openAddModal = () => {
  modalMode.value = 'add'
  resetFormData()
  formData.direction = activeTab.value
  formData.invoice_date = new Date().toISOString().split('T')[0]
  showModal.value = true
}

const viewInvoice = (invoice) => {
  modalMode.value = 'view'
  Object.assign(formData, invoice)
  showModal.value = true
}

const editInvoice = (invoice) => {
  modalMode.value = 'edit'
  Object.assign(formData, invoice)
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  resetFormData()
}

const resetFormData = () => {
  formData.id = null
  formData.invoice_no = ''
  formData.invoice_code = ''
  formData.invoice_type = 'vat_special'
  formData.direction = 'input'
  formData.invoice_date = ''
  formData.seller_name = ''
  formData.seller_tax_no = ''
  formData.buyer_name = ''
  formData.buyer_tax_no = ''
  formData.total_amount = 0
  formData.tax_amount = 0
  formData.amount_without_tax = 0
  formData.tax_rate = 0
  formData.image = null
  formData.image_path = ''
  formData.note = ''
}

const calculateTotal = () => {
  const amount = parseFloat(formData.amount_without_tax) || 0
  const rate = parseFloat(formData.tax_rate) || 0
  formData.tax_amount = (amount * rate / 100).toFixed(2)
  formData.total_amount = (amount + parseFloat(formData.tax_amount)).toFixed(2)
}

const saveInvoice = async () => {
  try {
    const data = new FormData()
    Object.keys(formData).forEach(key => {
      if (key === 'image' && formData.image instanceof File) {
        data.append('image', formData.image)
      } else if (key !== 'image' && key !== 'image_path' && formData[key] !== null) {
        data.append(key, formData[key])
      }
    })

    if (modalMode.value === 'add') {
      await api.post('/invoices', data, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
    } else {
      await api.put(`/invoices/${formData.id}`, data, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
    }

    closeModal()
    loadInvoices()
  } catch (error) {
    console.error('Failed to save invoice:', error)
    alert(t('invoice.saveFailed'))
  }
}

const verifyInvoice = async (id) => {
  if (!confirm(t('invoice.confirmVerify'))) return

  try {
    await api.put(`/invoices/${id}/verify`)
    loadInvoices()
  } catch (error) {
    console.error('Failed to verify invoice:', error)
  }
}

const voidInvoice = async (id) => {
  if (!confirm(t('invoice.confirmVoid'))) return

  try {
    await api.put(`/invoices/${id}/void`)
    loadInvoices()
  } catch (error) {
    console.error('Failed to void invoice:', error)
  }
}

const deleteInvoice = async (id) => {
  if (!confirm(t('common.confirmDelete'))) return

  try {
    await api.delete(`/invoices/${id}`)
    loadInvoices()
  } catch (error) {
    console.error('Failed to delete invoice:', error)
  }
}

const handlePageChange = (page) => {
  pagination.page = page
  loadInvoices()
}

const getStatusClass = (status) => {
  const classes = {
    pending: 'px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs',
    verified: 'px-2 py-1 bg-green-100 text-green-800 rounded text-xs',
    void: 'px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs'
  }
  return classes[status] || ''
}

const openImagePreview = (url) => {
  previewImage.value = url
}
</script>

<style scoped>
@media (max-width: 768px) {
  .invoice-manage.p-6 {
    padding: 0.75rem;
  }

  .mb-6 {
    margin-bottom: 0.75rem;
  }

  .mb-4 {
    margin-bottom: 0.5rem;
  }

  .flex.space-x-4 {
    flex-direction: row;
    overflow-x: auto;
    padding-bottom: 0.5rem;
  }

  .flex.flex-wrap.gap-4 {
    flex-direction: column;
    gap: 0.5rem;
  }

  .flex.flex-wrap.gap-4 > * {
    width: 100%;
  }

  .px-3.py-2.border {
    font-size: 0.875rem;
  }

  .px-4.py-2.bg-blue-500,
  .px-4.py-2.bg-gray-500,
  .px-4.py-2.bg-green-500 {
    width: 100%;
    text-align: center;
    margin-left: 0 !important;
    margin-top: 0.25rem;
  }

  .bg-white.rounded-lg.shadow.overflow-hidden {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .min-w-full {
    min-width: 800px;
  }

  .text-2xl.font-bold {
    font-size: 1.25rem;
  }

  h1 {
    font-size: 1.25rem;
  }

  .fixed.inset-0.bg-black.bg-opacity-50 {
    padding: 0.5rem;
  }

  .bg-white.rounded-lg.max-w-4xl {
    max-width: 100%;
    margin: 0.5rem;
  }

  .grid.grid-cols-2.gap-6 {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .p-6 {
    padding: 1rem;
  }
}
</style>
