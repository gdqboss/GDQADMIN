<template>
  <div class="scan-page">
    <!-- 顶部导航 -->
    <div class="scan-header">
      <span class="logo-text">GDQ</span>
      <span class="page-title">{{ $t('scan.title') }}</span>
      <button class="lang-btn" @click="toggleLang">
        {{ locale === 'zh' ? 'EN' : '中文' }}
      </button>
      <button v-if="h5Token" class="profile-btn" @click="$router.push('/h5/profile')">
        <span class="material-symbols-outlined">person</span>
        <span>{{ h5User?.name || $t('scan.mine') }}</span>
      </button>
    </div>

    <!-- 加载中 -->
    <div v-if="loading" class="state-container">
      <div class="spinner"></div>
      <p>{{ $t('scan.loading') }}</p>
    </div>

    <!-- 404 -->
    <div v-else-if="errorMsg" class="state-container">
      <span class="error-icon">❌</span>
      <p class="error-text">{{ errorMsg }}</p>
      <p class="error-code">{{ route.params.code }}</p>
    </div>

    <!-- 扫描结果 -->
    <div v-else-if="product" class="content">
      <!-- 产品卡片 -->
      <div class="product-card">
        <div class="product-header">
          <img :src="product.image_url" class="product-image" :alt="product.code" />
          <div class="product-info">
            <p class="product-name">{{ product.product_name || $t('common.unbound') }}</p>
            <p class="product-code">{{ product.code }}</p>
          </div>
          <button class="share-btn" @click="share">
            <span class="material-symbols-outlined">share</span>
            {{ $t('scan.share') }}
          </button>
        </div>

        <div class="product-details">
          <!-- SKU/规格/分类 -->
          <div v-if="product.sku || product.spec || product.category" class="detail-grid">
            <div v-if="product.sku">
              <p class="detail-label">SKU</p>
              <p class="detail-value font-mono">{{ product.sku }}</p>
            </div>
            <div v-if="product.spec">
              <p class="detail-label">{{ $t('product.spec') }}</p>
              <p class="detail-value">{{ product.spec }}</p>
            </div>
            <div v-if="product.category">
              <p class="detail-label">{{ $t('common.category') }}</p>
              <p class="detail-value">{{ product.category }}</p>
            </div>
          </div>

          <!-- 购买链接 -->
          <div v-if="externalLinks.length" class="buy-links">
            <p class="section-title">🛒 {{ $t('scan.buyLinks') }}</p>
            <div class="links-list">
              <a v-for="link in externalLinks" :key="link.url" :href="link.url" target="_blank" rel="noopener" class="buy-link-item">
                <img :src="`/uploads/platform-logos/${getPlatform(link.url).logo}`" class="platform-logo" :alt="getPlatform(link.url).name" />
                <span class="platform-name">{{ getPlatform(link.url).name }}</span>
                <span class="click-hint">{{ $t('scan.clickToBuy') }}</span>
                <span class="material-symbols-outlined arrow-icon">arrow_forward</span>
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- 保修信息 -->
      <div v-if="product.warranty_end" class="info-card">
        <h3 class="card-title">🛡️ {{ $t('scan.warrantyInfo') }}</h3>
        <div class="warranty-row">
          <div>
            <p class="detail-label">{{ $t('qrcode.warrantyEnd') }}</p>
            <p class="detail-value">{{ product.warranty_end.slice(0, 10) }}</p>
          </div>
          <span :class="['warranty-badge', warrantyStatus === 'valid' ? 'valid' : 'expired']">
            {{ warrantyStatus === 'valid' ? $t('qrcode.warrantyValid') : $t('qrcode.warrantyExpired') }}
          </span>
        </div>
      </div>

      <!-- 加入服务群 -->
      <div v-if="product.group_qr_url" class="info-card">
        <h3 class="card-title">
          👥 {{ $t('scan.joinServiceGroup') }}
          <span v-if="product.group_qr_type" class="platform-badge">{{ getGroupType(product.group_qr_type) }}</span>
        </h3>
        <p class="hint-text">{{ $t('scan.joinServiceGroupHint') }}</p>
        <div class="qr-center">
          <img :src="product.group_qr_url" :alt="$t('scan.joinServiceGroup')" class="group-qr" />
        </div>
        <p class="qr-hint">
          {{ product.group_qr_type ? $t('scan.longPressToJoinVia', { platform: getGroupType(product.group_qr_type) }) : $t('scan.longPressToJoin') }}
        </p>
      </div>

      <!-- 售后联系方式 -->
      <div v-if="product.after_sale_contact" class="info-card">
        <h3 class="card-title">📞 {{ $t('qrcode.afterSaleContact') }}</h3>
        <div v-if="h5User">
          <p class="contact-text">{{ product.after_sale_contact }}</p>
        </div>
        <div v-else>
          <p class="hint-text">{{ $t('scan.loginRequired') }}</p>
          <button class="primary-btn" @click="goLogin">{{ $t('scan.loginToView') }}</button>
        </div>
      </div>

      <!-- 维修记录 -->
      <div v-if="product.repairRecords?.length" class="info-card">
        <h3 class="card-title">🔧 {{ $t('qrcode.repairRecords') }}</h3>
        <div v-for="(record, idx) in product.repairRecords" :key="idx" class="repair-record">
          <div class="record-header">
            <p class="record-person">{{ record.repair_person || $t('common.unknown') }}</p>
            <p class="record-date">{{ record.repaired_at?.slice(0, 10) }}</p>
          </div>
          <p v-if="record.issue" class="record-issue">{{ $t('scan.fault') }}: {{ record.issue }}</p>
          <p v-if="record.solution" class="record-solution">{{ $t('scan.solution') }}: {{ record.solution }}</p>
        </div>
      </div>

      <!-- 我的售后记录 -->
      <div v-if="isBuyer && aftersaleRecords.length" class="info-card">
        <h3 class="card-title">📋 {{ $t('scan.myAftersale') }}</h3>
        <div v-for="(record, idx) in aftersaleRecords" :key="idx" class="aftersale-record">
          <div class="record-header">
            <span :class="['status-badge', record.status]">{{ getStatusText(record.status) }}</span>
            <span class="record-date">{{ record.created_at?.slice(0, 10) }}</span>
          </div>
          <p class="aftersale-issue">{{ record.issue }}</p>
          <div v-if="record.handler_name || record.handler_phone" class="handler-info">
            <p class="handler-label">{{ $t('scan.customerService') }}:</p>
            <p class="handler-name">{{ record.handler_name || record.assigned_to_name || '-' }} <span v-if="record.handler_phone">{{ record.handler_phone }}</span></p>
          </div>
        </div>
      </div>

      <!-- 提交成功 -->
      <div v-if="aftersaleSubmitted" class="success-card">
        <p>✅ {{ $t('scan.afterSaleSubmitted') }}</p>
      </div>

      <!-- 申请售后 -->
      <div v-if="!aftersaleSubmitted" class="info-card">
        <div class="card-title-row">
          <h3 class="card-title">🎫 {{ $t('scan.applyAfterSale') }}</h3>
          <button v-if="h5User" class="toggle-btn" @click="showAftersaleForm = !showAftersaleForm">
            {{ showAftersaleForm ? $t('common.collapse') : $t('common.expand') }}
          </button>
        </div>

        <div v-if="h5User && showAftersaleForm" class="aftersale-form">
          <div class="form-group">
            <label>{{ $t('scan.nameOrContact') }}</label>
            <input v-model="aftersaleForm.buyer" type="text" :placeholder="$t('scan.namePlaceholder')" />
          </div>
          <div class="form-group">
            <label>{{ $t('qrcode.issueDesc') }}</label>
            <textarea v-model="aftersaleForm.issue" rows="3" :placeholder="$t('scan.issuePlaceholder')"></textarea>
          </div>
          <div class="form-group">
            <label>{{ $t('scan.uploadImages') }}</label>
            <image-uploader v-model="aftersaleForm.images" :max-files="5" />
          </div>
          <p v-if="formError" class="form-error">{{ formError }}</p>
          <button 
            class="submit-btn" 
            :disabled="submitting || !aftersaleForm.buyer || !aftersaleForm.issue"
            @click="submitAftersale"
          >
            {{ submitting ? $t('common.submitting') : $t('scan.submitAfterSale') }}
          </button>
        </div>

        <div v-else-if="!h5User">
          <p class="hint-text">{{ $t('scan.loginRequired') }}</p>
          <button class="primary-btn" @click="goLogin">{{ $t('scan.loginToApply') }}</button>
        </div>
      </div>

      <!-- 客服聊天悬浮按钮 -->
      <service-chat v-if="product" :qrcode-id="product.id" :h5-token="h5Token" :h5-user="h5User" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import ImageUploader from './components/ImageUploader.vue'
import ServiceChat from './components/ServiceChat.vue'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()

const loading = ref(true)
const errorMsg = ref('')
const product = ref(null)
const isBuyer = ref(false)
const aftersaleRecords = ref([])
const aftersaleSubmitted = ref(false)
const showAftersaleForm = ref(false)
const submitting = ref(false)
const formError = ref('')
const aftersaleForm = ref({ buyer: '', issue: '', images: [] })

const h5User = JSON.parse(localStorage.getItem('h5_user') || 'null')
const h5Token = localStorage.getItem('h5_token')

function toggleLang() {
  locale.value = locale.value === 'zh' ? 'en' : 'zh'
  localStorage.setItem('caimeite_locale', locale.value)
}

function goLogin() {
  router.push(`/h5/login?redirect=/scan/${route.params.code}`)
}

const warrantyStatus = computed(() => {
  if (!product.value?.warranty_end) return null
  return new Date(product.value.warranty_end) >= new Date() ? 'valid' : 'expired'
})

function parseExternalLinks(data) {
  if (!data) return []
  if (typeof data === 'string') {
    try { return JSON.parse(data) } catch { return [] }
  }
  return Array.isArray(data) ? data : []
}

const externalLinks = computed(() => parseExternalLinks(product.value?.external_links))

function getPlatform(url) {
  if (!url) return { name: t('scan.platformOther'), logo: 'other.svg', platform: 'other' }
  const u = url.toLowerCase()
  if (u.includes('jd.com') || u.includes('jd.hk')) return { name: t('scan.platformJd'), logo: 'jd.svg', platform: 'jd' }
  if (u.includes('tmall.com') || u.includes('tmall.hk')) return { name: t('scan.platformTmall'), logo: 'tmall.svg', platform: 'tmall' }
  if (u.includes('taobao.com')) return { name: t('scan.platformTaobao'), logo: 'taobao.svg', platform: 'taobao' }
  if (u.includes('pinduoduo.com') || u.includes('yangkeduo.com')) return { name: t('scan.platformPinduoduo'), logo: 'pdd.svg', platform: 'pdd' }
  if (u.includes('suning.com')) return { name: t('scan.platformSuning'), logo: 'suning.svg', platform: 'suning' }
  return { name: t('scan.platformOther'), logo: 'other.svg', platform: 'other' }
}

function getGroupType(type) {
  const map = {
    wechat_work: t('product.groupQrTypeWechatWork'),
    dingtalk: t('product.groupQrTypeDingtalk'),
    telegram: t('product.groupQrTypeTelegram'),
    whatsapp: t('product.groupQrTypeWhatsapp'),
    other: t('product.groupQrTypeOther')
  }
  return map[type] || ''
}

function getStatusText(status) {
  return { processing: t('scan.statusPending'), resolved: t('scan.statusResolved'), rejected: t('scan.statusRejected') }[status] || status
}

async function share() {
  const url = `${window.location.href}${h5User ? `?ref=${h5User.id}` : ''}`
  if (navigator.share) {
    await navigator.share({ title: product.value?.product_name, url })
  } else {
    await navigator.clipboard.writeText(url).catch(() => {})
    alert(t('scan.linkCopied'))
  }
}

async function submitAftersale() {
  if (!aftersaleForm.value.buyer || !aftersaleForm.value.issue) return
  formError.value = ''
  submitting.value = true
  try {
    const formData = new FormData()
    formData.append('qrcode_id', product.value.id)
    formData.append('issue', aftersaleForm.value.issue)
    aftersaleForm.value.images.forEach(f => formData.append('images', f))

    const res = await fetch('/api/h5/after-sale', {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${h5Token}` },
      body: formData
    })
    const data = await res.json()
    if (data.code === 0) {
      aftersaleSubmitted.value = true
      showAftersaleForm.value = false
    } else if (res.status === 401) {
      formError.value = t('scan.loginExpired')
      localStorage.removeItem('h5_token')
      localStorage.removeItem('h5_user')
      setTimeout(() => goLogin(), 1500)
    } else {
      formError.value = data.message || t('scan.submitFailed')
    }
  } catch {
    formError.value = t('scan.networkError')
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  try {
    const res = await fetch(`/api/scan/${route.params.code}`)
    const data = await res.json()
    if (data.code === 0) {
      product.value = data.data
      if (h5User?.phone) aftersaleForm.value.buyer = h5User.phone
      if (h5Token) {
        try {
          const aftersaleRes = await fetch(`/api/scan/${route.params.code}/my-aftersale`, {
            headers: { 'Authorization': `Bearer ${h5Token}` }
          })
          const aftersaleData = await aftersaleRes.json()
          if (aftersaleData.code === 0) {
            isBuyer.value = aftersaleData.data.isBuyer
            aftersaleRecords.value = aftersaleData.data.records
          }
        } catch (e) { console.error('Failed to fetch aftersale records', e) }
      }
    } else {
      errorMsg.value = t('scan.notFound')
    }
  } catch {
    errorMsg.value = t('scan.loadError')
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.scan-page { min-height: 100vh; background: #f5f5f5; }
.scan-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 12px 16px; display: flex; align-items: center; gap: 8px; }
.logo-text { font-weight: 700; font-size: 18px; }
.page-title { font-size: 14px; opacity: 0.9; }
.lang-btn, .profile-btn { margin-left: auto; font-size: 12px; border: 1px solid rgba(255,255,255,0.3); border-radius: 4px; padding: 4px 8px; background: transparent; color: #fff; cursor: pointer; display: flex; align-items: center; gap: 4px; }
.lang-btn:hover, .profile-btn:hover { border-color: #fff; }
.state-container { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 60vh; gap: 12px; color: #666; }
.spinner { width: 32px; height: 32px; border: 3px solid #667eea; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.error-icon { font-size: 48px; }
.error-text { font-size: 16px; font-weight: 500; color: #333; }
.error-code { font-size: 12px; color: #999; }
.content { max-width: 680px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
.product-card, .info-card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.product-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 16px; display: flex; align-items: center; gap: 12px; color: #fff; }
.product-image { width: 56px; height: 56px; border-radius: 8px; background: #fff; object-fit: cover; }
.product-info { flex: 1; }
.product-name { font-size: 16px; font-weight: 600; }
.product-code { font-size: 12px; opacity: 0.8; margin-top: 4px; }
.share-btn { font-size: 12px; color: rgba(255,255,255,0.8); background: transparent; border: 1px solid rgba(255,255,255,0.3); border-radius: 4px; padding: 6px 10px; cursor: pointer; display: flex; align-items: center; gap: 4px; }
.share-btn:hover { color: #fff; border-color: #fff; }
.product-details { padding: 16px; }
.detail-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.detail-label { font-size: 12px; color: #999; }
.detail-value { font-size: 14px; color: #333; margin-top: 2px; }
.font-mono { font-family: monospace; }
.section-title { font-size: 14px; color: #333; margin-bottom: 8px; }
.buy-links { margin-top: 12px; }
.links-list { display: flex; flex-direction: column; gap: 8px; }
.buy-link-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #f9f9f9; border-radius: 8px; text-decoration: none; color: inherit; transition: background 0.2s; }
.buy-link-item:hover { background: #f0f0f0; }
.platform-logo { width: 20px; height: 20px; }
.platform-name { flex: 1; font-size: 14px; }
.click-hint { font-size: 12px; color: #999; }
.arrow-icon { font-size: 16px; color: #999; }
.card-title { font-size: 16px; font-weight: 600; color: #333; display: flex; align-items: center; gap: 6px; }
.info-card { padding: 16px; }
.card-title-row { display: flex; align-items: center; justify-content: space-between; }
.platform-badge { font-size: 12px; background: rgba(102, 126, 234, 0.1); color: #667eea; padding: 2px 8px; border-radius: 12px; font-weight: normal; }
.hint-text { color: #999; font-size: 14px; margin-bottom: 12px; }
.qr-center { display: flex; justify-content: center; margin: 16px 0; }
.group-qr { width: 192px; height: 192px; border-radius: 8px; border: 1px solid #eee; }
.qr-hint { text-align: center; font-size: 12px; color: #999; }
.contact-text { font-size: 16px; color: #333; font-weight: 500; }
.warranty-row { display: flex; align-items: center; justify-content: space-between; margin-top: 8px; }
.warranty-badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; }
.warranty-badge.valid { background: rgba(76, 175, 80, 0.1); color: #4CAF50; }
.warranty-badge.expired { background: rgba(244, 67, 54, 0.1); color: #F44336; }
.repair-record, .aftersale-record { border-bottom: 1px solid #f0f0f0; padding-bottom: 12px; margin-bottom: 12px; }
.repair-record:last-child, .aftersale-record:last-child { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
.record-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.record-person, .record-date { font-size: 13px; color: #333; }
.record-date { color: #999; }
.record-issue, .record-solution { font-size: 13px; color: #666; margin-top: 2px; }
.success-card { background: rgba(76, 175, 80, 0.1); border: 1px solid rgba(76, 175, 80, 0.2); border-radius: 12px; padding: 16px; text-align: center; color: #4CAF50; font-weight: 500; }
.status-badge { font-size: 12px; font-weight: 500; padding: 2px 8px; border-radius: 4px; }
.status-badge.processing { color: #ff9800; background: rgba(255, 152, 0, 0.1); }
.status-badge.resolved { color: #4CAF50; background: rgba(76, 175, 80, 0.1); }
.status-badge.rejected { color: #f44336; background: rgba(244, 67, 54, 0.1); }
.aftersale-issue { font-size: 14px; color: #333; margin-top: 4px; }
.handler-info { display: flex; gap: 4px; margin-top: 4px; font-size: 12px; color: #666; }
.handler-label { color: #999; }
.handler-name { color: #333; }
.toggle-btn { font-size: 12px; color: #667eea; background: none; border: none; cursor: pointer; }
.primary-btn { width: 100%; padding: 10px; border-radius: 8px; background: #667eea; color: #fff; border: none; font-size: 14px; font-weight: 500; cursor: pointer; }
.primary-btn:hover { background: #5a6fd6; }
.aftersale-form { margin-top: 16px; display: flex; flex-direction: column; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-group label { font-size: 13px; color: #666; }
.form-group input, .form-group textarea { width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 10px 12px; font-size: 14px; outline: none; transition: border-color 0.2s; }
.form-group input:focus, .form-group textarea:focus { border-color: #667eea; }
.form-group textarea { resize: none; }
.form-error { color: #f44336; background: rgba(244, 67, 54, 0.1); padding: 8px 12px; border-radius: 8px; font-size: 13px; }
.submit-btn { width: 100%; padding: 12px; border-radius: 8px; background: #667eea; color: #fff; border: none; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.2s; }
.submit-btn:disabled { background: #ccc; cursor: not-allowed; }
.submit-btn:not(:disabled):hover { background: #5a6fd6; }
</style>
