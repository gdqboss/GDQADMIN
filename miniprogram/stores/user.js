import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUserStore = defineStore('user', () => {
  const userInfo = ref(null)
  const token = ref('')

  function setUserInfo(info) {
    userInfo.value = info
  }

  function setToken(newToken) {
    token.value = newToken
    uni.setStorageSync('token', newToken)
  }

  function logout() {
    userInfo.value = null
    token.value = ''
    uni.removeStorageSync('token')
  }

  return {
    userInfo,
    token,
    setUserInfo,
    setToken,
    logout
  }
})
