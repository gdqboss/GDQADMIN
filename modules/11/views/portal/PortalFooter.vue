<template>
  <footer class="bg-mo text-xuanzhi mt-16" style="background-color:#1a1a1a;">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <div class="mb-8 pb-8 border-b border-gold/30">
        <img src="/logo-wide.png" alt="澳門中醫藥學會" class="h-14 object-contain" />
      </div>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
        <!-- 关于 -->
        <div>
          <h3 class="text-white font-bold mb-4">{{ $t('portal.footer.about') }}</h3>
          <p class="text-sm leading-relaxed">{{ footerIntro }}</p>
        </div>

        <!-- 联系 -->
        <div>
          <h3 class="text-white font-bold mb-4">{{ $t('portal.footer.contact') }}</h3>
          <ul class="space-y-2 text-sm">
            <li v-if="contact.address" class="flex gap-2">
              <span class="text-amber-500">📍</span>
              <span>{{ contact.address }}</span>
            </li>
            <li v-if="contact.phone" class="flex gap-2">
              <span class="text-amber-500">📞</span>
              <span>{{ contact.phone }}</span>
            </li>
            <li v-if="contact.email" class="flex gap-2">
              <span class="text-amber-500">✉</span>
              <span>{{ contact.email }}</span>
            </li>
          </ul>
        </div>

        <!-- 快速链接 -->
        <div>
          <h3 class="text-white font-bold mb-4">{{ $t('portal.nav.home') }}</h3>
          <ul class="space-y-2 text-sm">
            <li><router-link to="/portal/about" class="hover:text-amber-500">{{ $t('portal.nav.about') }}</router-link></li>
            <li><router-link to="/portal/news" class="hover:text-amber-500">{{ $t('portal.nav.news') }}</router-link></li>
            <li><router-link to="/portal/activities" class="hover:text-amber-500">{{ $t('portal.nav.activities') }}</router-link></li>
            <li><router-link to="/portal/members" class="hover:text-amber-500">{{ $t('portal.nav.members') }}</router-link></li>
          </ul>
        </div>

        <!-- 资源 -->
        <div>
          <h3 class="text-white font-bold mb-4">{{ $t('portal.nav.downloads') }}</h3>
          <ul class="space-y-2 text-sm">
            <li><router-link to="/portal/journals" class="hover:text-amber-500">{{ $t('portal.nav.journals') }}</router-link></li>
            <li><router-link to="/portal/academic" class="hover:text-amber-500">{{ $t('portal.nav.academic') }}</router-link></li>
            <li><router-link to="/portal/org" class="hover:text-amber-500">{{ $t('portal.nav.org') }}</router-link></li>
            <li><router-link to="/portal/contact" class="hover:text-amber-500">{{ $t('portal.nav.contact') }}</router-link></li>
          </ul>
        </div>
      </div>

      <div class="border-t border-gray-700 mt-8 pt-6 text-center text-sm">
        {{ $t('portal.footer.copyright') }} © {{ year }} {{ siteName }} · {{ $t('portal.footer.icp') }} 12345678
      </div>
    </div>
  </footer>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api.js'

const year = new Date().getFullYear()
const { locale } = useI18n()
const siteName = ref('')
const footerIntro = '致力推動行業創新發展，服務廣大會員，促進交流合作。'
const contact = ref({ address: '', phone: '', email: '' })

let cachedInfo = null
function applyFromCache() {
  if (!cachedInfo) return
  const lc = locale.value
  const map = {
    'zh': cachedInfo.name_zh,
    'zh-TW': cachedInfo.name_zh_tw || cachedInfo.name_zh,
    'en': cachedInfo.name_en || cachedInfo.name_zh,
  }
  if (map[lc]) siteName.value = map[lc]
}
watch(locale, applyFromCache)

onMounted(async () => {
  try {
    const res = await api.get('/association/info')
    if (res.code === 0 && res.data) {
      cachedInfo = res.data
      applyFromCache()
      contact.value.address = res.data.address || ''
      contact.value.phone = res.data.phone || ''
      contact.value.email = res.data.email || ''
    }
  } catch (e) {}
})
</script>