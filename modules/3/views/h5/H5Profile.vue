<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const router = useRouter()

// 用户信息
const user = ref(null)
const loading = ref(true)

// 我的商品列表
const myProducts = ref([])
const productsLoading = ref(false)

// 修改密码弹窗
const showChangePassword = ref(false)
const oldPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const passwordError = ref('')
const passwordLoading = ref(false)

// 加载用户信息
async function loadUserInfo() {
  try {
    const token = localStorage.getItem('h5_token')
    if (!token) {
      router.replace('/h5/login')
      return
    }

    const res = await fetch('/api/h5/me', {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    const json = await res.json()
    if (json.code === 0) {
      user.value = json.data
      localStorage.setItem('h5_user', JSON.stringify(json.data))
    } else {
      router.replace('/h5/login')
    }
  } catch (err) {
    console.error('加载用户信息失败:', err)
  } finally {
    loading.value = false
  }
}

// 加载我的商品
async function loadMyProducts() {
  productsLoading.value = true
  try {
    const token = localStorage.getItem('h5_token')
    const res = await fetch('/api/h5/my-products', {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    const json = await res.json()
    if (json.code === 0) {
      myProducts.value = json.data || []
    }
  } catch (err) {
    console.error('加载商品失败:', err)
  } finally {
    productsLoading.value = false
  }
}

// 修改密码
async function changePassword() {
  passwordError.value = ''

  if (!oldPassword.value || !newPassword.value) {
    passwordError.value = t('h5.fillComplete')
    return
  }

  if (newPassword.value.length < 6) {
    passwordError.value = t('h5.newPasswordMin6')
    return
  }

  if (newPassword.value !== confirmPassword.value) {
    passwordError.value = t('h5.passwordMismatch')
    return
  }

  passwordLoading.value = true
  try {
    const token = localStorage.getItem('h5_token')
    const res = await fetch('/api/h5/change-password', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({
        old_password: oldPassword.value,
        new_password: newPassword.value
      })
    })
    const json = await res.json()
    if (json.code === 0) {
      alert(t('h5.passwordChanged'))
      showChangePassword.value = false
      oldPassword.value = ''
      newPassword.value = ''
      confirmPassword.value = ''
    } else {
      passwordError.value = json.message || t('h5.changeFailed')
    }
  } catch (err) {
    passwordError.value = t('h5.networkError')
  } finally {
    passwordLoading.value = false
  }
}

// 退出登录
function logout() {
  if (confirm(t('h5.confirmLogout'))) {
    localStorage.removeItem('h5_token')
    localStorage.removeItem('h5_user')
    router.replace('/h5/login')
  }
}

// 查看商品详情
function viewProduct(code) {
  router.push(`/scan/${code}`)
}

onMounted(() => {
  loadUserInfo()
  loadMyProducts()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- 加载中 -->
    <div v-if="loading" class="flex items-center justify-center min-h-screen">
      <div class="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- 主内容 -->
    <div v-else>
      <!-- 用户信息卡片 -->
      <div class="bg-gradient-to-br from-primary to-primary-hover text-white p-6 pb-8">
        <div class="flex items-center gap-4">
          <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center text-2xl font-bold">
            {{ user?.name?.charAt(0) || t('h5.defaultAvatar') }}
          </div>
          <div class="flex-1">
            <h2 class="text-xl font-bold">{{ user?.name || t('h5.noNameSet') }}</h2>
            <p class="text-sm text-white/80 mt-1">{{ user?.phone }}</p>
            <div class="flex items-center gap-2 mt-2">
              <span class="px-2 py-0.5 bg-white/20 rounded text-xs">{{ user?.role_label || user?.role }}</span>
              <span v-if="user?.level > 1" class="px-2 py-0.5 bg-white/20 rounded text-xs">LV{{ user?.level }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 功能菜单 -->
      <div class="px-4 -mt-4">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
          <button @click="showChangePassword = true" class="w-full flex items-center justify-between px-4 py-4 border-b border-gray-100 hover:bg-gray-50 transition">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-primary">lock</span>
              <span class="text-sm font-medium text-text-primary">{{ t('h5.changePassword') }}</span>
            </div>
            <span class="material-symbols-outlined text-gray-400">chevron_right</span>
          </button>

          <button @click="logout" class="w-full flex items-center justify-between px-4 py-4 hover:bg-gray-50 transition">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-danger">logout</span>
              <span class="text-sm font-medium text-danger">{{ t('h5.logout') }}</span>
            </div>
            <span class="material-symbols-outlined text-gray-400">chevron_right</span>
          </button>
        </div>
      </div>

      <!-- 我的商品 -->
      <div class="px-4 mt-6">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-base font-bold text-text-primary">{{ t('h5.myProducts') }}</h3>
          <span class="text-xs text-text-secondary">{{ t('h5.totalItems', { count: myProducts.length }) }}</span>
        </div>

        <!-- 加载中 -->
        <div v-if="productsLoading" class="flex justify-center py-8">
          <div class="w-6 h-6 border-3 border-primary border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- 商品列表 -->
        <div v-else-if="myProducts.length > 0" class="space-y-3">
          <div
            v-for="item in myProducts"
            :key="item.id"
            @click="viewProduct(item.code)"
            class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition cursor-pointer"
          >
            <div class="flex gap-3">
              <img
                v-if="item.image_main"
                :src="item.image_main"
                :alt="item.product_name"
                class="w-16 h-16 rounded-lg object-cover bg-gray-100"
              />
              <div v-else class="w-16 h-16 rounded-lg bg-gray-100 flex items-center justify-center">
                <span class="material-symbols-outlined text-gray-400">inventory_2</span>
              </div>

              <div class="flex-1 min-w-0">
                <h4 class="text-sm font-medium text-text-primary truncate">{{ item.product_name }}</h4>
                <p class="text-xs text-text-secondary mt-1">{{ item.code }}</p>
                <div class="flex items-center gap-2 mt-2">
                  <span :class="[
                    'px-2 py-0.5 rounded text-xs',
                    item.status === 'sold' ? 'bg-success/10 text-success' :
                    item.status === 'in_stock' ? 'bg-primary/10 text-primary' :
                    'bg-gray-100 text-gray-600'
                  ]">
                    {{ item.status === 'sold' ? t('h5.statusSold') : item.status === 'in_stock' ? t('h5.statusInStock') : t('h5.statusUnused') }}
                  </span>
                  <span v-if="item.purchase_date" class="text-xs text-text-secondary">
                    {{ new Date(item.purchase_date).toLocaleDateString() }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 空状态 -->
        <div v-else class="bg-white rounded-xl p-8 text-center">
          <span class="material-symbols-outlined text-gray-300 text-5xl">inventory_2</span>
          <p class="text-sm text-text-secondary mt-3">{{ t('h5.noProducts') }}</p>
          <p class="text-xs text-text-secondary mt-1">{{ t('h5.noProductsHint') }}</p>
        </div>
      </div>
    </div>

    <!-- 修改密码弹窗 -->
    <div v-if="showChangePassword" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4">
      <div class="w-full max-w-sm bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b border-gray-100">
          <h3 class="text-lg font-bold text-text-primary">{{ t('h5.changePassword') }}</h3>
          <button @click="showChangePassword = false" class="text-gray-400 hover:text-gray-600">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="p-6 space-y-4">
          <div>
            <label class="block text-xs text-text-secondary mb-1.5">{{ t('h5.currentPassword') }}</label>
            <input
              v-model="oldPassword"
              type="password"
              :placeholder="t('h5.enterCurrentPassword')"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 transition"
            />
          </div>

          <div>
            <label class="block text-xs text-text-secondary mb-1.5">{{ t('h5.newPassword') }}</label>
            <input
              v-model="newPassword"
              type="password"
              :placeholder="t('h5.atLeast6Chars')"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 transition"
            />
          </div>

          <div>
            <label class="block text-xs text-text-secondary mb-1.5">{{ t('h5.confirmNewPassword') }}</label>
            <input
              v-model="confirmPassword"
              type="password"
              :placeholder="t('h5.enterNewPasswordAgain')"
              @keyup.enter="changePassword"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 transition"
            />
          </div>

          <p v-if="passwordError" class="text-xs text-danger bg-danger/10 rounded-lg px-3 py-2">{{ passwordError }}</p>

          <button
            @click="changePassword"
            :disabled="passwordLoading"
            class="w-full bg-primary hover:bg-primary-hover disabled:opacity-60 text-white font-medium py-3 rounded-xl text-sm transition-colors"
          >
            <span v-if="passwordLoading" class="inline-flex items-center gap-2">
              <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              {{ t('h5.changing') }}
            </span>
            <span v-else>{{ t('h5.confirmChange') }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
