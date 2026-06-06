<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import ImageUploader from '../../components/ImageUploader.vue'
import H5ServiceChat from '../../components/H5ServiceChat.vue'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()

function toggleLocale() {
  locale.value = locale.value === 'zh' ? 'en' : 'zh'
  localStorage.setItem('caimeite_locale', locale.value)
}
const data = ref(null)
const loading = ref(true)
const error = ref('')

// After-sale form
const showAfterSale = ref(false)
const afterSaleForm = ref({ buyer: '', issue: '' })
const afterSaleImages = ref([])
const submitting = ref(false)
const submitted = ref(false)
const afterSaleError = ref('')

// Buyer after-sale records
const myAftersaleRecords = ref([])
const isBuyer = ref(false)

// H5 user (logged-in consumer)
const h5User = JSON.parse(localStorage.getItem('h5_user') || 'null')
const h5Token = localStorage.getItem('h5_token')

onMounted(async () => {
  try {
    const res = await fetch(`/api/scan/${route.params.code}`)
    const json = await res.json()
    if (json.code === 0) {
      data.value = json.data
      // Pre-fill buyer phone from logged-in user
      if (h5User?.phone) {
        afterSaleForm.value.buyer = h5User.phone
      }

      // Fetch buyer after-sale records if logged in
      if (h5Token) {
        try {
          const asRes = await fetch(`/api/scan/${route.params.code}/my-aftersale`, {
            headers: { 'Authorization': `Bearer ${h5Token}` }
          })
          const asJson = await asRes.json()
          if (asJson.code === 0) {
            isBuyer.value = asJson.data.isBuyer
            myAftersaleRecords.value = asJson.data.records
          }
        } catch (e) {
          console.error('Failed to fetch after-sale records:', e)
        }
      }
    } else error.value = t('scan.notFound')
  } catch {
    error.value = t('scan.loadError')
  } finally {
    loading.value = false
  }
})

function warrantyStatus(warranty_end) {
  if (!warranty_end) return null
  return new Date(warranty_end) >= new Date() ? 'valid' : 'expired'
}

function parseLinks(links) {
  if (!links) return []
  if (typeof links === 'string') { try { return JSON.parse(links) } catch { return [] } }
  return Array.isArray(links) ? links : []
}

function platformLabel(p) {
  const map = { jd: t('scan.platformJd'), taobao: t('scan.platformTaobao'), tmall: t('scan.platformTmall'), pinduoduo: t('scan.platformPinduoduo'), suning: t('scan.platformSuning'), other: t('scan.platformOther') }
  return map[p] || p
}

function groupPlatformLabel(type) {
  const map = {
    wechat_work: t('product.groupQrTypeWechatWork'),
    dingtalk: t('product.groupQrTypeDingtalk'),
    telegram: t('product.groupQrTypeTelegram'),
    whatsapp: t('product.groupQrTypeWhatsapp'),
    other: t('product.groupQrTypeOther')
  }
  return map[type] || ''
}

function detectPlatform(url) {
  if (!url) return { name: t('scan.platformOther'), logo: 'other.svg', platform: 'other' }
  const urlLower = url.toLowerCase()
  if (urlLower.includes('jd.com') || urlLower.includes('jd.hk')) {
    return { name: t('scan.platformJd'), logo: 'jd.svg', platform: 'jd' }
  }
  if (urlLower.includes('tmall.com') || urlLower.includes('tmall.hk')) {
    return { name: t('scan.platformTmall'), logo: 'tmall.svg', platform: 'tmall' }
  }
  if (urlLower.includes('taobao.com')) {
    return { name: t('scan.platformTaobao'), logo: 'taobao.svg', platform: 'taobao' }
  }
  if (urlLower.includes('pinduoduo.com') || urlLower.includes('yangkeduo.com')) {
    return { name: t('scan.platformPinduoduo'), logo: 'pdd.svg', platform: 'pdd' }
  }
  if (urlLower.includes('suning.com')) {
    return { name: t('scan.platformSuning'), logo: 'suning.svg', platform: 'suning' }
  }
  return { name: t('scan.platformOther'), logo: 'other.svg', platform: 'other' }
}

async function share() {
  const url = `${window.location.href}${h5User ? `?ref=${h5User.id}` : ''}`
  if (navigator.share) {
    await navigator.share({ title: data.value?.product_name, url })
  } else {
    await navigator.clipboard.writeText(url).catch(() => {})
    alert(t('scan.linkCopied'))
  }
}

async function submitAfterSale() {
  if (!afterSaleForm.value.buyer || !afterSaleForm.value.issue) return
  afterSaleError.value = ''
  submitting.value = true
  try {
    const formData = new FormData()
    formData.append('qrcode_id', data.value.id)
    formData.append('issue', afterSaleForm.value.issue)

    // Append images
    for (const file of afterSaleImages.value) {
      formData.append('images', file)
    }

    const res = await fetch('/api/h5/after-sale', {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${h5Token}` },
      body: formData
    })
    const json = await res.json()
    if (json.code === 0) {
      submitted.value = true
      showAfterSale.value = false
    } else if (res.status === 401) {
      afterSaleError.value = t('scan.loginExpired')
      localStorage.removeItem('h5_token')
      localStorage.removeItem('h5_user')
      setTimeout(() => goLogin(), 1500)
    } else {
      afterSaleError.value = json.message || t('scan.submitFailed')
    }
  } catch {
    afterSaleError.value = t('scan.networkError')
  } finally { submitting.value = false }
}

function goLogin() {
  router.push(`/h5/login?redirect=/scan/${route.params.code}`)
}

function statusLabel(status) {
  const map = { processing: t('scan.statusPending'), resolved: t('scan.statusResolved'), rejected: t('scan.statusRejected') }
  return map[status] || status
}

function statusColor(status) {
  const map = { processing: 'text-warning', resolved: 'text-success', rejected: 'text-danger' }
  return map[status] || 'text-text-secondary'
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white border-b border-gray-100 px-4 py-3 flex items-center gap-2 shadow-sm">
      <span class="text-primary font-bold text-lg">GDQ</span>
      <span class="text-text-secondary text-sm">{{ $t('scan.title') }}</span>
      <button @click="toggleLocale" class="ml-auto text-xs text-text-secondary border border-gray-200 rounded px-2 py-1 hover:border-primary hover:text-primary transition-colors">
        {{ locale === 'zh' ? 'EN' : '中文' }}
      </button>
      <button v-if="h5Token" @click="router.push('/h5/profile')" class="text-xs text-text-secondary border border-gray-200 rounded px-2 py-1 hover:border-primary hover:text-primary transition-colors flex items-center gap-1">
        <span class="material-symbols-outlined text-[16px]">person</span>
        <span class="hidden sm:inline">{{ h5User?.name || t('scan.mine') }}</span>
      </button>
    </div>

    <div class="max-w-lg mx-auto p-4">
      <!-- Loading -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-20 text-text-secondary gap-3">
        <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm">{{ $t('scan.loading') }}</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="mt-10 bg-white rounded-xl p-8 text-center shadow-sm">
        <span class="text-4xl">❌</span>
        <p class="mt-3 text-text-primary font-medium">{{ error }}</p>
        <p class="mt-1 text-xs text-text-secondary">{{ route.params.code }}</p>
      </div>

      <!-- Content -->
      <div v-else-if="data" class="space-y-4 mt-4">
        <!-- Product card -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
          <div class="bg-primary px-4 py-3 flex items-center gap-3">
            <img :src="data.image_url" class="w-14 h-14 rounded bg-white p-0.5" :alt="data.code" />
            <div class="text-white flex-1">
              <p class="font-bold text-base">{{ data.product_name || $t('common.unbound') }}</p>
              <p class="text-primary-100 text-xs opacity-80">{{ data.code }}</p>
            </div>
            <!-- Share button -->
            <button @click="share" class="flex items-center gap-1 text-xs text-white/80 hover:text-white transition-colors shrink-0">
              <span class="material-symbols-outlined text-[16px]">share</span> {{ $t('scan.share') }}
            </button>
          </div>
          <div class="p-4 space-y-3">
            <!-- SKU and Spec info -->
            <div v-if="data.sku || data.spec || data.category" class="grid grid-cols-2 gap-3">
              <div v-if="data.sku">
                <p class="text-xs text-text-secondary">SKU</p>
                <p class="text-sm font-mono">{{ data.sku }}</p>
              </div>
              <div v-if="data.spec">
                <p class="text-xs text-text-secondary">{{ $t('product.spec') }}</p>
                <p class="text-sm">{{ data.spec }}</p>
              </div>
              <div v-if="data.category">
                <p class="text-xs text-text-secondary">{{ $t('common.category') }}</p>
                <p class="text-sm">{{ data.category }}</p>
              </div>
            </div>

            <!-- Purchase links in SKU area -->
            <div v-if="data.external_links && parseLinks(data.external_links).length" class="border-t border-gray-100 pt-3">
              <p class="text-xs text-text-secondary mb-2">🛒 {{ $t('scan.buyLinks') }}</p>
              <div class="space-y-2">
                <a
                  v-for="link in parseLinks(data.external_links)"
                  :key="link.url"
                  :href="link.url"
                  target="_blank"
                  rel="noopener"
                  class="flex items-center gap-2 px-3 py-2 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors"
                >
                  <img :src="`/uploads/platform-logos/${detectPlatform(link.url).logo}`" class="h-5" :alt="detectPlatform(link.url).name" />
                  <span class="text-xs font-medium text-text-primary">{{ detectPlatform(link.url).name }}</span>
                  <span class="text-xs text-text-secondary ml-auto">{{ $t('scan.clickToBuy') }}</span>
                  <span class="material-symbols-outlined text-[14px] text-text-secondary">arrow_forward</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Warranty card -->
        <div v-if="data.warranty_end" class="bg-white rounded-xl shadow-sm p-4">
          <h3 class="font-medium text-text-primary text-sm mb-3 flex items-center gap-2">
            <span class="text-base">🛡️</span> {{ $t('scan.warrantyInfo') }}
          </h3>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs text-text-secondary">{{ $t('qrcode.warrantyEnd') }}</p>
              <p class="text-sm font-medium">{{ data.warranty_end.slice(0, 10) }}</p>
            </div>
            <span :class="['px-3 py-1 rounded-full text-xs font-medium', warrantyStatus(data.warranty_end) === 'valid' ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger']">
              {{ warrantyStatus(data.warranty_end) === 'valid' ? $t('qrcode.warrantyValid') : $t('qrcode.warrantyExpired') }}
            </span>
          </div>
        </div>

        <!-- Join service group card (only when sold and has group QR) -->
        <div v-if="data.group_qr_url" class="bg-white rounded-xl shadow-sm p-4">
          <h3 class="font-medium text-text-primary text-sm mb-3 flex items-center gap-2">
            <span class="text-base">👥</span> {{ $t('scan.joinServiceGroup') }}
            <span v-if="data.group_qr_type" class="ml-auto text-xs px-2 py-0.5 rounded-full bg-primary/10 text-primary font-medium">{{ groupPlatformLabel(data.group_qr_type) }}</span>
          </h3>
          <p class="text-xs text-text-secondary mb-3">{{ $t('scan.joinServiceGroupHint') }}</p>
          <div class="flex justify-center">
            <img :src="data.group_qr_url" :alt="$t('scan.joinServiceGroup')" class="w-48 h-48 rounded-lg border border-gray-100" />
          </div>
          <p class="text-center text-xs text-text-secondary mt-2">
            {{ data.group_qr_type ? $t('scan.longPressToJoinVia', { platform: groupPlatformLabel(data.group_qr_type) }) : $t('scan.longPressToJoin') }}
          </p>
        </div>

        <!-- After sale contact (only for logged-in users) -->
        <div v-if="data.after_sale_contact" class="bg-white rounded-xl shadow-sm p-4">
          <h3 class="font-medium text-text-primary text-sm mb-2 flex items-center gap-2">
            <span class="text-base">📞</span> {{ $t('qrcode.afterSaleContact') }}
          </h3>
          <!-- Show contact only if logged in -->
          <div v-if="h5User">
            <p class="text-sm text-text-primary">{{ data.after_sale_contact }}</p>
          </div>
          <!-- Prompt to login if not logged in -->
          <div v-else class="space-y-2">
            <p class="text-xs text-text-secondary">{{ $t('scan.loginRequired') }}</p>
            <button
              @click="goLogin"
              class="w-full py-2 rounded-lg text-sm font-medium bg-primary hover:bg-primary-hover text-white transition-colors"
            >
              {{ $t('scan.loginToView') }}
            </button>
          </div>
        </div>

        <!-- Repair history -->
        <div v-if="data.repairRecords?.length > 0" class="bg-white rounded-xl shadow-sm p-4">
          <h3 class="font-medium text-text-primary text-sm mb-3 flex items-center gap-2">
            <span class="text-base">🔧</span> {{ $t('qrcode.repairRecords') }}
          </h3>
          <div v-for="(r, i) in data.repairRecords" :key="i" class="border-b border-gray-100 pb-3 mb-3 last:border-0 last:mb-0 last:pb-0">
            <div class="flex items-center justify-between mb-1">
              <p class="text-xs font-medium text-text-primary">{{ r.repair_person || $t('common.unknown') }}</p>
              <p class="text-xs text-text-secondary">{{ r.repaired_at?.slice(0, 10) }}</p>
            </div>
            <p v-if="r.issue" class="text-xs text-text-secondary">{{ $t('scan.fault') }}: {{ r.issue }}</p>
            <p v-if="r.solution" class="text-xs text-text-secondary">{{ $t('scan.solution') }}: {{ r.solution }}</p>
          </div>
        </div>

        <!-- My after-sale records (for buyers) -->
        <div v-if="isBuyer && myAftersaleRecords.length > 0" class="bg-white rounded-xl shadow-sm p-4">
          <h3 class="font-medium text-text-primary text-sm mb-3 flex items-center gap-2">
            <span class="text-base">📋</span> {{ $t('scan.myAftersale') }}
          </h3>
          <div v-for="(record, i) in myAftersaleRecords" :key="i" class="border-b border-gray-100 pb-3 mb-3 last:border-0 last:mb-0 last:pb-0">
            <div class="flex items-center justify-between mb-2">
              <span :class="['text-xs font-medium', statusColor(record.status)]">
                {{ statusLabel(record.status) }}
              </span>
              <span class="text-xs text-text-secondary">{{ record.created_at?.slice(0, 10) }}</span>
            </div>
            <p class="text-xs text-text-secondary mb-2">{{ record.issue }}</p>
            <div v-if="record.handler_name || record.handler_phone" class="bg-gray-50 rounded px-2 py-1.5">
              <p class="text-xs text-text-secondary">{{ $t('scan.customerService') }}:</p>
              <p class="text-xs font-medium text-text-primary">
                {{ record.handler_name || record.assigned_to_name || '-' }}
                <span v-if="record.handler_phone" class="ml-2">{{ record.handler_phone }}</span>
              </p>
            </div>
          </div>
        </div>

        <!-- After sale submitted success -->
        <div v-if="submitted" class="bg-success/10 border border-success/20 rounded-xl p-4 text-center">
          <p class="text-success font-medium text-sm">✅ {{ $t('scan.afterSaleSubmitted') }}</p>
        </div>

        <!-- Apply after sale -->
        <div v-if="!submitted" class="bg-white rounded-xl shadow-sm p-4">
          <div class="flex items-center justify-between">
            <h3 class="font-medium text-text-primary text-sm flex items-center gap-2">
              <span class="text-base">🎫</span> {{ $t('scan.applyAfterSale') }}
            </h3>
            <button v-if="h5User" @click="showAfterSale = !showAfterSale" class="text-xs text-primary">
              {{ showAfterSale ? $t('common.collapse') : $t('common.expand') }}
            </button>
          </div>

          <!-- Not logged in: prompt to login -->
          <div v-if="!h5User" class="mt-3">
            <p class="text-xs text-text-secondary mb-3">{{ $t('scan.loginRequired') }}</p>
            <button
              @click="goLogin"
              class="w-full py-2.5 rounded-lg text-sm font-medium bg-primary hover:bg-primary-hover text-white transition-colors"
            >
              {{ $t('scan.loginToApply') }}
            </button>
          </div>

          <!-- Logged in: show after-sale form -->
          <div v-else-if="showAfterSale" class="mt-3 space-y-3">
            <div>
              <label class="text-xs text-text-secondary mb-1 block">{{ $t('scan.nameOrContact') }}</label>
              <input
                v-model="afterSaleForm.buyer"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none"
                :placeholder="$t('scan.namePlaceholder')"
              />
            </div>
            <div>
              <label class="text-xs text-text-secondary mb-1 block">{{ $t('qrcode.issueDesc') }}</label>
              <textarea
                v-model="afterSaleForm.issue"
                rows="3"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary focus:outline-none resize-none"
                :placeholder="$t('scan.issuePlaceholder')"
              ></textarea>
            </div>
            <div>
              <label class="text-xs text-text-secondary mb-1 block">{{ $t('scan.uploadImages') }}</label>
              <ImageUploader v-model="afterSaleImages" :max-files="5" />
            </div>
            <p v-if="afterSaleError" class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ afterSaleError }}</p>
            <button
              @click="submitAfterSale"
              :disabled="submitting || !afterSaleForm.buyer || !afterSaleForm.issue"
              :class="['w-full py-2.5 rounded-lg text-sm font-medium transition-colors', (afterSaleForm.buyer && afterSaleForm.issue) ? 'bg-primary hover:bg-primary-hover text-white' : 'bg-gray-100 text-text-secondary cursor-not-allowed']"
            >
              {{ submitting ? $t('common.submitting') : $t('scan.submitAfterSale') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Customer Service Chat -->
    <H5ServiceChat v-if="data" :qrcode-id="data.id" :h5-token="h5Token" :h5-user="h5User" />
  </div>
</template>
