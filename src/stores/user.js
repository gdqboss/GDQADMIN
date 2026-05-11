import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUserStore = defineStore('user', () => {
  const token = ref(localStorage.getItem('caimeite_token') || '')
  const userInfo = ref(JSON.parse(localStorage.getItem('caimeite_user') || '{}'))
  const userName = ref(userInfo.value?.name || 'Admin')
  const userRole = ref(userInfo.value?.role || 'admin')

  const isLoggedIn = () => !!token.value

  const logout = () => {
    token.value = ''
    userInfo.value = {}
    userName.value = 'Admin'
    userRole.value = 'admin'
    localStorage.removeItem('caimeite_token')
    localStorage.removeItem('caimeite_user')
  }

  return { token, userInfo, userName, userRole, isLoggedIn, logout }
})
