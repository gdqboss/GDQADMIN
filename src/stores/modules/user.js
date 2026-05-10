import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

const TOKEN_KEY = 'user_token'

export const useUserStore = defineStore('user', () => {
  const token = ref(localStorage.getItem(TOKEN_KEY) || '')
  const userInfo = ref(null)
  const permissions = ref([])

  const isLoggedIn = computed(() => !!token.value)

  const hasPermission = computed(() => (permission) => {
    return permissions.value.includes(permission)
  })

  function login(credentials) {
    return new Promise((resolve, reject) => {
      // Simulate API call - replace with actual API request
      setTimeout(() => {
        if (credentials.username && credentials.password) {
          const mockToken = 'mock_token_' + Date.now()
          const mockUserInfo = {
            id: 1,
            username: credentials.username,
            nickname: credentials.username
          }
          const mockPermissions = ['view', 'edit', 'delete']

          token.value = mockToken
          userInfo.value = mockUserInfo
          permissions.value = mockPermissions
          localStorage.setItem(TOKEN_KEY, mockToken)

          resolve({ token: mockToken, userInfo: mockUserInfo })
        } else {
          reject(new Error('Invalid credentials'))
        }
      }, 500)
    })
  }

  function logout() {
    token.value = ''
    userInfo.value = null
    permissions.value = []
    localStorage.removeItem(TOKEN_KEY)
  }

  function fetchUserInfo() {
    return new Promise((resolve, reject) => {
      if (!token.value) {
        reject(new Error('No token found'))
        return
      }
      // Simulate API call - replace with actual API request
      setTimeout(() => {
        const mockUserInfo = {
          id: 1,
          username: 'user',
          nickname: 'User'
        }
        const mockPermissions = ['view', 'edit', 'delete']
        userInfo.value = mockUserInfo
        permissions.value = mockPermissions
        resolve(mockUserInfo)
      }, 300)
    })
  }

  return {
    token,
    userInfo,
    permissions,
    isLoggedIn,
    hasPermission,
    login,
    logout,
    fetchUserInfo
  }
})
