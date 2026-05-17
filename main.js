import { createApp, nextTick } from 'vue'
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'
import App from './App.vue'
import router from './router'
import i18n from './i18n'
import 'material-symbols/outlined.css'
import 'element-plus/dist/index.css'
import './style.css'

// 创建应用实例
const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(i18n)
app.use(ElementPlus)

// 全局 Vue 错误处理：静默处理，不弹 alert
app.config.errorHandler = (err, instance, info) => {
  console.error('Vue error:', err, info)
}

// chunk 加载失败自动刷新（只刷新一次，防循环）
router.onError((error, to) => {
  const isChunkError = /Loading chunk|Failed to fetch|Importing a module|dynamically imported module/i.test(error.message)
  if (isChunkError) {
    const lastReload = sessionStorage.getItem('chunk_reload')
    if (!lastReload || Date.now() - Number(lastReload) > 10000) {
      sessionStorage.setItem('chunk_reload', String(Date.now()))
      window.location.assign(to.fullPath)
      return
    }
  }
  console.error('Router error:', error)
})

// 兜底：捕获未处理的动态 import 失败
window.addEventListener('unhandledrejection', (event) => {
  const msg = event.reason?.message || ''
  if (/Loading chunk|Failed to fetch|Importing a module|dynamically imported module/i.test(msg)) {
    event.preventDefault()
    const lastReload = sessionStorage.getItem('chunk_reload')
    if (!lastReload || Date.now() - Number(lastReload) > 10000) {
      sessionStorage.setItem('chunk_reload', String(Date.now()))
      window.location.reload()
    }
  }
})

// 显示应用并移除加载动画
function showApp() {
  const appEl = document.getElementById('app')
  const loading = document.getElementById('app-loading')

  if (appEl) {
    appEl.classList.add('ready')
  }

  if (loading) {
    loading.classList.add('fade-out')
    setTimeout(() => loading.remove(), 400)
  }
}

// 等路由准备好（所有异步组件加载完成）再挂载，防止 i18n key 暴露
router.isReady().then(() => {
  app.mount('#app')

  // 等待 Vue 完成首次渲染 + i18n 翻译 + CSS 应用
  nextTick(() => {
    // 等待 2 帧确保浏览器完成绘制和 i18n 翻译
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        showApp()
      })
    })
  })
}).catch(() => {
  app.mount('#app')
  showApp()
})

// 兜底：最多 5 秒后强制移除 loading overlay
setTimeout(() => {
  const appEl = document.getElementById('app')
  const loading = document.getElementById('app-loading')

  if (appEl) {
    appEl.classList.add('ready')
  }

  if (loading) {
    loading.classList.add('fade-out')
    setTimeout(() => loading.remove(), 400)
  }
}, 5000)
