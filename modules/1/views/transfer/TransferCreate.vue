<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const router = useRouter()
const { t } = useI18n()
const submitting = ref(false)
const stores = ref([])
const products = ref([])

const form = ref({
  from_store_id: null,
  to_store_id: null,
  note: '',
  items: []
})

const showProductSelector = ref(false)
const selectedProduct = ref(null)
const itemQuantity = ref(1)

onMounted(async () => {
  await Promise.all([fetchStores(), fetchProducts()])
})

async function fetchStores() {
  const res = await fetch('/api/stores?page=1&limit=1000', {
    headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
  })
  const json = await res.json()
  if (json.code === 0) stores.value = json.data.list
}

async function fetchProducts() {
  const res = await fetch('/api/products?page=1&limit=1000', {
    headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
  })
  const json = await res.json()
  if (json.code === 0) products.value = json.data.list
}

function addItem() {
  showProductSelector.value = true
  selectedProduct.value = null
  itemQuantity.value = 1
}

function confirmAddItem() {
  if (!selectedProduct.value) return

  const existing = form.value.items.find(item => item.product_id === selectedProduct.value.id)
  if (existing) {
    existing.quantity += itemQuantity.value
  } else {
    form.value.items.push({
      product_id: selectedProduct.value.id,
      product_name: selectedProduct.value.name,
      quantity: itemQuantity.value
    })
  }

  showProductSelector.value = false
}

function removeItem(index) {
  form.value.items.splice(index, 1)
}

async function submit() {
  if (!form.value.from_store_id || !form.value.to_store_id) {
    alert(t('transfer.selectStoresAlert'))
    return
  }
  if (form.value.from_store_id === form.value.to_store_id) {
    alert(t('transfer.sameStoreAlert'))
    return
  }
  if (form.value.items.length === 0) {
    alert(t('transfer.addProductsAlert'))
    return
  }

  submitting.value = true
  try {
    const res = await fetch('/api/transfer', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token')}`
      },
      body: JSON.stringify(form.value)
    })
    const json = await res.json()
    if (json.code === 0) {
      alert(t('transfer.createSuccess'))
      router.push('/transfer')
    } else {
      alert(json.message)
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-text-primary">{{ $t('transfer.createTitle') }}</h1>
      <p class="text-sm text-text-secondary mt-1">{{ $t('transfer.createSubtitle') }}</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6 max-w-4xl">
      <div class="space-y-6">
        <!-- Store Selection -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-text-secondary mb-2">{{ $t('transfer.fromStoreLabel') }}</label>
            <select
              v-model="form.from_store_id"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none"
            >
              <option :value="null">{{ $t('transfer.pleaseSelect') }}</option>
              <option v-for="store in stores" :key="store.id" :value="store.id">{{ store.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-secondary mb-2">{{ $t('transfer.toStoreLabel') }}</label>
            <select
              v-model="form.to_store_id"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none"
            >
              <option :value="null">{{ $t('transfer.pleaseSelect') }}</option>
              <option v-for="store in stores" :key="store.id" :value="store.id">{{ store.name }}</option>
            </select>
          </div>
        </div>

        <!-- Note -->
        <div>
          <label class="block text-sm font-medium text-text-secondary mb-2">{{ $t('transfer.noteLabel') }}</label>
          <textarea
            v-model="form.note"
            rows="3"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none resize-none"
            :placeholder="$t('transfer.notePlaceholderCreate')"
          ></textarea>
        </div>

        <!-- Items -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <label class="block text-sm font-medium text-text-secondary">{{ $t('transfer.transferProducts') }}</label>
            <button
              @click="addItem"
              class="text-primary hover:text-primary-hover text-sm font-medium flex items-center gap-1"
            >
              <span class="material-symbols-outlined text-[16px]">add</span>
              {{ $t('transfer.addProduct') }}
            </button>
          </div>

          <div v-if="form.items.length === 0" class="border-2 border-dashed border-gray-200 rounded-lg p-8 text-center text-text-secondary">
            {{ $t('transfer.noProductsYet') }}
          </div>

          <div v-else class="border border-gray-200 rounded-lg overflow-hidden">
            <table class="w-full">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('transfer.productNameCol') }}</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('transfer.quantityCol') }}</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-text-secondary">{{ $t('transfer.actionCol') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-for="(item, index) in form.items" :key="index">
                  <td class="px-4 py-3 text-sm text-text-primary">{{ item.product_name }}</td>
                  <td class="px-4 py-3 text-sm text-text-primary">{{ item.quantity }}</td>
                  <td class="px-4 py-3 text-sm">
                    <button
                      @click="removeItem(index)"
                      class="text-danger hover:text-danger/80"
                    >
                      {{ $t('common.delete') }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 pt-4">
          <button
            @click="router.back()"
            class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-text-secondary hover:bg-gray-50 transition-colors"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            @click="submit"
            :disabled="submitting"
            class="flex-1 px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
          >
            {{ submitting ? $t('common.submitting') : $t('transfer.createTransfer') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Product Selector Dialog -->
    <div v-if="showProductSelector" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showProductSelector = false">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold text-text-primary mb-4">{{ $t('transfer.selectProductTitle') }}</h2>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-secondary mb-2">{{ $t('transfer.productLabel2') }}</label>
            <select
              v-model="selectedProduct"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none"
            >
              <option :value="null">{{ $t('transfer.pleaseSelect') }}</option>
              <option v-for="product in products" :key="product.id" :value="product">{{ product.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-text-secondary mb-2">{{ $t('transfer.quantityLabel2') }}</label>
            <input
              v-model.number="itemQuantity"
              type="number"
              min="1"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none"
            />
          </div>
        </div>
        <div class="flex gap-3 mt-6">
          <button
            @click="showProductSelector = false"
            class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-text-secondary hover:bg-gray-50 transition-colors"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            @click="confirmAddItem"
            :disabled="!selectedProduct"
            class="flex-1 px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
          >
            {{ $t('common.confirm') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
